<?php
// stok_gudang.php (FINAL+) — sticky header + scroll body + client-side pagination
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// ===== master untuk opsi =====
$kebun = $conn->query("SELECT id, kode, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$bahan = $conn->query("
  SELECT b.id, b.kode, b.nama_bahan, s.nama AS satuan
  FROM md_bahan_kimia b
  JOIN md_satuan s ON s.id=b.satuan_id
  ORDER BY b.nama_bahan
")->fetchAll(PDO::FETCH_ASSOC);

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');
$currentPage = 'stok_gudang';

include_once '../layouts/header.php';
?>
<style>
  /* Sticky header untuk thead */
  .table-sticky thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f9fafb; /* Tailwind bg-gray-50 */
    box-shadow: inset 0 -1px 0 rgba(0,0,0,0.06); /* border-b tipis */
  }
  /* Supaya scroll halus pada container tabel saja */
  .table-scroll {
    max-height: 60vh;         /* bisa kamu ubah sesuai kebutuhan */
    overflow: auto;
  }
  /* Hindari loncat saat scrollbar muncul */
  .scrollbar-gutter-stable {
    scrollbar-gutter: stable both-edges;
  }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-3xl font-bold text-gray-800">📦 Stok Gudang</h1>
      <p class="text-gray-500 mt-1">Rekap stok bahan kimia per kebun & periode</p>
    </div>
    <div class="flex gap-3">
      <button id="btn-export-excel" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.4 1.8L7.2 12l-1.8 2.2h1.6l1.1-1.5 1.1 1.5H11L9.2 12l1.8-2.2H9.4L8.3 11 7.2 9.8H5.4z"/></svg>
        <span>Export Excel</span>
      </button>
      <button id="btn-export-pdf" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/></svg>
        <span>Cetak PDF</span>
      </button>
      <button id="btn-add" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600">+ Input Stok</button>
    </div>
  </div>

  <!-- Filter -->
  <div class="bg-white p-4 rounded-xl shadow-sm">
    <div class="flex items-center gap-2 text-gray-600 mb-3"><span>🧰</span><span class="font-semibold">Filter Data</span></div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm text-gray-600 mb-1">Nama Kebun</label>
        <select id="filter-kebun" class="w-full border rounded-lg px-3 py-2">
          <option value="">Semua Kebun</option>
          <?php foreach($kebun as $k): ?>
            <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['kode'].' — '.$k['nama_kebun']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Nama Bahan Kimia</label>
        <select id="filter-bahan" class="w-full border rounded-lg px-3 py-2">
          <option value="">Semua Bahan</option>
          <?php foreach($bahan as $b): ?>
            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['kode'].' — '.$b['nama_bahan'].' ('.$b['satuan'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Bulan</label>
        <select id="filter-bulan" class="w-full border rounded-lg px-3 py-2">
          <option value="">Semua Bulan</option>
          <?php foreach ($bulanList as $bl): ?>
            <option value="<?= $bl ?>"><?= $bl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Tahun</label>
        <select id="filter-tahun" class="w-full border rounded-lg px-3 py-2">
          <?php for ($y=$tahunNow-3; $y<=$tahunNow+2; $y++): ?>
            <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Tabel (sticky header + scroll body) -->
  <div class="bg-white rounded-xl shadow-sm">
    <!-- Toolbar kecil untuk page size -->
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <div class="flex items-center gap-2 text-sm text-gray-600">
        <span>Tampilkan</span>
        <select id="page-size" class="border rounded px-2 py-1">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span>baris</span>
      </div>
      <div id="range-label" class="text-sm text-gray-500">—</div>
    </div>

    <div class="table-scroll scrollbar-gutter-stable">
      <table class="min-w-full text-sm table-sticky">
        <thead class="bg-gray-50">
          <tr class="text-gray-600">
            <th class="py-3 px-4 text-left">Kebun</th>
            <th class="py-3 px-4 text-left">Bahan (Satuan)</th>
            <th class="py-3 px-4 text-right">Stok Awal</th>
            <th class="py-3 px-4 text-right text-green-700">Mutasi Masuk</th>
            <th class="py-3 px-4 text-right text-red-700">Mutasi Keluar</th>
            <th class="py-3 px-4 text-right text-blue-700">Pasokan</th>
            <th class="py-3 px-4 text-right text-red-700">Dipakai</th>
            <th class="py-3 px-4 text-right">Net Mutasi</th>
            <th class="py-3 px-4 text-right font-semibold">Sisa Stok</th>
            <th class="py-3 px-4 text-left">Aksi</th>
          </tr>
        </thead>
        <tbody id="tbody-stok" class="text-gray-800">
          <tr><td colspan="10" class="text-center py-8 text-gray-500">Memuat data…</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex flex-col md:flex-row items-center justify-between gap-3 px-4 py-3 border-t">
      <div id="total-label" class="text-sm text-gray-600">—</div>
      <div class="flex items-center gap-1">
        <button id="btn-first" class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm">&laquo; First</button>
        <button id="btn-prev"  class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm">&lsaquo; Prev</button>
        <span id="page-info" class="px-3 text-sm text-gray-600">Page 1/1</span>
        <button id="btn-next"  class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm">Next &rsaquo;</button>
        <button id="btn-last"  class="px-3 py-1.5 border rounded hover:bg-gray-50 text-sm">Last &raquo;</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-3xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input Rekap Stok</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>

    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-1">
          <label class="block text-sm mb-1">Nama Kebun</label>
          <select name="kebun_id" id="kebun_id" class="w-full border rounded px-3 py-2" required>
            <option value="">— Pilih Kebun —</option>
            <?php foreach($kebun as $k): ?>
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['kode'].' — '.$k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Nama Bahan Kimia</label>
          <select name="bahan_id" id="bahan_id" class="w-full border rounded px-3 py-2" required>
            <option value="">— Pilih Bahan —</option>
            <?php foreach($bahan as $b): ?>
              <option value="<?= (int)$b['id'] ?>" data-satuan="<?= htmlspecialchars($b['satuan']) ?>">
                <?= htmlspecialchars($b['kode'].' — '.$b['nama_bahan'].' ('.$b['satuan'].')') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="text-xs text-gray-500 mt-1">Satuan: <span id="hint-satuan" class="font-semibold">-</span></p>
        </div>

        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select name="bulan" id="bulan" class="w-full border rounded px-3 py-2" required>
            <?php foreach ($bulanList as $bl): ?><option value="<?= $bl ?>"><?= $bl ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Tahun</label>
          <select name="tahun" id="tahun" class="w-full border rounded px-3 py-2" required>
            <?php for ($y = $tahunNow-1; $y <= $tahunNow+3; $y++): ?>
              <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Stok Awal</label>
          <input type="number" step="0.01" name="stok_awal" id="stok_awal" class="w-full border rounded px-3 py-2" min="0" value="0">
        </div>

        <div>
          <label class="block text-sm mb-1">Mutasi Masuk</label>
          <input type="number" step="0.01" name="mutasi_masuk" id="mutasi_masuk" class="w-full border rounded px-3 py-2" min="0" value="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Mutasi Keluar</label>
          <input type="number" step="0.01" name="mutasi_keluar" id="mutasi_keluar" class="w-full border rounded px-3 py-2" min="0" value="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Pasokan</label>
          <input type="number" step="0.01" name="pasokan" id="pasokan" class="w-full border rounded px-3 py-2" min="0" value="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Dipakai</label>
          <input type="number" step="0.01" name="dipakai" id="dipakai" class="w-full border rounded px-3 py-2" min="0" value="0">
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-orange-500 text-white hover:bg-orange-600">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);

  const tbody = $('#tbody-stok');

  const selKebun = $('#filter-kebun');
  const selBahan = $('#filter-bahan');
  const selBulan = $('#filter-bulan');
  const selTahun = $('#filter-tahun');

  const modal = $('#crud-modal');
  const btnAdd = $('#btn-add');
  const btnClose = $('#btn-close');
  const btnCancel = $('#btn-cancel');
  const form = $('#crud-form');
  const title = $('#modal-title');
  const formAction = $('#form-action');
  const formId = $('#form-id');

  // Pagination elements
  const pageSizeEl = $('#page-size');
  const rangeLabel = $('#range-label');
  const totalLabel = $('#total-label');
  const pageInfo   = $('#page-info');
  const btnFirst   = $('#btn-first');
  const btnPrev    = $('#btn-prev');
  const btnNext    = $('#btn-next');
  const btnLast    = $('#btn-last');

  const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }

  // ===== Client-side pagination state =====
  let allRows = [];          // cache hasil list dari server (sesuai filter)
  let currentPage = 1;       // mulai dari 1
  let pageSize = parseInt(pageSizeEl.value || '10', 10);

  function numberFmt(x){
    return Number(x ?? 0).toLocaleString(undefined,{maximumFractionDigits:2});
  }

  function renderPage(){
    const total = allRows.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * pageSize;
    const endIdx   = Math.min(startIdx + pageSize, total);
    const pageRows = allRows.slice(startIdx, endIdx);

    if (total === 0){
      tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`;
    } else {
      tbody.innerHTML = pageRows.map(row => `
        <tr class="border-b hover:bg-gray-50">
          <td class="py-3 px-4">
            <div class="font-semibold">${row.kebun_kode || ''}</div>
            <div class="text-xs text-gray-500">${row.nama_kebun || ''}</div>
          </td>
          <td class="py-3 px-4">
            <div class="font-semibold">${row.bahan_kode || ''}</div>
            <div class="text-xs text-gray-500">${row.nama_bahan || ''} (${row.satuan||''})</div>
          </td>
          <td class="py-3 px-4 text-right">${numberFmt(row.stok_awal)}</td>
          <td class="py-3 px-4 text-right text-green-700">${numberFmt(row.mutasi_masuk)}</td>
          <td class="py-3 px-4 text-right text-red-700">${numberFmt(row.mutasi_keluar)}</td>
          <td class="py-3 px-4 text-right text-blue-700">${numberFmt(row.pasokan)}</td>
          <td class="py-3 px-4 text-right text-red-700">${numberFmt(row.dipakai)}</td>
          <td class="py-3 px-4 text-right">${numberFmt(row.net_mutasi)}</td>
          <td class="py-3 px-4 text-right font-semibold">${numberFmt(row.sisa_stok)}</td>
          <td class="py-3 px-4">
            <div class="flex items-center gap-3">
              <button class="btn-edit text-blue-600 underline" data-json='${JSON.stringify(row)}'>✏️</button>
              <button class="btn-delete text-red-600 underline" data-id="${row.id}">🗑️</button>
            </div>
          </td>
        </tr>
      `).join('');
    }

    // Labels & controls
    const showFrom = total === 0 ? 0 : (startIdx + 1);
    const showTo   = endIdx;

    rangeLabel.textContent = `Menampilkan ${showFrom}–${showTo}`;
    totalLabel.textContent = `Total data: ${total}`;
    pageInfo.textContent   = `Page ${currentPage}/${totalPages}`;

    // Enable/disable
    btnFirst.disabled = (currentPage <= 1);
    btnPrev.disabled  = (currentPage <= 1);
    btnNext.disabled  = (currentPage >= totalPages);
    btnLast.disabled  = (currentPage >= totalPages);

    [btnFirst, btnPrev, btnNext, btnLast].forEach(b=>{
      b.classList.toggle('opacity-50', b.disabled);
      b.classList.toggle('cursor-not-allowed', b.disabled);
    });
  }

  function refreshList(){
    const fd = new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    if (selKebun.value) fd.append('kebun_id', selKebun.value);
    if (selBahan.value) fd.append('bahan_id', selBahan.value);
    if (selBulan.value) fd.append('bulan', selBulan.value);
    fd.append('tahun', selTahun.value || '<?= (int)date('Y') ?>');

    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-gray-500">Memuat data…</td></tr>`;

    fetch('stok_gudang_crud.php',{method:'POST', body:fd})
      .then(r=>r.json())
      .then(j=>{
        if (!j.success) {
          allRows = [];
          renderPage();
          tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-red-500">${j.message||'Gagal memuat data'}</td></tr>`;
          return;
        }
        allRows = Array.isArray(j.data) ? j.data : [];
        currentPage = 1; // reset ke halaman 1 setiap filter berubah
        renderPage();
      })
      .catch(err=>{
        allRows = [];
        renderPage();
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-red-500">${err?.message||'Network error'}</td></tr>`;
      });
  }

  // init load & filter events
  refreshList();
  [selKebun, selBahan, selBulan, selTahun].forEach(el=> el.addEventListener('change', refreshList));

  // page size change
  pageSizeEl.addEventListener('change', ()=>{
    pageSize = parseInt(pageSizeEl.value || '10', 10);
    currentPage = 1;
    renderPage();
  });

  // pagination buttons
  btnFirst.addEventListener('click', ()=>{ currentPage = 1; renderPage(); });
  btnPrev.addEventListener('click',  ()=>{ currentPage = Math.max(1, currentPage-1); renderPage(); });
  btnNext.addEventListener('click',  ()=>{ 
    const totalPages = Math.max(1, Math.ceil(allRows.length / pageSize));
    currentPage = Math.min(totalPages, currentPage+1); 
    renderPage(); 
  });
  btnLast.addEventListener('click',  ()=>{ 
    currentPage = Math.max(1, Math.ceil(allRows.length / pageSize)); 
    renderPage(); 
  });

  // set satuan hint saat pilih bahan
  function updateSatuanHint(){
    const opt = document.querySelector('#bahan_id option:checked');
    $('#hint-satuan').textContent = opt?.dataset?.satuan || '-';
  }
  document.getElementById('bahan_id')?.addEventListener('change', updateSatuanHint);

  // Add
  btnAdd.addEventListener('click', ()=>{
    form.reset();
    formId.value = '';
    formAction.value = 'store';
    title.textContent = 'Input Rekap Stok Baru';
    // Prefill dari filter aktif
    if (selKebun.value) document.getElementById('kebun_id').value = selKebun.value;
    if (selBahan.value) document.getElementById('bahan_id').value = selBahan.value;
    if (selBulan.value) document.getElementById('bulan').value = selBulan.value;
    document.getElementById('tahun').value = selTahun.value;
    updateSatuanHint();
    open();
  });
  btnClose.addEventListener('click', close);
  btnCancel.addEventListener('click', close);

  // Edit / Delete
  document.body.addEventListener('click', (e)=>{
    const t = e.target;
    if (t.classList.contains('btn-edit')) {
      const row = JSON.parse(t.dataset.json);
      form.reset();
      formAction.value='update';
      formId.value = row.id;
      title.textContent='Edit Rekap Stok';

      ['kebun_id','bahan_id','bulan','tahun','stok_awal','mutasi_masuk','mutasi_keluar','pasokan','dipakai'].forEach(k=>{
        if (document.getElementById(k)) document.getElementById(k).value = row[k] ?? '';
      });
      updateSatuanHint();
      open();
    }
    if (t.classList.contains('btn-delete')) {
      const id = t.dataset.id;
      Swal.fire({title:'Hapus data ini?',text:'Tindakan ini tidak dapat dibatalkan.',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, hapus',confirmButtonColor:'#d33'})
        .then(res=>{
          if (!res.isConfirmed) return;
          const fd = new FormData();
          fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete');
          fd.append('id', id);
          fetch('stok_gudang_crud.php',{method:'POST',body:fd})
            .then(r=>r.json())
            .then(j=>{
              if (j.success){ Swal.fire('Terhapus!', j.message, 'success'); refreshList(); }
              else Swal.fire('Gagal', j.message||'Tidak bisa menghapus','error');
            })
            .catch(err=> Swal.fire('Error', err?.message||'Network error','error'));
        });
    }
  });

  // Submit
  form.addEventListener('submit', (e)=>{
    e.preventDefault();
    const req = ['kebun_id','bahan_id','bulan','tahun'];
    for (const id of req){
      const el = document.getElementById(id);
      if (!el || !el.value){ Swal.fire('Validasi', `Field ${id.replace('_',' ')} wajib diisi.`, 'warning'); return; }
    }
    const fd = new FormData(form);
    fetch('stok_gudang_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        if (j.success){
          modal.classList.add('hidden');
          Swal.fire({icon:'success',title:'Berhasil',text:j.message,timer:1400,showConfirmButton:false});
          refreshList();
        } else {
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
          Swal.fire('Gagal', html || 'Terjadi kesalahan.', 'error');
        }
      })
      .catch(err=> Swal.fire('Error', err?.message||'Network error', 'error'));
  });

  // Export (bawa filter aktif)
  document.getElementById('btn-export-excel').addEventListener('click', ()=>{
    const qs = new URLSearchParams({
      csrf_token: '<?= htmlspecialchars($CSRF) ?>',
      kebun_id: selKebun.value || '',
      bahan_id: selBahan.value || '',
      bulan: selBulan.value || '',
      tahun: selTahun.value || ''
    }).toString();
    window.open('cetak/stok_gudang_export_excel.php?'+qs, '_blank');
  });
  document.getElementById('btn-export-pdf').addEventListener('click', ()=>{
    const qs = new URLSearchParams({
      csrf_token: '<?= htmlspecialchars($CSRF) ?>',
      kebun_id: selKebun.value || '',
      bahan_id: selBahan.value || '',
      bulan: selBulan.value || '',
      tahun: selTahun.value || ''
    }).toString();
    window.open('cetak/stok_gudang_export_pdf.php?'+qs, '_blank');
  });
});
</script>
