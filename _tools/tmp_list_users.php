<?php
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
include 'c:/xampp/htdocs/pekema/config.php';

echo "--- ALLOWED_USERS ---\n";
$res1 = $conn->query("SELECT email, role FROM allowed_users");
while ($row = $res1->fetch_assoc()) {
    echo "{$row['email']} -> {$row['role']}\n";
}

echo "\n--- USERS ---\n";
$res2 = $conn->query("SELECT email, role FROM users");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo "{$row['email']} -> {$row['role']}\n";
    }
} else {
    echo "Error checking users table: " . $conn->error . "\n";
}
$conn->close();
?>