<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'user';

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

    $where_clauses = ["1=1"];
    $params = [];
    $types = "";

    if ($user_role === 'licensee') {
        $licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;
        $where_clauses[] = "v.gbpekema_id = ?";
        $params[] = $licensee_gb_id;
        $types .= "i";
    }

if ($status_filter) {
    if ($status_filter === 'Pending') {
        $where_clauses[] = "status_pergerakan = 'Pending'";
    } else {
        $where_clauses[] = "status_pergerakan = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

if ($search_term) {
    $where_clauses[] = "(lot_number LIKE ? OR vehicle_model LIKE ? OR chassis_number LIKE ?)";
    $like_term = "%$search_term%";
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= "sss";
}

$sql = "SELECT v.*, g.nama as gbpekema_nama 
        FROM vehicle_inventory v 
        LEFT JOIN gbpekema g ON v.gbpekema_id = g.id 
        WHERE " . implode(" AND ", $where_clauses) . " 
        ORDER BY v.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$applications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Permohonan Pergerakan - MyPEKEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Dropdown Menu Styling */
        .group {
            position: relative;
        }

        .group > button:active ~ div,
        .group > div:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .group > button {
            cursor: pointer;
        }

        .group.active > div {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
    </style>
</head>

<body class="text-slate-900">
    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 tracking-tight">Status <span
                        class="text-blue-600">Permohonan</span></h1>
                <p class="text-slate-500 font-medium">Jejak dan urus permohonan Pergerakan Kenderaan (Lampiran J).</p>
            </div>

            <div class="flex gap-2 relative group">
                <button class="px-5 py-2.5 bg-white border border-slate-200 rounded-2xl font-bold text-slate-700 hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm"
                    onclick="this.parentElement.classList.toggle('active')">
                    <i class="fas fa-plus"></i> Mohon
                    <i class="fas fa-chevron-down text-sm opacity-60"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="absolute top-full mt-2 left-0 w-64 bg-white border border-slate-200 rounded-2xl shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 translate-y-2 group-hover:translate-y-0 z-50">
                    <div class="py-2">
                        <!-- JENIS PERMOHONAN Section -->
                        <div class="px-4 py-2 text-xs font-black text-slate-400 uppercase tracking-widest">Jenis Permohonan</div>
                        
                        <!-- Pergerakan -->
                        <a href="borang_pergerakan.php"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-red-50 text-slate-700 hover:text-red-700 transition-colors">
                            <i class="fas fa-truck-fast text-red-500 w-5"></i>
                            <div>
                                <p class="font-bold text-sm">Pergerakan</p>
                                <p class="text-xs text-slate-500">Permohonan Pergerakan Kenderaan</p>
                            </div>
                        </a>
                        
                        <!-- Pameran (Lampiran F) -->
                        <a href="borang_lampiran_f.php"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 text-slate-700 hover:text-blue-700 transition-colors border-t border-slate-100">
                            <i class="fas fa-building text-blue-500 w-5"></i>
                            <div>
                                <p class="font-bold text-sm">Pameran</p>
                                <p class="text-xs text-slate-500">Pindaan Struktur/Lokasi Gudang</p>
                            </div>
                        </a>
                        
                        <!-- Lampiran K -->
                        <a href="borang_lampiran_k.php"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 text-slate-700 hover:text-amber-700 transition-colors border-t border-slate-100">
                            <i class="fas fa-boxes text-amber-500 w-5"></i>
                            <div>
                                <p class="font-bold text-sm">Lampiran K</p>
                                <p class="text-xs text-slate-500">Permohonan Penyimpanan Barang</p>
                            </div>
                        </a>

                        <!-- Divider -->
                        <div class="my-2 border-t border-slate-100"></div>

                        <!-- BORANG LAIN-LAIN Section -->
                        <div class="px-4 py-2 text-xs font-black text-slate-400 uppercase tracking-widest">Borang Lain-lain</div>
                        
                        <!-- Lampiran G -->
                        <a href="borang_lampiran_g.php"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 transition-colors">
                            <i class="fas fa-file-signature text-emerald-500 w-5"></i>
                            <div>
                                <p class="font-bold text-sm">Lampiran G</p>
                                <p class="text-xs text-slate-500">Laporan Pemeriksaan Pindaan Gudang</p>
                            </div>
                        </a>
                        
                        <!-- Lampiran L -->
                        <a href="borang_lampiran_l.php"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-purple-50 text-slate-700 hover:text-purple-700 transition-colors border-t border-slate-100 rounded-b-2xl">
                            <i class="fas fa-file-signature text-purple-500 w-5"></i>
                            <div>
                                <p class="font-bold text-sm">Lampiran L</p>
                                <p class="text-xs text-slate-500">Pelupusan/Pemusnahan & Remisi</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="glass-card p-6 rounded-[2rem] shadow-sm mb-8">
            <form method="GET" class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>"
                        placeholder="Cari No. Lot, Model, atau Casis..."
                        class="w-full pl-12 pr-4 py-3.5 bg-slate-100 border-none rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-medium transition-all">
                </div>

                <div class="flex flex-wrap gap-2">
                    <?php
                    $statuses = [
                        '' => 'Semua',
                        'Pending' => 'Pending',
                        'Lulus' => 'Lulus',
                        'Ditolak' => 'Ditolak'
                    ];
                    foreach ($statuses as $val => $label):
                        $active = ($status_filter === $val);
                        $color = 'slate';
                        if ($val === 'Pending')
                            $color = 'amber';
                        if ($val === 'Lulus')
                            $color = 'emerald';
                        if ($val === 'Ditolak')
                            $color = 'rose';
                        ?>
                        <a href="?status=<?= $val ?>&search=<?= urlencode($search_term) ?>"
                            class="px-5 py-3 rounded-2xl font-bold text-sm transition-all <?= $active ? "bg-$color-600 text-white shadow-lg shadow-$color-500/20" : "bg-white text-slate-500 hover:bg-slate-50 border border-slate-200" ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <button type="submit"
                    class="hidden lg:block px-8 py-3.5 bg-slate-800 text-white font-bold rounded-2xl hover:bg-slate-900 transition-all">
                    Tapis
                </button>
            </form>
        </div>

        <!-- Desktop Table -->
        <div class="glass-card rounded-[2.5rem] shadow-xl overflow-hidden border border-white/40">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                Maklumat Kenderaan</th>
                            <th class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                Syarikat / Lot</th>
                            <th
                                class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] text-center">
                                Status Pergerakan</th>
                            <th
                                class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] text-center">
                                Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (count($applications) > 0): ?>
                            <?php foreach ($applications as $app):
                                $status = $app['status_pergerakan'] ?: 'Draf';
                                $status_color = 'slate';
                                if ($status === 'Pending')
                                    $status_color = 'amber';
                                if ($status === 'Lulus')
                                    $status_color = 'emerald';
                                if ($status === 'Ditolak')
                                    $status_color = 'rose';
                                ?>
                                <tr class="hover:bg-blue-50/30 transition-colors group">
                                    <td class="px-6 py-6">
                                        <p class="font-black text-slate-800 tracking-tight leading-tight">
                                            <?= htmlspecialchars($app['vehicle_model']) ?>
                                        </p>
                                        <p class="text-xs text-slate-500 font-mono mt-1">
                                            <?= htmlspecialchars($app['chassis_number']) ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-6">
                                        <p class="font-bold text-slate-700 text-sm">
                                            <?= htmlspecialchars($app['gbpekema_nama'] ?: 'N/A') ?>
                                        </p>
                                        <div class="flex items-center gap-1.5 mt-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                            <span class="text-xs font-black text-blue-600/70 uppercase tracking-widest">
                                                <?= htmlspecialchars($app['lot_number']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6 text-center">
                                        <span
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-<?= $status_color ?>-50 text-<?= $status_color ?>-700 text-xs font-black uppercase tracking-widest border border-<?= $status_color ?>-100">
                                            <span
                                                class="w-2 h-2 rounded-full bg-<?= $status_color ?>-500 <?= $status === 'Pending' ? 'animate-pulse' : '' ?>"></span>
                                            <?= $status ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-6 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="borang_pergerakan.php?id=<?= $app['id'] ?>"
                                                class="w-10 h-10 flex items-center justify-center rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all group/btn shadow-sm"
                                                title="Lihat Borang">
                                                <i
                                                    class="fas fa-file-invoice text-sm group-hover/btn:scale-110 transition-transform"></i>
                                            </a>
                                            <?php if ($status === 'Lulus'): ?>
                                                <a href="kad_kenderaan.php?id=<?= $app['id'] ?>"
                                                    class="w-10 h-10 flex items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all group/btn shadow-sm"
                                                    title="Cetak Pas">
                                                    <i
                                                        class="fas fa-id-card text-sm group-hover/btn:scale-110 transition-transform"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center">
                                        <div
                                            class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 mb-4">
                                            <i class="fas fa-inbox text-3xl"></i>
                                        </div>
                                        <p class="text-slate-500 font-bold">Tiada data permohonan ditemui.</p>
                                        <p class="text-slate-400 text-sm">Gunakan bar carian atau tukar penapis status.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Dropdown menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const groupBtn = document.querySelector('.group > button');
            const groupContainer = document.querySelector('.group');
            const dropdownMenu = document.querySelector('.group > div:last-child');

            // Toggle dropdown on button click
            if (groupBtn) {
                groupBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    groupContainer.classList.toggle('active');
                });
            }

            // Close dropdown when clicking on a link
            if (dropdownMenu) {
                const links = dropdownMenu.querySelectorAll('a');
                links.forEach(link => {
                    link.addEventListener('click', function() {
                        groupContainer.classList.remove('active');
                    });
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!groupContainer.contains(e.target)) {
                    groupContainer.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>