<?php
include 'config.php';
$result = $conn->query("DESCRIBE gbpekema");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
