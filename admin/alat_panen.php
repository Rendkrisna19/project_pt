<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

// --- Role user
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); 
$conn = $db->getConnection();

$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$kebun = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC")->fetchAll(PDO::FETCH_ASSOC);

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

$currentPage = 'alat_panen';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
  .btn-icon { background: transparent; border: none; padding: 0.25rem; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">Alat Panen</h1>
      <p class="text-gray-500">Kelola stok alat panen per kebun, unit &amp; periode</p>
    </div>
    <?php if (!$isStaf): ?>
      <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 flex items-center gap-2">
        <i class="ti ti-plus"></i>
        <span>Input Alat Panen</span>
      </button>
    <?php endif; ?>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm grid grid-cols-1 md:grid-cols-5 gap-3 items-start">
    <select id="f-kebun" class="border rounded px-3 py-2">
      <option value="">Semua Kebun</option>
      <?php foreach ($kebun as $k): ?>
        <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="f-unit" class="border rounded px-3 py-2">
      <option value="">Semua Unit</option>
      <?php foreach ($units as $u): ?>
        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
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
      <button id="btn-refresh" class="border rounded px-3 py-2 hover:bg-gray-50 flex items-center gap-2">
        <i class="ti ti-refresh"></i>
        <span>Refresh</span>
      </button>
      <button id="btn-export-excel" class="flex items-center gap-2 border px-3 py-2 rounded bg-white hover:bg-gray-50" title="Export Excel">
        <i class="ti ti-file-spreadsheet text-emerald-600"></i>
        <span>Excel</span>
      </button>
      <button id="btn-export-pdf" class="flex items-center gap-2 border px-3 py-2 rounded bg-white hover:bg-gray-50" title="Cetak PDF">
        <i class="ti ti-file-type-pdf text-red-600"></i>
        <span>PDF</span>
      </button>
    </div>
  </div>

  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
    <div class="text-sm text-gray-600" id="page-info">Menampilkan 0–0 dari 0 data</div>
    <div class="flex items-center gap-2">
      <label class="text-sm text-gray-700">Baris / halaman</label>
      <select id="per-page" class="border rounded-lg px-2 py-1">
        <option value="10">10</option>
        <option value="25" selected>25</option>
        <option value="50">50</option>
        <option value="100">100</option>
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
            <th class="py-3 px-4 text-left">Periode</th>
            <th class="py-3 px-4 text-left">Kebun</th>
            <th class="py-3 px-4 text-left">Unit/Devisi</th>
            <th class="py-3 px-4 text-left">Jenis Alat Panen</th>
            <th class="py-3 px-4 text-right">Stok Awal</th>
            <th class="py-3 px-4 text-right">Mutasi Masuk</th>
            <th class="py-3 px-4 text-right">Mutasi Keluar</th>
            <th class="py-3 px-4 text-right">Dipakai</th>
            <th class="py-3 px-4 text-right">Stok Akhir</th>
            <th class="py-3 px-4 text-left">Krani Afdeling</th>
            <th class="py-3 px-4 text-left">Catatan</th>
            <?php if (!$isStaf): ?>
              <th class="py-3 px-4 text-center">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="tbody-data">
          <tr><td colspan="<?= $isStaf ? 11 : 12 ?>" class="text-center py-10 text-gray-500">Memuat…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if (!$isStaf): ?>
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
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Unit/Devisi</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2" required>
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
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
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;
  const COLSPAN = IS_STAF ? 11 : 12;

  const $=s=>document.querySelector(s);
  const tbody=$('#tbody-data');
  const perSel=$('#per-page'), btnPrev=$('#btn-prev'), btnNext=$('#btn-next'), pageInfo=$('#page-info');
  let DATA_CACHE=[], CUR_PAGE=1;

  // Map nama bulan -> index
  const BULAN_INDEX = {
    "Januari":1,"Februari":2,"Maret":3,"April":4,"Mei":5,"Juni":6,
    "Juli":7,"Agustus":8,"September":9,"Oktober":10,"November":11,"Desember":12
  };

  // Parser tanggal/generator score untuk sorting "terbaru"
  function toTime(v){
    if(!v) return 0;
    const d = new Date(v);
    return isNaN(d.getTime()) ? 0 : d.getTime();
  }
  function monthScore(row){
    const m = BULAN_INDEX[String(row.bulan||'').trim()] || 0;
    const y = parseInt(row.tahun||0,10) || 0;
    // Score komposit (tahun dan bulan) untuk fallback
    return (y*100 + m);
  }

  // Sort terbaru: created_at desc -> updated_at desc -> (tahun,bulan) desc -> id desc
  function sortTerbaru(arr){
    return (arr||[]).slice().sort((a,b)=>{
      const ca = toTime(a.created_at || a.createdAt || a.created || null);
      const cb = toTime(b.created_at || b.createdAt || b.created || null);
      if(cb !== ca) return cb - ca;

      const ua = toTime(a.updated_at || a.updatedAt || null);
      const ub = toTime(b.updated_at || b.updatedAt || null);
      if(ub !== ua) return ub - ua;

      const ma = monthScore(a), mb = monthScore(b);
      if(mb !== ma) return mb - ma;

      const ia = parseInt(a.id||0,10), ib = parseInt(b.id||0,10);
      return ib - ia;
    });
  }

  function nf(n){ return Number(n||0).toLocaleString(); }

  function renderPage(){
    const total=DATA_CACHE.length, per=parseInt(perSel.value||'25',10), totalPages=Math.max(1, Math.ceil(total/per));
    CUR_PAGE=Math.min(Math.max(1, CUR_PAGE), totalPages);
    const start=(CUR_PAGE-1)*per, end=Math.min(start+per, total), rows=DATA_CACHE.slice(start, end);

    const emptyRow = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-500">Belum ada data.</td></tr>`;

    if(rows.length===0){
      tbody.innerHTML = emptyRow;
    } else {
      tbody.innerHTML = rows.map(x=>{
        const payload=encodeURIComponent(JSON.stringify(x));
        const actionCell = IS_STAF ? '' : `
          <td class="py-2 px-3">
            <div class="flex items-center justify-center gap-2">
              <button class="btn-edit btn-icon text-blue-600 hover:text-blue-800" data-json="${payload}" title="Edit">
                <i class="ti ti-pencil text-lg"></i>
              </button>
              <button class="btn-del btn-icon text-red-600 hover:text-red-800" data-id="${x.id}" title="Hapus">
                <i class="ti ti-trash text-lg"></i>
              </button>
            </div>
          </td>
        `;
        const periode = `${x.bulan} ${x.tahun}`;
        return `
          <tr class="border-b hover:bg-gray-50">
            <td class="py-2 px-3">${periode}</td>
            <td class="py-2 px-3">${x.nama_kebun || '-'}</td>
            <td class="py-2 px-3">${x.nama_unit || '-'}</td>
            <td class="py-2 px-3">${x.jenis_alat || '-'}</td>
            <td class="py-2 px-3 text-right">${nf(x.stok_awal)}</td>
            <td class="py-2 px-3 text-right">${nf(x.mutasi_masuk)}</td>
            <td class="py-2 px-3 text-right">${nf(x.mutasi_keluar)}</td>
            <td class="py-2 px-3 text-right">${nf(x.dipakai)}</td>
            <td class="py-2 px-3 text-right font-semibold">${nf(x.stok_akhir)}</td>
            <td class="py-2 px-3">${x.krani_afdeling||'-'}</td>
            <td class="py-2 px-3">${x.catatan||'-'}</td>
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
    fd.append('kebun_id',$('#f-kebun').value);
    fd.append('unit_id',$('#f-unit').value);
    fd.append('bulan',$('#f-bulan').value);
    fd.append('tahun',$('#f-tahun').value);
    // Hint ke backend agar urut terbaru:
    fd.append('order_by','created_at');
    fd.append('order_dir','desc');

    tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-500">Memuat…</td></tr>`;

    fetch('alat_panen_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(!j.success){
          DATA_CACHE=[];
          tbody.innerHTML=`<tr><td colspan="${COLSPAN}" class="text-center py-10 text-red-500">${j.message||'Error'}</td></tr>`;
        } else {
          const arr = Array.isArray(j.data)? j.data : [];
          // Client-side force: urut terbaru di atas
          DATA_CACHE = sortTerbaru(arr);
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

  $('#btn-refresh').addEventListener('click', refresh);
  ['f-kebun','f-unit','f-bulan','f-tahun'].forEach(id=>document.getElementById(id).addEventListener('change', refresh));
  perSel.addEventListener('change', ()=>{ CUR_PAGE=1; renderPage(); });
  btnPrev.addEventListener('click', ()=>{ if(CUR_PAGE > 1) { CUR_PAGE--; renderPage(); } });
  btnNext.addEventListener('click', ()=>{ CUR_PAGE++; renderPage(); });

  // CRUD (hanya non-staf)
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
      $('#form-action').value='store';
      $('#form-id').value='';
      // defaultkan bulan/tahun ke filter aktif biar konsisten
      const b = document.getElementById('f-bulan').value;
      const t = document.getElementById('f-tahun').value;
      if (b) document.getElementById('bulan').value = b;
      if (t) document.getElementById('tahun').value = t;
      calc(); openModal();
    });
    $('#btn-close').addEventListener('click',closeModal);
    $('#btn-cancel').addEventListener('click',closeModal);

    document.body.addEventListener('click',e=>{
      const btn = e.target.closest('button');
      if (!btn) return;
      
      if(btn.classList.contains('btn-edit')){
        const d=JSON.parse(decodeURIComponent(btn.dataset.json));
        form.reset();
        $('#form-action').value='update';
        $('#form-id').value=d.id;
        ['bulan','tahun','kebun_id','unit_id','jenis_alat','stok_awal','mutasi_masuk','mutasi_keluar','dipakai','stok_akhir','krani_afdeling','catatan'].forEach(k=>{
          if(document.getElementById(k)) document.getElementById(k).value = d[k] ?? '';
        });
        calc(); openModal();
      }
      
      if(btn.classList.contains('btn-del')){
        const id=btn.dataset.id;
        Swal.fire({title:'Hapus data?', text:'Data yang dihapus tidak dapat dikembalikan', icon:'warning', showCancelButton:true})
        .then(res=>{
          if(res.isConfirmed){
            const fd=new FormData();
            fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
            fd.append('action','delete');
            fd.append('id',id);
            fetch('alat_panen_crud.php',{method:'POST',body:fd})
              .then(r=>r.json()).then(j=>{
                if(j.success){ 
                  Swal.fire('Terhapus','', 'success'); 
                  refresh(); 
                } else {
                  Swal.fire('Gagal', j.message||'Error', 'error');
                }
              });
          }
        });
      }
    });

    form.addEventListener('submit',e=>{
      e.preventDefault();
      const need=['kebun_id','unit_id','bulan','tahun','jenis_alat'];
      for(const n of need){
        const el = document.getElementById(n);
        if(!el || !el.value){ 
          Swal.fire('Oops', 'Bulan/Tahun/Kebun/Unit dan Jenis Alat wajib diisi.', 'warning'); 
          return; 
        }
      }
      calc();
      const fd=new FormData(e.target);
      fetch('alat_panen_crud.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(j=>{
          if(j.success){
            closeModal(); 
            Swal.fire('Berhasil', j.message || 'Data tersimpan', 'success'); 
            // Setelah simpan, muat ulang dan tetap urutkan terbaru:
            refresh();
          } else {
            Swal.fire('Gagal', j.message||'Error', 'error');
          }
        })
        .catch(()=> Swal.fire('Gagal','Terjadi kesalahan jaringan','error'));
    });
  }

  // Export (semua role)
  document.getElementById('btn-export-excel').addEventListener('click', () => {
    const qs=new URLSearchParams({
      csrf_token:'<?= htmlspecialchars($CSRF) ?>',
      kebun_id:$('#f-kebun').value||'',
      unit_id:$('#f-unit').value||'',
      bulan:$('#f-bulan').value||'',
      tahun:$('#f-tahun').value||'',
      // hint urutan terbaru untuk cetak juga
      order_by:'created_at',
      order_dir:'desc'
    }).toString();
    window.open('cetak/alat_panen_export_excel.php?'+qs, '_blank');
  });
  document.getElementById('btn-export-pdf').addEventListener('click', () => {
    const qs=new URLSearchParams({
      csrf_token:'<?= htmlspecialchars($CSRF) ?>',
      kebun_id:$('#f-kebun').value||'',
      unit_id:$('#f-unit').value||'',
      bulan:$('#f-bulan').value||'',
      tahun:$('#f-tahun').value||'',
      order_by:'created_at',
      order_dir:'desc'
    }).toString();
    window.open('cetak/alat_panen_export_pdf.php?'+qs, '_blank');
  });
});
</script>
