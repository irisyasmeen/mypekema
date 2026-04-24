<?php
// Mock server variables for config.php
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';

include 'c:/xampp/htdocs/pekema/config.php';

$test_users = [
    ['email' => 'admin_test@customs.gov.my', 'nama' => 'Pentadbir Ujian', 'role' => 'admin'],
    ['email' => 'supervisor_test@customs.gov.my', 'nama' => 'Penyelia Ujian', 'role' => 'supervisor'],
    ['email' => 'user_test@customs.gov.my', 'nama' => 'Pengguna Ujian', 'role' => 'user'],
    ['email' => 'senior_test@customs.gov.my', 'nama' => 'Pegawai Kanan Ujian', 'role' => 'senior_officer']
];

foreach ($test_users as $user) {
    $stmt = $conn->prepare("INSERT INTO allowed_users (email, nama_pegawai, role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nama_pegawai = VALUES(nama_pegawai), role = VALUES(role)");
    $stmt->bind_param("sss", $user['email'], $user['nama'], $user['role']);
    if ($stmt->execute()) {
        echo "Berjaya: {$user['email']} ({$user['role']})\n";
    } else {
        echo "Gagal: {$user['email']} - " . $conn->error . "\n";
    }
    $stmt->close();
}
$conn->close();
?>