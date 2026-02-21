<?php
// pages/data_karyawan_crud.php
// VERSI FULL MODIFIKASI: KEBUN TEXT, STATUS KELUARGA, & IMPORT 30 KOLOM
// ✅ FIX: Error status_tax → status_pajak
// ✅ FIX: No Rekening format (prevent decimal dari Excel)

session_start();
header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../vendor/autoload.php';
require_once '../config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// 1. CEK AUTH
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';
$role = $_SESSION['user_role'] ?? 'viewer';

// 2. CEK PERMISSION
$inputActions = ['store', 'import_excel_lib', 'store_tanggungan', 'store_peringatan'];
$adminActions = ['update', 'delete', 'update_tanggungan', 'delete_tanggungan', 'update_peringatan', 'delete_peringatan'];

if (in_array($action, $inputActions) && ($role !== 'admin' && $role !== 'staf')) {
    echo json_encode(['success' => false, 'message' => 'Akses Ditolak']);
    exit;
}
if (in_array($action, $adminActions) && $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin only']);
    exit;
}

try {

    // ============================================================
    // A. MODUL KARYAWAN (LIST UTAMA)
    // ============================================================
    if ($action === 'list') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $viewType = $_POST['view_type'] ?? 'active';

        $f_afdeling = $_POST['f_afdeling'] ?? '';
        $f_kebun    = $_POST['f_kebun'] ?? '';

        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        $offset = ($page - 1) * $limit;

        // PERUBAHAN: Hapus JOIN md_kebun
        $sqlBase = "FROM data_karyawan k WHERE 1=1";
        $params = [];

        // Logic Pensiun
        if ($viewType === 'pension') {
            $sqlBase .= " AND k.tmt_pensiun <= CURDATE()";
        } else {
            $sqlBase .= " AND (k.tmt_pensiun > CURDATE() OR k.tmt_pensiun IS NULL)";
        }

        if ($q) {
            $sqlBase .= " AND (k.nama_lengkap LIKE :q OR k.id_sap LIKE :q OR k.kebun_id LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($f_afdeling) {
            $sqlBase .= " AND k.afdeling = :afd";
            $params[':afd'] = $f_afdeling;
        }
        if ($f_kebun) {
            // PERUBAHAN: Filter kebun sekarang teks
            $sqlBase .= " AND k.kebun_id = :kebun";
            $params[':kebun'] = $f_kebun;
        }

        $stmtCount = $conn->prepare("SELECT COUNT(*) as total $sqlBase");
        $stmtCount->execute($params);
        $totalRows = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        // PERUBAHAN: Select semua, tidak perlu ambil nama_kebun dari tabel lain
        $sql = "SELECT k.* $sqlBase ORDER BY k.nama_lengkap ASC LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mappedData = array_map(function ($row) {
            $row['sap_id']          = $row['id_sap'];
            $row['nama_karyawan']   = $row['nama_lengkap'];
            $row['foto_karyawan']   = $row['foto_profil'];
            // Mapping nama_kebun dari kebun_id (karena sekarang teks)
            $row['nama_kebun']      = $row['kebun_id'];
            return $row;
        }, $data);

        echo json_encode(['success' => true, 'data' => $mappedData, 'total' => $totalRows]);
        exit;
    }

    // B. LIST OPTIONS (DROPDOWN)
    if ($action === 'list_options') {
        // PERUBAHAN: Ambil DISTINCT dari data_karyawan, bukan master
        $stmtKebun = $conn->query("SELECT DISTINCT kebun_id FROM data_karyawan WHERE kebun_id IS NOT NULL AND kebun_id != '' ORDER BY kebun_id ASC");
        $kebun = $stmtKebun->fetchAll(PDO::FETCH_COLUMN);

        $stmtAfd = $conn->query("SELECT DISTINCT afdeling FROM data_karyawan WHERE afdeling IS NOT NULL AND afdeling != '' ORDER BY afdeling ASC");
        $afdeling = $stmtAfd->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode(['success' => true, 'kebun' => $kebun, 'afdeling' => $afdeling]);
        exit;
    }

    // C. LIST KARYAWAN SIMPLE
    if ($action === 'list_karyawan_simple') {
        $sql = "SELECT id, id_sap, nama_lengkap as nama_karyawan 
                FROM data_karyawan 
                WHERE (tmt_pensiun > CURDATE() OR tmt_pensiun IS NULL) 
                ORDER BY nama_lengkap ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // ============================================================
    // D. MODUL MBT (MONITORING MASA BERLAKU TUNJANGAN)
    // ============================================================
    if ($action === 'list_mbt') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');
        $f_afdeling = $_POST['f_afdeling'] ?? '';

        // PERUBAHAN: Hapus Join md_kebun, pakai kebun_id langsung
        $sql = "SELECT k.*, k.kebun_id as nama_kebun, 
                DATEDIFF(k.tmt_mbt, CURDATE()) as sisa_hari 
                FROM data_karyawan k
                WHERE k.tmt_mbt IS NOT NULL";

        $params = [];

        if ($year) {
            $sql .= " AND YEAR(k.tmt_mbt) = :year";
            $params[':year'] = $year;
        }
        if ($f_afdeling) {
            $sql .= " AND k.afdeling = :afd";
            $params[':afd'] = $f_afdeling;
        }
        if ($q) {
            $sql .= " AND (k.nama_lengkap LIKE :q OR k.jabatan_real LIKE :q OR k.id_sap LIKE :q)";
            $params[':q'] = "%$q%";
        }

        $sql .= " ORDER BY k.tmt_mbt ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mappedData = array_map(function ($row) {
            $row['sap_id'] = $row['id_sap'];
            $row['nama_karyawan'] = $row['nama_lengkap'];
            $row['foto_karyawan'] = $row['foto_profil'];
            return $row;
        }, $data);

        echo json_encode(['success' => true, 'data' => $mappedData]);
        exit;
    }

    // ============================================================
    // E. CRUD KARYAWAN (STORE/UPDATE/DELETE)
    // ============================================================
    if ($action === 'store' || $action === 'update') {
        $id = $_POST['id'] ?? null;

        // ✅ FIX: Mapping Form ke DB (status_pajak sesuai kolom DB asli)
        $fieldMap = [
            'sap_id' => 'id_sap',
            'nama_karyawan' => 'nama_lengkap',
            'nik_ktp' => 'nik_ktp',
            'gender' => 'gender',
            'tempat_lahir' => 'tempat_lahir',
            'tgl_lahir' => 'tanggal_lahir',
            'person_grade' => 'person_grade',
            'phdp_golongan' => 'phdp_golongan',
            'status_keluarga' => 's_kel',
            'jabatan_sap' => 'jabatan_sap',
            'jabatan_real' => 'jabatan_real',
            'afdeling' => 'afdeling',
            'kebun_id' => 'kebun_id', // Text
            'status_karyawan' => 'status_karyawan',
            'tmt_kerja' => 'tmt_kerja',
            'tmt_mbt' => 'tmt_mbt',
            'tmt_pensiun' => 'tmt_pensiun',
            'tax_id' => 'tax_id',
            'bpjs_id' => 'bpjs_id',
            'jamsostek_id' => 'jamsostek_id',
            'nama_bank' => 'nama_bank',
            'no_rekening' => 'no_rekening',
            'nama_pemilik_rekening' => 'nama_pemilik_rekening',
            'no_hp' => 'no_hp',
            'agama' => 'agama',
            'status_pajak' => 'status_pajak',
            'pendidikan_terakhir' => 'pendidikan_terakhir',
            'jurusan' => 'jurusan',
            'institusi' => 'institusi'
        ];

        $params = [];
        $dbCols = [];
        $dbVals = [];
        $updateSets = [];

        foreach ($fieldMap as $formField => $dbCol) {
            $val = isset($_POST[$formField]) && $_POST[$formField] !== '' ? $_POST[$formField] : null;
            if ($formField === 'gender' && !empty($val)) $val = strtoupper(substr($val, 0, 1));

            $params[":$dbCol"] = $val;
            $dbCols[] = $dbCol;
            $dbVals[] = ":$dbCol";
            $updateSets[] = "$dbCol = :$dbCol";
        }

        // Upload Foto
        $foto_name = null;
        if (!empty($_FILES['foto_karyawan']['name'])) {
            $dir = "../uploads/profil/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['foto_karyawan']['name'], PATHINFO_EXTENSION);
            $foto_name = "KARYAWAN_" . time() . ".$ext";
            move_uploaded_file($_FILES['foto_karyawan']['tmp_name'], $dir . $foto_name);
            $params[':foto'] = $foto_name;
        }

        // Upload Dokumen
        $doc_name = null;
        if (!empty($_FILES['dokumen_file']['name'])) {
            $dirDoc = "../uploads/dokumen/";
            if (!is_dir($dirDoc)) mkdir($dirDoc, 0777, true);
            $extDoc = pathinfo($_FILES['dokumen_file']['name'], PATHINFO_EXTENSION);
            $doc_name = "DOC_" . ($_POST['sap_id'] ?? 'X') . "_" . time() . ".$extDoc";
            move_uploaded_file($_FILES['dokumen_file']['tmp_name'], $dirDoc . $doc_name);
            $params[':doc'] = $doc_name;
        }

        if ($action === 'store') {
            $colsStr = implode(',', $dbCols);
            $valsStr = implode(',', $dbVals);
            if ($foto_name) {
                $colsStr .= ",foto_profil";
                $valsStr .= ",:foto";
            }
            if ($doc_name) {
                $colsStr .= ",dokumen_path";
                $valsStr .= ",:doc";
            }
            $colsStr .= ",created_at,updated_at";
            $valsStr .= ",NOW(),NOW()";

            $stmt = $conn->prepare("INSERT INTO data_karyawan ($colsStr) VALUES ($valsStr)");
            $stmt->execute($params);
        } else {
            if ($foto_name) $updateSets[] = "foto_profil = :foto";
            if ($doc_name)  $updateSets[] = "dokumen_path = :doc";
            $updateSets[] = "updated_at = NOW()";

            $params[':id'] = $id;
            $stmt = $conn->prepare("UPDATE data_karyawan SET " . implode(',', $updateSets) . " WHERE id = :id");
            $stmt->execute($params);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT foto_profil FROM data_karyawan WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists("../uploads/profil/$old")) unlink("../uploads/profil/$old");

        $conn->prepare("DELETE FROM data_karyawan WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // F. IMPORT EXCEL LIB (FULL VERSION TANPA LOOKUP)
    // ============================================================
    if ($action === 'import_excel_lib') {
        if (empty($_FILES['file_excel']['name'])) {
            echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
            exit;
        }

        $fileTmp = $_FILES['file_excel']['tmp_name'];
        try {
            // 1. Load Excel
            $spreadsheet = IOFactory::load($fileTmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            // PERUBAHAN: Tidak ada lookup kebunMap, text langsung masuk

            // 2. Query Insert Full (✅ FIX: status_pajak, bukan status_tax)
            $query = "INSERT INTO data_karyawan 
                (id_sap, old_pers_no, nama_lengkap, nik_ktp, gender, tempat_lahir, tanggal_lahir, 
                 person_grade, phdp_golongan, s_kel, jabatan_sap, jabatan_real, 
                 kebun_id, afdeling, status_karyawan, tmt_kerja, tmt_mbt, tmt_pensiun, 
                 tax_id, bpjs_id, jamsostek_id, nama_bank, no_rekening, nama_pemilik_rekening, 
                 no_hp, agama, status_pajak, pendidikan_terakhir, jurusan, institusi,
                 created_at, updated_at) 
                 VALUES 
                 (?, ?, ?, ?, ?, ?, ?, 
                  ?, ?, ?, ?, ?, 
                  ?, ?, ?, ?, ?, ?, 
                  ?, ?, ?, ?, ?, ?, 
                  ?, ?, ?, ?, ?, ?, 
                  NOW(), NOW())
                 ON DUPLICATE KEY UPDATE 
                 nama_lengkap=VALUES(nama_lengkap), jabatan_real=VALUES(jabatan_real), 
                 kebun_id=VALUES(kebun_id), status_karyawan=VALUES(status_karyawan), 
                 updated_at=NOW()";

            $stmtInsert = $conn->prepare($query);
            $inserted = 0;
            $rowIdx = 0;

            // Helper Format Tanggal
            $fmtDate = function ($val) {
                if (empty($val) || $val == '-') return null;
                try {
                    if (is_numeric($val)) {
                        return Date::excelToDateTimeObject($val)->format('Y-m-d');
                    }
                    return date('Y-m-d', strtotime(str_replace('/', '-', $val)));
                } catch (Exception $e) {
                    return null;
                }
            };

            // ✅ FIX: Helper untuk format No Rekening (prevent desimal)
            // ✅ FIX: Helper untuk format No Rekening (prevent desimal)
            $fmtNoRek = function ($val) {
                if (empty($val) || $val == '-') return null;
                $val = trim($val);
                if (is_numeric($val)) {
                    return number_format((float)$val, 0, '', ''); // Hilangkan .00
                }
                return preg_replace('/[^0-9]/', '', $val);
            };

            foreach ($rows as $row) {
                $rowIdx++;
                // Skip Header (Baris 1) & Data Kosong
                if ($rowIdx <= 1 || empty(trim($row['A']))) continue;

                // Format Gender
                $gender = strtoupper(substr(trim($row['E']), 0, 1));
                if ($gender !== 'L' && $gender !== 'P') $gender = null;

                $vals = [
                    trim($row['A']), // id_sap
                    trim($row['B']), // old_pers_no
                    trim($row['C']), // nama_lengkap
                    trim($row['D']), // nik_ktp
                    $gender,         // gender
                    trim($row['F']), // tempat_lahir
                    $fmtDate($row['G']), // tanggal_lahir

                    trim($row['H']), // person_grade
                    trim($row['I']), // phdp_golongan
                    trim($row['J']), // s_kel (Status Keluarga - SUDAH FIX)
                    trim($row['K']), // jabatan_sap
                    trim($row['L']), // jabatan_real

                    strtoupper(trim($row['M'])), // kebun_id (LANGSUNG TEXT)
                    trim($row['N']), // afdeling
                    trim($row['O']), // status_karyawan
                    $fmtDate($row['P']), // tmt_kerja
                    $fmtDate($row['Q']), // tmt_mbt
                    $fmtDate($row['R']), // tmt_pensiun

                    trim($row['S']), // tax_id
                    trim($row['T']), // bpjs_id
                    trim($row['U']), // jamsostek_id
                    trim($row['V']), // nama_bank
                    $fmtNoRek($row['W']), // ✅ FIX: no_rekening (hilangkan desimal)
                    trim($row['X']), // nama_pemilik_rekening

                    trim($row['Y']), // no_hp
                    trim($row['Z']), // agama
                    trim($row['AA']), // status_pajak (PTKP) - ✅ FIXED
                    trim($row['AB']), // pendidikan_terakhir
                    trim($row['AC']), // jurusan
                    trim($row['AD']) // institusi
                ];

                $stmtInsert->execute($vals);
                $inserted++;
            }
            echo json_encode(['success' => true, 'message' => "Import $inserted data selesai."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ============================================================
    // G. MODUL TANGGUNGAN / KELUARGA
    // ============================================================
    if ($action === 'list_tanggungan') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $sql = "SELECT dt.*, dk.id_sap, dk.nama_lengkap FROM data_keluarga dt LEFT JOIN data_karyawan dk ON dt.karyawan_id = dk.id WHERE 1=1";
        $params = [];
        if ($q) {
            $sql .= " AND (dk.nama_lengkap LIKE :q OR dt.nama_batih LIKE :q)";
            $params[':q'] = "%$q%";
        }
        $sql .= " ORDER BY dk.nama_lengkap ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'store_tanggungan' || $action === 'update_tanggungan') {
        $id = $_POST['id'] ?? null;
        $fields = ['karyawan_id', 'nama_batih', 'hubungan', 'tempat_lahir', 'tanggal_lahir', 'pendidikan_terakhir', 'pekerjaan', 'keterangan'];
        $params = [];
        foreach ($fields as $f) $params[":$f"] = $_POST[$f] ?? null;

        if ($action === 'store_tanggungan') {
            $cols = implode(',', $fields) . ',created_at';
            $vals = implode(',', array_keys($params)) . ',NOW()';
            $sql = "INSERT INTO data_keluarga ($cols) VALUES ($vals)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        } else {
            $set = [];
            foreach ($fields as $f) $set[] = "$f = :$f";
            $sql = "UPDATE data_keluarga SET " . implode(',', $set) . " WHERE id = :id";
            $params[':id'] = $id;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_tanggungan') {
        $id = $_POST['id'];
        $conn->prepare("DELETE FROM data_keluarga WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============================================================
    // H. MODUL PERINGATAN (SP)
    // ============================================================
    if ($action === 'list_peringatan') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        $f_afdeling = isset($_POST['f_afdeling']) ? trim($_POST['f_afdeling']) : '';

        // PERUBAHAN: Hapus JOIN md_kebun, pakai dk.kebun_id langsung
        $sql = "SELECT dp.*, 
                        dk.id_sap, dk.nama_lengkap, dk.afdeling, dk.status_karyawan,
                        dk.kebun_id as nama_kebun
                FROM data_peringatan dp 
                LEFT JOIN data_karyawan dk ON dp.karyawan_id = dk.id 
                WHERE 1=1";

        $params = [];

        if ($q) {
            $sql .= " AND (dk.nama_lengkap LIKE :q OR dp.no_surat LIKE :q OR dk.id_sap LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($f_afdeling) {
            $sql .= " AND dk.afdeling = :afd";
            $params[':afd'] = $f_afdeling;
        }

        $sql .= " ORDER BY dp.tanggal_sp DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    if ($action === 'store_peringatan' || $action === 'update_peringatan') {
        $id = $_POST['id'] ?? null;

        // Upload File SP
        $file_name = null;
        if (!empty($_FILES['file_scan']['name'])) {
            $dir = "../uploads/sp/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['file_scan']['name'], PATHINFO_EXTENSION);
            $file_name = "SP_" . time() . ".$ext";
            move_uploaded_file($_FILES['file_scan']['tmp_name'], $dir . $file_name);
        }

        // Hapus file lama jika update
        if ($action === 'update_peringatan' && $file_name && $id) {
            $cek = $conn->prepare("SELECT file_scan FROM data_peringatan WHERE id=?");
            $cek->execute([$id]);
            $old = $cek->fetchColumn();
            if ($old && file_exists("../uploads/sp/$old")) @unlink("../uploads/sp/$old");
        }

        $fields = ['karyawan_id', 'no_surat', 'jenis_sp', 'tanggal_sp', 'masa_berlaku', 'pelanggaran', 'sanksi'];
        $params = [];
        foreach ($fields as $f) $params[":$f"] = $_POST[$f] ?? null;

        if ($action === 'store_peringatan') {
            $cols = implode(',', $fields);
            $vals = implode(',', array_keys($params));
            if ($file_name) {
                $cols .= ",file_scan";
                $vals .= ",:file";
                $params[':file'] = $file_name;
            }
            $cols .= ",created_at";
            $vals .= ",NOW()";

            $sql = "INSERT INTO data_peringatan ($cols) VALUES ($vals)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        } else {
            $set = [];
            foreach ($fields as $f) $set[] = "$f = :$f";
            if ($file_name) {
                $set[] = "file_scan = :file";
                $params[':file'] = $file_name;
            }

            $sql = "UPDATE data_peringatan SET " . implode(',', $set) . " WHERE id = :id";
            $params[':id'] = $id;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_peringatan') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT file_scan FROM data_peringatan WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists("../uploads/sp/$old")) unlink("../uploads/sp/$old");

        $conn->prepare("DELETE FROM data_peringatan WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 6. DELETE ALL (RESET DATA) - NEW FEATURE
    if ($action === 'delete_all') {
        $secret_code = $_POST['code'] ?? '';

        // VALIDASI KODE RAHASIA
        if ($secret_code !== '5013100') {
            echo json_encode(['success' => false, 'message' => 'Kode Konfirmasi Salah! Data aman.']);
            exit;
        }

        // Jika kode benar, lanjut hapus...
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Hapus file fisik
        $stmt = $conn->query("SELECT foto_profil, dokumen_path FROM data_karyawan");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['foto_profil']) && file_exists("../uploads/profil/" . $r['foto_profil'])) @unlink("../uploads/profil/" . $r['foto_profil']);
            if (!empty($r['dokumen_path']) && file_exists("../uploads/dokumen/" . $r['dokumen_path'])) @unlink("../uploads/dokumen/" . $r['dokumen_path']);
        }

        // Hapus file SP
        $stmtSp = $conn->query("SELECT file_scan FROM data_peringatan");
        while ($r = $stmtSp->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['file_scan']) && file_exists("../uploads/sp/" . $r['file_scan'])) @unlink("../uploads/sp/" . $r['file_scan']);
        }

        $conn->exec("TRUNCATE TABLE data_keluarga");
        $conn->exec("TRUNCATE TABLE data_peringatan");
        $conn->exec("TRUNCATE TABLE data_history_jabatan");
        $conn->exec("TRUNCATE TABLE data_karyawan");

        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo json_encode(['success' => true, 'message' => 'Sukses! Semua data telah dihapus.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}
