<?php
// --- ENABLE ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- DATABASE CONNECTION ---
include 'config.php';

echo "<h1>Truncate Vehicle Inventory Script</h1>";

// --- SQL TRUNCATE COMMAND ---
// This command is faster than DELETE and resets the auto-increment counter.
$sql = "TRUNCATE TABLE vehicle_inventory";

if ($conn->query($sql) === TRUE) {
    echo "<h2 style='color:green;'>Success!</h2>";
    echo "<p>All records have been permanently deleted from the 'vehicle_inventory' table.</p>";
    echo "<p><strong>Important:</strong> Please delete this file ('truncate_vehicles.php') from your server now.</p>";
} else {
    echo "<h2 style='color:red;'>Error!</h2>";
    echo "<p>Error truncating table: " . $conn->error . "</p>";
}

$conn->close();
?>
