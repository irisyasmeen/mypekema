<?php
include 'config.php';

$res = $conn->query("SELECT id, nama FROM gbpekema WHERE kod_gudang IS NULL OR kod_gudang = ''");
$updated = 0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        $nama = $row['nama'];
        
        // Extract first word (potential kod)
        $parts = explode(' ', trim($nama));
        $potential_kod = $parts[0];
        
        // Simple heuristic: if it's 3-4 chars and has a number or is uppercase, use it
        if (strlen($potential_kod) >= 3 && strlen($potential_kod) <= 5) {
            $stmt = $conn->prepare("UPDATE gbpekema SET kod_gudang = ? WHERE id = ?");
            $stmt->bind_param("si", $potential_kod, $id);
            if ($stmt->execute()) {
                $updated++;
                echo "Updated ID $id: $nama -> $potential_kod\n";
            }
            $stmt->close();
        }
    }
}

echo "\nTotal updated: $updated\n";
?>
