<?php
session_start();

require_once __DIR__ . '/../identity.php';
require_once __DIR__ . '/lib/db.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pageUrl(int $page, string $sort = 'terbaru', string $search = ''): string {
    $params = ['halaman' => $page, 'urut' => $sort];
    if ($search !== '') {
        $params['cari'] = $search;
    }
    return '?' . http_build_query($params);
}

function formatTanggal($value): string {
    if (!$value) return 'Waktu tidak tersedia';
    try {
        return (new DateTime((string)$value))->format('d M Y · H:i');
    } catch (Throwable $e) {
        return (string)$value;
    }
}

if (empty($_SESSION['qna_csrf'])) {
    $_SESSION['qna_csrf'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['qna_voted']) || !is_array($_SESSION['qna_voted'])) {
    $_SESSION['qna_voted'] = [];
}

$perHalaman = 5;
$halaman = filter_input(INPUT_GET, 'halaman', FILTER_VALIDATE_INT);
$halaman = (!$halaman || $halaman < 1) ? 1 : $halaman;
$sort = (($_GET['urut'] ?? 'terbaru') === 'upvote') ? 'upvote' : 'terbaru';
$cari = trim((string)($_GET['cari'] ?? ''));

$error = false;
$pesan = (string)($_SESSION['qna_flash'] ?? '');
$jenisPesan = (string)($_SESSION['qna_flash_type'] ?? 'success');
unset($_SESSION['qna_flash'], $_SESSION['qna_flash_type']);

$pertanyaan = [];
$totalData = 0;
$totalUpvote = 0;
$totalHalaman = 1;

try {
    $pdo = db();

    // Tangani upvote. Pembatasan di sini menggunakan session browser.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'upvote') {
        $token = (string)($_POST['csrf_token'] ?? '');
        $idPertanyaan = filter_var($_POST['id_pertanyaan'] ?? null, FILTER_VALIDATE_INT);
        if (!hash_equals($_SESSION['qna_csrf'], $token)) {
            $_SESSION['qna_flash'] = 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.';
            $_SESSION['qna_flash_type'] = 'error';
        } elseif (!$idPertanyaan || $idPertanyaan < 1) {
            $_SESSION['qna_flash'] = 'Pertanyaan tidak valid.';
            $_SESSION['qna_flash_type'] = 'error';
        } elseif (in_array($idPertanyaan, $_SESSION['qna_voted'], true)) {
            $_SESSION['qna_flash'] = 'Kamu sudah memberikan upvote untuk pertanyaan ini dari sesi browser ini.';

            $_SESSION['qna_flash_type'] = 'error';

        } else {
            $voteStmt = $pdo->prepare(
                'UPDATE pertanyaan
                 SET upvote = COALESCE(upvote, 0) + 1
                 WHERE id = :id AND disembunyikan = FALSE'
            );
            $voteStmt->execute(['id' => $idPertanyaan]);

            if ($voteStmt->rowCount() > 0) {
                $_SESSION['qna_voted'][] = $idPertanyaan;
                $_SESSION['qna_flash'] = 'Upvote berhasil ditambahkan. Terima kasih sudah mendukung pertanyaan ini!';
                $_SESSION['qna_flash_type'] = 'success';
            } else {
                $_SESSION['qna_flash'] = 'Pertanyaan tidak ditemukan atau sudah tidak tersedia.';
                $_SESSION['qna_flash_type'] = 'error';
            }
        }

        $target = pageUrl($halaman, $sort, $cari);
        header('Location: ' . $target);
        exit;
    }

    $where = 'WHERE p.disembunyikan = FALSE';
    $params = [];

    if ($cari !== '') {
        $where .= ' AND (p.isi ILIKE :cari_isi OR p.nama ILIKE :cari_nama)';
        $params['cari_isi'] = '%' . $cari . '%';
        $params['cari_nama'] = '%' . $cari . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pertanyaan p $where");
    $countStmt->execute($params);
    $totalData = (int)$countStmt->fetchColumn();

    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(p.upvote), 0) FROM pertanyaan p $where");
    $sumStmt->execute($params);
    $totalUpvote = (int)$sumStmt->fetchColumn();

    $totalHalaman = max(1, (int)ceil($totalData / $perHalaman));
    $halaman = min($halaman, $totalHalaman);
    $offset = ($halaman - 1) * $perHalaman;
    $orderBy = $sort === 'upvote'
        ? 'p.upvote DESC, p.created_at DESC, p.id DESC'
        : 'p.created_at DESC, p.id DESC';

    $sql = "SELECT p.id, p.route_id, p.nama, p.isi, p.upvote, p.created_at,
                   a.nama AS nama_server
            FROM pertanyaan p
            JOIN anggota a ON a.route_id = p.route_id
            $where
            ORDER BY $orderBy
            LIMIT :batas OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':batas', $perHalaman, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pertanyaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Daftar pertanyaan: ' . $exception->getMessage());
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f3ea">
    <title>Daftar Pertanyaan | Q&amp;A Room</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream:#f7f3ea; --paper:#fffefa; --white:#fff; --ink:#171717;
            --muted:#77716a; --soft-line:#e4ddd1; --coral:#ff936b;
            --coral-dark:#b94b27; --peach:#ffe9de; --sage:#ddecce;
            --yellow:#fff0c7; --lilac:#eee8ff; --shadow:3px 4px 0 var(--ink);
        }
        *{box-sizing:border-box}
        html{scroll-behavior:smooth;scroll-padding-top:110px}
        body{margin:0;background:var(--cream);color:var(--ink);font-family:"DM Sans",Arial,sans-serif;font-size:14px;line-height:1.65}
        body:before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background-image:radial-gradient(#beb6a8 .7px,transparent .7px);background-size:19px 19px;opacity:.25}
        a{color:inherit;text-decoration:none}button,input{font:inherit}
        a:focus-visible,button:focus-visible,input:focus-visible{outline:3px solid var(--coral-dark);outline-offset:3px}
        .served-by{width:min(1120px,90%);margin:18px auto 0;padding:10px 14px;border:1px solid #d8cbbb;border-radius:9px;background:#f0e8db;color:#5f564d;font-size:11px}
        .topbar{position:sticky;top:0;z-index:20;background:rgba(247,243,234,.96);border-bottom:2px solid var(--ink);backdrop-filter:blur(12px)}
        .nav-inner{width:min(1200px,92%);min-height:74px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:20px}
        .brand{display:flex;align-items:center;gap:9px;flex-shrink:0}
        .brand-mark{display:grid;place-items:center;width:38px;height:38px;border:1.5px solid var(--ink);border-radius:50%;background:var(--coral);font-family:"Playfair Display",Georgia,serif;font-size:25px;font-weight:700}
        .brand-copy strong{display:block;font-family:"Playfair Display",Georgia,serif;font-size:20px;line-height:1.2}
        .brand-copy small{display:block;color:var(--muted);font-size:10px;font-style:italic}
        .nav-links{display:flex;gap:4px;padding:5px;border:1.4px solid var(--ink);border-radius:99px;background:var(--paper)}
        .nav-link{padding:7px 15px;border-radius:99px;color:#625a51;font-size:12px}
        .nav-link:hover,.nav-link.active{background:var(--peach);color:var(--ink)}
        .nav-actions{display:flex;align-items:center;justify-content:flex-end;gap:14px}
        .back-link{color:#5c554d;font-size:11px;white-space:nowrap}
        .btn{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 17px;border:1.5px solid var(--ink);border-radius:99px;font-size:12px;font-weight:700;text-align:center;cursor:pointer;transition:transform .18s,box-shadow .18s,background .18s}
        .btn:hover{transform:translate(-1px,-2px);box-shadow:2px 3px 0 var(--ink)}
        .btn-coral{background:var(--coral);color:var(--ink)}.btn-coral:hover{background:#ffac8b}
        .btn-outline{background:var(--paper)}.btn-outline:hover{background:var(--peach)}
        .btn-dark{background:var(--ink);color:white}.btn-dark:hover{background:#3b352e}
        .container{width:min(1120px,90%);margin:auto}
        .hero{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(260px,.65fr);align-items:center;gap:50px;padding:54px 0 42px}
        .eyebrow{display:inline-flex;align-items:center;flex-wrap:wrap;gap:8px;padding:6px 11px;border:1.2px solid var(--ink);border-radius:99px;background:var(--peach);color:#713d2c;font-size:9px;font-weight:700;letter-spacing:.5px}
        .status-dot{display:inline-block;width:7px;height:7px;flex-shrink:0;border-radius:50%;background:#70a56b}
        .hero h1{margin:23px 0 18px;font-family:"Playfair Display",Georgia,serif;font-size:clamp(39px,5.3vw,65px);font-weight:500;line-height:1.05;letter-spacing:-2.2px}
        .headline-accent{color:var(--coral-dark);font-style:italic}
        .hero-description{max-width:610px;margin:0;color:#625a51;font-size:14px;line-height:1.9}
        .hero-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}
        .hero-note{display:flex;align-items:flex-start;gap:8px;margin-top:23px;color:var(--muted);font-size:11px}
        .sparkle{color:var(--coral-dark);font-size:18px;line-height:1}
        .hero-art{position:relative;width:100%;max-width:345px;min-height:300px;margin:auto;padding:16px;border:1.5px solid var(--ink);border-radius:20px;background:#fffdfa;box-shadow:var(--shadow);transform:rotate(1deg)}
        .art-top{display:flex;align-items:center;gap:6px;padding-bottom:13px;border-bottom:1px solid var(--soft-line)}
        .art-top>span{width:9px;height:9px;border:1px solid var(--ink);border-radius:50%;background:var(--coral)}
        .art-top>span:nth-child(2){background:#f5d991}.art-top>span:nth-child(3){background:var(--sage)}
        .art-top small{margin-left:auto;color:var(--muted);font-size:8px;font-weight:700;letter-spacing:.8px}
        .art-avatar{display:grid;place-items:center;width:105px;height:105px;margin:27px auto 19px;border:1.5px solid var(--ink);border-radius:46% 54% 55% 45%;background:var(--coral);box-shadow:3px 4px 0 var(--ink);font-family:"Playfair Display",Georgia,serif;font-size:60px}
        .art-message{padding:14px;border:1px solid var(--ink);border-radius:12px;background:var(--cream)}
        .art-label{color:var(--coral-dark);font-size:9px;font-weight:700;letter-spacing:.8px}
        .art-message p{margin:7px 0 0;font-family:"Playfair Display",Georgia,serif;font-size:14px;font-style:italic;line-height:1.5}
        .art-sticker{position:absolute;padding:7px 11px;border:1.2px solid var(--ink);border-radius:9px;background:white;box-shadow:2px 3px 0 var(--ink);font-size:10px;font-weight:700}
        .sticker-one{top:77px;left:-27px;background:var(--sage);transform:rotate(-7deg)}
        .sticker-two{right:-22px;bottom:32px;background:var(--yellow);transform:rotate(5deg)}
        .art-star{position:absolute;color:var(--coral-dark);font-size:22px}.star-one{top:20px;right:20px}.star-two{bottom:17px;left:17px}
        .alert{display:flex;align-items:center;gap:10px;margin:0 0 22px;padding:13px 16px;border:1.4px solid var(--ink);border-radius:12px;background:white;font-size:12px}
        .alert-success{background:var(--sage);color:#33482a}.alert-error{background:#ffe5e2;color:#812f2a}
        .stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:4px 0 36px}
        .stat-card{min-width:0;padding:16px;border:1.4px solid var(--ink);border-radius:14px;background:var(--white);box-shadow:2px 3px 0 var(--ink)}
        .stat-sage{background:#e5eddc}
        .stat-top{display:flex;align-items:center;justify-content:space-between;gap:8px;color:#6b6258;font-size:9px;font-weight:700;letter-spacing:.4px}
        .stat-icon{display:grid;place-items:center;width:31px;height:31px;flex-shrink:0;border:1px solid var(--ink);border-radius:50%;color:var(--ink);font-size:17px}
        .icon-coral{background:#ffd8c9}.icon-sage{background:var(--sage)}.icon-yellow{background:var(--yellow)}.icon-lilac{background:var(--lilac)}
        .stat-bottom{display:flex;align-items:baseline;flex-wrap:wrap;gap:6px;margin-top:8px}
        .stat-bottom strong{font-family:"Playfair Display",Georgia,serif;font-size:31px;font-weight:500;line-height:1.2}
        .stat-bottom span{color:var(--muted);font-size:10px}
        .question-section{padding-bottom:55px}
        .section-heading{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:19px;padding-bottom:15px;border-bottom:1.5px solid var(--ink)}
        .section-kicker{color:var(--coral-dark);font-size:9px;font-weight:700;letter-spacing:1px}
        .section-heading h2{margin:5px 0;font-family:"Playfair Display",Georgia,serif;font-size:clamp(28px,3.6vw,39px);font-weight:500;letter-spacing:-.8px;line-height:1.2}
        .coral-period{color:var(--coral-dark)}.section-heading p{margin:8px 0 0;color:var(--muted);font-size:12px}
        .text-link{flex-shrink:0;padding-bottom:4px;color:var(--coral-dark);font-size:11px;font-weight:700}
        .filter-panel{margin-bottom:24px;padding:15px;border:1.4px solid var(--ink);border-radius:15px;background:#f2eee7;box-shadow:2px 3px 0 var(--ink)}
        .search-form{display:flex;gap:10px}
        .search-box{display:flex;align-items:center;flex:1;min-width:0;gap:10px;padding:0 12px;border:1.3px solid var(--ink);border-radius:9px;background:white}
        .search-box>span{color:var(--coral-dark);font-size:22px}
        .search-box input{width:100%;min-width:0;height:42px;border:0;outline:none;background:transparent;color:var(--ink);font-size:12px}
        .filter-bottom{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:14px;padding-top:13px;border-top:1px solid var(--soft-line)}
        .filter-label{color:var(--muted);font-size:9px;font-weight:700;letter-spacing:.7px}
        .filter-pills{display:flex;flex-wrap:wrap;gap:7px}
        .filter-pill{display:inline-flex;align-items:center;justify-content:center;min-height:29px;padding:5px 12px;border:1px solid var(--ink);border-radius:99px;background:white;font-size:10px;transition:background .2s}
        .filter-pill:hover,.filter-pill.selected{background:var(--coral)}
        .question-list{display:grid;gap:18px}
        .question-card{padding:19px;border:1.4px solid var(--ink);border-radius:15px;background:white;box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s}
        .question-card:hover{transform:translateY(-2px);box-shadow:4px 6px 0 var(--ink)}
        .question-card-top{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px}
        .question-meta{display:flex;align-items:center;flex-wrap:wrap;gap:7px;color:var(--muted);font-size:10px}
        .question-number{color:var(--coral-dark);font-family:"Playfair Display",Georgia,serif;font-size:14px;font-weight:700}
        .meta-separator{color:#c0b7a9}.question-author{color:#625a51;font-weight:600}
        .status-badge{display:inline-flex;align-items:center;flex-shrink:0;gap:6px;padding:5px 10px;border:1px solid var(--ink);border-radius:99px;font-size:10px;font-weight:700}
        .status-pending{background:var(--yellow);color:#75551e}
        .question-card-content{margin-top:14px}
        .question-card-content h3{max-width:950px;margin:0;font-family:"Playfair Display",Georgia,serif;font-size:clamp(19px,2.2vw,25px);font-weight:500;line-height:1.45;letter-spacing:-.25px;overflow-wrap:anywhere;white-space:pre-wrap}
        .question-card-bottom{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:13px;margin-top:19px;padding-top:14px;border-top:1px solid #eee8dd}
        .question-tags{display:flex;flex-wrap:wrap;gap:7px}
        .tag{display:inline-flex;align-items:center;padding:4px 9px;border:1px solid #d8d0c3;border-radius:6px;background:#f7f4ed;color:#625a51;font-size:9px}
        .upvote-form{margin:0}

        /* Tombol menuju jawab.php untuk setiap pertanyaan */
        .answer-link{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:7px 12px;border:1.3px solid var(--ink);border-radius:99px;background:var(--coral);color:var(--ink);font-size:11px;font-weight:700;text-decoration:none;transition:transform .2s,background .2s}
        .answer-link:hover{background:#ffac8b;transform:translateY(-1px)}
        .upvote-button{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:7px 12px;border:1.3px solid var(--ink);border-radius:99px;background:#fffefa;color:var(--ink);font-size:11px;cursor:pointer;transition:background .2s,transform .2s}
        .upvote-button:hover:not(:disabled){transform:translateY(-1px);background:#ffe0d2}
        .upvote-button:disabled{opacity:.7;cursor:default}
        .upvote-arrow{color:var(--coral-dark);font-size:17px;font-weight:800}.upvote-button strong{font-weight:800}
        .upvote-label{color:#5f584f}.upvote-action{margin-left:3px;padding-left:8px;border-left:1px solid #d8d0c3;color:#5f584f}
        .empty-state{padding:55px 20px;border:1.5px dashed #a9a091;border-radius:17px;background:rgba(255,254,250,.75);text-align:center}
        .empty-illustration{display:grid;place-items:center;width:65px;height:65px;margin:0 auto 17px;border:1.5px solid var(--ink);border-radius:50%;background:var(--coral);font-family:"Playfair Display",Georgia,serif;font-size:37px}
        .empty-state h3{margin:0;font-family:"Playfair Display",Georgia,serif;font-size:24px;font-weight:500}
        .empty-state p{margin:10px 0 20px;color:var(--muted);font-size:12px}
        .pagination{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:8px;margin-top:30px}
        .page-number,.page-arrow{display:inline-flex;align-items:center;justify-content:center;min-width:37px;min-height:37px;padding:7px 11px;border:1.2px solid var(--ink);border-radius:9px;background:white;font-size:11px}
        .page-number.current{background:var(--coral);font-weight:700}.page-arrow:hover,.page-number:hover{background:var(--peach)}
        .footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:23px 0 30px;border-top:1px solid #cfc7b9}
        .footer-brand{font-family:"Playfair Display",Georgia,serif;font-size:19px;font-weight:700}
        .footer p{margin:0;color:var(--muted);font-size:11px}.footer-link{color:#5e574e;font-size:11px}

        @media(max-width:950px){
            .nav-inner{flex-wrap:wrap;padding:12px 0;gap:12px}.nav-links{order:3;width:100%;justify-content:center}
            .nav-actions{margin-left:auto}.hero{grid-template-columns:minmax(0,1fr) minmax(220px,.7fr);gap:25px;padding-top:45px}
            .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        }

        @media(max-width:650px){
            .container,.nav-inner{width:91%}.nav-actions{gap:8px}.back-link{display:none}
            .nav-actions .btn{min-height:36px;padding:8px 11px;font-size:10px}
            .hero{grid-template-columns:1fr;gap:35px;padding:38px 0}.hero h1{font-size:clamp(38px,10vw,54px);letter-spacing:-1.6px}
            .hero-description{font-size:13px}.hero-art{width:calc(100% - 28px);min-height:285px}
            .stats-grid{gap:10px;margin-bottom:35px}.stat-card{padding:12px}.stat-top{font-size:8px}
            .stat-bottom strong{font-size:27px}.stat-bottom span{font-size:9px}
            .section-heading{align-items:flex-start;flex-direction:column;gap:8px}.section-heading h2{font-size:32px}
            .filter-panel{padding:11px}.search-form{flex-wrap:wrap}.search-box{flex-basis:100%}
            .search-form .btn{width:100%}.filter-bottom{align-items:flex-start;flex-direction:column}
            .question-card{padding:14px;box-shadow:2px 3px 0 var(--ink)}.question-card-top{flex-direction:column;align-items:flex-start}
            .question-card-content h3{font-size:20px}.question-card-bottom{align-items:flex-start;flex-direction:column}
            .upvote-button{min-height:40px}.footer{align-items:flex-start;flex-direction:column}
        }
        @media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;transition:none!important}}

    </style>
</head>
<body>
    <header class="topbar">
        <div class="nav-inner">
            <a href="daftar-pertanyaan.php" class="brand" aria-label="Q&A Room">
                <span class="brand-mark">q.</span>
                <span class="brand-copy"><strong>q&amp;a room.</strong><small>ruang tanya santai</small></span>
            </a>
            <nav class="nav-links" aria-label="Navigasi">
                <a href="daftar-pertanyaan.php" class="nav-link active">Daftar Pertanyaan</a>
            </nav>
            <div class="nav-actions">
                <a href="../index.php" class="back-link">← Kembali ke Personal Page</a>
                <a href="form-tanya.php" class="btn btn-coral">Ajukan Pertanyaan <span>↗</span></a>
            </div>
        </div>
    </header>

    <main class="container">
        <?php
        // Keep the group's shared server banner. The partial should render the identity from identity.php.
        $servedByFile = __DIR__ . '/partials/served_by.php';
        if (is_file($servedByFile)) {
            require $servedByFile;
        } else {
            echo '<div class="served-by">Personal Q&amp;A Room</div>';
        }

        ?>
        <?php if ($pesan !== ''): ?>
            <div class="alert <?= $jenisPesan === 'success' ? 'alert-success' : 'alert-error' ?>" role="status">
                <?= $jenisPesan === 'success' ? '✓' : '!' ?> <?= e($pesan) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">Daftar pertanyaan belum dapat dimuat. Periksa koneksi database dan struktur tabel.</div>
        <?php endif; ?>

        <section class="hero" id="about">
            <div>
                <div class="eyebrow"><span class="status-dot"></span> YOUR PERSONAL Q&amp;A SPACE <span>•</span> Bebas &amp; Terbuka</div>
                <h1>Punya pertanyaan?<br><span class="headline-accent">Let's talk about it.</span></h1>
                <p class="hero-description">Selamat datang di pojok tanya jawab personal page. Mau bertanya soal pengalaman kuliah, proyek, organisasi, atau dunia Informatika? Tulis pertanyaanmu di sini. Pengunjung lain juga bisa memberi upvote pada pertanyaan yang ingin mereka ketahui jawabannya.</p>
                <div class="hero-actions">
                    <a href="form-tanya.php" class="btn btn-coral">Kirim Pertanyaan <span>↗</span></a>
                    <a href="#questions" class="btn btn-outline">Lihat Daftar Pertanyaan ↓</a>
                </div>
                <p class="hero-note"><span class="sparkle">✳</span> Upvote membantu pemilik personal page mengetahui pertanyaan yang paling ingin diketahui pengunjung.</p>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="art-top"><span></span><span></span><span></span><small>YOUR QUESTION BOARD</small></div>
                <div class="art-avatar">?</div>
                <div class="art-message"><span class="art-label">A LITTLE REMINDER</span><p>"Rasa ingin tahu adalah awal dari hal-hal hebat."</p></div>
                <span class="art-sticker sticker-one">Ask away! ↗</span>
                <span class="art-sticker sticker-two">✳ stay curious</span>
                <span class="art-star star-one">✳</span><span class="art-star star-two">✧</span>
            </div>
        </section>

        <section class="stats-grid" aria-label="Statistik pertanyaan">
            <article class="stat-card">
                <div class="stat-top"><span>TOTAL PERTANYAAN</span><span class="stat-icon icon-coral">▤</span></div>
                <div class="stat-bottom"><strong><?= number_format($totalData, 0, ',', '.') ?></strong><span>pertanyaan masuk</span></div>
            </article>
            <article class="stat-card stat-sage">
                <div class="stat-top"><span>TOTAL DUKUNGAN</span><span class="stat-icon icon-sage">↑</span></div>
                <div class="stat-bottom"><strong><?= number_format($totalUpvote, 0, ',', '.') ?></strong><span>upvotes terkumpul</span></div>
            </article>
            <article class="stat-card">
                <div class="stat-top"><span>HALAMAN AKTIF</span><span class="stat-icon icon-yellow">#</span></div>
                <div class="stat-bottom"><strong><?= $halaman ?></strong><span>dari <?= $totalHalaman ?> halaman</span></div>
            </article>
            <article class="stat-card">
                <div class="stat-top"><span>RUANG BERBAGI</span><span class="stat-icon icon-lilac">♡</span></div>
                <div class="stat-bottom"><strong>Q&amp;A</strong><span>tanya dan berbagi</span></div>
            </article>
        </section>

        <section class="question-section" id="questions">
            <div class="section-heading">
                <div>
                    <div class="section-kicker">THE COMMUNITY WALL ✦</div>
                    <h2>The Question Board<span class="coral-period">.</span></h2>
                    <p>Daftar pertanyaan pengunjung. Dukung pertanyaan yang juga ingin kamu ketahui jawabannya.</p>
                </div>
                <a href="form-tanya.php" class="text-link">Tanyakan sesuatu ↗</a>
            </div>

            <div class="filter-panel">
                <form class="search-form" action="daftar-pertanyaan.php" method="get">
                    <label class="search-box"><span aria-hidden="true">⌕</span>
                        <input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Cari kata kunci atau topik pertanyaan..." aria-label="Cari pertanyaan">
                    </label>
                    <input type="hidden" name="urut" value="<?= e($sort) ?>">
                    <button class="btn btn-dark" type="submit">Cari</button>
                </form>
                <div class="filter-bottom">
                    <span class="filter-label">URUTKAN PERTANYAAN</span>
                    <div class="filter-pills">
                        <a class="filter-pill <?= $sort === 'terbaru' ? 'selected' : '' ?>" href="<?= e(pageUrl(1, 'terbaru', $cari)) ?>">Terbaru</a>
                        <a class="filter-pill <?= $sort === 'upvote' ? 'selected' : '' ?>" href="<?= e(pageUrl(1, 'upvote', $cari)) ?>">↑ Paling Banyak Didukung</a>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="empty-state"><div class="empty-illustration">!</div><h3>Daftar pertanyaan belum tersedia.</h3><p>Periksa koneksi database, lalu muat ulang halaman.</p></div>
            <?php elseif (empty($pertanyaan)): ?>
                <div class="empty-state">
                    <div class="empty-illustration">?</div>
                    <h3><?= $cari !== '' ? 'Pertanyaan tidak ditemukan.' : 'Belum ada pertanyaan di sini.' ?></h3>
                    <p><?= $cari !== '' ? 'Coba kata kunci yang berbeda.' : 'Jadilah orang pertama yang mengajukan pertanyaan!' ?></p>
                    <?php if ($cari !== ''): ?><a href="daftar-pertanyaan.php" class="btn btn-outline">Hapus pencarian</a>
                    <?php else: ?><a href="form-tanya.php" class="btn btn-coral">Ajukan Pertanyaan ↗</a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="question-list">
                    <?php foreach ($pertanyaan as $item): ?>
                        <?php $alreadyVoted = in_array((int)$item['id'], $_SESSION['qna_voted'], true); ?>
                        <article class="question-card">
                            <div class="question-card-top">
                                <div class="question-meta">
                                    <span class="question-number">#<?= str_pad((string)$item['id'], 3, '0', STR_PAD_LEFT) ?></span>
                                    <span class="meta-separator">•</span>
                                    <span class="question-author"><?= e(trim((string)$item['nama']) !== '' ? $item['nama'] : 'Anonim') ?></span>
                                    <span class="meta-separator">•</span>
                                    <time datetime="<?= e($item['created_at']) ?>"><?= e(formatTanggal($item['created_at'])) ?></time>
                                </div>
                                <span class="status-badge status-pending"><span class="status-dot"></span> Menunggu Jawaban</span>
                            </div>
                            <div class="question-card-content">
                                <h3><?= e($item['isi']) ?></h3>
                                <div class="question-card-bottom">
                                    <div class="question-tags">
                                        <span class="tag">Personal Q&amp;A</span>
                                        <span class="tag">Server: <?= e($item['nama_server'] ?? 'Tidak diketahui') ?></span>
                                    </div>

                                    <!Tombol baru: membuka halaman jawab.php dengan ID pertanyaan>
                                    <a href="jawab.php?id=<?= (int)$item['id'] ?>" class="answer-link">↗ Jawab Pertanyaan</a>
                                    <form class="upvote-form" method="post" action="<?= e(pageUrl($halaman, $sort, $cari)) ?>">
                                        <input type="hidden" name="aksi" value="upvote">
                                        <input type="hidden" name="id_pertanyaan" value="<?= (int)$item['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['qna_csrf']) ?>">
                                        <button type="submit" class="upvote-button" <?= $alreadyVoted ? 'disabled' : '' ?>>
                                            <span class="upvote-arrow">↑</span>
                                            <strong><?= number_format((int)$item['upvote'], 0, ',', '.') ?></strong>
                                            <span class="upvote-label">Dukungan</span>
                                            <span class="upvote-action"><?= $alreadyVoted ? '✓ Didukung' : '+ Dukung' ?></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalHalaman > 1): ?>
                    <nav class="pagination" aria-label="Navigasi halaman">
                        <?php if ($halaman > 1): ?><a class="page-arrow" href="<?= e(pageUrl($halaman - 1, $sort, $cari)) ?>">← Sebelumnya</a><?php endif; ?>

                        <?php
                        $start = max(1, $halaman - 2);
                        $end = min($totalHalaman, $halaman + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a class="page-number <?= $i === $halaman ? 'current' : '' ?>" href="<?= e(pageUrl($i, $sort, $cari)) ?>" <?= $i === $halaman ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($halaman < $totalHalaman): ?><a class="page-arrow" href="<?= e(pageUrl($halaman + 1, $sort, $cari)) ?>">Berikutnya →</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <footer class="footer">
            <a href="daftar-pertanyaan.php" class="footer-brand">q&amp;a room.</a>
            <p>Tempat bertanya, berbagi, dan menemukan jawaban.</p>
            <a href="../index.php" class="footer-link">← Kembali ke Personal Page</a>
        </footer>
    </main>
</body>
</html>
