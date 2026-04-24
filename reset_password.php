<?php
// --- DATABASE CONNECTION ---
include 'config.php';

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';
$show_form = false;

if (empty($token)) {
    $message = "Token tidak sah atau tiada.";
    $message_type = 'error';
} else {
    $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (strtotime($user['reset_token_expiry']) > time()) {
            $show_form = true; // Token is valid and not expired
        } else {
            $message = "Token telah tamat tempoh. Sila minta pautan baharu.";
            $message_type = 'error';
        }
    } else {
        $message = "Token tidak sah.";
        $message_type = 'error';
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $show_form) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $message = "Kata laluan tidak sepadan.";
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = "Kata laluan mesti sekurang-kurangnya 6 aksara.";
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
        $update_stmt->bind_param("ss", $hashed_password, $token);

        if ($update_stmt->execute()) {
            $message = "Kata laluan anda telah berjaya dikemas kini! Anda kini boleh log masuk.";
            $message_type = 'success';
            $show_form = false;
        } else {
            $message = "Ralat semasa mengemas kini kata laluan.";
            $message_type = 'error';
        }
        $update_stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Semula Kata Laluan - MyPEKEMA</title>
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
            <i class="fas fa-lock text-4xl text-green-500"></i>
            <h1 class="text-3xl font-bold mt-2 text-gray-800">Cipta Kata Laluan Baharu</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="px-4 py-3 rounded relative mb-4 <?= $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>"
                role="alert">
                <span class="block sm:inline"><?= $message ?></span>
            </div>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <form action="reset_password.php?token=<?= htmlspecialchars($token) ?>" method="POST" class="space-y-6">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Kata Laluan Baharu</label>
                    <input type="password" name="password" id="password"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-3" required>
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Sahkan Kata Laluan
                        Baharu</label>
                    <input type="password" name="confirm_password" id="confirm_password"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-3" required>
                </div>
                <div>
                    <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg">Set Semula
                        Kata Laluan</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="text-center mt-6">
            <p class="text-sm text-gray-600"><a href="login.php"
                    class="font-medium text-blue-600 hover:underline">Kembali ke Log Masuk</a></p>
        </div>
    </div>
</body>

</html>