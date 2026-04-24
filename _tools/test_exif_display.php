<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pekema";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$exif_data = json_encode([
    'DateTimeOriginal' => '2024:03:13 09:30:00',
    'GPS' => [
        'Latitude' => 2.7433,
        'Longitude' => 101.7086,
        'MapLink' => 'https://www.google.com/maps?q=2.7433,101.7086'
    ]
]);

$sql = "UPDATE vehicle_inventory SET exif_data_front = ? WHERE id = 4203";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $exif_data);
if ($stmt->execute()) {
    echo "Dummy EXIF added to ID 4203";
} else {
    echo "Error: " . $stmt->error;
}
?>
