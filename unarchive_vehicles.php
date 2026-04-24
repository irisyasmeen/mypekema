<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Only admin can unarchive
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Ralat: Anda tiada kebenaran untuk nyah-arkib.";
    $_SESSION['msg_type'] = "danger";
    header("Location: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'vehicles.php'));
    exit();
}

$success = false;
$error = "";

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Individual Unarchive
    $id = (int)$_GET['id'];
    $sql_move = "INSERT INTO vehicle_inventory (id, lot_number, k8_number_full, odometer8, vehicle_condition, import_date, bond_release_date, vehicle_model, chassis_number, engine_number, tpa_date, engine_cc, kw, manufacturing_year, color, ap, tarikh_luput, gbpekema_id, k1_number, odometer1, odometer, kod_gudang, condition_status, duty_rm, duti_import, duti_eksais, cukai_jualan, receipt_number, payment_date, catatan, vehicle_image, created_at, updated_at)
    SELECT id, lot_number, k8_number_full, odometer8, vehicle_condition, import_date, bond_release_date, vehicle_model, chassis_number, engine_number, tpa_date, engine_cc, kw, manufacturing_year, color, ap, tarikh_luput, gbpekema_id, k1_number, odometer1, odometer, kod_gudang, condition_status, duty_rm, duti_import, duti_eksais, cukai_jualan, receipt_number, payment_date, catatan, vehicle_image, created_at, updated_at 
    FROM vehicle_archive WHERE id = $id
    ON DUPLICATE KEY UPDATE vehicle_inventory.id = vehicle_inventory.id;";
    
    if ($conn->query($sql_move)) {
        $conn->query("DELETE FROM vehicle_archive WHERE id = $id;");
        $_SESSION['message'] = "Kenderaan berjaya diletakkan semula ke dalam inventori.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Ralat pangkalan data: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: arkib.php");
    exit();

} else {
    // Bulk Unarchive (All)
    $sql_move = "INSERT INTO vehicle_inventory (id, lot_number, k8_number_full, odometer8, vehicle_condition, import_date, bond_release_date, vehicle_model, chassis_number, engine_number, tpa_date, engine_cc, kw, manufacturing_year, color, ap, tarikh_luput, gbpekema_id, k1_number, odometer1, odometer, kod_gudang, condition_status, duty_rm, duti_import, duti_eksais, cukai_jualan, receipt_number, payment_date, catatan, vehicle_image, created_at, updated_at)
    SELECT id, lot_number, k8_number_full, odometer8, vehicle_condition, import_date, bond_release_date, vehicle_model, chassis_number, engine_number, tpa_date, engine_cc, kw, manufacturing_year, color, ap, tarikh_luput, gbpekema_id, k1_number, odometer1, odometer, kod_gudang, condition_status, duty_rm, duti_import, duti_eksais, cukai_jualan, receipt_number, payment_date, catatan, vehicle_image, created_at, updated_at 
    FROM vehicle_archive
    ON DUPLICATE KEY UPDATE vehicle_inventory.id = vehicle_inventory.id;";
    
    if ($conn->query($sql_move)) {
        $conn->query("DELETE FROM vehicle_archive;");
        $_SESSION['message'] = "Semua kenderaan di dalam arkib telah dikembalikan ke inventori.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Ralat pangkalan data: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: vehicles.php");
    exit();
}
