<?php
include 'config.php';
$conn->query("ALTER TABLE gbpekema ADD COLUMN email VARCHAR(255) DEFAULT 'gudang@example.com'");
$conn->query("ALTER TABLE vehicle_inventory ADD COLUMN status_pergerakan VARCHAR(50) DEFAULT 'Pending'");
echo "Database updated successfully.";
?>