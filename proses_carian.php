<?php
header('Content-Type: application/json');
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'error' => 'Akses dinafikan.']);
    exit();
}

$query = $_POST['query'] ?? '';

if (empty($query)) {
    echo json_encode(['success' => false, 'error' => 'Sila masukkan pertanyaan.']);
    exit();
}

// Store in recent searches
if (!isset($_SESSION['recent_searches'])) {
    $_SESSION['recent_searches'] = [];
}
if (!in_array($query, $_SESSION['recent_searches'])) {
    array_unshift($_SESSION['recent_searches'], $query);
    $_SESSION['recent_searches'] = array_slice($_SESSION['recent_searches'], 0, 10);
}

$query_lower = strtolower($query);

$response = [
    'success' => true,
    'type' => 'list',
    'data' => []
];

// Helper to extract year
$year = null;
$year_range = null;
if (preg_match('/(202[0-9]|201[0-9])\s*hingga\s*(202[0-9]|201[0-9])/', $query_lower, $matches)) {
    $year_range = [$matches[1], $matches[2]];
} elseif (preg_match('/(202[0-9]|201[0-9])/', $query_lower, $matches)) {
    $year = $matches[1];
}

$color = null;
$colors = ['putih', 'hitam', 'merah', 'biru', 'kelabu', 'silver', 'kuning', 'abu-abu', 'coklat', 'hijau'];
foreach ($colors as $c) {
    if (strpos($query_lower, $c) !== false) {
        $color = $c;
        break;
    }
}

$duty_condition = null;
if (preg_match('/bawah.*?(\d+)/', $query_lower, $m)) {
    $duty_condition = " <= " . (int)$m[1];
} elseif (preg_match('/lebih.*?(\d+)/', $query_lower, $m)) {
    $duty_condition = " >= " . (int)$m[1];
}

$cc_condition = null;
if (preg_match('/cc.*?(bawah|lebih|dari)?\s*(\d+)/', $query_lower, $m)) {
    $op = ($m[1] == 'bawah') ? '<=' : '>=';
    $cc_condition = " $op " . (int)$m[2];
}

function apply_filters($sql_or_where, &$where_array, $conn, $query_lower) {
    global $year, $year_range, $color, $duty_condition, $cc_condition;
    $conditions = [];
    if (is_array($where_array)) $conditions = &$where_array;
    
    // Licensee Restriction
    $is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
    $licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;
    if ($is_licensee) {
        $conditions[] = "v.gbpekema_id = " . (int)$licensee_gb_id;
    }
    
    if ($year_range) {
        $conditions[] = "YEAR(COALESCE(v.payment_date, v.created_at)) BETWEEN " . (int)$year_range[0] . " AND " . (int)$year_range[1];
    } elseif ($year) {
        $conditions[] = "YEAR(COALESCE(v.payment_date, v.created_at)) = " . (int)$year;
    }

    if ($color) {
        $col = $conn->real_escape_string($color);
        $conditions[] = "v.color LIKE '%$col%'";
    }

    if ($duty_condition) {
        $conditions[] = "v.duty_rm" . $duty_condition;
    }
    
    if ($cc_condition) {
        $conditions[] = "v.engine_cc" . $cc_condition;
    }

    // Brands
    $brands = ['toyota', 'honda', 'mercedes', 'bmw', 'voxy', 'vellfire', 'alphard', 'nissan', 'mazda', 'lexus', 'audi', 'porsche'];
    foreach ($brands as $brand) {
        if (strpos($query_lower, $brand) !== false) {
            $brand_escaped = $conn->real_escape_string($brand);
            $conditions[] = "v.vehicle_model LIKE '%$brand_escaped%'";
        }
    }
    
    // Models
    $models = ['harrier', 'camry', 'accord', 'civic'];
    foreach ($models as $model) {
        if (strpos($query_lower, $model) !== false) {
            $model_escaped = $conn->real_escape_string($model);
            $conditions[] = "v.vehicle_model LIKE '%$model_escaped%'";
        }
    }

    if (is_string($sql_or_where)) {
        if (count($conditions) > 0) {
            return $sql_or_where . " AND " . implode(' AND ', $conditions);
        }
        return $sql_or_where;
    }
}

// 1. COUNT QUERIES (Berapa kenderaan, jumlah unit, etc)
if (preg_match('/(berapa|jumlah|bilangan).*(kenderaan|unit|kereta)/', $query_lower)) {
    $response['type'] = 'count';
    $sql = "SELECT COUNT(*) as count FROM vehicle_inventory v WHERE 1=1";
    $sql = apply_filters($sql, $dummy, $conn, $query_lower);
    
    $res = $conn->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        $response['data'] = ['count' => $row['count'] ?? 0];
    } else {
        $response['data'] = ['count' => 0];
    }
}
// 2. SUMMARY QUERIES (Total Duty/Tax)
elseif (preg_match('/(jumlah|total|berapa|hitung).*(cukai|duty|bayaran|duti)/', $query_lower)) {
    $response['type'] = 'summary';
    $sql = "SELECT SUM(duty_rm) as value, COUNT(*) as count FROM vehicle_inventory v WHERE 1=1";
    $sql = apply_filters($sql, $dummy, $conn, $query_lower);
    
    $res = $conn->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        $response['data'] = [
            'value' => $row['value'] ?? 0,
            'count' => $row['count'] ?? 0
        ];
    } else {
        $response['data'] = ['value' => 0, 'count' => 0];
    }
}
// 3. COMPANY/SYARIKAT QUERIES
elseif (preg_match('/(syarikat|company|entiti|gb|pekema)/', $query_lower)) {
    $is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
    $licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;
    
    $where_company = "";
    if ($is_licensee) {
        $where_company = " WHERE id = " . (int)$licensee_gb_id;
    }
    
    $sql = "SELECT id, nama as company_name, negeri, no_tel FROM gbpekema $where_company ORDER BY nama ASC LIMIT 50";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $response['data'][] = [
                'id' => $row['id'],
                'vehicle_model' => $row['company_name'],
                'chassis_no' => 'Syarikat',
                'company_name' => '-',
                'duty_rm' => 0,
                'match_type' => 'syarikat',
                'negeri' => $row['negeri'] ?: 'Tiada Maklumat Negeri',
                'no_tel' => $row['no_tel'] ?: 'Tiada Maklumat Telefon'
            ];
        }
    }
}
// 4. HIGHEST/LOWEST QUERIES
elseif (preg_match('/(tertinggi|highest|terbesar|maximum|max)/', $query_lower)) {
    $where = ["1=1"];
    apply_filters(null, $where, $conn, $query_lower);
    $sql = "SELECT v.id, v.vehicle_model, v.chassis_number as chassis_no, v.duty_rm, g.nama as company_name 
            FROM vehicle_inventory v
            LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
            WHERE v.duty_rm IS NOT NULL AND " . implode(' AND ', $where) . "
            ORDER BY v.duty_rm DESC
            LIMIT 10";
    
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['match_type'] = 'kenderaan';
            $response['data'][] = $row;
        }
    }
}
elseif (preg_match('/(terendah|lowest|terkecil|minimum|min)/', $query_lower)) {
    $where = ["1=1"];
    apply_filters(null, $where, $conn, $query_lower);
    $sql = "SELECT v.id, v.vehicle_model, v.chassis_number as chassis_no, v.duty_rm, g.nama as company_name 
            FROM vehicle_inventory v
            LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
            WHERE v.duty_rm IS NOT NULL AND v.duty_rm > 0 AND " . implode(' AND ', $where) . "
            ORDER BY v.duty_rm ASC
            LIMIT 10";
    
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['match_type'] = 'kenderaan';
            $response['data'][] = $row;
        }
    }
}
// 5. RECENT/LATEST QUERIES
elseif (preg_match('/(terkini|terbaru|latest|recent|baru)/', $query_lower)) {
    $where = ["1=1"];
    apply_filters(null, $where, $conn, $query_lower);
    $sql = "SELECT v.id, v.vehicle_model, v.chassis_number as chassis_no, v.duty_rm, g.nama as company_name 
            FROM vehicle_inventory v
            LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.created_at DESC
            LIMIT 20";
    
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['match_type'] = 'kenderaan';
            $response['data'][] = $row;
        }
    }
}
// 6. LIST QUERIES (Brand/Model specific)
else {
    $where = ["1=1"];
    apply_filters(null, $where, $conn, $query_lower);

    $sql = "SELECT v.id, v.vehicle_model, v.chassis_number as chassis_no, v.duty_rm, g.nama as company_name 
            FROM vehicle_inventory v
            LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.created_at DESC
            LIMIT 50";
            
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['match_type'] = 'kenderaan';
            $response['data'][] = $row;
        }
    }
}

echo json_encode($response);
?>
