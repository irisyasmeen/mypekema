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
 * Road Tax Analysis Logic
 * 
 * We need to analyze vehicles that have not paid road tax (cukai jalan).
 * This is critical for compliance and avoiding penalties.
 * 
 * We will categorize vehicles into:
 * 1. Overdue (Road tax expired)
 * 2. Expiring Soon (< 30 days)
 * 3. Due Soon (< 60 days)
 * 4. Active (> 60 days)
 */

$current_date = date('Y-m-d');

// Fetch vehicles with road tax information
// Assuming 'road_tax_expiry' field exists or we use 'payment_date' as proxy
// For this analysis, we'll check if payment_date is null/empty (unpaid) 
// and analyze based on created_at or a specific road_tax_expiry field

$is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

$where_clauses = [
    "created_at IS NOT NULL",
    "created_at != '0000-00-00'"
];

if ($is_licensee) {
    $where_clauses[] = "gbpekema_id = " . (int)$licensee_gb_id;
}

$where_sql = implode(" AND ", $where_clauses);

$sql = "SELECT 
            id, vehicle_model, chassis_number,
            created_at, payment_date, duty_rm as duty_amount,
            CASE 
                WHEN payment_date IS NULL OR payment_date = '0000-00-00' OR payment_date = '' THEN 'Belum Bayar'
                ELSE 'Sudah Bayar'
            END as payment_status,
            DATEDIFF('$current_date', created_at) as days_since_entry
        FROM vehicle_inventory 
        WHERE $where_sql
        ORDER BY days_since_entry DESC";

$result = $conn->query($sql);

$stats = [
    'unpaid_critical' => [],  // Unpaid > 90 days (critical)
    'unpaid_warning' => [],   // Unpaid 30-90 days
    'unpaid_recent' => [],    // Unpaid < 30 days
    'paid' => []              // Already paid
];

$total_unpaid_amount = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $days = (int) $row['days_since_entry'];

        if ($row['payment_status'] == 'Belum Bayar') {
            $total_unpaid_amount += floatval($row['duty_amount'] ?? 0);

            if ($days > 90) {
                $stats['unpaid_critical'][] = $row;
            } elseif ($days > 30) {
                $stats['unpaid_warning'][] = $row;
            } else {
                $stats['unpaid_recent'][] = $row;
            }
        } else {
            $stats['paid'][] = $row;
        }
    }
}

// Stats Counts
$total_unpaid_critical = count($stats['unpaid_critical']);
$total_unpaid_warning = count($stats['unpaid_warning']);
$total_unpaid_recent = count($stats['unpaid_recent']);
$total_paid = count($stats['paid']);
$total_vehicles = $total_unpaid_critical + $total_unpaid_warning + $total_unpaid_recent + $total_paid;
$total_unpaid = $total_unpaid_critical + $total_unpaid_warning + $total_unpaid_recent;

// AI Insight
$ai_status = "STABIL";
$ai_color = "text-emerald-400";
$ai_message = "Status pembayaran cukai berada dalam keadaan terkawal.";

if ($total_unpaid_critical > 0) {
    $ai_status = "KRITIKAL";
    $ai_color = "text-rose-500";
    $ai_message = "Terdapat {$total_unpaid_critical} unit kenderaan yang belum bayar cukai melebihi 90 hari. Jumlah cukai tertunggak: RM " . number_format($total_unpaid_amount, 2) . ". Sila ambil tindakan segera.";
} elseif ($total_unpaid_warning > 0) {
    $ai_status = "WASPADA";
    $ai_color = "text-orange-400";
    $ai_message = "Terdapat {$total_unpaid_warning} unit kenderaan yang belum bayar cukai antara 30-90 hari. Jumlah cukai tertunggak: RM " . number_format($total_unpaid_amount, 2) . ".";
} elseif ($total_unpaid_recent > 0) {
    $ai_status = "PERHATIAN";
    $ai_color = "text-blue-400";
    $ai_message = "Terdapat {$total_unpaid_recent} unit kenderaan baru yang belum bayar cukai. Jumlah cukai tertunggak: RM " . number_format($total_unpaid_amount, 2) . ".";
}

?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisa Cukai Belum Bayar - MyPEKEMA AI</title>
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
            background: linear-gradient(135deg, #dc2626 0%, #f59e0b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse-ring {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white !important;
                font-size: 10pt;
            }

            .grid-bg,
            .no-print {
                display: none !important;
            }

            .glass,
            .glass-card {
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                backdrop-filter: none !important;
                page-break-inside: avoid;
            }

            main {
                padding: 0 !important;
            }

            header {
                page-break-after: avoid;
                margin-bottom: 20px !important;
            }

            h1 {
                font-size: 24pt !important;
                color: #000 !important;
            }

            .text-gradient {
                -webkit-text-fill-color: #dc2626 !important;
                color: #dc2626 !important;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }

            .max-h-\[400px\] {
                max-height: none !important;
                overflow: visible !important;
            }

            /* Make tables full width when printing */
            .grid-cols-1.lg\\:grid-cols-3 {
                grid-template-columns: 1fr !important;
            }

            .lg\\:col-span-2 {
                grid-column: span 1 !important;
            }

            /* KPI Cards for print */
            .grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4 {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 10px !important;
                margin-bottom: 20px !important;
            }

            .glass-card {
                border: 1px solid #333 !important;
                padding: 10px !important;
            }

            /* Print header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #000;
            }

            .print-date {
                display: block !important;
                text-align: right;
                font-size: 9pt;
                margin-bottom: 10px;
            }

            /* Hide detailed vehicle lists - only print analysis */
            .grid.grid-cols-1.lg\\:grid-cols-3.gap-8 {
                display: none !important;
            }

            /* Show print summary */
            .print-summary {
                display: block !important;
                page-break-before: avoid;
            }
        }

        .print-header,
        .print-date {
            display: none;
        }

        .print-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 50;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 9999px;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(59, 130, 246, 0.4);
        }
    </style>
</head>

<body class="min-h-screen selection:bg-blue-200">
    <div class="grid-bg"></div>

    <?php include 'topmenu.php'; ?>

    <!-- Print Button -->
    <button onclick="window.print()" class="print-btn no-print">
        <i class="fas fa-print"></i>
        <span>Cetak Laporan</span>
    </button>

    <main class="container mx-auto px-4 lg:px-8 py-12 relative z-10">

        <!-- Print Header (only visible when printing) -->
        <div class="print-header">
            <h2 style="font-size: 18pt; font-weight: bold; margin: 0;">LAPORAN ANALISIS CUKAI BELUM BAYAR</h2>
            <p style="margin: 5px 0 0 0; font-size: 10pt;">Pemantauan Status Pembayaran Cukai Kenderaan</p>
        </div>
        <div class="print-date">
            Tarikh Cetak: <?= date('d/m/Y H:i:s') ?>
        </div>

        <!-- Header -->
        <header class="flex flex-col lg:flex-row justify-between items-end gap-8 mb-12">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <span
                        class="px-3 py-1 bg-red-50 border border-red-200 text-red-700 text-[10px] font-bold uppercase tracking-[0.2em] rounded-full">
                        <i class="fas fa-receipt mr-2"></i>Tax Compliance
                    </span>
                </div>
                <h1 class="text-4xl lg:text-7xl font-bold space-font tracking-tight text-slate-900">
                    Analisis <span class="text-gradient">Cukai Belum Bayar</span>
                </h1>
                <p class="text-slate-600 mt-4 text-lg max-w-2xl">
                    Pemantauan pintar status pembayaran cukai kenderaan untuk memastikan pematuhan dan mengelakkan
                    penalti.
                </p>
            </div>

            <div class="glass p-6 rounded-2xl border-l-[3px] border-red-500 max-w-sm no-print">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">AI Summary</p>
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center text-red-600 shrink-0">
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
            <!-- Critical Unpaid -->
            <div class="glass-card p-6 rounded-3xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fas fa-exclamation-triangle text-8xl text-red-500"></i>
                </div>
                <p class="text-xs font-black text-red-600 uppercase tracking-widest mb-1">Kritikal (>90 Hari)</p>
                <p class="text-4xl font-black text-slate-900 space-font mb-2">
                    <?= number_format($total_unpaid_critical) ?></p>
                <p class="text-[10px] text-slate-500">Tindakan segera diperlukan</p>
                <div class="w-full h-1 bg-slate-200 mt-4 rounded-full overflow-hidden">
                    <div class="h-full bg-red-500 pulse-ring"
                        style="width: <?= ($total_vehicles > 0) ? ($total_unpaid_critical / $total_vehicles) * 100 : 0 ?>%">
                    </div>
                </div>
            </div>

            <!-- Warning Unpaid -->
            <div class="glass-card p-6 rounded-3xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fas fa-clock text-8xl text-orange-500"></i>
                </div>
                <p class="text-xs font-black text-orange-600 uppercase tracking-widest mb-1">Amaran (30-90 Hari)</p>
                <p class="text-4xl font-black text-slate-900 space-font mb-2">
                    <?= number_format($total_unpaid_warning) ?></p>
                <p class="text-[10px] text-slate-500">Perlu perhatian</p>
                <div class="w-full h-1 bg-slate-200 mt-4 rounded-full overflow-hidden">
                    <div class="h-full bg-orange-500"
                        style="width: <?= ($total_vehicles > 0) ? ($total_unpaid_warning / $total_vehicles) * 100 : 0 ?>%">
                    </div>
                </div>
            </div>

            <!-- Recent Unpaid -->
            <div class="glass-card p-6 rounded-3xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fas fa-hourglass-half text-8xl text-blue-500"></i>
                </div>
                <p class="text-xs font-black text-blue-600 uppercase tracking-widest mb-1">Terkini (<30 Hari)</p>
                        <p class="text-4xl font-black text-slate-900 space-font mb-2">
                            <?= number_format($total_unpaid_recent) ?></p>
                        <p class="text-[10px] text-slate-500">Masih dalam tempoh normal</p>
                        <div class="w-full h-1 bg-slate-200 mt-4 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500"
                                style="width: <?= ($total_vehicles > 0) ? ($total_unpaid_recent / $total_vehicles) * 100 : 0 ?>%">
                            </div>
                        </div>
            </div>

            <!-- Total Unpaid Amount -->
            <div
                class="glass-card p-6 rounded-3xl relative overflow-hidden group bg-gradient-to-br from-rose-50 to-orange-50">
                <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                    <i class="fas fa-money-bill-wave text-8xl text-rose-500"></i>
                </div>
                <p class="text-xs font-black text-rose-600 uppercase tracking-widest mb-1">Jumlah Tertunggak</p>
                <p class="text-3xl font-black text-slate-900 space-font mb-2">RM
                    <?= number_format($total_unpaid_amount, 2) ?></p>
                <p class="text-[10px] text-slate-500"><?= $total_unpaid ?> unit belum bayar</p>
            </div>
        </div>

        <!-- Print Summary (only visible when printing) -->
        <div class="print-summary" style="display: none;">
            <div
                style="margin-top: 30px; padding: 20px; border: 2px solid #dc2626; border-radius: 10px; background: #fef2f2;">
                <h3 style="font-size: 14pt; font-weight: bold; color: #dc2626; margin: 0 0 10px 0;">
                    <i class="fas fa-exclamation-circle"></i> Ringkasan Analisis
                </h3>
                <p style="margin: 10px 0; font-size: 11pt; line-height: 1.6;">
                    <strong>Status:</strong> <?= $ai_status ?><br>
                    <strong>Mesej:</strong> <?= htmlspecialchars($ai_message) ?>
                </p>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #fca5a5;">
                    <table style="width: 100%; font-size: 10pt;">
                        <tr>
                            <td style="padding: 5px;"><strong>Jumlah Kenderaan:</strong></td>
                            <td style="padding: 5px; text-align: right;"><?= number_format($total_vehicles) ?> unit</td>
                            <td style="padding: 5px; padding-left: 20px;"><strong>Belum Bayar:</strong></td>
                            <td style="padding: 5px; text-align: right; color: #dc2626; font-weight: bold;">
                                <?= number_format($total_unpaid) ?> unit</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px;"><strong>Sudah Bayar:</strong></td>
                            <td style="padding: 5px; text-align: right; color: #059669;">
                                <?= number_format($total_paid) ?> unit</td>
                            <td style="padding: 5px; padding-left: 20px;"><strong>Kadar Pembayaran:</strong></td>
                            <td style="padding: 5px; text-align: right; font-weight: bold;">
                                <?= $total_vehicles > 0 ? number_format(($total_paid / $total_vehicles) * 100, 1) : 0 ?>%
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Left Column: Critical Lists (Span 2) -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Critical Unpaid List -->
                <?php if ($total_unpaid_critical > 0): ?>
                    <div class="glass rounded-[2rem] overflow-hidden border border-red-200">
                        <div class="p-6 border-b border-red-100 bg-red-50 flex justify-between items-center">
                            <h3 class="font-bold text-red-700 flex items-center gap-2">
                                <i class="fas fa-exclamation-circle"></i> Senarai Kritikal (>90 Hari Belum Bayar)
                            </h3>
                            <span
                                class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs font-bold"><?= $total_unpaid_critical ?>
                                Unit</span>
                        </div>
                        <div class="max-h-[400px] overflow-y-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr class="text-[10px] text-slate-600 uppercase tracking-wider">
                                        <th class="px-6 py-3">Model</th>
                                        <th class="px-6 py-3">Casis</th>
                                        <th class="px-6 py-3 text-right">Jumlah Cukai</th>
                                        <th class="px-6 py-3 text-right">Hari Tertunggak</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($stats['unpaid_critical'] as $row): ?>
                                        <tr class="hover:bg-red-50 transition-colors">
                                            <td class="px-6 py-4 font-bold text-slate-900 text-sm">
                                                <?= htmlspecialchars($row['vehicle_model']) ?></td>
                                            <td class="px-6 py-4 font-mono text-xs text-slate-600">
                                                <?= htmlspecialchars($row['chassis_number']) ?></td>
                                            <td class="px-6 py-4 text-right font-bold text-red-600 text-sm">RM
                                                <?= number_format($row['duty_amount'] ?? 0, 2) ?></td>
                                            <td class="px-6 py-4 text-right text-red-600 font-bold text-xs">
                                                <?= $row['days_since_entry'] ?> hari</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Warning Unpaid List -->
                <?php if ($total_unpaid_warning > 0): ?>
                    <div class="glass rounded-[2rem] overflow-hidden border border-orange-200">
                        <div class="p-6 border-b border-orange-100 bg-orange-50 flex justify-between items-center">
                            <h3 class="font-bold text-orange-700 flex items-center gap-2">
                                <i class="fas fa-clock"></i> Senarai Amaran (30-90 Hari Belum Bayar)
                            </h3>
                            <span
                                class="px-2 py-1 rounded bg-orange-100 text-orange-700 text-xs font-bold"><?= $total_unpaid_warning ?>
                                Unit</span>
                        </div>
                        <div class="max-h-[400px] overflow-y-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr class="text-[10px] text-slate-600 uppercase tracking-wider">
                                        <th class="px-6 py-3">Model</th>
                                        <th class="px-6 py-3">Casis</th>
                                        <th class="px-6 py-3 text-right">Jumlah Cukai</th>
                                        <th class="px-6 py-3 text-right">Hari Tertunggak</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($stats['unpaid_warning'] as $row): ?>
                                        <tr class="hover:bg-orange-50 transition-colors">
                                            <td class="px-6 py-4 font-bold text-slate-900 text-sm">
                                                <?= htmlspecialchars($row['vehicle_model']) ?></td>
                                            <td class="px-6 py-4 font-mono text-xs text-slate-600">
                                                <?= htmlspecialchars($row['chassis_number']) ?></td>
                                            <td class="px-6 py-4 text-right font-bold text-orange-600 text-sm">RM
                                                <?= number_format($row['duty_amount'] ?? 0, 2) ?></td>
                                            <td class="px-6 py-4 text-right">
                                                <span
                                                    class="px-2 py-1 rounded bg-orange-500 text-white text-xs font-bold"><?= $row['days_since_entry'] ?>
                                                    Hari</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($total_unpaid_critical == 0 && $total_unpaid_warning == 0): ?>
                    <div class="glass p-12 rounded-[2rem] text-center border border-emerald-200">
                        <div
                            class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-6 text-emerald-600 text-3xl">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Status Pembayaran Cukai Baik</h3>
                        <p class="text-slate-600">Tiada kenderaan yang dikesan mempunyai tunggakan cukai kritikal atau
                            amaran buat masa ini.</p>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Column: Visualization (Span 1) -->
            <div class="lg:col-span-1 space-y-8 no-print">

                <!-- Status Chart -->
                <div class="glass p-6 rounded-[2rem]">
                    <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest mb-6">Komposisi Status
                        Pembayaran</h3>
                    <div class="relative w-full aspect-square">
                        <canvas id="taxChart"></canvas>
                        <div class="absolute inset-0 flex items-center justify-center flex-col pointer-events-none">
                            <span class="text-3xl font-black text-slate-900 space-font"><?= $total_vehicles ?></span>
                            <span class="text-[10px] text-slate-500 uppercase tracking-widest">Jumlah Unit</span>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="glass p-6 rounded-[2rem]">
                    <h3 class="text-xs font-black text-slate-600 uppercase tracking-widest mb-4">Ringkasan</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-600">Jumlah Kenderaan</span>
                            <span class="font-bold text-slate-900"><?= number_format($total_vehicles) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-600">Belum Bayar</span>
                            <span class="font-bold text-red-600"><?= number_format($total_unpaid) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-600">Sudah Bayar</span>
                            <span class="font-bold text-emerald-600"><?= number_format($total_paid) ?></span>
                        </div>
                        <div class="pt-4 border-t border-slate-200">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-700">Kadar Pembayaran</span>
                                <span
                                    class="font-black text-blue-600 text-lg"><?= $total_vehicles > 0 ? number_format(($total_paid / $total_vehicles) * 100, 1) : 0 ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="glass p-6 rounded-[2rem] bg-gradient-to-br from-blue-50 to-indigo-50">
                    <h3 class="text-xs font-bold text-blue-700 uppercase tracking-widest mb-4">Nota Penting</h3>
                    <ul class="space-y-4">
                        <li class="flex gap-3 text-xs text-slate-700">
                            <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                            <span>Cukai kenderaan perlu dibayar <strong>sebelum pendaftaran</strong> kenderaan.</span>
                        </li>
                        <li class="flex gap-3 text-xs text-slate-700">
                            <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                            <span>Kenderaan yang tertunggak lebih 90 hari mungkin tertakluk kepada <strong>penalti
                                    tambahan</strong>.</span>
                        </li>
                        <li class="flex gap-3 text-xs text-slate-700">
                            <i class="fas fa-lightbulb text-emerald-600 mt-0.5"></i>
                            <span>Prioritaskan pembayaran untuk unit kritikal bagi mengelakkan kos tambahan.</span>
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

        const ctx = document.getElementById('taxChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Kritikal (>90)', 'Amaran (30-90)', 'Terkini (<30)', 'Sudah Bayar'],
                datasets: [{
                    data: [<?= $total_unpaid_critical ?>, <?= $total_unpaid_warning ?>, <?= $total_unpaid_recent ?>, <?= $total_paid ?>],
                    backgroundColor: [
                        '#ef4444', // Red 500
                        '#f97316', // Orange 500
                        '#3b82f6', // Blue 500
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed + ' unit';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>