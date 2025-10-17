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

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

$currentPage = 'alat_panen';
include_once '../layouts/header.php';
?>
<style>
  /* --- MODIFIKASI: Style untuk tombol disabled --- */
  button:disabled { opacity: 0.5; cursor: not-allowed !important; text-decoration: none !important; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">Alat Panen</h1>
      <p class="text-gray-500">Kelola stok alat panen per kebun, unit &amp; periode</p>
    </div>
    <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ Input Alat Panen</button>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm grid grid-cols-1 md:grid-cols-5 gap-3 items-start">
    <select id="f-kebun" class="border rounded px-3 py-2">
      <option value="">Semua Kebun</option>
      <?php foreach ($kebun as $k): ?>
        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="f-unit" class="border rounded px-3 py-2">
      <option value="">Semua Unit</option>
      <?php foreach ($units as $u): ?>
        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="f-bulan" class="border rounded px-3 py-2">
      <option value="">Semua Bulan</option>
      <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
    </select>
    <select id="f-tahun" class="border rounded px-3 py-2">
      <?php for ($y=$tahunNow-2; $y<=$tahunNow+2; $y++): ?>
        <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <div class="flex flex-wrap gap-2">
      <button id="btn-refresh" class="border rounded px-2 py-2 hover:bg-gray-50">Refresh</button>
      <button id="btn-export-excel" class="flex items-center gap-2 border px-3 py-2 rounded bg-white hover:bg-gray-50" title="Export Excel">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.3 1.7L7 12l-1.7 2.3h1.5l1-1.5 1 1.5h1.5L8.6 12l1.7-2.3H8.8L7.8 11 6.8 9.7H5.3z"/></svg>
        <span>Excel</span>
      </button>
      <button id="btn-export-pdf" class="flex items-center gap-2 border px-3 py-2 rounded bg-white hover:bg-gray-50" title="Cetak PDF">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/></svg>
        <span>PDF</span>
      </button>
    </div>
  </div>

  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
    <div class="text-sm text-gray-600" id="page-info">Menampilkan 0–0 dari 0 data</div>
    <div class="flex items-center gap-2">
      <label class="text-sm text-gray-700">Baris / halaman</label>
      <select id="per-page" class="border rounded-lg px-2 py-1">
        <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option>
      </select>
      <div class="inline-flex gap-2 ml-2">
        <button id="btn-prev" class="px-3 py-2 rounded-lg border text-gray-800 hover:bg-gray-50" disabled>Prev</button>
        <button id="btn-next" class="px-3 py-2 rounded-lg border text-gray-800 hover:bg-gray-50" disabled>Next</button>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="max-h-[70vh] overflow-y-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-green-500 sticky top-0 z-10">
          <tr class="text-gray-100">
            <th class="py-3 px-4 text-left">Periode</th><th class="py-3 px-4 text-left">Kebun</th>
            <th class="py-3 px-4 text-left">Unit/Devisi</th><th class="py-3 px-4 text-left">Jenis Alat Panen</th>
            <th class="py-3 px-4 text-right">Stok Awal</th><th class="py-3 px-4 text-right">Mutasi Masuk</th>
            <th class="py-3 px-4 text-right">Mutasi Keluar</th><th class="py-3 px-4 text-right">Dipakai</th>
            <th class="py-3 px-4 text-right">Stok Akhir</th><th class="py-3 px-4 text-left">Krani Afdeling</th>
            <th class="py-3 px-4 text-left">Catatan</th><th class="py-3 px-4 text-left">Aksi</th>
          </tr>
        </thead>
        <tbody id="tbody-data">
          <tr><td colspan="12" class="text-center py-10 text-gray-500">Memuat…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input Alat Panen</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action"><input type="hidden" name="id" id="form-id">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div><label class="block text-sm mb-1">Bulan</label><select id="bulan" name="bulan" class="w-full border rounded px-3 py-2" required><?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm mb-1">Tahun</label><select id="tahun" name="tahun" class="w-full border rounded px-3 py-2" required><?php for ($y=$tahunNow-1;$y<=$tahunNow+3;$y++): ?><option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></div>
        <div><label class="block text-sm mb-1">Kebun</label><select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2" required><option value="">-- Pilih Kebun --</option><?php foreach ($kebun as $k): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm mb-1">Unit/Devisi</label><select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2" required><option value="">-- Pilih Unit --</option><?php foreach ($units as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select></div>
        <div class="md:col-span-3"><label class="block text-sm mb-1">Jenis Alat Panen</label><input type="text" id="jenis_alat" name="jenis_alat" class="w-full border rounded px-3 py-2" placeholder="Contoh: Egrek, Dodos, Gancu" required></div>
        <div><label class="block text-sm mb-1">Stok Awal</label><input type="number" step="0.01" id="stok_awal" name="stok_awal" class="w-full border rounded px-3 py-2" value="0"></div>
        <div><label class="block text-sm mb-1">Mutasi Masuk</label><input type="number" step="0.01" id="mutasi_masuk" name="mutasi_masuk" class="w-full border rounded px-3 py-2" value="0"></div>
        <div><label class="block text-sm mb-1">Mutasi Keluar</label><input type="number" step="0.01" id="mutasi_keluar" name="mutasi_keluar" class="w-full border rounded px-3 py-2" value="0"></div>
        <div><label class="block text-sm mb-1">Dipakai</label><input type="number" step="0.01" id="dipakai" name="dipakai" class="w-full border rounded px-3 py-2" value="0"></div>
        <div><label class="block text-sm mb-1">Stok Akhir (auto)</label><input type="number" step="0.01" id="stok_akhir" name="stok_akhir" class="w-full border rounded px-3 py-2 bg-gray-50" readonly></div>
        <div class="md:col-span-2"><label class="block text-sm mb-1">Krani Afdeling</label><input type="text" id="krani_afdeling" name="krani_afdeling" class="w-full border rounded px-3 py-2" placeholder="Nama krani"></div>
        <div class="md:col-span-3"><label class="block text-sm mb-1">Catatan</label><input type="text" id="catatan" name="catatan" class="w-full border rounded px-3 py-2" placeholder="Catatan tambahan"></div>
      </div>
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
  // --- MODIFIKASI: Kirim role ke JavaScript ---
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;

  const $=s=>document.querySelector(s);
  const tbody=$('#tbody-data');
  const perSel=$('#per-page'), btnPrev=$('#btn-prev'), btnNext=$('#btn-next'), pageInfo=$('#page-info');
  let DATA_CACHE=[], CUR_PAGE=1;
  const modal=$('#crud-modal'), openModal=()=>{modal.classList.remove('hidden');modal.classList.add('flex')}, closeModal=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

  const calc=()=>{
    const sa=+($('#stok_awal').value||0), mi=+($('#mutasi_masuk').value||0), mk=+($('#mutasi_keluar').value||0), dp=+($('#dipakai').value||0);
    $('#stok_akhir').value = (sa+mi-mk-dp).toFixed(2);
  };
  ['stok_awal','mutasi_masuk','mutasi_keluar','dipakai'].forEach(id=>document.getElementById(id).addEventListener('input', calc));

  $('#btn-add').addEventListener('click',()=>{
    const f=document.getElementById('crud-form'); f.reset();
    document.getElementById('form-action').value='store';
    document.getElementById('form-id').value='';
    calc(); openModal();
  });
  $('#btn-close').addEventListener('click',closeModal);
  $('#btn-cancel').addEventListener('click',closeModal);
  $('#btn-refresh').addEventListener('click', refresh);
  ['f-kebun','f-unit','f-bulan','f-tahun'].forEach(id=>document.getElementById(id).addEventListener('change', refresh));

  function renderPage(){
    const total=DATA_CACHE.length, per=parseInt(perSel.value||'25',10), totalPages=Math.max(1, Math.ceil(total/per));
    CUR_PAGE=Math.min(Math.max(1, CUR_PAGE), totalPages);
    const start=(CUR_PAGE-1)*per, end=Math.min(start+per, total), rows=DATA_CACHE.slice(start, end);
    if(rows.length===0){ tbody.innerHTML = `<tr><td colspan="12" class="text-center py-10 text-gray-500">Belum ada data.</td></tr>`;
    }else{
      tbody.innerHTML = rows.map(x=>{
        const payload=encodeURIComponent(JSON.stringify(x)), nf=(n)=>Number(n||0).toLocaleString();
        // --- MODIFIKASI: Tambahkan atribut 'disabled' jika user adalah staf ---
        const disabledAttr = IS_STAF ? 'disabled' : '';
        return `
          <tr class="border-b hover:bg-gray-50">
            <td class="py-2 px-3">${x.bulan} ${x.tahun}</td>
            <td class="py-2 px-3">${x.nama_kebun || '-'}</td>
            <td class="py-2 px-3">${x.nama_unit}</td>
            <td class="py-2 px-3">${x.jenis_alat}</td>
            <td class="py-2 px-3 text-right">${nf(x.stok_awal)}</td>
            <td class="py-2 px-3 text-right">${nf(x.mutasi_masuk)}</td>
            <td class="py-2 px-3 text-right">${nf(x.mutasi_keluar)}</td>
            <td class="py-2 px-3 text-right">${nf(x.dipakai)}</td>
            <td class="py-2 px-3 text-right font-semibold">${nf(x.stok_akhir)}</td>
            <td class="py-2 px-3">${x.krani_afdeling||'-'}</td>
            <td class="py-2 px-3">${x.catatan||'-'}</td>
            <td class="py-2 px-3">
              <button class="text-blue-600 underline btn-edit" data-json="${payload}" ${disabledAttr}>Edit</button>
              <button class="text-red-600 underline btn-del" data-id="${x.id}" ${disabledAttr}>Hapus</button>
            </td>
          </tr>`;
      }).join('');
    }
    const from=total?start+1:0;
    pageInfo.textContent=`Menampilkan ${from}–${end} dari ${total} data`;
    btnPrev.disabled=CUR_PAGE<=1; btnNext.disabled=CUR_PAGE>=totalPages;
  }

  function refresh(){
    const fd=new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); fd.append('action','list');
    fd.append('kebun_id',$('#f-kebun').value); fd.append('unit_id',$('#f-unit').value);
    fd.append('bulan',$('#f-bulan').value); fd.append('tahun',$('#f-tahun').value);
    tbody.innerHTML = `<tr><td colspan="12" class="text-center py-10 text-gray-500">Memuat…</td></tr>`;
    fetch('alat_panen_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(!j.success){
          DATA_CACHE=[]; renderPage();
          tbody.innerHTML=`<tr><td colspan="12" class="text-center py-10 text-red-500">${j.message||'Error'}</td></tr>`;
          return;
        }
        DATA_CACHE=Array.isArray(j.data)? j.data : [];
        CUR_PAGE=1; renderPage();
      })
      .catch(()=>{
        DATA_CACHE=[]; renderPage();
        tbody.innerHTML=`<tr><td colspan="12" class="text-center py-10 text-red-500">Gagal memuat data.</td></tr>`;
      });
  }
  refresh();
  perSel.addEventListener('change', ()=>{ CUR_PAGE=1; renderPage(); });
  btnPrev.addEventListener('click', ()=>{ CUR_PAGE--; renderPage(); });
  btnNext.addEventListener('click', ()=>{ CUR_PAGE++; renderPage(); });

  document.body.addEventListener('click',e=>{
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(e.target.classList.contains('btn-edit') && !IS_STAF){
      const d=JSON.parse(decodeURIComponent(e.target.dataset.json));
      const f=document.getElementById('crud-form'); f.reset();
      document.getElementById('form-action').value='update';
      document.getElementById('form-id').value=d.id;
      ['bulan','tahun','kebun_id','unit_id','jenis_alat','stok_awal','mutasi_masuk','mutasi_keluar','dipakai','stok_akhir','krani_afdeling','catatan'].forEach(k=>{
        if(document.getElementById(k)) document.getElementById(k).value = d[k] ?? '';
      });
      calc(); openModal();
    }
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(e.target.classList.contains('btn-del') && !IS_STAF){
      const id=e.target.dataset.id;
      Swal.fire({title:'Hapus data?', text:'Data yang dihapus tidak dapat dikembalikan', icon:'warning', showCancelButton:true})
      .then(res=>{
        if(res.isConfirmed){
          const fd=new FormData();
          fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); fd.append('action','delete'); fd.append('id',id);
          fetch('alat_panen_crud.php',{method:'POST',body:fd})
            .then(r=>r.json()).then(j=>{
              if(j.success){ Swal.fire('Terhapus','', 'success'); refresh(); }
              else Swal.fire('Gagal', j.message||'Error', 'error');
            });
        }
      });
    }
  });

  document.getElementById('crud-form').addEventListener('submit',e=>{
    e.preventDefault();
    const need=['kebun_id','unit_id','bulan','tahun','jenis_alat'];
    for(const n of need){ if(!document.getElementById(n).value){ Swal.fire('Oops', 'Bulan/Tahun/Kebun/Unit dan Jenis Alat wajib diisi.', 'warning'); return; } }
    calc();
    const fd=new FormData(e.target);
    fetch('alat_panen_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(j.success){ closeModal(); Swal.fire('Berhasil', j.message, 'success'); refresh(); }
        else Swal.fire('Gagal', j.message||'Error', 'error');
      })
      .catch(()=> Swal.fire('Gagal','Terjadi kesalahan jaringan','error'));
  });
});

document.getElementById('btn-export-excel').addEventListener('click', () => {
  const qs=new URLSearchParams({
    csrf_token:'<?= htmlspecialchars($CSRF) ?>',kebun_id:document.getElementById('f-kebun').value||'',
    unit_id:document.getElementById('f-unit').value||'',bulan:document.getElementById('f-bulan').value||'',
    tahun:document.getElementById('f-tahun').value||''
  }).toString();
  window.open('cetak/alat_panen_export_excel.php?'+qs, '_blank');
});
document.getElementById('btn-export-pdf').addEventListener('click', () => {
  const qs=new URLSearchParams({
    csrf_token:'<?= htmlspecialchars($CSRF) ?>',kebun_id:document.getElementById('f-kebun').value||'',
    unit_id:document.getElementById('f-unit').value||'',bulan:document.getElementById('f-bulan').value||'',
    tahun:document.getElementById('f-tahun').value||''
  }).toString();
  window.open('cetak/alat_panen_export_pdf.php?'+qs, '_blank');
});
</script>