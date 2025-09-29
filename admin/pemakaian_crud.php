<?php
// pemakaian_crud.php (FINAL stable)
// - Tag [Kebun: ...] & [Fisik: ...] disisipkan di awal keterangan saat store/update (normalisasi)
// - Parser tag toleran: bisa muncul di mana saja, spasi bebas, case-insensitive
// - Saat list: expose kebun_label, fisik_label, keterangan_clean

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

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
function s($k){ return trim((string)($_POST[$k] ?? '')); }
function f($k){ $v = $_POST[$k] ?? null; if ($v===''||$v===null) return null; return is_numeric($v) ? (float)$v : null; }
function validYear($y){ return preg_match('/^(19[7-9]\d|20\d{2}|2100)$/',$y); } // 1970..2100

// ===================== Tag Helpers (tolerant & anywhere) =====================
// Temukan tag [Label: value] DI MANA SAJA; kembalikan [value, cleanKet]
// - Spasi opsional setelah '[' dan sebelum/ sesudah ':'
// - Case-insensitive, unicode-safe
function extract_tag_anywhere($ket, $label){
  if (!is_string($ket)) $ket = (string)$ket;
  $pattern = '/\[\s*' . preg_quote($label,'/') . '\s*:\s*([^\]]+)\]\s*/iu';
  if (preg_match($pattern, $ket, $m)) {
    $val = trim($m[1]);
    $clean = preg_replace($pattern, '', $ket, 1); // hapus kemunculan pertama
    return [$val !== '' ? $val : null, trim($clean)];
  }
  return [null, trim($ket)];
}

// Buang semua tag Label di mana saja lalu prefix ulang satu kali di depan
function inject_tag_prefix($ket, $label, $value){
  $ket = (string)$ket;
  $pattern = '/\[\s*' . preg_quote($label,'/') . '\s*:\s*([^\]]+)\]\s*/iu';
  $ket = preg_replace($pattern, '', $ket);
  if ($value !== '') $ket = '['.$label.': '.$value.']'.($ket!==''?' '.$ket:'');
  return trim($ket);
}

function normalize_keterangan($ket_raw, $kebun_lbl, $fisik_lbl){
  // buang semua tag Kebun & Fisik di mana saja, lalu prefix ulang urut: Kebun -> Fisik
  $ket = (string)$ket_raw;
  $ket = preg_replace('/\[\s*Kebun\s*:\s*[^\]]+\]\s*/iu', '', $ket);
  $ket = preg_replace('/\[\s*Fisik\s*:\s*[^\]]+\]\s*/iu', '', $ket);
  if ($kebun_lbl!=='') $ket = '[Kebun: '.$kebun_lbl.']'.($ket!==''?' '.$ket:'');
  if ($fisik_lbl!=='') $ket = '[Fisik: '.$fisik_lbl.']'.($ket!==''?' '.$ket:'');
  return trim($ket);
}

// Helper spesifik
function extract_kebun_from_ket($ket){ return extract_tag_anywhere($ket, 'Kebun'); }
function extract_fisik_from_ket($ket){ return extract_tag_anywhere($ket, 'Fisik'); }
// ============================================================================

try {
  $db = new Database();
  $conn = $db->getConnection();
  $action = $_POST['action'] ?? '';

  // ===== LIST
  if ($action === 'list') {
    $q          = s('q');
    $unit_id    = (int)($_POST['unit_id'] ?? 0);
    $bulan      = s('bulan');
    $tahun      = (int)($_POST['tahun'] ?? date('Y'));
    $kebun_lblF = s('kebun_label'); // optional filter kebun via tag

    $sql = "SELECT p.*, u.nama_unit AS unit_nama
            FROM pemakaian_bahan_kimia p
            LEFT JOIN units u ON u.id = p.unit_id
            WHERE 1=1";
    $bind = [];
    if ($q !== '') {
      $sql .= " AND (p.no_dokumen LIKE :q OR p.nama_bahan LIKE :q OR p.jenis_pekerjaan LIKE :q OR p.keterangan LIKE :q)";
      $bind[':q'] = "%$q%";
    }
    if ($unit_id > 0) { $sql .= " AND p.unit_id = :uid"; $bind[':uid'] = $unit_id; }
    if ($bulan !== '') { $sql .= " AND p.bulan = :bln"; $bind[':bln'] = $bulan; }
    if ($tahun) { $sql .= " AND p.tahun = :thn"; $bind[':thn'] = $tahun; }
    if ($kebun_lblF !== '') {
      // Karena kita normalisasi agar tag di depan, LIKE awalan ini valid
      $sql .= " AND p.keterangan LIKE :kbntag";
      $bind[':kbntag'] = "[Kebun: ".$kebun_lblF."]%";
    }

    $sql .= " ORDER BY p.tahun DESC,
              FIELD(p.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
              p.created_at DESC";
    $stmt = $conn->prepare($sql); $stmt->execute($bind);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse kebun_label & fisik_label dari keterangan; urutan tag bebas
    foreach ($rows as &$r) {
      $ketRaw = (string)($r['keterangan'] ?? '');
      list($kebun, $rest)  = extract_kebun_from_ket($ketRaw);
      list($fisik, $clean) = extract_fisik_from_ket($rest);
      if ($kebun === null && $fisik === null) {
        // fallback jika [Fisik] muncul lebih dulu
        list($fisik2, $rest2) = extract_fisik_from_ket($ketRaw);
        list($kebun2, $clean2)= extract_kebun_from_ket($rest2);
        $kebun = $kebun2; $fisik = $fisik2; $clean = $clean2;
      }
      $r['kebun_label']      = $kebun;
      $r['fisik_label']      = $fisik;
      $r['keterangan_clean'] = trim($clean ?? $ketRaw);
    }
    unset($r);

    echo json_encode(['success'=>true,'data'=>$rows]); exit;
  }

  // ===== STORE
  if ($action === 'store' || $action === 'create') {
    $errors = [];
    $no_dokumen = s('no_dokumen');
    $unit_id    = (int)($_POST['unit_id'] ?? 0);
    $bulan      = s('bulan');
    $tahun      = s('tahun');
    $nama_bahan = s('nama_bahan');
    $jenis      = s('jenis_pekerjaan');
    $fisik_lbl  = s('fisik_label');
    $kebun_lbl  = s('kebun_label');
    $diminta    = f('jlh_diminta') ?? 0;
    $fisik      = f('jlh_fisik') ?? 0;
    $ket_raw    = s('keterangan');

    if ($no_dokumen==='')   $errors[]='No dokumen wajib diisi.';
    if ($unit_id<=0)        $errors[]='Unit wajib dipilih.';
    if ($bulan==='' || !in_array($bulan,$bulanList,true)) $errors[]='Bulan tidak valid.';
    if ($tahun==='' || !validYear($tahun)) $errors[]='Tahun tidak valid.';
    if ($nama_bahan==='')   $errors[]='Nama bahan wajib dipilih.';
    if ($jenis==='')        $errors[]='Jenis pekerjaan wajib dipilih.';
    if ($diminta<0 || $fisik<0) $errors[]='Jumlah tidak boleh negatif.';

    // upload dokumen (opsional)
    $path = null; $orig = null;
    if (!empty($_FILES['dokumen']) && $_FILES['dokumen']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['dokumen']['error'] !== UPLOAD_ERR_OK) $errors[]='Gagal upload dokumen.';
      else {
        $dir = realpath(__DIR__ . '/../uploads');
        if (!$dir) { mkdir(__DIR__ . '/../uploads', 0775, true); $dir = realpath(__DIR__ . '/../uploads'); }
        $dirPem = $dir . '/pemakaian';
        if (!is_dir($dirPem)) mkdir($dirPem, 0775, true);
        $ext = strtolower(pathinfo($_FILES['dokumen']['name'], PATHINFO_EXTENSION));
        $fname = 'PK_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $full = $dirPem . '/' . $fname;
        if (!move_uploaded_file($_FILES['dokumen']['tmp_name'], $full)) $errors[]='Tidak bisa menyimpan dokumen.';
        else { $path = '../uploads/pemakaian/' . $fname; $orig = $_FILES['dokumen']['name']; }
      }
    }

    if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    // Normalisasi: [Kebun: ..] [Fisik: ..] + teks asli
    $ket_store = normalize_keterangan($ket_raw, $kebun_lbl, $fisik_lbl);

    $sql = "INSERT INTO pemakaian_bahan_kimia
            (no_dokumen, unit_id, bulan, tahun, nama_bahan, jenis_pekerjaan, jlh_diminta, jlh_fisik, dokumen_path, dokumen_name, keterangan, created_at, updated_at)
            VALUES (:no,:uid,:bln,:thn,:nb,:jp,:dim,:fis,:path,:orig,:ket,NOW(),NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':no'=>$no_dokumen, ':uid'=>$unit_id, ':bln'=>$bulan, ':thn'=>$tahun,
      ':nb'=>$nama_bahan, ':jp'=>$jenis, ':dim'=>$diminta, ':fis'=>$fisik,
      ':path'=>$path, ':orig'=>$orig, ':ket'=>$ket_store
    ]);

    echo json_encode(['success'=>true,'message'=>'Data pemakaian berhasil ditambahkan.']); exit;
  }

  // ===== UPDATE
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }

    $errors = [];
    $no_dokumen = s('no_dokumen');
    $unit_id    = (int)($_POST['unit_id'] ?? 0);
    $bulan      = s('bulan');
    $tahun      = s('tahun');
    $nama_bahan = s('nama_bahan');
    $jenis      = s('jenis_pekerjaan');
    $fisik_lbl  = s('fisik_label');
    $kebun_lbl  = s('kebun_label');
    $diminta    = f('jlh_diminta') ?? 0;
    $fisik      = f('jlh_fisik') ?? 0;
    $ket_raw    = s('keterangan');

    if ($no_dokumen==='')   $errors[]='No dokumen wajib diisi.';
    if ($unit_id<=0)        $errors[]='Unit wajib dipilih.';
    if ($bulan==='' || !in_array($bulan,$bulanList,true)) $errors[]='Bulan tidak valid.';
    if ($tahun==='' || !validYear($tahun)) $errors[]='Tahun tidak valid.';
    if ($nama_bahan==='')   $errors[]='Nama bahan wajib dipilih.';
    if ($jenis==='')        $errors[]='Jenis pekerjaan wajib dipilih.';
    if ($diminta<0 || $fisik<0) $errors[]='Jumlah tidak boleh negatif.';

    // file baru?
    $path = null; $orig = null; $addSet = '';
    if (!empty($_FILES['dokumen']) && $_FILES['dokumen']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['dokumen']['error'] !== UPLOAD_ERR_OK) $errors[]='Gagal upload dokumen.';
      else {
        $dir = realpath(__DIR__ . '/../uploads');
        if (!$dir) { mkdir(__DIR__ . '/../uploads', 0775, true); $dir = realpath(__DIR__ . '/../uploads'); }
        $dirPem = $dir . '/pemakaian';
        if (!is_dir($dirPem)) mkdir($dirPem, 0775, true);
        $ext = strtolower(pathinfo($_FILES['dokumen']['name'], PATHINFO_EXTENSION));
        $fname = 'PK_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $full = $dirPem . '/' . $fname;
        if (!move_uploaded_file($_FILES['dokumen']['tmp_name'], $full)) $errors[]='Tidak bisa menyimpan dokumen.';
        else { $path = '../uploads/pemakaian/' . $fname; $orig = $_FILES['dokumen']['name']; $addSet = ", dokumen_path=:path, dokumen_name=:orig"; }
      }
    }

    if ($errors) { echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    $ket_store = normalize_keterangan($ket_raw, $kebun_lbl, $fisik_lbl);

    $sql = "UPDATE pemakaian_bahan_kimia SET
              no_dokumen=:no, unit_id=:uid, bulan=:bln, tahun=:thn,
              nama_bahan=:nb, jenis_pekerjaan=:jp, jlh_diminta=:dim, jlh_fisik=:fis, keterangan=:ket
              $addSet, updated_at=NOW()
            WHERE id=:id";
    $stmt = $conn->prepare($sql);
    $params = [
      ':no'=>$no_dokumen, ':uid'=>$unit_id, ':bln'=>$bulan, ':thn'=>$tahun,
      ':nb'=>$nama_bahan, ':jp'=>$jenis, ':dim'=>$diminta, ':fis'=>$fisik,
      ':ket'=>$ket_store, ':id'=>$id
    ];
    if ($addSet) { $params[':path']=$path; $params[':orig']=$orig; }
    $stmt->execute($params);

    echo json_encode(['success'=>true,'message'=>'Data pemakaian berhasil diperbarui.']); exit;
  }

  // ===== DELETE
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
    $stmt = $conn->prepare("DELETE FROM pemakaian_bahan_kimia WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data pemakaian berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
} catch (PDOException $e) {
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
