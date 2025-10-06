<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

$units   = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$satuan  = $conn->query("SELECT id, nama FROM md_satuan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$sap     = $conn->query("SELECT id, no_sap FROM md_sap ORDER BY no_sap")->fetchAll(PDO::FETCH_ASSOC);
$pupuk   = $conn->query("SELECT id, nama FROM md_pupuk ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$kodeActs= $conn->query("SELECT id, kode FROM md_kode_aktivitas ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'master_data';
include_once '../layouts/header.php';
?>
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-gray-900">Manajemen Master Data</h1>
      <p class="text-gray-500 mt-1">CRUD semua master (Nama Kebun, Bahan Kimia, Jenis Pekerjaan, Unit, Blok, Satuan, dst.)</p>
    </div>
    <button id="btn-add" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 bg-emerald-600 text-white hover:bg-emerald-700 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-emerald-400">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11 11V5a1 1 0 1 1 2 0v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6z"/></svg>
      <span class="hidden sm:inline">Tambah</span>
    </button>
  </div>

  <!-- Tabs -->
  <div class="bg-white p-2 md:p-3 rounded-xl shadow-sm overflow-x-auto">
    <div id="tabs" class="flex gap-2 md:gap-3 whitespace-nowrap">
      <!-- NEW: dua tab baru -->
      <button data-entity="kebun" class="tab active">Nama Kebun</button>
      <button data-entity="bahan_kimia" class="tab">Bahan Kimia</button>

      <button data-entity="jenis_pekerjaan" class="tab">Jenis Pekerjaan</button>
      <button data-entity="unit" class="tab">Unit/Devisi</button>
      <button data-entity="tahun_tanam" class="tab">Tahun Tanam</button>
      <button data-entity="blok" class="tab">Blok</button>
      <button data-entity="fisik" class="tab">Fisik</button>
      <button data-entity="satuan" class="tab">Satuan</button>
      <button data-entity="tenaga" class="tab">Tenaga</button>
      <button data-entity="mobil" class="tab">Mobil</button>
      <button data-entity="alat_panen" class="tab">Jenis Alat Panen</button>
      <button data-entity="sap" class="tab">No SAP</button>
      <button data-entity="jabatan" class="tab">Jabatan</button>
      <button data-entity="pupuk" class="tab">Pupuk</button>
      <button data-entity="kode_aktivitas" class="tab">Kode Aktivitas</button>
      <button data-entity="anggaran" class="tab">Anggaran</button>
    </div>
  </div>

  <!-- Table + Scroll wrapper -->
  <div class="bg-white rounded-xl shadow-sm">
    <div class="overflow-x-auto">
      <div class="max-h-[70vh] overflow-y-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 sticky top-0 z-10">
            <tr id="thead-row" class="text-gray-600"></tr>
          </thead>
          <tbody id="tbody-data" class="[&>tr:nth-child(even)]:bg-gray-50/40">
            <tr><td class="py-10 text-center text-gray-500">Memuat…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination bar -->
    <div class="flex flex-col md:flex-row items-center justify-between gap-3 p-4 border-t">
      <div class="text-sm text-gray-600" id="page-info">—</div>
      <div class="flex items-center gap-3">
        <label class="text-sm text-gray-600">Per Halaman
          <select id="per-page" class="ml-2 border rounded px-2 py-1">
            <option>10</option><option selected>15</option><option>20</option><option>25</option><option>50</option><option>100</option>
          </select>
        </label>
        <div id="pager" class="flex items-center flex-wrap gap-1"></div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL CRUD -->
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl md:text-2xl font-bold">Tambah Data</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800 focus:outline-none" aria-label="Tutup">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="entity" id="form-entity">
      <input type="hidden" name="id" id="form-id">
      <div id="form-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded border bg-white hover:bg-gray-50 text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">Batal</button>
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const $=s=>document.querySelector(s);
  const tbody=$('#tbody-data'), thead=$('#thead-row');

  // default buka "Nama Kebun"
  let currentEntity='kebun';

  // pagination state
  let page = 1;
  let perPage = 15;
  let total = 0;
  let totalPages = 1;

  // cache untuk client-side pagination fallback
  let clientCache = { entity:null, rows:[] };

  const OPTIONS_UNITS = [<?php foreach($units as $u){echo "{value:{$u['id']},label:'".htmlspecialchars($u['nama_unit'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_SATUAN= [<?php foreach($satuan as $s){echo "{value:{$s['id']},label:'".htmlspecialchars($s['nama'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_SAP   = [<?php foreach($sap as $s){echo "{value:{$s['id']},label:'".htmlspecialchars($s['no_sap'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_PUPUK = [<?php foreach($pupuk as $p){echo "{value:{$p['id']},label:'".htmlspecialchars($p['nama'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_KODE  = [<?php foreach($kodeActs as $k){echo "{value:{$k['id']},label:'".htmlspecialchars($k['kode'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_BULAN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'].map(b=>({value:b,label:b}));
  const yearNow = (new Date()).getFullYear();
  const OPTIONS_TAHUN = Array.from({length:6},(_,i)=> yearNow-1+i).map(y=>({value:y,label:y}));

  // ================== ENTITIES ==================
  const ENTITIES = {
    kebun:{ title:'Nama Kebun', table:['Kode','Nama Kebun','Keterangan','Aksi'],
      fields:[
        {name:'kode',label:'Kode Kebun',type:'text',required:true},
        {name:'nama_kebun',label:'Nama Kebun',type:'text',required:true},
        {name:'keterangan',label:'Keterangan',type:'text'}
      ]},
    bahan_kimia:{ title:'Bahan Kimia', table:['Kode','Nama Bahan','Satuan','Keterangan','Aksi'],
      fields:[
        {name:'kode',label:'Kode Bahan',type:'text',required:true},
        {name:'nama_bahan',label:'Nama Bahan',type:'text',required:true},
        {name:'satuan_id',label:'Satuan',type:'select',options:OPTIONS_SATUAN},
        {name:'keterangan',label:'Keterangan',type:'text'}
      ]},
    jenis_pekerjaan:{ title:'Jenis Pekerjaan', table:['Nama','Keterangan','Aksi'],
      fields:[{name:'nama',label:'Nama',type:'text',required:true},{name:'keterangan',label:'Keterangan',type:'text'}] },
    unit:{ title:'Unit/Devisi', table:['Nama Unit','Keterangan','Aksi'],
      fields:[{name:'nama_unit',label:'Nama Unit',type:'text',required:true},{name:'keterangan',label:'Keterangan',type:'text'}] },
    tahun_tanam:{ title:'Tahun Tanam', table:['Tahun','Keterangan','Aksi'],
      fields:[{name:'tahun',label:'Tahun',type:'number',required:true},{name:'keterangan',label:'Keterangan',type:'text'}] },
    blok:{ title:'Blok', table:['Unit','Kode Blok','Tahun Tanam','Luas (Ha)','Aksi'],
      fields:[
        {name:'unit_id',label:'Unit',type:'select',options:OPTIONS_UNITS,required:true},
        {name:'kode',label:'Kode Blok',type:'text',required:true},
        {name:'tahun_tanam',label:'Tahun Tanam',type:'number'},
        {name:'luas_ha',label:'Luas (Ha)',type:'number',step:'0.01'}
      ]},
    fisik:{ title:'Fisik', table:['Nama','Aksi'],
      fields:[{name:'nama',label:'Nama Fisik (Ha/Pkk/Unit/..)',type:'text',required:true}] },
    satuan:{ title:'Satuan', table:['Nama','Aksi'],
      fields:[{name:'nama',label:'Nama Satuan (Kg/Liter/..)',type:'text',required:true}] },
    tenaga:{ title:'Tenaga', table:['Kode','Nama','Aksi'],
      fields:[{name:'kode',label:'Kode (TS/KNG/PKWT/TP)',type:'text',required:true},{name:'nama',label:'Nama',type:'text',required:true}] },
    mobil:{ title:'Mobil', table:['Kode','Nama','Aksi'],
      fields:[{name:'kode',label:'Kode (TS/TP)',type:'text',required:true},{name:'nama',label:'Nama',type:'text',required:true}] },
    alat_panen:{ title:'Jenis Alat Panen', table:['Nama','Keterangan','Aksi'],
      fields:[{name:'nama',label:'Nama',type:'text',required:true},{name:'keterangan',label:'Keterangan',type:'text'}] },
    sap:{ title:'No SAP', table:['No SAP','Deskripsi','Aksi'],
      fields:[{name:'no_sap',label:'No SAP',type:'text',required:true},{name:'deskripsi',label:'Deskripsi',type:'text'}] },
    jabatan:{ title:'Jabatan', table:['Nama','Aksi'],
      fields:[{name:'nama',label:'Nama Jabatan',type:'text',required:true}] },
    pupuk:{ title:'Pupuk', table:['Nama','Satuan','Aksi'],
      fields:[{name:'nama',label:'Nama Pupuk',type:'text',required:true},{name:'satuan_id',label:'Satuan',type:'select',options:OPTIONS_SATUAN}] },
    kode_aktivitas:{ title:'Kode Aktivitas', table:['Kode','Nama','No SAP','Aksi'],
      fields:[{name:'kode',label:'Kode',type:'text',required:true},{name:'nama',label:'Nama',type:'text',required:true},{name:'no_sap_id',label:'No SAP',type:'select',options:OPTIONS_SAP}] },
    anggaran:{ title:'Anggaran', table:['Periode','Unit','Kode Aktivitas','Pupuk','Angg Bulan','Angg Tahun','Aksi'],
      fields:[
        {name:'tahun',label:'Tahun',type:'select',options:OPTIONS_TAHUN,required:true},
        {name:'bulan',label:'Bulan',type:'select',options:OPTIONS_BULAN,required:true},
        {name:'unit_id',label:'Unit',type:'select',options:OPTIONS_UNITS,required:true},
        {name:'kode_aktivitas_id',label:'Kode Aktivitas',type:'select',options:OPTIONS_KODE,required:true},
        {name:'pupuk_id',label:'Pupuk',type:'select',options:OPTIONS_PUPUK},
        {name:'anggaran_bulan_ini',label:'Anggaran Bulan Ini',type:'number',step:'0.01',required:true},
        {name:'anggaran_tahun',label:'Anggaran Tahun',type:'number',step:'0.01',required:true}
      ]}
  };

  const modal=$('#crud-modal');
  const open = ()=>{modal.classList.remove('hidden');modal.classList.add('flex')};
  const close= ()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

  function renderHead(entity){
    thead.innerHTML='';
    ENTITIES[entity].table.forEach((h,i)=>{
      const th=document.createElement('th');
      th.className='py-3 px-4 text-left font-semibold text-gray-700';
      th.textContent=h;
      if (i===ENTITIES[entity].table.length-1) th.className+=' w-32';
      thead.appendChild(th);
    });
  }

  function inputEl(f){
    const wrap=document.createElement('div');
    const label=`<label class="block text-sm font-medium text-gray-700 mb-1">${f.label}${f.required?'<span class="text-red-500">*</span>':''}</label>`;
    let control='';
    if(f.type==='select'){
      const opts=(f.options||[]).map(o=>`<option value="${o.value}">${o.label}</option>`).join('');
      control=`<select id="${f.name}" name="${f.name}" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300" ${f.required?'required':''}>${opts}</select>`;
    }else{
      control=`<input type="${f.type||'text'}" ${f.step?`step="${f.step}"`:''} id="${f.name}" name="${f.name}" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300" ${f.required?'required':''}>`;
    }
    wrap.innerHTML=label+control;
    return wrap;
  }

  function renderForm(entity, data={}){
    $('#modal-title').textContent=(data.id?'Edit ':'Tambah ')+ENTITIES[entity].title;
    $('#form-entity').value=entity;
    $('#form-action').value=data.id?'update':'store';
    $('#form-id').value=data.id||'';
    const holder=$('#form-fields');
    holder.innerHTML='';
    ENTITIES[entity].fields.forEach(f=>{
      const el=inputEl(f);
      holder.appendChild(el);
      if(data && data[f.name]!=null){
        holder.querySelector('#'+f.name).value = data[f.name];
      }
    });
    open();
  }

  function cell(v){ return (v==null||v==='')?'-':v; }

  function actionButtons(entity, rowJson, id){
    const payload = encodeURIComponent(JSON.stringify(rowJson));
    return `
      <div class="flex items-center gap-2">
        <button class="btn-edit icon-btn text-blue-600 hover:bg-blue-50" title="Edit" aria-label="Edit" data-entity="${entity}" data-json="${payload}">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M21.7 7.04a1 1 0 0 0 0-1.41l-3.33-3.33a1 1 0 0 0-1.41 0L4 14.26V18a1 1 0 0 0 1 1h3.74L21.7 7.04zM7.41 17H6v-1.41L15.06 6.5l1.41 1.41L7.41 17z"/></svg>
        </button>
        <button class="btn-del icon-btn text-rose-600 hover:bg-rose-50" title="Hapus" aria-label="Hapus" data-entity="${entity}" data-id="${id}">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M9 3a1 1 0 0 0-1 1v1H5a1 1 0 1 0 0 2h.59l.86 12.04A2 2 0 0 0 8.45 22h7.1a2 2 0 0 0 2-1.96L18.41 7H19a1 1 0 1 0 0-2h-3V4a1 1 0 0 0-1-1H9zm2 2h2v1h-2V5zm-1.58 3h7.16l-.82 11.5a.5.5 0 0 1-.5.47h-4.99a.5.5 0 0 1-.5-.47L9.42 8z"/></svg>
        </button>
      </div>`;
  }

  function rowCols(entity, r){
    switch(entity){
      case 'kebun':
        return `<td class="py-2.5 px-3">${cell(r.kode)}</td>
                <td class="py-2.5 px-3">${cell(r.nama_kebun)}</td>
                <td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'bahan_kimia':
        return `<td class="py-2.5 px-3">${cell(r.kode)}</td>
                <td class="py-2.5 px-3">${cell(r.nama_bahan)}</td>
                <td class="py-2.5 px-3">${cell(r.nama_satuan||'')}</td>
                <td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'jenis_pekerjaan': return `<td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'unit':           return `<td class="py-2.5 px-3">${cell(r.nama_unit)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'tahun_tanam':    return `<td class="py-2.5 px-3">${cell(r.tahun)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'blok':           return `<td class="py-2.5 px-3">${cell(r.nama_unit)}</td><td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.tahun_tanam)}</td><td class="py-2.5 px-3">${cell(r.luas_ha)}</td>`;
      case 'fisik':          return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'satuan':         return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'tenaga':         return `<td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'mobil':          return `<td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'alat_panen':     return `<td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'sap':            return `<td class="py-2.5 px-3">${cell(r.no_sap)}</td><td class="py-2.5 px-3">${cell(r.deskripsi)}</td>`;
      case 'jabatan':        return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'pupuk':          return `<td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.nama_satuan||'')}</td>`;
      case 'kode_aktivitas': return `<td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.no_sap||'')}</td>`;
      case 'anggaran':       return `<td class="py-2.5 px-3">${cell(r.bulan)} ${cell(r.tahun)}</td><td class="py-2.5 px-3">${cell(r.nama_unit)}</td><td class="py-2.5 px-3">${cell(r.kode_aktivitas)}</td><td class="py-2.5 px-3">${cell(r.nama_pupuk||'')}</td><td class="py-2.5 px-3">${(+r.anggaran_bulan_ini).toLocaleString()}</td><td class="py-2.5 px-3">${(+r.anggaran_tahun).toLocaleString()}</td>`;
    }
    return '';
  }

  function renderRows(entity, rows){
    if(!rows.length){
      tbody.innerHTML=`<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-gray-500">Belum ada data.</td></tr>`;
      return;
    }
    tbody.innerHTML = rows.map(r=>`
      <tr class="border-b last:border-0 hover:bg-emerald-50/40 transition-colors">
        ${rowCols(entity, r)}
        <td class="py-2.5 px-3">${actionButtons(entity, r, r.id)}</td>
      </tr>
    `).join('');
  }

  // ===== Pagination UI =====
  function updatePageInfo(){
    const info = $('#page-info');
    const start = total ? ((page-1)*perPage)+1 : 0;
    const end = Math.min(page*perPage, total);
    info.textContent = `Menampilkan ${start.toLocaleString()}–${end.toLocaleString()} dari ${total.toLocaleString()} data`;
  }

  function renderPager(){
    const pager = $('#pager');
    pager.innerHTML = '';
    const makeBtn=(label, targetPage, disabled=false, active=false)=>{
      const a=document.createElement('button');
      a.textContent=label;
      a.className='px-3 py-1 border rounded';
      if (active){ a.className+=' bg-black text-white border-black'; }
      else if (!disabled){ a.className+=' hover:bg-gray-50'; }
      if (disabled){ a.disabled=true; a.classList.add('opacity-40','pointer-events-none'); }
      a.addEventListener('click', ()=> { if(!disabled){ page=targetPage; renderHeadAndLoad(currentEntity); } });
      pager.appendChild(a);
    };

    makeBtn('« First', 1, page<=1);
    makeBtn('‹ Prev', Math.max(1,page-1), page<=1);

    // window 5
    const win=5;
    let start = Math.max(1, page - Math.floor(win/2));
    let end = Math.min(totalPages, start + win - 1);
    start = Math.max(1, end - win + 1);
    for(let p=start; p<=end; p++){
      makeBtn(String(p), p, false, p===page);
    }

    makeBtn('Next ›', Math.min(totalPages, page+1), page>=totalPages);
    makeBtn('Last »', totalPages, page>=totalPages);
  }

  // ===== Data Loader (server or client pagination) =====
  function loadServer(entity){
    const fd=new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    fd.append('entity',entity);
    fd.append('page', String(page));
    fd.append('per_page', String(perPage));

    return fetch('master_data_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        // Jika server mengembalikan total (server-side pagination)
        if (j && j.success && typeof j.total === 'number') {
          total = j.total;
          perPage = j.per_page ? parseInt(j.per_page) : perPage;
          page = j.page ? parseInt(j.page) : page;
          totalPages = Math.max(1, Math.ceil(total / perPage));
          renderRows(entity, j.data||[]);
          updatePageInfo();
          renderPager();
          return true;
        }
        // Fallback: anggap j.data adalah full list (client-side)
        if (j && j.success && Array.isArray(j.data)) {
          clientCache = { entity, rows: j.data };
          total = clientCache.rows.length;
          totalPages = Math.max(1, Math.ceil(total / perPage));
          const start = (page-1)*perPage;
          const slice = clientCache.rows.slice(start, start+perPage);
          renderRows(entity, slice);
          updatePageInfo();
          renderPager();
          return true;
        }
        throw new Error(j?.message || 'Gagal memuat data');
      });
  }

  function renderHeadAndLoad(entity){
    renderHead(entity);
    tbody.innerHTML=`<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-gray-500">Memuat…</td></tr>`;

    // Jika cache entity sama dan kita berada di client-side mode, cukup slice
    if (clientCache.entity === entity && clientCache.rows.length){
      total = clientCache.rows.length;
      totalPages = Math.max(1, Math.ceil(total / perPage));
      const start = (page-1)*perPage;
      const slice = clientCache.rows.slice(start, start+perPage);
      renderRows(entity, slice);
      updatePageInfo();
      renderPager();
      return;
    }

    // Load dari server
    loadServer(entity).catch(()=>{
      tbody.innerHTML=`<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-red-500">Gagal memuat data.</td></tr>`;
      $('#page-info').textContent='—';
      $('#pager').innerHTML='';
    });
  }

  // Tabs
  document.querySelectorAll('#tabs button').forEach(btn=>{
    btn.addEventListener('click',()=>{
      document.querySelectorAll('#tabs button').forEach(b=>b.classList.remove('active','border-emerald-600','text-emerald-700'));
      btn.classList.add('active','border-emerald-600','text-emerald-700');
      currentEntity = btn.dataset.entity;
      // reset pagination saat pindah entity
      page = 1;
      clientCache = { entity:null, rows:[] };
      renderHeadAndLoad(currentEntity);
    });
  });

  // Per-page selector
  $('#per-page').addEventListener('change', (e)=>{
    perPage = parseInt(e.target.value) || 15;
    page = 1;
    renderHeadAndLoad(currentEntity);
  });

  // Add
  $('#btn-add').addEventListener('click', ()=> renderForm(currentEntity));

  // Edit/Delete
  document.body.addEventListener('click', e=>{
    if(e.target.closest('.btn-edit')){
      const t=e.target.closest('.btn-edit');
      const entity=t.dataset.entity;
      const data=JSON.parse(decodeURIComponent(t.dataset.json));
      renderForm(entity,data);
    }
    if(e.target.closest('.btn-del')){
      const t=e.target.closest('.btn-del');
      const entity=t.dataset.entity;
      const id=t.dataset.id;
      Swal.fire({title:'Hapus data?',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, hapus'}).then(res=>{
        if(res.isConfirmed){
          const fd=new FormData();
          fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete');
          fd.append('entity',entity);
          fd.append('id',id);
          fetch('master_data_crud.php',{method:'POST',body:fd})
            .then(r=>r.json())
            .then(j=>{
              if(j.success){
                Swal.fire('Terhapus','', 'success');
                // refresh list pada halaman sekarang
                if (clientCache.entity === entity && clientCache.rows.length){
                  // hapus dari cache juga
                  clientCache.rows = clientCache.rows.filter(x => String(x.id) !== String(id));
                }
                renderHeadAndLoad(entity);
              }
              else Swal.fire('Gagal', j.message||'Error','error');
            })
            .catch(()=> Swal.fire('Gagal','Jaringan bermasalah','error'));
        }
      })
    }
  });

  // Modal
  const modalEl=document.getElementById('crud-modal');
  document.getElementById('btn-close').onclick=()=>{modalEl.classList.add('hidden');modalEl.classList.remove('flex')};
  document.getElementById('btn-cancel').onclick=()=>{modalEl.classList.add('hidden');modalEl.classList.remove('flex')};

  // Submit
  document.getElementById('crud-form').addEventListener('submit', e=>{
    e.preventDefault();
    const fd=new FormData(e.target);
    const entity=fd.get('entity');
    const def=ENTITIES[entity];
    let ok=true;
    def.fields.forEach(f=>{ if(f.required && !(fd.get(f.name)||'').toString().trim()) ok=false; });
    if(!ok){ Swal.fire('Oops','Lengkapi field wajib (*)','warning'); return; }

    fetch('master_data_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        if(j.success){
          Swal.fire('Berhasil',j.message,'success');
          modalEl.classList.add('hidden'); modalEl.classList.remove('flex');
          // invalidasi cache biar refetch bersih (atau kita patch cache)
          clientCache = { entity:null, rows:[] };
          // setelah create/update, balik ke halaman 1 biar kelihatan data baru
          page = 1;
          renderHeadAndLoad(entity);
        }
        else Swal.fire('Gagal', j.message||'Error', 'error');
      })
      .catch(()=> Swal.fire('Gagal','Jaringan bermasalah','error'));
  });

  // Load awal
  renderHeadAndLoad(currentEntity);
});
</script>

<style>
#tabs .tab{ padding:.5rem .75rem; border-radius:.5rem; border:1px solid transparent; color:#334155; background:#fff; }
#tabs .tab:hover{ background:#f8fafc; }
#tabs .tab.active{ background:#ecfeff; border-color:#34d399; color:#065f46; }
.icon-btn{ display:inline-flex; align-items:center; justify-content:center; width:2.25rem; height:2.25rem; border-radius:.5rem; transition:background-color .15s ease, transform .05s ease; }
.icon-btn:active{ transform:scale(.98); }
table thead th{ position:sticky; top:0; background:#f9fafb; }
</style>
