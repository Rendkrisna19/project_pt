<?php
// stok_gudang.php
// MODIFIKASI: Implementasi Hak Akses (Viewer, Staf, Admin)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

// --- Role Logic & Permissions ---
$userRole = $_SESSION['user_role'] ?? 'viewer'; // Default viewer

// Definisi Hak Akses
$isAdmin   = ($userRole === 'admin'); // Hanya Admin yang bisa Edit/Hapus (Lihat Kolom Aksi)
$isStaf    = ($userRole === 'staf');
$canInput  = ($isAdmin || $isStaf);   // Admin & Staf bisa Input
$showAction = $isAdmin;               // Kolom Aksi hanya untuk Admin

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

// ===== Master Data =====
$kebun = $conn->query("SELECT id, kode, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$bahan = $conn->query("
  SELECT b.id, b.kode, b.nama_bahan, s.nama AS satuan
  FROM md_bahan_kimia b
  JOIN md_satuan s ON s.id=b.satuan_id
  ORDER BY b.nama_bahan
")->fetchAll(PDO::FETCH_ASSOC);

// --- Waktu ---
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');
$bulanIndex = (int)date('n') - 1; 
$bulanNowName = $bulanList[$bulanIndex]; 

$currentPage = 'stok_gudang';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  /* --- CONTAINER UTAMA STICKY --- */
  .sticky-container {
    max-height: 72vh;
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }

  table.rekap {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1200px;
  }

  table.rekap th, table.rekap td {
    padding: 0.65rem 0.75rem;
    font-size: 0.85rem;
    white-space: nowrap;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0; 
  }

  table.rekap th:last-child, 
  table.rekap td:last-child {
    border-right: none;
  }

  table.rekap thead th {
    position: sticky;
    top: 0;
    background: #059fd3; 
    color: #fff;
    z-index: 10;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    height: 45px;
  }

  table.rekap tbody tr:hover td { background-color: #f8fafc; }
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .text-left { text-align: left; }
  
  .badge-satuan {
      background-color: #f1f5f9; color: #475569; padding: 4px 8px;
      border-radius: 6px; font-size: 0.75rem; font-weight: 600;
      border: 1px solid #e2e8f0; display: inline-block;
  }

  .btn-action {
      display: inline-flex; align-items: center; justify-content: center;
      width: 28px; height: 28px; border-radius: 4px; 
      transition: all 0.2s; border: 1px solid transparent; cursor: pointer;
  }
  .btn-action:hover { background-color: #f1f5f9; border-color: #cbd5e1; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">Bahan Kimia</h1>
      <p class="text-gray-500 text-sm mt-1">Rekap stok bahan kimia per kebun & periode</p>
    </div>
    <div class="flex gap-2">
      <button id="btn-export-excel" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2 shadow-sm transition" title="Export Excel">
        <i class="ti ti-file-spreadsheet"></i> Excel
      </button>
      <button id="btn-export-pdf" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 shadow-sm transition" title="Cetak PDF">
        <i class="ti ti-file-type-pdf"></i> PDF
      </button>
      
      <?php if ($canInput): ?>
      <button id="btn-add" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2 shadow-sm transition ml-2">
        <i class="ti ti-plus"></i> Input Stok
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
    <div class="flex items-center gap-2 text-gray-700 mb-3">
        <i class="ti ti-filter text-cyan-600"></i><span class="font-bold text-sm uppercase">Filter Data</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Kebun</label>
        <select id="filter-kebun" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none">
          <option value="">Semua Kebun</option>
          <?php foreach($kebun as $k): ?>
            <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['kode'].' — '.$k['nama_kebun']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Bahan Kimia</label>
        <select id="filter-bahan" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none">
          <option value="">Semua Bahan</option>
          <?php foreach($bahan as $b): ?>
            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['nama_bahan'].' ('.$b['satuan'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
        <select id="filter-bulan" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none">
          <option value="">Semua Bulan</option>
          <?php foreach ($bulanList as $bl): ?>
            <option value="<?= $bl ?>" <?= $bl === $bulanNowName ? 'selected' : '' ?>><?= $bl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
        <select id="filter-tahun" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none">
          <?php for ($y=$tahunNow-3; $y<=$tahunNow+2; $y++): ?>
            <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="flex flex-col md:flex-row items-center justify-between gap-3 px-1">
     <div class="text-sm text-gray-600 font-medium" id="range-label">Menampilkan 0–0 data</div>
     <div class="flex items-center gap-2">
        <span class="text-sm text-gray-600">Tampilkan</span>
        <select id="page-size" class="border rounded px-2 py-1 text-sm">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="text-sm text-gray-600">baris</span>
     </div>
  </div>

  <div class="sticky-container">
      <table class="rekap">
        <thead>
          <tr>
            <th class="text-left">Kebun</th>
            <th class="text-left">Nama Bahan</th> 
            <th class="text-center">Satuan</th>   
            <th class="text-right">Stok Awal</th>
            <th class="text-right">Mutasi Masuk</th>
            <th class="text-right">Mutasi Keluar</th>
            <th class="text-right">Pasokan</th>
            <th class="text-right">Dipakai</th>
            <th class="text-right">Net Mutasi</th>
            <th class="text-right">Sisa Stok</th>
            <?php if ($showAction): ?>
            <th class="text-center" style="width: 100px;">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="tbody-stok" class="text-gray-800">
          <tr><td colspan="<?= $showAction ? 11 : 10 ?>" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>
        </tbody>
      </table>
  </div>

  <div class="flex flex-col md:flex-row items-center justify-between gap-3 px-1 border-t pt-4">
      <div id="total-label" class="text-sm text-gray-600 font-medium">Total data: 0</div>
      <div class="flex items-center gap-1">
        <button id="btn-first" class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm text-gray-600">&laquo; First</button>
        <button id="btn-prev"  class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm text-gray-600">&lsaquo; Prev</button>
        <span id="page-info" class="px-3 text-sm text-gray-600 font-medium">Page 1/1</span>
        <button id="btn-next"  class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm text-gray-600">Next &rsaquo;</button>
        <button id="btn-last"  class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm text-gray-600">Last &raquo;</button>
      </div>
  </div>
</div>

<?php if ($canInput): ?>
<div id="crud-modal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
  <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-3xl transform scale-100 transition-transform">
    <div class="flex items-center justify-between mb-6 border-b pb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-800">Input Rekap Stok</h3>
      <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-1">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nama Kebun</label>
          <select name="kebun_id" id="kebun_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none" required>
            <option value="">— Pilih Kebun —</option>
            <?php foreach($kebun as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['kode'].' — '.$k['nama_kebun']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nama Bahan Kimia</label>
          <select name="bahan_id" id="bahan_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none" required>
            <option value="">— Pilih Bahan —</option>
            <?php foreach($bahan as $b): ?>
              <option value="<?= (int)$b['id'] ?>" data-satuan="<?= htmlspecialchars($b['satuan']) ?>"><?= htmlspecialchars($b['nama_bahan'].' ('.$b['satuan'].')') ?></option>
            <?php endforeach; ?>
          </select>
          <p class="text-xs text-gray-500 mt-1">Satuan: <span id="hint-satuan" class="font-semibold text-gray-800">-</span></p>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Bulan</label>
          <select name="bulan" id="bulan" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none" required>
            <?php foreach ($bulanList as $bl): ?><option value="<?= $bl ?>"><?= $bl ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tahun</label>
          <select name="tahun" id="tahun" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none" required>
            <?php for ($y = $tahunNow-1; $y <= $tahunNow+3; $y++): ?>
              <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Stok Awal</label><input type="number" step="0.01" name="stok_awal" id="stok_awal" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" min="0" value="0"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mutasi Masuk</label><input type="number" step="0.01" name="mutasi_masuk" id="mutasi_masuk" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" min="0" value="0"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mutasi Keluar</label><input type="number" step="0.01" name="mutasi_keluar" id="mutasi_keluar" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" min="0" value="0"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Pasokan</label><input type="number" step="0.01" name="pasokan" id="pasokan" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" min="0" value="0"></div>
        <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Dipakai</label><input type="number" step="0.01" name="dipakai" id="dipakai" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" min="0" value="0"></div>
      </div>
      <div class="flex justify-end gap-3 mt-8 pt-4 border-t border-gray-100">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50 text-sm font-medium">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 text-sm font-medium shadow-lg shadow-cyan-500/30">Simpan Data</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // === LOGIKA Frontend berdasarkan Role PHP ===
  const SHOW_ACTION = <?= $showAction ? 'true' : 'false'; ?>; // Hanya true jika Admin
  const COL_SPAN    = SHOW_ACTION ? 11 : 10;

  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-stok');
  const selKebun = $('#filter-kebun'), selBahan = $('#filter-bahan'), selBulan = $('#filter-bulan'), selTahun = $('#filter-tahun');
  const pageSizeEl = $('#page-size'), rangeLabel = $('#range-label'), totalLabel = $('#total-label'), pageInfo = $('#page-info');
  const btnFirst = $('#btn-first'), btnPrev = $('#btn-prev'), btnNext = $('#btn-next'), btnLast = $('#btn-last');

  // Modal logic (mungkin null jika Viewer)
  const modal = $('#crud-modal'), btnAdd = $('#btn-add'), btnClose = $('#btn-close'), btnCancel = $('#btn-cancel');
  const form = $('#crud-form'), title = $('#modal-title'), formAction = $('#form-action'), formId = $('#form-id');

  const open = () => { if(modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); } }
  const close = () => { if(modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); } }

  let allRows = [], currentPage = 1, pageSize = parseInt(pageSizeEl.value || '10', 10);

  // Helper Sort & Format
  const MONTH_IDX = {'januari':1,'februari':2,'maret':3,'april':4,'mei':5,'juni':6,'juli':7,'agustus':8,'september':9,'oktober':10,'november':11,'desember':12};
  const norm = v => (v||'').toString().trim().toLowerCase();
  function parseRowTimestamp(r){
    if (r.created_at) return new Date(r.created_at).getTime();
    const th = parseInt(r.tahun,10), bln = MONTH_IDX[norm(r.bulan)];
    if (!isNaN(th) && bln) return new Date(th, bln-1, 28).getTime();
    return 0;
  }
  function compareDescByLatest(a,b){
    const ta = parseRowTimestamp(a), tb = parseRowTimestamp(b);
    if (tb !== ta) return tb - ta; 
    return (b.id||0) - (a.id||0);
  }
  function numberFmt(x) {
    const val = parseFloat(x ?? 0);
    return val === 0 ? '-' : val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function renderPage(){
    const total = allRows.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * pageSize;
    const endIdx   = Math.min(startIdx + pageSize, total);
    const pageRows = allRows.slice(startIdx, endIdx);

    if (total === 0){
      tbody.innerHTML = `<tr><td colspan="${COL_SPAN}" class="text-center py-8 text-gray-400 italic">Belum ada data untuk filter ini.</td></tr>`;
    } else {
      tbody.innerHTML = pageRows.map(row => `
        <tr class="hover:bg-blue-50 transition-colors">
          <td>
            <div class="font-semibold text-gray-800">${row.kebun_kode || ''}</div>
            <div class="text-xs text-gray-500">${row.nama_kebun || ''}</div>
          </td>
          <td><div class="font-semibold text-gray-800">${row.nama_bahan || ''}</div></td>
          <td class="text-center"><span class="badge-satuan">${row.satuan||'-'}</span></td>
          <td class="text-right text-gray-600">${numberFmt(row.stok_awal)}</td>
          <td class="text-right text-gray-600">${numberFmt(row.mutasi_masuk)}</td>
          <td class="text-right text-gray-600">${numberFmt(row.mutasi_keluar)}</td>
          <td class="text-right text-gray-600">${numberFmt(row.pasokan)}</td>
          <td class="text-right text-gray-600">${numberFmt(row.dipakai)}</td>
          <td class="text-right text-gray-600">${numberFmt(row.net_mutasi)}</td>
          <td class="text-right font-bold text-gray-900">${numberFmt(row.sisa_stok)}</td>
          
          ${SHOW_ACTION ? `
          <td class="text-center">
            <div class="flex justify-center gap-1">
              <button class="btn-action text-cyan-600 hover:text-cyan-800" title="Edit" data-json='${encodeURIComponent(JSON.stringify(row))}'><i class="ti ti-pencil"></i></button>
              <button class="btn-action text-red-600 hover:text-red-800" title="Hapus" data-id="${row.id}"><i class="ti ti-trash"></i></button>
            </div>
          </td>` : ''}
        </tr>
      `).join('');
    }

    const showFrom = total === 0 ? 0 : (startIdx + 1);
    rangeLabel.textContent = `Menampilkan ${showFrom}–${endIdx}`;
    totalLabel.textContent = `Total data: ${total}`;
    pageInfo.textContent   = `Page ${currentPage}/${totalPages}`;
    btnFirst.disabled = (currentPage <= 1); btnPrev.disabled = (currentPage <= 1);
    btnNext.disabled = (currentPage >= totalPages); btnLast.disabled = (currentPage >= totalPages);
    [btnFirst, btnPrev, btnNext, btnLast].forEach(b => b.classList.toggle('opacity-50', b.disabled));
  }

  function refreshList(){
    const fd = new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    if (selKebun.value) fd.append('kebun_id', selKebun.value);
    if (selBahan.value) fd.append('bahan_id', selBahan.value);
    if (selBulan.value) fd.append('bulan', selBulan.value);
    fd.append('tahun', selTahun.value || '<?= (int)date('Y') ?>');

    tbody.innerHTML = `<tr><td colspan="${COL_SPAN}" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin"></i> Memuat data...</td></tr>`;
    fetch('stok_gudang_crud.php',{method:'POST', body:fd})
      .then(r=>r.json())
      .then(j=>{
        if (!j.success) {
          allRows = []; renderPage();
          tbody.innerHTML = `<tr><td colspan="${COL_SPAN}" class="text-center py-8 text-red-500">${j.message||'Gagal memuat data'}</td></tr>`;
          return;
        }
        allRows = (Array.isArray(j.data) ? j.data : []).sort(compareDescByLatest);
        currentPage = 1; renderPage();
      })
      .catch(err=>{
        allRows = []; renderPage();
        tbody.innerHTML = `<tr><td colspan="${COL_SPAN}" class="text-center py-8 text-red-500">Network error</td></tr>`;
      });
  }

  refreshList();
  
  // Event Listeners
  [selKebun, selBahan, selBulan, selTahun].forEach(el=> el.addEventListener('change', refreshList));
  pageSizeEl.addEventListener('change', ()=>{ pageSize=parseInt(pageSizeEl.value,10); currentPage=1; renderPage(); });
  btnFirst.addEventListener('click', ()=>{ currentPage=1; renderPage(); });
  btnPrev.addEventListener('click', ()=>{ currentPage=Math.max(1, currentPage-1); renderPage(); });
  btnNext.addEventListener('click', ()=>{ 
    const totalPages = Math.max(1, Math.ceil(allRows.length / pageSize));
    currentPage = Math.min(totalPages, currentPage+1); renderPage(); 
  });
  btnLast.addEventListener('click', ()=>{ currentPage=Math.max(1, Math.ceil(allRows.length/pageSize)); renderPage(); });

  // Update Satuan Hint
  const updateSatuanHint = () => {
    const opt = document.querySelector('#bahan_id option:checked');
    if($('#hint-satuan')) $('#hint-satuan').textContent = opt?.dataset?.satuan || '-';
  }
  if(document.getElementById('bahan_id')) document.getElementById('bahan_id').addEventListener('change', updateSatuanHint);

  // Logic Tombol Input & CRUD (Hanya ada jika element ada)
  if (btnAdd) {
    btnAdd.addEventListener('click', ()=>{
      form.reset(); formId.value = ''; formAction.value = 'store';
      title.textContent = 'Input Rekap Stok Baru';
      if (selKebun.value) $('#kebun_id').value = selKebun.value;
      if (selBahan.value) $('#bahan_id').value = selBahan.value;
      if (selBulan.value) $('#bulan').value = selBulan.value;
      $('#tahun').value = selTahun.value;
      updateSatuanHint(); open();
    });
    btnClose.addEventListener('click', close);
    btnCancel.addEventListener('click', close);

    form.addEventListener('submit', (e)=>{
      e.preventDefault();
      const fd = new FormData(form);
      fetch('stok_gudang_crud.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(j=>{
          if (j.success){
            close(); Swal.fire({icon:'success',title:'Berhasil',text:j.message,timer:1400,showConfirmButton:false});
            refreshList();
          } else {
            const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
            Swal.fire('Gagal', html || 'Error', 'error');
          }
        })
        .catch(err=> Swal.fire('Error', 'Network error', 'error'));
    });
  }

  // Edit & Hapus (Event delegation, hanya berjalan jika tombol ada)
  if (SHOW_ACTION) {
      document.body.addEventListener('click', (e)=>{
        const btn = e.target.closest('button');
        if (!btn) return;

        if (btn.title === 'Edit' && btn.dataset.json) {
          const row = JSON.parse(decodeURIComponent(btn.dataset.json));
          // Pastikan modal ada (karena Admin bisa Edit)
          if(form) {
             form.reset(); formAction.value='update'; formId.value = row.id;
             title.textContent='Edit Rekap Stok';
             ['kebun_id','bahan_id','bulan','tahun','stok_awal','mutasi_masuk','mutasi_keluar','pasokan','dipakai'].forEach(k=>{
               if ($('#'+k)) $('#'+k).value = row[k] ?? '';
             });
             updateSatuanHint(); open();
          }
        }

        if (btn.title === 'Hapus' && btn.dataset.id) {
          const id = btn.dataset.id;
          Swal.fire({
            title:'Hapus data?', text:'Tidak bisa dikembalikan.', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33'
          }).then(res=>{
            if (!res.isConfirmed) return;
            const fd = new FormData();
            fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
            fd.append('action','delete'); fd.append('id', id);
            fetch('stok_gudang_crud.php',{method:'POST',body:fd})
              .then(r=>r.json())
              .then(j=>{
                if(j.success){ Swal.fire('Terhapus!', j.message, 'success'); refreshList(); }
                else Swal.fire('Gagal', j.message, 'error');
              });
          });
        }
      });
  }

  document.getElementById('btn-export-excel').addEventListener('click', ()=>{
    const qs = new URLSearchParams({csrf_token: '<?= htmlspecialchars($CSRF) ?>', kebun_id: selKebun.value, bahan_id: selBahan.value, bulan: selBulan.value, tahun: selTahun.value}).toString();
    window.open('cetak/stok_gudang_export_excel.php?'+qs, '_blank');
  });
  document.getElementById('btn-export-pdf').addEventListener('click', ()=>{
    const qs = new URLSearchParams({csrf_token: '<?= htmlspecialchars($CSRF) ?>', kebun_id: selKebun.value, bahan_id: selBahan.value, bulan: selBulan.value, tahun: selTahun.value}).toString();
    window.open('cetak/stok_gudang_export_pdf.php?'+qs, '_blank');
  });
});
</script>