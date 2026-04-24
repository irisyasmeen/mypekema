<?php
// --- DATABASE CONNECTION ---
include 'config.php';

// Check for connection errors
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// Proceed only if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Prepare an UPDATE statement to prevent SQL injection
    $stmt = $conn->prepare("UPDATE gbpekema SET nama=?, alamat=?, pic=?, no_tel=? WHERE id=?");
    
    // Sanitize input data, defaulting to an empty string if not set
    $nama = $_POST['nama'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $pic = $_POST['pic'] ?? '';
    $no_tel = $_POST['no_tel'] ?? '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Bind the sanitized variables to the prepared statement
    $stmt->bind_param("ssssi", $nama, $alamat, $pic, $no_tel, $id);

    // Execute the statement and redirect on success, or show error on failure
    if ($stmt->execute()) {
        header("Location: gbpekema.php?status=updated");
        exit();
    } else {
        echo "Error updating record: " . $stmt->error;
    }
    
    // Close the statement
    $stmt->close();
}

// Close the database connection
$conn->close();
?>
