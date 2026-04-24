<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Info</h1>";
echo "Exif extension: " . (extension_loaded('exif') ? 'Enabled' : 'Disabled') . "<br>";

try {
    echo "Attempting to include exif_helper.php...<br>";
    include 'exif_helper.php';
    echo "Included exif_helper.php successfully.<br>";
    
    if (class_exists('ExifHelper')) {
        echo "ExifHelper class exists.<br>";
    } else {
        echo "ExifHelper class DOES NOT exist.<br>";
    }
} catch (Throwable $t) {
    echo "Caught Error: " . $t->getMessage() . "<br>";
    echo "File: " . $t->getFile() . " line " . $t->getLine() . "<br>";
}

echo "Done.";
?>
