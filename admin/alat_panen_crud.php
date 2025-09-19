<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true){
  echo json_encode(['success'=>false,'message'=>'Silakan login.']); exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){
  echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); exit;
}
if(empty($_SESSION['csrf_token']) || $_SESSION['csrf_token']!==($_POST['csrf_token']??'')){
  echo json_encode(['success'=>false,'message'=>'CSRF token tidak valid.']); exit;
}

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();
$act = $_POST['action'] ?? '';

try {
  if ($act === 'list') {
    $w=[]; $p=[];
    if(!empty($_POST['unit_id'])){ $w[]='a.unit_id=:u'; $p[':u']=$_POST['unit_id']; }
    if(!empty($_POST['bulan'])){ $w[]='a.bulan=:b'; $p[':b']=$_POST['bulan']; }
    if(!empty($_POST['tahun'])){ $w[]='a.tahun=:t'; $p[':t']=(int)$_POST['tahun']; }

    $sql="SELECT a.*, u.nama_unit
          FROM alat_panen a
          JOIN units u ON a.unit_id=u.id".
          (count($w) ? " WHERE ".implode(' AND ',$w) : "") .
          " ORDER BY a.tahun DESC,
             FIELD(a.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
             u.nama_unit ASC, a.jenis_alat ASC";
    $st=$conn->prepare($sql); $st->execute($p);
    echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  if (in_array($act, ['store','update'], true)) {
    // Ambil & sanitasi
    $bulan  = $_POST['bulan'] ?? '';
    $tahun  = (int)($_POST['tahun'] ?? 0);
    $unit   = (int)($_POST['unit_id'] ?? 0);
    $jenis  = trim($_POST['jenis_alat'] ?? '');
    $sa     = (float)($_POST['stok_awal'] ?? 0);
    $mi     = (float)($_POST['mutasi_masuk'] ?? 0);
    $mk     = (float)($_POST['mutasi_keluar'] ?? 0);
    $dp     = (float)($_POST['dipakai'] ?? 0);
    $krani  = trim($_POST['krani_afdeling'] ?? '');
    $cat    = trim($_POST['catatan'] ?? '');

    if (!$bulan || !$tahun || !$unit || !$jenis) {
      echo json_encode(['success'=>false,'message'=>'Bulan, Tahun, Unit, dan Jenis Alat wajib diisi.']); exit;
    }

    // Kalkulasi stok akhir di server (sumber kebenaran)
    $akhir = $sa + $mi - $mk - $dp;

    if ($act === 'store') {
      $sql="INSERT INTO alat_panen (bulan,tahun,unit_id,jenis_alat,stok_awal,mutasi_masuk,mutasi_keluar,dipakai,stok_akhir,krani_afdeling,catatan)
            VALUES (:b,:t,:u,:j,:sa,:mi,:mk,:dp,:ak,:kr,:ct)";
      $st=$conn->prepare($sql);
      $st->execute([
        ':b'=>$bulan, ':t'=>$tahun, ':u'=>$unit, ':j'=>$jenis, ':sa'=>$sa, ':mi'=>$mi, ':mk'=>$mk, ':dp'=>$dp, ':ak'=>$akhir, ':kr'=>$krani, ':ct'=>$cat
      ]);
      echo json_encode(['success'=>true,'message'=>'Data alat panen berhasil disimpan']); exit;

    } else {
      $id=(int)($_POST['id'] ?? 0);
      if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }

      $sql="UPDATE alat_panen SET bulan=:b,tahun=:t,unit_id=:u,jenis_alat=:j,stok_awal=:sa,mutasi_masuk=:mi,mutasi_keluar=:mk,dipakai=:dp,stok_akhir=:ak,krani_afdeling=:kr,catatan=:ct WHERE id=:id";
      $st=$conn->prepare($sql);
      $st->execute([
        ':b'=>$bulan, ':t'=>$tahun, ':u'=>$unit, ':j'=>$jenis, ':sa'=>$sa, ':mi'=>$mi, ':mk'=>$mk, ':dp'=>$dp, ':ak'=>$akhir, ':kr'=>$krani, ':ct'=>$cat, ':id'=>$id
      ]);
      echo json_encode(['success'=>true,'message'=>'Data alat panen berhasil diperbarui']); exit;
    }
  }

  if ($act === 'delete') {
    $id=(int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
    $conn->prepare("DELETE FROM alat_panen WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data alat panen berhasil dihapus']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
