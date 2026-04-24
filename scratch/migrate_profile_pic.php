<?php
include '../config.php';

$sql1 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) DEFAULT NULL";
$sql2 = "ALTER TABLE " . TABLE_WHITELIST . " ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) DEFAULT NULL";

if ($conn->query($sql1) === TRUE) {
    echo "Users table updated successfully\n";
} else {
    echo "Error updating users table: " . $conn->error . "\n";
}

if ($conn->query($sql2) === TRUE) {
    echo "Whitelist table updated successfully\n";
} else {
    echo "Error updating whitelist table: " . $conn->error . "\n";
}
?>
