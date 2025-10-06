<?php
// pemupukan_crud.php (FINAL+APL+TAHUN+TTANAM: dukung kebun_kode/kebun_id; mapping by md_kebun; validasi master; tahun input; tahun tanam master)
// kompatibel dengan form dari pemupukan.php yang mengirim: tahun_tanam_id & tahun_tanam
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
function validDate($d){ return $d!=='' && (bool)strtotime($d); }

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

  // helpers
  $unitName = function(PDO $conn, $unitId){
    if ($unitId===null) return null;
    $st=$conn->prepare("SELECT nama_unit FROM units WHERE id=:id");
    $st->execute([':id'=>$unitId]);
    $r=$st->fetch(PDO::FETCH_ASSOC);
    return $r['nama_unit'] ?? null;
  };

  $blokExists = function(PDO $conn, $unitId, $blokKode){
    if (!$unitId || $blokKode==='') return false;
    $st=$conn->prepare("SELECT 1 FROM md_blok WHERE unit_id=:u AND kode=:k LIMIT 1");
    $st->execute([':u'=>$unitId, ':k'=>$blokKode]);
    return (bool)$st->fetchColumn();
  };

  $pupukExists = function(PDO $conn, $nama){
    if ($nama==='') return false;
    try{
      $st=$conn->prepare("SELECT 1 FROM md_pupuk WHERE nama=:n LIMIT 1");
      $st->execute([':n'=>$nama]);
      return (bool)$st->fetchColumn();
    }catch(Throwable $e){ return true; }
  };

  // cek id tahun tanam di master
  $tahunTanamIdValid = function(PDO $conn, $id){
    if (!$id) return false;
    $st=$conn->prepare("SELECT 1 FROM md_tahun_tanam WHERE id=:i");
    $st->execute([':i'=>$id]);
    return (bool)$st->fetchColumn();
  };

  // Lookup kebun dari id / kode (pakai md_kebun)
  $kebunFromEither = function(PDO $conn, $kebun_id_input, $kebun_kode_input){
    $kid = null; $kkod = null;
    if ($kebun_id_input) {
      $st=$conn->prepare("SELECT id, kode FROM md_kebun WHERE id=:i");
      $st->execute([':i'=>$kebun_id_input]);
      if ($r=$st->fetch(PDO::FETCH_ASSOC)) { $kid=(int)$r['id']; $kkod=(string)$r['kode']; return [$kid,$kkod]; }
    }
    $kk = trim((string)$kebun_kode_input);
    if ($kk!=='') {
      $st=$conn->prepare("SELECT id, kode FROM md_kebun WHERE kode=:k LIMIT 1");
      $st->execute([':k'=>$kk]);
      if ($r=$st->fetch(PDO::FETCH_ASSOC)) { $kid=(int)$r['id']; $kkod=(string)$r['kode']; return [$kid,$kkod]; }
    }
    return [null,null];
  };

  // ==== COMMON: deteksi kolom opsional MENABUR ====
  $hasAfdeling = $columnExists($conn,'menabur_pupuk','afdeling');
  $hasDosis    = $columnExists($conn,'menabur_pupuk','dosis');
  $hasTahun    = $columnExists($conn,'menabur_pupuk','tahun');
  // APL bisa bernama 'apl' atau 'aplikator'
  $aplCol = null;
  foreach (['apl','aplikator'] as $cand) { if ($columnExists($conn,'menabur_pupuk',$cand)) { $aplCol = $cand; break; } }

  // Tahun Tanam di menabur_pupuk
  $hasTTId  = $columnExists($conn,'menabur_pupuk','tahun_tanam_id'); // FK ke md_tahun_tanam
  $hasTTVal = $columnExists($conn,'menabur_pupuk','tahun_tanam');    // angka tahun

  // ==== COMMON: deteksi kolom kebun ====
  $hasKidMen   = $columnExists($conn,'menabur_pupuk','kebun_id');
  $hasKkodMen  = $columnExists($conn,'menabur_pupuk','kebun_kode');
  $hasKidAng   = $columnExists($conn,'angkutan_pupuk','kebun_id');
  $hasKkodAng  = $columnExists($conn,'angkutan_pupuk','kebun_kode');

  /* ====== CREATE ====== */
  if ($action==='store' || $action==='create'){
    $errors=[];

    if ($tab==='angkutan'){
      $kebun_id_post  = i('kebun_id');        // optional
      $kebun_kode_post= s('kebun_kode');      // optional
      $gudang_asal   = s('gudang_asal');
      $unit_tujuan_id= i('unit_tujuan_id');
      $tanggal       = s('tanggal');
      $jenis_pupuk   = s('jenis_pupuk');
      $jumlah        = f('jumlah');
      $nomor_do      = s('nomor_do');
      $supir         = s('supir');

      if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.';
      if (!$unit_tujuan_id)  $errors[]='Unit tujuan wajib dipilih.';
      if (!validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($jenis_pupuk!=='' && !$pupukExists($conn,$jenis_pupuk)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      [$kid,$kkod] = $kebunFromEither($conn,$kebun_id_post,$kebun_kode_post);

      $cols = []; $vals = []; $params = [];
      if ($hasKidAng){  $cols[]='kebun_id';   $vals[]=':kid';  $params[':kid']=$kid; }
      if ($hasKkodAng){ $cols[]='kebun_kode'; $vals[]=':kkod'; $params[':kkod']=$kkod; }

      $cols = array_merge($cols, ['gudang_asal','unit_tujuan_id','tanggal','jenis_pupuk','jumlah','nomor_do','supir','created_at','updated_at']);
      $vals = array_merge($vals, [':ga',':uid',':tgl',':jp',':jml',':no',':sp','NOW()','NOW()']);
      $params += [
        ':ga'=>$gudang_asal, ':uid'=>$unit_tujuan_id, ':tgl'=>$tanggal, ':jp'=>$jenis_pupuk,
        ':jml'=>$jumlah??0, ':no'=>$nomor_do, ':sp'=>$supir
      ];

      $sql="INSERT INTO angkutan_pupuk (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$conn->prepare($sql); $st->execute($params);

    } else {
      $kebun_id_post  = i('kebun_id');        // optional
      $kebun_kode_post= s('kebun_kode');      // optional
      $unit_id  = i('unit_id');
      $blok     = s('blok');
      $tanggal  = s('tanggal');
      $jenis    = s('jenis_pupuk');
      $dosis    = f('dosis');
      $jumlah   = f('jumlah');
      $luas     = f('luas');
      $invt     = i('invt_pokok');
      $catatan  = s('catatan');
      $aplPost  = s('apl');                   // APL
      $tahunPost= i('tahun');                 // Tahun (opsional int)

      // Tahun Tanam (dari form — kirim dua nilai)
      $tahunTanamId = i('tahun_tanam_id');    // id master (prioritas jika kolom ada)
      $tahunTanam   = i('tahun_tanam');       // angka tahun (fallback jika kolom angka yang ada)

      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if (!validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($dosis!==null && $dosis<0) $errors[]='Dosis tidak boleh negatif.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';
      if ($unit_id && $blok && !$blokExists($conn,$unit_id,$blok)) $errors[]='Blok tidak ditemukan pada unit terpilih (cek md_blok).';
      if ($jenis!=='' && !$pupukExists($conn,$jenis)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';

      // Tahun input (bukan tahun tanam)
      if ($hasTahun) {
        if ($tahunPost!==null && ($tahunPost<1900 || $tahunPost>2100)) $errors[]='Tahun tidak valid (1900–2100).';
        if ($tahunPost===null && validDate($tanggal)) $tahunPost = (int)date('Y', strtotime($tanggal));
      }

      // Validasi Tahun Tanam ID jika dipakai
      if ($hasTTId && $tahunTanamId!==null && !$tahunTanamIdValid($conn,$tahunTanamId)) {
        $errors[]='Tahun Tanam (ID) tidak ditemukan di master.';
      }

      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      [$kid,$kkod] = $kebunFromEither($conn,$kebun_id_post,$kebun_kode_post);

      $cols = []; $vals = []; $params = [];
      if ($hasKidMen){  $cols[]='kebun_id';   $vals[]=':kid';  $params[':kid']=$kid; }
      if ($hasKkodMen){ $cols[]='kebun_kode'; $vals[]=':kkod'; $params[':kkod']=$kkod; }

      $cols[]='unit_id';   $vals[]=':uid'; $params[':uid']=$unit_id;
      if ($hasAfdeling){ $cols[]='afdeling'; $vals[]=':afd'; $params[':afd']=$unitName($conn,$unit_id)??''; }

      $cols[]='blok';      $vals[]=':blk'; $params[':blk']=$blok;
      $cols[]='tanggal';   $vals[]=':tgl'; $params[':tgl']=$tanggal;

      if ($hasTahun){ $cols[]='tahun'; $vals[]=':thn'; $params[':thn']=$tahunPost; }

      $cols[]='jenis_pupuk'; $vals[]=':jp'; $params[':jp']=$jenis;

      if ($aplCol){ $cols[]=$aplCol; $vals[]=':apl'; $params[':apl']=($aplPost!=='') ? $aplPost : null; }
      if ($hasDosis){ $cols[]='dosis'; $vals[]=':ds'; $params[':ds']=$dosis??null; }

      // ===== Tahun Tanam (adaptif) =====
      if ($hasTTId) { // simpan id master
        $cols[]='tahun_tanam_id'; $vals[]=':ttid'; $params[':ttid']=$tahunTanamId;
      } elseif ($hasTTVal) { // simpan angka tahunnya
        $cols[]='tahun_tanam';    $vals[]=':tt';   $params[':tt']=$tahunTanam;
      }

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
      $kebun_id_post  = i('kebun_id');        // optional
      $kebun_kode_post= s('kebun_kode');      // optional
      $gudang_asal   = s('gudang_asal');
      $unit_tujuan_id= i('unit_tujuan_id');
      $tanggal       = s('tanggal');
      $jenis_pupuk   = s('jenis_pupuk');
      $jumlah        = f('jumlah');
      $nomor_do      = s('nomor_do');
      $supir         = s('supir');

      if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.';
      if (!$unit_tujuan_id)  $errors[]='Unit tujuan wajib dipilih.';
      if (!validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($jenis_pupuk!=='' && !$pupukExists($conn,$jenis_pupuk)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      [$kid,$kkod] = $kebunFromEither($conn,$kebun_id_post,$kebun_kode_post);

      $sql="UPDATE angkutan_pupuk SET ".
           ($hasKidAng  ? "kebun_id=:kid, "   : "").
           ($hasKkodAng ? "kebun_kode=:kkod, ": "").
           "gudang_asal=:ga, unit_tujuan_id=:uid, tanggal=:tgl, jenis_pupuk=:jp, jumlah=:jml, nomor_do=:no, supir=:sp, updated_at=NOW()
            WHERE id=:id";

      $params=[':ga'=>$gudang_asal, ':uid'=>$unit_tujuan_id, ':tgl'=>$tanggal, ':jp'=>$jenis_pupuk,
               ':jml'=>$jumlah??0, ':no'=>$nomor_do, ':sp'=>$supir, ':id'=>$id];
      if ($hasKidAng)  $params[':kid']=$kid;
      if ($hasKkodAng) $params[':kkod']=$kkod;

      $st=$conn->prepare($sql); $st->execute($params);

    } else {
      $kebun_id_post  = i('kebun_id');        // optional
      $kebun_kode_post= s('kebun_kode');      // optional
      $unit_id  = i('unit_id');
      $blok     = s('blok');
      $tanggal  = s('tanggal');
      $jenis    = s('jenis_pupuk');
      $dosis    = f('dosis');
      $jumlah   = f('jumlah');
      $luas     = f('luas');
      $invt     = i('invt_pokok');
      $catatan  = s('catatan');
      $aplPost  = s('apl');                   // APL
      $tahunPost= i('tahun');                 // Tahun input

      // Tahun Tanam (form)
      $tahunTanamId = i('tahun_tanam_id');
      $tahunTanam   = i('tahun_tanam');

      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if (!validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($dosis!==null && $dosis<0) $errors[]='Dosis tidak boleh negatif.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';
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

      $sql="UPDATE menabur_pupuk SET ".
            ($hasKidMen  ? "kebun_id=:kid, "   : "").
            ($hasKkodMen ? "kebun_kode=:kkod, ": "").
            "unit_id=:uid, ".
            ($hasAfdeling?'afdeling=:afd, ':'').
            "blok=:blk, tanggal=:tgl, ".
            ($hasTahun ? 'tahun=:thn, ' : '').
            "jenis_pupuk=:jp, ".
            ($aplCol ? "$aplCol=:apl, " : '').
            ($hasDosis?'dosis=:ds, ':'').
            // ===== set Tahun Tanam adaptif =====
            ($hasTTId  ? 'tahun_tanam_id=:ttid, ' : '').
            ($hasTTVal ? 'tahun_tanam=:tt, '      : '').
            "jumlah=:jml, luas=:luas, invt_pokok=:invt, catatan=:cat, updated_at=NOW()
            WHERE id=:id";

      $params=[
        ':uid'=>$unit_id, ':blk'=>$blok, ':tgl'=>$tanggal,
        ':jp'=>$jenis, ':jml'=>$jumlah??0, ':luas'=>$luas??0, ':invt'=>$invt??0, ':cat'=>$catatan, ':id'=>$id
      ];
      if ($hasAfdeling) $params[':afd']=$unitName($conn,$unit_id)??'';
      if ($hasTahun)    $params[':thn']=$tahunPost;
      if ($aplCol)      $params[':apl']=($aplPost!=='') ? $aplPost : null;
      if ($hasDosis)    $params[':ds']=$dosis??null;
      if ($hasKidMen)   $params[':kid']=$kid;
      if ($hasKkodMen)  $params[':kkod']=$kkod;

      // bind Tahun Tanam sesuai kolom yang ada
      if ($hasTTId)  { $params[':ttid'] = $tahunTanamId; }
      if ($hasTTVal) { $params[':tt']   = $tahunTanam;   }

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
