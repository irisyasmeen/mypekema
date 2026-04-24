<?php
include 'config.php';

echo "<div style='font-family: sans-serif; padding: 20px; line-height: 1.6;'>";
echo "<h2>MyPEKEMA Database Repair Tool</h2>";

// 1. Diagnostics: Show current columns
echo "<h3>1. Checking current columns in 'allowed_users'...</h3>";
$res = $conn->query("SHOW COLUMNS FROM allowed_users");
$columns = [];
while ($row = $res->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo "<p>Current columns: " . implode(', ', $columns) . "</p>";

// 2. Repair: Add column if missing
if (!in_array('gbpekema_id', $columns)) {
    echo "<h3>2. Column 'gbpekema_id' is missing. Repairing...</h3>";
    $sql = "ALTER TABLE allowed_users ADD COLUMN gbpekema_id INT NULL AFTER role";
    if ($conn->query($sql)) {
        echo "<p style='color: green; font-weight: bold;'>SUCCESS: Column 'gbpekema_id' has been added!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>ERROR: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue; font-weight: bold;'>INFO: Column 'gbpekema_id' already exists. No repair needed.</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Go to Dashboard</a> | <a href='login.php'>Go to Login</a></p>";
echo "</div>";

$conn->close();
?>
