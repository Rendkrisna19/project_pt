<?php
// pages/data_karyawan_crud.php
// FIXED VERSION: Mapping Frontend Names <-> Database Columns & Import Fixes

session_start();
header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

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
$inputActions = ['store', 'import_excel_lib', 'store_tanggungan', 'store_peringatan'];
$adminActions = ['update', 'delete', 'update_tanggungan', 'delete_tanggungan', 'update_peringatan', 'delete_peringatan'];

if (in_array($action, $inputActions) && ($role !== 'admin' && $role !== 'staf')) {
    echo json_encode(['success'=>false, 'message'=>'No permission']); exit;
}
if (in_array($action, $adminActions) && $role !== 'admin') {
    echo json_encode(['success'=>false, 'message'=>'Admin only']); exit;
}

try {
    // ============================================================
    // DATA KARYAWAN
    // ============================================================
    
    if ($action === 'list') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $sql = "SELECT * FROM data_karyawan WHERE 1=1";
        $params = [];
        if ($q) {
            // Menggunakan nama kolom DATABASE (id_sap, nama_lengkap)
            $sql .= " AND (nama_lengkap LIKE :q OR id_sap LIKE :q OR jabatan_real LIKE :q OR nik_ktp LIKE :q)";
            $params[':q'] = "%$q%";
        }
        $sql .= " ORDER BY nama_lengkap ASC LIMIT 200";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        // Mapping Data untuk Frontend (DB Col -> Frontend Name)
        // Agar JS editData() bisa membaca field dengan benar
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mappedData = array_map(function($row) {
            // Kita tambahkan alias agar frontend JS yang pakai 'sap_id' atau 'nama_karyawan' tetap jalan
            $row['sap_id'] = $row['id_sap'];
            $row['nama_karyawan'] = $row['nama_lengkap'];
            $row['tgl_lahir'] = $row['tanggal_lahir'];
            $row['status_keluarga'] = $row['s_kel'];
            return $row;
        }, $data);

        echo json_encode(['success'=>true, 'data'=>$mappedData]);
        exit;
    }

    // IMPORT EXCEL FIX
    if ($action === 'import_excel_lib') {
        if (empty($_FILES['file_excel']['name'])) {
            echo json_encode(['success'=>false, 'message'=>'File tidak ditemukan']); exit;
        }

        $fileTmp = $_FILES['file_excel']['tmp_name'];

        try {
            $spreadsheet = IOFactory::load($fileTmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $inserted = 0;
            $rowIdx = 0;

            // INSERT QUERY (Menggunakan nama kolom DATABASE)
            $stmtInsert = $conn->prepare("INSERT INTO data_karyawan 
                (id_sap, old_pers_no, nama_lengkap, nik_ktp, gender, tempat_lahir, tanggal_lahir, 
                 person_grade, phdp_golongan, s_kel, jabatan_sap, jabatan_real, 
                 afdeling, status_karyawan, tmt_kerja, tmt_mbt, tmt_pensiun, tax_id, bpjs_id, 
                 jamsostek_id, nama_bank, no_rekening, nama_pemilik_rekening, no_hp, agama, npwp, 
                 created_at, updated_at) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
                ON DUPLICATE KEY UPDATE 
                nama_lengkap=VALUES(nama_lengkap), 
                jabatan_real=VALUES(jabatan_real), 
                status_karyawan=VALUES(status_karyawan), 
                updated_at=NOW()");

            foreach ($rows as $row) {
                $rowIdx++;
                if ($rowIdx == 1 || empty(trim($row['B']))) continue; 

                $fmtDate = function($val) {
                    if (empty($val)) return null;
                    if (is_numeric($val)) return Date::excelToDateTimeObject($val)->format('Y-m-d');
                    return date('Y-m-d', strtotime(str_replace('/','-', $val)));
                };

                // FIX GENDER: Ambil huruf pertama saja & uppercase (mengatasi 'Data truncated')
                $rawGender = trim($row['F']);
                $genderFix = !empty($rawGender) ? strtoupper(substr($rawGender, 0, 1)) : null;

                $vals = [
                    trim($row['B']),  // id_sap
                    trim($row['C']),  // old_pers_no
                    trim($row['D']),  // nama_lengkap
                    trim($row['E']),  // nik_ktp
                    $genderFix,       // gender (FIXED)
                    trim($row['G']),  // tempat_lahir
                    $fmtDate($row['H']), // tanggal_lahir
                    trim($row['I']),  // person_grade
                    trim($row['J']),  // phdp_golongan
                    trim($row['K']),  // s_kel
                    trim($row['L']),  // jabatan_sap
                    trim($row['M']),  // jabatan_real
                    trim($row['N']),  // afdeling
                    trim($row['O']),  // status_karyawan
                    $fmtDate($row['P']), // tmt_kerja
                    $fmtDate($row['Q']), // tmt_mbt
                    $fmtDate($row['R']), // tmt_pensiun
                    trim($row['S']),  // tax_id
                    trim($row['T']),  // bpjs_id
                    trim($row['U']),  // jamsostek_id
                    trim($row['V']),  // nama_bank
                    trim($row['W']),  // no_rekening
                    trim($row['X']),  // nama_pemilik_rekening
                    trim($row['Y']),  // no_hp
                    trim($row['Z']),  // agama
                    trim($row['AA']), // npwp
                ];

                $stmtInsert->execute($vals);
                $inserted++;
            }
            echo json_encode(['success'=>true, 'message'=>"Import Selesai. $inserted data diproses."]);
        } catch (Exception $e) {
            echo json_encode(['success'=>false, 'message'=>'Import Gagal: ' . $e->getMessage()]);
        }
        exit;
    }

    // STORE / UPDATE KARYAWAN (FIXED MAPPING)
    if ($action === 'store' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        
        // 1. Definisikan Mapping: 'name_di_form_html' => 'nama_kolom_database'
        $map = [
            'sap_id'            => 'id_sap',           // FIX: sap_id form -> id_sap db
            'old_pers_no'       => 'old_pers_no',
            'no_urut'           => 'no_urut',
            'nama_karyawan'     => 'nama_lengkap',     // FIX: nama_karyawan form -> nama_lengkap db
            'nik_ktp'           => 'nik_ktp',
            'gender'            => 'gender',
            'tgl_lahir'         => 'tanggal_lahir',    // FIX: tgl_lahir form -> tanggal_lahir db
            'person_grade'      => 'person_grade',
            'phdp_golongan'     => 'phdp_golongan',
            'status_keluarga'   => 's_kel',            // FIX: status_keluarga form -> s_kel db
            'jabatan_sap'       => 'jabatan_sap',
            'jabatan_real'      => 'jabatan_real',
            'afdeling'          => 'afdeling',
            'status_karyawan'   => 'status_karyawan',
            'tmt_kerja'         => 'tmt_kerja',
            'tmt_mbt'           => 'tmt_mbt',
            'tmt_pensiun'       => 'tmt_pensiun',
            'tax_id'            => 'tax_id',
            'bpjs_id'           => 'bpjs_id',
            'jamsostek_id'      => 'jamsostek_id',
            'nama_bank'         => 'nama_bank',
            'no_rekening'       => 'no_rekening',
            'nama_pemilik_rekening' => 'nama_pemilik_rekening',
            'no_hp'             => 'no_hp',
            'agama'             => 'agama',
            'npwp'              => 'npwp'
        ];

        // 2. Siapkan parameter query
        $params = [];
        $insertCols = [];
        $insertVals = [];
        $updateSets = [];

        foreach ($map as $formName => $dbCol) {
            // Ambil value dari POST, jika string kosong ubah jadi NULL
            $val = isset($_POST[$formName]) && $_POST[$formName] !== '' ? $_POST[$formName] : null;
            
            $params[":$dbCol"] = $val;
            
            $insertCols[] = $dbCol;
            $insertVals[] = ":$dbCol";
            $updateSets[] = "$dbCol = :$dbCol";
        }

        // 3. Handle Foto
        $foto_name = null;
        if (!empty($_FILES['foto_karyawan']['name'])) { 
            $dir = "../uploads/profil/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['foto_karyawan']['name'], PATHINFO_EXTENSION);
            $foto_name = "KARYAWAN_" . time() . ".$ext";
            move_uploaded_file($_FILES['foto_karyawan']['tmp_name'], $dir . $foto_name);
            
            $params[':foto'] = $foto_name;
        }

        if ($action === 'store') {
            $colsStr = implode(',', $insertCols);
            $valsStr = implode(',', $insertVals);
            
            if ($foto_name) {
                $colsStr .= ",foto_profil"; // Pastikan kolom DB 'foto_profil'
                $valsStr .= ",:foto";
            }
            $colsStr .= ",created_at,updated_at";
            $valsStr .= ",NOW(),NOW()";

            $sql = "INSERT INTO data_karyawan ($colsStr) VALUES ($valsStr)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

        } else { // Update
            if ($foto_name) {
                $updateSets[] = "foto_profil = :foto";
            }
            $updateSets[] = "updated_at = NOW()";
            
            $sql = "UPDATE data_karyawan SET " . implode(',', $updateSets) . " WHERE id = :id";
            $params[':id'] = $id;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        
        echo json_encode(['success'=>true]); exit;
    }

    // DELETE KARYAWAN
    if ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT foto_profil FROM data_karyawan WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists("../uploads/profil/$old")) unlink("../uploads/profil/$old");

        $conn->prepare("DELETE FROM data_karyawan WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

    // ============================================================
    // DATA TANGGUNGAN
    // ============================================================
    if ($action === 'list_tanggungan') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $sql = "SELECT dt.*, dk.id_sap, dk.nama_lengkap 
                FROM data_keluarga dt
                LEFT JOIN data_karyawan dk ON dt.karyawan_id = dk.id
                WHERE 1=1";
        $params = [];
        if ($q) {
            $sql .= " AND (dk.nama_lengkap LIKE :q OR dt.nama_anggota LIKE :q)";
            $params[':q'] = "%$q%";
        }
        $sql .= " ORDER BY dk.nama_lengkap ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'store_tanggungan' || $action === 'update_tanggungan') {
        $id = $_POST['id'] ?? null;
        $fields = ['karyawan_id', 'nama_anggota', 'hubungan', 'tempat_lahir', 'tanggal_lahir', 'pendidikan', 'pekerjaan', 'keterangan'];
        $params = [];
        foreach($fields as $f) $params[":$f"] = $_POST[$f] ?? null;

        if ($action === 'store_tanggungan') {
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

    if ($action === 'delete_tanggungan') {
        $id = $_POST['id'];
        $conn->prepare("DELETE FROM data_keluarga WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

    // ============================================================
    // DATA SURAT PERINGATAN
    // ============================================================
    if ($action === 'list_peringatan') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $sql = "SELECT dp.*, dk.id_sap, dk.nama_lengkap 
                FROM data_peringatan dp
                LEFT JOIN data_karyawan dk ON dp.karyawan_id = dk.id
                WHERE 1=1";
        $params = [];
        if ($q) {
            $sql .= " AND (dk.nama_lengkap LIKE :q OR dp.no_surat LIKE :q OR dp.jenis_sp LIKE :q)";
            $params[':q'] = "%$q%";
        }
        $sql .= " ORDER BY dp.tanggal_sp DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'store_peringatan' || $action === 'update_peringatan') {
        $id = $_POST['id'] ?? null;
        $file_name = null;
        if (!empty($_FILES['file_scan']['name'])) {
            $dir = "../uploads/sp/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['file_scan']['name'], PATHINFO_EXTENSION);
            $file_name = "SP_" . time() . ".$ext";
            move_uploaded_file($_FILES['file_scan']['tmp_name'], $dir . $file_name);
        }

        $fields = ['karyawan_id', 'no_surat', 'jenis_sp', 'tanggal_sp', 'masa_berlaku', 'pelanggaran', 'sanksi'];
        $params = [];
        foreach($fields as $f) $params[":$f"] = $_POST[$f] ?? null;

        if ($action === 'store_peringatan') {
            $cols = implode(',', $fields);
            $vals = implode(',', array_keys($params));
            if ($file_name) { 
                $cols .= ",file_scan"; $vals .= ",:file"; $params[':file'] = $file_name; 
            }
            $cols .= ",created_at"; $vals .= ",NOW()";
            $sql = "INSERT INTO data_peringatan ($cols) VALUES ($vals)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        } else {
            $set = [];
            foreach($fields as $f) $set[] = "$f = :$f";
            if ($file_name) { 
                $set[] = "file_scan = :file"; $params[':file'] = $file_name; 
            }
            $sql = "UPDATE data_peringatan SET " . implode(',', $set) . " WHERE id = :id";
            $params[':id'] = $id;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        echo json_encode(['success'=>true]); exit;
    }

    if ($action === 'delete_peringatan') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT file_scan FROM data_peringatan WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists("../uploads/sp/$old")) unlink("../uploads/sp/$old");
        $conn->prepare("DELETE FROM data_peringatan WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>