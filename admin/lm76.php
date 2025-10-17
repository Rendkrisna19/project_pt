<?php
// lm76.php — MOD: Role 'staf' tidak bisa edit/hapus + tombol ikon
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

/* masters */
$units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$kebun  = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$ttList = $conn->query("SELECT DISTINCT tahun FROM md_tahun_tanam WHERE tahun IS NOT NULL AND tahun<>'' ORDER BY tahun")->fetchAll(PDO::FETCH_COLUMN);
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

$currentPage = 'lm76';
include_once '../layouts/header.php';
?>
<style>
  .thead-sticky th{position:sticky;top:0;z-index:10}
  /* --- MODIFIKASI: Style untuk tombol disabled --- */
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">LM-76 — Statistik Panen Kelapa Sawit</h1>
      <p class="text-gray-500">Input 13 kolom sesuai kebutuhan laporan LM-76</p>
    </div>
    <div class="flex gap-2">
      <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ Input LM-76</button>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm grid grid-cols-1 md:grid-cols-5 gap-3">
    <select id="filter-kebun" class="border rounded px-3 py-2"><option value="">Semua Kebun</option><?php foreach ($kebun as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select>
    <select id="filter-unit" class="border rounded px-3 py-2"><option value="">Semua Unit</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select>
    <select id="filter-bulan" class="border rounded px-3 py-2"><option value="">Semua Bulan</option><?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?></select>
    <select id="filter-tahun" class="border rounded px-3 py-2"><?php for ($y=$tahunNow-2;$y<=$tahunNow+2;$y++): ?><option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select>
    <select id="filter-tt" class="border rounded px-3 py-2"><option value="">Semua T. Tanam</option><?php foreach ($ttList as $tt): ?><option value="<?= htmlspecialchars($tt) ?>"><?= htmlspecialchars($tt) ?></option><?php endforeach; ?></select>
  </div>

  <div class="bg-white rounded-xl shadow-sm">
    <div class="overflow-x-auto">
      <div class="max-h-[520px] overflow-auto">
        <table class="min-w-full text-sm">
          <thead class="thead-sticky bg-green-700 text-white">
            <tr>
              <th class="py-3 px-4 text-left">Tahun</th><th class="py-3 px-4 text-left">Kebun</th>
              <th class="py-3 px-4 text-left">Unit/Defisi</th><th class="py-3 px-4 text-left">Periode</th>
              <th class="py-3 px-4 text-left">T. Tanam</th><th class="py-3 px-4 text-right">Luas (Ha)</th>
              <th class="py-3 px-4 text-right">Invt Pokok</th><th class="py-3 px-4 text-right">Anggaran (Kg)</th>
              <th class="py-3 px-4 text-right">Realisasi (Kg)</th><th class="py-3 px-4 text-right">Jumlah Tandan</th>
              <th class="py-3 px-4 text-right">Jumlah HK</th><th class="py-3 px-4 text-right">Panen (Ha)</th>
              <th class="py-3 px-4 text-right">Frekuensi</th><th class="py-3 px-4 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody id="tbody-data" class="text-gray-800">
            <tr><td colspan="14" class="text-center py-10 text-gray-500">Memuat…</td></tr>
          </tbody>
          <tfoot>
            <tr class="bg-green-50 border-t-4 border-green-700 text-gray-900">
              <td class="py-3 px-4 font-semibold" colspan="5">TOTAL</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-luas">0</td><td class="py-3 px-4 text-right font-semibold" id="tot-pokok">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-anggaran">0</td><td class="py-3 px-4 text-right font-semibold" id="tot-realisasi">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-tandan">0</td><td class="py-3 px-4 text-right font-semibold" id="tot-hk">0</td>
              <td class="py-3 px-4 text-right font-semibold" id="tot-panenha">0</td><td class="py-3 px-4 text-right font-semibold" id="tot-freq">0</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 p-3 border-t">
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-600">Tampilkan</label>
        <select id="page-size" class="border rounded px-2 py-1 text-sm">
          <option value="10" selected>10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
        </select>
        <span class="text-sm text-gray-600">baris</span>
      </div>
      <div class="flex items-center gap-2" id="pager"></div><div class="text-sm text-gray-600" id="range-info"></div>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input LM-76</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>"><input type="hidden" name="action" id="form-action"><input type="hidden" name="id" id="form-id">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2"><label class="block text-sm mb-1">Kebun</label><select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2"><option value="">-- Pilih Kebun --</option><?php foreach ($kebun as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select></div>
        <div class="md:col-span-2"><label class="block text-sm mb-1">Unit/Defisi</label><select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2" required><option value="">-- Pilih Unit --</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm mb-1">Bulan</label><select id="bulan" name="bulan" class="w-full border rounded px-3 py-2" required><?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm mb-1">Tahun</label><select id="tahun" name="tahun" class="w-full border rounded px-3 py-2" required><?php for ($y=$tahunNow-1;$y<=$tahunNow+3;$y++): ?><option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></div>
        <div><label class="block text-sm mb-1">T. Tanam</label><select id="tt" name="tt" class="w-full border rounded px-3 py-2" required><option value="">-- Pilih Tahun Tanam --</option><?php foreach ($ttList as $tt): ?><option value="<?= htmlspecialchars($tt) ?>"><?= htmlspecialchars($tt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm mb-1">Luas (Ha)</label><input type="number" step="0.01" id="luas_ha" name="luas_ha" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Invt Pokok</label><input type="number" id="jumlah_pohon" name="jumlah_pohon" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Anggaran (Kg)</label><input type="number" step="0.001" id="anggaran_kg" name="anggaran_kg" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Realisasi (Kg)</label><input type="number" step="0.001" id="realisasi_kg" name="realisasi_kg" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Jumlah Tandan</label><input type="number" id="jumlah_tandan" name="jumlah_tandan" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Jumlah HK</label><input type="number" step="0.001" id="jumlah_hk" name="jumlah_hk" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Panen (Ha)</label><input type="number" step="0.001" id="panen_ha" name="panen_ha" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Frekuensi</label><input type="number" step="0.001" id="frekuensi" name="frekuensi" class="w-full border rounded px-3 py-2"></div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded border text-gray-800 hover:bg-gray-50">Batal</button>
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  // --- MODIFIKASI: Kirim role ke JavaScript ---
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;

  const $=s=>document.querySelector(s);
  const tbody=$('#tbody-data'), fKebun=$('#filter-kebun'), fUnit=$('#filter-unit'), fBulan=$('#filter-bulan'), fTahun=$('#filter-tahun'), fTT=$('#filter-tt');
  const pageSizeSel=$('#page-size'), pagerEl=$('#pager'), rangeInfoEl=$('#range-info');
  const tot={luas:$('#tot-luas'), pokok:$('#tot-pokok'), anggaran:$('#tot-anggaran'), realisasi:$('#tot-realisasi'), tandan:$('#tot-tandan'), hk:$('#tot-hk'), panenha:$('#tot-panenha'), freq:$('#tot-freq')};
  const modal=$('#crud-modal'), form=$('#crud-form'), titleEl=$('#modal-title');
  const close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')}, open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')};

  $('#btn-add').addEventListener('click',()=>{
    form.reset(); $('#form-action').value='store'; $('#form-id').value=''; titleEl.textContent='Input LM-76';
    if(fKebun.value) $('#kebun_id').value=fKebun.value; if(fUnit.value) $('#unit_id').value=fUnit.value;
    if(fBulan.value) $('#bulan').value=fBulan.value; $('#tahun').value=fTahun.value;
    open();
  });
  $('#btn-close').addEventListener('click', close);
  $('#btn-cancel').addEventListener('click', close);

  let allData=[], currentPage=1;
  const fmt=n=>Number(n||0).toLocaleString(undefined,{maximumFractionDigits:3}), sum=(arr,key)=>arr.reduce((a,r)=>a+(parseFloat(r[key])||0),0);

  function buildRowHTML(r){
    const periode=`${r.bulan||'-'} ${r.tahun||'-'}`;
    // --- MODIFIKASI: Tambahkan atribut 'disabled' jika user adalah staf ---
    const disabledAttr = IS_STAF ? 'disabled' : '';
    return `
      <tr class="border-b hover:bg-gray-50">
        <td class="py-2 px-3">${r.tahun||'-'}</td><td class="py-2 px-3">${r.nama_kebun||'-'}</td>
        <td class="py-2 px-3">${r.nama_unit||'-'}</td><td class="py-2 px-3">${periode}</td>
        <td class="py-2 px-3">${r.tt||'-'}</td><td class="py-2 px-3 text-right">${fmt(r.luas_ha)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.jumlah_pohon)}</td><td class="py-2 px-3 text-right">${fmt(r.anggaran_kg)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.realisasi_kg)}</td><td class="py-2 px-3 text-right">${fmt(r.jumlah_tandan)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.jumlah_hk)}</td><td class="py-2 px-3 text-right">${fmt(r.panen_ha)}</td>
        <td class="py-2 px-3 text-right">${fmt(r.frekuensi)}</td>
        <td class="py-2 px-3">
          <div class="flex items-center gap-3">
            <button class="btn-edit text-blue-600" title="Edit" data-json="${encodeURIComponent(JSON.stringify(r))}" ${disabledAttr}>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.1 2.1 0 0 1 2.97 2.97l-9.9 9.9-4.2 1.23 1.23-4.2 9.9-9.9z" /></svg>
            </button>
            <button class="btn-del text-red-600" title="Hapus" data-id="${r.id}" ${disabledAttr}>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3m-9 0h12" /></svg>
            </button>
          </div>
        </td>
      </tr>`;
  }

  function renderTotals(){
    tot.luas.textContent=fmt(sum(allData,'luas_ha')); tot.pokok.textContent=fmt(sum(allData,'jumlah_pohon'));
    tot.anggaran.textContent=fmt(sum(allData,'anggaran_kg')); tot.realisasi.textContent=fmt(sum(allData,'realisasi_kg'));
    tot.tandan.textContent=fmt(sum(allData,'jumlah_tandan')); tot.hk.textContent=fmt(sum(allData,'jumlah_hk'));
    tot.panenha.textContent=fmt(sum(allData,'panen_ha'));
    const luas=sum(allData,'luas_ha'), panenha=sum(allData,'panen_ha');
    tot.freq.textContent=fmt(luas?(panenha/luas):0);
  }

  function renderTable(){
    const size=parseInt(pageSizeSel.value||'10',10), total=allData.length, totalPages=Math.max(1, Math.ceil(total/size));
    if(currentPage>totalPages)currentPage=totalPages;
    if(!total){
      tbody.innerHTML=`<tr><td colspan="14" class="text-center py-10 text-gray-500">Belum ada data.</td></tr>`;
      $('#pager').innerHTML=''; $('#range-info').textContent='';
      renderTotals(); return;
    }
    const start=(currentPage-1)*size, end=Math.min(start+size, total);
    tbody.innerHTML=allData.slice(start,end).map(buildRowHTML).join('');
    rangeInfoEl.textContent=`Menampilkan ${start+1}–${end} dari ${total} data`;
    const btn=(label,disabled,goto,active=false)=>`<button ${disabled?'disabled':''} data-goto="${goto}" class="px-3 py-1.5 border rounded text-sm ${disabled?'opacity-50 cursor-not-allowed':'hover:bg-gray-100'} ${active?'bg-gray-200 font-semibold':''}">${label}</button>`;
    let html=btn('« Prev', currentPage<=1, currentPage-1);
    const windowSize=5; let s=Math.max(1, currentPage-Math.floor(windowSize/2)), e=Math.min(totalPages, s+windowSize-1);
    if(e-s+1<windowSize) s=Math.max(1, e-windowSize+1);
    for(let p=s;p<=e;p++) html+=btn(p, false, p, p===currentPage);
    html+=btn('Next »', currentPage>=totalPages, currentPage+1);
    pagerEl.innerHTML=html;
    renderTotals();
  }

  function refresh(){
    const fd=new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); fd.append('action','list');
    fd.append('kebun_id',fKebun.value); fd.append('unit_id',fUnit.value);
    fd.append('bulan',fBulan.value); fd.append('tahun',fTahun.value); fd.append('tt',fTT.value);
    tbody.innerHTML=`<tr><td colspan="14" class="text-center py-10 text-gray-500">Memuat…</td></tr>`;
    fetch('lm76_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{allData=j.success&&Array.isArray(j.data)?j.data:[];currentPage=1;renderTable();})
      .catch(()=>{tbody.innerHTML=`<tr><td colspan="14" class="text-center py-10 text-red-500">Gagal memuat data</td></tr>`;});
  }
  refresh();
  [fKebun,fUnit,fBulan,fTahun,fTT].forEach(el=>el.addEventListener('change', ()=>{currentPage=1;refresh();}));
  pageSizeSel.addEventListener('change', ()=>{currentPage=1;renderTable();});

  pagerEl.addEventListener('click',(e)=>{
    const b=e.target.closest('button[data-goto]'); if(!b||b.disabled) return;
    currentPage=parseInt(b.dataset.goto,10); renderTable();
  });

  document.body.addEventListener('click',(e)=>{
    const t=e.target.closest('button'); if(!t)return;
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(t.classList.contains('btn-edit') && !IS_STAF){
      const row=JSON.parse(decodeURIComponent(t.dataset.json||''));
      form.reset(); $('#form-action').value='update'; $('#form-id').value=row.id; titleEl.textContent='Edit LM-76';
      const ids=['kebun_id','unit_id','bulan','tahun','tt','luas_ha','jumlah_pohon','anggaran_kg','realisasi_kg','jumlah_tandan','jumlah_hk','panen_ha','frekuensi'];
      ids.forEach(id=>{const el=$('#'+id);if(el&&row[id]!==undefined)el.value=row[id]??'';});
      open();
    }
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(t.classList.contains('btn-del') && !IS_STAF){
      const id=t.dataset.id;
      Swal.fire({title:'Hapus data ini?',text:'Tindakan ini tidak dapat dibatalkan.',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',cancelButtonColor:'#3085d6',confirmButtonText:'Ya, hapus',cancelButtonText:'Batal'})
      .then((res)=>{
        if(!res.isConfirmed)return;
        const fd=new FormData(); fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); fd.append('action','delete'); fd.append('id',id);
        fetch('lm76_crud.php',{method:'POST',body:fd})
          .then(r=>r.json()).then(j=>{if(j.success){Swal.fire('Terhapus!','Data berhasil dihapus.','success');refresh();}else Swal.fire('Gagal',j.message||'Tidak bisa menghapus','error');})
          .catch(err=>Swal.fire('Error',err?.message||'Network error','error'));
      });
    }
  });

  form.addEventListener('submit',(e)=>{
    e.preventDefault();
    const req=['unit_id','bulan','tahun','tt'];
    for(const id of req){const el=$('#'+id);if(!el||!el.value){Swal.fire('Validasi',`Field ${id.replace('_',' ')} wajib diisi.`,'warning');return;}}
    const fd=new FormData(form);
    fetch('lm76_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{if(j.success){close();Swal.fire({icon:'success',title:'Berhasil',text:j.message,timer:1400,showConfirmButton:false});refresh();}
        else{const html=j.errors?.length?`<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>`:(j.message||'Terjadi kesalahan');Swal.fire('Gagal',html,'error');}})
      .catch(err=>Swal.fire('Error',err?.message||'Network error','error'));
  });
});
</script>