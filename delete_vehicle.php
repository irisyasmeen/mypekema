<?php
session_start();
include 'config.php'; // Pastikan laluan ini betul

// Keselamatan: Pastikan hanya pengguna yang log masuk boleh memadam
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// RESTRICTION: Only Admin can delete
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: vehicles.php");
    exit();
}

// Dapatkan ID daripada URL (kaedah GET)
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Teruskan hanya jika ID yang sah diberikan
if ($id > 0) {
    // Sediakan kenyataan DELETE untuk mengelakkan SQL injection
    $stmt = $conn->prepare("DELETE FROM vehicle_inventory WHERE id = ?");

    // Periksa jika penyediaan kenyataan berjaya
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    // Ikat ID pada kenyataan yang disediakan
    $stmt->bind_param("i", $id);

    // Laksanakan kenyataan dan halakan semula jika berjaya, atau tunjukkan ralat jika gagal
    if ($stmt->execute()) {
        // Hantar pengguna kembali ke senarai kenderaan dengan mesej kejayaan
        header("Location: vehicles.php?status=deleted");
        exit();
    } else {
        echo "Ralat semasa memadam rekod: " . $stmt->error;
    }

    // Tutup kenyataan
    $stmt->close();
} else {
    // Jika tiada ID yang sah diberikan
    die("ID Kenderaan tidak sah.");
}

// Tutup sambungan pangkalan data
$conn->close();
?>