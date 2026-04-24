<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pekema";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$result = $conn->query("SELECT COUNT(*) as cnt FROM vehicle_inventory WHERE exif_data_front IS NOT NULL AND exif_data_front != ''");
$row = $result->fetch_assoc();
echo "Records with EXIF data (front): " . $row['cnt'] . "\n";
?>
