<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$vehicle_id = isset($input['id']) ? intval($input['id']) : 0;

if ($vehicle_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Vehicle ID']);
    exit();
}

// 1. Get Vehicle Data
$sql = "SELECT id, kod_gudang, import_date, lot_number FROM vehicle_inventory WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result->fetch_assoc();
$stmt->close();

if (!$vehicle) {
    echo json_encode(['success' => false, 'message' => 'Kenderaan tidak dijumpai']);
    exit();
}

if (!empty($vehicle['lot_number'])) {
    // Optional: Prevent overwrite if not explicitly asked. 
    // For now, let's assume if the button is clicked, we generate (or maybe check first).
    // The UI should handle hiding the button if it exists, or provide a 'Regenerate' option.
    // We will proceed to generate and overwrite.
}

$kod_gudang = $vehicle['kod_gudang'];
if (empty($kod_gudang)) {
    echo json_encode(['success' => false, 'message' => 'Kod Gudang tidak sah. Sila kemaskini maklumat gudang terlebih dahulu.']);
    exit();
}

// Determine Date Basis (Import Date / Bond In Date)
$date_basis = $vehicle['import_date'];
if (empty($date_basis) || $date_basis == '0000-00-00') {
    // Fallback to current date if import_date is missing
    $date_basis = date('Y-m-d');
}

$year = date('Y', strtotime($date_basis));
$month = date('m', strtotime($date_basis));

// Prefix format: KOD/YEAR/MONTH/
// Example: PKG/2026/01/
$prefix = "$kod_gudang/$year/$month/";
$prefix_len = strlen($prefix);

// 2. Find last running number
// We search for lot numbers starting with this prefix
$sql_check = "SELECT lot_number FROM vehicle_inventory 
              WHERE lot_number LIKE ? 
              ORDER BY LENGTH(lot_number) DESC, lot_number DESC LIMIT 1";
$like_query = $prefix . "%";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("s", $like_query);
$stmt->execute();
$result_check = $stmt->get_result();

$next_num = 1;

if ($row = $result_check->fetch_assoc()) {
    $last_lot = $row['lot_number'];
    // Extract the running number part
    // Assuming format is PREFIX + Number
    $suffix = substr($last_lot, $prefix_len);
    
    // Check if suffix is numeric
    if (is_numeric($suffix)) {
        $next_num = intval($suffix) + 1;
    }
}
$stmt->close();

// Pad with leading zeros (e.g., 4 digits: 0001)
$running_number = str_pad($next_num, 4, '0', STR_PAD_LEFT);

$new_lot_number = $prefix . $running_number;

// 3. Update Database
$update_sql = "UPDATE vehicle_inventory SET lot_number = ? WHERE id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("si", $new_lot_number, $vehicle_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'new_lot_number' => $new_lot_number]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
