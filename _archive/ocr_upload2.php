<?php
session_start();
include 'config.php';

// Auth check
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// RESTRICTION: Supervisor is view-only, Admin, User and Licensee are allowed
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'supervisor') {
    header("Location: vehicles.php");
    exit();
}

// Ambil senarai syarikat untuk dropdown
$companies = [];
$res = $conn->query("SELECT id, nama FROM gbpekema ORDER BY nama ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $companies[] = $row;
    }
}

// Handle form submission (saving to database)
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vehicle'])) {
    $gbpekema_id = $_POST['gbpekema_id'];
    $model = $_POST['vehicle_model'];
    $chassis = $_POST['chassis_no'];
    $engine = $_POST['engine_no'];
    $year = $_POST['manufacturing_year'];
    $fob = floatval($_POST['fob_price'] ?? 0);
    $p_date = $_POST['payment_date'];

    // Calculation logic (standard)
    $cif = $fob + 500 + 200; // Mock freight/ins
    $import_duty = $cif * 0.30;
    $excise_duty = ($cif + $import_duty) * 0.90;
    $sst = ($cif + $import_duty + $excise_duty) * 0.10;
    $total_tax = $import_duty + $excise_duty + $sst;

    try {
        $stmt = $conn->prepare("INSERT INTO vehicle_inventory (gbpekema_id, vehicle_model, chassis_no, duty_rm, duti_import, duti_eksais, cukai_jualan, payment_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            // Corrected types: i(int), s(string), s(string), d(double), d(double), d(double), d(double), s(string)
            $stmt->bind_param("issdddds", $gbpekema_id, $model, $chassis, $total_tax, $import_duty, $excise_duty, $sst, $p_date);

            if ($stmt->execute()) {
                $success_msg = "Kenderaan berjaya didaftarkan melalui automasi OCR!";
            } else {
                // This block might not be reached if exception is thrown, but safe to keep
                throw new Exception($stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $error_msg = "Ralat semasa menyimpan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI OCR Intelligence - MyPEKEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #002d62;
            --secondary: #1d4ed8;
            --accent: #ffd700;
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
            background: linear-gradient(135deg, #002d62 0%, #1d4ed8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Scanning Animation */
        .scanner-container {
            position: relative;
            overflow: hidden;
            border-radius: 1.5rem;
        }

        .scanning-line {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, #002d62, transparent);
            box-shadow: 0 0 15px #002d62;
            z-index: 10;
            animation: scan 3s linear infinite;
            display: none;
        }

        @keyframes scan {
            0% {
                top: 0;
            }

            100% {
                top: 100%;
            }
        }

        .drop-zone {
            border: 2px dashed #cbd5e1;
            transition: all 0.3s;
        }

        .drop-zone.active {
            border-color: var(--primary);
            background: rgba(0, 45, 98, 0.05);
        }

        .field-highlight {
            animation: highlight-pulse 2s ease-out;
        }

        @keyframes highlight-pulse {
            0% {
                background-color: rgba(0, 45, 98, 0.2);
            }

            100% {
                background-color: transparent;
            }
        }

        .btn-ai {
            background: linear-gradient(135deg, #002d62 0%, #1d4ed8 100%);
            box-shadow: 0 4px 15px rgba(0, 45, 98, 0.4);
            transition: all 0.3s;
        }

        .btn-ai:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 45, 98, 0.6);
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900">

    <header class="bg-white/40 backdrop-blur-md border-b border-white/20 sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-900 rounded-lg text-white">
                    <i class="fas fa-robot text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-800">Gudang<span
                            class="text-blue-900">Sys</span> AI Vision</h1>
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider">Document Intelligence</p>
                </div>
            </div>
            <a href="index.php" class="text-sm font-bold text-blue-900 hover:text-blue-700 flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </header>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">

        <div class="mb-10 flex flex-col items-center text-center max-w-3xl mx-auto">
            <span
                class="px-4 py-1.5 bg-blue-50 text-blue-900 rounded-full text-xs font-black uppercase tracking-widest mb-4">Powered
                by OCR 2.0</span>
            <h2 class="text-4xl font-extrabold text-slate-900 tracking-tight">
                Pengecaman Dokumen <span class="ai-gradient-text">Pintar</span>
            </h2>
            <p class="text-slate-500 mt-3 text-lg">
                Muat naik imej Borang K1, Sijil AP, atau Invois. AI kami akan mengekstrak maklumat kenderaan secara
                automatik dalam masa beberapa saat.
            </p>
        </div>

        <?php if ($success_msg): ?>
            <div
                class="max-w-4xl mx-auto mb-8 bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl flex items-center gap-4">
                <div class="bg-emerald-500 text-white p-2 rounded-full"><i class="fas fa-check"></i></div>
                <p class="font-bold"><?= $success_msg ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

            <!-- Left: Upload & Preview -->
            <div class="space-y-6">
                <div class="glass-panel p-8 rounded-3xl relative">
                    <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-camera text-blue-900"></i> Muat Naik Dokumen
                    </h3>

                    <div id="dropZone" class="drop-zone rounded-2xl p-12 text-center cursor-pointer mb-6">
                        <input type="file" id="imageUpload" class="hidden" accept="image/*">
                        <div class="space-y-4">
                            <div
                                class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-slate-400">
                                <i class="fas fa-file-image text-3xl"></i>
                            </div>
                            <div>
                                <p class="text-slate-700 font-bold">Seret & Lepas Imej</p>
                                <p class="text-sm text-slate-500">atau klik untuk pilih fail (JPG, PNG)</p>
                            </div>
                        </div>
                    </div>

                    <div id="previewContainer" class="hidden">
                        <div class="scanner-container bg-black/5 p-2 border border-slate-200">
                            <div class="scanning-line" id="scannerLine"></div>
                            <img id="imagePreview" src="#" alt="Pratonton" class="w-full h-auto rounded-xl">
                        </div>
                        <div class="mt-4 flex justify-between items-center text-sm">
                            <span id="fileName" class="text-slate-500 font-medium italic">dokumen.jpg</span>
                            <button onclick="resetUpload()" class="text-rose-500 font-bold hover:underline">Padam &
                                Lakukan Semula</button>
                        </div>
                    </div>

                    <div id="processingStatus" class="mt-6 hidden">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-bold text-blue-900 flex items-center gap-2">
                                <i class="fas fa-brain animate-pulse"></i> AI Mengekstrak Data...
                            </span>
                            <span class="text-xs font-bold text-slate-400" id="percentComplete">45%</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div id="progressBar" class="bg-blue-900 h-full transition-all duration-300"
                                style="width: 45%"></div>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-6 rounded-3xl bg-amber-50 border-amber-200">
                    <h4 class="font-bold text-amber-800 flex items-center gap-2 mb-2">
                        <i class="fas fa-lightbulb"></i> Tips untuk Keputusan Terbaik
                    </h4>
                    <p class="text-sm text-amber-700 leading-relaxed">
                        Pastikan dokumen berada dalam keadaan rata, pencahayaan mencukupi, dan teks jelas kelihatan. AI
                        berfungsi paling baik dengan dokumen rasmi JKDM.
                    </p>
                </div>
            </div>

            <!-- Right: Extraction Results & Form -->
            <div class="glass-panel p-8 rounded-3xl">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-xl font-bold text-slate-800">Pengesahan Data</h3>
                    <span
                        class="px-3 py-1 bg-green-50 text-green-600 border border-green-100 rounded-full text-[10px] font-black uppercase">Sedia
                        Disahkan</span>
                </div>

                <form action="ocr_upload.php" method="POST" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label
                                class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Syarikat
                                Pengeksport/Pengimport</label>
                            <select name="gbpekema_id" required
                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-900 outline-none transition-all">
                                <option value="">-- Pilih Syarikat --</option>
                                <?php foreach ($companies as $comp): ?>
                                    <option value="<?= $comp['id'] ?>"><?= htmlspecialchars($comp['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Jenama
                                & Model</label>
                            <input type="text" name="vehicle_model" id="vehicle_model" placeholder="..."
                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Tahun
                                Buatan</label>
                            <input type="number" name="manufacturing_year" id="manufacturing_year" placeholder="..."
                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">No.
                                Casis</label>
                            <input type="text" name="chassis_no" id="chassis_no" placeholder="..."
                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">No.
                                Enjin</label>
                            <input type="text" name="engine_no" id="engine_no" placeholder="..."
                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Harga
                                FOB (RM)</label>
                            <input type="number" step="0.01" name="fob_price" id="fob_price" placeholder="0.00"
                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-blue-900 focus:border-blue-950 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Tarikh
                                Bayaran</label>
                            <input type="date" name="payment_date" id="payment_date"
                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>
                    </div>

                    <div class="p-6 bg-slate-50 rounded-2xl border border-dashed border-slate-200 mt-6">
                        <div class="flex justify-between items-center mb-4">
                            <p class="text-sm font-bold text-slate-700">Anggaran Cukai (Auto)</p>
                            <p class="text-lg font-black text-blue-900" id="estimated_tax">RM 0.00</p>
                        </div>
                        <p class="text-[10px] text-slate-400">Pengiraan berdasarkan Duti Import 30%, Eksais 90%, dan SST
                            10% mengikut harga FOB yang dikesan atau dimasukkan.</p>
                    </div>

                    <button type="submit" name="save_vehicle"
                        class="w-full btn-ai text-white font-black py-4 rounded-2xl flex items-center justify-center gap-3 mt-8">
                        <i class="fas fa-check-double"></i>
                        SAHKAN & SIMPAN DATA
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        const dropZone = document.getElementById('dropZone');
        const imageUpload = document.getElementById('imageUpload');
        const previewContainer = document.getElementById('previewContainer');
        const imagePreview = document.getElementById('imagePreview');
        const scannerLine = document.getElementById('scannerLine');
        const processingStatus = document.getElementById('processingStatus');
        const progressBar = document.getElementById('progressBar');
        const percentComplete = document.getElementById('percentComplete');

        dropZone.onclick = () => imageUpload.click();

        imageUpload.onchange = (e) => {
            const file = e.target.files ? e.target.files[0] : null;
            if (file) handleFile(file);
        };

        dropZone.ondragover = (e) => { e.preventDefault(); dropZone.classList.add('active'); };
        dropZone.ondragleave = () => dropZone.classList.remove('active');
        dropZone.ondrop = (e) => {
            e.preventDefault();
            dropZone.classList.remove('active');
            if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
        };

        function handleFile(file) {
            document.getElementById('fileName').innerText = file.name;
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                dropZone.classList.add('hidden');
                previewContainer.classList.remove('hidden');
                startSimulatedOCR();
            };
            reader.readAsDataURL(file);
        }

        function resetUpload() {
            dropZone.classList.remove('hidden');
            previewContainer.classList.add('hidden');
            imageUpload.value = '';
            scannerLine.style.display = 'none';
            processingStatus.classList.add('hidden');
        }

        function startSimulatedOCR() {
            scannerLine.style.display = 'block';
            processingStatus.classList.remove('hidden');
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress >= 100) {
                    progress = 100;
                    clearInterval(interval);
                    finishOCR();
                }
                progressBar.style.width = progress + '%';
                percentComplete.innerText = Math.floor(progress) + '%';
            }, 300);
        }

        function finishOCR() {
            scannerLine.style.display = 'none';
            // Simulated Data Extraction
            const mockData = {
                model: "TOYOTA VELLFIRE ZG 2.5",
                year: 2022,
                chassis: "AGH30-1049283",
                engine: "2AR-FE",
                fob: 85000.00,
                date: new Date().toISOString().split('T')[0]
            };

            fillField('vehicle_model', mockData.model);
            setTimeout(() => fillField('manufacturing_year', mockData.year), 400);
            setTimeout(() => fillField('chassis_no', mockData.chassis), 800);
            setTimeout(() => fillField('engine_no', mockData.engine), 1200);
            setTimeout(() => {
                fillField('fob_price', mockData.fob);
                calculateTax(mockData.fob);
            }, 1600);
            setTimeout(() => fillField('payment_date', mockData.date), 2000);
        }

        function fillField(id, value) {
            const el = document.getElementById(id);
            el.value = value;
            el.classList.add('field-highlight');
            setTimeout(() => el.classList.remove('field-highlight'), 2000);
        }

        document.getElementById('fob_price').oninput = (e) => calculateTax(e.target.value);

        function calculateTax(fob) {
            const f = parseFloat(fob) || 0;
            if (f === 0) {
                document.getElementById('estimated_tax').innerText = 'RM 0.00';
                return;
            }
            const cif = f + 700;
            const duty = cif * 0.30;
            const excise = (cif + duty) * 0.90;
            const sst = (cif + duty + excise) * 0.10;
            const total = duty + excise + sst;
            document.getElementById('estimated_tax').innerText = 'RM ' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    </script>

</body>

</html>
<?php
// Note:proses_ocr.php would be the real backend for this in a production environment.
?>