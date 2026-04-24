<?php
include 'config.php';
$sql = "ALTER TABLE gbpekema ADD COLUMN tarikh_kuatkuasa_lesen DATE DEFAULT NULL AFTER negeri";
if ($conn->query($sql)) {
    echo "Column added successfully";
} else {
    echo "Error: " . $conn->error;
}
?>
