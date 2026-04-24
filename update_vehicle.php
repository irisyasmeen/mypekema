<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
include 'config.php';

if ($conn->connect_error) {
    header("Location: vehicles.php?status=error&message=" . urlencode("Database connection failed."));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    function nullIfEmpty($value) {
        return trim($value) === '' ? null : trim($value);
    }

    try {
        // 1. DATA COLLECTION FROM FORM
        $id = intval($_POST['id']);
        $lot_number = nullIfEmpty($_POST['lot_number']);
        $k8_number_full = nullIfEmpty($_POST['k8_number_full']);
        $k1_number = nullIfEmpty($_POST['k1_number']);
        $kod_gudang = nullIfEmpty($_POST['kod_gudang']);
        $condition_status = nullIfEmpty($_POST['condition_status']);
        $chassis_number = nullIfEmpty($_POST['chassis_number']);
        $engine_number = nullIfEmpty($_POST['engine_number']);
        $vehicle_model = nullIfEmpty($_POST['vehicle_model']);
        $color = nullIfEmpty($_POST['color']);
        $tpa_date = nullIfEmpty($_POST['tpa_date']);
        $ap = nullIfEmpty($_POST['ap']);
        $tarikh_luput = nullIfEmpty($_POST['tarikh_luput']);
        $catatan = nullIfEmpty($_POST['catatan']);
        $receipt_number = nullIfEmpty($_POST['receipt_number']);
        
        // Numeric values - handle nulls properly
        $gbpekema_id = (!empty($_POST['gbpekema_id']) && is_numeric($_POST['gbpekema_id'])) ? intval($_POST['gbpekema_id']) : null;
        $odometer8 = (!empty($_POST['odometer8']) && is_numeric($_POST['odometer8'])) ? intval($_POST['odometer8']) : null;
        $odometer1 = (!empty($_POST['odometer1']) && is_numeric($_POST['odometer1'])) ? intval($_POST['odometer1']) : null;
        $manufacturing_year = (!empty($_POST['manufacturing_year']) && is_numeric($_POST['manufacturing_year'])) ? intval($_POST['manufacturing_year']) : null;
        $engine_cc = (!empty($_POST['engine_cc']) && is_numeric($_POST['engine_cc'])) ? intval($_POST['engine_cc']) : null;
        $kw = (!empty($_POST['kw']) && is_numeric($_POST['kw'])) ? intval($_POST['kw']) : null;
        $duty_rm = (!empty($_POST['duty_rm']) && is_numeric($_POST['duty_rm'])) ? floatval($_POST['duty_rm']) : null;
        $duti_import = (!empty($_POST['duti_import']) && is_numeric($_POST['duti_import'])) ? floatval($_POST['duti_import']) : null;
        $duti_eksais = (!empty($_POST['duti_eksais']) && is_numeric($_POST['duti_eksais'])) ? floatval($_POST['duti_eksais']) : null;
        $cukai_jualan = (!empty($_POST['cukai_jualan']) && is_numeric($_POST['cukai_jualan'])) ? floatval($_POST['cukai_jualan']) : null;

        // 2. SQL PREPARATION & EXECUTION
        $sql = "UPDATE vehicle_inventory SET
                    lot_number = ?, gbpekema_id = ?, k8_number_full = ?, odometer8 = ?, k1_number = ?, odometer1 = ?,
                    kod_gudang = ?, condition_status = ?, chassis_number = ?, engine_number = ?, vehicle_model = ?,
                    manufacturing_year = ?, color = ?, engine_cc = ?, kw = ?, tpa_date = ?, ap = ?, tarikh_luput = ?,
                    duty_rm = ?, duti_import = ?, duti_eksais = ?, cukai_jualan = ?, 
                    receipt_number = ?, catatan = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Gagal prepare statement: " . $conn->error);
        }

        // Debug: Count parameters
        $param_count = substr_count($sql, '?');
        error_log("SQL parameter count: " . $param_count);

        // Bind parameters - ALL AS STRINGS for simplicity and compatibility
        // This avoids type mismatch issues with NULL values
        $stmt->bind_param(
            "sssssssssssssssssssssssss", // 25 's' for 25 parameters
            $lot_number, $gbpekema_id, $k8_number_full, $odometer8, $k1_number, $odometer1,
            $kod_gudang, $condition_status, $chassis_number, $engine_number, $vehicle_model,
            $manufacturing_year, $color, $engine_cc, $kw, $tpa_date, $ap, $tarikh_luput,
            $duty_rm, $duti_import, $duti_eksais, $cukai_jualan,
            $receipt_number, $catatan,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header("Location: vehicles.php?status=success&message=" . urlencode("Butiran kenderaan berjaya dikemas kini sepenuhnya."));
            exit();
        } else {
            throw new Exception("Gagal execute statement: " . $stmt->error);
        }

    } catch (Exception $e) {
        error_log("Update vehicle error: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if (isset($conn)) $conn->close();
        header("Location: vehicles.php?status=error&message=" . urlencode("Error: " . $e->getMessage()));
        exit();
    }

} else {
    header("Location: vehicles.php");
    exit();
}
?>