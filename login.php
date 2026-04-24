<?php
include 'config.php';
session_start();

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "Sila masukkan e-mel dan kata laluan.";
    } elseif (substr($email, -15) !== '@customs.gov.my') {
        $error_message = "Akses ditolak! Sila gunakan e-mel rasmi @customs.gov.my.";
    } else {
        // Semak dalam whitelist
        $stmt = $conn->prepare("SELECT role, nama_pegawai, gbpekema_id FROM " . TABLE_WHITELIST . " WHERE email = ?");
        if (!$stmt) {
            die("Database error (Prepare failed): " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $whitelist_result = $stmt->get_result();

        if ($whitelist_result->num_rows === 0) {
            $error_message = "E-mel anda tiada dalam senarai putih sistem. Sila hubungi pentadbir.";
        } else {
            $whitelist_user = $whitelist_result->fetch_assoc();
            
            // Semak kata laluan dalam jadual users
            $stmt_user = $conn->prepare("SELECT id, password, role, nama_pegawai FROM users WHERE email = ?");
            $stmt_user->bind_param("s", $email);
            $stmt_user->execute();
            $user_result = $stmt_user->get_result();

            if ($user_result->num_rows === 0) {
                $error_message = "Akaun belum didaftarkan. Sila <a href='register.php' class='underline font-bold'>daftar di sini</a>.";
            } else {
                $user = $user_result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Berjaya!
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['nama_pegawai'] = $user['nama_pegawai'];
                    $_SESSION['gbpekema_id'] = $whitelist_user['gbpekema_id'];
                    $_SESSION['profile_pic'] = $user['profile_pic'] ?? null;

                    // Kemas kini tarikh log masuk terakhir
                    $conn->query("UPDATE " . TABLE_WHITELIST . " SET last_login = NOW() WHERE email = '$email'");
                    $conn->query("UPDATE users SET last_login = NOW() WHERE email = '$email'");

                    header("Location: index.php");
                    exit();
                } else {
                    $error_message = "Kata laluan salah. Sila cuba lagi.";
                }
            }
            $stmt_user->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyPEKEMA - Intelligence Management Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #002d62;
            --primary-dark: #001f42;
            --secondary: #1d4ed8;
            --accent: #ffd700;
            --bg-glass: rgba(255, 255, 255, 0.85);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #f8fafc, #eff6ff);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .hero-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .glass-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
        }

        .feature-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            background: white;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .mesh-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.4;
            background-image:
                radial-gradient(at 0% 0%, rgba(0, 45, 98, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(29, 78, 216, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(255, 215, 0, 0.05) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(37, 99, 235, 0.1) 0px, transparent 50%);
        }

        .btn-premium {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: all 0.3s;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.4);
            filter: brightness(1.1);
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="text-slate-900">
    <div class="mesh-bg"></div>

    <!-- Navigation Header -->
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
                    <a href="#features"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-blue-700 hover:bg-white hover:shadow-sm rounded-xl transition-all duration-300">Ciri
                        Utama</a>
                    <a href="#about"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-blue-700 hover:bg-white hover:shadow-sm rounded-xl transition-all duration-300">Tentang</a>
                    <div class="w-px h-4 bg-slate-300/50 mx-1"></div>
                    <a href="manual.php"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-blue-700 hover:bg-white hover:shadow-sm rounded-xl transition-all duration-300">Manual</a>
                    <a href="terma.php"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-blue-700 hover:bg-white hover:shadow-sm rounded-xl transition-all duration-300">Terma</a>
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
                <a href="#login-section"
                    class="group relative px-6 py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm shadow-xl shadow-blue-900/10 hover:shadow-blue-900/20 hover:-translate-y-0.5 transition-all overflow-hidden border border-slate-800">
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-blue-600 to-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>
                    <span class="relative flex items-center gap-2">
                        Portal Rasmi <i
                            class="fas fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                    </span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-12 md:py-24">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">

            <!-- Left: Hero Content -->
            <div class="space-y-10">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-900 rounded-full text-xs font-bold tracking-widest uppercase border border-blue-100">
                    <span class="flex h-2 w-2 rounded-full bg-blue-800 animate-pulse"></span>
                    Versi 3.0 Generasi Seterusnya
                </div>

                <h1 class="text-5xl md:text-7xl font-black text-slate-900 leading-[1.1] tracking-tight">
                    Revolusi Pengurusan <span class="hero-gradient">Data GB Pekema</span>
                </h1>

                <p class="text-xl text-slate-500 leading-relaxed max-w-lg">
                    Platform perisikan termaju yang menggabungkan Analisis AI, Ramalan Pintar, dan Pemantauan Stok masa
                    nyata untuk keberkesanan operasi optimum.
                </p>

                <div class="flex flex-col sm:flex-row items-center gap-4">
                    <a href="#login-section"
                        class="w-full sm:w-auto px-8 py-4 bg-blue-900 text-white rounded-2xl font-bold text-lg btn-premium flex items-center justify-center gap-3">
                        Log Masuk Sistem <i class="fas fa-arrow-right"></i>
                    </a>
                    <p class="text-sm font-medium text-slate-400">
                        <i class="fas fa-shield-alt mr-2 text-blue-800"></i> Disah dan Diiktiraf oleh GB Pekema
                    </p>
                </div>

                <!-- Live Metrics (Animated) -->
                <div class="grid grid-cols-3 gap-8 pt-10 border-t border-slate-200">
                    <div>
                        <p class="text-4xl font-black text-slate-800">99.9<span class="text-blue-800">%</span></p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Ketepatan Data</p>
                    </div>
                    <div>
                        <p class="text-4xl font-black text-slate-800">24<span class="text-purple-600">/7</span></p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Pemantauan AI</p>
                    </div>
                    <div>
                        <p class="text-4xl font-black text-slate-800">10<span class="text-emerald-500">ms</span></p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Latency Rendah</p>
                    </div>
                </div>
            </div>

            <!-- Right: Login Card -->
            <div id="login-section" class="relative">
                <!-- Decorative Elements -->
                <div class="absolute -top-20 -right-10 w-64 h-64 bg-blue-900/10 blur-3xl rounded-full"></div>
                <div class="absolute -bottom-20 -left-10 w-64 h-64 bg-blue-600/10 blur-3xl rounded-full"></div>

                <div class="glass-panel p-10 md:p-14 rounded-[3rem] relative z-10 border border-white/40">
                    <div class="text-center mb-10">
                        <div
                            class="w-20 h-20 bg-gradient-to-tr from-blue-900 to-blue-700 rounded-3xl mx-auto flex items-center justify-center text-white text-3xl shadow-2xl mb-6 shadow-blue-200 animate-float">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <h2 class="text-3xl font-black text-slate-900">Portal Pegawai</h2>
                        <p class="text-slate-500 mt-2 font-medium">Sila log masuk menggunakan e-mel rasmi untuk
                            mengakses pusat arahan.</p>
                    </div>

                    <!-- Google SSO Integration -->
                    <div class="mb-8 flex flex-col items-center gap-4">
                        <div id="g_id_onload"
                            data-client_id="<?= $google_client_id ?>"
                            data-context="signin" 
                            data-ux_mode="popup" 
                            data-callback="handleCredentialResponse"
                            data-itp_support="true">
                        </div>

                        <div class="g_id_signin" 
                            data-type="standard" 
                            data-shape="pill"
                            data-theme="filled_blue" 
                            data-text="continue_with" 
                            data-size="large"
                            data-logo_alignment="left">
                        </div>
                        
                        <div class="flex items-center gap-4 w-full">
                            <div class="h-px bg-slate-200 flex-1"></div>
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Atau Log Masuk Manual</span>
                            <div class="h-px bg-slate-200 flex-1"></div>
                        </div>
                    </div>

                    <div id="error-message-sso" class="hidden mb-6 animate-shake bg-rose-50 border border-rose-100 text-rose-600 p-4 rounded-2xl text-center text-sm font-semibold"></div>

                    <!-- Manual Login Form -->
                    <form action="login.php" method="POST" class="space-y-5">
                        <input type="hidden" name="login" value="1">
                        
                        <div class="space-y-2">
                            <label for="email" class="text-xs font-bold text-slate-400 uppercase tracking-widest ml-1">E-mel Rasmi</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition-colors">
                                    <i class="fas fa-envelope text-sm"></i>
                                </div>
                                <input type="email" name="email" id="email" required 
                                    placeholder="nama@customs.gov.my"
                                    class="block w-full pl-11 pr-4 py-4 bg-white/50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 outline-none transition-all font-medium text-slate-700"
                                    pattern=".+@customs\.gov\.my$">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between ml-1">
                                <label for="password" class="text-xs font-bold text-slate-400 uppercase tracking-widest">Kata Laluan</label>
                                <a href="lupa_password.php" class="text-[10px] font-bold text-blue-600 hover:text-blue-800 uppercase tracking-wider">Lupa?</a>
                            </div>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition-colors">
                                    <i class="fas fa-lock text-sm"></i>
                                </div>
                                <input type="password" name="password" id="password" required 
                                    placeholder="••••••••"
                                    class="block w-full pl-11 pr-4 py-4 bg-white/50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 outline-none transition-all font-medium text-slate-700">
                            </div>
                        </div>

                        <?php if ($error_message): ?>
                        <div class="animate-shake bg-rose-50 border border-rose-100 text-rose-600 p-4 rounded-2xl text-center text-sm font-semibold">
                            <?= $error_message ?>
                        </div>
                        <?php endif; ?>

                        <button type="submit" 
                            class="w-full py-4 bg-blue-900 text-white rounded-2xl font-bold text-lg btn-premium flex items-center justify-center gap-3 mt-4">
                            Log Masuk <i class="fas fa-sign-in-alt"></i>
                        </button>

                        <div class="text-center mt-6">
                            <p class="text-xs font-medium text-slate-500">
                                Belum mempunyai akaun? 
                                <a href="register.php" class="text-blue-600 font-bold hover:underline">Daftar Sekarang</a>
                            </p>
                        </div>
                    </form>

                    <?php
                    $is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');
                    if ($is_localhost):
                        ?>
                        <!-- Simulation Mode (Local Only) -->
                        <div class="mt-8 pt-6 border-t border-slate-100">
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest text-center mb-4">
                                Simulation Mode (Testing Only)</p>
                            <div class="grid grid-cols-2 gap-3 pb-4">
                                <a href="_archive/simulate_auth.php?email=admin_test@customs.gov.my"
                                    class="text-[10px] font-bold py-2 px-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors text-center">
                                    Simulate Admin
                                </a>
                                <a href="_archive/simulate_auth.php?email=senior_test@customs.gov.my"
                                    class="text-[10px] font-bold py-2 px-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors text-center">
                                    Simulate Senior
                                </a>
                                <a href="_archive/simulate_auth.php?email=user_test@customs.gov.my"
                                    class="text-[10px] font-bold py-2 px-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors text-center">
                                    Simulate User
                                </a>
                                <a href="_archive/simulate_auth.php?email=supervisor_test@customs.gov.my"
                                    class="text-[10px] font-bold py-2 px-3 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors text-center">
                                    Simulate Supervisor
                                </a>
                            </div>
                            <p class="text-[9px] text-slate-400 text-center italic">Nota: Butang ini hanya muncul di
                                localhost untuk tujuan testing level pengguna.</p>
                        </div>
                    <?php endif; ?>

                    <div class="pt-6 border-t border-slate-100 mt-8">
                        <div class="flex items-center justify-center gap-6">
                            <i class="fas fa-envelope text-slate-200 text-2xl"></i>
                            <i class="fas fa-lock text-slate-200 text-2xl"></i>
                            <i class="fas fa-key text-slate-200 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Note -->
            <p class="text-center text-xs text-slate-400 mt-8 font-medium italic">
                <i class="fas fa-info-circle mr-1"></i> Sesi anda akan disulitkan secara end-to-end menggunakan
                piawaian AES-256.
            </p>

            <!-- Market News Feed Widget -->
            <div
                class="mt-8 glass-panel p-6 rounded-3xl border border-white/30 relative overflow-hidden group hover:bg-white/90 transition-all">
                <!-- Decor -->
                <div class="absolute -top-6 -right-6 w-20 h-20 bg-blue-100 rounded-full blur-2xl opacity-50"></div>

                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-black text-blue-900 uppercase tracking-widest flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                            </span>
                            Pasaran CBU Terkini
                        </h3>
                        <i class="fas fa-newspaper text-slate-300"></i>
                    </div>

                    <div class="space-y-3">
                        <!-- News Item 1 -->
                        <a href="https://www.google.com/search?q=harga+kereta+CBU+Malaysia+2025" target="_blank"
                            class="block group/item p-3 rounded-xl hover:bg-white/80 transition-all border border-transparent hover:border-slate-100 hover:shadow-sm">
                            <div class="flex items-center gap-2 mb-1">
                                <span
                                    class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Analisis</span>
                                <span class="text-[10px] font-medium text-slate-400">Baru Saja</span>
                            </div>
                            <p
                                class="text-sm font-bold text-slate-800 leading-snug group-hover/item:text-blue-700 transition-colors">
                                Kenaikan harga kenderaan CBU dijangka pada 2025 impak struktur cukai baharu.
                            </p>
                        </a>

                        <!-- News Item 2 -->
                        <a href="https://www.google.com/search?q=PEKEMA+unjuran+jualan+kereta+import+2025"
                            target="_blank"
                            class="block group/item p-3 rounded-xl hover:bg-white/80 transition-all border border-transparent hover:border-slate-100 hover:shadow-sm">
                            <div class="flex items-center gap-2 mb-1">
                                <span
                                    class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Trend</span>
                                <span class="text-[10px] font-medium text-slate-400">2 Jam lepas</span>
                            </div>
                            <p
                                class="text-sm font-bold text-slate-800 leading-snug group-hover/item:text-blue-700 transition-colors">
                                PEKEMA: Sasaran jualan kereta import terpakai cecah 50,000 unit.
                            </p>
                        </a>

                        <!-- News Item 3 -->
                        <a href="https://www.google.com/search?q=MITI+kereta+CBU+enjin+kecil" target="_blank"
                            class="block group/item p-3 rounded-xl hover:bg-white/80 transition-all border border-transparent hover:border-slate-100 hover:shadow-sm">
                            <div class="flex items-center gap-2 mb-1">
                                <span
                                    class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Polisi</span>
                                <span class="text-[10px] font-medium text-slate-400">Hari ini</span>
                            </div>
                            <p
                                class="text-sm font-bold text-slate-800 leading-snug group-hover/item:text-blue-700 transition-colors">
                                MITI pantau lambakan CBU enjin kecil demi lindungi industri tempatan.
                            </p>
                        </a>
                    </div>

                    <a href="https://www.google.com/search?q=berita+automotif+CBU+Malaysia+terkini" target="_blank"
                        class="block mt-4 text-center text-xs font-bold text-blue-600 hover:text-blue-800 uppercase tracking-widest transition-colors">
                        Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
        </div>

        <!-- Features Grid -->
        <section id="features" class="py-24 md:py-40">
            <div class="text-center mb-20 space-y-4">
                <h4 class="text-blue-900 font-black text-xs uppercase tracking-[0.3em]">Teknologi GB Pekema</h4>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900">Kenapa MyPEKEMA?</h2>
                <div class="w-24 h-1.5 bg-blue-900 mx-auto rounded-full"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="glass-panel p-10 rounded-[2.5rem] feature-card">
                    <div
                        class="w-14 h-14 bg-blue-50 text-blue-900 rounded-2xl flex items-center justify-center text-2xl mb-8 shadow-sm">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-4">Ramalan AI Pintar</h3>
                    <p class="text-slate-500 leading-relaxed font-medium">
                        Menggunakan algoritma regresi linear termaju untuk meramal trend kutipan cukai 6 bulan ke
                        hadapan dengan ketepatan tinggi.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="glass-panel p-10 rounded-[2.5rem] feature-card">
                    <div
                        class="w-14 h-14 bg-blue-50 text-blue-700 rounded-2xl flex items-center justify-center text-2xl mb-8 shadow-sm">
                        <i class="fas fa-search-dollar"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-4">Pengesanan Anomali</h3>
                    <p class="text-slate-500 leading-relaxed font-medium">
                        Sistem secara automatik mengesan ralat data atau ralat pengisytiharan cukai menerusi analisis
                        statistik masa nyata.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="glass-panel p-10 rounded-[2.5rem] feature-card">
                    <div
                        class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mb-8 shadow-sm">
                        <i class="fas fa-cube"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-4">Visualisasi 360&deg;</h3>
                    <p class="text-slate-500 leading-relaxed font-medium">
                        Dapatkan gambaran menyeluruh inventori dan prestasi syarikat melalui dashboard interaktif yang
                        dinamik dan responsif.
                    </p>
                </div>
            </div>
        </section>

        <!-- About & Manual Section -->
        <section id="about" class="py-24 md:py-32 border-t border-slate-100">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="relative">
                    <div class="absolute -top-10 -left-10 w-32 h-32 bg-blue-950/5 rounded-full blur-2xl"></div>
                    <img src="https://images.unsplash.com/photo-1551288049-bb8c803eroot?auto=format&fit=crop&q=80&w=800"
                        alt="Data Analytics" class="rounded-[3rem] shadow-2xl relative z-10 border-8 border-white">
                    <div
                        class="absolute -bottom-6 -right-6 bg-white p-6 rounded-3xl shadow-xl z-20 border border-slate-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-900 rounded-2xl flex items-center justify-center text-white">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Sistem Stabil</p>
                            <p class="text-lg font-bold text-slate-800">Uptime 99.9%</p>
                        </div>
                    </div>
                </div>
                <div class="space-y-8" id="manual">
                    <h4 class="text-blue-900 font-black text-xs uppercase tracking-[0.3em]">Tentang Sistem</h4>
                    <h2 class="text-4xl font-extrabold text-slate-900 leading-tight">Misi Perisikan Data Terpusat GB
                        Pekema</h2>
                    <p class="text-lg text-slate-500 leading-relaxed">
                        MyPEKEMA bukan sekadar pangkalan data, ia adalah ekosistem pintar yang dibina khusus untuk
                        memperkasakan ahli GB Pekema dengan automasi laporan, ramalan pertumbuhan, dan pengesanan risiko
                        fiskal secara proaktif.
                    </p>

                    <div class="p-8 bg-slate-900 rounded-[2.5rem] shadow-2xl text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-8 opacity-10">
                            <i class="fas fa-file-pdf text-8xl"></i>
                        </div>
                        <div class="relative z-10">
                            <h3 class="text-xl font-bold mb-2">Panduan Pengguna (User Manual)</h3>
                            <p class="text-slate-400 text-sm mb-6">Akses manual digital lengkap untuk mempelajari cara
                                menggunakan dashboard, memahami ramalan AI, dan mengurus inventori.</p>
                            <a href="manual.php"
                                class="inline-flex items-center gap-3 px-6 py-3 bg-blue-700 hover:bg-blue-600 text-white rounded-xl font-bold text-sm transition-all shadow-lg shadow-blue-500/20 uppercase tracking-widest">
                                <i class="fas fa-external-link-alt"></i> Buka Manual Digital
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-white border-t border-slate-100 py-12">
        <div class="container mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-2">
                <i class="fas fa-warehouse text-indigo-200"></i>
                <span class="text-sm font-bold text-slate-400 uppercase tracking-widest">&copy; Bahagian Perkastaman LTA
                    KL</span>
            </div>
            <div class="flex gap-8 text-xs font-bold text-slate-400 uppercase tracking-widest">
                <a href="#" class="hover:text-indigo-600 transition-colors">Dasar Privasi</a>
                <a href="terma.php" class="hover:text-indigo-600 transition-colors">Terma Perkhidmatan</a>
                <a href="#" class="hover:text-indigo-600 transition-colors">Log Sistem</a>
            </div>
        </div>
    </footer>

    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        function jwt_decode(token) {
            try {
                return JSON.parse(atob(token.split('.')[1]));
            } catch (e) {
                return null;
            }
        }

        function handleCredentialResponse(response) {
            const responsePayload = jwt_decode(response.credential);
            const errorDiv = document.getElementById('error-message-sso');

            if (responsePayload && responsePayload.email && responsePayload.email.endsWith('@customs.gov.my')) {
                fetch('google_auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: responsePayload.email,
                        name: responsePayload.name,
                        token: response.credential
                    }),
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'index.php';
                    } else {
                        showSSOError(data.message || 'Akses ditolak.');
                    }
                })
                .catch(err => {
                    showSSOError('Ralat sambungan ke pelayan.');
                });
            } else {
                showSSOError('Sila gunakan akaun rasmi @customs.gov.my sahaja.');
            }
        }

        function showSSOError(msg) {
            const el = document.getElementById('error-message-sso');
            el.textContent = msg;
            el.classList.remove('hidden');
        }
    </script>
</body>

</html>