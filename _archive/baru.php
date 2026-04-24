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
        // Semak dalam whitelist (allowed_users)
        $stmt = $conn->prepare("SELECT role, nama_pegawai FROM allowed_users WHERE email = ?");
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

                    // Kemas kini tarikh log masuk terakhir
                    $conn->query("UPDATE allowed_users SET last_login = NOW() WHERE email = '$email'");
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
<html lang="ms" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyPEKEMA - Intelligence Management Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <style>
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
            position: relative;
            overflow-x: hidden;
        }

        .font-display {
            font-family: 'Space Grotesk', sans-serif;
        }

        /* Animated Background */
        .bg-animated {
            background: linear-gradient(-45deg, #0d9488, #14b8a6, #2dd4bf, #5eead4);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        /* Glass Morphism */
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }

        .glass-dark {
            background: rgba(30, 30, 50, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Floating Animation */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        /* Glow Effect */
        .glow {
            box-shadow: 0 0 20px rgba(20, 184, 166, 0.5),
                0 0 40px rgba(20, 184, 166, 0.3),
                0 0 60px rgba(20, 184, 166, 0.1);
        }

        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Hover Card Effect */
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hover-lift:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        /* Pulse Animation */
        @keyframes pulse-ring {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(20, 184, 166, 0.5);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(20, 184, 166, 0.8);
        }

        /* Fade In Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .delay-100 {
            animation-delay: 0.1s;
        }

        .delay-200 {
            animation-delay: 0.2s;
        }

        .delay-300 {
            animation-delay: 0.3s;
        }

        .delay-400 {
            animation-delay: 0.4s;
        }

        /* Shimmer Effect */
        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }

            100% {
                background-position: 1000px 0;
            }
        }

        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            background-size: 1000px 100%;
            animation: shimmer 3s infinite;
        }
    </style>
</head>

<body class="bg-animated text-slate-900">

    <!-- Navigation Bar -->
    <nav class="fixed top-0 left-0 right-0 z-50 glass border-b border-white/20">
        <div class="container mx-auto px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="relative">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-2xl blur-lg opacity-50 group-hover:opacity-75 transition-opacity">
                        </div>
                        <div
                            class="relative w-12 h-12 bg-gradient-to-br from-teal-600 to-cyan-700 rounded-2xl flex items-center justify-center text-white shadow-xl">
                            <i class="fas fa-warehouse text-xl"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="font-display font-bold text-2xl text-slate-900 leading-none">
                            Gudang<span class="gradient-text">Sys</span>
                        </h1>
                        <p class="text-[10px] font-bold text-teal-600 uppercase tracking-[0.2em] leading-none">
                            Intelligence Hub</p>
                    </div>
                </a>

                <!-- Desktop Menu -->
                <div
                    class="hidden md:flex items-center gap-2 bg-white/50 backdrop-blur-sm px-2 py-2 rounded-full border border-white/30">
                    <a href="#features"
                        class="px-5 py-2 text-sm font-bold text-slate-600 hover:text-teal-700 hover:bg-white rounded-full transition-all">Ciri
                        Utama</a>
                    <a href="#about"
                        class="px-5 py-2 text-sm font-bold text-slate-600 hover:text-teal-700 hover:bg-white rounded-full transition-all">Tentang</a>
                    <a href="manual.php"
                        class="px-5 py-2 text-sm font-bold text-slate-600 hover:text-teal-700 hover:bg-white rounded-full transition-all">Manual</a>
                    <a href="terma.php"
                        class="px-5 py-2 text-sm font-bold text-slate-600 hover:text-teal-700 hover:bg-white rounded-full transition-all">Terma</a>
                </div>

                <!-- CTA Button -->
                <a href="#login-section"
                    class="hidden md:flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-teal-600 to-cyan-600 text-white rounded-full font-bold text-sm shadow-lg hover:shadow-xl hover:scale-105 transition-all">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Portal Rasmi</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-32 pb-20 px-6">
        <div class="container mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">

                <!-- Left: Content -->
                <div class="space-y-8 fade-in-up">
                    <!-- Badge -->
                    <div
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white/90 backdrop-blur-sm rounded-full border border-indigo-200 shadow-lg">
                        <span class="flex h-2 w-2 rounded-full bg-green-500 pulse-ring"></span>
                        <span class="text-xs font-bold text-indigo-700 uppercase tracking-wider">Versi 3.0 • Generasi
                            Baharu</span>
                    </div>

                    <!-- Headline -->
                    <div>
                        <h1
                            class="font-display font-black text-6xl lg:text-7xl xl:text-8xl leading-[0.9] text-white mb-6">
                            Revolusi<br />
                            <span
                                class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-200 via-pink-200 to-purple-200">
                                Data Pintar
                            </span>
                        </h1>
                        <p class="text-xl text-white/90 leading-relaxed max-w-xl font-medium">
                            Platform perisikan terpusat yang menggabungkan <strong>Analisis AI</strong>, <strong>Ramalan
                                Pintar</strong>, dan <strong>Pemantauan Stok</strong> masa nyata untuk memacu
                            keberkesanan operasi GB Pekema.
                        </p>
                    </div>

                    <!-- CTA Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="#login-section"
                            class="group relative px-8 py-4 bg-white text-indigo-700 rounded-2xl font-bold text-lg shadow-2xl hover:shadow-3xl transition-all overflow-hidden">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-indigo-600 to-purple-600 opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <span
                                class="relative flex items-center justify-center gap-3 group-hover:text-white transition-colors">
                                <i class="fas fa-fingerprint"></i>
                                Akses Portal
                            </span>
                        </a>
                        <a href="manual.php"
                            class="px-8 py-4 bg-white/10 backdrop-blur-sm text-white border-2 border-white/30 rounded-2xl font-bold text-lg hover:bg-white/20 transition-all flex items-center justify-center gap-3">
                            <i class="fas fa-book-open"></i>
                            Pelajari Sistem
                        </a>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-6 pt-8">
                        <div class="text-center">
                            <p class="font-display font-black text-4xl text-white mb-1">99.9%</p>
                            <p class="text-xs font-bold text-white/70 uppercase tracking-wider">Ketepatan</p>
                        </div>
                        <div class="text-center">
                            <p class="font-display font-black text-4xl text-white mb-1">24/7</p>
                            <p class="text-xs font-bold text-white/70 uppercase tracking-wider">Pemantauan</p>
                        </div>
                        <div class="text-center">
                            <p class="font-display font-black text-4xl text-white mb-1">10ms</p>
                            <p class="text-xs font-bold text-white/70 uppercase tracking-wider">Latency</p>
                        </div>
                    </div>
                </div>

                <!-- Right: Login & News Module -->
                <div class="space-y-6 fade-in-up delay-200" id="login-section">

                    <!-- Login Card -->
                    <div class="glass rounded-[2.5rem] p-8 lg:p-10 hover-lift">
                        <div class="text-center space-y-6">
                            <!-- Icon -->
                            <div class="inline-flex">
                                <div
                                    class="w-20 h-20 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-3xl flex items-center justify-center text-white text-3xl shadow-2xl animate-float glow">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                            </div>

                            <!-- Title -->
                            <div>
                                <h2 class="font-display font-bold text-3xl text-slate-900 mb-2">Portal Pegawai</h2>
                                <p class="text-slate-600 font-medium">Sistem ini dikhaskan untuk kakitangan berdaftar
                                    sahaja.</p>
                            </div>

                            <!-- Google SSO Integration -->
                            <div class="space-y-4">
                                <div id="g_id_onload"
                                    data-client_id="<?= $google_client_id ?>"
                                    data-context="signin" 
                                    data-ux_mode="popup" 
                                    data-callback="handleCredentialResponse"
                                    data-itp_support="true">
                                </div>

                                <div class="g_id_signin flex justify-center" 
                                    data-type="standard" 
                                    data-shape="pill"
                                    data-theme="outline" 
                                    data-text="continue_with" 
                                    data-size="large"
                                    data-logo_alignment="left">
                                </div>
                                
                                <div class="flex items-center gap-4 py-2">
                                    <div class="h-px bg-slate-200/50 flex-1"></div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Atau Manual</span>
                                    <div class="h-px bg-slate-200/50 flex-1"></div>
                                </div>
                            </div>

                            <div id="error-message-sso" class="hidden mb-4 bg-red-50 border border-red-100 text-red-700 px-4 py-2 rounded-xl text-xs font-bold text-center"></div>

                            <!-- Manual Login Form -->
                            <form action="baru.php" method="POST" class="space-y-4 text-left">
                                <input type="hidden" name="login" value="1">
                                
                                <div class="space-y-1">
                                    <label for="email" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">E-mel Jabatan</label>
                                    <div class="relative group">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-teal-600 transition-colors">
                                            <i class="fas fa-envelope text-xs"></i>
                                        </div>
                                        <input type="email" name="email" id="email" required 
                                            placeholder="nama@customs.gov.my"
                                            class="block w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-teal-600/20 focus:border-teal-600 outline-none transition-all text-sm font-medium text-slate-700"
                                            pattern=".+@customs\.gov\.my$">
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex items-center justify-between ml-1">
                                        <label for="password" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Kata Laluan</label>
                                        <a href="lupa_password.php" class="text-[9px] font-bold text-teal-600 hover:text-teal-800 uppercase tracking-wider">Lupa?</a>
                                    </div>
                                    <div class="relative group">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-teal-600 transition-colors">
                                            <i class="fas fa-lock text-xs"></i>
                                        </div>
                                        <input type="password" name="password" id="password" required 
                                            placeholder="••••••••"
                                            class="block w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-teal-600/20 focus:border-teal-600 outline-none transition-all text-sm font-medium text-slate-700">
                                    </div>
                                </div>

                                <?php if ($error_message): ?>
                                <div class="bg-red-50 border-2 border-red-100 text-red-700 px-4 py-3 rounded-xl text-xs font-bold text-center">
                                    <?= $error_message ?>
                                </div>
                                <?php endif; ?>

                                <button type="submit" 
                                    class="w-full py-3 bg-gradient-to-r from-teal-600 to-cyan-600 text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2 mt-2">
                                    <span>Log Masuk</span>
                                    <i class="fas fa-sign-in-alt text-xs"></i>
                                </button>

                                <div class="text-center mt-4">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        Belum berdaftar? 
                                        <a href="register.php" class="text-teal-600 hover:underline">Cipta Akaun</a>
                                    </p>
                                </div>
                            </form>

                            <!-- Security Icons -->
                            <div class="flex items-center justify-center gap-6 pt-4 opacity-40">
                                <i class="fas fa-envelope text-2xl"></i>
                                <i class="fas fa-shield-alt text-2xl"></i>
                                <i class="fas fa-lock text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- News Feed Card -->
                    <div class="glass rounded-3xl overflow-hidden hover-lift">
                        <!-- Header -->
                        <div
                            class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="relative flex h-3 w-3">
                                    <span
                                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                                </span>
                                <h3 class="font-bold text-white uppercase tracking-wider text-sm">Pasaran CBU Terkini
                                </h3>
                            </div>
                            <span
                                class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-[10px] font-bold text-white uppercase">Live</span>
                        </div>

                        <!-- News Items -->
                        <div class="p-6 space-y-4">
                            <!-- Item 1 -->
                            <a href="https://www.google.com/search?q=harga+kereta+CBU+Malaysia+2025" target="_blank"
                                class="flex gap-4 p-4 rounded-xl hover:bg-indigo-50 transition-all group">
                                <div
                                    class="shrink-0 w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                    <i class="fas fa-chart-line text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <h4
                                        class="font-bold text-slate-900 text-sm mb-1 group-hover:text-indigo-700 transition-colors">
                                        Kenaikan harga CBU 2025 impak struktur cukai baharu</h4>
                                    <p class="text-xs text-slate-500 font-medium">Analisis • Baru Saja</p>
                                </div>
                            </a>

                            <!-- Item 2 -->
                            <a href="https://www.google.com/search?q=PEKEMA+unjuran+jualan+kereta+import+2025"
                                target="_blank"
                                class="flex gap-4 p-4 rounded-xl hover:bg-emerald-50 transition-all group">
                                <div
                                    class="shrink-0 w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                                    <i class="fas fa-bullseye text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <h4
                                        class="font-bold text-slate-900 text-sm mb-1 group-hover:text-emerald-700 transition-colors">
                                        PEKEMA sasaran 50,000 unit tahun 2025</h4>
                                    <p class="text-xs text-slate-500 font-medium">Trend • 2 Jam lepas</p>
                                </div>
                            </a>

                            <!-- Item 3 -->
                            <a href="https://www.google.com/search?q=MITI+kereta+CBU+enjin+kecil" target="_blank"
                                class="flex gap-4 p-4 rounded-xl hover:bg-amber-50 transition-all group">
                                <div
                                    class="shrink-0 w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-all">
                                    <i class="fas fa-gavel text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <h4
                                        class="font-bold text-slate-900 text-sm mb-1 group-hover:text-amber-700 transition-colors">
                                        MITI pantau lambakan CBU enjin kecil</h4>
                                    <p class="text-xs text-slate-500 font-medium">Polisi • Hari ini</p>
                                </div>
                            </a>
                        </div>

                        <!-- Footer -->
                        <button
                            onclick="window.open('https://www.google.com/search?q=berita+automotif+CBU+Malaysia+terkini', 'NewsPopup', 'width=1000,height=800,scrollbars=yes,resizable=yes')"
                            class="w-full py-4 bg-slate-50 hover:bg-slate-100 text-indigo-600 font-bold text-sm uppercase tracking-wider transition-all border-t border-slate-200">
                            Lihat Semua Berita <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 px-6">
        <div class="container mx-auto">
            <!-- Section Header -->
            <div class="text-center mb-16 fade-in-up">
                <h2 class="font-display font-black text-5xl lg:text-6xl text-white mb-4">
                    Teknologi Pintar, Keputusan Tepat
                </h2>
                <p class="text-xl text-white/80 max-w-2xl mx-auto">
                    Disokong oleh enjin analitik pintar yang memproses data raya untuk memberi gambaran jelas masa
                    depan.
                </p>
            </div>

            <!-- Features Grid -->
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="glass rounded-[2rem] p-8 hover-lift fade-in-up delay-100">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center text-white text-3xl mb-6 shadow-xl">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3 class="font-display font-bold text-2xl text-slate-900 mb-3">AI Prediktif</h3>
                    <p class="text-slate-600 leading-relaxed">
                        Algoritma pembelajaran mesin yang meramal trend pasaran dan impak cukai secara automatik dengan
                        ketepatan tinggi.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="glass rounded-[2rem] p-8 hover-lift fade-in-up delay-200">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-600 rounded-2xl flex items-center justify-center text-white text-3xl mb-6 shadow-xl">
                        <i class="fas fa-search-dollar"></i>
                    </div>
                    <h3 class="font-display font-bold text-2xl text-slate-900 mb-3">Pengesanan Anomali</h3>
                    <p class="text-slate-600 leading-relaxed">
                        Sistem amaran awal untuk mengesan ketirisan hasil atau pengisytiharan yang mencurigakan secara
                        real-time.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="glass rounded-[2rem] p-8 hover-lift fade-in-up delay-300">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center text-white text-3xl mb-6 shadow-xl">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3 class="font-display font-bold text-2xl text-slate-900 mb-3">Dashboard 360°</h3>
                    <p class="text-slate-600 leading-relaxed">
                        Visualisasi data interaktif masa nyata untuk pemantauan prestasi menyeluruh dengan paparan
                        intuitif.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 px-6">
        <div class="container mx-auto">
            <div class="glass rounded-[3rem] p-12 lg:p-16 fade-in-up">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <!-- Left: Image -->
                    <div class="relative">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-3xl blur-2xl opacity-30">
                        </div>
                        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&q=80&w=800"
                            alt="Analytics Dashboard" class="relative rounded-3xl shadow-2xl border-8 border-white">
                    </div>

                    <!-- Right: Content -->
                    <div class="space-y-6">
                        <div>
                            <span
                                class="inline-block px-4 py-2 bg-indigo-100 text-indigo-700 rounded-full text-xs font-bold uppercase tracking-wider mb-4">Tentang
                                Sistem</span>
                            <h2 class="font-display font-black text-4xl lg:text-5xl text-slate-900 mb-4">
                                Misi Perisikan Data Terpusat GB Pekema
                            </h2>
                            <p class="text-lg text-slate-600 leading-relaxed">
                                MyPEKEMA bukan sekadar pangkalan data, ia adalah <strong>ekosistem pintar</strong> yang
                                dibina khusus untuk memperkasakan ahli GB Pekema dengan automasi laporan, ramalan
                                pertumbuhan, dan pengesanan risiko fiskal secara proaktif.
                            </p>
                        </div>

                        <!-- Manual CTA -->
                        <div
                            class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl p-8 text-white relative overflow-hidden">
                            <div class="absolute top-0 right-0 opacity-10">
                                <i class="fas fa-file-pdf text-9xl"></i>
                            </div>
                            <div class="relative z-10">
                                <h3 class="font-display font-bold text-2xl mb-2">Panduan Pengguna</h3>
                                <p class="text-slate-300 mb-6">Akses manual digital lengkap untuk mempelajari cara
                                    menggunakan dashboard dan memahami ramalan AI.</p>
                                <a href="manual.php"
                                    class="inline-flex items-center gap-3 px-6 py-3 bg-white text-slate-900 rounded-xl font-bold hover:bg-slate-100 transition-all shadow-lg">
                                    <i class="fas fa-book-open text-indigo-600"></i>
                                    Buka Manual Digital
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 px-6 border-t border-white/20">
        <div class="container mx-auto">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center">
                        <i class="fas fa-warehouse text-indigo-600 text-lg"></i>
                    </div>
                    <span class="text-sm font-bold text-white/80 uppercase tracking-wider">
                        &copy; 2026 Bahagian Perkastaman LTA KL
                    </span>
                </div>
                <div class="flex gap-8">
                    <a href="#"
                        class="text-sm font-bold text-white/60 hover:text-white uppercase tracking-wider transition-colors">Privasi</a>
                    <a href="terma.php"
                        class="text-sm font-bold text-white/60 hover:text-white uppercase tracking-wider transition-colors">Terma</a>
                    <a href="mailto:support@customs.gov.my"
                        class="text-sm font-bold text-white/60 hover:text-white uppercase tracking-wider transition-colors">Sokongan</a>
                </div>
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
            if (el) {
                el.textContent = msg;
                el.classList.remove('hidden');
            }
        }
    </script>
</body>

</html>