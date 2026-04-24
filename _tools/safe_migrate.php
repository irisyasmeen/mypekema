<?php
include 'config.php';

$cols_to_add = [
    'gbpekema' => [
        ['tarikh_kuatkuasa_lesen', 'DATE DEFAULT NULL'],
        ['tarikh_mula', 'DATE DEFAULT NULL'],
        ['tarikh_akhir', 'DATE DEFAULT NULL'],
        ['kod_gudang', 'VARCHAR(50) DEFAULT NULL']
    ],
    'vehicle_inventory' => [
        ['import_date', 'DATE DEFAULT NULL'],
        ['stesen_asal', 'VARCHAR(100) DEFAULT NULL'],
        ['tarikh_tamat_tempoh_gudang', 'DATE DEFAULT NULL'],
        ['payment_date', 'DATE DEFAULT NULL'],
        ['harga_taksiran', 'DECIMAL(12, 2) DEFAULT 0.00'],
        ['duti_import', 'DECIMAL(12, 2) DEFAULT 0.00'],
        ['duti_eksais', 'DECIMAL(12, 2) DEFAULT 0.00'],
        ['cukai_jualan', 'DECIMAL(12, 2) DEFAULT 0.00'],
        ['duty_rm', 'DECIMAL(12, 2) DEFAULT 0.00'],
        ['exif_data_front', 'TEXT DEFAULT NULL'],
        ['exif_data_rear', 'TEXT DEFAULT NULL'],
        ['exif_data_left', 'TEXT DEFAULT NULL'],
        ['exif_data_right', 'TEXT DEFAULT NULL'],
        ['gbpekema_id', 'INT(11) DEFAULT NULL']
    ]
];

foreach ($cols_to_add as $table => $columns) {
    foreach ($columns as $col) {
        $name = $col[0];
        $def = $col[1];
        
        // Check if column exists
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$name'");
        if ($check->num_rows == 0) {
            $sql = "ALTER TABLE `$table` ADD COLUMN `$name` $def";
            if ($conn->query($sql)) {
                echo "Added $name to $table<br>";
            } else {
                echo "Error adding $name: " . $conn->error . "<br>";
            }
        } else {
            echo "Column $name already exists in $table<br>";
        }
    }
}

echo "Database update check completed.";
?>
