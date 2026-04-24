<?php
include 'config.php';
$res = $conn->query("SELECT DISTINCT role FROM users_auth");
while ($r = $res->fetch_row()) {
    echo $r[0] . "\n";
}
?>