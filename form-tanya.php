<?php
// Pastikan path ini sesuai dengan struktur folder Anda (identity.php ada di luar folder app)
require_once __DIR__ . '/../identity.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta content="web_standard" name="shell-type"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>@layer base{html,body{margin:0;padding:0;}body{overscroll-behavior:none;}main>:first-child{margin-top:0!important;}main>:last-child{margin-bottom:0!important;}}::-webkit-scrollbar{display:none;}</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script id="tailwind-config">
        tailwind.config={darkMode:"class",theme:{extend:{colors:{"tertiary":"#665e43","surface-bright":"#fcf9f8","surface-container-high":"#eae7e7","on-tertiary":"#ffffff","surface-container-highest":"#e5e2e1","on-tertiary-fixed-variant":"#4e462d","on-secondary":"#ffffff","on-surface":"#1c1b1b","on-tertiary-container":"#4a4229","on-error":"#ffffff","surface-container-low":"#f6f3f2","on-secondary-fixed-variant":"#54433c","on-tertiary-fixed":"#211b06","on-surface-variant":"#55433c","background":"#fcf9f8","tertiary-container":"#baaf8f","surface":"#fcf9f8","secondary-container":"#f6ddd4","on-primary-fixed-variant":"#7b2e0e","surface-container-lowest":"#ffffff","on-primary-container":"#762a0a","on-secondary-fixed":"#251913","secondary":"#6c5b53","inverse-primary":"#ffb59b","secondary-fixed-dim":"#d9c2b8","on-secondary-container":"#736159","on-background":"#1c1b1b","primary-fixed-dim":"#ffb59b","outline-variant":"#dcc1b8","inverse-surface":"#313030","error-container":"#ffdad6","tertiary-fixed":"#eee2bf","on-primary":"#ffffff","error":"#ba1a1a","primary":"#9a4523","outline":"#88726b","on-error-container":"#93000a","secondary-fixed":"#f6ddd4","on-primary-fixed":"#380d00","primary-fixed":"#ffdbcf","surface-tint":"#9a4523","surface-dim":"#dcd9d9","inverse-on-surface":"#f3f0ef","tertiary-fixed-dim":"#d2c6a5","surface-variant":"#e5e2e1","primary-container":"#ff936b","surface-container":"#f0eded"},borderRadius:{"DEFAULT":"0.25rem","lg":"0.5rem","xl":"0.75rem","full":"9999px"},spacing:{"space-xs":"0.25rem","gutter-mobile":"1rem","space-lg":"1.5rem","space-sm":"0.5rem","space-xl":"2.5rem","space-md":"1rem","margin-mobile":"1.25rem","margin":"3rem","gutter":"1.5rem"},fontFamily:{"display-xl-mobile":["Newsreader"],"label-sm":["Plus Jakarta Sans"],"body-md":["Plus Jakarta Sans"],"headline-lg":["Newsreader"],"headline-md":["Newsreader"],"headline-lg-mobile":["Newsreader"],"headline-sm":["Newsreader"],"body-lg":["Plus Jakarta Sans"],"label-md":["Plus Jakarta Sans"],"body-sm":["Plus Jakarta Sans"],"display-xl":["Newsreader"],"label-lg":["Plus Jakarta Sans"]},fontSize:{"display-xl-mobile":["38px",{"lineHeight":"44px","letterSpacing":"-0.01em","fontWeight":"500"}],"label-sm":["11px",{"lineHeight":"14px","letterSpacing":"0.04em","fontWeight":"700"}],"body-md":["15px",{"lineHeight":"24px","fontWeight":"400"}],"headline-lg":["40px",{"lineHeight":"48px","letterSpacing":"-0.015em","fontWeight":"600"}],"headline-md":["28px",{"lineHeight":"36px","fontWeight":"600"}],"headline-lg-mobile":["28px",{"lineHeight":"34px","letterSpacing":"-0.01em","fontWeight":"600"}],"headline-sm":["22px",{"lineHeight":"30px","fontWeight":"500"}],"body-lg":["18px",{"lineHeight":"28px","fontWeight":"400"}],"label-md":["12px",{"lineHeight":"16px","letterSpacing":"0.02em","fontWeight":"600"}],"body-sm":["13px",{"lineHeight":"20px","fontWeight":"400"}],"display-xl":["56px",{"lineHeight":"64px","letterSpacing":"-0.02em","fontWeight":"500"}],"label-lg":["14px",{"lineHeight":"20px","letterSpacing":"0.01em","fontWeight":"600"}]}}}};
    </script>
</head>
<body class="bg-surface text-on-surface min-h-screen flex flex-col font-body-md text-body-md">

    <!-- Memanggil Banner Identitas Kelompok (served_by.php) -->
    <div class="fixed top-0 left-0 right-0 w-full z-[60] bg-surface-container-low border-b-[1.5px] border-on-surface shadow-[0_2px_0px_#1c1b1b]">
        <?php 
        $served_by_path = __DIR__ . '/partials/served_by.php';
        if(file_exists($served_by_path)) {
            include $served_by_path;
        } else {
            echo '<div class="p-3 text-center text-sm font-bold">Dilayani oleh: '.htmlspecialchars(MEMBER_NAMA).' ('.htmlspecialchars(MEMBER_ROUTE).')</div>';
        }
        ?>
    </div>

    <header class="fixed top-12 left-0 right-0 w-full z-50 bg-surface/95 backdrop-blur-md border-b-[1.5px] border-on-surface shadow-[0_2px_0px_#1c1b1b]">
        <div class="h-20 max-w-[1200px] mx-auto px-gutter flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex flex-col">
                    <div class="flex items-center gap-1.5">
                        <span class="font-headline-sm text-headline-sm text-on-surface tracking-tight font-semibold">q&a room<span class="text-primary">.</span></span>
                        <span class="inline-block px-1.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container font-label-sm text-label-sm uppercase tracking-wider border-[1px] border-on-surface">✦</span>
                    </div>
                    <span class="font-label-sm text-label-sm text-on-surface-variant italic">Aplikasi Bersama Kelompok</span>
                </div>
            </div>
            
            <div class="flex items-center gap-3 sm:gap-4">
                <a class="hidden sm:flex items-center gap-2 text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-colors py-1 px-2" href="../index.php">
                    <span class="hidden lg:inline">← Kembali ke Personal Page</span>
                </a>
            </div>
        </div>
    </header>

    <main class="w-full pt-36 flex-grow bg-surface">
        <div class="max-w-[1200px] mx-auto px-gutter py-space-lg">
            <div class="flex flex-col w-full">
                <div class="w-full max-w-2xl mx-auto py-4 md:py-8">
                    
                    <div class="relative mb-6 p-4 rounded-xl bg-[#DDECCF] text-[#244822] border-[1.5px] border-on-surface shadow-[3px_3px_0px_#1c1b1b] flex items-start justify-between gap-3 transition-all duration-300" id="toastSuccess">
                        <div class="flex items-start gap-3">
                            <div class="w-7 h-7 rounded-full bg-[#244822] text-[#DDECCF] flex items-center justify-center shrink-0 text-sm font-bold shadow-[1px_1px_0px_#1c1b1b]">✓</div>
                            <div class="flex flex-col">
                                <span class="font-label-md text-label-md font-bold text-[#1c1b1b] tracking-wide">Pemberitahuan Sistem</span>
                                <p class="font-body-sm text-body-sm text-[#244822] mt-0.5">
                                    Pertanyaan akan direkam pada database server rute <strong class="uppercase"><?= htmlspecialchars(MEMBER_ROUTE) ?></strong>.
                                </p>
                            </div>
                        </div>
                        <button class="text-[#244822] hover:text-[#1c1b1b] p-1 rounded-full transition-colors leading-none font-bold" onclick="document.getElementById('toastSuccess').classList.add('hidden')" type="button">✕</button>
                    </div>

                    <section class="relative bg-surface-container-lowest border-[1.5px] border-on-surface rounded-2xl p-6 sm:p-10 shadow-[4px_4px_0px_#1c1b1b] overflow-hidden">
                        
                        <div class="space-y-2 mb-8">
                            <div class="inline-flex items-center gap-1.5 text-primary font-label-sm text-label-sm font-bold tracking-widest uppercase">
                                <span>✦</span>
                                <span>Ajukan Pertanyaan Baru</span>
                                <span>✧</span>
                            </div>
                            <h1 class="font-display-xl text-display-xl-mobile sm:text-display-xl text-on-surface tracking-tight leading-none italic font-medium">
                                What’s on your mind?
                            </h1>
                            <p class="font-body-md text-body-md text-on-surface-variant max-w-xl pt-1">
                                Tanyakan apapun seputar proses kreatif, tugas kuliah, atau obrolan santai. Saya, <strong><?= htmlspecialchars(MEMBER_NAMA) ?></strong>, akan menerima pertanyaan ini di database server lokal.
                            </p>
                        </div>

                        <!-- FORM ACTION DIUBAH KE proses_tanya.php, METHOD POST -->
                        <form class="space-y-6" id="askQuestionForm" action="proses_tanya.php" method="POST">
                            
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-1" for="senderName">
                                        Nama / Identitas Pengirim <span class="text-error font-bold">*</span>
                                    </label>
                                    <span class="font-label-sm text-label-sm text-on-surface-variant">Wajib diisi</span>
                                </div>
                                <input class="w-full bg-surface-container-lowest text-on-surface font-body-md text-body-md px-4 py-3 rounded-xl border-[1.5px] border-on-surface placeholder:text-outline/70 focus:outline-none focus:ring-0 focus:shadow-[2px_2px_0px_#1c1b1b] focus:border-on-surface transition-all" id="senderName" name="nama" placeholder="Contoh: Budi Santoso atau 'Anonim'" required type="text"/>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-1" for="senderEmail">
                                        Email Kamu <span class="font-label-sm text-label-sm text-on-surface-variant font-normal">(Opsional)</span>
                                    </label>
                                </div>
                                <input class="w-full bg-surface-container-lowest text-on-surface font-body-md text-body-md px-4 py-3 rounded-xl border-[1.5px] border-on-surface placeholder:text-outline/70 focus:outline-none focus:ring-0 focus:shadow-[2px_2px_0px_#1c1b1b] focus:border-on-surface transition-all" id="senderEmail" name="email" placeholder="nama@domain.com" type="email"/>
                            </div>

                            <div class="space-y-2">
                                <label class="font-label-md text-label-md text-on-surface font-semibold block">
                                    Topik Pertanyaan <span class="text-error font-bold">*</span>
                                </label>
                                <input id="selectedTopic" name="topik" type="hidden" value="Portofolio & Desain"/>
                                <div class="flex flex-wrap gap-2 pt-1" id="topicPillsContainer">
                                    <button class="topic-pill px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all bg-primary-container text-on-surface font-bold shadow-[2px_2px_0px_#1c1b1b]" onclick="selectTopic(this, 'Portofolio & Desain')" type="button">✦ Portofolio & Desain</button>
                                    <button class="topic-pill px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all bg-surface-container-lowest text-on-surface-variant hover:text-on-surface shadow-[1px_1px_0px_#1c1b1b]" onclick="selectTopic(this, 'Karir & Magang')" type="button">Karir & Magang</button>
                                    <button class="topic-pill px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all bg-surface-container-lowest text-on-surface-variant hover:text-on-surface shadow-[1px_1px_0px_#1c1b1b]" onclick="selectTopic(this, 'Kuliah & Skripsi')" type="button">Kuliah & Skripsi</button>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-1" for="questionBody">
                                        Pertanyaan Lengkap <span class="text-error font-bold">*</span>
                                    </label>
                                    <span class="font-label-sm text-label-sm text-on-surface-variant font-mono" id="charCounter">0/500</span>
                                </div>
                                <textarea class="w-full bg-surface-container-lowest text-on-surface font-body-md text-body-md p-4 rounded-xl border-[1.5px] border-on-surface placeholder:text-outline/70 focus:outline-none focus:ring-0 focus:shadow-[2px_2px_0px_#1c1b1b] focus:border-on-surface transition-all resize-y" id="questionBody" maxlength="500" minlength="10" name="isi_pertanyaan" oninput="handleCharCount(this)" placeholder="Tulis pertanyaanmu di sini..." required rows="5"></textarea>
                                <div class="flex justify-end text-on-surface-variant font-body-sm text-body-sm">
                                    <span class="font-label-sm text-label-sm text-outline-variant transition-colors" id="minValidationHint">Belum memenuhi minimal</span>
                                </div>
                            </div>

                            <div class="pt-4 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3.5 border-t-[1.5px] border-on-surface/20">
                                <button class="inline-flex items-center justify-center gap-2 font-label-lg text-label-lg px-7 py-3 rounded-full bg-primary-container text-on-surface font-bold border-[1.5px] border-on-surface shadow-[2px_2px_0px_#1c1b1b] hover:shadow-[4px_4px_0px_#1c1b1b] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[0px_0px_0px_#1c1b1b] transition-all cursor-pointer" type="submit">
                                    <span>Kirim Pertanyaan</span>
                                    <span class="text-base font-black">↗</span>
                                </button>
                            </div>

                            <a href="daftar-pertanyaan.php" class="answer-link">↗ Lihat Daftar Pertanyaan</a>

                        </form>

                    </section>

                    <!-- Modal Sukses Berbasis PHP / URL Parameter -->
                    <div class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" id="successModal">
                        <div class="bg-surface border-[1.5px] border-on-surface rounded-2xl max-w-md w-full p-6 sm:p-8 shadow-[6px_6px_0px_#1c1b1b] relative">
                            <div class="w-12 h-12 rounded-full bg-[#DDECCF] border-[1.5px] border-on-surface text-[#244822] flex items-center justify-center font-bold text-2xl shadow-[2px_2px_0px_#1c1b1b] mb-4">✓</div>
                            <div class="space-y-2">
                                <span class="inline-block px-2.5 py-0.5 rounded-full bg-secondary-container text-on-secondary-container font-label-sm text-label-sm border-[1px] border-on-surface">Status Terkirim</span>
                                <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Pertanyaanmu telah tercatat!</h2>
                                <p class="font-body-md text-body-md text-on-surface-variant">
                                    Data berhasil dimasukkan ke PostgreSQL dengan <code>route_id: <?= htmlspecialchars(MEMBER_ROUTE) ?></code>.
                                </p>
                            </div>
                            <div class="mt-6 pt-4 border-t-[1.5px] border-on-surface/20 flex flex-col sm:flex-row gap-3">
                                <button class="flex-1 py-2.5 px-4 rounded-full bg-primary-container text-on-surface font-label-md text-label-md font-bold border-[1.5px] border-on-surface shadow-[2px_2px_0px_#1c1b1b] text-center cursor-pointer" onclick="closeModal()">Tutup</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <footer class="w-full bg-surface-container-low border-t-[1.5px] border-on-surface mt-auto">
        <div class="max-w-[1200px] mx-auto px-gutter py-space-lg flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-on-surface-variant font-body-sm text-body-sm">
                <span class="text-primary">✦</span>
                <span>Proyek Kelompok Terdistribusi</span>
                <span class="text-outline-variant">•</span>
                <span>PHP & PostgreSQL</span>
            </div>
        </div>
    </footer>

    <script>
        function handleCharCount(textarea) {
            const len = textarea.value.length;
            const counter = document.getElementById('charCounter');
            const hint = document.getElementById('minValidationHint');
            counter.textContent = `${len}/500`;
            if (len >= 10) {
                hint.textContent = 'Minimal karakter terpenuhi ✓';
                hint.classList.remove('text-outline-variant');
                hint.classList.add('text-[#244822]', 'font-semibold');
            } else {
                hint.textContent = 'Minimal 10 karakter (' + (10 - len) + ' lagi)';
                hint.classList.remove('text-[#244822]', 'font-semibold');
                hint.classList.add('text-outline-variant');
            }
        }

        function selectTopic(buttonElement, topicValue) {
            document.getElementById('selectedTopic').value = topicValue;
            const allPills = document.querySelectorAll('.topic-pill');
            allPills.forEach(pill => {
                pill.className = "topic-pill px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all bg-surface-container-lowest text-on-surface-variant hover:text-on-surface shadow-[1px_1px_0px_#1c1b1b]";
                pill.textContent = pill.textContent.replace('✦ ', '');
            });
            buttonElement.className = "topic-pill px-3.5 py-1.5 rounded-full border-[1.5px] border-on-surface font-label-md text-label-md transition-all bg-primary-container text-on-surface font-bold shadow-[2px_2px_0px_#1c1b1b]";
            buttonElement.textContent = '✦ ' + topicValue;
        }

        function closeModal() {
            document.getElementById('successModal').classList.add('hidden');
            // Hapus parameter ?status=sukses dari URL agar jika di-refresh tidak pop-up lagi
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>

    <?php 
    // Trigger modal otomatis jika form berhasil disubmit
    if (isset($_GET['status']) && $_GET['status'] === 'sukses'): 
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('successModal').classList.remove('hidden');
        });
    </script>
    <?php endif; ?>

</body>
</html>