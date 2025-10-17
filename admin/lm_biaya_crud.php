<?php
// admin/lm_biaya_crud.php
// Disesuaikan: dukung tabel tanpa kebun_id (optional), validasi dinamis, CSRF, hasil JSON.

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(['success'=>false,'message'=>'Akses ditolak.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success'=>false,'message'=>'Metode tidak valid.']); exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
  echo json_encode(['success'=>false,'message'=>'Token CSRF tidak valid.']); exit;
}

require_once '../config/database.php';

function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

$action = $_POST['action'] ?? '';

try {
  $db = new Database();
  $conn = $db->getConnection();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $hasKebun = col_exists($conn,'lm_biaya','kebun_id');

  $num = function($k){
    if (!isset($_POST[$k]) || $_POST[$k]==='') return 0.0;
    return (float)$_POST[$k];
  };
  $str = fn($k)=> trim((string)($_POST[$k] ?? ''));
  $int = fn($k)=> (int)($_POST[$k] ?? 0);

  if ($action === 'store') {
    $kebun_id  = $int('kebun_id');
    $unit_id   = $int('unit_id');
    $alokasi   = $str('alokasi');
    $uraian    = $str('uraian_pekerjaan');
    $bulan     = $str('bulan');
    $tahun     = $int('tahun');
    $rencana   = $num('rencana_bi');
    $realisasi = $num('realisasi_bi');

    $errors=[];
    if ($hasKebun && !$kebun_id) $errors[]='Kebun wajib dipilih.';
    if (!$unit_id) $errors[]='Unit wajib dipilih.';
    if ($alokasi==='') $errors[]='Alokasi wajib diisi.';
    if ($uraian==='') $errors[]='Uraian pekerjaan wajib diisi.';
    if ($bulan==='')  $errors[]='Bulan wajib diisi.';
    if ($tahun<=0)    $errors[]='Tahun wajib diisi.';
    if ($rencana<0)   $errors[]='Anggaran tidak valid.';
    if ($realisasi<0) $errors[]='Realisasi tidak valid.';
    if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    if ($hasKebun) {
      $stmt = $conn->prepare("INSERT INTO lm_biaya 
        (kebun_id, unit_id, alokasi, uraian_pekerjaan, bulan, tahun, rencana_bi, realisasi_bi, created_at, updated_at)
        VALUES (:kid,:uid,:al,:ur,:bln,:thn,:rbi,:reb,NOW(),NOW())");
      $stmt->execute([
        ':kid'=>$kebun_id, ':uid'=>$unit_id, ':al'=>$alokasi, ':ur'=>$uraian,
        ':bln'=>$bulan, ':thn'=>$tahun, ':rbi'=>$rencana, ':reb'=>$realisasi
      ]);
    } else {
      $stmt = $conn->prepare("INSERT INTO lm_biaya 
        (unit_id, alokasi, uraian_pekerjaan, bulan, tahun, rencana_bi, realisasi_bi, created_at, updated_at)
        VALUES (:uid,:al,:ur,:bln,:thn,:rbi,:reb,NOW(),NOW())");
      $stmt->execute([
        ':uid'=>$unit_id, ':al'=>$alokasi, ':ur'=>$uraian,
        ':bln'=>$bulan, ':thn'=>$tahun, ':rbi'=>$rencana, ':reb'=>$realisasi
      ]);
    }
    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  if ($action === 'update') {
    $id = $int('id');
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $kebun_id  = $int('kebun_id');
    $unit_id   = $int('unit_id');
    $alokasi   = $str('alokasi');
    $uraian    = $str('uraian_pekerjaan');
    $bulan     = $str('bulan');
    $tahun     = $int('tahun');
    $rencana   = $num('rencana_bi');
    $realisasi = $num('realisasi_bi');

    $errors=[];
    if ($hasKebun && !$kebun_id) $errors[]='Kebun wajib dipilih.';
    if (!$unit_id) $errors[]='Unit wajib dipilih.';
    if ($alokasi==='') $errors[]='Alokasi wajib diisi.';
    if ($uraian==='') $errors[]='Uraian pekerjaan wajib diisi.';
    if ($bulan==='')  $errors[]='Bulan wajib diisi.';
    if ($tahun<=0)    $errors[]='Tahun wajib diisi.';
    if ($rencana<0)   $errors[]='Anggaran tidak valid.';
    if ($realisasi<0) $errors[]='Realisasi tidak valid.';
    if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    if ($hasKebun) {
      $stmt = $conn->prepare("UPDATE lm_biaya SET
        kebun_id=:kid, unit_id=:uid, alokasi=:al, uraian_pekerjaan=:ur,
        bulan=:bln, tahun=:thn, rencana_bi=:rbi, realisasi_bi=:reb, updated_at=NOW()
        WHERE id=:id");
      $stmt->execute([
        ':kid'=>$kebun_id, ':uid'=>$unit_id, ':al'=>$alokasi, ':ur'=>$uraian,
        ':bln'=>$bulan, ':thn'=>$tahun, ':rbi'=>$rencana, ':reb'=>$realisasi, ':id'=>$id
      ]);
    } else {
      $stmt = $conn->prepare("UPDATE lm_biaya SET
        unit_id=:uid, alokasi=:al, uraian_pekerjaan=:ur,
        bulan=:bln, tahun=:thn, rencana_bi=:rbi, realisasi_bi=:reb, updated_at=NOW()
        WHERE id=:id");
      $stmt->execute([
        ':uid'=>$unit_id, ':al'=>$alokasi, ':ur'=>$uraian,
        ':bln'=>$bulan, ':thn'=>$tahun, ':rbi'=>$rencana, ':reb'=>$realisasi, ':id'=>$id
      ]);
    }
    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  if ($action === 'delete') {
    $id = $int('id');
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    $stmt = $conn->prepare("DELETE FROM lm_biaya WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
