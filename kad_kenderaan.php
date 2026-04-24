<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
include 'config.php';
// The lines below are now handled by config.php
/*
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gudang";
$conn = new mysqli($servername, $username, $password, $dbname);
*/
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($vehicle_id <= 0) {
    die("Invalid Vehicle ID.");
}

// Fetch vehicle data along with GB/PEKEMA name
$sql = "SELECT v.*, g.nama as gbpekema_nama, g.kod_gudang as gbpekema_kod
        FROM vehicle_inventory v 
        LEFT JOIN gbpekema g ON v.gbpekema_id = g.id 
        WHERE v.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $vehicle_found = false;
} else {
    $vehicle_found = true;
    $vehicle = $result->fetch_assoc();

    // Debug color value - add this for testing
    error_log("Raw color value from DB: " . var_export($vehicle['color'], true));
}
$stmt->close();
// Removed $conn->close() here as it is needed for topmenu.php included later

// Helper function to format K8 number
function formatK8Number($k8_full)
{
    if (empty($k8_full))
        return 'N/A';
    $parts = explode(',', $k8_full);
    if (count($parts) === 6) {
        return sprintf('%s-%s-%s/%s', $parts[3], $parts[1], $parts[5], substr($parts[4], 1));
    }
    return $k8_full;
}

// Helper function to properly display color
function displayColor($color)
{
    // Handle all possible empty/null/zero cases
    if (is_null($color) || $color === '' || $color === '0' || $color === 0) {
        return 'N/A';
    }
    return htmlspecialchars(trim($color));
}
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kad Kenderaan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body * {
                visibility: hidden;
            }

            #print-section,
            #print-section * {
                visibility: visible;
            }

            #print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .no-print {
                display: none;
            }
        }

        .info-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.5rem 1.5rem;
            align-items: center;
        }

        .info-grid dt {
            color: #4B5563;
        }

        .info-grid dd {
            font-weight: 500;
            color: #1F2937;
        }
    </style>
</head>

<body class="bg-gray-100">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        <?php if ($vehicle_found): ?>
            <div id="print-section" class="bg-white p-8 rounded-2xl shadow-lg max-w-4xl mx-auto border">
                <header class="flex justify-between items-center mb-8 pb-4 border-b-2">
                    <div class="flex items-center">
                        <img src="logo.jpg" alt="Logo Jabatan" class="h-16 w-16 mr-5">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Rekod Kenderaan</h1>
                            <p class="text-md text-gray-500">Cawangan Industri, JKDM LTA KL</p>
                        </div>
                    </div>
                </header>

                <div class="space-y-6">
                    <!-- Maklumat Utama -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center"><i
                                class="fas fa-file-alt text-blue-500 mr-3"></i>Maklumat Utama</h3>
                        <dl class="info-grid">
                            <dt>No. Lot:</dt>
                            <dd class="flex items-center gap-2">
                                <span
                                    id="lot_number_display"><?= htmlspecialchars($vehicle['lot_number'] ?? 'N/A') ?></span>
                                <button onclick="generateLotNumber(<?= $vehicle_id ?>)"
                                    class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-1 px-3 rounded shadow transition-all"
                                    title="Jana No. Lot Automatik">
                                    <i class="fas fa-magic mr-1"></i> Jana
                                </button>
                            </dd>
                            <dt>No.K8:</dt>
                            <dd><?= htmlspecialchars(formatK8Number($vehicle['k8_number_full'])) ?> <span
                                    class="text-xs text-gray-500 ml-2">(Odo:
                                    <?= (!empty($vehicle['odometer8']) && is_numeric($vehicle['odometer8'])) ? number_format($vehicle['odometer8']) . ' km' : 'N/A' ?>)</span>
                            </dd>
                            <dt>No. K1:</dt>
                            <dd><?= htmlspecialchars($vehicle['k1_number'] ?? 'N/A') ?> <span
                                    class="text-xs text-gray-500 ml-2">(Odo:
                                    <?= (!empty($vehicle['odometer1']) && is_numeric($vehicle['odometer1'])) ? number_format($vehicle['odometer1']) . ' km' : 'N/A' ?>)</span>
                            </dd>
                            <?php 
                            $raw_kod = $vehicle['kod_gudang'] ?? 'N/A';
                            $display_kod = $vehicle['gbpekema_kod'] ?? $raw_kod;
                            $display_nama = $vehicle['gbpekema_nama'] ?? 'N/A';
                            
                            // fallback logic if joined data is missing but raw_kod has both
                            if ($display_nama === 'N/A' && $raw_kod !== 'N/A' && strpos($raw_kod, ' ') !== false) {
                                $parts = explode(' ', $raw_kod, 2);
                                $display_kod = $parts[0];
                                $display_nama = $parts[1];
                            }
                            ?>
                            <dt>Kod Gudang:</dt>
                            <dd><?= htmlspecialchars($display_kod) ?></dd>
                            <dt>Nama Gudang:</dt>
                            <dd><?= htmlspecialchars($display_nama) ?></dd>
                            <dt>Status:</dt>
                            <dd><span
                                    class="font-semibold px-2 py-1 text-xs rounded-full <?= strtoupper($vehicle['condition_status'] ?? '') == 'NEW' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>"><?= htmlspecialchars(strtoupper($vehicle['condition_status'] ?? 'N/A')) ?></span>
                            </dd>
                        </dl>
                    </div>

                    <!-- Spesifikasi Teknikal -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center"><i
                                class="fas fa-cogs text-green-500 mr-3"></i>Spesifikasi Teknikal</h3>
                        <dl class="info-grid">
                            <dt>Model/Jenis:</dt>
                            <dd><?= htmlspecialchars($vehicle['vehicle_model'] ?? 'N/A') ?></dd>
                            <dt>No. Chasis:</dt>
                            <dd><?= htmlspecialchars($vehicle['chassis_number'] ?? 'N/A') ?></dd>
                            <dt>No. Enjin:</dt>
                            <dd><?= htmlspecialchars($vehicle['engine_number'] ?? 'N/A') ?></dd>
                            <dt>Kapasiti:</dt>
                            <dd><?= htmlspecialchars($vehicle['engine_cc'] ?? 'N/A') ?> cc /
                                <?= htmlspecialchars($vehicle['kw'] ?? 'N/A') ?> kw
                            </dd>
                            <dt>Warna:</dt>
                            <dd><?= displayColor($vehicle['color']) ?></dd>
                        </dl>
                    </div>

                    <!-- Butiran Kastam & Cukai -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center"><i
                                class="fas fa-landmark text-red-500 mr-3"></i>Butiran Kastam & Cukai</h3>
                        <dl class="info-grid">
                            <dt>Tarikh Import:</dt>
                            <dd>
                                <?= !empty($vehicle['import_date']) ? date('d-m-Y', strtotime($vehicle['import_date'])) : 'N/A' ?>
                            </dd>
                            <dt>Stesen Asal:</dt>
                            <dd><?= htmlspecialchars($vehicle['stesen_asal'] ?? 'N/A') ?></dd>
                            <dt>Tamat Tempoh Gudang:</dt>
                            <dd>
                                <?= !empty($vehicle['tarikh_tamat_tempoh_gudang']) ? date('d-m-Y', strtotime($vehicle['tarikh_tamat_tempoh_gudang'])) : 'N/A' ?>
                            </dd>
                            <dt>AP:</dt>
                            <dd><?= htmlspecialchars($vehicle['ap'] ?? 'N/A') ?></dd>
                            <dt>No. Resit:</dt>
                            <dd><?= htmlspecialchars($vehicle['receipt_number'] ?? 'N/A') ?></dd>
                            <dt>Tarikh Bayar K1:</dt>
                            <dd>
                                <?= !empty($vehicle['payment_date']) ? date('d-m-Y', strtotime($vehicle['payment_date'])) : 'N/A' ?>
                            </dd>
                            <dt>Harga Taksiran:</dt>
                            <dd>RM <?= number_format($vehicle['harga_taksiran'] ?? 0, 2) ?></dd>
                            <dt class="font-bold">Jumlah Duti:</dt>
                            <dd class="font-extrabold text-lg text-red-600">RM
                                <?= number_format($vehicle['duty_rm'] ?? 0, 2) ?>
                            </dd>
                            <dt class="align-top pt-1">Pecahan Cukai:</dt>
                            <dd>
                                <div class="space-y-1 text-sm">
                                    <p class="flex justify-between border-b pb-1"><span>Duti Import:</span> <span>RM
                                            <?= number_format($vehicle['duti_import'] ?? 0, 2) ?></span></p>
                                    <p class="flex justify-between border-b pb-1"><span>Duti Eksais:</span> <span>RM
                                            <?= number_format($vehicle['duti_eksais'] ?? 0, 2) ?></span></p>
                                    <p class="flex justify-between border-b pb-1"><span>Cukai Jualan:</span> <span>RM
                                            <?= number_format($vehicle['cukai_jualan'] ?? 0, 2) ?></span></p>
                                </div>
                            </dd>
                            <dt>Catatan:</dt>
                            <dd><?= htmlspecialchars($vehicle['catatan'] ?? 'Tiada') ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="text-center mt-8 no-print">
                <button onclick="window.print()"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">Cetak</button>
            </div>
        <?php else: ?>
            <div class="text-center bg-white p-8 rounded-lg shadow-md">
                <h1 class="text-2xl font-bold text-red-600">Kenderaan Tidak Dijumpai</h1>
                <p class="mt-2 text-gray-600">ID Kenderaan: <?= $vehicle_id ?></p>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function generateLotNumber(id) {
            Swal.fire({
                title: 'Jana No. Lot?',
                text: "Adakah anda pasti mahu menjana No. Lot baharu secara automatik? Ini akan menggantikan No. Lot sedia ada (jika ada).",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Jana Sekarang!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Sedang Proses...',
                        text: 'Sila tunggu sebentar...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Call API
                    fetch('generate_lot_number.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id: id })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire(
                                    'Berjaya!',
                                    'No. Lot baharu telah dijana: ' + data.new_lot_number,
                                    'success'
                                ).then(() => {
                                    // Update display and reload to reflect changes strictly if needed, 
                                    // but updating DOM is nicer.
                                    document.getElementById('lot_number_display').textContent = data.new_lot_number;
                                    // Reload page to ensure consistency
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'Ralat!',
                                    data.message || 'Gagal menjana No. Lot.',
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'Ralat!',
                                'Terdapat masalah rangkaian atau pelayan.',
                                'error'
                            );
                        });
                }
            })
        }
    </script>

</body>

<?php $conn->close(); ?>

</html>