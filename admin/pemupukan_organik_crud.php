<?php
// admin/pemupukan_organik_crud.php (FULL CRUD + CSRF)
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

$action = $_POST['action'] ?? '';
$tab    = $_POST['tab']    ?? '';
if (!in_array($tab, ['angkutan','menabur'], true)) {
  echo json_encode(['success'=>false,'message'=>'Tab tidak valid.']); exit;
}

function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){
  $v = $_POST[$k] ?? null;
  if ($v === '' || $v === null) return null;
  return is_numeric($v) ? (float)$v : null;
}
function validDate($d){ return (bool)strtotime($d); }

try {
  $db = new Database();
  $conn = $db->getConnection();

  // ======= STORE =======
  if ($action === 'store' || $action === 'create') {
    $errors = [];

    if ($tab === 'angkutan') {
      $gudang_asal   = s('gudang_asal');
      $unit_tujuan_id= s('unit_tujuan_id');
      $tanggal       = s('tanggal');
      $jenis_pupuk   = s('jenis_pupuk');
      $jumlah        = f('jumlah');
      $nomor_do      = s('nomor_do');
      $supir         = s('supir');

      if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.';
      if ($unit_tujuan_id==='') $errors[]='Unit tujuan wajib dipilih.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';

      if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      $sql = "INSERT INTO angkutan_pupuk_organik
              (gudang_asal, unit_tujuan_id, tanggal, jenis_pupuk, jumlah, nomor_do, supir, created_at, updated_at)
              VALUES (:ga,:ut,:tgl,:jp,:jml,:ndo,:sp,NOW(),NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->execute([
        ':ga'=>$gudang_asal,
        ':ut'=>(int)$unit_tujuan_id,
        ':tgl'=>$tanggal,
        ':jp'=>$jenis_pupuk,
        ':jml'=>$jumlah ?? 0,
        ':ndo'=>$nomor_do,
        ':sp'=>$supir
      ]);
    } else {
      $unit_id  = s('unit_id');
      $blok     = s('blok');
      $tanggal  = s('tanggal');
      $jenis    = s('jenis_pupuk');
      $jumlah   = f('jumlah');
      $luas     = f('luas');
      $invt     = $_POST['invt_pokok'] ?? null; $invt = ($invt===''||$invt===null) ? null : (int)$invt;
      $catatan  = s('catatan');

      if ($unit_id==='') $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';

      if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      $sql = "INSERT INTO menabur_pupuk_organik
              (unit_id, blok, tanggal, jenis_pupuk, jumlah, luas, invt_pokok, catatan, created_at, updated_at)
              VALUES (:uid,:blk,:tgl,:jp,:jml,:luas,:invt,:cat,NOW(),NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->execute([
        ':uid'=>(int)$unit_id,
        ':blk'=>$blok,
        ':tgl'=>$tanggal,
        ':jp'=>$jenis,
        ':jml'=>$jumlah ?? 0,
        ':luas'=>$luas ?? 0,
        ':invt'=>$invt ?? 0,
        ':cat'=>$catatan
      ]);
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  // ======= UPDATE =======
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors = [];

    if ($tab === 'angkutan') {
      $gudang_asal   = s('gudang_asal');
      $unit_tujuan_id= s('unit_tujuan_id');
      $tanggal       = s('tanggal');
      $jenis_pupuk   = s('jenis_pupuk');
      $jumlah        = f('jumlah');
      $nomor_do      = s('nomor_do');
      $supir         = s('supir');

      if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.';
      if ($unit_tujuan_id==='') $errors[]='Unit tujuan wajib dipilih.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';

      if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      $sql = "UPDATE angkutan_pupuk_organik SET
                gudang_asal=:ga, unit_tujuan_id=:ut, tanggal=:tgl, jenis_pupuk=:jp,
                jumlah=:jml, nomor_do=:ndo, supir=:sp, updated_at=NOW()
              WHERE id=:id";
      $stmt = $conn->prepare($sql);
      $stmt->execute([
        ':ga'=>$gudang_asal,
        ':ut'=>(int)$unit_tujuan_id,
        ':tgl'=>$tanggal,
        ':jp'=>$jenis_pupuk,
        ':jml'=>$jumlah ?? 0,
        ':ndo'=>$nomor_do,
        ':sp'=>$supir,
        ':id'=>$id
      ]);
    } else {
      $unit_id  = s('unit_id');
      $blok     = s('blok');
      $tanggal  = s('tanggal');
      $jenis    = s('jenis_pupuk');
      $jumlah   = f('jumlah');
      $luas     = f('luas');
      $invt     = $_POST['invt_pokok'] ?? null; $invt = ($invt===''||$invt===null) ? null : (int)$invt;
      $catatan  = s('catatan');

      if ($unit_id==='') $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';

      if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      $sql = "UPDATE menabur_pupuk_organik SET
                unit_id=:uid, blok=:blk, tanggal=:tgl, jenis_pupuk=:jp,
                jumlah=:jml, luas=:luas, invt_pokok=:invt, catatan=:cat, updated_at=NOW()
              WHERE id=:id";
      $stmt = $conn->prepare($sql);
      $stmt->execute([
        ':uid'=>(int)$unit_id,
        ':blk'=>$blok,
        ':tgl'=>$tanggal,
        ':jp'=>$jenis,
        ':jml'=>$jumlah ?? 0,
        ':luas'=>$luas ?? 0,
        ':invt'=>$invt ?? 0,
        ':cat'=>$catatan,
        ':id'=>$id
      ]);
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  // ======= DELETE =======
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $stmt = $conn->prepare($tab==='angkutan'
      ? "DELETE FROM angkutan_pupuk_organik WHERE id=:id"
      : "DELETE FROM menabur_pupuk_organik WHERE id=:id");
    $stmt->execute([':id'=>$id]);

    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch (PDOException $e) {
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
