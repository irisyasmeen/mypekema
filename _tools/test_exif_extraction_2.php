<?php
include 'exif_helper.php';
$filePath = 'uploads/vehicles/69b36841c4ea9_79863b01.jpeg';
echo "Checking file: $filePath\n";
$exif = ExifHelper::extractExifAsJson($filePath);
if ($exif) { echo "EXIF Data Found:\n$exif\n"; } else { echo "No EXIF data.\n"; }
?>
