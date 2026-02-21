<?php
// pages/data_tanggungan_crud.php
session_start();
header('Content-Type: application/json');

// Error Reporting (Matikan saat production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Load Database & Library
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
    if ($action === 'list_tanggungan') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1; // Jika nanti pakai server-side paging
        $f_afdeling = $_POST['f_afdeling'] ?? '';

        // Query Base: Join Karyawan -> Join Kebun
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

        // Ambil Data
        // Penting: Kita ambil 'mk.nama_kebun' agar kolom kebun terisi
        $sql = "SELECT dk.*, 
                       k.nama_lengkap, 
                       k.id_sap, 
                       k.afdeling, 
                       k.kebun_id,
                       mk.nama_kebun 
                $sqlBase 
                ORDER BY k.nama_lengkap ASC, dk.nama_batih ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Output JSON
        echo json_encode([
            'success' => true, 
            'data' => $raw,
            'total' => count($raw)
        ]);
        exit;
    }

    // ============================================================
    // 2. LIST KARYAWAN SIMPLE (Untuk Dropdown Select2)
    // ============================================================
    if ($action === 'list_karyawan_simple') {
        $stmt = $conn->query("SELECT id, id_sap, nama_lengkap FROM data_karyawan ORDER BY nama_lengkap ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true, 'data'=>$data]); exit;
    }

    // ============================================================
    // 3. GET KARYAWAN DETAIL (Untuk Auto-Fill Kebun/Afdeling di Modal)
    // ============================================================
    if ($action === 'get_karyawan_detail') {
        $id = $_POST['id'] ?? 0;
        
        $sql = "SELECT k.afdeling, k.kebun_id, mk.nama_kebun 
                FROM data_karyawan k 
                LEFT JOIN md_kebun mk ON k.kebun_id = mk.id 
                WHERE k.id = :id";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            echo json_encode(['success'=>true, 'data'=>$row]);
        } else {
            echo json_encode(['success'=>false, 'message'=>'Data tidak ditemukan']);
        }
        exit;
    }

    // ============================================================
    // 4. HELPER: LIST AFDELING (Untuk Filter)
    // ============================================================
    if ($action === 'list_options') {
        $stmt = $conn->query("SELECT DISTINCT afdeling FROM data_karyawan WHERE afdeling IS NOT NULL AND afdeling != '' ORDER BY afdeling ASC");
        echo json_encode(['success'=>true, 'afdeling'=>$stmt->fetchAll(PDO::FETCH_COLUMN)]); exit;
    }

    // ============================================================
    // 5. CRUD: STORE & UPDATE
    // ============================================================
    if ($action === 'store_tanggungan' || $action === 'update_tanggungan') {
        $id = $_POST['id'] ?? null;
        
        // Kolom yang boleh diinput
        $fields = ['karyawan_id', 'nama_batih', 'hubungan', 'tempat_lahir', 'tanggal_lahir', 'pendidikan_terakhir', 'pekerjaan', 'keterangan'];
        
        $params = [];
        foreach($fields as $f) {
            // Ubah string kosong jadi NULL
            $val = isset($_POST[$f]) && $_POST[$f] !== '' ? $_POST[$f] : null;
            $params[":$f"] = $val;
        }

        if ($action === 'store_tanggungan') {
            $cols = implode(',', $fields) . ',created_at';
            $vals = implode(',', array_keys($params)) . ',NOW()';
            
            $sql = "INSERT INTO data_keluarga ($cols) VALUES ($vals)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
        } else { // Update
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
    // 6. DELETE
    // ============================================================
    if ($action === 'delete_tanggungan') {
        $id = $_POST['id'];
        $conn->prepare("DELETE FROM data_keluarga WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

    // ============================================================
    // 7. IMPORT EXCEL
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

        // Siapkan Statement
        $stmtCek = $conn->prepare("SELECT id FROM data_karyawan WHERE id_sap = ? LIMIT 1");
        $stmtInsert = $conn->prepare("INSERT INTO data_keluarga 
            (karyawan_id, nama_batih, hubungan, tempat_lahir, tanggal_lahir, pendidikan_terakhir, pekerjaan, keterangan, created_at) 
            VALUES (?,?,?,?,?,?,?,?, NOW())");

        foreach ($rows as $row) {
            $rowIdx++;
            if ($rowIdx <= 1) continue; // Skip Header
            
            $sap_id = trim($row['A'] ?? '');
            if(empty($sap_id)) continue;

            // Cari ID Karyawan berdasarkan SAP
            $stmtCek->execute([$sap_id]);
            $karyawan = $stmtCek->fetch(PDO::FETCH_ASSOC);

            if ($karyawan) {
                // Helper Tanggal Excel
                $tgl = null;
                $rawTgl = $row['E'];
                if (!empty($rawTgl)) {
                    if (is_numeric($rawTgl)) {
                        $tgl = Date::excelToDateTimeObject($rawTgl)->format('Y-m-d');
                    } else {
                        // Coba format manual d-m-Y atau Y-m-d
                        $tgl = date('Y-m-d', strtotime(str_replace('/','-', $rawTgl)));
                    }
                }

                $vals = [
                    $karyawan['id'],
                    trim($row['B'] ?? ''), // Nama Keluarga
                    trim($row['C'] ?? ''), // Hubungan
                    trim($row['D'] ?? ''), // Tempat Lahir
                    $tgl,                  // Tanggal Lahir
                    trim($row['F'] ?? ''), // Pendidikan
                    trim($row['G'] ?? ''), // Pekerjaan
                    trim($row['H'] ?? '')  // Keterangan
                ];
                
                $stmtInsert->execute($vals);
                $inserted++;
            } else {
                $skipped++;
            }
        }
        echo json_encode(['success'=>true, 'message'=>"Import Selesai. Masuk: $inserted, Gagal/SAP Salah: $skipped"]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>