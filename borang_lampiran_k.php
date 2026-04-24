<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$vehicle = null;

if ($vehicle_id > 0) {
    $sql = "SELECT v.*, g.nama as gbpekema_nama, g.kod_gudang
            FROM vehicle_inventory v 
            LEFT JOIN gbpekema g ON v.gbpekema_id = g.id 
            WHERE v.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $vehicle = $result->fetch_assoc();
    }
    $stmt->close();
}

$tarikh_semasa = date('d/m/Y');
$jenis_barangan = '';
if ($vehicle) {
    $jenis_barangan = ($vehicle['vehicle_model'] ?? '') . "\nCasis: " . ($vehicle['chassis_number'] ?? '') . "\nEnjin: " . ($vehicle['engine_number'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Lampiran K - Permohonan Penyimpanan Barang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f4f6;
            color: #000;
        }
        .paper-container {
            background-color: white;
            width: 210mm;
            min-height: 297mm;
            margin: 2rem auto;
            padding: 15mm 20mm;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header-ref {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 20px;
            line-height: 1.2;
            text-align: right;
        }
        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 30px;
            text-decoration: underline;
        }
        .section-title {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 10pt;
            text-decoration: underline;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            vertical-align: top;
        }
        .no-border, .no-border td {
            border: none;
            padding: 4px 0;
        }
        .editable-field {
            cursor: text;
            min-height: 1.2em;
            display: inline-block;
            width: 100%;
            outline: none;
            white-space: pre-wrap;
        }
        .editable-field:hover { background-color: #fdfde8; }
        .editable-field:focus {
            background-color: #fffde0;
            border-bottom: 1px dashed #999;
        }
        @media print {
            body { background-color: transparent; }
            .paper-container {
                margin: 0; padding: 10mm; box-shadow: none; width: 100%;
            }
            .no-print { display: none !important; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <?php include 'topmenu.php'; ?>
    </div>
    <!-- Action Bar -->
    <div class="fixed top-0 left-0 right-0 bg-white border-b shadow-sm p-4 flex justify-between items-center no-print z-10 hidden sm:flex">
        <div class="flex items-center gap-4">
            <button onclick="window.close()" class="text-gray-600 hover:text-gray-900 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </button>
            <h2 class="text-lg font-bold text-gray-800">Cetak Borang Lampiran K</h2>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md shadow-sm flex items-center gap-2 transition-colors">
                <i class="fas fa-print"></i> Cetak Dokumen
            </button>
        </div>
    </div>

    <!-- Mobile Action Button -->
    <button onclick="window.print()" class="fixed bottom-6 right-6 bg-blue-600 text-white p-4 rounded-full shadow-lg no-print z-50 sm:hidden">
        <i class="fas fa-print text-xl"></i>
    </button>

    <div class="mt-20 print:mt-0 max-w-[210mm] mx-auto">
        <div class="paper-container">

            <div class="header-ref">
                LAMPIRAN K<br>
                PTK BIL. 53 - PER: 17.10, 25.7
            </div>

            <div class="form-title">
                PERMOHONAN PENYIMPANAN BARANG-BARANG TELAH<br>DIBAYAR DUTI CUKAI
            </div>

            <div class="section-title" style="text-decoration: none;">BAHAGIAN A</div>
            
            <table class="no-border mb-6">
                <tr>
                    <td style="width: 200px; font-weight: bold;">NAMA SYARIKAT</td>
                    <td style="width: 10px;">:</td>
                    <td>
                        <div class="editable-field" contenteditable="true"><?= htmlspecialchars($vehicle['gbpekema_nama'] ?? '') ?></div>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">KOD GUDANG</td>
                    <td>:</td>
                    <td>
                        <div class="editable-field" contenteditable="true"><?= htmlspecialchars($vehicle['kod_gudang'] ?? '') ?></div>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">TARIKH PERMOHONAN</td>
                    <td>:</td>
                    <td>
                        <div class="editable-field" contenteditable="true"><?= $tarikh_semasa ?></div>
                    </td>
                </tr>
            </table>

            <div style="font-weight: bold; margin-bottom: 10px; font-size: 10pt;">BUTIRAN BARANGAN TERLIBAT :</div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%; text-align: center;">Jenis Barangan<br><span style="font-weight: normal; font-size: 9pt;">(bagi kenderaan sila nyatakan no.chasis dan<br>no. enjin)</span></th>
                        <th style="width: 15%; text-align: center; vertical-align: middle;">Kuantiti</th>
                        <th style="width: 25%; text-align: center; vertical-align: middle;">Tarikh Pertama Masuk Gudang</th>
                        <th style="width: 20%; text-align: center; vertical-align: middle;">No. K8</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: left; padding: 10px;">
                            <div class="editable-field" contenteditable="true" style="min-height: 80px;"><?= htmlspecialchars($jenis_barangan) ?></div>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <div class="editable-field" contenteditable="true" style="text-align: center;">1</div>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <div class="editable-field" contenteditable="true" style="text-align: center;"><?= isset($vehicle['import_date']) && $vehicle['import_date'] != '0000-00-00' ? date('d/m/Y', strtotime($vehicle['import_date'])) : '' ?></div>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <div class="editable-field" contenteditable="true" style="text-align: center;"><?= htmlspecialchars($vehicle['k8_number_full'] ?? '') ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="no-border" style="margin-top: 20px; line-height: 1.8;">
                <tr>
                    <td style="width: 200px; font-weight: bold;">ALASAN PERMOHONAN</td>
                    <td style="width: 10px;">:</td>
                    <td><span class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #ccc;"></span></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">TEMPOH YANG DIPERLUKAN</td>
                    <td>:</td>
                    <td><span class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #ccc;"></span></td>
                </tr>
            </table>

            <div class="section-title" style="margin-top: 40px; text-decoration: none;">BAHAGIAN B</div>
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 10pt;">ULASAN PEGAWAI YANG MENGAWAL GB:</div>
            
            <div class="editable-field" contenteditable="true" style="width: 100%; border: 1px solid black; min-height: 100px; padding: 10px; margin-bottom: 40px;"></div>

            <div class="section-title" style="text-decoration: none;">BAHAGIAN C</div>
            <div style="font-weight: bold; margin-bottom: 20px; font-size: 10pt; text-transform: uppercase;">
                PERTIMBANGAN PERMOHONAN OLEH KETUA BAHAGIAN/KETUA CAWANGAN/ KETUA STESEN
            </div>

            <div style="line-height: 2; margin-bottom: 40px; font-size: 10pt;">
                Permohonan diluluskan / tidak diluluskan mulai <span class="editable-field" contenteditable="true" style="width: 150px; border-bottom: 1px dotted #ccc;"></span> 
                hingga <span class="editable-field" contenteditable="true" style="width: 150px; border-bottom: 1px dotted #ccc;"></span>
            </div>

            <div style="margin-top: 50px;">
                <span style="display: inline-block; width: 300px; border-bottom: 1px dotted #000;"></span><br>
                (Nama: <span class="editable-field" contenteditable="true" style="width: 250px;"></span>)<br><br><br>
                <span style="display: inline-block; width: 300px; border-bottom: 1px dotted #000;"></span><br>
                (Jawatan: <span class="editable-field" contenteditable="true" style="width: 250px;"></span>)
            </div>

        </div>
    </div>
</body>
</html>
