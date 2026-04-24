<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pekema";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$result = $conn->query("SELECT id, image_front, exif_data_front FROM vehicle_inventory ORDER BY id DESC LIMIT 5");
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Image: " . $row['image_front'] . "\n";
    echo "EXIF: " . ($row['exif_data_front'] ?: "NULL") . "\n";
    echo "-------------------\n";
}
?>
