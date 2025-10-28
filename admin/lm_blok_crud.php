<?php
// lm_blok_crud.php — endpoint ringan untuk simpan anggaran per blok
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['loggedin'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$userRole = $_SESSION['user_role'] ?? 'staf';
if ($userRole === 'staf') { echo json_encode(['success'=>false,'message'=>'Izin ditolak']); exit; }

if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  echo json_encode(['success'=>false,'message'=>'CSRF tidak valid']); exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'save_anggaran_blok') { echo json_encode(['success'=>false,'message'=>'Aksi tidak valid']); exit; }

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$kebun = (int)($_POST['kebun_id'] ?? 0);
$unit  = (int)($_POST['unit_id'] ?? 0);
$jpm   = (int)($_POST['jenis_pekerjaan_id'] ?? 0);
$tahun = (int)($_POST['tahun'] ?? 0);
$rows  = json_decode($_POST['payload'] ?? '[]', true);

if (!$kebun || !$unit || !$jpm || !$tahun || !is_array($rows)) {
  echo json_encode(['success'=>false,'message'=>'Data tidak lengkap']); exit;
}

try{
  $conn->beginTransaction();
  $sql = "INSERT INTO lm_anggaran_blok (kebun_id, unit_id, blok_kode, tahun, jenis_pekerjaan_id, anggaran)
          VALUES (:k,:u,:b,:t,:jp,:a)
          ON DUPLICATE KEY UPDATE anggaran=VALUES(anggaran)";
  $st = $conn->prepare($sql);
  foreach($rows as $r){
    $b = trim((string)($r['blok_kode'] ?? ''));
    $a = (float)($r['anggaran'] ?? 0);
    if ($b==='') continue;
    $st->execute([':k'=>$kebun, ':u'=>$unit, ':b'=>$b, ':t'=>$tahun, ':jp'=>$jpm, ':a'=>$a]);
  }
  $conn->commit();
  echo json_encode(['success'=>true, 'message'=>'Anggaran per blok tersimpan']);
}catch(Throwable $e){
  $conn->rollBack();
  error_log("[LM_BLOK_SAVE] ".$e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Gagal menyimpan']);
}
