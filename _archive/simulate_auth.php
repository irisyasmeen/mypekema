<?php
session_start();
include __DIR__ . '/../config.php';

// ONLY allow simulation on localhost for security
// $is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');
// if (!$is_localhost) {
//     die("Akses simulasi hanya dibenarkan di persekitaran localhost.");
// }

if (isset($_GET['email'])) {
    $email = $_GET['email'];

    // Check if user exists in whitelist
    $stmt = $conn->prepare("SELECT * FROM " . TABLE_WHITELIST . " WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($user = $res->fetch_assoc()) {
        // Sync with users table
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $res_users = $check_stmt->get_result();

        $user_id = null;
        if ($res_users->num_rows == 0) {
            $insert_stmt = $conn->prepare("INSERT INTO users (email, nama_pegawai, role, created_at) VALUES (?, ?, ?, NOW())");
            $insert_stmt->bind_param("sss", $email, $user['nama_pegawai'], $user['role']);
            $insert_stmt->execute();
            $user_id = $conn->insert_id;
        } else {
            $user_row = $res_users->fetch_assoc();
            $user_id = $user_row['id'];
        }

        // Set Session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['nama_pegawai'] = $user['nama_pegawai'];
        $_SESSION['user_role'] = $user['role'];

        // Update last login
        $conn->query("UPDATE " . TABLE_WHITELIST . " SET last_login = NOW() WHERE email = '$email'");
        $conn->query("UPDATE users SET last_login = NOW() WHERE id = $user_id");

        header("Location: ../index.php");
        exit();
    } else {
        die("Pengguna tidak ditemui dalam whitelist.");
    }
}
?>