<?php
// --- DATABASE CONNECTION ---
include 'config.php';

$message = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    if (empty($email)) {
        $message = "Sila masukkan emel jabatan anda.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // User exists, generate a reset token
            $token = bin2hex(random_bytes(50));
            $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $update_stmt->bind_param("sss", $token, $expiry, $email);
            $update_stmt->execute();

            // In a real application, you would email this link.
            // For this system, we will display the link directly.
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

            $message = "Pautan set semula kata laluan telah dijana. Sila klik pautan di bawah:<br><a href='{$reset_link}' class='font-bold text-blue-600 break-all'>{$reset_link}</a>";
            $message_type = 'success';

        } else {
            $message = "Tiada akaun ditemui dengan emel tersebut.";
            $message_type = 'error';
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
    <title>Lupa Kata Laluan - MyPEKEMA</title>
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
            <i class="fas fa-key text-4xl text-yellow-500"></i>
            <h1 class="text-3xl font-bold mt-2 text-gray-800">Set Semula Kata Laluan</h1>
            <p class="text-gray-500">Masukkan emel jabatan anda untuk menerima pautan set semula.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="px-4 py-3 rounded relative mb-4 <?= $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>"
                role="alert">
                <span class="block sm:inline"><?= $message ?></span>
            </div>
        <?php endif; ?>

        <form action="lupa_password.php" method="POST" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Emel Jabatan</label>
                <input type="email" name="email" id="email"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-3" required>
            </div>
            <div>
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg">Hantar
                    Pautan</button>
            </div>
        </form>
        <div class="text-center mt-6">
            <p class="text-sm text-gray-600"><a href="login.php"
                    class="font-medium text-blue-600 hover:underline">Kembali ke Log Masuk</a></p>
        </div>
    </div>
</body>

</html>