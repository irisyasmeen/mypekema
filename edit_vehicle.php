<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// RESTRICTION: Supervisor is view-only
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'supervisor') {
    header("Location: vehicles.php");
    exit();
}

$is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

// Check if ID is provided
if (!isset($_GET['id']) && !isset($_POST['vehicle_id'])) {
    die("ID Kenderaan tidak disediakan.");
}

$vehicle_id = isset($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : (int) $_GET['id'];
$error_message = '';
$success_message = '';

// --- HANDLE FORM SUBMISSION (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize and prepare variables, converting empty strings to NULL for numeric/date fields
    $lot_number = $_POST['lot_number'];
    $vehicle_model = $_POST['vehicle_model'];
    $chassis_number = $_POST['chassis_number'];
    $engine_number = $_POST['engine_number'];
    $manufacturing_year = $_POST['manufacturing_year'] === '' ? null : (int) $_POST['manufacturing_year'];
    $engine_cc = $_POST['engine_cc'] === '' ? null : (int) $_POST['engine_cc'];
    $color = $_POST['color'];
    $k8_number_full = $_POST['k8_number_full'];
    $k1_number = $_POST['k1_number'];
    $ap = $_POST['ap'];
    $tarikh_luput = $_POST['tarikh_luput'] === '' ? null : $_POST['tarikh_luput'];
    $kod_gudang = $_POST['kod_gudang'];
    $duty_rm = $_POST['duty_rm'] === '' ? null : (float) $_POST['duty_rm'];
    $duti_import = $_POST['duti_import'] === '' ? null : (float) $_POST['duti_import'];
    $duti_eksais = $_POST['duti_eksais'] === '' ? null : (float) $_POST['duti_eksais'];
    $cukai_jualan = $_POST['cukai_jualan'] === '' ? null : (float) $_POST['cukai_jualan'];
    $receipt_number = $_POST['receipt_number'];
    $payment_date = $_POST['payment_date'] === '' ? null : $_POST['payment_date'];
    $odometer = $_POST['odometer'] === '' ? null : (int) $_POST['odometer'];
    $import_date = $_POST['import_date'] === '' ? null : $_POST['import_date'];
    $stesen_asal = $_POST['stesen_asal'] ?? null;
    $tarikh_tamat_tempoh_gudang = $_POST['tarikh_tamat_tempoh_gudang'] === '' ? null : $_POST['tarikh_tamat_tempoh_gudang'];
    $harga_taksiran = $_POST['harga_taksiran'] === '' ? null : (float) $_POST['harga_taksiran'];
    $catatan = $_POST['catatan'];
    $condition_status = $_POST['condition_status'] ?? 'USED';
    $gbpekema_id = !empty($_POST['gbpekema_id']) ? (int) $_POST['gbpekema_id'] : null;

    // SECURITY CHECK: Licensee cannot change company ID
    if ($is_licensee) {
        $gbpekema_id = (int)$licensee_gb_id;
    }

    // Prepare statement for update
    $sql = "UPDATE vehicle_inventory SET 
            lot_number = ?, vehicle_model = ?, chassis_number = ?, engine_number = ?, 
            manufacturing_year = ?, engine_cc = ?, color = ?, k8_number_full = ?, 
            k1_number = ?, ap = ?, tarikh_luput = ?, kod_gudang = ?, 
            stesen_asal = ?, tarikh_tamat_tempoh_gudang = ?,
            duty_rm = ?, duti_import = ?, duti_eksais = ?, cukai_jualan = ?, harga_taksiran = ?,
            receipt_number = ?, payment_date = ?, odometer = ?, import_date = ?, condition_status = ?, gbpekema_id = ?, catatan = ?
            WHERE id = ?";

    // SECURITY: Ensure licensee only updates THEIR vehicle
    if ($is_licensee) {
        $sql = "UPDATE vehicle_inventory SET 
                lot_number = ?, vehicle_model = ?, chassis_number = ?, engine_number = ?, 
                manufacturing_year = ?, engine_cc = ?, color = ?, k8_number_full = ?, 
                k1_number = ?, ap = ?, tarikh_luput = ?, kod_gudang = ?, 
                stesen_asal = ?, tarikh_tamat_tempoh_gudang = ?,
                duty_rm = ?, duti_import = ?, duti_eksais = ?, cukai_jualan = ?, harga_taksiran = ?,
                receipt_number = ?, payment_date = ?, odometer = ?, import_date = ?, condition_status = ?, gbpekema_id = ?, catatan = ?
                WHERE id = ? AND gbpekema_id = " . (int)$licensee_gb_id;
    }

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        $error_message = "Ralat penyediaan kenyataan: " . $conn->error;
    } else {
        $stmt->bind_param(
            "ssssiissssssssdddddssissssi",
            $lot_number,
            $vehicle_model,
            $chassis_number,
            $engine_number,
            $manufacturing_year,
            $engine_cc,
            $color,
            $k8_number_full,
            $k1_number,
            $ap,
            $tarikh_luput,
            $kod_gudang,
            $stesen_asal,
            $tarikh_tamat_tempoh_gudang,
            $duty_rm,
            $duti_import,
            $duti_eksais,
            $cukai_jualan,
            $harga_taksiran,
            $receipt_number,
            $payment_date,
            $odometer,
            $import_date,
            $condition_status,
            $gbpekema_id,
            $catatan,
            $vehicle_id
        );

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header("Location: vehicle_details.php?id=" . $vehicle_id . "&status=updatesuccess");
                exit();
            } else {
                // If it's a licensee, maybe they don't own it
                if ($is_licensee) {
                     $error_message = "Tiada perubahan dikesan atau akses dinafikan.";
                } else {
                     header("Location: vehicle_details.php?id=" . $vehicle_id . "&status=updatesuccess");
                     exit();
                }
            }
        } else {
            // Check for duplicate entry error
            if ($conn->errno === 1062) {
                $error_message = "Gagal mengemas kini. No. Lot atau No. Casis yang dimasukkan sudah wujud dalam rekod lain.";
            } else {
                $error_message = "Ralat mengemas kini rekod: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}


// --- FETCH EXISTING DATA (GET REQUEST) ---
if (isset($_GET['status']) && $_GET['status'] == 'updatesuccess') {
    $success_message = "Maklumat kenderaan telah berjaya dikemaskini.";
}

$stmt = $conn->prepare("SELECT * FROM vehicle_inventory WHERE id = ?");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result->fetch_assoc();
$stmt->close();

if (!$vehicle) {
    die("Kenderaan tidak ditemui.");
}

// SECURITY CHECK FOR LICENSEE: Only allow access to vehicles from their own company
if ($is_licensee) {
    if ($vehicle['gbpekema_id'] != $licensee_gb_id) {
        header("Location: vehicles.php");
        exit();
    }
}

// Fetch GB/PEKEMA list for dropdown
$gb_list = [];
$gb_sql = "SELECT id, nama, kod_gudang FROM gbpekema";
if ($is_licensee) {
    $gb_sql .= " WHERE id = " . (int)$licensee_gb_id;
}
$gb_sql .= " ORDER BY nama ASC";

if ($gb_result = $conn->query($gb_sql)) {
    while ($row = $gb_result->fetch_assoc()) {
        $gb_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kemaskini Kenderaan - <?= htmlspecialchars($vehicle['vehicle_model'] ?? '') ?></title>
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

        .form-label {
            font-weight: 500;
            color: #1e3a8a;
            /* text-blue-900 */
        }

        .form-input {
            border: 1px solid #D1D5DB;
            border-radius: 0.5rem;
            padding: 0.6rem 1rem;
            width: 100%;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus {
            border-color: #2563eb;
            /* border-blue-600 */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            outline: none;
        }
    </style>
</head>

<body class="bg-blue-50">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center mb-6">
                <i class="fas fa-edit text-blue-700 text-3xl mr-4"></i>
                <div>
                    <h1 class="text-3xl font-bold text-blue-900">Kemaskini Maklumat Kenderaan</h1>
                    <p class="text-blue-700">Ubah suai butiran untuk rekod <span
                            class="font-semibold"><?= htmlspecialchars($vehicle['chassis_number'] ?? '') ?></span></p>
                </div>
            </div>

            <!-- Display Success/Error Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-600 text-green-800 p-4 mb-6 rounded-r-lg shadow-sm"
                    role="alert">
                    <p><i class="fas fa-check-circle mr-2"></i><?= $success_message ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-600 text-red-800 p-4 mb-6 rounded-r-lg shadow-sm" role="alert">
                    <p><i class="fas fa-exclamation-triangle mr-2"></i><?= $error_message ?></p>
                </div>
            <?php endif; ?>

            <form action="edit_vehicle.php" method="POST"
                class="bg-white shadow-xl rounded-xl p-8 border border-blue-100">
                <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">

                <div class="space-y-10">
                    <!-- Vehicle Information -->
                    <section>
                        <h2
                            class="text-xl font-bold text-blue-800 border-b-2 border-blue-200 pb-3 mb-6 flex items-center">
                            <i class="fas fa-car mr-3 text-blue-500"></i>Maklumat Asas</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="lot_number" class="form-label block mb-2">No. Lot</label>
                                <input type="text" id="lot_number" name="lot_number" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['lot_number'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="vehicle_model" class="form-label block mb-2">Model Kenderaan</label>
                                <input type="text" id="vehicle_model" name="vehicle_model" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['vehicle_model'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="chassis_number" class="form-label block mb-2">No. Casis</label>
                                <input type="text" id="chassis_number" name="chassis_number" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['chassis_number'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="engine_number" class="form-label block mb-2">No. Enjin</label>
                                <input type="text" id="engine_number" name="engine_number" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['engine_number'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="manufacturing_year" class="form-label block mb-2">Tahun Dibuat</label>
                                <input type="number" id="manufacturing_year" name="manufacturing_year"
                                    class="form-input"
                                    value="<?= htmlspecialchars((string) ($vehicle['manufacturing_year'] ?? '')) ?>">
                            </div>
                            <div>
                                <label for="engine_cc" class="form-label block mb-2">Kapasiti Enjin (CC)</label>
                                <input type="number" id="engine_cc" name="engine_cc" class="form-input"
                                    value="<?= htmlspecialchars((string) ($vehicle['engine_cc'] ?? '')) ?>">
                            </div>
                            <div>
                                <label for="color" class="form-label block mb-2">Warna</label>
                                <input type="text" id="color" name="color" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['color'] ?? '') ?>">
                            </div>
                        </div>
                    </section>

                    <!-- Customs Information -->
                    <section>
                        <h2
                            class="text-xl font-bold text-blue-800 border-b-2 border-blue-200 pb-3 mb-6 flex items-center">
                            <i class="fas fa-shield-alt mr-3 text-blue-500"></i>Maklumat Kastam</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="k8_number_full" class="form-label block mb-2">No. K8</label>
                                <input type="text" id="k8_number_full" name="k8_number_full" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['k8_number_full'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="k1_number" class="form-label block mb-2">No. K1</label>
                                <input type="text" id="k1_number" name="k1_number" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['k1_number'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="ap" class="form-label block mb-2">No. AP</label>
                                <input type="text" id="ap" name="ap" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['ap'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="tarikh_luput" class="form-label block mb-2">Tarikh Luput AP</label>
                                <input type="text" id="tarikh_luput" name="tarikh_luput" class="form-input datepicker"
                                    value="<?= htmlspecialchars($vehicle['tarikh_luput'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="kod_gudang" class="form-label block mb-2">Kod Gudang</label>
                                <select id="kod_gudang" name="kod_gudang" class="form-input">
                                    <option value="">Pilih Kod Gudang</option>
                                    <?php foreach ($gb_list as $gb): ?>
                                        <option value="<?= htmlspecialchars($gb['kod_gudang']) ?>"
                                            <?= (trim($vehicle['kod_gudang'] ?? '') == $gb['kod_gudang']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($gb['kod_gudang']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <label for="gbpekema_id" class="form-label block mb-2">Syarikat GB/PEKEMA (Nama Gudang)</label>
                                <select id="gbpekema_id" name="gbpekema_id" class="form-input" <?= $is_licensee ? 'disabled' : '' ?>>
                                    <option value="">Pilih Syarikat</option>
                                    <?php foreach ($gb_list as $gb): ?>
                                        <option value="<?= $gb['id'] ?>"
                                            <?= (isset($vehicle['gbpekema_id']) && $vehicle['gbpekema_id'] == $gb['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($gb['nama']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($is_licensee): ?>
                                    <input type="hidden" name="gbpekema_id" value="<?= $licensee_gb_id ?>">
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="condition_status" class="form-label block mb-2">Status Baharu/Terpakai</label>
                                <select id="condition_status" name="condition_status" class="form-input">
                                    <option value="USED" <?= ($vehicle['condition_status'] ?? '') == 'USED' ? 'selected' : '' ?>>Terpakai</option>
                                    <option value="NEW" <?= ($vehicle['condition_status'] ?? '') == 'NEW' ? 'selected' : '' ?>>Baharu</option>
                                </select>
                            </div>
                            <div>
                                <label for="import_date" class="form-label block mb-2">Tarikh Bond In</label>
                                <input type="text" id="import_date" name="import_date" class="form-input datepicker"
                                    value="<?= htmlspecialchars($vehicle['import_date'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="odometer" class="form-label block mb-2">Odometer (km)</label>
                                <input type="number" id="odometer" name="odometer" class="form-input"
                                    value="<?= htmlspecialchars((string) ($vehicle['odometer'] ?? '')) ?>">
                            </div>
                            <div>
                                <label for="stesen_asal" class="form-label block mb-2">Stesen Asal</label>
                                <input type="text" id="stesen_asal" name="stesen_asal" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['stesen_asal'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="tarikh_tamat_tempoh_gudang" class="form-label block mb-2">Tamat Tempoh Gudang</label>
                                <input type="text" id="tarikh_tamat_tempoh_gudang" name="tarikh_tamat_tempoh_gudang" class="form-input datepicker"
                                    value="<?= htmlspecialchars($vehicle['tarikh_tamat_tempoh_gudang'] ?? '') ?>">
                            </div>
                        </div>
                    </section>

                    <!-- Payment Information -->
                    <section>
                        <h2
                            class="text-xl font-bold text-blue-800 border-b-2 border-blue-200 pb-3 mb-6 flex items-center">
                            <i class="fas fa-money-check-alt mr-3 text-blue-500"></i>Maklumat Bayaran Cukai</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="payment_date" class="form-label block mb-2">Tarikh Bayar K1</label>
                                <input type="text" id="payment_date" name="payment_date" class="form-input datepicker"
                                    value="<?= htmlspecialchars($vehicle['payment_date'] ?? '') ?>">
                            </div>
                            <div>
                                <label for="harga_taksiran" class="form-label block mb-2">Harga Taksiran (RM)</label>
                                <input type="number" step="0.01" id="harga_taksiran" name="harga_taksiran" class="form-input"
                                    value="<?= htmlspecialchars((string) ($vehicle['harga_taksiran'] ?? '0.00')) ?>">
                            </div>
                            <div>
                                <label for="duti_import" class="form-label block mb-2">Duti Import (RM)</label>
                                <input type="number" step="0.01" id="duti_import" name="duti_import" class="form-input tax-calc"
                                    value="<?= htmlspecialchars((string) ($vehicle['duti_import'] ?? '0.00')) ?>">
                            </div>
                            <div>
                                <label for="duti_eksais" class="form-label block mb-2">Duti Eksais (RM)</label>
                                <input type="number" step="0.01" id="duti_eksais" name="duti_eksais" class="form-input tax-calc"
                                    value="<?= htmlspecialchars((string) ($vehicle['duti_eksais'] ?? '0.00')) ?>">
                            </div>
                            <div>
                                <label for="cukai_jualan" class="form-label block mb-2">Cukai Jualan (RM)</label>
                                <input type="number" step="0.01" id="cukai_jualan" name="cukai_jualan"
                                    class="form-input tax-calc"
                                    value="<?= htmlspecialchars((string) ($vehicle['cukai_jualan'] ?? '0.00')) ?>">
                            </div>
                            <div>
                                <label for="duty_rm" class="form-label block mb-2 font-bold text-blue-700">Jumlah Cukai (RM)</label>
                                <input type="number" step="0.01" id="duty_rm" name="duty_rm" class="form-input bg-blue-50 font-bold"
                                    value="<?= htmlspecialchars((string) ($vehicle['duty_rm'] ?? '0.00')) ?>" readonly>
                            </div>
                            <div>
                                <label for="receipt_number" class="form-label block mb-2">No. Resit</label>
                                <input type="text" id="receipt_number" name="receipt_number" class="form-input"
                                    value="<?= htmlspecialchars($vehicle['receipt_number'] ?? '') ?>">
                            </div>
                        </div>
                    </section>

                    <!-- Notes -->
                    <section>
                        <h2
                            class="text-xl font-bold text-blue-800 border-b-2 border-blue-200 pb-3 mb-6 flex items-center">
                            <i class="fas fa-sticky-note mr-3 text-blue-500"></i>Catatan</h2>
                        <div>
                            <textarea id="catatan" name="catatan" rows="4"
                                class="form-input"><?= htmlspecialchars($vehicle['catatan'] ?? '') ?></textarea>
                        </div>
                    </section>
                </div>

                <!-- Form Actions -->
                <div class="mt-10 pt-6 border-t border-blue-200 flex items-center justify-between">
                    <div>
                        <a href="vehicles.php"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300">
                            <i class="fas fa-arrow-left mr-2"></i>Kembali ke Senarai
                        </a>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="vehicle_details.php?id=<?= $vehicle['id'] ?>"
                            class="text-gray-600 font-medium py-2 px-4 hover:bg-gray-200 rounded-lg transition duration-300">Batal</a>
                        <button type="submit"
                            class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-6 rounded-lg transition duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i>Simpan Kemaskini
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            allowInput: true
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Automatic Tax Calculation
            const taxInputs = document.querySelectorAll('.tax-calc');
            const totalTaxInput = document.getElementById('duty_rm');

            if (taxInputs.length > 0 && totalTaxInput) {
                taxInputs.forEach(input => {
                    input.addEventListener('input', () => {
                        let total = 0;
                        taxInputs.forEach(ti => {
                            total += parseFloat(ti.value) || 0;
                        });
                        totalTaxInput.value = total.toFixed(2);
                    });
                });
            }
        });
    </script>
</body>

</html>