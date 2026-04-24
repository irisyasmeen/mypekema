<?php
include 'config.php';

$sql = "
ALTER TABLE vehicle_inventory
ADD COLUMN image_front VARCHAR(255) NULL AFTER catatan,
ADD COLUMN image_rear VARCHAR(255) NULL AFTER image_front,
ADD COLUMN image_left VARCHAR(255) NULL AFTER image_rear,
ADD COLUMN image_right VARCHAR(255) NULL AFTER image_left,
ADD COLUMN video_file VARCHAR(255) NULL AFTER image_right,
ADD COLUMN exif_data_front TEXT NULL AFTER video_file,
ADD COLUMN exif_data_rear TEXT NULL AFTER exif_data_front,
ADD COLUMN exif_data_left TEXT NULL AFTER exif_data_rear,
ADD COLUMN exif_data_right TEXT NULL AFTER exif_data_left;
";

if ($conn->query($sql) === TRUE) {
    echo "Columns added successfully";
} else {
    echo "Error adding columns: " . $conn->error;
}
$conn->close();
?>