<?php
// stok_gudang_crud.php (FINAL) — konsisten relasi kebun_id & bahan_id, hitung net_mutasi & sisa_stok di SQL
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { echo json_encode(['success'=>false,'message'=>'Akses ditolak. Silakan login.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); exit; }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
  echo json_encode(['success'=>false,'message'=>'Token keamanan tidak valid. Refresh halaman.']); exit;
}

require_once '../config/database.php';

$action = $_POST['action'] ?? '';
function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){ $v = $_POST[$k] ?? null; if ($v===''||$v===null) return null; return is_numeric($v) ? (float)$v : null; }
function i($k){ $v = $_POST[$k] ?? null; if ($v===''||$v===null) return null; return ctype_digit((string)$v) ? (int)$v : null; }
function validYear($y){ return preg_match('/^(19[7-9]\d|20\d{2}|2100)$/',$y); }

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];

try {
  $db = new Database(); $conn = $db->getConnection();

  // ===== LIST
  if ($action === 'list') {
    $kebun_id = i('kebun_id');
    $bahan_id = i('bahan_id');
    $bulan    = s('bulan');
    $tahun    = (int)($_POST['tahun'] ?? date('Y'));

    $sql = "SELECT 
              sg.*,
              k.kode      AS kebun_kode,
              k.nama_kebun,
              b.kode      AS bahan_kode,
              b.nama_bahan,
              st.nama     AS satuan,
              (COALESCE(sg.mutasi_masuk,0) + COALESCE(sg.pasokan,0) - COALESCE(sg.mutasi_keluar,0) - COALESCE(sg.dipakai,0)) AS net_mutasi,
              (COALESCE(sg.stok_awal,0) + COALESCE(sg.mutasi_masuk,0) + COALESCE(sg.pasokan,0) - COALESCE(sg.mutasi_keluar,0) - COALESCE(sg.dipakai,0)) AS sisa_stok
            FROM stok_gudang sg
            JOIN md_kebun k ON k.id = sg.kebun_id
            JOIN md_bahan_kimia b ON b.id = sg.bahan_id
            JOIN md_satuan st ON st.id = b.satuan_id
            WHERE 1=1";
    $bind = [];
    if ($kebun_id) { $sql .= " AND sg.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }
    if ($bahan_id) { $sql .= " AND sg.bahan_id = :bid"; $bind[':bid'] = $bahan_id; }
    if ($bulan !== '') { $sql .= " AND sg.bulan = :bln"; $bind[':bln'] = $bulan; }
    if ($tahun)        { $sql .= " AND sg.tahun = :thn"; $bind[':thn'] = $tahun; }

    $sql .= " ORDER BY k.nama_kebun ASC, b.nama_bahan ASC,
                     FIELD(sg.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
                     sg.tahun DESC, sg.id DESC";

    $st = $conn->prepare($sql);
    $st->execute($bind);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'data'=>$rows]); exit;
  }

  // ===== STORE
  if ($action === 'store' || $action === 'create') {
    $errors = [];
    $kebun_id = i('kebun_id');
    $bahan_id = i('bahan_id');
    $bulan    = s('bulan');
    $tahun    = s('tahun');

    $stok_awal     = f('stok_awal') ?? 0;
    $mutasi_masuk  = f('mutasi_masuk') ?? 0;
    $mutasi_keluar = f('mutasi_keluar') ?? 0;
    $pasokan       = f('pasokan') ?? 0;
    $dipakai       = f('dipakai') ?? 0;

    if (!$kebun_id) $errors[]='Nama kebun wajib dipilih.';
    if (!$bahan_id) $errors[]='Bahan kimia wajib dipilih.';
    if ($bulan==='' || !in_array($bulan, $bulanList, true)) $errors[]='Bulan tidak valid.';
    if ($tahun==='' || !validYear($tahun)) $errors[]='Tahun tidak valid.';
    foreach (['stok_awal'=>$stok_awal,'mutasi_masuk'=>$mutasi_masuk,'mutasi_keluar'=>$mutasi_keluar,'pasokan'=>$pasokan,'dipakai'=>$dipakai] as $k=>$v){
      if ($v < 0) $errors[] = ucfirst(str_replace('_',' ',$k)).' tidak boleh negatif.';
    }
    if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    // Cegah duplikat periode
    $cek=$conn->prepare("SELECT id FROM stok_gudang WHERE kebun_id=:kid AND bahan_id=:bid AND bulan=:bln AND tahun=:thn");
    $cek->execute([':kid'=>$kebun_id,':bid'=>$bahan_id,':bln'=>$bulan,':thn'=>$tahun]);
    if ($cek->fetch()){ echo json_encode(['success'=>false,'message'=>'Periode ini sudah ada untuk kombinasi kebun+bahan.']); exit; }

    $sql = "INSERT INTO stok_gudang
            (kebun_id,bahan_id,bulan,tahun,stok_awal,mutasi_masuk,mutasi_keluar,pasokan,dipakai,created_at,updated_at)
            VALUES
            (:kid,:bid,:bln,:thn,:sa,:mm,:mk,:ps,:dp,NOW(),NOW())";
    $st=$conn->prepare($sql);
    $st->execute([
      ':kid'=>$kebun_id, ':bid'=>$bahan_id, ':bln'=>$bulan, ':thn'=>$tahun,
      ':sa'=>$stok_awal, ':mm'=>$mutasi_masuk, ':mk'=>$mutasi_keluar, ':ps'=>$pasokan, ':dp'=>$dipakai
    ]);
    echo json_encode(['success'=>true,'message'=>'Data stok berhasil ditambahkan.']); exit;
  }

  // ===== UPDATE
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors=[];
    $kebun_id = i('kebun_id');
    $bahan_id = i('bahan_id');
    $bulan    = s('bulan');
    $tahun    = s('tahun');

    $stok_awal     = f('stok_awal') ?? 0;
    $mutasi_masuk  = f('mutasi_masuk') ?? 0;
    $mutasi_keluar = f('mutasi_keluar') ?? 0;
    $pasokan       = f('pasokan') ?? 0;
    $dipakai       = f('dipakai') ?? 0;

    if (!$kebun_id) $errors[]='Nama kebun wajib dipilih.';
    if (!$bahan_id) $errors[]='Bahan kimia wajib dipilih.';
    if ($bulan==='' || !in_array($bulan, $bulanList, true)) $errors[]='Bulan tidak valid.';
    if ($tahun==='' || !validYear($tahun)) $errors[]='Tahun tidak valid.';
    foreach (['stok_awal'=>$stok_awal,'mutasi_masuk'=>$mutasi_masuk,'mutasi_keluar'=>$mutasi_keluar,'pasokan'=>$pasokan,'dipakai'=>$dipakai] as $k=>$v){
      if ($v < 0) $errors[] = ucfirst(str_replace('_',' ',$k)).' tidak boleh negatif.';
    }
    if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    // Cegah duplikat periode lain
    $cek=$conn->prepare("SELECT id FROM stok_gudang WHERE kebun_id=:kid AND bahan_id=:bid AND bulan=:bln AND tahun=:thn AND id<>:id");
    $cek->execute([':kid'=>$kebun_id,':bid'=>$bahan_id,':bln'=>$bulan,':thn'=>$tahun,':id'=>$id]);
    if ($cek->fetch()){ echo json_encode(['success'=>false,'message'=>'Periode ini sudah dipakai oleh data lain.']); exit; }

    $sql="UPDATE stok_gudang SET
            kebun_id=:kid, bahan_id=:bid, bulan=:bln, tahun=:thn,
            stok_awal=:sa, mutasi_masuk=:mm, mutasi_keluar=:mk, pasokan=:ps, dipakai=:dp,
            updated_at=NOW()
          WHERE id=:id";
    $st=$conn->prepare($sql);
    $st->execute([
      ':kid'=>$kebun_id, ':bid'=>$bahan_id, ':bln'=>$bulan, ':thn'=>$tahun,
      ':sa'=>$stok_awal, ':mm'=>$mutasi_masuk, ':mk'=>$mutasi_keluar, ':ps'=>$pasokan, ':dp'=>$dipakai,
      ':id'=>$id
    ]);
    echo json_encode(['success'=>true,'message'=>'Data stok berhasil diperbarui.']); exit;
  }

  // ===== DELETE
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    $st=$conn->prepare("DELETE FROM stok_gudang WHERE id=:id");
    $st->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data stok berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch (PDOException $e) {
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
