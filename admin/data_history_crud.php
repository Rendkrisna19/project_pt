<?php
// pages/data_history_crud.php
// BACKEND KHUSUS HISTORY JABATAN (Stabil & Error Free)

session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../vendor/autoload.php'; 
require_once '../config/database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']); exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    // 1. LIST DATA
    if ($action === 'list') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $kebun_id = isset($_POST['kebun_id']) ? $_POST['kebun_id'] : '';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT h.*, k.nama_lengkap as nama_karyawan, k.id_sap as sap_id, m.nama_kebun 
                FROM data_history_jabatan h
                JOIN data_karyawan k ON h.karyawan_id = k.id
                LEFT JOIN md_kebun m ON h.kebun_id = m.id
                WHERE 1=1";
        
        $params = [];
        if ($q) {
            $sql .= " AND (k.nama_lengkap LIKE :q OR h.no_surat LIKE :q OR k.id_sap LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($kebun_id) {
            $sql .= " AND h.kebun_id = :kid";
            $params[':kid'] = $kebun_id;
        }

        // Count
        $stmtC = $conn->prepare("SELECT COUNT(*) as total FROM ($sql) as sub");
        $stmtC->execute($params);
        $total = $stmtC->fetchColumn();

        // Data
        $sql .= " ORDER BY h.tgl_surat DESC LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>true, 'data'=>$data, 'total'=>$total]);
        exit;
    }

    // 2. LIST OPTIONS (KEBUN & KARYAWAN)
    if ($action === 'list_options') {
        $kebun = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC")->fetchAll(PDO::FETCH_ASSOC);
        $karyawan = $conn->query("SELECT id, id_sap, nama_lengkap as nama_karyawan FROM data_karyawan ORDER BY nama_lengkap ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true, 'kebun'=>$kebun, 'karyawan'=>$karyawan]);
        exit;
    }

    // 3. STORE / UPDATE
    if ($action === 'store' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $fields = ['karyawan_id', 'kebun_id', 'afdeling', 'strata', 'tgl_surat', 'no_surat', 'jabatan_lama', 'jabatan_baru', 'keterangan'];
        $params = [];
        foreach($fields as $f) $params[":$f"] = $_POST[$f] ?? null;

        // Upload
        $file_sk = null;
        if (!empty($_FILES['file_sk']['name'])) {
            $dir = "../uploads/sk/"; if(!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['file_sk']['name'], PATHINFO_EXTENSION);
            $file_sk = "SK_" . time() . ".$ext";
            move_uploaded_file($_FILES['file_sk']['tmp_name'], $dir . $file_sk);
        }

        if ($action === 'store') {
            $cols = implode(',', $fields);
            $vals = implode(',', array_keys($params));
            if ($file_sk) { $cols .= ",file_sk"; $vals .= ",:file"; $params[':file'] = $file_sk; }
            
            $stmt = $conn->prepare("INSERT INTO data_history_jabatan ($cols) VALUES ($vals)");
            $stmt->execute($params);
        } else {
            $set = []; foreach($fields as $f) $set[] = "$f = :$f";
            if ($file_sk) { $set[] = "file_sk = :file"; $params[':file'] = $file_sk; }
            
            $sql = "UPDATE data_history_jabatan SET " . implode(',', $set) . " WHERE id = :id";
            $params[':id'] = $id;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        echo json_encode(['success'=>true]); exit;
    }

    // 4. DELETE
    if ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT file_sk FROM data_history_jabatan WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if($old && file_exists("../uploads/sk/$old")) unlink("../uploads/sk/$old");
        
        $conn->prepare("DELETE FROM data_history_jabatan WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>