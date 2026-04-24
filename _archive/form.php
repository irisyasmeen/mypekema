<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$msg = "";
$error = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_ata_carnet = $_POST['no_ata_carnet'] ?? '';
    $ejen = $_POST['ejen'] ?? '';
    $pengeksport = $_POST['pengeksport'] ?? '';
    $status = $_POST['status'] ?? '';
    $tarikh_eksport = $_POST['tarikh_eksport'] ?? '';
    $tempoh_sah = $_POST['tempoh_sah'] ?? '';
    $daftar_eksport = $_POST['daftar_eksport'] ?? '';
    $daftar_import = $_POST['daftar_import'] ?? '';
    $tarikh_daftar_import = $_POST['tarikh_daftar_import'] ?? '';
    $no_replacement = $_POST['no_replacement'] ?? '';
    $tarikh_lanjutan = $_POST['tarikh_lanjutan'] ?? '';
    $status1 = $_POST['status1'] ?? '';
    $status2 = $_POST['status2'] ?? '';

    // Basic Validation
    if (empty($no_ata_carnet)) {
        $error = "Sila isi Nombor ATA Carnet.";
    } else {
        $stmt = $conn->prepare("INSERT INTO carnet_malaysia (
            no_ata_carnet, ejen, pengeksport, status, tarikh_eksport, 
            `tempoh_sah_carnet_1_tahun__(d_m_y)`, daftar_eksport, daftar_import, 
            tarikh_daftar_import, no_replacement_carnet, tarikh_lanjutan_replacement_carnet, 
            `status.1`, `status.2`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "sssssssssssss",
            $no_ata_carnet,
            $ejen,
            $pengeksport,
            $status,
            $tarikh_eksport,
            $tempoh_sah,
            $daftar_eksport,
            $daftar_import,
            $tarikh_daftar_import,
            $no_replacement,
            $tarikh_lanjutan,
            $status1,
            $status2
        );

        if ($stmt->execute()) {
            $msg = "Rekod baru berjaya ditambah!";
            // Optional: Redirect or clear form
            // header("Location: index.php"); 
        } else {
            $error = "Ralat: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Data Baru - Carnet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: #f0f2f5;
            background-image: radial-gradient(at 0% 0%, rgba(102, 126, 234, 0.1) 0px, transparent 50%), radial-gradient(at 100% 0%, rgba(118, 75, 162, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
        }

        .main-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            animation: fadeIn 0.6s ease-out;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            border-radius: 24px;
            padding: 40px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
        }

        .btn-gradient {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
            transition: all 0.3s;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
            color: white;
        }

        .section-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            margin-top: 30px;
        }

        .section-header:first-of-type {
            margin-top: 0;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="text-decoration-none text-muted fw-bold"><i
                    class="fas fa-arrow-left me-2"></i>Kembali</a>
            <div class="text-muted small">Tambah Rekod Baru</div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success rounded-4 shadow-sm mb-4 border-0 d-flex align-items-center">
                <i class="fas fa-check-circle fs-4 me-3"></i>
                <div>
                    <?php echo $msg; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger rounded-4 shadow-sm mb-4 border-0 d-flex align-items-center">
                <i class="fas fa-exclamation-circle fs-4 me-3"></i>
                <div>
                    <?php echo $error; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <h2 class="fw-bold mb-4 text-dark"><i class="fas fa-plus-circle text-primary me-2"></i>Tambah Rekod Carnet
            </h2>

            <form method="POST">

                <div class="section-header">Info Asas</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">No. ATA Carnet <span class="text-danger">*</span></label>
                        <input type="text" name="no_ata_carnet" class="form-control" placeholder="Contoh: MY 24 0001"
                            required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="VALID">VALID</option>
                            <option value="EXPIRED">EXPIRED</option>
                            <option value="PENDING">PENDING</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ejen Penghantaran</label>
                        <input type="text" name="ejen" class="form-control" placeholder="Nama Syarikat Logistik">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pengeksport</label>
                        <input type="text" name="pengeksport" class="form-control" placeholder="Nama Pengeksport">
                    </div>
                </div>

                <div class="section-header">Tarikh & Pengesahan</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tarikh Eksport</label>
                        <input type="text" name="tarikh_eksport" class="form-control" placeholder="YYYY-MM-DD">
                        <div class="form-text small">Format: YYYY-MM-DD (Contoh: 2024-03-20)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tempoh Sah (Sehingga)</label>
                        <input type="text" name="tempoh_sah" class="form-control" placeholder="Contoh: 31/12/2025">
                    </div>
                </div>

                <div class="section-header">Daftar Import/Eksport</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Daftar Eksport</label>
                        <input type="text" name="daftar_eksport" class="form-control" placeholder="No. Daftar Eksport">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Daftar Import</label>
                        <input type="text" name="daftar_import" class="form-control" placeholder="No. Daftar Import">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tarikh Daftar Import</label>
                        <input type="text" name="tarikh_daftar_import" class="form-control"
                            placeholder="YYYY-MM-DD atau DD/MM/YYYY">
                    </div>
                </div>

                <div class="section-header">Replacement (Jika Ada)</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">No. Replacement Carnet</label>
                        <input type="text" name="no_replacement" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tarikh Lanjutan Replacement</label>
                        <input type="text" name="tarikh_lanjutan" class="form-control">
                    </div>
                </div>

                <div class="section-header">Lain-lain</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Status Penyelesaian</label>
                        <input type="text" name="status1" class="form-control" list="status1Options"
                            placeholder="Pilih atau Taip">
                        <datalist id="status1Options">
                            <option value="SELESAI">
                            <option value="BELUM SELESAI">
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Catatan / Status Tambahan</label>
                        <input type="text" name="status2" class="form-control">
                    </div>
                </div>

                <div class="mt-5 pt-3 border-top text-end">
                    <a href="index.php" class="btn btn-light rounded-pill px-4 me-2">Batal</a>
                    <button type="submit" class="btn btn-gradient rounded-pill px-5">
                        <i class="fas fa-plus me-2"></i> Tambah Rekod
                    </button>
                </div>

            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>