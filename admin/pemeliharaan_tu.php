<?php
// pages/pemeliharaan_tu.php — Rekap TM (Live Filter / No Reload)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php");
  exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

$AFDS = ['AFD01','AFD02','AFD03','AFD04','AFD05','AFD06','AFD07','AFD08','AFD09','AFD10'];

/* Master Data untuk Dropdown */
$jenisMaster = $conn->query("SELECT nama FROM md_pemeliharaan_tu ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
$TENAGA      = $conn->query("SELECT id, kode FROM md_tenaga ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC);
$JENIS_ROWS  = $conn->query("SELECT id, nama FROM md_pemeliharaan_tu ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

/* Konfigurasi Kolom */
$monthLabels = ['Jan','Feb','Mar','Apr','Mei','Juni','Juli','Agust','Sept','Okt','Nov','Des'];
$monthKeys   = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des']; 
$COLS_TOTAL  = 7 + 1 + count($monthLabels) + 1 + 1 + 1 + ($isStaf ? 0 : 1);

$currentPage = 'pemeliharaan_tu';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* --- CONTAINER UTAMA STICKY --- */
  .sticky-container {
    max-height: 75vh;
    overflow: auto;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }

  /* Tabel Styling */
  table.rekap {
    width: 100%;
    border-collapse: separate; 
    border-spacing: 0;
    min-width: 1800px; 
  }

  table.rekap th, table.rekap td {
    padding: 0.65rem 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    border-right: 1px solid #f3f4f6;
    border-left: 1px solid #f3f4f6;
    font-size: 0.85rem;
    white-space: nowrap;
    vertical-align: middle;
  }

  /* Sticky Header Logic */
  table.rekap thead th {
    position: sticky;
    background: #059fd3; 
    color: #fff;
    z-index: 10;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.03em;
    font-size: 0.75rem;
    box-shadow: inset 0 -1px 0 rgba(255,255,255,0.2);
  }

  table.rekap thead tr:nth-child(1) th { top: 0; height: 45px; }
  table.rekap thead tr:nth-child(2) th { top: 45px; box-shadow: 0 2px 3px rgba(0,0,0,0.15); z-index: 9; }

  table.rekap tr td:last-child, table.rekap tr th:last-child { border-right: none; }

  /* Colors & Groups */
  tr.group-head td { background: linear-gradient(90deg, #eff6ff 0%, #f8fafc 100%); border-top: 1px solid #bfdbfe; border-bottom: 1px solid #bfdbfe; color: #1e3a8a; font-weight: 700; padding: 0.8rem; position: sticky; left: 0; } 
  tr.sum-jenis td { background: #f0fdf4; font-weight: 700; color: #14532d; border-top: 2px solid #bbf7d0; }
  tr.sum-rayon td { background: #fff7ed; font-weight: 600; color: #7c2d12; }
  
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .delta-pos { color: #dc2626; font-weight: 700; } 
  .delta-neg { color: #16a34a; font-weight: 700; }
  .cell-anggaran { background: rgba(5, 159, 211, 0.04); font-weight: 600; color: #0c4a6e; }

  /* Toolbar Grid Updated (Tanpa Button Column) */
  .toolbar { display: grid; grid-template-columns: repeat(12, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
  .toolbar > * { grid-column: span 12; }
  @media (min-width: 768px){
    /* Layout baru agar input full width */
    .toolbar > .col-thn { grid-column: span 2; }
    .toolbar > .col-afd { grid-column: span 2; }
    .toolbar > .col-jns { grid-column: span 3; }
    .toolbar > .col-hk  { grid-column: span 2; }
    .toolbar > .col-src { grid-column: span 3; }
  }

  .btn { display: inline-flex; align-items: center; gap: 0.45rem; border: none; background: #059fd3; color: #fff; border-radius: 0.5rem; padding: 0.5rem 1rem; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; cursor: pointer; }
  .btn:hover { background: #0386b3; }
  .btn-gray { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
  .act { display: inline-grid; place-items: center; width: 32px; height: 32px; border-radius: 0.375rem; border: 1px solid #e5e7eb; background: #fff; color: #4b5563; cursor: pointer; transition: 0.2s; }
  .act:hover { background: #f9fafb; border-color: #d1d5db; }
</style>

<div class="space-y-6">
  <div class="flex justify-between items-center">
  <h1 class="text-2xl font-bold text-gray-800">
      Pemeliharaan TU
      <span id="loading-indicator" class="text-sm font-normal text-blue-600 hidden ml-2">
          <i class="ti ti-loader animate-spin"></i> Memuat...
      </span>
  </h1>

  <div class="flex gap-2">
      <button onclick="exportData('excel')" class="btn shadow-sm" style="background-color: #236f9eff;">
          <i class="ti ti-file-spreadsheet"></i> <span class="hidden md:inline">Excel</span>
      </button>

      <button onclick="exportData('pdf')" class="btn shadow-sm" style="background-color: #ef4444a7;">
          <i class="ti ti-file-type-pdf"></i> <span class="hidden md:inline">PDF</span>
      </button>

      <?php if (!$isStaf): ?>
        <button id="btn-add" class="btn shadow-md"><i class="ti ti-plus"></i> Tambah Data</button>
      <?php endif; ?>
  </div>
</div>

  <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 toolbar">
    <div class="col-thn">
      <label class="text-xs font-bold text-gray-500 uppercase mb-1 block">Tahun</label>
      <input type="number" id="f_tahun" min="2000" max="2100" value="<?= date('Y') ?>" 
             class="filter-input w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-200 outline-none transition">
    </div>
    <div class="col-afd">
      <label class="text-xs font-bold text-gray-500 uppercase mb-1 block">AFD</label>
      <select id="f_afd" class="filter-input w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-200 outline-none transition">
        <option value="">— Semua AFD —</option>
        <?php foreach($AFDS as $a): ?>
          <option value="<?= $a ?>"><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-jns">
      <label class="text-xs font-bold text-gray-500 uppercase mb-1 block">Jenis Pekerjaan</label>
      <select id="f_jenis" class="filter-input w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-200 outline-none transition">
        <option value="">— Semua Jenis —</option>
        <?php foreach($jenisMaster as $jn): ?>
          <option value="<?= htmlspecialchars($jn) ?>"><?= htmlspecialchars($jn) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-hk">
      <label class="text-xs font-bold text-gray-500 uppercase mb-1 block">HK</label>
      <select id="f_hk" class="filter-input w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-200 outline-none transition">
        <option value="">— Semua HK —</option>
        <?php foreach($TENAGA as $t): ?>
          <option value="<?= htmlspecialchars($t['kode']) ?>"><?= htmlspecialchars($t['kode']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-src">
      <label class="text-xs font-bold text-gray-500 uppercase mb-1 block">Pencarian (Ket)</label>
      <div class="relative">
        <input id="f_ket" class="filter-input w-full border border-gray-300 rounded-lg px-3 py-2 pl-9 focus:ring-2 focus:ring-blue-200 outline-none transition" placeholder="Cari keterangan...">
        <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
      </div>
    </div>
  </div>

  <div class="flex justify-between items-center px-1 mb-2">
      <div class="text-sm text-gray-600 font-medium" id="filter-info">
         Menampilkan data...
      </div>
      <div id="loading-indicator" class="hidden text-blue-600 text-sm font-bold flex items-center gap-1">
         <i class="ti ti-loader animate-spin"></i> Memuat...
      </div>
  </div>

  <div class="sticky-container">
    <table class="rekap" id="tm-table">
      <thead>
        <tr>
          <th rowspan="2" style="min-width:60px;">Tahun</th>
          <th rowspan="2" style="min-width:100px;">Kebun</th>
          <th rowspan="2" style="min-width:80px;">Rayon</th>
          <th rowspan="2" style="min-width:80px;">Unit</th>
          <th rowspan="2" style="min-width:150px;">Ket</th>
          <th rowspan="2" style="min-width:60px;">HK</th>
          <th rowspan="2" style="min-width:60px;">Sat</th>
          <th rowspan="2" class="text-right" style="min-width:120px;">Anggaran Thn</th>
          <th colspan="<?= count($monthLabels) ?>" class="text-center">Realisasi Bulanan</th>
          <th rowspan="2" class="text-right" style="min-width:120px;">Jumlah Realisasi</th>
          <th rowspan="2" class="text-right" style="min-width:120px;">+/- Anggaran</th>
          <th rowspan="2" class="text-right" style="min-width:80px;">%</th>
          <?php if(!$isStaf): ?><th rowspan="2" class="text-center" style="min-width:100px;">Aksi</th><?php endif; ?>
        </tr>
        <tr>
          <?php foreach($monthLabels as $m): ?>
            <th class="text-center" style="min-width:85px;"><?= $m ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody id="tm-body">
        </tbody>
    </table>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>

function exportData(type) {
    // 1. Cek apakah elemen ada sebelum mengambil value agar tidak error
    const getVal = (id) => {
        const el = document.getElementById(id);
        return el ? el.value : '';
    };

    // 2. Gunakan ID yang SESUAI dengan HTML di atas (f_tahun, f_afd, dst)
    const params = new URLSearchParams({
        action: 'export',          // Tambahkan action jika diperlukan di PHP
        tahun:  getVal('f_tahun'), // SEBELUMNYA: filter_tahun (Salah)
        afd:    getVal('f_afd'),   // SEBELUMNYA: filter_kebun (Salah)
        jenis:  getVal('f_jenis'), // SEBELUMNYA: filter_jenis (Benar, tapi cek lagi)
        hk:     getVal('f_hk'),    // SEBELUMNYA: filter_hk (Benar)
        ket:    getVal('f_ket')    // SEBELUMNYA: filter_ket (Benar)
    });

    // Tentukan file tujuan
    const baseUrl = 'cetak/'; 
    const file = type === 'pdf' ? 'pemeliharaan_tu_pdf.php' : 'pemeliharaan_tu_excel.php';
    
    // Buka di tab baru
    window.open(baseUrl + file + '?' + params.toString(), '_blank');
}



document.addEventListener('DOMContentLoaded', ()=>{
  const IS_STAF = <?= $isStaf ? 'true' : 'false' ?>;
  const CSRF    = '<?= htmlspecialchars($CSRF) ?>';
  const months  = <?= json_encode($monthKeys) ?>;
  const COLS_TOTAL = <?= $COLS_TOTAL ?>;

  // Helper Formatter
  const nf      = (n)=> Number(n||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
  const dash    = (n)=> (Number(n||0)===0 ? '<span class="text-gray-300">—</span>' : nf(n));
  const dashPct = (n)=> (Number(n||0)===0 ? '<span class="text-gray-300">—</span>' : `${nf(n)}%`);

  // Elements
  const btnAdd    = document.getElementById('btn-add');
  const tbody     = document.getElementById('tm-body');
  const loader    = document.getElementById('loading-indicator');
  const infoDiv   = document.getElementById('filter-info');
  
  // Filter Inputs
  const inpTahun  = document.getElementById('f_tahun');
  const inpAfd    = document.getElementById('f_afd');
  const inpJenis  = document.getElementById('f_jenis');
  const inpHk     = document.getElementById('f_hk');
  const inpKet    = document.getElementById('f_ket');

  if (btnAdd) btnAdd.addEventListener('click', ()=> openForm({ unit_kode: inpAfd.value || 'AFD01', tahun: inpTahun.value }));

  // Rayon Logic
  const rayonA_AFDS = ['AFD02','AFD03','AFD04','AFD05','AFD06'];
  const rayonB_AFDS = ['AFD01','AFD07','AFD08','AFD09','AFD10'];

  // --- CORE FUNCTION: Fetch Data Realtime ---
  async function fetchData() {
    // UI State: Loading
    loader.classList.remove('hidden');
    tbody.style.opacity = '0.5';
    
    // Update Info Text
    infoDiv.innerHTML = `Menampilkan Data Tahun <b>${inpTahun.value}</b> ${inpAfd.value ? '• ' + inpAfd.value : ''}`;

    // Build Params
    const qs = new URLSearchParams({
      action: 'list',
      tahun:  inpTahun.value,
      afd:    inpAfd.value,
      hk:     inpHk.value,
      jenis:  inpJenis.value,
      ket:    inpKet.value
    });

    try {
        const res = await fetch('pemeliharaan_tu_crud.php?' + qs.toString(), {credentials:'same-origin'});
        const j = await res.json();
        
        if (!j.success){
          tbody.innerHTML = `<tr><td colspan="${COLS_TOTAL}" class="text-center py-6 text-red-600 font-bold"><i class="ti ti-alert-circle"></i> ${j.message||'Error'}</td></tr>`;
          return;
        }
        
        renderTable(j);

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="${COLS_TOTAL}" class="text-center py-6 text-red-600">Gagal terhubung ke server.</td></tr>`;
    } finally {
        loader.classList.add('hidden');
        tbody.style.opacity = '1';
    }
  }

  // --- RENDERING TABLE (Sama persis logicnya) ---
  function renderTable(data) {
    const rows  = data.rows || [];
    const order = data.jenis_order || [];
    const kebunNama = data.kebun_nama || 'Sei Rokan';
    
    // Empty check
    if(rows.length === 0 && order.length === 0) {
       tbody.innerHTML = `<tr><td colspan="${COLS_TOTAL}" class="text-center py-10 text-gray-400 italic">Tidak ada data ditemukan untuk filter ini.</td></tr>`;
       return;
    }

    // Grouping
    const byJenis = {};
    order.forEach(jn => byJenis[jn] = []);
    rows.forEach(r=>{
      const jn = r.jenis_nama || 'Tidak Berjenis';
      if (!byJenis[jn]) byJenis[jn]=[];
      byJenis[jn].push(r);
    });

    let html = '';
    const emptyRow = (jn)=>`<tr class="text-gray-400 italic"><td colspan="${COLS_TOTAL}" class="py-3 text-center">Tidak ada data transaksi untuk <b>${jn}</b></td></tr>`;

    for (const jn of order){
      html += `<tr class="group-head"><td colspan="${COLS_TOTAL}"><i class="ti ti-folder-open text-lg mr-1 align-text-bottom"></i> ${jn}</td></tr>`;
      
      const list = (byJenis[jn]||[]).sort((a,b)=> (a.unit_kode||'').localeCompare(b.unit_kode||'') || (a.id - b.id));
      
      if (!list.length) { 
          html += emptyRow(jn); 
          continue; 
      }

      // accumulator jenis & rayon
      const sumJenis = {anggaran:0, jumlah:0};
      const sumRY = {'RY A':{anggaran:0,jumlah:0}, 'RY B':{anggaran:0,jumlah:0}};
      const perBulanJenis = Object.fromEntries(months.map(m=>[m,0]));
      const perBulanRYA   = Object.fromEntries(months.map(m=>[m,0]));
      const perBulanRYB   = Object.fromEntries(months.map(m=>[m,0]));

      for (const r of list){
        const jml = months.reduce((a,m)=> a + Number(r[m]||0), 0);
        const delt= jml - Number(r.anggaran_tahun||0);
        const prog= Number(r.anggaran_tahun||0) > 0 ? (jml/Number(r.anggaran_tahun)*100) : 0;
        const deltStr = Number(delt||0)===0 ? '<span class="text-gray-300">—</span>' : nf(delt);
        const deltCls = delt < 0 ? 'delta-neg' : (delt > 0 ? 'delta-pos' : '');

        sumJenis.anggaran += Number(r.anggaran_tahun||0);
        sumJenis.jumlah   += jml;

        // Rayon label logic
        const unit = r.unit_kode || '';
        let rayonLabel = r.rayon_nama || '';
        const isRayonA = rayonA_AFDS.includes(unit);
        const isRayonB = rayonB_AFDS.includes(unit);
        if (!rayonLabel) { if (isRayonA) rayonLabel = 'RY A'; else if (isRayonB) rayonLabel = 'RY B'; }

        months.forEach(m=>{
          const val = Number(r[m]||0);
          perBulanJenis[m] += val;
          if (isRayonA) perBulanRYA[m] += val;
          else if (isRayonB) perBulanRYB[m] += val;
        });

        if (isRayonA) { sumRY['RY A'].anggaran += Number(r.anggaran_tahun||0); sumRY['RY A'].jumlah += jml; }
        else if (isRayonB){ sumRY['RY B'].anggaran += Number(r.anggaran_tahun||0); sumRY['RY B'].jumlah += jml; }

        html += `
          <tr class="hover:bg-blue-50 transition-colors">
            <td>${r.tahun||''}</td>
            <td>${kebunNama}</td>
            <td>${rayonLabel||''}</td>
            <td><span class="px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs font-bold">${r.unit_kode||''}</span></td>
            <td class="text-gray-600 italic">${r.ket||''}</td>
            <td>${r.hk||''}</td>
            <td>${r.satuan||''}</td>
            <td class="text-right cell-anggaran">${dash(r.anggaran_tahun)}</td>
            ${months.map(m=>`<td class="text-right text-gray-700">${dash(r[m])}</td>`).join('')}
            <td class="text-right font-semibold text-gray-900">${dash(jml)}</td>
            <td class="text-right ${deltCls}">${deltStr}</td>
            <td class="text-right text-sm">${dashPct(prog)}</td>
            ${!IS_STAF ? `
            <td class="text-center">
              <div class="flex justify-center gap-1">
                <button class="act text-blue-600 border-blue-200 hover:bg-blue-50" title="Edit" data-edit='${JSON.stringify(r).replaceAll("'","&apos;")}'><i class="ti ti-pencil"></i></button>
                <button class="act text-red-600 border-red-200 hover:bg-red-50" title="Hapus" data-del="${r.id}"><i class="ti ti-trash"></i></button>
              </div>
            </td>` : ''}
          </tr>`;
      }

      // Subtotal per jenis
      const deltaJenis = (sumJenis.jumlah - sumJenis.anggaran);
      html += `
        <tr class="sum-jenis text-sm">
          <td colspan="7" class="text-right pr-4 uppercase">Total ${jn}</td>
          <td class="text-right cell-anggaran">${dash(sumJenis.anggaran)}</td>
          ${months.map(m=>`<td class="text-right">${dash(perBulanJenis[m])}</td>`).join('')}
          <td class="text-right">${dash(sumJenis.jumlah)}</td>
          <td class="text-right ${(deltaJenis<0)?'delta-neg':(deltaJenis>0?'delta-pos':'')}">${Number(deltaJenis||0)===0?'—':nf(deltaJenis)}</td>
          <td class="text-right">${sumJenis.anggaran>0?dashPct(sumJenis.jumlah/sumJenis.anggaran*100):'—'}</td>
          ${!IS_STAF ? '<td></td>' : ''}
        </tr>
      `;

      // Subtotal Rayon A
      if (sumRY['RY A'].anggaran > 0 || sumRY['RY A'].jumlah > 0) {
        const dRYA = sumRY['RY A'].jumlah - sumRY['RY A'].anggaran;
        html += `
          <tr class="sum-rayon text-xs">
            <td colspan="7" class="text-right pr-4 uppercase text-gray-500">Subtotal Rayon A</td>
            <td class="text-right cell-anggaran">${dash(sumRY['RY A'].anggaran)}</td>
            ${months.map(m=>`<td class="text-right">${dash(perBulanRYA[m])}</td>`).join('')}
            <td class="text-right">${dash(sumRY['RY A'].jumlah)}</td>
            <td class="text-right ${(dRYA<0)?'delta-neg':(dRYA>0?'delta-pos':'')}">${Number(dRYA||0)===0?'—':nf(dRYA)}</td>
            <td class="text-right">${sumRY['RY A'].anggaran>0?dashPct(sumRY['RY A'].jumlah/sumRY['RY A'].anggaran*100):'—'}</td>
            ${!IS_STAF ? '<td></td>' : ''}
          </tr>`;
      }

      // Subtotal Rayon B
      if (sumRY['RY B'].anggaran > 0 || sumRY['RY B'].jumlah > 0) {
        const dRYB = sumRY['RY B'].jumlah - sumRY['RY B'].anggaran;
        html += `
          <tr class="sum-rayon text-xs">
            <td colspan="7" class="text-right pr-4 uppercase text-gray-500">Subtotal Rayon B</td>
            <td class="text-right cell-anggaran">${dash(sumRY['RY B'].anggaran)}</td>
            ${months.map(m=>`<td class="text-right">${dash(perBulanRYB[m])}</td>`).join('')}
            <td class="text-right">${dash(sumRY['RY B'].jumlah)}</td>
            <td class="text-right ${(dRYB<0)?'delta-neg':(dRYB>0?'delta-pos':'')}">${Number(dRYB||0)===0?'—':nf(dRYB)}</td>
            <td class="text-right">${sumRY['RY B'].anggaran>0?dashPct(sumRY['RY B'].jumlah/sumRY['RY B'].anggaran*100):'—'}</td>
            ${!IS_STAF ? '<td></td>' : ''}
          </tr>`;
      }
    } // end for order
    
    tbody.innerHTML = html;
    
    // Re-bind actions
    if (!IS_STAF) bindActions();
  }

  // --- ACTION BINDING (Edit/Delete) ---
  function bindActions(){
      tbody.querySelectorAll('[data-edit]').forEach(b=>{
        b.addEventListener('click', ()=> openForm(JSON.parse(b.dataset.edit||'{}')));
      });
      tbody.querySelectorAll('[data-del]').forEach(b=>{
        b.addEventListener('click', async ()=>{
          const id = b.dataset.del;
          const y = await Swal.fire({
            title:'Hapus data ini?', icon:'warning', text:'Data tidak dapat dikembalikan.',
            showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal'
          });
          if(!y.isConfirmed) return;
          const fd=new FormData();
          fd.append('csrf_token',CSRF);
          fd.append('action','delete');
          fd.append('id',id);
          const r = await fetch('pemeliharaan_tu_crud.php',{method:'POST',body:fd});
          const jj=await r.json();
          if (jj.success){ Swal.fire('Berhasil','Data dihapus','success'); fetchData(); } // Panggil fetchData() bukan reload
          else { Swal.fire('Gagal',jj.message||'Error','error'); }
        });
      });
  }

  // --- EVENT LISTENERS (Auto Filter) ---
  // 1. Debounce function untuk text input
  function debounce(func, wait) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  }

  // 2. Pasang Listener
  const filters = [inpTahun, inpAfd, inpJenis, inpHk];
  filters.forEach(el => el.addEventListener('change', fetchData));
  
  // Khusus pencarian text pakai debounce 500ms agar tidak spam request
  inpKet.addEventListener('input', debounce(fetchData, 500));

  // Load awal
  fetchData();

  /* ===== Modal (Create/Update) Logic Tetap Sama ===== */
  let MODAL=null;
  function modalTpl(){return `
<div id="tm-modal" class="fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
  <div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl transform transition-all scale-100">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50 rounded-t-2xl">
      <h3 id="tm-title" class="font-bold text-xl text-gray-800">Form TM</h3>
      <button id="tm-x" class="text-gray-400 hover:text-gray-600 text-2xl transition">&times;</button>
    </div>
    <form id="tm-form" class="p-6 grid grid-cols-12 gap-4 max-h-[80vh] overflow-y-auto">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" value="store">
      <input type="hidden" name="id" value="">
      
      <div class="col-span-2">
        <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Tahun</label>
        <input name="tahun" type="number" min="2000" max="2100" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required value="<?= date('Y') ?>">
      </div>
      <div class="col-span-2">
        <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">AFD</label>
        <select name="unit_kode" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" required>
          <?php foreach($AFDS as $a){ echo '<option value="'.$a.'">'.$a.'</option>'; } ?>
        </select>
      </div>
      <div class="col-span-3">
        <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Rayon</label>
        <select name="rayon_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
          <option value="">— Pilih —</option>
          <?php foreach($conn->query("SELECT id,nama FROM md_rayon ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC) as $x){ echo '<option value="'.$x['id'].'">'.htmlspecialchars($x['nama']).'</option>'; } ?>
        </select>
      </div>
      <div class="col-span-5">
        <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Jenis Pekerjaan</label>
        <input id="jenis_text" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" list="jenis_datalist" placeholder="Ketik untuk mencari..." autocomplete="off" required>
        <datalist id="jenis_datalist">
          <?php foreach($JENIS_ROWS as $jr){ echo '<option data-id="'.$jr['id'].'" value="'.htmlspecialchars($jr['nama']).'"></option>'; } ?>
        </datalist>
        <input type="hidden" name="jenis_id" id="jenis_id">
      </div>
      <div class="col-span-3">
        <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">HK (Tenaga)</label>
        <select name="hk_id" id="hk_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
          <option value="">— Pilih —</option>
          <?php foreach($TENAGA as $t){ echo '<option value="'.$t['id'].'">'.htmlspecialchars($t['kode']).'</option>'; } ?>
        </select>
        <input type="hidden" name="hk" id="hk_hidden" value="">
      </div>
      <div class="col-span-2">
        <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Satuan</label>
        <input name="satuan" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ha/Kg">
      </div>
      <div class="col-span-7">
        <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Anggaran 1 Tahun</label>
        <input name="anggaran_tahun" inputmode="decimal" class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono font-bold text-blue-800 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0.00">
      </div>
      <div class="col-span-12 border-t my-2"></div>
      <div class="col-span-12 text-sm font-bold text-gray-500 mb-1">Realisasi Bulanan</div>
      <?php foreach($monthKeys as $m): ?>
        <div class="col-span-2">
          <label class="text-xs font-bold text-gray-500 uppercase block text-center"><?= strtoupper($m) ?></label>
          <input name="<?= $m ?>" inputmode="decimal" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0">
        </div>
      <?php endforeach; ?>
      <div class="col-span-12 mt-2">
        <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Keterangan</label>
        <textarea name="ket" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Catatan tambahan..."></textarea>
      </div>
      <div class="col-span-12 flex justify-end gap-3 mt-4 pt-4 border-t border-gray-100">
        <button type="button" id="tm-cancel" class="btn btn-gray px-5">Batal</button>
        <button class="btn px-6 shadow-lg shadow-blue-500/30">Simpan Data</button>
      </div>
    </form>
  </div>
</div>`}

  function openForm(d={}) {
    if (MODAL) MODAL.remove();
    document.body.insertAdjacentHTML('beforeend', modalTpl());
    MODAL=document.getElementById('tm-modal');
    const F=document.getElementById('tm-form'); const T=document.getElementById('tm-title');

    F.action.value = d.id ? 'update' : 'store';
    if (!d.id && (!d.unit_kode)) d.unit_kode = inpAfd.value || 'AFD01'; // Ambil dari filter aktif jika baru

    ['id','tahun','satuan','anggaran_tahun','ket',
      'jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'
    ].forEach(k=>{ if (F[k]!==undefined) F[k].value = d[k] ?? ''; });

    if (d.unit_kode && F['unit_kode']) F['unit_kode'].value = d.unit_kode;

    if (d.rayon_id) {
        F['rayon_id'].value = d.rayon_id;
    } else if (d.rayon_nama && F['rayon_id']){
      [...F['rayon_id'].options].forEach(o=>{ if (o.text.trim()===String(d.rayon_nama||'').trim()) F['rayon_id'].value=o.value; });
    }

    const jenisText = document.getElementById('jenis_text');
    const jenisId   = document.getElementById('jenis_id');
    if (d.jenis_id) {
      jenisId.value = d.jenis_id;
      const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o=>o.dataset.id==String(d.jenis_id));
      if (opt) jenisText.value = opt.value;
    } else if (d.jenis_nama) {
      jenisText.value = d.jenis_nama;
      const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o=>o.value.trim()===String(d.jenis_nama).trim());
      if (opt) jenisId.value = opt.dataset.id || '';
    }
    const syncJenis = ()=>{
      const val = jenisText.value.trim();
      const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o=>o.value.trim()===val);
      jenisId.value = opt ? (opt.dataset.id||'') : '';
    };
    jenisText.addEventListener('change', syncJenis);
    jenisText.addEventListener('input',  syncJenis);

    const selHK = document.getElementById('hk_id');
    const hidHK = document.getElementById('hk_hidden');
    if (d.hk_id && selHK){ selHK.value = String(d.hk_id); hidHK.value = selHK.options[selHK.selectedIndex]?.text || ''; }
    else if (d.hk && selHK){
      [...selHK.options].forEach(o=>{ if (o.text.trim()===String(d.hk||'').trim()) selHK.value=o.value; });
      hidHK.value = d.hk || '';
    }
    selHK?.addEventListener('change', e=>{
      hidHK.value = e.target.options[e.target.selectedIndex]?.text || '';
    });

    T.textContent = (d.id?'Edit':'Tambah')+' Data TM';

    const close=()=>MODAL.remove();
    document.getElementById('tm-x').onclick=close;
    document.getElementById('tm-cancel').onclick=close;

    F.onsubmit = async (e)=>{
      e.preventDefault();
      const jenisId = F.querySelector('#jenis_id');
      const jenisText = F.querySelector('#jenis_text');
      if (!jenisId.value) {
        await Swal.fire('Validasi', 'Silakan pilih Jenis Pekerjaan dari daftar yang muncul.', 'warning');
        jenisText.focus(); return;
      }
      const fd=new FormData(F);
      const r = await fetch('pemeliharaan_tu_crud.php',{method:'POST',body:fd});
      const j = await r.json();
      if (j.success){
        await Swal.fire('Berhasil', j.message||'Tersimpan','success');
        close(); fetchData(); // Panggil fetchData() agar table refresh tanpa reload
      } else {
        Swal.fire('Gagal', (j.errors||[]).map(x=>'• '+x).join('<br>') || j.message || 'Error', 'error');
      }
    }
  }

  <?php if(!$isStaf): ?>
  document.addEventListener('keydown', (e)=>{ 
    if (e.key==='n' && (e.ctrlKey||e.metaKey)){ 
      e.preventDefault(); 
      openForm({ unit_kode: inpAfd.value || 'AFD01', tahun: inpTahun.value }); 
    }
  });
  <?php endif; ?>
});
</script>