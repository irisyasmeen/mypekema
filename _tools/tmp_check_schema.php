<?php
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
include 'c:/xampp/htdocs/pekema/config.php';

function check_table($conn, $table)
{
    echo "--- Table: $table ---\n";
    $res = $conn->query("SHOW COLUMNS FROM $table LIKE 'role'");
    $row = $res->fetch_assoc();
    print_r($row);
    echo "\n";
}

check_table($conn, 'allowed_users');
check_table($conn, 'users');

$conn->close();
?>