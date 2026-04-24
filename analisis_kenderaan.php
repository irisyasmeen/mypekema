<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Kenderaan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js library for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .chart-bar { transition: width 0.5s ease-in-out; }
        /* CSS for sticky header */
        .sticky-header th {
            position: -webkit-sticky; /* For Safari */
            position: sticky;
            top: 0;
            z-index: 10;
        }
        @media print {
            .no-print { display: none; }
            .print-area { box-shadow: none; border: none; }
        }
    </style>
</head>
<body class="bg-gray-100">

    <?php include 'topmenu.php'; ?>

    <main>
        <?php
        // --- DATABASE CONNECTION ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';

        // --- FETCH ANALYSIS DATA ---
        $is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
        $licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

        $where_sql = "";
        if ($is_licensee) {
            $where_sql = " WHERE g.id = " . (int)$licensee_gb_id;
        }

        $sql = "SELECT g.nama, COUNT(v.id) AS total_vehicles 
                FROM gbpekema g 
                LEFT JOIN vehicle_inventory v ON g.id = v.gbpekema_id 
                $where_sql
                GROUP BY g.id, g.nama
                ORDER BY total_vehicles DESC";
        
        $result = $conn->query($sql);
        $analysis_data = [];
        $max_vehicles = 0;
        $chart_labels = [];
        $chart_data = [];

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $analysis_data[] = $row;
                $chart_labels[] = $row['nama'];
                $chart_data[] = $row['total_vehicles'];
                if ($row['total_vehicles'] > $max_vehicles) {
                    $max_vehicles = $row['total_vehicles'];
                }
            }
        }
        $conn->close();
        ?>

        <div class="container mx-auto p-4 sm:p-6 lg:p-8">
            <header class="bg-white shadow-md rounded-lg p-6 mb-8 no-print">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-700">Analisis Kenderaan Mengikut Syarikat</h1>
                        <p class="text-gray-500 mt-1">Ringkasan jumlah kenderaan bagi setiap syarikat.</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="exportTableToExcel('analysisTable', 'analisis-kenderaan')" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center transition duration-300">
                            <i class="fas fa-file-excel mr-2"></i> Eksport
                        </button>
                        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center transition duration-300">
                            <i class="fas fa-print mr-2"></i> Cetak
                        </button>
                    </div>
                </div>
            </header>

            <!-- Data Table -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden mb-8 print-area">
                <div class="p-6 border-b">
                    <h2 class="text-xl font-bold text-gray-800">Jadual Ringkasan</h2>
                </div>
                <div class="overflow-x-auto" style="max-height: 500px;">
                    <table id="analysisTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky-header">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Nama Syarikat (GB/PEKEMA)</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Jumlah Kenderaan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($analysis_data)): ?>
                                <?php foreach($analysis_data as $data): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($data['nama']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right font-bold"><?= $data['total_vehicles'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="px-6 py-12 text-center text-gray-500">Tiada data untuk dianalisis.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts (will not be printed) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 no-print">
                <!-- Bar Chart -->
                <div class="bg-white shadow-md rounded-lg">
                     <div class="p-6 border-b">
                        <h2 class="text-xl font-bold text-gray-800">Carta Bar</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <?php if (!empty($analysis_data) && $max_vehicles > 0): ?>
                            <?php foreach($analysis_data as $data): ?>
                                <?php $bar_width = ($data['total_vehicles'] / $max_vehicles) * 100; ?>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($data['nama']) ?></span>
                                        <span class="text-sm font-bold text-gray-600"><?= $data['total_vehicles'] ?></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-4">
                                        <div class="bg-blue-600 h-4 rounded-full chart-bar" style="width: <?= $bar_width ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <div class="text-center text-gray-500 py-10">Tiada data untuk dipaparkan.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Pie Chart -->
                <div class="bg-white shadow-md rounded-lg">
                     <div class="p-6 border-b">
                        <h2 class="text-xl font-bold text-gray-800">Carta Pai</h2>
                    </div>
                    <div class="p-6">
                        <canvas id="vehiclePieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('vehiclePieChart');
        if (ctx) {
            const vehicleData = {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Jumlah Kenderaan',
                    data: <?= json_encode($chart_data) ?>,
                    backgroundColor: ['#3B82F6', '#EF4444', '#F59E0B', '#10B981', '#8B5CF6', '#F97316'],
                    hoverOffset: 4
                }]
            };
            new Chart(ctx, {
                type: 'pie',
                data: vehicleData,
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        function exportTableToExcel(tableID, filename = ''){
            let downloadLink;
            let dataType = 'application/vnd.ms-excel';
            let tableSelect = document.getElementById(tableID);
            let tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            filename = filename?filename+'.xls':'excel_data.xls';
            
            downloadLink = document.createElement("a");
            
            document.body.appendChild(downloadLink);
            
            if(navigator.msSaveOrOpenBlob){
                let blob = new Blob(['\ufeff', tableHTML], {
                    type: dataType
                });
                navigator.msSaveOrOpenBlob( blob, filename);
            }else{
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
        }
    </script>

</body>
</html>
