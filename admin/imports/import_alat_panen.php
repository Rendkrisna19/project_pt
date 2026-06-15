<?php
// admin/imports/import_alat_panen.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$currentPage = 'alat_panen';
chdir(__DIR__ . '/..');
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="space-y-6">
  <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Import Alat Panen</h1>
      <p class="text-gray-500 text-sm mt-1">Unggah file Excel (.xlsx) atau CSV (.csv) untuk mengisi data alat panen.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="import_template_alat_panen.php" class="inline-flex items-center gap-2 bg-cyan-600 text-white border border-cyan-700 px-4 py-2 rounded-lg hover:bg-cyan-700 shadow-sm transition text-sm font-semibold">
        <i class="ti ti-download"></i> Unduh Template (Excel)
      </a>
      <a href="../alat_panen.php" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 shadow-sm transition text-sm font-semibold">
        <i class="ti ti-arrow-left"></i> Kembali
      </a>
    </div>
  </div>

  <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
    <form id="import-form" method="POST" action="import_alat_panen_process.php" enctype="multipart/form-data" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">

      <div>
        <label class="block text-sm font-bold text-gray-700 mb-2">File Excel/CSV <span class="text-red-500">*</span></label>
        <input type="file" name="file" accept=".xlsx,.csv" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-cyan-500 outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer">
        <p class="text-xs text-gray-400 mt-1.5">Maks. 10 MB. Format .xlsx (Excel) atau .csv</p>
      </div>

      <details class="bg-slate-50 rounded-lg border border-slate-200 p-4">
        <summary class="cursor-pointer text-sm font-bold text-gray-700 flex items-center gap-2">
          <i class="ti ti-info-circle text-cyan-600"></i> Struktur Kolom yang Diterima
        </summary>
        <div class="text-sm text-gray-600 mt-3 space-y-2">
          <p class="font-semibold text-gray-800">Header kolom (baris pertama file):</p>
          <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse border border-slate-300 rounded">
              <thead>
                <tr class="bg-cyan-600 text-white">
                  <th class="px-3 py-2 border border-cyan-700 text-left">Kolom</th>
                  <th class="px-3 py-2 border border-cyan-700 text-left">Keterangan</th>
                  <th class="px-3 py-2 border border-cyan-700 text-center">Wajib?</th>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">kebun</td><td class="px-3 py-1.5 border">Nama Kebun (cocokkan dengan master)</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">unit</td><td class="px-3 py-1.5 border">Nama Unit/Afdeling (cocokkan dengan master)</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">jenis_alat</td><td class="px-3 py-1.5 border">Nama Jenis Alat: Egrek, Dodos, Gancu</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">bulan</td><td class="px-3 py-1.5 border">Januari, Februari, ... Desember</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">tahun</td><td class="px-3 py-1.5 border">Tahun data (misal: 2026)</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">stok_awal</td><td class="px-3 py-1.5 border">Stok Awal Periode</td><td class="px-3 py-1.5 border text-center">—</td></tr>
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">mutasi_masuk</td><td class="px-3 py-1.5 border">Mutasi Masuk / Alat Masuk</td><td class="px-3 py-1.5 border text-center">—</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">mutasi_keluar</td><td class="px-3 py-1.5 border">Mutasi Keluar / Alat Keluar</td><td class="px-3 py-1.5 border text-center">—</td></tr>
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">dipakai</td><td class="px-3 py-1.5 border">Jumlah Alat yang Dipakai</td><td class="px-3 py-1.5 border text-center">—</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">krani_afdeling</td><td class="px-3 py-1.5 border">Nama Krani Afdeling</td><td class="px-3 py-1.5 border text-center">Opsional</td></tr>
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">catatan</td><td class="px-3 py-1.5 border">Catatan Tambahan</td><td class="px-3 py-1.5 border text-center">Opsional</td></tr>
              </tbody>
            </table>
          </div>
          <p class="text-[11px] text-gray-500 mt-2"><strong>Catatan:</strong> Stok akhir dihitung otomatis. Kolom angka yang kosong dianggap 0.</p>
        </div>
      </details>

      <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
        <button type="submit" class="w-full sm:w-auto bg-cyan-600 text-white px-6 py-2.5 rounded-lg hover:bg-cyan-700 text-sm font-bold shadow-lg shadow-cyan-500/20 transition flex items-center justify-center gap-2">
          <i class="ti ti-file-import"></i> Import Data
        </button>
        <a href="import_template_alat_panen.php" class="text-cyan-600 hover:underline text-sm font-semibold">Download template lagi</a>
      </div>
    </form>
  </div>

  <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
    <div class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
      <i class="ti ti-bulb text-amber-500"></i> Tips Import
    </div>
    <ul class="text-sm text-gray-600 list-disc ml-5 space-y-1.5">
      <li>Nama <strong>kebun</strong> akan dicocokkan ke data master (<code>md_kebun</code>).</li>
      <li>Nama <strong>unit</strong> akan dicocokkan ke data master (<code>units</code>). Jika tidak ditemukan, baris dianggap error.</li>
      <li>Nama <strong>jenis alat</strong> akan dicocokkan ke master (<code>md_jenis_alat_panen</code>): Egrek, Dodos, Gancu.</li>
      <li>Kolom <strong>bulan</strong> wajib salah satu dari "Januari" s/d "Desember" (huruf kapital pertama).</li>
      <li><strong>Stok Akhir</strong> dihitung otomatis: Stok Awal + Mutasi Masuk - Mutasi Keluar - Dipakai.</li>
    </ul>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
