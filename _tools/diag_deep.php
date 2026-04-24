<?php
header('Content-Type: application/json');
include 'config.php';

$response = [
    'curl_enabled' => function_exists('curl_init'),
    'users_schema' => [],
    'allowed_users_schema' => [],
    'error' => null
];

try {
    $result = $conn->query("DESCRIBE users");
    while($row = $result->fetch_assoc()) {
        $response['users_schema'][] = $row;
    }

    $result2 = $conn->query("DESCRIBE allowed_users");
    while($row = $result2->fetch_assoc()) {
        $response['allowed_users_schema'][] = $row;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
