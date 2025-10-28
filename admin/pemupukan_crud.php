<?php
// pemupukan_crud.php (FINAL)
// - [FIXED] Mengisi helper function
// - [FIXED] Mengisi kolom teks 'gudang_asal'
// - [FIXED] Memperbaiki nama tabel di 'UPDATE' -> 'menabur_pupuk'

session_start();
header('Content-Type: application/json; charset=utf-8'); // Set charset utf-8

/* ===== SAFETY ===== */
ini_set('display_errors','0');
ini_set('log_errors','1');
error_reporting(E_ALL);
set_error_handler(function($sev,$msg,$file,$line){ throw new ErrorException($msg, 0, $sev, $file, $line); });
set_exception_handler(function($e){
  http_response_code(500);
  $errMsg = $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
  error_log("Pemupukan CRUD Error: " . $errMsg); 
  
  // Tampilkan pesan error spesifik jika ini error SQL
  if ($e instanceof PDOException) {
     echo json_encode(['success'=>false,'message'=>'Kesalahan Database: ' . $e->getMessage()]);
  } else {
     echo json_encode(['success'=>false,'message'=>'Terjadi kesalahan pada server.']);
  }
  exit;
});

/* ===== AUTH & CSRF ===== */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(['success'=>false,'message'=>'Silakan login.']); exit;
}
// --- Role Check ---
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');
// -------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
  echo json_encode(['success'=>false,'message'=>'Token keamanan tidak valid. Refresh halaman.']); exit;
}
// --- Staf tidak bisa Create, Update, Delete ---
$action = $_POST['action'] ?? '';
if ($action !== '' && $isStaf) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.']); exit;
}
// ---------------------------------------------

require_once '../config/database.php';

$tab = $_POST['tab'] ?? '';
if (!in_array($tab, ['angkutan','menabur'], true)) { echo json_encode(['success'=>false,'message'=>'Tab tidak valid.']); exit; }

function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){ $v=$_POST[$k]??null; if ($v===''||$v===null) return null; return is_numeric($v)? (float)$v : null; }
function i($k){ $v=$_POST[$k]??null; if ($v===''||$v===null) return null; return ctype_digit((string)$v) ? (int)$v : null; }
function validDate($d){ return $d!=='' && ($ts = strtotime($d)) !== false && date('Y-m-d', $ts) === $d; } // Validasi format YYYY-MM-DD

try{
  $db   = new Database();
  $conn = $db->getConnection();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // cache columns
  $cacheCols = [];
  $columnExists = function(PDO $conn, $table, $col) use (&$cacheCols){
    $key = $table;
    if (!isset($cacheCols[$key])) {
      $st=$conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
      $st->execute([':t'=>$table]);
      $cacheCols[$key]=array_flip(array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME')));
    }
    return isset($cacheCols[$key][strtolower($col)]);
  };

  // helpers diisi
  $unitName = function(PDO $conn, $unitId){
    if(!$unitId) return null;
    $st = $conn->prepare("SELECT nama_unit FROM units WHERE id = ?");
    $st->execute([$unitId]);
    return $st->fetchColumn() ?: null;
  };
  $blokExists = function(PDO $conn, $unitId, $blokKode){
    if(!$unitId || $blokKode==='') return false;
    // Validasi berdasarkan 'kode' (teks) di 'md_blok'
    $st = $conn->prepare("SELECT 1 FROM md_blok WHERE unit_id = :uid AND kode = :kode LIMIT 1");
    $st->execute([':uid' => $unitId, ':kode' => $blokKode]);
    return (bool)$st->fetchColumn();
  };
  $pupukExists = function(PDO $conn, $nama){
    if($nama==='') return false;
    // Validasi berdasarkan 'nama' (teks) di 'md_pupuk'
    $st = $conn->prepare("SELECT 1 FROM md_pupuk WHERE nama = ? LIMIT 1");
    $st->execute([$nama]);
    return (bool)$st->fetchColumn();
  };
  $tahunTanamIdValid = function(PDO $conn, $id){
    if(!$id) return false;
    $st = $conn->prepare("SELECT 1 FROM md_tahun_tanam WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    return (bool)$st->fetchColumn();
  };
  $kebunFromEither = function(PDO $conn, $kebun_id_input, $kebun_kode_input){
    $kid = $kebun_id_input;
    $kkod = $kebun_kode_input;
    if ($kid) { // Jika ID ada
      if (empty($kkod)) { // Dan kode kosong, cari kodenya
        $st = $conn->prepare("SELECT kode FROM md_kebun WHERE id = ?"); $st->execute([$kid]); $kkod = $st->fetchColumn() ?: null;
      }
    } elseif ($kkod) { // Jika ID tidak ada, tapi kode ada
      $st = $conn->prepare("SELECT id FROM md_kebun WHERE kode = ?"); $st->execute([$kkod]); $kid = $st->fetchColumn() ?: null;
    }
    return [$kid, $kkod]; // Kembalikan [id, kode]
  };
  
  $gudangNamaFromId = function(PDO $conn, $id){
    if(!$id) return null;
    $st = $conn->prepare("SELECT nama FROM md_asal_gudang WHERE id = ?");
    $st->execute([$id]);
    return $st->fetchColumn() ?: null;
  };


  // ==== MENABUR: deteksi kolom opsional ====
  $hasAfdeling  = $columnExists($conn,'menabur_pupuk','afdeling');
  $hasDosis     = $columnExists($conn,'menabur_pupuk','dosis');
  $hasTahun     = $columnExists($conn,'menabur_pupuk','tahun');
  $hasNoAU58Men = $columnExists($conn,'menabur_pupuk','no_au58') || $columnExists($conn,'menabur_pupuk','no_au_58');
  $noAU58ColMen = $columnExists($conn,'menabur_pupuk','no_au58') ? 'no_au58' : ($columnExists($conn,'menabur_pupuk','no_au_58') ? 'no_au_58' : null);
  $hasTTId  = $columnExists($conn,'menabur_pupuk','tahun_tanam_id');
  $hasTTVal = $columnExists($conn,'menabur_pupuk','tahun_tanam'); 
  $hasRayonIdM = $columnExists($conn, 'menabur_pupuk', 'rayon_id');
  $hasAplIdM = $columnExists($conn, 'menabur_pupuk', 'apl_id');
  $hasKetIdM = $columnExists($conn, 'menabur_pupuk', 'keterangan_id');

  // ==== KEBUN flags ====
  $hasKidMen   = $columnExists($conn,'menabur_pupuk','kebun_id');
  $hasKkodMen  = $columnExists($conn,'menabur_pupuk','kebun_kode');
  $hasKidAng   = $columnExists($conn,'angkutan_pupuk','kebun_id');
  $hasKkodAng  = $columnExists($conn,'angkutan_pupuk','kebun_kode');

  // ==== ANGKUTAN: deteksi kolom opsional ====
  $hasNoSPBAng    = $columnExists($conn,'angkutan_pupuk','no_spb');
  $hasNomorDOAng  = $columnExists($conn,'angkutan_pupuk','nomor_do');
  $hasSupirAng    = $columnExists($conn,'angkutan_pupuk','supir'); 
  $hasRayonIdA = $columnExists($conn, 'angkutan_pupuk', 'rayon_id');
  $hasGudangIdA = $columnExists($conn, 'angkutan_pupuk', 'gudang_asal_id'); 
  $hasGudangTextA = $columnExists($conn, 'angkutan_pupuk', 'gudang_asal'); 
  $hasKetIdA = $columnExists($conn, 'angkutan_pupuk', 'keterangan_id');

  /* ====== CREATE / STORE ====== */
  if ($action==='store' || $action==='create'){
    $errors=[];

    if ($tab==='angkutan'){
      $kebun_id_post   = i('kebun_id');
      $kebun_kode_post = s('kebun_kode');
      $unit_tujuan_id  = i('unit_tujuan_id');
      $tanggal         = s('tanggal');
      $jenis_pupuk     = s('jenis_pupuk');
      $jumlah          = f('jumlah');
      $no_spb          = s('no_spb');
      $nomor_do        = s('nomor_do');
      $supir           = s('supir');
      $rayon_id       = i('rayon_id');
      $gudang_asal_id = i('gudang_asal_id');
      $keterangan_id  = i('keterangan_id');

      // Validasi Angkutan
      if (!$gudang_asal_id) $errors[]='Gudang asal wajib dipilih.'; 
      if (!$unit_tujuan_id) $errors[]='Unit tujuan wajib dipilih.';
      if (!validDate($tanggal)) $errors[]='Tanggal tidak valid (format YYYY-MM-DD).';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($jenis_pupuk!=='' && !$pupukExists($conn,$jenis_pupuk)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';

      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }
      
      $gudang_asal_text = $gudangNamaFromId($conn, $gudang_asal_id);
      [$kid,$kkod] = $kebunFromEither($conn,$kebun_id_post,$kebun_kode_post);

      $cols = []; $vals = []; $params = [];
      
      $cols = ['unit_tujuan_id','tanggal','jenis_pupuk','jumlah'];
      $vals = [':uid',':tgl',':jp',':jml'];
      $params = [
          ':uid'=>$unit_tujuan_id, 
          ':tgl'=>$tanggal, 
          ':jp'=>$jenis_pupuk, 
          ':jml'=>$jumlah??0
      ];
      if ($hasKidAng){  $cols[]='kebun_id';   $vals[]=':kid';  $params[':kid']=$kid; }
      if ($hasKkodAng){ $cols[]='kebun_kode'; $vals[]=':kkod'; $params[':kkod']=$kkod; }
      if ($hasRayonIdA){  $cols[]='rayon_id';       $vals[]=':rid';  $params[':rid']=$rayon_id; }
      if ($hasGudangIdA){ $cols[]='gudang_asal_id'; $vals[]=':gid';  $params[':gid']=$gudang_asal_id; }
      if ($hasKetIdA){    $cols[]='keterangan_id';  $vals[]=':ketid';$params[':ketid']=$keterangan_id; }
      if ($hasGudangTextA){ $cols[]='gudang_asal'; $vals[]=':gtxt'; $params[':gtxt']=$gudang_asal_text; }
      if ($hasNoSPBAng){ $cols[]='no_spb'; $vals[]=':spb'; $params[':spb']=$no_spb!==''?$no_spb:null; }
      if ($hasNomorDOAng){ $cols[]='nomor_do'; $vals[]=':no'; $params[':no']=$nomor_do!==''?$nomor_do:null; }
      if ($hasSupirAng){   $cols[]='supir';    $vals[]=':sp'; $params[':sp']=$supir!==''?$supir:null; }

      $cols[]='created_at'; $vals[]='NOW()';
      $cols[]='updated_at'; $vals[]='NOW()';

      $sql="INSERT INTO angkutan_pupuk (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$conn->prepare($sql); $st->execute($params); 

    } else { // Menabur
      $kebun_id_post   = i('kebun_id');
      $kebun_kode_post = s('kebun_kode');
      $unit_id   = i('unit_id');
      $blok      = s('blok');
      $tanggal   = s('tanggal');
      $jenis     = s('jenis_pupuk');
      $dosis     = f('dosis');
      $jumlah    = f('jumlah');
      $luas      = f('luas');
      $invt      = i('invt_pokok');
      $tahunPost = i('tahun');
      $no_au58   = s('no_au_58');
      $rayon_id      = i('rayon_id');
      $apl_id        = i('apl_id');
      $keterangan_id = i('keterangan_id');
      $tahunTanamId = i('tahun_tanam_id');
      $tahunTanam   = i('tahun_tanam'); 

      // Validasi Menabur
      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if (!validDate($tanggal)) $errors[]='Tanggal tidak valid (format YYYY-MM-DD).';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($unit_id && $blok && !$blokExists($conn,$unit_id,$blok)) $errors[]='Blok tidak ditemukan pada unit terpilih (cek md_blok).';
      if ($jenis!=='' && !$pupukExists($conn,$jenis)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($hasTahun) {
        if ($tahunPost===null && validDate($tanggal)) $tahunPost = (int)date('Y', strtotime($tanggal));
        if ($tahunPost!==null && ($tahunPost<1900 || $tahunPost>2100)) $errors[]='Tahun tidak valid (1900–2100).';
      }
      if ($hasTTId && $tahunTanamId!==null && !$tahunTanamIdValid($conn,$tahunTanamId)) {
        $errors[]='Tahun Tanam (ID) tidak ditemukan di master.';
      }

      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      [$kid,$kkod] = $kebunFromEither($conn,$kebun_id_post,$kebun_kode_post);

      $cols = []; $vals = []; $params = [];
      $cols = ['unit_id', 'blok', 'tanggal', 'jenis_pupuk', 'jumlah', 'luas', 'invt_pokok'];
      $vals = [':uid', ':blk', ':tgl', ':jp', ':jml', ':luas', ':invt'];
      $params = [
          ':uid'=>$unit_id,
          ':blk'=>$blok,
          ':tgl'=>$tanggal,
          ':jp'=>$jenis,
          ':jml'=>$jumlah??0,
          ':luas'=>$luas??0,
          ':invt'=>$invt??0
      ];
      if ($hasKidMen){  $cols[]='kebun_id';   $vals[]=':kid';  $params[':kid']=$kid; }
      if ($hasKkodMen){ $cols[]='kebun_kode'; $vals[]=':kkod'; $params[':kkod']=$kkod; }
      if ($hasAfdeling){ $cols[]='afdeling'; $vals[]=':afd'; $params[':afd']=$unitName($conn,$unit_id)??''; }
      if ($hasTahun){ $cols[]='tahun'; $vals[]=':thn'; $params[':thn']=$tahunPost; }
      if ($hasDosis){ $cols[]='dosis'; $vals[]=':ds'; $params[':ds']=$dosis??null; }
      if ($hasTTId) { $cols[]='tahun_tanam_id'; $vals[]=':ttid'; $params[':ttid']=$tahunTanamId; }
      elseif ($hasTTVal && $tahunTanam !== null) { $cols[]='tahun_tanam'; $vals[]=':tt'; $params[':tt']=$tahunTanam; }
      if ($hasRayonIdM){ $cols[]='rayon_id';      $vals[]=':rid';  $params[':rid']=$rayon_id; }
      if ($hasAplIdM){   $cols[]='apl_id';        $vals[]=':aid';  $params[':aid']=$apl_id; }
      if ($hasKetIdM){   $cols[]='keterangan_id'; $vals[]=':ketid';$params[':ketid']=$keterangan_id; }
      if ($hasNoAU58Men){ $cols[]=$noAU58ColMen; $vals[]=':au'; $params[':au']=$no_au58!==''?$no_au58:null; }
      
      $cols[]='created_at'; $vals[]='NOW()';
      $cols[]='updated_at'; $vals[]='NOW()';

      $sql="INSERT INTO menabur_pupuk (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$conn->prepare($sql); $st->execute($params); 
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  /* ====== UPDATE ====== */
  if ($action==='update'){
    $id = i('id');
    if ($id===null || $id <= 0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors=[];
    if ($tab==='angkutan'){
      $kebun_id_post   = i('kebun_id');
      $kebun_kode_post = s('kebun_kode');
      $unit_tujuan_id  = i('unit_tujuan_id');
      $tanggal         = s('tanggal');
      $jenis_pupuk     = s('jenis_pupuk');
      $jumlah          = f('jumlah');
      $no_spb          = s('no_spb');
      $nomor_do        = s('nomor_do');
      $supir           = s('supir');
      $rayon_id       = i('rayon_id');
      $gudang_asal_id = i('gudang_asal_id');
      $keterangan_id  = i('keterangan_id');

      // Validasi Angkutan
      if (!$gudang_asal_id) $errors[]='Gudang asal wajib dipilih.';
      if (!$unit_tujuan_id) $errors[]='Unit tujuan wajib dipilih.';
      if (!validDate($tanggal)) $errors[]='Tanggal tidak valid (format YYYY-MM-DD).';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($jenis_pupuk!=='' && !$pupukExists($conn,$jenis_pupuk)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';

      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }
      
      $gudang_asal_text = $gudangNamaFromId($conn, $gudang_asal_id);
      [$kid,$kkod] = $kebunFromEither($conn,$kebun_id_post,$kebun_kode_post);

      $set = [];
      $params = [
        ':uid'=>$unit_tujuan_id, ':tgl'=>$tanggal, ':jp'=>$jenis_pupuk,
        ':jml'=>$jumlah??0, ':id'=>$id
      ];

      if ($hasKidAng)   { $set[]='kebun_id=:kid';   $params[':kid']=$kid; }
      if ($hasKkodAng)  { $set[]='kebun_kode=:kkod';  $params[':kkod']=$kkod; }
      if ($hasRayonIdA)   { $set[]='rayon_id=:rid';       $params[':rid']=$rayon_id; }
      if ($hasGudangIdA)  { $set[]='gudang_asal_id=:gid'; $params[':gid']=$gudang_asal_id; } 
      if ($hasKetIdA)     { $set[]='keterangan_id=:ketid';$params[':ketid']=$keterangan_id; }
      if ($hasGudangTextA) { $set[]='gudang_asal=:gtxt'; $params[':gtxt']=$gudang_asal_text; }
      if ($hasNoSPBAng){ $set[]='no_spb=:spb'; $params[':spb']=$no_spb!==''?$no_spb:null; }

      $set[]='unit_tujuan_id=:uid';
      $set[]='tanggal=:tgl';
      $set[]='jenis_pupuk=:jp';
      $set[]='jumlah=:jml';

      if ($hasNomorDOAng){ $set[]='nomor_do=:no'; $params[':no']=$nomor_do!==''?$nomor_do:null; }
      if ($hasSupirAng){   $set[]='supir=:sp';    $params[':sp']=$supir!==''?$supir:null; }

      $set[]='updated_at=NOW()';

      $sql="UPDATE angkutan_pupuk SET ".implode(', ', $set)." WHERE id=:id";
      $st=$conn->prepare($sql); $st->execute($params);

    } else { // Menabur
      $kebun_id_post   = i('kebun_id');
      $kebun_kode_post = s('kebun_kode');
      $unit_id   = i('unit_id');
      $blok      = s('blok');
      $tanggal   = s('tanggal');
      $jenis     = s('jenis_pupuk');
      $dosis     = f('dosis');
      $jumlah    = f('jumlah');
      $luas      = f('luas');
      $invt      = i('invt_pokok');
      $tahunPost = i('tahun');
      $no_au58   = s('no_au_58');
      $rayon_id      = i('rayon_id');
      $apl_id        = i('apl_id');
      $keterangan_id = i('keterangan_id');
      $tahunTanamId = i('tahun_tanam_id');
      $tahunTanam   = i('tahun_tanam');

      // Validasi Menabur
      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if (!validDate($tanggal)) $errors[]='Tanggal tidak valid (format YYYY-MM-DD).';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($unit_id && $blok && !$blokExists($conn,$unit_id,$blok)) $errors[]='Blok tidak ditemukan pada unit terpilih (cek md_blok).';
      if ($jenis!=='' && !$pupukExists($conn,$jenis)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($hasTahun) {
          if ($tahunPost === null && validDate($tanggal)) $tahunPost = (int)date('Y', strtotime($tanggal));
          if ($tahunPost !== null && ($tahunPost < 1900 || $tahunPost > 2100)) $errors[] = 'Tahun tidak valid (1900–2100).';
      }
      if ($hasTTId && $tahunTanamId !== null && !$tahunTanamIdValid($conn, $tahunTanamId)) {
          $errors[] = 'Tahun Tanam (ID) tidak ditemukan di master.';
      }


      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      [$kid,$kkod] = $kebunFromEither($conn,$kebun_id_post,$kebun_kode_post);

      $set=[];
      $params=[':uid'=>$unit_id, ':blk'=>$blok, ':tgl'=>$tanggal, ':jp'=>$jenis,
              ':jml'=>$jumlah??0, ':luas'=>$luas??0, ':invt'=>$invt??0, ':id'=>$id];

      if ($hasKidMen){  $set[]='kebun_id=:kid';   $params[':kid']=$kid; }
      if ($hasKkodMen){ $set[]='kebun_kode=:kkod';  $params[':kkod']=$kkod; }
      $set[]='unit_id=:uid';
      if ($hasAfdeling){ $set[]='afdeling=:afd'; $params[':afd']=$unitName($conn,$unit_id)??''; }
      $set[]='blok=:blk';
      $set[]='tanggal=:tgl';
      if ($hasTahun){ $set[]='tahun=:thn'; $params[':thn']=$tahunPost; }
      $set[]='jenis_pupuk=:jp';
      if ($hasDosis){ $set[]='dosis=:ds'; $params[':ds']=$dosis??null; }
      if ($hasTTId){  $set[]='tahun_tanam_id=:ttid'; $params[':ttid']=$tahunTanamId; }
      if ($hasTTVal){ $set[]='tahun_tanam=:tt';     $params[':tt']=$tahunTanam; } 
      if ($hasRayonIdM){ $set[]='rayon_id=:rid';       $params[':rid']=$rayon_id; }
      if ($hasAplIdM){   $set[]='apl_id=:aid';         $params[':aid']=$apl_id; }
      if ($hasKetIdM){   $set[]='keterangan_id=:ketid';$params[':ketid']=$keterangan_id; }
      if ($hasNoAU58Men){ $set[]="$noAU58ColMen = :au"; $params[':au']=$no_au58!==''?$no_au58:null; }

      $set[]='jumlah=:jml';
      $set[]='luas=:luas';
      $set[]='invt_pokok=:invt';
      $set[]='updated_at=NOW()';

      // [FIX] Ini adalah baris yang salah sebelumnya. Sekarang sudah benar.
      $sql="UPDATE menabur_pupuk SET ".implode(', ', $set)." WHERE id=:id";
      $st=$conn->prepare($sql); $st->execute($params);
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  /* ====== DELETE ====== */
  if ($action==='delete'){
    $id = i('id');
    if ($id === null || $id <= 0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    try{
      $st=$conn->prepare($tab==='angkutan' ? "DELETE FROM angkutan_pupuk WHERE id=:id" : "DELETE FROM menabur_pupuk WHERE id=:id");
      $st->execute([':id'=>$id]);
      echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
    }catch(PDOException $e){
      if ($e->getCode()==='23000') { // Foreign key constraint
        echo json_encode(['success'=>false,'message'=>'Tidak bisa menghapus: data ini mungkin terkait dengan data lain.']); exit;
      }
      throw $e; // Re-throw other errors
    }
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);

} catch(PDOException $e){
    error_log("Pemupukan CRUD PDO Error: " . $e->getMessage()); // Log detail error
    echo json_encode([
        'success'=>false,
        'message'=>'Kesalahan Database: ' . $e->getMessage() 
    ]);

} catch(Throwable $e){ // Catch other general errors
    error_log("Pemupukan CRUD General Error: " . $e->getMessage()); // Log detail error
    echo json_encode([
        'success'=>false,
        'message'=>'Kesalahan Server: ' . $e->getMessage()
    ]);
}