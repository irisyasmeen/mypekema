<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Persist active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ringkasan';

// LICENSEE RESTRICTIONS: Redirect strictly to vehicle list
$user_role = $_SESSION['user_role'] ?? 'user';

$is_licensee = ($user_role === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;
// Note: If licensee logged in without gbpekema_id, they will see 0 records (safe fallback)
$role_data_filter = $is_licensee ? " AND gbpekema_id = " . (int)$licensee_gb_id : "";
$role_data_filter_join = $is_licensee ? " AND v.gbpekema_id = " . (int)$licensee_gb_id : "";

// === DATA PREPARATION & YEAR SELECTION ===
$selected_year = isset($_GET['year']) ? $_GET['year'] : 'current';

// Fetch available years for the dropdown (Using both payment_date and created_at as fallback)
$available_years_sql = "SELECT DISTINCT YEAR(COALESCE(payment_date, created_at)) as year 
                        FROM vehicle_inventory 
                        WHERE (payment_date IS NOT NULL AND payment_date != '0000-00-00') 
                           OR created_at IS NOT NULL 
                        ORDER BY year DESC";
$years_result = $conn->query($available_years_sql) or die("Database Query Error (Years): " . $conn->error);
$available_years = [];
if ($years_result) {
    while ($yr = $years_result->fetch_assoc()) {
        if ($yr['year'])
            $available_years[] = (int) $yr['year'];
    }
}

// Fallback logic for year selection
if ($selected_year === 'current' || !isset($_GET['year'])) {
    $this_year = (int) date('Y');
    if (in_array($this_year, $available_years)) {
        $selected_year = $this_year;
    } elseif (!empty($available_years)) {
        $selected_year = $available_years[0];
    } else {
        $selected_year = $this_year;
    }
}

$current_year = $selected_year;
$year_filter = ($current_year === 'all') ? "1=1" : "YEAR(COALESCE(v.payment_date, v.created_at)) = " . (int) $current_year;

// 1. Summary Cards (Now Filtered by Year)
$sql_summary = "SELECT 
                    SUM(duty_rm) as t_cukai,
                    COUNT(id) as t_kenderaan
                FROM vehicle_inventory v
                WHERE $year_filter $role_data_filter_join";
$result_summary = $conn->query($sql_summary) or die("Database Query Error (Summary): " . $conn->error);
$summary_data = $result_summary->fetch_assoc();

$sql_gb_count = "SELECT COUNT(id) as total_gb FROM gbpekema";
$result_gb_count = $conn->query($sql_gb_count) or die("Database Query Error (GB Count): " . $conn->error);
$gb_count_data = $result_gb_count->fetch_assoc();

$total_cukai = (float) ($summary_data['t_cukai'] ?? 0);
$total_kenderaan = (int) ($summary_data['t_kenderaan'] ?? 0);
$total_gb = (int) ($gb_count_data['total_gb'] ?? 0);

// 2. Pie Chart Data (Company Dominance)
$sql_pie = "SELECT 
                COALESCE(g.nama, 'Tidak Diketahui') as nama, 
                COUNT(v.id) as vehicle_count 
            FROM vehicle_inventory v 
            LEFT JOIN gbpekema g ON v.gbpekema_id = g.id 
            WHERE $year_filter $role_data_filter_join
            GROUP BY nama ORDER BY vehicle_count DESC";
$result_pie = $conn->query($sql_pie) or die("Database Query Error (Pie Chart): " . $conn->error);
$pie_labels = [];
$pie_data = [];
if ($result_pie) {
    while ($row = $result_pie->fetch_assoc()) {
        $pie_labels[] = $row['nama'];
        $pie_data[] = (int) $row['vehicle_count'];
    }
}
$pie_chart_data = json_encode(['labels' => $pie_labels, 'data' => $pie_data]);

// 3. Recent Activities (Global awareness)
$sql_activity = "SELECT v.id, v.vehicle_model, g.nama as gb_nama, v.created_at 
                FROM vehicle_inventory v
                LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
                WHERE 1=1 $role_data_filter_join
                ORDER BY v.created_at DESC 
                LIMIT 5";
$result_activity = $conn->query($sql_activity) or die("Database Query Error (Recent Activities): " . $conn->error);
$recent_activities = ($result_activity) ? $result_activity->fetch_all(MYSQLI_ASSOC) : [];

// 3.5. Pending Applications (For Admin Dashboard)
$pending_applications = [];
$total_pending = 0;
if (!$is_licensee) {
    $sql_count_pending = "SELECT COUNT(id) as total FROM vehicle_inventory WHERE status_pergerakan = 'Pending'";
    $res_count_pending = $conn->query($sql_count_pending);
    if ($res_count_pending) {
        $total_pending = (int) $res_count_pending->fetch_assoc()['total'];
    }

    $sql_pending = "SELECT v.id, v.lot_number, v.vehicle_model, v.chassis_number, v.created_at, g.nama as gb_nama 
                    FROM vehicle_inventory v 
                    LEFT JOIN gbpekema g ON v.gbpekema_id = g.id 
                    WHERE status_pergerakan = 'Pending' 
                    ORDER BY v.created_at ASC LIMIT 5";
    $result_pending = $conn->query($sql_pending);
    if ($result_pending) {
        $pending_applications = $result_pending->fetch_all(MYSQLI_ASSOC);
    }
}

// 4. Fetch Quick Vehicle List for Licensee Dashboard
$licensee_vehicles = [];
if ($is_licensee) {
    $sql_lv = "SELECT v.id, v.lot_number, v.vehicle_model, v.chassis_number, v.color, v.created_at, v.import_date
               FROM vehicle_inventory v
               WHERE v.gbpekema_id = ?
               ORDER BY v.created_at DESC LIMIT 15";
    $stmt_lv = $conn->prepare($sql_lv);
    $stmt_lv->bind_param("i", $licensee_gb_id);
    $stmt_lv->execute();
    $licensee_vehicles = $stmt_lv->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_lv->close();
}

// 4. Tax Analysis (Breakdown with fallback to total duty_rm)
$sql_tax = "SELECT 
                SUM(duti_import) as t_import, 
                SUM(duti_eksais) as t_eksais, 
                SUM(cukai_jualan) as t_jualan,
                SUM(duty_rm) as t_total
            FROM vehicle_inventory v
            WHERE $year_filter $role_data_filter_join";
$result_tax = $conn->query($sql_tax) or die("Database Query Error (Tax Breakdown): " . $conn->error);
$tax_data = $result_tax->fetch_assoc();

$total_import = (float) ($tax_data['t_import'] ?? 0);
$total_eksais = (float) ($tax_data['t_eksais'] ?? 0);
$total_jualan = (float) ($tax_data['t_jualan'] ?? 0);
$total_recorded_duty = (float) ($tax_data['t_total'] ?? 0);

// Logic: If breakdown is missing but total exists, categorize the difference as 'Lain-lain'
$sum_breakdown = $total_import + $total_eksais + $total_jualan;
$other_tax = max(0, $total_recorded_duty - $sum_breakdown);
$total_annual_cukai = $total_recorded_duty;

$tax_distribution_labels = ['Duti Import', 'Duti Eksais', 'Cukai Jualan'];
$tax_distribution_values = [$total_import, $total_eksais, $total_jualan];

if ($other_tax > 1) {
    $tax_distribution_labels[] = 'Cukai Lain-lain';
    $tax_distribution_values[] = $other_tax;
}

$tax_distribution_data = json_encode([
    'labels' => $tax_distribution_labels,
    'data' => $tax_distribution_values
]);

// 5. Monthly Trend
$sql_line = "SELECT 
                MONTH(COALESCE(v.payment_date, v.created_at)) as month, 
                SUM(v.duty_rm) as monthly_total
            FROM vehicle_inventory v
            WHERE v.duty_rm > 0 AND $year_filter $role_data_filter_join
            GROUP BY MONTH(COALESCE(v.payment_date, v.created_at))
            ORDER BY month ASC";
$result_line = $conn->query($sql_line) or die("Database Query Error (Monthly Trend): " . $conn->error);

$monthly_data = array_fill(1, 12, 0);
if ($result_line && $result_line->num_rows > 0) {
    while ($row = $result_line->fetch_assoc()) {
        if ($row['month'])
            $monthly_data[(int) $row['month']] = (float) $row['monthly_total'];
    }
}

$tax_line_chart_data = json_encode([
    'labels' => ["Jan", "Feb", "Mac", "Apr", "Mei", "Jun", "Jul", "Ogo", "Sep", "Okt", "Nov", "Dis"],
    'data' => array_values($monthly_data)
]);

// AI FEATURES: Historical data for last 12 months (increased from 6)
$sql_historical = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        SUM(duty_rm) as total,
                        COUNT(id) as count
                    FROM vehicle_inventory v
                    WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    AND v.duty_rm > 0 $role_data_filter_join
                    GROUP BY DATE_FORMAT(v.created_at, '%Y-%m')
                    ORDER BY month ASC";
$result_historical = $conn->query($sql_historical) or die("Database Query Error (Historical Data): " . $conn->error);
$historical_data = [];
if ($result_historical && $result_historical->num_rows > 0) {
    while ($row = $result_historical->fetch_assoc()) {
        $historical_data[] = [
            'month' => $row['month'],
            'total' => floatval($row['total']),
            'count' => intval($row['count'])
        ];
    }
}

// AI: Company performance data (Top 10 by Total Tax)
$sql_company_performance = "SELECT 
                                g.nama,
                                COUNT(v.id) as vehicle_count,
                                SUM(v.duty_rm) as total_tax,
                                AVG(v.duty_rm) as avg_tax
                            FROM vehicle_inventory v
                            JOIN gbpekema g ON v.gbpekema_id = g.id
                            WHERE v.duty_rm > 0
                            GROUP BY g.nama
                            ORDER BY total_tax DESC
                            LIMIT 10";
$result_company_performance = $conn->query($sql_company_performance) or die("Database Query Error (Company Performance): " . $conn->error);
$company_performance = [];
if ($result_company_performance) {
    while ($row = $result_company_performance->fetch_assoc()) {
        $company_performance[] = [
            'name' => $row['nama'],
            'count' => intval($row['vehicle_count']),
            'total' => floatval($row['total_tax']),
            'avg' => floatval($row['avg_tax'])
        ];
    }
}

// AI: Get Top Company by Total (for insight card)
$top_company_by_total = $company_performance[0] ?? ['name' => 'N/A'];

// AI: Pareto Analysis (80/20)
$top_10_tax_sum = 0;
foreach ($company_performance as $company) {
    $top_10_tax_sum += $company['total'];
}
$pareto_percentage = 0;
if ($total_cukai > 0) {
    $pareto_percentage = ($top_10_tax_sum / $total_cukai) * 100;
}

// AI: Highest Average Tax Company
$sql_highest_avg = "SELECT g.nama, AVG(v.duty_rm) as avg_tax
                    FROM vehicle_inventory v
                    JOIN gbpekema g ON v.gbpekema_id = g.id
                    WHERE v.duty_rm > 0
                    GROUP BY g.nama
                    ORDER BY avg_tax DESC
                    LIMIT 1";
$result_highest_avg = $conn->query($sql_highest_avg);
$highest_avg_company = $result_highest_avg->fetch_assoc() ?? ['nama' => 'N/A', 'avg_tax' => 0];


// Get average for anomaly comparison
$avg_result = $conn->query("SELECT AVG(duty_rm) as avg_duty FROM vehicle_inventory WHERE duty_rm > 0");
$avg_duty = 1; // Default to 1 to prevent division by zero
if ($avg_result) {
    $avg_row = $avg_result->fetch_assoc();
    $avg_duty = $avg_row['avg_duty'] ?? 1;
}

// AI: Anomaly detection - High value vehicles (UPDATED to include gb_nama)
$sql_anomalies = "SELECT v.id, v.vehicle_model, v.duty_rm, v.payment_date, g.nama as gb_nama
                FROM vehicle_inventory v
                LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
                WHERE v.duty_rm > (SELECT AVG(duty_rm) * 2 FROM vehicle_inventory WHERE duty_rm > 0 $role_data_filter)
                AND v.duty_rm > 0 $role_data_filter_join
                ORDER BY v.duty_rm DESC
                LIMIT 5";
$result_anomalies = $conn->query($sql_anomalies);
$anomalies = [];
if ($result_anomalies) {
    while ($row = $result_anomalies->fetch_assoc()) {
        $row['avg_duty'] = $avg_duty > 0 ? $avg_duty : 1;
        $anomalies[] = $row;
    }
}

// AI: Anomaly detection - Low value vehicles
$sql_anomalies_low = "SELECT v.id, v.vehicle_model, v.duty_rm, v.payment_date, g.nama as gb_nama
                    FROM vehicle_inventory v
                    LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
                    WHERE v.duty_rm < (SELECT AVG(duty_rm) * 0.3 FROM vehicle_inventory WHERE duty_rm > 0 $role_data_filter)
                    AND v.duty_rm > 0 $role_data_filter_join
                    ORDER BY v.duty_rm ASC
                    LIMIT 5";
$result_anomalies_low = $conn->query($sql_anomalies_low);
$anomalies_low = [];
if ($result_anomalies_low) {
    while ($row = $result_anomalies_low->fetch_assoc()) {
        $row['avg_duty'] = $avg_duty > 0 ? $avg_duty : 1;
        $anomalies_low[] = $row;
    }
}

// --- NEW FEATURES ---

// AI: Average Processing Time (NEW)
$sql_processing_time = "SELECT AVG(DATEDIFF(payment_date, created_at)) as avg_processing_days 
                        FROM vehicle_inventory 
                        WHERE payment_date IS NOT NULL AND created_at IS NOT NULL AND payment_date > created_at $role_data_filter";
$result_processing_time = $conn->query($sql_processing_time);
$processing_time_data = $result_processing_time->fetch_assoc();
$avg_processing_days = $processing_time_data['avg_processing_days'] ?? 0;

// AI: Unpaid/Aging Stock (NEW)
$sql_aging_stock = "SELECT 
                        COUNT(id) as unpaid_count,
                        AVG(DATEDIFF(NOW(), created_at)) as avg_aging_days
                    FROM vehicle_inventory 
                    WHERE (payment_date IS NULL OR duty_rm = 0) $role_data_filter";
$result_aging_stock = $conn->query($sql_aging_stock);
$aging_stock_data = $result_aging_stock->fetch_assoc();
$unpaid_stock_count = $aging_stock_data['unpaid_count'] ?? 0;
$avg_aging_days = $aging_stock_data['avg_aging_days'] ?? 0;

// AI: Oldest Unpaid Stock List (NEW)
$sql_oldest_stock = "SELECT v.id, v.vehicle_model, v.created_at, g.nama as gb_nama, DATEDIFF(NOW(), v.created_at) as days_old
                    FROM vehicle_inventory v
                    LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
                    WHERE (v.payment_date IS NULL OR v.duty_rm = 0) $role_data_filter_join
                    ORDER BY v.created_at ASC
                    LIMIT 5";
$result_oldest_stock = $conn->query($sql_oldest_stock);
$oldest_stock = $result_oldest_stock->fetch_all(MYSQLI_ASSOC);

// AI: Top Vehicle Models by Tax (NEW)
$sql_top_vehicles = "SELECT 
                        vehicle_model, 
                        COUNT(id) as count, 
                        SUM(duty_rm) as total_tax, 
                        AVG(duty_rm) as avg_tax 
                    FROM vehicle_inventory 
                    WHERE duty_rm > 0 AND vehicle_model IS NOT NULL AND vehicle_model != '' $role_data_filter
                    GROUP BY vehicle_model 
                    ORDER BY total_tax DESC 
                    LIMIT 10";
$result_top_vehicles = $conn->query($sql_top_vehicles);
$top_vehicle_data = [];
if ($result_top_vehicles) {
    while ($row = $result_top_vehicles->fetch_assoc()) {
        $top_vehicle_data[] = [
            'model' => $row['vehicle_model'],
            'count' => intval($row['count']),
            'total' => floatval($row['total_tax']),
            'avg' => floatval($row['avg_tax'])
        ];
    }
}
$top_vehicle_chart_data = json_encode($top_vehicle_data);
// --- END OF NEW FEATURES ---

?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Enhanced Dashboard - MyPEKEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #a855f7;
            --accent: #f59e0b;
            --bg-glass: rgba(255, 255, 255, 0.7);
            --bg-glass-dark: rgba(15, 23, 42, 0.8);
            --border-glass: rgba(255, 255, 255, 0.2);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #eef2ff, #f8fafc);
            min-height: 100vh;
        }

        .glass-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-glass);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }

        .ai-gradient-text {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .ai-badge {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            animation: pulse-glow 2s infinite;
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            }

            50% {
                box-shadow: 0 4px 25px rgba(168, 85, 247, 0.6);
            }
        }

        .insight-card {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .insight-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            transform: translateX(-100%);
            transition: 0.6s;
        }

        .insight-card:hover::before {
            transform: translateX(100%);
        }

        .insight-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .tab-button {
            position: relative;
            transition: all 0.3s;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #a855f7);
            border-radius: 99px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                width: 0;
                opacity: 0;
            }

            to {
                width: 100%;
                opacity: 1;
            }
        }

        .no-data-message {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            color: #94a3b8;
            text-align: center;
            flex-direction: column;
            gap: 1.5rem;
        }

        .chart-container {
            position: relative;
            transition: all 0.3s;
        }

        .chart-container:hover {
            filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.05));
        }

        /* Floating AI Button */
        .ai-fab {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.5);
            cursor: pointer;
            z-index: 50;
            transition: all 0.3s;
        }

        .ai-fab:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.7);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .stat-card {
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-left-color: var(--primary);
            background: rgba(99, 102, 241, 0.02);
        }

        [x-cloak] {
            display: none !important;
        }

        @keyframes scan {
            0% {
                top: 0;
            }

            50% {
                top: 100%;
            }

            100% {
                top: 0;
            }
        }

        .scanner-line {
            position: absolute;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            box-shadow: 0 0 15px var(--accent);
            z-index: 10;
            opacity: 0.3;
            animation: scan 4s linear infinite;
            pointer-events: none;
        }

        .premium-glow {
            box-shadow: 0 0 40px -10px var(--primary);
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900 overflow-x-hidden">

    <?php include 'topmenu.php'; ?>

    <!-- Header Section -->
    <header class="bg-white/40 backdrop-blur-md border-b border-white/20 sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-600 rounded-lg text-white">
                    <i class="fas fa-warehouse text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-800">My<span
                            class="text-indigo-600">PEKEMA</span> Management Hub</h1>
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider">AI-Powered Intelligence</p>
                </div>
            </div>

            <div class="flex items-center gap-4 text-sm font-medium">
                <div
                    class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-green-50 text-green-700 rounded-full border border-green-100">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    Live Data
                </div>
                <div class="text-slate-400">|</div>
                <div class="text-slate-600">
                    <i class="far fa-calendar-alt mr-1"></i>
                    <?= date('d M Y') ?>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8" x-data="{ activeTab: '<?= $active_tab ?>' }" x-cloak>

        <!-- Welcome Area -->
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <h2 class="text-4xl font-extrabold text-slate-900 tracking-tight">
                    Selamat Datang, <span
                        class="ai-gradient-text"><?= htmlspecialchars($_SESSION['nama_pegawai'] ?? 'Pelesen') ?></span>
                </h2>
                <p class="text-slate-500 mt-2 max-w-2xl text-lg">
                    <?= $is_licensee ? "Berikut adalah senarai inventori kenderaan terkini untuk syarikat anda." : "Berikut adalah ringkasan prestasi gudang dan ramalan dipacu AI untuk mengoptimumkan operasi anda." ?>
                </p>
            </div>
            <?php if (!$is_licensee): ?>
            <div class="flex flex-col sm:flex-row items-center gap-4">
                <!-- Global Year Selector -->
                <form method="GET"
                    class="flex items-center gap-3 bg-white/60 backdrop-blur-sm p-2 rounded-2xl border border-slate-200 shadow-sm">
                    <input type="hidden" name="tab" :value="activeTab">
                    <label for="year_select"
                        class="pl-3 text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="far fa-calendar-check text-indigo-500"></i> Data Tahun:
                    </label>
                    <select name="year" id="year_select" onchange="this.form.submit()"
                        class="bg-white border border-slate-200 rounded-xl text-sm font-bold px-4 py-2 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer hover:border-indigo-300 transition-colors">
                        <option value="all" <?= $current_year == 'all' ? 'selected' : '' ?>>Semua Rekod</option>
                        <?php foreach ($available_years as $yr): ?>
                            <option value="<?= $yr ?>" <?= $yr == (string) $current_year ? 'selected' : '' ?>><?= $yr ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="flex gap-3">
                    <button onclick="exportDashboardData()"
                        class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl font-semibold text-slate-700 hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div
                        class="ai-badge text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center shadow-lg">
                        <i class="fas fa-robot mr-2"></i> INTELLIGENCE
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($is_licensee): ?>
        <!-- Licensee Quick Inventory View -->
        <div class="mb-10 animate-fade-in" x-show="activeTab === 'ringkasan'">
            <div class="glass-panel p-8 rounded-[2.5rem] shadow-2xl relative overflow-hidden bg-white/40">
                <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/5 rounded-full -mr-32 -mt-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-64 h-64 bg-blue-500/5 rounded-full -ml-32 -mb-32 blur-3xl"></div>
                
                <div class="relative z-10">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-lg">
                                    <i class="fas fa-warehouse"></i>
                                </div>
                                Inventori Terkini Syarikat
                            </h3>
                            <p class="text-slate-500 font-medium mt-1">Memaparkan 15 rekod kemasukan kenderaan terbaru.</p>
                        </div>
                        <a href="vehicles.php" 
                           class="group px-6 py-3 bg-indigo-600 text-white rounded-2xl font-bold flex items-center gap-3 hover:bg-indigo-700 hover:shadow-xl hover:shadow-indigo-200 transition-all active:scale-95">
                            Lihat Semua Stok
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>

                    <div class="overflow-x-auto rounded-3xl border border-slate-100 shadow-sm bg-white/50 backdrop-blur-md">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80">
                                    <th class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">No. Lot</th>
                                    <th class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Model Kenderaan</th>
                                    <th class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">No. Casis</th>
                                    <th class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Warna</th>
                                    <th class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 text-center">Tarikh</th>
                                    <th class="px-6 py-5 text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 text-right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/50">
                                <?php if (!empty($licensee_vehicles)): ?>
                                    <?php foreach ($licensee_vehicles as $v): ?>
                                        <tr class="hover:bg-indigo-50/30 transition-all group">
                                            <td class="px-6 py-5">
                                                <a href="vehicle_details.php?id=<?= $v['id'] ?>" class="hover:opacity-80 transition-opacity">
                                                    <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-black"><?= htmlspecialchars($v['lot_number']) ?></span>
                                                </a>
                                            </td>
                                            <td class="px-6 py-5">
                                                <div class="font-bold text-slate-800 group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($v['vehicle_model']) ?></div>
                                            </td>
                                            <td class="px-6 py-5">
                                                <div class="text-sm font-mono font-medium text-slate-500 uppercase"><?= htmlspecialchars($v['chassis_number']) ?></div>
                                            </td>
                                            <td class="px-6 py-5">
                                                <div class="text-sm font-semibold text-slate-600"><?= htmlspecialchars($v['color']) ?></div>
                                            </td>
                                            <td class="px-6 py-5 text-center">
                                                <div class="text-[11px] font-bold text-slate-400 uppercase"><?= date('d/m/Y', strtotime($v['created_at'])) ?></div>
                                            </td>
                                            <td class="px-6 py-5 text-right flex items-center justify-end gap-2" x-data="{ open: false }">
                                                <div class="relative">
                                                    <button @click="open = !open" @click.away="open = false"
                                                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 hover:shadow-lg transition-all text-[10px] font-black uppercase tracking-wider whitespace-nowrap">
                                                        <i class="fas fa-file-signature"></i>
                                                        Mohon
                                                        <i class="fas fa-chevron-down text-[8px] opacity-50 ml-1 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                                    </button>
                                                    
                                                    <!-- Dropdown Menu -->
                                                    <div x-show="open" 
                                                         x-transition:enter="transition ease-out duration-100"
                                                         x-transition:enter-start="opacity-0 scale-95"
                                                         x-transition:enter-end="opacity-100 scale-100"
                                                         x-transition:leave="transition ease-in duration-75"
                                                         x-transition:leave-start="opacity-100 scale-100"
                                                         x-transition:leave-end="opacity-0 scale-95"
                                                         class="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 p-2 overflow-hidden"
                                                         style="display: none;">
                                                        <div class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50 mb-1">Jenis Permohonan</div>
                                                        
                                                        <a href="borang_pergerakan.php?id=<?= $v['id'] ?>&type=pergerakan" 
                                                           class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 rounded-xl transition-colors">
                                                            <div class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                                                                <i class="fas fa-truck-fast"></i>
                                                            </div>
                                                            Pergerakan
                                                        </a>
                                                        
                                                        <a href="borang_pergerakan.php?id=<?= $v['id'] ?>&type=pameran" 
                                                           class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-700 rounded-xl transition-colors">
                                                            <div class="w-7 h-7 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-600">
                                                                <i class="fas fa-store"></i>
                                                            </div>
                                                            Pameran
                                                        </a>
                                                        
                                                        <a href="borang_lampiran_k.php?id=<?= $v['id'] ?>" 
                                                           class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-amber-50 hover:text-amber-700 rounded-xl transition-colors">
                                                            <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-600">
                                                                <i class="fas fa-boxes"></i>
                                                            </div>
                                                            Lampiran K
                                                        </a>

                                                        <div class="px-3 py-2 text-[9px] font-black text-slate-400 uppercase tracking-widest border-t border-b border-slate-50 my-1">Borang Lain-lain</div>

                                                        <a href="borang_lampiran_f.php?id=<?= $v['id'] ?>&gb_id=<?= $licensee_gb_id ?>" 
                                                           class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl transition-colors">
                                                            <div class="w-7 h-7 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-600">
                                                                <i class="fas fa-building"></i>
                                                            </div>
                                                            Lampiran F
                                                        </a>

                                                        <a href="borang_lampiran_g.php?id=<?= $v['id'] ?>&gb_id=<?= $licensee_gb_id ?>" 
                                                           class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-teal-50 hover:text-teal-700 rounded-xl transition-colors">
                                                            <div class="w-7 h-7 rounded-lg bg-teal-500/10 flex items-center justify-center text-teal-600">
                                                                <i class="fas fa-file-signature"></i>
                                                            </div>
                                                            Lampiran G
                                                        </a>

                                                        <a href="borang_lampiran_l.php?id=<?= $v['id'] ?>" 
                                                           class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-purple-50 hover:text-purple-700 rounded-xl transition-colors">
                                                            <div class="w-7 h-7 rounded-lg bg-purple-500/10 flex items-center justify-center text-purple-600">
                                                                <i class="fas fa-file-circle-xmark"></i>
                                                            </div>
                                                            Lampiran L
                                                        </a>
                                                    </div>
                                                </div>

                                                <a href="vehicle_details.php?id=<?= $v['id'] ?>" 
                                                   class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:shadow-lg transition-all"
                                                   title="Lihat Perincian">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-20 text-center text-slate-400 font-medium italic">Tiada rekod kenderaan untuk dipaparkan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$is_licensee): ?>
        <!-- Navigation Tabs -->
        <div class="mb-8 border-b border-slate-200">
            <nav class="-mb-px flex space-x-12 overflow-x-auto scrollbar-hide" aria-label="Tabs">
                <button @click="activeTab = 'ringkasan'"
                    :class="{ 'active text-indigo-600 font-bold': activeTab === 'ringkasan', 'text-slate-500 hover:text-slate-700': activeTab !== 'ringkasan' }"
                    class="tab-button whitespace-nowrap pb-4 px-1 text-base transition-all flex items-center gap-2">
                    <i class="fas fa-th-large"></i> Ringkasan Utama
                </button>
                <button @click="activeTab = 'cukai'"
                    :class="{ 'active text-indigo-600 font-bold': activeTab === 'cukai', 'text-slate-500 hover:text-slate-700': activeTab !== 'cukai' }"
                    class="tab-button whitespace-nowrap pb-4 px-1 text-base transition-all flex items-center gap-2">
                    <i class="fas fa-money-bill-wave"></i> Analisa Cukai
                </button>
                <?php if ($user_role !== 'licensee'): ?>
                <button @click="activeTab = 'ai'"
                    :class="{ 'active text-purple-600 font-bold': activeTab === 'ai', 'text-slate-500 hover:text-slate-700': activeTab !== 'ai' }"
                    class="tab-button whitespace-nowrap pb-4 px-1 text-base transition-all flex items-center gap-2">
                    <i class="fas fa-dna"></i> AI Deep Insights
                </button>
                <button @click="activeTab = 'more_ai'"
                    :class="{ 'active text-teal-600 font-bold': activeTab === 'more_ai', 'text-slate-500 hover:text-slate-700': activeTab !== 'more_ai' }"
                    class="tab-button whitespace-nowrap pb-4 px-1 text-base transition-all flex items-center gap-2">
                    <i class="fas fa-microchip"></i> Advanced Analytics
                </button>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>

        <?php if (!$is_licensee): ?>
        <!-- Content Area -->
        <div class="min-h-[600px]">

            <!-- Tab: Ringkasan Utama -->
            <div x-show="activeTab === 'ringkasan'" x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                    <div class="glass-panel stat-card p-6 rounded-3xl flex justify-between items-center group">
                        <div>
                            <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">Jumlah
                                Kenderaan</p>
                            <p class="text-5xl font-black text-slate-900"><?= number_format($total_kenderaan) ?></p>
                            <div class="mt-2 text-xs font-bold text-green-600 flex items-center">
                                <i class="fas fa-arrow-up mr-1"></i> +4.2% <span
                                    class="text-slate-400 font-normal ml-1">vs bulan lepas</span>
                            </div>
                        </div>
                        <div
                            class="bg-indigo-50 p-4 rounded-2xl text-indigo-600 group-hover:scale-110 transition-transform">
                            <i class="fas fa-car-side text-3xl"></i>
                        </div>
                    </div>

                    <?php if ($user_role !== 'licensee'): ?>
                    <div class="glass-panel stat-card p-6 rounded-3xl flex justify-between items-center group">
                        <div>
                            <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">Syarikat Berdaftar</p>
                            <p class="text-5xl font-black text-slate-900"><?= number_format($total_gb) ?></p>
                            <div class="mt-2 text-xs font-bold text-slate-500 flex items-center">
                                <i class="fas fa-building mr-1"></i> Utama
                            </div>
                        </div>
                        <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all duration-500">
                            <i class="fas fa-city text-3xl"></i>
                        </div>
                    </div>
                    <?php endif; ?>

                    <a href="analisis_cukai.php"
                        class="glass-panel stat-card p-6 rounded-3xl bg-indigo-900/5 flex justify-between items-center group cursor-pointer hover:shadow-md transition-all">
                        <div>
                            <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider mb-1">Cukai
                                Terkumpul</p>
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-bold text-indigo-600">RM</span>
                                <p class="text-4xl font-black text-indigo-900">
                                    <?= number_format($total_cukai / 1000000, 1) ?>M
                                </p>
                            </div>
                            <div class="mt-2 text-xs font-bold text-indigo-400 truncate max-w-[150px]"
                                title="RM <?= number_format($total_cukai, 2) ?>">
                                Tepat: RM <?= number_format($total_cukai, 2) ?>
                            </div>
                        </div>
                        <div
                            class="bg-indigo-600 p-4 rounded-2xl text-white shadow-lg group-hover:scale-110 transition-transform">
                            <i class="fas fa-coins text-3xl"></i>
                        </div>
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <?php if ($user_role !== 'licensee'): ?>
                    <div class="lg:col-span-2 glass-panel p-8 rounded-3xl shadow-sm">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="text-xl font-bold text-slate-800">Dominasi Syarikat</h3>
                                <p class="text-sm text-slate-500">Pecahan kenderaan mengikut syarikat</p>
                            </div>
                            <button class="text-slate-400 hover:text-slate-600"><i
                                    class="fas fa-ellipsis-v"></i></button>
                        </div>
                        <div class="chart-container h-[420px]">
                            <canvas id="companyPieChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="glass-panel p-8 rounded-3xl shadow-sm">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-bold text-slate-800">Log Aktiviti</h3>
                            <span
                                class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full font-bold">LIVE</span>
                        </div>
                        <div class="space-y-6">
                            <?php if (count($recent_activities) > 0): ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <a href="vehicle_details.php?id=<?= $activity['id'] ?>"
                                        class="flex items-center gap-4 group cursor-pointer hover:bg-slate-50/50 p-2 -m-2 rounded-xl transition-all">
                                        <div
                                            class="w-12 h-12 bg-white border border-slate-100 rounded-xl flex items-center justify-center text-indigo-500 shadow-sm group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                            <i class="fas fa-plus text-xs"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-slate-800 text-sm truncate">
                                                <?= htmlspecialchars($activity['vehicle_model']) ?>
                                            </p>
                                            <p class="text-xs text-slate-500 truncate">
                                                <?= htmlspecialchars($activity['gb_nama'] ?? 'N/A') ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[10px] uppercase font-bold text-slate-400">
                                                <?= date('h:i A', strtotime($activity['created_at'])) ?>
                                            </p>
                                            <p class="text-[10px] text-slate-300">
                                                <?= date('d M', strtotime($activity['created_at'])) ?>
                                            </p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                                <div class="pt-4 mt-4 border-t border-slate-100">
                                    <a href="#"
                                        class="text-sm font-bold text-indigo-600 hover:text-indigo-800 flex items-center justify-center gap-2 group">
                                        Lihat Semua Aktiviti <i
                                            class="fas fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="no-data-message py-12">
                                    <div
                                        class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300">
                                        <i class="fas fa-inbox text-2xl"></i>
                                    </div>
                                    <p class="text-slate-400 font-medium">Tiada aktiviti terkini</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!$is_licensee): ?>
                <!-- Pending Applications Section -->
                <div class="mt-8 glass-panel p-8 rounded-3xl shadow-sm border-t-4 border-amber-500">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500 shadow-sm">
                                    <i class="fas fa-file-signature"></i>
                                </div>
                                Permohonan Menunggu Kelulusan
                            </h3>
                            <p class="text-sm text-slate-500 mt-2">Terdapat <span class="font-bold text-amber-600"><?= number_format($total_pending) ?></span> permohonan yang memerlukan semakan dan kelulusan.</p>
                        </div>
                        <a href="permohonan.php?status=Pending" class="group px-5 py-2.5 bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white rounded-xl font-bold text-sm transition-all shadow-sm flex items-center gap-2 whitespace-nowrap">
                            Urus Permohonan
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white/50">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/80 border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest">Maklumat Kenderaan</th>
                                    <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest">Syarikat / Gudang</th>
                                    <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest">Tarikh Permohonan</th>
                                    <th class="px-6 py-4 text-[11px] font-black text-slate-400 uppercase tracking-widest text-right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (count($pending_applications) > 0): ?>
                                    <?php foreach ($pending_applications as $app): ?>
                                        <tr class="hover:bg-amber-50/30 transition-colors group">
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-slate-800 group-hover:text-amber-600 transition-colors"><?= htmlspecialchars($app['vehicle_model']) ?></div>
                                                <div class="text-xs text-slate-500 font-mono mt-1 flex items-center gap-2">
                                                    <i class="fas fa-barcode text-slate-300"></i>
                                                    <?= htmlspecialchars($app['chassis_number']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($app['gb_nama'] ?: 'N/A') ?></div>
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                    <span class="text-[10px] font-black text-blue-600/70 uppercase tracking-widest"><?= htmlspecialchars($app['lot_number']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-semibold text-slate-600 flex items-center gap-2">
                                                    <i class="far fa-calendar text-slate-400"></i>
                                                    <?= date('d M Y', strtotime($app['created_at'])) ?>
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-bold ml-6 mt-0.5"><?= date('h:i A', strtotime($app['created_at'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="borang_pergerakan.php?id=<?= $app['id'] ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-amber-500 hover:bg-amber-500 hover:text-white hover:border-amber-500 transition-all shadow-sm" title="Semak Permohonan">
                                                    <i class="fas fa-file-signature"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 mb-3">
                                                    <i class="fas fa-check-double text-2xl"></i>
                                                </div>
                                                <p class="text-slate-500 font-bold text-sm">Tiada permohonan tertunggak.</p>
                                                <p class="text-slate-400 text-xs mt-1">Semua permohonan telah disemak.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Analisa Cukai -->
            <div x-show="activeTab === 'cukai'" x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="glass-panel p-6 rounded-3xl border-l-[6px] border-indigo-500">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Duti Import</span>
                            <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg"><i class="fas fa-ship"></i></div>
                        </div>
                        <p class="text-3xl font-black text-slate-900">RM <?= number_format($total_import, 2) ?></p>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-4 overflow-hidden">
                            <div class="bg-indigo-500 h-full rounded-full"
                                style="width: <?= ($total_annual_cukai > 0 ? ($total_import / $total_annual_cukai) * 100 : 0) ?>%">
                            </div>
                        </div>
                    </div>

                    <div class="glass-panel p-6 rounded-3xl border-l-[6px] border-emerald-500">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Duti Eksais</span>
                            <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg"><i class="fas fa-gas-pump"></i>
                            </div>
                        </div>
                        <p class="text-3xl font-black text-slate-900">RM <?= number_format($total_eksais, 2) ?></p>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-4 overflow-hidden">
                            <div class="bg-emerald-500 h-full rounded-full"
                                style="width: <?= ($total_annual_cukai > 0 ? ($total_eksais / $total_annual_cukai) * 100 : 0) ?>%">
                            </div>
                        </div>
                    </div>

                    <div class="glass-panel p-6 rounded-3xl border-l-[6px] border-amber-500">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Cukai Jualan</span>
                            <div class="p-2 bg-amber-50 text-amber-600 rounded-lg"><i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                        <p class="text-3xl font-black text-slate-900">RM <?= number_format($total_jualan, 2) ?></p>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-4 overflow-hidden">
                            <div class="bg-amber-500 h-full rounded-full"
                                style="width: <?= ($total_annual_cukai > 0 ? ($total_jualan / $total_annual_cukai) * 100 : 0) ?>%">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-8 rounded-3xl shadow-sm">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
                        <div>
                            <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                                <i class="fas fa-chart-line text-indigo-500"></i> Trend Kutipan Tahunan
                            </h3>
                            <p class="text-sm text-slate-500">Analisis prestasi vs ramalan bulanan
                                (<?= $current_year ?>)</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-6">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                                    <span class="w-3 h-3 rounded-full bg-indigo-500 shadow-sm shadow-indigo-200"></span>
                                    Sebenar
                                </div>
                                <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                                    <span class="w-3 h-3 rounded-full border-2 border-dashed border-purple-500"></span>
                                    Ramalan AI
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container h-[450px]">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                    <div class="lg:col-span-3 glass-panel p-8 rounded-3xl shadow-sm">
                        <h3 class="text-xl font-bold text-slate-800 mb-6">Perbandingan Kategori Cukai</h3>
                        <div class="chart-container h-[350px]">
                            <canvas id="taxBarChart"></canvas>
                        </div>
                    </div>
                    <div class="lg:col-span-2 glass-panel p-8 rounded-3xl shadow-sm">
                        <h3 class="text-xl font-bold text-slate-800 mb-6">Pecahan Pasaran</h3>
                        <div class="chart-container h-[350px]">
                            <canvas id="taxPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: AI Insights -->
            <?php if ($user_role !== 'licensee'): ?>
            <div x-show="activeTab === 'ai'" x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                class="space-y-8 relative overflow-hidden">
                <div class="scanner-line"></div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        class="insight-card text-white p-7 rounded-3xl shadow-xl flex flex-col justify-between min-h-[180px]">
                        <div class="flex items-center justify-between">
                            <div class="p-2.5 bg-white/10 rounded-xl"><i class="fas fa-bolt text-indigo-400"></i></div>
                            <span
                                class="text-[10px] font-black bg-indigo-500 px-2 py-0.5 rounded text-white uppercase tracking-widest">Prediction</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase mb-1">Ramalan Bulan Depan</p>
                            <p class="text-3xl font-black" id="aiPrediction">...</p>
                        </div>
                    </div>

                    <div
                        class="insight-card text-white p-7 rounded-3xl shadow-xl flex flex-col justify-between min-h-[180px]">
                        <div class="flex items-center justify-between">
                            <div class="p-2.5 bg-white/10 rounded-xl"><i class="fas fa-chart-line text-emerald-400"></i>
                            </div>
                            <span
                                class="text-[10px] font-black bg-emerald-500 px-2 py-0.5 rounded text-white uppercase tracking-widest">Growth</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase mb-1">Kadar Pertumbuhan</p>
                            <p class="text-3xl font-black" id="growthRate">...</p>
                        </div>
                    </div>

                    <div
                        class="insight-card text-white p-7 rounded-3xl shadow-xl flex flex-col justify-between min-h-[180px]">
                        <div class="flex items-center justify-between">
                            <div class="p-2.5 bg-white/10 rounded-xl"><i class="fas fa-crown text-amber-400"></i></div>
                            <span
                                class="text-[10px] font-black bg-amber-500 px-2 py-0.5 rounded text-white uppercase tracking-widest">Market
                                Top</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase mb-1">Peneraju Pasaran</p>
                            <p class="text-xl font-black truncate leading-tight mt-1"
                                title="<?= htmlspecialchars($top_company_by_total['name']) ?>">
                                <?= htmlspecialchars($top_company_by_total['name']) ?>
                            </p>
                        </div>
                    </div>

                    <div
                        class="insight-card text-white p-7 rounded-3xl shadow-xl flex flex-col justify-between min-h-[180px]">
                        <div class="flex items-center justify-between">
                            <div class="p-2.5 bg-white/10 rounded-xl"><i class="fas fa-chart-line text-green-400"></i>
                            </div>
                            <span
                                class="text-[10px] font-black bg-green-500 px-2 py-0.5 rounded text-white uppercase tracking-widest">Growth</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase mb-1">Momentum Bulanan</p>
                            <div id="growthRate" class="text-3xl font-black">...</div>
                        </div>
                    </div>

                    <div @click="$refs.highAnomalyList && $refs.highAnomalyList.scrollIntoView({behavior: 'smooth'})"
                        class="insight-card cursor-pointer text-white p-7 rounded-3xl shadow-xl flex flex-col justify-between min-h-[180px]">
                        <div class="flex items-center justify-between">
                            <div class="p-2.5 bg-white/10 rounded-xl"><i class="fas fa-shield-virus text-rose-400"></i>
                            </div>
                            <span
                                class="text-[10px] font-black bg-rose-500 px-2 py-0.5 rounded text-white uppercase tracking-widest">Alert</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase mb-1">Anomali Dikesan</p>
                            <p class="text-4xl font-black"><?= count($anomalies) ?></p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="glass-panel p-8 rounded-3xl shadow-xl border-t-4 border-indigo-600">
                        <div class="flex items-center gap-3 mb-8">
                            <div
                                class="w-10 h-10 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-600">
                                <i class="fas fa-magic"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800">Advanced Forecast Analysis</h3>
                        </div>
                        <div class="chart-container h-[420px]">
                            <canvas id="predictionChart"></canvas>
                        </div>
                    </div>

                    <div class="glass-panel p-8 rounded-3xl shadow-xl border-t-4 border-blue-600">
                        <div class="flex items-center gap-3 mb-8">
                            <div
                                class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-600">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800">Top Performing Entities</h3>
                        </div>
                        <div class="chart-container h-[420px]">
                            <canvas id="companyPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>

                <?php if (count($anomalies) > 0): ?>
                    <div x-ref="highAnomalyList"
                        class="glass-panel p-8 rounded-3xl shadow-md border-l-8 border-rose-500 overflow-hidden relative">
                        <div class="absolute -right-10 -top-10 text-rose-500/5 rotate-12">
                            <i class="fas fa-exclamation-triangle text-9xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-8 flex items-center gap-3">
                            <span class="flex h-3 w-3 relative">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                            </span>
                            Anomali Nilai Cukai Melampau
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[400px] overflow-y-auto pr-2">
                            <?php foreach ($anomalies as $anomaly): ?>
                                <a href="vehicle_details.php?id=<?= $anomaly['id'] ?>"
                                    class="group flex items-center justify-between p-5 bg-rose-50/50 hover:bg-rose-50 transition-colors border border-rose-100 rounded-2xl">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-slate-800 truncate leading-snug">
                                            <?= htmlspecialchars($anomaly['vehicle_model']) ?>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            <i class="fas fa-building mr-1"></i>
                                            <?= htmlspecialchars($anomaly['gb_nama'] ?? 'N/A') ?>
                                            <span class="mx-2 font-normal text-slate-300">|</span>
                                            <i class="far fa-calendar mr-1"></i>
                                            <?= (!empty($anomaly['payment_date']) && $anomaly['payment_date'] != '0000-00-00') ? date('d M Y', strtotime($anomaly['payment_date'])) : 'N/A' ?>
                                        </p>
                                    </div>
                                    <div class="text-right ml-6">
                                        <p class="text-xl font-black text-rose-600 leading-none">RM
                                            <?= number_format($anomaly['duty_rm'], 0) ?>
                                        </p>
                                        <p class="text-[10px] font-black text-rose-400 mt-1 uppercase tracking-wider">
                                            <i class="fas fa-arrow-up mr-0.5"></i>
                                            <?= number_format(($anomaly['duty_rm'] / ($anomaly['avg_duty'] ?: 1)) * 100, 0) ?>%
                                            Avg
                                        </p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            </div>

            <!-- Tab: More AI Deep Insights -->
            <?php if ($user_role !== 'licensee'): ?>
            <div x-show="activeTab === 'more_ai'" x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                class="space-y-8">
                <!-- Advanced Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div
                        class="glass-panel p-8 rounded-3xl shadow-sm hover:shadow-lg transition-all border-b-4 border-indigo-500">
                        <div class="flex items-center gap-4 mb-4">
                            <div
                                class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 shadow-sm">
                                <i class="fas fa-medal text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-400 uppercase tracking-widest">Purata Cukai
                                    Tertinggi</h4>
                                <p class="text-lg font-black text-slate-800 truncate leading-snug mt-0.5"
                                    title="<?= htmlspecialchars($highest_avg_company['nama']) ?>">
                                    <?= htmlspecialchars($highest_avg_company['nama']) ?>
                                </p>
                            </div>
                        </div>
                        <p class="text-2xl font-black text-indigo-600">RM
                            <?= number_format($highest_avg_company['avg_tax'], 0) ?> <span
                                class="text-xs font-bold text-slate-400">/ Unit</span>
                        </p>
                    </div>

                    <div
                        class="glass-panel p-8 rounded-3xl shadow-sm hover:shadow-lg transition-all border-b-4 border-teal-500">
                        <div class="flex items-center gap-4 mb-4">
                            <div
                                class="w-12 h-12 bg-teal-50 rounded-2xl flex items-center justify-center text-teal-600 shadow-sm">
                                <i class="fas fa-bullseye text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-400 uppercase tracking-widest">Fokus Pareto Top
                                    10</h4>
                                <p class="text-lg font-black text-slate-800 leading-snug mt-0.5">Strategi 80/20 Pasaran
                                </p>
                            </div>
                        </div>
                        <p class="text-4xl font-black text-teal-600"><?= number_format($pareto_percentage, 1) ?>% <span
                                class="text-xs font-bold text-slate-400">Total Cukai</span></p>
                    </div>

                    <div
                        class="glass-panel p-8 rounded-3xl shadow-sm hover:shadow-lg transition-all border-b-4 border-blue-500">
                        <div class="flex items-center gap-4 mb-4">
                            <div
                                class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 shadow-sm">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-400 uppercase tracking-widest">Kecekapan Proses
                                </h4>
                                <p class="text-lg font-black text-slate-800 leading-snug mt-0.5">Purata Hari Pemprosesan
                                </p>
                            </div>
                        </div>
                        <p class="text-4xl font-black text-blue-600"><?= number_format($avg_processing_days, 1) ?> <span
                                class="text-xs font-bold text-slate-400 uppercase">Hari</span></p>
                    </div>

                    <div @click="$refs.lowAnomalyList && $refs.lowAnomalyList.scrollIntoView({behavior: 'smooth'})"
                        class="glass-panel p-6 rounded-3xl shadow-sm cursor-pointer hover:bg-slate-50 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center text-rose-500">
                                <i class="fas fa-search-minus"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase">Anomali Rendah</p>
                                <p class="text-xl font-black text-rose-600"><?= count($anomalies_low) ?> <span
                                        class="text-xs font-bold">Keciciran</span></p>
                            </div>
                            <i class="fas fa-chevron-right text-slate-300"></i>
                        </div>
                    </div>

                    <div @click="$refs.oldestStockList && $refs.oldestStockList.scrollIntoView({behavior: 'smooth'})"
                        class="glass-panel p-6 rounded-3xl shadow-sm cursor-pointer hover:bg-slate-50 transition-colors">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-500">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase">Stok Belum Selesai</p>
                                <p class="text-xl font-black text-indigo-600"><?= number_format($unpaid_stock_count) ?>
                                    <span class="text-xs font-bold">Unit</span>
                                </p>
                            </div>
                            <i class="fas fa-chevron-right text-slate-300"></i>
                        </div>
                    </div>

                    <div @click="$refs.oldestStockList && $refs.oldestStockList.scrollIntoView({behavior: 'smooth'})"
                        class="glass-panel p-6 rounded-3xl shadow-sm cursor-pointer hover:bg-slate-50 transition-colors">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase">Purata Umur Stok</p>
                                <p class="text-xl font-black text-amber-600"><?= number_format($avg_aging_days, 1) ?>
                                    <span class="text-xs font-bold">Hari</span>
                                </p>
                            </div>
                            <i class="fas fa-chevron-right text-slate-300"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="glass-panel p-8 rounded-3xl shadow-lg border-t-8 border-emerald-500">
                        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                            <i class="fas fa-car-side text-emerald-500"></i> Dominasi Model Kenderaan
                        </h3>
                        <div class="chart-container h-[420px]">
                            <canvas id="topVehicleChart"></canvas>
                        </div>
                    </div>

                    <div class="glass-panel p-8 rounded-3xl shadow-lg border-t-8 border-purple-500">
                        <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                            <i class="fas fa-project-diagram text-purple-500"></i> Korelasi Volume vs Cukai
                        </h3>
                        <div class="chart-container h-[420px]">
                            <canvas id="correlationChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <?php if (count($oldest_stock) > 0): ?>
                        <div x-ref="oldestStockList"
                            class="glass-panel p-8 rounded-3xl shadow-md border-l-8 border-indigo-400">
                            <h3 class="text-xl font-bold text-slate-800 mb-8 flex items-center gap-3">
                                <i class="fas fa-hourglass-half text-indigo-400"></i> Penuaan Stok (Kritikal)
                            </h3>
                            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2">
                                <?php foreach ($oldest_stock as $stock): ?>
                                    <a href="vehicle_details.php?id=<?= $stock['id'] ?>"
                                        class="flex items-center justify-between p-5 bg-indigo-50/30 hover:bg-indigo-50 border border-indigo-50 rounded-2xl transition-colors">
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-slate-800 truncate">
                                                <?= htmlspecialchars($stock['vehicle_model']) ?>
                                            </p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                <?= htmlspecialchars($stock['gb_nama'] ?? 'N/A') ?>
                                                <span class="mx-2 text-slate-200">|</span>
                                                Mula:
                                                <?= (!empty($stock['created_at']) && $stock['created_at'] != '0000-00-00 00:00:00') ? date('d M Y', strtotime($stock['created_at'])) : 'N/A' ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-black text-indigo-600 leading-none">
                                                <?= number_format($stock['days_old']) ?>
                                            </p>
                                            <p class="text-[10px] font-black text-indigo-300 mt-1 uppercase">Hari Unpaid</p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (count($anomalies_low) > 0): ?>
                        <div x-ref="lowAnomalyList"
                            class="glass-panel p-8 rounded-3xl shadow-md border-l-8 border-amber-400">
                            <h3 class="text-xl font-bold text-slate-800 mb-8 flex items-center gap-3 text-amber-600">
                                <i class="fas fa-search-dollar"></i> Risiko Keciciran Nilai Cukai
                            </h3>
                            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2">
                                <?php foreach ($anomalies_low as $anomaly): ?>
                                    <a href="vehicle_details.php?id=<?= $anomaly['id'] ?>"
                                        class="flex items-center justify-between p-5 bg-amber-50/50 hover:bg-amber-100 border border-amber-100 rounded-2xl transition-colors">
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-slate-800 truncate">
                                                <?= htmlspecialchars($anomaly['vehicle_model']) ?>
                                            </p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                <?= htmlspecialchars($anomaly['gb_nama'] ?? 'N/A') ?>
                                                <span class="mx-2 text-slate-200">|</span>
                                                Lapor:
                                                <?= (!empty($anomaly['payment_date']) && $anomaly['payment_date'] != '0000-00-00') ? date('d M Y', strtotime($anomaly['payment_date'])) : 'N/A' ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-black text-amber-600 leading-none">RM
                                                <?= number_format($anomaly['duty_rm'], 0) ?>
                                            </p>
                                            <p class="text-[10px] font-black text-amber-400 mt-1 uppercase">Mencurigakan Rendah
                                            </p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </main>

    <?php if (!$is_licensee): ?>
    <!-- Floating AI Assistant Button UI -->
    <div class="ai-fab group" title="Tanya AI Assistant">
        <i class="fas fa-comment-dots text-2xl group-hover:hidden"></i>
        <i class="fas fa-robot text-2xl hidden group-hover:block"></i>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>

    <script>
        // Set Global Chart Defaults
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#64748b";
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.borderRadius = 12;
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
        Chart.defaults.plugins.tooltip.titleFont = { size: 14, weight: 'bold' };

        // Data from PHP
        const pieChartData = <?= $pie_chart_data ?>;
        const taxDistributionData = <?= $tax_distribution_data ?>;
        const taxLineData = <?= $tax_line_chart_data ?>;
        const historicalData = <?= json_encode($historical_data) ?>;
        const companyPerformance = <?= json_encode($company_performance) ?>;
        const anomaliesData = <?= json_encode($anomalies) ?>;
        const avgDuty = <?= $avg_duty ?>;
        const topVehicleData = <?= $top_vehicle_chart_data ?>;

        // Colors Palette
        const colors = {
            indigo: '#6366f1',
            purple: '#a855f7',
            emerald: '#10b981',
            amber: '#f59e0b',
            rose: '#f43f5e',
            blue: '#3b82f6',
            slate: '#64748b'
        };

        // Linear regression function
        function linearRegression(data) {
            const n = data.length;
            if (n < 2) return { slope: 0, intercept: data[0] || 0 };
            let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;
            data.forEach((val, i) => { sumX += i; sumY += val; sumXY += i * val; sumX2 += i * i; });
            const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
            const intercept = (sumY - slope * sumX) / n;
            return { slope, intercept };
        }

        function calculateGrowth(data) {
            if (!Array.isArray(data) || data.length < 2) return 0;
            const recent = data[data.length - 1];
            const previous = data[data.length - 2];
            if (typeof previous !== 'number' || previous === 0) return 0;
            return (((recent - previous) / previous) * 100).toFixed(1);
        }

        document.addEventListener('DOMContentLoaded', function () {

            function getChartContext(canvasId) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return { context: null, parent: null };
                return { context: canvas.getContext('2d'), parent: canvas.parentElement };
            }

            function displayNoDataMessage(parent, iconClass, message) {
                if (parent) {
                    parent.innerHTML = `<div class="no-data-message"><div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 mb-2"><i class="${iconClass} text-2xl"></i></div><p class="font-medium text-slate-400">${message}</p></div>`;
                }
            }

            // --- 1. Company Pie Chart ---
            const { context: pieCtx, parent: pieParent } = getChartContext('companyPieChart');
            if (pieCtx && pieChartData && pieChartData.data && pieChartData.data.length > 0) {
                new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: pieChartData.labels,
                        datasets: [{
                            data: pieChartData.data,
                            backgroundColor: [colors.indigo, colors.purple, colors.emerald, colors.amber, colors.rose, colors.blue, '#94a3b8', '#475569', colors.slate, '#e2e8f0'],
                            hoverOffset: 15, borderWidth: 4, borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '70%',
                        plugins: {
                            legend: { position: 'bottom', labels: { padding: 25, usePointStyle: true, font: { size: 12, weight: '500' } } },
                            tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.parsed} unit (${(ctx.parsed / ctx.dataset.data.reduce((a, b) => a + b, 0) * 100).toFixed(1)}%)` } }
                        }
                    }
                });
            } else {
                displayNoDataMessage(pieParent, 'fas fa-chart-pie', 'Tiada data kenderaan.');
            }

            // --- 2. Tax Bar Chart ---
            const { context: barCtx, parent: barParent } = getChartContext('taxBarChart');
            if (barCtx && taxDistributionData && taxDistributionData.data?.reduce((a, b) => a + b, 0) > 0) {
                new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: taxDistributionData.labels,
                        datasets: [{
                            label: 'Jumlah (RM)',
                            data: taxDistributionData.data,
                            backgroundColor: [colors.indigo + 'cc', colors.emerald + 'cc', colors.amber + 'cc'],
                            borderColor: [colors.indigo, colors.emerald, colors.amber],
                            borderWidth: 1, borderRadius: 12, barThickness: 50
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true, grid: { drawBorder: false, color: '#f1f5f9' }, ticks: { callback: value => 'RM ' + value.toLocaleString() } },
                            x: { grid: { display: false } }
                        },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => ` RM ${ctx.parsed.y.toLocaleString()}` } } }
                    }
                });
            } else {
                displayNoDataMessage(barParent, 'fas fa-chart-bar', 'Tiada data cukai.');
            }

            // --- 3. Tax Pie Chart ---
            const { context: taxPieCtx, parent: taxPieParent } = getChartContext('taxPieChart');
            if (taxPieCtx && taxDistributionData && taxDistributionData.data?.reduce((a, b) => a + b, 0) > 0) {
                new Chart(taxPieCtx, {
                    type: 'pie',
                    data: {
                        labels: taxDistributionData.labels,
                        datasets: [{
                            data: taxDistributionData.data,
                            backgroundColor: [colors.indigo, colors.emerald, colors.amber],
                            hoverOffset: 10, borderWidth: 4, borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } },
                            tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: RM ${ctx.parsed.toLocaleString()} (${(ctx.parsed / ctx.dataset.data.reduce((a, b) => a + b, 0) * 100).toFixed(1)}%)` } }
                        }
                    }
                });
            } else {
                displayNoDataMessage(taxPieParent, 'fas fa-chart-pie', 'Tiada data cukai.');
            }

            // --- 4. Monthly Trend Chart ---
            const { context: lineCtx, parent: lineParent } = getChartContext('monthlyTrendChart');
            const monthlyData = taxLineData.data;
            let predictions = [];
            const futureMonths = 3;
            const validMonthlyData = monthlyData.filter(d => typeof d === 'number' && d > 0);

            if (validMonthlyData.length >= 2) {
                const regression = linearRegression(validMonthlyData);
                for (let i = 1; i <= futureMonths; i++) {
                    predictions.push(Math.max(0, regression.slope * (validMonthlyData.length - 1 + i) + regression.intercept));
                }
            } else {
                predictions = Array(futureMonths).fill(validMonthlyData[0] || 0);
            }

            if (lineCtx) {
                new Chart(lineCtx, {
                    type: 'line',
                    data: {
                        labels: [...taxLineData.labels, ...Array(futureMonths).fill(null).map((_, i) => `Ramalan ${i + 1}`)],
                        datasets: [{
                            label: 'Kutipan Sebenar',
                            data: [...monthlyData, ...Array(futureMonths).fill(null)],
                            fill: true, backgroundColor: 'rgba(99, 102, 241, 0.08)', borderColor: colors.indigo, tension: 0.4, borderWidth: 3, pointRadius: 5, pointBackgroundColor: '#fff', pointBorderWidth: 2
                        }, {
                            label: 'Projeksi AI',
                            data: [...Array(monthlyData.length - 1).fill(null), monthlyData[monthlyData.length - 1], ...predictions],
                            fill: false, borderColor: colors.purple, borderDash: [6, 4], tension: 0.4, borderWidth: 3, pointRadius: 5, pointBackgroundColor: '#fff', pointStyle: 'rectRounded'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: value => 'RM ' + value.toLocaleString() } },
                            x: { grid: { display: false } }
                        },
                        plugins: { tooltip: { mode: 'index', intersect: false } }
                    }
                });
            } else {
                displayNoDataMessage(lineParent, 'fas fa-chart-line', 'Tiada data trend bulanan.');
            }

            // AI Insights Text Updates
            const nextMonthPred = predictions[0] ?? 0;
            const predEl = document.getElementById('aiPrediction');
            if (predEl) predEl.textContent = 'RM ' + nextMonthPred.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

            const growthValue = calculateGrowth(validMonthlyData);
            const growthEl = document.getElementById('growthRate');
            if (growthEl) {
                if (validMonthlyData.length >= 2) {
                    growthEl.innerHTML = growthValue > 0 ? `<span class="text-green-400">+${growthValue}%</span>` : (growthValue < 0 ? `<span class="text-rose-400">${growthValue}%</span>` : `0%`);
                } else {
                    growthEl.innerHTML = '<span class="text-slate-500 text-lg">Data Terhad</span>';
                }
            }

            // --- 5. Company Performance (Horizontal Bar) ---
            const { context: perfCtx, parent: perfParent } = getChartContext('companyPerformanceChart');
            if (perfCtx && companyPerformance.length > 0) {
                new Chart(perfCtx, {
                    type: 'bar',
                    data: {
                        labels: companyPerformance.map(c => c.name),
                        datasets: [{
                            label: 'Jumlah Cukai',
                            data: companyPerformance.map(c => c.total),
                            backgroundColor: colors.indigo + 'cc', borderColor: colors.indigo,
                            borderWidth: 1, borderRadius: 8
                        }]
                    },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        scales: {
                            x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: value => 'RM ' + value.toLocaleString() } },
                            y: { grid: { display: false }, ticks: { font: { size: 11, weight: '600' } } }
                        },
                        plugins: { legend: { display: false } }
                    }
                });
            } else {
                displayNoDataMessage(perfParent, 'fas fa-building', 'Tiada data prestasi.');
            }

            // --- 6. Correlation (Scatter) ---
            const { context: corrCtx, parent: corrParent } = getChartContext('correlationChart');
            if (corrCtx && companyPerformance.length > 1) {
                new Chart(corrCtx, {
                    type: 'scatter',
                    data: {
                        datasets: [{
                            label: 'Analisis Korelasi Syarikat',
                            data: companyPerformance.map(c => ({ x: c.count, y: c.total })),
                            backgroundColor: colors.purple + '99', borderColor: colors.purple,
                            pointRadius: 8, pointHoverRadius: 10, borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            x: { title: { display: true, text: 'Volume Kenderaan', font: { weight: 'bold' } }, grid: { color: '#f1f5f9' }, beginAtZero: true },
                            y: { title: { display: true, text: 'Total Cukai (RM)', font: { weight: 'bold' } }, grid: { color: '#f1f5f9' }, beginAtZero: true, ticks: { callback: v => 'RM ' + v.toLocaleString() } }
                        }
                    }
                });
            } else {
                displayNoDataMessage(corrParent, 'fas fa-project-diagram', 'Data korelasi tidak mencukupi.');
            }

            // --- 7. Prediction Chart (6 Months) ---
            const { context: predCtx, parent: predParent } = getChartContext('predictionChart');
            if (predCtx && historicalData.length >= 2) {
                const historicalTotals = historicalData.map(d => d.total);
                const historicalLabels = historicalData.map(d => d.month);
                const forecastMonths = 6;
                const forecasts = [];
                const regression = linearRegression(historicalTotals);

                for (let i = 1; i <= forecastMonths; i++) {
                    forecasts.push(Math.max(0, regression.slope * (historicalTotals.length - 1 + i) + regression.intercept));
                }

                new Chart(predCtx, {
                    type: 'line',
                    data: {
                        labels: [...historicalLabels, ...Array(forecastMonths).fill('+').map((p, i) => p + (i + 1))],
                        datasets: [{
                            label: 'Sejarah Kutipan',
                            data: [...historicalTotals, ...Array(forecastMonths).fill(null)],
                            borderColor: colors.indigo, backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.3, fill: true, borderWidth: 3, pointRadius: 4
                        }, {
                            label: 'Ramalan AI (6 Bln)',
                            data: [...Array(historicalTotals.length - 1).fill(null), historicalTotals[historicalTotals.length - 1], ...forecasts],
                            borderColor: colors.purple, borderDash: [5, 5], tension: 0.3, pointStyle: 'triangle', pointRadius: 6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: v => 'RM ' + v.toLocaleString() } } },
                    }
                });
            } else {
                displayNoDataMessage(predParent, 'fas fa-dna', 'Data sejarah tidak mencukupi.');
            }

            // --- 8. Top Vehicle Model ---
            const { context: vehicleCtx, parent: vehicleParent } = getChartContext('topVehicleChart');
            if (vehicleCtx && topVehicleData.length > 0) {
                new Chart(vehicleCtx, {
                    type: 'bar',
                    data: {
                        labels: topVehicleData.map(v => v.model),
                        datasets: [{
                            label: 'Kutipan Model',
                            data: topVehicleData.map(v => v.total),
                            backgroundColor: colors.emerald + 'cc', borderColor: colors.emerald,
                            borderWidth: 1, borderRadius: 8
                        }]
                    },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        scales: { x: { beginAtZero: true }, y: { ticks: { font: { weight: '600' } } } },
                        plugins: { legend: { display: false } }
                    }
                });
            } else {
                displayNoDataMessage(vehicleParent, 'fas fa-car-side', 'Tiada data model.');
            }

            // AI FAB Click Listener
            const fab = document.querySelector('.ai-fab');
            if (fab) {
                fab.addEventListener('click', () => {
                    alert('AI Assistant: "Sistem stabil. Berdasarkan data terkini, saya cadangkan untuk memfokuskan pemantauan pada 5 anomali tinggi dikesan untuk mengurangkan risiko ralat data."');
                });
            }
        });

        // Export Function
        function exportDashboardData() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Laporan Dashboard MyPEKEMA\n";
            csvContent += "Tarikh: " + new Date().toLocaleDateString() + "\n\n";

            // 1. Prestasi Syarikat
            csvContent += "PRESTASI SYARIKAT\n";
            csvContent += "Syarikat,Total Cukai (RM),Count,Avg\n";
            companyPerformance.forEach(row => {
                csvContent += `"${row.name}",${row.total},${row.count},${row.avg}\n`;
            });

            // 2. Dominasi Model
            csvContent += "\nDOMINASI MODEL KENDERAAN\n";
            csvContent += "Model,Total Cukai (RM),Count,Avg\n";
            topVehicleData.forEach(row => {
                csvContent += `"${row.model}",${row.total},${row.count},${row.avg}\n`;
            });

            // 3. Anomali
            csvContent += "\nANOMALI DENGAN RISIKO TINGGI\n";
            csvContent += "Model,Syarikat,Tarikh Bayaran,Duty (RM)\n";
            anomaliesData.forEach(row => {
                csvContent += `"${row.vehicle_model}","${row.gb_nama}","${row.payment_date}",${row.duty_rm}\n`;
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "MyPEKEMA_Report_" + new Date().toISOString().slice(0, 10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>

</html>