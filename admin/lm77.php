<?php
// lm77.php — Rekap LM-77 (turunan dari LM-76 13 kolom)
// Versi modifikasi: Group by Unit, style dari LM-76

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* master untuk filter */
$units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$kebuns = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

$currentPage = 'lm77';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
  .thead-sticky th{position:sticky;top:0;z-index:10}
  /* Style grouping dari lm76 */
  .unit-head { background:#d1fae5; color:#065f46; font-weight:700; }
  .unit-sub { background:#ecfdf5; font-weight:700; }
</style>

<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold">LM-77 — Statistik Panen (Rekap)</h1>
      <p class="text-gray-500">Rekap turunan dari LM-76. Dikelompokkan per Unit/AFD. Kolom 8–13 dihitung otomatis.</p>
    </div>

    <div class="flex items-center gap-2">
      <button id="btn-export-excel" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
          <i class="ti ti-file-spreadsheet"></i><span>Excel</span>
      </button>
      <button id="btn-export-pdf" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
          <i class="ti ti-file-type-pdf"></i><span>PDF</span>
      </button>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm grid grid-cols-1 md:grid-cols-4 gap-3">
    <select id="filter-unit" class="border rounded px-3 py-2">
      <option value="">Semua Unit</option>
      <?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
    </select>
    <select id="filter-bulan" class="border rounded px-3 py-2">
      <option value="">Semua Bulan</option>
      <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
    </select>
    <select id="filter-tahun" class="border rounded px-3 py-2">
      <?php for ($y=$tahunNow-2;$y<=$tahunNow+2;$y++): ?>
        <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select id="filter-kebun" class="border rounded px-3 py-2">
      <option value="">Semua Kebun</option>
      <?php foreach ($kebuns as $k): ?>
        <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="bg-white rounded-xl shadow-sm">
    <div class="overflow-x-auto">
      <div class="max-h-[70vh] overflow-auto">
        <table class="min-w-full text-sm">
          <thead class="thead-sticky bg-green-700 text-white">
            <tr>
              <th class="py-3 px-4 text-left">Tahun</th>
              <th class="py-3 px-4 text-left">Kebun</th>
              <th class="py-3 px-4 text-left">Unit/Defisi</th>
              <th class="py-3 px-4 text-left">Periode</th>
              <th class="py-3 px-4 text-left">T. Tanam</th>
              <th class="py-3 px-4 text-right">Luas (Ha)</th>
              <th class="py-3 px-4 text-right">Inv. Pokok</th>
              <th class="py-3 px-4 text-right">Variance (%)</th>
              <th class="py-3 px-4 text-right">Tandan/Pokok</th>
              <th class="py-3 px-4 text-right">Protas (Ton)</th>
              <th class="py-3 px-4 text-right">BTR</th>
              <th class="py-3 px-4 text-right">Prestasi HK (Kg)</th>
              <th class="py-3 px-4 text-right">Prestasi HK (Tandan)</th>
            </tr>
          </thead>
          <tbody id="tbody-data">
            <tr><td colspan="13" class="text-center py-10 text-gray-500">Memuat…</td></tr>
          </tbody>
          <tfoot class="bg-green-50 border-t-4 border-green-700 text-gray-900">
            <tr class="bg-green-50 border-t-4 border-green-700 text-gray-900">
              <td class="py-3 px-4 font-semibold" colspan="5">TOTAL</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-luas">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-pokok">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-var">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-tpp">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-protas">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-btr">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-kg-hk">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-tdn-hk">0</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
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

  // Hapus DOM Pager
  // const pageSizeSel = $('#page-size');
  // const pagerEl     = $('#pager');
  // const rangeInfoEl = $('#range-info');

  const T = {
    luas: $('#tot-luas'), pokok: $('#tot-pokok'),
    v: $('#tot-var'), tpp: $('#tot-tpp'), prot: $('#tot-protas'),
    btr: $('#tot-btr'), kghk: $('#tot-kg-hk'), tdnhk: $('#tot-tdn-hk')
  };

  let allData = [];
  // Hapus state Pager
  // let currentPage = 1; 
  
  const bulanOrder = {
    'Januari':1,'Februari':2,'Maret':3,'April':4,'Mei':5,'Juni':6,
    'Juli':7,'Agustus':8,'September':9,'Oktober':10,'November':11,'Desember':12
  };

  const fmt = (n, d=2) => Number(n ?? 0).toLocaleString(undefined,{maximumFractionDigits:d});
  const sum = (arr, getter) => arr.reduce((a,r)=> a + (getter(r)||0), 0);

  /* ====== RUMUS (ambil dari LM-76 v13 kolom) ====== */
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
      <tr class="border-b hover:bg-gray-50">
        <td class="py-2 px-3">${r.tahun ?? '-'}</td>
        <td class="py-2 px-3">${r.nama_kebun ?? '-'}</td>
        <td class="py-2 px-3">${r.nama_unit ?? '-'}</td>
        <td class="py-2 px-3">${(r.bulan||'-')} ${(r.tahun||'-')}</td>
        <td class="py-2 px-3">${r.tt ?? '-'}</td>
        <td class="py-2 px-3 text-right">${fmt(r.luas_ha)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.jumlah_pohon,0)}</td>
        <td class="py-2 px-3 text-right">${v!==null ? fmt(v) : '-'}</td>
        <td class="py-2 px-3 text-right">${tpp!==null ? fmt(tpp,4) : '-'}</td>
        <td class="py-2 px-3 text-right">${pt!==null ? fmt(pt,3) : '-'}</td>
        <td class="py-2 px-3 text-right">${b!==null ? fmt(b,3) : '-'}</td>
        <td class="py-2 px-3 text-right">${kg!==null ? fmt(kg,3) : '-'}</td>
        <td class="py-2 px-3 text-right">${td!==null ? fmt(td,3) : '-'}</td>
      </tr>
    `;
  }

  // Fungsi ini menghitung Grand Total (Footer)
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

  // Hapus fungsi renderPager()
  
  // Ganti renderTable() menjadi groupAndRender() (dari lm76)
  function groupAndRender(){
    if (!allData.length){
      tbody.innerHTML = `<tr><td colspan="13" class="text-center py-10 text-gray-500">Belum ada data.</td></tr>`;
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

    // Sort unit keys alphabetically
    const unitKeys = Array.from(unitBuckets.keys()).sort((a,b)=>a.localeCompare(b));

    let html = '';

    unitKeys.forEach(uKey=>{
      const rowsU = unitBuckets.get(uKey) || [];

      // Urutkan baris dalam Unit: Tahun ASC → Bulan ASC → TT ASC
      rowsU.sort((a,b)=>{
        const ty = (parseInt(a.tahun)||0) - (parseInt(b.tahun)||0);
        if (ty!==0) return ty;
        const ba = bulanOrder[a.bulan] || 0, bb = bulanOrder[b.bulan] || 0;
        if (ba!==bb) return ba - bb;
        return (a.tt||'').localeCompare(b.tt||'');
      });

      // Unit header
      html += `<tr class="unit-head"><td class="py-2 px-3" colspan="13">Unit/AFD: ${uKey}</td></tr>`;

      // Rows
      html += rowsU.map(buildRowHTML).join('');

      // Subtotal Unit (logika kalkulasi sama seperti renderGrandTotals)
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
          <td class="py-2 px-3" colspan="5">Jumlah (${uKey})</td>
          <td class="py-2 px-3 text-right">${fmt(uLuas)}</td>
          <td class="py-2 px-3 text-right">${fmt(uPokok,0)}</td>
          <td class="py-2 px-3 text-right">${uVtot!==null?fmt(uVtot):'-'}</td>
          <td class="py-2 px-3 text-right">${uTPP!==null?fmt(uTPP,4):'-'}</td>
          <td class="py-2 px-3 text-right">${uPROT!==null?fmt(uPROT,3):'-'}</td>
          <td class="py-2 px-3 text-right">${uBTR!==null?fmt(uBTR,3):'-'}</td>
          <td class="py-2 px-3 text-right">${uKG_HK!==null?fmt(uKG_HK,3):'-'}</td>
          <td class="py-2 px-3 text-right">${uTDN_HK!==null?fmt(uTDN_HK,3):'-'}</td>
        </tr>`;
    });

    tbody.innerHTML = html;
    renderGrandTotals(); // Render Grand Total di footer
  }

  function refresh(){
    // ambil data LM-76 (turunan)
    const fd = new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    fd.append('kebun_id', fKebun.value);
    fd.append('unit_id',  fUnit.value);
    fd.append('bulan',    fBulan.value);
    fd.append('tahun',    fTahun.value);
    // Kita tidak filter 'tt' di LM-77, jadi tidak perlu dikirim
    // fd.append('tt', fTT.value); 

    tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-gray-500">Memuat…</td></tr>';
    fetch('lm76_crud.php',{method:'POST', body:fd})
      .then(r=>r.json())
      // Modifikasi .then() untuk memanggil groupAndRender
      .then(j=>{ 
        allData = (j && j.success && Array.isArray(j.data)) ? j.data : []; 
        groupAndRender(); 
      })
      .catch(()=>{ tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-red-500">Gagal memuat data</td></tr>'; });
  }

  refresh();
  
  // Modifikasi listener, hapus currentPage=1
  [fUnit,fBulan,fTahun,fKebun].forEach(el=>el.addEventListener('change', refresh));
  
  // Hapus listener Pager
  // pageSizeSel.addEventListener('change', ...);
  // pagerEl.addEventListener('click', ...);p

  // Export ikut filter + CSRF (Sudah benar, tidak perlu diubah)
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