<?php
include 'config.php';

// Add 'role' column
$sql1 = "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user' AFTER nama_pegawai";
if ($conn->query($sql1) === TRUE) {
    echo "Column 'role' added successfully.\n";
} else {
    echo "Error adding 'role' column: " . $conn->error . "\n";
}

// Add 'last_login' column
$sql2 = "ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER role";
if ($conn->query($sql2) === TRUE) {
    echo "Column 'last_login' added successfully.\n";
} else {
    echo "Error adding 'last_login' column: " . $conn->error . "\n";
}

// Ensure the first user is an admin so they aren't locked out of manage_users.php
$sql3 = "UPDATE users SET role = 'admin' LIMIT 1";
if ($conn->query($sql3) === TRUE) {
    echo "Set first user as admin.\n";
} else {
    echo "Error setting admin: " . $conn->error . "\n";
}

$conn->close();
?>