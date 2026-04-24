<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Ini borang Lampiran F, yang biasanya melibatkan syarikat (GBPEKEMA)
// Mari kita cuba dapatkan data syarikat jika gb_id diberi, atau vehicle_id
$gb_id = isset($_GET['gb_id']) ? intval($_GET['gb_id']) : 0;
$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$company = null;

if ($vehicle_id > 0 && $gb_id == 0) {
    // Kalau pengguna buka dari rekod kenderaan, cari gb_id dia
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
$tempoh_lesen = '';
if (!empty($company['tarikh_mula']) && !empty($company['tarikh_akhir']) && $company['tarikh_mula'] != '0000-00-00' && $company['tarikh_akhir'] != '0000-00-00') {
    $tempoh_lesen = date('d/m/Y', strtotime($company['tarikh_mula'])) . " hingga " . date('d/m/Y', strtotime($company['tarikh_akhir']));
}

$jumlah_jaminan = '';
$tempoh_jaminan = '';
// Cuba dapatkan dari rekod_jaminan jika ada
if ($gb_id > 0) {
    $sql_j = "SELECT nilai_jaminan, tarikh_mula, tarikh_tamat FROM rekod_jaminan WHERE syarikat_id = ? ORDER BY id DESC LIMIT 1";
    $stmt_j = $conn->prepare($sql_j);
    if($stmt_j) {
        $stmt_j->bind_param("i", $gb_id);
        $stmt_j->execute();
        $res_j = $stmt_j->get_result();
        if ($row_j = $res_j->fetch_assoc()) {
            $jumlah_jaminan = "RM " . number_format($row_j['nilai_jaminan'], 2);
            if (!empty($row_j['tarikh_mula']) && !empty($row_j['tarikh_tamat'])) {
                $tempoh_jaminan = date('d/m/Y', strtotime($row_j['tarikh_mula'])) . " hingga " . date('d/m/Y', strtotime($row_j['tarikh_tamat']));
            }
        }
        $stmt_j->close();
    }
}

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Lampiran F - Pindaan Struktur/Lokasi Gudang</title>
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
            text-decoration: underline;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 15px;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid black;
            padding: 8px;
            vertical-align: top;
        }
        .table-bordered th {
            text-align: center;
            vertical-align: middle;
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
        
        /* Checkbox styling fix for print */
        .print-checkbox {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #000;
            text-align: center;
            line-height: 12px;
            font-size: 12px;
            cursor: pointer;
            margin-right: 5px;
            font-family: monospace;
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
        }
    </style>
    <script>
        function toggleCheck(el) {
            if(el.innerText.trim() === '') {
                el.innerText = 'X';
            } else if(el.innerText.trim() === 'X') {
                el.innerText = '/';
            } else {
                el.innerText = '';
            }
        }
        function toggleRadio(groupName, el) {
            document.querySelectorAll('.'+groupName).forEach(e => e.innerText = '');
            el.innerText = '/';
        }
    </script>
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
            <h2 class="text-lg font-bold text-gray-800">Cetak Borang Lampiran F</h2>
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
                LAMPIRAN F<br>
                PTK BIL. 53 - PER: 11.1, 11.2<br>
                1
            </div>

            <div class="form-title" style="margin-top: 30px;">
                PERMOHONAN MENGUBAH STRUKTUR KAWASAN<br>
                GUDANG BERLESEN / MEMINDAHKAN AKTIVITI GUDANG<br>
                BERLESEN KE PREMIS BAHARU
            </div>

            <div class="font-bold underline mb-4 text-[10pt]">BAHAGIAN A</div>
            
            <div class="font-bold text-[10pt] mb-2">1. Butiran pemohon</div>
            <table class="no-border" style="margin-left: 20px; width: 90%;">
                <tr>
                    <td style="width: 250px;">(a) Nama</td>
                    <td style="width: 10px;">:</td>
                    <td><div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000;"><?= htmlspecialchars($_SESSION['nama_pegawai'] ?? '') ?></div></td>
                </tr>
                <tr>
                    <td>(b) Pangkat</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000;"></div></td>
                </tr>
                <tr>
                    <td>(c) No. Kad Pengenalan / No. Pasport</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000;"></div></td>
                </tr>
                <tr>
                    <td>(d) Alamat</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="min-height: 40px; border-bottom: 1px dotted #000;"></div></td>
                </tr>
            </table>

            <div class="font-bold text-[10pt] mt-6 mb-2">2. Butiran Gudang Berlesen</div>
            <table class="no-border" style="margin-left: 20px; width: 93%;">
                <tr>
                    <td style="width: 250px;">(a) Jenis lesen</td>
                    <td style="width: 10px;">:</td>
                    <td style="line-height: 1.8;">
                        <span class="print-checkbox radio-jenis" onclick="toggleRadio('radio-jenis', this)"></span> Gudang Berlesen Awam <br>
                        <span class="print-checkbox radio-jenis" onclick="toggleRadio('radio-jenis', this)"></span> Gudang Berlesen Awam di Pelabuhan <br>
                        <span class="print-checkbox radio-jenis" onclick="toggleRadio('radio-jenis', this)"></span> Gudang Berlesen Awam di Lapangan Terbang <br>
                        <span class="print-checkbox radio-jenis" onclick="toggleRadio('radio-jenis', this)"></span> Gudang Berlesen Persendirian <br>
                        <span class="print-checkbox radio-jenis" onclick="toggleRadio('radio-jenis', this)">/</span> Gudang Berlesen PEKEMA
                    </td>
                </tr>
                <tr>
                    <td>(b) Alamat premis sedia ada</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="min-height: 40px; border-bottom: 1px dotted #000;"><?= htmlspecialchars($nama_syarikat) ?></div></td>
                </tr>
                <tr>
                    <td>(c) No. Gudang Berlesen</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000;"><?= htmlspecialchars($kod_gudang) ?></div></td>
                </tr>
                <tr>
                    <td>(d) Tempoh lesen</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000;"><?= $tempoh_lesen ?></div></td>
                </tr>
                <tr>
                    <td>(e) Jumlah dan tempoh Jaminan Bank</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="min-height: 40px; border-bottom: 1px dotted #000;"><?= $jumlah_jaminan ? ($jumlah_jaminan . " (" . $tempoh_jaminan . ")") : '' ?></div></td>
                </tr>
                <tr>
                    <td>(f) Alamat premis baharu</td>
                    <td>:</td>
                    <td><div class="editable-field" contenteditable="true" style="min-height: 40px; border-bottom: 1px dotted #000;"></div></td>
                </tr>
            </table>

            <div class="font-bold text-[10pt] mt-6 mb-3">3. Bersama-sama ini disertakan dokumen-dokumen seperti berikut:</div>
            
            <table class="table-bordered" style="width: 95%; margin-left: 20px;">
                <thead>
                    <tr>
                        <th style="width: 5%;">BIL</th>
                        <th style="width: 75%;">DOKUMEN</th>
                        <th style="width: 20%;">ADA/TIADA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;">(a)</td>
                        <td>Surat permohonan daripada syarikat.</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">ADA</div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">(b)</td>
                        <td>2 salinan / set pelan struktur sekarang / pelan premis sekarang.</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">ADA</div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">(c)</td>
                        <td>2 salinan / set pelan dicadangkan perubahan / pelan premis baharu.</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">ADA</div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">(d)</td>
                        <td>Surat sokongan premis baharu oleh MITI (jika berkenaan)</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">TIADA</div></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PAGE 2 -->
        <div class="paper-container page-break" style="margin-top: 2rem;">
            <div class="header-ref">
                LAMPIRAN F<br>
                PTK BIL. 53 - PER: 11.1, 11.2<br>
                2
            </div>

            <table class="table-bordered" style="width: 95%; margin-left: 20px; margin-top: 30px;">
                <thead>
                    <tr>
                        <th style="width: 5%;">BIL</th>
                        <th style="width: 75%;">DOKUMEN</th>
                        <th style="width: 20%;">ADA/TIADA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;">(e)</td>
                        <td>Penyata stok bulanan selain kenderaan (Lampiran M) /<br>Penyata stok bulanan kenderaan (Lampiran N)</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">ADA</div></td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">(f)</td>
                        <td>Perjanjian sewa premis / Perjanjian jual beli premis (jika berkenaan)</td>
                        <td style="text-align: center;"><div class="editable-field" contenteditable="true">TIADA</div></td>
                    </tr>
                </tbody>
            </table>

            <div class="font-bold text-[10pt] mt-8 mb-4">4. Butir-butir Gudang Berlesen seperti di bawah:</div>
            
            <table class="no-border" style="margin-left: 20px; width: 95%; line-height: 2;">
                <tr>
                    <td style="width: 250px;">(a) Tempoh lesen dari</td>
                    <td>
                        <div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000; width: 150px; text-align: center;"><?= !empty($company['tarikh_mula']) && $company['tarikh_mula'] != '0000-00-00' ? date('d/m/Y', strtotime($company['tarikh_mula'])) : '' ?></div>
                        hingga
                        <div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000; width: 150px; text-align: center;"><?= !empty($company['tarikh_akhir']) && $company['tarikh_akhir'] != '0000-00-00' ? date('d/m/Y', strtotime($company['tarikh_akhir'])) : '' ?></div>
                    </td>
                </tr>
                <tr>
                    <td>(b) No. Sijil Ahli PEKEMA (jika berkenaan)</td>
                    <td>
                        <div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000; width: 330px;"><?= htmlspecialchars($company['no_ahli_pekema'] ?? '') ?></div>
                    </td>
                </tr>
                <tr>
                    <td>(c) Jaminan bank berjumlah RM</td>
                    <td>
                        <div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000; width: 330px;"><?= $jumlah_jaminan ? str_replace('RM ', '', $jumlah_jaminan) : '' ?></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">Sahlaku dalam tempoh dari</td>
                    <td>
                        <div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000; width: 150px; text-align: center;"><?= $tempoh_jaminan ? explode(' hingga ', $tempoh_jaminan)[0] : '' ?></div>
                        hingga
                        <div class="editable-field" contenteditable="true" style="border-bottom: 1px dotted #000; width: 150px; text-align: center;"><?= $tempoh_jaminan ? explode(' hingga ', $tempoh_jaminan)[1] : '' ?></div>
                    </td>
                </tr>
            </table>

            <table class="no-border" style="width: 100%; margin-top: 60px;">
                <tr>
                    <td style="width: 150px; padding-bottom: 15px;">Tandatangan</td>
                    <td style="width: 10px;">:</td>
                    <td>
                         <span style="display: inline-block; width: 300px; border-bottom: 1px dotted #000;"></span>
                    </td>
                </tr>
                <tr>
                    <td style="padding-bottom: 15px;">Cop Rasmi</td>
                    <td>:</td>
                    <td>
                        <span class="editable-field" contenteditable="true" style="width: 300px; border-bottom: 1px dotted #ccc; height: 80px;"></span>
                    </td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td>:</td>
                    <td>
                        <span class="editable-field" contenteditable="true" style="width: 300px; border-bottom: 1px dotted #ccc;"><?= $tarikh_semasa ?></span>
                    </td>
                </tr>
            </table>

        </div>
    </div>
</body>
</html>
