<?php
// pages/alat_panen.php
// MODIFIKASI: Filter Jenis Alat Panen (AJAX)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); 
$conn = $db->getConnection();

// Ambil Data Master untuk Filter & Dropdown
$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$kebun = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil Master Jenis Alat Panen (Pastikan kolom 'nama' sesuai database Anda)
$jenisAlatMaster = $conn->query("SELECT id, nama FROM md_jenis_alat_panen ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- LOGIKA BULAN & TAHUN OTOMATIS ---
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');
$bulanIndex = (int)date('n') - 1; 
$bulanSekarang = $bulanList[$bulanIndex];

$currentPage = 'alat_panen';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  .sticky-container {
    max-height: 72vh; overflow: auto; border: 1px solid #cbd5e1;
    border-radius: 0.75rem; background: #fff; position: relative;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  }
  table.rekap { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1400px; }
  table.rekap th, table.rekap td {
    padding: 0.65rem 0.75rem; font-size: 0.85rem; white-space: nowrap; vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; 
  }
  table.rekap th:last-child, table.rekap td:last-child { border-right: none; }
  table.rekap thead th {
    position: sticky; top: 0; background: #059fd3; color: #fff; z-index: 10;
    font-weight: 700; text-transform: uppercase; font-size: 0.75rem; height: 45px;
  }
  table.rekap tbody tr:hover td { background-color: #f8fafc; }
  .btn-icon {
    display: inline-flex; justify-content: center; align-items: center; width: 32px; height: 32px;
    border-radius: .375rem; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; transition: 0.2s;
  }
  .btn-icon:hover { background: #f1f5f9; border-color: #cbd5e1; }
</style>

<div class="space-y-6 ">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Alat Pertanian</h1>
            <p class="text-slate-500 text-sm mt-1 font-medium">Kelola stok alat panen per kebun, unit & periode</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            
            <button id="btn-refresh" class="flex items-center justify-center w-10 h-10 bg-white border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-[#059fd3] transition-all shadow-sm" title="Refresh Data">
                <i class="ti ti-refresh text-lg"></i>
            </button>

            <button id="btn-export-excel" class="flex items-center gap-2 bg-[#008b9c] hover:bg-[#007a8a] text-white px-4 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:shadow-md transition-all duration-200 border-none cursor-pointer">
                <i class="ti ti-file-spreadsheet text-lg"></i> 
                <span>Excel</span>
            </button>

            <button id="btn-export-pdf" class="flex items-center gap-2 bg-[#d32f2f] hover:bg-[#b71c1c] text-white px-4 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:shadow-md transition-all duration-200 border-none cursor-pointer">
                <i class="ti ti-file-type-pdf text-lg"></i> 
                <span>PDF</span>
            </button>

            <?php if (!$isStaf): ?>
            <button id="btn-add" class="flex items-center gap-2 bg-[#059fd3] hover:bg-[#0487b4] text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:shadow-md transition-all duration-200 ml-2 border-none cursor-pointer">
                <i class="ti ti-plus text-lg"></i> 
                <span>Input Alat</span>
            </button>
            <?php endif; ?>

        </div>
    </div>

    <div class="bg-white p-5 rounded-xl shadow-[0_2px_10px_-3px_rgba(6,81,237,0.1)] border border-slate-200/60 relative overflow-hidden">
        
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#059fd3]"></div>

        <div class="flex items-center gap-2 mb-5 pb-2 border-b border-slate-100">
            <div class="p-1.5 bg-cyan-50 rounded text-[#059fd3]">
                <i class="ti ti-filter"></i> 
            </div>
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Filter Data</span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
            
            <div class="group">
                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5 group-focus-within:text-[#059fd3] transition-colors">Kebun</label>
                <div class="relative">
                    <select id="f-kebun" class="w-full appearance-none bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-[#059fd3] focus:border-[#059fd3] block p-2.5 outline-none transition-all font-medium cursor-pointer">
                        <option value="">Semua Kebun</option>
                        <?php foreach ($kebun as $k): ?>
                            <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                        <i class="ti ti-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            <div class="group">
                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5 group-focus-within:text-[#059fd3] transition-colors">Unit</label>
                <div class="relative">
                    <select id="f-unit" class="w-full appearance-none bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-[#059fd3] focus:border-[#059fd3] block p-2.5 outline-none transition-all font-medium cursor-pointer">
                        <option value="">Semua Unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                        <i class="ti ti-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            <div class="group">
                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5 group-focus-within:text-[#059fd3] transition-colors">Jenis Alat</label>
                <div class="relative">
                    <select id="f-jenis-alat" class="w-full appearance-none bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-[#059fd3] focus:border-[#059fd3] block p-2.5 outline-none transition-all font-medium cursor-pointer">
                        <option value="">Semua Jenis</option>
                        <?php foreach ($jenisAlatMaster as $jam): ?>
                            <option value="<?= (int)$jam['id'] ?>"><?= htmlspecialchars($jam['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                        <i class="ti ti-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            <div class="group">
                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5 group-focus-within:text-[#059fd3] transition-colors">Bulan</label>
                <div class="relative">
                    <select id="f-bulan" class="w-full appearance-none bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-[#059fd3] focus:border-[#059fd3] block p-2.5 outline-none transition-all font-medium cursor-pointer">
                        <option value="">Semua Bulan</option>
                        <?php foreach ($bulanList as $b): ?>
                            <option value="<?= $b ?>" <?= ($b === $bulanSekarang) ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                        <i class="ti ti-calendar text-xs"></i>
                    </div>
                </div>
            </div>

            <div class="group">
                <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5 group-focus-within:text-[#059fd3] transition-colors">Tahun</label>
                <div class="relative">
                    <select id="f-tahun" class="w-full appearance-none bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg focus:ring-2 focus:ring-[#059fd3] focus:border-[#059fd3] block p-2.5 outline-none transition-all font-medium cursor-pointer">
                        <?php for ($y=$tahunNow-2; $y<=$tahunNow+2; $y++): ?>
                            <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                        <i class="ti ti-calendar-event text-xs"></i>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 px-1">
    <div class="text-sm text-gray-600 font-medium" id="page-info">Menampilkan 0–0 dari 0 data</div>
    <div class="flex items-center gap-2">
      <select id="per-page" class="border rounded-lg px-2 py-1 text-sm">
        <option value="10">10</option>
        <option value="25" selected>25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
      <div class="inline-flex gap-1 ml-2">
        <button id="btn-prev" class="px-3 py-1 rounded border text-gray-600 hover:bg-gray-50 text-sm" disabled>Prev</button>
        <button id="btn-next" class="px-3 py-1 rounded border text-gray-600 hover:bg-gray-50 text-sm" disabled>Next</button>
      </div>
    </div>
  </div>

  <div class="sticky-container">
      <table class="rekap">
        <thead>
          <tr>
            <th class="text-left">Kebun</th>
            <th class="text-left">Unit/Devisi</th>
            <th class="text-left">Jenis Alat Panen</th>
            <th class="text-right">Stok Awal</th>
            <th class="text-right">Mutasi Masuk</th>
            <th class="text-right">Mutasi Keluar</th>
            <th class="text-right">Dipakai</th>
            <th class="text-right">Stok Akhir</th>
            <th class="text-left">Krani Afdeling</th>
            <th class="text-left">Catatan</th>
            <?php if (!$isStaf): ?>
              <th class="text-center" style="width: 100px;">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="tbody-data">
          <tr><td colspan="<?= $isStaf ? 10 : 11 ?>" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat Data...</td></tr>
        </tbody>
      </table>
  </div>
</div>

<?php if (!$isStaf): ?>
<div id="crud-modal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
  <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-4xl transform scale-100 transition-transform">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-800">Input Alat Pertanian</h3>
      <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Bulan</label>
          <select id="bulan" name="bulan" class="w-full border rounded px-3 py-2 text-sm" required>
            <?php foreach ($bulanList as $b): ?>
                <option value="<?= $b ?>" <?= ($b === $bulanSekarang) ? 'selected' : '' ?>><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tahun</label>
          <select id="tahun" name="tahun" class="w-full border rounded px-3 py-2 text-sm" required>
            <?php for ($y=$tahunNow-1;$y<=$tahunNow+3;$y++): ?>
              <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Kebun</label>
          <select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2 text-sm" required>
            <option value="">-- Pilih Kebun --</option>
            <?php foreach ($kebun as $k): ?>
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Unit/Devisi</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2 text-sm" required>
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Jenis Alat Pertanian</label>
          <select id="id_jenis_alat" name="id_jenis_alat" class="w-full border rounded px-3 py-2 text-sm" required>
            <option value=""> Pilih Jenis Alat Pertanian </option>
            <?php foreach ($jenisAlatMaster as $jam): ?>
              <option value="<?= (int)$jam['id'] ?>"><?= htmlspecialchars($jam['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="text-xs text-gray-400 mt-1">*Pilih dari daftar master alat panen Pertanian</p>
        </div>
        
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Stok Awal</label>
          <input type="number" step="0.01" id="stok_awal" name="stok_awal" class="w-full border rounded px-3 py-2 text-sm" value="0">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mutasi Masuk</label>
          <input type="number" step="0.01" id="mutasi_masuk" name="mutasi_masuk" class="w-full border rounded px-3 py-2 text-sm" value="0">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mutasi Keluar</label>
          <input type="number" step="0.01" id="mutasi_keluar" name="mutasi_keluar" class="w-full border rounded px-3 py-2 text-sm" value="0">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Dipakai</label>
          <input type="number" step="0.01" id="dipakai" name="dipakai" class="w-full border rounded px-3 py-2 text-sm" value="0">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Stok Akhir (Auto)</label>
          <input type="number" step="0.01" id="stok_akhir" name="stok_akhir" class="w-full border rounded px-3 py-2 bg-gray-100 font-bold text-blue-600 text-sm" readonly>
        </div>
        
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Krani Afdeling</label>
          <input type="text" id="krani_afdeling" name="krani_afdeling" class="w-full border rounded px-3 py-2 text-sm" placeholder="Nama krani">
        </div>
        <div class="md:col-span-3">
          <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Catatan</label>
          <input type="text" id="catatan" name="catatan" class="w-full border rounded px-3 py-2 text-sm" placeholder="Catatan tambahan">
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-8 pt-4 border-t">
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
document.addEventListener('DOMContentLoaded', ()=>{
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;
  const COLSPAN = IS_STAF ? 10 : 11;
  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data');
  const perSel = $('#per-page'), btnPrev = $('#btn-prev'), btnNext = $('#btn-next'), pageInfo = $('#page-info');
  let DATA_CACHE = [], CUR_PAGE = 1;

  const BULAN_INDEX = { "Januari":1,"Februari":2,"Maret":3,"April":4,"Mei":5,"Juni":6,"Juli":7,"Agustus":8,"September":9,"Oktober":10,"November":11,"Desember":12 };
  function toTime(v){ if(!v) return 0; const d = new Date(v); return isNaN(d.getTime()) ? 0 : d.getTime(); }
  
  function sortTerbaru(arr){
    return (arr||[]).slice().sort((a,b)=>{
      const ca = toTime(a.created_at || a.createdAt || null);
      const cb = toTime(b.created_at || b.createdAt || null);
      if(cb !== ca) return cb - ca;
      return parseInt(b.id||0,10) - parseInt(a.id||0,10);
    });
  }

  function nf(n){ return Number(n||0).toLocaleString('id-ID'); }

  function renderPage(){
    const total=DATA_CACHE.length, per=parseInt(perSel.value||'25',10), totalPages=Math.max(1, Math.ceil(total/per));
    CUR_PAGE=Math.min(Math.max(1, CUR_PAGE), totalPages);
    const start=(CUR_PAGE-1)*per, end=Math.min(start+per, total), rows=DATA_CACHE.slice(start, end);

    if(rows.length===0){
      tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-400 italic">Belum ada data.</td></tr>`;
    } else {
      tbody.innerHTML = rows.map(x=>{
        const payload=encodeURIComponent(JSON.stringify(x));
        const namaAlat = x.display_jenis_alat || x.jenis_alat || '-';

        const actionCell = IS_STAF ? '' : `
          <td class="text-center">
            <div class="flex items-center justify-center gap-1">
              <button class="btn-icon text-cyan-600 hover:text-cyan-800" data-json="${payload}" title="Edit">
                <i class="ti ti-pencil"></i>
              </button>
              <button class="btn-icon text-red-600 hover:text-red-800" data-id="${x.id}" title="Hapus">
                <i class="ti ti-trash"></i>
              </button>
            </div>
          </td>
        `;
        return `
          <tr>
            <td class="text-left font-medium text-gray-800">${x.nama_kebun || '-'}</td>
            <td class="text-left text-gray-600">${x.nama_unit || '-'}</td>
            <td class="text-left text-gray-800 font-semibold">${namaAlat}</td>
            <td class="text-right text-gray-600">${nf(x.stok_awal)}</td>
            <td class="text-right text-gray-600">${nf(x.mutasi_masuk)}</td>
            <td class="text-right text-gray-600">${nf(x.mutasi_keluar)}</td>
            <td class="text-right text-gray-600">${nf(x.dipakai)}</td>
            <td class="text-right font-bold text-gray-900">${nf(x.stok_akhir)}</td>
            <td class="text-left text-gray-600 italic">${x.krani_afdeling||'-'}</td>
            <td class="text-left text-gray-500 italic text-xs">${x.catatan||'-'}</td>
            ${actionCell}
          </tr>
        `;
      }).join('');
    }
    const from=total?start+1:0;
    pageInfo.textContent=`Menampilkan ${from}–${end} dari ${total} data`;
    btnPrev.disabled=CUR_PAGE<=1; btnNext.disabled=CUR_PAGE>=totalPages;
  }

  function refresh(){
    const fd=new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    
    // Filter Params
    fd.append('kebun_id',$('#f-kebun').value);
    fd.append('unit_id',$('#f-unit').value);
    fd.append('bulan',$('#f-bulan').value);
    fd.append('tahun',$('#f-tahun').value);
    // MODIFIKASI: Kirim ID jenis alat ke backend
    fd.append('id_jenis_alat', $('#f-jenis-alat').value);
    
    fd.append('order_by','created_at'); fd.append('order_dir','desc');

    tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin"></i> Memuat...</td></tr>`;

    fetch('alat_panen_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(!j.success){
          DATA_CACHE=[];
          tbody.innerHTML=`<tr><td colspan="${COLSPAN}" class="text-center py-10 text-red-500">${j.message||'Error'}</td></tr>`;
        } else {
          DATA_CACHE = sortTerbaru(j.data || []);
        }
        CUR_PAGE=1; renderPage();
      })
      .catch(()=>{
        DATA_CACHE=[];
        tbody.innerHTML=`<tr><td colspan="${COLSPAN}" class="text-center py-10 text-red-500">Gagal memuat data.</td></tr>`;
        renderPage();
      });
  }

  refresh();
  
  // Listeners (Menambahkan listener untuk #f-jenis-alat)
  $('#btn-refresh').addEventListener('click', refresh);
  ['f-kebun','f-unit','f-bulan','f-tahun','f-jenis-alat'].forEach(id=>{
      const el = document.getElementById(id);
      if(el) el.addEventListener('change', refresh);
  });
  
  perSel.addEventListener('change', ()=>{ CUR_PAGE=1; renderPage(); });
  btnPrev.addEventListener('click', ()=>{ if(CUR_PAGE > 1) { CUR_PAGE--; renderPage(); } });
  btnNext.addEventListener('click', ()=>{ CUR_PAGE++; renderPage(); });

  // CRUD MODAL LOGIC
  if (!IS_STAF) {
    const modal=$('#crud-modal'), form=$('#crud-form'), btnAdd=$('#btn-add');
    const openModal=()=>{modal.classList.remove('hidden');modal.classList.add('flex')};
    const closeModal=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

    const calc=()=>{
      const sa=+($('#stok_awal').value||0), mi=+($('#mutasi_masuk').value||0), mk=+($('#mutasi_keluar').value||0), dp=+($('#dipakai').value||0);
      $('#stok_akhir').value = (sa+mi-mk-dp).toFixed(2);
    };
    ['stok_awal','mutasi_masuk','mutasi_keluar','dipakai'].forEach(id=>document.getElementById(id).addEventListener('input', calc));
    
    btnAdd.addEventListener('click',()=>{
      form.reset();
      $('#form-action').value='store'; $('#form-id').value='';
      const b = $('#f-bulan').value, t = $('#f-tahun').value;
      if(b) $('#bulan').value = b;
      if(t) $('#tahun').value = t;
      calc(); openModal();
    });

    $('#btn-close').addEventListener('click',closeModal);
    $('#btn-cancel').addEventListener('click',closeModal);

    document.body.addEventListener('click',e=>{
      const btn = e.target.closest('button');
      if (!btn) return;
      
      if(btn.classList.contains('btn-icon') && btn.dataset.json){
        const d=JSON.parse(decodeURIComponent(btn.dataset.json));
        form.reset();
        $('#form-action').value='update'; $('#form-id').value=d.id;
        
        ['bulan','tahun','kebun_id','unit_id','stok_awal','mutasi_masuk','mutasi_keluar','dipakai','stok_akhir','krani_afdeling','catatan'].forEach(k=>{
          if($('#'+k)) $('#'+k).value = d[k] ?? '';
        });

        if(d.id_jenis_alat) {
            if($('#id_jenis_alat')) $('#id_jenis_alat').value = d.id_jenis_alat;
        } else {
            if($('#id_jenis_alat')) $('#id_jenis_alat').value = ""; 
        }

        calc(); openModal();
      }
      
      if(btn.classList.contains('btn-icon') && btn.dataset.id && !btn.dataset.json){
        Swal.fire({title:'Hapus data?', text:'Tidak dapat dikembalikan', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Hapus'})
        .then(res=>{
          if(res.isConfirmed){
            const fd=new FormData();
            fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
            fd.append('action','delete'); fd.append('id', btn.dataset.id);
            fetch('alat_panen_crud.php',{method:'POST',body:fd})
              .then(r=>r.json()).then(j=>{
                if(j.success){ Swal.fire('Terhapus','','success'); refresh(); } 
                else { Swal.fire('Gagal', j.message||'Error', 'error'); }
              });
          }
        });
      }
    });

    form.addEventListener('submit',e=>{
      e.preventDefault();
      if(!$('#id_jenis_alat').value) {
          Swal.fire('Validasi', 'Mohon pilih Jenis Alat Panen', 'warning');
          return;
      }
      calc();
      const fd=new FormData(e.target);
      fetch('alat_panen_crud.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(j=>{
          if(j.success){ closeModal(); Swal.fire('Berhasil', j.message, 'success'); refresh(); } 
          else { Swal.fire('Gagal', j.message, 'error'); }
        })
        .catch(()=> Swal.fire('Gagal','Koneksi Error','error'));
    });
  }

  // Exports (Updated with id_jenis_alat)
  const getQs = () => new URLSearchParams({
      csrf_token:'<?= htmlspecialchars($CSRF) ?>',
      kebun_id:$('#f-kebun').value, 
      unit_id:$('#f-unit').value,
      bulan:$('#f-bulan').value, 
      tahun:$('#f-tahun').value,
      id_jenis_alat:$('#f-jenis-alat').value // Parameter baru
  }).toString();

  $('#btn-export-excel').addEventListener('click', () => window.open('cetak/alat_panen_export_excel.php?'+getQs(), '_blank'));
  $('#btn-export-pdf').addEventListener('click', () => window.open('cetak/alat_panen_export_pdf.php?'+getQs(), '_blank'));
});
</script>