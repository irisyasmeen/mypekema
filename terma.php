<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terma Perkhidmatan - MyPEKEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
</head>

<body class="bg-slate-50 text-slate-900 font-sans tracking-tight">

    <!-- Navigation Header (Enhanced) -->
    <nav class="sticky top-0 z-50 w-full backdrop-blur-xl bg-white/70 border-b border-white/40 transition-all duration-300 shadow-sm shadow-slate-200/50"
        id="navbar">
        <div class="container mx-auto px-6 py-3 flex items-center justify-between">

            <!-- Brand Identity -->
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="relative">
                    <div
                        class="absolute inset-0 bg-blue-600 blur-lg opacity-20 group-hover:opacity-40 transition-opacity rounded-xl">
                    </div>
                    <div
                        class="relative w-10 h-10 bg-gradient-to-br from-blue-900 to-blue-800 rounded-xl flex items-center justify-center text-white shadow-lg group-hover:scale-105 transition-transform duration-300 ring-1 ring-white/50">
                        <i class="fas fa-warehouse text-sm"></i>
                    </div>
                </div>
                <div class="flex flex-col">
                    <span
                        class="text-lg font-black tracking-tight text-slate-800 leading-none group-hover:text-blue-900 transition-colors">My<span
                            class="text-blue-700">PEKEMA</span></span>
                    <span
                        class="text-[10px] uppercase font-bold text-slate-500 tracking-widest leading-none mt-1 group-hover:text-blue-500 transition-colors">Management
                        Hub</span>
                </div>
            </a>

            <!-- Central Navigation (Desktop) -->
            <div class="hidden lg:flex items-center justify-center">
                <div
                    class="flex items-center gap-1 bg-slate-100/80 p-1.5 rounded-2xl border border-white/60 shadow-inner backdrop-blur-md">
                    <a href="login.php"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-blue-700 hover:bg-white hover:shadow-sm rounded-xl transition-all duration-300">Utama</a>
                    <a href="manual.php"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-blue-700 hover:bg-white hover:shadow-sm rounded-xl transition-all duration-300">Manual</a>
                    <a href="#"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-blue-700 bg-white shadow-sm rounded-xl transition-all duration-300">Terma</a>
                </div>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-4">
                <a href="mailto:support@customs.gov.my"
                    class="hidden md:flex items-center gap-2 text-xs font-bold text-slate-500 uppercase tracking-wider hover:text-blue-700 transition-colors group">
                    <div
                        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                        <i class="far fa-life-ring"></i>
                    </div>
                    <span>Sokongan</span>
                </a>
                <a href="login.php"
                    class="group relative px-6 py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm shadow-xl shadow-blue-900/10 hover:shadow-blue-900/20 hover:-translate-y-0.5 transition-all overflow-hidden border border-slate-800">
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-blue-600 to-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>
                    <span class="relative flex items-center gap-2">
                        Log Masuk <i
                            class="fas fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-12 md:py-20 max-w-4xl">
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-6">Terma Perkhidmatan</h1>
            <p class="text-lg text-slate-500 max-w-2xl mx-auto">Sila baca terma berikut dengan teliti sebelum
                menggunakan sistem MyPEKEMA.</p>
        </div>

        <div
            class="bg-white border border-slate-200 rounded-[2rem] p-8 md:p-12 shadow-xl shadow-slate-200/50 space-y-12">

            <!-- Section 1 -->
            <section class="group">
                <div class="flex items-start gap-6">
                    <div
                        class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 flex-shrink-0 group-hover:scale-110 transition-transform">
                        <span class="font-black text-xl">1</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 mb-4">Penerimaan Terma</h2>
                        <p class="text-slate-600 leading-relaxed">
                            Dengan mengakses, melayari, atau menggunakan sistem ini, anda mengakui bahawa anda telah
                            membaca, memahami, dan bersetuju untuk terikat dengan terma-terma ini. Jika anda tidak
                            bersetuju dengan mana-mana bahagian terma ini, anda tidak dibenarkan menggunakan sistem ini.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Section 2 -->
            <section class="group">
                <div class="flex items-start gap-6">
                    <div
                        class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 flex-shrink-0 group-hover:scale-110 transition-transform">
                        <span class="font-black text-xl">2</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 mb-4">Akses & Keselamatan Akaun</h2>
                        <p class="text-slate-600 leading-relaxed mb-4">
                            Penggunaan sistem ini dihadkan kepada kakitangan yang diberi kuasa sahaja. Anda
                            bertanggungjawab untuk:
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-slate-600">
                                <i class="fas fa-check-circle text-emerald-500"></i>
                                Mengekalkan kerahsiaan kata laluan dan akaun anda.
                            </li>
                            <li class="flex items-center gap-3 text-slate-600">
                                <i class="fas fa-check-circle text-emerald-500"></i>
                                Melaporkan sebarang akses tanpa kebenaran kepada pentadbir sistem dengan serta-merta.
                            </li>
                            <li class="flex items-center gap-3 text-slate-600">
                                <i class="fas fa-check-circle text-emerald-500"></i>
                                Log keluar dari sistem selepas selesai setiap sesi.
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Section 3 -->
            <section class="group">
                <div class="flex items-start gap-6">
                    <div
                        class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600 flex-shrink-0 group-hover:scale-110 transition-transform">
                        <span class="font-black text-xl">3</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 mb-4">Penggunaan Data & Kerahsiaan</h2>
                        <p class="text-slate-600 leading-relaxed">
                            Semua data yang terkandung dalam sistem ini adalah <strong>SULIT</strong> dan hak milik
                            Kerajaan Malaysia. Sebarang penyebaran, penyalinan, atau penggunaan data tanpa kebenaran
                            bertulis adalah dilarang keras dan boleh dikenakan tindakan undang-undang di bawah Akta
                            Rahsia Rasmi 1972.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Section 4 -->
            <section class="group">
                <div class="flex items-start gap-6">
                    <div
                        class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-600 flex-shrink-0 group-hover:scale-110 transition-transform">
                        <span class="font-black text-xl">4</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 mb-4">Penafian Liabiliti</h2>
                        <p class="text-slate-600 leading-relaxed">
                            Walaupun usaha terbaik diambil untuk memastikan ketepatan data sistem, pihak pentadbiran
                            tidak akan bertanggungjawab ke atas sebarang kerugian atau kerosakan yang timbul daripada
                            penggunaan maklumat yang terdapat dalam sistem ini. Keputusan kritikal hendaklah disahkan
                            melalui dokumen fizikal rasmi.
                        </p>
                    </div>
                </div>
            </section>

        </div>

        <div class="mt-12 text-center border-t border-slate-200 pt-8">
            <p class="text-slate-400 text-sm italic">
                Terakhir dikemaskini: <span class="font-bold text-slate-600"><?= date('d F Y') ?></span>
            </p>
        </div>

    </main>

    <footer class="bg-white border-t border-slate-200 py-8 mt-12">
        <div class="container mx-auto px-6 text-center">
            <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">&copy; <?= date('Y') ?> Bahagian
                Perkastaman LTA KL. Hak Cipta Terpelihara.</p>
        </div>
    </footer>

</body>

</html>