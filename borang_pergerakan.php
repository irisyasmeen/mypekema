<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_role = $_SESSION['user_role'] ?? 'user';
$is_licensee = ($user_role === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;
$vehicle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vehicle = null;

if ($vehicle_id > 0) {
    $sql = "SELECT v.*, g.nama as gbpekema_nama, g.kod_gudang 
            FROM vehicle_inventory v 
            LEFT JOIN gbpekema g ON v.gbpekema_id = g.id 
            WHERE v.id = ?";
            
    if ($is_licensee) {
        $sql .= " AND v.gbpekema_id = " . (int)$licensee_gb_id;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $vehicle = $result->fetch_assoc();
    } else if ($is_licensee) {
        // Record not found or belongs to another company
        header("Location: permohonan.php");
        exit();
    }
    $stmt->close();
}
$can_approve = ($user_role === 'admin' || $user_role === 'senior_officer');

// Format numbers
$duti_import = isset($vehicle['duti_import']) ? $vehicle['duti_import'] : 0;
$duti_eksais = isset($vehicle['duti_eksais']) ? $vehicle['duti_eksais'] : 0;
$cukai_jualan = isset($vehicle['cukai_jualan']) ? $vehicle['cukai_jualan'] : 0;
$jumlah_taksiran = $duti_import + $duti_eksais + $cukai_jualan;

// Tarikh
$tarikh_semasa = date('d/m/Y');

// QR Code Logic
$show_qr = false;
$qr_url = "";
if (isset($vehicle['status_pergerakan']) && $vehicle['status_pergerakan'] === 'Lulus') {
    $show_qr = true;
    $qr_data = "KELULUSAN JKDM\nSyarikat: " . ($vehicle['gbpekema_nama'] ?? '') . "\nNo. Lot: " . ($vehicle['lot_number'] ?? '') . "\nStatus: LULUS\nTarikh: " . date('d/m/Y');
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($qr_data);
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Permohonan Pergerakan Kenderaan</title>
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
            /* A4 width */
            min-height: 297mm;
            /* A4 height */
            margin: 2rem mx-auto;
            padding: 15mm 20mm;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid black;
            padding: 6px 8px;
            vertical-align: top;
        }

        .no-border,
        .no-border td {
            border: none;
            padding: 2px 0;
        }

        .checkbox-box {
            display: inline-block;
            width: 15px;
            height: 15px;
            border: 1px solid black;
            margin-left: 5px;
            vertical-align: middle;
        }

        .header-ref {
            text-align: right;
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 10px;
        }

        .form-subtitle {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 10px;
            font-size: 10pt;
        }

        .editable-field {
            cursor: text;
            min-height: 1.2em;
            display: inline-block;
            width: 100%;
            outline: none;
        }

        .editable-field:hover {
            background-color: #fdfde8;
        }

        .editable-field:focus {
            background-color: #fffde0;
            border-bottom: 1px dashed #999;
        }

        /* Print styles */
        @media print {
            body {
                background-color: transparent;
            }

            .paper-container {
                margin: 0;
                padding: 10mm;
                box-shadow: none;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }

            @page {
                size: A4 portrait;
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="no-print">
        <?php include 'topmenu.php'; ?>
    </div>
    <!-- Action Bar -->
    <div
        class="fixed top-0 left-0 right-0 bg-white border-b shadow-sm p-4 flex justify-between items-center no-print z-10 hidden sm:flex">
        <div class="flex items-center gap-4">
            <a href="vehicles.php" class="text-gray-600 hover:text-gray-900 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h2 class="text-lg font-bold text-gray-800">Cetak Borang Pergerakan J</h2>
        </div>
        <div class="flex gap-2">
            <?php if ($can_approve): ?>
                <!-- Approval Buttons -->
                <button onclick="prosesKelulusan(<?= $vehicle_id ?>, 'Lulus')"
                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md shadow-sm flex items-center gap-2 transition-colors">
                    <i class="fas fa-check"></i> Kelulusan & Emel
                </button>
                <button onclick="prosesKelulusan(<?= $vehicle_id ?>, 'Ditolak')"
                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md shadow-sm flex items-center gap-2 transition-colors">
                    <i class="fas fa-times"></i> Tolak
                </button>
                <div class="border-l border-gray-300 mx-2"></div>
            <?php elseif ($is_licensee && empty($vehicle['status_pergerakan'])): ?>
                <!-- Submit Button for Licensee -->
                <button onclick="hantarPermohonan(<?= $vehicle_id ?>)"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md shadow-sm flex items-center gap-2 transition-colors">
                    <i class="fas fa-paper-plane"></i> Hantar Permohonan
                </button>
                <div class="border-l border-gray-300 mx-2"></div>
            <?php endif; ?>
            <button onclick="window.print()"
                class="bg-slate-600 hover:bg-slate-700 text-white font-medium py-2 px-6 rounded-md shadow-sm flex items-center gap-2 transition-colors">
                <i class="fas fa-print"></i> Cetak Dokumen
            </button>
        </div>
    </div>

    <!-- Mobile Action Button (Floating) -->
    <button onclick="window.print()"
        class="fixed bottom-6 right-6 bg-blue-600 text-white p-4 rounded-full shadow-lg no-print z-50 sm:hidden">
        <i class="fas fa-print text-xl"></i>
    </button>

    <div class="mt-20 print:mt-0 max-w-[210mm] mx-auto">
        <div class="paper-container">

            <div class="header-ref">
                LAMPIRAN J<br>
                PTK BIL 53 - PER: 16.10.7, 16.10.8
            </div>

            <div class="form-title">
                PERMOHONAN PERGERAKAN KENDERAAN
            </div>

            <div class="form-subtitle" style="display: flex; justify-content: center; align-items: center;">
                BAGI TUJUAN :
                PAMERAN <input type="checkbox" <?= !$is_licensee ? 'disabled' : '' ?>
                    style="width: 16px; height: 16px; margin: 0 8px; cursor: pointer; accent-color: black; border: 1px solid black;">
                / PENYELENGGARAAN <input type="checkbox" <?= !$is_licensee ? 'disabled' : '' ?>
                    style="width: 16px; height: 16px; margin-left: 8px; cursor: pointer; accent-color: black; border: 1px solid black;">
            </div>

            <table class="no-border mb-4">
                <tr>
                    <td style="width: 250px; font-weight: bold;">NAMA SYARIKAT DAN KOD GUDANG</td>
                    <td style="width: 10px;">:</td>
                    <td>
                        <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                            <?= htmlspecialchars($vehicle ? ($vehicle['gbpekema_nama'] . ' / ' . $vehicle['kod_gudang']) : '') ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">NO. LESEN GUDANG</td>
                    <td>:</td>
                    <td>
                        <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"></div>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">TARIKH PERMOHONAN</td>
                    <td>:</td>
                    <td>
                        <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"><?= $tarikh_semasa ?></div>
                    </td>
                </tr>
            </table>

            <!-- Jadual Maklumat -->
            <table>
                <tbody>
                    <tr>
                        <td style="width: 5%; text-align: center;">1</td>
                        <td style="width: 40%;">Bilangan</td>
                        <td style="width: 55%;" colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">1</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">2</td>
                        <td>No. Lot</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['lot_number'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">3</td>
                        <td>Tarikh Pertama Masuk Gudang</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= isset($vehicle['import_date']) && $vehicle['import_date'] != '0000-00-00' ? date('d/m/Y', strtotime($vehicle['import_date'])) : '' ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">4</td>
                        <td>Tarikh Import</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= isset($vehicle['k8_date']) ? date('d/m/Y', strtotime($vehicle['k8_date'])) : '' ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">5</td>
                        <td>No. K8</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['k8_number_full'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">6</td>
                        <td>Stesen Asal</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">7</td>
                        <td>Jenis Kenderaan</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">8</td>
                        <td>Model</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['vehicle_model'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">9</td>
                        <td>Tarikh Pendaftaran Pertama</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['tarikh_pendaftaran_pertama'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">10</td>
                        <td>No. Casis</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['chassis_number'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">11</td>
                        <td>No. Enjin</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['engine_number'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">12</td>
                        <td>Kapasiti Enjin (CC)</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['engine_cc'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">13</td>
                        <td>Status : (Baru/Terpakai)</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['condition_status'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">14</td>
                        <td>No.AP</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($vehicle['ap'] ?? '') ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">15</td>
                        <td>Tarikh Tamat Tempoh Digudang</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">16</td>
                        <td>Harga Kenderaan</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">17</td>
                        <td>Duti Import</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"><?= number_format($duti_import, 2) ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">18</td>
                        <td>Duti Eksais</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"><?= number_format($duti_eksais, 2) ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">19</td>
                        <td>Cukai Jualan</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"><?= number_format($cukai_jualan, 2) ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">20</td>
                        <td>Jumlah Duti / Cukai</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"><?= number_format($jumlah_taksiran, 2) ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">21</td>
                        <td>Alamat Pameran / Penyelenggaraan / Pemindahan</td>
                        <td colspan="2">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">22</td>
                        <td style="vertical-align: top; padding-top: 10px;">Catatan</td>
                        <td colspan="2" style="height: 60px;">
                            <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="height: 100%;"></div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Page Break if needed for longer forms when printing -->
            <!-- <div style="page-break-before: always;"></div> -->

            <div class="header-ref mt-8" style="margin-bottom: 5px;">
                LAMPIRAN J<br>
                PTK BIL 53 - PER: 16.10.7, 16.10.8
            </div>

            <div class="section-title">BAHAGIAN B:</div>
            <div style="font-weight: bold; margin-bottom: 10px; font-size: 10pt;">Pengesahan oleh Penganjur Pameran /
                Pusat Penyelenggaraan</div>

            <table>
                <tr>
                    <td style="width: 35%;">Nama Penganjur<br>Pameran / Pusat<br>Penyelenggaraan</td>
                    <td>
                        <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="height: 100%;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="height: 40px;">Alamat</td>
                    <td>
                        <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="height: 100%;"></div>
                    </td>
                </tr>
                <tr>
                    <td>Tarikh Penyelenggaraan</td>
                    <td>
                        <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="height: 100%;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="height: 40px;">Nama & Jawatan<br>Wakil Syarikat</td>
                    <td>
                        <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="height: 100%;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="height: 60px;">Tandatangan & Cop<br>Syarikat</td>
                    <td>
                        <div class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="height: 100%;"></div>
                    </td>
                </tr>
            </table>

            <div class="section-title mt-6">BAHAGIAN C :</div>
            <div style="font-weight: bold; font-size: 10pt; margin-bottom: 10px;">PENGESAHAN PEMOHON</div>

            <div style="margin-bottom: 20px; line-height: 1.6;">
                Saya <span class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"
                    style="width: 250px; border-bottom: 1px dotted #ccc;"></span>
                jawatan <span class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"
                    style="width: 250px; border-bottom: 1px dotted #ccc;"></span><br>
                dari Gudang Berlesen <span class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>"
                    style="width: 300px; border-bottom: 1px dotted #ccc;"></span>
                mengakui bahawa butir-butir di atas<br>adalah betul.
            </div>

            <table class="no-border" style="width: 50%; margin-bottom: 40px;">
                <tr>
                    <td style="width: 100px;">Tandatangan</td>
                    <td>: <span class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="width: 80%;"></span></td>
                </tr>
                <tr>
                    <td>Cop Rasmi</td>
                    <td>: <span class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="width: 80%;"></span></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td>: <span class="editable-field" contenteditable="<?= $is_licensee ? 'true' : 'false' ?>" style="width: 80%;"></span></td>
                </tr>
            </table>

            <!-- Page Break if needed for longer forms when printing -->
            <!-- <div style="page-break-before: always;"></div> -->

            <div class="header-ref mt-8" style="margin-bottom: 5px;">
                LAMPIRAN J<br>
                PTK BIL 53 - PER: 16.10.7, 16.10.8
            </div>

            <div style="border: 2px solid #5a8bb6; padding: 20px; margin-top: 10px;">
                <div class="section-title" style="margin-top: 0; text-decoration: underline;">BAHAGIAN D :</div>
                <div style="font-weight: bold; font-size: 10pt; text-decoration: underline; margin-bottom: 25px;">UNTUK
                    KEGUNAAN JKDM.</div>

                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="flex: 1;">
                        <div style="margin-bottom: 15px;">
                            Rujukan Kelulusan : <span class="editable-field" contenteditable="<?= $can_approve ? 'true' : 'false' ?>"
                                style="width: 80%; border-bottom: 1px dotted #ccc;"></span>
                        </div>

                        <div style="margin-bottom: 15px;">
                            Bertarikh <input type="date" <?= !$can_approve ? 'disabled' : '' ?> style="width: 150px; border: none; border-bottom: 1px dotted #ccc; background: transparent; outline: none; font-family: inherit; font-size: inherit; cursor: pointer; margin-left: 8px; color: #333;">
                        </div>

                        <div style="margin-bottom: 40px; display: flex; align-items: center; flex-wrap: wrap;">
                            Permohonan 
                            <input type="radio" <?= !$can_approve ? 'disabled' : '' ?> name="status_kelulusan" style="width: 16px; height: 16px; margin: 0 8px; cursor: pointer; accent-color: black; border: 1px solid black;" <?= (isset($vehicle['status_pergerakan']) && $vehicle['status_pergerakan'] === 'Lulus') ? 'checked' : '' ?>> diluluskan / 
                            <input type="radio" <?= !$can_approve ? 'disabled' : '' ?> name="status_kelulusan" style="width: 16px; height: 16px; margin: 0 8px; cursor: pointer; accent-color: black; border: 1px solid black;" <?= (isset($vehicle['status_pergerakan']) && $vehicle['status_pergerakan'] === 'Ditolak') ? 'checked' : '' ?>> tidak diluluskan 
                            <span style="margin-left: 8px;">mulai</span> <input type="date" <?= !$can_approve ? 'disabled' : '' ?> style="width: 130px; border: none; border-bottom: 1px dotted #ccc; background: transparent; outline: none; font-family: inherit; font-size: inherit; cursor: pointer; margin: 0 8px; color: #333;">
                            hingga <input type="date" <?= !$can_approve ? 'disabled' : '' ?> style="width: 130px; border: none; border-bottom: 1px dotted #ccc; background: transparent; outline: none; font-family: inherit; font-size: inherit; cursor: pointer; margin-left: 8px; color: #333;">
                        </div>

                        <div style="margin-bottom: 10px;">
                            <span class="editable-field" contenteditable="<?= $can_approve ? 'true' : 'false' ?>"
                                style="width: 300px; border-bottom: 1px dotted #ccc;"></span>
                        </div>

                        <div style="margin-bottom: 10px;">
                            (*Nama: <span class="editable-field" contenteditable="<?= $can_approve ? 'true' : 'false' ?>"
                                style="width: 250px; border-bottom: 1px dotted #ccc;"></span>)
                        </div>

                        <div style="margin-bottom: 10px;">
                            Jawatan : <span class="editable-field" contenteditable="<?= $can_approve ? 'true' : 'false' ?>"
                                style="width: 250px; border-bottom: 1px dotted #ccc;"></span>
                        </div>

                        <div style="margin-bottom: 20px;">
                            Cawangan <span class="editable-field" contenteditable="<?= $can_approve ? 'true' : 'false' ?>"
                                style="width: 245px; border-bottom: 1px dotted #ccc;"></span>
                        </div>

                        <div style="font-size: 9pt; font-style: italic;">
                            *Pegawai Kanan Kastam
                        </div>
                    </div>

                    <?php if ($show_qr): ?>
                        <div
                            style="margin-left: 20px; text-align: center; border: 1px dashed #ccc; padding: 10px; background-color: #f9fafb;">
                            <img src="<?= $qr_url ?>" alt="QR Kelulusan"
                                style="width: 100px; height: 100px; margin: 0 auto;">
                            <div style="font-size: 8pt; margin-top: 5px; font-weight: bold;">Dokumen Sah</div>
                            <div style="font-size: 7pt; color: #555;">Diluluskan Secara Digital</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function hantarPermohonan(id) {
            Swal.fire({
                title: 'Hantar Permohonan?',
                text: "Adakah anda pasti untuk menghantar permohonan pergerakan kenderaan ini untuk kelulusan JKDM?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hantar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Sedang Diproses...',
                        text: 'Sila tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('process_hantar_pergerakan.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: id
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire(
                                    'Berjaya!',
                                    data.message,
                                    'success'
                                ).then(() => {
                                    window.location.href = 'permohonan.php';
                                });
                            } else {
                                Swal.fire(
                                    'Ralat!',
                                    data.message || 'Ralat semasa menghantar permohonan.',
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'Ralat Proses',
                                'Gagal berhubung dengan pelayan. Sila cuba lagi.',
                                'error'
                            );
                        });
                }
            });
        }

        function prosesKelulusan(id, status) {
            Swal.fire({
                title: 'Sahkan Tindakan?',
                text: status === 'Lulus'
                    ? "Adakah anda pasti untuk Luluskan permohonan ini? Emel notifikasi akan dihantar secara automatik kepada syarikat pengimport."
                    : "Adakah anda pasti untuk Tolak permohonan ini?",
                icon: status === 'Lulus' ? 'info' : 'warning',
                showCancelButton: true,
                confirmButtonColor: status === 'Lulus' ? '#16a34a' : '#d33',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Teruskan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Sedang Diproses...',
                        text: 'Sila tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('process_kelulusan_pergerakan.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: id,
                            status: status
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire(
                                    'Berjaya!',
                                    data.message,
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'Ralat!',
                                    data.message || 'Ralat semasa memproses kelulusan.',
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'Ralat Proses',
                                'Gagal berhubung dengan pelayan. Sila cuba lagi.',
                                'error'
                            );
                        });
                }
            });
        }
    </script>
</body>

</html>