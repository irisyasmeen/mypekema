<?php
// --- DATABASE CONNECTION ---
// --- DATABASE CONNECTION ---
include 'config.php';


// Proceed only if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Prepare an INSERT statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO gbpekema (nama, alamat, pic, no_tel) VALUES (?, ?, ?, ?)");
    
    // Sanitize input data, defaulting to an empty string if not set
    $nama = $_POST['nama'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $pic = $_POST['pic'] ?? '';
    $no_tel = $_POST['no_tel'] ?? '';

    // Bind the sanitized variables to the prepared statement
    $stmt->bind_param("ssss", $nama, $alamat, $pic, $no_tel);
    
    // Execute the statement and redirect on success, or show error on failure
    if ($stmt->execute()) {
        header("Location: gbpekema.php?status=success");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    
    // Close the statement
    $stmt->close();
}

// Close the database connection
$conn->close();
?>
