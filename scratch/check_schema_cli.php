<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pekema";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "vehicle_inventory schema:\n";
$res2 = $conn->query("DESCRIBE vehicle_inventory");
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
