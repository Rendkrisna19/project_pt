<?php
// lm76.php — Modifikasi Full: Desain Tabel Grid (TM Style), Sticky Header, Auto Filter

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

/* masters */
$units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$kebun  = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$ttList = $conn->query("SELECT DISTINCT tahun FROM md_tahun_tanam WHERE tahun IS NOT NULL AND tahun<>'' ORDER BY tahun")->fetchAll(PDO::FETCH_COLUMN);

// --- LOGIKA TANGGAL DEFAULT ---
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$bulanIndex = (int)date('n') - 1; 
$bulanNowName = $bulanList[$bulanIndex]; 
$tahunNow = (int)date('Y');

// Filter default
$f_kebun = $_GET['kebun_id'] ?? '';
$f_unit  = $_GET['unit_id'] ?? '';
$f_tt    = $_GET['tt'] ?? '';
$f_bulan = $_GET['bulan'] ?? $bulanNowName;
$f_tahun = $_GET['tahun'] ?? $tahunNow;

// Build Query String untuk Export Link (agar tetap sinkron dengan filter saat ini)
$exportParams = http_build_query([
    'csrf_token' => $CSRF,
    'kebun_id' => $f_kebun,
    'unit_id'  => $f_unit,
    'bulan'    => $f_bulan,
    'tahun'    => $f_tahun,
    'tt'       => $f_tt
]);

$currentPage = 'lm76';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  /* --- CONTAINER & TABLE GRID STYLE (TM Style) --- */
  .sticky-container {
    max-height: 75vh;
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }

  table.table-grid {
    width: 100%;
    border-collapse: separate; 
    border-spacing: 0;
    min-width: 1800px; 
  }

  /* Garis Grid Penuh */
  table.table-grid th, 
  table.table-grid td {
    padding: 0.65rem 0.75rem;
    font-size: 0.85rem;
    white-space: nowrap;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0; /* Garis vertikal */
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

  /* Footer Sticky */
  table.table-grid tfoot td {
    position: sticky;
    bottom: 0;
    background: #059fd3;
    color: #fff;
    font-weight: 700;
    z-index: 10;
    border-top: 2px solid rgba(255,255,255,0.3);
  }

  /* Grouping Rows Style */
  .unit-head td { background: #e0f2fe; color: #0369a1; font-weight: 800; border-top: 2px solid #bae6fd; }
  .unit-sub td  { background: #f0f9ff; color: #0c4a6e; font-weight: 700; border-top: 1px solid #e2e8f0; }

  /* Utilities */
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
  .btn-icon { background: transparent; border: none; padding: 0.25rem; cursor: pointer; }
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .text-left { text-align: left; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">LM-76 — Statistik Panen</h1>
      <p class="text-gray-500 text-sm mt-1">Laporan Harian/Bulanan • Grouping per Unit</p>
    </div>
    <div class="flex gap-2">
      <?php if (!$isStaf): ?>
      <button id="btn-add" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2 shadow-sm transition">
        <i class="ti ti-plus"></i><span>Input LM-76</span>
      </button>
      <?php endif; ?>
      
      <a href="cetak/lm76_export_excel.php?<?= $exportParams ?>" target="_blank" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2 shadow-sm transition no-underline">
        <i class="ti ti-file-spreadsheet"></i><span>Excel</span>
      </a>
      <a href="cetak/lm76_export_pdf.php?<?= $exportParams ?>" target="_blank" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 shadow-sm transition no-underline">
        <i class="ti ti-file-type-pdf"></i><span>PDF</span>
      </a>
    </div>
  </div>

  <form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-5 gap-3">
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kebun</label>
        <select name="kebun_id" id="filter-kebun" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">Semua Kebun</option>
            <?php foreach ($kebun as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= ((string)$f_kebun === (string)$k['id'])?'selected':'' ?>><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit/Defisi</label>
        <select name="unit_id" id="filter-unit" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">Semua Unit</option>
            <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ((string)$f_unit === (string)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
        <select name="bulan" id="filter-bulan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">Semua Bulan</option>
            <?php foreach ($bulanList as $b): ?>
            <option value="<?= $b ?>" <?= ($f_bulan === $b)?'selected':'' ?>><?= $b ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
        <select name="tahun" id="filter-tahun" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <?php for ($y=$tahunNow-2;$y<=$tahunNow+2;$y++): ?>
            <option value="<?= $y ?>" <?= ((int)$f_tahun === $y)?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">T. Tanam</label>
        <select name="tt" id="filter-tt" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">Semua T. Tanam</option>
            <?php foreach ($ttList as $tt): ?>
            <option value="<?= htmlspecialchars($tt) ?>" <?= ($f_tt === $tt)?'selected':'' ?>><?= htmlspecialchars($tt) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
  </form>

  <div class="sticky-container">
    <table class="table-grid">
      <thead>
        <tr>
          <th class="text-left">Tahun</th>
          <th class="text-left">Kebun</th>
          <th class="text-left">Unit/Defisi</th>
          <th class="text-left">Periode</th>
          <th class="text-left">T. Tanam</th>
          <th class="text-right">Luas (Ha)</th>
          <th class="text-right">Invt Pokok</th>
          <th class="text-right">Anggaran (Kg)</th>
          <th class="text-right">Realisasi (Kg)</th>
          <th class="text-right">Jumlah Tandan</th>
          <th class="text-right">Jumlah HK</th>
          <th class="text-right">Panen (Ha)</th>
          <th class="text-right">Frekuensi</th>
          <?php if (!$isStaf): ?><th class="text-center">Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="tbody-data" class="bg-white text-gray-800">
        <tr><td colspan="<?= $isStaf ? 13 : 14 ?>" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>
      </tbody>
      <tfoot>
        <tr>
          <td class="text-right px-4" colspan="5">TOTAL</td>
          <td class="text-right" id="tot-luas">0</td>
          <td class="text-right" id="tot-pokok">0</td>
          <td class="text-right" id="tot-anggaran">0</td>
          <td class="text-right" id="tot-realisasi">0</td>
          <td class="text-right" id="tot-tandan">0</td>
          <td class="text-right" id="tot-hk">0</td>
          <td class="text-right" id="tot-panenha">0</td>
          <td class="text-right" id="tot-freq">0</td>
          <?php if (!$isStaf): ?><td></td><?php endif; ?>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php if (!$isStaf): ?>
<div id="crud-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-opacity">
  <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-4xl transform scale-100 transition-transform">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-800">Input LM-76</h3>
      <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600 transition">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Kebun</label>
          <select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <option value="">-- Pilih Kebun --</option>
            <?php foreach ($kebun as $k): ?>
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Unit/Defisi</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" required>
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Bulan</label>
          <select id="bulan" name="bulan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" required>
            <?php foreach ($bulanList as $b): ?>
              <option value="<?= $b ?>"><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tahun</label>
          <select id="tahun" name="tahun" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" required>
            <?php for ($y=$tahunNow-1;$y<=$tahunNow+3;$y++): ?>
              <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">T. Tanam</label>
          <select id="tt" name="tt" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" required>
            <option value="">-- Pilih --</option>
            <?php foreach ($ttList as $tt): ?>
              <option value="<?= htmlspecialchars($tt) ?>"><?= htmlspecialchars($tt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Luas (Ha)</label><input type="number" step="0.01"  id="luas_ha" name="luas_ha" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Invt Pokok</label> <input type="number"           id="jumlah_pohon"   name="jumlah_pohon"   class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Anggaran (Kg)</label><input type="number" step="0.001" id="anggaran_kg" name="anggaran_kg" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Realisasi (Kg)</label><input type="number" step="0.001" id="realisasi_kg" name="realisasi_kg" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Jumlah Tandan</label><input type="number" id="jumlah_tandan" name="jumlah_tandan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Jumlah HK</label>    <input type="number" step="0.001" id="jumlah_hk"   name="jumlah_hk"   class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Panen (Ha)</label>   <input type="number" step="0.001" id="panen_ha"    name="panen_ha"    class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none"></div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Frekuensi</label>
          <input type="number" step="0.001" id="frekuensi" name="frekuensi" class="w-full border rounded px-3 py-2 text-sm bg-gray-100 text-gray-600 font-bold" readonly>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-8 pt-4 border-t border-gray-100">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50 transition">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 transition shadow-lg shadow-cyan-500/30">Simpan Data</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;
  const COLSPAN = IS_STAF ? 13 : 14;

  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data');
  
  const fKebun = $('#filter-kebun');
  const fUnit  = $('#filter-unit');
  const fBulan = $('#filter-bulan');
  const fTahun = $('#filter-tahun');
  const fTT    = $('#filter-tt');

  const tot = {
    luas: $('#tot-luas'), pokok: $('#tot-pokok'), anggaran: $('#tot-anggaran'), realisasi: $('#tot-realisasi'),
    tandan: $('#tot-tandan'), hk: $('#tot-hk'), panenha: $('#tot-panenha'), freq: $('#tot-freq')
  };

  const fmt = n => Number(n||0).toLocaleString(undefined,{maximumFractionDigits:2});
  const sum = (arr,key) => arr.reduce((a,r)=>a+(parseFloat(r[key])||0),0);
  const toNum = v => { const x=parseFloat(v); return Number.isNaN(x)?0:x; };
  const bulanOrder = {
    'Januari':1,'Februari':2,'Maret':3,'April':4,'Mei':5,'Juni':6,
    'Juli':7,'Agustus':8,'September':9,'Oktober':10,'November':11,'Desember':12
  };

  let allData=[];

  /* ========= Auto-Calc Frekuensi ========= */
  function calcFreq(){
    const luas  = parseFloat(document.getElementById('luas_ha')?.value || '0');
    const panen = parseFloat(document.getElementById('panen_ha')?.value || '0');
    const out   = document.getElementById('frekuensi');
    const freq  = (luas > 0) ? (panen / luas) : 0;
    if (out) out.value = Number(freq).toFixed(3);
  }

  function buildRowHTML(r){
    const periode = `${r.bulan||'-'} ${r.tahun||'-'}`;
    const freqCalc = (toNum(r.luas_ha) > 0) ? (toNum(r.panen_ha) / toNum(r.luas_ha)) : 0;

    let actionCell='';
    if (!IS_STAF){
      actionCell = `
        <td class="text-center">
          <div class="flex items-center justify-center gap-1">
            <button class="btn-icon text-cyan-600 hover:text-cyan-800" title="Edit" data-json="${encodeURIComponent(JSON.stringify(r))}">
              <i class="ti ti-pencil"></i>
            </button>
            <button class="btn-icon text-red-600 hover:text-red-800" title="Hapus" data-id="${r.id}">
              <i class="ti ti-trash"></i>
            </button>
          </div>
        </td>`;
    }
    return `
      <tr class="hover:bg-blue-50 transition-colors">
        <td>${r.tahun||'-'}</td>
        <td>${r.nama_kebun||'-'}</td>
        <td>${r.nama_unit||'-'}</td>
        <td>${periode}</td>
        <td>${r.tt||'-'}</td>
        <td class="text-right">${fmt(r.luas_ha)}</td>
        <td class="text-right">${fmt(r.jumlah_pohon)}</td>
        <td class="text-right">${fmt(r.anggaran_kg)}</td>
        <td class="text-right">${fmt(r.realisasi_kg)}</td>
        <td class="text-right">${fmt(r.jumlah_tandan)}</td>
        <td class="text-right">${fmt(r.jumlah_hk)}</td>
        <td class="text-right">${fmt(r.panen_ha)}</td>
        <td class="text-right">${fmt(freqCalc)}</td>
        ${actionCell}
      </tr>`;
  }

  function renderGrandTotals(data){
    tot.luas.textContent      = fmt(sum(data,'luas_ha'));
    tot.pokok.textContent     = fmt(sum(data,'jumlah_pohon'));
    tot.anggaran.textContent  = fmt(sum(data,'anggaran_kg'));
    tot.realisasi.textContent = fmt(sum(data,'realisasi_kg'));
    tot.tandan.textContent    = fmt(sum(data,'jumlah_tandan'));
    tot.hk.textContent        = fmt(sum(data,'jumlah_hk'));
    tot.panenha.textContent   = fmt(sum(data,'panen_ha'));
    const luas = sum(data,'luas_ha'), pan = sum(data,'panen_ha');
    tot.freq.textContent      = fmt(luas ? (pan/luas) : 0);
  }

  function groupAndRender(){
    if (!allData.length){
      tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-500 italic">Belum ada data pada filter ini.</td></tr>`;
      renderGrandTotals(allData);
      return;
    }

    // Group by Unit
    const unitBuckets = new Map();
    allData.forEach(r=>{
      const key = (r.nama_unit || '').trim() || '(Unit Tidak Diketahui)';
      if (!unitBuckets.has(key)) unitBuckets.set(key, []);
      unitBuckets.get(key).push(r);
    });

    const unitKeys = Array.from(unitBuckets.keys()).sort((a,b)=>a.localeCompare(b));
    let html='';

    unitKeys.forEach(uKey=>{
      const rowsU = unitBuckets.get(uKey) || [];
      rowsU.sort((a,b)=>{
        const ty = (parseInt(a.tahun)||0) - (parseInt(b.tahun)||0);
        if (ty!==0) return ty;
        const ba = bulanOrder[a.bulan] || 0, bb = bulanOrder[b.bulan] || 0;
        if (ba!==bb) return ba - bb;
        const tta = parseInt(String(a.tt||'').replace(/[^\d]/g,''),10) || 0;
        const ttb = parseInt(String(b.tt||'').replace(/[^\d]/g,''),10) || 0;
        if (tta!==ttb) return tta - ttb;
        return (a.nama_kebun||'').localeCompare(b.nama_kebun||'');
      });

      html += `<tr class="unit-head"><td class="px-3 py-2" colspan="${COLSPAN}"><i class="ti ti-folder"></i> Unit: ${uKey}</td></tr>`;
      html += rowsU.map(buildRowHTML).join('');

      // Subtotal
      const uLuas   = sum(rowsU,'luas_ha');
      const uPokok  = sum(rowsU,'jumlah_pohon');
      const uAgg    = sum(rowsU,'anggaran_kg');
      const uReal   = sum(rowsU,'realisasi_kg');
      const uTdn    = sum(rowsU,'jumlah_tandan');
      const uHK     = sum(rowsU,'jumlah_hk');
      const uPanen  = sum(rowsU,'panen_ha');
      const uFreq   = uLuas ? (uPanen/uLuas) : 0;

      html += `
        <tr class="unit-sub">
          <td class="px-3 py-2 text-right font-bold text-gray-600" colspan="5">Subtotal (${uKey})</td>
          <td class="text-right">${fmt(uLuas)}</td>
          <td class="text-right">${fmt(uPokok)}</td>
          <td class="text-right">${fmt(uAgg)}</td>
          <td class="text-right">${fmt(uReal)}</td>
          <td class="text-right">${fmt(uTdn)}</td>
          <td class="text-right">${fmt(uHK)}</td>
          <td class="text-right">${fmt(uPanen)}</td>
          <td class="text-right">${fmt(uFreq)}</td>
          ${!IS_STAF ? '<td></td>' : ''}
        </tr>`;
    });

    tbody.innerHTML = html;
    renderGrandTotals(allData);
  }

  function refresh(){
    const fd=new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    fd.append('kebun_id',fKebun.value);
    fd.append('unit_id',fUnit.value);
    fd.append('bulan',fBulan.value);
    fd.append('tahun',fTahun.value);
    fd.append('tt',fTT.value);

    tbody.innerHTML=`<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>`;

    fetch('lm76_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        allData = (j && j.success && Array.isArray(j.data)) ? j.data : [];
        groupAndRender();
      })
      .catch(()=>{
        tbody.innerHTML=`<tr><td colspan="${COLSPAN}" class="text-center py-10 text-red-500">Gagal memuat data.</td></tr>`;
      });
  }

  refresh();
  
  /* ===== CRUD (Non-Staf) ===== */
  if (!IS_STAF){
    const modal=$('#crud-modal'), form=$('#crud-form'), titleEl=$('#modal-title');
    const open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')};
    const close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

    const btnAdd = document.getElementById('btn-add');
    if(btnAdd){
      btnAdd.addEventListener('click',()=>{
        form.reset();
        document.getElementById('form-action').value='store';
        document.getElementById('form-id').value='';
        titleEl.textContent='Input LM-76';
        if(fKebun.value) $('#kebun_id').value=fKebun.value;
        if(fUnit.value)  $('#unit_id').value=fUnit.value;
        if(fBulan.value) $('#bulan').value=fBulan.value;
        if(fTahun.value) $('#tahun').value=fTahun.value;
        calcFreq(); open();
      });
    }

    document.getElementById('btn-close').addEventListener('click', close);
    document.getElementById('btn-cancel').addEventListener('click', close);

    ['luas_ha','panen_ha'].forEach(id=>{
      const el=document.getElementById(id);
      if(el) el.addEventListener('input', calcFreq);
    });

    document.body.addEventListener('click',(e)=>{
      const t=e.target.closest('button'); if(!t) return;
      
      if (t.classList.contains('btn-icon') && t.title==='Edit'){
        const row=JSON.parse(decodeURIComponent(t.dataset.json||'{}'));
        form.reset();
        document.getElementById('form-action').value='update';
        document.getElementById('form-id').value=row.id||'';
        titleEl.textContent='Edit LM-76';
        ['kebun_id','unit_id','bulan','tahun','tt','luas_ha','jumlah_pohon','anggaran_kg','realisasi_kg','jumlah_tandan','jumlah_hk','panen_ha','frekuensi']
          .forEach(id=>{ const el=document.getElementById(id); if(el && row[id]!==undefined) el.value=row[id]??''; });
        calcFreq(); open();
      }

      if (t.classList.contains('btn-icon') && t.title==='Hapus'){
        const id=t.dataset.id;
        Swal.fire({
          title:'Hapus data?', text:'Tindakan tidak dapat dikembalikan.', icon:'warning',
          showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Ya, hapus', cancelButtonText:'Batal'
        }).then(res=>{
          if(res.isConfirmed){
            const fd=new FormData();
            fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
            fd.append('action','delete'); fd.append('id',id);
            fetch('lm76_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
              if(j.success){ Swal.fire('Terhapus!','','success'); refresh(); }
              else Swal.fire('Gagal', j.message||'Error','error');
            });
          }
        });
      }
    });

    form.addEventListener('submit',(e)=>{
      e.preventDefault();
      const req=['unit_id','bulan','tahun','tt'];
      for(const id of req){
        const el=document.getElementById(id);
        if(!el || !el.value){ Swal.fire('Validasi',`Field ${id} wajib diisi.`,'warning'); return; }
      }
      calcFreq();
      const fd=new FormData(form);
      fetch('lm76_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.success){ close(); Swal.fire({icon:'success',title:'Berhasil',text:j.message,timer:1200,showConfirmButton:false}); refresh(); }
        else Swal.fire('Gagal',j.message||'Error','error');
      });
    });
  }
});
</script>