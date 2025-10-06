<?php
// admin/imports/import_pemeliharaan.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];
require_once '../../config/database.php';
$db = new Database(); $pdo = $db->getConnection();

// Untuk pilihan kategori
$daftar_tab = [
  'TU' => 'Pemeliharaan TU',
  'TBM' => 'Pemeliharaan TBM',
  'TM' => 'Pemeliharaan TM',
  'BIBIT_PN' => 'Pemeliharaan Bibit PN',
  'BIBIT_MN' => 'Pemeliharaan Bibit MN'
];

// Master untuk bantuan mapping by nama (opsional di proses)
$units  = $pdo->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$jenis  = $pdo->query("SELECT id, nama FROM md_jenis_pekerjaan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$tenaga = $pdo->query("SELECT id, nama FROM md_tenaga ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'pemeliharaan';
include_once '../../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Import Pemeliharaan</h1>
      <p class="text-gray-600">Unggah Excel (.xlsx) atau CSV (.csv) sesuai format template.</p>
    </div>
    <div class="flex gap-2">
      <a href="import_template_pemeliharaan.php" class="inline-flex items-center gap-2 bg-white border px-4 py-2 rounded-lg hover:bg-gray-50">
        <i class="ti ti-download"></i> Unduh Template (CSV)
      </a>
      <a href="../admin/pemeliharaan.php" class="inline-flex items-center gap-2 bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
        <i class="ti ti-arrow-left"></i> Kembali
      </a>
    </div>
  </div>

  <div class="bg-white p-6 rounded-xl shadow">
    <form method="POST" action="import_pemeliharaan_process.php" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">File Excel/CSV <span class="text-red-500">*</span></label>
          <input type="file" name="file" accept=".xlsx,.csv" required
                 class="w-full border rounded-lg px-3 py-2">
          <p class="text-xs text-gray-500 mt-1">Maks. 10 MB. Format .xlsx (Excel) atau .csv</p>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Kategori Data <span class="text-red-500">*</span></label>
          <select name="kategori" required class="w-full border rounded-lg px-3 py-2">
            <?php foreach ($daftar_tab as $k=>$v): ?>
              <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <details class="mt-2">
        <summary class="cursor-pointer text-sm font-semibold text-gray-800">Struktur kolom yang diterima</summary>
        <div class="text-sm text-gray-700 mt-2 space-y-2">
          <p><strong>Wajib:</strong> tanggal (YYYY-MM-DD), bulan (Januari–Desember), tahun (2000–2100), jenis (nama), tenaga (nama), unit (nama), status (Berjalan|Selesai|Tertunda)</p>
          <p><strong>Opsional:</strong> rencana, realisasi, kebun (akan disimpan ke kolom <em>rayon</em>)</p>
          <p>Contoh header: <code>tanggal,bulan,tahun,jenis,tenaga,unit,kebun,rencana,realisasi,status</code></p>
        </div>
      </details>

      <div class="pt-2 flex items-center gap-3">
        <button type="submit" class="bg-gray-900 text-white px-5 py-2 rounded-lg hover:bg-gray-800">
          <i class="ti ti-file-import mr-1"></i> Import
        </button>
        <a href="import_template_pemeliharaan.php" class="text-gray-700 hover:underline">Download template lagi</a>
      </div>
    </form>
  </div>

  <div class="bg-white p-4 rounded-lg border">
    <div class="text-sm font-semibold text-gray-800 mb-2">Tips</div>
    <ul class="text-sm text-gray-700 list-disc ml-5 space-y-1">
      <li>Nama <strong>jenis</strong>, <strong>tenaga</strong>, dan <strong>unit</strong> akan dicocokkan ke master (md_jenis_pekerjaan, md_tenaga, units). Jika tidak ditemukan, baris dianggap error.</li>
      <li>Pastikan kolom tanggal valid (format YYYY-MM-DD). Bulan wajib salah satu dari “Januari..Desember”.</li>
      <li>Angka rencana/realisasi boleh kosong (akan dianggap 0).</li>
    </ul>
  </div>
</div>
<?php include_once '../../layouts/footer.php'; ?>
