<?php
// pemakaian_barang_gudang_crud.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

// Helper & Safety
ini_set('display_errors', '0');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid Method']); exit; }

$db = new Database();
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';

// --- LIST DATA ---
if ($action === 'list') {
    $where = " WHERE 1=1 ";
    $params = [];

    // 1. Filter Tahun
    if (!empty($_POST['tahun'])) { 
        $where .= " AND YEAR(t.tanggal) = :thn"; 
        $params[':thn'] = $_POST['tahun']; 
    }
    
    // 2. Filter Bulan (BARU)
    if (!empty($_POST['bulan'])) { 
        $where .= " AND MONTH(t.tanggal) = :bln"; 
        $params[':bln'] = $_POST['bulan']; 
    }

    // 3. Filter Kebun
    if (!empty($_POST['kebun_id'])) { 
        $where .= " AND t.kebun_id = :kbd"; 
        $params[':kbd'] = $_POST['kebun_id']; 
    }

    // 4. Filter Tanggal Spesifik
    if (!empty($_POST['tanggal'])) { 
        $where .= " AND t.tanggal = :tgl"; 
        $params[':tgl'] = $_POST['tanggal']; 
    }

    // 5. Filter Jenis Bahan
    if (!empty($_POST['jenis_bahan_id'])) { 
        $where .= " AND t.jenis_bahan_id = :jbh"; 
        $params[':jbh'] = $_POST['jenis_bahan_id']; 
    }

    // 6. Logika Limit (BARU)
    $limitClause = "";
    $limitVal = $_POST['limit'] ?? '25';
    if ($limitVal !== 'all') {
        $limitClause = " LIMIT " . (int)$limitVal;
    }

    // Query Utama (Join Kendaraan & Mobil DIHAPUS agar lebih ringan & sesuai frontend)
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

        // Hitung Total (Hanya dari data yang ter-load / atau bisa buat query terpisah jika ingin total seluruh data)
        // Disini kita hitung total dari result set query (sesuai limit)
        $totalJumlah = 0;
        foreach($rows as $r) $totalJumlah += (float)$r['jumlah'];

        echo json_encode(['success' => true, 'data' => $rows, 'total_jumlah' => $totalJumlah]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- STORE / UPDATE ---
if ($action === 'store' || $action === 'update') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Mapping Data (KENDARAAN & MOBIL DIHAPUS)
    $data = [
        ':kbd' => $_POST['kebun_id'] ?? null,
        ':tgl' => $_POST['tanggal'] ?? null,
        ':jbb' => $_POST['jenis_bahan_id'] ?? null,
        ':jml' => $_POST['jumlah'] ?? 0,
        ':doc' => $_POST['no_dokumen'] ?? '',
        ':ket' => $_POST['keterangan'] ?? ''
    ];

    /* Catatan: 
       Jika kolom 'jenis_kendaraan_id' atau 'mobil_id' di database bersifat NOT NULL, 
       Anda mungkin perlu mengubah struktur database menjadi NULLABLE 
       atau mengirim nilai default (misal 0 atau NULL jika diizinkan).
       Disini diasumsikan kolom tersebut boleh NULL atau memiliki default.
    */

    if ($action === 'store') {
        // Query Insert (Tanpa Kendaraan/Mobil)
        $sql = "INSERT INTO tr_pemakaian_barang_gudang 
                (kebun_id, tanggal, jenis_bahan_id, jumlah, no_dokumen, keterangan) 
                VALUES (:kbd, :tgl, :jbb, :jml, :doc, :ket)";
    } else {
        // Query Update (Tanpa Kendaraan/Mobil)
        $sql = "UPDATE tr_pemakaian_barang_gudang 
                SET kebun_id=:kbd, tanggal=:tgl, jenis_bahan_id=:jbb, jumlah=:jml, no_dokumen=:doc, keterangan=:ket 
                WHERE id=:id";
        $data[':id'] = $id;
    }

    try {
        $st = $conn->prepare($sql);
        $st->execute($data);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- DELETE ---
if ($action === 'delete') {
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