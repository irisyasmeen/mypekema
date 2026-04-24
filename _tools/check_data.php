<?php
include 'config.php';
$res = $conn->query("SELECT id, nama, kod_gudang FROM gbpekema LIMIT 20");
echo "ID | NAMA | KOD_GUDANG\n";
echo "---|------|-----------\n";
while($row = $res->fetch_assoc()) {
    echo $row['id'] . " | " . $row['nama'] . " | [" . ($row['kod_gudang'] ?? 'NULL') . "]\n";
}
?>
