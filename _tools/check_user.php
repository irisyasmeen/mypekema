<?php
header('Content-Type: application/json');
include 'config.php';

$response = [
    'email_found' => false,
    'user_details' => null,
    'error' => null
];

try {
    $email = "afandi.amin@customs.gov.my";
    $stmt = $conn->prepare("SELECT id, email, nama_pegawai, role FROM allowed_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['email_found'] = true;
        $response['user_details'] = $result->fetch_assoc();
    } else {
        // Jika tiada, senaraikan e-mel yang ada untuk rujukan (hadkan ke 5 pertama)
        $others = $conn->query("SELECT email FROM allowed_users LIMIT 5");
        $all_emails = [];
        while($row = $others->fetch_assoc()) $all_emails[] = $row['email'];
        $response['existing_emails_sample'] = $all_emails;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
