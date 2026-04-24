<?php
// --- ENABLE ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- DATABASE CONNECTION ---
include 'config.php';

echo "<h1>GB/PEKEMA Data Population Script (100 Records)</h1>";

// --- SAMPLE DATA GENERATION ---
$first_names = ['Ahmad', 'Razali', 'Tan', 'Chan', 'Sarah', 'Lim', 'Siti', 'Muthu', 'David', 'Farah'];
$last_names = ['Bin Abdullah', 'Bin Ismail', 'Wei Ling', 'Siew Mun', 'Binti Kassim', 'Goh', 'Binti Saleh', 'Pillai', 'Wong', 'Binti Rahman'];
$company_prefixes = ['Maju', 'Global', 'Jaya', 'Bintang', 'Prestige', 'Synergy', 'Apex', 'Titan', 'Zenith', 'Meridian'];
$company_suffixes = ['Motors', 'Automart', 'Auto Imports', 'Forwarding', 'Trading', 'Ventures', 'Holdings', 'Sdn Bhd'];
$streets = ['Jalan Industri', 'Jalan Pelabuhan', 'Jalan Ampang', 'Jalan Klang Lama', 'Jalan PJS', 'Jalan Teknologi', 'Jalan Perdana', 'Jalan Duta'];
$cities = [
    '40150 Shah Alam, Selangor', '42000 Port Klang, Selangor', '50450 Kuala Lumpur', '58100 Kuala Lumpur', 
    '47500 Subang Jaya, Selangor', '63000 Cyberjaya, Selangor', '60000 Petaling Jaya, Selangor'
];

$companies = [];
for ($i = 0; $i < 100; $i++) {
    $company_name = $company_prefixes[array_rand($company_prefixes)] . ' ' . $company_suffixes[array_rand($company_suffixes)];
    $address = 'Lot ' . rand(1, 500) . ', ' . $streets[array_rand($streets)] . ', ' . $cities[array_rand($cities)];
    $pic = ($i % 2 == 0 ? 'Encik ' : 'Ms. ') . $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
    $phone = '01' . rand(0, 9) . '-' . rand(1000000, 9999999);
    $companies[] = [$company_name, $address, $pic, $phone];
}

// --- PREPARE INSERT STATEMENT ---
$stmt = $conn->prepare("INSERT INTO gbpekema (nama, alamat, pic, no_tel) VALUES (?, ?, ?, ?)");

if (!$stmt) {
    die("<strong>Error preparing statement:</strong> " . $conn->error);
}

$record_count = 0;
foreach ($companies as $company) {
    $stmt->bind_param("ssss", $company[0], $company[1], $company[2], $company[3]);

    if ($stmt->execute()) {
        $record_count++;
    } else {
        if ($conn->errno == 1062) {
            echo "<p style='color:orange;'>Skipped duplicate entry: " . htmlspecialchars($company[0]) . "</p>";
        } else {
            echo "<p style='color:red;'>Failed to insert record for " . htmlspecialchars($company[0]) . ": " . $stmt->error . "</p>";
        }
    }
}

echo "<h2>Population Complete!</h2>";
echo "<p style='color:green; font-weight:bold;'>" . $record_count . " new sample records were successfully inserted into the 'gbpekema' table.</p>";
echo "<p>You can now delete this file ('populate_gbpekema.php').</p>";

$stmt->close();
$conn->close();
?>
