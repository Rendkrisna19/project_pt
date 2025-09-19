<?php
// pages/pemeliharaan_crud.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(['success'=>false,'message'=>'Akses ditolak. Silakan login.']); exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {
  echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token'])) {
  echo json_encode(['success'=>false,'message'=>'Token keamanan tidak valid. Silakan refresh halaman.']); exit;
}

require_once '../config/database.php';
$db = new Database(); $pdo = $db->getConnection();

$action = $_POST['action'] ?? '';

$allowedKategori = ['TU','TBM','TM','BIBIT_PN','BIBIT_MN'];
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];

function str($k){ return trim((string)($_POST[$k] ?? '')); }
function num($k){ $v=$_POST[$k]??null; return ($v===''||$v===null)?null:(is_numeric($v)?(float)$v:null); }
function validDate($d){ return (bool)strtotime($d); }

try{
  if ($action==='store' || $action==='create'){
    $kategori = str('kategori');
    $jenis    = str('jenis_pekerjaan');
    $unit_id  = $_POST['unit_id']!=='' ? (int)$_POST['unit_id'] : null; // FK ke units
    $rayon    = str('rayon');
    $tanggal  = str('tanggal');
    $bulan    = str('bulan');
    $tahun    = str('tahun');
    $rencana  = num('rencana');
    $realisasi= num('realisasi');
    $status   = str('status') ?: 'Berjalan';

    $errors=[];
    if(!in_array($kategori,$allowedKategori,true)) $errors[]='Kategori tidak valid.';
    if($jenis==='') $errors[]='Jenis pekerjaan wajib diisi.';
    if(!$unit_id)   $errors[]='Unit/Devisi wajib dipilih.';
    if($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
    if(!in_array($bulan,$bulanList,true)) $errors[]='Bulan tidak valid.';
    if($tahun==='' || !preg_match('/^(20[0-9]{2}|2100)$/',$tahun)) $errors[]='Tahun harus 2000–2100.';
    if($rencana!==null && $rencana<0) $errors[]='Rencana tidak boleh negatif.';
    if($realisasi!==null && $realisasi<0) $errors[]='Realisasi tidak boleh negatif.';
    if(!in_array($status,['Berjalan','Selesai','Tertunda'],true)) $status='Berjalan';

    if($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $sql="INSERT INTO pemeliharaan
          (kategori, jenis_pekerjaan, unit_id, rayon, tanggal, bulan, tahun, rencana, realisasi, status, created_at, updated_at)
          VALUES
          (:kategori,:jenis,:unit_id,:rayon,:tanggal,:bulan,:tahun,:rencana,:realisasi,:status,NOW(),NOW())";
    $st=$pdo->prepare($sql);
    $st->execute([
      ':kategori'=>$kategori, ':jenis'=>$jenis, ':unit_id'=>$unit_id, ':rayon'=>$rayon,
      ':tanggal'=>$tanggal, ':bulan'=>$bulan, ':tahun'=>$tahun,
      ':rencana'=>$rencana??0, ':realisasi'=>$realisasi??0, ':status'=>$status
    ]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  if ($action==='update'){
    $id=(int)($_POST['id']??0);
    if($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $jenis    = str('jenis_pekerjaan');
    $unit_id  = $_POST['unit_id']!=='' ? (int)$_POST['unit_id'] : null;
    $rayon    = str('rayon');
    $tanggal  = str('tanggal');
    $bulan    = str('bulan');
    $tahun    = str('tahun');
    $rencana  = num('rencana');
    $realisasi= num('realisasi');
    $status   = str('status') ?: 'Berjalan';

    $errors=[];
    if($jenis==='') $errors[]='Jenis pekerjaan wajib diisi.';
    if(!$unit_id)   $errors[]='Unit/Devisi wajib dipilih.';
    if($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
    if(!in_array($bulan,$bulanList,true)) $errors[]='Bulan tidak valid.';
    if($tahun==='' || !preg_match('/^(20[0-9]{2}|2100)$/',$tahun)) $errors[]='Tahun harus 2000–2100.';
    if($rencana!==null && $rencana<0) $errors[]='Rencana tidak boleh negatif.';
    if($realisasi!==null && $realisasi<0) $errors[]='Realisasi tidak boleh negatif.';
    if(!in_array($status,['Berjalan','Selesai','Tertunda'],true)) $status='Berjalan';

    if($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $sql="UPDATE pemeliharaan SET
            jenis_pekerjaan=:jenis, unit_id=:unit_id, rayon=:rayon, tanggal=:tanggal,
            bulan=:bulan, tahun=:tahun, rencana=:rencana, realisasi=:realisasi,
            status=:status, updated_at=NOW()
          WHERE id=:id";
    $st=$pdo->prepare($sql);
    $st->execute([
      ':jenis'=>$jenis, ':unit_id'=>$unit_id, ':rayon'=>$rayon, ':tanggal'=>$tanggal,
      ':bulan'=>$bulan, ':tahun'=>$tahun, ':rencana'=>$rencana??0, ':realisasi'=>$realisasi??0,
      ':status'=>$status, ':id'=>$id
    ]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  if ($action==='delete'){
    $id=(int)($_POST['id']??0);
    if($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    $pdo->prepare("DELETE FROM pemeliharaan WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
}catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
