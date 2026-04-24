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
    <title>Analisis Usia Kenderaan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js library for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sticky-header th {
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .stat-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
        include 'config.php';

        // --- FETCH AND PROCESS AGE ANALYSIS DATA ---
        $is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
        $licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

        $where_clauses = ["tpa_date IS NOT NULL"];
        if ($is_licensee) {
            $where_clauses[] = "gbpekema_id = " . (int)$licensee_gb_id;
        }
        $where_sql = implode(" AND ", $where_clauses);

        $sql = "SELECT 
                    TIMESTAMPDIFF(YEAR, tpa_date, CURDATE()) AS vehicle_age, 
                    COUNT(id) AS total_vehicles 
                FROM vehicle_inventory 
                WHERE $where_sql
                GROUP BY vehicle_age
                ORDER BY vehicle_age ASC";
        
        $result = $conn->query($sql);
        $chart_labels = [];
        $chart_data = [];
        $normal_age_data = [];
        $over_age_data = [];
        $total_over_age_vehicles = 0;

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // For chart (all data)
                $chart_labels[] = $row['vehicle_age'] . " Tahun";
                $chart_data[] = $row['total_vehicles'];

                // Segregate data for tables
                if ($row['vehicle_age'] > 4) {
                    $over_age_data[] = $row;
                    $total_over_age_vehicles += $row['total_vehicles'];
                } else {
                    $normal_age_data[] = $row;
                }
            }
        }
        $conn->close();
        ?>

        <div class="container mx-auto p-4 sm:p-6 lg:p-8">
            <header class="bg-white shadow-md rounded-lg p-6 mb-8 no-print">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-700">Analisis Usia Kenderaan (Berdasarkan TPA)</h1>
                        <p class="text-gray-500 mt-1">Ringkasan taburan usia kenderaan yang telah dijual oleh GB</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center transition duration-300">
                            <i class="fas fa-print mr-2"></i> Cetak Jadual
                        </button>
                    </div>
                </div>
            </header>

            <!-- Over Age Card -->
            <div class="stat-card bg-gradient-to-br from-red-500 to-red-600 text-white rounded-lg shadow-lg p-6 flex items-center justify-between mb-8 no-print">
                <div>
                    <p class="text-red-200 text-sm font-semibold">Kenderaan Melebihi 4 Tahun Telah Dijual Kepada Pelanggan</p>
                    <p class="text-4xl font-bold"><?= $total_over_age_vehicles ?></p>
                </div>
                <i class="fas fa-exclamation-triangle fa-3x text-red-300 opacity-75"></i>
            </div>

            <!-- Over Age Table -->
            <?php if (!empty($over_age_data)): ?>
            <div class="bg-white shadow-md rounded-lg overflow-hidden mb-8 print-area">
                <div class="p-6 border-b bg-red-50 text-red-800 flex justify-between items-center">
                    <h2 class="text-xl font-bold">Jadual Kenderaan Melebihi 4 Tahun telah dijual kepada pelanggan</h2>
                    <button onclick="exportTableToExcel('overAgeAnalysisTable', 'analisis-usia-lebih-4-tahun')" class="no-print bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-3 rounded-lg flex items-center transition duration-300 text-sm">
                        <i class="fas fa-file-excel mr-2"></i> Eksport
                    </button>
                </div>
                <div class="overflow-x-auto" style="max-height: 300px;">
                    <table id="overAgeAnalysisTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-red-50 sticky-header">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-red-700 uppercase tracking-wider">Usia Kenderaan (Tahun)</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-red-700 uppercase tracking-wider">Jumlah Kenderaan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach($over_age_data as $data): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($data['vehicle_age']) ?> Tahun</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right font-bold"><?= $data['total_vehicles'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Normal Age Table -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden mb-8 print-area">
                <div class="p-6 border-b flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">Jadual Usia Kenderaan (4 Tahun ke Bawah)</h2>
                     <button onclick="exportTableToExcel('ageAnalysisTable', 'analisis-usia-normal')" class="no-print bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-3 rounded-lg flex items-center transition duration-300 text-sm">
                        <i class="fas fa-file-excel mr-2"></i> Eksport
                    </button>
                </div>
                <div class="overflow-x-auto" style="max-height: 500px;">
                    <table id="ageAnalysisTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky-header">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Usia Kenderaan (Tahun)</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Jumlah Kenderaan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($normal_age_data)): ?>
                                <?php foreach($normal_age_data as $data): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($data['vehicle_age']) ?> Tahun</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right font-bold"><?= $data['total_vehicles'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="px-6 py-12 text-center text-gray-500">Tiada data TPA untuk dianalisis.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bar Chart -->
            <div class="bg-white shadow-md rounded-lg no-print">
                 <div class="p-6 border-b">
                    <h2 class="text-xl font-bold text-gray-800">Carta Taburan Usia Keseluruhan</h2>
                </div>
                <div class="p-6">
                    <canvas id="ageBarChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('ageBarChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Jumlah Kenderaan',
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: '#3B82F6',
                        borderColor: '#1D4ED8',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1 // Ensure y-axis increments by whole numbers
                            }
                        } 
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
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
                let blob = new Blob(['\ufeff', tableHTML], { type: dataType });
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
