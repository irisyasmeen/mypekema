<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
include 'config.php';

if ($conn->connect_error) {
    header("Location: tambah_kenderaan.php?status=error&message=" . urlencode("Database connection failed."));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    function nullIfEmpty($value) {
        return trim($value) === '' ? null : trim($value);
    }

    // Mengambil semua data dari borang
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
    
    // Data nombor
    $gbpekema_id = !empty($_POST['gbpekema_id']) ? intval($_POST['gbpekema_id']) : null;
    $odometer8 = !empty($_POST['odometer8']) ? intval($_POST['odometer8']) : null;
    $odometer1 = !empty($_POST['odometer1']) ? intval($_POST['odometer1']) : null;
    $manufacturing_year = !empty($_POST['manufacturing_year']) ? intval($_POST['manufacturing_year']) : null;
    $engine_cc = !empty($_POST['engine_cc']) ? intval($_POST['engine_cc']) : null;
    $kw = !empty($_POST['kw']) ? intval($_POST['kw']) : null;
    $duty_rm = !empty($_POST['duty_rm']) ? floatval($_POST['duty_rm']) : null;
    $duti_import = !empty($_POST['duti_import']) ? floatval($_POST['duti_import']) : null;
    $duti_eksais = !empty($_POST['duti_eksais']) ? floatval($_POST['duti_eksais']) : null;
    $cukai_jualan = !empty($_POST['cukai_jualan']) ? floatval($_POST['cukai_jualan']) : null;

    // Query INSERT INTO untuk rekod baharu
    $sql = "INSERT INTO vehicle_inventory (
                lot_number, gbpekema_id, k8_number_full, odometer8, k1_number, odometer1,
                kod_gudang, condition_status, chassis_number, engine_number, vehicle_model,
                manufacturing_year, color, engine_cc, kw, tpa_date, ap, tarikh_luput,
                duty_rm, duti_import, duti_eksais, cukai_jualan, receipt_number, catatan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        header("Location: tambah_kenderaan.php?status=error&message=" . urlencode("Gagal 'prepare' statement: " . $conn->error));
        exit();
    }

    // bind_param kini mempunyai 24 jenis data dan 24 pembolehubah (tanpa `id`)
    $stmt->bind_param(
        "sisisisssssisiiisssddddss",
        $lot_number, $gbpekema_id, $k8_number_full, $odometer8, $k1_number, $odometer1,
        $kod_gudang, $condition_status, $chassis_number, $engine_number, $vehicle_model,
        $manufacturing_year, $color, $engine_cc, $kw, $tpa_date, $ap, $tarikh_luput,
        $duty_rm, $duti_import, $duti_eksais, $cukai_jualan,
        $receipt_number, $catatan
    );

    if ($stmt->execute()) {
        header("Location: vehicles.php?status=success&message=" . urlencode("Kenderaan baharu berjaya ditambah."));
    } else {
        // Semak jika ralat adalah kerana No. Lot atau Casis duplikat
        if ($conn->errno == 1062) {
             header("Location: tambah_kenderaan.php?status=error&message=" . urlencode("Gagal: No. Lot atau No. Casis telah wujud."));
        } else {
            header("Location: tambah_kenderaan.php?status=error&message=" . urlencode("Gagal menambah kenderaan: " . $stmt->error));
        }
    }

    $stmt->close();
    $conn->close();
} else {
    // Jika fail ini diakses secara terus, hantar balik ke senarai kenderaan
    header("Location: vehicles.php");
    exit();
}
?>