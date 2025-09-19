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
      <h1 class="text-2xl font-bold">Manajemen Master Data</h1>
      <p class="text-gray-500">CRUD semua master (Jenis Pekerjaan, Unit, Blok, Satuan, dst.)</p>
    </div>
    <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ Tambah</button>
  </div>

  <div class="bg-white p-3 rounded-xl shadow-sm overflow-x-auto">
    <div id="tabs" class="flex gap-3 whitespace-nowrap">
      <button data-entity="jenis_pekerjaan" class="tab active">Jenis Pekerjaan</button>
      <button data-entity="unit">Unit/Devisi</button>
      <button data-entity="tahun_tanam">Tahun Tanam</button>
      <button data-entity="blok">Blok</button>
      <button data-entity="fisik">Fisik</button>
      <button data-entity="satuan">Satuan</button>
      <button data-entity="tenaga">Tenaga</button>
      <button data-entity="mobil">Mobil</button>
      <button data-entity="alat_panen">Jenis Alat Panen</button>
      <button data-entity="sap">No SAP</button>
      <button data-entity="jabatan">Jabatan</button>
      <button data-entity="pupuk">Pupuk</button>
      <button data-entity="kode_aktivitas">Kode Aktivitas</button>
      <button data-entity="anggaran">Anggaran</button>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr id="thead-row" class="text-gray-600"></tr>
      </thead>
      <tbody id="tbody-data">
        <tr><td class="py-10 text-center text-gray-500">Memuat…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL CRUD -->
<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Tambah Data</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="entity" id="form-entity">
      <input type="hidden" name="id" id="form-id">
      <div id="form-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded bg-gray-100 hover:bg-gray-200">Batal</button>
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Simpan</button>
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
  let currentEntity='jenis_pekerjaan';

  const OPTIONS_UNITS = [<?php foreach($units as $u){echo "{value:{$u['id']},label:'".htmlspecialchars($u['nama_unit'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_SATUAN= [<?php foreach($satuan as $s){echo "{value:{$s['id']},label:'".htmlspecialchars($s['nama'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_SAP   = [<?php foreach($sap as $s){echo "{value:{$s['id']},label:'".htmlspecialchars($s['no_sap'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_PUPUK = [<?php foreach($pupuk as $p){echo "{value:{$p['id']},label:'".htmlspecialchars($p['nama'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_KODE  = [<?php foreach($kodeActs as $k){echo "{value:{$k['id']},label:'".htmlspecialchars($k['kode'],ENT_QUOTES)."'},";}?>];
  const OPTIONS_BULAN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'].map(b=>({value:b,label:b}));
  const yearNow = (new Date()).getFullYear();
  const OPTIONS_TAHUN = Array.from({length:6},(_,i)=> yearNow-1+i).map(y=>({value:y,label:y}));

  const ENTITIES = {
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
    ENTITIES[entity].table.forEach(h=>{
      const th=document.createElement('th'); 
      th.className='py-3 px-4 text-left'; 
      th.textContent=h; 
      thead.appendChild(th);
    });
  }

  function inputEl(f){
    const wrap=document.createElement('div');
    const label=`<label class="block text-sm mb-1">${f.label}${f.required?'<span class="text-red-500">*</span>':''}</label>`;
    let control='';
    if(f.type==='select'){
      const opts=(f.options||[]).map(o=>`<option value="${o.value}">${o.label}</option>`).join('');
      control=`<select id="${f.name}" name="${f.name}" class="w-full border rounded px-3 py-2" ${f.required?'required':''}>${opts}</select>`;
    }else{
      control=`<input type="${f.type||'text'}" ${f.step?`step="${f.step}"`:''} id="${f.name}" name="${f.name}" class="w-full border rounded px-3 py-2" ${f.required?'required':''}>`;
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

  function renderRows(entity, rows){
    if(!rows.length){ 
      tbody.innerHTML=`<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-gray-500">Belum ada data.</td></tr>`; 
      return; 
    }
    tbody.innerHTML = rows.map(r=>{
      let cols='';
      switch(entity){
        case 'jenis_pekerjaan': cols=`<td class="py-2 px-3">${cell(r.nama)}</td><td class="py-2 px-3">${cell(r.keterangan)}</td>`; break;
        case 'unit':           cols=`<td class="py-2 px-3">${cell(r.nama_unit)}</td><td class="py-2 px-3">${cell(r.keterangan)}</td>`; break;
        case 'tahun_tanam':    cols=`<td class="py-2 px-3">${cell(r.tahun)}</td><td class="py-2 px-3">${cell(r.keterangan)}</td>`; break;
        case 'blok':           cols=`<td class="py-2 px-3">${cell(r.nama_unit)}</td><td class="py-2 px-3">${cell(r.kode)}</td><td class="py-2 px-3">${cell(r.tahun_tanam)}</td><td class="py-2 px-3">${cell(r.luas_ha)}</td>`; break;
        case 'fisik':          cols=`<td class="py-2 px-3">${cell(r.nama)}</td>`; break;
        case 'satuan':         cols=`<td class="py-2 px-3">${cell(r.nama)}</td>`; break;
        case 'tenaga':         cols=`<td class="py-2 px-3">${cell(r.kode)}</td><td class="py-2 px-3">${cell(r.nama)}</td>`; break;
        case 'mobil':          cols=`<td class="py-2 px-3">${cell(r.kode)}</td><td class="py-2 px-3">${cell(r.nama)}</td>`; break;
        case 'alat_panen':     cols=`<td class="py-2 px-3">${cell(r.nama)}</td><td class="py-2 px-3">${cell(r.keterangan)}</td>`; break;
        case 'sap':            cols=`<td class="py-2 px-3">${cell(r.no_sap)}</td><td class="py-2 px-3">${cell(r.deskripsi)}</td>`; break;
        case 'jabatan':        cols=`<td class="py-2 px-3">${cell(r.nama)}</td>`; break;
        case 'pupuk':          cols=`<td class="py-2 px-3">${cell(r.nama)}</td><td class="py-2 px-3">${cell(r.nama_satuan||'')}</td>`; break;
        case 'kode_aktivitas': cols=`<td class="py-2 px-3">${cell(r.kode)}</td><td class="py-2 px-3">${cell(r.nama)}</td><td class="py-2 px-3">${cell(r.no_sap||'')}</td>`; break;
        case 'anggaran':       cols=`<td class="py-2 px-3">${cell(r.bulan)} ${cell(r.tahun)}</td><td class="py-2 px-3">${cell(r.nama_unit)}</td><td class="py-2 px-3">${cell(r.kode_aktivitas)}</td><td class="py-2 px-3">${cell(r.nama_pupuk||'')}</td><td class="py-2 px-3">${(+r.anggaran_bulan_ini).toLocaleString()}</td><td class="py-2 px-3">${(+r.anggaran_tahun).toLocaleString()}</td>`; break;
      }
      // === FIX: encode JSON payload agar aman di atribut HTML ===
      const payload = encodeURIComponent(JSON.stringify(r));
      return `<tr class="border-b hover:bg-gray-50">
        ${cols}
        <td class="py-2 px-3">
          <button class="text-blue-600 underline btn-edit" data-entity="${entity}" data-json="${payload}">Edit</button>
          <button class="text-red-600 underline btn-del" data-entity="${entity}" data-id="${r.id}">Hapus</button>
        </td>
      </tr>`;
    }).join('');
  }

  function refresh(entity){
    currentEntity=entity; 
    renderHead(entity);
    tbody.innerHTML=`<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-gray-500">Memuat…</td></tr>`;
    const fd=new FormData(); 
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); 
    fd.append('action','list'); 
    fd.append('entity',entity);
    fetch('master_data_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(j.success) renderRows(entity, j.data||[]);
        else tbody.innerHTML=`<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-red-500">${j.message||'Error'}</td></tr>`;
      })
      .catch(()=> tbody.innerHTML=`<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-red-500">Gagal memuat data.</td></tr>`);
  }

  // Tabs
  document.querySelectorAll('#tabs button').forEach(btn=>{
    btn.addEventListener('click',()=>{
      document.querySelectorAll('#tabs button').forEach(b=>b.classList.remove('active','border-b-2','border-emerald-600','text-emerald-700'));
      btn.classList.add('active','border-b-2','border-emerald-600','text-emerald-700');
      refresh(btn.dataset.entity);
    });
  });

  // Add
  $('#btn-add').addEventListener('click', ()=> renderForm(currentEntity));

  // Edit/Delete
  document.body.addEventListener('click', e=>{
    if(e.target.classList.contains('btn-edit')){
      const entity=e.target.dataset.entity; 
      const data=JSON.parse(decodeURIComponent(e.target.dataset.json)); // FIX decode
      renderForm(entity,data);
    }
    if(e.target.classList.contains('btn-del')){
      const entity=e.target.dataset.entity; 
      const id=e.target.dataset.id;
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
              if(j.success){ Swal.fire('Terhapus','', 'success'); refresh(entity); }
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
    // cek required
    const def=ENTITIES[entity];
    let ok=true;
    def.fields.forEach(f=>{ 
      if(f.required && !(fd.get(f.name)||'').toString().trim()) ok=false; 
    });
    if(!ok){ Swal.fire('Oops','Lengkapi field wajib (*)','warning'); return; }

    fetch('master_data_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())  
      .then(j=>{
        if(j.success){ Swal.fire('Berhasil',j.message,'success'); modalEl.classList.add('hidden'); modalEl.classList.remove('flex'); refresh(entity); }
        else Swal.fire('Gagal', j.message||'Error', 'error');
      })
      .catch(()=> Swal.fire('Gagal','Jaringan bermasalah','error'));
  });

  // Load awal
  refresh(currentEntity);
});
</script>
<style>
#tabs button { padding:.5rem .75rem; border-radius:.5rem; }
#tabs button.active { background:#ecfeff; }
</style>
