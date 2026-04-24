<?php
session_start();
include 'config.php';

// Auth check
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
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
    $year = !empty($_POST['manufacturing_year']) ? intval($_POST['manufacturing_year']) : null;
    $fob = floatval($_POST['fob_price'] ?? 0);
    $p_date = $_POST['payment_date'];
    
    // New fields
    $lot_number = $_POST['lot_number'] ?? null;
    $kod_gudang = $_POST['kod_gudang'] ?? null;
    $color = $_POST['color'] ?? null;
    $engine_cc = !empty($_POST['engine_cc']) ? intval($_POST['engine_cc']) : null;
    $k8_number = $_POST['k8_number_full'] ?? null;
    $ap_number = $_POST['ap'] ?? null;
    $ap_expiry = $_POST['tarikh_luput'] ?? null;
    $receipt_number = $_POST['receipt_number'] ?? null;
    $pegawai_kastam = $_POST['pegawai_kastam'] ?? null;
    $nama_ejen = $_POST['nama_ejen'] ?? null;
    $catatan = $_POST['catatan'] ?? null;

    // Calculation logic (standard)
    $cif = $fob + 500 + 200; // Mock freight/ins
    $import_duty = $cif * 0.30;
    $excise_duty = ($cif + $import_duty) * 0.90;
    $sst = ($cif + $import_duty + $excise_duty) * 0.10;
    $total_tax = $import_duty + $excise_duty + $sst;

    try {
        $stmt = $conn->prepare("INSERT INTO vehicle_inventory (gbpekema_id, vehicle_model, chassis_number, engine_number, manufacturing_year, duty_rm, duti_import, duti_eksais, cukai_jualan, tpa_date, payment_date, lot_number, kod_gudang, color, engine_cc, k8_number_full, ap, tarikh_luput, receipt_number, pegawai_kastam, nama_ejen, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            // isssiddddssssisssssss : i=gbpekema, s=model, s=chassis, s=engine, i=year, d=duty, d=import, d=excise, d=sst, s=tpa, s=payment, s=lot, s=kod, s=color, i=cc, s=k8, s=ap, s=expiry, s=receipt, s=pegawai, s=ejen, s=catatan
            $stmt->bind_param("isssiddddssssisssssss", $gbpekema_id, $model, $chassis, $engine, $year, $total_tax, $import_duty, $excise_duty, $sst, $p_date, $p_date, $lot_number, $kod_gudang, $color, $engine_cc, $k8_number, $ap_number, $ap_expiry, $receipt_number, $pegawai_kastam, $nama_ejen, $catatan);
            
            if ($stmt->execute()) {
                $success_msg = "Kenderaan berjaya didaftarkan melalui automasi OCR!";
            } else {
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
            0% { top: 0; }
            100% { top: 100%; }
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
            0% { background-color: rgba(0, 45, 98, 0.2); }
            100% { background-color: transparent; }
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
                    <h1 class="text-xl font-bold tracking-tight text-slate-800">My<span class="text-blue-900">PEKEMA</span> AI Vision</h1>
                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wider">Document Intelligence System</p>
                </div>
            </div>
            <a href="index.php" class="text-sm font-bold text-blue-900 hover:text-blue-700 flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </header>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        
        <div class="mb-10 flex flex-col items-center text-center max-w-3xl mx-auto">
            <span class="px-4 py-1.5 bg-blue-50 text-blue-900 rounded-full text-xs font-black uppercase tracking-widest mb-4">Powered by OCR 2.0</span>
            <h2 class="text-4xl font-extrabold text-slate-900 tracking-tight">
                Pengecaman Dokumen <span class="ai-gradient-text">Pintar</span>
            </h2>
            <p class="text-slate-500 mt-3 text-lg">
                Muat naik imej Borang K1, Sijil AP, atau Invois. AI kami akan mengekstrak maklumat kenderaan secara automatik dalam masa beberapa saat.
            </p>
        </div>

        <?php if ($success_msg): ?>
            <div class="max-w-4xl mx-auto mb-8 bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl flex items-center gap-4">
                <div class="bg-emerald-500 text-white p-2 rounded-full"><i class="fas fa-check"></i></div>
                <p class="font-bold"><?= $success_msg ?></p>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="max-w-4xl mx-auto mb-8 bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl flex items-center gap-4">
                <div class="bg-rose-500 text-white p-2 rounded-full"><i class="fas fa-exclamation-triangle"></i></div>
                <p class="font-bold"><?= $error_msg ?></p>
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
                        <input type="file" id="imageUpload" class="hidden" accept="image/jpeg, image/png, application/pdf">
                        <div class="space-y-4">
                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-slate-400">
                                <i class="fas fa-file-upload text-3xl"></i>
                            </div>
                            <div>
                                <p class="text-slate-700 font-bold">Drag & Drop fail di sini</p>
                                <p class="text-sm text-slate-500">atau klik untuk pilih fail (JPG, PNG, PDF)</p>
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
                            <button onclick="resetUpload()" class="text-rose-500 font-bold hover:underline">Padam & Lakukan Semula</button>
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
                            <div id="progressBar" class="bg-blue-900 h-full transition-all duration-300" style="width: 45%"></div>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-6 rounded-3xl bg-amber-50 border-amber-200">
                    <h4 class="font-bold text-amber-800 flex items-center gap-2 mb-2">
                        <i class="fas fa-lightbulb"></i> Tips untuk Keputusan Terbaik
                    </h4>
                    <p class="text-sm text-amber-700 leading-relaxed">
                        Pastikan dokumen berada dalam keadaan rata, pencahayaan mencukupi, dan teks jelas kelihatan. AI berfungsi paling baik dengan dokumen rasmi JKDM.
                    </p>
                </div>

                <!-- Raw OCR Text Panel -->
                <div id="ocrRawPanel" class="hidden glass-panel p-6 rounded-3xl border border-blue-100">
                    <h4 class="font-bold text-slate-700 flex items-center gap-2 mb-3">
                        <i class="fas fa-file-alt text-blue-900"></i> Teks Mentah OCR
                        <button type="button" id="copyOcrBtn" onclick="copyOCRText()"
                            class="ml-auto flex items-center gap-1.5 px-3 py-1 bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-800 text-xs font-semibold rounded-lg border border-slate-200 hover:border-blue-300 transition-all">
                            <i class="fas fa-copy"></i> <span id="copyOcrLabel">Salin Semua</span>
                        </button>
                    </h4>
                    <textarea id="ocrRawText" rows="8"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-mono text-slate-600 resize-y outline-none focus:border-blue-400"
                        placeholder="Teks yang dikesan oleh OCR akan muncul di sini..."></textarea>
                    <div class="flex gap-2 mt-3">
                        <button type="button" onclick="reRunExtraction()"
                            class="flex-1 bg-blue-900 text-white text-sm font-bold py-2.5 rounded-xl flex items-center justify-center gap-2 hover:bg-blue-800">
                            <i class="fas fa-magic"></i> Uji Pengekstrakan Semula
                        </button>
                        <button type="button" onclick="document.getElementById('ocrRawText').value='';document.getElementById('ocrRawPanel').classList.add('hidden');"
                            class="px-4 bg-slate-200 text-slate-600 text-sm font-bold py-2.5 rounded-xl hover:bg-slate-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="text-xs text-amber-600 mt-2">
                        <i class="fas fa-lightbulb mr-1"></i>
                        Klik <strong>Salin Semua</strong> untuk menyalin teks ke papan klip, atau edit terus dan klik Uji semula.
                    </p>
                </div>
            </div>

            <!-- Paste & Extract Panel -->
            <div class="lg:col-span-2 glass-panel p-6 rounded-3xl border border-indigo-200 bg-indigo-50/60">
                <h4 class="font-bold text-indigo-800 flex items-center gap-2 mb-1">
                    <i class="fas fa-paste text-indigo-600"></i> Tampal Teks Dokumen (Alternatif Pantas)
                    <button type="button" id="pasteToggleBtn" onclick="togglePastePanel()"
                        class="ml-auto text-xs text-indigo-500 hover:text-indigo-700 font-semibold underline">Sembunyikan</button>
                </h4>
                <div id="pasteExtractBody">
                    <p class="text-xs text-indigo-600 mb-3 mt-1">Jika OCR gagal atau dokumen adalah PDF, <strong>salin teks dari dokumen</strong> dan tampal di bawah.</p>
                    <textarea id="pasteInputText" rows="4"
                        class="w-full bg-white border border-indigo-200 rounded-xl p-3 text-xs font-mono text-slate-700 resize-y outline-none focus:border-indigo-500"
                        placeholder="Contoh: Brand: TOYOTA, Model: VELLFIRE Z PREMIER, EngineNo: T24AP124713, ChassisNo: TAHA40-0012180, CCY :2393. Year:2025&#10;FOB VALUE = RM 56,685.74"></textarea>
                    <button type="button" onclick="extractFromPaste()"
                        class="mt-3 w-full bg-indigo-700 text-white text-sm font-bold py-3 rounded-xl flex items-center justify-center gap-2 hover:bg-indigo-800">
                        <i class="fas fa-magic"></i> Ekstrak Data dari Teks
                    </button>
                </div>
            </div>

            <!-- Right: Extraction Results & Form -->
            <div class="glass-panel p-8 rounded-3xl">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-xl font-bold text-slate-800">Pengesahan Data</h3>
                    <span class="px-3 py-1 bg-green-50 text-green-600 border border-green-100 rounded-full text-[10px] font-black uppercase">Sedia Disahkan</span>
                </div>

                <form action="ocr_upload.php" method="POST" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Syarikat Pengeksport/Pengimport</label>
                            <select name="gbpekema_id" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-900 outline-none transition-all">
                                <option value="">-- Pilih Syarikat --</option>
                                <?php foreach ($companies as $comp): ?>
                                    <option value="<?= $comp['id'] ?>"><?= htmlspecialchars($comp['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Jenama & Model</label>
                            <input type="text" name="vehicle_model" id="vehicle_model" placeholder="..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Tahun Buatan</label>
                            <input type="number" name="manufacturing_year" id="manufacturing_year" placeholder="..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">No. Casis</label>
                            <input type="text" name="chassis_no" id="chassis_no" placeholder="..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">No. Enjin</label>
                            <input type="text" name="engine_no" id="engine_no" placeholder="..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Warna (Color)</label>
                            <input type="text" name="color" id="color" placeholder="Cth: Pearl White" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">CC Enjin</label>
                            <input type="number" name="engine_cc" id="engine_cc" placeholder="Cth: 2500" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">No. Lot</label>
                            <input type="text" name="lot_number" id="lot_number" placeholder="Cth: L-102" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Kod Gudang</label>
                            <input type="text" name="kod_gudang" id="kod_gudang" placeholder="Cth: G-A1" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">No. Pendaftaran K8</label>
                            <input type="text" name="k8_number_full" id="k8_number_full" placeholder="Cth: B10D12008346" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">No. AP (Permit Kelulusan)</label>
                            <input type="text" name="ap" id="ap" placeholder="Cth: Q0102..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Tarikh Luput AP</label>
                            <input type="date" name="tarikh_luput" id="tarikh_luput" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">No. Resit / No. Daftar (Box 48)</label>
                            <input type="text" name="receipt_number" id="receipt_number" placeholder="Cth: WAF/2025/12/337" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Harga FOB (RM)</label>
                            <input type="number" step="0.01" name="fob_price" id="fob_price" placeholder="0.00" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-blue-900 focus:border-blue-950 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Tarikh Penerimaan Dokumen (TPA)</label>
                            <input type="date" name="payment_date" id="payment_date" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Pegawai Kastam Kelulusan</label>
                            <input type="text" name="pegawai_kastam" id="pegawai_kastam" placeholder="Cth: MOHD FIKRI HAKIM..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Nama Ejen Pengimport</label>
                            <input type="text" name="nama_ejen" id="nama_ejen" placeholder="Cth: RABIZA LOGISTICS..." class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Lain-lain Catatan (Opsional)</label>
                            <textarea name="catatan" id="catatan" rows="2" placeholder="Cth: Diterima oleh RASHID BIN NAYAN" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-blue-900 outline-none transition-all"></textarea>
                        </div>
                    </div>

                    <div class="p-6 bg-slate-50 rounded-2xl border border-dashed border-slate-200 mt-6">
                        <div class="flex justify-between items-center mb-4">
                            <p class="text-sm font-bold text-slate-700">Anggaran Cukai (Auto)</p>
                            <p class="text-lg font-black text-blue-900" id="estimated_tax">RM 0.00</p>
                        </div>
                        <p class="text-[10px] text-slate-400">Pengiraan berdasarkan Duti Import 30%, Eksais 90%, dan SST 10% mengikut harga FOB yang dikesan atau dimasukkan.</p>
                    </div>

                    <button type="submit" name="save_vehicle" class="w-full btn-ai text-white font-black py-4 rounded-2xl flex items-center justify-center gap-3 mt-8">
                        <i class="fas fa-check-double"></i>
                        SAHKAN & SIMPAN DATA
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
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
                if (file.type === 'application/pdf') {
                    imagePreview.src = 'https://upload.wikimedia.org/wikipedia/commons/8/87/PDF_file_icon.svg';
                    imagePreview.style.maxHeight = '200px';
                    imagePreview.style.objectFit = 'contain';
                    imagePreview.style.padding = '20px';
                } else {
                    imagePreview.src = e.target.result;
                    imagePreview.style.maxHeight = 'none';
                    imagePreview.style.objectFit = 'initial';
                    imagePreview.style.padding = '0';
                }
                dropZone.classList.add('hidden');
                previewContainer.classList.remove('hidden');
                
                if (file.type.startsWith('image/')) {
                    startRealOCR(file);
                } else {
                    alert('Info: OCR hanya menyokong fail imej (JPG/PNG). Sila masukkan data secara manual untuk fail PDF.');
                }
            };
            reader.readAsDataURL(file);
        }

        function resetUpload() {
            dropZone.classList.remove('hidden');
            previewContainer.classList.add('hidden');
            imageUpload.value = '';
            scannerLine.style.display = 'none';
            processingStatus.classList.add('hidden');
            progressBar.style.width = '0%';
            percentComplete.innerText = '0%';
        }

        // ── Image preprocessing: upscale + grayscale + contrast ──────────────
        async function preprocessImageForOCR(imageFile) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const img = new Image();
                    img.onload = () => {
                        const scale = Math.max(1.5, Math.min(3, 2400 / Math.max(img.width, img.height)));
                        const canvas = document.createElement('canvas');
                        canvas.width  = Math.round(img.width  * scale);
                        canvas.height = Math.round(img.height * scale);
                        const ctx = canvas.getContext('2d');
                        ctx.imageSmoothingEnabled = true;
                        ctx.imageSmoothingQuality = 'high';
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        // Grayscale + adaptive contrast boost
                        const id = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const d = id.data;
                        for (let i = 0; i < d.length; i += 4) {
                            let g = 0.299 * d[i] + 0.587 * d[i+1] + 0.114 * d[i+2];
                            g = Math.min(255, Math.max(0, (g - 128) * 1.6 + 128));
                            d[i] = d[i+1] = d[i+2] = g;
                        }
                        ctx.putImageData(id, 0, 0);
                        canvas.toBlob(blob => resolve(blob), 'image/png');
                    };
                    img.src = ev.target.result;
                };
                reader.readAsDataURL(imageFile);
            });
        }

        // ── Normalise raw OCR output ──────────────────────────────────────────
        function normalizeOCRText(raw) {
            return raw
                .replace(/\r\n/g, '\n').replace(/\r/g, '\n')
                .replace(/[|~`\^{}\[\]\\]/g, ' ')   // noise chars
                .replace(/[ \t]+/g, ' ')              // collapse spaces
                .replace(/\n[ \t]+/g, '\n')           // trim line starts
                .replace(/[ \t]+\n/g, '\n');          // trim line ends
        }

        async function startRealOCR(imageFile) {
            scannerLine.style.display = 'block';
            processingStatus.classList.remove('hidden');
            progressBar.style.width = '5%';
            percentComplete.innerText = 'Memproses imej...';

            try {
                // Step 1 – enhance image
                const enhanced = await preprocessImageForOCR(imageFile);
                progressBar.style.width = '20%';

                // Step 2 – Tesseract with better page-seg-mode
                const worker = await Tesseract.createWorker('eng', 1, {
                    logger: m => {
                        if (m.status === 'recognizing text') {
                            const p = 20 + (m.progress * 70);
                            progressBar.style.width = p + '%';
                            percentComplete.innerText = Math.floor(p) + '%';
                        }
                    }
                });
                await worker.setParameters({ tessedit_pageseg_mode: '6' });
                const { data: { text } } = await worker.recognize(enhanced);
                await worker.terminate();

                processExtractedText(text);
            } catch (error) {
                console.error('OCR Error:', error);
                alert('OCR gagal. Sila gunakan panel "Tampal Teks" di bawah.');
            } finally {
                scannerLine.style.display = 'none';
            }
        }

        function processExtractedText(rawText) {
            console.log('Raw OCR:', rawText);

            // Show in debug panel
            const rawPanel = document.getElementById('ocrRawPanel');
            const rawTA    = document.getElementById('ocrRawText');
            if (rawPanel && rawTA) { rawTA.value = rawText; rawPanel.classList.remove('hidden'); }

            const text  = normalizeOCRText(rawText);
            const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 1);
            const ext   = {};

            // ── STRATEGY 1: K1/JKDM fuzzy patterns on full text ──────────────
            // Each regex has alternatives to handle OCR spacing/character noise

            // Engine number
            const engM = text.match(/Engin(?:e\s*No?\.?|eNo)\s*:?\s*([A-Z0-9][A-Z0-9\s-]{4,25})/i)
                      || text.match(/No\.?\s*Enj?in\s*:?\s*([A-Z0-9][A-Z0-9\s-]{4,25})/i);
            if (engM) ext.engine = engM[1].replace(/\s/g, '');

            // Chassis number
            const chaM = text.match(/Chass?is\s*(?:No?\.?)?\s*:?\s*([A-Z0-9][A-Z0-9\s-]{4,25})/i)
                      || text.match(/(?:Frame|VIN|Casis)\s*(?:No?\.?)?\s*:?\s*([A-Z0-9][A-Z0-9\s-]{4,25})/i)
                      || text.match(/No\.?\s*Cas?is\s*:?\s*([A-Z0-9][A-Z0-9\s-]{4,25})/i);
            if (chaM) ext.chassis = chaM[1].replace(/\s/g, '');

            // Brand + Model combined: "Brand: TOYOTA, Model: VELLFIRE Z..."
            const brM  = text.match(/Brand\s*:?\s*([A-Z][A-Z0-9\s]{1,20}?)(?:,|Model)/i);
            const modM = text.match(/Model\s*:?\s*([A-Z0-9][A-Z0-9\s\/.-]{2,40}?)(?:,|\n|Engine|Chassis|$)/im);
            if (brM && modM) ext.model = brM[1].trim() + ' ' + modM[1].trim();
            else if (modM)   ext.model = modM[1].trim();

            // CC: "CCY :2393" or "CAPACITY: 2393" or "{CCY :2393"
            const ccM = text.match(/\{?\s*CC[Yy]?\s*:?\s*(\d{3,5})/i)
                     || text.match(/Capac?ity\s*[\s:]\s*(\d{3,5})/i);
            if (ccM) ext.cc = ccM[1];

            // Year
            const yrM = text.match(/\bYear\s*:?\s*(\d{4})/i)
                     || text.match(/\bTahun\s*(?:Buatan)?\s*:?\s*(\d{4})/i);
            if (yrM) ext.year = yrM[1];

            // FOB value → prefer "FOB VALUE = RM ..." then CIF as fallback
            const fobM = text.match(/FOB\s*VALUE\s*[=:]\s*RM\s*([\d,.]+)/i)
                      || text.match(/FOB\s*[=:]\s*RM\s*([\d,.]+)/i)
                      || text.match(/CIF\s*VALUE\s*[=:]\s*RM\s*([\d,.]+)/i);
            if (fobM) ext.fob = fobM[1].replace(/,/g, '');

            // K8 number: port-code format e.g. B10D04004096 (letter + 2 digits + letter + 8-12 digits)
            const k8M = text.match(/\b([BWKPS]\d{2}[A-Z]\d{8,12})\b/i);
            if (k8M) ext.k8 = k8M[1];

            // AP number: FPA / OSP / Q0 prefix
            const apM = text.match(/\b((?:FPA|OSP|Q0)[A-Z0-9]{5,15})\b/i)
                     || text.match(/\bAP\s*(?:No\.?|:)\s*([A-Z0-9]{8,20})/i);
            if (apM) ext.ap = apM[1];

            // Colour
            const colM = text.match(/(?:Colour|Color|Warna)\s*:?\s*([A-Z][A-Z\s]{2,20}?)(?:,|\n|$)/i);
            if (colM) ext.color = colM[1].trim();

            // ── STRATEGY 2: Generic label:value same-line ─────────────────────
            const inline = [
                { key:'model',   re: /(?:MAKE|MODEL|TYPE|JENIS)\s*[:\-]\s*([A-Z0-9][A-Z0-9\s\/.-]{2,40})/i },
                { key:'chassis', re: /(?:CHASSIS|FRAME|VIN|CASIS)\s*(?:NO\.?)?\s*[:\-]\s*([A-Z0-9][A-Z0-9-]{5,25})/i },
                { key:'engine',  re: /(?:ENGINE|ENJIN)\s*(?:NO\.?)?\s*[:\-]\s*([A-Z0-9][A-Z0-9-]{5,25})/i },
                { key:'year',    re: /(?:TAHUN|YEAR|BUATAN)\s*[:\-]\s*(\d{4})/i },
                { key:'fob',     re: /(?:NILAI|HARGA)\s*FOB\s*[:\-]\s*(?:RM)?\s*([\d,.]+)/i },
                { key:'cc',      re: /(?:ISIPADU|SILINDER|ENGINE\s*CC)\s*[:\-]\s*(\d{3,5})/i },
                { key:'color',   re: /WARNA\s*[:\-]\s*([A-Z][A-Z\s]{2,20})/i }
            ];
            for (const line of lines) {
                for (const { key, re } of inline) {
                    if (ext[key]) continue;
                    const m = line.match(re);
                    if (m) ext[key] = m[1].trim();
                }
            }

            // ── STRATEGY 3: Label on line N, value on line N+1 ───────────────
            const lblMap = {
                model:   /^(?:MODEL|TYPE|JENIS)\s*:?$/i,
                chassis: /^(?:CHASSIS|CASIS|VIN|FRAME)\s*(?:NO\.?)?\s*:?$/i,
                engine:  /^(?:ENGINE|ENJIN)\s*(?:NO\.?)?\s*:?$/i,
                year:    /^(?:YEAR|TAHUN|BUATAN)\s*:?$/i,
                k8:      /^(?:NO\.?\s*K8|K8)\s*:?$/i,
                ap:      /^(?:AP|PERMIT)\s*:?$/i,
                cc:      /^(?:CC|CCY|CAPACITY|ISIPADU)\s*:?$/i,
                color:   /^(?:COLOR|COLOUR|WARNA)\s*:?$/i
            };
            for (let i = 0; i < lines.length - 1; i++) {
                for (const [key, re] of Object.entries(lblMap)) {
                    if (ext[key]) continue;
                    if (re.test(lines[i]) && lines[i+1].length > 1) ext[key] = lines[i+1].trim();
                }
            }

            // ── STRATEGY 4: Token-based FORMAT fallback ───────────────────────
            // Find values by shape alone, no label needed
            if (!ext.chassis || !ext.engine) {
                // Chassis/engine look like: letter(s) + digits + maybe dashes, 8-25 chars total
                const seen = new Set();
                const tokRe = /\b([A-Z]{1,4}[0-9][A-Z0-9]{6,22})\b/gi;
                let tm;
                while ((tm = tokRe.exec(text)) !== null) {
                    const tok = tm[1].replace(/\s/g, '');
                    if (tok.length < 8 || tok.length > 25) continue;
                    if (ext.k8 && tok === ext.k8) continue;
                    if (ext.ap && tok === ext.ap) continue;
                    seen.add(tok);
                }
                const toks = [...seen];
                if (!ext.chassis && toks[0]) ext.chassis = toks[0];
                if (!ext.engine  && toks[1]) ext.engine  = toks[1];
            }

            if (!ext.year) {
                const yf = text.match(/\b(20[1-3][0-9])\b/);
                if (yf) ext.year = yf[1];
            }

            if (!ext.fob) {
                // Find the largest RM decimal value in the document
                const amts = [];
                const amRe = /\b(\d{1,7}[.,]\d{2})\b/g;
                let am;
                while ((am = amRe.exec(text)) !== null) {
                    const v = parseFloat(am[1].replace(',', '.'));
                    if (v > 100) amts.push(v);
                }
                if (amts.length) ext.fob = Math.max(...amts).toFixed(2);
            }

            // ── CLEANUP ───────────────────────────────────────────────────────
            if (ext.fob)     ext.fob     = String(ext.fob).replace(/[,\s]/g, '');
            if (ext.year)    ext.year     = (String(ext.year).match(/\d{4}/) || [])[0] || '';
            if (ext.cc)      ext.cc       = (String(ext.cc).match(/\d{3,5}/) || [])[0] || '';
            if (ext.chassis) ext.chassis  = ext.chassis.replace(/[^A-Z0-9-]/gi, '').toUpperCase();
            if (ext.engine)  ext.engine   = ext.engine.replace(/[^A-Z0-9-]/gi, '').toUpperCase();
            if (ext.model)   ext.model    = ext.model.replace(/\s+/g, ' ').trim().toUpperCase();
            if (ext.color)   ext.color    = ext.color.trim().toUpperCase();

            // ── FILL FORM FIELDS ──────────────────────────────────────────────
            if (ext.model)   fillField('vehicle_model', ext.model);
            if (ext.year)    fillField('manufacturing_year', ext.year);
            if (ext.chassis) fillField('chassis_no', ext.chassis);
            if (ext.engine)  fillField('engine_no', ext.engine);
            if (ext.color)   fillField('color', ext.color);
            if (ext.k8)      fillField('k8_number_full', ext.k8);
            if (ext.ap)      fillField('ap', ext.ap);
            if (ext.cc)      fillField('engine_cc', ext.cc);
            if (ext.fob) { fillField('fob_price', ext.fob); calculateTax(ext.fob); }

            const n = Object.values(ext).filter(v => v).length;
            progressBar.style.width = '100%';
            percentComplete.innerText = n > 0
                ? `100% - ${n} medan dikesan`
                : '100% - Tiada data dikesan. Gunakan panel Tampal Teks.';
            console.log('Extracted:', ext);
        }

        function startSimulatedOCR() { /* removed */ }

        function fillField(id, value) {
            const el = document.getElementById(id);
            if(el) {
                el.value = value;
                el.classList.add('field-highlight');
                setTimeout(() => el.classList.remove('field-highlight'), 2000);
            }
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
            document.getElementById('estimated_tax').innerText = 'RM ' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function reRunExtraction() {
            const text = document.getElementById('ocrRawText').value;
            if (!text.trim()) { alert('Tiada teks untuk diproses.'); return; }
            processExtractedText(text);
        }

        function copyOCRText() {
            const ta = document.getElementById('ocrRawText');
            const label = document.getElementById('copyOcrLabel');
            if (!ta.value.trim()) { alert('Tiada teks untuk disalin.'); return; }
            navigator.clipboard.writeText(ta.value).then(() => {
                label.textContent = 'Disalin!';
                document.getElementById('copyOcrBtn').classList.add('bg-green-50', 'text-green-700', 'border-green-300');
                setTimeout(() => {
                    label.textContent = 'Salin Semua';
                    document.getElementById('copyOcrBtn').classList.remove('bg-green-50', 'text-green-700', 'border-green-300');
                }, 2000);
            }).catch(() => {
                ta.select();
                document.execCommand('copy');
                label.textContent = 'Disalin!';
                setTimeout(() => { label.textContent = 'Salin Semua'; }, 2000);
            });
        }

        function extractFromPaste() {
            const text = document.getElementById('pasteInputText').value;
            if (!text.trim()) { alert('Sila tampal teks dokumen dahulu.'); return; }
            document.getElementById('ocrRawText').value = text;
            document.getElementById('ocrRawPanel').classList.remove('hidden');
            processExtractedText(text);
        }

        function togglePastePanel() {
            const body = document.getElementById('pasteExtractBody');
            const btn  = document.getElementById('pasteToggleBtn');
            if (body.style.display === 'none') {
                body.style.display = '';
                btn.textContent = 'Sembunyikan';
            } else {
                body.style.display = 'none';
                btn.textContent = 'Tunjukkan';
            }
        }
    </script>

</body>
</html>