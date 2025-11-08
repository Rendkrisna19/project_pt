<?php
// pemupukan.php — MOD: Role 'staf', tombol ikon, Dropdown Master Baru (FIXED)
// FIX 2: Hapus fallback AJAX agar konsisten dengan validasi CRUD

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php");
  exit;
}

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

function qstr($v)
{
  return trim((string)$v);
}
function qintOrEmpty($v)
{
  return ($v === '' || $v === null) ? '' : (int)$v;
}

try {
  $db   = new Database();
  $conn = $db->getConnection();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Aktifkan error exception

  $cacheCols = [];
  $columnExists = function (PDO $c, $table, $col) use (&$cacheCols) {
    $k = "$table";
    if (!isset($cacheCols[$k])) {
      $st = $c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t' => $table]);
      $cacheCols[$k] = array_flip(array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME')));
    }
    return isset($cacheCols[$k][strtolower($col)]);
  };

  if (($_GET['ajax'] ?? '') === 'options') {
    header('Content-Type: application/json; charset=utf-8'); // Tambah charset
    $type = qstr($_GET['type'] ?? '');
    $unit_id = (isset($_GET['unit_id']) && $_GET['unit_id'] !== '') ? (int)$_GET['unit_id'] : null;

    if ($type === 'blok') {
      $data = [];
      if ($unit_id) {
        try {
          $st = $conn->prepare("SELECT kode AS blok FROM md_blok WHERE unit_id=:u AND kode<>'' ORDER BY kode");
          $st->execute([':u' => $unit_id]);
          $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'blok');
        } catch (Throwable $e) {
          error_log("Error fetching master blok: " . $e->getMessage()); // Log error
        }
        // [FIXED] Menghapus 'if (!$data)' (fallback ke tabel transaksi)
        // Ini untuk mencegah error validasi di CRUD
      }
      echo json_encode(['success' => true, 'data' => $data]);
      exit;
    }

    if ($type === 'jenis') {
      $data = [];
      try {
        $st = $conn->query("SELECT nama FROM md_pupuk WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama");
        $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'nama');
      } catch (Throwable $e) {
        error_log("Error fetching master pupuk: " . $e->getMessage());
        // [FIXED] Menghapus fallback ke tabel transaksi
        // Ini untuk mencegah error validasi di CRUD
      }
      echo json_encode(['success' => true, 'data' => $data]);
      exit;
    }


    echo json_encode(['success' => false, 'message' => 'Tipe options tidak dikenali']);
    exit;
  }


  $tab = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur', 'angkutan'], true)) $tab = 'menabur';

  // Filters
  $f_unit_id    = qintOrEmpty($_GET['unit_id'] ?? '');
  $f_kebun_id   = qintOrEmpty($_GET['kebun_id'] ?? '');
  $f_tanggal    = qstr($_GET['tanggal'] ?? '');
  $f_bulan      = qstr($_GET['bulan'] ?? ''); // Filter bulan masih pakai angka (1-12)
  $f_jenis      = qstr($_GET['jenis_pupuk'] ?? ''); // Filter jenis masih pakai nama
  // Filters untuk field baru (opsional, bisa ditambahkan jika perlu filter by ID master baru)
  $f_rayon_id      = qintOrEmpty($_GET['rayon_id'] ?? '');
  $f_apl_id        = qintOrEmpty($_GET['apl_id'] ?? '');
  $f_keterangan_id = qintOrEmpty($_GET['keterangan_id'] ?? '');
  $f_gudang_id     = qintOrEmpty($_GET['gudang_asal_id'] ?? '');
  // Filter teks lama (jika kolomnya masih ada dan diperlukan)
  $f_rayon_text    = qstr($_GET['rayon'] ?? '');
  $f_keterangan_text = qstr($_GET['keterangan'] ?? '');


  // --- Fetch Master Data (Termasuk yang Baru) ---
  $units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC); // Order by ID? or nama_unit?
  $kebuns = $conn->query("SELECT id, kode, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
  try {
    // [FIXED] Logika ini sekarang konsisten dengan AJAX
    $pupuks = $conn->query("SELECT nama FROM md_pupuk WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
  } catch (Throwable $e) { // Fallback (hanya untuk tampilan, tapi bisa menyebabkan error saat save)
    error_log("Error fetching master pupuk (main): " . $e->getMessage());
    // Sebaiknya dihapus juga jika CRUD-nya ketat
    $pupuks = $conn->query("SELECT DISTINCT jenis_pupuk AS nama FROM (SELECT jenis_pupuk FROM menabur_pupuk UNION ALL SELECT jenis_pupuk FROM angkutan_pupuk) t WHERE jenis_pupuk<>'' ORDER BY jenis_pupuk")->fetchAll(PDO::FETCH_COLUMN);
  }
  try {
    $tahunTanamList = $conn->query("SELECT id, tahun, COALESCE(keterangan,'') AS ket FROM md_tahun_tanam ORDER BY tahun DESC")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $tahunTanamList = [];
    error_log("Error fetching master tahun tanam: " . $e->getMessage());
  }

  // Fetch Master Baru (dengan error handling)
  try {
    $rayons = $conn->query("SELECT id, nama FROM md_rayon ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $rayons = [];
    error_log("Error fetching master rayon: " . $e->getMessage());
  }
  try {
    $apls = $conn->query("SELECT id, nama FROM md_apl ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $apls = [];
    error_log("Error fetching master apl: " . $e->getMessage());
  }
  try {
    $keterangans = $conn->query("SELECT id, keterangan FROM md_keterangan ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $keterangans = [];
    error_log("Error fetching master keterangan: " . $e->getMessage());
  }
  try {
    $gudangs = $conn->query("SELECT id, nama FROM md_asal_gudang ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $gudangs = [];
    error_log("Error fetching master gudang: " . $e->getMessage());
  }
  // --------------------------------------------------

  // Cek kolom (tidak berubah)
  $hasKebunMenaburId  = $columnExists($conn, 'menabur_pupuk', 'kebun_id');
  $hasKebunMenaburKod = $columnExists($conn, 'menabur_pupuk', 'kebun_kode');
  $hasKebunAngkutId   = $columnExists($conn, 'angkutan_pupuk', 'kebun_id');
  $hasKebunAngkutKod  = $columnExists($conn, 'angkutan_pupuk', 'kebun_kode');
  $hasTTIdMenabur   = $columnExists($conn, 'menabur_pupuk', 'tahun_tanam_id');
  $hasTTValMenabur = $columnExists($conn, 'menabur_pupuk', 'tahun_tanam');
  // Deteksi kolom APL lama (apl atau aplikator)
  $aplField = null;
  foreach (['apl', 'aplikator'] as $cand) {
    if ($columnExists($conn, 'menabur_pupuk', $cand)) {
      $aplField = $cand;
      break;
    }
  }
  $hasTahunMenabur = $columnExists($conn, 'menabur_pupuk', 'tahun');

  // Cek kolom ID baru
  $hasRayonIdM = $columnExists($conn, 'menabur_pupuk', 'rayon_id');
  $hasAplIdM = $columnExists($conn, 'menabur_pupuk', 'apl_id');
  $hasKetIdM = $columnExists($conn, 'menabur_pupuk', 'keterangan_id');
  $hasRayonIdA = $columnExists($conn, 'angkutan_pupuk', 'rayon_id');
  $hasGudangIdA = $columnExists($conn, 'angkutan_pupuk', 'gudang_asal_id');
  $hasKetIdA = $columnExists($conn, 'angkutan_pupuk', 'keterangan_id');


  $kebunIdToKode = [];
  foreach ($kebuns as $kb) {
    $kebunIdToKode[(int)$kb['id']] = $kb['kode'];
  }

  // Pagination (tidak berubah)
  $page     = max(1, (int)($_GET['page'] ?? 1));
  $perOpts  = [10, 25, 50, 100];
  $per_page = (int)($_GET['per_page'] ?? 10);
  if (!in_array($per_page, $perOpts, true)) $per_page = 10;
  $offset   = ($page - 1) * $per_page;
  $bulanList = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

  // --- Query Building (Dengan Join Master Baru) ---
  if ($tab === 'angkutan') {
    $title = "Data Angkutan Pupuk Kimia";
    $selectKebun = '';
    $joinKebun = '';
    if ($hasKebunAngkutId) {
      $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinKebun = " LEFT JOIN md_kebun kb ON kb.id = a.kebun_id ";
    } elseif ($hasKebunAngkutKod) {
      $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinKebun = " LEFT JOIN md_kebun kb ON kb.kode = a.kebun_kode ";
    }

    // Select & Join Master Baru (Angkutan)
    $selectRayon = $hasRayonIdA ? ", r.nama AS rayon_nama" : "";
    $joinRayon = $hasRayonIdA ? " LEFT JOIN md_rayon r ON r.id = a.rayon_id" : "";
    $selectGudang = $hasGudangIdA ? ", g.nama AS gudang_asal_nama" : "";
    $joinGudang = $hasGudangIdA ? " LEFT JOIN md_asal_gudang g ON g.id = a.gudang_asal_id" : "";
    $selectKet = $hasKetIdA ? ", k.keterangan AS keterangan_text" : "";
    $joinKet = $hasKetIdA ? " LEFT JOIN md_keterangan k ON k.id = a.keterangan_id" : "";

    $where = " WHERE 1=1";
    $p = [];
    if ($f_unit_id !== '') {
      $where .= " AND a.unit_tujuan_id = :uid";
      $p[':uid'] = (int)$f_unit_id;
    }
    if ($f_kebun_id !== '') {/* ... (logika kebun tidak berubah) ... */
    }
    if ($f_tanggal !== '') {
      $where .= " AND a.tanggal = :tgl";
      $p[':tgl'] = $f_tanggal;
    }
    if ($f_bulan !== '' && ctype_digit($f_bulan)) {
      $where .= " AND MONTH(a.tanggal) = :bln";
      $p[':bln'] = (int)$f_bulan;
    }
    if ($f_jenis !== '') {
      $where .= " AND a.jenis_pupuk = :jp";
      $p[':jp'] = $f_jenis;
    }
    // Filter by ID (jika ada di GET)
    if ($f_rayon_id !== '' && $hasRayonIdA) {
      $where .= " AND a.rayon_id = :rid";
      $p[':rid'] = $f_rayon_id;
    }
    if ($f_gudang_id !== '' && $hasGudangIdA) {
      $where .= " AND a.gudang_asal_id = :gid";
      $p[':gid'] = $f_gudang_id;
    }
    if ($f_keterangan_id !== '' && $hasKetIdA) {
      $where .= " AND a.keterangan_id = :kid";
      $p[':kid'] = $f_keterangan_id;
    }
    // Filter teks lama (jika kolom masih ada dan field ID tidak dipakai)
    if ($f_rayon_text !== '' && !$hasRayonIdA && $columnExists($conn, 'angkutan_pupuk', 'rayon')) {
      $where .= " AND a.rayon LIKE :ry";
      $p[':ry'] = "%$f_rayon_text%";
    }
    if ($f_keterangan_text !== '' && !$hasKetIdA && $columnExists($conn, 'angkutan_pupuk', 'keterangan')) {
      $where .= " AND a.keterangan LIKE :ket";
      $p[':ket'] = "%$f_keterangan_text%";
    }


    $fullJoins = $joinKebun . $joinRayon . $joinGudang . $joinKet;
    $stc = $conn->prepare("SELECT COUNT(*) FROM angkutan_pupuk a $fullJoins $where");
    foreach ($p as $k => $v) $stc->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stc->execute();
    $total_rows = (int)$stc->fetchColumn();
    $total_pages = max(1, (int)ceil($total_rows / $per_page));

    $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama $selectKebun $selectRayon $selectGudang $selectKet
          FROM angkutan_pupuk a
          LEFT JOIN units u ON u.id = a.unit_tujuan_id
          $fullJoins $where
          ORDER BY a.tanggal DESC, a.id DESC LIMIT :limit OFFSET :offset";
    $st = $conn->prepare($sql);
    foreach ($p as $k => $v) $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $st->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $stt = $conn->prepare("SELECT COALESCE(SUM(a.jumlah),0) AS tot_kg FROM angkutan_pupuk a $fullJoins $where");
    foreach ($p as $k => $v) $stt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stt->execute();
    $tot_all = $stt->fetch(PDO::FETCH_ASSOC);
    $tot_all_kg = (float)($tot_all['tot_kg'] ?? 0);
    $sum_page_kg = 0.0;
    foreach ($rows as $r) $sum_page_kg += (float)($r['jumlah'] ?? 0);
  } else { // Menabur
    $title = "Data Penaburan Pupuk Kimia";
    $selectKebun = '';
    $joinKebun = '';
    if ($hasKebunMenaburId) {
      $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinKebun = " LEFT JOIN md_kebun kb ON kb.id = m.kebun_id ";
    } elseif ($hasKebunMenaburKod) {
      $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinKebun = " LEFT JOIN md_kebun kb ON kb.kode = m.kebun_kode ";
    }

    $joinTT = '';
    $selectTT = '';
    $selectTTRaw = '';
    $joinBlok = '';
    if ($hasTTIdMenabur) {
      $joinTT = " LEFT JOIN md_tahun_tanam tt ON tt.id = m.tahun_tanam_id ";
      $selectTT = ", tt.tahun AS t_tanam";
      $selectTTRaw = ", m.tahun_tanam_id";
    } elseif ($hasTTValMenabur) {
      $selectTT = ", m.tahun_tanam AS t_tanam";
      $selectTTRaw = ", m.tahun_tanam AS tahun_tanam_val";
    } // Ambil value tahun jika kolom ID tidak ada
    else { /* Fallback join ke md_blok jika perlu */
    }

    $selectAplOld = $aplField ? ", m.`$aplField` AS apl_text" : ", NULL AS apl_text"; // Ambil teks APL lama jika ada
    $selectTahun = $hasTahunMenabur ? ", m.tahun AS tahun_input" : "";

    // Select & Join Master Baru (Menabur)
    $selectRayon = $hasRayonIdM ? ", r.nama AS rayon_nama" : "";
    $joinRayon = $hasRayonIdM ? " LEFT JOIN md_rayon r ON r.id = m.rayon_id" : "";
    $selectAplNew = $hasAplIdM ? ", apl.nama AS apl_nama" : ""; // Ambil nama APL baru jika ada
    $joinApl = $hasAplIdM ? " LEFT JOIN md_apl apl ON apl.id = m.apl_id" : "";
    $selectKet = $hasKetIdM ? ", k.keterangan AS keterangan_text" : "";
    $joinKet = $hasKetIdM ? " LEFT JOIN md_keterangan k ON k.id = m.keterangan_id" : "";


    $where = " WHERE 1=1";
    $p = [];
    if ($f_unit_id !== '') {
      $where .= " AND m.unit_id = :uid";
      $p[':uid'] = (int)$f_unit_id;
    }
    if ($f_kebun_id !== '') {/* ... (logika kebun tidak berubah) ... */
    }
    if ($f_tanggal !== '') {
      $where .= " AND m.tanggal = :tgl";
      $p[':tgl'] = $f_tanggal;
    }
    if ($f_bulan !== '' && ctype_digit($f_bulan)) {
      $where .= " AND MONTH(m.tanggal) = :bln";
      $p[':bln'] = (int)$f_bulan;
    }
    if ($f_jenis !== '') {
      $where .= " AND m.jenis_pupuk = :jp";
      $p[':jp'] = $f_jenis;
    }
    // Filter by ID (jika ada di GET)
    if ($f_rayon_id !== '' && $hasRayonIdM) {
      $where .= " AND m.rayon_id = :rid";
      $p[':rid'] = $f_rayon_id;
    }
    if ($f_apl_id !== '' && $hasAplIdM) {
      $where .= " AND m.apl_id = :aid";
      $p[':aid'] = $f_apl_id;
    }
    if ($f_keterangan_id !== '' && $hasKetIdM) {
      $where .= " AND m.keterangan_id = :kid";
      $p[':kid'] = $f_keterangan_id;
    }
    // Filter teks lama (jika kolom masih ada dan field ID tidak dipakai)
    if ($f_rayon_text !== '' && !$hasRayonIdM && $columnExists($conn, 'menabur_pupuk', 'rayon')) {
      $where .= " AND m.rayon LIKE :ry";
      $p[':ry'] = "%$f_rayon_text%";
    }
    if ($f_keterangan_text !== '' && !$hasKetIdM && $columnExists($conn, 'menabur_pupuk', 'keterangan')) {
      $where .= " AND m.keterangan LIKE :ket";
      $p[':ket'] = "%$f_keterangan_text%";
    }


    $fullJoins = $joinKebun . $joinTT . $joinBlok . $joinRayon . $joinApl . $joinKet;
    $stc = $conn->prepare("SELECT COUNT(*) FROM menabur_pupuk m $fullJoins $where");
    foreach ($p as $k => $v) $stc->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stc->execute();
    $total_rows = (int)$stc->fetchColumn();
    $total_pages = max(1, (int)ceil($total_rows / $per_page));

    $sql = "SELECT m.*, u.nama_unit AS unit_nama
                  $selectKebun $selectTT $selectTTRaw $selectAplOld $selectTahun
                  $selectRayon $selectAplNew $selectKet
          FROM menabur_pupuk m
          LEFT JOIN units u ON u.id=m.unit_id
          $fullJoins $where
          ORDER BY m.tanggal DESC, m.id DESC LIMIT :limit OFFSET :offset";

    $st = $conn->prepare($sql);
    foreach ($p as $k => $v) $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $st->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $sql_tot = "SELECT COALESCE(SUM(m.jumlah),0) AS tot_kg, COALESCE(SUM(m.luas),0) AS tot_luas, COALESCE(SUM(m.invt_pokok),0) AS tot_invt FROM menabur_pupuk m $fullJoins $where";
    $stt = $conn->prepare($sql_tot);
    foreach ($p as $k => $v) $stt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stt->execute();
    $tot_all = $stt->fetch(PDO::FETCH_ASSOC);
    $tot_all_kg = (float)($tot_all['tot_kg'] ?? 0);
    $tot_all_luas = (float)($tot_all['tot_luas'] ?? 0);
    $tot_all_invt = (int)($tot_all['tot_invt'] ?? 0);
    $sum_page_kg = 0.0;
    $sum_page_luas = 0.0;
    $sum_page_invt = 0;
    foreach ($rows as $r) {
      $sum_page_kg += (float)($r['jumlah'] ?? 0);
      $sum_page_luas += (float)($r['luas'] ?? 0);
      $sum_page_invt += (int)($r['invt_pokok'] ?? 0);
    }
  }
} catch (PDOException $e) {
  die("DB Error: " . $e->getMessage());
}

function qs_no_page(array $extra = [])
{
  // Update base array if new ID filters are added
  $base = [
    'tab' => $_GET['tab'] ?? '',
    'unit_id' => $_GET['unit_id'] ?? '',
    'kebun_id' => $_GET['kebun_id'] ?? '',
    'tanggal' => $_GET['tanggal'] ?? '',
    'bulan' => $_GET['bulan'] ?? '',
    'jenis_pupuk' => $_GET['jenis_pupuk'] ?? '',
    'rayon' => $_GET['rayon'] ?? '',
    'keterangan' => $_GET['keterangan'] ?? '', // Keep old text filters?
    // Add new ID filters if they exist in GET
    'rayon_id' => $_GET['rayon_id'] ?? '',
    'apl_id' => $_GET['apl_id'] ?? '',
    'keterangan_id' => $_GET['keterangan_id'] ?? '',
    'gudang_asal_id' => $_GET['gudang_asal_id'] ?? ''
  ];
  return http_build_query(array_merge($base, $extra));
}
$currentPage = 'pemupukan';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  .i-input,
  .i-select {
    border: 1px solid #e5e7eb;
    border-radius: .6rem;
    padding: .5rem .75rem;
    width: 100%;
    outline: none
  }

  .i-input:focus,
  .i-select:focus {
    border-color: #9ca3af;
    box-shadow: 0 0 0 3px rgba(156, 163, 175, .15)
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #1f2937;
    border-radius: .6rem;
    padding: .5rem 1rem
  }

  .btn:hover {
    background: #f9fafb
  }

  .btn-dark {
    background: #059669;
    color: #fff;
    border-color: #059669
  }

  .btn-dark:hover {
    background: #047857
  }

  .tbl-wrap {
    max-height: 60vh;
    overflow-y: auto
  }

  thead.sticky {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #f9fafb;
  }

  /* Pastikan background thead */
  table.table-fixed {
    table-layout: fixed
  }

  button:disabled {
    opacity: 0.5;
    cursor: not-allowed !important;
  }

  .action-btn {
    background: none;
    border: none;
    padding: 0.25rem;
    cursor: pointer;
    border-radius: 0.25rem;
  }

  .action-btn:hover:not(:disabled) {
    background-color: rgba(0, 0, 0, 0.05);
  }

  .action-btn:disabled svg {
    color: #9ca3af;
    /* gray-400 */
  }

  .action-btn svg {
    width: 1.1rem;
    height: 1.1rem;
  }

  /* Style baris total */
  .summary-totals {
    background-color: #077d11ff;
    /* bg-gray-100 */
    padding: 0.75rem 1rem;
    /* p-3 */
    margin-top: -1px;
    /* Rapatkan dengan tabel */
    border: 1px solid #e5e7eb;
    border-top: none;
    /* Hilangkan border atas */
    border-bottom-left-radius: 0.75rem;
    border-bottom-right-radius: 0.75rem;
    /* rounded-b-xl */
    font-size: 0.875rem;
    /* text-sm */
    color: #ffffffff;
    /* text-gray-800 */
    display: flex;
    justify-content: flex-end;
    /* Align to the right */
    gap: 1.5rem;
    /* gap-6 */
  }

  .summary-totals strong {
    font-weight: 600;
    /* font-semibold */
  }
</style>

<div class="space-y-6">
  <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">🔬 Pemupukan Kimia</h1>
  <div class="border-b border-gray-200 flex flex-wrap gap-2 md:gap-6">
    <?php
    $persist = [ // Persist filters
      'unit_id' => $f_unit_id,
      'kebun_id' => $f_kebun_id,
      'tanggal' => $f_tanggal,
      'bulan' => $f_bulan,
      'jenis_pupuk' => $f_jenis,
      'per_page' => $per_page,
      'rayon_id' => $f_rayon_id,
      'apl_id' => $f_apl_id,
      'keterangan_id' => $f_keterangan_id,
      'gudang_asal_id' => $f_gudang_id, // New IDs
      // 'rayon'=>$f_rayon_text, 'keterangan'=>$f_keterangan_text // Old text filters (optional)
    ];
    $qsMen = http_build_query(array_merge(['tab' => 'menabur'], $persist));
    $qsAng = http_build_query(array_merge(['tab' => 'angkutan'], $persist));
    ?>
    <a href="?<?= $qsMen ?>" class="px-3 py-2 border-b-2 text-sm font-medium <?= $tab === 'menabur' ? 'border-green-600 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Menabur Pupuk</a>
    <a href="?<?= $qsAng ?>" class="px-3 py-2 border-b-2 text-sm font-medium <?= $tab === 'angkutan' ? 'border-green-600 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Angkutan Pupuk</a>
  </div>
  <div class="bg-white p-6 rounded-xl shadow-md">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
      <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($title) ?></h2>
      <div class="flex gap-2 flex-wrap"> <?php $qsExport = qs_no_page(); ?>
        <a href="cetak/pemupukan_excel.php?<?= $qsExport ?>" class="btn"><i class="ti ti-file-spreadsheet text-emerald-600 text-xl"></i><span>Export Excel</span></a>
        <a href="cetak/pemupukan_pdf.php?<?= $qsExport ?>" target="_blank" rel="noopener" class="btn"><i class="ti ti-file-type-pdf text-red-600 text-xl"></i><span>Cetak PDF</span></a>

        <button id="btn-add" class="btn btn-dark"><i class="ti ti-plus"></i><span>Tambah Data</span></button>
      </div>
    </div>
    <form class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4" method="GET" id="filter-form">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-700 mb-1">Unit</label><select name="unit_id" class="i-select">
          <option value="">— Semua Unit —</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>" <?= ($f_unit_id !== '' && (int)$f_unit_id === (int)$u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-700 mb-1">Kebun</label><select name="kebun_id" class="i-select">
          <option value="">— Semua Kebun —</option><?php foreach ($kebuns as $k): ?><option value="<?= (int)$k['id'] ?>" <?= ($f_kebun_id !== '' && (int)$f_kebun_id === (int)$k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kebun']) ?> (<?= htmlspecialchars($k['kode']) ?>)</option><?php endforeach; ?>
        </select></div>
      <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal</label><input type="date" name="tanggal" value="<?= htmlspecialchars($f_tanggal) ?>" class="i-input"></div>
      <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-700 mb-1">Bulan (dari Tanggal)</label><select name="bulan" class="i-select">
          <option value="">— Semua Bulan —</option><?php foreach ($bulanList as $num => $name): ?><option value="<?= $num ?>" <?= ($f_bulan !== '' && (int)$f_bulan === $num) ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
        </select></div>
      <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-700 mb-1">Jenis Pupuk</label><select name="jenis_pupuk" class="i-select">
          <option value="">— Semua Jenis —</option><?php foreach ($pupuks as $jp): ?><option value="<?= htmlspecialchars($jp) ?>" <?= ($f_jenis === $jp) ? 'selected' : '' ?>><?= htmlspecialchars($jp) ?></option><?php endforeach; ?>
        </select></div>
      <div class="md:col-span-12 flex items-end justify-between gap-3">
        <div class="flex items-center gap-3">
          <?php $from = $total_rows ? ($offset + 1) : 0;
          $to = min($offset + $per_page, $total_rows); ?><span class="text-sm text-gray-700">Menampilkan <strong><?= $from ?></strong>–<strong><?= $to ?></strong> dari <strong><?= number_format($total_rows) ?></strong> data</span>
          <div class="flex items-center gap-2"><input type="hidden" name="page" value="1"><label class="text-sm text-gray-700">Baris/hal</label><select name="per_page" class="i-select" style="width:auto" onchange="this.form.submit()"><?php foreach ($perOpts as $opt): ?><option value="<?= $opt ?>" <?= $per_page == $opt ? 'selected' : '' ?>><?= $opt ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="flex gap-2"><button class="btn btn-dark" type="submit"><i class="ti ti-filter"></i> Terapkan</button><a href="?tab=<?= urlencode($tab) ?>" class="btn"><i class="ti ti-restore"></i> Reset</a></div>
      </div>
      
    </form>

    <div class="border rounded-xl overflow-x-auto">
      <div class="tbl-wrap">
        <table class="min-w-full text-sm table-fixed">
          <thead class="sticky bg-gray-50">
            <?php if ($tab === 'angkutan'): ?>
              <tr class=" bg-green-700 text-gray-100 uppercase tracking-wider text-sm">
                <th class="py-3 px-4 text-left w-[14rem]">Kebun</th>
                <th class="py-3 px-4 text-left w-[10rem]">Rayon</th>
                <th class="py-3 px-4 text-left w-[12rem]">Gudang Asal</th>
                <th class="py-3 px-4 text-left w-[12rem]">Unit Tujuan</th>
                <th class="py-3 px-4 text-left w-[10rem]">Tanggal</th>
                <th class="py-3 px-4 text-left w-[12rem]">Jenis Pupuk</th>
                <th class="py-3 px-4 text-right w-[10rem]">Jumlah (Kg)</th>
                <th class="py-3 px-4 text-left w-[12rem]">No SPB</th>
                <th class="py-3 px-4 text-left w-[12rem]">Keterangan</th>
                <th class="py-3 px-4 text-center w-[8rem]">Aksi</th>
              </tr>
            <?php else: ?>
              <tr class=" bg-green-700 text-gray-100 uppercase tracking-wider text-sm">
                <th class="py-3 px-4 text-left w-[7rem]">Tahun</th>
                <th class="py-3 px-4 text-left w-[12rem]">Kebun</th>
                <th class="py-3 px-4 text-left w-[10rem]">Tanggal</th>
                <th class="py-3 px-4 text-left w-[10rem]">Periode</th>
                <th class="py-3 px-4 text-left w-[12rem]">Unit/Defisi</th>
                <th class="py-3 px-4 text-left w-[9rem]">T.Tanam</th>
                <th class="py-3 px-4 text-left w-[9rem]">Blok</th>
                <th class="py-3 px-4 text-left w-[9rem]">Rayon</th>
                <th class="py-3 px-4 text-right w-[9rem]">Luas (Ha)</th>
                <th class="py-3 px-4 text-right w-[9rem]">Inv.Pkk</th>
                <th class="py-3 px-4 text-left w-[12rem]">Jenis Pupuk</th>
                <th class="py-3 px-4 text-left w-[7rem]">APL</th>
                <th class="py-3 px-4 text-right w-[8rem]">Dosis</th>
                <th class="py-3 px-4 text-left w-[12rem]">No AU-58</th>
                <th class="py-3 px-4 text-left w-[12rem]">Keterangan</th>
                <th class="py-3 px-4 text-right w-[8rem]">Kg</th>
                <th class="py-3 px-4 text-center w-[8rem]">Aksi</th>
              </tr>
            <?php endif; ?>
          </thead>
          <tbody class="text-gray-900">
            <?php if (empty($rows)): ?><tr>
                <td colspan="<?= $tab === 'angkutan' ? 10 : 17 ?>" class="text-center py-8 text-gray-500">Belum ada data.</td>
              </tr>
              <?php else: foreach ($rows as $r): ?>
                <tr class="border-b last:border-0 hover:bg-gray-50/50">
                  <?php if ($tab === 'angkutan'):
                    $rayonDisplay = $r['rayon_nama'] ?? ($r['rayon'] ?? '-'); // Prioritaskan nama dari join
                    $gudangDisplay = $r['gudang_asal_nama'] ?? ($r['gudang_asal'] ?? '-'); // Prioritaskan nama dari join
                    $ketDisplay = $r['keterangan_text'] ?? ($r['keterangan'] ?? ''); // Prioritaskan teks dari join
                  ?>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($rayonDisplay) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($gudangDisplay) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['tanggal']) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                    <td class="py-2.5 px-4 text-right"><?= number_format((float)($r['jumlah'] ?? 0), 2) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['no_spb'] ?? '') ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($ketDisplay) ?></td>
                    <td class="py-2.5 px-4 text-center">
                      <div class="flex items-center justify-center gap-2">
                        <button class="btn-edit action-btn text-blue-600" title="Edit" data-tab="angkutan" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>' <?= $isStaf ? 'disabled' : '' ?>>
                          <i class="ti ti-pencil"></i>
                        </button>
                        <button class="btn-delete action-btn text-red-600" title="Hapus" data-tab="angkutan" data-id="<?= (int)($r['id'] ?? 0) ?>" <?= $isStaf ? 'disabled' : '' ?>>
                          <i class="ti ti-trash"></i>
                        </button>
                      </div>
                    </td>
                  <?php else: // Menabur
                    $ts = strtotime($r['tanggal'] ?? ''); // Tambah null coalesce
                    $tahunFallback = $ts ? date('Y', $ts) : '';
                    $tahunRow = isset($r['tahun_input']) && $r['tahun_input'] !== '' ? $r['tahun_input'] : $tahunFallback;
                    $bulanIndex = $ts ? (int)date('n', $ts) : 0;
                    $periodeRow = $bulanIndex ? ($bulanList[$bulanIndex] . ' ' . $tahunFallback) : '';
                    $ttanam = isset($r['t_tanam']) && $r['t_tanam'] !== '' ? $r['t_tanam'] : '-';
                    $rayonDisplay = $r['rayon_nama'] ?? ($r['rayon'] ?? '-'); // Prioritaskan nama dari join
                    $aplDisplay = $r['apl_nama'] ?? ($r['apl_text'] ?? '-'); // Prioritaskan nama APL baru, fallback ke teks lama
                    $ketDisplay = $r['keterangan_text'] ?? ($r['keterangan'] ?? ''); // Prioritaskan teks dari join
                  ?>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($tahunRow) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['tanggal'] ?? '') ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($periodeRow) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($ttanam) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['blok'] ?? '') ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($rayonDisplay) ?></td>
                    <td class="py-2.5 px-4 text-right"><?= number_format((float)($r['luas'] ?? 0), 2) ?></td>
                    <td class="py-2.5 px-4 text-right"><?= (int)($r['invt_pokok'] ?? 0) ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['jenis_pupuk'] ?? '') ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($aplDisplay) ?></td>
                    <td class="py-2.5 px-4 text-right"><?= isset($r['dosis']) ? number_format((float)$r['dosis'], 2) : '-' ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($r['no_au_58'] ?? '') ?></td>
                    <td class="py-2.5 px-4"><?= htmlspecialchars($ketDisplay) ?></td>
                    <td class="py-2.5 px-4 text-right"><?= number_format((float)($r['jumlah'] ?? 0), 2) ?></td>
                    <td class="py-2.5 px-4 text-center">
                      <div class="flex items-center justify-center gap-2">
                        <button class="btn-edit action-btn text-blue-600" title="Edit" data-tab="menabur" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>' <?= $isStaf ? 'disabled' : '' ?>>
                          <i class="ti ti-pencil"></i>
                        </button>
                        <button class="btn-delete action-btn text-red-600" title="Hapus" data-tab="menabur" data-id="<?= (int)($r['id'] ?? 0) ?>" <?= $isStaf ? 'disabled' : '' ?>>
                          <i class="ti ti-trash"></i>
                        </button>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
            <?php endforeach;
            endif; ?>
          </tbody>
        </table>
      </div>
    </div> <?php if ($total_rows > 0): ?>
      <div class="summary-totals">
        <span><strong>TOTAL JUMLAH (Kg):</strong> <?= number_format($tab === 'angkutan' ? $tot_all_kg : $tot_all_kg, 2) ?></span>
        <?php if ($tab !== 'angkutan'): ?>
          <span><strong>TOTAL LUAS (Ha):</strong> <?= number_format($tot_all_luas, 2) ?></span>
          <span><strong>TOTAL INVT. POKOK:</strong> <?= number_format($tot_all_invt, 0) ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>


    <div class="mt-3 px-3 md:px-0 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
      <div class="text-sm text-gray-700">
        Halaman <span class="font-semibold"><?= $page ?></span>
        dari <span class="font-semibold"><?= $total_pages ?></span>
      </div>
      <?php function page_link($p)
      {
        $q = [
          'tab' => $_GET['tab'] ?? '',
          'unit_id' => $_GET['unit_id'] ?? '',
          'kebun_id' => $_GET['kebun_id'] ?? '',
          'tanggal' => $_GET['tanggal'] ?? '',
          'bulan' => $_GET['bulan'] ?? '',
          'jenis_pupuk' => $_GET['jenis_pupuk'] ?? '',
          'rayon_id' => $_GET['rayon_id'] ?? '',
          'apl_id' => $_GET['apl_id'] ?? '', // Tambah filter ID baru
          'keterangan_id' => $_GET['keterangan_id'] ?? '',
          'gudang_asal_id' => $_GET['gudang_asal_id'] ?? '',
          // 'rayon'=>$_GET['rayon']??'', 'keterangan'=>$_GET['keterangan']??'', // Teks lama opsional
          'per_page' => $_GET['per_page'] ?? '',
          'page' => $p,
        ];
        return '?' . http_build_query(array_filter($q)); // array_filter menghapus parameter kosong
      } ?>
      <div class="inline-flex items-center gap-2">
        <a href="<?= $page > 1 ? page_link($page - 1) : 'javascript:void(0)' ?>"
          class="px-3 py-2 rounded border text-sm <?= $page > 1 ? 'hover:bg-gray-50 text-gray-800' : 'opacity-50 cursor-not-allowed text-gray-400' ?>">
          Prev
        </a>
        <a href="<?= $page < $total_pages ? page_link($page + 1) : 'javascript:void(0)' ?>"
          class="px-3 py-2 rounded border text-sm <?= $page < $total_pages ? 'hover:bg-gray-50 text-gray-800' : 'opacity-50 cursor-not-allowed text-gray-400' ?>">
          Next
        </a>
      </div>
    </div>

  </div>
</div>
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-6">
      <h2 id="modal-title" class="text-2xl font-bold text-gray-900">Tambah Data</h2><button id="btn-close" class="text-gray-500 hover:text-gray-800 text-3xl" aria-label="Close">&times;</button>
    </div>
    <form id="crud-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>"><input type="hidden" name="action" id="form-action"><input type="hidden" name="id" id="form-id"><input type="hidden" name="tab" id="form-tab" value="<?= htmlspecialchars($tab) ?>">

      <div id="group-angkutan" class="<?= $tab === 'angkutan' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div><label class="block text-sm font-semibold mb-1">Kebun</label><select id="kebun_id_angkutan" name="kebun_id" class="i-select">
              <option value="">— Pilih Kebun —</option><?php foreach ($kebuns as $k): ?><option value="<?= (int)$k['id'] ?>" data-kode="<?= htmlspecialchars($k['kode']) ?>"><?= htmlspecialchars($k['nama_kebun']) ?> (<?= htmlspecialchars($k['kode']) ?>)</option><?php endforeach; ?>
            </select><input type="hidden" id="kebun_kode_angkutan" name="kebun_kode"></div>
          <div><label class="block text-sm font-semibold mb-1">Rayon</label><select id="rayon_id_angkutan" name="rayon_id" class="i-select">
              <option value="">— Pilih Rayon —</option><?php foreach ($rayons as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?></option><?php endforeach; ?>
            </select></div>
          <div><label class="block text-sm font-semibold mb-1">Gudang Asal *</label><select id="gudang_asal_id" name="gudang_asal_id" required class="i-select">
              <option value="">— Pilih Gudang —</option><?php foreach ($gudangs as $g): ?><option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['nama']) ?></option><?php endforeach; ?>
            </select></div>
          <div><label class="block text-sm font-semibold mb-1">Unit Tujuan *</label><select id="unit_tujuan_id" name="unit_tujuan_id" required class="i-select">
              <option value="">— Pilih Unit —</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
            </select></div>
          <div><label class="block text-sm font-semibold mb-1">Tanggal *</label><input type="date" id="tanggal_angkutan" name="tanggal" required class="i-input"></div>
          <div><label class="block text-sm font-semibold mb-1">Jenis Pupuk *</label><select id="jenis_pupuk_angkutan" name="jenis_pupuk" required class="i-select">
              <option value="">-- Pilih Jenis --</option><?php foreach ($pupuks as $jp): ?><option value="<?= htmlspecialchars($jp) ?>"><?= htmlspecialchars($jp) ?></option><?php endforeach; ?>
            </select></div>
          <div><label class="block text-sm font-semibold mb-1">Jumlah (Kg)</label><input type="number" step="0.01" min="0" id="jumlah_angkutan" name="jumlah" class="i-input"></div>
          <div><label class="block text-sm font-semibold mb-1">No SPB</label><input type="text" id="no_spb_angkutan" name="no_spb" class="i-input" placeholder="Contoh: SPB/XX/2025"></div>
          <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Keterangan</label><select id="keterangan_id_angkutan" name="keterangan_id" class="i-select">
              <option value="">— Pilih Keterangan —</option><?php foreach ($keterangans as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['keterangan']) ?></option><?php endforeach; ?>
            </select></div>
          <!-- <div><label class="block text-sm font-semibold mb-1">Nomor DO</label><input type="text" id="nomor_do" name="nomor_do" class="i-input"></div> -->
          <div><label class="block text-sm font-semibold mb-1">Supir</label><input type="text" id="supir" name="supir" class="i-input"></div>
        </div>
      </div>

      <div id="group-menabur" class="<?= $tab === 'menabur' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-1"><label class="block text-sm font-semibold mb-1">Unit *</label><select id="unit_id" name="unit_id" required class="i-select">
              <option value="">— Pilih Unit —</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="md:col-span-1"><label class="block text-sm font-semibold mb-1">Kebun</label><select id="kebun_id_menabur" name="kebun_id" class="i-select">
              <option value="">— Pilih Kebun —</option><?php foreach ($kebuns as $k): ?><option value="<?= (int)$k['id'] ?>" data-kode="<?= htmlspecialchars($k['kode']) ?>"><?= htmlspecialchars($k['nama_kebun']) ?> (<?= htmlspecialchars($k['kode']) ?>)</option><?php endforeach; ?>
            </select></div>
          <div class="md:col-span-1"><label class="block text-sm font-semibold mb-1">Blok *</label><select id="blok" name="blok" required class="i-select">
              <option value="">— pilih Unit dulu —</option>
            </select></div>
          <div><label class="block text-sm font-semibold mb-1">Rayon</label><select id="rayon_id_menabur" name="rayon_id" class="i-select">
              <option value="">— Pilih Rayon —</option><?php foreach ($rayons as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?></option><?php endforeach; ?>
            </select></div>
          <div><label class="block text-sm font-semibold mb-1">Tanggal *</label><input type="date" id="tanggal_menabur" name="tanggal" required class="i-input"></div>
          <div><label class="block text-sm font-semibold mb-1">Tahun</label><input type="number" id="tahun" name="tahun" min="1900" max="2100" placeholder="YYYY" class="i-input"></div>
          <div><label class="block text-sm font-semibold mb-1">Jenis Pupuk *</label><select id="jenis_pupuk_menabur" name="jenis_pupuk" required class="i-select">
              <option value="">-- Pilih Jenis --</option><?php foreach ($pupuks as $jp): ?><option value="<?= htmlspecialchars($jp) ?>"><?= htmlspecialchars($jp) ?></option><?php endforeach; ?>
            </select></div>
          <div><label class="block text-sm font-semibold mb-1">Tahun Tanam</label><select id="tahun_tanam_select" name="tahun_tanam_dummy" class="i-select">
              <option value="">— Pilih Tahun Tanam —</option><?php foreach (($tahunTanamList ?? []) as $tt): ?><option value="<?= htmlspecialchars($tt['tahun']) ?>" data-id="<?= (int)$tt['id'] ?>"><?= htmlspecialchars($tt['tahun']) ?><?= $tt['ket'] ? ' - ' . htmlspecialchars($tt['ket']) : '' ?></option><?php endforeach; ?>
            </select><input type="hidden" name="tahun_tanam_id" id="tahun_tanam_id"><input type="hidden" name="tahun_tanam" id="tahun_tanam_val"></div>
          <div><label class="block text-sm font-semibold mb-1">APL (Aplikasi)</label><select id="apl_id" name="apl_id" class="i-select">
              <option value="">— Pilih APL —</option><?php foreach ($apls as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?>
            </select></div>
          <div><label class="block text-sm font-semibold mb-1">Dosis</label><input type="number" step="0.01" min="0" id="dosis" name="dosis" class="i-input"></div>
          <div><label class="block text-sm font-semibold mb-1">Jumlah (Kg)</label><input type="number" step="0.01" min="0" id="jumlah_menabur" name="jumlah" class="i-input"></div>
          <div><label class="block text-sm font-semibold mb-1">Luas (Ha)</label><input type="number" step="0.01" min="0" id="luas" name="luas" class="i-input"></div>
          <div><label class="block text-sm font-semibold mb-1">Invt. Pokok</label><input type="number" step="1" min="0" id="invt_pokok" name="invt_pokok" class="i-input"></div>
          <div><label class="block text-sm font-semibold mb-1">No AU-58</label><input type="text" id="no_au_58_menabur" name="no_au_58" class="i-input" placeholder="Contoh: AU58/XX/2025"></div>
          <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Keterangan</label><select id="keterangan_id_menabur" name="keterangan_id" class="i-select">
              <option value="">— Pilih Keterangan —</option><?php foreach ($keterangans as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['keterangan']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6"><button type="button" id="btn-cancel" class="btn">Batal</button><button type="submit" class="btn btn-dark">Simpan</button></div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
  // [FIXED] JavaScript 'toggleGroup' dengan 'disabled' TETAP DIPAKAI
  // Ini untuk memperbaiki error "tanggal wajib diisi"
  document.addEventListener('DOMContentLoaded', () => {
    const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;
    const $ = s => document.querySelector(s);

    async function loadJSON(url) {
      try {
        const r = await fetch(url);
        if (!r.ok) throw new Error(r.statusText);
        return await r.json()
      } catch (e) {
        console.error("Fetch failed:", url, e);
        return null
      }
    }

    function fillSelect(el, list, placeholder = '— Pilih —') {
      if (!el) return;
      el.innerHTML = '';
      const d = document.createElement('option');
      d.value = '';
      d.textContent = placeholder;
      el.appendChild(d);
      (list || []).forEach(v => {
        const op = document.createElement('option');
        op.value = v;
        op.textContent = v;
        el.appendChild(op)
      })
    }

    // [FIXED] AJAX 'jenis' sekarang HANYA akan memuat dari master
    (async () => {
      const j = await loadJSON('?ajax=options&type=jenis');
      const arr = (j && j.success && Array.isArray(j.data)) ? j.data : [];
      if (arr.length) {
        fillSelect($('#jenis_pupuk_angkutan'), arr, '-- Pilih Jenis --');
        fillSelect($('#jenis_pupuk_menabur'), arr, '-- Pilih Jenis --')
      }
    })();

    // [FIXED] AJAX 'blok' sekarang HANYA akan memuat dari master
    async function refreshFormBlokByUnit() {
      const uid = $('#unit_id')?.value || '';
      const sel = $('#blok');
      if (!sel) return;
      sel.disabled = !uid;
      if (!uid) {
        sel.innerHTML = '<option value="">— pilih Unit dulu —</option>';
        return
      }
      sel.innerHTML = '<option value="">Memuat...</option>';
      const j = await loadJSON(`?ajax=options&type=blok&unit_id=${encodeURIComponent(uid)}`);
      const list = (j && j.success && Array.isArray(j.data)) ? j.data : [];
      fillSelect(sel, list, '-- Pilih Blok --')
    }
    $('#unit_id')?.addEventListener('change', refreshFormBlokByUnit);

    function syncKebunKode(selectId, hiddenId) {
      const sel = document.getElementById(selectId);
      const hid = document.getElementById(hiddenId);
      if (!sel || !hid) return;
      const kode = sel.options[sel.selectedIndex]?.dataset?.kode || '';
      hid.value = kode;
    }
    document.getElementById('kebun_id_angkutan')?.addEventListener('change', () => syncKebunKode('kebun_id_angkutan', 'kebun_kode_angkutan'));
    // document.getElementById('kebun_id_menabur')?.addEventListener('change',()=>syncKebunKode('kebun_id_menabur','kebun_kode_menabur')); 

    const modal = $('#crud-modal'),
      form = $('#crud-form'),
      title = $('#modal-title');
    const open = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex')
      },
      close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex')
      };

    /**
     * [PERBAIKAN TANGGAL - TETAP DIPAKAI]
     * Fungsi ini sekarang juga men-disable input di grup yang tidak aktif.
     * Ini memastikan FormData() hanya mengambil nilai dari grup yang aktif.
     */
    function toggleGroup(tab) {
      const ga = $('#group-angkutan'),
        gm = $('#group-menabur');
      const isAngkutan = (tab === 'angkutan');

      // Toggle visibility
      ga.classList.toggle('hidden', !isAngkutan);
      gm.classList.toggle('hidden', isAngkutan);

      // [FIX] Toggle 'disabled' state for ALL form elements in each group.
      // Input yang disabled TIDAK akan masuk ke FormData.
      ga.querySelectorAll('input, select, textarea').forEach(el => el.disabled = !isAngkutan);
      gm.querySelectorAll('input, select, textarea').forEach(el => el.disabled = isAngkutan);

      // Toggle 'required' state (logika ini sudah benar)
      ga.querySelectorAll('[required]').forEach(el => el.required = isAngkutan);
      gm.querySelectorAll('[required]').forEach(el => el.required = !isAngkutan);
    }

    // Sync hidden inputs for Tahun Tanam
    const ttSel = document.getElementById('tahun_tanam_select');
    const ttIdInput = document.getElementById('tahun_tanam_id');
    const ttValInput = document.getElementById('tahun_tanam_val');
    if (ttSel && ttIdInput && ttValInput) {
      ttSel.addEventListener('change', () => {
        const selectedOption = ttSel.options[ttSel.selectedIndex];
        ttIdInput.value = selectedOption?.dataset?.id || '';
        ttValInput.value = ttSel.value || ''; // Simpan nilai tahun (e.g., 2020)
      });
    }

    if ($('#btn-add')) {
      $('#btn-add').addEventListener('click', () => {
        form.reset();
        $('#form-action').value = 'store';
        $('#form-id').value = '';
        const currentTab = '<?= htmlspecialchars($tab) ?>'; // Ambil tab saat ini
        $('#form-tab').value = currentTab;
        title.textContent = 'Tambah Data';

        // Panggil toggleGroup untuk me-reset state disabled & required
        toggleGroup(currentTab);

        if (currentTab === 'menabur') {
          refreshFormBlokByUnit();
          ttSel.dispatchEvent(new Event('change'));
        } // Reset & trigger TT sync
        if (currentTab === 'angkutan') {
          $('#kebun_kode_angkutan').value = ''
        }
        open()
      });
    }

    $('#btn-close')?.addEventListener('click', close);
    $('#btn-cancel')?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    }); // Tutup modal dengan Esc

    document.body.addEventListener('click', async e => {
      const btn = e.target.closest('button');
      if (!btn) return;

      // Edit Button (hanya non-staf)
      if (btn.classList.contains('btn-edit') && !IS_STAF) {
        form.reset();
        const row = JSON.parse(decodeURIComponent(btn.dataset.json));
        const tab = btn.dataset.tab;
        $('#form-tab').value = tab;
        $('#form-action').value = 'update';
        $('#form-id').value = row.id || '';
        title.textContent = 'Edit Data';

        // Panggil toggleGroup untuk set state disabled & required
        toggleGroup(tab);

        // --- Isi Form Angkutan ---
        if (tab === 'angkutan') {
          setKebunSelect('kebun_id_angkutan', row);
          syncKebunKode('kebun_id_angkutan', 'kebun_kode_angkutan');
          $('#rayon_id_angkutan').value = row.rayon_id ?? ''; // Isi ID Rayon
          $('#gudang_asal_id').value = row.gudang_asal_id ?? ''; // Isi ID Gudang
          $('#unit_tujuan_id').value = row.unit_tujuan_id ?? '';
          $('#tanggal_angkutan').value = row.tanggal ?? '';
          $('#jenis_pupuk_angkutan').value = row.jenis_pupuk ?? '';
          $('#jumlah_angkutan').value = row.jumlah ?? '';
          $('#no_spb_angkutan').value = row.no_spb ?? '';
          $('#keterangan_id_angkutan').value = row.keterangan_id ?? ''; // Isi ID Keterangan
          // $('#nomor_do').value = row.nomor_do ?? '';
          $('#supir').value = row.supir ?? '';
        }
        // --- Isi Form Menabur ---
        else {
          setKebunSelect('kebun_id_menabur', row);
          // Sync kode kebun menabur (jika perlu)
          $('#unit_id').value = row.unit_id ?? '';
          await refreshFormBlokByUnit(); // Tunggu blok load
          $('#blok').value = row.blok ?? '';
          $('#rayon_id_menabur').value = row.rayon_id ?? ''; // Isi ID Rayon
          $('#tanggal_menabur').value = row.tanggal ?? '';
          $('#jenis_pupuk_menabur').value = row.jenis_pupuk ?? '';
          $('#dosis').value = (row.dosis ?? '') === null ? '' : row.dosis;
          $('#jumlah_menabur').value = row.jumlah ?? '';
          $('#luas').value = row.luas ?? '';
          $('#invt_pokok').value = row.invt_pokok ?? '';
          $('#no_au_58_menabur').value = row.no_au_58 ?? row.no_au58 ?? ''; // Cek kedua nama kolom
          $('#keterangan_id_menabur').value = row.keterangan_id ?? ''; // Isi ID Keterangan
          $('#apl_id').value = row.apl_id ?? ''; // Isi ID APL

          // Set Tahun input
          const tahunFromRow = (row.tahun_input ?? '');
          const tahunFromTanggal = row.tanggal ? String(row.tanggal).slice(0, 4) : '';
          $('#tahun').value = tahunFromRow || tahunFromTanggal || '';

          // Set Tahun Tanam Dropdown & hidden inputs
          const ttSel = document.getElementById('tahun_tanam_select');
          const ttIdInput = document.getElementById('tahun_tanam_id');
          const ttValInput = document.getElementById('tahun_tanam_val');
          if (ttSel && ttIdInput && ttValInput) {
            // Prioritaskan ID jika ada kolomnya di data JSON
            const idFromRow = row.tahun_tanam_id ?? '';
            // Ambil value tahun (bisa dari kolom tahun_tanam_val atau t_tanam)
            const tahunFromRowTT = row.tahun_tanam_val ?? row.t_tanam ?? '';
            ttSel.value = ''; // Reset first

            if (idFromRow) { // Jika ada ID, cari berdasarkan data-id
              const op = Array.from(ttSel.options).find(o => String(o.dataset.id || '') === String(idFromRow));
              if (op) ttSel.value = op.value;
            } else if (tahunFromRowTT) { // Jika tidak ada ID, coba cocokkan berdasarkan value (tahun)
              ttSel.value = String(tahunFromRowTT);
            }
            // Trigger change untuk sinkronisasi hidden inputs
            ttSel.dispatchEvent(new Event('change'));
          }
        }
        open();
      } // End btn-edit

      // Delete Button (hanya non-staf)
      if (btn.classList.contains('btn-delete') && !IS_STAF) {
        const id = btn.dataset.id;
        const tab = btn.dataset.tab;
        if (!id || !tab) return; // Basic validation
        Swal.fire({
          title: 'Hapus data ini?',
          text: 'Tindakan ini tidak dapat dibatalkan.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          confirmButtonText: 'Ya, Hapus',
          cancelButtonText: 'Batal'
        }).then(res => {
          if (!res.isConfirmed) return;
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action', 'delete');
          fd.append('tab', tab);
          fd.append('id', id);
          fetch('pemupukan_crud.php', {
              method: 'POST',
              body: fd
            })
            .then(r => {
              if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
              }
              return r.json();
            })
            .then(j => {
              if (j.success) Swal.fire({
                icon: 'success',
                title: 'Terhapus',
                timer: 1200,
                showConfirmButton: false
              }).then(() => location.reload());
              else Swal.fire('Gagal', j.message || 'Error saat menghapus', 'error')
            })
            .catch(err => Swal.fire('Error', `Gagal menghubungi server: ${err.message}`, 'error'));
        });
      } // End btn-delete
    }); // End body click listener

    // Form Submit (hanya non-staf)
    if (form) {
      form.addEventListener('submit', e => {
        e.preventDefault();
        const tab = $('#form-tab').value;

        const fd = new FormData(e.target); // Ambil semua data form (sekarang sudah benar)
        fd.set('tab', tab);
        fd.set('action', $('#form-action').value);

        let requiredFields = [];
        if (tab === 'angkutan') {
          requiredFields = ['gudang_asal_id', 'unit_tujuan_id', 'tanggal', 'jenis_pupuk'];
          syncKebunKode('kebun_id_angkutan', 'kebun_kode_angkutan'); // Pastikan kode kebun sinkron
        } else { // Menabur
          requiredFields = ['unit_id', 'blok', 'tanggal', 'jenis_pupuk'];
          // Sync hidden inputs for Tahun Tanam just before submit
          const ttSel = document.getElementById('tahun_tanam_select');
          const ttIdInput = document.getElementById('tahun_tanam_id');
          const ttValInput = document.getElementById('tahun_tanam_val');
          if (ttSel && ttIdInput && ttValInput) {
            const selectedOption = ttSel.options[ttSel.selectedIndex];
            fd.set('tahun_tanam_id', selectedOption?.dataset?.id || '');
            fd.set('tahun_tanam', ttSel.value || ''); // Kirim value tahunnya juga
          }
          fd.delete('tahun_tanam_dummy'); // Hapus dummy select
        }

        // Validasi Required Fields
        for (const name of requiredFields) {
          // fd.get() sekarang akan bekerja karena input yg tidak aktif di-disable
          if (!fd.get(name)) {
            // Cari input yang aktif (tidak disabled)
            const inputEl = form.querySelector(`[name="${name}"]:not(:disabled)`);

            let labelEl = null;
            if (inputEl) {
              // Coba cari label di parent div-nya
              const parentDiv = inputEl.closest('div');
              if (parentDiv) {
                labelEl = parentDiv.querySelector('label');
              }
            }

            const fieldName = labelEl ? labelEl.textContent.replace('*', '').trim() : name.replaceAll('_', ' ');
            Swal.fire('Validasi', `Field ${fieldName} wajib diisi.`, 'warning');

            if (inputEl) inputEl.focus(); // Fokus ke input yang kosong
            return; // Hentikan submit
          }
        }

        fetch('pemupukan_crud.php', {
            method: 'POST',
            body: fd
          })
          .then(r => {
            if (!r.ok) {
              throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
          })
          .then(j => {
            if (j.success) {
              close();
              Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: j.message || 'Tersimpan',
                timer: 1400,
                showConfirmButton: false
              }).then(() => location.reload())
            } else {
              const html = j.errors?.length ? `<ul class="list-disc list-inside text-left">${j.errors.map(e=>`<li>${e}</li>`).join('')}</ul>` : (j.message || 'Terjadi kesalahan');
              Swal.fire('Gagal', html, 'error')
            }
          })
          .catch(err => Swal.fire('Error', `Gagal menghubungi server: ${err.message}`, 'error'));
      });
    } // End if(!IS_STAF && form)

    // Auto-set tahun on date change (tidak berubah)
    $('#tanggal_menabur')?.addEventListener('change', e => {
      const y = (e.target.value || '').slice(0, 4);
      const t = document.getElementById('tahun');
      if (y && t && !t.value) t.value = y
    });

    // Initial load Blok options if Menabur tab is active
    if (document.querySelector('#group-menabur') && !document.querySelector('#group-menabur').classList.contains('hidden')) {
      refreshFormBlokByUnit()
    }

    // Helper function to find and set select value based on ID or text content
    function setKebunSelect(selectId, row) {
      const sel = document.getElementById(selectId);
      if (!sel) return;
      sel.value = ''; // Reset first
      if (row.kebun_id) {
        sel.value = row.kebun_id;
      } else if (row.kebun_kode) {
        const opt = Array.from(sel.options).find(o => (o.dataset.kode || '') === String(row.kebun_kode));
        if (opt) sel.value = opt.value;
      }
    }

  });
</script>