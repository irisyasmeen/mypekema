<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // If not logged in, you can choose to do nothing or send an error
    exit('Sila log masuk untuk memuat turun laporan.');
}

// --- DATABASE CONNECTION ---
include 'config.php';

// --- FILTERING LOGIC (Same as report.php) ---
$gbpekema_id_filter = $_GET['gbpekema_id'] ?? null;
$start_date_filter = $_GET['start_date'] ?? null;
$end_date_filter = $_GET['end_date'] ?? null;

$sql = "SELECT g.nama as gbpekema_nama, v.import_date, v.lot_number, v.vehicle_model, v.chassis_number 
        FROM vehicle_inventory v 
        LEFT JOIN gbpekema g ON v.gbpekema_id = g.id";

$conditions = [];
$params = [];
$types = '';

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee') {
    $conditions[] = "v.gbpekema_id = ?";
    $params[] = (int)$_SESSION['gbpekema_id'];
    $types .= 'i';
} else if (!empty($gbpekema_id_filter) && $gbpekema_id_filter != 'all') {
    $conditions[] = "v.gbpekema_id = ?";
    $params[] = $gbpekema_id_filter;
    $types .= 'i';
}
if (!empty($start_date_filter)) {
    $conditions[] = "v.import_date >= ?";
    $params[] = $start_date_filter;
    $types .= 's';
}
if (!empty($end_date_filter)) {
    $conditions[] = "v.import_date <= ?";
    $params[] = $end_date_filter;
    $types .= 's';
}

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY v.import_date DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// --- CSV GENERATION ---
$filename = "laporan_mypekema_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Add header row
fputcsv($output, ['Syarikat GB/PEKEMA', 'Tarikh Import', 'No. Lot', 'Model Kenderaan', 'No. Casis']);

// Add data rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['gbpekema_nama'] ?? 'N/A',
            $row['import_date'],
            $row['lot_number'],
            $row['vehicle_model'],
            $row['chassis_number']
        ]);
    }
}

fclose($output);
$stmt->close();
$conn->close();
exit();
?>