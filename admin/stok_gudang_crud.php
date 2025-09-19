<?php
// stok_gudang_crud.php
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
function f($k){ $v = $_POST[$k] ?? null; if ($v===''||$v===null) return null; return is_numeric($v) ? (float)$v : null; }
function validYear($y){ return preg_match('/^(19[7-9]\d|20\d{2}|2100)$/',$y); } // 1970..2100
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];

try {
  $db = new Database();
  $conn = $db->getConnection();

  // LIST (untuk filter realtime)
  if ($action === 'list') {
    $jenis = s('jenis'); // nama_bahan
    $bulan = s('bulan');
    $tahun = (int)($_POST['tahun'] ?? date('Y'));

    $sql = "SELECT * FROM stok_gudang WHERE 1=1";
    $bind = [];
    if ($jenis !== '') { $sql .= " AND nama_bahan = :nb"; $bind[':nb'] = $jenis; }
    if ($bulan !== '') { $sql .= " AND bulan = :bln"; $bind[':bln'] = $bulan; }
    if ($tahun)        { $sql .= " AND tahun = :thn"; $bind[':thn'] = $tahun; }
    $sql .= " ORDER BY nama_bahan ASC, FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), tahun DESC, id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true,'data'=>$rows]); exit;
  }

  // STORE / CREATE
  if ($action === 'store' || $action === 'create') {
    $errors = [];
    $nama_bahan = s('nama_bahan');
    $satuan     = s('satuan');
    $bulan      = s('bulan');
    $tahun      = s('tahun');

    $stok_awal     = f('stok_awal') ?? 0;
    $mutasi_masuk  = f('mutasi_masuk') ?? 0;
    $mutasi_keluar = f('mutasi_keluar') ?? 0;
    $pasokan       = f('pasokan') ?? 0;
    $dipakai       = f('dipakai') ?? 0;

    if ($nama_bahan==='') $errors[]='Nama/Jenis bahan wajib diisi.';
    if ($satuan==='') $errors[]='Satuan wajib diisi.';
    if ($bulan==='' || !in_array($bulan, $bulanList, true)) $errors[]='Bulan tidak valid.';
    if ($tahun==='' || !validYear($tahun)) $errors[]='Tahun tidak valid.';
    foreach (['stok_awal'=>$stok_awal,'mutasi_masuk'=>$mutasi_masuk,'mutasi_keluar'=>$mutasi_keluar,'pasokan'=>$pasokan,'dipakai'=>$dipakai] as $k=>$v){
      if ($v < 0) $errors[] = ucfirst(str_replace('_',' ',$k)).' tidak boleh negatif.';
    }

    if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $sql = "INSERT INTO stok_gudang 
            (nama_bahan, satuan, bulan, tahun, stok_awal, mutasi_masuk, mutasi_keluar, pasokan, dipakai, created_at, updated_at)
            VALUES (:nb, :sat, :bln, :thn, :sa, :mm, :mk, :ps, :dp, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':nb'=>$nama_bahan, ':sat'=>$satuan, ':bln'=>$bulan, ':thn'=>$tahun,
      ':sa'=>$stok_awal, ':mm'=>$mutasi_masuk, ':mk'=>$mutasi_keluar, ':ps'=>$pasokan, ':dp'=>$dipakai
    ]);

    echo json_encode(['success'=>true,'message'=>'Data stok berhasil ditambahkan.']); exit;
  }

  // UPDATE
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors = [];
    $nama_bahan = s('nama_bahan');
    $satuan     = s('satuan');
    $bulan      = s('bulan');
    $tahun      = s('tahun');

    $stok_awal     = f('stok_awal') ?? 0;
    $mutasi_masuk  = f('mutasi_masuk') ?? 0;
    $mutasi_keluar = f('mutasi_keluar') ?? 0;
    $pasokan       = f('pasokan') ?? 0;
    $dipakai       = f('dipakai') ?? 0;

    if ($nama_bahan==='') $errors[]='Nama/Jenis bahan wajib diisi.';
    if ($satuan==='') $errors[]='Satuan wajib diisi.';
    if ($bulan==='' || !in_array($bulan, $bulanList, true)) $errors[]='Bulan tidak valid.';
    if ($tahun==='' || !validYear($tahun)) $errors[]='Tahun tidak valid.';
    foreach (['stok_awal'=>$stok_awal,'mutasi_masuk'=>$mutasi_masuk,'mutasi_keluar'=>$mutasi_keluar,'pasokan'=>$pasokan,'dipakai'=>$dipakai] as $k=>$v){
      if ($v < 0) $errors[] = ucfirst(str_replace('_',' ',$k)).' tidak boleh negatif.';
    }

    if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $sql = "UPDATE stok_gudang SET
              nama_bahan=:nb, satuan=:sat, bulan=:bln, tahun=:thn,
              stok_awal=:sa, mutasi_masuk=:mm, mutasi_keluar=:mk, pasokan=:ps, dipakai=:dp, updated_at=NOW()
            WHERE id=:id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':nb'=>$nama_bahan, ':sat'=>$satuan, ':bln'=>$bulan, ':thn'=>$tahun,
      ':sa'=>$stok_awal, ':mm'=>$mutasi_masuk, ':mk'=>$mutasi_keluar, ':ps'=>$pasokan, ':dp'=>$dipakai, ':id'=>$id
    ]);

    echo json_encode(['success'=>true,'message'=>'Data stok berhasil diperbarui.']); exit;
  }

  // DELETE
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    $stmt = $conn->prepare("DELETE FROM stok_gudang WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data stok berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch (PDOException $e) {
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
