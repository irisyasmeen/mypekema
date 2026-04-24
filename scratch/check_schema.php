<?php
include 'config.php';

$res = $conn->query("DESCRIBE " . TABLE_WHITELIST);
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
