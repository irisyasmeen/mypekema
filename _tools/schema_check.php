<?php
include 'config.php';

echo "=== Tables in pekema ===\n";
$res1 = $conn->query("SHOW TABLES");
while ($row = $res1->fetch_array()) {
    echo $row[0] . "\n";
}

echo "\n=== Columns in vehicle_inventory ===\n";
$res2 = $conn->query("SHOW COLUMNS FROM vehicle_inventory");
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

$conn->close();
?>