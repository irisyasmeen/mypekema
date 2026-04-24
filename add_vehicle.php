<?php
// --- ERROR REPORTING FOR DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END OF ERROR REPORTING ---

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
// --- DATABASE CONNECTION ---
include 'config.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    function nullIfEmpty($value) {
        return trim($value) === '' ? null : trim($value);
    }

    // DATA COLLECTION
    $lot_number = nullIfEmpty($_POST['lot_number']);
    $kod_gudang = nullIfEmpty($_POST['kod_gudang']);
    $condition_status = nullIfEmpty($_POST['condition_status']);
    $gbpekema_id = !empty($_POST['gbpekema_id']) ? intval($_POST['gbpekema_id']) : null;
    $chassis_number = nullIfEmpty($_POST['chassis_number']);
    $engine_number = nullIfEmpty($_POST['engine_number']);
    $vehicle_model = nullIfEmpty($_POST['vehicle_model']);
    $manufacturing_year = !empty($_POST['manufacturing_year']) ? intval($_POST['manufacturing_year']) : null;
    $color = nullIfEmpty($_POST['color']);
    $engine_cc = !empty($_POST['engine_cc']) ? intval($_POST['engine_cc']) : null;
    $kw = !empty($_POST['kw']) ? intval($_POST['kw']) : null;
    $tpa_date = nullIfEmpty($_POST['tpa_date']);
    $ap = nullIfEmpty($_POST['ap']);
    $tarikh_luput = nullIfEmpty($_POST['tarikh_luput']);
    $receipt_number = nullIfEmpty($_POST['receipt_number']);
    $k8_number_full = nullIfEmpty($_POST['k8_number_full']);
    $odometer8 = !empty($_POST['odometer8']) ? intval($_POST['odometer8']) : null;
    $k1_number = nullIfEmpty($_POST['k1_number']);
    $odometer1 = !empty($_POST['odometer1']) ? intval($_POST['odometer1']) : null;
    $duti_import = !empty($_POST['duti_import']) ? floatval($_POST['duti_import']) : null;
    $duti_eksais = !empty($_POST['duti_eksais']) ? floatval($_POST['duti_eksais']) : null;
    $cukai_jualan = !empty($_POST['cukai_jualan']) ? floatval($_POST['cukai_jualan']) : null;
    $duty_rm = !empty($_POST['duty_rm']) ? floatval($_POST['duty_rm']) : null;
    $catatan = nullIfEmpty($_POST['catatan']);

    // SQL PREPARATION
    $sql = "INSERT INTO vehicle_inventory (
                lot_number, kod_gudang, `condition_status`, gbpekema_id, chassis_number, engine_number, 
                vehicle_model, manufacturing_year, color, engine_cc, kw, tpa_date, ap, 
                tarikh_luput, receipt_number, k8_number_full, odometer8, k1_number, odometer1, 
                duti_import, duti_eksais, cukai_jualan, duty_rm, catatan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Failed to 'prepare' statement: " . $conn->error);
    }

    // Bind parameters with correct data types
    // sssissssisiiisssisidddds (24 characters for 24 fields)
    $stmt->bind_param(
        "sssissssisiiisssisidddds",
        $lot_number, $kod_gudang, $condition_status, $gbpekema_id, $chassis_number, $engine_number,
        $vehicle_model, $manufacturing_year, $color, $engine_cc, $kw, $tpa_date, $ap,
        $tarikh_luput, $receipt_number, $k8_number_full, $odometer8, $k1_number, $odometer1,
        $duti_import, $duti_eksais, $cukai_jualan, $duty_rm, $catatan
    );

    if ($stmt->execute()) {
        header("Location: vehicles.php?status=success&message=" . urlencode("Kenderaan baharu berjaya ditambah."));
    } else {
        if ($conn->errno == 1062) {
            header("Location: vehicles.php?status=error&message=" . urlencode("Gagal: No. Lot '$lot_number' telah wujud."));
        } else {
            die("Execution failed: " . $stmt->error);
        }
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: tambah_kenderaan.php");
    exit();
}
?>

