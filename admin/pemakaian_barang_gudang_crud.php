<?php
// pemakaian_barang_gudang_crud.php (FINAL SECURED)
// Role: Viewer (Read Only), Staf (Create Only), Admin (Full Access)

session_start();
header('Content-Type: application/json');

// 1. Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    echo json_encode(['success'=>false,'message'=>'Akses ditolak. Silakan login.']); 
    exit; 
}

// 2. Cek CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); 
    exit; 
}
// Opsional: Cek Token CSRF jika dikirim dari JS
// if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { ... }

// 3. AMBIL ROLE
$role = $_SESSION['user_role'] ?? 'viewer'; 

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';

// --- LIST DATA (SEMUA ROLE BOLEH) ---
if ($action === 'list') {
    $where = " WHERE 1=1 ";
    $params = [];

    // Filter Logic
    if (!empty($_POST['tahun'])) { 
        $where .= " AND YEAR(t.tanggal) = :thn"; 
        $params[':thn'] = $_POST['tahun']; 
    }
    if (!empty($_POST['bulan'])) { 
        $where .= " AND MONTH(t.tanggal) = :bln"; 
        $params[':bln'] = $_POST['bulan']; 
    }
    if (!empty($_POST['kebun_id'])) { 
        $where .= " AND t.kebun_id = :kbd"; 
        $params[':kbd'] = $_POST['kebun_id']; 
    }
    if (!empty($_POST['tanggal'])) { 
        $where .= " AND t.tanggal = :tgl"; 
        $params[':tgl'] = $_POST['tanggal']; 
    }
    if (!empty($_POST['jenis_bahan_id'])) { 
        $where .= " AND t.jenis_bahan_id = :jbh"; 
        $params[':jbh'] = $_POST['jenis_bahan_id']; 
    }

    $limitClause = "";
    $limitVal = $_POST['limit'] ?? '25';
    if ($limitVal !== 'all') {
        $limitClause = " LIMIT " . (int)$limitVal;
    }

    $sql = "SELECT t.*, 
                   k.nama_kebun, 
                   bbm.nama AS nama_bahan,
                   bbm.satuan AS satuan
            FROM tr_pemakaian_barang_gudang t
            LEFT JOIN md_kebun k ON t.kebun_id = k.id
            LEFT JOIN md_jenis_bahan_bakar_pelumas bbm ON t.jenis_bahan_id = bbm.id
            $where
            ORDER BY t.tanggal DESC, t.id DESC
            $limitClause";

    try {
        $st = $conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Hitung Total (Sederhana dari result set)
        $totalJumlah = 0;
        foreach($rows as $r) $totalJumlah += (float)$r['jumlah'];

        echo json_encode(['success' => true, 'data' => $rows, 'total_jumlah' => $totalJumlah]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- STORE (TAMBAH DATA) ---
if ($action === 'store') {
    // Permission Check: Admin & Staf Only
    if ($role !== 'admin' && $role !== 'staf') {
        echo json_encode(['success'=>false, 'message'=>'Anda tidak memiliki izin untuk menambah data.']); 
        exit;
    }

    $sql = "INSERT INTO tr_pemakaian_barang_gudang 
            (kebun_id, tanggal, jenis_bahan_id, jumlah, no_dokumen, keterangan) 
            VALUES (:kbd, :tgl, :jbb, :jml, :doc, :ket)";
    
    $data = [
        ':kbd' => $_POST['kebun_id'] ?? null,
        ':tgl' => $_POST['tanggal'] ?? null,
        ':jbb' => $_POST['jenis_bahan_id'] ?? null,
        ':jml' => $_POST['jumlah'] ?? 0,
        ':doc' => $_POST['no_dokumen'] ?? '',
        ':ket' => $_POST['keterangan'] ?? ''
    ];

    try {
        $st = $conn->prepare($sql);
        $st->execute($data);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- UPDATE (EDIT DATA) ---
if ($action === 'update') {
    // Permission Check: Admin Only
    if ($role !== 'admin') {
        echo json_encode(['success'=>false, 'message'=>'Hanya Admin yang boleh mengubah data.']); 
        exit;
    }

    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    $sql = "UPDATE tr_pemakaian_barang_gudang 
            SET kebun_id=:kbd, tanggal=:tgl, jenis_bahan_id=:jbb, jumlah=:jml, no_dokumen=:doc, keterangan=:ket 
            WHERE id=:id";
    
    $data = [
        ':kbd' => $_POST['kebun_id'] ?? null,
        ':tgl' => $_POST['tanggal'] ?? null,
        ':jbb' => $_POST['jenis_bahan_id'] ?? null,
        ':jml' => $_POST['jumlah'] ?? 0,
        ':doc' => $_POST['no_dokumen'] ?? '',
        ':ket' => $_POST['keterangan'] ?? '',
        ':id'  => $id
    ];

    try {
        $st = $conn->prepare($sql);
        $st->execute($data);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- DELETE (HAPUS DATA) ---
if ($action === 'delete') {
    // Permission Check: Admin Only
    if ($role !== 'admin') {
        echo json_encode(['success'=>false, 'message'=>'Hanya Admin yang boleh menghapus data.']); 
        exit;
    }

    $id = (int)$_POST['id'];
    try {
        $st = $conn->prepare("DELETE FROM tr_pemakaian_barang_gudang WHERE id = ?");
        $st->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>