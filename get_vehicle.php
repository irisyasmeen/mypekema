<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// --- DATABASE CONNECTION ---
include 'config.php';

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM vehicle_inventory WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $vehicle = $result->fetch_assoc();
        echo json_encode($vehicle);
    } else {
        echo json_encode(['error' => 'Vehicle not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid ID']);
}

$conn->close();
?>
