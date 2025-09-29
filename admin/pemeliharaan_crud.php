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

// lookup helper -> nama
function namaById(PDO $pdo, $table, $id, $field='nama'){
  if (!$id) return null;
  $allowed = ['md_jenis_pekerjaan','md_tenaga','md_kebun'];
  if (!in_array($table, $allowed, true)) return null;
  // kolom untuk md_kebun berbeda (nama_kebun)
  $col = $field;
  if ($table==='md_kebun') $col = 'nama_kebun';
  $st = $pdo->prepare("SELECT {$col} AS nama FROM {$table} WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row['nama'] ?? null;
}

try{
  if ($action==='store' || $action==='create'){
    $kategori = str('kategori');

    // ambil id dari dropdown
    $jenis_id   = $_POST['jenis_id']!=='' ? (int)$_POST['jenis_id'] : 0;
    $tenaga_id  = $_POST['tenaga_id']!=='' ? (int)$_POST['tenaga_id'] : 0;
    $kebun_id   = $_POST['kebun_id']!=='' ? (int)$_POST['kebun_id'] : 0;

    // konversi ke nama (yang akan disimpan)
    $jenis_nama   = namaById($pdo, 'md_jenis_pekerjaan', $jenis_id);
    $tenaga_nama  = namaById($pdo, 'md_tenaga', $tenaga_id);
    $kebun_nama   = $kebun_id ? namaById($pdo, 'md_kebun', $kebun_id, 'nama_kebun') : null;

    $unit_id  = $_POST['unit_id']!=='' ? (int)$_POST['unit_id'] : null;

    // rayon dipakai untuk MENYIMPAN nama kebun (tanpa ubah skema)
    $rayon    = $kebun_nama ?: str('rayon'); // jika pilih kebun, override
    $tanggal  = str('tanggal');
    $bulan    = str('bulan');
    $tahun    = str('tahun');
    $rencana  = num('rencana');
    $realisasi= num('realisasi');
    $status   = str('status') ?: 'Berjalan';

    $errors=[];
    if(!in_array($kategori,$allowedKategori,true)) $errors[]='Kategori tidak valid.';
    if(!$jenis_id || !$jenis_nama) $errors[]='Jenis pekerjaan wajib dipilih.';
    if(!$tenaga_id || !$tenaga_nama) $errors[]='Tenaga wajib dipilih.';
    if(!$unit_id)   $errors[]='Unit/Devisi wajib dipilih.';
    if($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
    if(!in_array($bulan,$bulanList,true)) $errors[]='Bulan tidak valid.';
    if($tahun==='' || !preg_match('/^(20[0-9]{2}|2100)$/',$tahun)) $errors[]='Tahun harus 2000–2100.';
    if($rencana!==null && $rencana<0) $errors[]='Rencana tidak boleh negatif.';
    if($realisasi!==null && $realisasi<0) $errors[]='Realisasi tidak boleh negatif.';
    if(!in_array($status,['Berjalan','Selesai','Tertunda'],true)) $status='Berjalan';

    if($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $sql="INSERT INTO pemeliharaan
          (kategori, jenis_pekerjaan, tenaga, unit_id, rayon, tanggal, bulan, tahun, rencana, realisasi, status, created_at, updated_at)
          VALUES
          (:kategori,:jenis,:tenaga,:unit_id,:rayon,:tanggal,:bulan,:tahun,:rencana,:realisasi,:status,NOW(),NOW())";
    $st=$pdo->prepare($sql);
    $st->execute([
      ':kategori'=>$kategori, ':jenis'=>$jenis_nama, ':tenaga'=>$tenaga_nama,
      ':unit_id'=>$unit_id, ':rayon'=>$rayon,
      ':tanggal'=>$tanggal, ':bulan'=>$bulan, ':tahun'=>$tahun,
      ':rencana'=>$rencana??0, ':realisasi'=>$realisasi??0, ':status'=>$status
    ]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  if ($action==='update'){
    $id=(int)($_POST['id']??0);
    if($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $jenis_id   = $_POST['jenis_id']!=='' ? (int)$_POST['jenis_id'] : 0;
    $tenaga_id  = $_POST['tenaga_id']!=='' ? (int)$_POST['tenaga_id'] : 0;
    $kebun_id   = $_POST['kebun_id']!=='' ? (int)$_POST['kebun_id'] : 0;

    $jenis_nama   = namaById($pdo, 'md_jenis_pekerjaan', $jenis_id);
    $tenaga_nama  = namaById($pdo, 'md_tenaga', $tenaga_id);
    $kebun_nama   = $kebun_id ? namaById($pdo, 'md_kebun', $kebun_id, 'nama_kebun') : null;

    $unit_id  = $_POST['unit_id']!=='' ? (int)$_POST['unit_id'] : null;
    // rayon: tetap jadi nama kebun (kalau kebun dipilih); jika tidak, gunakan yang dikirim (hidden)
    $rayon    = $kebun_nama ?: str('rayon');
    $tanggal  = str('tanggal');
    $bulan    = str('bulan');
    $tahun    = str('tahun');
    $rencana  = num('rencana');
    $realisasi= num('realisasi');
    $status   = str('status') ?: 'Berjalan';

    $errors=[];
    if(!$jenis_id || !$jenis_nama) $errors[]='Jenis pekerjaan wajib dipilih.';
    if(!$tenaga_id || !$tenaga_nama) $errors[]='Tenaga wajib dipilih.';
    if(!$unit_id)   $errors[]='Unit/Devisi wajib dipilih.';
    if($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
    if(!in_array($bulan,$bulanList,true)) $errors[]='Bulan tidak valid.';
    if($tahun==='' || !preg_match('/^(20[0-9]{2}|2100)$/',$tahun)) $errors[]='Tahun harus 2000–2100.';
    if($rencana!==null && $rencana<0) $errors[]='Rencana tidak boleh negatif.';
    if($realisasi!==null && $realisasi<0) $errors[]='Realisasi tidak boleh negatif.';
    if(!in_array($status,['Berjalan','Selesai','Tertunda'],true)) $status='Berjalan';

    if($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $sql="UPDATE pemeliharaan SET
            jenis_pekerjaan=:jenis,
            tenaga=:tenaga,
            unit_id=:unit_id,
            rayon=:rayon,
            tanggal=:tanggal,
            bulan=:bulan,
            tahun=:tahun,
            rencana=:rencana,
            realisasi=:realisasi,
            status=:status,
            updated_at=NOW()
          WHERE id=:id";
    $st=$pdo->prepare($sql);
    $st->execute([
      ':jenis'=>$jenis_nama, ':tenaga'=>$tenaga_nama, ':unit_id'=>$unit_id, ':rayon'=>$rayon, ':tanggal'=>$tanggal,
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
