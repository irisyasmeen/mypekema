<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed with error code " . $file['error']);
    }

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        die("Invalid file type. Only JPG, PNG and WEBP are allowed.");
    }

    // Create uploads/profile_pics directory if it doesn't exist
    $upload_dir = 'uploads/profile_pics/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate a unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = md5($user_email . time()) . '.' . $extension;
    $target_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Update database
        $sql = "UPDATE users SET profile_pic = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $target_path, $user_email);
        $stmt->execute();

        $sql_wl = "UPDATE " . TABLE_WHITELIST . " SET profile_pic = ? WHERE email = ?";
        $stmt_wl = $conn->prepare($sql_wl);
        $stmt_wl->bind_param("ss", $target_path, $user_email);
        $stmt_wl->execute();

        // Update session
        $_SESSION['profile_pic'] = $target_path;

        header("Location: settings.php?upload=success");
    } else {
        die("Failed to move uploaded file.");
    }
} else {
    header("Location: settings.php");
}
?>
