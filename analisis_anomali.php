<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

// if (!isset($_SESSION['user_email'])) {
//     header("Location: login.php");
//     exit();
// }

// --- ADVANCED ANOMALY DETECTION LOGIC ---

// Check if engine_cc column exists (actual column name in database)
$columns_check = $conn->query("SHOW COLUMNS FROM vehicle_inventory LIKE 'engine_cc'");
$has_capacity = ($columns_check && $columns_check->num_rows > 0);

// 1. Calculate base statistics per model
$capacity_fields = $has_capacity ? "AVG(engine_cc) as avg_cap, STDDEV(engine_cc) as stddev_cap," : "0 as avg_cap, 0 as stddev_cap,";

$is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

$where_stats = ["vehicle_model IS NOT NULL", "vehicle_model != ''"];
if ($is_licensee) {
    $where_stats[] = "gbpekema_id = " . (int)$licensee_gb_id;
}
$where_stats_sql = implode(" AND ", $where_stats);

$stats_sql = "SELECT 
                vehicle_model, 
                AVG(duty_rm) as avg_duty, 
                STDDEV(duty_rm) as stddev_duty,
                {$capacity_fields}
                COUNT(id) as unit_count
              FROM vehicle_inventory
              WHERE $where_stats_sql
              GROUP BY vehicle_model
              HAVING unit_count > 1";

$stats_res = $conn->query($stats_sql);
$model_stats = [];
if ($stats_res) {
    while ($row = $stats_res->fetch_assoc()) {
        $model_stats[$row['vehicle_model']] = $row;
    }
}

// 2. Identify different types of anomalies
$anomalies = [
    'duty' => [],
    'chassis' => [],
    'capacity' => [],
    'unusual' => [],
    'missing_data' => [],
    'price_outlier' => [],
    'unpaid_duty' => []  // NEW: Kenderaan belum bayar cukai
];

$all_duties = []; // For Distribution Chart

$z_threshold = 2.0;

// Fetch all records for deep scan
$capacity_select = $has_capacity ? "v.engine_cc as capacity," : "0 as capacity,";
$where_scan = [];
if ($is_licensee) {
    $where_scan[] = "v.gbpekema_id = " . (int)$licensee_gb_id;
}
$where_scan_sql = !empty($where_scan) ? " WHERE " . implode(" AND ", $where_scan) : "";

$scan_sql = "SELECT v.id, v.vehicle_model, v.chassis_number as chassis_no, v.duty_rm, {$capacity_select} v.created_at, v.payment_date, g.nama as company 
             FROM vehicle_inventory v
             LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
             $where_scan_sql";
$scan_res = $conn->query($scan_sql);

$chassis_map = []; // For duplicate detection

while ($v = $scan_res->fetch_assoc()) {
    $has_issue = false;

    if ($v['duty_rm'] > 0) {
        $all_duties[] = (float) $v['duty_rm'];
    }

    // Missing Critical Data Check
    if (empty($v['vehicle_model']) || empty($v['chassis_no'])) {
        $missing_fields = [];
        if (empty($v['vehicle_model']))
            $missing_fields[] = 'Model';
        if (empty($v['chassis_no']))
            $missing_fields[] = 'Chassis No';

        $anomalies['missing_data'][] = array_merge($v, [
            'reason' => 'Missing: ' . implode(', ', $missing_fields),
            'severity' => 'Critical',
            'missing_fields' => $missing_fields
        ]);
        $has_issue = true;
    }

    // Duplicate Chassis Check
    $c_no = trim($v['chassis_no']);
    if (!empty($c_no)) {
        if (isset($chassis_map[$c_no])) {
            $chassis_map[$c_no]++;
            $anomalies['chassis'][] = array_merge($v, ['reason' => 'Duplicate Chassis Detected', 'severity' => 'Critical']);
            $has_issue = true;
        } else {
            $chassis_map[$c_no] = 1;
        }
    }

    // Model-based Statistical Scan
    if (isset($model_stats[$v['vehicle_model']])) {
        $ms = $model_stats[$v['vehicle_model']];

        // Duty Anomaly
        if ($ms['stddev_duty'] > 0) {
            $z = abs(($v['duty_rm'] - $ms['avg_duty']) / $ms['stddev_duty']);
            if ($z > $z_threshold) {
                $sev = ($z > 3.5) ? 'Critical' : (($z > 2.5) ? 'High' : 'Medium');
                $anomalies['duty'][] = array_merge($v, [
                    'z_score' => $z,
                    'avg' => $ms['avg_duty'],
                    'severity' => $sev,
                    'diff' => $v['duty_rm'] - $ms['avg_duty']
                ]);
                $has_issue = true;
            }
        }

        // Capacity Anomaly (only if capacity column exists)
        if ($has_capacity && $ms['stddev_cap'] > 0) {
            $z_cap = abs(($v['capacity'] - $ms['avg_cap']) / $ms['stddev_cap']);
            if ($z_cap > $z_threshold) {
                $anomalies['capacity'][] = array_merge($v, [
                    'z_score' => $z_cap,
                    'avg' => $ms['avg_cap'],
                    'severity' => 'High',
                    'reason' => 'Unusual Engine Capacity'
                ]);
                $has_issue = true;
            }
        }
    }

    // Logic-based Unusual check
    if ($v['duty_rm'] < 1000 && !empty($v['vehicle_model'])) {
        $anomalies['unusual'][] = array_merge($v, ['reason' => 'Extremely Low Duty Value', 'severity' => 'High']);
    }

    // Price Outlier Detection (extremely high duty values)
    if ($v['duty_rm'] > 200000) {
        $anomalies['price_outlier'][] = array_merge($v, [
            'reason' => 'Exceptionally High Duty Value',
            'severity' => 'High',
            'duty_value' => $v['duty_rm']
        ]);
    }

    // Unpaid Duty Detection (NEW: Kenderaan belum bayar cukai)
    // Detect vehicles with duty amount but no payment date or receipt
    if ($v['duty_rm'] > 0 && (empty($v['payment_date']) || $v['payment_date'] == '0000-00-00')) {
        $days_since_creation = 0;
        if (!empty($v['created_at'])) {
            $created = strtotime($v['created_at']);
            $now = time();
            $days_since_creation = floor(($now - $created) / 86400);
        }

        $severity = 'Low';
        $reason = 'Stok Dalam Gudang (Belum Dijual)';

        if ($days_since_creation > 730) { // > 2 Tahun
            $severity = 'Critical';
            $reason = 'Stok Lama (>2 Tahun)';
        } elseif ($days_since_creation > 365) { // > 1 Tahun
            $severity = 'High';
            $reason = 'Stok Perlahan (>1 Tahun)';
        } elseif ($days_since_creation > 180) { // > 6 Bulan
            $severity = 'Medium';
        }

        $anomalies['unpaid_duty'][] = array_merge($v, [
            'reason' => $reason,
            'severity' => $severity,
            'days_unpaid' => $days_since_creation,
            'duty_amount' => $v['duty_rm']
        ]);
    }
}

// Summary stats and AI Logic
$count_duty = count($anomalies['duty']);
$count_tech = count($anomalies['chassis']) + count($anomalies['capacity']);
$count_missing = count($anomalies['missing_data']);
$count_price = count($anomalies['price_outlier']);
$count_unpaid = count($anomalies['unpaid_duty']);
$total_anomalies = $count_duty + $count_tech + count($anomalies['unusual']) + $count_missing + $count_price + $count_unpaid;

// Calculate total unpaid amount
$total_unpaid_amount = 0;
$critical_unpaid = 0;
foreach ($anomalies['unpaid_duty'] as $unpaid) {
    $total_unpaid_amount += $unpaid['duty_rm'];
    if ($unpaid['severity'] == 'Critical')
        $critical_unpaid++;
}

// Dynamic Insight Generation
$ai_message = "Analisis selesai. Tiada anomali kritikal dikesan dalam pangkalan data.";
$ai_actions = ["Teruskan pemantauan berkala.", "Kemaskini pangkalan data dengan rekod terkini."];
$system_status = "SECURE";
$status_color = "text-emerald-400";
$risk_level = "LOW";

if ($total_anomalies > 0) {
    // Prioritize unpaid duties (most critical for revenue)
    if ($count_unpaid > 0) {
        $ai_message = "NOTIS: Sebanyak {$count_unpaid} unit kenderaan masih berada dalam pegangan gudang (Bonded Stock) dengan status duti belum dijelaskan. Anggaran nilai duti tertangguh: RM " . number_format($total_unpaid_amount, 2) . ". ";
        if ($critical_unpaid > 0) {
            $ai_message .= "{$critical_unpaid} unit telah melebihi tempoh 2 tahun dalam simpanan (Aging Stock).";
        }
        $ai_actions = [
            "Semak status jualan bagi kenderaan > 2 tahun.",
            "Pastikan bon gudang mencukupi untuk menampung duti tertangguh.",
            "Lakukan promosi jualan untuk stok lama (Aging Stock).",
            "Sahkan status fizikal kenderaan di gudang.",
            "Jana laporan pegangan stok (Stock Holding Report)."
        ];
        $system_status = "MONITOR";
        $status_color = "text-amber-400";
        $risk_level = "MEDIUM";
    }
    // Prioritize by severity
    elseif ($count_missing > 0) {
        $ai_message = "AMARAN: Sebanyak {$count_missing} rekod dengan data kritikal yang hilang dikesan. Ini boleh menjejaskan integriti keseluruhan sistem dan menyukarkan audit.";
        $ai_actions = [
            "Segera lengkapkan medan kritikal yang hilang (Model/Chassis No).",
            "Hubungi pasukan data entry untuk pengesahan.",
            "Lakukan data cleansing menyeluruh.",
            "Aktifkan validasi input untuk mencegah isu berulang."
        ];
        $system_status = "CRITICAL";
        $status_color = "text-rose-500";
        $risk_level = "CRITICAL";
    } elseif ($count_duty > $count_tech) {
        $ai_message = "Corak ketidakteraturan dikesan dalam penilaian duti import. Sebanyak {$count_duty} anomali dikesan di mana nilai cukai menyimpang signifikan dari min model.";
        $ai_actions = [
            "Lakukan audit manual pada model berisiko tinggi.",
            "Semak semula pengisytiharan kastam bagi tahun semasa.",
            "Sahkan kod tarif bagi model yang terlibat.",
            "Bandingkan dengan harga pasaran semasa."
        ];
        $system_status = "WARNING";
        $status_color = "text-amber-400";
        $risk_level = "MEDIUM";
    } elseif ($count_tech > 0) {
        $ai_message = "Isu integriti data teknikal dikesan. Terdapat pertindihan nombor casis atau ralat kapasiti enjin yang memerlukan perhatian segera.";
        $ai_actions = [
            "Sahkan integriti data fizikal kenderaan.",
            "Hubungi pasukan teknikal untuk semakan rekod.",
            "Jalankan pembersihan data (Data Cleansing).",
            "Periksa sistem import data untuk ralat."
        ];
        $system_status = "ALERT";
        $status_color = "text-rose-500";
        $risk_level = "HIGH";
    }

    // Additional check for price outliers
    if ($count_price > 0) {
        $ai_message .= " Turut dikesan <a href=\"javascript:void(0)\" onclick=\"filterSection('price'); document.getElementById('price-panel').scrollIntoView({behavior: 'smooth', block: 'center'});\" class=\"underline font-bold text-blue-600 hover:text-blue-800 transition-colors\">{$count_price} kenderaan dengan nilai duti luar biasa tinggi yang memerlukan verifikasi khas</a>.";
        if (!in_array("Sahkan harga dengan vendor rasmi.", $ai_actions)) {
            $ai_actions[] = "Sahkan harga dengan vendor rasmi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Anomaly Scanner - MyPEKEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0284c7;
            --secondary: #2563eb;
            --accent: #f59e0b;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --bg-deep: #f8fafc;
            --premium-blue: #0ea5e9;
            --glass-white: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(226, 232, 240, 0.8);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            background-attachment: fixed;
            color: #1e293b;
            overflow-x: hidden;
        }

        .space-font {
            font-family: 'Space Grotesk', sans-serif;
        }

        /* Modern Glassmorphism Upgrade */
        /* Modern Glassmorphism Upgrade - Light Mode */
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 20px 50px -12px rgba(100, 116, 139, 0.1);
        }

        .glass-premium {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 1);
            box-shadow: 0 25px 50px -12px rgba(100, 116, 139, 0.15);
        }

        .glass-strong {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 20px 40px -10px rgba(59, 130, 246, 0.15);
        }

        /* Animated Grid Background */
        .grid-bg {
            background-size: 50px 50px;
            background-image: linear-gradient(to right, rgba(15, 23, 42, 0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(15, 23, 42, 0.08) 1px, transparent 1px);
            mask-image: radial-gradient(circle at center, black 0%, transparent 80%);
        }

        /* Glowing Scanner Line */
        .scanner-line {
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary), var(--accent), transparent);
            position: absolute;
            width: 100%;
            top: 0;
            animation: scan 4s ease-in-out infinite;
            z-index: 10;
            box-shadow: 0 0 15px var(--primary);
        }

        @keyframes scan {
            0% {
                top: 0%;
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                top: 100%;
                opacity: 0;
            }
        }

        .glow-card {
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glow-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 20px 40px -10px rgba(0, 45, 98, 0.3);
            border-color: rgba(0, 45, 98, 0.3);
        }

        /* Ambient Glow Effects */
        .ambient-glow {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(0, 45, 98, 0.15) 0%, transparent 70%);
            top: -100px;
            left: -100px;
            pointer-events: none;
            z-index: 0;
            filter: blur(80px);
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.5);
        }

        ::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Status Badges */
        .status-badge {
            position: relative;
            z-index: 1;
        }

        .status-badge::before {
            content: '';
            position: absolute;
            inset: -2px;
            background: currentColor;
            opacity: 0.15;
            border-radius: inherit;
            z-index: -1;
            filter: blur(4px);
        }

        .tab-btn {
            position: relative;
            overflow: hidden;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            box-shadow: 0 8px 20px -5px rgba(99, 102, 241, 0.5);
            border: none;
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-enter {
            animation: slideInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .delay-100 {
            animation-delay: 100ms;
        }

        .delay-200 {
            animation-delay: 200ms;
        }

        .delay-300 {
            animation-delay: 300ms;
        }

        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            background-size: 200% 100%;
            animation: shimmer 2s infinite linear;
        }

        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }
    </style>
</head>

<body class="min-h-screen selection:bg-blue-500/30">

    <?php include 'topmenu.php'; ?>

    <!-- Deep Ambient Background -->
    <!-- Deep Ambient Background -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] bg-blue-300/20 rounded-full blur-[120px] animate-pulse"
            style="animation-duration: 12s;"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[60%] h-[60%] bg-indigo-300/20 rounded-full blur-[120px] animate-pulse"
            style="animation-duration: 15s;"></div>
        <div class="grid-bg absolute inset-0 opacity-100"></div>
    </div>

    <main class="container mx-auto px-4 lg:px-8 py-12 relative z-10">

        <!-- Header Section -->
        <header class="flex flex-col lg:flex-row justify-between items-end gap-8 mb-16 animate-enter">
            <div class="w-full lg:w-auto">
                <div class="flex items-center gap-3 mb-6">
                    <div
                        class="relative flex items-center gap-2 px-3 py-1.5 bg-white border border-blue-200 rounded-full shadow-sm">
                        <span class="flex h-2 w-2 relative">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                        </span>
                        <span class="text-blue-600 text-[10px] font-black uppercase tracking-[0.2em]"><i
                                class="fas fa-microchip mr-2"></i>AI Vision Core v2.4</span>
                    </div>
                </div>
                <h1
                    class="text-6xl lg:text-8xl font-black space-font tracking-tighter text-slate-900 mb-6 leading-none">
                    Deteksi <span
                        class="text-transparent bg-clip-text bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800">Anomali</span>
                </h1>
                <p class="text-slate-600 text-xl max-w-2xl leading-relaxed font-medium">
                    Sistem pemantauan berasaskan <span class="text-blue-600 font-bold">Kecerdasan Buatan</span> yang
                    mengimbas pangkalan data secara real-time.
                </p>
            </div>

            <!-- Quick Stats Cards -->
            <!-- Premium Quick Stats Chips -->
            <div class="flex flex-wrap lg:flex-nowrap gap-6 w-full lg:w-auto">
                <div class="glass-premium p-6 rounded-[2rem] min-w-[220px] relative overflow-hidden group">
                    <div
                        class="absolute -top-4 -right-4 w-24 h-24 bg-rose-500/5 rounded-full blur-2xl group-hover:bg-rose-500/10 transition-all">
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <p
                                class="text-[10px] font-black text-slate-500 uppercase tracking-widest group-hover:text-rose-500">
                                Anomali Aktif</p>
                            <div
                                class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center text-rose-500 border border-rose-100">
                                <i class="fas fa-bolt-lightning"></i>
                            </div>
                        </div>
                        <p class="text-5xl font-black text-slate-800 count-up space-font"
                            data-target="<?= $total_anomalies ?>">0</p>
                    </div>
                </div>
                <div class="glass-premium p-6 rounded-[2rem] min-w-[220px] relative overflow-hidden group">
                    <div
                        class="absolute -top-4 -right-4 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all">
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <p
                                class="text-[10px] font-black text-slate-500 uppercase tracking-widest group-hover:text-blue-400">
                                Status Sistem</p>
                            <div
                                class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-500">
                                <i class="fas fa-satellite-dish"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-black <?= $status_color ?> space-font tracking-tight uppercase">
                            <?= $system_status ?>
                        </p>
                        <div class="mt-2 flex items-center gap-1.5">
                            <span class="h-1 flex-1 bg-blue-500/20 rounded-full overflow-hidden">
                                <span class="block h-full bg-blue-500 w-[85%] animate-pulse"></span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="glass-premium p-6 rounded-[2rem] min-w-[220px] relative overflow-hidden group">
                    <div
                        class="absolute -top-4 -right-4 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl group-hover:bg-amber-500/10 transition-all">
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <p
                                class="text-[10px] font-black text-slate-500 uppercase tracking-widest group-hover:text-amber-500">
                                Risiko</p>
                            <div
                                class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500 border border-amber-100">
                                <i class="fas fa-triangle-exclamation"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-black <?= $status_color ?> space-font tracking-tight uppercase">
                            <?= $risk_level ?>
                        </p>
                        <p class="text-[9px] text-slate-400 font-bold mt-2 uppercase tracking-tighter italic">Based on
                            AI Scan</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- Left Column: Anomaly Feed (Span 8) -->
            <div class="lg:col-span-8 space-y-8 animate-enter delay-100">

                <!-- Premium Controls Bar -->
                <div
                    class="glass-premium p-4 rounded-[2rem] flex flex-col sm:flex-row gap-6 justify-between items-center sticky top-28 z-30 shadow-xl backdrop-blur-3xl border-white/40">

                    <!-- Tabs -->
                    <div
                        class="flex p-1.5 bg-slate-100/80 rounded-2xl overflow-x-auto max-w-full no-scrollbar shadow-inner">
                        <button onclick="filterSection('all')" id="tab-all"
                            class="tab-btn active px-8 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all">Semua
                            Isu</button>
                        <button onclick="filterSection('unpaid')" id="tab-unpaid"
                            class="tab-btn px-8 py-3 text-slate-500 hover:text-blue-600 rounded-xl text-xs font-black uppercase tracking-wider transition-all">
                            <span
                                class="w-2 h-2 rounded-full bg-orange-500 inline-block mr-2 shadow-[0_0_8px_rgba(249,115,22,0.6)]"></span>Stok
                            Tertangguh
                        </button>
                        <button onclick="filterSection('duty')" id="tab-duty"
                            class="tab-btn px-8 py-3 text-slate-500 hover:text-blue-600 rounded-xl text-xs font-black uppercase tracking-wider transition-all">
                            <span
                                class="w-2 h-2 rounded-full bg-rose-500 inline-block mr-2 shadow-[0_0_8px_rgba(244,63,94,0.6)]"></span>Cukai
                        </button>
                        <button onclick="filterSection('price')" id="tab-price"
                            class="tab-btn px-8 py-3 text-slate-500 hover:text-blue-600 rounded-xl text-xs font-black uppercase tracking-wider transition-all">
                            <span
                                class="w-2 h-2 rounded-full bg-purple-500 inline-block mr-2 shadow-[0_0_8px_rgba(168,85,247,0.6)]"></span>Harga Luar Biasa
                        </button>
                        <button onclick="filterSection('tech')" id="tab-tech"
                            class="tab-btn px-8 py-3 text-slate-500 hover:text-blue-600 rounded-xl text-xs font-black uppercase tracking-wider transition-all">
                            <span
                                class="w-2 h-2 rounded-full bg-amber-500 inline-block mr-2 shadow-[0_0_8px_rgba(245,158,11,0.6)]"></span>Teknikal
                        </button>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 w-full sm:w-auto">
                        <div class="relative flex-1 sm:flex-none">
                            <i
                                class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="searchInput" placeholder="Carian Anomali..."
                                class="w-full sm:w-64 bg-white border border-slate-200 text-sm text-slate-700 rounded-xl pl-11 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all placeholder:text-slate-400 shadow-sm">
                        </div>
                        <button onclick="window.print()"
                            class="w-12 h-12 flex items-center justify-center bg-white hover:bg-slate-50 border border-slate-200 rounded-xl text-slate-500 hover:text-blue-600 transition-all group shadow-sm">
                            <i class="fas fa-print group-hover:scale-110 transition-transform"></i>
                        </button>
                    </div>
                </div>

                <!-- Unpaid Duty Section (Premium Table) -->
                <section id="unpaid-panel"
                    class="glow-card glass-premium rounded-[2.5rem] overflow-hidden border-orange-200">
                    <div class="scanner-line"></div>
                    <div
                        class="p-8 border-b border-slate-200 bg-gradient-to-r from-orange-50 via-white to-transparent flex justify-between items-center">
                        <div class="flex items-center gap-6">
                            <div
                                class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center text-orange-500 shadow-lg shadow-orange-100">
                                <i class="fas fa-warehouse text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-black text-slate-800 space-font tracking-tight">Analisis Stok
                                    Tertangguh</h2>
                                <p class="text-[10px] text-orange-500 font-black uppercase tracking-[0.2em] mt-1">Cukai
                                    Belum Jelas (Lebih 30 Hari)</p>
                            </div>
                        </div>
                        <div class="text-right hidden md:block">
                            <p class="text-4xl font-black space-font text-slate-800">RM
                                <?= number_format($total_unpaid_amount, 2) ?>
                            </p>
                            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-black mt-1">
                                <?= count($anomalies['unpaid_duty']) ?> Kes Diimbas
                            </p>
                        </div>
                    </div>

                    <div class="max-h-[500px] overflow-y-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-white/95 backdrop-blur-md shadow-sm z-10">
                                <tr
                                    class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 border-b border-slate-200">
                                    <th class="px-8 py-5">Kenderaan & Rekod</th>
                                    <th class="px-8 py-5 text-right">Amaun Cukai</th>
                                    <th class="px-8 py-5 text-center">Tempoh Tertunggak</th>
                                    <th class="px-8 py-5 text-center">Tahap Risiko</th>
                                    <th class="px-8 py-5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($anomalies['unpaid_duty'])): ?>
                                    <tr>
                                        <td colspan="5"
                                            class="px-8 py-20 text-center text-slate-500 font-bold italic tracking-wide">
                                            Tiada tunggakan cukai dikesan. Sistem dalam keadaan optimum.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($anomalies['unpaid_duty'] as $item): ?>
                                        <tr class="group hover:bg-slate-50 transition-all duration-300">
                                            <td class="px-8 py-6">
                                                <div
                                                    class="font-black text-sm text-slate-700 group-hover:text-blue-600 transition-colors uppercase tracking-tight">
                                                    <?= htmlspecialchars($item['vehicle_model']) ?>
                                                </div>
                                                <div class="flex items-center gap-2 mt-1.5">
                                                    <span
                                                        class="text-[10px] font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded uppercase"><?= $item['chassis_no'] ?></span>
                                                    <span
                                                        class="text-[10px] text-slate-400 font-bold truncate max-w-[120px]"><?= htmlspecialchars($item['company'] ?? 'N/A') ?></span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 text-right">
                                                <div class="font-black text-lg text-slate-700 space-font">RM
                                                    <?= number_format((float) $item['duty_rm'], 2) ?>
                                                </div>
                                                <p class="text-[9px] text-slate-400 font-black uppercase tracking-tighter">
                                                    Penilaian Semasa</p>
                                            </td>
                                            <td class="px-8 py-6 text-center">
                                                <div
                                                    class="inline-flex items-center gap-2.5 px-4 py-1.5 bg-white rounded-full border border-slate-200 shadow-sm">
                                                    <i
                                                        class="fas fa-history text-[10px] <?= $item['days_unpaid'] > 90 ? 'text-rose-500' : 'text-emerald-500' ?> animate-pulse"></i>
                                                    <span class="text-xs font-black text-slate-600"><?= $item['days_unpaid'] ?>
                                                        HARI</span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 text-center">
                                                <span
                                                    class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-xl border 
                                                <?= $item['severity'] == 'Critical' ? 'bg-rose-50 text-rose-500 border-rose-200 shadow-sm' : 'bg-orange-50 text-orange-500 border-orange-200' ?>">
                                                    <?= $item['severity'] ?>
                                                </span>
                                            </td>
                                            <td class="px-8 py-6 text-right">
                                                <a href="vehicle_details.php?id=<?= $item['id'] ?>"
                                                    class="inline-flex w-10 h-10 rounded-xl bg-white hover:bg-blue-600 border border-slate-100 text-slate-400 hover:text-white items-center justify-center transition-all group/btn shadow-md">
                                                    <i
                                                        class="fas fa-arrow-right text-xs group-hover/btn:translate-x-1 transition-transform"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Duty Anomalies Section (Premium Cards) -->
                <section id="duty-panel"
                    class="glow-card glass-premium rounded-[2.5rem] overflow-hidden border-rose-200">
                    <div
                        class="p-8 border-b border-slate-200 bg-gradient-to-r from-rose-50 via-white to-transparent flex justify-between items-center">
                        <div class="flex items-center gap-6">
                            <div
                                class="w-14 h-14 rounded-2xl bg-rose-100 flex items-center justify-center text-rose-500 shadow-lg shadow-rose-100">
                                <i class="fas fa-microchip-ai text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-black text-slate-800 space-font tracking-tight">Pengesanan
                                    Sisihan
                                    Cukai</h2>
                                <p class="text-[10px] text-rose-500 font-black uppercase tracking-[0.2em] mt-1">
                                    Algoritma Z-Score (Ambang >2.0)</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="px-3 py-1.5 bg-rose-50 border border-rose-200 text-rose-500 text-[10px] font-black rounded-xl uppercase tracking-widest"><?= count($anomalies['duty']) ?>
                                Deteksi</span>
                        </div>
                    </div>

                    <div class="max-h-[500px] overflow-y-auto custom-scrollbar w-full">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-white/95 backdrop-blur-md shadow-sm z-10">
                                <tr
                                    class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 border-b border-slate-200">
                                    <th class="px-8 py-5">Kenderaan & Rekod</th>
                                    <th class="px-8 py-5 text-right">Nilai Duti</th>
                                    <th class="px-8 py-5 text-center">Penyimpangan (RM)</th>
                                    <th class="px-8 py-5 text-center">Skor Keyakinan</th>
                                    <th class="px-8 py-5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($anomalies['duty'])): ?>
                                    <tr>
                                        <td colspan="5"
                                            class="px-8 py-20 text-center text-slate-500 font-bold italic tracking-wide">
                                            Pangkalan data dalam keadaan konsisten. Tiada penyimpangan cukai dikesan.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($anomalies['duty'] as $item): ?>
                                        <tr class="group hover:bg-slate-50 transition-all duration-300">
                                            <td class="px-8 py-6">
                                                <div
                                                    class="font-black text-sm text-slate-700 group-hover:text-blue-600 transition-colors uppercase tracking-tight">
                                                    <?= htmlspecialchars($item['vehicle_model']) ?>
                                                </div>
                                                <div class="flex items-center gap-2 mt-1.5">
                                                    <span
                                                        class="text-[10px] font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded uppercase"><?= htmlspecialchars($item['chassis_no'] ?? 'N/A') ?></span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 text-right">
                                                <div class="font-black text-lg text-slate-700 space-font">RM
                                                    <?= number_format((float) $item['duty_rm'], 2) ?>
                                                </div>
                                                <p class="text-[9px] text-slate-400 font-black uppercase tracking-tighter">Amaun
                                                    Dilaporkan</p>
                                            </td>
                                            <td class="px-8 py-6 text-center">
                                                <div class="inline-flex items-center justify-center gap-2">
                                                    <span
                                                        class="text-xs font-black <?= $item['diff'] > 0 ? 'text-rose-500' : 'text-emerald-500' ?>">
                                                        <?= $item['diff'] > 0 ? '▲' : '▼' ?> RM
                                                        <?= number_format(abs((float) $item['diff'])) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 text-center">
                                                <div class="flex flex-col items-center gap-1.5 w-full max-w-[120px] mx-auto">
                                                    <span
                                                        class="text-[10px] font-black text-rose-500 uppercase"><?= number_format((float) $item['z_score'], 1) ?>_Z</span>
                                                    <div class="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden">
                                                        <div class="h-full bg-gradient-to-r from-rose-500 to-rose-400 rounded-full"
                                                            style="width: <?= min(100, $item['z_score'] * 20) ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 text-right">
                                                <a href="vehicle_details.php?id=<?= $item['id'] ?>"
                                                    class="inline-flex w-10 h-10 rounded-xl bg-white hover:bg-blue-600 border border-slate-100 text-slate-400 hover:text-white items-center justify-center transition-all group/btn shadow-md">
                                                    <i
                                                        class="fas fa-arrow-right text-xs group-hover/btn:translate-x-1 transition-transform"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Price Anomalies Section -->
                <section id="price-panel" class="glow-card glass-premium rounded-[2.5rem] overflow-hidden border-purple-200">
                    <div class="p-8 border-b border-slate-200 bg-gradient-to-r from-purple-50 via-white to-transparent flex justify-between items-center">
                        <div class="flex items-center gap-6">
                            <div class="w-14 h-14 rounded-2xl bg-purple-100 flex items-center justify-center text-purple-500 shadow-lg shadow-purple-100">
                                <i class="fas fa-money-bill-wave text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-black text-slate-800 space-font tracking-tight">Verifikasi Harga Khas</h2>
                                <p class="text-[10px] text-purple-500 font-black uppercase tracking-[0.2em] mt-1">Duti melebihi RM 200,000</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1.5 bg-purple-50 border border-purple-200 text-purple-500 text-[10px] font-black rounded-xl uppercase tracking-widest"><?= count($anomalies['price_outlier']) ?> Kes</span>
                        </div>
                    </div>

                    <div class="max-h-[500px] overflow-y-auto custom-scrollbar w-full">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-white/95 backdrop-blur-md shadow-sm z-10">
                                <tr class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 border-b border-slate-200">
                                    <th class="px-8 py-5">Kenderaan & Rekod</th>
                                    <th class="px-8 py-5 text-right">Nilai Duti</th>
                                    <th class="px-8 py-5 text-center">Tahap Risiko</th>
                                    <th class="px-8 py-5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($anomalies['price_outlier'])): ?>
                                    <tr>
                                        <td colspan="4" class="px-8 py-20 text-center text-slate-500 font-bold italic tracking-wide">Tiada kenderaan dikesan mempunyai nilai duti luar biasa.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($anomalies['price_outlier'] as $item): ?>
                                        <tr class="group hover:bg-slate-50 transition-all duration-300">
                                            <td class="px-8 py-6">
                                                <div class="font-black text-sm text-slate-700 group-hover:text-purple-600 transition-colors uppercase tracking-tight">
                                                    <?= htmlspecialchars($item['vehicle_model']) ?>
                                                </div>
                                                <div class="flex items-center gap-2 mt-1.5">
                                                    <span class="text-[10px] font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded uppercase"><?= htmlspecialchars($item['chassis_no'] ?? 'N/A') ?></span>
                                                </div>
                                            </td>
                                            <td class="px-8 py-6 text-right">
                                                <div class="font-black text-lg text-slate-700 space-font">RM
                                                    <?= number_format((float) $item['duty_rm'], 2) ?>
                                                </div>
                                                <p class="text-[9px] text-slate-400 font-black uppercase tracking-tighter">Amaun Dilaporkan</p>
                                            </td>
                                            <td class="px-8 py-6 text-center">
                                                <span class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-xl border bg-purple-50 text-purple-600 border-purple-200">
                                                    <?= htmlspecialchars($item['severity']) ?>
                                                </span>
                                            </td>
                                            <td class="px-8 py-6 text-right">
                                                <a href="vehicle_details.php?id=<?= $item['id'] ?>" class="inline-flex w-10 h-10 rounded-xl bg-white hover:bg-purple-600 border border-slate-100 text-slate-400 hover:text-white items-center justify-center transition-all group/btn shadow-md">
                                                    <i class="fas fa-arrow-right text-xs group-hover/btn:translate-x-1 transition-transform"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Technical & Data Integrity (Grid) -->
                <div id="tech-panel" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Duplicate Chassis -->
                    <section class="glow-card glass-premium p-8 rounded-[2.5rem] border-amber-200 group">
                        <div class="flex items-center gap-4 mb-8">
                            <div
                                class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform">
                                <i class="fas fa-fingerprint text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-slate-800 text-lg space-font tracking-tight">Duplikasi Casis
                                </h3>
                                <p class="text-[9px] text-amber-500 font-black uppercase tracking-[0.2em]">Kesan
                                    Perlanggaran Hash</p>
                            </div>
                        </div>
                        <div class="space-y-3 max-h-[350px] overflow-y-auto custom-scrollbar pr-2">
                            <?php if (empty($anomalies['chassis'])): ?>
                                <p class="text-xs text-slate-500 font-bold italic tracking-wide">Integriti nombor casis
                                    selamat.</p>
                            <?php else: ?>
                                <?php foreach ($anomalies['chassis'] as $item): ?>
                                    <div
                                        class="p-4 bg-white border border-slate-100 rounded-2xl flex justify-between items-center group/item hover:bg-amber-50 transition-all shadow-sm">
                                        <span
                                            class="font-black space-font text-xs text-slate-700"><?= $item['chassis_no'] ?></span>
                                        <a href="vehicle_details.php?id=<?= $item['id'] ?>"
                                            class="text-[9px] font-black px-4 py-2 bg-amber-50 text-amber-600 rounded-xl hover:bg-amber-500 hover:text-white transition-all">SELESAIKAN</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Missing Data -->
                    <section class="glow-card glass-premium p-8 rounded-[2.5rem] border-blue-200 group">
                        <div class="flex items-center gap-4 mb-8">
                            <div
                                class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-500 group-hover:scale-110 transition-transform">
                                <i class="fas fa-database text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-slate-800 text-lg space-font tracking-tight">Rekod Tidak
                                    Lengkap</h3>
                                <p class="text-[9px] text-blue-500 font-black uppercase tracking-[0.2em]">Amaran
                                    Sanitasi Data</p>
                            </div>
                        </div>
                        <div class="space-y-3 max-h-[350px] overflow-y-auto custom-scrollbar pr-2">
                            <?php if (empty($anomalies['missing_data'])): ?>
                                <p class="text-xs text-slate-500 font-bold italic tracking-wide">Semua rekod telah melalui
                                    sanitasi penuh.</p>
                            <?php else: ?>
                                <?php foreach ($anomalies['missing_data'] as $item): ?>
                                    <div
                                        class="p-4 bg-white border border-slate-100 rounded-2xl group/item hover:bg-blue-50 transition-all shadow-sm">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-[10px] font-black text-slate-700 uppercase tracking-tighter">ID
                                                Rujukan #<?= $item['id'] ?></span>
                                            <span
                                                class="text-[9px] font-black text-rose-500 uppercase tracking-widest">KRITIKAL</span>
                                        </div>
                                        <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest leading-loose">
                                            Hilang: <span
                                                class="text-blue-500"><?= implode(', ', $item['missing_fields']) ?></span></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

            </div>

            <!-- Right Column: AI Analysis & Charts (Span 4) -->
            <div class="lg:col-span-4 space-y-8 animate-enter delay-200 sticky top-28 self-start">

                <!-- AI Insight Card (Ultra Premium) -->
                <div
                    class="glass-strong rounded-[3rem] p-10 border border-white/50 relative overflow-hidden group shadow-2xl">
                    <div
                        class="absolute -top-24 -right-24 w-64 h-64 bg-blue-100 rounded-full blur-[80px] group-hover:bg-blue-200 transition-all duration-700">
                    </div>

                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-10">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <h3 class="text-[10px] font-black text-blue-600 uppercase tracking-[0.3em]">AI Synthesis
                                    Core</h3>
                            </div>
                            <div
                                class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600">
                                <i class="fas fa-robot text-xl"></i>
                            </div>
                        </div>

                        <div class="relative mb-10">
                            <p class="text-lg text-slate-700 leading-relaxed font-semibold italic">
                                <i class="fas fa-quote-left text-blue-100 text-4xl absolute -top-4 -left-4"></i>
                                <span class="relative z-10"><?= $ai_message ?></span>
                            </p>
                        </div>

                        <!-- Score Ring with Chart.js -->
                        <div class="relative w-48 h-48 mx-auto mb-10">
                            <canvas id="scoreChart"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center flex-col">
                                <span
                                    class="text-4xl font-black text-slate-800 space-font"><?= max(0, 100 - ($total_anomalies * 2)) ?>%</span>
                                <span
                                    class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mt-1">Integrity
                                    Score</span>
                            </div>
                        </div>

                        <?php if (!empty($ai_actions)): ?>
                            <div class="space-y-4 mt-10 p-6 bg-slate-50 rounded-[2rem] border border-slate-100">
                                <p
                                    class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-list-check text-blue-600"></i> Syor Tindakan
                                </p>
                                <?php foreach ($ai_actions as $action): ?>
                                    <div class="flex gap-4 text-xs group">
                                        <div
                                            class="mt-1 w-1.5 h-1.5 rounded-full bg-blue-600 group-hover:scale-150 transition-transform">
                                        </div>
                                        <span
                                            class="text-slate-500 group-hover:text-slate-800 transition-colors leading-relaxed font-medium"><?= htmlspecialchars($action) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Distribution Chart -->
                <div class="glass p-6 rounded-3xl">
                    <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-4">Analisis Taburan</h3>
                    <div class="h-40">
                        <canvas id="dutyChart"></canvas>
                    </div>
                </div>

                <!-- Quick Export -->
                <button onclick="window.print()"
                    class="w-full py-4 bg-gradient-to-r from-blue-900 to-blue-700 rounded-2xl text-sm font-black text-white uppercase tracking-widest hover:shadow-lg hover:shadow-blue-500/30 hover:scale-[1.02] transition-all active:scale-95 group relative overflow-hidden">
                    <div class="shimmer absolute inset-0 opacity-30"></div>
                    <span class="relative flex items-center justify-center gap-3">
                        <i class="fas fa-file-export"></i> Jana Laporan Penuh
                    </span>
                </button>

            </div>
        </div>
    </main>

    <footer class="text-center py-12 relative z-10">
        <p class="text-[10px] font-bold text-slate-600 uppercase tracking-[0.3em]">MyPEKEMA AI Intelligence &copy; 2026
        </p>
    </footer>

    <script>
        // --- CHART CONFIGURATIONS ---
        Chart.defaults.color = '#64748b'; // Slate-500
        Chart.defaults.font.family = "'Outfit', sans-serif";

        // 1. Data Integrity Score (Doughnut)
        const ctxScore = document.getElementById('scoreChart').getContext('2d');
        new Chart(ctxScore, {
            type: 'doughnut',
            data: {
                labels: ['Integriti', 'Risiko'],
                datasets: [{
                    data: [<?= max(0, 100 - ($total_anomalies * 2)) ?>, <?= min(100, $total_anomalies * 2) ?>],
                    backgroundColor: ['#2563eb', '#e2e8f0'], // Premium Blue vs Slate-200
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                cutout: '85%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: { animateScale: true, animateRotate: true }
            }
        });

        // 2. Duty Distribution (Bar/Line) - Simple Mockup
        const ctxDuty = document.getElementById('dutyChart').getContext('2d');
        // Simple distribution based on passed data - simplified for visual
        const dutyData = <?= json_encode(array_slice($all_duties, 0, 20)) ?>;
        // In real app, calculate actual histogram bins. Here just show sample trend.

        new Chart(ctxDuty, {
            type: 'line',
            data: {
                labels: dutyData.map((_, i) => i + 1),
                datasets: [{
                    label: 'Sample Duty',
                    data: dutyData,
                    borderColor: '#f43f5e',
                    backgroundColor: 'rgba(244, 63, 94, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { display: false }
                }
            }
        });

        // --- INTERACTIVITY ---

        // Counter Animation
        document.querySelectorAll('.count-up').forEach(el => {
            const target = parseInt(el.getAttribute('data-target'));
            let current = 0;
            const duration = 1500;
            const stepTime = 20;
            const steps = duration / stepTime;
            const increment = target / steps;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                el.innerText = Math.floor(current);
            }, stepTime);
        });

        // Tab Filtering Logic
        function filterSection(type) {
            const dutyPanel = document.getElementById('duty-panel');
            const unpaidPanel = document.getElementById('unpaid-panel');
            const techPanel = document.getElementById('tech-panel');
            const pricePanel = document.getElementById('price-panel');
            const tabs = document.querySelectorAll('.tab-btn');

            // Reset active tab style
            tabs.forEach(t => {
                t.classList.remove('active', 'text-white');
                t.classList.add('text-slate-400');
            });

            // Set clicked tab active
            const activeTab = document.getElementById('tab-' + type);
            if (activeTab) {
                activeTab.classList.add('active', 'text-white');
                activeTab.classList.remove('text-slate-400');
            }

            // Show/Hide Panels Simple Logic
            if (type === 'all') {
                dutyPanel.style.display = 'block';
                unpaidPanel.style.display = 'block';
                techPanel.style.display = 'grid';
                pricePanel.style.display = 'block';
            } else if (type === 'unpaid') {
                dutyPanel.style.display = 'none';
                unpaidPanel.style.display = 'block';
                techPanel.style.display = 'none';
                pricePanel.style.display = 'none';
            } else if (type === 'duty') {
                dutyPanel.style.display = 'block';
                unpaidPanel.style.display = 'none';
                techPanel.style.display = 'none';
                pricePanel.style.display = 'none';
            } else if (type === 'tech') {
                dutyPanel.style.display = 'none';
                unpaidPanel.style.display = 'none';
                techPanel.style.display = 'grid';
                pricePanel.style.display = 'none';
            } else if (type === 'price') {
                dutyPanel.style.display = 'none';
                unpaidPanel.style.display = 'none';
                techPanel.style.display = 'none';
                pricePanel.style.display = 'block';
            }
        }

        // Search Filter (Client-side simple)
        document.getElementById('searchInput').addEventListener('keyup', function (e) {
            const term = e.target.value.toLowerCase();
            // This is a basic implementation. For full search, we'd iterate all rows.
            // Implementing for table rows:
            document.querySelectorAll('tbody tr').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? 'table-row' : 'none';
            });
        });
    </script>
</body>

</html>