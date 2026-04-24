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
    // Determine which table to query
    $is_archive = isset($_GET['archive']) && $_GET['archive'] == '1';
    $table_name = $is_archive ? "vehicle_archive" : "vehicle_inventory";

    $sql = "SELECT v.*, g.nama as gbpekema_nama, g.kod_gudang
            FROM $table_name v 
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
$duti_import = 0;
$duti_eksais = 0;
$cukai_jualan = 0;
$jumlah_cukai = 0;

if ($vehicle) {
    $jenis_barangan = ($vehicle['vehicle_model'] ?? '') . "\nCasis: " . ($vehicle['chassis_number'] ?? '') . "\nEnjin: " . ($vehicle['engine_number'] ?? '');
    $duti_import = isset($vehicle['duti_import']) ? (float)$vehicle['duti_import'] : 0;
    $duti_eksais = isset($vehicle['duti_eksais']) ? (float)$vehicle['duti_eksais'] : 0;
    $cukai_jualan = isset($vehicle['cukai_jualan']) ? (float)$vehicle['cukai_jualan'] : 0;
    $jumlah_cukai = $duti_import + $duti_eksais + $cukai_jualan;
}

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Lampiran L - Pelupusan/Pemusnahan & Remisi</title>
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
            margin-bottom: 20px;
        }
        .section-title {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 10pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid black;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            text-align: center;
            vertical-align: middle;
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
        .page-break {
            page-break-before: always;
        }
        @media print {
            body { background-color: transparent; }
            .paper-container {
                margin: 0; padding: 10mm; box-shadow: none; width: 100%; min-height: 0;
            }
            .no-print { display: none !important; }
            @page { size: A4 portrait; margin: 0; }
            .page-break { page-break-before: always; }
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
            <h2 class="text-lg font-bold text-gray-800">Cetak Borang Lampiran L</h2>
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
        <!-- PAGE 1 -->
        <div class="paper-container">

            <div class="header-ref">
                LAMPIRAN L<br>
                PTK BIL. 53 - PER: 22.1<br>
                1
            </div>

            <div class="form-title">
                PERMOHONAN UNTUK PELUPUSAN / PEMUSNAHAN DAN<br>
                REMISI DUTI / CUKAI
            </div>

            <div class="section-title">1. Maklumat Gudang Berlesen Barangan Disimpan</div>
            <table class="no-border" style="margin-left: 15px; width: 95%;">
                <tr>
                    <td style="width: 150px;">1.1 Nama dan Alamat<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Gudang</td>
                    <td style="width: 10px;">:</td>
                    <td>
                        <div class="editable-field" contenteditable="true" style="min-height: 40px; border-bottom: 1px dotted #ccc;"><?= htmlspecialchars($vehicle['gbpekema_nama'] ?? '') ?></div>
                    </td>
                </tr>
                <tr>
                    <td>1.2 Jenis Gudang</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #ccc;">Gudang Berlesen Awam</div></td>
                </tr>
            </table>

            <div class="section-title">2. Maklumat Pemilik Barang</div>
            <table class="no-border" style="margin-left: 15px; width: 95%;">
                <tr>
                    <td style="width: 150px;">2.1 Nama Syarikat</td>
                    <td style="width: 10px;">:</td>
                    <td>
                        <div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #ccc;"><?= htmlspecialchars($vehicle['gbpekema_nama'] ?? '') ?></div>
                    </td>
                </tr>
                <tr>
                    <td>2.2 No Sijil Perniagaan /<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;No Daftar Syarikat</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #ccc;"></div></td>
                </tr>
                <tr>
                    <td>2.3 Alamat Syarikat</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="min-height: 40px; border-bottom: 1px dotted #ccc;"></div></td>
                </tr>
            </table>

            <div class="section-title">3. Sebab-sebab permohonan :</div>
            <div class="editable-field" contenteditable="true" style="width: 100%; border: 1px solid black; min-height: 80px; padding: 10px; margin-bottom: 15px;"></div>

            <div class="section-title">4. Kaedah pelupusan atau pemusnahan dan pusat pemusnahan yang dicadangkan oleh syarikat :</div>
            <div class="editable-field" contenteditable="true" style="width: 100%; border: 1px solid black; min-height: 80px; padding: 10px; margin-bottom: 15px;"></div>

            <div class="section-title">5. Bersama-sama ini disertakan dokumen-dokumen seperti berikut:</div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">BIL.</th>
                        <th style="width: 45%;">SENARAI DOKUMEN</th>
                        <th style="width: 10%;">ADA (/)</th>
                        <th style="width: 10%;">TIADA (X)</th>
                        <th style="width: 30%;">CATATAN</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;">5.1</td>
                        <td>Surat Permohonan daripada pemilik barang dan GBA</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">/</div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5.2</td>
                        <td>Laporan daripada agensi berkaitan (jika berkenaan)</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">X</div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5.3</td>
                        <td>Gambar</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">/</div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5.4</td>
                        <td>Borang Kastam No.8 (penerimaan)</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">/</div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5.5</td>
                        <td>Inbois dan packing list</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">/</div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5.6</td>
                        <td>Pengesahan pembayaran survey (jika berkenaan)</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">X</div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5.7</td>
                        <td>Salinan Polisi Insuran (jika berkenaan)</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">X</div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5.8</td>
                        <td>Surat kaedah pelupusan atau pemusnahan yang dicadangkan oleh syarikat</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">/</div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5.9</td>
                        <td>Surat klasifikasi dan penilaian negeri perlu disertakan bagi jualan scrap (jika berkenaan)</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">X</div></td>
                        <td><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PAGE 2 -->
        <div class="paper-container page-break" style="margin-top: 2rem;">
            <div class="header-ref">
                LAMPIRAN L<br>
                PTK BIL. 53 - PER: 22.1<br>
                2
            </div>

            <div class="section-title">6. Maklumat Barang</div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">BIL</th>
                        <th style="width: 15%;">TARIF KOD</th>
                        <th style="width: 10%;">KUANTITI</th>
                        <th style="width: 15%;">NILAI (RM)</th>
                        <th style="width: 12%;">DUTI IMPORT<br>(RM)</th>
                        <th style="width: 12%;">DUTI EKSAIS<br>(RM)</th>
                        <th style="width: 12%;">CUKAI JUALAN<br>(RM)</th>
                        <th style="width: 19%;">JUMLAH DUTI/ CUKAI<br>(RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">1</div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">1</div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td style="text-align: right;"><div class="editable-field" contenteditable="true"><?= number_format($duti_import, 2) ?></div></td>
                        <td style="text-align: right;"><div class="editable-field" contenteditable="true"><?= number_format($duti_eksais, 2) ?></div></td>
                        <td style="text-align: right;"><div class="editable-field" contenteditable="true"><?= number_format($cukai_jualan, 2) ?></div></td>
                        <td style="text-align: right; background-color: #f9fafb;"><div class="editable-field font-bold" contenteditable="true"><?= number_format($jumlah_cukai, 2) ?></div></td>
                    </tr>
                </tbody>
            </table>

            <div class="section-title">7. Maklumat Pengimportan</div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">BIL</th>
                        <th style="width: 25%;">DAGANGAN</th>
                        <th style="width: 14%;">TARIKH IMPORT</th>
                        <th style="width: 14%;">TARIKH MASUK<br>GUDANG</th>
                        <th style="width: 14%;">TARIKH MOHON<br>REMISI / PELUPUSAN</th>
                        <th style="width: 14%;">TARIKH<br>PEMERIKSAAN<br>FIZIKAL</th>
                        <th style="width: 14%;">TARIKH LUPUT<br>BARANG (SEKIRANYA<br>BERKENAAN)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">1</div></td>
                        <td style="text-align: left; padding: 4px;">
                            <div class="editable-field" contenteditable="true" style="font-size: 8pt;"><?= htmlspecialchars($jenis_barangan) ?></div>
                        </td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"><?= isset($vehicle['k8_date']) ? date('d/m/Y', strtotime($vehicle['k8_date'])) : '' ?></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"><?= isset($vehicle['import_date']) && $vehicle['import_date'] != '0000-00-00' ? date('d/m/Y', strtotime($vehicle['import_date'])) : '' ?></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"><?= $tarikh_semasa ?></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true"></div></td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top: 40px; margin-bottom: 30px;">
                Dengan ini saya membuat akuan bahawa butir-butir di atas adalah betul dan benar.
            </div>

            <table class="no-border" style="width: 100%; margin-top: 20px;">
                <tr>
                    <td style="width: 400px; padding-bottom: 20px;">
                        <span style="display: inline-block; width: 300px; border-bottom: 1px dotted #000;"></span><br>
                        (Tandatangan Pemohon)
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td style="padding-bottom: 10px;">
                        Nama dan Jawatan : <span class="editable-field" contenteditable="true" style="width: 250px; border-bottom: 1px dotted #ccc;"></span>
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td style="padding-bottom: 10px;">
                        No. Kad Pengenalan / No. Pasport : <span class="editable-field" contenteditable="true" style="width: 200px; border-bottom: 1px dotted #ccc;"></span>
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td style="padding-bottom: 10px;">
                        Cop Syarikat : <span class="editable-field" contenteditable="true" style="width: 250px; border-bottom: 1px dotted #ccc; height: 60px; vertical-align: bottom;"></span>
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td>
                        Tarikh :  <span class="editable-field" contenteditable="true" style="width: 250px; border-bottom: 1px dotted #ccc;"><?= $tarikh_semasa ?></span>
                    </td>
                    <td></td>
                </tr>
            </table>

        </div>
    </div>
</body>
</html>
