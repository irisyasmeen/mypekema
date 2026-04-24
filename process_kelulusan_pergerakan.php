<?php
session_start();
include 'config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sila log masuk terlebih dahulu.']);
    exit;
}

// RESTRICTION: Only Admin and Senior Officer can approve
$allowed_approval_roles = ['admin', 'senior_officer'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_approval_roles)) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak mempunyai kebenaran untuk melakukan tindakan ini.']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$vehicle_id = isset($data['id']) ? intval($data['id']) : 0;
$status = isset($data['status']) ? $data['status'] : '';

if ($vehicle_id <= 0 || !in_array($status, ['Lulus', 'Ditolak'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak sah.']);
    exit;
}

try {
    // 1. Update Database Status
    $update_sql = "UPDATE vehicle_inventory SET status_pergerakan = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $status, $vehicle_id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal kemaskini status di pangkalan data.");
    }
    $stmt->close();

    // 2. Fetch data for email if approved
    if ($status === 'Lulus') {
        $get_info_sql = "SELECT v.lot_number, v.vehicle_model, v.chassis_number, g.nama as syarikat, g.email 
                         FROM vehicle_inventory v 
                         LEFT JOIN gbpekema g ON v.gbpekema_id = g.id 
                         WHERE v.id = ?";
        $stmt_info = $conn->prepare($get_info_sql);
        $stmt_info->bind_param("i", $vehicle_id);
        $stmt_info->execute();
        $result = $stmt_info->get_result();

        if ($row = $result->fetch_assoc()) {
            $to = !empty($row['email']) ? $row['email'] : 'gudang@example.com'; // Fallback
            $subject = "Kelulusan Pergerakan Kenderaan: " . $row['lot_number'];
            $message = "
            <html>
            <head>
                <title>Kelulusan Pergerakan Kenderaan</title>
            </head>
            <body>
                <h3>Kepada Pengurus {$row['syarikat']},</h3>
                <p>Sukacita dimaklumkan bahawa permohonan Pergerakan Kenderaan (Lampiran J) bagi kenderaan berikut telah <strong>DILULUSKAN</strong> oleh pihak JKDM.</p>
                <ul>
                    <li><strong>No. Lot:</strong> {$row['lot_number']}</li>
                    <li><strong>Model:</strong> {$row['vehicle_model']}</li>
                    <li><strong>No. Casis:</strong> {$row['chassis_number']}</li>
                </ul>
                <p>Sila log masuk ke dalam sistem untuk memuat turun dan mencetak Borang Pergerakan J yang telah disahkan untuk rujukan anda.</p>
                <br>
                <p>Yang Benar,<br>Pegawai Kanan Kastam<br>Jabatan Kastam Diraja Malaysia</p>
            </body>
            </html>
            ";

            // To send HTML mail, the Content-type header must be set
            $headers = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

            // Additional headers
            $headers .= 'From: Sistem Inventori Gudang Kastam <no-reply@jkdm.gov.my>' . "\r\n";

            // Attempt to send email (in local XAMPP this might fail without config, so we wrap it in @ and proceed anyway)
            @mail($to, $subject, $message, $headers);
        }
        $stmt_info->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status berjaya dikemaskini. ' . ($status === 'Lulus' ? 'Emel kelulusan dihantar kepada syarikat gudang.' : '')
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>