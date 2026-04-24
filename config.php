<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- ENVIRONMENT DETECTION & CONFIGURATION ---
$is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');

if ($is_localhost) {
    // --- LOCALHOST CONFIGURATION ---
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "pekema";
    $google_client_id = "530053025560-rgghabhndd4epnojae7l3n7fb9lc5si4.apps.googleusercontent.com";
} else {
    // --- PRODUCTION CONFIGURATION ---
    $servername = "localhost";
    $username = "kliacust_iris";
    $password = "Iris6102009@#";
    $dbname = "kliacust_gudang";
    $google_client_id = "530053025560-8am0iiemj6psh5m8mulfjfr69v8abi2v.apps.googleusercontent.com";
}

// --- CREATE AND CHECK THE CONNECTION ---
try {
    // Create a new mysqli object to establish the connection.
    // The 'new mysqli()' constructor attempts to connect to the MySQL server.
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check if there was a connection error.
    // The connect_error property will contain an error message if the connection failed.
    if ($conn->connect_error) {
        // If an error occurred, throw an exception to be caught by the catch block.
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // --- AUTO-DETECT WHITELIST TABLE ---
    // This helps transition between 'allowed_list' (new) and 'allowed_users' (legacy/corrupted)
    $whitelist_table = 'allowed_list';
    $check_list = $conn->query("SHOW TABLES LIKE 'allowed_list'");
    if (!$check_list || $check_list->num_rows == 0) {
        $whitelist_table = 'allowed_users';
    }
    // Define as constant for global access
    define('TABLE_WHITELIST', $whitelist_table);

} catch (Exception $e) {
    // If any exception is caught during the connection attempt,
    // display the error message and stop the script.
    // For a production environment, you might want to log this error instead of displaying it.
    die("Database Connection Error: " . $e->getMessage());
}

// --- (Optional) CLOSE THE CONNECTION ---
// While PHP automatically closes the connection when the script ends,
// it's good practice to close it manually if the script is long
// or does a lot of work after the database operations are complete.
// $conn->close();

?>
