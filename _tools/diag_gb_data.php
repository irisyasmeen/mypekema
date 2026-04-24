<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

echo "<h2>GB Pekema Data Dump</h2>";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT id, nama, kod_gudang FROM gbpekema ORDER BY nama ASC";
$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'><tr><th>ID</th><th>Nama</th><th>Kod Gudang</th></tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>" . htmlspecialchars($row['nama']) . "</td><td>" . htmlspecialchars($row['kod_gudang'] ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "0 results found in gbpekema table.";
    }
} else {
    echo "Error: " . $conn->error;
}
?>
