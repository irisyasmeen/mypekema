<?php
// Simple script to test the DB structure is sound and ready
include 'config.php';

$sql = "SELECT * FROM vehicle_inventory ORDER BY id DESC LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    print_r($res->fetch_assoc());
} else {
    echo "No vehicles or query failed. " . $conn->error;
}
?>