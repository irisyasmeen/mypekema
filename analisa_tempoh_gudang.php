<?php
session_start();
include 'config.php';

// Check Auth
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Prepare Data for Analysis

// 1. Fetch all valid vehicles with an import_date
// We assume 'In Warehouse' means we are tracking their age from import_date. 
// If there is a 'released' status, we should ideally filter, but based on available info, 
// we'll calculate age relative to NOW() for all, or allow filtering.
// For "Inventory" context, usually we care about what hasn't been paid/released.
// Let's grab payment_date too to potentially flag 'Paid' vehicles.

$is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

$where_clauses = ["v.import_date IS NOT NULL", "v.import_date != '0000-00-00'"];
if ($is_licensee) {
    $where_clauses[] = "v.gbpekema_id = " . (int)$licensee_gb_id;
}
$where_sql = implode(" AND ", $where_clauses);

$sql = "SELECT 
            v.id, 
            v.lot_number, 
            v.vehicle_model, 
            v.chassis_number, 
            v.import_date, 
            v.kod_gudang, 
            v.gbpekema_id,
            v.payment_date,
            g.negeri as vehicle_negeri,
            DATEDIFF(NOW(), v.import_date) as days_in_warehouse
        FROM vehicle_inventory v
        LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
        WHERE $where_sql
        ORDER BY days_in_warehouse DESC";

$result = $conn->query($sql);

$vehicles = [];
$stats = [
    'total_vehicles' => 0,
    'avg_days' => 0,
    'max_days' => 0,
    'categories' => [
        '0-3_months' => 0, // 0 - 90 days
        '3-6_months' => 0, // 91 - 180 days
        '6-12_months' => 0, // 181 - 365 days
        '1-2_years' => 0, // 366 - 730 days
        'over_2_years' => 0 // > 730 days
    ]
];

$total_days = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $days = (int) $row['days_in_warehouse'];

        // Categorize
        if ($days <= 90) {
            $stats['categories']['0-3_months']++;
        } elseif ($days <= 180) {
            $stats['categories']['3-6_months']++;
        } elseif ($days <= 365) {
            $stats['categories']['6-12_months']++;
        } elseif ($days <= 730) {
            $stats['categories']['1-2_years']++;
        } else {
            $stats['categories']['over_2_years']++;
        }

        $total_days += $days;
        if ($days > $stats['max_days']) {
            $stats['max_days'] = $days;
        }

        $vehicles[] = $row;
    }
    $stats['total_vehicles'] = count($vehicles);
    $stats['avg_days'] = $stats['total_vehicles'] > 0 ? round($total_days / $stats['total_vehicles']) : 0;
}

// Fetch Warehouse Names for filtering/display if needed
$warehouses = [];
$gb_sql = "SELECT id, nama FROM gbpekema";
$gb_res = $conn->query($gb_sql);
while ($gb = $gb_res->fetch_assoc()) {
    $warehouses[$gb['id']] = $gb['nama'];
}

?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisa Tempoh Gudang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .dt-search input {
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem;
            outline: none;
        }

        .dt-search input:focus {
            border-color: #3b82f6;
            ring: 2px solid #93c5fd;
        }

        .dataTables_wrapper .dataTables_length select {
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            padding: 0.25rem 2rem 0.25rem 0.75rem;
        }
    </style>
</head>

<body>

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Analisa Tempoh Bonded</h1>
                <p class="text-slate-500 mt-1">Analitik jangka masa kenderaan berada di dalam gudang (Aging Report).</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Removed Filter Form -->
                <a href="vehicles.php"
                    class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold py-2 px-4 rounded-lg shadow-sm transition-all inline-flex items-center">
                    <i class="fas fa-list mr-2"></i> Senarai Utama
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Card 1 -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                        <i class="fas fa-car text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Jumlah Kenderaan</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= number_format($stats['total_vehicles']) ?>
                        </h3>
                    </div>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-amber-50 text-amber-600 rounded-xl">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Purata Masa (Hari)</p>
                        <h3 class="text-2xl font-bold text-slate-800"><?= number_format($stats['avg_days']) ?></h3>
                    </div>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-red-50 text-red-600 rounded-xl">
                        <i class="fas fa-hourglass-end text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Tempoh Maksimum Kenderaan digudangkan</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <h3 class="text-2xl font-bold text-slate-800"><?= number_format($stats['max_days']) ?></h3>
                            <?php if ($stats['max_days'] > 0):
                                $max_bulan = floor($stats['max_days'] / 30);
                                $max_hari = $stats['max_days'] % 30;
                                $tempoh_str = "";
                                if ($max_bulan > 0)
                                    $tempoh_str .= $max_bulan . " Bulan ";
                                if ($max_hari > 0)
                                    $tempoh_str .= $max_hari . " Hari";
                                ?>
                                <span
                                    class="text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded-full border border-red-100">
                                    <?= trim($tempoh_str) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($stats['categories']['over_2_years'] > 0): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8 rounded-r-lg flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-red-500 mt-1"></i>
                <div>
                    <h3 class="font-bold text-red-800">Amaran Tempoh Kritikal</h3>
                    <p class="text-red-700 text-sm">Terdapat <span
                            class="font-black text-lg"><?= $stats['categories']['over_2_years'] ?></span> kenderaan yang
                        telah melebihi tempoh 24 bulan (2 Tahun) di dalam gudang. Sila ambil tindakan segera.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <div class="lg:col-span-1 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-800 mb-4">Pengelasan Tempoh (Aging)</h3>
                <div id="agingPieChart"></div>
            </div>
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-800 mb-4">Taburan Kumpulan Tempoh</h3>
                <div id="agingBarChart"></div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="font-bold text-slate-800">Senarai Kenderaan Mengikut Tempoh</h3>
                <button onclick="exportTableCheck()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-download mr-1"></i> Export CSV
                </button>
            </div>
            <div class="p-6">
                <table id="agingTable" class="w-full text-sm text-left text-slate-600 display">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 rounded-l-lg">No. Lot</th>
                            <th class="px-4 py-3">Model</th>
                            <th class="px-4 py-3">No. Casis</th>
                            <th class="px-4 py-3">Negeri</th>
                            <th class="px-4 py-3">Gudang</th>
                            <th class="px-4 py-3">Tarikh Masuk</th>
                            <th class="px-4 py-3">Tempoh (Hari)</th>
                            <th class="px-4 py-3 rounded-r-lg">Status Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $v):
                            $days = $v['days_in_warehouse'];
                            $badgeColor = 'bg-green-100 text-green-800';
                            $label = '< 3 Bulan';

                            if ($days > 730) {
                                $badgeColor = 'bg-red-100 text-red-800 animate-pulse';
                                $label = '> 24 Bulan (Kritikal)';
                                $rowClass = 'bg-red-50 border-red-100 hover:bg-red-100'; // Special styling for > 24 months
                            } elseif ($days > 365) {
                                $badgeColor = 'bg-orange-100 text-orange-800';
                                $label = '1 - 2 Tahun';
                                $rowClass = 'hover:bg-slate-50';
                            } elseif ($days > 90) {
                                $badgeColor = 'bg-yellow-100 text-yellow-800';
                                $label = '3 - 12 Bulan';
                                $rowClass = 'hover:bg-slate-50';
                            } else {
                                $rowClass = 'hover:bg-slate-50';
                            }
                            ?>
                            <tr class="<?= $rowClass ?> transition-colors border-b last:border-0 border-slate-100">
                                <td class="px-4 py-3 font-semibold text-slate-800">
                                    <?php if (!empty($v['lot_number'])): ?>
                                        <a href="vehicle_details.php?id=<?= $v['id'] ?>"
                                            class="text-blue-600 hover:text-blue-800 hover:underline">
                                            <?= htmlspecialchars($v['lot_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars($v['vehicle_model']) ?></td>
                                <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($v['chassis_number']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                                        <?= htmlspecialchars($v['vehicle_negeri'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars($v['kod_gudang'] ?? '-') ?></td>
                                <td class="px-4 py-3"><?= date('d/m/Y', strtotime($v['import_date'])) ?></td>
                                <td class="px-4 py-3 font-bold <?= $days > 730 ? 'text-red-600' : 'text-slate-800' ?>">
                                    <?= $days ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $badgeColor ?>">
                                        <?= $label ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Data for Charts
        const categories = <?= json_encode($stats['categories']) ?>;

        // Pie Chart
        const pieOptions = {
            series: [
                categories['0-3_months'],
                categories['3-6_months'],
                categories['6-12_months'],
                categories['1-2_years'],
                categories['over_2_years']
            ],
            chart: {
                type: 'donut',
                height: 350,
                fontFamily: 'Inter, sans-serif'
            },
            labels: ['0-3 Bulan', '3-6 Bulan', '6-12 Bulan', '1-2 Tahun', '> 2 Tahun'],
            colors: ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%'
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            legend: {
                position: 'bottom'
            }
        };

        const pieChart = new ApexCharts(document.querySelector("#agingPieChart"), pieOptions);
        pieChart.render();

        // Bar Chart
        const barOptions = {
            series: [{
                name: 'Bilangan Kenderaan',
                data: [
                    categories['0-3_months'],
                    categories['3-6_months'],
                    categories['6-12_months'],
                    categories['1-2_years'],
                    categories['over_2_years']
                ]
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: false },
                fontFamily: 'Inter, sans-serif'
            },
            colors: ['#3b82f6'],
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '50%',
                    distributed: true
                }
            },
            colors: ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: ['0-3 Bln', '3-6 Bln', '6-12 Bln', '1-2 Thn', '> 2 Thn'],
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            legend: {
                show: false
            },
            grid: {
                borderColor: '#f1f5f9'
            }
        };

        const barChart = new ApexCharts(document.querySelector("#agingBarChart"), barOptions);
        barChart.render();

        // DataTable
        $(document).ready(function () {
            $('#agingTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[5, 'desc']], // Sort by Days (column index 5) descending
                language: {
                    search: "Carian:",
                    lengthMenu: "Papar _MENU_ rekod",
                    info: "Menunjukkan _START_ hingga _END_ daripada _TOTAL_ rekod",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Seterusnya",
                        previous: "Sebelumnya"
                    }
                }
            });
        });

        // Simple CSV Export Function
        function exportTableCheck() {
            // Implementation for export can be added here or via DataTable buttons
            alert("Fungsi muat turun CSV akan ditambah.");
        }
    </script>
</body>

</html>