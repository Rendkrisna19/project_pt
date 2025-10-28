<?php
// pemakaian.php — MOD: Role 'staf' tidak bisa edit/hapus/input

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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
  /* sticky thead */
  .table-sticky thead th{
    position: sticky; top: 0; z-index: 10;
  }
  /* badge hijau untuk kolom keterangan */
  .badge-emerald {
    display:inline-flex; align-items:center;
    padding:2px 8px; border-radius:9999px; font-size:.8rem; font-weight:600;
    color:#047857; background:#ecfdf5; box-shadow:inset 0 0 0 1px #6ee7b7;
    max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .btn-icon { background: transparent; border: none; padding: 0.25rem; }
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-3xl font-bold text-gray-800">🧪 Permintaan / Pemakaian Bahan Kimia</h1>
      <p class="text-gray-500 mt-1">Kelola pemakaian bahan kimia per Unit/Divisi</p>
    </div>

    <div class="flex gap-3">
      <button id="btn-export-excel" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50 text-gray-800">
        <i class="ti ti-file-spreadsheet text-emerald-600 text-lg"></i>
        <span>Export Excel</span>
      </button>
      <button id="btn-export-pdf" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50 text-gray-800">
        <i class="ti ti-file-type-pdf text-red-600 text-lg"></i>
        <span>Cetak PDF</span>
      </button>

      <?php if (!$isStaf): // MODIFIKASI: Sembunyikan tombol jika staf ?>
      <button id="btn-add" class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-black flex items-center gap-2">
        <i class="ti ti-plus"></i>
        <span>Input Pemakaian</span>
      </button>
      <?php endif; ?>
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
              <?php if (!$isStaf): // MODIFIKASI: Sembunyikan kolom jika staf ?>
                <th class="py-3 px-4 text-center">Aksi</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody id="tbody-data" class="text-gray-800">
            <tr><td colspan="<?= $isStaf ? 10 : 11 ?>" class="text-center py-8 text-gray-500">Memuat data…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 p-3">
      <div class="text-sm text-gray-700">
        Halaman <span id="page-now" class="font-semibold">1</span> dari <span id="page-total" class="font-semibold">1</span>
      </div>
      <div class="inline-flex gap-2">
        <button id="btn-prev" class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-gray-800" disabled>Prev</button>
        <button id="btn-next" class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-gray-800" disabled>Next</button>
      </div>
    </div>
    <div class="p-4 bg-green-50 border-t-4 border-green-600 flex flex-col md:flex-row md:items-center md:justify-end gap-6">
      <div class="text-sm text-gray-800">Σ Jlh Bahan Diminta: <span id="sum-diminta" class="font-bold">0</span></div>
      <div class="text-sm text-gray-800">Σ Jumlah Fisik: <span id="sum-fisik" class="font-bold">0</span></div>
    </div>
  </div>
</div>

<?php if (!$isStaf): // MODIFIKASI: Sembunyikan seluruh modal jika staf ?>
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
<?php endif; ?>


<?php include_once '../layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // --- MODIFIKASI: Kirim role ke JavaScript ---
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;
  const COLSPAN = IS_STAF ? 10 : 11;

  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data'), q = $('#filter-q'), selUnit = $('#filter-unit'), selBulan = $('#filter-bulan');
  const selTahun = $('#filter-tahun'), selBahanName = $('#filter-bahan-name'), selJenis = $('#filter-jenis');
  const perPageEl = $('#per-page'), btnPrev = $('#btn-prev'), btnNext = $('#btn-next'), infoFrom = $('#info-from');
  const infoTo = $('#info-to'), infoTotal = $('#info-total'), pageNow = $('#page-now'), pageTotal = $('#page-total');
  const sumDimintaEl = $('#sum-diminta'), sumFisikEl = $('#sum-fisik');

  let ALL_ROWS = [], CURRENT_PAGE = 1, PER_PAGE = parseInt(perPageEl.value, 10) || 10;
  const fmt = (n) => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });

  function buildRowHTML(row) {
    const jumlahDiminta = fmt(row.jlh_diminta);
    const jumlahFisik   = fmt(row.jlh_fisik);
    const fisikSuffix   = row.fisik_label ? ` <span class="text-xs text-gray-500">(${row.fisik_label})</span>` : '';
    const docLink       = row.dokumen_path
      ? `<a href="${row.dokumen_path}" target="_blank" class="underline text-blue-600 hover:text-blue-800">Lihat</a>`
      : 'N/A';
    const ketText = (row.keterangan_clean && String(row.keterangan_clean).trim()) ? row.keterangan_clean : '-';
    const keteranganBadge = `<span class="badge-emerald" title="${ketText}">${ketText}</span>`;

    // --- MODIFIKASI: Kolom aksi hanya dibuat jika bukan staf ---
    let actionCell = '';
    if (!IS_STAF) {
      const payload = encodeURIComponent(JSON.stringify(row || {}));
      actionCell = `
        <td class="py-3 px-4">
          <div class="flex items-center justify-center gap-2">
            <button class="btn-edit btn-icon text-blue-600 hover:text-blue-800" data-json="${payload}" title="Edit">
              <i class="ti ti-pencil text-lg"></i>
            </button>
            <button class="btn-delete btn-icon text-red-600 hover:text-red-800" data-id="${row.id}" title="Hapus">
              <i class="ti ti-trash text-lg"></i>
            </button>
          </div>
        </td>`;
    }

    return `
      <tr class="border-b hover:bg-gray-50">
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
        ${actionCell}
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
    const emptyRow = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`;

    if (!total) {
      tbody.innerHTML = emptyRow;
      renderTotals(); return;
    }
    const rows = ALL_ROWS.slice(startIdx, endIdx).map(buildRowHTML).join('');
    tbody.innerHTML = rows || emptyRow;
    renderTotals();
  }

  function refreshList() {
    const fd = new FormData();
    fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
    fd.append('action', 'list');
    fd.append('q', q.value); fd.append('unit_id', selUnit.value);
    fd.append('bulan', selBulan.value); fd.append('tahun', selTahun.value);
    fd.append('nama_bahan', selBahanName.value); fd.append('jenis_pekerjaan', selJenis.value);
    tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-gray-500">Memuat data…</td></tr>`;
    sumDimintaEl.textContent = '0'; sumFisikEl.textContent = '0';
    fetch('pemakaian_crud.php', { method: 'POST', body: fd })
      .then(r => r.json()).then(j => {
        if (!j.success) {
          tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-red-500">${j.message||'Gagal memuat data'}</td></tr>`;
          ALL_ROWS = []; renderPage(); return;
        }
        ALL_ROWS = Array.isArray(j.data) ? j.data : [];
        CURRENT_PAGE = 1;
        renderPage();
      }).catch(err => {
        tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-red-500">${err?.message||'Network error'}</td></tr>`;
        ALL_ROWS = []; renderPage();
      });
  }

  refreshList();
  [q].forEach(el => el.addEventListener('input', refreshList));
  [selUnit, selTahun, selBahanName, selJenis, selBulan].forEach(el => el.addEventListener('change', refreshList));
  perPageEl.addEventListener('change', () => { PER_PAGE = parseInt(perPageEl.value, 10) || 10; CURRENT_PAGE = 1; renderPage(); });
  btnPrev.addEventListener('click', () => { if(CURRENT_PAGE > 1){ CURRENT_PAGE -= 1; renderPage();} });
  btnNext.addEventListener('click', () => { CURRENT_PAGE += 1; renderPage(); });


  // --- MODIFIKASI: Seluruh event CRUD hanya untuk non-staf ---
  if (!IS_STAF) {
    const modal = $('#crud-modal'), btnClose = $('#btn-close'), btnCancel = $('#btn-cancel'), form = $('#crud-form');
    const formAction = $('#form-action'), formId = $('#form-id'), title = $('#modal-title');
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    const close= () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    const bahanSelect = $('#nama_bahan'), hintSatuan = $('#hint-satuan-bahan');

    function updateSatuanHint() {
      const opt = bahanSelect.options[bahanSelect.selectedIndex];
      hintSatuan.textContent = opt ? (opt.getAttribute('data-satuan') || '-') : '-';
    }
    bahanSelect.addEventListener('change', updateSatuanHint);

    $('#btn-add').addEventListener('click', () => {
      form.reset();
      formId.value = ''; formAction.value = 'store';
      title.textContent = 'Input Pemakaian Baru';
      if (selUnit.value) $('#unit_id').value = selUnit.value;
      if (selBulan.value) $('#bulan').value = selBulan.value;
      $('#tahun').value = selTahun.value;
      updateSatuanHint();
      open();
    });
    btnClose.addEventListener('click', close);
    btnCancel.addEventListener('click', close);

    document.body.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;

      if (btn.classList.contains('btn-edit')) {
        const row = JSON.parse(decodeURIComponent(btn.dataset.json));
        form.reset();
        formAction.value = 'update';
        formId.value = row.id;
        title.textContent = 'Edit Pemakaian';
        ['no_dokumen','bulan','tahun','nama_bahan','jenis_pekerjaan','jlh_diminta','jlh_fisik'].forEach(k=>{
          const el=$(`#${k}`); if(el) el.value=row[k] ?? '';
        });
        $('#unit_id').value = row.unit_id ?? '';
        $('#fisik_label').value= row.fisik_label ?? '';
        $('#kebun_label').value= row.kebun_label || '';
        $('#keterangan').value = row.keterangan_clean || '';
        updateSatuanHint();
        open();
      }
      
      if (btn.classList.contains('btn-delete')) {
        const id = btn.dataset.id;
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
      const req = ['unit_id','bulan','tahun','nama_bahan','jenis_pekerjaan'];
      for (const id of req) {
        const el = $(`#${id}`);
        if (!el || !el.value) { Swal.fire('Validasi', `Field ${id.replace('_',' ')} wajib diisi.`, 'warning'); return; }
      }
      const fd = new FormData(form);
      fetch('pemakaian_crud.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(j => {
          if (j.success) {
            close();
            Swal.fire({ icon: 'success', title: 'Berhasil', text: j.message, timer: 1400, showConfirmButton: false });
            refreshList();
          } else {
            const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
            Swal.fire('Gagal', html || 'Terjadi kesalahan.', 'error');
          }
        })
        .catch(err => Swal.fire('Error', err?.message || 'Network error', 'error'));
    });
  } // end if !IS_STAF

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