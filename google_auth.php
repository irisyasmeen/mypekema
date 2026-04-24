<?php
include 'config.php';
session_start();

header('Content-Type: application/json');

try {
    // Ambil input JSON dari frontend
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['email'])) {
        throw new Exception("Data tidak lengkap.");
    }

    $verified_email = $data['email'];
    $verified_name = $data['name'] ?? 'User';

    // 1. KESELAMATAN: Pastikan domain adalah @customs.gov.my
    if (substr($verified_email, -15) !== '@customs.gov.my') {
        throw new Exception("Akses ditolak! Sila gunakan e-mel rasmi @customs.gov.my.");
    }

    // 2. Semak dalam whitelist
    $stmt = $conn->prepare("SELECT role, nama_pegawai, gbpekema_id FROM " . TABLE_WHITELIST . " WHERE email = ?");
    $stmt->bind_param("s", $verified_email);
    $stmt->execute();
    $whitelist_result = $stmt->get_result();

    if ($whitelist_result->num_rows > 0) {
        $whitelist_user = $whitelist_result->fetch_assoc();
        
        // 3. Semak jika pengguna sudah wujud dalam jadual users
        $stmt_user = $conn->prepare("SELECT id, role, nama_pegawai FROM users WHERE email = ?");
        $stmt_user->bind_param("s", $verified_email);
        $stmt_user->execute();
        $user_result = $stmt_user->get_result();

        $final_user_id = null;
        $final_role = $whitelist_user['role'];
        $final_nama = $whitelist_user['nama_pegawai'] ?: $verified_name;
        $final_gb_id = $whitelist_user['gbpekema_id'];

        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            $final_user_id = $user['id'];
        } else {
            // Jika dalam whitelist tapi belum ada akaun, daftar secara automatik (SSO Trust)
            $stmt_insert = $conn->prepare("INSERT INTO users (email, nama_pegawai, role, password) VALUES (?, ?, ?, 'SSO_GOOGLE_AUTH')");
            $stmt_insert->bind_param("sss", $verified_email, $final_nama, $final_role);
            $stmt_insert->execute();
            $final_user_id = $conn->insert_id;
            $stmt_insert->close();
        }

        // Tetapkan Session
        $_SESSION['user_id'] = $final_user_id;
        $_SESSION['user_email'] = $verified_email;
        $_SESSION['nama_pegawai'] = $final_nama;
        $_SESSION['user_role'] = $final_role;
        $_SESSION['gbpekema_id'] = $final_gb_id;

        // Kemas kini tarikh log masuk terakhir
        $conn->query("UPDATE " . TABLE_WHITELIST . " SET last_login = NOW() WHERE email = '$verified_email'");
        $conn->query("UPDATE users SET last_login = NOW() WHERE id = $final_user_id");

        echo json_encode(['success' => true]);
    } else {
        // Pengguna tiada dalam senarai putih
        error_log("Akses Ditolak (Google SSO): E-mel $verified_email tiada dalam " . TABLE_WHITELIST . ".");
        echo json_encode(['success' => false, 'message' => 'E-mel anda (' . htmlspecialchars($verified_email) . ') tiada dalam senarai putih sistem. Sila hubungi pentadbir.']);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // Tangkap sebarang ralat dan kembalikan sebagai JSON
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>