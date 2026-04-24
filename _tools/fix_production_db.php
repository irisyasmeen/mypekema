<?php
header('Content-Type: application/json');
include 'config.php';

$response = [
    'success' => false,
    'steps' => [],
    'error' => null
];

try {
    // 1. Add 'role' to 'users' table if it doesn't exist
    $check_role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($check_role->num_rows == 0) {
        if ($conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'user' AFTER nama_pegawai")) {
            $response['steps'][] = "Added 'role' column to 'users' table.";
        }
    } else {
        $response['steps'][] = "'role' column already exists in 'users'.";
    }

    // 2. Add 'last_login' to 'users' table if it doesn't exist
    $check_last_login = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($check_last_login->num_rows == 0) {
        if ($conn->query("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL AFTER role")) {
            $response['steps'][] = "Added 'last_login' column to 'users' table.";
        }
    } else {
        $response['steps'][] = "'last_login' column already exists in 'users'.";
    }

    // 3. Optional: make 'password' nullable since we use Google Auth primarily
    $conn->query("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL");
    $response['steps'][] = "Modified 'password' column to be NULLABLE.";

    $response['success'] = true;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
