<?php
// --- ENABLE ERROR REPORTING FOR DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- DATABASE CONNECTION ---
include 'config.php';

if ($conn->connect_error) { 
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM gbpekema WHERE id = ?");
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare SQL statement: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute statement: ' . $stmt->error]);
        exit();
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Company not found']);
    }
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
}
$conn->close();
?>
