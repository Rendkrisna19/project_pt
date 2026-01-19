<?php
// pages/data_karyawan_crud.php
// MODIFIED: Use PhpSpreadsheet for all Excel Import

session_start();
header('Content-Type: application/json');

// Matikan error display agar JSON valid (Error log ke file)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Sertakan Autoload Composer (Wajib)
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
$role = $_SESSION['user_role'] ?? 'viewer';

// Permission Gate
if (in_array($action, ['store', 'import_excel_lib']) && ($role !== 'admin' && $role !== 'staf')) {
    echo json_encode(['success'=>false, 'message'=>'No permission']); exit;
}
if (($action === 'update' || $action === 'delete') && $role !== 'admin') {
    echo json_encode(['success'=>false, 'message'=>'Admin only']); exit;
}

try {
    // --- LIST ---
    if ($action === 'list') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $sql = "SELECT * FROM data_karyawan WHERE 1=1";
        $params = [];
        if ($q) {
            $sql .= " AND (nama_lengkap LIKE :q OR id_sap LIKE :q OR jabatan_real LIKE :q)";
            $params[':q'] = "%$q%";
        }
        $sql .= " ORDER BY nama_lengkap ASC LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- IMPORT EXCEL (PHPSPREADSHEET) ---
    if ($action === 'import_excel_lib') {
        if (empty($_FILES['file_excel']['name'])) {
            echo json_encode(['success'=>false, 'message'=>'File tidak ditemukan']); exit;
        }

        $fileTmp = $_FILES['file_excel']['tmp_name'];

        try {
            // Auto detect file type (Xls, Xlsx, Csv)
            $spreadsheet = IOFactory::load($fileTmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true); // Return A, B, C... indexed array

            $inserted = 0;
            $rowIdx = 0;

            // Prepare Statement
            $stmtInsert = $conn->prepare("INSERT INTO data_karyawan 
                (id_sap, old_pers_no, nama_lengkap, nik_ktp, gender, tempat_lahir, tanggal_lahir, 
                 jabatan_real, afdeling, status_karyawan, tmt_kerja, tmt_pensiun, no_hp, agama, 
                 nama_bank, no_rekening, npwp, created_at, updated_at) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
                ON DUPLICATE KEY UPDATE 
                nama_lengkap=VALUES(nama_lengkap), jabatan_real=VALUES(jabatan_real), 
                status_karyawan=VALUES(status_karyawan), updated_at=NOW()");

            foreach ($rows as $row) {
                $rowIdx++;
                // Skip Header (Baris 1) & Check kolom A (ID SAP)
                if ($rowIdx == 1 || empty(trim($row['A']))) continue;

                // Format Tanggal Excel ke YYYY-MM-DD
                // Helper function local
                $fmtDate = function($val) {
                    if (empty($val)) return null;
                    // Jika format Excel Date (numeric), convert
                    if (is_numeric($val)) {
                         return Date::excelToDateTimeObject($val)->format('Y-m-d');
                    }
                    // Jika string biasa Y-m-d
                    return date('Y-m-d', strtotime(str_replace('/','-', $val)));
                };

                $vals = [
                    trim($row['A']), // ID SAP
                    trim($row['B']), // Old Pers No
                    trim($row['C']), // Nama
                    trim($row['D']), // NIK
                    trim($row['E']), // Gender
                    trim($row['F']), // Tmpt Lahir
                    $fmtDate($row['G']), // Tgl Lahir
                    trim($row['H']), // Jabatan
                    trim($row['I']), // Afdeling
                    trim($row['J']), // Status
                    $fmtDate($row['K']), // TMT Kerja
                    $fmtDate($row['L']), // TMT Pensiun
                    trim($row['M']), // No HP
                    trim($row['N']), // Agama
                    trim($row['O']), // Bank
                    trim($row['P']), // No Rek
                    trim($row['Q']), // NPWP
                ];

                $stmtInsert->execute($vals);
                $inserted++;
            }

            echo json_encode(['success'=>true, 'message'=>"Import Selesai. $inserted data berhasil diproses."]);

        } catch (Exception $e) {
            echo json_encode(['success'=>false, 'message'=>'Gagal membaca file: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- STORE / UPDATE (MANUAL) ---
    if ($action === 'store' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        
        // Handle Upload Foto
        $foto_name = null;
        if (!empty($_FILES['foto_profil']['name'])) {
            $dir = "../uploads/profil/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION);
            $foto_name = "KARYAWAN_" . time() . ".$ext";
            move_uploaded_file($_FILES['foto_profil']['tmp_name'], $dir . $foto_name);
        }

        $fields = [
            'id_sap', 'old_pers_no', 'nama_lengkap', 'nik_ktp', 'jabatan_real', 'afdeling',
            'status_karyawan', 'tmt_kerja', 'tmt_pensiun', 'no_hp', 'nama_bank', 'no_rekening',
            'tempat_lahir', 'tanggal_lahir', 'person_grade'
        ];
        
        $params = [];
        foreach($fields as $f) $params[":$f"] = $_POST[$f] ?? null;

        if ($action === 'store') {
            $cols = implode(',', $fields);
            $vals = implode(',', array_keys($params));
            if ($foto_name) { $cols .= ",foto_profil"; $vals .= ",:foto"; $params[':foto'] = $foto_name; }
            
            $sql = "INSERT INTO data_karyawan ($cols) VALUES ($vals)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        } else {
            $set = [];
            foreach($fields as $f) $set[] = "$f = :$f";
            if ($foto_name) { $set[] = "foto_profil = :foto"; $params[':foto'] = $foto_name; }
            
            $sql = "UPDATE data_karyawan SET " . implode(',', $set) . " WHERE id = :id";
            $params[':id'] = $id;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        echo json_encode(['success'=>true]); exit;
    }

    // --- DELETE ---
    if ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT foto_profil FROM data_karyawan WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists("../uploads/profil/$old")) unlink("../uploads/profil/$old");

        $conn->prepare("DELETE FROM data_karyawan WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>