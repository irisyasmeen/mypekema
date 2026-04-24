<?php
session_start();
// Pastikan laluan ini betul
include 'config.php';

// Semak jika pengguna telah log masuk, jika tidak, halakan ke halaman log masuk
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Dapatkan maklumat pengguna daripada sesi
$user_name = $_SESSION['nama_pegawai'] ?? 'Pengguna Tidak Dikenali';
$user_email = $_SESSION['user_email'] ?? 'Tiada E-mel';
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f5ff;
            /* bg-blue-50 */
        }
    </style>
</head>

<body class="bg-blue-50">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="max-w-3xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-blue-900">Profil Saya</h1>
                <p class="text-blue-700 mt-1">Uruskan maklumat akaun anda yang dipautkan dengan Google.</p>
            </div>

            <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-blue-100">
                <!-- Bahagian Maklumat Akaun -->
                <div class="p-6 md:p-8">
                    <h2 class="text-xl font-bold text-blue-800 flex items-center mb-6">
                        <i class="fas fa-user-circle text-blue-500 mr-3"></i>
                        Maklumat Akaun
                    </h2>
                    <div class="flex flex-col md:flex-row items-center gap-8 mb-8 pb-8 border-b border-gray-100">
                        <div class="relative group">
                            <div class="w-32 h-32 rounded-full overflow-hidden border-4 border-white shadow-xl group-hover:opacity-75 transition-all">
                                <?php 
                                $profile_pic = $_SESSION['profile_pic'] ?? null;
                                if ($profile_pic && file_exists($profile_pic)): ?>
                                    <img src="<?= htmlspecialchars($profile_pic) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full bg-indigo-600 flex items-center justify-center text-white text-4xl font-bold">
                                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form id="profilePicForm" action="upload_profile_pic.php" method="POST" enctype="multipart/form-data" class="hidden">
                                <input type="file" name="profile_pic" id="profile_pic_input" accept="image/*" onchange="document.getElementById('profilePicForm').submit()">
                            </form>
                            <button onclick="document.getElementById('profile_pic_input').click()" 
                                    class="absolute bottom-0 right-0 w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center shadow-lg hover:bg-indigo-700 transition-all">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        <div class="text-center md:text-left">
                            <h2 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($user_name) ?></h2>
                            <p class="text-slate-500 font-medium"><?= htmlspecialchars($user_role) ?></p>
                            <p class="text-xs text-indigo-600 font-bold mt-2 uppercase tracking-widest">Kastam MyPEKEMA User</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Nama Penuh</label>
                            <p class="mt-1 text-lg font-semibold text-gray-900"><?= htmlspecialchars($user_name) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Alamat E-mel</label>
                            <p class="mt-1 text-lg font-semibold text-gray-900"><?= htmlspecialchars($user_email) ?></p>
                            <div class="flex items-center mt-2">
                                <i class="fab fa-google text-green-600 mr-2"></i>
                                <span class="text-xs text-green-700 font-medium">Akaun disahkan dan dipautkan melalui
                                    Google</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Pengurusan Akaun</label>
                            <p class="mt-2 text-sm text-gray-600">
                                Untuk menukar nama atau tetapan keselamatan lain, sila uruskan tetapan Akaun Google anda
                                secara terus.
                            </p>
                            <a href="https://myaccount.google.com/" target="_blank" rel="noopener noreferrer"
                                class="mt-3 inline-block bg-blue-100 hover:bg-blue-200 text-blue-800 font-bold py-2 px-4 rounded-lg text-sm transition duration-300">
                                Pergi ke Akaun Google <i class="fas fa-external-link-alt ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($user_role === 'admin'): ?>
                    <!-- Sub-Bahagian: Pengurusan Sistem -->
                    <div class="p-6 md:p-8 border-t border-blue-100 bg-blue-50/50 relative overflow-hidden">
                        <!-- Decor -->
                        <div class="absolute -right-10 -bottom-10 opacity-10 blur-xl">
                            <i class="fas fa-users-gear text-9xl text-blue-500"></i>
                        </div>
                        <h2 class="text-lg font-bold text-blue-900 flex items-center mb-4 relative z-10">
                            <i class="fas fa-users-gear text-blue-600 mr-3"></i>
                            Pengurusan Sistem (Admin)
                        </h2>
                        <p class="text-sm text-blue-700 font-medium mb-6 relative z-10">Sebagai pentadbir, anda mempunyai
                            akses untuk menguruskan senarai pengguna dalam sistem.</p>
                        <a href="manage_users.php"
                            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-xl text-sm shadow-lg shadow-blue-500/30 transition-all hover:scale-[1.02] relative z-10">
                            <i class="fas fa-cog"></i> Urus Pengguna Whitelist
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 flex justify-end items-center rounded-b-xl">
                    <a href="vehicles.php"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300">
                        Kembali ke Senarai
                    </a>
                </div>
            </div>
        </div>
    </main>

</body>

</html>