<?php
// --- ENABLE ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- DATABASE CONNECTION ---
include 'config.php';

echo "<h1>Truncate GB/PEKEMA Script</h1>";

// --- SQL TRUNCATE COMMAND ---
// This command will delete all records from the 'gbpekema' table.
$sql = "TRUNCATE TABLE gbpekema";

if ($conn->query($sql) === TRUE) {
    echo "<h2 style='color:green;'>Success!</h2>";
    echo "<p>All records have been permanently deleted from the 'gbpekema' table.</p>";
    echo "<p><strong>Important:</strong> Please delete this file ('truncate_gbpekema.php') from your server now.</p>";
} else {
    echo "<h2 style='color:red;'>Error!</h2>";
    echo "<p>Error truncating table: " . $conn->error . "</p>";
}

$conn->close();
?>
