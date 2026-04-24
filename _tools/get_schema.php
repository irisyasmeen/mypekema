<?php
include 'config.php';
$res = $conn->query("DESCRIBE gbpekema");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "----\n";
$res2 = $conn->query("DESCRIBE vehicle_inventory");
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>