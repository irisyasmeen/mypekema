<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

/**
 * AP Expiry Analysis Logic
 * 
 * We need to calculate the AP Expiry date based on the 'import_date' (Tarikh Import).
 * Rule: AP expires 12 months (or 1 year) after the import date if not used/registered.
 * 
 * We will categorize APs into:
 * 1. Expired (Overdue)
 * 2. Expiring Soon (< 30 days)
 * 3. Healthy (> 30 days)
 */

$current_date = date('Y-m-d');
$expiry_threshold_days = 30; // Warning threshold

// 1. Fetch relevant data
// We assume 'import_date' is the anchor. If 'created_at' is used as proxy, change accordingly.
// We also need to check if the vehicle is already registered/sold to exclude it.
// Assuming 'status' = 'sold' or check payment_date to see if duty paid.
// For AP management, usually we care about unsold vehicles in Bonded Warehouse.

$is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

$where_clauses = [
    "created_at IS NOT NULL",
    "created_at != '0000-00-00'",
    "(payment_date IS NULL OR payment_date = '0000-00-00' OR payment_date = '')"
];

if ($is_licensee) {
    $where_clauses[] = "gbpekema_id = " . (int)$licensee_gb_id;
}

$where_sql = implode(" AND ", $where_clauses);

$sql = "SELECT 
            id, vehicle_model, chassis_number, created_at as import_date, 
            DATEDIFF(DATE_ADD(created_at, INTERVAL 1 YEAR), '$current_date') as days_remaining,
            DATE_ADD(created_at, INTERVAL 1 YEAR) as expiry_date
        FROM vehicle_inventory 
        WHERE $where_sql
        ORDER BY days_remaining ASC";

$result = $conn->query($sql);

$stats = [
    'expired' => [],
    'critical' => [], // < 30 days
    'warning' => [],  // < 60 days
    'healthy' => []
];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $days = (int) $row['days_remaining'];

        if ($days < 0) {
            $stats['expired'][] = $row;
        } elseif ($days <= 30) {
            $stats['critical'][] = $row;
        } elseif ($days <= 90) {
            $stats['warning'][] = $row;
        } else {
            $stats['healthy'][] = $row;
        }
    }
}

// Stats Counts
$total_expired = count($stats['expired']);
$total_critical = count($stats['critical']);
$total_warning = count($stats['warning']);
$total_healthy = count($stats['healthy']);
$total_monitored = $total_expired + $total_critical + $total_warning + $total_healthy;

// AI Insight
$ai_status = "STABIL";
$ai_color = "text-emerald-400";
$ai_message = "Status pegangan AP berada dalam keadaan terkawal.";

if ($total_expired > 0) {
    $ai_status = "KRITIKAL";
    $ai_color = "text-rose-500";
    $ai_message = "Terdapat {$total_expired} unit kenderaan dengan AP yang telah tamat tempoh. Sila ambil tindakan segera untuk elak penalti.";
} elseif ($total_critical > 0) {
    $ai_status = "WASPADA";
    $ai_color = "text-orange-400";
    $ai_message = "Terdapat {$total_critical} unit kenderaan dengan AP yang akan tamat tempoh dalam masa kurang 30 hari. Prioritaskan jualan unit ini.";
}

?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisa AP - MyPEKEMA AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --accent: #60a5fa;
            --bg-deep: #f8fafc;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e0e7ff 100%);
            background-attachment: fixed;
            color: #1e293b;
            overflow-x: hidden;
        }

        .space-font {
            font-family: 'Space Grotesk', sans-serif;
        }

        .glass {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 4px 24px 0 rgba(0, 0, 0, 0.06);
        }

        .glass-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
        }

        /* Animated Grid Background */
        .grid-bg {
            background-size: 50px 50px;
            background-image: linear-gradient(to right, rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            mask-image: radial-gradient(circle at center, black 0%, transparent 90%);
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        .text-gradient {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>

<body class="min-h-screen selection:bg-blue-200">
    <div class="grid-bg"></div>

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto px-4 lg:px-8 py-12 relative z-10">

        <!-- Header -->
        <header class="flex flex-col lg:flex-row justify-between items-end gap-8 mb-12">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <span
                        class="px-3 py-1 bg-blue-50 border border-blue-200 text-blue-700 text-[10px] font-bold uppercase tracking-[0.2em] rounded-full">
                        <i class="fas fa-passport mr-2"></i>Permit Intelligence
                    </span>
                </div>
                <h1 class="text-4xl lg:text-7xl font-bold space-font tracking-tight text-slate-900">
                    Analisis <span class="text-gradient">AP</span>
                </h1>
                <p class="text-slate-600 mt-4 text-lg max-w-2xl">
                    Pemantauan pintar tempoh sah laku Approved Permit (AP) untuk kenderaan dalam pegangan.
                </p>
            </div>

            <div class="glass p-6 rounded-2xl border-l-[3px] border-blue-500 max-w-sm">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">AI Summary</p>
                <div class="flex items-start gap-4">
                    <div
                        class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                        <i class="fas fa-robot text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm text-slate-700 leading-relaxed font-medium">
                            <?= htmlspecialchars($ai_message) ?>
                        </p>
                    </div>
                </div>
            </div>
        </header>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Expired -->
            <div class="glass-card p-6 rounded-3xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fas fa-exclamation-circle text-8xl text-red-500"></i>
                </div>
                <p class="text-xs font-black text-red-600 uppercase tracking-widest mb-1">Tamat Tempoh</p>
                <p class="text-4xl font-black text-slate-900 space-font mb-2"><?= number_format($total_expired) ?></p>
                <p class="text-[10px] text-slate-500">Unit perlu tindakan segera</p>
                <div class="w-full h-1 bg-slate-200 mt-4 rounded-full overflow-hidden">
                    <div class="h-full bg-red-500"
                        style="width: <?= ($total_monitored > 0) ? ($total_expired / $total_monitored) * 100 : 0 ?>%"></div>
                </div>
            </div>

            <!-- Critical -->
            <div class="glass-card p-6 rounded-3xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fas fa-stopwatch text-8xl text-orange-500"></i>
                </div>
                <p class="text-xs font-black text-orange-600 uppercase tracking-widest mb-1">Kritikal (< 30 Hari)</p>
                        <p class="text-4xl font-black text-slate-900 space-font mb-2">
                            <?= number_format($total_critical) ?></p>
                        <p class="text-[10px] text-slate-500">Hampir luput</p>
                        <div class="w-full h-1 bg-slate-200 mt-4 rounded-full overflow-hidden">
                            <div class="h-full bg-orange-500"
                                style="width: <?= ($total_monitored > 0) ? ($total_critical / $total_monitored) * 100 : 0 ?>%">
                            </div>
                        </div>
            </div>

            <!-- Warning -->
            <div class="glass-card p-6 rounded-3xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fas fa-clock text-8xl text-amber-500"></i>
                </div>
                <p class="text-xs font-black text-amber-600 uppercase tracking-widest mb-1">Amaran (1-3 Bulan)</p>
                <p class="text-4xl font-black text-slate-900 space-font mb-2"><?= number_format($total_warning) ?></p>
                <p class="text-[10px] text-slate-500">Pantauan berkala</p>
                <div class="w-full h-1 bg-slate-200 mt-4 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500"
                        style="width: <?= ($total_monitored > 0) ? ($total_warning / $total_monitored) * 100 : 0 ?>%"></div>
                </div>
            </div>

            <!-- Healthy -->
            <div class="glass-card p-6 rounded-3xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fas fa-check-circle text-8xl text-emerald-500"></i>
                </div>
                <p class="text-xs font-black text-emerald-600 uppercase tracking-widest mb-1">Sihat (> 3 Bulan)</p>
                <p class="text-4xl font-black text-slate-900 space-font mb-2"><?= number_format($total_healthy) ?></p>
                <p class="text-[10px] text-slate-500">Status selamat</p>
                <div class="w-full h-1 bg-slate-200 mt-4 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500"
                        style="width: <?= ($total_monitored > 0) ? ($total_healthy / $total_monitored) * 100 : 0 ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Left Column: Critical Lists (Span 2) -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Expired List -->
                <?php if ($total_expired > 0): ?>
                    <div class="glass rounded-[2rem] overflow-hidden border border-red-200">
                        <div class="p-6 border-b border-red-100 bg-red-50 flex justify-between items-center">
                            <h3 class="font-bold text-red-700 flex items-center gap-2">
                                <i class="fas fa-times-circle"></i> Senarai Tamat Tempoh
                            </h3>
                            <span class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs font-bold"><?= $total_expired ?>
                                Unit</span>
                        </div>
                        <div class="max-h-[400px] overflow-y-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr class="text-[10px] text-slate-600 uppercase tracking-wider">
                                        <th class="px-6 py-3">Model</th>
                                        <th class="px-6 py-3">Casis</th>
                                        <th class="px-6 py-3">Tarikh AP</th>
                                        <th class="px-6 py-3 text-right">Lupus Sejak</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($stats['expired'] as $row): ?>
                                        <tr class="hover:bg-red-50 transition-colors">
                                            <td class="px-6 py-4 font-bold text-slate-900 text-sm">
                                                <?= htmlspecialchars($row['vehicle_model']) ?></td>
                                            <td class="px-6 py-4 font-mono text-xs text-slate-600">
                                                <?= htmlspecialchars($row['chassis_number']) ?></td>
                                            <td class="px-6 py-4 text-xs text-slate-700">
                                                <?= date('d M Y', strtotime($row['expiry_date'])) ?></td>
                                            <td class="px-6 py-4 text-right text-red-600 font-bold text-xs">
                                                <?= abs($row['days_remaining']) ?> hari lalu</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Critical List (< 30 Days) -->
                <?php if ($total_critical > 0): ?>
                    <div class="glass rounded-[2rem] overflow-hidden border border-orange-200">
                        <div class="p-6 border-b border-orange-100 bg-orange-50 flex justify-between items-center">
                            <h3 class="font-bold text-orange-700 flex items-center gap-2">
                                <i class="fas fa-fire"></i> Kritikal (< 30 Hari) </h3>
                                    <span
                                        class="px-2 py-1 rounded bg-orange-100 text-orange-700 text-xs font-bold"><?= $total_critical ?>
                                        Unit</span>
                        </div>
                        <div class="max-h-[400px] overflow-y-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr class="text-[10px] text-slate-600 uppercase tracking-wider">
                                        <th class="px-6 py-3">Model</th>
                                        <th class="px-6 py-3">Casis</th>
                                        <th class="px-6 py-3">Tarikh Luput</th>
                                        <th class="px-6 py-3 text-right">Baki Hari</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($stats['critical'] as $row): ?>
                                        <tr class="hover:bg-orange-50 transition-colors">
                                            <td class="px-6 py-4 font-bold text-slate-900 text-sm">
                                                <?= htmlspecialchars($row['vehicle_model']) ?></td>
                                            <td class="px-6 py-4 font-mono text-xs text-slate-600">
                                                <?= htmlspecialchars($row['chassis_number']) ?></td>
                                            <td class="px-6 py-4 text-xs text-slate-700">
                                                <?= date('d M Y', strtotime($row['expiry_date'])) ?></td>
                                            <td class="px-6 py-4 text-right">
                                                <span
                                                    class="px-2 py-1 rounded bg-orange-500 text-white text-xs font-bold"><?= $row['days_remaining'] ?>
                                                    Hari</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($total_expired == 0 && $total_critical == 0): ?>
                    <div class="glass p-12 rounded-[2rem] text-center border border-emerald-200">
                        <div
                            class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-6 text-emerald-600 text-3xl">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Semua AP Dalam Keadaan Baik</h3>
                        <p class="text-slate-600">Tiada kenderaan yang dikesan mempunyai AP luput atau kritikal buat masa
                            ini.</p>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Column: Visualization (Span 1) -->
            <div class="lg:col-span-1 space-y-8">

                <!-- Status Chart -->
                <div class="glass p-6 rounded-[2rem]">
                    <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest mb-6">Komposisi Status</h3>
                    <div class="relative w-full aspect-square">
                        <canvas id="apChart"></canvas>
                        <div class="absolute inset-0 flex items-center justify-center flex-col pointer-events-none">
                            <span class="text-3xl font-black text-slate-900 space-font"><?= $total_monitored ?></span>
                            <span class="text-[10px] text-slate-500 uppercase tracking-widest">Jumlah Unit</span>
                        </div>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="glass p-6 rounded-[2rem] bg-gradient-to-br from-blue-50 to-indigo-50">
                    <h3 class="text-xs font-bold text-blue-700 uppercase tracking-widest mb-4">Nota Polisi</h3>
                    <ul class="space-y-4">
                        <li class="flex gap-3 text-xs text-slate-700">
                            <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                            <span>AP sah laku selama <strong>12 bulan (1 Tahun)</strong> dari tarikh kemasukan sistem
                                (proxy untuk landing date).</span>
                        </li>
                        <li class="flex gap-3 text-xs text-slate-700">
                            <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                            <span>Kenderaan yang tidak didaftarkan dalam tempoh ini memerlukan pelanjutan khas atau
                                penalti.</span>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

    </main>

    <script>
        // Chart Config
        Chart.defaults.font.family = "'Outfit', sans-serif";
        Chart.defaults.color = '#64748b';

        const ctx = document.getElementById('apChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Tamat Tempoh', 'Kritikal (<30)', 'Amaran (<90)', 'Sihat'],
                datasets: [{
                    data: [<?= $total_expired ?>, <?= $total_critical ?>, <?= $total_warning ?>, <?= $total_healthy ?>],
                    backgroundColor: [
                        '#ef4444', // Red 500
                        '#f97316', // Orange 500
                        '#f59e0b', // Amber 500
                        '#10b981'  // Emerald 500
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '75%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 10, weight: 'bold' },
                            color: '#475569'
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>