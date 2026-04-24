<?php
session_start();
include 'config.php'; // Ensure this path is correct

// Use user_email for session checking with Google Auth
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'user';
if ($user_role === 'licensee') {
    header("Location: vehicles.php");
    exit();
}

// Fetch GB/PEKEMA list for the filter dropdown
$gb_list = [];
$gb_sql = "SELECT id, nama FROM gbpekema ORDER BY nama ASC";
if ($gb_result = $conn->query($gb_sql)) {
    $gb_list = $gb_result->fetch_all(MYSQLI_ASSOC);
}

// --- FILTER & SEARCH LOGIC ---
$search_term = '';
$gb_id_filter = 0;
$where_clauses = [];
$params = [];
$types = '';

// GB Pekema Filter
if (isset($_GET['gb_id']) && is_numeric($_GET['gb_id']) && $_GET['gb_id'] > 0) {
    $gb_id_filter = (int)$_GET['gb_id'];
    $where_clauses[] = "v.gbpekema_id = ?";
    $params[] = $gb_id_filter;
    $types .= 'i';
}

// Search Term Filter (Smart Search - Multi Keyword)
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $keywords = explode(' ', $search_term);
    foreach ($keywords as $kw) {
        $kw = trim($kw);
        if ($kw === '') continue;
        $where_clauses[] = "(v.lot_number LIKE ? OR v.vehicle_model LIKE ? OR v.chassis_number LIKE ? OR v.k8_number_full LIKE ? OR v.color LIKE ?)";
        $like_term = "%" . $kw . "%";
        $params[] = $like_term; // lot_number
        $params[] = $like_term; // vehicle_model
        $params[] = $like_term; // chassis_number
        $params[] = $like_term; // k8_number_full
        $params[] = $like_term; // color
        $types .= 'sssss';
    }
}

$query_part = '';
if (!empty($where_clauses)) {
    $query_part = " WHERE " . implode(' AND ', $where_clauses);
}


// --- PAGINATION LOGIC ---
$records_per_page = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

// --- DATA FETCHING ---
// Get total records based on search/filter
// FIXED: Added LEFT JOIN to allow filtering by g.negeri in COUNT query
$total_records_sql = "SELECT COUNT(v.id) FROM vehicle_archive v 
                      LEFT JOIN gbpekema g ON v.gbpekema_id = g.id" . $query_part;
$stmt_total = $conn->prepare($total_records_sql);
if (!empty($params)) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total_result = $stmt_total->get_result();
$total_records = $total_result->fetch_row()[0];
$total_pages = ceil($total_records / $records_per_page);
$stmt_total->close();

// Fetch records for the current page, with search, filter, and new sorting
$sql = "SELECT v.id, v.lot_number, v.vehicle_model, v.chassis_number, v.manufacturing_year, v.duty_rm, v.color, v.import_date, v.archived_at, TIMESTAMPDIFF(MONTH, v.import_date, COALESCE(v.archived_at, NOW())) as bulan_simpanan, g.nama as gbpekema_nama, g.negeri as gbpekema_negeri 
        FROM vehicle_archive v
        LEFT JOIN gbpekema g ON v.gbpekema_id = g.id" 
        . $query_part . " 
        ORDER BY g.nama ASC, v.lot_number ASC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

// Bind search/filter parameters and pagination parameters
$final_params = $params;
$final_params[] = $start_from;
$final_params[] = $records_per_page;
$final_types = $types . 'ii';

$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$result = $stmt->get_result();
$vehicles = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arkib Kenderaan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f0f5ff; /* bg-blue-50 */
        }
        .pagination-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border: 1px solid #d1d5db;
            background-color: white;
            color: #1e3a8a;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
        }
        .pagination-link:hover {
            background-color: #eff6ff;
            border-color: #93c5fd;
        }
        .pagination-link.disabled {
            color: #9ca3af;
            pointer-events: none;
            background-color: #f9fafb;
        }
        /* Custom scrollbar for better visibility */
        .overflow-x-auto::-webkit-scrollbar {
            height: 8px; /* Height of the scrollbar */
        }
        .overflow-x-auto::-webkit-scrollbar-track {
            background: #e0e7ff; /* A lighter blue */
        }
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #6366f1; /* A darker, prominent blue */
            border-radius: 4px;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: #4f46e5; /* Darker on hover */
        }
    </style>
</head>
<body class="bg-blue-50">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-blue-900">Arkib Kenderaan</h1>
                <p class="text-blue-700 mt-1">Paparan rekod inventori yang telah dipindahkan ke arkib.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 mt-4 sm:mt-0">
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <a href="unarchive_vehicles.php" onclick="return confirm('AMARAN: Anda pasti untuk mengembalikan SEMUA kenderaan dari arkib semula ke inventori?')" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                    <i class="fas fa-box-open mr-2"></i>Nyah-Arkib Semua
                </a>
                <?php endif; ?>
                <a href="vehicles.php" class="bg-slate-500 hover:bg-slate-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                    <i class="fas fa-arrow-left mr-2"></i>Kembali ke Inventori
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo ($_SESSION['msg_type'] == 'success') ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                <div class="flex items-center">
                    <i class="fas <?php echo ($_SESSION['msg_type'] == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3 text-lg"></i>
                    <span class="font-medium"><?php echo htmlspecialchars($_SESSION['message']); ?></span>
                </div>
            </div>
            <?php 
                unset($_SESSION['message']);
                unset($_SESSION['msg_type']);
            ?>
        <?php endif; ?>
        
        <!-- Filter and Search Form -->
        <div class="mb-6">
            <form action="arkib.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="gb_id" class="block text-sm font-medium text-gray-700">Tapis ikut Syarikat</label>
                    <select name="gb_id" id="gb_id" class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg shadow-sm" onchange="this.form.submit()">
                        <option value="0">Semua Syarikat</option>
                        <?php foreach ($gb_list as $gb): ?>
                            <option value="<?= $gb['id'] ?>" <?= ($gb_id_filter == $gb['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($gb['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700">Carian Kata Kunci</label>
                    <div class="relative mt-1">
                        <input type="text" name="search" id="search" placeholder="Cari No. Lot, No. K8, No. Casis, Warna..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($search_term) ?>">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
            </form>
        </div>


        <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-blue-100">
            <!-- Wrapper for horizontal scrolling -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-blue-200">
                    <thead class="bg-blue-100">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-blue-800 uppercase tracking-wider">No. Lot</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-blue-800 uppercase tracking-wider">Syarikat GB/PEKEMA</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-blue-800 uppercase tracking-wider">No. Casis</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-blue-800 uppercase tracking-wider">Warna</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-blue-800 uppercase tracking-wider">Tarikh Arkib</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-blue-800 uppercase tracking-wider">Tempoh</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-blue-800 uppercase tracking-wider">Status Bayaran</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-blue-800 uppercase tracking-wider">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($vehicles)): ?>
                            <?php foreach ($vehicles as $vehicle): 
                                $lot_color_class = 'text-blue-600 hover:text-blue-800'; // Default text color
                                $tempoh_display = '<span class="text-gray-400">-</span>';

                                if (!empty($vehicle['import_date']) && $vehicle['import_date'] != '0000-00-00') {
                                    $bulan = (int)$vehicle['bulan_simpanan'];
                                    if ($bulan < 22) {
                                        $color_class = 'bg-green-100 text-green-800 border border-green-200 shadow-sm';
                                        $icon = '<i class="fas fa-circle text-green-500 text-[10px] mr-1.5"></i>';
                                        $label = $bulan . ' Bulan';
                                    } elseif ($bulan >= 22 && $bulan <= 24) {
                                        $color_class = 'bg-yellow-100 text-yellow-800 border border-yellow-200 shadow-sm';
                                        $icon = '<i class="fas fa-circle text-yellow-500 text-[10px] mr-1.5"></i>';
                                        $label = $bulan . ' Bulan';
                                    } elseif ($bulan >= 25 && $bulan <= 45) {
                                        $color_class = 'bg-green-100 text-green-800 border border-green-300 shadow-sm';
                                        $icon = '<i class="fas fa-circle text-green-600 text-[10px] mr-1.5"></i>';
                                        $label = $bulan . ' Bulan (R)';
                                    } elseif ($bulan >= 46 && $bulan <= 48) {
                                        $color_class = 'bg-yellow-100 text-yellow-800 border border-yellow-300 shadow-sm';
                                        $icon = '<i class="fas fa-circle text-yellow-600 text-[10px] mr-1.5"></i>';
                                        $label = $bulan . ' Bulan (R)';
                                    } else {
                                        $color_class = 'bg-red-100 text-red-800 font-bold border border-red-300 shadow-sm';
                                        $icon = '<i class="fas fa-circle text-red-600 text-[10px] mr-1.5 animate-pulse"></i>';
                                        $label = $bulan . ' Bulan (R)';
                                    }
                                    
                                    $lot_color_class = $color_class;
                                    $tempoh_display = '<span class="px-3 py-1.5 inline-flex items-center text-xs leading-5 font-bold rounded-full ' . $color_class . '">' . $icon . $label . '</span>';
                                }
                            ?>
                                <tr class="hover:bg-blue-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <a href="kad_kenderaan.php?id=<?= $vehicle['id'] ?>" class="inline-block px-3 py-1.5 rounded-lg hover:underline font-semibold <?= $lot_color_class ?>">
                                            <?= htmlspecialchars($vehicle['lot_number']) ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($vehicle['gbpekema_nama'] ?? 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono"><?= htmlspecialchars($vehicle['chassis_number']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($vehicle['color'] ?? 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= !empty($vehicle['archived_at']) ? date('d/m/Y', strtotime($vehicle['archived_at'])) : '-' ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <?= $tempoh_display ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-slate-100 text-slate-800">
                                            Diarkibkan
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center space-x-4">
                                        <a href="vehicle_details.php?id=<?= $vehicle['id'] ?>&archive=1" class="text-blue-600 hover:text-blue-900" title="Lihat">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                        <a href="unarchive_vehicles.php?id=<?= $vehicle['id'] ?>" onclick="return confirm('Anda pasti mahu nyah-arkib kenderaan ini?')" class="text-orange-600 hover:text-orange-900" title="Nyah-Arkib">
                                            <i class="fas fa-box-open"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-12 text-gray-500">
                                     <i class="fas fa-search text-4xl mb-3"></i>
                                    <p>Tiada rekod kenderaan ditemui sepadan dengan kriteria anda.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm text-blue-800">
                Menunjukkan <span class="font-bold"><?= $start_from + 1 ?></span> hingga <span class="font-bold"><?= min($start_from + $records_per_page, $total_records) ?></span> dari <span class="font-bold"><?= $total_records ?></span> rekod
            </div>
            <nav class="flex items-center space-x-1">
                <?php 
                    $base_query = $_GET;
                    unset($base_query['page']);
                    $query_str = http_build_query($base_query);
                    $query_str = $query_str ? $query_str . '&' : '';
                ?>
                
                <!-- Butang Sebelum -->
                <a href="?<?= $query_str ?>page=<?= max(1, $page - 1) ?>" class="pagination-link <?= $page <= 1 ? 'disabled' : '' ?>" title="Sebelumnya">
                    <i class="fas fa-chevron-left"></i>
                </a>

                <!-- Nombor Halaman -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?'.$query_str.'page=1" class="pagination-link">1</a>';
                    if ($start_page > 2) echo '<span class="px-2 text-gray-400">...</span>';
                }

                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?<?= $query_str ?>page=<?= $i ?>" class="pagination-link <?= $page == $i ? 'bg-blue-700 text-white border-blue-700 hover:bg-blue-800' : 'hover:bg-blue-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; 

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span class="px-2 text-gray-400">...</span>';
                    echo '<a href="?'.$query_str.'page='.$total_pages.'" class="pagination-link">'.$total_pages.'</a>';
                }
                ?>

                <!-- Butang Seterusnya -->
                <a href="?<?= $query_str ?>page=<?= min($total_pages, $page + 1) ?>" class="pagination-link <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Seterusnya">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </nav>
        </div>
        <?php endif; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            let timer;

            // Smart Auto-submit (Debounce)
            searchInput.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    this.form.submit();
                }, 800); // Tunggu 800ms selepas berhenti menaip
            });

            // Fokus semula pada input carian selepas halaman dimuat semula
            if (searchInput.value !== '') {
                const val = searchInput.value;
                searchInput.value = '';
                searchInput.focus();
                searchInput.value = val; // Letakkan kursor di hujung teks
            }
        });
    </script>
</body>
</html>

