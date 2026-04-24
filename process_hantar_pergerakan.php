<?php
session_start();
include 'config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sila log masuk terlebih dahulu.']);
    exit;
}

// RESTRICTION: Only Licensee can submit
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'licensee') {
    echo json_encode(['success' => false, 'message' => 'Hanya pelesen dibenarkan menghantar permohonan.']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$vehicle_id = isset($data['id']) ? intval($data['id']) : 0;
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

if ($vehicle_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data kenderaan tidak sah.']);
    exit;
}

try {
    // 1. Verify ownership before updating
    $verify_sql = "SELECT id, status_pergerakan FROM vehicle_inventory WHERE id = ? AND gbpekema_id = ?";
    $stmt_verify = $conn->prepare($verify_sql);
    $stmt_verify->bind_param("ii", $vehicle_id, $licensee_gb_id);
    $stmt_verify->execute();
    $result = $stmt_verify->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Kenderaan tidak dijumpai atau anda tidak mempunyai akses.");
    }
    
    $vehicle = $result->fetch_assoc();
    if (!empty($vehicle['status_pergerakan'])) {
        throw new Exception("Permohonan untuk kenderaan ini telah dihantar (" . htmlspecialchars($vehicle['status_pergerakan']) . ").");
    }
    $stmt_verify->close();

    // 2. Update Database Status
    $status = 'Pending';
    $update_sql = "UPDATE vehicle_inventory SET status_pergerakan = ? WHERE id = ? AND gbpekema_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sii", $status, $vehicle_id, $licensee_gb_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal kemaskini status di pangkalan data.");
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Permohonan berjaya dihantar untuk semakan.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
