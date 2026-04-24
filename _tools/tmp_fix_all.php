<?php
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
include 'c:/xampp/htdocs/pekema/config.php';

// 1. ALTER TABLE to support new roles
echo "Altering allowed_users...\n";
$conn->query("ALTER TABLE allowed_users MODIFY COLUMN role VARCHAR(50) DEFAULT 'user'");
echo "Altering users...\n";
$conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) DEFAULT 'user'");

// 2. Clear and Re-add Test Users
echo "Clearing test users...\n";
$conn->query("DELETE FROM allowed_users WHERE email LIKE '%_test@customs.gov.my'");

$test_users = [
    ['email' => 'admin_test@customs.gov.my', 'nama' => 'Pentadbir Ujian', 'role' => 'admin'],
    ['email' => 'supervisor_test@customs.gov.my', 'nama' => 'Penyelia Ujian', 'role' => 'supervisor'],
    ['email' => 'user_test@customs.gov.my', 'nama' => 'Pengguna Ujian', 'role' => 'user'],
    ['email' => 'senior_test@customs.gov.my', 'nama' => 'Pegawai Kanan Ujian', 'role' => 'senior_officer']
];

foreach ($test_users as $user) {
    $stmt = $conn->prepare("INSERT INTO allowed_users (email, nama_pegawai, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user['email'], $user['nama'], $user['role']);
    if ($stmt->execute()) {
        echo "Berjaya: {$user['email']} ({$user['role']})\n";
    } else {
        echo "Gagal: {$user['email']} - " . $conn->error . "\n";
    }
    $stmt->close();
}

// 3. Check for any existing user whose role might have been corrupted
echo "\nChecking existing users for corrupted roles...\n";
$res = $conn->query("SELECT email, role FROM allowed_users");
while ($row = $res->fetch_assoc()) {
    echo "{$row['email']} -> [{$row['role']}]\n";
}

$conn->close();
?>