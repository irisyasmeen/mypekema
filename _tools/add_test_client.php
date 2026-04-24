<?php
include 'config.php';

$email = 'test_licensee@customs.gov.my';
$nama = 'Syarikat Test Licensee';
$role = 'licensee';

$stmt = $conn->prepare("INSERT INTO allowed_users (email, nama_pegawai, role) VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE nama_pegawai = VALUES(nama_pegawai), role = VALUES(role)");
$stmt->bind_param("sss", $email, $nama, $role);

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h2>MyPEKEMA Test Account Creator</h2>";

if ($stmt->execute()) {
    echo "<p style='color: green;'><strong>SUCCESS:</strong> Test licensee user added/updated!</p>";
    echo "<ul>";
    echo "<li><strong>E-mel:</strong> $email</li>";
    echo "<li><strong>Nama:</strong> $nama</li>";
    echo "<li><strong>Peranan:</strong> $role</li>";
    echo "</ul>";
    echo "<p><a href='login.php'>Kembali ke Log Masuk</a></p>";
} else {
    echo "<p style='color: red;'><strong>ERROR:</strong> " . $stmt->error . "</p>";
}
echo "</div>";

$stmt->close();
$conn->close();
?>
