<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Ensure the archive table exists
$sql_create = "CREATE TABLE IF NOT EXISTS vehicle_archive LIKE vehicle_inventory;";
$conn->query($sql_create);

// Optionally add archived_at to it if it doesn't have one (ignore error if it already exists)
try {
    $conn->query("ALTER TABLE vehicle_archive ADD COLUMN archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;");
} catch (mysqli_sql_exception $e) {
    // Ignore duplicate column error
}

// Find all items with payment and move them
$sql_move = "INSERT INTO vehicle_archive (id, lot_number, k8_number_full, odometer8, vehicle_condition, import_date, bond_release_date, vehicle_model, chassis_number, engine_number, tpa_date, engine_cc, kw, manufacturing_year, color, ap, tarikh_luput, gbpekema_id, k1_number, odometer1, odometer, kod_gudang, condition_status, duty_rm, duti_import, duti_eksais, cukai_jualan, receipt_number, payment_date, catatan, vehicle_image, created_at, updated_at)
SELECT id, lot_number, k8_number_full, odometer8, vehicle_condition, import_date, bond_release_date, vehicle_model, chassis_number, engine_number, tpa_date, engine_cc, kw, manufacturing_year, color, ap, tarikh_luput, gbpekema_id, k1_number, odometer1, odometer, kod_gudang, condition_status, duty_rm, duti_import, duti_eksais, cukai_jualan, receipt_number, payment_date, catatan, vehicle_image, created_at, updated_at 
FROM vehicle_inventory 
WHERE (duty_rm IS NOT NULL AND duty_rm > 0) OR receipt_number IS NOT NULL OR payment_date IS NOT NULL
ON DUPLICATE KEY UPDATE vehicle_archive.id = vehicle_archive.id;";

if ($conn->query($sql_move)) {
    // Delete them from inventory
    $sql_delete = "DELETE FROM vehicle_inventory WHERE (duty_rm IS NOT NULL AND duty_rm > 0) OR receipt_number IS NOT NULL OR payment_date IS NOT NULL;";
    $conn->query($sql_delete);
    
    $_SESSION['message'] = "Data kenderaan yang telah dibayar cukai berjaya dipindahkan ke arkib.";
    $_SESSION['msg_type'] = "success";
} else {
    $_SESSION['message'] = "Ralat: " . $conn->error;
    $_SESSION['msg_type'] = "danger";
}

header("Location: vehicles.php");
exit();
?>
