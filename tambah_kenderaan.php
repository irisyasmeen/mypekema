<?php
session_start();
include 'config.php'; // Ensure this path is correct
include 'exif_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// RESTRICTION: Supervisor is view-only, Admin, User and Licensee are allowed
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'supervisor') {
    header("Location: vehicles.php");
    exit();
}

$gbpekema_list = [];
$sql_gb = "SELECT id, nama FROM gbpekema ORDER BY nama ASC";
$result_gb = $conn->query($sql_gb);
if ($result_gb->num_rows > 0) {
    while ($row = $result_gb->fetch_assoc()) {
        $gbpekema_list[] = $row;
    }
}

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic validation
    if (empty($_POST['lot_number']) || empty($_POST['chassis_number']) || empty($_POST['vehicle_model'])) {
        $error_message = "Sila isi semua medan yang diperlukan: No. Lot, No. Casis, dan Model Kenderaan.";
    } else {
        // Handle File Uploads
        $upload_dir = 'uploads/vehicles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $image_front = $image_rear = $image_left = $image_right = $video_file = null;
        $exif_front = $exif_rear = $exif_left = $exif_right = null;

        // Helper function to process upload and extract EXIF
        $processUpload = function ($fileInput, &$filePath, &$exifData, $isImage = true) use ($upload_dir) {
            if (isset($_FILES[$fileInput]) && $_FILES[$fileInput]['error'] == 0) {
                // Generate a unique filename
                $ext = pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION);
                $uniqueName = uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = $upload_dir . $uniqueName;

                if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $targetPath)) {
                    $filePath = $targetPath;

                    // Only attempt EXIF extraction if it's an image
                    if ($isImage) {
                        $exifData = ExifHelper::extractExifAsJson($targetPath);
                    }
                }
            }
        };

        $processUpload('image_front', $image_front, $exif_front, true);
        $processUpload('image_rear', $image_rear, $exif_rear, true);
        $processUpload('image_left', $image_left, $exif_left, true);
        $processUpload('image_right', $image_right, $exif_right, true);

        $dummy = null; // No EXIF for video
        $processUpload('video_file', $video_file, $dummy, false);

        // Prepare an insert statement
        $sql = "INSERT INTO vehicle_inventory (
            lot_number, kod_gudang, condition_status, gbpekema_id, 
            chassis_number, engine_number, vehicle_model, manufacturing_year, 
            color, engine_cc, kw, k8_number_full, odometer8, 
            k1_number, odometer1, odometer, ap, tarikh_luput, tpa_date, import_date, catatan,
            image_front, image_rear, image_left, image_right, video_file,
            exif_data_front, exif_data_rear, exif_data_left, exif_data_right
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            // Added new s string types for the 9 new fields
            $stmt->bind_param(
                "sssisssisiisisiissssssssssssss",
                $lot_number,
                $kod_gudang,
                $condition_status,
                $gbpekema_id,
                $chassis_number,
                $engine_number,
                $vehicle_model,
                $manufacturing_year,
                $color,
                $engine_cc,
                $kw,
                $k8_number_full,
                $odometer8,
                $k1_number,
                $odometer1,
                $odometer,
                $ap,
                $tarikh_luput,
                $tpa_date,
                $import_date,
                $catatan,
                $image_front,
                $image_rear,
                $image_left,
                $image_right,
                $video_file,
                $exif_front,
                $exif_rear,
                $exif_left,
                $exif_right
            );

            // Set parameters and execute
            $lot_number = $_POST['lot_number'];
            $kod_gudang = $_POST['kod_gudang'] ?? null;
            $condition_status = $_POST['condition_status'] ?? 'USED';
            $gbpekema_id = !empty($_POST['gbpekema_id']) ? (int) $_POST['gbpekema_id'] : null;
            $chassis_number = $_POST['chassis_number'];
            $engine_number = $_POST['engine_number'] ?? null;
            $vehicle_model = $_POST['vehicle_model'];
            $manufacturing_year = !empty($_POST['manufacturing_year']) ? (int) $_POST['manufacturing_year'] : null;
            $color = $_POST['color'] ?? null;
            $engine_cc = !empty($_POST['engine_cc']) ? (int) $_POST['engine_cc'] : null;
            $kw = !empty($_POST['kw']) ? (int) $_POST['kw'] : null;
            $k8_number_full = $_POST['k8_number_full'] ?? null;
            $odometer8 = !empty($_POST['odometer8']) ? (int) $_POST['odometer8'] : null;
            $k1_number = $_POST['k1_number'] ?? null;
            $odometer1 = !empty($_POST['odometer1']) ? (int) $_POST['odometer1'] : null;
            $odometer = !empty($_POST['odometer']) ? (int) $_POST['odometer'] : null;
            $ap = $_POST['ap'] ?? null;
            $tarikh_luput = !empty($_POST['tarikh_luput']) ? $_POST['tarikh_luput'] : null;
            $tpa_date = !empty($_POST['tpa_date']) ? $_POST['tpa_date'] : null;
            $import_date = !empty($_POST['import_date']) ? $_POST['import_date'] : null;
            $catatan = $_POST['catatan'] ?? null;

            if ($stmt->execute()) {
                header("Location: vehicles.php");
                exit();
            } else {
                if ($conn->errno == 1062) { // Duplicate entry error code
                    $error_message = "Ralat: No. Lot atau No. Casis telah wujud dalam pangkalan data.";
                } else {
                    $error_message = "Ralat semasa menyimpan data: " . $stmt->error;
                }
            }
            $stmt->close();
        } else {
            $error_message = "Ralat menyediakan kenyataan: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kenderaan Baharu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Flatpickr for better Calendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f5ff;
            /* bg-blue-50 */
        }

        .form-input {
            transition: all 0.3s;
        }

        .form-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4);
        }
    </style>
</head>

<body class="bg-blue-50">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-blue-900">Tambah Kenderaan Baharu</h1>
                <p class="text-blue-700 mt-1">Sila isi semua maklumat yang diperlukan di bawah.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Ralat</p>
                    <p><?= $error_message ?></p>
                </div>
            <?php endif; ?>

            <form action="tambah_kenderaan.php" method="POST" enctype="multipart/form-data" class="space-y-8">

                <!-- Maklumat Asas & Gudang -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-blue-600">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-blue-900 mb-6 flex items-center">
                            <i class="fas fa-file-alt text-blue-600 mr-3"></i>
                            Maklumat Asas & Gudang
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="lot_number" class="block text-sm font-medium text-gray-700">No. Lot <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="lot_number" id="lot_number"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input" required>
                            </div>
                            <div>
                                <label for="kod_gudang" class="block text-sm font-medium text-gray-700">Kod
                                    Gudang</label>
                                <select name="kod_gudang" id="kod_gudang"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                                    <option value="">Pilih Kod Gudang</option>
                                    <?php foreach ($gbpekema_list as $gb): ?>
                                        <option value="<?= htmlspecialchars($gb['nama']) ?>">
                                            <?= htmlspecialchars($gb['nama']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="import_date" class="block text-sm font-medium text-gray-700">Tarikh Bond
                                    In</label>
                                <input type="text" name="import_date" id="import_date"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input datepicker"
                                    placeholder="Pilih Tarikh" value="<?= date('Y-m-d') ?>">

                            </div>
                            <div>
                                <label for="condition_status"
                                    class="block text-sm font-medium text-gray-700">Keadaan</label>
                                <select id="condition_status" name="condition_status"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                                    <option value="USED">USED</option>
                                    <option value="NEW">NEW</option>
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <label for="gbpekema_id" class="block text-sm font-medium text-gray-700">Syarikat
                                    GB/PEKEMA (Nama Gudang)</label>
                                <select id="gbpekema_id" name="gbpekema_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                                    <option value="">Pilih Syarikat</option>
                                    <?php foreach ($gbpekema_list as $gb): ?>
                                        <option value="<?= $gb['id'] ?>"><?= htmlspecialchars($gb['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spesifikasi Teknikal -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-green-600">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-green-900 mb-6 flex items-center">
                            <i class="fas fa-cogs text-green-600 mr-3"></i>
                            Spesifikasi Teknikal
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="chassis_number" class="block text-sm font-medium text-gray-700">No. Casis
                                    <span class="text-red-500">*</span></label>
                                <input type="text" name="chassis_number" id="chassis_number"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input" required>
                            </div>
                            <div>
                                <label for="engine_number" class="block text-sm font-medium text-gray-700">No.
                                    Enjin</label>
                                <input type="text" name="engine_number" id="engine_number"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div>
                                <label for="vehicle_model" class="block text-sm font-medium text-gray-700">Model/Jenis
                                    <span class="text-red-500">*</span></label>
                                <input type="text" name="vehicle_model" id="vehicle_model"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input" required>
                            </div>
                            <div>
                                <label for="manufacturing_year" class="block text-sm font-medium text-gray-700">Tahun
                                    Dibuat</label>
                                <input type="number" name="manufacturing_year" id="manufacturing_year"
                                    placeholder="e.g., 2022"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div>
                                <label for="color" class="block text-sm font-medium text-gray-700">Warna</label>
                                <input type="text" name="color" id="color"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="engine_cc" class="block text-sm font-medium text-gray-700">Kapasiti
                                        (CC)</label>
                                    <input type="number" name="engine_cc" id="engine_cc"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                                </div>
                                <div>
                                    <label for="kw" class="block text-sm font-medium text-gray-700">KW</label>
                                    <input type="number" name="kw" id="kw"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maklumat Kastam -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-purple-600">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-purple-900 mb-6 flex items-center">
                            <i class="fas fa-shield-alt text-purple-600 mr-3"></i>
                            Maklumat Kastam
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="k8_number_full" class="block text-sm font-medium text-gray-700">No.
                                    K8</label>
                                <input type="text" name="k8_number_full" id="k8_number_full"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div>
                                <label for="odometer8" class="block text-sm font-medium text-gray-700">Odometer K8
                                    (km)</label>
                                <input type="number" name="odometer8" id="odometer8"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div>
                                <label for="k1_number" class="block text-sm font-medium text-gray-700">No. K1</label>
                                <input type="text" name="k1_number" id="k1_number"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div>
                                <label for="odometer1" class="block text-sm font-medium text-gray-700">Odometer K1
                                    (km)</label>
                                <input type="number" name="odometer1" id="odometer1"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div>
                                <label for="ap" class="block text-sm font-medium text-gray-700">No. AP</label>
                                <input type="text" name="ap" id="ap"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div>
                                <label for="tarikh_luput" class="block text-sm font-medium text-gray-700">Tarikh Luput
                                    AP</label>
                                <input type="text" name="tarikh_luput" id="tarikh_luput"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input datepicker"
                                    placeholder="Pilih Tarikh">
                            </div>
                            <div>
                                <label for="odometer" class="block text-sm font-medium text-gray-700">Odometer
                                    (km)</label>
                                <input type="number" name="odometer" id="odometer"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input">
                            </div>
                            <div class="md:col-span-1">
                                <label for="tpa_date" class="block text-sm font-medium text-gray-700">Tarikh Pendaftaran
                                    Asal</label>
                                <input type="text" name="tpa_date" id="tpa_date"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm form-input datepicker"
                                    placeholder="Pilih Tarikh">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Catatan -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-yellow-500">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-yellow-900 mb-4 flex items-center">
                            <i class="fas fa-sticky-note text-yellow-500 mr-3"></i>
                            Catatan
                        </h2>
                        <div>
                            <label for="catatan" class="sr-only">Catatan</label>
                            <textarea id="catatan" name="catatan" rows="4"
                                class="block w-full rounded-md border-gray-300 shadow-sm form-input"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Media Kenderaan (Uploads) -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-indigo-500">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-indigo-900 mb-6 flex items-center">
                            <i class="fas fa-camera text-indigo-500 mr-3"></i>
                            Media Kenderaan
                        </h2>
                        <p class="text-sm text-gray-600 mb-6">
                            Sila muat naik gambar kenderaan (Pandangan Depan, Belakang, Kiri, Kanan) dan 1 video
                            pilihan. <br>
                            <i>Nota: Jika gambar mengandungi maklumat lokasi (GPS) dan tarikh/masa sebenar, sistem akan
                                mengekstrak EXIF data secara automatik. Sila muat naik gambar asli.</i>
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                            <!-- Image Front -->
                            <div>
                                <label for="image_front" class="block text-sm font-medium text-gray-700 mb-1">Pandangan
                                    Depan (Gambar)</label>
                                <input type="file" name="image_front" id="image_front"
                                    accept="image/jpeg, image/png, image/webp"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer form-input border border-gray-300 rounded leading-normal">
                            </div>
                            <!-- Image Rear -->
                            <div>
                                <label for="image_rear" class="block text-sm font-medium text-gray-700 mb-1">Pandangan
                                    Belakang (Gambar)</label>
                                <input type="file" name="image_rear" id="image_rear"
                                    accept="image/jpeg, image/png, image/webp"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer form-input border border-gray-300 rounded leading-normal">
                            </div>
                            <!-- Image Left -->
                            <div>
                                <label for="image_left" class="block text-sm font-medium text-gray-700 mb-1">Pandangan
                                    Kiri (Gambar)</label>
                                <input type="file" name="image_left" id="image_left"
                                    accept="image/jpeg, image/png, image/webp"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer form-input border border-gray-300 rounded leading-normal">
                            </div>
                            <!-- Image Right -->
                            <div>
                                <label for="image_right" class="block text-sm font-medium text-gray-700 mb-1">Pandangan
                                    Kanan (Gambar)</label>
                                <input type="file" name="image_right" id="image_right"
                                    accept="image/jpeg, image/png, image/webp"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer form-input border border-gray-300 rounded leading-normal">
                            </div>
                        </div>

                        <!-- Video Video -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="video_file" class="block text-sm font-medium text-gray-700 mb-1">Video
                                    Kenderaan (Pilihan)</label>
                                <input type="file" name="video_file" id="video_file"
                                    accept="video/mp4, video/quicktime, video/webm"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer form-input border border-gray-300 rounded leading-normal">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4">
                    <button type="submit"
                        class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-3 px-8 rounded-lg transition duration-300 shadow-md hover:shadow-lg flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Simpan Rekod
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Tarikh Bond In - restore to full date
        flatpickr("#import_date", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            allowInput: true,
            defaultDate: "today"
        });

        // Other datepickers (without default date)
        flatpickr("#tarikh_luput, #tpa_date", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            allowInput: true
        });
    </script>
</body>

</html>