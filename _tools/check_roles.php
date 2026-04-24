<?php
include 'config.php';
$res = $conn->query("SELECT email, role FROM users");
echo "USERS TABLE:\n";
while ($r = $res->fetch_assoc())
    print_r($r);

$res = $conn->query("SELECT email, role FROM allowed_users");
echo "\nALLOWED_USERS TABLE:\n";
while ($r = $res->fetch_assoc())
    print_r($r);
?>