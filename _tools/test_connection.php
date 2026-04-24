<?php
// 1. Tunjukkan semua ralat
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Maklumat sambungan (sama seperti dalam fail lain)
include 'config.php';

// 4. Periksa jika sambungan gagal
if ($conn->connect_error) {
    die("<strong>Sambungan GAGAL:</strong> " . $conn->connect_error);
}
echo "<strong>Sambungan Berjaya!</strong><br><br>";

// 5. Cuba buat satu query yang sangat mudah
echo "Mencuba query mudah...<br>";
$sql = "SELECT id, lot_number FROM vehicle_inventory LIMIT 1";
$stmt = $conn->prepare($sql);

// 6. Periksa jika query gagal disediakan
if ($stmt === false) {
    die("<strong>Query GAGAL disediakan:</strong> Ralat pada SQL atau nama jadual/lajur tidak wujud. Semak log ralat pelayan.");
}
echo "<strong>Query Berjaya Disediakan! Skrip berfungsi dengan baik.</strong>";

$stmt->close();
$conn->close();

?>