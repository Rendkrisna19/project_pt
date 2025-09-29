<?php
// pemupukan_crud.php (FINAL: dukung kebun_kode/kebun_id; mapping by md_kebun; validasi master)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { echo json_encode(['success'=>false,'message'=>'Akses ditolak. Silakan login.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); exit; }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Token keamanan tidak valid. Refresh halaman.']); exit; }

require_once '../config/database.php';

$action = $_POST['action'] ?? '';
$tab    = $_POST['tab']    ?? '';
if (!in_array($tab, ['angkutan','menabur'], true)) { echo json_encode(['success'=>false,'message'=>'Tab tidak valid.']); exit; }

function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){ $v=$_POST[$k]??null; if ($v===''||$v===null) return null; return is_numeric($v)? (float)$v : null; }
function i($k){ $v=$_POST[$k]??null; if ($v===''||$v===null) return null; return ctype_digit((string)$v) ? (int)$v : null; }
function validDate($d){ return (bool)strtotime($d); }

try{
  $db   = new Database();
  $conn = $db->getConnection();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // cache columns
  $cacheCols = [];
  $columnExists = function(PDO $conn, $table, $col) use (&$cacheCols){
    if (!isset($cacheCols[$table])) {
      $st=$conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
      $st->execute([':t'=>$table]); $cacheCols[$table]=array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME');
    }
    return in_array($col, $cacheCols[$table] ?? [], true);
  };

  // helpers
  $unitName = function(PDO $conn, $unitId){
    if ($unitId===null) return null;
    $st=$conn->prepare("SELECT nama_unit FROM units WHERE id=:id"); $st->execute([':id'=>$unitId]);
    $r=$st->fetch(PDO::FETCH_ASSOC); return $r['nama_unit'] ?? null;
  };
  $blokExists = function(PDO $conn, $unitId, $blokKode){
    if (!$unitId || $blokKode==='') return false;
    $st=$conn->prepare("SELECT 1 FROM md_blok WHERE unit_id=:u AND kode=:k LIMIT 1");
    $st->execute([':u'=>$unitId, ':k'=>$blokKode]);
    return (bool)$st->fetchColumn();
  };
  $pupukExists = function(PDO $conn, $nama){
    if ($nama==='') return false;
    $st=$conn->prepare("SELECT 1 FROM md_pupuk WHERE nama=:n LIMIT 1");
    $st->execute([':n'=>$nama]);
    return (bool)$st->fetchColumn();
  };
  $kebunById = function(PDO $conn, $kid){
    if (!$kid) return [null,null];
    $st=$conn->prepare("SELECT id, kode FROM md_kebun WHERE id=:i");
    $st->execute([':i'=>$kid]);
    $r=$st->fetch(PDO::FETCH_ASSOC);
    return $r ? [(int)$r['id'], (string)$r['kode']] : [null,null];
  };

  /* ====== CREATE ====== */
  if ($action==='store' || $action==='create'){
    $errors=[];

    if ($tab==='angkutan'){
      $kebun_id      = i('kebun_id'); // optional
      $gudang_asal   = s('gudang_asal');
      $unit_tujuan_id= i('unit_tujuan_id');
      $tanggal       = s('tanggal');
      $jenis_pupuk   = s('jenis_pupuk');
      $jumlah        = f('jumlah');
      $nomor_do      = s('nomor_do');
      $supir         = s('supir');

      if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.';
      if (!$unit_tujuan_id)  $errors[]='Unit tujuan wajib dipilih.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($jenis_pupuk!=='' && !$pupukExists($conn,$jenis_pupuk)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      // mapping kebun
      [$kid,$kkod] = $kebunById($conn,$kebun_id);

      $hasKid  = $columnExists($conn,'angkutan_pupuk','kebun_id');
      $hasKkod = $columnExists($conn,'angkutan_pupuk','kebun_kode');

      $cols = [];
      $vals = [];
      $params = [];

      if ($hasKid){  $cols[]='kebun_id';   $vals[]=':kid';  $params[':kid']=$kid; }
      if ($hasKkod){ $cols[]='kebun_kode'; $vals[]=':kkod'; $params[':kkod']=$kkod; }

      $cols = array_merge($cols, ['gudang_asal','unit_tujuan_id','tanggal','jenis_pupuk','jumlah','nomor_do','supir','created_at','updated_at']);
      $vals = array_merge($vals, [':ga',':uid',':tgl',':jp',':jml',':no',':sp','NOW()','NOW()']);

      $params += [
        ':ga'=>$gudang_asal, ':uid'=>$unit_tujuan_id, ':tgl'=>$tanggal, ':jp'=>$jenis_pupuk,
        ':jml'=>$jumlah??0, ':no'=>$nomor_do, ':sp'=>$supir
      ];

      $sql="INSERT INTO angkutan_pupuk (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$conn->prepare($sql); $st->execute($params);

    } else {
      $kebun_id = i('kebun_id'); // optional
      $unit_id  = i('unit_id');
      $blok     = s('blok');
      $tanggal  = s('tanggal');
      $jenis    = s('jenis_pupuk');
      $dosis    = f('dosis');
      $jumlah   = f('jumlah');
      $luas     = f('luas');
      $invt     = i('invt_pokok');
      $catatan  = s('catatan');

      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($dosis!==null && $dosis<0) $errors[]='Dosis tidak boleh negatif.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';
      if ($unit_id && $blok && !$blokExists($conn,$unit_id,$blok)) $errors[]='Blok tidak ditemukan pada unit terpilih (cek md_blok).';
      if ($jenis!=='' && !$pupukExists($conn,$jenis)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      [$kid,$kkod] = $kebunById($conn,$kebun_id);

      $hasAfdeling = $columnExists($conn,'menabur_pupuk','afdeling');
      $hasDosis    = $columnExists($conn,'menabur_pupuk','dosis');
      $hasKid      = $columnExists($conn,'menabur_pupuk','kebun_id');
      $hasKkod     = $columnExists($conn,'menabur_pupuk','kebun_kode');

      $cols = [];
      $vals = [];
      $params = [];

      if ($hasKid){  $cols[]='kebun_id';   $vals[]=':kid';  $params[':kid']=$kid; }
      if ($hasKkod){ $cols[]='kebun_kode'; $vals[]=':kkod'; $params[':kkod']=$kkod; }

      $cols[]='unit_id';   $vals[]=':uid'; $params[':uid']=$unit_id;
      if ($hasAfdeling){ $cols[]='afdeling'; $vals[]=':afd'; $params[':afd']=$unitName($conn,$unit_id)??''; }
      $cols[]='blok';      $vals[]=':blk'; $params[':blk']=$blok;
      $cols[]='tanggal';   $vals[]=':tgl'; $params[':tgl']=$tanggal;
      $cols[]='jenis_pupuk'; $vals[]=':jp'; $params[':jp']=$jenis;
      if ($hasDosis){ $cols[]='dosis'; $vals[]=':ds'; $params[':ds']=$dosis??null; }
      $cols = array_merge($cols, ['jumlah','luas','invt_pokok','catatan','created_at','updated_at']);
      $vals = array_merge($vals, [':jml',':luas',':invt',':cat','NOW()','NOW()']);
      $params += [':jml'=>$jumlah??0, ':luas'=>$luas??0, ':invt'=>$invt??0, ':cat'=>$catatan];

      $sql="INSERT INTO menabur_pupuk (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$conn->prepare($sql); $st->execute($params);
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  /* ====== UPDATE ====== */
  if ($action==='update'){
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors=[];
    if ($tab==='angkutan'){
      $kebun_id      = i('kebun_id');
      $gudang_asal   = s('gudang_asal');
      $unit_tujuan_id= i('unit_tujuan_id');
      $tanggal       = s('tanggal');
      $jenis_pupuk   = s('jenis_pupuk');
      $jumlah        = f('jumlah');
      $nomor_do      = s('nomor_do');
      $supir         = s('supir');

      if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.';
      if (!$unit_tujuan_id)  $errors[]='Unit tujuan wajib dipilih.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($jenis_pupuk!=='' && !$pupukExists($conn,$jenis_pupuk)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      [$kid,$kkod] = $kebunById($conn,$kebun_id);
      $hasKid  = $columnExists($conn,'angkutan_pupuk','kebun_id');
      $hasKkod = $columnExists($conn,'angkutan_pupuk','kebun_kode');

      $sql="UPDATE angkutan_pupuk SET ".
           ($hasKid  ? "kebun_id=:kid, "   : "").
           ($hasKkod ? "kebun_kode=:kkod, ": "").
           "gudang_asal=:ga, unit_tujuan_id=:uid, tanggal=:tgl, jenis_pupuk=:jp, jumlah=:jml, nomor_do=:no, supir=:sp, updated_at=NOW()
            WHERE id=:id";

      $params=[':ga'=>$gudang_asal, ':uid'=>$unit_tujuan_id, ':tgl'=>$tanggal, ':jp'=>$jenis_pupuk,
               ':jml'=>$jumlah??0, ':no'=>$nomor_do, ':sp'=>$supir, ':id'=>$id];
      if ($hasKid)  $params[':kid']=$kid;
      if ($hasKkod) $params[':kkod']=$kkod;

      $st=$conn->prepare($sql); $st->execute($params);

    } else {
      $kebun_id = i('kebun_id');
      $unit_id  = i('unit_id');
      $blok     = s('blok');
      $tanggal  = s('tanggal');
      $jenis    = s('jenis_pupuk');
      $dosis    = f('dosis');
      $jumlah   = f('jumlah');
      $luas     = f('luas');
      $invt     = i('invt_pokok');
      $catatan  = s('catatan');

      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($dosis!==null && $dosis<0) $errors[]='Dosis tidak boleh negatif.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';
      if ($unit_id && $blok && !$blokExists($conn,$unit_id,$blok)) $errors[]='Blok tidak ditemukan pada unit terpilih (cek md_blok).';
      if ($jenis!=='' && !$pupukExists($conn,$jenis)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      [$kid,$kkod] = $kebunById($conn,$kebun_id);
      $hasAfdeling = $columnExists($conn,'menabur_pupuk','afdeling');
      $hasDosis    = $columnExists($conn,'menabur_pupuk','dosis');
      $hasKid      = $columnExists($conn,'menabur_pupuk','kebun_id');
      $hasKkod     = $columnExists($conn,'menabur_pupuk','kebun_kode');

      $sql="UPDATE menabur_pupuk SET ".
            ($hasKid  ? "kebun_id=:kid, "   : "").
            ($hasKkod ? "kebun_kode=:kkod, ": "").
            "unit_id=:uid, ".
            ($hasAfdeling?'afdeling=:afd, ':'').
            "blok=:blk, tanggal=:tgl, jenis_pupuk=:jp, ".
            ($hasDosis?'dosis=:ds, ':'').
            "jumlah=:jml, luas=:luas, invt_pokok=:invt, catatan=:cat, updated_at=NOW()
            WHERE id=:id";

      $params=[':uid'=>$unit_id, ':blk'=>$blok, ':tgl'=>$tanggal, ':jp'=>$jenis,
               ':jml'=>$jumlah??0, ':luas'=>$luas??0, ':invt'=>$invt??0, ':cat'=>$catatan, ':id'=>$id];
      if ($hasAfdeling) $params[':afd']=$unitName($conn,$unit_id)??'';
      if ($hasDosis)    $params[':ds']=$dosis??null;
      if ($hasKid)      $params[':kid']=$kid;
      if ($hasKkod)     $params[':kkod']=$kkod;

      $st=$conn->prepare($sql); $st->execute($params);
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  /* ====== DELETE ====== */
  if ($action==='delete'){
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    $st=$conn->prepare($tab==='angkutan' ? "DELETE FROM angkutan_pupuk WHERE id=:id" : "DELETE FROM menabur_pupuk WHERE id=:id");
    $st->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
}catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
