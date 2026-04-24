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
    <title>Analisis Mengikut Tahun</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js library for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
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
        include 'config.php';

        // --- FETCH YEARLY ANALYSIS DATA ---
        $where_clauses = ["manufacturing_year IS NOT NULL", "manufacturing_year > 0"];
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee') {
            $licensee_gb_id = (int)$_SESSION['gbpekema_id'];
            $where_clauses[] = "gbpekema_id = $licensee_gb_id";
        }
        $where_sql = implode(" AND ", $where_clauses);

        $sql = "SELECT manufacturing_year, COUNT(id) AS total_vehicles 
                FROM vehicle_inventory 
                WHERE $where_sql
                GROUP BY manufacturing_year
                ORDER BY manufacturing_year DESC";
        
        $result = $conn->query($sql);
        $analysis_data = [];
        $chart_labels = [];
        $chart_data = [];

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $analysis_data[] = $row;
                $chart_labels[] = $row['manufacturing_year'];
                $chart_data[] = $row['total_vehicles'];
            }
        }
        $conn->close();
        ?>

        <div class="container mx-auto p-4 sm:p-6 lg:p-8">
            <header class="bg-white shadow-md rounded-lg p-6 mb-8 no-print">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-700">Analisis Kenderaan Mengikut Tahun Pembuatan</h1>
                        <p class="text-gray-500 mt-1">Ringkasan jumlah kenderaan bagi setiap tahun.</p>
                    </div>
                     <div class="flex space-x-2">
                        <button onclick="exportTableToExcel('yearlyAnalysisTable', 'analisis-tahunan')" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center transition duration-300">
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
                    <h2 class="text-xl font-bold text-gray-800">Jadual Ringkasan Tahunan</h2>
                </div>
                <div class="overflow-x-auto" style="max-height: 500px;">
                    <table id="yearlyAnalysisTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky-header">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Tahun Pembuatan</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Jumlah Kenderaan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($analysis_data)): ?>
                                <?php foreach($analysis_data as $data): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($data['manufacturing_year']) ?></td>
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

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 no-print">
                <!-- Bar Chart -->
                <div class="bg-white shadow-md rounded-lg">
                     <div class="p-6 border-b">
                        <h2 class="text-xl font-bold text-gray-800">Carta Bar Tahunan</h2>
                    </div>
                    <div class="p-6">
                        <canvas id="yearlyBarChart"></canvas>
                    </div>
                </div>
                <!-- Pie Chart -->
                <div class="bg-white shadow-md rounded-lg">
                     <div class="p-6 border-b">
                        <h2 class="text-xl font-bold text-gray-800">Carta Pai Tahunan</h2>
                    </div>
                    <div class="p-6">
                        <canvas id="yearlyPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const labels = <?= json_encode($chart_labels) ?>;
        const data = <?= json_encode($chart_data) ?>;
        const backgroundColors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#F97316', '#6B7280', '#EC4899'];

        const barCtx = document.getElementById('yearlyBarChart');
        if (barCtx) {
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Kenderaan',
                        data: data,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        const pieCtx = document.getElementById('yearlyPieChart');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Kenderaan',
                        data: data,
                        backgroundColor: backgroundColors,
                        hoverOffset: 4
                    }]
                },
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
