<?php
include 'config.php';
$res = $conn->query("DESCRIBE users");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Table 'users' may not exist: " . $conn->error;
}
?>