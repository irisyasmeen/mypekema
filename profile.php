<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
include 'config.php';

// Fetch current user's data
$user_id = $_SESSION['user_id'];
$sql = "SELECT nama_pegawai, email, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Check if user is admin in whitelist
$is_admin = false;
$role_check = $conn->prepare("SELECT role FROM " . TABLE_WHITELIST . " WHERE email = ?");
if ($role_check) {
    if (isset($user['email'])) {
        $role_check->bind_param("s", $user['email']);
        $role_check->execute();
        $role_res = $role_check->get_result();
        if ($role_row = $role_res->fetch_assoc()) {
            if ($role_row['role'] === 'admin') {
                $is_admin = true;
                $user['role'] = 'admin'; // Override role for display
                $_SESSION['user_role'] = 'admin'; // Sync session
            }
        }
    }
    $role_check->close();
}

$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

$feedback_message = '';
$feedback_type = '';

// ==========================================
// --- LOGIK PENGURUSAN PENGGUNA (ADMIN) ---
// ==========================================
if ($is_admin) {
    // --- LOGIK PEMPROSESAN CSV ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        if ($file['error'] == UPLOAD_ERR_OK) {
            $file_tmp_path = $file['tmp_name'];
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) == 'csv') {
                $conn->begin_transaction();
                try {
                    $handle = fopen($file_tmp_path, "r");
                    fgetcsv($handle); // Langkau baris pengepala
                    $stmt = $conn->prepare("INSERT INTO " . TABLE_WHITELIST . " (email, nama_pegawai, role) VALUES (?, ?, ?) 
                                            ON DUPLICATE KEY UPDATE nama_pegawai = VALUES(nama_pegawai), role = VALUES(role)");
                    $added_count = 0;
                    $updated_count = 0;
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        if (count($data) >= 3) {
                            $email = trim($data[0]);
                            $nama_pegawai = trim($data[1]);
                            $role = strtolower(trim($data[2]));
                            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($nama_pegawai) && ($role == 'admin' || $role == 'user')) {
                                $stmt->bind_param("sss", $email, $nama_pegawai, $role);
                                $stmt->execute();
                                if ($stmt->affected_rows === 1)
                                    $added_count++;
                                elseif ($stmt->affected_rows === 2)
                                    $updated_count++;
                            }
                        }
                    }
                    fclose($handle);
                    $stmt->close();
                    $conn->commit();
                    $feedback_message = "Import CSV berjaya: $added_count pengguna ditambah, $updated_count pengguna dikemaskini.";
                    $feedback_type = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $feedback_message = "Ralat semasa memproses fail: " . $e->getMessage();
                    $feedback_type = "error";
                }
            } else {
                $feedback_message = "Ralat: Format fail tidak sah. Sila muat naik fail .csv sahaja.";
                $feedback_type = "error";
            }
        } else {
            $feedback_message = "Ralat semasa muat naik fail.";
            $feedback_type = "error";
        }
    }

    // --- LOGIK TAMBAH PENGGUNA (DARI BORANG INLINE) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
        $email = trim($_POST['email']);
        $nama_pegawai = trim($_POST['nama_pegawai']);
        $role = trim($_POST['role']);

        if (!empty($email) && !empty($nama_pegawai) && !empty($role)) {
            $stmt = $conn->prepare("INSERT INTO " . TABLE_WHITELIST . " (email, nama_pegawai, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $nama_pegawai, $role);
            if ($stmt->execute()) {
                $feedback_message = "Pengguna berjaya ditambah.";
                $feedback_type = "success";
            } else {
                $feedback_message = "Ralat: E-mel ini mungkin sudah wujud. " . $stmt->error;
                $feedback_type = "error";
            }
            $stmt->close();
        } else {
            $feedback_message = "Ralat: Sila isi semua medan (Nama, E-mel, Peranan).";
            $feedback_type = "error";
        }
    }

    // --- LOGIK KEMAS KINI PENGGUNA (DARI MODAL) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
        $email = trim($_POST['email']);
        $nama_pegawai = trim($_POST['nama_pegawai']);
        $role = trim($_POST['role']);
        $id = intval($_POST['id']);

        if (!empty($email) && !empty($nama_pegawai) && !empty($role) && $id > 0) {
            $stmt = $conn->prepare("UPDATE " . TABLE_WHITELIST . " SET email = ?, nama_pegawai = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $email, $nama_pegawai, $role, $id);
            if ($stmt->execute()) {
                $feedback_message = "Pengguna berjaya dikemaskini.";
                $feedback_type = "success";
            } else {
                $feedback_message = "Ralat: " . $stmt->error;
                $feedback_type = "error";
            }
            $stmt->close();
        } else {
            $feedback_message = "Ralat: Sila isi semua medan.";
            $feedback_type = "error";
        }
    }

    // --- LOGIK PADAM PENGGUNA ---
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['delete_id'])) {
        $id = intval($_GET['delete_id']);
        if ($id == $_SESSION['user_id']) {
            $feedback_message = "Ralat: Anda tidak boleh memadam akaun anda sendiri.";
            $feedback_type = "error";
        } elseif ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM " . TABLE_WHITELIST . " WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $feedback_message = "Pengguna berjaya dipadam.";
                $feedback_type = "success";
            } else {
                $feedback_message = "Ralat semasa memadam.";
                $feedback_type = "error";
            }
            $stmt->close();
        }
    }

    // Helper to ensure UTF-8
    if (!function_exists('force_utf8')) {
        function force_utf8($data)
        {
            if (is_array($data)) {
                foreach ($data as $k => $v)
                    $data[$k] = force_utf8($v);
                return $data;
            }
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }
    }

    // Dapatkan senarai semua pengguna untuk dipaparkan
    $users_list = [];
    $sql_users = "SELECT id, email, nama_pegawai, role, last_login FROM " . TABLE_WHITELIST . " ORDER BY nama_pegawai ASC";
    $result_users = $conn->query($sql_users);
    if ($result_users) {
        $raw_users = $result_users->fetch_all(MYSQLI_ASSOC);
        $users_list = force_utf8($raw_users);
    }
}
$conn->close();

// Determine default tab base on URL params
$active_tab = (isset($_GET['tab']) && $_GET['tab'] == 'manage' && $is_admin) ? 'manage' : 'profile';
if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['delete_id'])) {
    if (isset($_POST['add_user']) || isset($_POST['update_user']) || isset($_FILES['csv_file']) || isset($_GET['delete_id'])) {
        $active_tab = 'manage';
    } else {
        $active_tab = 'profile';
    }
}

?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya & Pengurusan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        [x-cloak] {
            display: none;
        }
    </style>

    <?php if ($is_admin): ?>
        <script>
            // Inject PHP data directly into a JS variable safely
            var usersData = <?= json_encode($users_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            // Convert array to object map for faster lookup
            var usersMap = {};
            if (Array.isArray(usersData)) {
                usersData.forEach(function (u) {
                    usersMap[String(u.id)] = u;
                });
            }

            function openEdit(userId) {
                var uid = String(userId);
                var user = usersMap[uid];
                if (!user) {
                    alert("Ralat: Data pengguna tidak ditemui. ID: " + uid);
                    return;
                }

                // Find Alpine component and update data inside the main block
                var mainElement = document.querySelector('#manage-app');
                if (mainElement && mainElement.__x) {
                    mainElement.__x.$data.userToEdit = user;
                    mainElement.__x.$data.editModalOpen = true;

                    setTimeout(function () {
                        var modal = document.querySelector('[x-show="editModalOpen"]');
                        if (modal) modal.style.display = '';
                    }, 50);
                }
            }

            function closeModal() {
                var mainElement = document.querySelector('#manage-app');
                if (mainElement && mainElement.__x) {
                    mainElement.__x.$data.editModalOpen = false;
                    setTimeout(function () {
                        var modal = document.querySelector('[x-show="editModalOpen"]');
                        if (modal) modal.style.display = 'none';
                    }, 300);
                }
            }
        </script>
    <?php endif; ?>
</head>

<body class="bg-gray-100">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8" x-data="{ activeTab: '<?= $active_tab ?>' }">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800"
                x-text="activeTab === 'manage' ? 'Pengurusan Sistem' : 'Profil Saya'">Profil Saya</h1>
            <p class="text-gray-500 mt-1"
                x-text="activeTab === 'manage' ? 'Sistem pengurusan senarai whitelist.' : 'Urus maklumat peribadi dan tetapan keselamatan anda.'">
                Urus maklumat anda.</p>
        </header>

        <div class="flex flex-col md:flex-row gap-8">

            <!-- Sidebar Navigation -->
            <div class="md:w-64 flex-shrink-0">
                <nav class="flex flex-col space-y-2">
                    <button @click="activeTab = 'profile'"
                        :class="activeTab === 'profile' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-200'"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all text-left">
                        <i class="fas fa-user w-5 text-center"></i>
                        Profil Peribadi
                    </button>
                    <?php if ($is_admin): ?>
                        <button @click="activeTab = 'manage'"
                            :class="activeTab === 'manage' ? 'bg-purple-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-200'"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all text-left">
                            <i class="fas fa-users-gear w-5 text-center"></i>
                            Urus Pengguna
                        </button>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Content Area -->
            <div class="flex-grow max-w-5xl">

                <!-- TAB: PROFIL SAYA -->
                <div x-show="activeTab === 'profile'" x-cloak class="space-y-8">
                    <!-- User Information Card -->
                    <div class="bg-white p-8 rounded-lg shadow-md border-l-4 border-blue-500">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-user-circle text-blue-500 mr-3"></i>
                            Maklumat Pegawai
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Nama Pegawai</label>
                                <p class="mt-1 text-lg font-semibold text-gray-900">
                                    <?= htmlspecialchars($user['nama_pegawai'] ?? '') ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Alamat Emel</label>
                                <p class="mt-1 text-lg font-semibold text-gray-900">
                                    <?= htmlspecialchars($user['email'] ?? '') ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Peranan Akses</label>
                                <div class="mt-1">
                                    <span
                                        class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= ($user['role'] ?? '') == 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= htmlspecialchars(ucfirst($user['role'] ?? 'User')) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bahagian Tukar Kata Laluan telah dialih keluar (Google Auth Sahaja) -->

                </div>

                <!-- TAB: PENGURUSAN PENGGUNA -->
                <?php if ($is_admin): ?>
                    <div x-show="activeTab === 'manage'" x-cloak id="manage-app"
                        x-data="{ editModalOpen: false, userToEdit: {} }"
                        @open-edit.window="userToEdit = $event.detail; editModalOpen = true">
                        <!-- Mesej Maklum Balas -->
                        <?php if ($feedback_message): ?>
                            <div
                                class="mb-6 p-4 rounded-lg <?= $feedback_type == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= htmlspecialchars($feedback_message) ?>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                            <!-- Bahagian Muat Naik CSV -->
                            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center"><i
                                        class="fas fa-file-csv mr-3 text-green-600"></i>Muat Naik CSV</h2>
                                <form action="profile.php?tab=manage" method="POST" enctype="multipart/form-data"
                                    class="space-y-4">
                                    <p class="text-sm text-gray-600">
                                        <a href="template_pengguna.csv"
                                            class="text-blue-600 hover:underline font-medium">Muat Turun Templat Di
                                            Sini</a>.
                                    </p>
                                    <div>
                                        <label for="csv_file" class="block text-sm font-medium text-gray-700">Pilih fail
                                            'template_pengguna.csv'</label>
                                        <input type="file" name="csv_file" id="csv_file" required accept=".csv" class="mt-1 block w-full text-sm text-gray-500
                                        file:mr-4 file:py-2 file:px-4
                                        file:rounded-lg file:border-0
                                        file:text-sm file:font-semibold
                                        file:bg-blue-50 file:text-blue-700
                                        hover:file:bg-blue-100
                                    ">
                                    </div>
                                    <div class="text-left">
                                        <button type="submit"
                                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-lg transition duration-300">
                                            <i class="fas fa-upload mr-2"></i>Muat Naik
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Bahagian Tambah Pengguna -->
                            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center"><i
                                        class="fas fa-user-plus mr-3 text-blue-600"></i>Tambah Pengguna</h2>
                                <form action="profile.php?tab=manage" method="POST" class="space-y-4">
                                    <div>
                                        <label for="add-nama_pegawai" class="block text-sm font-medium text-gray-700">Nama
                                            Pegawai</label>
                                        <input type="text" name="nama_pegawai" id="add-nama_pegawai" required
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="add-email" class="block text-sm font-medium text-gray-700">E-mel
                                            Pengguna</label>
                                        <input type="email" name="email" id="add-email" required
                                            pattern=".+@customs\.gov\.my$" placeholder="pengguna@customs.gov.my"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="add-role"
                                            class="block text-sm font-medium text-gray-700">Peranan</label>
                                        <select name="role" id="add-role"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            <option value="user" selected>User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <div class="text-left">
                                        <button type="submit" name="add_user"
                                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded-lg transition duration-300">
                                            <i class="fas fa-plus mr-2"></i>Tambah
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Senarai Pengguna -->
                        <div class="bg-white p-6 rounded-xl shadow-md border">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Senarai Pengguna Dibenarkan</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                                E-mel</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                                Nama</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                                Peranan</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                                Kali Terakhir Log Masuk</th>
                                            <th
                                                class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                                Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($users_list as $u): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                                    <?= htmlspecialchars($u['email'], ENT_QUOTES | ENT_SUBSTITUTE) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                                    <?= htmlspecialchars($u['nama_pegawai'], ENT_QUOTES | ENT_SUBSTITUTE) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                                    <span
                                                        class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $u['role'] == 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800' ?>">
                                                        <?= htmlspecialchars(ucfirst($u['role'])) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                                    <?= $u['last_login'] ? date('d-m-Y h:i A', strtotime($u['last_login'])) : 'Belum pernah' ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-4">
                                                    <button type="button" onclick="openEdit(<?= $u['id'] ?>)"
                                                        class="text-blue-600 hover:text-blue-900 cursor-pointer transition-colors duration-200"
                                                        title="Kemaskini">
                                                        <i class="fas fa-edit transform hover:scale-110"></i>
                                                    </button>
                                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                        <a href="profile.php?tab=manage&delete_id=<?= $u['id'] ?>"
                                                            onclick="return confirm('Anda pasti mahu memadam pengguna ini?')"
                                                            class="text-red-600 hover:text-red-900 transition-colors duration-200"
                                                            title="Padam">
                                                            <i class="fas fa-trash-alt transform hover:scale-110"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Modal Kemas Kini Pengguna -->
                        <div x-show="editModalOpen" style="display: none;" class="fixed z-50 inset-0 overflow-y-auto"
                            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                            <div class="flex items-center justify-center min-h-screen">
                                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                    @click="editModalOpen = false"></div>
                                <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full"
                                    @click.away="editModalOpen = false" x-transition:enter="ease-out duration-300"
                                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                    x-transition:leave="ease-in duration-200"
                                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                                    <form action="profile.php?tab=manage" method="POST">
                                        <input type="hidden" name="id" x-model="userToEdit.id">
                                        <div class="px-4 pt-5 pb-4 sm:p-6">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900">Kemas Kini Pengguna</h3>
                                            <div class="mt-4 space-y-4">
                                                <div>
                                                    <label for="edit-nama_pegawai"
                                                        class="block text-sm font-medium text-gray-700">Nama Pegawai</label>
                                                    <input type="text" name="nama_pegawai" id="edit-nama_pegawai" required
                                                        x-model="userToEdit.nama_pegawai"
                                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                </div>
                                                <div>
                                                    <label for="edit-email"
                                                        class="block text-sm font-medium text-gray-700">E-mel</label>
                                                    <input type="email" name="email" id="edit-email" required
                                                        pattern=".+@customs\.gov\.my$" x-model="userToEdit.email"
                                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                </div>
                                                <div>
                                                    <label for="edit-role"
                                                        class="block text-sm font-medium text-gray-700">Peranan
                                                        (Role)</label>
                                                    <select name="role" id="edit-role" x-model="userToEdit.role"
                                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                        <option value="user">User</option>
                                                        <option value="admin">Admin</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                            <button type="submit" name="update_user"
                                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                                            <button type="button" @click="editModalOpen = false" onclick="closeModal()"
                                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
</body>

</html>