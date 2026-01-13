<?php
// pemakaian.php
// MODIFIKASI FULL: Grid Table, Auto Filter Date, New Export Buttons

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php"); exit;
}

// --- Role user ---
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// --- LOGIKA TANGGAL OTOMATIS ---
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$bulanIndex = (int)date('n') - 1; 
$bulanNowName = $bulanList[$bulanIndex]; // Bulan saat ini
$tahunNow = (int)date('Y');              // Tahun saat ini

// Master Data
$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit ASC")->fetchAll(PDO::FETCH_ASSOC);
$kebunList = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$bahanList = $conn->query("SELECT b.nama_bahan, s.nama AS satuan FROM md_bahan_kimia b LEFT JOIN md_satuan s ON s.id=b.satuan_id ORDER BY b.nama_bahan")->fetchAll(PDO::FETCH_ASSOC);
$jenisList = $conn->query("SELECT nama FROM md_jenis_pekerjaan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$fisikList = $conn->query("SELECT nama FROM md_fisik ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'pemakaian';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  /* --- CONTAINER & TABLE GRID STYLE (TM Style) --- */
  .sticky-container {
    max-height: 72vh; /* Tinggi maksimal area scroll */
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }

  table.table-grid {
    width: 100%;
    border-collapse: separate; /* Wajib separate agar sticky berfungsi */
    border-spacing: 0;
    min-width: 1600px; /* Lebar min agar kolom tidak gepeng */
  }

  /* Garis Grid Penuh */
  table.table-grid th, 
  table.table-grid td {
    padding: 0.65rem 0.75rem;
    font-size: 0.85rem;
    white-space: nowrap;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
  }

  table.table-grid th:last-child, 
  table.table-grid td:last-child {
    border-right: none;
  }

  /* Header Sticky & Tinggi */
  table.table-grid thead th {
    position: sticky;
    top: 0;
    background: #059fd3; /* Warna Biru */
    color: #fff;
    z-index: 10;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    height: 55px; 
    vertical-align: middle;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  table.table-grid tbody tr:hover td {
    background-color: #f0f9ff;
  }

  /* Badge Keterangan */
  .badge-ket {
    display: inline-block; padding: 2px 8px; border-radius: 12px;
    font-size: 0.75rem; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;
    max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

  /* Utilities */
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .text-left { text-align: left; }
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
  
  /* Action Buttons */
  .btn-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 6px; transition: 0.2s; border: 1px solid transparent;
  }
  .btn-icon:hover { background: #f1f5f9; border-color: #cbd5e1; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Permintaan Bahan Kimia</h1>
      <p class="text-gray-500 text-sm mt-1">Kelola pemakaian bahan kimia per Unit/Divisi</p>
    </div>

    <div class="flex gap-2">
      <button id="btn-export-excel" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2 shadow-sm transition">
        <i class="ti ti-file-spreadsheet"></i> Excel
      </button>
      <button id="btn-export-pdf" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 shadow-sm transition">
        <i class="ti ti-file-type-pdf"></i> PDF
      </button>

      <?php if (!$isStaf): ?>
      <button id="btn-add" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900 flex items-center gap-2 shadow-sm transition ml-2">
        <i class="ti ti-plus"></i> Input Data
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
    <div class="md:col-span-1">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pencarian</label>
        <input id="filter-q" type="text" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Cari...">
    </div>
    <div class="md:col-span-1">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit</label>
        <select id="filter-unit" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
          <option value="">Semua Unit</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
          <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-1">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
        <select id="filter-bulan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
          <option value="">Semua Bulan</option>
          <?php foreach ($bulanList as $b): ?>
            <option value="<?= $b ?>" <?= ($b === $bulanNowName) ? 'selected' : '' ?>><?= $b ?></option>
          <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-1">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
        <select id="filter-tahun" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
          <?php for ($y = $tahunNow - 2; $y <= $tahunNow + 2; $y++): ?>
            <option value="<?= $y ?>" <?= ($y === $tahunNow) ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
    </div>
    <div class="md:col-span-1">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bahan</label>
        <select id="filter-bahan-name" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
          <option value="">Semua Bahan</option>
          <?php foreach ($bahanList as $b): ?>
            <option value="<?= htmlspecialchars($b['nama_bahan']) ?>"><?= htmlspecialchars($b['nama_bahan']) ?></option>
          <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-1">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pekerjaan</label>
        <select id="filter-jenis" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
          <option value="">Semua Jenis</option>
          <?php foreach ($jenisList as $j): ?>
            <option value="<?= htmlspecialchars($j['nama']) ?>"><?= htmlspecialchars($j['nama']) ?></option>
          <?php endforeach; ?>
        </select>
    </div>
  </div>

  <div class="sticky-container">
    <table class="table-grid">
        <thead>
        <tr>
            <th class="text-left">No. Dokumen</th>
            <th class="text-left">Kebun</th>
            <th class="text-left">Unit</th>
            <th class="text-center">Periode</th>
            <th class="text-left">Nama Bahan</th>
            <th class="text-left">Jenis Pekerjaan</th>
            <th class="text-right">Jlh Diminta</th>
            <th class="text-right">Jlh Fisik</th>
            <th class="text-center">Dokumen</th>
            <th class="text-left">Keterangan</th>
            <?php if (!$isStaf): ?><th class="text-center" style="width:100px">Aksi</th><?php endif; ?>
        </tr>
        </thead>
        <tbody id="tbody-data" class="text-gray-800">
        <tr><td colspan="<?= $isStaf ? 10 : 11 ?>" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>
        </tbody>
    </table>
  </div>

  <div class="flex flex-col md:flex-row items-center justify-between gap-4 bg-gray-50 p-3 rounded-b-xl border border-gray-200 border-t-0">
     <div class="text-sm text-gray-600">
        Menampilkan <span id="info-from" class="font-bold">0</span>–<span id="info-to" class="font-bold">0</span> dari <span id="info-total" class="font-bold">0</span> data
     </div>
     
     <div class="flex gap-4 text-sm">
        <div class="bg-white px-3 py-1 border rounded shadow-sm">
            <span class="text-gray-500">Total Diminta:</span> 
            <span id="sum-diminta" class="font-bold text-blue-600">0</span>
        </div>
        <div class="bg-white px-3 py-1 border rounded shadow-sm">
            <span class="text-gray-500">Total Fisik:</span> 
            <span id="sum-fisik" class="font-bold text-green-600">0</span>
        </div>
     </div>

     <div class="flex items-center gap-2">
        <select id="per-page" class="border rounded px-2 py-1 text-sm focus:outline-none">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        <div class="inline-flex gap-1">
            <button id="btn-prev" class="px-3 py-1 border rounded hover:bg-gray-100 text-sm" disabled>Prev</button>
            <button id="btn-next" class="px-3 py-1 border rounded hover:bg-gray-100 text-sm" disabled>Next</button>
        </div>
     </div>
  </div>
</div>

<?php if (!$isStaf): ?>
<div id="crud-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-opacity">
  <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-4xl transform scale-100 transition-transform">
    <div class="flex items-center justify-between mb-6 border-b pb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-900">Input Pemakaian</h3>
      <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600 transition">&times;</button>
    </div>
    <form id="crud-form" novalidate enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">No Dokumen</label>
          <input type="text" id="no_dokumen" name="no_dokumen" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nama Kebun</label>
          <select id="kebun_label" name="kebun_label" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <option value="">-- Pilih Kebun --</option>
            <?php foreach ($kebunList as $k): ?>
              <option value="<?= htmlspecialchars($k['nama_kebun']) ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Unit/Divisi</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Bulan</label>
                <select id="bulan" name="bulan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
                    <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tahun</label>
                <select id="tahun" name="tahun" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
                    <?php for ($y = $tahunNow - 1; $y <= $tahunNow + 3; $y++): ?>
                    <option value="<?= $y ?>" <?= $y === $tahunNow ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nama Bahan</label>
          <select id="nama_bahan" name="nama_bahan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <option value="">-- Pilih Bahan --</option>
            <?php foreach ($bahanList as $b): ?>
              <option value="<?= htmlspecialchars($b['nama_bahan']) ?>" data-satuan="<?= htmlspecialchars($b['satuan'] ?? '') ?>">
                <?= htmlspecialchars($b['nama_bahan']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="text-[10px] text-gray-500 mt-1">Satuan: <span id="hint-satuan-bahan" class="font-bold">-</span></p>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Jenis Pekerjaan</label>
          <select id="jenis_pekerjaan" name="jenis_pekerjaan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <option value="">-- Pilih Jenis --</option>
            <?php foreach ($jenisList as $j): ?>
              <option value="<?= htmlspecialchars($j['nama']) ?>"><?= htmlspecialchars($j['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Jumlah Diminta</label>
          <input type="number" step="0.01" id="jlh_diminta" name="jlh_diminta" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" value="0">
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Jumlah Fisik</label>
                <input type="number" step="0.01" id="jlh_fisik" name="jlh_fisik" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" value="0">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Ket. Fisik</label>
                <select id="fisik_label" name="fisik_label" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
                    <option value="">— Pilih —</option>
                    <?php foreach ($fisikList as $f): ?>
                    <option value="<?= htmlspecialchars($f['nama']) ?>"><?= htmlspecialchars($f['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Dokumen Pendukung</label>
          <input type="file" id="dokumen" name="dokumen" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Keterangan</label>
          <input type="text" id="keterangan" name="keterangan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Catatan tambahan">
        </div>
      </div>
      
      <div class="flex justify-end gap-3 mt-8 pt-4 border-t border-gray-100">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50 transition">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 transition shadow-lg shadow-cyan-500/30">Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>


<?php include_once '../layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;
  const COLSPAN = IS_STAF ? 10 : 11;

  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data');
  
  // Filter Elements
  const q = $('#filter-q'), selUnit = $('#filter-unit'), selBulan = $('#filter-bulan');
  const selTahun = $('#filter-tahun'), selBahanName = $('#filter-bahan-name'), selJenis = $('#filter-jenis');
  
  // Pagination & Info
  const perPageEl = $('#per-page'), btnPrev = $('#btn-prev'), btnNext = $('#btn-next');
  const infoFrom = $('#info-from'), infoTo = $('#info-to'), infoTotal = $('#info-total');
  
  const sumDimintaEl = $('#sum-diminta'), sumFisikEl = $('#sum-fisik');

  let ALL_ROWS = [], CURRENT_PAGE = 1, PER_PAGE = parseInt(perPageEl.value, 10) || 10;
  const fmt = n => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });

  /* === Logic Export (Fixed) === */
  const handleExport = (format) => {
    // Ambil value langsung dari dropdown
    const params = new URLSearchParams({
      csrf_token: '<?= htmlspecialchars($CSRF) ?>',
      q: q.value,
      unit_id: selUnit.value,
      bulan: selBulan.value,
      tahun: selTahun.value,
      nama_bahan: selBahanName.value,
      jenis_pekerjaan: selJenis.value
    });
    const url = `cetak/pemakaian_export_${format}.php?${params.toString()}`;
    window.open(url, '_blank');
  };
  $('#btn-export-excel').onclick = () => handleExport('excel');
  $('#btn-export-pdf').onclick = () => handleExport('pdf');

  /* === Logic Sort & Render === */
  const MONTH_IDX = { 'Januari':1,'Februari':2,'Maret':3,'April':4,'Mei':5,'Juni':6,'Juli':7,'Agustus':8,'September':9,'Oktober':10,'November':11,'Desember':12 };
  const norm = v => (v||'').toString().trim().toLowerCase();

  function parseRowDate(r){
    if (r.created_at) { const d = new Date(r.created_at); if (!isNaN(d)) return d.getTime(); }
    const th = parseInt(r.tahun,10);
    const bln = MONTH_IDX[r.bulan] || 0;
    if (!isNaN(th) && bln > 0) return new Date(th, bln-1, 1).getTime();
    return 0;
  }

  function compareDescByLatest(a,b){
    const ta = parseRowDate(a), tb = parseRowDate(b);
    if (tb !== ta) return tb - ta;
    return (b.id || 0) - (a.id || 0);
  }

  function buildRowHTML(row) {
    const suffix = row.fisik_label ? ` <span class="text-xs text-gray-500">(${row.fisik_label})</span>` : '';
    const docLink = row.dokumen_path ? `<a href="${row.dokumen_path}" target="_blank" class="text-blue-600 underline hover:text-blue-800">Lihat</a>` : '-';
    const ketText = (row.keterangan_clean && String(row.keterangan_clean).trim()) ? row.keterangan_clean : '-';
    
    let actionCell = '';
    if (!IS_STAF) {
      actionCell = `
        <td class="text-center">
          <div class="flex items-center justify-center gap-1">
            <button class="btn-icon text-cyan-600 hover:text-cyan-800" data-json="${encodeURIComponent(JSON.stringify(row))}" title="Edit">
                <i class="ti ti-pencil"></i>
            </button>
            <button class="btn-icon text-red-600 hover:text-red-800" data-id="${row.id}" title="Hapus">
                <i class="ti ti-trash"></i>
            </button>
          </div>
        </td>`;
    }

    return `
      <tr class="hover:bg-blue-50 transition-colors">
        <td class="font-semibold text-gray-700">${row.no_dokumen || '-'}</td>
        <td>${row.kebun_label || '-'}</td>
        <td>${row.unit_nama || '-'}</td>
        <td class="text-center">${row.bulan || ''} ${row.tahun || ''}</td>
        <td>${row.nama_bahan || '-'}</td>
        <td>${row.jenis_pekerjaan || '-'}</td>
        <td class="text-right font-mono">${fmt(row.jlh_diminta)}</td>
        <td class="text-right font-mono">${fmt(row.jlh_fisik)}${suffix}</td>
        <td class="text-center">${docLink}</td>
        <td><span class="badge-ket" title="${ketText}">${ketText}</span></td>
        ${actionCell}
      </tr>`;
  }

  function renderTotals(){
    const totalDiminta = ALL_ROWS.reduce((a,r)=> a + (+r.jlh_diminta||0), 0);
    const totalFisik = ALL_ROWS.reduce((a,r)=> a + (+r.jlh_fisik||0), 0);
    sumDimintaEl.textContent = fmt(totalDiminta);
    sumFisikEl.textContent = fmt(totalFisik);
  }

  function renderPage() {
    const total = ALL_ROWS.length;
    const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (CURRENT_PAGE > totalPages) CURRENT_PAGE = totalPages;
    const startIdx = (CURRENT_PAGE - 1) * PER_PAGE;
    const endIdx = Math.min(startIdx + PER_PAGE, total);
    
    infoTotal.textContent = total;
    infoFrom.textContent = total ? (startIdx + 1) : 0;
    infoTo.textContent = endIdx;
    
    btnPrev.disabled = CURRENT_PAGE <= 1;
    btnNext.disabled = CURRENT_PAGE >= totalPages;

    if (!total) {
      tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-gray-500 italic">Tidak ada data ditemukan.</td></tr>`;
      renderTotals();
      return;
    }

    const rows = ALL_ROWS.slice(startIdx, endIdx).map(buildRowHTML).join('');
    tbody.innerHTML = rows;
    renderTotals();
  }

  function refreshList() {
    const fd = new FormData();
    fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
    fd.append('action', 'list');
    fd.append('q', q.value);
    fd.append('unit_id', selUnit.value);
    fd.append('bulan', selBulan.value);
    fd.append('tahun', selTahun.value);
    fd.append('nama_bahan', selBahanName.value);
    fd.append('jenis_pekerjaan', selJenis.value);

    tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>`;

    fetch('pemakaian_crud.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        if (!j.success) {
          tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-red-500">${j.message||'Gagal memuat data'}</td></tr>`;
          ALL_ROWS = []; renderPage(); return;
        }
        ALL_ROWS = Array.isArray(j.data) ? j.data.slice().sort(compareDescByLatest) : [];
        CURRENT_PAGE = 1;
        renderPage();
      })
      .catch(err => {
        tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-red-500">${err?.message||'Network error'}</td></tr>`;
      });
  }

  // Initial Load
  refreshList();
  
  // Realtime Filters
  [q, selUnit, selBulan, selTahun, selBahanName, selJenis].forEach(el => el.addEventListener('change', refreshList));
  perPageEl.addEventListener('change', () => { PER_PAGE = parseInt(perPageEl.value, 10) || 10; CURRENT_PAGE = 1; renderPage(); });
  btnPrev.addEventListener('click', () => { if(CURRENT_PAGE>1){ CURRENT_PAGE--; renderPage(); } });
  btnNext.addEventListener('click', () => { CURRENT_PAGE++; renderPage(); });

  /* === CRUD (Non-Staf) === */
  if (!IS_STAF) {
    const modal = $('#crud-modal'), form = $('#crud-form');
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    const bahanSelect = $('#nama_bahan'), hintSatuan = $('#hint-satuan-bahan');

    function updateSatuanHint() {
      const opt = bahanSelect.options[bahanSelect.selectedIndex];
      hintSatuan.textContent = opt ? (opt.getAttribute('data-satuan') || '-') : '-';
    }
    bahanSelect.addEventListener('change', updateSatuanHint);

    $('#btn-add')?.addEventListener('click', () => {
      form.reset();
      $('#form-action').value = 'store';
      $('#form-id').value = '';
      // Pre-fill modal with current filter
      if(selUnit.value) $('#unit_id').value = selUnit.value;
      if(selBulan.value) $('#bulan').value = selBulan.value;
      if(selTahun.value) $('#tahun').value = selTahun.value;
      updateSatuanHint();
      open();
    });

    $('#btn-close')?.addEventListener('click', close);
    $('#btn-cancel')?.addEventListener('click', close);

    document.body.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;

      if (btn.classList.contains('btn-icon') && btn.title==='Edit') {
        const row = JSON.parse(decodeURIComponent(btn.dataset.json));
        form.reset();
        $('#form-action').value = 'update';
        $('#form-id').value = row.id;
        ['no_dokumen','bulan','tahun','nama_bahan','jenis_pekerjaan','jlh_diminta','jlh_fisik','keterangan'].forEach(k=>{
          const el=$(`#${k}`); if(el) el.value=row[k] ?? '';
        });
        $('#unit_id').value = row.unit_id ?? '';
        $('#fisik_label').value= row.fisik_label ?? '';
        $('#kebun_label').value= row.kebun_label || '';
        updateSatuanHint();
        open();
      }

      if (btn.classList.contains('btn-icon') && btn.title==='Hapus') {
        const id = btn.dataset.id;
        Swal.fire({
          title: 'Hapus data ini?', text: 'Tindakan tidak dapat dikembalikan.', icon: 'warning',
          showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
          confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal'
        }).then(res => {
          if (!res.isConfirmed) return;
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action', 'delete'); fd.append('id', id);
          fetch('pemakaian_crud.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
              if (j.success) { Swal.fire('Terhapus!', '', 'success'); refreshList(); }
              else Swal.fire('Gagal', j.message, 'error');
            });
        });
      }
    });

    form.addEventListener('submit', e => {
      e.preventDefault();
      const req = ['unit_id','bulan','tahun','nama_bahan','jenis_pekerjaan'];
      for (const id of req) {
        const el = $(`#${id}`);
        if (!el || !el.value) { Swal.fire('Validasi', `Field ${id.replace('_',' ')} wajib diisi.`, 'warning'); return; }
      }
      const fd = new FormData(form);
      fetch('pemakaian_crud.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
          if (j.success) {
            close(); Swal.fire({ icon: 'success', title: 'Berhasil', text: j.message, timer: 1300, showConfirmButton: false });
            refreshList();
          } else { Swal.fire('Gagal', j.message, 'error'); }
        });
    });
  }
});
</script>