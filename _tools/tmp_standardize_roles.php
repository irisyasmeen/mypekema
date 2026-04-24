<?php
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
include 'c:/xampp/htdocs/pekema/config.php';

echo "Standardizing roles in allowed_users...\n";
// Any role that is not one of our new valid roles should probably be 'user' for safety
$valid_roles = ["'admin'", "'user'", "'supervisor'", "'senior_officer'"];
$valid_list = implode(",", $valid_roles);

// Find users with invalid roles
$res = $conn->query("SELECT id, email, role FROM allowed_users WHERE role NOT IN ($valid_list)");
while ($row = $res->fetch_assoc()) {
    echo "Fixing {$row['email']} (role: [{$row['role']}]) -> user\n";
    $conn->query("UPDATE allowed_users SET role = 'user' WHERE id = {$row['id']}");
}

// Sync to users table too
echo "Syncing roles to users table...\n";
$conn->query("UPDATE users u JOIN allowed_users a ON u.email = a.email SET u.role = a.role");

echo "All fixed.\n";
$conn->close();
?>