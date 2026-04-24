<?php
include '../config.php';

$tables = ['allowed_list', 'users'];
foreach ($tables as $t) {
    echo "Schema for table: $t\n";
    $res = $conn->query("DESCRIBE $t");
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
    echo "\n";
}
?>
