<?php
// pemupukan_crud.php (REVISI: pakai unit_id & unit_tujuan_id)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(['success'=>false,'message'=>'Akses ditolak. Silakan login.']);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']);
  exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
  echo json_encode(['success'=>false,'message'=>'Token keamanan tidak valid. Refresh halaman.']);
  exit;
}

require_once '../config/database.php';

$action = $_POST['action'] ?? '';
$tab    = $_POST['tab']    ?? '';
if (!in_array($tab, ['angkutan','menabur'], true)) {
  echo json_encode(['success'=>false,'message'=>'Tab tidak valid.']); exit;
}

function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){
  $v = $_POST[$k] ?? null;
  if ($v === '' || $v === null) return null;
  return is_numeric($v) ? (float)$v : null;
}
function i($k){
  $v = $_POST[$k] ?? null;
  if ($v === '' || $v === null) return null;
  return ctype_digit((string)$v) ? (int)$v : null;
}
function validDate($d){ return (bool)strtotime($d); }

try {
  $db = new Database();
  $conn = $db->getConnection();

  // helper: cek apakah kolom ada (untuk kompatibilitas saat transisi skema)
  $cacheCols = [];
  $columnExists = function(PDO $conn, $table, $col) use (&$cacheCols){
    $key = $table;
    if (!isset($cacheCols[$key])) {
      $stmt = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
      $stmt->execute([':t'=>$table]);
      $cacheCols[$key] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    }
    return in_array($col, $cacheCols[$key], true);
  };

  // helper: ambil nama unit dari id
  $getUnitName = function(PDO $conn, $unitId){
    if ($unitId === null) return null;
    $st = $conn->prepare("SELECT nama_unit FROM units WHERE id=:id");
    $st->execute([':id'=>$unitId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row['nama_unit'] ?? null;
  };

  if ($action === 'store' || $action === 'create') {
    $errors = [];

    if ($tab === 'angkutan') {
      $gudang_asal    = s('gudang_asal');
      $unit_tujuan_id = i('unit_tujuan_id');    // ganti dari afdeling_tujuan
      $tanggal        = s('tanggal');
      $jenis_pupuk    = s('jenis_pupuk');
      $jumlah         = f('jumlah');
      $nomor_do       = s('nomor_do');
      $supir          = s('supir');

      if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.';
      if (!$unit_tujuan_id) $errors[]='Unit tujuan wajib dipilih.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';

      if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      $afdeling_tujuan_name = $getUnitName($conn, $unit_tujuan_id);

      // siapkan SQL dinamis: isi kolom teks lama jika masih ada
      $hasAfdelingTujuan = $columnExists($conn, 'angkutan_pupuk', 'afdeling_tujuan');
      $cols = "gudang_asal, unit_tujuan_id, tanggal, jenis_pupuk, jumlah, nomor_do, supir, created_at, updated_at";
      $vals = ":ga,:uid,:tgl,:jp,:jml,:do,:supir,NOW(),NOW()";
      if ($hasAfdelingTujuan) {
        $cols = "gudang_asal, unit_tujuan_id, afdeling_tujuan, tanggal, jenis_pupuk, jumlah, nomor_do, supir, created_at, updated_at";
        $vals = ":ga,:uid,:aft,:tgl,:jp,:jml,:do,:supir,NOW(),NOW()";
      }

      $sql = "INSERT INTO angkutan_pupuk ($cols) VALUES ($vals)";
      $stmt = $conn->prepare($sql);
      $params = [
        ':ga'=>$gudang_asal,
        ':uid'=>$unit_tujuan_id,
        ':tgl'=>$tanggal,
        ':jp'=>$jenis_pupuk,
        ':jml'=>$jumlah ?? 0,
        ':do'=>$nomor_do,
        ':supir'=>$supir,
      ];
      if ($hasAfdelingTujuan) $params[':aft'] = $afdeling_tujuan_name ?? '';

      $stmt->execute($params);

    } else { // MENABUR
      $unit_id = i('unit_id');                 // ganti dari afdeling
      $blok    = s('blok');
      $tanggal = s('tanggal');
      $jenis   = s('jenis_pupuk');
      $jumlah  = f('jumlah');
      $luas    = f('luas');
      $invt    = $_POST['invt_pokok'] ?? null; $invt = ($invt===''||$invt===null) ? null : (int)$invt;
      $catatan = s('catatan');

      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';

      if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      $afdeling_name = $getUnitName($conn, $unit_id);
      $hasAfdeling   = $columnExists($conn, 'menabur_pupuk', 'afdeling');

      // Insert selalu set unit_id; isi kolom 'afdeling' jika masih ada
      if ($hasAfdeling) {
        $sql = "INSERT INTO menabur_pupuk
                (unit_id, afdeling, blok, tanggal, jenis_pupuk, jumlah, luas, invt_pokok, catatan, created_at, updated_at)
                VALUES (:uid,:afd,:blk,:tgl,:jp,:jml,:luas,:invt,:cat,NOW(),NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
          ':uid'=>$unit_id, ':afd'=>($afdeling_name ?? ''), ':blk'=>$blok, ':tgl'=>$tanggal, ':jp'=>$jenis,
          ':jml'=>$jumlah ?? 0, ':luas'=>$luas ?? 0, ':invt'=>$invt ?? 0, ':cat'=>$catatan
        ]);
      } else {
        $sql = "INSERT INTO menabur_pupuk
                (unit_id, blok, tanggal, jenis_pupuk, jumlah, luas, invt_pokok, catatan, created_at, updated_at)
                VALUES (:uid,:blk,:tgl,:jp,:jml,:luas,:invt,:cat,NOW(),NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
          ':uid'=>$unit_id, ':blk'=>$blok, ':tgl'=>$tanggal, ':jp'=>$jenis,
          ':jml'=>$jumlah ?? 0, ':luas'=>$luas ?? 0, ':invt'=>$invt ?? 0, ':cat'=>$catatan
        ]);
      }
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
  }

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors = [];

    if ($tab === 'angkutan') {
      $gudang_asal    = s('gudang_asal');
      $unit_tujuan_id = i('unit_tujuan_id');
      $tanggal        = s('tanggal');
      $jenis_pupuk    = s('jenis_pupuk');
      $jumlah         = f('jumlah');
      $nomor_do       = s('nomor_do');
      $supir          = s('supir');

      if ($gudang_asal==='') $errors[]='Gudang asal wajib diisi.';
      if (!$unit_tujuan_id) $errors[]='Unit tujuan wajib dipilih.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis_pupuk==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';

      if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      $afdeling_tujuan_name = $getUnitName($conn, $unit_tujuan_id);
      $hasAfdelingTujuan = $columnExists($conn, 'angkutan_pupuk', 'afdeling_tujuan');

      if ($hasAfdelingTujuan) {
        $sql = "UPDATE angkutan_pupuk SET
                  gudang_asal=:ga, unit_tujuan_id=:uid, afdeling_tujuan=:aft, tanggal=:tgl, jenis_pupuk=:jp,
                  jumlah=:jml, nomor_do=:do, supir=:supir, updated_at=NOW()
                WHERE id=:id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
          ':ga'=>$gudang_asal, ':uid'=>$unit_tujuan_id, ':aft'=>($afdeling_tujuan_name ?? ''),
          ':tgl'=>$tanggal, ':jp'=>$jenis_pupuk, ':jml'=>$jumlah ?? 0, ':do'=>$nomor_do, ':supir'=>$supir, ':id'=>$id
        ]);
      } else {
        $sql = "UPDATE angkutan_pupuk SET
                  gudang_asal=:ga, unit_tujuan_id=:uid, tanggal=:tgl, jenis_pupuk=:jp,
                  jumlah=:jml, nomor_do=:do, supir=:supir, updated_at=NOW()
                WHERE id=:id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
          ':ga'=>$gudang_asal, ':uid'=>$unit_tujuan_id,
          ':tgl'=>$tanggal, ':jp'=>$jenis_pupuk, ':jml'=>$jumlah ?? 0, ':do'=>$nomor_do, ':supir'=>$supir, ':id'=>$id
        ]);
      }
    } else {
      $unit_id = i('unit_id');
      $blok    = s('blok');
      $tanggal = s('tanggal');
      $jenis   = s('jenis_pupuk');
      $jumlah  = f('jumlah');
      $luas    = f('luas');
      $invt    = $_POST['invt_pokok'] ?? null; $invt = ($invt===''||$invt===null) ? null : (int)$invt;
      $catatan = s('catatan');

      if (!$unit_id) $errors[]='Unit wajib dipilih.';
      if ($blok==='') $errors[]='Blok wajib diisi.';
      if ($tanggal==='' || !validDate($tanggal)) $errors[]='Tanggal tidak valid.';
      if ($jenis==='') $errors[]='Jenis pupuk wajib diisi.';
      if ($jumlah!==null && $jumlah<0) $errors[]='Jumlah tidak boleh negatif.';
      if ($luas!==null && $luas<0) $errors[]='Luas tidak boleh negatif.';
      if ($invt!==null && $invt<0) $errors[]='Invt. Pokok tidak boleh negatif.';

      if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

      $afdeling_name = $getUnitName($conn, $unit_id);
      $hasAfdeling   = $columnExists($conn, 'menabur_pupuk', 'afdeling');

      if ($hasAfdeling) {
        $sql = "UPDATE menabur_pupuk SET
                  unit_id=:uid, afdeling=:afd, blok=:blk, tanggal=:tgl, jenis_pupuk=:jp,
                  jumlah=:jml, luas=:luas, invt_pokok=:invt, catatan=:cat, updated_at=NOW()
                WHERE id=:id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
          ':uid'=>$unit_id, ':afd'=>($afdeling_name ?? ''), ':blk'=>$blok, ':tgl'=>$tanggal, ':jp'=>$jenis,
          ':jml'=>$jumlah ?? 0, ':luas'=>$luas ?? 0, ':invt'=>$invt ?? 0, ':cat'=>$catatan, ':id'=>$id
        ]);
      } else {
        $sql = "UPDATE menabur_pupuk SET
                  unit_id=:uid, blok=:blk, tanggal=:tgl, jenis_pupuk=:jp,
                  jumlah=:jml, luas=:luas, invt_pokok=:invt, catatan=:cat, updated_at=NOW()
                WHERE id=:id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
          ':uid'=>$unit_id, ':blk'=>$blok, ':tgl'=>$tanggal, ':jp'=>$jenis,
          ':jml'=>$jumlah ?? 0, ':luas'=>$luas ?? 0, ':invt'=>$invt ?? 0, ':cat'=>$catatan, ':id'=>$id
        ]);
      }
    }

    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $stmt = $conn->prepare($tab==='angkutan'
      ? "DELETE FROM angkutan_pupuk WHERE id=:id"
      : "DELETE FROM menabur_pupuk WHERE id=:id");
    $stmt->execute([':id'=>$id]);

    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch (PDOException $e) {
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
