<?php
include 'exif_helper.php';
$filePath = 'uploads/vehicles/69b368d928f25_0290afef.jpeg';
echo "Checking file: $filePath\n";
if (!file_exists($filePath)) {
    die("File does not exist\n");
}
$exif = ExifHelper::extractExifAsJson($filePath);
if ($exif) {
    echo "EXIF Data Found:\n$exif\n";
} else {
    echo "No EXIF data extracted (either none in file or extraction failed).\n";
    // Let's check raw exif just in case
    $raw = @exif_read_data($filePath, 'ANY_TAG', true);
    if ($raw === false) {
        echo "exif_read_data returned false. Check IF the file is a valid JP(E)G/TIFF.\n";
    } else {
        echo "Raw EXIF data exists but not parsed by helper. Keys found: " . implode(', ', array_keys($raw)) . "\n";
    }
}
?>
