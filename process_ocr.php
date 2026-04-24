<?php
// --- ENABLE ERROR REPORTING FOR DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // --- PENTING: Konfigurasi Pelayan ---
    // Ciri ini memerlukan perisian Tesseract OCR dipasang pada pelayan anda.
    // Sila minta bantuan pentadbir pelayan anda untuk pemasangan.
    // Arahkan Tesseract ke fail imej yang dimuat naik, contohnya:
    // $text = shell_exec('tesseract ' . $uploaded_file_path . ' stdout');

    // Check for file upload errors
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Ralat muat naik fail atau fail terlalu besar.');
    }

    // --- Simulasi Proses OCR ---
    // Kerana Tesseract perlu dipasang di pelayan, kod di bawah ini adalah simulasi.
    // Ia akan memulangkan data contoh untuk menunjukkan bagaimana sistem berfungsi.

    // Simulasi teks yang diekstrak dari dokumen
    $simulated_text = "
        BORANG KASTAM NO. 1
        MODEL: TOYOTA / VOXY S-Z
        CHASSIS NO: ZRR80-1234567
        ENJIN NO: 3ZR-A98765
        TAHUN DIBUAT: 2022
    ";

    // --- Logik Pengecaman Teks (Sama seperti sebelum ini) ---
    $extracted_data = [];
    $text_lower = strtolower($simulated_text);

    // Corak untuk mencari maklumat
    $patterns = [
        'chassis_number' => '/(?:chassis no|chassis number|chassis)[\s:.-]*([A-Z0-9-]+)/i',
        'engine_number' => '/(?:engine no|engine number|enjin no)[\s:.-]*([A-Z0-9-]+)/i',
        'vehicle_model' => '/(?:model)[\s:.-]*([A-Z0-9\s\/\\-]+)/i',
        'manufacturing_year' => '/(?:tahun|year)[\s:.-]*(\d{4})/i'
    ];

    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $simulated_text, $matches)) {
            $extracted_data[$key] = trim($matches[1]);
        } else {
            $extracted_data[$key] = '';
        }
    }

    echo json_encode(['success' => true, 'data' => $extracted_data]);

} catch (Exception $e) {
    // Catch any error and return a proper JSON error response
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ralat dalaman pelayan: ' . $e->getMessage()]);
}
?>
