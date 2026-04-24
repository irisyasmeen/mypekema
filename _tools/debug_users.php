<?php
include 'c:/xampp/htdocs/pekema/config.php';
echo "--- ALLOWED USERS ---\n";
$res1 = $conn->query("SELECT email, role FROM allowed_users");
while ($row = $res1->fetch_assoc())
    echo "{$row['email']} ({$row['role']})\n";

echo "\n--- USERS TABLE ---\n";
$res2 = $conn->query("SELECT email, role FROM users");
if ($res2) {
    while ($row = $res2->fetch_assoc())
        echo "{$row['email']} ({$row['role']})\n";
} else {
    echo "Table 'users' check failed: " . $conn->error . "\n";
}
$conn->close();
?>