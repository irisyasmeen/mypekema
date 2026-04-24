<?php
// --- ENABLE ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- DATABASE CONNECTION ---
include 'config.php';

echo "<h1>Vehicle Data Population Script (500 Records)</h1>";

// --- FETCH EXISTING GBPEKEMA IDs ---
$gbpekema_ids = [];
$result = $conn->query("SELECT id FROM gbpekema");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $gbpekema_ids[] = $row['id'];
    }
} else {
    die("<strong>Error:</strong> No companies found in 'gbpekema' table. Please add at least one company before running this script.");
}

// --- SAMPLE DATA ARRAYS ---
$models = [
    'TOYOTA / ALPHARD SC', 'TOYOTA / VELLFIRE ZG', 'HONDA / STEPWGN SPADA', 'TOYOTA / HARRIER',
    'MERCEDES-BENZ / C200', 'BMW / 320I', 'LEXUS / RX300', 'TOYOTA / VOXY S-Z', 'NISSAN / SERENA HIGHWAY STAR',
    'PORSCHE / CAYENNE', 'MITSUBISHI / OUTLANDER', 'SUBARU / FORESTER'
];
$colors = ['WHITE', 'BLACK', 'SILVER', 'GREY', 'RED', 'BLUE', 'GREEN', 'BROWN'];

// --- PREPARE INSERT STATEMENT ---
$stmt = $conn->prepare("INSERT INTO vehicle_inventory 
    (lot_number, k8_number_full, vehicle_condition, import_date, bond_release_date, vehicle_model, chassis_number, engine_number, tpa_date, engine_cc, manufacturing_year, color, k1_number, duty_rm, payment_date, receipt_number, gbpekema_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    die("<strong>Error preparing statement:</strong> " . $conn->error);
}

$record_count = 0;
// Change loop to generate 500 records
for ($i = 1; $i <= 500; $i++) {
    // --- GENERATE RANDOMIZED DATA ---
    $lot_number = 'WH7/2025/10/' . (1000 + $i);
    $k8_number = 'B10,D05,' . str_pad(3000 + $i, 6, '0', STR_PAD_LEFT) . ',C05,/25,' . str_pad(500 + $i, 6, '0', STR_PAD_LEFT);
    $condition = 'USED';
    $import_date = date('Y-m-d', strtotime("-".rand(5, 365)." days"));
    $bond_release_date = date('Y-m-d', strtotime($import_date . " +".rand(1, 15)." days"));
    $model = $models[array_rand($models)];
    $chassis_prefix = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);
    $chassis_number = $chassis_prefix . '-' . rand(1000000, 9999999);
    $engine_prefix = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
    $engine_number = $engine_prefix . rand(100000, 999999);
    $tpa_date = date('Y-m-d', strtotime("-".rand(365, 3000)." days"));
    $engine_cc = rand(15, 40) * 100; // 1500cc to 4000cc
    $year = date('Y', strtotime($tpa_date));
    $color = $colors[array_rand($colors)];
    $k1_number = '10800' . rand(1000, 9999) . '/25';
    $duty = rand(20000, 150000) + (rand(0, 99) / 100);
    $payment_date = date('Y-m-d', strtotime($import_date . " +".rand(1, 5)." days"));
    $receipt_number = rand(10000, 19999) . ' IM';
    $gbpekema_id = $gbpekema_ids[array_rand($gbpekema_ids)];

    // Bind parameters and execute
    $stmt->bind_param("sssssssssisssdssi",
        $lot_number, $k8_number, $condition, $import_date, $bond_release_date, $model, $chassis_number,
        $engine_number, $tpa_date, $engine_cc, $year, $color, $k1_number, $duty,
        $payment_date, $receipt_number, $gbpekema_id
    );

    if ($stmt->execute()) {
        $record_count++;
    } else {
        echo "<p style='color:red;'>Failed to insert record " . $i . ": " . $stmt->error . "</p>";
    }
}

echo "<h2>Population Complete!</h2>";
echo "<p style='color:green; font-weight:bold;'>" . $record_count . " sample records were successfully inserted into the 'vehicle_inventory' table.</p>";
echo "<p>You can now delete this file ('populate_vehicles.php').</p>";

$stmt->close();
$conn->close();
?>
