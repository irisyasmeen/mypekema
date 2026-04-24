<?php
session_start();
include 'config.php'; // Ensure this path is correct

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if an ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID Kenderaan tidak sah.");
}

$vehicle_id = (int)$_GET['id'];

// Determine which table to query
$is_archive = isset($_GET['archive']) && $_GET['archive'] == '1';
$table_name = $is_archive ? "vehicle_archive" : "vehicle_inventory";

// Prepare and execute the query to get all vehicle details
$stmt = $conn->prepare("SELECT * FROM $table_name WHERE id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result->fetch_assoc();
$stmt->close();

// If no vehicle is found with the given ID, stop the script
if (!$vehicle) {
    die("Rekod kenderaan tidak ditemui.");
}

// SECURITY CHECK FOR LICENSEE: Only allow access to vehicles from their own company
$user_role = $_SESSION['user_role'] ?? 'user';
if ($user_role === 'licensee') {
    $licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;
    if ($vehicle['gbpekema_id'] != $licensee_gb_id) {
        die("Akses dinafikan: Anda tidak mempunyai kebenaran untuk melihat rekod ini.");
    }
}

// Handle Tax Update (Pegawai Kanan/Admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_tax']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $duti_import = !empty($_POST['duti_import']) ? (float)$_POST['duti_import'] : null;
    $duti_eksais = !empty($_POST['duti_eksais']) ? (float)$_POST['duti_eksais'] : null;
    $cukai_jualan = !empty($_POST['cukai_jualan']) ? (float)$_POST['cukai_jualan'] : null;
    $duty_rm = !empty($_POST['duty_rm']) ? (float)$_POST['duty_rm'] : null; // Jumlah Cukai
    $receipt_number = !empty($_POST['receipt_number']) ? $_POST['receipt_number'] : null;
    $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;

    $update_sql = "UPDATE $table_name SET duti_import = ?, duti_eksais = ?, cukai_jualan = ?, duty_rm = ?, receipt_number = ?, payment_date = ? WHERE id = ?";
    if ($update_stmt = $conn->prepare($update_sql)) {
        $update_stmt->bind_param("ddddssi", $duti_import, $duti_eksais, $cukai_jualan, $duty_rm, $receipt_number, $payment_date, $vehicle_id);
        if ($update_stmt->execute()) {
            $archive_param = $is_archive ? "&archive=1" : "";
            header("Location: vehicle_details.php?id=$vehicle_id&status=taxupdatesuccess$archive_param");
            exit();
        } else {
            $error_msg = "Ralat kemaskini cukai: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['vehicle_image'])) {
    $target_dir = "uploads/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $file_extension = strtolower(pathinfo($_FILES["vehicle_image"]["name"], PATHINFO_EXTENSION));
    $new_filename = "vehicle_" . $vehicle_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    $uploadOk = 1;
    $error_msg = "";

    // Check if directory is writable
    if (!is_writable($target_dir)) {
        $error_msg = "Folder 'uploads' tidak mempunyai kebenaran menulis (Write Permission). Sila hubungi admin.";
        $uploadOk = 0;
    }

    // Check if upload has errors
    if ($_FILES['vehicle_image']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Ralat semasa memuat naik: " . $_FILES['vehicle_image']['error'];
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        $check = getimagesize($_FILES["vehicle_image"]["tmp_name"]);
        if($check === false) {
            $error_msg = "Fail bukan imej.";
            $uploadOk = 0;
        } else {
            // SECURITY: Verify actual MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES["vehicle_image"]["tmp_name"]);
            finfo_close($finfo);

            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($mime, $allowed_mimes)) {
                $error_msg = "Jenis fail tidak sah (MIME: $mime).";
                $uploadOk = 0;
            }
        }
    }

    // Check file size (limit to 10MB)
    if ($uploadOk == 1 && $_FILES["vehicle_image"]["size"] > 10000000) {
        $error_msg = "Maaf, saiz fail terlalu besar (Max 10MB).";
        $uploadOk = 0;
    }

    // Allow certain file formats
    if($uploadOk == 1 && $file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "webp" ) {
        $error_msg = "Maaf, hanya fail JPG, JPEG, PNG & WEBP sahaja dibenarkan.";
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["vehicle_image"]["tmp_name"], $target_file)) {
            // Delete old image if exists
            if (!empty($vehicle['vehicle_image']) && file_exists($target_dir . $vehicle['vehicle_image'])) {
                unlink($target_dir . $vehicle['vehicle_image']);
            }

            // Update database
            $stmt = $conn->prepare("UPDATE $table_name SET vehicle_image = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $vehicle_id);
            $stmt->execute();
            $stmt->close();
            
            // Redirect to avoid resubmission
            $archive_param = $is_archive ? "&archive=1" : "";
            header("Location: vehicle_details.php?id=$vehicle_id&status=uploadsuccess$archive_param");
            exit();
        } else {
            // Get more info about কেন the move failed
            $last_error = error_get_last();
            $error_msg = "Maaf, gagal memindahkan fail ke folder 'uploads'. Sila pastikan folder 'uploads' wujud dan mempunyai kebenaran menulis (CHMOD 755/777). " . ($last_error ? "Punca: " . $last_error['message'] : "");
        }
    }
}

// Helper function to format data for display
function display_data($data, $default = 'N/A') {
    return htmlspecialchars(!empty($data) ? $data : $default);
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Butiran Kenderaan - <?= display_data($vehicle['chassis_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f0f5ff; /* bg-blue-50 */
        }
        .detail-label {
            font-weight: 500;
            color: #1e3a8a; /* text-blue-900 */
        }
        .detail-value {
            color: #374151; /* text-gray-700 */
        }
        .image-container {
            position: relative;
            cursor: pointer;
        }
        .image-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: 0.5rem;
        }
        .image-container:hover .image-overlay {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-blue-50">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="max-w-4xl mx-auto">
            
            <?php if (isset($_GET['status']) && $_GET['status'] == 'updatesuccess'): ?>
                <div class="bg-green-100 border-l-4 border-green-600 text-green-800 p-4 mb-6 rounded-r-lg shadow-sm" role="alert">
                    <p><i class="fas fa-check-circle mr-2"></i>Maklumat kenderaan telah berjaya dikemaskini.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['status']) && $_GET['status'] == 'uploadsuccess'): ?>
                <div class="bg-green-100 border-l-4 border-green-600 text-green-800 p-4 mb-6 rounded-r-lg shadow-sm" role="alert">
                    <p><i class="fas fa-image mr-2"></i>Gambar kenderaan telah berjaya dimuat naik.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['status']) && $_GET['status'] == 'taxupdatesuccess'): ?>
                <div class="bg-green-100 border-l-4 border-green-600 text-green-800 p-4 mb-6 rounded-r-lg shadow-sm" role="alert">
                    <p><i class="fas fa-check-circle mr-2"></i>Maklumat bayaran cukai telah berjaya dikemaskini.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-red-100 border-l-4 border-red-600 text-red-800 p-4 mb-6 rounded-r-lg shadow-sm" role="alert">
                    <p><i class="fas fa-exclamation-triangle mr-2"></i><?= $error_msg ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-white shadow-xl rounded-xl border border-blue-100 overflow-hidden">
                <!-- Header with Blue Background and Image -->
                <div class="bg-blue-800 p-6 md:p-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                        <div class="md:col-span-1">
                            <div class="image-container group" onclick="document.getElementById('imageInput').click()">
                                <?php 
                                $image_path = !empty($vehicle['vehicle_image']) ? 'uploads/' . $vehicle['vehicle_image'] : 'https://placehold.co/400x300/e0e7ff/1e3a8a?text=Klik+Untuk+Muat+Naik';
                                ?>
                                <img src="<?= $image_path ?>" alt="Imej Kenderaan" class="rounded-lg shadow-lg w-full aspect-[4/3] object-cover bg-blue-100">
                                <div class="image-overlay">
                                    <span class="text-white font-semibold"><i class="fas fa-camera mr-2"></i>Tukar Gambar</span>
                                </div>
                            </div>
                            <form id="uploadForm" action="vehicle_details.php?id=<?= $vehicle_id ?>" method="POST" enctype="multipart/form-data" class="hidden">
                                <input type="file" name="vehicle_image" id="imageInput" accept="image/*" onchange="document.getElementById('uploadForm').submit()">
                            </form>
                        </div>
                        <div class="md:col-span-2 text-white">
                            <h1 class="text-4xl font-extrabold "><?= display_data($vehicle['vehicle_model']) ?></h1>
                            <p class="text-blue-300 font-mono text-md mt-2"><?= display_data($vehicle['chassis_number']) ?></p>
                            <div class="mt-4 flex gap-2">
                                <?php if (!empty($vehicle['duty_rm']) && $vehicle['duty_rm'] > 0): ?>
                                    <span class="px-4 py-2 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-2"></i>Telah Dibayar
                                    </span>
                                <?php else: ?>
                                    <span class="px-4 py-2 text-sm font-semibold rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-2"></i>Belum Dibayar
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($is_archive): ?>
                                    <span class="px-4 py-2 text-sm font-semibold rounded-full bg-slate-100 text-slate-800 border border-slate-300">
                                        <i class="fas fa-archive mr-2"></i>Diarkibkan
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="p-6 md:p-8 space-y-8">
                    <!-- Vehicle Information -->
                    <section>
                        <h2 class="text-xl font-bold text-blue-800 border-b-2 border-blue-200 pb-3 mb-6 flex items-center"><i class="fas fa-car mr-3 text-blue-500"></i>Maklumat Asas</h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-5">
                            <div>
                                <p class="detail-label">No. Lot</p>
                                <p class="detail-value"><?= display_data($vehicle['lot_number']) ?></p>
                            </div>
                            <div>
                                <p class="detail-label">No. Enjin</p>
                                <p class="detail-value"><?= display_data($vehicle['engine_number']) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">Tahun Dibuat</p>
                                <p class="detail-value"><?= display_data($vehicle['manufacturing_year']) ?></p>
                            </div>
                            <div>
                                <p class="detail-label">Kapasiti Enjin (CC)</p>
                                <p class="detail-value"><?= display_data($vehicle['engine_cc']) ?></p>
                            </div>
                            <div>
                                <p class="detail-label">Warna</p>
                                <p class="detail-value"><?= display_data($vehicle['color']) ?></p>
                            </div>
                        </div>
                    </section>

                    <!-- Customs Information -->
                    <section>
                        <h2 class="text-xl font-bold text-blue-800 border-b-2 border-blue-200 pb-3 mb-6 flex items-center"><i class="fas fa-shield-alt mr-3 text-blue-500"></i>Maklumat Kastam</h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-5">
                            <div>
                                <p class="detail-label">No. K8</p>
                                <p class="detail-value"><?= display_data($vehicle['k8_number_full']) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">No. K1</p>
                                <p class="detail-value"><?= display_data($vehicle['k1_number']) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">No. AP</p>
                                <p class="detail-value"><?= display_data($vehicle['ap']) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">Tarikh Luput AP</p>
                                <p class="detail-value"><?= display_data($vehicle['tarikh_luput']) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">Kod Gudang</p>
                                <p class="detail-value"><?= display_data($vehicle['kod_gudang']) ?></p>
                            </div>
                            <div>
                                <p class="detail-label">Tarikh Bond In</p>
                                <p class="detail-value"><?= !empty($vehicle['import_date']) ? date('d/m/Y', strtotime($vehicle['import_date'])) : 'N/A' ?></p>
                            </div>
                             <div>
                                <p class="detail-label">Odometer</p>
                                <p class="detail-value"><?= number_format($vehicle['odometer'] ?? 0) ?> km</p>
                            </div>
                        </div>
                    </section>

                     <!-- Payment Information -->
                    <section>
                         <div class="flex justify-between items-center border-b-2 border-blue-200 pb-3 mb-6">
                             <h2 class="text-xl font-bold text-blue-800 flex items-center"><i class="fas fa-money-check-alt mr-3 text-blue-500"></i>Maklumat Bayaran Cukai</h2>
                             <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && !$is_archive): ?>
                             <button type="button" onclick="document.getElementById('taxEditModal').classList.remove('hidden')" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 text-sm font-semibold py-1 px-3 rounded shadow-sm flex items-center transition-colors">
                                 <i class="fas fa-edit mr-2"></i>Kemaskini Cukai
                             </button>
                             <?php endif; ?>
                         </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                            <div>
                                <p class="detail-label">Duti Import</p>
                                <p class="detail-value">RM <?= number_format($vehicle['duti_import'] ?? 0, 2) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">Duti Eksais</p>
                                <p class="detail-value">RM <?= number_format($vehicle['duti_eksais'] ?? 0, 2) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">Cukai Jualan</p>
                                <p class="detail-value">RM <?= number_format($vehicle['cukai_jualan'] ?? 0, 2) ?></p>
                            </div>
                            <div>
                                <p class="detail-label font-bold">Jumlah Cukai</p>
                                <p class="detail-value font-bold text-lg">RM <?= number_format($vehicle['duty_rm'] ?? 0, 2) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">No. Resit</p>
                                <p class="detail-value"><?= display_data($vehicle['receipt_number']) ?></p>
                            </div>
                             <div>
                                <p class="detail-label">Tarikh Bayaran</p>
                                <p class="detail-value"><?= display_data($vehicle['payment_date']) ?></p>
                            </div>
                        </div>
                    </section>

                    <!-- Notes -->
                    <section>
                         <h2 class="text-xl font-bold text-blue-800 border-b-2 border-blue-200 pb-3 mb-6 flex items-center"><i class="fas fa-sticky-note mr-3 text-blue-500"></i>Catatan</h2>
                         <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="text-gray-700 italic"><?= !empty($vehicle['catatan']) ? nl2br(display_data($vehicle['catatan'])) : 'Tiada catatan.' ?></p>
                         </div>
                    </section>

                    <!-- Media Kenderaan -->
                    <section>
                        <h2 class="text-xl font-bold text-blue-800 border-b-2 border-blue-200 pb-3 mb-6 flex items-center"><i class="fas fa-camera mr-3 text-blue-500"></i>Media Kenderaan</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <?php 
                            $media_fields = [
                                'image_front' => 'Pandangan Depan',
                                'image_rear' => 'Pandangan Belakang',
                                'image_left' => 'Pandangan Kiri',
                                'image_right' => 'Pandangan Kanan'
                            ];
                            foreach ($media_fields as $field => $label): 
                                $path = !empty($vehicle[$field]) ? $vehicle[$field] : null;
                            ?>
                                <div class="space-y-2">
                                    <p class="text-sm font-semibold text-gray-600 text-center"><?= $label ?></p>
                                    <?php if ($path && file_exists($path)): ?>
                                        <div class="relative group cursor-pointer overflow-hidden rounded-lg shadow-md aspect-[4/3] mb-2" onclick="window.open('<?= $path ?>', '_blank')">
                                            <img src="<?= $path ?>" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110" alt="<?= $label ?>">
                                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 flex items-center justify-center transition-all">
                                                <i class="fas fa-search-plus text-white opacity-0 group-hover:opacity-100 text-2xl"></i>
                                            </div>
                                        </div>
                                        <!-- EXIF Display -->
                                        <?php 
                                        $exif_json = $vehicle['exif_data_' . str_replace('image_', '', $field)] ?? null;
                                        if ($exif_json):
                                            $exif = json_decode($exif_json, true);
                                            if ($exif):
                                                $dt = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;
                                                $lat = $exif['GPS']['Latitude'] ?? $exif['Latitude'] ?? null;
                                                $lon = $exif['GPS']['Longitude'] ?? $exif['Longitude'] ?? null;
                                        ?>
                                            <div class="text-[10px] bg-white p-2 rounded border border-blue-100 space-y-1 shadow-sm">
                                                <?php if ($dt): ?>
                                                    <p class="text-gray-500 flex items-center"><i class="fas fa-calendar-alt mr-1 text-blue-400"></i> <?= $dt ?></p>
                                                <?php endif; ?>
                                                <?php if ($lat && $lon): ?>
                                                    <a href="https://www.google.com/maps?q=<?= $lat ?>,<?= $lon ?>" target="_blank" class="text-blue-600 hover:text-blue-800 flex items-center font-medium">
                                                        <i class="fas fa-map-marker-alt mr-1 text-red-400"></i> Lokasi GPS
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php 
                                            endif;
                                        endif; 
                                        ?>
                                    <?php else: ?>
                                        <div class="flex flex-col items-center justify-center bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg aspect-[4/3] text-gray-400">
                                            <i class="fas fa-image text-3xl mb-2"></i>
                                            <span class="text-xs italic">Tiada Gambar</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Video Display -->
                        <?php if (!empty($vehicle['video_file']) && file_exists($vehicle['video_file'])): ?>
                            <div class="mt-8">
                                <h3 class="text-lg font-semibold text-blue-700 mb-4 flex items-center">
                                    <i class="fas fa-video mr-2"></i> Video Kenderaan
                                </h3>
                                <div class="max-w-2xl mx-auto rounded-xl overflow-hidden shadow-lg border-2 border-blue-100">
                                    <video controls class="w-full">
                                        <source src="<?= $vehicle['video_file'] ?>" type="video/mp4">
                                        Pemain video tidak disokong oleh pelayar anda.
                                    </video>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>

                </div>
                 <!-- Footer Actions -->
                <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-4 rounded-b-xl">
                    <?php 
                    $back_url = ($is_archive && $user_role !== 'licensee') ? 'arkib.php' : 'vehicles.php';
                    $back_label = ($is_archive && $user_role !== 'licensee') ? 'Arkib' : 'Senarai';
                    ?>
                    <a href="<?= $back_url ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300 w-full sm:w-auto text-center">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali ke <?= $back_label ?>
                    </a>
                    <?php if (!$is_archive): ?>
                    <div class="flex flex-wrap gap-2">
                    <?php if ($user_role !== 'licensee'): ?>
                    <a href="borang_pergerakan.php?id=<?= $vehicle_id ?>" target="_blank"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm flex items-center gap-2 transition-colors">
                        <i class="fas fa-file-alt"></i> Borang Pergerakan
                    </a>
                    <a href="kad_kenderaan.php?id=<?= $vehicle_id ?>" target="_blank"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm flex items-center gap-2 transition-colors">
                        <i class="fas fa-print"></i> Cetak Maklumat
                    </a>
                    <a href="edit_vehicle.php?id=<?= $vehicle['id'] ?>" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-6 rounded-lg transition duration-300 shadow-md hover:shadow-lg w-full sm:w-auto text-center">
                        <i class="fas fa-edit mr-2"></i>Kemaskini
                    </a>
                    <?php else: ?>
                    <a href="kad_kenderaan.php?id=<?= $vehicle_id ?>" target="_blank"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm flex items-center gap-2 transition-colors">
                        <i class="fas fa-print"></i> Cetak Maklumat
                    </a>
                    <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Tax Edit Modal (For Pegawai Kanan/Admin) -->
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && !$is_archive): ?>
    <div id="taxEditModal" class="fixed inset-0 z-50 overflow-y-auto hidden bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true" onclick="document.getElementById('taxEditModal').classList.add('hidden')"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border-t-4 border-indigo-600">
                <form action="vehicle_details.php?id=<?= $vehicle_id ?>" method="POST">
                    <input type="hidden" name="update_tax" value="1">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-money-check-alt text-indigo-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">
                                    Kemaskini Maklumat Bayaran Cukai
                                </h3>
                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="duti_import" class="block text-sm font-medium text-gray-700">Duti Import (RM)</label>
                                        <input type="number" step="0.01" name="duti_import" id="duti_import" value="<?= htmlspecialchars($vehicle['duti_import'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2 border">
                                    </div>
                                    <div>
                                        <label for="duti_eksais" class="block text-sm font-medium text-gray-700">Duti Eksais (RM)</label>
                                        <input type="number" step="0.01" name="duti_eksais" id="duti_eksais" value="<?= htmlspecialchars($vehicle['duti_eksais'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2 border">
                                    </div>
                                    <div>
                                        <label for="cukai_jualan" class="block text-sm font-medium text-gray-700">Cukai Jualan (RM)</label>
                                        <input type="number" step="0.01" name="cukai_jualan" id="cukai_jualan" value="<?= htmlspecialchars($vehicle['cukai_jualan'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2 border">
                                    </div>
                                    <div>
                                        <label for="duty_rm" class="block text-sm font-medium text-indigo-700 font-bold">Jumlah Cukai (RM)</label>
                                        <input type="number" step="0.01" name="duty_rm" id="duty_rm" value="<?= htmlspecialchars($vehicle['duty_rm'] ?? '') ?>" class="mt-1 block w-full border-indigo-300 bg-indigo-50 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2 border font-bold">
                                    </div>
                                    <div>
                                        <label for="receipt_number" class="block text-sm font-medium text-gray-700">No. Resit</label>
                                        <input type="text" name="receipt_number" id="receipt_number" value="<?= htmlspecialchars($vehicle['receipt_number'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2 border">
                                    </div>
                                    <div>
                                        <label for="payment_date" class="block text-sm font-medium text-gray-700">Tarikh Bayaran</label>
                                        <input type="date" name="payment_date" id="payment_date" value="<?= htmlspecialchars($vehicle['payment_date'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2 border">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-save mr-2 mt-1"></i> Simpan
                        </button>
                        <button type="button" onclick="document.getElementById('taxEditModal').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Simple script to auto-calculate total tax -->
    <script>
        const inputs = ['duti_import', 'duti_eksais', 'cukai_jualan'].map(id => document.getElementById(id));
        const totalInput = document.getElementById('duty_rm');
        
        inputs.forEach(input => {
            if (input) {
                input.addEventListener('input', () => {
                    let total = 0;
                    inputs.forEach(i => {
                        const val = parseFloat(i.value);
                        if (!isNaN(val)) total += val;
                    });
                    totalInput.value = total > 0 ? total.toFixed(2) : '';
                });
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>

