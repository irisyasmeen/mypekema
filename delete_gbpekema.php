<?php
// --- DATABASE CONNECTION ---
include 'config.php';

// Check for connection errors
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// Proceed only if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get the ID from the form, ensuring it is an integer
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Proceed only if a valid ID is provided
    if ($id > 0) {
        // Prepare a DELETE statement to prevent SQL injection
        $stmt = $conn->prepare("DELETE FROM gbpekema WHERE id = ?");
        
        // Bind the ID to the prepared statement
        $stmt->bind_param("i", $id);
        
        // Execute the statement and redirect on success, or show error on failure
        if ($stmt->execute()) {
            header("Location: gbpekema.php?status=deleted");
            exit();
        } else {
            echo "Error deleting record: " . $stmt->error;
        }
        
        // Close the statement
        $stmt->close();
    }
}

// Close the database connection
$conn->close();
?>
