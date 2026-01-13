<?php
// api/get_latest_login.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // Opsional
require_once '../config/database.php';

// Set Timezone
date_default_timezone_set('Asia/Jakarta');

try {
    $db = new Database();
    $conn = $db->getConnection();

    $query = "SELECT id, username, nama_lengkap, last_login 
              FROM users 
              WHERE last_login IS NOT NULL 
              ORDER BY last_login DESC  
              LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as &$row) {
        $row['time_ago'] = time_elapsed_string($row['last_login']);
        $row['nama_lengkap'] = htmlspecialchars($row['nama_lengkap']);
        $row['username'] = htmlspecialchars($row['username']);
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->days / 7);
    $days = $diff->days - ($weeks * 7);

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );

    $vals = array(
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    );

    foreach ($string as $k => &$v) {
        if ($vals[$k]) {
            $v = $vals[$k] . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'Baru saja';
}
?>