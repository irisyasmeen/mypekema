<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pekema";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$sql = "ALTER TABLE gbpekema ADD COLUMN tarikh_kuatkuasa_lesen DATE DEFAULT NULL AFTER negeri";
if ($conn->query($sql)) {
    echo "Column added successfully";
} else {
    echo "Error: " . $conn->error;
}
?>
