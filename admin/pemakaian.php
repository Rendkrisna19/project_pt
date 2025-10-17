<?php
// pemakaian.php — MOD: Role 'staf' tidak bisa edit/hapus

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}

// --- MODIFIKASI: Dapatkan role user dari session ---
$userRole = $_SESSION['user_role'] ?? 'staf'; // Default ke 'staf' untuk keamanan
$isStaf = ($userRole === 'staf');
// ---------------------------------------------------

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

// master data (langsung dari tabel masing-masing, tidak bergantung relasi)
$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit ASC")->fetchAll(PDO::FETCH_ASSOC);
$kebunList = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$bahanList = $conn->query("
  SELECT b.nama_bahan, s.nama AS satuan
  FROM md_bahan_kimia b
  LEFT JOIN md_satuan s ON s.id=b.satuan_id
  ORDER BY b.nama_bahan
")->fetchAll(PDO::FETCH_ASSOC);
$jenisList = $conn->query("SELECT nama FROM md_jenis_pekerjaan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$fisikList = $conn->query("SELECT nama FROM md_fisik ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'pemakaian';
include_once '../layouts/header.php';
?>
<style>
  /* sticky thead */
  .table-sticky thead th{
    position: sticky; top: 0; z-index: 10;
  }
  /* --- MODIFIKASI: Style untuk tombol disabled --- */
  button:disabled { opacity: 0.5; cursor: not-allowed !important; text-decoration: none !important; }
  /* badge hijau untuk kolom keterangan */
  .badge-emerald {
    display:inline-flex; align-items:center;
    padding:2px 8px; border-radius:9999px; font-size:.8rem; font-weight:600;
    color:#047857; background:#ecfdf5; box-shadow:inset 0 0 0 1px #6ee7b7;
    max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  /* untuk staf: matikan klik tanpa pakai disabled (supaya DOM aman & data tetap render) */
  .is-staf .btn-noaction { pointer-events:none; opacity:.5; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-3xl font-bold text-gray-800">🧪 Permintaan / Pemakaian Bahan Kimia</h1>
      <p class="text-gray-500 mt-1">Kelola pemakaian bahan kimia per Unit/Divisi</p>
    </div>

    <div class="flex gap-3">
      <button id="btn-export-excel" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50 text-gray-800">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.3 1.7L7 12l-1.7 2.3h1.5l1-1.5 1 1.5h1.5L8.6 12l1.7-2.3H8.8L7.8 11 6.8 9.7H5.3z"/></svg>
        <span>Export Excel</span>
      </button>
      <button id="btn-export-pdf" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50 text-gray-800">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/></svg>
        <span>Cetak PDF</span>
      </button>

      <button id="btn-add" class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-black">+ Input Pemakaian</button>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm">
    <div class="flex items-center gap-2 text-gray-700 mb-3"><span>🔎</span><span class="font-semibold">Filter & Pencarian</span></div>
    <div class="grid grid-cols-1 gap-4">
      <input id="filter-q" type="text" class="w-full border rounded-lg px-3 py-2 text-gray-800" placeholder="Cari no dokumen, bahan, pekerjaan...">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <select id="filter-unit" class="w-full border rounded-lg px-3 py-2 text-gray-800">
          <option value="">Semua Unit</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
          <?php endforeach; ?>
        </select>

        <select id="filter-bulan" class="w-full border rounded-lg px-3 py-2 text-gray-800">
          <option value="">Semua Bulan</option>
          <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
        </select>

        <select id="filter-tahun" class="w-full border rounded-lg px-3 py-2 text-gray-800">
          <?php for ($y = $tahunNow - 2; $y <= $tahunNow + 2; $y++): ?>
            <option value="<?= $y ?>" <?= $y === $tahunNow ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>

        <select id="filter-bahan-name" class="w-full border rounded-lg px-3 py-2 text-gray-800">
          <option value="">Semua Bahan</option>
          <?php foreach ($bahanList as $b): ?>
            <option value="<?= htmlspecialchars($b['nama_bahan']) ?>">
              <?= htmlspecialchars($b['nama_bahan'] . ($b['satuan'] ? ' ('.$b['satuan'].')' : '')) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select id="filter-jenis" class="w-full border rounded-lg px-3 py-2 text-gray-800">
          <option value="">Semua Jenis Pekerjaan</option>
          <?php foreach ($jenisList as $j): ?>
            <option value="<?= htmlspecialchars($j['nama']) ?>"><?= htmlspecialchars($j['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm border">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 p-3">
      <div class="text-sm text-gray-700">
        Menampilkan <span id="info-from" class="font-semibold">0</span>–<span id="info-to" class="font-semibold">0</span>
        dari <span id="info-total" class="font-semibold">0</span> data
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-700">Baris per halaman</label>
        <select id="per-page" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-800">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>
    <div class="overflow-x-auto">
      <div class="max-h-[60vh] overflow-y-auto">
        <table class="min-w-full text-sm table-sticky">
          <thead class="bg-green-600 text-white">
            <tr>
              <th class="py-3 px-4 text-left">No. Dokumen</th>
              <th class="py-3 px-4 text-left">Kebun</th>
              <th class="py-3 px-4 text-left">Unit</th>
              <th class="py-3 px-4 text-left">Periode</th>
              <th class="py-3 px-4 text-left">Nama Bahan</th>
              <th class="py-3 px-4 text-left">Jenis Pekerjaan</th>
              <th class="py-3 px-4 text-right">Jlh Bahan Diminta</th>
              <th class="py-3 px-4 text-right">Jumlah Fisik</th>
              <th class="py-3 px-4 text-left">Dokumen</th>
              <th class="py-3 px-4 text-left">Keterangan</th>
              <th class="py-3 px-4 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody id="tbody-data" class="text-gray-800">
            <tr><td colspan="11" class="text-center py-8 text-gray-500">Memuat data…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 p-3">
      <div class="text-sm text-gray-700">
        Halaman <span id="page-now" class="font-semibold">1</span> dari <span id="page-total" class="font-semibold">1</span>
      </div>
      <div class="inline-flex gap-2">
        <button id="btn-prev" class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">Prev</button>
        <button id="btn-next" class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
      </div>
    </div>
    <div class="p-4 bg-green-50 border-t-4 border-green-600 flex flex-col md:flex-row md:items-center md:justify-end gap-6">
      <div class="text-sm text-gray-800">Σ Jlh Bahan Diminta: <span id="sum-diminta" class="font-bold">0</span></div>
      <div class="text-sm text-gray-800">Σ Jumlah Fisik: <span id="sum-fisik" class="font-bold">0</span></div>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-900">Input Pemakaian Baru</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>
    <form id="crud-form" novalidate enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">No Dokumen</label>
          <input type="text" id="no_dokumen" name="no_dokumen" class="w-full border rounded px-3 py-2 text-gray-800">
        </div>
        <div>
          <label class="block text-sm mb-1">Nama Kebun</label>
          <select id="kebun_label" name="kebun_label" class="w-full border rounded px-3 py-2 text-gray-800">
            <option value="">-- Pilih Kebun --</option>
            <?php foreach ($kebunList as $k): ?>
              <option value="<?= htmlspecialchars($k['nama_kebun']) ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Unit/Divisi</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2 text-gray-800">
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select id="bulan" name="bulan" class="w-full border rounded px-3 py-2 text-gray-800">
            <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Tahun</label>
          <select id="tahun" name="tahun" class="w-full border rounded px-3 py-2 text-gray-800">
            <?php for ($y = $tahunNow - 1; $y <= $tahunNow + 3; $y++): ?>
              <option value="<?= $y ?>" <?= $y === $tahunNow ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Nama Bahan</label>
          <select id="nama_bahan" name="nama_bahan" class="w-full border rounded px-3 py-2 text-gray-800">
            <option value="">-- Pilih Bahan --</option>
            <?php foreach ($bahanList as $b): ?>
              <option value="<?= htmlspecialchars($b['nama_bahan']) ?>" data-satuan="<?= htmlspecialchars($b['satuan'] ?? '') ?>">
                <?= htmlspecialchars($b['nama_bahan'] . ($b['satuan'] ? ' ('.$b['satuan'].')' : '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="text-xs text-gray-500 mt-1">Satuan bahan: <span id="hint-satuan-bahan" class="font-semibold">-</span></p>
        </div>
        <div>
          <label class="block text-sm mb-1">Jenis Pekerjaan</label>
          <select id="jenis_pekerjaan" name="jenis_pekerjaan" class="w-full border rounded px-3 py-2 text-gray-800">
            <option value="">-- Pilih Jenis Pekerjaan --</option>
            <?php foreach ($jenisList as $j): ?>
              <option value="<?= htmlspecialchars($j['nama']) ?>"><?= htmlspecialchars($j['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Jumlah Bahan Diminta</label>
          <input type="number" step="0.01" id="jlh_diminta" name="jlh_diminta" class="w-full border rounded px-3 py-2 text-gray-800" min="0" value="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Jumlah Fisik</label>
          <input type="number" step="0.01" id="jlh_fisik" name="jlh_fisik" class="w-full border rounded px-3 py-2 text-gray-800" min="0" value="0">
          <div class="mt-2">
            <label class="block text-xs text-gray-600 mb-1">Keterangan Fisik</label>
            <select id="fisik_label" name="fisik_label" class="w-full border rounded px-3 py-2 text-gray-800">
              <option value="">— Pilih (Ha, Pkk, dll) —</option>
              <?php foreach ($fisikList as $f): ?>
                <option value="<?= htmlspecialchars($f['nama']) ?>"><?= htmlspecialchars($f['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Dokumen Pendukung (opsional)</label>
          <input type="file" id="dokumen" name="dokumen" class="w-full border rounded px-3 py-2 text-gray-800" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Keterangan</label>
          <input type="text" id="keterangan" name="keterangan" class="w-full border rounded px-3 py-2 text-gray-800" placeholder="Catatan tambahan (opsional)">
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-gray-900 text-white hover:bg-black">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // --- MODIFIKASI: Kirim role ke JavaScript ---
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;

  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data'), q = $('#filter-q'), selUnit = $('#filter-unit'), selBulan = $('#filter-bulan');
  const selTahun = $('#filter-tahun'), selBahanName = $('#filter-bahan-name'), selJenis = $('#filter-jenis');
  const perPageEl = $('#per-page'), btnPrev = $('#btn-prev'), btnNext = $('#btn-next'), infoFrom = $('#info-from');
  const infoTo = $('#info-to'), infoTotal = $('#info-total'), pageNow = $('#page-now'), pageTotal = $('#page-total');
  const sumDimintaEl = $('#sum-diminta'), sumFisikEl = $('#sum-fisik');
  const modal = $('#crud-modal'), btnClose = $('#btn-close'), btnCancel = $('#btn-cancel'), form = $('#crud-form');
  const formAction = $('#form-action'), formId = $('#form-id'), title = $('#modal-title');

  const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  const close= () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }

  let ALL_ROWS = [], CURRENT_PAGE = 1, PER_PAGE = parseInt(perPageEl.value, 10) || 10;
  const fmt = (n) => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });

  function buildRowHTML(row) {
  const jumlahDiminta = fmt(row.jlh_diminta);
  const jumlahFisik   = fmt(row.jlh_fisik);
  const fisikSuffix   = row.fisik_label ? ` <span class="text-xs text-gray-500">(${row.fisik_label})</span>` : '';
  const docLink       = row.dokumen_path
    ? `<a href="${row.dokumen_path}" target="_blank" class="underline text-gray-800">Lihat</a>`
    : 'N/A';

  // siapkan payload edit
  const payload = encodeURIComponent(JSON.stringify(row || {}));

  // Keterangan versi badge (fallback ke '-')
  const ketText = (row.keterangan_clean && String(row.keterangan_clean).trim()) ? row.keterangan_clean : '-';
  const keteranganBadge = `<span class="badge-emerald" title="${ketText}">${ketText}</span>`;

  // state role staf -> non-klik + ikon
  const stafClass = IS_STAF ? 'is-staf' : '';
  const editBtn = `
    <button class="btn-edit ${IS_STAF ? 'btn-noaction' : ''} inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100"
            data-json="${payload}" title="Edit">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
        <path d="M21.7 7.04a1 1 0 0 0 0-1.41l-3.33-3.33a1 1 0 0 0-1.41 0L4 14.26V18a1 1 0 0 0 1 1h3.74L21.7 7.04zM7.41 17H6v-1.41L15.06 6.5l1.41 1.41L7.41 17z"/>
      </svg>
      <span class="underline text-gray-800">Edit</span>
    </button>`;

  const deleteBtn = `
    <button class="btn-delete ${IS_STAF ? 'btn-noaction' : ''} inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100"
            data-id="${row.id}" title="Hapus">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
        <path d="M9 3a1 1 0 0 0-1 1v1H5a1 1 0 1 0 0 2h.59l.86 12.04A2 2 0 0 0 8.45 22h7.1a2 2 0 0 0 2-1.96L18.41 7H19a1 1 0 1 0 0-2h-3V4a1 1 0 0 0-1-1H9zm2 2h2v1h-2V5zm-1.58 3h7.16l-.82 11.5a.5.5 0 0 1-.5.47h-4.99a.5.5 0 0 1-.5-.47L9.42 8z"/>
      </svg>
      <span class="underline text-gray-800">Hapus</span>
    </button>`;

  return `
    <tr class="border-b hover:bg-gray-50 ${stafClass}">
      <td class="py-3 px-4"><span class="font-semibold">${row.no_dokumen || '-'}</span></td>
      <td class="py-3 px-4">${row.kebun_label || '-'}</td>
      <td class="py-3 px-4">${row.unit_nama || '-'}</td>
      <td class="py-3 px-4">${row.bulan || '-'} ${row.tahun || '-'}</td>
      <td class="py-3 px-4">${row.nama_bahan || '-'}</td>
      <td class="py-3 px-4">${row.jenis_pekerjaan || '-'}</td>
      <td class="py-3 px-4 text-right">${jumlahDiminta}</td>
      <td class="py-3 px-4 text-right">${jumlahFisik}${fisikSuffix}</td>
      <td class="py-3 px-4">${docLink}</td>
      <td class="py-3 px-4">${keteranganBadge}</td>
      <td class="py-3 px-4">
        <div class="flex items-center gap-2">
          ${editBtn}
          ${deleteBtn}
        </div>
      </td>
    </tr>
  `;
}


  function renderTotals(){
    const totalDiminta = ALL_ROWS.reduce((a,r)=> a + (+r.jlh_diminta||0), 0);
    const totalFisik = ALL_ROWS.reduce((a,r)=> a + (+r.jlh_fisik||0), 0);
    sumDimintaEl.textContent = fmt(totalDiminta);
    sumFisikEl.textContent = fmt(totalFisik);
  }

  function renderPage() {
    const total = ALL_ROWS.length, totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (CURRENT_PAGE > totalPages) CURRENT_PAGE = totalPages;
    if (CURRENT_PAGE < 1) CURRENT_PAGE = 1;
    const startIdx = (CURRENT_PAGE - 1) * PER_PAGE, endIdx = Math.min(startIdx + PER_PAGE, total);
    infoTotal.textContent = total.toLocaleString();
    infoFrom.textContent = total ? (startIdx + 1).toLocaleString() : 0;
    infoTo.textContent = endIdx.toLocaleString();
    pageNow.textContent = CURRENT_PAGE.toString();
    pageTotal.textContent = totalPages.toString();
    btnPrev.disabled = CURRENT_PAGE <= 1;
    btnNext.disabled = CURRENT_PAGE >= totalPages;
    if (!total) {
      tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`;
      renderTotals(); return;
    }
    const rows = ALL_ROWS.slice(startIdx, endIdx).map(buildRowHTML).join('');
    tbody.innerHTML = rows || `<tr><td colspan="11" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`;
    renderTotals();
  }

  function refreshList() {
    const fd = new FormData();
    fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
    fd.append('action', 'list');
    fd.append('q', q.value); fd.append('unit_id', selUnit.value);
    fd.append('bulan', selBulan.value); fd.append('tahun', selTahun.value);
    fd.append('nama_bahan', selBahanName.value); fd.append('jenis_pekerjaan', selJenis.value);
    tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8 text-gray-500">Memuat data…</td></tr>`;
    sumDimintaEl.textContent = '0'; sumFisikEl.textContent = '0';
    fetch('pemakaian_crud.php', { method: 'POST', body: fd })
      .then(r => r.json()).then(j => {
        if (!j.success) {
          tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8 text-red-500">${j.message||'Gagal memuat data'}</td></tr>`;
          ALL_ROWS = []; renderPage(); return;
        }
        ALL_ROWS = Array.isArray(j.data) ? j.data : [];
        CURRENT_PAGE = 1;
        renderPage();
      }).catch(err => {
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8 text-red-500">${err?.message||'Network error'}</td></tr>`;
        ALL_ROWS = []; renderPage();
      });
  }

  refreshList();
  [q].forEach(el => el.addEventListener('input', refreshList));
  [selUnit, selTahun, selBahanName, selJenis, selBulan].forEach(el => el.addEventListener('change', refreshList));
  perPageEl.addEventListener('change', () => { PER_PAGE = parseInt(perPageEl.value, 10) || 10; CURRENT_PAGE = 1; renderPage(); });
  btnPrev.addEventListener('click', () => { CURRENT_PAGE -= 1; renderPage(); });
  btnNext.addEventListener('click', () => { CURRENT_PAGE += 1; renderPage(); });

  const bahanSelect = document.getElementById('nama_bahan'), hintSatuan = document.getElementById('hint-satuan-bahan');
  function updateSatuanHint() {
    const opt = bahanSelect.options[bahanSelect.selectedIndex];
    hintSatuan.textContent = opt ? (opt.getAttribute('data-satuan') || '-') : '-';
  }
  bahanSelect.addEventListener('change', updateSatuanHint);

  $('#btn-add').addEventListener('click', () => {
    form.reset();
    formId.value = ''; formAction.value = 'store';
    title.textContent = 'Input Pemakaian Baru';
    if (selUnit.value) document.getElementById('unit_id').value = selUnit.value;
    if (selBulan.value) document.getElementById('bulan').value = selBulan.value;
    document.getElementById('tahun').value = selTahun.value;
    updateSatuanHint();
    open();
  });
  btnClose.addEventListener('click', close);
  btnCancel.addEventListener('click', close);

  document.body.addEventListener('click', (e) => {
    const t = e.target;
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if (t.classList.contains('btn-edit') && !IS_STAF) {
      const row = JSON.parse(decodeURIComponent(t.dataset.json));
      form.reset();
      formAction.value = 'update';
      formId.value = row.id;
      title.textContent = 'Edit Pemakaian';
      ['no_dokumen','bulan','tahun','nama_bahan','jenis_pekerjaan','jlh_diminta','jlh_fisik'].forEach(k=>{
        const el=document.getElementById(k); if(el) el.value=row[k] ?? '';
      });
      document.getElementById('unit_id').value = row.unit_id ?? '';
      document.getElementById('fisik_label').value= row.fisik_label ?? '';
      document.getElementById('kebun_label').value= row.kebun_label || '';
      document.getElementById('keterangan').value = row.keterangan_clean || '';
      updateSatuanHint();
      open();
    }
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if (t.classList.contains('btn-delete') && !IS_STAF) {
      const id = t.dataset.id;
      Swal.fire({
        title: 'Hapus data ini?', text: 'Tindakan ini tidak dapat dibatalkan.', icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal'
      }).then((res) => {
        if (!res.isConfirmed) return;
        const fd = new FormData();
        fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('pemakaian_crud.php', { method: 'POST', body: fd })
          .then(r => r.json()).then(j => {
            if (j.success) { Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success'); refreshList(); }
            else Swal.fire('Gagal', j.message || 'Tidak bisa menghapus', 'error');
          }).catch(err => Swal.fire('Error', err?.message || 'Network error', 'error'));
      });
    }
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const req = ['no_dokumen','unit_id','bulan','tahun','nama_bahan','jenis_pekerjaan'];
    for (const id of req) {
      const el = document.getElementById(id);
      if (!el || !el.value) { Swal.fire('Validasi', `Field ${id.replace('_',' ')} wajib diisi.`, 'warning'); return; }
    }
    const fd = new FormData(form);
    fetch('pemakaian_crud.php', { method: 'POST', body: fd })
      .then(r => r.json()).then(j => {
        if (j.success) {
          modal.classList.add('hidden');
          Swal.fire({ icon: 'success', title: 'Berhasil', text: j.message, timer: 1400, showConfirmButton: false });
          refreshList();
        } else {
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
          Swal.fire('Gagal', html || 'Terjadi kesalahan.', 'error');
        }
      })
      .catch(err => Swal.fire('Error', err?.message || 'Network error', 'error'));
  });

  document.getElementById('btn-export-excel').addEventListener('click', () => {
    const qs = new URLSearchParams({
      csrf_token: '<?= htmlspecialchars($CSRF) ?>', q: q.value || '', unit_id: selUnit.value || '',
      bulan: selBulan.value || '', tahun: selTahun.value || '', nama_bahan: selBahanName.value || '',
      jenis_pekerjaan: selJenis.value || ''
    }).toString();
    window.open('cetak/pemakaian_export_excel.php?' + qs, '_blank');
  });
  document.getElementById('btn-export-pdf').addEventListener('click', () => {
    const qs = new URLSearchParams({
      csrf_token: '<?= htmlspecialchars($CSRF) ?>', q: q.value || '', unit_id: selUnit.value || '',
      bulan: selBulan.value || '', tahun: selTahun.value || '', nama_bahan: selBahanName.value || '',
      jenis_pekerjaan: selJenis.value || ''
    }).toString();
    window.open('cetak/pemakaian_export_pdf.php?' + qs, '_blank');
  });
});
</script>