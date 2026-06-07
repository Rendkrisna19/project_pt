<?php
// admin/imports/import_lm_biaya.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$currentPage = 'lm_biaya';
chdir(__DIR__ . '/..');
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="space-y-6">
  <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Import LM Biaya</h1>
      <p class="text-gray-500 text-sm mt-1">Unggah file Excel (.xlsx) atau CSV (.csv) untuk mengisi data Biaya Operasional dan HPP.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="import_template_lm_biaya.php" class="inline-flex items-center gap-2 bg-cyan-600 text-white border border-cyan-700 px-4 py-2 rounded-lg hover:bg-cyan-700 shadow-sm transition text-sm font-semibold">
        <i class="ti ti-download"></i> Unduh Template (Excel)
      </a>
      <a href="../lm_biaya.php" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 shadow-sm transition text-sm font-semibold">
        <i class="ti ti-arrow-left"></i> Kembali
      </a>
    </div>
  </div>

  <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
    <form id="import-form" method="POST" action="import_lm_biaya_process.php" enctype="multipart/form-data" class="space-y-5">
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
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">kebun</td><td class="px-3 py-1.5 border">Nama Kebun (cocokkan dengan master)</td><td class="px-3 py-1.5 border text-center">Opsional</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">unit</td><td class="px-3 py-1.5 border">Nama Unit/Defisi (cocokkan dengan master)</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">alokasi</td><td class="px-3 py-1.5 border">No. Alokasi Pekerjaan</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">uraian_pekerjaan</td><td class="px-3 py-1.5 border">Uraian Pekerjaan</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">bulan</td><td class="px-3 py-1.5 border">Januari, Februari, ... Desember</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">tahun</td><td class="px-3 py-1.5 border">Tahun data (misal: 2026)</td><td class="px-3 py-1.5 border text-center">✅</td></tr>
                <tr><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">anggaran</td><td class="px-3 py-1.5 border">Anggaran (Rp)</td><td class="px-3 py-1.5 border text-center">—</td></tr>
                <tr class="bg-slate-50"><td class="px-3 py-1.5 border font-mono font-bold text-cyan-700">realisasi</td><td class="px-3 py-1.5 border">Realisasi (Rp)</td><td class="px-3 py-1.5 border text-center">—</td></tr>
              </tbody>
            </table>
          </div>
          <p class="text-[11px] text-gray-500 mt-2"><strong>Catatan:</strong> Kolom anggaran dan realisasi angka yang kosong dianggap 0.</p>
        </div>
      </details>

      <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
        <button type="submit" class="w-full sm:w-auto bg-cyan-600 text-white px-6 py-2.5 rounded-lg hover:bg-cyan-700 text-sm font-bold shadow-lg shadow-cyan-500/20 transition flex items-center justify-center gap-2">
          <i class="ti ti-file-import"></i> Import Data
        </button>
        <a href="import_template_lm_biaya.php" class="text-cyan-600 hover:underline text-sm font-semibold">Download template lagi</a>
      </div>
    </form>
  </div>

  <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
    <div class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
      <i class="ti ti-bulb text-amber-500"></i> Tips Import
    </div>
    <ul class="text-sm text-gray-600 list-disc ml-5 space-y-1.5">
      <li>Nama <strong>kebun</strong> dan <strong>unit</strong> akan dicocokkan ke data master (<code>md_kebun</code>, <code>units</code>). Jika tidak ditemukan, baris dianggap error.</li>
      <li>Kolom <strong>bulan</strong> wajib salah satu dari "Januari" s/d "Desember" (huruf kapital pertama).</li>
      <li>Angka (<strong>anggaran</strong>, <strong>realisasi</strong>) boleh kosong — akan dianggap 0.</li>
    </ul>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
