<?php
// pemakaian_crud.php (REVISI: pakai unit_id)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(['success'=>false,'message'=>'Akses ditolak. Silakan login.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
  echo json_encode(['success'=>false,'message'=>'Token keamanan tidak valid. Refresh halaman.']); exit;
}

require_once '../config/database.php';

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){ $v = $_POST[$k] ?? null; if ($v===''||$v===null) return null; return is_numeric($v) ? (float)$v : null; }
function validYear($y){ return preg_match('/^(19[7-9]\d|20\d{2}|2100)$/',$y); } // 1970..2100

try {
  $db = new Database();
  $conn = $db->getConnection();
  $action = $_POST['action'] ?? '';

  // ===== LIST
  if ($action === 'list') {
    $q = s('q');
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $bulan = s('bulan');
    $tahun = (int)($_POST['tahun'] ?? date('Y'));

    $sql = "SELECT p.*, u.nama_unit AS unit_nama
            FROM pemakaian_bahan_kimia p
            LEFT JOIN units u ON u.id = p.unit_id
            WHERE 1=1";
    $bind = [];
    if ($q !== '') {
      $sql .= " AND (p.no_dokumen LIKE :q OR p.nama_bahan LIKE :q OR p.jenis_pekerjaan LIKE :q)";
      $bind[':q'] = "%$q%";
    }
    if ($unit_id > 0) { $sql .= " AND p.unit_id = :uid"; $bind[':uid'] = $unit_id; }
    if ($bulan !== '') { $sql .= " AND p.bulan = :bln"; $bind[':bln'] = $bulan; }
    if ($tahun) { $sql .= " AND p.tahun = :thn"; $bind[':thn'] = $tahun; }

    $sql .= " ORDER BY p.tahun DESC,
              FIELD(p.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
              p.created_at DESC";
    $stmt = $conn->prepare($sql); $stmt->execute($bind);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  // ===== STORE
  if ($action === 'store' || $action === 'create') {
    $errors = [];
    $no_dokumen = s('no_dokumen');
    $unit_id    = (int)($_POST['unit_id'] ?? 0);
    $bulan      = s('bulan');
    $tahun      = s('tahun');
    $nama_bahan = s('nama_bahan');
    $jenis      = s('jenis_pekerjaan');
    $diminta    = f('jlh_diminta') ?? 0;
    $fisik      = f('jlh_fisik') ?? 0;
    $ket        = s('keterangan');

    if ($no_dokumen==='')   $errors[]='No dokumen wajib diisi.';
    if ($unit_id<=0)        $errors[]='Unit wajib dipilih.';
    if ($bulan==='' || !in_array($bulan,$bulanList,true)) $errors[]='Bulan tidak valid.';
    if ($tahun==='' || !validYear($tahun)) $errors[]='Tahun tidak valid.';
    if ($nama_bahan==='')   $errors[]='Nama bahan wajib diisi.';
    if ($jenis==='')        $errors[]='Jenis pekerjaan wajib diisi.';
    if ($diminta<0 || $fisik<0) $errors[]='Jumlah tidak boleh negatif.';

    // upload dokumen (opsional)
    $path = null; $orig = null;
    if (!empty($_FILES['dokumen']) && $_FILES['dokumen']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['dokumen']['error'] !== UPLOAD_ERR_OK) $errors[]='Gagal upload dokumen.';
      else {
        $dir = realpath(__DIR__ . '/../uploads');
        if (!$dir) { mkdir(__DIR__ . '/../uploads', 0775, true); $dir = realpath(__DIR__ . '/../uploads'); }
        $dirPem = $dir . '/pemakaian';
        if (!is_dir($dirPem)) mkdir($dirPem, 0775, true);
        $ext = pathinfo($_FILES['dokumen']['name'], PATHINFO_EXTENSION);
        $fname = 'PK_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
        $full = $dirPem . '/' . $fname;
        if (!move_uploaded_file($_FILES['dokumen']['tmp_name'], $full)) $errors[]='Tidak bisa menyimpan dokumen.';
        else { $path = '../uploads/pemakaian/' . $fname; $orig = $_FILES['dokumen']['name']; }
      }
    }

    if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $sql = "INSERT INTO pemakaian_bahan_kimia
            (no_dokumen, unit_id, bulan, tahun, nama_bahan, jenis_pekerjaan, jlh_diminta, jlh_fisik, dokumen_path, dokumen_name, keterangan, created_at, updated_at)
            VALUES (:no,:uid,:bln,:thn,:nb,:jp,:dim,:fis,:path,:orig,:ket,NOW(),NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':no'=>$no_dokumen, ':uid'=>$unit_id, ':bln'=>$bulan, ':thn'=>$tahun,
      ':nb'=>$nama_bahan, ':jp'=>$jenis, ':dim'=>$diminta, ':fis'=>$fisik,
      ':path'=>$path, ':orig'=>$orig, ':ket'=>$ket
    ]);

    echo json_encode(['success'=>true,'message'=>'Data pemakaian berhasil ditambahkan.']); exit;
  }

  // ===== UPDATE
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors = [];
    $no_dokumen = s('no_dokumen');
    $unit_id    = (int)($_POST['unit_id'] ?? 0);
    $bulan      = s('bulan');
    $tahun      = s('tahun');
    $nama_bahan = s('nama_bahan');
    $jenis      = s('jenis_pekerjaan');
    $diminta    = f('jlh_diminta') ?? 0;
    $fisik      = f('jlh_fisik') ?? 0;
    $ket        = s('keterangan');

    if ($no_dokumen==='')   $errors[]='No dokumen wajib diisi.';
    if ($unit_id<=0)        $errors[]='Unit wajib dipilih.';
    if ($bulan==='' || !in_array($bulan,$bulanList,true)) $errors[]='Bulan tidak valid.';
    if ($tahun==='' || !validYear($tahun)) $errors[]='Tahun tidak valid.';
    if ($nama_bahan==='')   $errors[]='Nama bahan wajib diisi.';
    if ($jenis==='')        $errors[]='Jenis pekerjaan wajib diisi.';
    if ($diminta<0 || $fisik<0) $errors[]='Jumlah tidak boleh negatif.';

    // file baru?
    $path = null; $orig = null; $addSet = '';
    if (!empty($_FILES['dokumen']) && $_FILES['dokumen']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['dokumen']['error'] !== UPLOAD_ERR_OK) $errors[]='Gagal upload dokumen.';
      else {
        $dir = realpath(__DIR__ . '/../uploads');
        if (!$dir) { mkdir(__DIR__ . '/../uploads', 0775, true); $dir = realpath(__DIR__ . '/../uploads'); }
        $dirPem = $dir . '/pemakaian';
        if (!is_dir($dirPem)) mkdir($dirPem, 0775, true);
        $ext = pathinfo($_FILES['dokumen']['name'], PATHINFO_EXTENSION);
        $fname = 'PK_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . strtolower($ext);
        $full = $dirPem . '/' . $fname;
        if (!move_uploaded_file($_FILES['dokumen']['tmp_name'], $full)) $errors[]='Tidak bisa menyimpan dokumen.';
        else { $path = '../uploads/pemakaian/' . $fname; $orig = $_FILES['dokumen']['name']; $addSet = ", dokumen_path=:path, dokumen_name=:orig"; }
      }
    }

    if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $sql = "UPDATE pemakaian_bahan_kimia SET
              no_dokumen=:no, unit_id=:uid, bulan=:bln, tahun=:thn,
              nama_bahan=:nb, jenis_pekerjaan=:jp, jlh_diminta=:dim, jlh_fisik=:fis, keterangan=:ket
              $addSet, updated_at=NOW()
            WHERE id=:id";
    $stmt = $conn->prepare($sql);
    $params = [
      ':no'=>$no_dokumen, ':uid'=>$unit_id, ':bln'=>$bulan, ':thn'=>$tahun,
      ':nb'=>$nama_bahan, ':jp'=>$jenis, ':dim'=>$diminta, ':fis'=>$fisik,
      ':ket'=>$ket, ':id'=>$id
    ];
    if ($addSet) { $params[':path']=$path; $params[':orig']=$orig; }
    $stmt->execute($params);

    echo json_encode(['success'=>true,'message'=>'Data pemakaian berhasil diperbarui.']); exit;
  }

  // ===== DELETE
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    $stmt = $conn->prepare("DELETE FROM pemakaian_bahan_kimia WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data pemakaian berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch (PDOException $e) {
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
