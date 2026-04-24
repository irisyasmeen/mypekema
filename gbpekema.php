<?php
session_start();
include 'config.php'; // Ensure this points to the correct DB configuration

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// RESTRICTION: Licensees cannot manage companies
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee') {
    header("Location: index.php");
    exit();
}

$feedback_message = '';
$feedback_type = '';

// --- FORM PROCESSING LOGIC (ADD/UPDATE/DELETE) ---

// Add or Update Company
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_company']) || isset($_POST['update_company']))) {
    $nama = trim($_POST['nama']);
    $pic = trim($_POST['pic']);
    $alamat = trim($_POST['alamat']);
    $no_tel = trim($_POST['no_tel']);
    $google_map_url = trim($_POST['google_map_url']);
    $negeri = trim($_POST['negeri']);
    $tarikh_kuatkuasa_lesen = !empty($_POST['tarikh_kuatkuasa_lesen']) ? $_POST['tarikh_kuatkuasa_lesen'] : null;
    $tarikh_mula = !empty($_POST['tarikh_mula']) ? $_POST['tarikh_mula'] : null;
    $tarikh_akhir = !empty($_POST['tarikh_akhir']) ? $_POST['tarikh_akhir'] : null;
    $kod_gudang = trim($_POST['kod_gudang'] ?? '');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!empty($nama)) {
        if ($id > 0) { // Update Logic
            $stmt = $conn->prepare("UPDATE gbpekema SET nama = ?, pic = ?, alamat = ?, no_tel = ?, google_map_url = ?, negeri = ?, tarikh_kuatkuasa_lesen = ?, tarikh_mula = ?, tarikh_akhir = ?, kod_gudang = ? WHERE id = ?");
            $stmt->bind_param("ssssssssssi", $nama, $pic, $alamat, $no_tel, $google_map_url, $negeri, $tarikh_kuatkuasa_lesen, $tarikh_mula, $tarikh_akhir, $kod_gudang, $id);
            $action = 'dikemaskini';
        } else { // Add Logic
            $stmt = $conn->prepare("INSERT INTO gbpekema (nama, pic, alamat, no_tel, google_map_url, negeri, tarikh_kuatkuasa_lesen, tarikh_mula, tarikh_akhir, kod_gudang) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $nama, $pic, $alamat, $no_tel, $google_map_url, $negeri, $tarikh_kuatkuasa_lesen, $tarikh_mula, $tarikh_akhir, $kod_gudang);
            $action = 'ditambah';
        }
        
        if ($stmt->execute()) {
            $feedback_message = "Syarikat berjaya {$action}.";
            $feedback_type = "success";
        } else {
            $feedback_message = "Ralat: " . $stmt->error;
            $feedback_type = "error";
        }
        $stmt->close();
    }
}


// Delete Company
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    if ($id > 0) {
        // Check if company has related vehicles
        $check_stmt = $conn->prepare("SELECT COUNT(id) as count FROM vehicle_inventory WHERE gbpekema_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        $check_stmt->close();
        
        if ($row['count'] > 0) {
            $feedback_message = "Ralat: Syarikat tidak boleh dipadam kerana mempunyai rekod kenderaan yang berkaitan.";
            $feedback_type = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM gbpekema WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $feedback_message = "Syarikat berjaya dipadam.";
                $feedback_type = "success";
            } else {
                $feedback_message = "Ralat semasa memadam.";
                $feedback_type = "error";
            }
            $stmt->close();
        }
    }
}


// --- FETCH DATA FOR DASHBOARD ---

$total_gb = 0;
$most_active_gb = ['nama' => 'N/A', 'vehicle_count' => 0];
$companies = [];
$recent_gbs = [];

// Total Companies
$result_total = $conn->query("SELECT COUNT(id) as total FROM gbpekema");
if($result_total) $total_gb = $result_total->fetch_assoc()['total'];

// Most Active Company
$sql_active = "SELECT g.nama, COUNT(v.id) as vehicle_count FROM gbpekema g LEFT JOIN vehicle_inventory v ON g.id = v.gbpekema_id GROUP BY g.id ORDER BY vehicle_count DESC LIMIT 1";
$result_active = $conn->query($sql_active);
if($result_active && $result_active->num_rows > 0) $most_active_gb = $result_active->fetch_assoc();

// List of Companies (with Search)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql_companies = "SELECT id, nama, pic, alamat, no_tel, google_map_url, negeri, tarikh_kuatkuasa_lesen, tarikh_mula, tarikh_akhir, kod_gudang FROM gbpekema";

if (!empty($search)) {
    $escaped_search = $conn->real_escape_string($search);
    $sql_companies .= " WHERE nama LIKE '%$escaped_search%' OR pic LIKE '%$escaped_search%' OR alamat LIKE '%$escaped_search%' OR no_tel LIKE '%$escaped_search%'";
}

$sql_companies .= " ORDER BY nama ASC";
$result_companies = $conn->query($sql_companies);
if ($result_companies) $companies = $result_companies->fetch_all(MYSQLI_ASSOC);

// Recently Registered Companies
$sql_recent = "SELECT nama, created_at FROM gbpekema ORDER BY created_at DESC LIMIT 5";
$result_recent = $conn->query($sql_recent);
if($result_recent) $recent_gbs = $result_recent->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GB/PEKEMA Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Alpine.js is included in topmenu.php -->
    <style> 
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3), transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 135, 135, 0.3), transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(138, 180, 248, 0.3), transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        main {
            position: relative;
            z-index: 1;
        }
        
        [x-cloak] { display: none !important; }
        .map-container iframe { width: 100%; height: 100%; border:0; }
        
        /* Glassmorphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }
        
        /* Animated Gradient Cards */
        .gradient-card {
            background: linear-gradient(135deg, var(--tw-gradient-stops));
            position: relative;
            overflow: hidden;
        }
        
        .gradient-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .gradient-card:hover::before {
            left: 100%;
        }
        
        /* Smooth hover animations */
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        /* Table row animation */
        tbody tr {
            transition: all 0.2s ease;
        }
        
        tbody tr:hover {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.05) 0%, rgba(168, 85, 247, 0.05) 100%);
            transform: scale(1.01);
        }
        
        /* Button glow effect */
        .btn-glow {
            position: relative;
            overflow: hidden;
        }
        
        .btn-glow::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-glow:hover::after {
            width: 300px;
            height: 300px;
        }
        
        /* Pulse animation for stats */
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .pulse-soft {
            animation: pulse-soft 3s ease-in-out infinite;
        }
        
        /* Modal backdrop blur */
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.5);
        }
        
        /* Glassmorphism modal */
        .glass-modal {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }
        
        /* Search input glow */
        input:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        /* Floating animation */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Badge pulse */
        .badge-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="text-gray-800"
      x-data="{ 
          addModalOpen: false, 
          editModalOpen: false, 
          mapModalOpen: false, 
          companyToEdit: {}, 
          companies: <?= htmlspecialchars(json_encode($companies), ENT_QUOTES, 'UTF-8') ?>,
          selectedCompany: null,
          openMap(company) {
             this.selectedCompany = company;
             this.mapModalOpen = true;
          },
          getEmbedUrl(company) {
             if (!company || !company.alamat) return '';
             const query = encodeURIComponent(company.nama + ' ' + company.alamat);
             return `https://maps.google.com/maps?q=${query}&t=&z=13&ie=UTF8&iwloc=&output=embed`;
          }
      }">

    <?php include 'topmenu.php'; ?>

    <!-- Main Container -->
    <main class="container mx-auto p-4 sm:p-6 lg:p-8">

        <header class="mb-8 glass-card rounded-2xl p-6 hover-lift">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center float-animation">
                            <i class="fas fa-building text-white text-xl"></i>
                        </div>
                        <h1 class="text-4xl font-black bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">
                            Pengurusan Syarikat GB/PEKEMA
                        </h1>
                    </div>
                    <p class="text-gray-600 ml-15 text-lg">Urus dan selia maklumat syarikat dengan mudah dan efisien.</p>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <i class="fas fa-clock"></i>
                    <span><?= date('d M Y, h:i A') ?></span>
                </div>
            </div>
        </header>

        <!-- Feedback Message -->
        <?php if ($feedback_message): ?>
        <div class="mb-6 glass-card p-5 rounded-xl <?= $feedback_type == 'success' ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500' ?> flex justify-between items-center transition-all duration-500 ease-out transform" 
             x-data="{ show: true }" 
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full <?= $feedback_type == 'success' ? 'bg-green-100' : 'bg-red-100' ?> flex items-center justify-center">
                    <i class="fas <?= $feedback_type == 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600' ?> text-xl"></i>
                </div>
                <span class="<?= $feedback_type == 'success' ? 'text-green-800' : 'text-red-800' ?> font-semibold"><?= htmlspecialchars($feedback_message) ?></span>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors text-2xl font-bold w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100">&times;</button>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total GB Card -->
            <div class="gradient-card from-emerald-500 via-teal-500 to-cyan-500 text-white p-6 rounded-2xl shadow-2xl hover-lift">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-emerald-100 text-sm font-semibold uppercase tracking-wide mb-2">Jumlah GB</p>
                        <p class="text-5xl font-black mb-1"><?= $total_gb ?></p>
                        <p class="text-emerald-100 text-xs">Syarikat Berdaftar</p>
                    </div>
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center pulse-soft backdrop-blur-sm">
                        <i class="fas fa-building fa-2x text-white"></i>
                    </div>
                </div>
                <div class="h-1 bg-white bg-opacity-30 rounded-full overflow-hidden">
                    <div class="h-full bg-white rounded-full" style="width: 100%;"></div>
                </div>
            </div>
            
            <!-- Most Active GB Card -->
            <div class="gradient-card from-violet-600 via-purple-600 to-fuchsia-600 text-white p-6 rounded-2xl shadow-2xl md:col-span-2 hover-lift">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-purple-100 text-sm font-semibold uppercase tracking-wide mb-2">GB Paling Aktif</p>
                        <p class="text-3xl font-black mb-3"><?= htmlspecialchars($most_active_gb['nama'] ?? 'N/A') ?></p>
                        <div class="flex items-center gap-4">
                            <div class="inline-flex items-center bg-white bg-opacity-20 backdrop-blur-sm px-4 py-2 rounded-full">
                                <i class="fas fa-car mr-2 text-yellow-300"></i>
                                <span class="font-bold text-lg"><?= htmlspecialchars($most_active_gb['vehicle_count'] ?? 0) ?></span>
                                <span class="ml-2 text-sm text-purple-100">Kenderaan</span>
                            </div>
                            <div class="inline-flex items-center bg-yellow-400 bg-opacity-30 backdrop-blur-sm px-3 py-1 rounded-full badge-pulse">
                                <i class="fas fa-star mr-1 text-yellow-300 text-xs"></i>
                                <span class="text-xs font-semibold">Top Performer</span>
                            </div>
                        </div>
                    </div>
                    <div class="w-20 h-20 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center float-animation backdrop-blur-sm">
                        <i class="fas fa-trophy fa-3x text-yellow-300"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content (List View only) -->
        <div>
            <!-- Company List -->
            <div class="glass-card rounded-2xl shadow-2xl border border-white border-opacity-20 overflow-hidden hover-lift">
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-indigo-50">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div>
                            <h3 class="text-2xl font-black text-gray-800 mb-1">Senarai Syarikat</h3>
                            <p class="text-sm text-gray-500">Cari dan urus maklumat syarikat</p>
                        </div>
                        <div class="flex gap-2 w-full sm:w-auto">
                            <form action="" method="GET" class="relative flex-grow sm:flex-grow-0">
                                <input type="text" 
                                       name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Cari syarikat..." 
                                       class="w-full sm:w-72 pl-11 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-300 bg-white">
                                <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                            </form>
                            <button @click="addModalOpen = true" 
                                    class="btn-glow bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold py-3 px-6 rounded-xl text-sm flex items-center transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105">
                                <i class="fas fa-plus mr-2"></i>Tambah
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-black text-gray-700 uppercase tracking-wider">Nama Syarikat</th>
                                <th class="px-6 py-4 text-left text-xs font-black text-gray-700 uppercase tracking-wider">PIC & Telefon</th>
                                <th class="px-6 py-4 text-left text-xs font-black text-gray-700 uppercase tracking-wider">Lokasi & Lesen</th>
                                <th class="px-6 py-4 text-center text-xs font-black text-gray-700 uppercase tracking-wider">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($companies) > 0): ?>
                                    <?php foreach ($companies as $company): ?>
                                    <tr class="group transition-all duration-200">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-gray-900 mb-1"><?= htmlspecialchars($company['nama']) ?></div>
                                            <div class="text-xs text-gray-500 mb-1 font-semibold uppercase tracking-wider bg-gray-100 inline-block px-2 py-0.5 rounded"><?= htmlspecialchars($company['negeri'] ?? 'N/A') ?></div>
                                            <!-- Map Trigger Link -->
                                            <button @click="openMap(<?= htmlspecialchars(json_encode($company)) ?>)" class="text-xs text-purple-600 mt-1 truncate max-w-xs hover:text-purple-800 hover:underline block text-left transition-colors flex items-center gap-1">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?= htmlspecialchars($company['alamat'] ?? 'Lihat Peta') ?></span>
                                            </button>
                                            <div class="mt-2 flex flex-col gap-1">
                                                <div class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full inline-flex items-center gap-1 w-fit">
                                                    <i class="fas fa-calendar-check"></i>
                                                    Lesen: <?= !empty($company['tarikh_kuatkuasa_lesen']) ? date('d/m/Y', strtotime($company['tarikh_kuatkuasa_lesen'])) : 'N/A' ?>
                                                </div>
                                                <div class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full inline-flex items-center gap-1 w-fit">
                                                    <i class="fas fa-play-circle"></i>
                                                    Mula: <?= !empty($company['tarikh_mula']) ? date('d/m/Y', strtotime($company['tarikh_mula'])) : 'N/A' ?>
                                                </div>
                                                <?php 
                                                    $days_left = null;
                                                    $is_expired = false;
                                                    $is_ending_soon = false;
                                                    if (!empty($company['tarikh_akhir'])) {
                                                        $expiry_date = new DateTime($company['tarikh_akhir']);
                                                        $today = new DateTime();
                                                        $interval = $today->diff($expiry_date);
                                                        // Use format('%r%a') to get signed days
                                                        $days_left_val = (int)$expiry_date->diff($today)->format('%r%a');
                                                        $days_diff = (int)$today->diff($expiry_date)->format('%r%a');
                                                        $is_expired = $days_diff < 0;
                                                        $is_ending_soon = $days_diff >= 0 && $days_diff <= 60;
                                                        $days_left = $days_diff;
                                                    }
                                                ?>
                                                <div class="text-[10px] font-bold <?= $is_expired ? 'text-white bg-red-600 animate-pulse' : ($is_ending_soon ? 'text-orange-700 bg-orange-100' : 'text-rose-600 bg-rose-50') ?> px-2 py-0.5 rounded-full inline-flex items-center gap-1 w-fit">
                                                    <i class="fas fa-stop-circle"></i>
                                                    Akhir: <?= !empty($company['tarikh_akhir']) ? date('d/m/Y', strtotime($company['tarikh_akhir'])) : 'N/A' ?>
                                                    <?php if (!empty($company['tarikh_akhir'])): ?>
                                                        <span>(<?= $is_expired ? 'TAMAT' : $days_left . ' hari lagi' ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><i class="fas fa-user mr-2 text-gray-400"></i><?= htmlspecialchars($company['pic'] ?: '-') ?></div>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <?php if (!empty($company['no_tel']) && $company['no_tel'] !== '-'): ?>
                                                    <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $company['no_tel'])) ?>" class="hover:text-blue-600 transition-colors flex items-center group">
                                                        <i class="fas fa-phone mr-2 text-green-500 group-hover:text-green-600 transition-colors"></i>
                                                        <?= htmlspecialchars($company['no_tel']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400"><i class="fas fa-phone mr-2"></i> -</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button @click="openMap(<?= htmlspecialchars(json_encode($company)) ?>)" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-bold bg-gradient-to-r from-blue-500 to-cyan-500 text-white hover:from-blue-600 hover:to-cyan-600 transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105">
                                                <i class="fas fa-map-marked-alt mr-1.5"></i> Lihat Peta
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex justify-center space-x-2">
                                                <button @click='companyToEdit = <?= json_encode($company, JSON_HEX_APOS | JSON_HEX_QUOT) ?>; editModalOpen = true'
                                                        class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 text-white hover:from-amber-500 hover:to-orange-600 transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg transform hover:scale-110" 
                                                        aria-label="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="gbpekema.php?delete_id=<?= $company['id'] ?>" 
                                                   onclick="return confirm('Anda pasti mahu memadam syarikat ini? Tindakan ini tidak boleh dikembalikan.')" 
                                                   class="w-9 h-9 rounded-lg bg-gradient-to-br from-red-500 to-pink-600 text-white hover:from-red-600 hover:to-pink-700 transition-all duration-200 flex items-center justify-center shadow-md hover:shadow-lg transform hover:scale-110" 
                                                   aria-label="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                <i class="fas fa-inbox text-4xl text-gray-300 mb-2"></i>
                                                <p>Tiada syarikat ditemui.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
    
    <!-- Map Modal (Pop-up Box) - Moved outside main for better Z-index handling -->
    <div x-show="mapModalOpen" x-cloak class="fixed z-[9999] inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
             <!-- Background Overlay -->
            <div x-show="mapModalOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 modal-backdrop" 
                 aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

             <!-- Modal Panel -->
            <div x-show="mapModalOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 @click.away="mapModalOpen = false"
                 class="inline-block align-bottom glass-modal rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full relative z-[10000]">
                
                <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center">
                            <i class="fas fa-map-marked-alt text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-gray-900" x-text="selectedCompany ? selectedCompany.nama : ''"></h3>
                            <p class="text-sm text-gray-500">Lokasi Syarikat</p>
                        </div>
                    </div>
                    <button @click="mapModalOpen = false" class="w-10 h-10 rounded-xl bg-gray-100 hover:bg-red-100 text-gray-500 hover:text-red-600 transition-all duration-200 flex items-center justify-center group">
                        <i class="fas fa-times text-xl group-hover:rotate-90 transition-transform duration-200"></i>
                    </button>
                </div>
                
                <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl overflow-hidden h-[500px] w-full border-4 border-white shadow-inner">
                    <template x-if="selectedCompany && selectedCompany.alamat">
                         <iframe 
                            width="100%" 
                            height="100%" 
                            frameborder="0" 
                            scrolling="no" 
                            marginheight="0" 
                            marginwidth="0" 
                            :src="getEmbedUrl(selectedCompany)"
                        ></iframe>
                    </template>
                    <div x-show="!selectedCompany || !selectedCompany.alamat" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
                        <i class="fas fa-map-marked-alt text-6xl mb-4 opacity-30"></i>
                        <p class="text-lg font-semibold">Alamat tidak dijumpai.</p>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-between items-center p-4 bg-gradient-to-r from-gray-50 to-transparent rounded-xl">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <i class="fas fa-location-dot text-purple-600"></i>
                        <p x-text="selectedCompany ? selectedCompany.alamat + (selectedCompany.negeri ? ', ' + selectedCompany.negeri : '') : ''" class="font-medium"></p>
                    </div>
                    <a :href="selectedCompany && selectedCompany.google_map_url ? selectedCompany.google_map_url : (selectedCompany ? 'https://maps.google.com/?q=' + encodeURIComponent(selectedCompany.nama + ' ' + selectedCompany.alamat + ' ' + (selectedCompany.negeri || '')) : '#')" 
                        target="_blank" 
                        class="btn-glow inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white font-bold px-5 py-2.5 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105">
                        <span>Buka di Google Maps</span>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div x-show="addModalOpen" x-cloak class="fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="addModalOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 modal-backdrop" 
                 aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="addModalOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom glass-modal rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div>
                   <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 shadow-lg">
                        <i class="fas fa-plus text-white text-2xl"></i>
                    </div>
                    <div class="mt-4 text-center">
                        <h3 class="text-2xl font-black text-gray-900 mb-2" id="modal-title">Tambah GB Baharu</h3>
                        <p class="text-sm text-gray-500">Isi maklumat syarikat di bawah</p>
                        <div class="mt-6">
                             <form action="gbpekema.php" method="POST" id="addForm">
                                <div class="space-y-4 text-left">
                                    <div>
                                        <label for="add-nama" class="block text-sm font-bold text-gray-700 mb-2">Nama Syarikat <span class="text-red-500">*</span></label>
                                        <input type="text" name="nama" id="add-nama" required class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div>
                                        <label for="add-kod_gudang" class="block text-sm font-bold text-gray-700 mb-2">Kod Gudang</label>
                                        <input type="text" name="kod_gudang" id="add-kod_gudang" placeholder="Cth: GB/123" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div>
                                        <label for="add-pic" class="block text-sm font-bold text-gray-700 mb-2">PIC</label>
                                        <input type="text" name="pic" id="add-pic" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <!-- Alamat -->
                                    <div>
                                        <label for="add-alamat" class="block text-sm font-bold text-gray-700 mb-2">Alamat Syarikat</label>
                                        <textarea name="alamat" id="add-alamat" rows="3" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200"></textarea>
                                    </div>
                                    <!-- Negeri Dropdown -->
                                    <div>
                                        <label for="add-negeri" class="block text-sm font-bold text-gray-700 mb-2">Negeri</label>
                                        <div class="relative">
                                            <i class="fas fa-map absolute left-4 top-3.5 text-gray-400"></i>
                                            <select name="negeri" id="add-negeri" required class="w-full pl-11 pr-4 py-2.5 border-2 border-gray-200 rounded-xl shadow-sm px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                                <option value="" disabled selected>Pilih Negeri</option>
                                                <option value="KLIA">KLIA</option>
                                                <option value="Johor">Johor</option>
                                                <option value="Kedah">Kedah</option>
                                                <option value="Kelantan">Kelantan</option>
                                                <option value="Melaka">Melaka</option>
                                                <option value="Negeri Sembilan">Negeri Sembilan</option>
                                                <option value="Pahang">Pahang</option>
                                                <option value="Perak">Perak</option>
                                                <option value="Perlis">Perlis</option>
                                                <option value="Pulau Pinang">Pulau Pinang</option>
                                                <option value="Sabah">Sabah</option>
                                                <option value="Sarawak">Sarawak</option>
                                                <option value="Selangor">Selangor</option>
                                                <option value="Terengganu">Terengganu</option>
                                                <option value="W.P. Kuala Lumpur">W.P. Kuala Lumpur</option>
                                                <option value="W.P. Labuan">W.P. Labuan</option>
                                                <option value="W.P. Putrajaya">W.P. Putrajaya</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="add-tarikh_kuatkuasa_lesen" class="block text-sm font-bold text-gray-700 mb-2">Tarikh Kuatkuasa Lesen</label>
                                        <input type="date" name="tarikh_kuatkuasa_lesen" id="add-tarikh_kuatkuasa_lesen" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div>
                                        <label for="add-no_tel" class="block text-sm font-bold text-gray-700 mb-2">No. Telefon</label>
                                        <input type="text" name="no_tel" id="add-no_tel" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="add-tarikh_mula" class="block text-sm font-bold text-gray-700 mb-2">Tarikh Mula</label>
                                            <input type="date" name="tarikh_mula" id="add-tarikh_mula" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                        </div>
                                        <div>
                                            <label for="add-tarikh_akhir" class="block text-sm font-bold text-gray-700 mb-2">Tarikh Akhir</label>
                                            <input type="date" name="tarikh_akhir" id="add-tarikh_akhir" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                        </div>
                                    </div>
                                     <div>
                                        <label for="add-google_map_url" class="block text-sm font-bold text-gray-700 mb-2">Pautan Google Maps</label>
                                        <input type="url" name="google_map_url" id="add-google_map_url" placeholder="https://maps.app.goo.gl/..." class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" @click="addModalOpen = false" class="w-full inline-flex justify-center items-center rounded-xl border-2 border-gray-300 shadow-sm px-5 py-3 bg-white text-base font-bold text-gray-700 hover:bg-gray-50 focus:outline-none transition-all duration-200 hover:scale-105">Batal</button>
                    <button type="submit" form="addForm" name="add_company" class="btn-glow w-full inline-flex justify-center items-center rounded-xl border border-transparent shadow-lg px-5 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-base font-bold text-white hover:from-blue-700 hover:to-purple-700 focus:outline-none transition-all duration-200 hover:scale-105">Simpan</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div x-show="editModalOpen" x-cloak class="fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="editModalOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 modal-backdrop" 
                 aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="editModalOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom glass-modal rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div>
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-lg">
                        <i class="fas fa-edit text-white text-2xl"></i>
                    </div>
                    <div class="mt-4 text-center">
                        <h3 class="text-2xl font-black text-gray-900 mb-2">Kemas Kini Syarikat</h3>
                        <p class="text-sm text-gray-500">Edit maklumat syarikat</p>
                         <div class="mt-6 text-left">
                            <form action="gbpekema.php" method="POST" id="editForm">
                                <input type="hidden" name="id" x-model="companyToEdit.id">
                                <div class="space-y-4">
                                     <div>
                                        <label for="edit-nama" class="block text-sm font-bold text-gray-700 mb-2">Nama Syarikat <span class="text-red-500">*</span></label>
                                        <input type="text" name="nama" id="edit-nama" required x-model="companyToEdit.nama" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div>
                                        <label for="edit-kod_gudang" class="block text-sm font-bold text-gray-700 mb-2">Kod Gudang</label>
                                        <input type="text" name="kod_gudang" id="edit-kod_gudang" x-model="companyToEdit.kod_gudang" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div>
                                        <label for="edit-pic" class="block text-sm font-bold text-gray-700 mb-2">PIC</label>
                                        <input type="text" name="pic" id="edit-pic" x-model="companyToEdit.pic" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div>
                                        <label for="edit-alamat" class="block text-sm font-bold text-gray-700 mb-2">Alamat Syarikat</label>
                                        <textarea name="alamat" id="edit-alamat" rows="3" x-model="companyToEdit.alamat" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200"></textarea>
                                    </div>
                                    <!-- Negeri Dropdown (Edit) -->
                                    <div>
                                        <label for="edit-negeri" class="block text-sm font-bold text-gray-700 mb-2">Negeri</label>
                                        <div class="relative">
                                            <i class="fas fa-map absolute left-4 top-3.5 text-gray-400"></i>
                                            <select name="negeri" id="edit-negeri" x-model="companyToEdit.negeri" required class="w-full pl-11 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-purple-500 transition-colors appearance-none bg-white shadow-sm mt-1">
                                                <option value="" disabled>Pilih Negeri</option>
                                                <option value="KLIA">KLIA</option>
                                                <option value="Johor">Johor</option>
                                                <option value="Kedah">Kedah</option>
                                                <option value="Kelantan">Kelantan</option>
                                                <option value="Melaka">Melaka</option>
                                                <option value="Negeri Sembilan">Negeri Sembilan</option>
                                                <option value="Pahang">Pahang</option>
                                                <option value="Perak">Perak</option>
                                                <option value="Perlis">Perlis</option>
                                                <option value="Pulau Pinang">Pulau Pinang</option>
                                                <option value="Sabah">Sabah</option>
                                                <option value="Sarawak">Sarawak</option>
                                                <option value="Selangor">Selangor</option>
                                                <option value="Terengganu">Terengganu</option>
                                                <option value="W.P. Kuala Lumpur">W.P. Kuala Lumpur</option>
                                                <option value="W.P. Labuan">W.P. Labuan</option>
                                                <option value="W.P. Putrajaya">W.P. Putrajaya</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700 mt-1">
                                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="edit-tarikh_kuatkuasa_lesen" class="block text-sm font-bold text-gray-700 mb-2">Tarikh Kuatkuasa Lesen</label>
                                        <input type="date" name="tarikh_kuatkuasa_lesen" id="edit-tarikh_kuatkuasa_lesen" x-model="companyToEdit.tarikh_kuatkuasa_lesen" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div>
                                        <label for="edit-no_tel" class="block text-sm font-bold text-gray-700 mb-2">No. Telefon</label>
                                        <input type="text" name="no_tel" id="edit-no_tel" x-model="companyToEdit.no_tel" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="edit-tarikh_mula" class="block text-sm font-bold text-gray-700 mb-2">Tarikh Mula</label>
                                            <input type="date" name="tarikh_mula" id="edit-tarikh_mula" x-model="companyToEdit.tarikh_mula" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                        </div>
                                        <div>
                                            <label for="edit-tarikh_akhir" class="block text-sm font-bold text-gray-700 mb-2">Tarikh Akhir</label>
                                            <input type="date" name="tarikh_akhir" id="edit-tarikh_akhir" x-model="companyToEdit.tarikh_akhir" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                        </div>
                                    </div>
                                     <div>
                                        <label for="edit-google_map_url" class="block text-sm font-bold text-gray-700 mb-2">Pautan Google Maps</label>
                                        <input type="url" name="google_map_url" id="edit-google_map_url" placeholder="https://maps.app.goo.gl/..." x-model="companyToEdit.google_map_url" class="mt-1 block w-full border-2 border-gray-200 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-100 transition-all duration-200">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" @click="editModalOpen = false" class="w-full inline-flex justify-center items-center rounded-xl border-2 border-gray-300 shadow-sm px-5 py-3 bg-white text-base font-bold text-gray-700 hover:bg-gray-50 focus:outline-none transition-all duration-200 hover:scale-105">Batal</button>
                    <button type="submit" form="editForm" name="update_company" class="btn-glow w-full inline-flex justify-center items-center rounded-xl border border-transparent shadow-lg px-5 py-3 bg-gradient-to-r from-amber-500 to-orange-600 text-base font-bold text-white hover:from-amber-600 hover:to-orange-700 focus:outline-none transition-all duration-200 hover:scale-105">Simpan</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Optional: Add some animations to table rows on search/filter
        document.addEventListener('alpine:init', () => {
            // Alpine initialized
        });
    </script>
</body>
</html>
