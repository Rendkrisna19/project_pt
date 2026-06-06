<?php
// admin/imports/import_lm76_process.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== ($_POST['csrf_token'] ?? '')) {
  die('CSRF tidak valid.');
}
// Set working directory ke admin/ agar semua relative path konsisten
chdir(__DIR__ . '/..');

require_once '../config/database.php';
$db = new Database(); $pdo = $db->getConnection();

// Validasi file
if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  die('File belum dipilih atau gagal diupload.');
}
$maxBytes = 10 * 1024 * 1024;
if ($_FILES['file']['size'] > $maxBytes) die('File terlalu besar (maks 10 MB).');

$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
$tmpPath = $_FILES['file']['tmp_name'];

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$bulanSet = array_flip($bulanList);

// Master maps (nama -> id)
$mapUnit  = [];
$mapKebun = [];
$mapTT    = [];
foreach ($pdo->query("SELECT id, nama_unit FROM units") as $r) { $mapUnit[mb_strtolower(trim($r['nama_unit']))] = (int)$r['id']; }
foreach ($pdo->query("SELECT id, nama_kebun FROM md_kebun") as $r) { $mapKebun[mb_strtolower(trim($r['nama_kebun']))] = (int)$r['id']; }
foreach ($pdo->query("SELECT DISTINCT tahun FROM md_tahun_tanam WHERE tahun IS NOT NULL AND tahun<>''") as $r) { $mapTT[mb_strtolower(trim($r['tahun']))] = trim($r['tahun']); }

// Cek kolom kebun_id di tabel lm76
function col_exists(PDO $pdo, $table, $col) {
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]); return (bool)$st->fetchColumn();
}
$hasKebun = col_exists($pdo, 'lm76', 'kebun_id');

// Read file -> rows
$rows = [];

if ($ext === 'xlsx') {
  try {
    require_once '../vendor/autoload.php';
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($tmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $header = [];
    foreach ($sheet->getRowIterator() as $i => $row) {
      $cells = [];
      $cellIter = $row->getCellIterator();
      $cellIter->setIterateOnlyExistingCells(false);
      foreach ($cellIter as $cell) { $cells[] = trim((string)$cell->getValue()); }
      if ($i === 1) {
        $header = array_map(fn($x)=>mb_strtolower(trim($x)), $cells);
        continue;
      }
      if (count(array_filter($cells, fn($x)=>$x!=='')) === 0) continue;
      $assoc = [];
      foreach ($cells as $k=>$v) { $assoc[$header[$k] ?? ('col'.$k)] = trim((string)$v); }
      $rows[] = $assoc;
    }
  } catch (Throwable $e) {
    die('Gagal membaca XLSX. Pastikan PhpSpreadsheet terpasang dan file valid. Error: '.$e->getMessage());
  }
} elseif ($ext === 'csv') {
  if (($fh = fopen($tmpPath, 'r')) === false) die('Gagal membuka CSV.');
  $header = null;
  while (($cols = fgetcsv($fh, 0, ',')) !== false) {
    $cols = array_map(fn($x)=>trim((string)$x), $cols);
    if (!$header) { $header = array_map(fn($x)=>mb_strtolower(trim($x)), $cols); continue; }
    if (count(array_filter($cols, fn($x)=>$x!=='')) === 0) continue;
    $assoc = [];
    foreach ($cols as $k=>$v) { $assoc[$header[$k] ?? ('col'.$k)] = $v; }
    $rows[] = $assoc;
  }
  fclose($fh);
} else {
  die('Ekstensi tidak didukung. Gunakan .xlsx atau .csv');
}

if (empty($rows)) die('File kosong atau tidak ada data setelah header.');

// Mapping header tampilan (dari template Excel) -> nama kolom teknis
$headerAliases = [
  'tahun'          => 'tahun',
  'kebun'          => 'kebun',
  'unit/defisi'    => 'unit',
  'unit'           => 'unit',
  'bulan'          => 'bulan',
  't. tanam'       => 'tahun_tanam',
  'tahun_tanam'    => 'tahun_tanam',
  'tahun tanam'    => 'tahun_tanam',
  'luas (ha)'      => 'luas_ha',
  'luas_ha'        => 'luas_ha',
  'invt pokok'     => 'invt_pokok',
  'invt_pokok'     => 'invt_pokok',
  'anggaran (kg)'  => 'anggaran_kg',
  'anggaran_kg'    => 'anggaran_kg',
  'realisasi (kg)' => 'realisasi_kg',
  'realisasi_kg'   => 'realisasi_kg',
  'jlh tandan'     => 'jumlah_tandan',
  'jumlah_tandan'  => 'jumlah_tandan',
  'jumlah tandan'  => 'jumlah_tandan',
  'jlh hk'         => 'jumlah_hk',
  'jumlah_hk'      => 'jumlah_hk',
  'jumlah hk'      => 'jumlah_hk',
  'panen (ha)'     => 'panen_ha',
  'panen_ha'       => 'panen_ha',
];

// Normalisasi key di setiap baris menggunakan alias
$rows = array_map(function($row) use ($headerAliases) {
  $normalized = [];
  foreach ($row as $key => $val) {
    $lk = mb_strtolower(trim($key));
    $mappedKey = $headerAliases[$lk] ?? $lk;
    $normalized[$mappedKey] = $val;
  }
  return $normalized;
}, $rows);

// Validasi header minimal
$minHeaders = ['tahun','unit','bulan','tahun_tanam'];
$fileHeaders = array_keys($rows[0] ?? []);
$missing = array_diff($minHeaders, $fileHeaders);
if ($missing) {
  die('Header file tidak lengkap. Kolom wajib: ' . implode(', ', $missing));
}

// Helper
function nzFloat($v) { return ($v===''||$v===null) ? 0.0 : (float)$v; }
function nzInt($v) { return ($v===''||$v===null) ? null : (int)$v; }

// Build insert columns dynamically
$cols = [];
if ($hasKebun) $cols[] = 'kebun_id';
$cols = array_merge($cols, ['unit_id','bulan','tahun','tt','luas_ha','jumlah_pohon','anggaran_kg','realisasi_kg','jumlah_tandan','jumlah_hk','panen_ha','frekuensi']);

$placeholders = implode(',', array_map(fn($c)=>":$c", $cols));
$sql = "INSERT INTO lm76 (" . implode(',', $cols) . ") VALUES ($placeholders)";
$st = $pdo->prepare($sql);

// Loop & validate
$ok = 0; $fail = 0; $errors = [];
$pdo->beginTransaction();

try {
  foreach ($rows as $idx => $r) {
    $rowNo = $idx + 2; // +1 for header, +1 for 0-index

    $tahun   = trim($r['tahun'] ?? '');
    $kebunNm = trim($r['kebun'] ?? '');
    $unitNm  = trim($r['unit'] ?? '');
    $bulan   = trim($r['bulan'] ?? '');
    $tt      = trim($r['tahun_tanam'] ?? '');

    // Validasi wajib
    $tahunNum = (int)$tahun;
    if ($tahun === '' || $tahunNum < 2000 || $tahunNum > 2100) {
      $fail++; $errors[] = "Baris $rowNo: tahun tidak valid ($tahun)."; continue;
    }
    if ($unitNm === '') {
      $fail++; $errors[] = "Baris $rowNo: unit wajib diisi."; continue;
    }
    if ($bulan === '' || !isset($bulanSet[$bulan])) {
      $fail++; $errors[] = "Baris $rowNo: bulan harus salah satu dari Januari..Desember (ditemukan: '$bulan')."; continue;
    }
    if ($tt === '') {
      $fail++; $errors[] = "Baris $rowNo: tahun_tanam wajib diisi."; continue;
    }

    // Map nama -> id
    $uid = $mapUnit[mb_strtolower($unitNm)] ?? null;
    if (!$uid) { $fail++; $errors[] = "Baris $rowNo: unit '$unitNm' tidak ditemukan di master."; continue; }

    $kid = null;
    if ($hasKebun && $kebunNm !== '') {
      $kid = $mapKebun[mb_strtolower($kebunNm)] ?? null;
      if (!$kid) { $fail++; $errors[] = "Baris $rowNo: kebun '$kebunNm' tidak ditemukan di master."; continue; }
    }

    // Validasi tahun tanam ada di master
    if (!isset($mapTT[mb_strtolower($tt)])) {
      $fail++; $errors[] = "Baris $rowNo: tahun_tanam '$tt' tidak ditemukan di master md_tahun_tanam."; continue;
    }
    $ttVal = $mapTT[mb_strtolower($tt)];

    // Parse angka
    $luas_ha       = nzFloat($r['luas_ha'] ?? '');
    $jumlah_pohon  = nzInt($r['invt_pokok'] ?? '');
    $anggaran_kg   = nzFloat($r['anggaran_kg'] ?? '');
    $realisasi_kg  = nzFloat($r['realisasi_kg'] ?? '');
    $jumlah_tandan = nzInt($r['jumlah_tandan'] ?? '');
    $jumlah_hk     = nzFloat($r['jumlah_hk'] ?? '');
    $panen_ha      = nzFloat($r['panen_ha'] ?? '');
    $frekuensi     = ($luas_ha > 0) ? round($panen_ha / $luas_ha, 3) : 0;

    // Build params
    $params = [];
    if ($hasKebun) $params[':kebun_id'] = $kid;
    $params[':unit_id']        = $uid;
    $params[':bulan']          = $bulan;
    $params[':tahun']          = $tahunNum;
    $params[':tt']             = $ttVal;
    $params[':luas_ha']        = $luas_ha;
    $params[':jumlah_pohon']   = $jumlah_pohon;
    $params[':anggaran_kg']    = $anggaran_kg;
    $params[':realisasi_kg']   = $realisasi_kg;
    $params[':jumlah_tandan']  = $jumlah_tandan;
    $params[':jumlah_hk']      = $jumlah_hk;
    $params[':panen_ha']       = $panen_ha;
    $params[':frekuensi']      = $frekuensi;

    $st->execute($params);
    $ok++;
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  die('Gagal import: '.$e->getMessage());
}

// Tampilkan ringkasan
$currentPage = 'lm76';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-bold text-gray-800">Hasil Import LM-76</h1>
    <p class="text-gray-500 text-sm mt-1">Ringkasan proses import data Statistik Panen.</p>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white border border-emerald-200 rounded-xl p-5 text-center shadow-sm">
      <div class="text-xs font-bold text-emerald-600 uppercase mb-1">Berhasil</div>
      <div class="text-3xl font-black text-emerald-600"><?= (int)$ok ?></div>
    </div>
    <div class="bg-white border border-red-200 rounded-xl p-5 text-center shadow-sm">
      <div class="text-xs font-bold text-red-600 uppercase mb-1">Gagal</div>
      <div class="text-3xl font-black text-red-600"><?= (int)$fail ?></div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-5 text-center shadow-sm">
      <div class="text-xs font-bold text-gray-500 uppercase mb-1">Total Baris</div>
      <div class="text-3xl font-black text-gray-800"><?= (int)($ok + $fail) ?></div>
    </div>
  </div>

  <?php if ($errors): ?>
  <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
    <details open>
      <summary class="cursor-pointer font-bold text-red-700 px-5 py-3 bg-red-50 flex items-center gap-2">
        <i class="ti ti-alert-triangle"></i> Daftar Error (<?= count($errors) ?> baris)
      </summary>
      <div class="px-5 py-4 max-h-[300px] overflow-y-auto">
        <ul class="list-disc ml-5 space-y-1 text-sm text-gray-700">
          <?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?>
        </ul>
      </div>
    </details>
  </div>
  <?php endif; ?>

  <div class="flex flex-wrap gap-3">
    <a href="import_lm76.php" class="inline-flex items-center gap-2 bg-cyan-600 text-white px-5 py-2.5 rounded-lg hover:bg-cyan-700 shadow-sm transition text-sm font-bold">
      <i class="ti ti-file-import"></i> Import Lagi
    </a>
    <a href="../lm76.php" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-5 py-2.5 rounded-lg hover:bg-gray-50 shadow-sm transition text-sm font-bold">
      <i class="ti ti-arrow-left"></i> Kembali ke Tabel LM-76
    </a>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
