<?php
include 'config.php';

echo "<h2>Database Structure Update</h2>";

// 1. Add 'negeri' column to gbpekema if it doesn't exist
$table = 'gbpekema';
$column = 'negeri';
$check = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");

if ($check && $check->num_rows == 0) {
    echo "<p>Column '$column' not found in table '$table'. Attempting to add...</p>";
    // Add negeri column
    $sql = "ALTER TABLE $table ADD COLUMN $column VARCHAR(50) DEFAULT NULL AFTER alamat";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green'>Successfully added column '$column' to '$table'.</p>";
    } else {
        echo "<p style='color:red'>Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue'>Column '$column' already exists in '$table'. No action needed.</p>";
}

echo "<p>Update process finished.</p>";
echo "<a href='index.php'>Go back to Dashboard</a>";
?>
