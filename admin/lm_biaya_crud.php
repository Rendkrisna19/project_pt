<?php
// admin/lm_biaya_crud.php
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

function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){
  $v = $_POST[$k] ?? null;
  if ($v === '' || $v === null) return null;
  return is_numeric($v) ? (float)$v : null;
}

try {
  $db = new Database();
  $conn = $db->getConnection();

  if ($action === 'store' || $action === 'create') {
    $errors = [];
    $kode_aktivitas_id = (int)($_POST['kode_aktivitas_id'] ?? 0);
    $jenis_pekerjaan_id = $_POST['jenis_pekerjaan_id'] !== '' ? (int)$_POST['jenis_pekerjaan_id'] : null;
    $bulan = s('bulan');
    $tahun = (int)($_POST['tahun'] ?? 0);
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $rencana = f('rencana_bi');
    $realisasi = f('realisasi_bi');
    $catatan = s('catatan');

    if ($kode_aktivitas_id<=0) $errors[]='Kode aktivitas wajib dipilih.';
    if ($bulan==='') $errors[]='Bulan wajib dipilih.';
    if ($tahun<=0) $errors[]='Tahun wajib diisi.';
    if ($unit_id<=0) $errors[]='Unit wajib dipilih.';
    if ($rencana===null || $rencana<0) $errors[]='Rencana BI tidak valid.';
    if ($realisasi===null || $realisasi<0) $errors[]='Realisasi BI tidak valid.';

    if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $stmt = $conn->prepare("INSERT INTO lm_biaya
      (kode_aktivitas_id, jenis_pekerjaan_id, bulan, tahun, unit_id, rencana_bi, realisasi_bi, catatan, created_at, updated_at)
      VALUES (:ka,:jp,:bln,:th,:unit,:rbi,:reb,:cat,NOW(),NOW())");
    $stmt->execute([
      ':ka'=>$kode_aktivitas_id, ':jp'=>$jenis_pekerjaan_id, ':bln'=>$bulan, ':th'=>$tahun,
      ':unit'=>$unit_id, ':rbi'=>$rencana, ':reb'=>$realisasi, ':cat'=>$catatan?:null
    ]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors = [];
    $kode_aktivitas_id = (int)($_POST['kode_aktivitas_id'] ?? 0);
    $jenis_pekerjaan_id = $_POST['jenis_pekerjaan_id'] !== '' ? (int)$_POST['jenis_pekerjaan_id'] : null;
    $bulan = s('bulan');
    $tahun = (int)($_POST['tahun'] ?? 0);
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $rencana = f('rencana_bi');
    $realisasi = f('realisasi_bi');
    $catatan = s('catatan');

    if ($kode_aktivitas_id<=0) $errors[]='Kode aktivitas wajib dipilih.';
    if ($bulan==='') $errors[]='Bulan wajib dipilih.';
    if ($tahun<=0) $errors[]='Tahun wajib diisi.';
    if ($unit_id<=0) $errors[]='Unit wajib dipilih.';
    if ($rencana===null || $rencana<0) $errors[]='Rencana BI tidak valid.';
    if ($realisasi===null || $realisasi<0) $errors[]='Realisasi BI tidak valid.';

    if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $stmt = $conn->prepare("UPDATE lm_biaya SET
      kode_aktivitas_id=:ka, jenis_pekerjaan_id=:jp, bulan=:bln, tahun=:th, unit_id=:unit,
      rencana_bi=:rbi, realisasi_bi=:reb, catatan=:cat, updated_at=NOW()
      WHERE id=:id");
    $stmt->execute([
      ':ka'=>$kode_aktivitas_id, ':jp'=>$jenis_pekerjaan_id, ':bln'=>$bulan, ':th'=>$tahun,
      ':unit'=>$unit_id, ':rbi'=>$rencana, ':reb'=>$realisasi, ':cat'=>$catatan?:null, ':id'=>$id
    ]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $stmt = $conn->prepare("DELETE FROM lm_biaya WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch (PDOException $e) {
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
