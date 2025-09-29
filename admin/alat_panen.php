<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
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
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">Alat Panen</h1>
      <p class="text-gray-500">Kelola stok alat panen per kebun, unit & periode</p>
    </div>
    <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ Input Alat Panen</button>
  </div>

 <!-- Filter -->
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

    <!-- Export Excel -->
    <button id="btn-export-excel" class="flex items-center gap-2 border px-3 py-2 rounded bg-white hover:bg-gray-50" title="Export Excel">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
        <path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
        <path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.3 1.7L7 12l-1.7 2.3h1.5l1-1.5 1 1.5h1.5L8.6 12l1.7-2.3H8.8L7.8 11 6.8 9.7H5.3z"/>
      </svg>
      <span>Excel</span>
    </button>

    <!-- Cetak PDF -->
    <button id="btn-export-pdf" class="flex items-center gap-2 border px-3 py-2 rounded bg-white hover:bg-gray-50" title="Cetak PDF">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
        <path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/>
        <path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/>
      </svg>
      <span>PDF</span>
    </button>
  </div>
</div>

  <!-- Tabel -->
  <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-gray-600">
          <th class="py-3 px-4 text-left">Periode</th>
          <th class="py-3 px-4 text-left">Kebun</th>
          <th class="py-3 px-4 text-left">Unit/Devisi</th>
          <th class="py-3 px-4 text-left">Jenis Alat Panen</th>
          <th class="py-3 px-4 text-left">Stok Awal</th>
          <th class="py-3 px-4 text-left">Mutasi Masuk</th>
          <th class="py-3 px-4 text-left">Mutasi Keluar</th>
          <th class="py-3 px-4 text-left">Dipakai</th>
          <th class="py-3 px-4 text-left">Stok Akhir</th>
          <th class="py-3 px-4 text-left">Krani Afdeling</th>
          <th class="py-3 px-4 text-left">Catatan</th>
          <th class="py-3 px-4 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-data">
        <tr><td colspan="12" class="text-center py-10 text-gray-500">Memuat…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input Alat Panen</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>

    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select id="bulan" name="bulan" class="w-full border rounded px-3 py-2" required>
            <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
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
          <label class="block text-sm mb-1">Kebun</label>
          <select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2" required>
            <option value="">-- Pilih Kebun --</option>
            <?php foreach ($kebun as $k): ?>
              <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm mb-1">Unit/Devisi</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2" required>
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-3">
          <label class="block text-sm mb-1">Jenis Alat Panen</label>
          <input type="text" id="jenis_alat" name="jenis_alat" class="w-full border rounded px-3 py-2" placeholder="Contoh: Egrek, Dodos, Gancu" required>
        </div>

        <div>
          <label class="block text-sm mb-1">Stok Awal</label>
          <input type="number" step="0.01" id="stok_awal" name="stok_awal" class="w-full border rounded px-3 py-2" value="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Mutasi Masuk</label>
          <input type="number" step="0.01" id="mutasi_masuk" name="mutasi_masuk" class="w-full border rounded px-3 py-2" value="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Mutasi Keluar</label>
          <input type="number" step="0.01" id="mutasi_keluar" name="mutasi_keluar" class="w-full border rounded px-3 py-2" value="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Dipakai</label>
          <input type="number" step="0.01" id="dipakai" name="dipakai" class="w-full border rounded px-3 py-2" value="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Stok Akhir (auto)</label>
          <input type="number" step="0.01" id="stok_akhir" name="stok_akhir" class="w-full border rounded px-3 py-2 bg-gray-50" readonly>
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Krani Afdeling</label>
          <input type="text" id="krani_afdeling" name="krani_afdeling" class="w-full border rounded px-3 py-2" placeholder="Nama krani">
        </div>
        <div class="md:col-span-3">
          <label class="block text-sm mb-1">Catatan</label>
          <input type="text" id="catatan" name="catatan" class="w-full border rounded px-3 py-2" placeholder="Catatan tambahan">
        </div>
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
  const $=s=>document.querySelector(s);
  const tbody=$('#tbody-data');

  const modal=$('#crud-modal');
  const open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')};
  const close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

  // hitung stok akhir realtime
  const calc = ()=>{
    const sa=+($('#stok_awal').value||0);
    const mi=+($('#mutasi_masuk').value||0);
    const mk=+($('#mutasi_keluar').value||0);
    const dp=+($('#dipakai').value||0);
    $('#stok_akhir').value = (sa+mi-mk-dp).toFixed(2);
  };
  ['stok_awal','mutasi_masuk','mutasi_keluar','dipakai'].forEach(id=>{
    document.getElementById(id).addEventListener('input', calc);
  });

  // buttons
  $('#btn-add').addEventListener('click',()=>{document.getElementById('crud-form').reset(); document.getElementById('form-action').value='store'; document.getElementById('form-id').value=''; calc(); open();});
  $('#btn-close').addEventListener('click',close); $('#btn-cancel').addEventListener('click',close);
  $('#btn-refresh').addEventListener('click', refresh);

  // filter
  ['f-kebun','f-unit','f-bulan','f-tahun'].forEach(id=>document.getElementById(id).addEventListener('change', refresh));

  function refresh(){
    const fd=new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    fd.append('kebun_id',$('#f-kebun').value);
    fd.append('unit_id',$('#f-unit').value);
    fd.append('bulan',$('#f-bulan').value);
    fd.append('tahun',$('#f-tahun').value);

    fetch('alat_panen_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(!j.success){ tbody.innerHTML=`<tr><td colspan="12" class="text-center py-8 text-red-500">${j.message||'Error'}</td></tr>`; return; }
        if(!j.data.length){ tbody.innerHTML=`<tr><td colspan="12" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`; return; }
        tbody.innerHTML = j.data.map(x=>`
          <tr class="border-b hover:bg-gray-50">
            <td class="py-2 px-3">${x.bulan} ${x.tahun}</td>
            <td class="py-2 px-3">${x.nama_kebun || '-'}</td>
            <td class="py-2 px-3">${x.nama_unit}</td>
            <td class="py-2 px-3">${x.jenis_alat}</td>
            <td class="py-2 px-3">${(+x.stok_awal).toLocaleString()}</td>
            <td class="py-2 px-3">${(+x.mutasi_masuk).toLocaleString()}</td>
            <td class="py-2 px-3">${(+x.mutasi_keluar).toLocaleString()}</td>
            <td class="py-2 px-3">${(+x.dipakai).toLocaleString()}</td>
            <td class="py-2 px-3 font-semibold">${(+x.stok_akhir).toLocaleString()}</td>
            <td class="py-2 px-3">${x.krani_afdeling||'-'}</td>
            <td class="py-2 px-3">${x.catatan||'-'}</td>
            <td class="py-2 px-3">
              <button class="text-blue-600 underline btn-edit" data-json='${JSON.stringify(x)}'>Edit</button>
              <button class="text-red-600 underline btn-del" data-id="${x.id}">Hapus</button>
            </td>
          </tr>`).join('');
      })
      .catch(()=> tbody.innerHTML=`<tr><td colspan="12" class="text-center py-8 text-red-500">Gagal memuat data.</td></tr>`);
  }
  refresh();

  document.body.addEventListener('click',e=>{
    if(e.target.classList.contains('btn-edit')){
      const d=JSON.parse(e.target.dataset.json);
      const form=document.getElementById('crud-form'); form.reset();
      document.getElementById('form-action').value='update'; document.getElementById('form-id').value=d.id;
      ['bulan','tahun','kebun_id','unit_id','jenis_alat','stok_awal','mutasi_masuk','mutasi_keluar','dipakai','stok_akhir','krani_afdeling','catatan'].forEach(k=>{
        if(document.getElementById(k)) document.getElementById(k).value = d[k] ?? '';
      });
      calc(); open();
    }
    if(e.target.classList.contains('btn-del')){
      const id=e.target.dataset.id;
      Swal.fire({title:'Hapus data?', text:'Data yang dihapus tidak dapat dikembalikan', icon:'warning', showCancelButton:true})
      .then(res=>{
        if(res.isConfirmed){
          const fd=new FormData();
          fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete'); fd.append('id',id);
          fetch('alat_panen_crud.php',{method:'POST',body:fd})
            .then(r=>r.json()).then(j=>{
              if(j.success){ Swal.fire('Terhapus','', 'success'); refresh(); }
              else Swal.fire('Gagal', j.message||'Error', 'error');
            });
        }
      });
    }
  });

  // submit
  document.getElementById('crud-form').addEventListener('submit',e=>{
    e.preventDefault();
    if(!$('#kebun_id').value || !$('#unit_id').value || !$('#bulan').value || !$('#tahun').value || !$('#jenis_alat').value){
      Swal.fire('Oops', 'Bulan/Tahun/Kebun/Unit dan Jenis Alat wajib diisi.', 'warning'); return;
    }
    calc();
    const fd=new FormData(e.target);
    fetch('alat_panen_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(j.success){ close(); Swal.fire('Berhasil', j.message, 'success'); refresh(); }
        else Swal.fire('Gagal', j.message||'Error', 'error');
      })
      .catch(()=> Swal.fire('Gagal','Terjadi kesalahan jaringan','error'));
  });
});

// === Export Excel & PDF (bawa filter aktif, termasuk kebun) ===
document.getElementById('btn-export-excel').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    kebun_id: document.getElementById('f-kebun').value || '',
    unit_id: document.getElementById('f-unit').value || '',
    bulan: document.getElementById('f-bulan').value || '',
    tahun: document.getElementById('f-tahun').value || ''
  }).toString();
  window.open('cetak/alat_panen_export_excel.php?' + qs, '_blank');
});

document.getElementById('btn-export-pdf').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    kebun_id: document.getElementById('f-kebun').value || '',
    unit_id: document.getElementById('f-unit').value || '',
    bulan: document.getElementById('f-bulan').value || '',
    tahun: document.getElementById('f-tahun').value || ''
  }).toString();
  window.open('cetak/alat_panen_export_pdf.php?' + qs, '_blank');
});
</script>
