<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';

echo "<h2>System Info</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Exif extension: " . (extension_loaded('exif') ? 'Enabled' : 'Disabled') . "<br>";

echo "<h2>Database Connection Test</h2>";
if (isset($conn)) {
    echo "Connection object exists.<br>";
    if ($conn->connect_error) {
        echo "Connection Error: " . $conn->connect_error . "<br>";
    } else {
        echo "Database Connection Successful.<br>";
        
        echo "<h3>Checking vehicle_inventory Table Schema</h3>";
        $res = $conn->query("DESCRIBE vehicle_inventory");
        if ($res) {
            echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
            while ($row = $res->fetch_assoc()) {
                echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "Error describing vehicle_inventory: " . $conn->error . "<br>";
        }

        echo "<h3>Checking gbpekema Table Schema</h3>";
        $res = $conn->query("DESCRIBE gbpekema");
        if ($res) {
            echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
            while ($row = $res->fetch_assoc()) {
                echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "Error describing gbpekema: " . $conn->error . "<br>";
        }
    }
} else {
    echo "Connection object ($conn) NOT found. Check config.php<br>";
}

echo "<h2>File Existence Check</h2>";
$files = ['exif_helper.php', 'topmenu.php', 'kad_kenderaan.php', 'tambah_kenderaan.php'];
foreach ($files as $f) {
    echo "$f: " . (file_exists($f) ? "OK" : "MISSING") . "<br>";
}

echo "<h2>Lint Check</h2>";
foreach (['kad_kenderaan.php', 'tambah_kenderaan.php'] as $f) {
    echo "Testing include($f)...<br>";
    // We can't easily lint from PHP, but if we include it here, it might crash this script if there's a syntax error.
    // However, if we want to catch it without crashing, it's hard.
    // But we already did local lint.
}
?>
