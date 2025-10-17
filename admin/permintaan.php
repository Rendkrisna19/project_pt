<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

// --- MODIFIKASI: Dapatkan role user ---
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');
// ------------------------------------

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();
$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$kebun = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'permintaan';
include_once '../layouts/header.php';
?>
<style>
  /* --- MODIFIKASI: Style untuk tombol disabled --- */
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
</style>

<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-3xl font-bold text-gray-800">Pengajuan AU-58 (Permintaan Bahan)</h1>
      <p class="text-gray-500 mt-1">Kelola permintaan bahan untuk aktivitas kebun</p>
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
      <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ Tambah Pengajuan</button>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm">
    <div class="overflow-x-auto">
      <div class="max-h-[520px] overflow-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-green-500 sticky top-0 z-10">
            <tr class="text-gray-100">
              <th class="py-3 px-4 text-left">No. Dokumen</th><th class="py-3 px-4 text-left">Kebun</th>
              <th class="py-3 px-4 text-left">Unit/Devisi</th><th class="py-3 px-4 text-left">Tanggal</th>
              <th class="py-3 px-4 text-left">Blok</th><th class="py-3 px-4 text-left">Pokok</th>
              <th class="py-3 px-4 text-left">Dosis/Norma</th><th class="py-3 px-4 text-left">Jumlah Diminta</th>
              <th class="py-3 px-4 text-left">Keterangan</th><th class="py-3 px-4 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody id="tbody-data" class="text-gray-800">
            <tr><td colspan="10" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 p-3 border-t">
      <div class="flex items-center gap-2">
        <label for="page-size" class="text-sm text-gray-600">Tampilkan</label>
        <select id="page-size" class="border rounded px-2 py-1 text-sm">
          <option value="5">5</option><option value="10" selected>10</option><option value="25">25</option>
          <option value="50">50</option><option value="100">100</option>
        </select>
        <span class="text-sm text-gray-600">baris</span>
      </div>
      <div class="flex items-center gap-2" id="pager"></div>
      <div class="text-sm text-gray-600" id="range-info"></div>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Tambah Pengajuan</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>"><input type="hidden" name="action" id="form-action"><input type="hidden" name="id" id="form-id">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">No. Dokumen</label><input type="text" id="no_dokumen" name="no_dokumen" class="w-full border rounded px-3 py-2" required></div>
        <div><label class="block text-sm mb-1">Kebun</label><select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2" required><option value="">-- Pilih Kebun --</option><?php foreach ($kebun as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm mb-1">Unit/Devisi</label><select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2" required><option value="">-- Pilih Unit --</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm mb-1">Tanggal</label><input type="date" id="tanggal" name="tanggal" class="w-full border rounded px-3 py-2" required></div>
        <div><label class="block text-sm mb-1">Blok</label><input type="text" id="blok" name="blok" class="w-full border rounded px-3 py-2" placeholder="Pisahkan dengan koma jika lebih dari satu"></div>
        <div><label class="block text-sm mb-1">Pokok</label><input type="number" id="pokok" name="pokok" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Dosis/Norma</label><input type="text" id="dosis_norma" name="dosis_norma" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Jumlah Diminta</label><input type="number" step="0.01" id="jumlah_diminta" name="jumlah_diminta" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Keterangan</label><input type="text" id="keterangan" name="keterangan" class="w-full border rounded px-3 py-2"></div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // --- MODIFIKASI: Kirim role ke JavaScript ---
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;

  const $ = s => document.querySelector(s);
  const tbody=$('#tbody-data'), modal=$('#crud-modal'), btnAdd=$('#btn-add'), btnClose=$('#btn-close');
  const btnCancel=$('#btn-cancel'), form=$('#crud-form'), formAction=$('#form-action'), formId=$('#form-id'), title=$('#modal-title');
  const pageSizeSel=$('#page-size'), pagerEl=$('#pager'), rangeInfoEl=$('#range-info');
  const open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')}, close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

  let allData=[], currentPage=1;

  function paginate(array, pageSize, pageNumber){ const start=(pageNumber-1)*pageSize; return array.slice(start, start+pageSize); }

  function renderPager(page, totalPages){
    const windowSize=5; let start=Math.max(1, page-Math.floor(windowSize/2)), end=start+windowSize-1;
    if(end>totalPages){end=totalPages; start=Math.max(1, end-windowSize+1);}
    const btn=(label, disabled, goPage, extra='')=>`<button ${disabled?'disabled':''} data-goto="${goPage}" class="px-3 py-1 border rounded text-sm ${disabled?'opacity-50 cursor-not-allowed':'hover:bg-gray-100'} ${extra}">${label}</button>`;
    let html=''; html+=btn('« Prev', page<=1, page-1);
    for(let p=start; p<=end; p++){ html+=btn(p, false, p, p===page?'bg-gray-200 font-semibold':''); }
    html+=btn('Next »', page>=totalPages, page+1);
    pagerEl.innerHTML=html;
  }

  function renderTable(){
    const size=parseInt(pageSizeSel.value||'10',10), total=allData.length, totalPages=Math.max(1, Math.ceil(total/size));
    if(currentPage>totalPages) currentPage=totalPages;
    if(!total){
      tbody.innerHTML=`<tr><td colspan="10" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`;
      renderPager(1,1); rangeInfoEl.textContent=''; return;
    }
    const rows=paginate(allData, size, currentPage);
    tbody.innerHTML = rows.map(row => {
      // --- MODIFIKASI: Tambahkan atribut 'disabled' jika user adalah staf ---
      const disabledAttr = IS_STAF ? 'disabled' : '';
      return `
      <tr class="border-b hover:bg-gray-50">
        <td class="py-2 px-3">${row.no_dokumen??'-'}</td><td class="py-2 px-3">${row.nama_kebun??'-'}</td>
        <td class="py-2 px-3">${row.nama_unit??'-'}</td><td class="py-2 px-3">${row.tanggal??'-'}</td>
        <td class="py-2 px-3">${row.blok??'-'}</td><td class="py-2 px-3">${row.pokok??'-'}</td>
        <td class="py-2 px-3">${row.dosis_norma??'-'}</td><td class="py-2 px-3">${row.jumlah_diminta??'-'}</td>
        <td class="py-2 px-3">${row.keterangan??'-'}</td>
        <td class="py-2 px-3">
          <div class="flex items-center gap-2">
            <button class="btn-edit p-2 rounded-lg border border-gray-200 hover:bg-blue-50 hover:border-blue-300 text-blue-600" title="Edit" data-json='${JSON.stringify(row)}' ${disabledAttr}>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.1 2.1 0 0 1 2.97 2.97l-9.9 9.9-4.2 1.23 1.23-4.2 9.9-9.9z" /></svg>
            </button>
            <button class="btn-delete p-2 rounded-lg border border-gray-200 hover:bg-red-50 hover:border-red-300 text-red-600" title="Hapus" data-id="${row.id}" ${disabledAttr}>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3m-9 0h12" /></svg>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
    const start=(currentPage-1)*size+1, end=Math.min(currentPage*size, total);
    rangeInfoEl.textContent=`Menampilkan ${start}–${end} dari ${total} data`;
    renderPager(currentPage, totalPages);
  }

  function refreshList(){
    const fd=new FormData(); fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); fd.append('action','list');
    fetch('permintaan_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(!j.success){
          tbody.innerHTML=`<tr><td colspan="10" class="text-center py-8 text-red-500">${j.message||'Error'}</td></tr>`;
          allData=[]; renderPager(1,1); rangeInfoEl.textContent=''; return;
        }
        allData=Array.isArray(j.data)?j.data:[];
        currentPage=1; renderTable();
      }).catch(()=>{ tbody.innerHTML=`<tr><td colspan="10" class="text-center py-8 text-red-500">Gagal memuat data</td></tr>`; });
  }
  refreshList();

  pageSizeSel.addEventListener('change', ()=>{ currentPage=1; renderTable(); });
  pagerEl.addEventListener('click', (e)=>{
    const btn=e.target.closest('button[data-goto]');
    if(!btn||btn.disabled) return;
    const goto=parseInt(btn.dataset.goto,10);
    if(!Number.isNaN(goto)){ currentPage=goto; renderTable(); }
  });

  btnAdd.addEventListener('click',()=>{form.reset();formAction.value='store';formId.value='';title.textContent='Tambah Pengajuan';open();});
  btnClose.addEventListener('click',close); btnCancel.addEventListener('click',close);

  document.body.addEventListener('click',(e)=>{
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(e.target.closest('.btn-edit') && !IS_STAF){
      const btn=e.target.closest('.btn-edit'), row=JSON.parse(btn.dataset.json);
      formAction.value='update'; formId.value=row.id; title.textContent='Edit Pengajuan';
      ['no_dokumen','kebun_id','unit_id','tanggal','blok','pokok','dosis_norma','jumlah_diminta','keterangan'].forEach(k=>{
        const el=document.getElementById(k); if(el) el.value=row[k]??'';
      });
      open();
    }
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(e.target.closest('.btn-delete') && !IS_STAF){
      const btn=e.target.closest('.btn-delete'), id=btn.dataset.id;
      Swal.fire({title:'Hapus?',text:'Data tidak bisa dikembalikan',icon:'warning',showCancelButton:true})
      .then(res=>{
        if(res.isConfirmed){
          const fd=new FormData(); fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete'); fd.append('id',id);
          fetch('permintaan_crud.php',{method:'POST',body:fd})
            .then(r=>r.json()).then(j=>{
              if(j.success){ Swal.fire('OK','Data terhapus','success'); refreshList(); }
              else { Swal.fire('Gagal',j.message||'Error','error'); }
            });
        }
      });
    }
  });

  form.addEventListener('submit',(e)=>{
    e.preventDefault(); const fd=new FormData(form);
    fetch('permintaan_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(j.success){ close(); Swal.fire('Berhasil',j.message,'success'); refreshList(); }
        else { Swal.fire('Gagal',j.message||'Error','error'); }
      });
  });
});

document.getElementById('btn-export-excel').addEventListener('click',()=>{
  const qs=new URLSearchParams({csrf_token:'<?= htmlspecialchars($CSRF) ?>'}).toString();
  window.open('cetak/permintaan_export_excel.php?'+qs,'_blank');
});
document.getElementById('btn-export-pdf').addEventListener('click',()=>{
  const qs=new URLSearchParams({csrf_token:'<?= htmlspecialchars($CSRF) ?>'}).toString();
  window.open('cetak/permintaan_export_pdf.php?'+qs,'_blank');
});
</script>