<?php
// admin/imports/import_pemeliharaan_process.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== ($_POST['csrf_token'] ?? '')) {
  die('CSRF tidak valid.');
}

require_once '../../config/database.php';
$db = new Database(); $pdo = $db->getConnection();

$kategori = $_POST['kategori'] ?? '';
$allowedKategori = ['TU','TBM','TM','BIBIT_PN','BIBIT_MN'];
if (!in_array($kategori, $allowedKategori, true)) {
  die('Kategori tidak valid.');
}

if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  die('File belum dipilih atau gagal diupload.');
}
$maxBytes = 10 * 1024 * 1024; // 10MB
if ($_FILES['file']['size'] > $maxBytes) die('File terlalu besar (maks 10 MB).');

$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
$tmpPath = $_FILES['file']['tmp_name'];

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$bulanSet  = array_flip($bulanList);

// Master maps
$mapUnit  = [];
$mapJenis = [];
$mapTena  = [];
foreach ($pdo->query("SELECT id,nama_unit FROM units") as $r) { $mapUnit[mb_strtolower(trim($r['nama_unit']))] = (int)$r['id']; }
foreach ($pdo->query("SELECT id,nama FROM md_jenis_pekerjaan") as $r) { $mapJenis[mb_strtolower(trim($r['nama']))] = (int)$r['id']; }
foreach ($pdo->query("SELECT id,nama FROM md_tenaga") as $r) { $mapTena[mb_strtolower(trim($r['nama']))] = (int)$r['id']; }

// Read rows -> array asosiatif
$rows = [];

if ($ext === 'xlsx') {
  // === XLSX via PhpSpreadsheet ===
  // Instal sekali via Composer di root project:
  // composer require phpoffice/phpspreadsheet
  try {
    require_once '../../vendor/autoload.php';
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
      if (count(array_filter($cells, fn($x)=>$x!==''))===0) continue; // skip baris kosong
      $assoc = [];
      foreach ($cells as $k=>$v) { $assoc[$header[$k] ?? ('col'.$k)] = trim((string)$v); }
      $rows[] = $assoc;
    }
  } catch (Throwable $e) {
    die('Gagal membaca XLSX. Pastikan PhpSpreadsheet terpasang (composer) dan file valid. Error: '.$e->getMessage());
  }
} elseif ($ext === 'csv') {
  // === CSV Native ===
  if (($fh = fopen($tmpPath, 'r')) === false) die('Gagal membuka CSV.');
  $header = null;
  while (($cols = fgetcsv($fh, 0, ',')) !== false) {
    $cols = array_map(fn($x)=>trim((string)$x), $cols);
    if (!$header) { $header = array_map(fn($x)=>mb_strtolower(trim($x)), $cols); continue; }
    if (count(array_filter($cols, fn($x)=>$x!==''))===0) continue;
    $assoc = [];
    foreach ($cols as $k=>$v) { $assoc[$header[$k] ?? ('col'.$k)] = $v; }
    $rows[] = $assoc;
  }
  fclose($fh);
} else {
  die('Ekstensi tidak didukung. Gunakan .xlsx atau .csv');
}

// Valid header minimal
$minHeaders = ['tanggal','bulan','tahun','jenis','tenaga','unit','status'];
$missing = array_diff($minHeaders, array_map('strtolower', array_keys($rows[0] ?? [])));
if ($missing) {
  die('Header file tidak lengkap. Wajib ada: '.implode(', ', $minHeaders));
}

// Normalizer kecil
function nzFloat($v){ return ($v===''||$v===null) ? 0.0 : (float)$v; }
function cleanStatus($s){
  $s = trim((string)$s);
  $opt = ['Berjalan','Selesai','Tertunda'];
  foreach ($opt as $o) { if (strcasecmp($s,$o)===0) return $o; }
  return 'Berjalan';
}

// Prepare statement insert
$sql = "INSERT INTO pemeliharaan
        (kategori, jenis_id, jenis_pekerjaan, tenaga_id, tenaga, unit_id, rayon, tanggal, bulan, tahun, rencana, realisasi, status, created_at, updated_at)
        VALUES
        (:kategori, :jenis_id, :jenis_nama, :tenaga_id, :tenaga_nama, :unit_id, :rayon, :tanggal, :bulan, :tahun, :rencana, :realisasi, :status, NOW(), NOW())";
$st = $pdo->prepare($sql);

// Loop & validate
$ok = 0; $fail = 0; $errors = [];
$pdo->beginTransaction();

try {
  foreach ($rows as $idx => $r) {
    $rowNo = $idx + 2; // +1 header +1 index->row
    $tanggal = $r['tanggal'] ?? '';
    $bulan   = $r['bulan'] ?? '';
    $tahun   = $r['tahun'] ?? '';
    $jenisNm = $r['jenis'] ?? '';
    $tenaNm  = $r['tenaga'] ?? '';
    $unitNm  = $r['unit'] ?? '';
    $kebun   = $r['kebun'] ?? ''; // -> rayon
    $rencana = nzFloat($r['rencana'] ?? '');
    $realis  = nzFloat($r['realisasi'] ?? '');
    $status  = cleanStatus($r['status'] ?? '');

    // Validasi dasar
    if (!$tanggal || !strtotime($tanggal)) {
      $fail++; $errors[] = "Row $rowNo: tanggal tidak valid."; continue;
    }
    if ($bulan==='' || !isset($bulanSet[$bulan])) {
      $fail++; $errors[] = "Row $rowNo: bulan harus salah satu dari Januari..Desember."; continue;
    }
    $tahunNum = (int)$tahun;
    if ($tahun==='' || $tahunNum<2000 || $tahunNum>2100) {
      $fail++; $errors[] = "Row $rowNo: tahun harus 2000–2100."; continue;
    }
    if ($jenisNm==='') { $fail++; $errors[] = "Row $rowNo: jenis wajib diisi."; continue; }
    if ($tenaNm==='')  { $fail++; $errors[] = "Row $rowNo: tenaga wajib diisi."; continue; }
    if ($unitNm==='')  { $fail++; $errors[] = "Row $rowNo: unit wajib diisi."; continue; }

    // Map ke ID master
    $jid = $mapJenis[mb_strtolower($jenisNm)] ?? null;
    $tid = $mapTena[mb_strtolower($tenaNm)]  ?? null;
    $uid = $mapUnit[mb_strtolower($unitNm)]  ?? null;

    if (!$jid) { $fail++; $errors[] = "Row $rowNo: jenis '$jenisNm' tidak ditemukan di master."; continue; }
    if (!$tid) { $fail++; $errors[] = "Row $rowNo: tenaga '$tenaNm' tidak ditemukan di master."; continue; }
    if (!$uid) { $fail++; $errors[] = "Row $rowNo: unit '$unitNm' tidak ditemukan di master."; continue; }

    // Insert
    $st->execute([
      ':kategori'    => $kategori,
      ':jenis_id'    => $jid,
      ':jenis_nama'  => $jenisNm,
      ':tenaga_id'   => $tid,
      ':tenaga_nama' => $tenaNm,
      ':unit_id'     => $uid,
      ':rayon'       => $kebun, // disimpan apa adanya; boleh kosong
      ':tanggal'     => $tanggal,
      ':bulan'       => $bulan,
      ':tahun'       => $tahunNum,
      ':rencana'     => $rencana,
      ':realisasi'   => $realis,
      ':status'      => $status,
    ]);
    $ok++;
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  die('Gagal import: '.$e->getMessage());
}

// Tampilkan ringkasan
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hasil Import Pemeliharaan</title>
  <link rel="stylesheet" href="https://cdn.tailwindcss.com/3.4.0/tailwind.min.css">
</head>
<body class="bg-gray-50 p-6">
  <div class="max-w-3xl mx-auto bg-white rounded-xl shadow p-6">
    <h1 class="text-2xl font-bold mb-4">Hasil Import Pemeliharaan</h1>
    <div class="grid grid-cols-3 gap-3 text-center mb-4">
      <div class="border rounded-lg p-4">
        <div class="text-gray-500 text-sm">Berhasil</div>
        <div class="text-2xl font-bold text-emerald-600"><?= (int)$ok ?></div>
      </div>
      <div class="border rounded-lg p-4">
        <div class="text-gray-500 text-sm">Gagal</div>
        <div class="text-2xl font-bold text-red-600"><?= (int)$fail ?></div>
      </div>
      <div class="border rounded-lg p-4">
        <div class="text-gray-500 text-sm">Total Baris</div>
        <div class="text-2xl font-bold text-gray-800"><?= (int)($ok+$fail) ?></div>
      </div>
    </div>

    <?php if ($errors): ?>
      <details class="mb-4">
        <summary class="cursor-pointer font-semibold text-red-700">Lihat daftar error (<?= count($errors) ?>)</summary>
        <div class="mt-2 text-sm text-gray-800">
          <ul class="list-disc ml-5 space-y-1">
            <?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?>
          </ul>
        </div>
      </details>
    <?php endif; ?>

    <div class="flex gap-2">
      <a href="import_pemeliharaan.php" class="px-4 py-2 rounded bg-gray-900 text-white">Import Lagi</a>
      <a href="../admin/pemeliharaan.php?tab=<?= urlencode($kategori) ?>" class="px-4 py-2 rounded border">Kembali ke Tabel</a>
    </div>
  </div>
</body>
</html>
