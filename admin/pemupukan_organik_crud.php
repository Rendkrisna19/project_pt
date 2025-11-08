<?php
// admin/pemupukan_organik_crud.php — MODIFIKASI: +rayon_id, +asal_gudang_id, +keterangan_id
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { echo json_encode(['success'=>false,'message'=>'Akses ditolak. Silakan login.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); exit; }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
  echo json_encode(['success'=>false,'message'=>'Token keamanan tidak valid. Refresh halaman.']); exit;
}

require_once '../config/database.php';

$action = $_POST['action'] ?? '';
$tab    = $_POST['tab']    ?? '';
if (!in_array($tab, ['angkutan','menabur'], true)) { echo json_encode(['success'=>false,'message'=>'Tab tidak valid.']); exit; }

// helpers
function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){ $v = $_POST[$k] ?? null; if ($v===''||$v===null) return null; return is_numeric($v) ? (float)$v : null; }
function i($k){ $v = $_POST[$k] ?? null; if ($v===''||$v===null) return null; return ctype_digit((string)$v) ? (int)$v : null; }
function validDate($d){ return (bool)strtotime($d); }

try{
  $db   = new Database();
  $conn = $db->getConnection();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // cache kolom
  $colsCache = [];
  $hasCol = function(string $table, string $col) use (&$colsCache, $conn){
    if (!isset($colsCache[$table])) {
      $st=$conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t'=>$table]);
      $colsCache[$table]=array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME');
    }
    return in_array($col, $colsCache[$table], true);
  };

  // validasi master
  $blokExists = function(int $unitId, string $blokKode) use ($conn){
    if ($unitId<=0 || $blokKode==='') return false;
    $st=$conn->prepare("SELECT 1 FROM md_blok WHERE unit_id=:u AND kode=:k LIMIT 1");
    $st->execute([':u'=>$unitId, ':k'=>$blokKode]);
    return (bool)$st->fetchColumn();
  };
  $pupukExists = function(string $namaPupuk) use ($conn){
    if ($namaPupuk==='') return false;
    $st=$conn->prepare("SELECT 1 FROM md_pupuk WHERE nama=:n LIMIT 1");
    $st->execute([':n'=>$namaPupuk]);
    return (bool)$st->fetchColumn();
  };
  $kebunExists = function(?int $kebunId) use ($conn){
    if ($kebunId===null || $kebunId<=0) return false;
    $st=$conn->prepare("SELECT 1 FROM md_kebun WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$kebunId]);
    return (bool)$st->fetchColumn();
  };
  
  // BARU: Validasi Master Angkutan
  $rayonExists = function(?int $id) use ($conn){
    if ($id===null || $id<=0) return false;
    $st=$conn->prepare("SELECT 1 FROM md_rayon WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$id]);
    return (bool)$st->fetchColumn();
  };
  $gudangExists = function(?int $id) use ($conn){
    if ($id===null || $id<=0) return false;
    $st=$conn->prepare("SELECT 1 FROM md_asal_gudang WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$id]);
    return (bool)$st->fetchColumn();
  };
  $keteranganExists = function(?int $id) use ($conn){
    if ($id===null || $id<=0) return false; // Jika 0 atau null, anggap valid (opsional)
    $st=$conn->prepare("SELECT 1 FROM md_keterangan WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$id]);
    return (bool)$st->fetchColumn();
  };


  /* =================== STORE =================== */
  if ($action==='store' || $action==='create') {
    $errors=[];

    if ($tab==='angkutan') {
      $table = 'angkutan_pupuk_organik';
      $kebun_id       = i('kebun_id');
      $rayon_id       = i('rayon_id');       // BARU
      $asal_gudang_id = i('asal_gudang_id'); // BARU
      // $gudang_asal   = s('gudang_asal');  // LAMA
      $unit_tujuan_id = i('unit_tujuan_id'); // boleh NULL
      $tanggal        = s('tanggal');
      $jenis_pupuk    = s('jenis_pupuk');
      $jumlah         = f('jumlah');
      $nomor_do       = s('nomor_do');
      $supir          = s('supir');
      $keterangan_id  = i('keterangan_id');  // BARU (opsional)
      // $keterangan    = s('keterangan');   // LAMA

      if (!$kebun_id || !$kebunExists($kebun_id)) $errors[]='Kebun wajib dipilih.';
      if (!$rayon_id || !$rayonExists($rayon_id)) $errors[]='Rayon wajib dipilih.';
      if (!$asal_gudang_id || !$gudangExists($asal_gudang_id)) $errors[]='Gudang asal wajib diisi.';
      // if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.'; // LAMA
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($jenis_pupuk!=='' && !$pupukExists($jenis_pupuk)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($keterangan_id!==null && !$keteranganExists($keterangan_id)) $errors[]='Keterangan terpilih tidak valid.';
      
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      // Asumsi kolom baru (rayon_id, asal_gudang_id, keterangan_id) sudah ada di tabel
      // Asumsi kolom lama (gudang_asal, keterangan) diganti
      $cols = ['kebun_id','rayon_id','asal_gudang_id','unit_tujuan_id','tanggal','jenis_pupuk','jumlah','nomor_do','supir','keterangan_id','created_at','updated_at'];
      $vals = [':kid',    ':rid',    ':gid',          ':ut',           ':tgl',   ':jp',        ':jml',  ':ndo',    ':sp',   ':ket_id',    'NOW()',    'NOW()'];
      $params = [
        ':kid'    => $kebun_id,
        ':rid'    => $rayon_id,
        ':gid'    => $asal_gudang_id,
        ':ut'     => $unit_tujuan_id,
        ':tgl'    => $tanggal,
        ':jp'     => $jenis_pupuk,
        ':jml'    => $jumlah ?? 0,
        ':ndo'    => $nomor_do,
        ':sp'     => $supir,
        ':ket_id' => $keterangan_id
      ];

      $sql="INSERT INTO $table (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$conn->prepare($sql); $st->execute($params);

    } else { // TAB MENABUR (Tidak berubah)
      $table    = 'menabur_pupuk_organik';
      $kebun_id = i('kebun_id');
      $unit_id  = i('unit_id');
      $blok     = s('blok');      // md_blok.kode
      $tanggal  = s('tanggal');
      $jenis    = s('jenis_pupuk'); // md_pupuk.nama
      $t_tanam  = i('t_tanam');     // NEW (opsional)
      $dosis    = f('dosis');       // opsional
      $jumlah   = f('jumlah');
      $luas     = f('luas');
      $invt     = $_POST['invt_pokok'] ?? null; $invt = ($invt===''||$invt===null)? null : (int)$invt;

      if (!$kebun_id || !$kebunExists($kebun_id)) $errors[]='Kebun wajib dipilih.';
      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($t_tanam!==null && ($t_tanam<1900 || $t_tanam>2100)) $errors[]='T. Tanam di luar rentang wajar.';
      if ($dosis!==null && $dosis<0) $errors[]='Dosis tidak boleh negatif.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';
      if ($unit_id && $blok && !$blokExists($unit_id,$blok)) $errors[]='Blok tidak ditemukan pada unit terpilih (cek md_blok).';
      if ($jenis!=='' && !$pupukExists($jenis)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      // Build kolom dinamis (t_tanam & dosis opsional; catatan DIHAPUS)
      $cols   = ['kebun_id','unit_id','blok','tanggal','jenis_pupuk','jumlah','luas','invt_pokok','created_at','updated_at'];
      $vals   = [':kid',    ':uid',   ':blk',':tgl',   ':jp',        ':jml',  ':luas',':invt',     'NOW()',    'NOW()'];
      $params = [
        ':kid'=>$kebun_id, ':uid'=>$unit_id, ':blk'=>$blok, ':tgl'=>$tanggal, ':jp'=>$jenis,
        ':jml'=>$jumlah??0, ':luas'=>$luas??0, ':invt'=>$invt??0
      ];
      if ($hasCol($table,'t_tanam')) { $cols[]='t_tanam'; $vals[]=':tt'; $params[':tt']=$t_tanam; }
      if ($hasCol($table,'dosis'))   { $cols[]='dosis';   $vals[]=':ds'; $params[':ds']=$dosis??null; }

      $sql="INSERT INTO $table (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$conn->prepare($sql); $st->execute($params);
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  /* =================== UPDATE =================== */
  if ($action==='update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors=[];

    if ($tab==='angkutan') {
      $table = 'angkutan_pupuk_organik';
      $kebun_id       = i('kebun_id');
      $rayon_id       = i('rayon_id');       // BARU
      $asal_gudang_id = i('asal_gudang_id'); // BARU
      // $gudang_asal   = s('gudang_asal');  // LAMA
      $unit_tujuan_id = i('unit_tujuan_id');
      $tanggal        = s('tanggal');
      $jenis_pupuk    = s('jenis_pupuk');
      $jumlah         = f('jumlah');
      $nomor_do       = s('nomor_do');
      $supir          = s('supir');
      $keterangan_id  = i('keterangan_id');  // BARU
      // $keterangan    = s('keterangan');   // LAMA

      if (!$kebun_id || !$kebunExists($kebun_id)) $errors[]='Kebun wajib dipilih.';
      if (!$rayon_id || !$rayonExists($rayon_id)) $errors[]='Rayon wajib dipilih.';
      if (!$asal_gudang_id || !$gudangExists($asal_gudang_id)) $errors[]='Gudang asal wajib diisi.';
      // if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.'; // LAMA
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($jenis_pupuk!=='' && !$pupukExists($jenis_pupuk)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($keterangan_id!==null && !$keteranganExists($keterangan_id)) $errors[]='Keterangan terpilih tidak valid.';
      
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      // SQL dinamis untuk kolom opsional keterangan
      $sql = "UPDATE $table SET
                kebun_id = :kid, 
                rayon_id = :rid,
                asal_gudang_id = :gid,
                unit_tujuan_id = :ut, 
                tanggal = :tgl, 
                jenis_pupuk = :jp,
                jumlah = :jml, 
                nomor_do = :ndo, 
                supir = :sp,
                keterangan_id = :ket_id,
                updated_at = NOW()
              WHERE id = :id";
      $params = [
        ':kid'    => $kebun_id,
        ':rid'    => $rayon_id,
        ':gid'    => $asal_gudang_id,
        ':ut'     => $unit_tujuan_id,
        ':tgl'    => $tanggal,
        ':jp'     => $jenis_pupuk,
        ':jml'    => $jumlah ?? 0,
        ':ndo'    => $nomor_do,
        ':sp'     => $supir,
        ':ket_id' => $keterangan_id,
        ':id'     => $id
      ];

      $st=$conn->prepare($sql); $st->execute($params);

    } else { // TAB MENABUR (Tidak berubah)
      $table    = 'menabur_pupuk_organik';
      $kebun_id = i('kebun_id');
      $unit_id  = i('unit_id');
      $blok     = s('blok');
      $tanggal  = s('tanggal');
      $jenis    = s('jenis_pupuk');
      $t_tanam  = i('t_tanam');   // NEW
      $dosis    = f('dosis');     // opsional
      $jumlah   = f('jumlah');
      $luas     = f('luas');
      $invt     = $_POST['invt_pokok'] ?? null; $invt = ($invt===''||$invt===null)? null : (int)$invt;

      if (!$kebun_id || !$kebunExists($kebun_id)) $errors[]='Kebun wajib dipilih.';
      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($t_tanam!==null && ($t_tanam<1900 || $t_tanam>2100)) $errors[]='T. Tanam di luar rentang wajar.';
      if ($dosis!==null && $dosis<0) $errors[]='Dosis tidak boleh negatif.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';
      if ($unit_id && $blok && !$blokExists($unit_id,$blok)) $errors[]='Blok tidak ditemukan pada unit terpilih (cek md_blok).';
      if ($jenis!=='' && !$pupukExists($jenis)) $errors[]='Jenis pupuk tidak ada di master (md_pupuk).';
      if ($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      // SQL dinamis: t_tanam & dosis opsional; catatan dihapus
      $set = "kebun_id=:kid, unit_id=:uid, blok=:blk, tanggal=:tgl, jenis_pupuk=:jp, jumlah=:jml, luas=:luas, invt_pokok=:invt";
      if ($hasCol($table,'t_tanam')) $set .= ", t_tanam=:tt";
      if ($hasCol($table,'dosis'))   $set .= ", dosis=:ds";
      $set .= ", updated_at=NOW()";

      $sql = "UPDATE $table SET $set WHERE id=:id";
      $params = [
        ':kid'=>$kebun_id, ':uid'=>$unit_id, ':blk'=>$blok, ':tgl'=>$tanggal, ':jp'=>$jenis,
        ':jml'=>$jumlah??0, ':luas'=>$luas??0, ':invt'=>$invt??0, ':id'=>$id
      ];
      if ($hasCol($table,'t_tanam')) $params[':tt'] = $t_tanam;
      if ($hasCol($table,'dosis'))   $params[':ds'] = $dosis??null;

      $st=$conn->prepare($sql); $st->execute($params);
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  /* =================== DELETE =================== */
  if ($action==='delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    $st=$conn->prepare($tab==='angkutan'
      ? "DELETE FROM angkutan_pupuk_organik WHERE id=:id"
      : "DELETE FROM menabur_pupuk_organik WHERE id=:id");
    $st->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
}catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}