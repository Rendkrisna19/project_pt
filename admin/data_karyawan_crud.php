<?php
// pages/data_karyawan_crud.php
// FULL MODIFIED: Filter, Pension Logic, New Columns, Document Upload

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

$inputActions = ['store', 'import_excel_lib'];
$adminActions = ['update', 'delete'];

if (in_array($action, $inputActions) && ($role !== 'admin' && $role !== 'staf')) {
    echo json_encode(['success'=>false, 'message'=>'No permission']); exit;
}

try {
    // ============================================================
    // 1. LIST DATA (Filter & Pension Logic)
    // ============================================================
    if ($action === 'list') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $viewType = $_POST['view_type'] ?? 'active'; // 'active' or 'pension'
        
        // Filter Inputs
        $f_afdeling = $_POST['f_afdeling'] ?? '';
        $f_kebun    = $_POST['f_kebun'] ?? '';

        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        $offset = ($page - 1) * $limit;

        // Base Query
        // Join ke md_kebun untuk mengambil nama kebun
        $sqlBase = "FROM data_karyawan k 
                    LEFT JOIN md_kebun mk ON k.kebun_id = mk.id 
                    WHERE 1=1";
        
        $params = [];

        // Logic Pensiun Otomatis
        if ($viewType === 'pension') {
            // Tampilkan yang SUDAH lewat tanggal pensiun
            $sqlBase .= " AND k.tmt_pensiun <= CURDATE()";
        } else {
            // Tampilkan yang BELUM pensiun (Aktif)
            $sqlBase .= " AND (k.tmt_pensiun > CURDATE() OR k.tmt_pensiun IS NULL)";
        }

        // Search
        if ($q) {
            $sqlBase .= " AND (k.nama_lengkap LIKE :q OR k.id_sap LIKE :q OR k.jabatan_real LIKE :q OR k.nik_ktp LIKE :q)";
            $params[':q'] = "%$q%";
        }

        // Filters
        if ($f_afdeling) {
            $sqlBase .= " AND k.afdeling = :afd";
            $params[':afd'] = $f_afdeling;
        }
        if ($f_kebun) {
            $sqlBase .= " AND k.kebun_id = :kebun";
            $params[':kebun'] = $f_kebun;
        }

        // Count Total
        $stmtCount = $conn->prepare("SELECT COUNT(*) as total $sqlBase");
        $stmtCount->execute($params);
        $totalRows = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        // Get Data
        $sql = "SELECT k.*, mk.nama_kebun 
                $sqlBase 
                ORDER BY k.nama_lengkap ASC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map Data for Frontend
        $mappedData = array_map(function($row) {
            $row['sap_id']          = $row['id_sap'];
            $row['nama_karyawan']   = $row['nama_lengkap'];
            $row['foto_karyawan']   = $row['foto_profil'];
            // Status Tax Logic (jika kosong ambil NPWP lama atau dash)
            $row['status_pajak']    = $row['status_pajak'] ?: '-'; 
            return $row;
        }, $data);

        echo json_encode([
            'success' => true, 
            'data' => $mappedData,
            'total' => $totalRows,
            'page' => $page,
            'limit' => $limit
        ]);
        exit;
    }

    // ============================================================
    // 2. HELPER LIST (Kebun & Afdeling)
    // ============================================================
    if ($action === 'list_options') {
        // List Kebun
        $stmtKebun = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC");
        $kebun = $stmtKebun->fetchAll(PDO::FETCH_ASSOC);

        // List Afdeling (Distinct from existing data)
        $stmtAfd = $conn->query("SELECT DISTINCT afdeling FROM data_karyawan WHERE afdeling IS NOT NULL AND afdeling != '' ORDER BY afdeling ASC");
        $afdeling = $stmtAfd->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode(['success'=>true, 'kebun'=>$kebun, 'afdeling'=>$afdeling]);
        exit;
    }

    // ============================================================
    // 3. STORE / UPDATE (With New Columns & File Upload)
    // ============================================================
    if ($action === 'store' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        
        // Field Mapping (Form Name => DB Column)
        $fieldMap = [
            'sap_id'            => 'id_sap',
            'old_pers_no'       => 'old_pers_no',
            'nama_karyawan'     => 'nama_lengkap',
            'nik_ktp'           => 'nik_ktp',
            'gender'            => 'gender',
            'tempat_lahir'      => 'tempat_lahir',
            'tgl_lahir'         => 'tanggal_lahir',
            'person_grade'      => 'person_grade',
            'phdp_golongan'     => 'phdp_golongan',
            'status_keluarga'   => 's_kel',
            'jabatan_sap'       => 'jabatan_sap',
            'jabatan_real'      => 'jabatan_real',
            'afdeling'          => 'afdeling',
            'kebun_id'          => 'kebun_id',       // New
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
            'npwp'              => 'npwp',           // Keep for number
            'status_pajak'      => 'status_pajak',   // New UI Label
            'pendidikan_terakhir'=> 'pendidikan_terakhir', // New
            'jurusan'           => 'jurusan',        // New
            'institusi'         => 'institusi'       // New
        ];

        $params = [];
        $dbCols = [];
        $dbVals = [];
        $updateSets = [];

        foreach ($fieldMap as $formField => $dbCol) {
            $val = isset($_POST[$formField]) && $_POST[$formField] !== '' ? $_POST[$formField] : null;
            if ($formField === 'gender' && !empty($val)) {
                $val = strtoupper(substr($val, 0, 1));
                if ($val !== 'L' && $val !== 'P') $val = null;
            }
            $params[":$dbCol"] = $val;
            $dbCols[] = $dbCol;
            $dbVals[] = ":$dbCol";
            $updateSets[] = "$dbCol = :$dbCol";
        }

        // HANDLE FOTO PROFIL
        $foto_name = null;
        if (!empty($_FILES['foto_karyawan']['name'])) { 
            $dir = "../uploads/profil/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['foto_karyawan']['name'], PATHINFO_EXTENSION);
            $foto_name = "KARYAWAN_" . time() . ".$ext";
            move_uploaded_file($_FILES['foto_karyawan']['tmp_name'], $dir . $foto_name);
            $params[':foto'] = $foto_name;
        }

        // HANDLE DOKUMEN UPLOAD
        $doc_name = null;
        if (!empty($_FILES['dokumen_file']['name'])) { 
            $dirDoc = "../uploads/dokumen/";
            if (!is_dir($dirDoc)) mkdir($dirDoc, 0777, true);
            $extDoc = pathinfo($_FILES['dokumen_file']['name'], PATHINFO_EXTENSION);
            $doc_name = "DOC_" . $_POST['sap_id'] . "_" . time() . ".$extDoc";
            move_uploaded_file($_FILES['dokumen_file']['tmp_name'], $dirDoc . $doc_name);
            $params[':doc'] = $doc_name;
        }

        if ($action === 'store') {
            $colsStr = implode(',', $dbCols);
            $valsStr = implode(',', $dbVals);
            
            if ($foto_name) { $colsStr .= ",foto_profil"; $valsStr .= ",:foto"; }
            if ($doc_name)  { $colsStr .= ",dokumen_path"; $valsStr .= ",:doc"; }

            $colsStr .= ",created_at,updated_at";
            $valsStr .= ",NOW(),NOW()";

            $sql = "INSERT INTO data_karyawan ($colsStr) VALUES ($valsStr)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

        } else {
            if ($foto_name) $updateSets[] = "foto_profil = :foto";
            if ($doc_name)  $updateSets[] = "dokumen_path = :doc";
            $updateSets[] = "updated_at = NOW()";
            
            $sql = "UPDATE data_karyawan SET " . implode(',', $updateSets) . " WHERE id = :id";
            $params[':id'] = $id;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        
        echo json_encode(['success'=>true]); exit;
    }

    // ============================================================
    // 4. IMPORT EXCEL (Modified for new columns except File)
    // ============================================================
    if ($action === 'import_excel_lib') {
        // (Kode Import sama seperti sebelumnya, tambahkan mapping kolom baru jika perlu)
        // Untuk mempersingkat, saya fokuskan pada kolom baru yang diminta.
        // Anda bisa menambahkan 'kebun_id' (lookup by name logic needed) atau text fields.
        // Di sini saya update Text Fields baru saja.
        
        if (empty($_FILES['file_excel']['name'])) {
            echo json_encode(['success'=>false, 'message'=>'File tidak ditemukan']); exit;
        }
        $fileTmp = $_FILES['file_excel']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($fileTmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            $inserted = 0; $rowIdx = 0;

            $stmtInsert = $conn->prepare("INSERT INTO data_karyawan 
                (id_sap, old_pers_no, nama_lengkap, nik_ktp, gender, tempat_lahir, tanggal_lahir, 
                 person_grade, phdp_golongan, s_kel, jabatan_sap, jabatan_real, 
                 afdeling, status_karyawan, tmt_kerja, tmt_mbt, tmt_pensiun, tax_id, bpjs_id, 
                 jamsostek_id, nama_bank, no_rekening, nama_pemilik_rekening, no_hp, agama, 
                 status_pajak, pendidikan_terakhir, jurusan, institusi,
                 created_at, updated_at) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
                ON DUPLICATE KEY UPDATE nama_lengkap=VALUES(nama_lengkap), updated_at=NOW()");

            foreach ($rows as $row) {
                $rowIdx++;
                if ($rowIdx <= 1 || empty(trim($row['A']))) continue;

                $fmtDate = function($val) {
                    if (empty($val)) return null;
                    if (is_numeric($val)) return Date::excelToDateTimeObject($val)->format('Y-m-d');
                    return date('Y-m-d', strtotime(str_replace('/','-', $val)));
                };
                
                $gender = strtoupper(substr(trim($row['E']), 0, 1));
                if($gender !== 'L' && $gender !== 'P') $gender = '';

                // Mapping Excel Columns A-AC (Sesuai Template Baru)
                $vals = [
                    trim($row['A']), trim($row['B']), trim($row['C']), trim($row['D']), $gender,
                    trim($row['F']), $fmtDate($row['G']), trim($row['H']), trim($row['I']), trim($row['J']),
                    trim($row['K']), trim($row['L']), trim($row['M']), trim($row['N']), $fmtDate($row['O']),
                    $fmtDate($row['P']), $fmtDate($row['Q']), trim($row['R']), trim($row['S']), trim($row['T']),
                    trim($row['U']), trim($row['V']), trim($row['W']), trim($row['X']), trim($row['Y']),
                    trim($row['Z']),  // Status Pajak
                    trim($row['AA']), // Pendidikan
                    trim($row['AB']), // Jurusan
                    trim($row['AC'])  // Institusi
                ];
                $stmtInsert->execute($vals);
                $inserted++;
            }
            echo json_encode(['success'=>true, 'message'=>"Import $inserted data selesai."]);
        } catch (Exception $e) {
            echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
        }
        exit;
    }
    
    // DELETE (Sama seperti sebelumnya)
    if ($action === 'delete') {
        $id = $_POST['id'];
        $conn->prepare("DELETE FROM data_karyawan WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]); exit;
    }

} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>