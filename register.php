<?php
// --- DATABASE CONNECTION ---
include 'config.php';

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg_email = $_POST['email'];
    $reg_password = $_POST['password'];
    $nama_pegawai = $_POST['nama_pegawai']; // Get officer's name

    if (empty($reg_email) || empty($reg_password) || empty($nama_pegawai)) {
        $error_message = "Semua medan diperlukan.";
    } elseif (substr($reg_email, -15) !== '@customs.gov.my') {
        $error_message = "Pendaftaran hanya dibenarkan menggunakan emel jabatan (@customs.gov.my).";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $reg_email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_message = "Emel ini telah didaftarkan.";
        } else {
            $hashed_password = password_hash($reg_password, PASSWORD_DEFAULT);

            $insert_stmt = $conn->prepare("INSERT INTO users (email, nama_pegawai, password) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $reg_email, $nama_pegawai, $hashed_password);

            if ($insert_stmt->execute()) {
                $success_message = "Pendaftaran berjaya! Anda kini boleh <a href='login.php' class='font-bold text-blue-600 hover:underline'>log masuk</a>.";
            } else {
                $error_message = "Ralat semasa pendaftaran.";
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MyPEKEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
        <div class="text-center mb-8">
            <i class="fas fa-warehouse text-4xl text-blue-600"></i>
            <h1 class="text-3xl font-bold mt-2 text-gray-800">Cipta Akaun</h1>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span><?= $error_message ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span><?= $success_message ?></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-6">
            <div>
                <label for="nama_pegawai" class="block text-sm font-medium text-gray-700">Nama Pegawai</label>
                <input type="text" name="nama_pegawai" id="nama_pegawai"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-3" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Emel Jabatan</label>
                <input type="email" name="email" id="email" placeholder="nama@customs.gov.my"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-3" required>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Kata Laluan</label>
                <input type="password" name="password" id="password"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-3" required>
            </div>
            <div>
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg">Daftar</button>
            </div>
        </form>
        <div class="text-center mt-6">
            <p class="text-sm text-gray-600">Sudah mempunyai akaun? <a href="login.php"
                    class="font-medium text-blue-600 hover:underline">Log masuk di sini</a></p>
        </div>
    </div>
</body>

</html>