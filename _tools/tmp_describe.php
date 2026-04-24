<?php
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
include 'c:/xampp/htdocs/pekema/config.php';

function check($conn, $table)
{
    echo "--- Table: $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    while ($row = $res->fetch_assoc()) {
        if ($row['Field'] === 'role') {
            echo "Field: {$row['Field']} | Type: {$row['Type']} | Null: {$row['Null']} | Default: {$row['Default']}\n";
        }
    }
}

check($conn, 'allowed_users');
check($conn, 'users');
$conn->close();
?>