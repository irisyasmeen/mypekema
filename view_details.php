<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$row = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM carnet_malaysia WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    }
}

// Redirect if not found
if (!$row) {
    header("Location: index.php?error=notfound");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Data Carnet -
        <?php echo htmlspecialchars($row['no_ata_carnet']); ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: 1px solid rgba(255, 255, 255, 0.2);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            background-image:
                radial-gradient(at 0% 0%, rgba(102, 126, 234, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(118, 75, 162, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
        }

        .main-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
            animation: fadeIn 0.6s ease-out;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: var(--glass-border);
            box-shadow: var(--glass-shadow);
            border-radius: 24px;
            overflow: hidden;
            padding: 40px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-valid {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
            border: 1px solid rgba(25, 135, 84, 0.2);
        }

        .status-expired {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #d6a304;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .detail-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #2d3436;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 10px 20px;
            border-radius: 12px;
            color: #4a5568;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            color: #2d3436;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <?php include 'topmenu.php'; ?>

    <div class="main-container">
        <!-- Breadcrumb & Back -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Kembali ke Senarai
            </a>
            <div class="text-muted small">ID: #
                <?php echo $row['id']; ?>
            </div>
        </div>

        <div class="glass-card">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-5 pb-4 border-bottom">
                <div>
                    <div class="text-muted small mb-2 text-uppercase fw-bold ls-1">Nombor ATA Carnet</div>
                    <h1 class="display-5 fw-bold text-dark mb-0">
                        <?php echo htmlspecialchars($row['no_ata_carnet']); ?>
                    </h1>
                </div>
                <div>
                    <?php
                    $status = $row['status'];
                    $statusClass = $status == 'VALID' ? 'status-valid' : ($status == 'EXPIRED' ? 'status-expired' : 'status-pending');
                    $icon = $status == 'VALID' ? 'fa-check-circle' : ($status == 'EXPIRED' ? 'fa-times-circle' : 'fa-clock');
                    ?>
                    <div class="status-badge <?php echo $statusClass; ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                        <?php echo htmlspecialchars($status); ?>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="row g-5">
                <!-- Left Column -->
                <div class="col-lg-6">
                    <h3 class="section-title"><i class="fas fa-info-circle text-primary"></i> Maklumat Utama</h3>

                    <div class="mb-4">
                        <div class="detail-label">Pengeksport</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($row['pengeksport'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="detail-label">Ejen Penghantaran</div>
                        <div class="detail-value text-primary">
                            <?php echo htmlspecialchars($row['ejen'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="detail-label">Nilai Barang (MYR)</div>
                            <div class="detail-value fw-bold text-success font-monospace">
                                RM
                                <?php echo number_format($row['nilai_barang'] ?? 0, 2); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="detail-label">Negara Tujuan</div>
                            <div class="detail-value">
                                <i class="fas fa-globe-americas me-2 text-muted"></i>
                                <?php echo htmlspecialchars($row['negara_tujuan'] ?? '-'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-6">
                    <h3 class="section-title"><i class="fas fa-calendar-alt text-warning"></i> Maklumat Tarikh</h3>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="detail-label">Tarikh Eksport</div>
                            <div class="detail-value">
                                <?php echo !empty($row['tarikh_eksport']) ? date('d M Y', strtotime($row['tarikh_eksport'])) : '-'; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="detail-label">Sah Sehingga</div>
                            <?php
                            // Try to parse 'tempoh_sah_carnet_1_tahun__(d_m_y)'
                            $valid_date = $row['tempoh_sah_carnet_1_tahun__(d_m_y)'] ?? null;
                            ?>
                            <div class="detail-value">
                                <?php echo $valid_date ? htmlspecialchars($valid_date) : '-'; ?>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 mt-2">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="detail-label text-muted">Daftar Eksport</div>
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($row['daftar_eksport'] ?? '-'); ?>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="detail-label text-muted">Daftar Import</div>
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($row['daftar_import'] ?? '-'); ?>
                                </div>
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <div class="detail-label text-muted">Replacement Carnet</div>
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($row['no_replacement_carnet'] ?? '-'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Meta -->
            <div class="mt-5 pt-4 border-top text-muted small d-flex justify-content-between">
                <div>Dicipta pada:
                    <?php echo date('d M Y H:i', strtotime($row['created_at'] ?? 'now')); ?>
                </div>
                <div>Status Tambahan:
                    <?php echo htmlspecialchars($row['status.1'] ?? '') . ' ' . htmlspecialchars($row['status.2'] ?? ''); ?>
                </div>
            </div>

            <div class="mt-4 text-end no-print">
                <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4 me-2">
                    <i class="fas fa-print me-2"></i> Cetak
                </button>
                <a href="edit_data.php?id=<?php echo $row['id']; ?>" class="btn btn-gradient rounded-pill px-4">
                    <i class="fas fa-edit me-2"></i> Kemaskini
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>