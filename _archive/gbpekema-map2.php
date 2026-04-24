<?php
session_start();
include 'config.php'; // Pastikan laluan ini betul

// Semak jika pengguna telah log masuk
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$feedback_message = '';
$feedback_type = '';

// --- LOGIK PEMPROSESAN BORANG (TAMBAH/KEMAS KINI/PADAM) ---

// Tambah atau Kemas Kini Syarikat
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_company']) || isset($_POST['update_company']))) {
    $nama = trim($_POST['nama']);
    $pic = trim($_POST['pic']);
    $alamat = trim($_POST['alamat']);
    $no_tel = trim($_POST['no_tel']);
    $google_map_url = trim($_POST['google_map_url']);
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!empty($nama)) {
        if ($id > 0) { // Logik Kemas Kini
            $stmt = $conn->prepare("UPDATE gbpekema SET nama = ?, pic = ?, alamat = ?, no_tel = ?, google_map_url = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $nama, $pic, $alamat, $no_tel, $google_map_url, $id);
            $action = 'dikemaskini';
        } else { // Logik Tambah
            $stmt = $conn->prepare("INSERT INTO gbpekema (nama, pic, alamat, no_tel, google_map_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nama, $pic, $alamat, $no_tel, $google_map_url);
            $action = 'ditambah';
        }
        
        if ($stmt->execute()) {
            $feedback_message = "Syarikat berjaya {$action}.";
            $feedback_type = "success";
        } else {
            $feedback_message = "Ralat: " . $stmt->error;
            $feedback_type = "error";
        }
        $stmt->close();
    }
}


// Padam Syarikat
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    if ($id > 0) {
        // Semak jika syarikat ini mempunyai kenderaan yang berkaitan
        $check_stmt = $conn->prepare("SELECT COUNT(id) as count FROM vehicle_inventory WHERE gbpekema_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        $check_stmt->close();
        
        if ($row['count'] > 0) {
            $feedback_message = "Ralat: Syarikat tidak boleh dipadam kerana mempunyai rekod kenderaan yang berkaitan.";
            $feedback_type = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM gbpekema WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $feedback_message = "Syarikat berjaya dipadam.";
                $feedback_type = "success";
            } else {
                $feedback_message = "Ralat semasa memadam.";
                $feedback_type = "error";
            }
            $stmt->close();
        }
    }
}


// Dapatkan data untuk kad ringkasan dan senarai
$total_gb = 0;
$most_active_gb = ['nama' => 'N/A', 'vehicle_count' => 0];
$companies = [];
$recent_gbs = [];

// Jumlah syarikat
$result_total = $conn->query("SELECT COUNT(id) as total FROM gbpekema");
if($result_total) $total_gb = $result_total->fetch_assoc()['total'];

// Syarikat paling aktif
$sql_active = "SELECT g.nama, COUNT(v.id) as vehicle_count FROM gbpekema g LEFT JOIN vehicle_inventory v ON g.id = v.gbpekema_id GROUP BY g.id ORDER BY vehicle_count DESC LIMIT 1";
$result_active = $conn->query($sql_active);
if($result_active && $result_active->num_rows > 0) $most_active_gb = $result_active->fetch_assoc();

// Senarai semua syarikat
$sql_companies = "SELECT id, nama, pic, alamat, no_tel, google_map_url FROM gbpekema ORDER BY nama ASC";
$result_companies = $conn->query($sql_companies);
if ($result_companies) $companies = $result_companies->fetch_all(MYSQLI_ASSOC);

// Syarikat terbaharu didaftarkan
$sql_recent = "SELECT nama, created_at FROM gbpekema ORDER BY created_at DESC LIMIT 5";
$result_recent = $conn->query($sql_recent);
if($result_recent) $recent_gbs = $result_recent->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan GB/PEKEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; } </style>
</head>
<body class="bg-gray-100">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8" x-data="{ addModalOpen: false, editModalOpen: false, companyToEdit: {} }">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Pengurusan Syarikat GB/PEKEMA</h1>
        <p class="text-gray-500 mb-8">Urus dan selia maklumat syarikat.</p>

        <!-- Mesej Maklum Balas -->
        <?php if ($feedback_message): ?>
        <div class="mb-6 p-4 rounded-lg <?= $feedback_type == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
            <?= htmlspecialchars($feedback_message) ?>
        </div>
        <?php endif; ?>

        <!-- Kad Ringkasan -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-green-600 text-white p-6 rounded-xl shadow-lg flex justify-between items-center">
                <div>
                    <p class="text-sm font-medium opacity-80">Jumlah GB</p>
                    <p class="text-4xl font-bold"><?= $total_gb ?></p>
                </div>
                <i class="fas fa-building fa-3x opacity-30"></i>
            </div>
            <div class="bg-purple-600 text-white p-6 rounded-xl shadow-lg md:col-span-2">
                 <p class="text-sm font-medium opacity-80">GB Paling Aktif</p>
                 <div class="flex justify-between items-end">
                    <p class="text-2xl font-bold"><?= htmlspecialchars($most_active_gb['nama']) ?></p>
                    <p class="text-lg font-semibold"><?= htmlspecialchars($most_active_gb['vehicle_count']) ?> Kenderaan</p>
                 </div>
            </div>
        </div>

        <!-- Senarai Syarikat dan Aktiviti Terkini -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md border">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Senarai Syarikat</h3>
                    <button @click="addModalOpen = true" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Tambah GB
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">PIC</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No. Tel</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Alamat Peta</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                             <?php foreach ($companies as $company): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?= htmlspecialchars($company['nama']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?= htmlspecialchars($company['pic'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?= htmlspecialchars($company['no_tel'] ?: 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($company['google_map_url'])): ?>
                                            <a href="<?= htmlspecialchars($company['google_map_url']) ?>" target="_blank" class="text-blue-600 hover:underline">
                                                Lihat <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-4">
                                        <button @click="companyToEdit = <?= htmlspecialchars(json_encode($company)) ?>; editModalOpen = true" class="text-yellow-600 hover:text-yellow-900" title="Kemaskini">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="gbpekema.php?delete_id=<?= $company['id'] ?>" onclick="return confirm('Anda pasti mahu memadam syarikat ini?')" class="text-red-600 hover:text-red-900" title="Padam">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-md border">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Aktiviti Terkini</h3>
                <div class="space-y-4">
                    <?php foreach ($recent_gbs as $gb): ?>
                    <div class="flex items-start">
                        <div class="bg-green-100 text-green-600 rounded-full h-10 w-10 flex-shrink-0 flex items-center justify-center">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="ml-4">
                            <p class="font-semibold text-sm text-gray-800">GB Baharu Didaftarkan</p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($gb['nama']) ?></p>
                            <p class="text-xs text-gray-400 mt-1"><?= date('d M Y, h:i A', strtotime($gb['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Tambah Syarikat -->
    <div x-show="addModalOpen" x-cloak class="fixed z-10 inset-0 overflow-y-auto" 
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center min-h-screen">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="addModalOpen = false"></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full"
                 @click.away="addModalOpen = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <form action="gbpekema.php" method="POST">
                    <div class="px-4 pt-5 pb-4 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Tambah GB Baharu</h3>
                        <div class="mt-4 space-y-4">
                             <div>
                                <label for="add-nama" class="block text-sm font-medium text-gray-700">Nama Syarikat</label>
                                <input type="text" name="nama" id="add-nama" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="add-pic" class="block text-sm font-medium text-gray-700">PIC</label>
                                <input type="text" name="pic" id="add-pic" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="add-alamat" class="block text-sm font-medium text-gray-700">Alamat Syarikat</label>
                                <textarea name="alamat" id="add-alamat" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>
                            <div>
                                <label for="add-no_tel" class="block text-sm font-medium text-gray-700">No. Telefon</label>
                                <input type="text" name="no_tel" id="add-no_tel" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                             <div>
                                <label for="add-google_map_url" class="block text-sm font-medium text-gray-700">Pautan Google Maps</label>
                                <input type="url" name="google_map_url" id="add-google_map_url" placeholder="https://maps.app.goo.gl/contoh" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="add_company" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                        <button type="button" @click="addModalOpen = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Kemas Kini -->
    <div x-show="editModalOpen" x-cloak class="fixed z-10 inset-0 overflow-y-auto"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
         <div class="flex items-center justify-center min-h-screen">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="editModalOpen = false"></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full"
                 @click.away="editModalOpen = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <form action="gbpekema.php" method="POST">
                    <input type="hidden" name="id" x-model="companyToEdit.id">
                    <div class="px-4 pt-5 pb-4 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Kemas Kini Syarikat</h3>
                        <div class="mt-4 space-y-4">
                             <div>
                                <label for="edit-nama" class="block text-sm font-medium text-gray-700">Nama Syarikat</label>
                                <input type="text" name="nama" id="edit-nama" required x-model="companyToEdit.nama" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="edit-pic" class="block text-sm font-medium text-gray-700">PIC</label>
                                <input type="text" name="pic" id="edit-pic" x-model="companyToEdit.pic" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                             <div>
                                <label for="edit-alamat" class="block text-sm font-medium text-gray-700">Alamat Syarikat</label>
                                <textarea name="alamat" id="edit-alamat" rows="3" x-model="companyToEdit.alamat" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>
                            <div>
                                <label for="edit-no_tel" class="block text-sm font-medium text-gray-700">No. Telefon</label>
                                <input type="text" name="no_tel" id="edit-no_tel" x-model="companyToEdit.no_tel" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                             <div>
                                <label for="edit-google_map_url" class="block text-sm font-medium text-gray-700">Pautan Google Maps</label>
                                <input type="url" name="google_map_url" id="edit-google_map_url" x-model="companyToEdit.google_map_url" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="update_company" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">Simpan</button>
                        <button type="button" @click="editModalOpen = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
</body>
</html>

