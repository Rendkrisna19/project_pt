<?php
// lm76.php — LM-76 (UI ala LM-77): Group per Unit/AFD → urut Tahun, Bulan, T.Tanam; subtotal per Unit + Grand Total
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
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

$currentPage = 'lm76';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
  .thead-sticky th{position:sticky;top:0;z-index:10}
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
  .btn-icon { background: transparent; border: none; padding: 0.25rem; }

  /* Warna / grouping ala LM-77 */
  .unit-head { background:#d1fae5; color:#065f46; font-weight:700; }
  .unit-sub  { background:#ecfdf5; font-weight:700; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">LM-76 — Statistik Panen Kelapa Sawit</h1>
      <p class="text-gray-500">Tampilan ala LM-77: dikelompokkan per Unit/AFD dengan subtotal per Unit. Urut Tahun → Bulan → T. Tanam.</p>
    </div>
    <div class="flex gap-2">
      <?php if (!$isStaf): ?>
      <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 flex items-center gap-2">
        <i class="ti ti-plus"></i><span>Input LM-76</span>
      </button>
      <?php endif; ?>
      <button id="btn-export-excel" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
        <i class="ti ti-file-spreadsheet"></i><span>Export Excel</span>
      </button>
      <button id="btn-export-pdf" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
        <i class="ti ti-file-type-pdf"></i><span>Export PDF</span>
      </button>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm grid grid-cols-1 md:grid-cols-5 gap-3">
    <select id="filter-kebun" class="border rounded px-3 py-2">
      <option value="">Semua Kebun</option>
      <?php foreach ($kebun as $k): ?>
        <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="filter-unit" class="border rounded px-3 py-2">
      <option value="">Semua Unit</option>
      <?php foreach ($units as $u): ?>
        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="filter-bulan" class="border rounded px-3 py-2">
      <option value="">Semua Bulan</option>
      <?php foreach ($bulanList as $b): ?>
        <option value="<?= $b ?>"><?= $b ?></option>
      <?php endforeach; ?>
    </select>
    <select id="filter-tahun" class="border rounded px-3 py-2">
      <?php for ($y=$tahunNow-2;$y<=$tahunNow+2;$y++): ?>
        <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select id="filter-tt" class="border rounded px-3 py-2">
      <option value="">Semua T. Tanam</option>
      <?php foreach ($ttList as $tt): ?>
        <option value="<?= htmlspecialchars($tt) ?>"><?= htmlspecialchars($tt) ?></option>
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
              <th class="py-3 px-4 text-right">Invt Pokok</th>
              <th class="py-3 px-4 text-right">Anggaran (Kg)</th>
              <th class="py-3 px-4 text-right">Realisasi (Kg)</th>
              <th class="py-3 px-4 text-right">Jumlah Tandan</th>
              <th class="py-3 px-4 text-right">Jumlah HK</th>
              <th class="py-3 px-4 text-right">Panen (Ha)</th>
              <th class="py-3 px-4 text-right">Frekuensi</th>
              <?php if (!$isStaf): ?><th class="py-3 px-4 text-center">Aksi</th><?php endif; ?>
            </tr>
          </thead>
          <tbody id="tbody-data" class="text-gray-800">
            <tr><td colspan="<?= $isStaf ? 13 : 14 ?>" class="text-center py-10 text-gray-500">Memuat…</td></tr>
          </tbody>
          <tfoot>
            <tr class="bg-green-50 border-t-4 border-green-700 text-gray-900">
              <td class="py-3 px-4 font-semibold" colspan="5">TOTAL</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-luas">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-pokok">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-anggaran">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-realisasi">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-tandan">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-hk">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-panenha">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-freq">0</td>
              <?php if (!$isStaf): ?><td></td><?php endif; ?>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if (!$isStaf): ?>
<!-- Modal CRUD (hanya non-staf) — logika tetap, frekuensi readonly & auto-calc -->
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input LM-76</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Kebun</label>
          <select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2">
            <option value="">-- Pilih Kebun --</option>
            <?php foreach ($kebun as $k): ?>
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Unit/Defisi</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2" required>
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select id="bulan" name="bulan" class="w-full border rounded px-3 py-2" required>
            <?php foreach ($bulanList as $b): ?>
              <option value="<?= $b ?>"><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Tahun</label>
          <select id="tahun" name="tahun" class="w-full border rounded px-3 py-2" required>
            <?php for ($y=$tahunNow-1;$y<=$tahunNow+3;$y++): ?>
              <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">T. Tanam</label>
          <select id="tt" name="tt" class="w-full border rounded px-3 py-2" required>
            <option value="">-- Pilih Tahun Tanam --</option>
            <?php foreach ($ttList as $tt): ?>
              <option value="<?= htmlspecialchars($tt) ?>"><?= htmlspecialchars($tt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label class="block text-sm mb-1">Luas (Ha)</label><input type="number" step="0.01"  id="luas_ha"        name="luas_ha"        class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Invt Pokok</label> <input type="number"           id="jumlah_pohon"   name="jumlah_pohon"   class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Anggaran (Kg)</label><input type="number" step="0.001" id="anggaran_kg" name="anggaran_kg" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Realisasi (Kg)</label><input type="number" step="0.001" id="realisasi_kg" name="realisasi_kg" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Jumlah Tandan</label><input type="number" id="jumlah_tandan" name="jumlah_tandan" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Jumlah HK</label>    <input type="number" step="0.001" id="jumlah_hk"   name="jumlah_hk"   class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Panen (Ha)</label>   <input type="number" step="0.001" id="panen_ha"    name="panen_ha"    class="w-full border rounded px-3 py-2"></div>
        <div>
          <label class="block text-sm mb-1">Frekuensi</label>
          <input type="number" step="0.001" id="frekuensi" name="frekuensi"
                 class="w-full border rounded px-3 py-2 bg-gray-100"
                 readonly title="Otomatis dihitung: panen_ha / luas_ha">
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded border text-gray-800 hover:bg-gray-50">Batal</button>
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  /* ===== Role ===== */
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;
  const COLSPAN = IS_STAF ? 13 : 14;

  /* ===== DOM ===== */
  const $=s=>document.querySelector(s);
  const tbody=$('#tbody-data'),
        fKebun=$('#filter-kebun'),
        fUnit=$('#filter-unit'),
        fBulan=$('#filter-bulan'),
        fTahun=$('#filter-tahun'),
        fTT=$('#filter-tt');

  // Footer totals
  const tot={
    luas:$('#tot-luas'), pokok:$('#tot-pokok'), anggaran:$('#tot-anggaran'), realisasi:$('#tot-realisasi'),
    tandan:$('#tot-tandan'), hk:$('#tot-hk'), panenha:$('#tot-panenha'), freq:$('#tot-freq')
  };

  /* ===== Export (ikut filter) — tidak ubah endpoint ===== */
  const btnExportExcel = $('#btn-export-excel');
  const btnExportPdf   = $('#btn-export-pdf');
  const handleExport = (format) => {
    const params = new URLSearchParams({
      kebun_id: fKebun.value,
      unit_id:  fUnit.value,
      bulan:    fBulan.value,
      tahun:    fTahun.value,
      tt:       fTT.value,
    });
    const url = `cetak/lm76_${format}.php?${params.toString()}`;
    window.open(url, '_blank');
  };
  if (btnExportExcel) btnExportExcel.addEventListener('click', ()=>handleExport('excel'));
  if (btnExportPdf)   btnExportPdf  .addEventListener('click', ()=>handleExport('pdf'));

  /* ===== Utils ===== */
  const fmt=n=>Number(n||0).toLocaleString(undefined,{maximumFractionDigits:3});
  const sum=(arr,key)=>arr.reduce((a,r)=>a+(parseFloat(r[key])||0),0);
  const toNum=(v)=>{ const x=parseFloat(v); return Number.isNaN(x)?0:x; };
  const bulanOrder = {
    'Januari':1,'Februari':2,'Maret':3,'April':4,'Mei':5,'Juni':6,
    'Juli':7,'Agustus':8,'September':9,'Oktober':10,'November':11,'Desember':12
  };

  let allData=[];

  /* ========= FREKUENSI AUTO-CALC (panen_ha / luas_ha) ========= */
  function calcFreq(){
    const luas  = parseFloat(document.getElementById('luas_ha')?.value || '0');
    const panen = parseFloat(document.getElementById('panen_ha')?.value || '0');
    const out   = document.getElementById('frekuensi');
    const freq  = (luas > 0) ? (panen / luas) : 0;
    if (out) out.value = Number(freq).toFixed(3);
  }

  /* ======== Row Builder: frekuensi dihitung dari data (panen/luas) ======== */
  function buildRowHTML(r){
    const periode=`${r.bulan||'-'} ${r.tahun||'-'}`;
    const freqCalc = (toNum(r.luas_ha) > 0) ? (toNum(r.panen_ha) / toNum(r.luas_ha)) : 0;

    let actionCell='';
    if (!IS_STAF){
      actionCell = `
        <td class="py-2 px-3">
          <div class="flex items-center justify-center gap-2">
            <button class="btn-edit btn-icon text-blue-600 hover:text-blue-800" title="Edit" data-json="${encodeURIComponent(JSON.stringify(r))}">
              <i class="ti ti-pencil text-lg"></i>
            </button>
            <button class="btn-del btn-icon text-red-600 hover:text-red-800" title="Hapus" data-id="${r.id}">
              <i class="ti ti-trash text-lg"></i>
            </button>
          </div>
        </td>`;
    }
    return `
      <tr class="border-b hover:bg-gray-50">
        <td class="py-2 px-3">${r.tahun||'-'}</td>
        <td class="py-2 px-3">${r.nama_kebun||'-'}</td>
        <td class="py-2 px-3">${r.nama_unit||'-'}</td>
        <td class="py-2 px-3">${periode}</td>
        <td class="py-2 px-3">${r.tt||'-'}</td>
        <td class="py-2 px-3 text-right">${fmt(r.luas_ha)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.jumlah_pohon)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.anggaran_kg)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.realisasi_kg)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.jumlah_tandan)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.jumlah_hk)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.panen_ha)}</td>
        <td class="py-2 px-3 text-right">${fmt(freqCalc)}</td>
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
    tot.freq.textContent      = fmt(luas ? (pan/luas) : 0); // sudah benar
  }

  /* ======== Group & Render: ala LM-77 (Group by Unit) ======== */
  function groupAndRender(){
    if (!allData.length){
      tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-500">Belum ada data.</td></tr>`;
      renderGrandTotals(allData);
      return;
    }

    // Bucket Unit
    const unitBuckets = new Map();
    allData.forEach(r=>{
      const key = (r.nama_unit || '').trim() || '(Unit Tidak Diketahui)';
      if (!unitBuckets.has(key)) unitBuckets.set(key, []);
      unitBuckets.get(key).push(r);
    });

    // Urut nama Unit A-Z
    const unitKeys = Array.from(unitBuckets.keys()).sort((a,b)=>a.localeCompare(b));

    let html='';

    unitKeys.forEach(uKey=>{
      const rowsU = unitBuckets.get(uKey) || [];

      // Urut dalam Unit: Tahun ASC → Bulan ASC → T. Tanam ASC → Kebun (fallback)
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

      // Header Unit
      html += `<tr class="unit-head"><td class="py-2 px-3" colspan="${COLSPAN}">Unit/AFD: ${uKey}</td></tr>`;

      // Rows
      html += rowsU.map(buildRowHTML).join('');

      // Subtotal Unit (tetap pakai field asli)
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
          <td class="py-2 px-3" colspan="5">Jumlah (${uKey})</td>
          <td class="py-2 px-3 text-right">${fmt(uLuas)}</td>
          <td class="py-2 px-3 text-right">${fmt(uPokok)}</td>
          <td class="py-2 px-3 text-right">${fmt(uAgg)}</td>
          <td class="py-2 px-3 text-right">${fmt(uReal)}</td>
          <td class="py-2 px-3 text-right">${fmt(uTdn)}</td>
          <td class="py-2 px-3 text-right">${fmt(uHK)}</td>
          <td class="py-2 px-3 text-right">${fmt(uPanen)}</td>
          <td class="py-2 px-3 text-right">${fmt(uFreq)}</td>
          ${!IS_STAF ? '<td></td>' : ''}
        </tr>`;
    });

    tbody.innerHTML = html;
    renderGrandTotals(allData);
  }

  /* ==== Load from server (TIDAK diubah) ==== */
  function refresh(){
    const fd=new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    fd.append('kebun_id',fKebun.value);
    fd.append('unit_id',fUnit.value);
    fd.append('bulan',fBulan.value);
    fd.append('tahun',fTahun.value);
    fd.append('tt',fTT.value);

    tbody.innerHTML=`<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-500">Memuat…</td></tr>`;

    fetch('lm76_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        allData = (j && j.success && Array.isArray(j.data)) ? j.data : [];
        groupAndRender();
      })
      .catch(()=>{
        tbody.innerHTML=`<tr><td colspan="${COLSPAN}" class="text-center py-10 text-red-500">Gagal memuat data</td></tr>`;
      });
  }

  // init
  refresh();
  [fKebun,fUnit,fBulan,fTahun,fTT].forEach(el=>el.addEventListener('change', refresh));

  /* ===== CRUD: non-staf (tetap, tambah auto-calc hook) ===== */
  if (!IS_STAF){
    const modal=$('#crud-modal'), form=$('#crud-form'), titleEl=$('#modal-title');
    const open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')};
    const close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

    const setDefaultsFromFilter=()=>{
      if(fKebun.value) $('#kebun_id').value=fKebun.value;
      if(fUnit.value)  $('#unit_id').value=fUnit.value;
      if(fBulan.value) $('#bulan').value=fBulan.value;
      if(fTahun.value) $('#tahun').value=fTahun.value;
    };

    const btnAdd = document.getElementById('btn-add');
    if (btnAdd){
      btnAdd.addEventListener('click',()=>{
        form.reset();
        document.getElementById('form-action').value='store';
        document.getElementById('form-id').value='';
        titleEl.textContent='Input LM-76';
        setDefaultsFromFilter();
        calcFreq(); // frekuensi awal = 0
        open();
      });
    }

    const btnClose = document.getElementById('btn-close');
    const btnCancel = document.getElementById('btn-cancel');
    if (btnClose)  btnClose.addEventListener('click', close);
    if (btnCancel) btnCancel.addEventListener('click', close);

    // Dengarkan input luas & panen untuk hitung frekuensi real-time
    ['luas_ha','panen_ha'].forEach(id=>{
      const el=document.getElementById(id);
      if(el) el.addEventListener('input', calcFreq);
    });

    document.body.addEventListener('click',(e)=>{
      const t=e.target.closest('button'); if(!t) return;

      if (t.classList.contains('btn-edit')){
        const row=JSON.parse(decodeURIComponent(t.dataset.json||'{}'));
        form.reset();
        document.getElementById('form-action').value='update';
        document.getElementById('form-id').value=row.id||'';
        titleEl.textContent='Edit LM-76';
        ['kebun_id','unit_id','bulan','tahun','tt','luas_ha','jumlah_pohon','anggaran_kg','realisasi_kg','jumlah_tandan','jumlah_hk','panen_ha','frekuensi']
          .forEach(id=>{ const el=document.getElementById(id); if(el && row[id]!==undefined) el.value=row[id]??''; });
        calcFreq(); // pastikan frekuensi sesuai data panen/luas terkini
        open();
      }

      if (t.classList.contains('btn-del')){
        const id=t.dataset.id;
        Swal.fire({
          title:'Hapus data ini?', text:'Tindakan ini tidak dapat dibatalkan.', icon:'warning',
          showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#3085d6',
          confirmButtonText:'Ya, hapus', cancelButtonText:'Batal'
        }).then(res=>{
          if(!res.isConfirmed) return;
          const fd=new FormData();
          fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete'); fd.append('id',id);
          fetch('lm76_crud.php',{method:'POST',body:fd})
            .then(r=>r.json())
            .then(j=>{
              if(j.success){ Swal.fire('Terhapus!','Data berhasil dihapus.','success'); refresh(); }
              else Swal.fire('Gagal', j.message||'Tidak bisa menghapus','error');
            })
            .catch(err=>Swal.fire('Error', err?.message||'Network error','error'));
        });
      }
    });

    form.addEventListener('submit',(e)=>{
      e.preventDefault();
      const req=['unit_id','bulan','tahun','tt'];
      for(const id of req){
        const el=document.getElementById(id);
        if(!el || !el.value){ Swal.fire('Validasi',`Field ${id.replace('_',' ')} wajib diisi.`,'warning'); return; }
      }
      calcFreq(); // pastikan frekuensi terbaru terset sebelum kirim
      const fd=new FormData(form);
      fetch('lm76_crud.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(j=>{
          if(j.success){
            close();
            Swal.fire({icon:'success',title:'Berhasil',text:j.message,timer:1400,showConfirmButton:false});
            refresh();
          }else{
            const html=j.errors?.length?`<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>`:(j.message||'Terjadi kesalahan');
            Swal.fire('Gagal',html,'error');
          }
        })
        .catch(err=>Swal.fire('Error',err?.message||'Network error','error'));
    });
  }
});
</script>
