<?php
// --- ENABLE ERROR REPORTING & INCREASE SERVER LIMITS ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M'); // Increased memory limit for very large files
set_time_limit(600); // Increased execution time to 10 minutes

session_start();

// --- DATABASE CONNECTION VARIABLE ---
// Declare it outside try block to be accessible in the catch block
$conn = null;

// --- MAIN LOGIC WRAPPED IN TRY...CATCH TO PREVENT WHITE SCREEN ---
try {
    // Check if the library file exists
    if (!file_exists('SimpleXLSX.php')) {
        throw new Exception("Ralat Kritikal: Fail pustaka SimpleXLSX.php tidak ditemui.");
    }
    require_once 'SimpleXLSX.php';

    // Check for file upload errors
    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        $error_codes = [
            1 => 'Saiz fail melebihi had pelayan (upload_max_filesize).',
            2 => 'Saiz fail melebihi had borang.',
            3 => 'Fail hanya dimuat naik sebahagian.',
            4 => 'Tiada fail dimuat naik.',
            6 => 'Folder sementara tiada.',
            7 => 'Gagal menulis fail ke cakera.',
            8 => 'Satu sambungan PHP menghentikan muat naik fail.',
        ];
        $error_code = $_FILES['excelFile']['error'] ?? 0;
        $error_message = $error_codes[$error_code] ?? 'Ralat tidak diketahui semasa muat naik.';
        throw new Exception($error_message);
    }

    // --- DATABASE CONNECTION ---
    include 'config.php';
    if ($conn->connect_error) {
        throw new Exception("Sambungan pangkalan data gagal: " . $conn->connect_error);
    }

    if ($xlsx = SimpleXLSX::parse($_FILES['excelFile']['tmp_name'])) {
        $imported_count = 0;
        $updated_count = 0;

        // Start transaction for data integrity
        $conn->begin_transaction();

        $stmt = $conn->prepare("INSERT INTO vehicle_inventory 
            (lot_number, k8_number_full, vehicle_condition, import_date, vehicle_model, chassis_number, engine_number, tpa_date, engine_cc, manufacturing_year, color, k1_number, duty_rm, receipt_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            k8_number_full=VALUES(k8_number_full), vehicle_condition=VALUES(vehicle_condition), import_date=VALUES(import_date), 
            vehicle_model=VALUES(vehicle_model), chassis_number=VALUES(chassis_number), engine_number=VALUES(engine_number), 
            tpa_date=VALUES(tpa_date), engine_cc=VALUES(engine_cc), manufacturing_year=VALUES(manufacturing_year), 
            color=VALUES(color), k1_number=VALUES(k1_number), duty_rm=VALUES(duty_rm), receipt_number=VALUES(receipt_number)");

        if ($stmt === false) {
            throw new Exception("Gagal menyediakan kenyataan SQL: " . $conn->error);
        }

        // --- LOOP THROUGH ALL SHEETS IN THE EXCEL FILE ---
        foreach ($xlsx->getSheetNames() as $sheetIndex => $sheetName) {
            foreach ($xlsx->rows($sheetIndex) as $r_idx => $row) {
                if ($r_idx < 4) continue; // Skip header rows

                $lot_number = $row[1] ?? null;
                // Basic validation: Skip row if lot_number is empty
                if (empty(trim($lot_number))) {
                    continue;
                }

                $k8_parts = [
                    $row[2] ?? '', $row[3] ?? '', $row[4] ?? '',
                    $row[5] ?? '', $row[6] ?? '', $row[7] ?? ''
                ];
                $k8_number_full = implode(',', $k8_parts);
                
                $vehicle_condition = $row[8] ?? 'USED';
                $import_date = !empty($row[9]) ? date('Y-m-d', strtotime(str_replace('/', '-', $row[9]))) : null;
                $vehicle_model = $row[11] ?? null;
                $chassis_number = $row[12] ?? null;
                $engine_number = $row[13] ?? null;
                $tpa_date = !empty($row[14]) ? date('Y-m-d', strtotime(str_replace('/', '-', $row[14]))) : null;
                $engine_cc = !empty($row[15]) ? intval($row[15]) : null;
                $manufacturing_year = !empty($row[16]) ? intval($row[16]) : null;
                $color = $row[17] ?? null;
                $k1_number = $row[18] ?? null;
                $duty_rm = !empty($row[19]) ? floatval($row[19]) : null;
                $receipt_number = $row[21] ?? null;

                $stmt->bind_param("ssssssssiissds",
                    $lot_number, $k8_number_full, $vehicle_condition, $import_date, $vehicle_model, $chassis_number,
                    $engine_number, $tpa_date, $engine_cc, $manufacturing_year, $color, $k1_number, $duty_rm, $receipt_number
                );
                
                if (!$stmt->execute()) {
                    // If a single execution fails, throw an exception to rollback the entire transaction
                    throw new Exception("Gagal melaksanakan 'statement' untuk Lot Number " . htmlspecialchars($lot_number) . ": " . $stmt->error);
                }
                
                if ($stmt->affected_rows === 1) {
                    $imported_count++;
                } elseif ($stmt->affected_rows === 2) { // 2 means 1 row was updated (delete + insert)
                    $updated_count++;
                }
            }
        }
        
        // If everything is successful, commit the changes
        $conn->commit();
        $stmt->close();
        $conn->close();
        
        header("Location: upload_excel.php?status=success&imported=$imported_count&skipped=$updated_count");
        exit();

    } else {
        throw new Exception("Gagal memproses fail Excel: " . SimpleXLSX::parseError());
    }
} catch (Throwable $e) { // PEMBETULAN 1: Tangkap 'Throwable' dan bukan 'Exception'
    // This will catch almost all fatal errors in PHP 7+

    // PEMBETULAN 2: Rollback transaction on error
    if ($conn && $conn->ping()) { // Check if connection was established
        $conn->rollback();
    }
    
    // PEMBETULAN 3: Selalu tutup sambungan
    if ($conn && $conn->ping()) {
        $conn->close();
    }

    // Redirect with a clear error message
    header("Location: upload_excel.php?status=error&message=" . urlencode($e->getMessage()));
    exit();
}
?>