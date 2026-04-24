<?php
header('Content-Type: application/json');
include 'config.php';

$response = [
    'db_connection' => false,
    'tables' => [],
    'error' => null
];

try {
    if ($conn->connect_error) {
        throw new Exception("DB Connection Error: " . $conn->connect_error);
    }
    $response['db_connection'] = true;
    
    $result = $conn->query("SHOW TABLES");
    while($row = $result->fetch_array()) {
        $response['tables'][] = $row[0];
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
