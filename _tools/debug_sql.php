<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
include 'exif_helper.php';

echo "<h1>Debug tambah_kenderaan.php</h1>";
echo "Verifying Database Connection... ";
if ($conn->ping()) {
    echo "OK<br>";
} else {
    echo "FAILED: " . $conn->error . "<br>";
}

echo "Verifying SQL Statement Preparation... ";
$sql = "INSERT INTO vehicle_inventory (
    lot_number, kod_gudang, condition_status, gbpekema_id, 
    chassis_number, engine_number, vehicle_model, manufacturing_year, 
    color, engine_cc, kw, k8_number_full, odometer8, 
    k1_number, odometer1, odometer, ap, tarikh_luput, tpa_date, 
    import_date, stesen_asal, tarikh_tamat_tempoh_gudang, 
    payment_date, harga_taksiran, duti_import, duti_eksais, cukai_jualan, duty_rm,
    catatan,
    image_front, image_rear, image_left, image_right, video_file,
    exif_data_front, exif_data_rear, exif_data_left, exif_data_right
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

if ($stmt = $conn->prepare($sql)) {
    echo "OK (SQL is valid)<br>";
    $stmt->close();
} else {
    echo "FAILED: " . $conn->error . "<br>";
}

echo "Check completed.";
?>
