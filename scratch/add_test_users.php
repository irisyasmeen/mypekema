<?php
include 'config.php';

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
    
    // Check if exists
    $check = $conn->prepare("SELECT id FROM " . TABLE_WHITELIST . " WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "User $email already exists. Updating role to $role.\n";
        $update = $conn->prepare("UPDATE " . TABLE_WHITELIST . " SET role = ?, nama_pegawai = ? WHERE email = ?");
        $update->bind_param("sss", $role, $nama, $email);
        $update->execute();
    } else {
        echo "Creating user $email with role $role.\n";
        $insert = $conn->prepare("INSERT INTO " . TABLE_WHITELIST . " (email, nama_pegawai, role) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $email, $nama, $role);
        $insert->execute();
    }
}
echo "Done.\n";
?>
