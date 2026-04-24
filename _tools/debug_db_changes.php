<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pekema";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

echo "--- TABLE: gbpekema ---\n";
$res1 = $conn->query("DESCRIBE gbpekema");
while($row = $res1->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n--- TABLE: vehicle_inventory (New/Relevant Columns) ---\n";
$res2 = $conn->query("DESCRIBE vehicle_inventory");
while($row = $res2->fetch_assoc()) {
    if (in_array($row['Field'], ['gbpekema_id', 'kod_gudang', 'exif_data_front', 'exif_data_rear', 'exif_data_left', 'exif_data_right', 'tarikh_luput', 'import_date', 'payment_date'])) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}
?>
