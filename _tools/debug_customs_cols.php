<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pekema";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$fields_to_check = [
    'stesen_asal', 'tarikh_tamat_tempoh_gudang', 'harga_taksiran', 
    'duti_import', 'duti_eksais', 'cukai_jualan', 'duty_rm', 'payment_date'
];

echo "--- CHECKING CUSTOMS COLUMNS IN vehicle_inventory ---\n";
$res = $conn->query("DESCRIBE vehicle_inventory");
while($row = $res->fetch_assoc()) {
    if (in_array($row['Field'], $fields_to_check)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}
?>
