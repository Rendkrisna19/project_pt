<?php
// pages/data_tanggungan_crud.php
// FIXED: Mapping Column Names & Error Handling

session_start();
header('Content-Type: application/json');

// Error reporting untuk debugging (cek log server jika masih 500)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Pastikan path ini benar sesuai struktur folder Anda
require_once '../vendor/autoload.php'; 
require_once '../config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']); exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    // ============================================================
    // 1. LIST DATA (Tabel Utama)
    // ============================================================
    if ($action === 'list') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $f_afdeling = $_POST['f_afdeling'] ?? '';

        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit;

        // Query Utama
        $sqlBase = "FROM data_keluarga dk 
                    JOIN data_karyawan k ON dk.karyawan_id = k.id 
                    LEFT JOIN md_kebun mk ON k.kebun_id = mk.id 
                    WHERE 1=1";
        
        $params = [];

        if ($q) {
            $sqlBase .= " AND (k.nama_lengkap LIKE :q OR dk.nama_batih LIKE :q OR k.id_sap LIKE :q)";
            $params[':q'] = "%$q%";
        }

        if ($f_afdeling) {
            $sqlBase .= " AND k.afdeling = :afd";
            $params[':afd'] = $f_afdeling;
        }

        // Hitung Total
        $stmtCount = $conn->prepare("SELECT COUNT(*) as total $sqlBase");
        $stmtCount->execute($params);
        $totalRows = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        // Ambil Data
        $sql = "SELECT dk.*, k.nama_lengkap, k.id_sap, k.afdeling, mk.nama_kebun 
                $sqlBase 
                ORDER BY k.nama_lengkap ASC, dk.nama_batih ASC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // MAPPING DATA (FIX UNDEFINED)
        $data = array_map(function($row) {
            return [
                'id' => $row['id'],
                'karyawan_id' => $row['karyawan_id'],
                'sap_id' => $row['id_sap'],             // Map id_sap -> sap_id
                'nama_karyawan' => $row['nama_lengkap'], // Map nama_lengkap -> nama_karyawan
                'nama_batih' => $row['nama_batih'],
                'hubungan' => $row['hubungan'],
                'nama_kebun' => $row['nama_kebun'],
                'afdeling' => $row['afdeling'],
                'tempat_lahir' => $row['tempat_lahir'],
                'tanggal_lahir' => $row['tanggal_lahir'],
                'pendidikan_terakhir' => $row['pendidikan_terakhir'],
                'pekerjaan' => $row['pekerjaan'],
                'keterangan' => $row['keterangan']
            ];
        }, $raw);

        echo json_encode([
            'success' => true, 
            'data' => $data,
            'total' => $totalRows,
            'page' => $page,
            'limit' => $limit
        ]);
        exit;
    }

    // ============================================================
    // 2. LIST KARYAWAN SIMPLE (Untuk Dropdown Modal)
    // ============================================================
    if ($action === 'list_karyawan_simple') {
        $stmt = $conn->query("SELECT id, id_sap, nama_lengkap FROM data_karyawan ORDER BY nama_lengkap ASC");
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // MAPPING AGAR TIDAK UNDEFINED DI JS
        $data = array_map(function($row) {
            return [
                'id' => $row['id'],
                'sap_id' => $row['id_sap'],             // Penting: id_sap jadi sap_id
                'nama_karyawan' => $row['nama_lengkap'] // Penting: nama_lengkap jadi nama_karyawan
            ];
        }, $raw);

        echo json_encode(['success'=>true, 'data'=>$data]); exit;
    }

    // ============================================================
    // 3. HELPER: LIST AFDELING
    // ============================================================
    if ($action === 'list_afdeling') {
        $stmt = $conn->query("SELECT DISTINCT afdeling FROM data_karyawan WHERE afdeling IS NOT NULL AND afdeling != '' ORDER BY afdeling ASC");
        echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_COLUMN)]); exit;
    }

    // ============================================================
    // 4. CRUD (STORE / UPDATE)
    // ============================================================
    if ($action === 'store' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $fields = ['karyawan_id', 'nama_batih', 'hubungan', 'tempat_lahir', 'tanggal_lahir', 'pendidikan_terakhir', 'pekerjaan', 'keterangan'];
        
        $params = [];
        foreach($fields as $f) $params[":$f"] = $_POST[$f] ?? null;

        if ($action === 'store') {
            $cols = implode(',', $fields) . ',created_at';
            $vals = implode(',', array_keys($params)) . ',NOW()';
            $sql = "INSERT INTO data_keluarga ($cols) VALUES ($vals)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        } else {
            $set = []; 
            foreach($fields as $f) $set[] = "$f = :$f";
            $sql = "UPDATE data_keluarga SET " . implode(',', $set) . " WHERE id = :id";
            $params[':id'] = $id;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        echo json_encode(['success'=>true]); exit;
    }

    // ============================================================
    // 5. DELETE
    // ============================================================
    if ($action === 'delete') {
        $id = $_POST['id'];
        $conn->prepare("DELETE FROM data_keluarga WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

    // ============================================================
    // 6. IMPORT EXCEL
    // ============================================================
    if ($action === 'import') {
        if (empty($_FILES['file_excel']['name'])) {
            echo json_encode(['success'=>false, 'message'=>'File tidak ditemukan']); exit;
        }
        $fileTmp = $_FILES['file_excel']['tmp_name'];
        
        $spreadsheet = IOFactory::load($fileTmp);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $inserted = 0; $skipped = 0; $rowIdx = 0;

        $stmtCek = $conn->prepare("SELECT id FROM data_karyawan WHERE id_sap = ?");
        $stmtInsert = $conn->prepare("INSERT INTO data_keluarga (karyawan_id, nama_batih, hubungan, tempat_lahir, tanggal_lahir, pendidikan_terakhir, pekerjaan, keterangan, created_at) VALUES (?,?,?,?,?,?,?,?, NOW())");

        foreach ($rows as $row) {
            $rowIdx++;
            if ($rowIdx <= 1 || empty(trim($row['A']))) continue;

            $sap_id = trim($row['A']);
            $stmtCek->execute([$sap_id]);
            $karyawan = $stmtCek->fetch(PDO::FETCH_ASSOC);

            if ($karyawan) {
                $fmtDate = function($val) {
                    if (empty($val)) return null;
                    if (is_numeric($val)) return Date::excelToDateTimeObject($val)->format('Y-m-d');
                    return date('Y-m-d', strtotime(str_replace('/','-', $val)));
                };

                $vals = [
                    $karyawan['id'],
                    trim($row['B']), trim($row['C']), trim($row['D']),
                    $fmtDate($row['E']),
                    trim($row['F']), trim($row['G']), trim($row['H'])
                ];
                $stmtInsert->execute($vals);
                $inserted++;
            } else {
                $skipped++;
            }
        }
        echo json_encode(['success'=>true, 'message'=>"Selesai: $inserted masuk, $skipped dilewati (SAP tidak ada)."]);
        exit;
    }

} catch (Exception $e) {
    // Tangkap error agar tidak jadi 500 Page
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>