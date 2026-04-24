<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Pengguna - MyPEKEMA Intelligence</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #a855f7;
            --accent: #10b981;
            --bg-glass: rgba(255, 255, 255, 0.9);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }

        .glass-sidebar {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-right: 1px solid rgba(0, 0, 0, 0.05);
        }

        .glass-card {
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .nav-link {
            transition: all 0.2s;
            border-radius: 0.75rem;
        }

        .nav-link:hover {
            background: rgba(99, 102, 241, 0.05);
            color: var(--primary);
        }

        .nav-link.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .step-number {
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: 800;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        code {
            background: #f1f5f9;
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            font-family: monospace;
            color: #e11d48;
        }

        .ai-badge {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            color: white;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        [x-cloak] {
            display: none !important;
        }

        html {
            scroll-behavior: smooth;
        }
    </style>
</head>

<body class="overflow-x-hidden bg-slate-50">

    <!-- Global Navigation -->
    <nav
        class="fixed top-0 z-[60] w-full bg-white/80 backdrop-blur-md border-b border-slate-200/60 transition-all duration-300 h-[74px] flex items-center shadow-sm">
        <div class="container mx-auto px-6 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3 group">
                <div
                    class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg group-hover:scale-105 transition-transform">
                    <i class="fas fa-warehouse text-sm"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-lg font-black tracking-tight text-slate-800 leading-none">My<span
                            class="text-indigo-600">PEKEMA</span></span>
                    <span
                        class="text-[10px] uppercase font-bold text-slate-500 tracking-widest leading-none mt-1">Manual
                        Digital</span>
                </div>
            </a>

            <div class="hidden lg:flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200">
                <a href="login.php"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-indigo-600 hover:bg-white hover:shadow-sm rounded-lg transition-all">Utama</a>
                <a href="#"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-indigo-600 bg-white shadow-sm rounded-lg transition-all">Manual</a>
                <a href="terma.php"
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-indigo-600 hover:bg-white hover:shadow-sm rounded-lg transition-all">Terma</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="mailto:support@customs.gov.my"
                    class="hidden md:block text-xs font-bold text-slate-500 uppercase tracking-wider hover:text-indigo-600 transition-colors">Sokongan</a>
                <a href="login.php"
                    class="px-5 py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-slate-800 transition-all shadow-lg shadow-indigo-900/10">
                    Log Masuk
                </a>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen">
        <!-- Sidebar Navigation -->
        <aside class="w-80 glass-sidebar fixed top-[74px] bottom-0 left-0 z-40 hidden lg:block p-8 overflow-y-auto">
            <div class="flex items-center gap-3 mb-12">
                <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-warehouse text-lg"></i>
                </div>
                <div>
                    <h1 class="font-extrabold text-xl tracking-tight text-slate-800">My<span
                            class="text-indigo-600">PEKEMA</span></h1>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Manual Digital</p>
                </div>
            </div>

            <nav class="space-y-2">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4 pl-4">Pengenalan</p>
                <a href="#overview"
                    class="nav-link flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-600 active">
                    <i class="fas fa-info-circle w-5"></i> Gambaran Keseluruhan
                </a>
                <a href="#getting-started"
                    class="nav-link flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-600">
                    <i class="fas fa-rocket w-5"></i> Permulaan Pantas
                </a>

                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] pt-6 mb-4 pl-4">Ciri Utama
                </p>
                <a href="#dashboard"
                    class="nav-link flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-600">
                    <i class="fas fa-th-large w-5"></i> Dashboard Utama
                </a>
                <a href="#tax-analysis"
                    class="nav-link flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-600">
                    <i class="fas fa-calculator w-5"></i> Analisa Cukai
                </a>
                <a href="#ai-insights"
                    class="nav-link flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-600">
                    <i class="fas fa-brain w-5"></i> AI Deep Insights <span class="ai-badge">New</span>
                </a>

                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] pt-6 mb-4 pl-4">Data &
                    Laporan</p>
                <a href="#export" class="nav-link flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-600">
                    <i class="fas fa-file-export w-5"></i> Eksport Laporan
                </a>
                <a href="#anomalies"
                    class="nav-link flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-600">
                    <i class="fas fa-shield-virus w-5"></i> Pengesanan Anomali
                </a>
            </nav>

            <div class="absolute bottom-8 left-8 right-8">
                <a href="index.php"
                    class="w-full py-4 bg-slate-900 text-white rounded-2xl font-bold text-sm flex items-center justify-center gap-3 hover:bg-slate-800 transition-all">
                    <i class="fas fa-chevron-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 lg:ml-80 pt-[100px] p-6 md:p-12 lg:p-20 max-w-5xl">


            <!-- Overview Section -->
            <section id="overview" class="scroll-mt-32">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-full text-[10px] font-black tracking-widest uppercase mb-6 border border-indigo-100">
                    Dokumentasi Rasmi
                </div>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-8 tracking-tight">Gambaran Keseluruhan</h2>
                <p class="text-xl text-slate-500 leading-relaxed mb-12">
                    <strong class="text-slate-900">MyPEKEMA Management Hub</strong> dirancang untuk memberikan
                    visibilitas penuh terhadap inventori kenderaan dan prestasi cukai GB Pekema. Sistem ini menggunakan
                    teknologi kecerdasan buatan (AI) untuk memberikan wawasan yang tidak mungkin dicapai melalui
                    pelaporan manual.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-20">
                    <div class="glass-card p-8 rounded-[2rem]">
                        <div
                            class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl mb-6">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3 class="text-xl font-extrabold mb-4">Akses Selamat</h3>
                        <p class="text-slate-500 leading-relaxed">Sistem hanya boleh diakses melalui emel rasmi
                            <code>@customs.gov.my</code> dengan tahap penyulitan AES-256.</p>
                    </div>
                    <div class="glass-card p-8 rounded-[2rem]">
                        <div
                            class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-xl mb-6">
                            <i class="fas fa-magic"></i>
                        </div>
                        <h3 class="text-xl font-extrabold mb-4">Automasi AI</h3>
                        <p class="text-slate-500 leading-relaxed">Ramalan kutipan dan pengesanan anomali berjalan secara
                            latar belakang untuk memastikan kualiti data.</p>
                    </div>
                </div>
            </section>

            <!-- Dashboard Section -->
            <section id="dashboard" class="scroll-mt-32 pt-20 border-t border-slate-100">
                <h2 class="text-3xl font-black text-slate-900 mb-8 tracking-tight flex items-center gap-4">
                    <i class="fas fa-th-large text-indigo-500"></i> Dashboard Utama
                </h2>
                <p class="text-lg text-slate-500 leading-relaxed mb-10">
                    Dashboard Utama (Ringkasan) memberikan gambaran visual segera terhadap KPI (Key Performance
                    Indicators) kritikal sistem.
                </p>

                <div class="space-y-6">
                    <div class="flex gap-6 p-6 bg-white border border-slate-100 rounded-3xl shadow-sm">
                        <div class="step-number">01</div>
                        <div>
                            <h4 class="font-bold text-slate-900 mb-2">Kad Ringkasan (Stats Cards)</h4>
                            <p class="text-slate-500 text-sm leading-relaxed">Terletak di bahagian atas, menunjukkan
                                Jumlah Cukai Terkumpul, Bilangan Kenderaan, dan Jumlah Ahli GB Pekema. Angka dalam
                                "millions" dipendekkan (cth: 1.2M) untuk kejelasan visual.</p>
                        </div>
                    </div>
                    <div class="flex gap-6 p-6 bg-white border border-slate-100 rounded-3xl shadow-sm">
                        <div class="step-number">02</div>
                        <div>
                            <h4 class="font-bold text-slate-900 mb-2">Market Share Pie Chart</h4>
                            <p class="text-slate-500 text-sm leading-relaxed">Graf bahagian pasaran menunjukkan pecahan
                                kenderaan mengikut syarikat. Klik pada legenda graf untuk menapis data visual.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- AI Insights Section -->
            <section id="ai-insights" class="scroll-mt-32 pt-20 mb-20">
                <div class="p-10 md:p-14 bg-slate-900 rounded-[3rem] text-white overflow-hidden relative shadow-2xl">
                    <div class="absolute -right-20 -top-20 w-80 h-80 bg-indigo-500/20 blur-[100px] rounded-full"></div>

                    <div class="relative z-10 w-full mb-10">
                        <span class="ai-badge mb-4 inline-block">Teknologi Termaju</span>
                        <h2 class="text-4xl font-black mb-6 tracking-tight">AI Deep Insights</h2>
                        <p class="text-slate-400 text-lg leading-relaxed max-w-2xl">
                            Bahagian ini adalah "otak" kepada MyPEKEMA. Ia menggunakan pembelajaran mesin (Machine
                            Learning) untuk menganalisis corak yang kompleks.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative z-10">
                        <div class="p-8 bg-white/5 rounded-3xl border border-white/10">
                            <h4 class="font-bold text-xl mb-3 text-indigo-300">Advanced Forecast</h4>
                            <p class="text-slate-400 text-sm leading-relaxed">MyPEKEMA meramalkan kutipan cukai untuk 6
                                bulan akan datang menggunakan <code>Linear Regression</code>. Garis putus-putus ungu
                                menunjukkan ramalan masa depan.</p>
                        </div>
                        <div class="p-8 bg-white/5 rounded-3xl border border-white/10">
                            <h4 class="font-bold text-xl mb-3 text-emerald-300">Anomaly Radar</h4>
                            <p class="text-slate-400 text-sm leading-relaxed">Sistem membandingkan setiap bayaran dengan
                                purata sejarah. Jika bayaran melebihi purata (di atas 100%), ralat anomali akan
                                dibangkitkan untuk semakan.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Export Section -->
            <section id="export" class="scroll-mt-32 pt-20 border-t border-slate-100">
                <h2 class="text-3xl font-black text-slate-900 mb-8 tracking-tight">Eksport & Pelaporan</h2>
                <div
                    class="bg-indigo-50 p-8 rounded-[2.5rem] border border-indigo-100 flex flex-col md:flex-row items-center gap-10">
                    <div class="p-6 bg-white rounded-3xl shadow-xl shadow-indigo-100">
                        <i class="fas fa-file-csv text-5xl text-indigo-600"></i>
                    </div>
                    <div>
                        <h4 class="text-2xl font-black text-slate-800 mb-3">Satu Klik Laporan CSV</h4>
                        <p class="text-slate-600 leading-relaxed">Fungsi <strong>"Export Lapuran"</strong> di dashboard
                            akan menjana fail CSV yang mengandungi ringkasan prestasi syarikat, dominasi model, dan
                            senarai anomali yang dikesan untuk kegunaan pengurusan dalam mesyuarat atau audit.</p>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="mt-40 pt-12 border-t border-slate-100 pb-12">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">&copy; 2026 Manual MyPEKEMA
                        v3.2</p>
                    <div class="flex gap-8">
                        <a href="#"
                            class="text-xs font-black text-slate-400 uppercase hover:text-indigo-600">Sokongan</a>
                        <a href="#"
                            class="text-xs font-black text-slate-400 uppercase hover:text-indigo-600">Kemaskini</a>
                    </div>
                </div>
            </footer>
        </main>
    </div>

</body>

</html>