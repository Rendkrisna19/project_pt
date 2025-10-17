<?php
// lm77.php — Rekap LM-77 (turunan dari LM-76 13 kolom)

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
<style>.thead-sticky th{position:sticky;top:0;z-index:10}</style>

<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold">LM-77 — Statistik Panen (Rekap)</h1>
      <p class="text-gray-500">Rekap turunan dari LM-76. Kolom 8–13 dihitung otomatis.</p>
    </div>

    <div class="flex items-center gap-2">
      <button id="btn-export-excel" class="flex items-center gap-2 border px-3 py-2 rounded-lg bg-white hover:bg-gray-50" title="Export Excel">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.3 1.7L7 12l-1.7 2.3h1.5l1-1.5 1 1.5h1.5L8.6 12l1.7-2.3H8.8L7.8 11 6.8 9.7H5.3z"/></svg>
        <span>Excel</span>
      </button>
      <button id="btn-export-pdf" class="flex items-center gap-2 border px-3 py-2 rounded-lg bg-white hover:bg-gray-50" title="Cetak PDF">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/></svg>
        <span>PDF</span>
      </button>
    </div>
  </div>

  <!-- FILTER -->
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

  <!-- TABEL -->
  <div class="bg-white rounded-xl shadow-sm">
    <div class="overflow-x-auto">
      <div class="max-h-[520px] overflow-auto">
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
          <tfoot>
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

    <!-- Pagination -->
    <div class="flex flex-wrap items-center justify-between gap-3 p-3 border-t">
      <div class="flex items-center gap-2">
        <label for="page-size" class="text-sm text-gray-600">Tampilkan</label>
        <select id="page-size" class="border rounded px-2 py-1 text-sm">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="text-sm text-gray-600">baris</span>
      </div>
      <div class="flex items-center gap-2" id="pager"></div>
      <div class="text-sm text-gray-600" id="range-info"></div>
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

  const pageSizeSel = $('#page-size');
  const pagerEl     = $('#pager');
  const rangeInfoEl = $('#range-info');

  const T = {
    luas: $('#tot-luas'), pokok: $('#tot-pokok'),
    v: $('#tot-var'), tpp: $('#tot-tpp'), prot: $('#tot-protas'),
    btr: $('#tot-btr'), kghk: $('#tot-kg-hk'), tdnhk: $('#tot-tdn-hk')
  };

  let allData = [];
  let currentPage = 1;

  const fmt = (n, d=2) => Number(n ?? 0).toLocaleString(undefined,{maximumFractionDigits:d});
  const sum = (arr, getter) => arr.reduce((a,r)=> a + (getter(r)||0), 0);

  /* ====== RUMUS (ambil dari LM-76 v13 kolom) ====== */
  // r.realisasi_kg, r.anggaran_kg, r.jumlah_tandan, r.jumlah_pohon, r.luas_ha, r.jumlah_hk
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

  function renderTotals(){
    const totalLuas  = sum(allData, r => toNum(r.luas_ha));
    const totalPokok = sum(allData, r => toNum(r.jumlah_pohon));
    const totalReal  = sum(allData, r => toNum(r.realisasi_kg));
    const totalAngg  = sum(allData, r => toNum(r.anggaran_kg));
    const totalTdn   = sum(allData, r => toNum(r.jumlah_tandan));
    const totalHK    = sum(allData, r => toNum(r.jumlah_hk));

    const Vtot  = totalAngg>0 ? ((totalReal/totalAngg)*100 - 100) : null;
    const TPP   = totalPokok>0 ? (totalTdn/totalPokok) : null;
    const PROT  = totalLuas>0  ? ((totalReal/totalLuas)/1000) : null;
    const BTR   = totalTdn>0   ? (totalReal/totalTdn) : null;
    const KG_HK = totalHK>0    ? (totalReal/totalHK) : null;
    const TDN_HK= totalHK>0    ? (totalTdn/totalHK) : null;

    T.luas.textContent  = fmt(totalLuas);
    T.pokok.textContent = fmt(totalPokok,0);
    T.v.textContent     = Vtot!==null?fmt(Vtot):'-';
    T.tpp.textContent   = TPP!==null?fmt(TPP,4):'-';
    T.prot.textContent  = PROT!==null?fmt(PROT,3):'-';
    T.btr.textContent   = BTR!==null?fmt(BTR,3):'-';
    T.kghk.textContent  = KG_HK!==null?fmt(KG_HK,3):'-';
    T.tdnhk.textContent = TDN_HK!==null?fmt(TDN_HK,3):'-';
  }

  function renderPager(page, totalPages){
    const windowSize = 5;
    let start = Math.max(1, page - Math.floor(windowSize/2));
    let end   = start + windowSize - 1;
    if (end > totalPages) { end = totalPages; start = Math.max(1, end - windowSize + 1); }

    const btn = (label, disabled, goPage, extra='') => `
      <button ${disabled ? 'disabled' : ''} data-goto="${goPage}"
        class="px-3 py-1 border rounded text-sm ${disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100'} ${extra}">
        ${label}
      </button>`;

    let html = '';
    html += btn('« Prev', page <= 1, page - 1);
    for (let p = start; p <= end; p++){
      html += btn(p, false, p, p===page ? 'bg-gray-200 font-semibold' : '');
    }
    html += btn('Next »', page >= totalPages, page + 1);
    pagerEl.innerHTML = html;
  }

  function renderTable(){
    const size = parseInt(pageSizeSel.value || '10', 10);
    const total = allData.length;
    const totalPages = Math.max(1, Math.ceil(total / size));
    if (currentPage > totalPages) currentPage = totalPages;

    if (!total){
      tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-gray-500">Belum ada data.</td></tr>';
      pagerEl.innerHTML = ''; rangeInfoEl.textContent = '';
      renderTotals(); return;
    }

    const start = (currentPage - 1) * size;
    const end   = Math.min(start + size, total);
    tbody.innerHTML = allData.slice(start, end).map(buildRowHTML).join('');
    rangeInfoEl.textContent = `Menampilkan ${start+1}–${end} dari ${total} data`;

    renderPager(currentPage, totalPages);
    renderTotals();
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

    tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-gray-500">Memuat…</td></tr>';
    fetch('lm76_crud.php',{method:'POST', body:fd})
      .then(r=>r.json())
      .then(j=>{ allData = (j && j.success && Array.isArray(j.data)) ? j.data : []; currentPage = 1; renderTable(); })
      .catch(()=>{ tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-red-500">Gagal memuat data</td></tr>'; });
  }

  refresh();
  [fUnit,fBulan,fTahun,fKebun].forEach(el=>el.addEventListener('change', ()=>{ currentPage=1; refresh(); }));
  pageSizeSel.addEventListener('change', ()=>{ currentPage=1; renderTable(); });

  // Pager click
  pagerEl.addEventListener('click',(e)=>{
    const b = e.target.closest('button[data-goto]'); if(!b||b.disabled) return;
    currentPage = parseInt(b.dataset.goto,10); renderTable();
  });

  // Export ikut filter + CSRF
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
