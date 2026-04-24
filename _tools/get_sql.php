<?php
include 'config.php';

echo "### Table: gbpekema\n\n```sql\n";
$res = $conn->query("SHOW CREATE TABLE gbpekema");
if ($row = $res->fetch_assoc()) {
    echo $row['Create Table'] . ";\n";
}
echo "```\n\n### Table: vehicle_inventory\n\n```sql\n";
$res = $conn->query("SHOW CREATE TABLE vehicle_inventory");
if ($row = $res->fetch_assoc()) {
    echo $row['Create Table'] . ";\n";
}
echo "```\n";
?>