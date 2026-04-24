<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

// Auth check (standard for this project as per index.php)
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Logik untuk Muat Turun Fail Templat CSV
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_kenderaan.csv');
    $output = fopen('php://output', 'w');
    // Header CSV
    fputcsv($output, [
        'brand',
        'model',
        'chassis_no',
        'capacity',
        'fob_price',
        'freight_cost',
        'insurance_fee',
        'import_permit_no',
        'payment_date (YYYY-MM-DD)'
    ]);
    fclose($output);
    exit();
}

$success_message = '';
$error_message = '';
$imported_count = 0;
$skipped_rows = [];

// Logik untuk Memproses Fail CSV yang Dimuat Naik
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"])) {
    $gbpekema_id = $_POST['gbpekema_id'] ?? null;

    if (empty($gbpekema_id)) {
        $error_message = "Sila pilih syarikat terlebih dahulu.";
    } elseif ($_FILES["csv_file"]["error"] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES["csv_file"]["tmp_name"];
        $file_name = $_FILES["csv_file"]["name"];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);

        if (strtolower($file_extension) === 'csv') {
            $file_handle = fopen($file_tmp_path, "r");
            $header = fgetcsv($file_handle, 1000, ","); // Baca baris pengepala

            if ($header) {
                $row_number = 1;
                while (($data = fgetcsv($file_handle, 1000, ",")) !== FALSE) {
                    $row_number++;
                    // Pastikan bilangan lajur sepadan
                    if (count($data) >= 9) {
                        // Data mapping based on template
                        $brand = $data[0] ?? '';
                        $model = $data[1] ?? '';
                        $chassis = $data[2] ?? '';
                        $capacity = $data[3] ?? '';
                        $fob = floatval($data[4] ?? 0);
                        $freight = floatval($data[5] ?? 0);
                        $insurance = floatval($data[6] ?? 0);
                        $permit = $data[7] ?? '';
                        $p_date = $data[8] ?? date('Y-m-d');

                        // Pengiraan Cukai
                        $cif = $fob + $freight + $insurance;
                        $import_duty = $cif * 0.30;
                        $excise_duty = ($cif + $import_duty) * 0.90;
                        $sst = ($cif + $import_duty + $excise_duty) * 0.10;
                        $total_tax = $import_duty + $excise_duty + $sst;

                        // Insert into vehicle_inventory (matching index.php expectations)
                        // Note: Assuming vehicle_model is the column name for model and duty_rm for total_tax
                        $sql = "INSERT INTO vehicle_inventory 
                                (gbpekema_id, vehicle_model, chassis_no, capacity, duty_rm, duti_import, duti_eksais, cukai_jualan, payment_date, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param(
                                "isssdddds",
                                $gbpekema_id,
                                $model,
                                $chassis,
                                $capacity,
                                $total_tax,
                                $import_duty,
                                $excise_duty,
                                $sst,
                                $p_date
                            );

                            if ($stmt->execute()) {
                                $imported_count++;
                            } else {
                                $skipped_rows[] = "Baris $row_number (DB Error: " . $stmt->error . ")";
                            }
                            $stmt->close();
                        } else {
                            $skipped_rows[] = "Baris $row_number (Prepare Error)";
                        }
                    } else {
                        $skipped_rows[] = "Baris $row_number (Kolom tidak cukup)";
                    }
                }
                fclose($file_handle);

                if ($imported_count > 0) {
                    $success_message = "Berjaya mengimport $imported_count rekod kenderaan.";
                    if (!empty($skipped_rows)) {
                        $error_message = "Sesetengah baris dilangkau: " . implode('<br>', array_slice($skipped_rows, 0, 5));
                        if (count($skipped_rows) > 5)
                            $error_message .= "<br>... dan " . (count($skipped_rows) - 5) . " lagi.";
                    }
                } else {
                    $error_message = "Tiada rekod diimport. Sila pastikan format CSV anda betul.";
                }
            } else {
                $error_message = "Fail CSV kosong atau tidak sah.";
            }
        } else {
            $error_message = "Fail tidak sah. Sila muat naik fail CSV sahaja.";
        }
    } else {
        $error_message = "Ralat berlaku semasa memuat naik fail.";
    }
}

// Ambil senarai syarikat untuk dropdown
$companies = [];
$res = $conn->query("SELECT id, nama FROM gbpekema ORDER BY nama ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $companies[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV - MyPEKEMA Management Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #a855f7;
            --bg-glass: rgba(255, 255, 255, 0.7);
            --border-glass: rgba(255, 255, 255, 0.2);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #eef2ff, #f8fafc);
            min-height: 100vh;
        }

        .glass-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-glass);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }

        .ai-gradient-text {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-premium {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }

        .drop-zone {
            border: 2px dashed #cbd5e1;
            transition: all 0.3s;
        }

        .drop-zone:hover,
        .drop-zone.active {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900">

    <?php include 'topmenu.php'; ?>

    <header class="bg-white/40 backdrop-blur-md border-b border-white/20 sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-600 rounded-lg text-white">
                    <i class="fas fa-file-import text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-800">Gudang<span
                            class="text-indigo-600">Sys</span> CSV Utility</h1>
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider">Bulk Data Processing</p>
                </div>
            </div>
            <a href="index.php" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </header>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">

        <div class="mb-10 text-center max-w-2xl mx-auto">
            <h2 class="text-4xl font-extrabold text-slate-900 tracking-tight">
                Import Data <span class="ai-gradient-text">Pukal</span>
            </h2>
            <p class="text-slate-500 mt-2 text-lg">
                Muat naik fail CSV anda untuk memasukkan data kenderaan dengan pantas dan efisyen ke dalam sistem.
            </p>
        </div>

        <?php if ($success_message): ?>
            <div
                class="max-w-4xl mx-auto mb-8 bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl flex items-center gap-4 animate-in fade-in slide-in-from-top-4 duration-500">
                <div class="bg-emerald-500 text-white p-2 rounded-full">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <p class="font-bold">Berjaya!</p>
                    <p class="text-sm"><?= htmlspecialchars($success_message) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div
                class="max-w-4xl mx-auto mb-8 bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl flex items-center gap-4 animate-in fade-in slide-in-from-top-4 duration-500">
                <div class="bg-rose-500 text-white p-2 rounded-full">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <p class="font-bold">Perhatian</p>
                    <p class="text-sm"><?= $error_message ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">

            <!-- Step 1: Download Template -->
            <div class="glass-panel p-8 rounded-3xl flex flex-col justify-between">
                <div>
                    <div
                        class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-download text-xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-4">Langkah 1: Templat CSV</h3>
                    <p class="text-slate-500 mb-6 leading-relaxed">
                        Gunakan templat rasmi kami untuk memastikan struktur data anda sepadan dengan sistem. Jangan
                        ubah pengepala (header) CSV.
                    </p>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500"></i> Format Tarikh: YYYY-MM-DD
                        </li>
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500"></i> Gunakan titik (.) untuk perpuluhan
                        </li>
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <i class="fas fa-check-circle text-indigo-500"></i> Pastikan No. Casis unik
                        </li>
                    </ul>
                </div>
                <a href="import_csv.php?download_template=1"
                    class="w-full inline-flex items-center justify-center px-6 py-4 bg-white border-2 border-indigo-100 text-indigo-600 font-bold rounded-2xl hover:bg-indigo-50 transition-all gap-3 shadow-sm">
                    <i class="fas fa-file-csv"></i>
                    Muat Turun Templat (.csv)
                </a>
            </div>

            <!-- Step 2: Upload File -->
            <div class="glass-panel p-8 rounded-3xl">
                <form action="import_csv.php" method="post" enctype="multipart/form-data">
                    <div
                        class="w-12 h-12 bg-purple-100 text-purple-600 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-cloud-upload-alt text-xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-4">Langkah 2: Muat Naik</h3>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Pilih Syarikat <span
                                class="text-rose-500">*</span></label>
                        <select name="gbpekema_id" required
                            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <option value="">-- Pilih Syarikat --</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-8">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Fail CSV <span
                                class="text-rose-500">*</span></label>
                        <div class="drop-zone rounded-2xl p-8 text-center cursor-pointer" id="dropZone">
                            <input type="file" name="csv_file" id="csv_file" required class="hidden" accept=".csv">
                            <i class="fas fa-file-csv text-4xl text-slate-300 mb-3"></i>
                            <p class="text-sm text-slate-500" id="fileNameDisplay">Klik untuk pilih atau seret fail ke
                                sini</p>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full btn-premium text-white font-bold py-4 rounded-2xl flex items-center justify-center gap-3 transition-all">
                        <i class="fas fa-rocket"></i>
                        Mulakan Proses Import
                    </button>
                </form>
            </div>

        </div>

        <!-- Instructions / Help -->
        <div class="mt-12 max-w-4xl mx-auto glass-panel p-8 rounded-3xl bg-indigo-900/5">
            <h4 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
                <i class="fas fa-info-circle text-indigo-500"></i> Panduan Lajur CSV
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4 text-sm">
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="font-semibold text-slate-700">brand</span>
                    <span class="text-slate-500">Jenama (cth: TOYOTA)</span>
                </div>
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="font-semibold text-slate-700">model</span>
                    <span class="text-slate-500">Model (cth: VELLFIRE)</span>
                </div>
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="font-semibold text-slate-700">chassis_no</span>
                    <span class="text-slate-500">No. Casis unik</span>
                </div>
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="font-semibold text-slate-700">fob_price</span>
                    <span class="text-slate-500">Harga FOB (RM)</span>
                </div>
                <div class="flex justify-between border-b border-slate-200 pb-2">
                    <span class="font-semibold text-slate-700">payment_date</span>
                    <span class="text-slate-500">Tarikh Bayaran Cukai</span>
                </div>
            </div>
            <p class="mt-6 text-xs text-slate-400 italic">
                * Sistem akan mengira Duti Import (30%), Duti Eksais (90%) dan SST (10%) secara automatik berdasarkan
                harga FOB, Freight dan Insurans.
            </p>
        </div>

    </main>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('csv_file');
        const fileNameDisplay = document.getElementById('fileNameDisplay');

        dropZone.onclick = () => fileInput.click();

        fileInput.onchange = () => {
            if (fileInput.files.length > 0) {
                fileNameDisplay.innerText = fileInput.files[0].name;
                dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
            }
        };

        dropZone.ondragover = (e) => {
            e.preventDefault();
            dropZone.classList.add('active');
        };

        dropZone.ondragleave = () => {
            dropZone.classList.remove('active');
        };

        dropZone.ondrop = (e) => {
            e.preventDefault();
            dropZone.classList.remove('active');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                fileNameDisplay.innerText = e.dataTransfer.files[0].name;
                dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
            }
        };
    </script>

</body>

</html>
<?php
// No footer.php needed if we want it standalone or if it's missing
?>