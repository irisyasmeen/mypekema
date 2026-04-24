<?php
include '../config.php';

$password_plain = 'Customs123!';
$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

$users = [
    [
        'email' => 'gudang@customs.gov.my',
        'nama_pegawai' => 'Pegawai Gudang',
        'role' => 'supervisor'
    ],
    [
        'email' => 'kastam_capture@customs.gov.my',
        'nama_pegawai' => 'Pegawai Kastam (Capture)',
        'role' => 'user'
    ],
    [
        'email' => 'penaksir@customs.gov.my',
        'nama_pegawai' => 'Pegawai Penaksir',
        'role' => 'supervisor'
    ]
];

foreach ($users as $u) {
    $email = $u['email'];
    $nama = $u['nama_pegawai'];
    $role = $u['role'];
    
    // 1. Update/Insert into TABLE_WHITELIST
    $check_wl = $conn->prepare("SELECT id FROM " . TABLE_WHITELIST . " WHERE email = ?");
    $check_wl->bind_param("s", $email);
    $check_wl->execute();
    if ($check_wl->get_result()->num_rows > 0) {
        $update_wl = $conn->prepare("UPDATE " . TABLE_WHITELIST . " SET role = ?, nama_pegawai = ? WHERE email = ?");
        $update_wl->bind_param("sss", $role, $nama, $email);
        $update_wl->execute();
    } else {
        $insert_wl = $conn->prepare("INSERT INTO " . TABLE_WHITELIST . " (email, nama_pegawai, role) VALUES (?, ?, ?)");
        $insert_wl->bind_param("sss", $email, $nama, $role);
        $insert_wl->execute();
    }

    // 2. Update/Insert into users table (for manual login)
    $check_u = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_u->bind_param("s", $email);
    $check_u->execute();
    if ($check_u->get_result()->num_rows > 0) {
        $update_u = $conn->prepare("UPDATE users SET password = ?, role = ?, nama_pegawai = ? WHERE email = ?");
        $update_u->bind_param("ssss", $password_hashed, $role, $nama, $email);
        $update_u->execute();
    } else {
        $insert_u = $conn->prepare("INSERT INTO users (email, nama_pegawai, role, password) VALUES (?, ?, ?, ?)");
        $insert_u->bind_param("ssss", $email, $nama, $role, $password_hashed);
        $insert_u->execute();
    }
    
    echo "Updated/Created: $email (Password: $password_plain)\n";
}
echo "Done.\n";
?>
