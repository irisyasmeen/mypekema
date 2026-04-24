<?php
// topmenu.php - Premium Responsive Navigation for MyPEKEMA
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['user_role'] ?? 'user';
$nama_pegawai = $_SESSION['nama_pegawai'] ?? 'Pengguna';
$user_email = $_SESSION['user_email'] ?? '';

// Ensure we always have the freshest role and profile pic from the database
if (!empty($user_email) && isset($conn)) {
    // We use a safe query that doesn't fail if profile_pic column is missing
    $sql_u = "SELECT role, (SELECT profile_pic FROM " . TABLE_WHITELIST . " WHERE email = ?) as pic FROM " . TABLE_WHITELIST . " WHERE email = ?";
    $role_check = $conn->prepare("SELECT * FROM " . TABLE_WHITELIST . " WHERE email = ?");
    if ($role_check) {
        $role_check->bind_param("s", $user_email);
        $role_check->execute();
        $role_res = $role_check->get_result();
        if ($role_row = $role_res->fetch_assoc()) {
            $user_role = $role_row['role'];
            $_SESSION['user_role'] = $user_role;
            if (isset($role_row['profile_pic'])) {
                $_SESSION['profile_pic'] = $role_row['profile_pic'];
            }
        }
        $role_check->close();
    }
}
$profile_pic = $_SESSION['profile_pic'] ?? null;

// Pending applications count for admin/supervisor/customs
$total_pending = 0;
if (in_array($user_role, ['admin', 'supervisor', 'customs']) && isset($conn)) {
    $sql_count_pending = "SELECT COUNT(id) as total FROM vehicle_inventory WHERE status_pergerakan = 'Pending'";
    $res_count_pending = $conn->query($sql_count_pending);
    if ($res_count_pending) {
        $total_pending = (int) $res_count_pending->fetch_assoc()['total'];
    }
}

// Active page helper
function getActiveClasses($page_name, $current_page)
{
    return ($page_name === $current_page)
        ? 'bg-blue-600/10 text-blue-400 font-bold border-b-2 border-blue-400'
        : 'text-slate-400 hover:text-white hover:bg-white/5 transition-all duration-300';
}

// Group active pages for parent menus
$inventori_pages = ['vehicles.php', 'tambah_kenderaan.php', 'import_csv.php', 'upload_excel.php', 'ocr_upload.php', 'vehicle_details.php', 'edit_vehicle.php', 'kad_kenderaan.php', 'arkib.php'];
$analisa_pages = ['analisis_kenderaan.php', 'analisis_cukai.php', 'analisis_tahunan.php', 'analisis_usia.php', 'analisis_rangkaian.php', 'analisis_anomali.php', 'analisis_ap.php', 'analisa_tempoh_gudang.php', 'analisis_cukai_belum_bayar.php'];
$admin_pages = ['manage_users.php', 'settings.php'];

function isParentActive($pages, $current_page)
{
    return in_array($current_page, $pages) ? 'text-blue-400 font-bold' : 'text-slate-400 hover:text-white';
}
?>

<header class="sticky top-0 z-[100] w-full px-4 py-3" x-data="{ mobileMenuOpen: false }">
    <!-- Main Navbar Container -->
    <nav
        class="container mx-auto bg-[#001f3f]/90 backdrop-blur-2xl border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.3)] rounded-[2rem] relative">
        <!-- Top Glow Effect -->
        <div
            class="absolute top-0 left-1/2 -translate-x-1/2 w-1/2 h-px bg-gradient-to-r from-transparent via-blue-500/50 to-transparent">
        </div>

        <div class="px-6 lg:px-10">
            <div class="flex items-center justify-between h-20">

                <!-- Brand Section -->
                <div class="flex items-center gap-10">
                    <a href="index.php" class="flex items-center group">
                        <div class="relative">
                            <div
                                class="absolute inset-0 bg-blue-500 blur-lg opacity-20 group-hover:opacity-40 transition-opacity">
                            </div>
                            <div
                                class="relative p-2.5 bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl text-white shadow-lg group-hover:scale-110 transition-transform duration-500">
                                <i class="fas fa-shield-halved text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4 hidden md:block">
                            <span class="text-xl font-black text-white tracking-tight">My<span
                                    class="text-yellow-400">PEKEMA</span></span>
                            <div class="flex items-center gap-1.5">
                                <span class="h-1 w-1 rounded-full bg-emerald-500"></span>
                                <span class="text-[9px] text-slate-400 font-black uppercase tracking-[0.2em]">Management
                                    Hub</span>
                            </div>
                        </div>
                    </a>

                    <!-- Desktop Menu Items -->
                    <div class="hidden lg:flex items-center space-x-1">
                        <!-- Dashboard -->
                        <a href="index.php"
                            class="px-4 py-2 text-sm font-semibold h-20 flex items-center <?= getActiveClasses('index.php', $current_page) ?>">
                            <i class="fas fa-th-large mr-2 opacity-70"></i>Utama
                        </a>

                        <!-- Kenderaan Dropdown -->
                        <div class="relative group h-20 flex items-center">
                            <?php $kenderaan_pages = ['vehicles.php', 'tambah_kenderaan.php', 'arkib.php', 'ocr_upload.php']; ?>
                            <button
                                class="px-5 py-2 text-sm font-semibold flex items-center gap-2 <?= isParentActive($kenderaan_pages, $current_page) ?> transition-colors duration-300">
                                <i class="fas fa-car mr-1 opacity-70"></i>Kenderaan
                                <i
                                    class="fas fa-chevron-down text-[10px] opacity-40 group-hover:rotate-180 transition-transform"></i>
                            </button>
                            <div
                                class="absolute left-0 top-[90%] mt-2 w-80 bg-[#001a35]/95 backdrop-blur-2xl border border-white/10 rounded-[2rem] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 translate-y-4 group-hover:translate-y-0 p-3 z-50">
                                <div class="grid gap-1">
                                    <div
                                        class="px-4 py-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        Inventori</div>
                                    <a href="vehicles.php"
                                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all group/item">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover/item:scale-110 transition-transform">
                                            <i class="fas fa-list-ul"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold">Senarai Kenderaan</p>
                                            <p class="text-[10px] opacity-50">Lihat Inventori Aktif</p>
                                        </div>
                                    </a>
                                    <?php if ($user_role !== 'supervisor'): ?>
                                        <a href="tambah_kenderaan.php"
                                            class="flex items-center gap-4 px-4 py-3.5 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all group/item">
                                            <div
                                                class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 group-hover/item:scale-110 transition-transform">
                                                <i class="fas fa-plus"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold">Pendaftaran Manual</p>
                                                <p class="text-[10px] opacity-50">Input Data Baru</p>
                                            </div>
                                        </a>
                                        <a href="ocr_upload.php"
                                            class="flex items-center gap-4 px-4 py-3.5 rounded-2xl bg-blue-600/10 border border-blue-500/20 text-sm text-blue-300 hover:text-white transition-all group/item">
                                            <div
                                                class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center text-blue-300 group-hover/item:scale-110 transition-transform">
                                                <i class="fas fa-robot"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold">AI Vision Scan</p>
                                                <p class="text-[10px] opacity-50">Auto OCR Extract</p>
                                            </div>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!in_array($user_role, ['licensee', 'user'])): ?>
                                    <a href="arkib.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all">
                                        <i class="fas fa-archive text-slate-400 w-5"></i>
                                        <span>Arkib Bayaran Selesai</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Permohonan Dropdown (New structure) -->
                        <?php if (!in_array($user_role, ['admin', 'supervisor', 'user'])): ?>
                        <div class="relative group h-20 flex items-center">
                            <?php $permohonan_pages = ['permohonan.php', 'borang_pergerakan.php', 'borang_lampiran_f.php', 'borang_lampiran_g.php', 'borang_lampiran_k.php', 'borang_lampiran_l.php']; ?>
                            <button
                                class="px-5 py-2 text-sm font-semibold flex items-center gap-2 <?= isParentActive($permohonan_pages, $current_page) ?> transition-colors duration-300">
                                <i class="fas fa-file-signature mr-1 opacity-70"></i>Permohonan
                                <i
                                    class="fas fa-chevron-down text-[10px] opacity-40 group-hover:rotate-180 transition-transform"></i>
                            </button>
                            <div
                                class="absolute left-0 top-[90%] mt-2 w-80 bg-[#001a35]/95 backdrop-blur-2xl border border-white/10 rounded-[2rem] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 translate-y-4 group-hover:translate-y-0 p-3 z-50">
                                <div class="grid gap-1">
                                    <!-- JENIS PERMOHONAN Section -->
                                    <div
                                        class="px-4 py-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        Jenis Permohonan</div>
                                    
                                    <!-- Pergerakan -->
                                    <a href="borang_pergerakan.php"
                                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all group/item">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center text-red-400 group-hover/item:scale-110 transition-transform">
                                            <i class="fas fa-truck-fast"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold">Pergerakan</p>
                                            <p class="text-[10px] opacity-50">Permohonan Pergerakan Kenderaan</p>
                                        </div>
                                    </a>

                                    <!-- Pameran (Lampiran F) -->
                                    <a href="borang_lampiran_f.php"
                                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all group/item">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover/item:scale-110 transition-transform">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold">Pameran</p>
                                            <p class="text-[10px] opacity-50">Pindaan Struktur/Lokasi Gudang</p>
                                        </div>
                                    </a>

                                    <!-- Lampiran K -->
                                    <a href="borang_lampiran_k.php"
                                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all group/item">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 group-hover/item:scale-110 transition-transform">
                                            <i class="fas fa-boxes"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold">Lampiran K</p>
                                            <p class="text-[10px] opacity-50">Permohonan Penyimpanan Barang</p>
                                        </div>
                                    </a>

                                    <!-- Separator -->
                                    <div class="my-2 border-t border-white/10"></div>

                                    <!-- BORANG LAIN-LAIN Section -->
                                    <div
                                        class="px-4 py-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        Borang Lain-lain</div>

                                    <!-- Lampiran G -->
                                    <a href="borang_lampiran_g.php"
                                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all group/item">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 group-hover/item:scale-110 transition-transform">
                                            <i class="fas fa-file-signature"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold">Lampiran G</p>
                                            <p class="text-[10px] opacity-50">Laporan Pemeriksaan Pindaan Gudang</p>
                                        </div>
                                    </a>

                                    <!-- Lampiran L -->
                                    <a href="borang_lampiran_l.php"
                                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all group/item">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400 group-hover/item:scale-110 transition-transform">
                                            <i class="fas fa-file-signature"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold">Lampiran L</p>
                                            <p class="text-[10px] opacity-50">Pelupusan/Pemusnahan & Remisi</p>
                                        </div>
                                    </a>

                                    <!-- Separator -->
                                    <div class="my-2 border-t border-white/10"></div>

                                    <!-- LAMPIRAN J Section -->
                                    <div
                                        class="px-4 py-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        Lampiran J</div>
                                    <a href="permohonan.php"
                                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all group/item">
                                        <div
                                            class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 group-hover/item:scale-110 transition-transform">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                        <div>
                                            <p class="font-bold">Status Permohonan J</p>
                                            <p class="text-[10px] opacity-50">Semak Kelulusan Lampiran J</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>


                        <!-- Syarikat -->
                        <?php if (!in_array($user_role, ['licensee', 'user'])): ?>
                        <a href="gbpekema.php"
                            class="px-4 py-2 text-sm font-semibold h-20 flex items-center <?= getActiveClasses('gbpekema.php', $current_page) ?>">
                            <i class="fas fa-building mr-2 opacity-70"></i>Syarikat
                        </a>
                        <?php endif; ?>

                        <!-- Analisa AI Dropdown -->
                        <?php if (!in_array($user_role, ['licensee', 'user'])): ?>
                        <div class="relative group h-20 flex items-center">
                            <button
                                class="px-5 py-2 text-sm font-semibold flex items-center gap-2 <?= isParentActive($analisa_pages, $current_page) ?> transition-colors duration-300">
                                <i class="fas fa-microchip mr-1 opacity-70"></i>Analisa AI
                                <i
                                    class="fas fa-chevron-down text-[10px] opacity-40 group-hover:rotate-180 transition-transform"></i>
                            </button>
                            <div
                                class="absolute left-0 top-[90%] mt-2 w-72 bg-[#001a35]/95 backdrop-blur-2xl border border-white/10 rounded-[2rem] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 translate-y-4 group-hover:translate-y-0 p-3 z-50">
                                <div class="grid gap-1">
                                    <div
                                        class="px-3 py-2 text-[10px] font-black tracking-widest text-slate-500 uppercase">
                                        PRESTASI & STATISTIK</div>
                                    <a href="analisis_kenderaan.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all">
                                        <i class="fas fa-chart-bar text-blue-400 w-5"></i>
                                        <span>Analisa Kenderaan</span>
                                    </a>
                                    <a href="analisis_cukai.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all">
                                        <i class="fas fa-coins text-emerald-400 w-5"></i>
                                        <span>Analisa Cukai</span>
                                    </a>
                                    <a href="analisis_rangkaian.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all">
                                        <i class="fas fa-network-wired text-purple-400 w-5"></i>
                                        <span>Analisa Rangkaian</span>
                                    </a>

                                    <div class="h-px bg-white/5 mx-2 my-2"></div>
                                    <div
                                        class="px-3 py-2 text-[10px] font-black tracking-widest text-slate-500 uppercase">
                                        RISIKO & PEMATUHAN</div>

                                    <a href="analisis_anomali.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-rose-600/10 text-sm text-slate-300 hover:text-rose-400 transition-all">
                                        <i class="fas fa-exclamation-triangle text-rose-500 w-5"></i>
                                        <span>Kesan Anomali</span>
                                    </a>
                                    <a href="analisis_cukai_belum_bayar.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-red-600/10 text-sm text-slate-300 hover:text-red-400 transition-all">
                                        <i class="fas fa-receipt text-red-500 w-5"></i>
                                        <span>Cukai Belum Bayar</span>
                                    </a>
                                    <a href="analisa_tempoh_gudang.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-indigo-600/10 text-sm text-slate-300 hover:text-indigo-400 transition-all">
                                        <i class="fas fa-hourglass-half text-indigo-400 w-5"></i>
                                        <span>Analisa Tempoh Gudang</span>
                                    </a>
                                    <a href="analisis_ap.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-orange-600/10 text-sm text-slate-300 hover:text-orange-400 transition-all">
                                        <i class="fas fa-file-contract text-orange-400 w-5"></i>
                                        <span>Tarikh Luput AP</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Carian -->
                        <?php if ($user_role !== 'licensee'): ?>
                        <a href="carian_pintar.php"
                            class="px-4 py-2 text-sm font-semibold h-20 flex items-center <?= getActiveClasses('carian_pintar.php', $current_page) ?>">
                            <i class="fas fa-search-dollar mr-2 opacity-70"></i>Carian Pintar
                        </a>
                        <?php endif; ?>

                        <!-- Laporan -->
                        <?php if (!in_array($user_role, ['licensee', 'user'])): ?>
                        <a href="report.php"
                            class="px-4 py-2 text-sm font-semibold h-20 flex items-center <?= getActiveClasses('report.php', $current_page) ?>">
                            <i class="fas fa-file-pdf mr-2 opacity-70"></i>Laporan
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Side Actions -->
                <div class="flex items-center gap-5">
                    <!-- Manual (User Help) -->
                    <a href="manual.php" title="Manual Pengguna"
                        class="hidden sm:flex w-11 h-11 items-center justify-center rounded-2xl bg-white/5 border border-white/10 text-slate-400 hover:bg-blue-600 hover:text-white hover:border-blue-500 transition-all duration-300 group">
                        <i class="fas fa-book-open text-sm group-hover:scale-110"></i>
                    </a>

                    <!-- Notification Bell (For Admin/Supervisor/Customs) -->
                    <?php if ($total_pending > 0): ?>
                    <a href="permohonan.php?status=Pending" title="<?= $total_pending ?> Permohonan Menunggu Kelulusan"
                        class="relative flex w-11 h-11 items-center justify-center rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-500 hover:bg-amber-500 hover:text-white transition-all duration-300 group">
                        <i class="fas fa-bell text-sm group-hover:scale-110 <?= $total_pending > 0 ? 'animate-bounce' : '' ?>"></i>
                        <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-rose-600 text-[10px] font-bold text-white shadow-lg ring-2 ring-[#001f3f]">
                            <?= $total_pending ?>
                        </span>
                    </a>
                    <?php endif; ?>

                    <!-- User Profile Dropdown -->
                    <div class="relative group flex items-center">
                        <div class="h-8 w-px bg-white/10 mx-3 hidden lg:block"></div>
                        <button class="flex items-center gap-4 py-1.5 rounded-2xl transition-all group">
                            <div class="flex flex-col text-right hidden sm:block">
                                <span
                                    class="text-xs font-bold text-white group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($nama_pegawai) ?></span>
                                <div class="flex justify-end items-center gap-1.5 opacity-60">
                                    <span
                                        class="text-[8px] font-black text-blue-400 uppercase tracking-[0.2em]"><?= str_replace('_', ' ', $user_role) ?></span>
                                </div>
                            </div>
                            <div class="relative">
                                <div
                                    class="absolute inset-0 bg-blue-600 blur-md opacity-0 group-hover:opacity-40 transition-opacity">
                                </div>
                                <div
                                    class="relative w-11 h-11 rounded-2xl bg-gradient-to-br from-blue-600 to-blue-900 flex items-center justify-center text-white font-black shadow-lg border border-white/10 overflow-hidden">
                                    <?php if ($profile_pic && file_exists($profile_pic)): ?>
                                        <img src="<?= htmlspecialchars($profile_pic) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?= strtoupper(substr($nama_pegawai, 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </button>

                        <div
                            class="absolute right-0 top-[90%] mt-2 w-72 bg-[#001a35]/95 backdrop-blur-2xl border border-white/10 rounded-[2rem] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 translate-y-4 group-hover:translate-y-0 p-4 z-50">
                            <div class="px-4 py-4 border-b border-white/10 mb-2">
                                <p class="text-[10px] text-slate-500 uppercase font-black tracking-widest mb-1">Signed
                                    in as</p>
                                <p class="text-sm font-bold text-white truncate"><?= htmlspecialchars($user_email) ?>
                                </p>
                            </div>
                            <div class="grid gap-1">
                                <a href="settings.php"
                                    class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all">
                                    <i class="fas fa-user-shield w-5 text-blue-400"></i> Profil & Tetapan
                                </a>
                                <?php if ($user_role == 'admin'): ?>
                                    <a href="manage_users.php"
                                        class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all">
                                        <i class="fas fa-users-gear w-5 text-blue-500"></i> Pengurusan Pengguna
                                    </a>
                                <?php endif; ?>
                                <?php if ($user_role !== 'licensee'): ?>
                                <a href="terma.php"
                                    class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-white/5 text-sm text-slate-300 hover:text-white transition-all">
                                    <i class="fas fa-file-contract w-5 text-slate-400"></i> Terma Perkhidmatan
                                </a>
                                <?php endif; ?>
                                <div class="h-px bg-white/10 my-2"></div>
                                <a href="logout.php"
                                    class="flex items-center gap-4 px-4 py-3 rounded-2xl hover:bg-rose-600/10 text-sm text-rose-500 font-bold transition-all">
                                    <i class="fas fa-power-off w-5 text-rose-500"></i> Log Keluar
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden w-10 h-10 flex items-center justify-center rounded-xl bg-slate-800 text-white">
                        <i class="fas" :class="mobileMenuOpen ? 'fa-times' : 'fa-bars'"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Sidebar (Slide-down) -->
        <div x-show="mobileMenuOpen" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-10" x-transition:enter-end="opacity-100 translate-y-0"
            class="lg:hidden border-t border-white/10 p-6 space-y-6 pb-12 bg-[#001a35]/95 backdrop-blur-2xl">

            <a href="index.php"
                class="flex items-center gap-4 p-5 rounded-[1.5rem] bg-blue-600/20 text-blue-300 font-black tracking-tight border border-blue-500/20">
                <i class="fas fa-th-large w-6"></i> Dashboard Utama
            </a>

            <div class="space-y-1">
                <p class="px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 mt-4">KENDERAAN & INVENTORI</p>
                <a href="vehicles.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-list-ul w-6 text-blue-400"></i> Senarai Kenderaan</a>
                <?php if ($user_role !== 'supervisor'): ?>
                    <a href="tambah_kenderaan.php"
                        class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                            class="fas fa-plus w-6 text-emerald-400"></i> Pendaftaran Manual</a>
                    <a href="ocr_upload.php"
                        class="flex items-center gap-4 px-5 py-5 rounded-[1.5rem] bg-blue-600/10 text-blue-300 border border-blue-500/10"><i
                            class="fas fa-robot w-6"></i> AI Vision Scan</a>
                <?php endif; ?>
                <?php if (!in_array($user_role, ['licensee', 'user'])): ?>
                <a href="arkib.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-archive w-6 text-slate-400"></i> Arkib Selesai</a>
                <?php endif; ?>
            </div>

            <?php if (!in_array($user_role, ['admin', 'supervisor', 'user'])): ?>
            <div class="space-y-1">
                <p class="px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 mt-4">JENIS PERMOHONAN</p>
                <a href="borang_pergerakan.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-truck-fast w-6 text-red-400"></i> Pergerakan</a>
                <a href="borang_lampiran_f.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-building w-6 text-blue-400"></i> Pameran</a>
                <a href="borang_lampiran_k.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-boxes w-6 text-amber-400"></i> Lampiran K</a>
            </div>

            <div class="space-y-1">
                <p class="px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 mt-4">BORANG LAIN-LAIN</p>
                <a href="borang_lampiran_g.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-file-signature w-6 text-emerald-400"></i> Lampiran G</a>
                <a href="borang_lampiran_l.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-file-signature w-6 text-purple-400"></i> Lampiran L</a>
            </div>

            <div class="space-y-1">
                <p class="px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 mt-4">PERMOHONAN & PERGERAKAN</p>
                <a href="permohonan.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-tasks w-6 text-amber-400"></i> Status Permohonan J</a>
            </div>
            <?php endif; ?>


            <div class="space-y-1">
                <p class="px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 mt-4">ANALISA &
                    LAPORAN</p>
                <?php if (!in_array($user_role, ['licensee', 'user'])): ?>
                <a href="analisis_cukai.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-chart-line w-6 text-blue-400"></i> Analisa Pintar</a>
                <a href="analisa_tempoh_gudang.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-hourglass-half w-6 text-indigo-400"></i> Analisa Tempoh Gudang</a>
                <?php endif; ?>
                <?php if (!in_array($user_role, ['licensee', 'user'])): ?>
                <a href="gbpekema.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-building w-6 text-blue-400"></i> Senarai Syarikat</a>
                <?php endif; ?>
                <?php if (!in_array($user_role, ['licensee', 'user'])): ?>
                <a href="report.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-file-invoice w-6 text-blue-400"></i> Jana Laporan</a>
                <?php endif; ?>
            </div>

            <div class="space-y-1">
                <p class="px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 mt-4">PROFIL &
                    TETAPAN</p>
                <a href="profile.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-user-shield w-6 text-blue-400"></i> Profil & Tetapan</a>
                <?php if ($user_role == 'admin'): ?>
                    <a href="manage_users.php"
                        class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                            class="fas fa-users-gear w-6 text-blue-500"></i> Pengurusan Pengguna</a>
                <?php endif; ?>
                <?php if ($user_role !== 'licensee'): ?>
                <a href="terma.php"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-300 hover:bg-white/5 transition-all"><i
                        class="fas fa-file-contract w-6 text-slate-400"></i> Terma Perkhidmatan</a>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-4 p-5 mt-6 border-t border-white/10">
                <div class="w-12 h-12 rounded-xl bg-blue-600/20 flex items-center justify-center text-blue-400 overflow-hidden">
                    <?php if ($profile_pic && file_exists($profile_pic)): ?>
                        <img src="<?= htmlspecialchars($profile_pic) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="font-black"><?= strtoupper(substr($nama_pegawai, 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-white"><?= htmlspecialchars($nama_pegawai) ?></p>
                    <p class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($user_email) ?></p>
                </div>
                <a href="logout.php"
                    class="w-12 h-12 flex items-center justify-center rounded-2xl bg-rose-600 shadow-lg shadow-rose-500/20 text-white active:scale-95 transition-all">
                    <i class="fas fa-power-off"></i>
                </a>
            </div>
        </div>
    </nav>
</header>

<!-- Support for AlpineJS (v3 preferred) -->
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>