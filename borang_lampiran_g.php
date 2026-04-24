<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Ini borang Lampiran G, borang pemeriksaaan berikutan Lampiran F
$gb_id = isset($_GET['gb_id']) ? intval($_GET['gb_id']) : 0;
$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$company = null;

if ($vehicle_id > 0 && $gb_id == 0) {
    // Kalau pengguna buka dari rekod kenderaan, cari gb_id
    $is_archive = isset($_GET['archive']) && $_GET['archive'] == '1';
    $table_name = $is_archive ? "vehicle_archive" : "vehicle_inventory";
    
    $sql_v = "SELECT gbpekema_id FROM $table_name WHERE id = ?";
    $stmt_v = $conn->prepare($sql_v);
    if($stmt_v) {
        $stmt_v->bind_param("i", $vehicle_id);
        $stmt_v->execute();
        $res_v = $stmt_v->get_result();
        if ($row_v = $res_v->fetch_assoc()) {
            $gb_id = $row_v['gbpekema_id'];
        }
        $stmt_v->close();
    }
}

if ($gb_id > 0) {
    $sql_c = "SELECT * FROM gbpekema WHERE id = ?";
    $stmt_c = $conn->prepare($sql_c);
    if($stmt_c) {
        $stmt_c->bind_param("i", $gb_id);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();
        if ($res_c->num_rows > 0) {
            $company = $res_c->fetch_assoc();
        }
        $stmt_c->close();
    }
}

$tarikh_semasa = date('d/m/Y');
$nama_syarikat = $company['nama'] ?? '';
$kod_gudang = $company['kod_gudang'] ?? '';
$kategori_gudang = 'Gudang Berlesen PEKEMA'; // default for this system

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Lampiran G - Laporan Pemeriksaan Pindaan Gudang</title>
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
            line-height: 1.4;
        }
        .section-title {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            font-size: 10pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 15px;
        }
        .no-border, .no-border td {
            border: none;
            padding: 6px 0;
            vertical-align: top;
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
        .dotted-line {
            border-bottom: 1px dotted #ccc;
        }

        @media print {
            body { background-color: transparent; }
            .paper-container {
                margin: 0; padding: 10mm 15mm; box-shadow: none; width: 100%; min-height: 0;
            }
            .no-print { display: none !important; }
            @page { size: A4 portrait; margin: 0; }
            .page-break { page-break-before: always; }
            .editable-field { border-bottom: none !important; }
            .dotted-line { border-bottom: 1px dotted #000; }
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
            <h2 class="text-lg font-bold text-gray-800">Cetak Borang Lampiran G</h2>
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
                LAMPIRAN G<br>
                PTK BIL. 53 - PER: 11.5.1, 11.6.1<br>
                1
            </div>

            <div style="font-size: 10pt; font-weight: bold; margin-bottom: 30px;">
                No. Rujukan Fail : <span class="editable-field dotted-line" contenteditable="true" style="width: 250px;"></span>
            </div>

            <div class="form-title">
                LAPORAN PEMERIKSAAN BAGI TUJUAN MENGUBAH<br>
                STRUKTUR KAWASAN GUDANG BERLESEN /<br>
                MEMINDAHKAN AKTIVITI GUDANG BERLESEN<br>
                KE PREMIS BAHARU
            </div>

            <table class="no-border" style="width: 100%; margin-top: 10px; line-height: 1.6;">
                <tr>
                    <td style="width: 40px; font-weight: bold;">1.</td>
                    <td style="width: 240px; font-weight: bold;">Nama Gudang Berlesen</td>
                    <td style="width: 10px;">:</td>
                    <td><div class="editable-field dotted-line font-bold" contenteditable="true" style="min-height: 1.5em;"><?= htmlspecialchars($nama_syarikat) ?></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">2.</td>
                    <td style="font-weight: bold;">Kategori Gudang</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 1.5em;"><?= htmlspecialchars($kategori_gudang) ?></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">3.</td>
                    <td style="font-weight: bold;">No. Pendaftaran Syarikat</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 1.5em;"></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">4.</td>
                    <td style="font-weight: bold;">No. Lesen Gudang Berlesen</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 1.5em;"><?= htmlspecialchars($kod_gudang) ?></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">5.</td>
                    <td style="font-weight: bold;">Alamat Gudang Berlesen<br>(Sedia ada)</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 4em;"><?= htmlspecialchars($nama_syarikat) ?></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">6.</td>
                    <td style="font-weight: bold;">Alamat Gudang Berlesen<br>Baharu</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 4em;"></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">7.</td>
                    <td style="font-weight: bold;">Keluasan asal GB</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 1.5em;"></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">8.</td>
                    <td style="font-weight: bold;">Keluasan baharu GB</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 1.5em;"></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">9.</td>
                    <td style="font-weight: bold;">Perubahan struktur yang<br>akan dilakukan</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 4em;"></div></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">10.</td>
                    <td style="font-weight: bold;">Perihal kawasan dan premis<br>baharu</td>
                    <td>:</td>
                    <td><div class="editable-field dotted-line" contenteditable="true" style="min-height: 2em;"></div></td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="3" style="font-weight: bold; padding-top: 10px;">10.1 Keselamatan kawasan :</td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="3">
                        <div class="editable-field" contenteditable="true" style="border: 1px solid #000; min-height: 80px; padding: 8px;"></div>
                        <div style="font-size: 8.5pt; margin-top: 5px; font-style: italic;">
                            Nota : Pastikan Lokasi premis tidak berhampiran atau bersebelahan dengan kilang atau tempat yang menyimpan atau mempunyai peralatan yang mudah terbakar atau meletup seperti bahan-bahan kimia merbahaya dan selinder gas.
                        </div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="3" style="font-weight: bold; padding-top: 15px;">10.2 Keselamatan bangunan:</td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="3">
                        <div class="editable-field" contenteditable="true" style="border: 1px solid #000; min-height: 80px; padding: 8px;"></div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- PAGE 2 -->
        <div class="paper-container page-break" style="margin-top: 2rem;">
            <div class="header-ref">
                LAMPIRAN G<br>
                PTK BIL. 53 - PER: 11.5.1, 11.6.1<br>
                2
            </div>

            <table class="no-border" style="width: 100%; margin-top: 20px; line-height: 1.8;">
                <tr>
                    <td style="width: 40px; font-weight: bold;">11.</td>
                    <td style="font-weight: bold;">Hasil pemeriksaan stok fizikal :</td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <div class="editable-field" contenteditable="true" style="border: 1px solid #000; min-height: 150px; padding: 10px;"></div>
                    </td>
                </tr>
                
                <tr>
                    <td style="font-weight: bold; padding-top: 40px;">12.</td>
                    <td style="font-weight: bold; padding-top: 40px;">Ulasan dan syor Pegawai Kanan yang menyelia gudang</td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                         <div class="editable-field" contenteditable="true" style="border: 1px solid #000; min-height: 100px; padding: 10px;"></div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding-top: 20px;">
                        <table style="width: 80%; border: none;">
                            <tr><td style="width: 100px; padding-bottom: 5px; border:none;">Tandatangan</td><td style="width: 10px; border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Nama</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Jawatan</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Tarikh</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="font-weight: bold; padding-top: 40px;">13.</td>
                    <td style="font-weight: bold; padding-top: 40px;">Ulasan dan syor Ketua Cawangan</td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                         <div class="editable-field" contenteditable="true" style="border: 1px solid #000; min-height: 100px; padding: 10px;"></div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding-top: 20px;">
                        <table style="width: 80%; border: none;">
                            <tr><td style="width: 100px; padding-bottom: 5px; border:none;">Tandatangan</td><td style="width: 10px; border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Nama</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Jawatan</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Tarikh</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                        </table>
                    </td>
                </tr>
                
                <tr>
                    <td style="font-weight: bold; padding-top: 40px;">14.</td>
                    <td style="font-weight: bold; padding-top: 40px;">Ulasan dan syor Ketua Bahagian</td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                         <div class="editable-field" contenteditable="true" style="border: 1px solid #000; min-height: 100px; padding: 10px;"></div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding-top: 20px;">
                        <table style="width: 80%; border: none;">
                            <tr><td style="width: 100px; padding-bottom: 5px; border:none;">Tandatangan</td><td style="width: 10px; border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Nama</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Jawatan</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                            <tr><td style="padding-bottom: 5px; border:none;">Tarikh</td><td style="border:none;">:</td><td style="border:none;"> <span class="editable-field dotted-line" contenteditable="true"></span></td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <!-- PAGE 3 -->
        <div class="paper-container page-break" style="margin-top: 2rem;">
            <div class="header-ref">
                LAMPIRAN G<br>
                PTK BIL. 53 - PER: 11.5.1, 11.6.1<br>
                3
            </div>

            <table class="no-border" style="width: 100%; margin-top: 30px; line-height: 1.8;">
                <tr>
                    <td style="width: 40px; font-weight: bold;">15.</td>
                    <td style="font-weight: bold;">Pertimbangan Pengarah Operasi Perkastaman & Perkhidmatan Teknik / Pengarah Kastam Negeri.</td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding-top: 20px;">
                         <div class="editable-field" contenteditable="true" style="border: 1px solid #000; min-height: 150px; padding: 10px;"></div>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding-top: 50px;">
                        <span style="display: inline-block; width: 350px; border-bottom: 1px dotted #000;"></span>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="font-weight: bold;">
                        Pengarah Operasi Perkastaman & Perkhidmatan Teknik /<br>
                        Pengarah Kastam Negeri
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td style="padding-top: 40px;">
                        <span style="display: inline-block; width: 100px; font-weight: bold;">Cop Rasmi</span>: 
                        <span class="editable-field dotted-line" contenteditable="true" style="width: 300px; height: 100px; vertical-align: top;"></span>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</body>
</html>
