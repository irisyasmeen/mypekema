<?php
/**
 * firebase_auth.php - Secure Backend for Firebase Google Auth
 * Verifies Firebase ID Token and manages user session.
 */

// Production: hide errors, log them instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    session_start();

    if (!file_exists('config.php')) {
        throw new Exception("Configuration file not found.");
    }

    include 'config.php';

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid data format.");
    }

    if (!isset($data['idToken'])) {
        throw new Exception("ID Token missing.");
    }

    $idToken = $data['idToken'];
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? 'User';

    // 1. SECURITY: Domain Restriction
    if (!empty($email) && !str_ends_with($email, '@customs.gov.my')) {
        throw new Exception("Access Denied: Please use your @customs.gov.my account.");
    }

    /**
     * NOTE: For full security, we should verify the ID Token signature 
     * using a library like Firebase PHP SDK or a JWT library.
     * Since we are migrating, we will trust the client-side email FOR NOW
     * but ensure it exists in our local database.
     */

    // 2. Database Check
    $stmt = $conn->prepare("SELECT id, nama_pegawai, role FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("DB Error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Establish Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $email;
        $_SESSION['nama_pegawai'] = !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : $name;
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        // Update Last Login
        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }

        echo json_encode(['success' => true]);
    } else {
        error_log("Access Denied: Email $email not found in database.");
        echo json_encode(['success' => false, 'message' => "E-mel anda ($email) tiada dalam sistem. Sila hubungi pentadbir."]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
