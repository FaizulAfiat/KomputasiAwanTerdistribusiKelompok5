<?php
// ==============================================================================
// Aplikasi Tanya Jawab (Q&A) - Kelompok 05
// Fitur: Jawaban & Moderasi (Form Jawab, Tampilan Jawaban, Moderasi Pertanyaan)
// Branch: fitur/jawaban
// Design System: Stitch UI (Editorial Neo-Brutalist / Tactile Paper)
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

                    $msg = $statusBaru ? 'Pertanyaan berhasil disembunyikan (moderasi aktif).' : 'Pertanyaan berhasil dipulihkan (tampil kembali ke publik).';

                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'message' => $msg, 'disembunyikan' => (bool)$statusBaru]);
                        exit;
                    }

                    header("Location: jawab.php?id={$pertanyaanId}&msg=" . urlencode($msg) . "&type=" . ($statusBaru ? 'warning' : 'success'));
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

                        $msg = 'Jawaban berhasil dikirim dan tersimpan di database PostgreSQL!';
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => true, 'message' => $msg]);
                            exit;
                        }

                        header("Location: jawab.php?id={$pertanyaanId}&status=sukses&msg=" . urlencode($msg) . "&type=success#jawaban-terbaru");
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
$daftarAnggota = [];

if ($pdo) {
    // Ambil daftar anggota untuk opsi pemilihan route jika ingin simulasi
    try {
        $daftarAnggota = $pdo->query("SELECT route_id, nama, nim FROM anggota ORDER BY route_id ASC")->fetchAll();
    } catch (Throwable $e) {
        $daftarAnggota = [];
    }

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
            LIMIT 30
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
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta content="web_standard" name="shell-type"/>
    <title><?= $pertanyaan ? 'Jawab: ' . htmlspecialchars(mb_substr($pertanyaan['isi'], 0, 45)) . '...' : 'Fitur Jawaban & Moderasi' ?> - Kelompok 05</title>
    
    <!-- Google Fonts: Newsreader (Editorial Serif) & Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>

    <style>
        @layer base {
            html, body { margin: 0; padding: 0; }
            body { overscroll-behavior: none; }
            main > :first-child { margin-top: 0 !important; }
            main > :last-child { margin-bottom: 0 !important; }
        }
        /* Custom styled served-by banner inside stitch layout */
        .served-by {
            padding: 8px 16px;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #1c1b1b;
        }
    </style>

    <!-- Tailwind CSS with Stitch Design System Tokens -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "tertiary": "#665e43",
                        "surface-bright": "#fcf9f8",
                        "surface-container-high": "#eae7e7",
                        "on-tertiary": "#ffffff",
                        "surface-container-highest": "#e5e2e1",
                        "on-tertiary-fixed-variant": "#4e462d",
                        "on-secondary": "#ffffff",
                        "on-surface": "#1c1b1b",
                        "on-tertiary-container": "#4a4229",
                        "on-error": "#ffffff",
                        "surface-container-low": "#f6f3f2",
                        "on-secondary-fixed-variant": "#54433c",
                        "on-tertiary-fixed": "#211b06",
                        "on-surface-variant": "#55433c",
                        "background": "#fcf9f8",
                        "tertiary-container": "#baaf8f",
                        "surface": "#fcf9f8",
                        "secondary-container": "#f6ddd4",
                        "on-primary-fixed-variant": "#7b2e0e",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-container": "#762a0a",
                        "on-secondary-fixed": "#251913",
                        "secondary": "#6c5b53",
                        "inverse-primary": "#ffb59b",
                        "secondary-fixed-dim": "#d9c2b8",
                        "on-secondary-container": "#736159",
                        "on-background": "#1c1b1b",
                        "primary-fixed-dim": "#ffb59b",
                        "outline-variant": "#dcc1b8",
                        "inverse-surface": "#313030",
                        "error-container": "#ffdad6",
                        "tertiary-fixed": "#eee2bf",
                        "on-primary": "#ffffff",
                        "error": "#ba1a1a",
                        "primary": "#9a4523",
                        "outline": "#88726b",
                        "on-error-container": "#93000a",
                        "secondary-fixed": "#f6ddd4",
                        "on-primary-fixed": "#380d00",
                        "primary-fixed": "#ffdbcf",
                        "surface-tint": "#9a4523",
                        "surface-dim": "#dcd9d9",
                        "inverse-on-surface": "#f3f0ef",
                        "tertiary-fixed-dim": "#d2c6a5",
                        "surface-variant": "#e5e2e1",
                        "primary-container": "#ff936b",
                        "surface-container": "#f0eded"
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    spacing: {
                        "space-xs": "0.25rem",
                        "gutter-mobile": "1rem",
                        "space-lg": "1.5rem",
                        "space-sm": "0.5rem",
                        "space-xl": "2.5rem",
                        "space-md": "1rem",
                        "margin-mobile": "1.25rem",
                        "margin": "3rem",
                        "gutter": "1.5rem"
                    },
                    fontFamily: {
                        "display-xl-mobile": ["Newsreader", "serif"],
                        "label-sm": ["Plus Jakarta Sans", "sans-serif"],
                        "body-md": ["Plus Jakarta Sans", "sans-serif"],
                        "headline-lg": ["Newsreader", "serif"],
                        "headline-md": ["Newsreader", "serif"],
                        "headline-lg-mobile": ["Newsreader", "serif"],
                        "headline-sm": ["Newsreader", "serif"],
                        "body-lg": ["Plus Jakarta Sans", "sans-serif"],
                        "label-md": ["Plus Jakarta Sans", "sans-serif"],
                        "body-sm": ["Plus Jakarta Sans", "sans-serif"],
                        "display-xl": ["Newsreader", "serif"],
                        "label-lg": ["Plus Jakarta Sans", "sans-serif"]
                    },
                    fontSize: {
                        "display-xl-mobile": ["38px", { lineHeight: "44px", letterSpacing: "-0.01em", fontWeight: "500" }],
                        "label-sm": ["11px", { lineHeight: "14px", letterSpacing: "0.04em", fontWeight: "700" }],
                        "body-md": ["15px", { lineHeight: "24px", fontWeight: "400" }],
                        "headline-lg": ["40px", { lineHeight: "48px", letterSpacing: "-0.015em", fontWeight: "600" }],
                        "headline-md": ["28px", { lineHeight: "36px", fontWeight: "600" }],
                        "headline-lg-mobile": ["28px", { lineHeight: "34px", letterSpacing: "-0.01em", fontWeight: "600" }],
                        "headline-sm": ["22px", { lineHeight: "30px", fontWeight: "500" }],
                        "body-lg": ["18px", { lineHeight: "28px", fontWeight: "400" }],
                        "label-md": ["12px", { lineHeight: "16px", letterSpacing: "0.02em", fontWeight: "600" }],
                        "body-sm": ["13px", { lineHeight: "20px", fontWeight: "400" }],
                        "display-xl": ["56px", { lineHeight: "64px", letterSpacing: "-0.02em", fontWeight: "500" }],
                        "label-lg": ["14px", { lineHeight: "20px", letterSpacing: "0.01em", fontWeight: "600" }]
                    }
                }
            }
        };
    </script>
</head>
<body class="bg-surface text-on-surface min-h-screen flex flex-col font-body-md text-body-md">

    <!-- 1. BANNER IDENTITAS KELOMPOK (served_by.php) -->
    <div class="fixed top-0 left-0 right-0 w-full z-[60] bg-surface-container-low border-b-[1.5px] border-on-surface shadow-[0_2px_0px_#1c1b1b]">
        <?php 
        $served_by_path = __DIR__ . '/partials/served_by.php';
        if (file_exists($served_by_path)) {
            include $served_by_path;
        } else {
            echo '<div class="p-2 text-center text-xs font-bold">Dilayani oleh: ' . htmlspecialchars(MEMBER_NAMA) . ' (' . htmlspecialchars(MEMBER_ROUTE) . ')</div>';
        }
        ?>
    </div>

    <!-- 2. STICKY HEADER DENGAN GAYA STITCH -->
    <header class="fixed top-10 left-0 right-0 w-full z-50 bg-surface/95 backdrop-blur-md border-b-[1.5px] border-on-surface shadow-[0_2px_0px_#1c1b1b]">
        <div class="h-16 max-w-[1100px] mx-auto px-gutter flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="jawab.php" class="flex flex-col text-inherit no-underline">
                    <div class="flex items-center gap-1.5">
                        <span class="font-headline-sm text-headline-sm text-on-surface tracking-tight font-semibold">q&a room<span class="text-primary">.</span></span>
                        <span class="inline-block px-2 py-0.5 rounded-full bg-primary-container text-on-surface font-label-sm text-label-sm uppercase tracking-wider border-[1px] border-on-surface shadow-[1px_1px_0px_#1c1b1b]">Jawaban</span>
                    </div>
                    <span class="font-label-sm text-label-sm text-on-surface-variant italic">Fitur Jawaban & Moderasi (Kelompok 05)</span>
                </a>
            </div>
            
            <div class="flex items-center gap-2 sm:gap-3">
                <a class="hidden sm:inline-flex items-center gap-1 text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-colors py-1.5 px-3 rounded-full border-[1.5px] border-transparent hover:border-on-surface" href="../index.php">
                    <span>← Personal Page</span>
                </a>
                <a class="inline-flex items-center gap-1 px-3.5 py-1.5 rounded-full bg-surface-container-lowest text-on-surface font-label-md text-label-md font-bold border-[1.5px] border-on-surface shadow-[1.5px_1.5px_0px_#1c1b1b] hover:shadow-[3px_3px_0px_#1c1b1b] active:translate-x-[1px] active:translate-y-[1px] transition-all" href="form-tanya.php">
                    <span class="text-primary">✦</span>
                    <span>Tanya Baru</span>
                </a>
                <a class="inline-flex items-center gap-1 px-3.5 py-1.5 rounded-full bg-secondary-container text-on-surface font-label-md text-label-md font-bold border-[1.5px] border-on-surface shadow-[1.5px_1.5px_0px_#1c1b1b] hover:shadow-[3px_3px_0px_#1c1b1b] active:translate-x-[1px] active:translate-y-[1px] transition-all" href="jawab.php">
                    <span>Semua Pertanyaan</span>
                </a>
            </div>
        </div>
    </header>

    <!-- 3. MAIN CONTENT CONTAINER -->
    <main class="w-full pt-32 flex-grow bg-surface pb-16">
        <div class="max-w-[1000px] mx-auto px-gutter py-space-md">

            <!-- Flash Alert Box (Success / Error / Info) -->
            <?php if ($alert): ?>
                <div class="relative mb-6 p-4 rounded-xl <?= $alertType === 'error' ? 'bg-[#FFDAD6] text-[#93000A]' : ($alertType === 'warning' ? 'bg-[#FFF3CD] text-[#856404]' : 'bg-[#DDECCF] text-[#244822]') ?> border-[1.5px] border-on-surface shadow-[3px_3px_0px_#1c1b1b] flex items-start justify-between gap-3 transition-all duration-300" id="flashAlert">
                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-full <?= $alertType === 'error' ? 'bg-[#93000A] text-[#FFDAD6]' : ($alertType === 'warning' ? 'bg-[#856404] text-[#FFF3CD]' : 'bg-[#244822] text-[#DDECCF]') ?> flex items-center justify-center shrink-0 text-sm font-bold shadow-[1px_1px_0px_#1c1b1b]">
                            <?= $alertType === 'error' ? '✕' : ($alertType === 'warning' ? '!' : '✓') ?>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-label-md text-label-md font-bold text-[#1c1b1b] tracking-wide">
                                <?= $alertType === 'error' ? 'Pemberitahuan Kesalahan' : ($alertType === 'warning' ? 'Pemberitahuan Moderasi' : 'Operasi Berhasil') ?>
                            </span>
                            <p class="font-body-sm text-body-sm mt-0.5">
                                <?= htmlspecialchars($alert, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </div>
                    <button class="hover:opacity-75 p-1 rounded-full transition-colors leading-none font-bold" onclick="document.getElementById('flashAlert').remove()" type="button">✕</button>
                </div>
            <?php endif; ?>

            <?php if ($dbError): ?>
                <div class="relative mb-6 p-4 rounded-xl bg-[#FFDAD6] text-[#93000A] border-[1.5px] border-on-surface shadow-[3px_3px_0px_#1c1b1b]">
                    <strong>Koneksi Database Bermasalah:</strong> <?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($pertanyaan): ?>
                <!-- ===============================================================
                     TAMPILAN A: DETAIL PERTANYAAN, MODERASI, DAN JAWABAN
                     =============================================================== -->

                <!-- 1. KARTU PERTANYAAN (EDITORIAL STITCH CARD) -->
                <section class="relative bg-surface-container-lowest border-[1.5px] border-on-surface rounded-2xl p-6 sm:p-8 shadow-[4px_4px_0px_#1c1b1b] mb-8 overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b-[1.5px] border-on-surface/15 mb-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-secondary-container text-on-surface font-label-sm text-label-sm font-bold border-[1px] border-on-surface">
                                <span class="text-primary">✦</span>
                                <span>Oleh: <?= htmlspecialchars($pertanyaan['nama_anggota'] ?: $pertanyaan['nama'], ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <span class="px-2.5 py-1 rounded-full bg-surface-container text-on-surface font-label-sm text-label-sm font-mono border-[1px] border-on-surface">
                                Node: <?= htmlspecialchars($pertanyaan['route_id'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="px-2.5 py-1 rounded-full bg-surface-container-low text-on-surface-variant font-label-sm text-label-sm border-[1px] border-on-surface/40">
                                <?= date('d M Y, H:i', strtotime($pertanyaan['created_at'])) ?> WIB
                            </span>
                            <span class="px-2.5 py-1 rounded-full bg-primary-container text-on-surface font-label-sm text-label-sm font-bold border-[1px] border-on-surface shadow-[1px_1px_0px_#1c1b1b]">
                                ▲ <?= (int)$pertanyaan['upvote'] ?> Upvote
                            </span>
                        </div>

                        <!-- MODERASI BADGE & TOGGLE BUTTON -->
                        <div class="flex items-center gap-2.5">
                            <?php if ($pertanyaan['disembunyikan']): ?>
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-[#FFDAD6] text-[#93000A] font-label-sm text-label-sm font-bold border-[1.5px] border-on-surface shadow-[1px_1px_0px_#1c1b1b]">
                                    <span>🔒 Disembunyikan</span>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-[#DDECCF] text-[#244822] font-label-sm text-label-sm font-bold border-[1.5px] border-on-surface shadow-[1px_1px_0px_#1c1b1b]">
                                    <span>● Publik</span>
                                </span>
                            <?php endif; ?>

                            <!-- Tombol Moderasi -->
                            <form method="POST" action="jawab.php" onsubmit="return confirm('Apakah Anda yakin ingin <?= $pertanyaan['disembunyikan'] ? 'menampilkan kembali pertanyaan ini ke publik' : 'menyembunyikan pertanyaan ini (moderasi)' ?>?');" class="inline m-0">
                                <input type="hidden" name="action" value="moderasi">
                                <input type="hidden" name="pertanyaan_id" value="<?= (int)$pertanyaan['id'] ?>">
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full <?= $pertanyaan['disembunyikan'] ? 'bg-[#DDECCF] hover:bg-[#cbe3b7] text-[#244822]' : 'bg-surface-container-low hover:bg-[#FFDAD6] hover:text-[#93000A] text-on-surface' ?> font-label-md text-label-md font-bold border-[1.5px] border-on-surface shadow-[1.5px_1.5px_0px_#1c1b1b] hover:shadow-[3px_3px_0px_#1c1b1b] active:translate-x-[1px] active:translate-y-[1px] transition-all cursor-pointer">
                                    <span><?= $pertanyaan['disembunyikan'] ? '👁️ Pulihkan' : '🙈 Sembunyikan' ?></span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Judul / Isi Pertanyaan Besar -->
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-1 text-primary font-label-sm text-label-sm font-bold tracking-widest uppercase">
                            <span>✦</span>
                            <span>Pertanyaan Utama</span>
                            <span>✧</span>
                        </div>
                        <h1 class="font-headline-lg text-headline-lg-mobile sm:text-headline-lg text-on-surface tracking-tight leading-snug font-medium italic">
                            "<?= nl2br(htmlspecialchars($pertanyaan['isi'], ENT_QUOTES, 'UTF-8')) ?>"
                        </h1>
                    </div>

                    <!-- Warning jika pertanyaan sedang dimoderasi -->
                    <?php if ($pertanyaan['disembunyikan']): ?>
                        <div class="mt-6 p-4 rounded-xl bg-[#FFF3CD] text-[#856404] border-[1.5px] border-on-surface shadow-[2px_2px_0px_#1c1b1b] flex items-center gap-3">
                            <span class="text-xl">⚠️</span>
                            <div class="font-body-sm text-body-sm">
                                <strong>Status Moderasi Aktif:</strong> Pertanyaan ini sedang disembunyikan dari daftar publik. Anda dapat memulihkannya kembali kapan saja menggunakan tombol di pojok kanan atas.
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- 2. DAFTAR JAWABAN (RESPONSES LIST) -->
                <section class="mb-8" id="jawaban-terbaru">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <h2 class="font-headline-md text-headline-md text-on-surface font-semibold tracking-tight">
                                Tanggapan & Diskusi
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container font-label-sm text-label-sm font-bold border-[1px] border-on-surface">
                                <?= count($daftarJawaban) ?> Jawaban
                            </span>
                        </div>
                        <span class="font-label-sm text-label-sm text-on-surface-variant italic hidden sm:inline">
                            Diurutkan dari terlama ke terbaru
                        </span>
                    </div>

                    <?php if (empty($daftarJawaban)): ?>
                        <!-- Empty State Stitch -->
                        <div class="bg-surface-container-lowest border-[1.5px] border-dashed border-on-surface/40 rounded-2xl p-8 text-center shadow-[2px_2px_0px_#1c1b1b]">
                            <div class="w-12 h-12 rounded-full bg-secondary-container border-[1.5px] border-on-surface text-on-surface flex items-center justify-center font-bold text-2xl mx-auto mb-3 shadow-[2px_2px_0px_#1c1b1b]">
                                💬
                            </div>
                            <h3 class="font-headline-sm text-headline-sm text-on-surface font-medium italic mb-1">
                                Belum ada jawaban untuk pertanyaan ini
                            </h3>
                            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md mx-auto mb-4">
                                Jadilah orang pertama yang memberikan tanggapan atau solusi untuk pertanyaan ini melalui form di bawah.
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Loop Jawaban -->
                        <div class="space-y-4">
                            <?php foreach ($daftarJawaban as $index => $j): ?>
                                <article class="bg-surface-container-lowest border-[1.5px] border-on-surface rounded-2xl p-5 sm:p-6 shadow-[3px_3px_0px_#1c1b1b] transition-all hover:shadow-[5px_5px_0px_#1c1b1b]">
                                    <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b-[1.5px] border-on-surface/10 mb-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-secondary-container border-[1.5px] border-on-surface text-on-surface font-bold text-xs flex items-center justify-center shadow-[1px_1px_0px_#1c1b1b]">
                                                <?= strtoupper(substr($j['nama_penjawab'] ?: $j['route_id'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-label-md text-label-md font-bold text-on-surface">
                                                        <?= htmlspecialchars($j['nama_penjawab'] ?: 'Anggota ' . $j['route_id'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <span class="px-2 py-0.2 rounded-full bg-surface-container text-on-surface font-label-sm text-label-sm font-mono border-[1px] border-on-surface">
                                                        Node <?= htmlspecialchars($j['route_id'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-on-surface-variant font-label-sm text-label-sm">
                                            <span>#<?= $index + 1 ?></span>
                                            <span>•</span>
                                            <span><?= date('d M Y, H:i', strtotime($j['created_at'])) ?> WIB</span>
                                        </div>
                                    </div>

                                    <div class="font-body-md text-body-md text-on-surface whitespace-pre-wrap leading-relaxed">
                                        <?= nl2br(htmlspecialchars($j['isi'], ENT_QUOTES, 'UTF-8')) ?>
                                    </div>

                                    <div class="mt-4 pt-3 border-t-[1px] border-on-surface/10 flex items-center justify-between text-on-surface-variant font-label-sm text-label-sm">
                                        <span class="flex items-center gap-1">
                                            <span class="text-primary">✦</span>
                                            <span>Tersimpan di node PostgreSQL lokal</span>
                                        </span>
                                        <span class="italic font-mono text-outline">
                                            ID: <?= (int)$j['id'] ?>
                                        </span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- 3. FORM BERI JAWABAN (STITCH NEO-BRUTALIST FORM) -->
                <section class="bg-surface-container-lowest border-[1.5px] border-on-surface rounded-2xl p-6 sm:p-8 shadow-[4px_4px_0px_#1c1b1b] relative overflow-hidden">
                    <div class="space-y-1.5 mb-6">
                        <div class="inline-flex items-center gap-1.5 text-primary font-label-sm text-label-sm font-bold tracking-widest uppercase">
                            <span>✦</span>
                            <span>Berikan Tanggapan / Solusi</span>
                            <span>✧</span>
                        </div>
                        <h2 class="font-display-xl text-display-xl-mobile sm:text-display-xl text-on-surface tracking-tight leading-none italic font-medium">
                            Tulis Jawabanmu
                        </h2>
                        <p class="font-body-md text-body-md text-on-surface-variant pt-1">
                            Jawaban akan dicatat dengan <code>route_id: <?= htmlspecialchars(MEMBER_ROUTE, ENT_QUOTES, 'UTF-8') ?></code> atas nama <strong><?= htmlspecialchars(MEMBER_NAMA, ENT_QUOTES, 'UTF-8') ?></strong> dan disinkronkan ke PostgreSQL bersama.
                        </p>
                    </div>

                    <form id="formJawab" method="POST" action="jawab.php" class="space-y-5" novalidate>
                        <input type="hidden" name="action" value="jawab">
                        <input type="hidden" name="pertanyaan_id" value="<?= (int)$pertanyaan['id'] ?>">

                        <!-- Pemilihan Route ID (Default ke identitas lokal) -->
                        <div class="space-y-2">
                            <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center justify-between">
                                <span>Identitas Server Pengirim (Route)</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant font-normal">Sesuai arsitektur terdistribusi</span>
                            </label>
                            
                            <div class="flex flex-wrap gap-2 pt-1" id="routePills">
                                <?php if (!empty($daftarAnggota)): ?>
                                    <?php foreach ($daftarAnggota as $m): ?>
                                        <button 
                                            type="button" 
                                            class="route-pill-btn px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all <?= $m['route_id'] === MEMBER_ROUTE ? 'bg-primary-container text-on-surface font-bold shadow-[2px_2px_0px_#1c1b1b]' : 'bg-surface-container-lowest text-on-surface-variant hover:text-on-surface shadow-[1px_1px_0px_#1c1b1b]' ?>"
                                            onclick="selectRoute(this, '<?= htmlspecialchars($m['route_id']) ?>')">
                                            <?= $m['route_id'] === MEMBER_ROUTE ? '✦ ' : '' ?><?= htmlspecialchars($m['route_id']) ?>: <?= htmlspecialchars($m['nama']) ?>
                                        </button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <button 
                                        type="button" 
                                        class="route-pill-btn px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all bg-primary-container text-on-surface font-bold shadow-[2px_2px_0px_#1c1b1b]"
                                        onclick="selectRoute(this, '<?= htmlspecialchars(MEMBER_ROUTE) ?>')">
                                        ✦ <?= htmlspecialchars(MEMBER_ROUTE) ?>: <?= htmlspecialchars(MEMBER_NAMA) ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="route_id" id="selectedRouteId" value="<?= htmlspecialchars(MEMBER_ROUTE) ?>">
                        </div>

                        <!-- Textarea Jawaban -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-1" for="inputIsi">
                                    Isi Jawaban Lengkap <span class="text-error font-bold">*</span>
                                </label>
                                <span class="font-label-sm text-label-sm text-on-surface-variant font-mono" id="charCounter">0/5000</span>
                            </div>
                            <textarea 
                                class="w-full bg-surface-container-lowest text-on-surface font-body-md text-body-md p-4 rounded-xl border-[1.5px] border-on-surface placeholder:text-outline/70 focus:outline-none focus:ring-0 focus:shadow-[2px_2px_0px_#1c1b1b] focus:border-on-surface transition-all resize-y min-h-[140px]" 
                                id="inputIsi" 
                                name="isi" 
                                maxlength="5000" 
                                minlength="3" 
                                oninput="handleCharCount(this)" 
                                placeholder="Tuliskan jawaban yang komprehensif, jelas, dan membantu penanya..." 
                                required 
                                rows="5"></textarea>
                            <div class="flex items-center justify-between text-on-surface-variant font-body-sm text-body-sm">
                                <span class="text-error font-semibold font-label-sm text-label-sm" id="validationMsg"></span>
                                <span class="font-label-sm text-label-sm text-outline-variant transition-colors" id="minValidationHint">Minimal 3 karakter</span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-4 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3.5 border-t-[1.5px] border-on-surface/20">
                            <a href="jawab.php" class="inline-flex items-center justify-center font-label-md text-label-md px-5 py-2.5 rounded-full text-on-surface-variant hover:text-on-surface transition-colors text-center">
                                Batal & Pilih Pertanyaan Lain
                            </a>
                            <button 
                                class="inline-flex items-center justify-center gap-2 font-label-lg text-label-lg px-7 py-3 rounded-full bg-primary-container text-on-surface font-bold border-[1.5px] border-on-surface shadow-[2px_2px_0px_#1c1b1b] hover:shadow-[4px_4px_0px_#1c1b1b] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[0px_0px_0px_#1c1b1b] transition-all cursor-pointer" 
                                type="submit" 
                                id="btnKirim">
                                <span>Kirim Jawaban</span>
                                <span class="text-base font-black">↗</span>
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Modal Sukses Berbasis URL Parameter (Stitch Pop-up Style) -->
                <div class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" id="successModal">
                    <div class="bg-surface border-[1.5px] border-on-surface rounded-2xl max-w-md w-full p-6 sm:p-8 shadow-[6px_6px_0px_#1c1b1b] relative">
                        <div class="w-12 h-12 rounded-full bg-[#DDECCF] border-[1.5px] border-on-surface text-[#244822] flex items-center justify-center font-bold text-2xl shadow-[2px_2px_0px_#1c1b1b] mb-4">✓</div>
                        <div class="space-y-2">
                            <span class="inline-block px-2.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container font-label-sm text-label-sm border-[1px] border-on-surface">Status Tersimpan</span>
                            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Jawabanmu Berhasil Dikirim!</h2>
                            <p class="font-body-md text-body-md text-on-surface-variant">
                                Tanggapan Anda telah tercatat di basis data PostgreSQL dan dapat langsung dilihat oleh seluruh anggota cluster.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t-[1.5px] border-on-surface/20 flex flex-col sm:flex-row gap-3">
                            <button class="flex-1 py-2.5 px-4 rounded-full bg-primary-container text-on-surface font-label-md text-label-md font-bold border-[1.5px] border-on-surface shadow-[2px_2px_0px_#1c1b1b] text-center cursor-pointer" onclick="closeModal()">Tutup & Lihat Jawaban</button>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- ===============================================================
                     TAMPILAN B: JIKA TIDAK ADA ID (DAFTAR PERTANYAAN UNTUK DIJAWAB)
                     =============================================================== -->
                <section class="space-y-6">
                    <div class="space-y-2">
                        <div class="inline-flex items-center gap-1.5 text-primary font-label-sm text-label-sm font-bold tracking-widest uppercase">
                            <span>✦</span>
                            <span>Pilih Pertanyaan untuk Dijawab</span>
                            <span>✧</span>
                        </div>
                        <h1 class="font-display-xl text-display-xl-mobile sm:text-display-xl text-on-surface tracking-tight leading-none italic font-medium">
                            Daftar Pertanyaan
                        </h1>
                        <p class="font-body-md text-body-md text-on-surface-variant max-w-xl">
                            Pilih salah satu pertanyaan di bawah ini untuk membuka lembar diskusi, memberikan jawaban, atau melakukan moderasi konten.
                        </p>
                    </div>

                    <?php if (empty($semuaPertanyaan)): ?>
                        <div class="bg-surface-container-lowest border-[1.5px] border-dashed border-on-surface/40 rounded-2xl p-10 text-center shadow-[3px_3px_0px_#1c1b1b]">
                            <div class="w-14 h-14 rounded-full bg-secondary-container border-[1.5px] border-on-surface text-on-surface flex items-center justify-center font-bold text-3xl mx-auto mb-4 shadow-[2px_2px_0px_#1c1b1b]">
                                📝
                            </div>
                            <h3 class="font-headline-sm text-headline-sm text-on-surface font-medium italic mb-2">
                                Belum ada pertanyaan yang diajukan
                            </h3>
                            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md mx-auto mb-6">
                                Basis data kelompok saat ini belum memiliki data pertanyaan. Anda dapat membuat pertanyaan baru melalui fitur Tanya.
                            </p>
                            <a href="form-tanya.php" class="inline-flex items-center gap-2 font-label-lg text-label-lg px-6 py-2.5 rounded-full bg-primary-container text-on-surface font-bold border-[1.5px] border-on-surface shadow-[2px_2px_0px_#1c1b1b] hover:shadow-[4px_4px_0px_#1c1b1b] transition-all">
                                <span>Ajukan Pertanyaan Pertama</span>
                                <span>↗</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 gap-4">
                            <?php foreach ($semuaPertanyaan as $p): ?>
                                <article class="bg-surface-container-lowest border-[1.5px] border-on-surface rounded-2xl p-6 shadow-[3px_3px_0px_#1c1b1b] hover:shadow-[5px_5px_0px_#1c1b1b] transition-all flex flex-col justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b-[1px] border-on-surface/15 mb-4">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-secondary-container text-on-surface font-label-sm text-label-sm font-semibold border-[1px] border-on-surface">
                                                    Dari: <?= htmlspecialchars($p['nama_anggota'] ?: $p['nama'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <span class="px-2 py-0.5 rounded-full bg-surface-container text-on-surface font-label-sm text-label-sm font-mono border-[1px] border-on-surface">
                                                    Node <?= htmlspecialchars($p['route_id'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <span class="text-on-surface-variant font-label-sm text-label-sm">
                                                    <?= date('d M Y, H:i', strtotime($p['created_at'])) ?> WIB
                                                </span>
                                            </div>

                                            <div>
                                                <?php if ($p['disembunyikan']): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#FFDAD6] text-[#93000A] font-label-sm text-label-sm font-bold border-[1px] border-on-surface">
                                                        🔒 Disembunyikan
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#DDECCF] text-[#244822] font-label-sm text-label-sm font-bold border-[1px] border-on-surface">
                                                        ● Publik
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <h3 class="font-headline-sm text-headline-sm text-on-surface font-medium italic leading-snug mb-3">
                                            "<?= htmlspecialchars(mb_substr($p['isi'], 0, 160), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($p['isi']) > 160 ? '...' : '' ?>"
                                        </h3>
                                    </div>

                                    <div class="pt-4 border-t-[1px] border-on-surface/10 flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 font-label-sm text-label-sm">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-low border-[1px] border-on-surface/30 text-on-surface">
                                                💬 <?= (int)$p['jumlah_jawaban'] ?> Jawaban
                                            </span>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary-container/60 border-[1px] border-on-surface/40 text-on-surface font-bold">
                                                ▲ <?= (int)$p['upvote'] ?> Upvote
                                            </span>
                                        </div>

                                        <a href="jawab.php?id=<?= (int)$p['id'] ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-primary-container text-on-surface font-label-md text-label-md font-bold border-[1.5px] border-on-surface shadow-[1.5px_1.5px_0px_#1c1b1b] hover:shadow-[3px_3px_0px_#1c1b1b] active:translate-x-[1px] active:translate-y-[1px] transition-all">
                                            <span>Buka & Jawab</span>
                                            <span>↗</span>
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

        </div>
    </main>

    <!-- 4. FOOTER DENGAN GAYA STITCH -->
    <footer class="w-full bg-surface-container-low border-t-[1.5px] border-on-surface mt-auto">
        <div class="max-w-[1100px] mx-auto px-gutter py-space-lg flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-on-surface-variant font-body-sm text-body-sm">
                <span class="text-primary font-bold">✦</span>
                <span>Proyek Kelompok Terdistribusi (Kelompok 05)</span>
                <span class="text-outline-variant">•</span>
                <span>PHP 8 & PostgreSQL</span>
                <span class="text-outline-variant">•</span>
                <span>Node <?= htmlspecialchars(MEMBER_ROUTE) ?></span>
            </div>
            <div class="text-on-surface-variant font-label-sm text-label-sm">
                Laman Personal: <a href="../index.php" class="underline hover:text-on-surface"><?= htmlspecialchars(MEMBER_NAMA) ?> (<?= htmlspecialchars(MEMBER_NIM) ?>)</a>
            </div>
        </div>
    </footer>

    <!-- 5. INTERACTIVE SCRIPTING -->
    <script>
        // Hitung Karakter Realtime & Validasi
        function handleCharCount(textarea) {
            const len = textarea.value.length;
            const counter = document.getElementById('charCounter');
            const hint = document.getElementById('minValidationHint');
            const validationMsg = document.getElementById('validationMsg');

            if (counter) counter.textContent = `${len}/5000`;

            if (validationMsg) validationMsg.textContent = '';

            if (hint) {
                if (len >= 3) {
                    hint.textContent = 'Minimal karakter terpenuhi ✓';
                    hint.classList.remove('text-outline-variant');
                    hint.classList.add('text-[#244822]', 'font-semibold');
                } else {
                    hint.textContent = `Minimal 3 karakter (${3 - len} lagi)`;
                    hint.classList.remove('text-[#244822]', 'font-semibold');
                    hint.classList.add('text-outline-variant');
                }
            }
        }

        // Pilihan Route ID Penjawab
        function selectRoute(buttonElement, routeValue) {
            const routeInput = document.getElementById('selectedRouteId');
            if (routeInput) routeInput.value = routeValue;

            const allButtons = document.querySelectorAll('.route-pill-btn');
            allButtons.forEach(btn => {
                btn.className = "route-pill-btn px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all bg-surface-container-lowest text-on-surface-variant hover:text-on-surface shadow-[1px_1px_0px_#1c1b1b]";
                btn.textContent = btn.textContent.replace('✦ ', '');
            });

            buttonElement.className = "route-pill-btn px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all bg-primary-container text-on-surface font-bold shadow-[2px_2px_0px_#1c1b1b]";
            buttonElement.textContent = '✦ ' + buttonElement.textContent.trim();
        }

        // Tutup Modal Sukses
        function closeModal() {
            const modal = document.getElementById('successModal');
            if (modal) modal.classList.add('hidden');
            // Bersihkan parameter dari URL agar tidak memicu modal lagi saat direfresh
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/([&?]status=sukses|&?msg=[^&]*|&?type=[^&]*)/g, ''));
        }

        // Validasi Submit Form
        document.addEventListener('DOMContentLoaded', () => {
            const formJawab = document.getElementById('formJawab');
            const inputIsi = document.getElementById('inputIsi');
            const validationMsg = document.getElementById('validationMsg');
            const btnKirim = document.getElementById('btnKirim');

            if (formJawab && inputIsi) {
                formJawab.addEventListener('submit', (e) => {
                    const text = inputIsi.value.trim();
                    if (!text) {
                        e.preventDefault();
                        if (validationMsg) validationMsg.textContent = 'Isi jawaban tidak boleh kosong!';
                        inputIsi.focus();
                        return false;
                    }
                    if (text.length < 3) {
                        e.preventDefault();
                        if (validationMsg) validationMsg.textContent = 'Isi jawaban minimal 3 karakter!';
                        inputIsi.focus();
                        return false;
                    }

                    if (btnKirim) {
                        btnKirim.disabled = true;
                        btnKirim.innerHTML = '<span>Mengirimkan...</span>';
                    }
                });
            }
        });
    </script>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'sukses'): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('successModal');
                if (modal) modal.classList.remove('hidden');
            });
        </script>
    <?php endif; ?>

</body>
</html>
