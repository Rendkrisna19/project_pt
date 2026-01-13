<?php
// lm77.php — Modifikasi Full: Auto Filter, Realtime, Sticky Header, Grid Table

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* master untuk filter */
$units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$kebuns = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);

// --- LOGIKA TANGGAL DEFAULT ---
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$bulanIndex = (int)date('n') - 1; 
$bulanNowName = $bulanList[$bulanIndex]; 
$tahunNow = (int)date('Y');

// Filter awal (jika ada di URL, pakai itu. Jika tidak, pakai default server time)
$f_unit  = $_GET['unit_id'] ?? '';
$f_kebun = $_GET['kebun_id'] ?? '';
$f_bulan = $_GET['bulan'] ?? $bulanNowName;
$f_tahun = $_GET['tahun'] ?? $tahunNow;

$currentPage = 'lm77';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  /* --- STICKY CONTAINER & TABLE GRID --- */
  .sticky-container {
    max-height: 75vh; /* Tinggi area scroll */
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }

  table.table-grid {
    width: 100%;
    border-collapse: separate; /* Wajib separate untuk sticky */
    border-spacing: 0;
    min-width: 1600px; /* Lebar minimum agar tidak gepeng */
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
    background: #059fd3; /* Warna Biru Standar */
    color: #fff;
    z-index: 10;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    height: 55px; /* Header diperbesar ke bawah */
    vertical-align: middle;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  /* Footer Sticky di Bawah */
  table.table-grid tfoot td {
    position: sticky;
    bottom: 0;
    background: #059fd3; /* Warna Biru Footer */
    color: #fff;
    font-weight: 700;
    z-index: 10;
    border-top: 2px solid rgba(255,255,255,0.3);
  }

  /* Style Grouping Rows */
  .unit-head td { 
    background: #e0f2fe; /* Biru sangat muda */
    color: #0369a1; 
    font-weight: 800; 
    border-top: 2px solid #bae6fd;
  }
  .unit-sub td { 
    background: #f0f9ff; 
    font-weight: 700; 
    color: #0c4a6e;
    border-top: 1px solid #e2e8f0;
  }

  /* Utilities */
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .text-left { text-align: left; }
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
</style>

<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">LM-77 — Statistik Panen</h1>
      <p class="text-gray-500 text-sm mt-1">Rekap turunan LM-76 • Grouping per Unit</p>
    </div>

    <div class="flex items-center gap-2">
      <button id="btn-export-excel" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2 shadow-sm transition">
          <i class="ti ti-file-spreadsheet"></i><span>Excel</span>
      </button>
      <button id="btn-export-pdf" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 shadow-sm transition">
          <i class="ti ti-file-type-pdf"></i><span>PDF</span>
      </button>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-3">
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit/Defisi</label>
        <select id="filter-unit" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <option value="">Semua Unit</option>
            <?php foreach ($units as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= ((string)$f_unit === (string)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
        <select id="filter-bulan" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <option value="">Semua Bulan</option>
            <?php foreach ($bulanList as $b): ?>
                <option value="<?= $b ?>" <?= ($f_bulan === $b)?'selected':'' ?>><?= $b ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
        <select id="filter-tahun" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <?php for ($y=$tahunNow-2;$y<=$tahunNow+2;$y++): ?>
                <option value="<?= $y ?>" <?= ((int)$f_tahun === $y)?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kebun</label>
        <select id="filter-kebun" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            <option value="">Semua Kebun</option>
            <?php foreach ($kebuns as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= ((string)$f_kebun === (string)$k['id'])?'selected':'' ?>><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
  </div>

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
          <th class="text-right">Inv. Pokok</th>
          <th class="text-right">Variance (%)</th>
          <th class="text-right">Tandan/Pokok</th>
          <th class="text-right">Protas (Ton)</th>
          <th class="text-right">BTR</th>
          <th class="text-right">Prestasi HK (Kg)</th>
          <th class="text-right">Prestasi HK (Tandan)</th>
        </tr>
      </thead>
      <tbody id="tbody-data" class="bg-white text-gray-800">
        <tr><td colspan="13" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>
      </tbody>
      <tfoot>
        <tr>
          <td class="text-right px-4" colspan="5">TOTAL</td>
          <td class="text-right" id="tot-luas">0</td>
          <td class="text-right" id="tot-pokok">0</td>
          <td class="text-right" id="tot-var">0</td>
          <td class="text-right" id="tot-tpp">0</td>
          <td class="text-right" id="tot-protas">0</td>
          <td class="text-right" id="tot-btr">0</td>
          <td class="text-right" id="tot-kg-hk">0</td>
          <td class="text-right" id="tot-tdn-hk">0</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data');

  const fUnit  = $('#filter-unit');
  const fBulan = $('#filter-bulan');
  const fTahun = $('#filter-tahun');
  const fKebun = $('#filter-kebun');

  const T = {
    luas: $('#tot-luas'), pokok: $('#tot-pokok'),
    v: $('#tot-var'), tpp: $('#tot-tpp'), prot: $('#tot-protas'),
    btr: $('#tot-btr'), kghk: $('#tot-kg-hk'), tdnhk: $('#tot-tdn-hk')
  };

  let allData = [];
  
  const bulanOrder = {
    'Januari':1,'Februari':2,'Maret':3,'April':4,'Mei':5,'Juni':6,
    'Juli':7,'Agustus':8,'September':9,'Oktober':10,'November':11,'Desember':12
  };

  const fmt = (n, d=2) => Number(n ?? 0).toLocaleString(undefined,{maximumFractionDigits:d});
  const sum = (arr, getter) => arr.reduce((a,r)=> a + (getter(r)||0), 0);

  /* ====== RUMUS ====== */
  const toNum = v => { const x = parseFloat(v); return Number.isNaN(x) ? 0 : x; };

  function variancePct(r){
    const real = toNum(r.realisasi_kg);
    const angg = toNum(r.anggaran_kg);
    return angg ? (real/angg)*100 - 100 : null;
  }
  function tandanPerPohon(r){
    const tand = toNum(r.jumlah_tandan);
    const pokok = toNum(r.jumlah_pohon);
    return pokok ? tand/pokok : null;
  }
  function protasTon(r){
    const real = toNum(r.realisasi_kg);
    const luas = toNum(r.luas_ha);
    return luas ? (real/luas)/1000 : null;
  }
  function btr(r){
    const real = toNum(r.realisasi_kg);
    const tand = toNum(r.jumlah_tandan);
    return tand ? real/tand : null;
  }
  function prestasiKgHK(r){
    const real = toNum(r.realisasi_kg);
    const hk = toNum(r.jumlah_hk);
    return hk ? real/hk : null;
  }
  function prestasiTandanHK(r){
    const tand = toNum(r.jumlah_tandan);
    const hk = toNum(r.jumlah_hk);
    return hk ? tand/hk : null;
  }

  function buildRowHTML(r){
    const v   = variancePct(r);
    const tpp = tandanPerPohon(r);
    const pt  = protasTon(r);
    const b   = btr(r);
    const kg  = prestasiKgHK(r);
    const td  = prestasiTandanHK(r);

    return `
      <tr class="hover:bg-blue-50 transition-colors">
        <td>${r.tahun ?? '-'}</td>
        <td>${r.nama_kebun ?? '-'}</td>
        <td>${r.nama_unit ?? '-'}</td>
        <td>${(r.bulan||'-')} ${(r.tahun||'-')}</td>
        <td>${r.tt ?? '-'}</td>
        <td class="text-right">${fmt(r.luas_ha)}</td>
        <td class="text-right">${fmt(r.jumlah_pohon,0)}</td>
        <td class="text-right ${v!==null?(v<0?'text-red-600':'text-gray-800'):''}">${v!==null ? fmt(v) : '-'}</td>
        <td class="text-right">${tpp!==null ? fmt(tpp,4) : '-'}</td>
        <td class="text-right">${pt!==null ? fmt(pt,3) : '-'}</td>
        <td class="text-right">${b!==null ? fmt(b,3) : '-'}</td>
        <td class="text-right">${kg!==null ? fmt(kg,3) : '-'}</td>
        <td class="text-right">${td!==null ? fmt(td,3) : '-'}</td>
      </tr>
    `;
  }

  function renderGrandTotals(){
    const totalLuas  = sum(allData, r => toNum(r.luas_ha));
    const totalPokok = sum(allData, r => toNum(r.jumlah_pohon));
    const totalReal  = sum(allData, r => toNum(r.realisasi_kg));
    const totalAngg  = sum(allData, r => toNum(r.anggaran_kg));
    const totalTdn   = sum(allData, r => toNum(r.jumlah_tandan));
    const totalHK    = sum(allData, r => toNum(r.jumlah_hk));

    const Vtot   = totalAngg>0 ? ((totalReal/totalAngg)*100 - 100) : null;
    const TPP    = totalPokok>0 ? (totalTdn/totalPokok) : null;
    const PROT   = totalLuas>0  ? ((totalReal/totalLuas)/1000) : null;
    const BTR    = totalTdn>0   ? (totalReal/totalTdn) : null;
    const KG_HK  = totalHK>0    ? (totalReal/totalHK) : null;
    const TDN_HK = totalHK>0    ? (totalTdn/totalHK) : null;

    T.luas.textContent  = fmt(totalLuas);
    T.pokok.textContent = fmt(totalPokok,0);
    T.v.textContent     = Vtot!==null?fmt(Vtot):'-';
    T.tpp.textContent   = TPP!==null?fmt(TPP,4):'-';
    T.prot.textContent  = PROT!==null?fmt(PROT,3):'-';
    T.btr.textContent   = BTR!==null?fmt(BTR,3):'-';
    T.kghk.textContent  = KG_HK!==null?fmt(KG_HK,3):'-';
    T.tdnhk.textContent = TDN_HK!==null?fmt(TDN_HK,3):'-';
  }

  function groupAndRender(){
    if (!allData.length){
      tbody.innerHTML = `<tr><td colspan="13" class="text-center py-10 text-gray-500 italic">Belum ada data.</td></tr>`;
      renderGrandTotals();
      return;
    }

    // Group by Unit
    const unitBuckets = new Map();
    allData.forEach(r=>{
      const uKey = (r.nama_unit || '').trim() || '(Unit Tidak Diketahui)';
      if (!unitBuckets.has(uKey)) unitBuckets.set(uKey, []);
      unitBuckets.get(uKey).push(r);
    });

    // Sort unit keys
    const unitKeys = Array.from(unitBuckets.keys()).sort((a,b)=>a.localeCompare(b));

    let html = '';

    unitKeys.forEach(uKey=>{
      const rowsU = unitBuckets.get(uKey) || [];

      rowsU.sort((a,b)=>{
        const ty = (parseInt(a.tahun)||0) - (parseInt(b.tahun)||0);
        if (ty!==0) return ty;
        const ba = bulanOrder[a.bulan] || 0, bb = bulanOrder[b.bulan] || 0;
        if (ba!==bb) return ba - bb;
        return (a.tt||'').localeCompare(b.tt||'');
      });

      // Unit header row
      html += `<tr class="unit-head"><td class="px-3 py-2" colspan="13"><i class="ti ti-folder"></i> Unit: ${uKey}</td></tr>`;

      // Data Rows
      html += rowsU.map(buildRowHTML).join('');

      // Subtotal Unit
      const uLuas   = sum(rowsU, r => toNum(r.luas_ha));
      const uPokok  = sum(rowsU, r => toNum(r.jumlah_pohon));
      const uReal   = sum(rowsU, r => toNum(r.realisasi_kg));
      const uAngg   = sum(rowsU, r => toNum(r.anggaran_kg));
      const uTdn    = sum(rowsU, r => toNum(r.jumlah_tandan));
      const uHK     = sum(rowsU, r => toNum(r.jumlah_hk));

      const uVtot   = uAngg>0 ? ((uReal/uAngg)*100 - 100) : null;
      const uTPP    = uPokok>0 ? (uTdn/uPokok) : null;
      const uPROT   = uLuas>0  ? ((uReal/uLuas)/1000) : null;
      const uBTR    = uTdn>0   ? (uReal/uTdn) : null;
      const uKG_HK  = uHK>0    ? (uReal/uHK) : null;
      const uTDN_HK = uHK>0    ? (uTdn/uHK) : null;

      html += `
        <tr class="unit-sub">
          <td class="px-3 py-2 text-right font-bold text-gray-600" colspan="5">Subtotal (${uKey})</td>
          <td class="text-right">${fmt(uLuas)}</td>
          <td class="text-right">${fmt(uPokok,0)}</td>
          <td class="text-right">${uVtot!==null?fmt(uVtot):'-'}</td>
          <td class="text-right">${uTPP!==null?fmt(uTPP,4):'-'}</td>
          <td class="text-right">${uPROT!==null?fmt(uPROT,3):'-'}</td>
          <td class="text-right">${uBTR!==null?fmt(uBTR,3):'-'}</td>
          <td class="text-right">${uKG_HK!==null?fmt(uKG_HK,3):'-'}</td>
          <td class="text-right">${uTDN_HK!==null?fmt(uTDN_HK,3):'-'}</td>
        </tr>`;
    });

    tbody.innerHTML = html;
    renderGrandTotals();
  }

  function refresh(){
    const fd = new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    fd.append('kebun_id', fKebun.value);
    fd.append('unit_id',  fUnit.value);
    fd.append('bulan',    fBulan.value);
    fd.append('tahun',    fTahun.value);

    tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>';
    
    // Menggunakan lm76_crud.php karena lm77 adalah turunan data yang sama
    fetch('lm76_crud.php',{method:'POST', body:fd})
      .then(r=>r.json())
      .then(j=>{ 
        allData = (j && j.success && Array.isArray(j.data)) ? j.data : []; 
        groupAndRender(); 
      })
      .catch(()=>{ tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-red-500">Gagal memuat data</td></tr>'; });
  }

  // Load awal
  refresh();
  
  // Event listener realtime filter
  [fUnit,fBulan,fTahun,fKebun].forEach(el=>el.addEventListener('change', refresh));

  // Export Link Generator
  function filtersQS(){
    return new URLSearchParams({
      csrf_token: '<?= htmlspecialchars($CSRF) ?>',
      unit_id:  fUnit.value || '',
      bulan:    fBulan.value || '',
      tahun:    fTahun.value || '',
      kebun_id: fKebun.value || ''
    }).toString();
  }
  document.getElementById('btn-export-excel').addEventListener('click', ()=> window.open('cetak/lm77_export_excel.php?'+filtersQS(), '_blank'));
  document.getElementById('btn-export-pdf').addEventListener('click',  ()=> window.open('cetak/lm77_export_pdf.php?'+filtersQS(),  '_blank'));
});
</script>