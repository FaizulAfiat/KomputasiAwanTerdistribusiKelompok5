<?php
// ==============================================================================
// Aplikasi Tanya Jawab (Q&A) - Kelompok 05
// Fitur: Jawaban & Moderasi (Form Jawab, Tampilan Jawaban, Moderasi Pertanyaan)
// Branch: fitur/jawaban
// ==============================================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Panggil koneksi database
require_once __DIR__ . '/lib/db.php';

// Panggil identitas jika tersedia di root
if (file_exists(__DIR__ . '/../identity.php')) {
    require_once __DIR__ . '/../identity.php';
} elseif (file_exists(__DIR__ . '/../../identity.php')) {
    require_once __DIR__ . '/../../identity.php';
}

if (!defined('MEMBER_ROUTE')) define('MEMBER_ROUTE', 'm1');
if (!defined('MEMBER_NAMA')) define('MEMBER_NAMA', 'Raynar Khalid Mahardika Sumarno');
if (!defined('MEMBER_NIM')) define('MEMBER_NIM', '103012430017');

$pdo = null;
$dbError = null;
try {
    $pdo = db();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$alert = null;
$alertType = 'info';

// ==============================================================================
// 1. PROSES POST: TAMBAH JAWABAN & MODERASI PERTANYAAN
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? 'jawab';
    $pertanyaanId = isset($_POST['pertanyaan_id']) ? (int)$_POST['pertanyaan_id'] : 0;
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($pertanyaanId <= 0) {
        $alert = 'ID Pertanyaan tidak valid.';
        $alertType = 'error';
    } else {
        // Cek keberadaan pertanyaan
        $stmtCek = $pdo->prepare("SELECT id, disembunyikan FROM pertanyaan WHERE id = :id");
        $stmtCek->execute([':id' => $pertanyaanId]);
        $pertanyaanData = $stmtCek->fetch();

        if (!$pertanyaanData) {
            $alert = 'Pertanyaan tidak ditemukan atau sudah dihapus.';
            $alertType = 'error';
        } else {
            // A. MODERASI PERTANYAAN (Toggle Sembunyikan / Tampilkan)
            if ($action === 'moderasi') {
                try {
                    $stmtMod = $pdo->prepare("UPDATE pertanyaan SET disembunyikan = NOT disembunyikan WHERE id = :id RETURNING disembunyikan");
                    $stmtMod->execute([':id' => $pertanyaanId]);
                    $statusBaru = $stmtMod->fetchColumn();

                    $msg = $statusBaru ? 'Pertanyaan berhasil disembunyikan (moderasi aktif).' : 'Pertanyaan berhasil dipulihkan (tampil kembali).';

                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'message' => $msg, 'disembunyikan' => (bool)$statusBaru]);
                        exit;
                    }

                    header("Location: jawab.php?id={$pertanyaanId}&msg=" . urlencode($msg) . "&type=success");
                    exit;
                } catch (Throwable $e) {
                    $alert = 'Gagal melakukan moderasi: ' . $e->getMessage();
                    $alertType = 'error';
                }
            }
            // B. TAMBAH JAWABAN (Form Jawab)
            elseif ($action === 'jawab') {
                $isi = isset($_POST['isi']) ? trim($_POST['isi']) : '';
                $routeId = isset($_POST['route_id']) && !empty(trim($_POST['route_id'])) ? trim($_POST['route_id']) : MEMBER_ROUTE;

                // Validasi sisi server (PHP)
                if ($isi === '') {
                    $alert = 'Isi jawaban tidak boleh kosong.';
                    $alertType = 'error';
                } elseif (mb_strlen($isi, 'UTF-8') < 3) {
                    $alert = 'Isi jawaban minimal 3 karakter.';
                    $alertType = 'error';
                } elseif (mb_strlen($isi, 'UTF-8') > 5000) {
                    $alert = 'Isi jawaban terlalu panjang (maksimal 5000 karakter).';
                    $alertType = 'error';
                } else {
                    try {
                        // Pastikan route_id valid pada tabel anggota
                        $stmtR = $pdo->prepare("SELECT route_id FROM anggota WHERE route_id = :route_id");
                        $stmtR->execute([':route_id' => $routeId]);
                        if (!$stmtR->fetch()) {
                            // Fallback jika route_id tidak ada
                            $routeId = MEMBER_ROUTE;
                        }

                        // Simpan jawaban dengan prepared statement (Aman dari SQL Injection)
                        $stmtIns = $pdo->prepare("
                            INSERT INTO jawaban (pertanyaan_id, route_id, isi, created_at)
                            VALUES (:pertanyaan_id, :route_id, :isi, CURRENT_TIMESTAMP)
                        ");
                        $stmtIns->execute([
                            ':pertanyaan_id' => $pertanyaanId,
                            ':route_id' => $routeId,
                            ':isi' => $isi
                        ]);

                        $msg = 'Jawaban berhasil dikirim dan tersimpan di database!';
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => true, 'message' => $msg]);
                            exit;
                        }

                        header("Location: jawab.php?id={$pertanyaanId}&msg=" . urlencode($msg) . "&type=success#jawaban-terbaru");
                        exit;
                    } catch (Throwable $e) {
                        $alert = 'Gagal menyimpan jawaban: ' . $e->getMessage();
                        $alertType = 'error';
                    }
                }
            }
        }
    }

    if ($isAjax && $alert) {
        header('Content-Type: application/json; charset=utf-8', true, 400);
        echo json_encode(['success' => false, 'error' => $alert]);
        exit;
    }
}

// Flash message dari redirect URL
if (isset($_GET['msg'])) {
    $alert = $_GET['msg'];
    $alertType = isset($_GET['type']) ? $_GET['type'] : 'success';
}

// ==============================================================================
// 2. QUERY DATA PERTANYAAN & JAWABAN
// ==============================================================================
$pertanyaanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pertanyaan = null;
$daftarJawaban = [];
$semuaPertanyaan = [];

if ($pdo) {
    if ($pertanyaanId > 0) {
        // Ambil detail pertanyaan beserta identitas penanya
        $stmtP = $pdo->prepare("
            SELECT p.*, a.nama AS nama_anggota, a.nim AS nim_anggota
            FROM pertanyaan p
            LEFT JOIN anggota a ON p.route_id = a.route_id
            WHERE p.id = :id
        ");
        $stmtP->execute([':id' => $pertanyaanId]);
        $pertanyaan = $stmtP->fetch();

        if ($pertanyaan) {
            // Ambil seluruh jawaban untuk pertanyaan ini beserta nama penjawab
            $stmtJ = $pdo->prepare("
                SELECT j.*, a.nama AS nama_penjawab, a.nim AS nim_penjawab
                FROM jawaban j
                LEFT JOIN anggota a ON j.route_id = a.route_id
                WHERE j.pertanyaan_id = :id
                ORDER BY j.created_at ASC
            ");
            $stmtJ->execute([':id' => $pertanyaanId]);
            $daftarJawaban = $stmtJ->fetchAll();
        }
    } else {
        // Jika tidak ada ID pertanyaan, tampilkan daftar pertanyaan untuk dipilih
        $stmtList = $pdo->query("
            SELECT p.*, a.nama AS nama_anggota, 
                   (SELECT COUNT(*) FROM jawaban WHERE pertanyaan_id = p.id) AS jumlah_jawaban
            FROM pertanyaan p
            LEFT JOIN anggota a ON p.route_id = a.route_id
            ORDER BY p.created_at DESC
            LIMIT 25
        ");
        if ($stmtList) {
            $semuaPertanyaan = $stmtList->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pertanyaan ? 'Jawab: ' . htmlspecialchars(mb_substr($pertanyaan['isi'], 0, 40)) . '...' : 'Fitur Jawaban & Moderasi' ?> - Kelompok 05</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-base: #090a0f;
            --bg-card: rgba(18, 20, 29, 0.88);
            --bg-card-subtle: #141724;
            --border-color: rgba(255, 255, 255, 0.09);
            --border-hover: rgba(99, 102, 241, 0.45);
            --accent-primary: #6366f1;
            --accent-cyan: #38bdf8;
            --accent-emerald: #10b981;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
            --font-mono: 'Space Grotesk', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-sans);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(99, 102, 241, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 20%, rgba(56, 189, 248, 0.08) 0%, transparent 40%);
        }

        /* Banner Server */
        .server-banner {
            background: linear-gradient(90deg, #07080d 0%, #111424 50%, #07080d 100%);
            border-bottom: 1px solid rgba(99, 102, 241, 0.25);
            padding: 10px 16px;
            font-size: 0.85rem;
            text-align: center;
        }

        .server-banner .served-by {
            color: #e2e8f0;
            font-weight: 500;
        }

        /* Header Nav */
        header {
            background: rgba(9, 10, 15, 0.8);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 900px;
            margin: auto;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .logo-group {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #ffffff;
            font-weight: 700;
        }

        .badge-app {
            background: linear-gradient(135deg, #4f46e5 0%, #0284c7 100%);
            color: #ffffff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-family: var(--font-mono);
        }

        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.88rem;
            margin-left: 14px;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #ffffff;
        }

        /* Container & Card */
        main {
            max-width: 900px;
            width: 100%;
            margin: 28px auto 60px auto;
            padding: 0 20px;
            flex: 1;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(12px);
        }

        /* Alert Notification */
        .alert-box {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #f87171;
        }

        /* Question Box Styling */
        .question-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 16px;
            margin-bottom: 16px;
        }

        .question-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.82rem;
            color: var(--text-dim);
            font-family: var(--font-mono);
        }

        .route-pill {
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .moderasi-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-hidden {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .question-text {
            font-size: 1.15rem;
            font-weight: 600;
            color: #ffffff;
            line-height: 1.6;
            margin-bottom: 16px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Moderation Button */
        .btn-moderasi {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: #e2e8f0;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-moderasi:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.5);
            color: #fca5a5;
        }

        /* Answers List (Tampilan Jawaban) */
        .section-heading {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .answer-card {
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-color);
            border-left: 3px solid var(--accent-primary);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 16px;
            transition: transform 0.2s;
        }

        .answer-card:hover {
            transform: translateX(3px);
            border-color: var(--border-hover);
        }

        .answer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.82rem;
            color: var(--text-dim);
            flex-wrap: wrap;
            gap: 6px;
        }

        .answerer-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #e2e8f0;
            font-weight: 600;
        }

        .answer-body {
            color: #cbd5e1;
            font-size: 0.95rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .empty-answers {
            text-align: center;
            padding: 36px 20px;
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            border: 1px dashed var(--border-color);
        }

        /* Form Jawab */
        .form-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            background: #090a0f;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.92rem;
            color: #ffffff;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .form-meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-dim);
            margin-top: 6px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Question selection list styling */
        .q-list-item {
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 12px;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.2s;
        }

        .q-list-item:hover {
            border-color: var(--border-hover);
            transform: translateY(-2px);
            background: rgba(30, 34, 52, 0.8);
        }

        .q-list-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 8px;
        }

        footer {
            border-top: 1px solid var(--border-color);
            padding: 24px;
            text-align: center;
            font-size: 0.82rem;
            color: var(--text-dim);
            background: #06070a;
        }
    </style>
</head>
<body>

    <!-- 1. SERVED BY BANNER -->
    <div class="server-banner">
        <?php 
        if (file_exists(__DIR__ . '/partials/served_by.php')) {
            include __DIR__ . '/partials/served_by.php';
        } else {
            echo "Anda dilayani oleh laman " . htmlspecialchars(MEMBER_NAMA, ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars(MEMBER_NIM, ENT_QUOTES, 'UTF-8') . ")";
        }
        ?>
    </div>

    <!-- 2. HEADER -->
    <header>
        <div class="header-container">
            <a href="jawab.php" class="logo-group">
                <span class="badge-app">Q&A Kelompok 05</span>
                <span>Fitur Jawaban & Moderasi</span>
            </a>
            <div class="nav-links">
                <a href="../index.php">&larr; Kembali ke Personal Page</a>
                <?php if (file_exists(__DIR__ . '/index.php')): ?>
                    <a href="index.php">Daftar Pertanyaan (app/)</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- Notifikasi Pesan -->
        <?php if ($alert): ?>
            <div class="alert-box <?= $alertType === 'success' ? 'alert-success' : 'alert-error' ?>" role="alert">
                <span><?= htmlspecialchars($alert, ENT_QUOTES, 'UTF-8') ?></span>
                <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:inherit; cursor:pointer; font-size:1.1rem;">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($dbError): ?>
            <div class="alert-box alert-error">
                <strong>Koneksi Database Bermasalah:</strong> <?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($pertanyaan): ?>
            <!-- ===============================================================
                 A. DETAIL PERTANYAAN & MODERASI
                 =============================================================== -->
            <section class="card">
                <div class="question-header">
                    <div>
                        <div class="question-meta">
                            <span>Oleh: <strong style="color:#e2e8f0;"><?= htmlspecialchars($pertanyaan['nama_anggota'] ?: $pertanyaan['nama'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                            <span>&bull;</span>
                            <span class="route-pill">Node: <?= htmlspecialchars($pertanyaan['route_id'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span>&bull;</span>
                            <span><?= date('d M Y, H:i', strtotime($pertanyaan['created_at'])) ?> WIB</span>
                            <span>&bull;</span>
                            <span>Upvote: <strong><?= (int)$pertanyaan['upvote'] ?></strong></span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px;">
                        <!-- Status Moderasi -->
                        <?php if ($pertanyaan['disembunyikan']): ?>
                            <span class="moderasi-badge badge-hidden" title="Pertanyaan disembunyikan dari daftar publik">
                                &#128065; Disembunyikan
                            </span>
                        <?php else: ?>
                            <span class="moderasi-badge badge-active" title="Pertanyaan aktif dan publik">
                                &#9679; Publik
                            </span>
                        <?php endif; ?>

                        <!-- Tombol Moderasi (Toggle) -->
                        <form method="POST" action="jawab.php" onsubmit="return confirm('Apakah Anda yakin ingin <?= $pertanyaan['disembunyikan'] ? 'menampilkan kembali' : 'menyembunyikan' ?> pertanyaan ini?');" style="margin:0;">
                            <input type="hidden" name="action" value="moderasi">
                            <input type="hidden" name="pertanyaan_id" value="<?= (int)$pertanyaan['id'] ?>">
                            <button type="submit" class="btn-moderasi">
                                <?= $pertanyaan['disembunyikan'] ? '&#128065; Pulihkan' : '&#128584; Sembunyikan' ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="question-text">
                    <?= nl2br(htmlspecialchars($pertanyaan['isi'], ENT_QUOTES, 'UTF-8')) ?>
                </div>

                <?php if ($pertanyaan['disembunyikan']): ?>
                    <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; color: #fbbf24;">
                        <strong>Catatan Moderasi:</strong> Pertanyaan ini saat ini berstatus <em>disembunyikan</em>. Anda dapat memulihkannya kapan saja menggunakan tombol di atas.
                    </div>
                <?php endif; ?>
            </section>

            <!-- ===============================================================
                 B. TAMPILAN DAFTAR JAWABAN
                 =============================================================== -->
            <section class="card" id="jawaban-terbaru">
                <div class="section-heading">
                    <span>Daftar Jawaban (<?= count($daftarJawaban) ?>)</span>
                    <span style="font-size: 0.85rem; color: var(--text-dim); font-weight: normal;">Diurutkan dari terlama ke terbaru</span>
                </div>

                <?php if (empty($daftarJawaban)): ?>
                    <div class="empty-answers">
                        <div style="font-size: 2rem; margin-bottom: 8px;">&#128172;</div>
                        <h4 style="color: #f1f5f9; margin-bottom: 4px;">Belum Ada Jawaban</h4>
                        <p style="font-size: 0.85rem;">Pertanyaan ini belum dijawab. Tuliskan jawaban pertama Anda pada formulir di bawah ini.</p>
                    </div>
                <?php else: ?>
                    <div class="answers-container">
                        <?php foreach ($daftarJawaban as $index => $j): ?>
                            <article class="answer-card">
                                <div class="answer-header">
                                    <div class="answerer-info">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="#38bdf8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        <span><?= htmlspecialchars($j['nama_penjawab'] ?: 'Anggota ' . $j['route_id'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="route-pill">Node: <?= htmlspecialchars($j['route_id'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <span style="font-family: var(--font-mono);"><?= date('d M Y, H:i', strtotime($j['created_at'])) ?> WIB</span>
                                </div>
                                <div class="answer-body">
                                    <?= nl2br(htmlspecialchars($j['isi'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ===============================================================
                 C. FORM JAWAB
                 =============================================================== -->
            <section class="card">
                <h3 class="form-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="#818cf8"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Tuliskan Jawaban Anda
                </h3>
                <p class="form-desc">
                    Jawaban Anda akan dicatat dengan <code>route_id = '<?= htmlspecialchars(MEMBER_ROUTE, ENT_QUOTES, 'UTF-8') ?>'</code> dan disinkronkan ke dalam basis data bersama kelompok.
                </p>

                <form id="formJawab" method="POST" action="jawab.php" novalidate>
                    <input type="hidden" name="action" value="jawab">
                    <input type="hidden" name="pertanyaan_id" value="<?= (int)$pertanyaan['id'] ?>">
                    <input type="hidden" name="route_id" value="<?= htmlspecialchars(MEMBER_ROUTE, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label for="inputIsi">Isi Jawaban:</label>
                        <textarea 
                            name="isi" 
                            id="inputIsi" 
                            class="form-control" 
                            placeholder="Tuliskan jawaban yang jelas, informatif, dan membantu..." 
                            required 
                            minlength="3" 
                            maxlength="5000"></textarea>
                        <div class="form-meta-row">
                            <span id="validationMsg" style="color: #f87171; font-weight: 500;"></span>
                            <span id="charCounter">0 / 5000 karakter</span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-submit" id="btnKirim">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            Kirim Jawaban
                        </button>
                    </div>
                </form>
            </section>

        <?php else: ?>
            <!-- ===============================================================
                 D. JIKA TIDAK ADA ID: DAFTAR PERTANYAAN UNTUK DIJAWAB / DIMODERASI
                 =============================================================== -->
            <section class="card">
                <div class="section-heading">
                    <span>Pilih Pertanyaan untuk Dijawab atau Dimoderasi</span>
                    <span style="font-size: 0.85rem; color: var(--text-dim);"><?= count($semuaPertanyaan) ?> Pertanyaan Terdaftar</span>
                </div>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
                    Silakan klik salah satu pertanyaan di bawah untuk membuka form jawaban, melihat daftar tanggapan, atau melakukan moderasi.
                </p>

                <?php if (empty($semuaPertanyaan)): ?>
                    <div class="empty-answers">
                        <div style="font-size: 2.2rem; margin-bottom: 8px;">&#128221;</div>
                        <h4 style="color: #f1f5f9; margin-bottom: 4px;">Belum Ada Pertanyaan di Database</h4>
                        <p style="font-size: 0.85rem;">Belum ada data pertanyaan. Anda dapat menambahkan pertanyaan pengujian di bawah.</p>
                        <div style="margin-top: 16px;">
                            <a href="../index.php" class="btn-submit" style="text-decoration:none;">Kembali ke Personal Page</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach ($semuaPertanyaan as $p): ?>
                            <a href="jawab.php?id=<?= (int)$p['id'] ?>" class="q-list-item">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                    <div class="question-meta">
                                        <span>Dari: <strong style="color:#e2e8f0;"><?= htmlspecialchars($p['nama_anggota'] ?: $p['nama'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                                        <span>&bull;</span>
                                        <span class="route-pill">Node: <?= htmlspecialchars($p['route_id'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span>&bull;</span>
                                        <span><?= date('d M Y, H:i', strtotime($p['created_at'])) ?> WIB</span>
                                    </div>
                                    <?php if ($p['disembunyikan']): ?>
                                        <span class="moderasi-badge badge-hidden">&#128065; Disembunyikan</span>
                                    <?php else: ?>
                                        <span class="moderasi-badge badge-active">&#9679; Publik</span>
                                    <?php endif; ?>
                                </div>

                                <div class="q-list-title">
                                    <?= htmlspecialchars(mb_substr($p['isi'], 0, 140)) ?><?= mb_strlen($p['isi']) > 140 ? '...' : '' ?>
                                </div>

                                <div style="font-size: 0.82rem; color: var(--accent-cyan); display: flex; align-items: center; gap: 12px;">
                                    <span>&#128172; <?= (int)$p['jumlah_jawaban'] ?> Jawaban</span>
                                    <span>&bull;</span>
                                    <span>&#9650; <?= (int)$p['upvote'] ?> Upvote</span>
                                    <span>&bull;</span>
                                    <span style="color: #a5b4fc;">Buka Form Jawab & Moderasi &rarr;</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer>
        &copy; <?= date('Y') ?> <strong>Kelompok 05</strong> &bull; Komputasi Awan dan Terdistribusi &bull; Node <?= htmlspecialchars(MEMBER_ROUTE, ENT_QUOTES, 'UTF-8') ?>
    </footer>

    <!-- Client-side Validation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formJawab = document.getElementById('formJawab');
            const inputIsi = document.getElementById('inputIsi');
            const validationMsg = document.getElementById('validationMsg');
            const charCounter = document.getElementById('charCounter');
            const btnKirim = document.getElementById('btnKirim');

            if (inputIsi && charCounter) {
                inputIsi.addEventListener('input', function() {
                    const len = this.value.length;
                    charCounter.textContent = len + ' / 5000 karakter';

                    if (this.value.trim().length >= 3) {
                        validationMsg.textContent = '';
                        btnKirim.disabled = false;
                    }
                });
            }

            if (formJawab) {
                formJawab.addEventListener('submit', function(e) {
                    const val = inputIsi.value.trim();
                    if (!val) {
                        e.preventDefault();
                        validationMsg.textContent = 'Isi jawaban tidak boleh kosong!';
                        inputIsi.focus();
                        return false;
                    }

                    if (val.length < 3) {
                        e.preventDefault();
                        validationMsg.textContent = 'Jawaban terlalu singkat (minimal 3 karakter).';
                        inputIsi.focus();
                        return false;
                    }

                    btnKirim.disabled = true;
                    btnKirim.innerHTML = 'Mengirimkan...';
                });
            }
        });
    </script>
</body>
</html>
