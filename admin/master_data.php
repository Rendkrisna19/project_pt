<?php
// pages/master_data.php
// MODIFIKASI FULL: Fix No Polisi JS Config & Rendering

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Ambil data master untuk dropdown
$units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$satuan = $conn->query("SELECT id, nama FROM md_satuan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$pupuk  = $conn->query("SELECT id, nama FROM md_pupuk ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'master_data';
include_once '../layouts/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    
    /* Custom Scrollbar for Tabs */
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

    /* Tab Styling */
    .tab-btn {
        padding: 0.5rem 1rem;           
        font-size: 0.875rem;            
        font-weight: 500;               
        border-radius: 9999px;          
        border: 1px solid transparent;  
        transition: all 0.2s ease;      
        white-space: nowrap;            
        cursor: pointer;
    }

    .tab-btn.active {
        background-color: #0891b2;      /* cyan-600 */
        color: #ffffff;                 
        box-shadow: 0 4px 6px -1px rgba(8, 145, 178, 0.2);
        border-color: #0891b2;          
        transform: scale(1.02);         
    }

    .tab-btn:not(.active) {
        background-color: #ffffff;      
        color: #475569;                 
        border-color: #e2e8f0;          
    }

    .tab-btn:not(.active):hover {
        border-color: #cbd5e1;          
        background-color: #f1f5f9;      
    }

    /* Table Styling */
    .table-head-th {
        padding: 0.75rem 1rem;          
        text-align: left;               
        font-size: 0.75rem;             
        font-weight: 700;               
        color: #64748b;                 
        text-transform: uppercase;      
        letter-spacing: 0.05em;         
        background-color: #f8fafc;      
        border-bottom: 1px solid #e2e8f0; 
    }

    .table-body-td {
        padding: 0.75rem 1rem;          
        font-size: 0.875rem;            
        color: #334155;                 
        border-bottom: 1px solid #f1f5f9; 
    }
    
    .fade-in { animation: fadeIn 0.3s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="max-w-12xl mx-auto space-y-6 pb-10">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Master Data</h1>
            <p class="text-slate-500 text-sm mt-1">Kelola seluruh data referensi sistem perkebunan dalam satu tempat.</p>
        </div>
        <button id="btn-add" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium rounded-xl transition-all shadow-lg shadow-cyan-600/20 active:scale-95 focus:ring-4 focus:ring-cyan-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Tambah Data
        </button>
    </div>

    <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-100">
        <div id="tabs" class="flex gap-2 overflow-x-auto scrollbar-hide p-2 snap-x">
            <button data-entity="kebun" class="tab-btn active snap-start">Nama Kebun</button>
            <button data-entity="unit" class="tab-btn snap-start">Unit/Devisi</button>
            <button data-entity="rayon" class="tab-btn snap-start">Rayon</button>
            <button data-entity ="blok" class="tab-btn snap-start">Blok</button>
            <button data-entity="tahun_tanam" class="tab-btn snap-start">Tahun Tanam</button>
            
            <button data-entity="bahan_kimia" class="tab-btn snap-start border-l-2 border-slate-100 pl-4">Bahan Kimia</button>
            <button data-entity="pupuk" class="tab-btn snap-start">Pupuk</button>
            <button data-entity="jenis_kendaraan" class="tab-btn snap-start">Jenis Kendaraan</button> 
            <button data-entity="jenis_bahan_bakar_pelumas" class="tab-btn snap-start">BBM & Pelumas</button> 
            <button data-entity="barang_gudang" class="tab-btn snap-start">Barang Gudang</button>
            <button data-entity="alat_panen" class="tab-btn snap-start">Alat Panen</button>
            <button data-entity="apl" class="tab-btn snap-start">APL</button>
<button data-entity="jenis_pekerjaan_kertas_kerja" class="tab-btn snap-start border-l-2 border-slate-100 pl-4">Jenis Pek. Kertas Kerja</button>
            <button data-entity="bibit_tm" class="tab-btn snap-start border-l-2 border-slate-100 pl-4">Bibit MN</button>
            <button data-entity="bibit_pn" class="tab-btn snap-start">Bibit PN</button>

            <button data-entity="jenis_pekerjaan" class="tab-btn snap-start border-l-2 border-slate-100 pl-4">Jenis Pekerjaan</button>
            
            <button data-entity="pem_tm" class="tab-btn snap-start border-l-2 border-slate-100 pl-4">Pem. TM</button>
            <button data-entity="pem_tu" class="tab-btn snap-start">Pem. TU</button>
            <button data-entity="pem_tk" class="tab-btn snap-start">Pem. TK</button>
            <button data-entity="pem_tbm1" class="tab-btn snap-start">Pem. TBM I</button>
            <button data-entity="pem_tbm2" class="tab-btn snap-start">Pem. TBM II</button>
            <button data-entity="pem_tbm3" class="tab-btn snap-start">Pem. TBM III</button>
            <button data-entity="pem_pn" class="tab-btn snap-start">Pem. PN</button>
            <button data-entity="pem_mn" class="tab-btn snap-start">Pem. MN</button>

            <button data-entity="satuan" class="tab-btn snap-start border-l-2 border-slate-100 pl-4">Satuan</button>
            <button data-entity="fisik" class="tab-btn snap-start">Fisik</button>
            <button data-entity="tenaga" class="tab-btn snap-start">Tenaga</button>
            <button data-entity="mobil" class="tab-btn snap-start">Mobil</button>
            <button data-entity="no_polisi" class="tab-btn snap-start">No Polisi</button>
            <button data-entity="jabatan" class="tab-btn snap-start">Jabatan</button>
            <button data-entity="asal_gudang" class="tab-btn snap-start">Asal Gudang</button>
            <button data-entity="keterangan" class="tab-btn snap-start">Keterangan Master</button>
        </div>
    </div>

    <div id="blok-filter-bar" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 hidden fade-in">
        <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">Filter Data Blok</h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Unit</label>
                <select id="blok-filter-unit" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-cyan-500 focus:border-cyan-500 block p-2.5">
                    <option value="">— Semua Unit —</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Kode Blok (Cari)</label>
                <input id="blok-filter-kode" type="text" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-cyan-500 focus:border-cyan-500 block p-2.5" placeholder="Contoh: A12">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tahun Tanam</label>
                <input id="blok-filter-tahun" type="number" min="1900" max="2100" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-cyan-500 focus:border-cyan-500 block p-2.5" placeholder="YYYY">
            </div>
            <div class="md:col-span-5 flex justify-end gap-2 mt-2">
                <button id="blok-filter-reset" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-slate-800 transition-colors">Reset</button>
                <button id="blok-filter-apply" class="px-4 py-2 text-sm font-medium text-white bg-slate-800 rounded-lg hover:bg-slate-900 transition-colors shadow-lg shadow-slate-800/20">Terapkan Filter</button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <div class="max-h-[65vh] overflow-y-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 sticky top-0 z-10 shadow-sm">
                        <tr id="thead-row"></tr>
                    </thead>
                    <tbody id="tbody-data" class="divide-y divide-slate-100">
                        <tr><td class="p-10 text-center text-slate-400">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col md:flex-row items-center justify-between gap-4 p-4 border-t border-slate-100 bg-slate-50/50">
            <div class="text-sm text-slate-500 font-medium" id="page-info">Memuat...</div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase">Baris:</span>
                    <select id="per-page" class="bg-white border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-cyan-500 focus:border-cyan-500 block p-1.5">
                        <option>10</option>
                        <option selected>15</option>
                        <option>25</option>
                        <option>50</option>
                        <option>100</option>
                    </select>
                </div>
                <div id="pager" class="flex items-center gap-1"></div>
            </div>
        </div>
    </div>
</div>

<div id="crud-modal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-100">
                <div class="bg-white px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="text-lg font-bold leading-6 text-slate-800" id="modal-title">Tambah Data</h3>
                    <button id="btn-close" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form id="crud-form" novalidate class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
                    <input type="hidden" name="action" id="form-action">
                    <input type="hidden" name="entity" id="form-entity">
                    <input type="hidden" name="id" id="form-id">
                    <div id="form-fields" class="grid grid-cols-1 md:grid-cols-2 gap-5"></div>
                    <div class="mt-8 flex items-center justify-end gap-3">
                        <button type="button" id="btn-cancel" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition-all">Batal</button>
                        <button type="submit" class="rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-all">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data'), thead = $('#thead-row');

  let currentEntity = 'kebun';
  let page = 1, perPage = 15, total = 0, totalPages = 1;
  let clientCache = { entity: null, rows: [] };
  const blokFilter = { unit_id: '', kode: '', tahun: '' };

  const OPTIONS_UNITS  = [<?php foreach ($units as $u){ echo "{value:{$u['id']},label:'".htmlspecialchars($u['nama_unit'],ENT_QUOTES)."'},"; } ?>];
  const OPTIONS_SATUAN = [<?php foreach ($satuan as $s){ echo "{value:{$s['id']},label:'".htmlspecialchars($s['nama'],ENT_QUOTES)."'},"; } ?>];
  const OPTIONS_PUPUK  = [<?php foreach ($pupuk as $p){ echo "{value:{$p['id']},label:'".htmlspecialchars($p['nama'],ENT_QUOTES)."'},"; } ?>];

  const ENTITY_PEM = (title) => ({
    title,
    table: ['Nama', 'Deskripsi', 'Aksi'],
    fields: [
      { name:'nama',      label:'Nama',      type:'text',     required:true },
      { name:'deskripsi', label:'Deskripsi', type:'textarea' }
    ]
  });

  // ================== CONFIGURATION ==================
  const ENTITIES = {
    kebun: { title:'Nama Kebun', table:['Kode','Nama Kebun','Keterangan','Aksi'], fields:[
      {name:'kode',       label:'Kode Kebun', type:'text', required:true},
      {name:'nama_kebun', label:'Nama Kebun', type:'text', required:true},
      {name:'keterangan', label:'Keterangan', type:'text'}
    ]},
    unit: { title:'Unit/Devisi', table:['Nama Unit','Keterangan','Aksi'], fields:[
      {name:'nama_unit',  label:'Nama Unit', type:'text', required:true},
      {name:'keterangan', label:'Keterangan', type:'text'}
    ]},
    rayon:       { title:'Rayon',        table:['Nama Rayon','Aksi'],        fields:[{name:'nama', label:'Nama Rayon', type:'text', required:true}]},
    blok: { title:'Blok', table:['Unit','Kode Blok','Tahun Tanam','Luas (Ha)','Aksi'], fields:[
      {name:'unit_id', label:'Unit', type:'select', options:OPTIONS_UNITS, required:true},
      {name:'kode',    label:'Kode Blok', type:'text', required:true},
      {name:'tahun_tanam', label:'Tahun Tanam', type:'number', min:1900, max:2100},
      {name:'luas_ha', label:'Luas (Ha)', type:'number', step:'0.01', min:0}
    ]},
    tahun_tanam: { title:'Tahun Tanam', table:['Tahun','Keterangan','Aksi'], fields:[
      {name:'tahun',      label:'Tahun', type:'number', required:true, min:1900, max:2100},
      {name:'keterangan', label:'Keterangan', type:'text'}
    ]},

    jenis_pekerjaan_kertas_kerja: {
        title: 'Jenis Pekerjaan Kertas Kerja',
        table: ['Urutan', 'Nama Pekerjaan', 'Satuan', 'Kategori', 'Status', 'Aksi'],
        fields: [
            { name: 'nama', label: 'Nama Pekerjaan', type: 'text', required: true },
            { name: 'satuan', label: 'Satuan', type: 'text' },
            { name: 'kategori', label: 'Kategori', type: 'select', required: true,
              options: [
                  {value: 'FISIK', label: 'FISIK (Umum)'},
                  {value: 'LUAS', label: 'LUAS (Ha)'},
                  {value: 'TENAGA', label: 'TENAGA (Hk)'},
                  {value: 'KIMIA', label: 'BAHAN KIMIA'},
                  {value: 'CAMPURAN', label: 'CAMPURAN LAIN'}
              ] 
            },
            { name: 'urutan', label: 'Urutan Tampil', type: 'number', min: 0 },
            { name: 'is_active', label: 'Aktif?', type: 'checkbox' }
        ]
    },

    // --- MATERIAL & BARANG ---
    bahan_kimia: { title:'Bahan Kimia', table:['Kode','Nama Bahan','Satuan','Keterangan','Aksi'], fields:[
      {name:'kode',       label:'Kode Bahan', type:'text', required:true},
      {name:'nama_bahan', label:'Nama Bahan', type:'text', required:true},
      {name:'satuan_id',  label:'Satuan',     type:'select', options:OPTIONS_SATUAN},
      {name:'keterangan', label:'Keterangan', type:'text'}
    ]},
    pupuk:   { title:'Pupuk',   table:['Nama','Satuan','Aksi'], fields:[
      {name:'nama',      label:'Nama Pupuk', type:'text', required:true},
      {name:'satuan_id', label:'Satuan',     type:'select', options:OPTIONS_SATUAN}
    ]},
    
    jenis_kendaraan: { title:'Jenis Kendaraan', table:['Nama Jenis','Keterangan','Aksi'], fields:[
      {name:'nama',       label:'Nama Jenis Kendaraan', type:'text', required:true},
      {name:'keterangan', label:'Keterangan', type:'text'}
    ]},
    jenis_bahan_bakar_pelumas: { title:'Jenis BBM & Pelumas', table:['Nama','Satuan','Keterangan','Aksi'], fields:[
      {name:'nama',       label:'Nama BBM/Pelumas', type:'text', required:true},
      {name:'satuan',     label:'Satuan (Ltr/Pcs)', type:'text', required:true}, 
      {name:'keterangan', label:'Keterangan', type:'text'}
    ]},
    barang_gudang: { title:'Barang Gudang', table:['Nama','Satuan','Keterangan','Aksi'], fields:[
      {name:'nama',       label:'Nama Barang', type:'text', required:true},
      {name:'satuan',     label:'Satuan',      type:'text'}, 
      {name:'keterangan', label:'Keterangan',  type:'text'}
    ]},

    alat_panen:{ title:'Jenis Alat Panen', table:['Nama','Keterangan','Aksi'], fields:[
      {name:'nama', label:'Nama', type:'text', required:true},
      {name:'keterangan', label:'Keterangan', type:'text'}
    ]},
    apl:         { title:'APL',          table:['Nama APL','Aksi'],          fields:[{name:'nama', label:'Nama APL', type:'text', required:true}]},

    // --- BIBIT ---
    bibit_tm: { title: 'Bibit TM', table: ['Kode', 'Nama Bibit TM', 'Aktif', 'Aksi'], fields: [
        { name:'kode',      label:'Kode (opsional)', type:'text' },
        { name:'nama',      label:'Nama Bibit TM',   type:'text', required:true },
        { name:'is_active', label:'Aktif?',          type:'checkbox' }
    ]},
    bibit_pn: { title: 'Bibit PN', table: ['Kode', 'Nama Bibit PN', 'Aktif', 'Aksi'], fields: [
        { name:'kode',      label:'Kode (opsional)', type:'text' },
        { name:'nama',      label:'Nama Bibit PN',   type:'text', required:true },
        { name:'is_active', label:'Aktif?',          type:'checkbox' }
    ]},

    // --- PEKERJAAN ---
    jenis_pekerjaan: { title:'Jenis Pekerjaan', table:['Nama','Keterangan','Aksi'], fields:[
      {name:'nama',       label:'Nama',       type:'text', required:true},
      {name:'keterangan', label:'Keterangan', type:'text'}
    ]},
    
    // --- PEMELIHARAAN ---
    pem_tm:  ENTITY_PEM('Pemeliharaan TM'),
    pem_tu:  ENTITY_PEM('Pemeliharaan TU'),
    pem_tk:  ENTITY_PEM('Pemeliharaan TK'),
    pem_tbm1:ENTITY_PEM('Pemeliharaan TBM I'),
    pem_tbm2:ENTITY_PEM('Pemeliharaan TBM II'),
    pem_tbm3:ENTITY_PEM('Pemeliharaan TBM III'),
    pem_pn:  ENTITY_PEM('Pemeliharaan PN'),
    pem_mn:  ENTITY_PEM('Pemeliharaan MN'),

    // --- LAINNYA ---
    satuan: { title:'Satuan', table:['Nama','Aksi'], fields:[{name:'nama', label:'Nama Satuan (Kg/Liter/..)',  type:'text', required:true}]},
    fisik:  { title:'Fisik',  table:['Nama','Aksi'], fields:[{name:'nama', label:'Nama Fisik (Ha/Pkk/Unit/..)', type:'text', required:true}]},
    tenaga: { title:'Tenaga', table:['Kode','Nama','Aksi'], fields:[
      {name:'kode', label:'Kode (TS/KNG/PKWT/TP)', type:'text', required:true},
      {name:'nama', label:'Nama', type:'text', required:true}
    ]},
    mobil:  { title:'Mobil',  table:['Kode','Nama','Aksi'], fields:[
      {name:'kode', label:'Kode (TS/TP)', type:'text', required:true},
      {name:'nama', label:'Nama', type:'text', required:true}
    ]},
    // [ADDED: NO POLISI CONFIG]
    no_polisi: { 
        title: 'No Polisi Kendaraan', 
        table: ['No Polisi', 'Keterangan', 'Aksi'], 
        fields: [
            {name: 'no_polisi', label: 'No Polisi', type: 'text', required: true},
            {name: 'keterangan', label: 'Keterangan', type: 'text'}
        ]
    },
    jabatan: { title:'Jabatan', table:['Nama','Aksi'], fields:[{name:'nama', label:'Nama Jabatan', type:'text', required:true}]},
    asal_gudang: { title:'Asal Gudang',  table:['Nama Gudang','Aksi'],       fields:[{name:'nama', label:'Nama Gudang', type:'text', required:true}]},
    keterangan:  { title:'Keterangan Master', table:['Keterangan','Aksi'],   fields:[{name:'keterangan', label:'Keterangan', type:'textarea', required:true}]},
  };

  const modal = $('#crud-modal');
  const open  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
  const close = () => { modal.classList.add('hidden');    modal.classList.remove('flex'); };

  function renderHead(entity) {
    thead.innerHTML = '';
    ENTITIES[entity].table.forEach((h, i) => {
      const th = document.createElement('th');
      th.className = 'table-head-th'; 
      th.textContent = h;
      if (i === ENTITIES[entity].table.length - 1) th.classList.add('w-28', 'text-center');
      thead.appendChild(th);
    });
    document.getElementById('blok-filter-bar').classList.toggle('hidden', entity !== 'blok');
  }

  function inputEl(f) {
    const wrap = document.createElement('div');
    if (f.type === 'textarea') wrap.className = 'md:col-span-2';
    
    const label = `<label class="block text-sm font-semibold text-slate-700 mb-2">${f.label}${f.required?' <span class="text-red-500">*</span>':''}</label>`;
    let control = '';
    const baseClass = "w-full rounded-lg border-slate-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 text-sm py-2.5 px-3";

    if (f.type === 'select') {
      const opts = (f.options || []).map(o => `<option value="${o.value}">${o.label}</option>`).join('');
      control = `<select id="${f.name}" name="${f.name}" class="${baseClass}" ${f.required?'required':''}>${opts}</select>`;
    } else if (f.type === 'textarea') {
      control = `<textarea id="${f.name}" name="${f.name}" rows="3" class="${baseClass}" ${f.required?'required':''}></textarea>`;
    } else if (f.type === 'checkbox') {
      control = `<label class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox" id="${f.name}" name="${f.name}" class="h-5 w-5 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
        <span class="text-sm text-slate-700 font-medium">Status Aktif</span>
      </label>`;
    } else {
      control = `<input type="${f.type||'text'}" ${f.step?`step="${f.step}"`:''} ${f.min?`min="${f.min}"`:''} ${f.max?`max="${f.max}"`:''} id="${f.name}" name="${f.name}" class="${baseClass}" ${f.required?'required':''}>`;
    }
    wrap.innerHTML = label + control;
    return wrap;
  }

  function renderForm(entity, data = {}) {
    document.getElementById('modal-title').textContent = (data.id ? 'Edit ' : 'Tambah ') + ENTITIES[entity].title;
    document.getElementById('form-entity').value = entity;
    document.getElementById('form-action').value  = data.id ? 'update' : 'store';
    document.getElementById('form-id').value      = data.id || '';
    const holder = document.getElementById('form-fields');
    holder.innerHTML = '';
    ENTITIES[entity].fields.forEach(f => {
      const el = inputEl(f); holder.appendChild(el);
      const val = (data && data[f.name] != null) ? data[f.name] : null;
      const inputElement = holder.querySelector(`#${f.name}`);
      if (!inputElement) return;
      if (f.type === 'checkbox') inputElement.checked = String(val ?? '1') === '1';
      else inputElement.value = (val ?? '');
    });
    open();
  }

  function cell(v){ return (v==null || v==='') ? '<span class="text-slate-400">-</span>' : v; }

  function actionButtons(entity, rowJson, id){
    const payload = encodeURIComponent(JSON.stringify(rowJson));
    return `
      <div class="flex items-center justify-center gap-2">
        <button class="btn-edit p-1.5 rounded-lg text-cyan-600 hover:bg-cyan-50 transition-colors" title="Edit" data-entity="${entity}" data-json="${payload}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
        </button>
        <button class="btn-del p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors" title="Hapus" data-entity="${entity}" data-id="${id}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        </button>
      </div>`;
  }

  function rowCols(entity, r){
    const td = (content) => `<td class="table-body-td">${content}</td>`;
    
    switch(entity){
      case 'kebun': return td(cell(r.kode)) + td(cell(r.nama_kebun)) + td(cell(r.keterangan));
      case 'bahan_kimia': return td(cell(r.kode)) + td(cell(r.nama_bahan)) + td(cell(r.nama_satuan||'')) + td(cell(r.keterangan));
      case 'jenis_pekerjaan': return td(cell(r.nama)) + td(cell(r.keterangan));
      case 'bibit_tm': case 'bibit_pn':
        const badge = String(r.is_active ?? '1') === '1' 
          ? '<span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>' 
          : '<span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Nonaktif</span>';
        return td(cell(r.kode)) + td(cell(r.nama)) + td(badge);
      
      // New Entities
      case 'jenis_kendaraan': return td(cell(r.nama)) + td(cell(r.keterangan));
      case 'jenis_bahan_bakar_pelumas': return td(cell(r.nama)) + td(cell(r.satuan)) + td(cell(r.keterangan));
      case 'barang_gudang': return td(cell(r.nama)) + td(cell(r.satuan)) + td(cell(r.keterangan));

      case 'unit': return td(cell(r.nama_unit)) + td(cell(r.keterangan));
      case 'rayon': return td(cell(r.nama));
      case 'blok': return td(cell(r.nama_unit)) + td(cell(r.kode)) + td(cell(r.tahun_tanam)) + td(cell(r.luas_ha));
      case 'tahun_tanam': return td(cell(r.tahun)) + td(cell(r.keterangan));
      case 'pupuk': return td(cell(r.nama)) + td(cell(r.nama_satuan||''));
      case 'alat_panen': return td(cell(r.nama)) + td(cell(r.keterangan));
      case 'apl': return td(cell(r.nama));

      case 'jenis_pekerjaan_kertas_kerja':
        const badgeJP = String(r.is_active ?? '1') === '1' 
          ? '<span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>' 
          : '<span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Nonaktif</span>';
        
        // Tampilan Kategori dengan warna berbeda
        let katColor = 'bg-gray-100 text-gray-800';
        if(r.kategori === 'FISIK') katColor = 'bg-blue-100 text-blue-800';
        if(r.kategori === 'LUAS') katColor = 'bg-green-100 text-green-800';
        if(r.kategori === 'TENAGA') katColor = 'bg-amber-100 text-amber-800';

        const katBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold ${katColor}">${cell(r.kategori)}</span>`;

        return td(cell(r.urutan)) + td(cell(r.nama)) + td(cell(r.satuan)) + td(katBadge) + td(badgeJP);
      
      // Lainnya
      case 'satuan': case 'fisik': case 'jabatan': case 'asal_gudang': return td(cell(r.nama));
      case 'tenaga': case 'mobil': return td(cell(r.kode)) + td(cell(r.nama));
      case 'keterangan': return td(cell(r.keterangan));
      
      // [ADDED: NO POLISI RENDER]
      case 'no_polisi': return td(cell(r.no_polisi)) + td(cell(r.keterangan));
      
      case 'pem_tm': case 'pem_tu': case 'pem_tk': case 'pem_tbm1': case 'pem_tbm2': 
      case 'pem_tbm3': case 'pem_pn': case 'pem_mn':
        return td(cell(r.nama)) + td(cell(r.deskripsi));
    }
    return '';
  }

  function renderRows(entity, rows){
    if (!rows.length){
      tbody.innerHTML = `<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-slate-400 italic">Belum ada data tersedia.</td></tr>`;
      return;
    }
    tbody.innerHTML = rows.map(r => `
      <tr class="hover:bg-cyan-50/50 transition-colors duration-150">
        ${rowCols(entity, r)}
        <td class="table-body-td text-center">${actionButtons(entity, r, r.id)}</td>
      </tr>`).join('');
  }

  // --- LOGIC FETCH & PAGINATION ---
  function updatePageInfo(){
    const start = total ? ((page - 1) * perPage) + 1 : 0;
    const end   = Math.min(page * perPage, total);
    $('#page-info').textContent = `Menampilkan ${start} - ${end} dari total ${total} data`;
  }

  function renderPager(){
    const pager = $('#pager'); pager.innerHTML = '';
    if (totalPages <= 1) return;
    const btnClass = "px-3 py-1 text-xs font-medium border rounded transition-colors";
    const activeClass = "bg-slate-800 text-white border-slate-800";
    const inactiveClass = "bg-white text-slate-600 border-slate-200 hover:bg-slate-50";

    const makeBtn = (label, targetPage, disabled=false, active=false) => {
      const a = document.createElement('button');
      a.innerHTML = label; 
      a.className = `${btnClass} ${active ? activeClass : inactiveClass}`;
      if (disabled) { a.disabled = true; a.classList.add('opacity-50','cursor-not-allowed'); }
      a.addEventListener('click', ()=>{ if(!disabled && !active){ page = targetPage; renderHeadAndLoad(currentEntity); }});
      pager.appendChild(a);
    };
    
    makeBtn('Prev', Math.max(1,page-1), page<=1);
    makeBtn(page, page, false, true);
    makeBtn('Next', Math.min(totalPages, page+1), page>=totalPages);
  }

  function applyBlokFilterClient(rows){
    let out = rows;
    if (blokFilter.unit_id) out = out.filter(r => String(r.unit_id||'') === String(blokFilter.unit_id));
    if (blokFilter.kode){
      const kw = blokFilter.kode.toLowerCase();
      out = out.filter(r => String(r.kode||'').toLowerCase().includes(kw));
    }
    if (blokFilter.tahun) out = out.filter(r => String(r.tahun_tanam||'') === String(blokFilter.tahun));
    return out;
  }

  function loadServer(entity){
    const fd = new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    fd.append('entity',entity);
    fd.append('page', String(page));
    fd.append('per_page', String(perPage));
    if (entity === 'blok'){
      fd.append('unit_id', blokFilter.unit_id || '');
      fd.append('kode',    blokFilter.kode    || '');
      fd.append('tahun',   blokFilter.tahun   || '');
      fd.append('filters', JSON.stringify(blokFilter));
    }

    return fetch('master_data_crud.php', { method:'POST', body:fd })
      .then(r => { if(!r.ok) throw new Error(`HTTP error! status: ${r.status}`); return r.json(); })
      .then(j => {
        if (j && j.success && typeof j.total === 'number'){
          total = j.total;
          perPage = j.per_page ? parseInt(j.per_page) : perPage;
          page = j.page ? parseInt(j.page) : page;
          totalPages = Math.max(1, Math.ceil(total / perPage));
          renderRows(entity, j.data || []);
          updatePageInfo(); renderPager();
          clientCache = { entity:null, rows:[] };
          return true;
        }
        if (j && j.success && Array.isArray(j.data)){
          clientCache = { entity, rows:j.data };
          let list = clientCache.rows;
          if (entity === 'blok') list = applyBlokFilterClient(list);
          total = list.length; totalPages = Math.max(1, Math.ceil(total / perPage));
          page = Math.min(page, totalPages);
          const start = (page-1)*perPage;
          renderRows(entity, list.slice(start, start+perPage));
          updatePageInfo(); renderPager();
          return true;
        }
        throw new Error(j?.message || 'Gagal memuat data');
      });
  }

  function renderHeadAndLoad(entity){
    renderHead(entity);
    tbody.innerHTML = `<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-slate-400">Memuat data...</td></tr>`;
    $('#page-info').textContent = 'Memuat...'; $('#pager').innerHTML = '';

    if (clientCache.entity === entity && clientCache.rows.length){
      let list = clientCache.rows;
      if (entity === 'blok') list = applyBlokFilterClient(list);
      total = list.length; totalPages = Math.max(1, Math.ceil(total / perPage));
      page = Math.min(page, totalPages);
      const start = (page-1)*perPage;
      renderRows(entity, list.slice(start, start+perPage));
      updatePageInfo(); renderPager();
      return;
    }
    loadServer(entity).catch(err => {
      console.error('Load error:', err);
      tbody.innerHTML = `<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-rose-500 bg-rose-50 rounded-lg">Gagal memuat data. ${err.message||''}</td></tr>`;
    });
  }

  // Event Listeners
  document.querySelectorAll('#tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#tabs button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentEntity = btn.dataset.entity; page = 1;
      // Reset filter visual only
      if (currentEntity === 'blok'){
        document.getElementById('blok-filter-unit').value  = blokFilter.unit_id || '';
        document.getElementById('blok-filter-kode').value  = blokFilter.kode    || '';
        document.getElementById('blok-filter-tahun').value = blokFilter.tahun   || '';
      }
      renderHeadAndLoad(currentEntity);
    });
  });

  document.getElementById('per-page').addEventListener('change', e => {
    perPage = parseInt(e.target.value) || 15; page = 1; renderHeadAndLoad(currentEntity);
  });
  document.getElementById('btn-add').addEventListener('click', () => renderForm(currentEntity));
  document.getElementById('btn-close').onclick = close;
  document.getElementById('btn-cancel').onclick = close;

  // Filter Event
  document.getElementById('blok-filter-apply').addEventListener('click', (e)=>{ e.preventDefault();
    blokFilter.unit_id = document.getElementById('blok-filter-unit').value.trim();
    blokFilter.kode    = document.getElementById('blok-filter-kode').value.trim();
    blokFilter.tahun   = document.getElementById('blok-filter-tahun').value.trim();
    page=1; clientCache={entity:null,rows:[]}; renderHeadAndLoad('blok');
  });
  document.getElementById('blok-filter-reset').addEventListener('click', (e)=>{ e.preventDefault();
    blokFilter.unit_id=''; blokFilter.kode=''; blokFilter.tahun='';
    document.getElementById('blok-filter-unit').value=''; document.getElementById('blok-filter-kode').value=''; document.getElementById('blok-filter-tahun').value='';
    page=1; clientCache={entity:null,rows:[]}; renderHeadAndLoad('blok');
  });

  // Edit/Delete Delegation
  document.body.addEventListener('click', e => {
    const editBtn = e.target.closest('.btn-edit');
    const delBtn  = e.target.closest('.btn-del');
    if (editBtn){
      const entity = editBtn.dataset.entity;
      const data   = JSON.parse(decodeURIComponent(editBtn.dataset.json));
      renderForm(entity, data);
    } else if (delBtn){
      const entity = delBtn.dataset.entity;
      const id     = delBtn.dataset.id;
      Swal.fire({
        title:'Hapus Data?', text:'Data yang dihapus tidak dapat dikembalikan.', icon:'warning',
        showCancelButton:true, confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal', confirmButtonColor:'#e11d48'
      }).then(res=>{
        if (!res.isConfirmed) return;
        const fd = new FormData();
        fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); fd.append('action','delete'); fd.append('entity',entity); fd.append('id',id);
        fetch('master_data_crud.php',{method:'POST', body:fd}).then(r=>r.json()).then(j=>{
          if (j.success){ Swal.fire('Terhapus','','success'); clientCache={entity:null,rows:[]}; renderHeadAndLoad(entity); }
          else Swal.fire('Gagal', j.message||'Error','error');
        }).catch(()=>Swal.fire('Gagal','Koneksi Error','error'));
      });
    }
  });

  // Submit Form
  document.getElementById('crud-form').addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const entity = fd.get('entity');
    
    // Checkbox handling
    ENTITIES[entity].fields.forEach(f=>{
      if (f.type === 'checkbox') fd.set(f.name, e.target.querySelector(`[name="${f.name}"]`).checked ? '1' : '0');
    });

    fetch('master_data_crud.php',{method:'POST', body:fd})
      .then(r=>r.json())
      .then(j=>{
        if (j.success){ Swal.fire('Berhasil', 'Data disimpan', 'success'); close(); clientCache={entity:null,rows:[]}; page=1; renderHeadAndLoad(entity); }
        else Swal.fire('Gagal', j.message||'Error', 'error');
      })
      .catch(err=>Swal.fire('Error', err.message, 'error'));
  });

  // Initial
  renderHeadAndLoad(currentEntity);
});
</script>