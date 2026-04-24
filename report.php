<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// --- ROLE BASED ACCESS ---
$is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

// Fetch GB/PEKEMA companies for the filter dropdown
$gb_sql = "SELECT id, nama FROM gbpekema";
if ($is_licensee) {
    $gb_sql .= " WHERE id = " . (int)$licensee_gb_id;
}
$gb_sql .= " ORDER BY nama ASC";
$gbpekema_result = $conn->query($gb_sql);

// --- FILTERING LOGIC ---
$gbpekema_id_filter = $_GET['gbpekema_id'] ?? 'all';

// Enforce Licensee Restriction on Filter
if ($is_licensee) {
    $gbpekema_id_filter = $licensee_gb_id;
}
$negeri_filter = $_GET['negeri'] ?? 'all';
$start_date_filter = $_GET['start_date'] ?? '';
$end_date_filter = $_GET['end_date'] ?? '';

$where = ["1=1"];

if ($gbpekema_id_filter != 'all') {
    $id_safe = (int) $gbpekema_id_filter;
    $where[] = "v.gbpekema_id = $id_safe";
}

if ($negeri_filter != 'all') {
    $negeri_safe = $conn->real_escape_string($negeri_filter);
    $where[] = "g.negeri = '$negeri_safe'";
}

if (!empty($start_date_filter)) {
    $start_safe = $conn->real_escape_string($start_date_filter);
    $where[] = "COALESCE(v.payment_date, v.created_at) >= '$start_safe 00:00:00'";
}

if (!empty($end_date_filter)) {
    $end_safe = $conn->real_escape_string($end_date_filter);
    $where[] = "COALESCE(v.payment_date, v.created_at) <= '$end_safe 23:59:59'";
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT g.nama as gbpekema_nama, g.negeri as gbpekema_negeri, COALESCE(v.payment_date, v.created_at) as process_date, 
                v.vehicle_model, v.chassis_number as chassis_no, v.duty_rm, v.duti_import, v.duti_eksais, v.cukai_jualan, v.id as vehicle_id
        FROM vehicle_inventory v 
        LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
        WHERE $where_clause
        ORDER BY process_date DESC";

$result = $conn->query($sql);

// Summary data
$summary = ['total_tax' => 0, 'total_units' => 0];
// FIXED: Added LEFT JOIN because $where_clause references 'g.negeri'
$summary_sql = "SELECT SUM(duty_rm) as total_tax, COUNT(*) as total_units 
                FROM vehicle_inventory v 
                LEFT JOIN gbpekema g ON v.gbpekema_id = g.id 
                WHERE $where_clause";
$summary_res = $conn->query($summary_sql);
if ($summary_res) {
    $summary = $summary_res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pintar - MyPEKEMA AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #002d62;
            --accent: #1d4ed8;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
            /* Slate 50 */
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 30px 30px;
            color: #0f172a;
            /* Slate 900 */
        }

        .space-font {
            font-family: 'Space Grotesk', sans-serif;
        }

        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        @media print {
            body {
                background: white !important;
                color: black !important;
                background-image: none !important;
            }

            .no-print {
                display: none !important;
            }

            .glass {
                background: none !important;
                border: none !important;
                box-shadow: none !important;
            }

            table {
                font-size: 10px;
                width: 100%;
            }

            th,
            td {
                border-bottom: 1px solid #ddd;
                padding: 4px;
            }
        }

        /* Form Elements */
        select,
        input {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            color: #1e293b !important;
        }

        select:focus,
        input:focus {
            border-color: var(--primary) !important;
            ring: 2px solid var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 45, 98, 0.1);
        }
    </style>
</head>

<body class="min-h-screen">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto px-4 lg:px-8 py-12 relative z-10">

        <!-- Header -->
        <header class="flex flex-col lg:flex-row justify-between items-end gap-8 mb-12 no-print">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <span
                        class="px-3 py-1 bg-white border border-slate-200 text-emerald-600 text-[10px] font-bold uppercase tracking-[0.2em] rounded-full shadow-sm">
                        <i class="fas fa-chart-pie mr-2"></i>Financial Intelligence
                    </span>
                </div>
                <h1 class="text-4xl lg:text-6xl font-bold space-font tracking-tight text-slate-900">
                    Pusat <span
                        class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-blue-600">Laporan</span>
                </h1>
                <p class="text-slate-500 mt-4 text-lg max-w-2xl">Penjanaan laporan inventori dan data cukai yang
                    komprehensif.</p>
            </div>

            <div class="flex gap-4">
                <div class="glass p-6 rounded-2xl border-l-[3px] border-emerald-500 min-w-[200px] bg-white/60">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Jumlah Unit</p>
                    <p class="text-3xl font-black text-slate-800 space-font">
                        <?= number_format($summary['total_units'] ?? 0) ?></p>
                </div>
                <div class="glass p-6 rounded-2xl border-l-[3px] border-blue-600 min-w-[240px] bg-white/60">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Anggaran Cukai</p>
                    <p class="text-3xl font-black text-blue-700 space-font">RM
                        <?= number_format($summary['total_tax'] ?? 0) ?></p>
                </div>
            </div>
        </header>

        <!-- Filters Section -->
        <div
            class="glass p-8 rounded-[2rem] mb-12 border border-blue-100 no-print relative overflow-hidden group bg-white/80">

            <form action="report.php" method="GET"
                class="relative z-10 grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                <div class="md:col-span-3">
                    <label
                        class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Negeri</label>
                    <div class="relative">
                        <i class="fas fa-map-marker-alt absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <select name="negeri"
                            class="w-full rounded-xl pl-12 pr-4 py-3.5 text-sm font-bold outline-none transition-all cursor-pointer hover:border-blue-400">
                            <option value="all" <?= $negeri_filter == 'all' ? 'selected' : '' ?>>Semua Negeri</option>
                            <option value="KLIA" <?= $negeri_filter == 'KLIA' ? 'selected' : '' ?>>KLIA</option>
                            <option value="Johor" <?= $negeri_filter == 'Johor' ? 'selected' : '' ?>>Johor</option>
                            <option value="Kedah" <?= $negeri_filter == 'Kedah' ? 'selected' : '' ?>>Kedah</option>
                            <option value="Kelantan" <?= $negeri_filter == 'Kelantan' ? 'selected' : '' ?>>Kelantan
                            </option>
                            <option value="Melaka" <?= $negeri_filter == 'Melaka' ? 'selected' : '' ?>>Melaka</option>
                            <option value="Negeri Sembilan" <?= $negeri_filter == 'Negeri Sembilan' ? 'selected' : '' ?>>
                                Negeri Sembilan</option>
                            <option value="Pahang" <?= $negeri_filter == 'Pahang' ? 'selected' : '' ?>>Pahang</option>
                            <option value="Perak" <?= $negeri_filter == 'Perak' ? 'selected' : '' ?>>Perak</option>
                            <option value="Perlis" <?= $negeri_filter == 'Perlis' ? 'selected' : '' ?>>Perlis</option>
                            <option value="Pulau Pinang" <?= $negeri_filter == 'Pulau Pinang' ? 'selected' : '' ?>>Pulau
                                Pinang</option>
                            <option value="Sabah" <?= $negeri_filter == 'Sabah' ? 'selected' : '' ?>>Sabah</option>
                            <option value="Sarawak" <?= $negeri_filter == 'Sarawak' ? 'selected' : '' ?>>Sarawak</option>
                            <option value="Selangor" <?= $negeri_filter == 'Selangor' ? 'selected' : '' ?>>Selangor
                            </option>
                            <option value="Terengganu" <?= $negeri_filter == 'Terengganu' ? 'selected' : '' ?>>Terengganu
                            </option>
                            <option value="W.P. Kuala Lumpur" <?= (empty($negeri_filter) || $negeri_filter == 'all' || $negeri_filter == 'W.P. Kuala Lumpur') ? '' : ($negeri_filter == 'W.P. Kuala Lumpur' ? 'selected' : '') ?>>W.P. Kuala Lumpur</option>
                            <option value="W.P. Labuan" <?= $negeri_filter == 'W.P. Labuan' ? 'selected' : '' ?>>W.P.
                                Labuan</option>
                            <option value="W.P. Putrajaya" <?= $negeri_filter == 'W.P. Putrajaya' ? 'selected' : '' ?>>W.P.
                                Putrajaya</option>
                        </select>
                    </div>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Syarikat /
                        Entiti</label>
                    <div class="relative">
                        <i class="fas fa-building absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <select name="gbpekema_id"
                            class="w-full rounded-xl pl-12 pr-4 py-3.5 text-sm font-bold outline-none transition-all cursor-pointer hover:border-blue-400">
                            <?php if (!$is_licensee): ?>
                            <option value="all" <?= $gbpekema_id_filter == 'all' ? 'selected' : '' ?>>Semua Syarikat
                                (Master List)</option>
                            <?php endif; ?>
                            <?php
                            if ($gbpekema_result) {
                                $gbpekema_result->data_seek(0);
                                while ($row = $gbpekema_result->fetch_assoc()):
                                    ?>
                                    <option value="<?= $row['id'] ?>" <?= $gbpekema_id_filter == $row['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['nama']) ?></option>
                                <?php
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Tarikh
                        Mula</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date_filter) ?>"
                        class="w-full rounded-xl px-4 py-3.5 text-sm font-bold outline-none transition-all">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">Tarikh
                        Akhir</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date_filter) ?>"
                        class="w-full rounded-xl px-4 py-3.5 text-sm font-bold outline-none transition-all">
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit"
                        class="w-full py-3.5 bg-blue-800 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-900/20 transition-all active:scale-95 text-sm uppercase tracking-wider">
                        <i class="fas fa-search mr-2"></i> Jana
                    </button>
                    <a href="report.php"
                        class="py-3.5 px-4 bg-slate-200 hover:bg-slate-300 text-slate-600 rounded-xl transition-all"
                        title="Reset">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Data Visual Summary (Simple Chart) -->
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="glass p-6 rounded-[2rem] mb-8 no-print bg-white/70">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-700">Analisis Transaksi Terkini</h3>
                </div>
                <div class="h-64 w-full">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- Results Table -->
        <div
            class="bg-white rounded-[2.5rem] overflow-hidden min-h-[500px] border border-slate-200 shadow-xl shadow-slate-200/50 print-area">
            <div class="p-8 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Senarai Transaksi</h2>
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Found
                        <?= ($result) ? $result->num_rows : 0 ?> Records</p>
                </div>
                <div class="flex gap-3 no-print">
                    <button onclick="window.print()"
                        class="px-6 py-3 bg-slate-100 border border-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-200 transition-all text-xs uppercase tracking-widest">
                        <i class="fas fa-print mr-2"></i> Cetak
                    </button>
                    <?php
                    $query_params = $_GET;
                    $download_url = "export_csv.php?" . http_build_query($query_params);
                    ?>
                    <a href="<?= $download_url ?>"
                        class="px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-500 transition-all shadow-lg shadow-emerald-500/20 text-xs uppercase tracking-widest">
                        <i class="fas fa-file-csv mr-2"></i> CSV
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">
                                Syarikat / Entiti</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">
                                Negeri</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">
                                Tarikh</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Model
                            </th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">Casis
                            </th>
                            <th
                                class="px-8 py-5 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">
                                Cukai (RM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        // Prepare data arrays for chart while iterating
                        $chart_labels = [];
                        $chart_data = [];
                        $limit_chart = 20; // limit points for clarity
                        $counter = 0;

                        if ($result && $result->num_rows > 0):
                            // Reset pointer just in case, though usually at 0
                            $result->data_seek(0);
                            while ($row = $result->fetch_assoc()):
                                // Collect data for chart (first 20 rows reversed effectively since desc)
                                if ($counter < $limit_chart) {
                                    $chart_labels[] = date('d/m', strtotime($row["process_date"]));
                                    $chart_data[] = $row["duty_rm"];
                                    $counter++;
                                }
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors group">
                                    <td class="px-8 py-5">
                                        <p class="text-sm font-bold text-slate-700 truncate max-w-[200px]">
                                            <?= htmlspecialchars($row["gbpekema_nama"] ?? 'N/A') ?></p>
                                    </td>
                                    <td class="px-8 py-5">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-800">
                                            <?= htmlspecialchars($row["gbpekema_negeri"] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-5">
                                        <span class="font-mono text-xs text-slate-500">
                                            <?= (!empty($row["process_date"]) && $row["process_date"] != '0000-00-00') ? date('d M Y', strtotime($row["process_date"])) : '-' ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 rounded bg-indigo-50 text-indigo-500 flex items-center justify-center group-hover:bg-indigo-100 transition-all no-print">
                                                <i class="fas fa-car text-xs"></i>
                                            </div>
                                            <a href="vehicle_details.php?id=<?= $row['vehicle_id'] ?>"
                                                class="text-sm font-bold text-slate-700 group-hover:text-blue-600 transition-colors">
                                                <?= htmlspecialchars($row["vehicle_model"]) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <p class="text-xs font-mono font-bold text-slate-500 group-hover:text-slate-700">
                                            <?= htmlspecialchars($row["chassis_no"]) ?></p>
                                    </td>
                                    <td class="px-8 py-5 text-right">
                                        <p class="text-sm font-black text-emerald-600">RM
                                            <?= number_format((float) $row["duty_rm"], 2) ?></p>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        else:
                            ?>
                            <tr>
                                <td colspan="6" class="px-8 py-24 text-center">
                                    <div
                                        class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                                        <i class="fas fa-search text-2xl"></i>
                                    </div>
                                    <p class="text-slate-500 font-bold">Tiada rekod ditemui.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="hidden print:block p-8 border-t border-gray-200 mt-8">
                <p class="text-center text-xs text-gray-500">Laporan dijana komputer. Tandatangan tidak diperlukan.</p>
                <p class="text-center text-[10px] text-gray-400 mt-1">MyPEKEMA Intelligent Report &bull; Generated on
                    <?= date('d M Y H:i') ?></p>
            </div>
        </div>
    </main>

    <script>
        // Setup Chart
        <?php if (!empty($chart_data)): ?>
            const ctx = document.getElementById('trendChart').getContext('2d');
            // Reverse arrays to show chronological left-to-right (if query is DESC)
            const labels = <?= json_encode(array_reverse($chart_labels)) ?>;
            const data = <?= json_encode(array_reverse($chart_data)) ?>;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nilai Cukai (RM)',
                        data: data,
                        borderColor: '#10b981', // Emerald 500
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#064e3b'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            grid: { color: 'rgba(0, 0, 0, 0.05)' },
                            ticks: { color: '#64748b' } // Slate 500
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b' }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>
