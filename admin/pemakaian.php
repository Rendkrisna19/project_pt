<?php
// pemakaian.php (REVISI: pakai units/unit_id)
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

// ambil master units (untuk filter & form)
$units = [];
try {
  $units = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e){ $units = []; }

$currentPage = 'pemakaian';
include_once '../layouts/header.php';
?>
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-3xl font-bold text-gray-800">🧪 Pemakaian Bahan Kimia</h1>
      <p class="text-gray-500 mt-1">Kelola data pemakaian bahan kimia per Unit/Divisi</p>
    </div>

    <div class="flex gap-3">
      <!-- Export Excel -->
      <button id="btn-export-excel" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.3 1.7L7 12l-1.7 2.3h1.5l1-1.5 1 1.5h1.5L8.6 12l1.7-2.3H8.8L7.8 11 6.8 9.7H5.3z"/></svg>
        <span>Export Excel</span>
      </button>
      <!-- Cetak PDF -->
      <button id="btn-export-pdf" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/></svg>
        <span>Cetak PDF</span>
      </button>

      <button id="btn-add" class="bg-violet-600 text-white px-4 py-2 rounded-lg hover:bg-violet-700">+ Input Pemakaian</button>
    </div>
  </div>

  <!-- Filter & Pencarian -->
  <div class="bg-white p-4 rounded-xl shadow-sm">
    <div class="flex items-center gap-2 text-gray-600 mb-3"><span>🔎</span><span class="font-semibold">Filter & Pencarian</span></div>
    <div class="grid grid-cols-1 gap-4">
      <input id="filter-q" type="text" class="w-full border rounded-lg px-3 py-2" placeholder="Cari no dokumen, bahan, pekerjaan...">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <select id="filter-unit" class="w-full border rounded-lg px-3 py-2">
          <option value="">Semua Unit</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="filter-bulan" class="w-full border rounded-lg px-3 py-2">
          <option value="">Semua Bulan</option>
          <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
        </select>
        <select id="filter-tahun" class="w-full border rounded-lg px-3 py-2">
          <?php for ($y = $tahunNow-2; $y <= $tahunNow+2; $y++): ?>
            <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Tabel -->
  <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-gray-600">
          <th class="py-3 px-4 text-left">No. Dokumen</th>
          <th class="py-3 px-4 text-left">Unit</th>
          <th class="py-3 px-4 text-left">Periode</th>
          <th class="py-3 px-4 text-left">Nama Bahan</th>
          <th class="py-3 px-4 text-left">Jenis Pekerjaan</th>
          <th class="py-3 px-4 text-left">Jlh Bhn Diminta</th>
          <th class="py-3 px-4 text-left">Jumlah Fisik</th>
          <th class="py-3 px-4 text-left">Dokumen</th>
          <th class="py-3 px-4 text-left">Keterangan</th>
          <th class="py-3 px-4 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-data" class="text-gray-800">
        <tr><td colspan="10" class="text-center py-8 text-gray-500">Memuat data…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input Pemakaian Baru</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>

    <form id="crud-form" novalidate enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">No Dokumen</label>
          <input type="text" id="no_dokumen" name="no_dokumen" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Unit/Divisi</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2">
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select id="bulan" name="bulan" class="w-full border rounded px-3 py-2">
            <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Tahun</label>
          <select id="tahun" name="tahun" class="w-full border rounded px-3 py-2">
            <?php for ($y = $tahunNow-1; $y <= $tahunNow+3; $y++): ?>
              <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Nama Bahan</label>
          <input type="text" id="nama_bahan" name="nama_bahan" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Jenis Pekerjaan</label>
          <input type="text" id="jenis_pekerjaan" name="jenis_pekerjaan" class="w-full border rounded px-3 py-2" placeholder="Spraying, dll.">
        </div>
        <div>
          <label class="block text-sm mb-1">Jumlah Bahan Diminta</label>
          <input type="number" step="0.01" id="jlh_diminta" name="jlh_diminta" class="w-full border rounded px-3 py-2" min="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Jumlah Fisik</label>
          <input type="number" step="0.01" id="jlh_fisik" name="jlh_fisik" class="w-full border rounded px-3 py-2" min="0">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Dokumen Pendukung</label>
          <input type="file" id="dokumen" name="dokumen" class="w-full border rounded px-3 py-2" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Keterangan</label>
          <input type="text" id="keterangan" name="keterangan" class="w-full border rounded px-3 py-2">
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-violet-600 text-white hover:bg-violet-700">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data');
  const q = $('#filter-q');
  const selUnit = $('#filter-unit');
  const selBulan = $('#filter-bulan');
  const selTahun = $('#filter-tahun');

  const modal = $('#crud-modal');
  const btnAdd = $('#btn-add');
  const btnClose = $('#btn-close');
  const btnCancel = $('#btn-cancel');
  const form = $('#crud-form');
  const formAction = $('#form-action');
  const formId = $('#form-id');
  const title = $('#modal-title');

  const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }

  // LIST
  function refreshList() {
    const fd = new FormData();
    fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
    fd.append('action', 'list');
    fd.append('q', q.value);
    fd.append('unit_id', selUnit.value);
    fd.append('bulan', selBulan.value);
    fd.append('tahun', selTahun.value);

    fetch('pemakaian_crud.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        if (!j.success) {
          tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-red-500">${j.message||'Gagal memuat data'}</td></tr>`;
          return;
        }
        if (!j.data || j.data.length===0) {
          tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`;
          return;
        }
        tbody.innerHTML = j.data.map(row => `
          <tr class="border-b hover:bg-gray-50">
            <td class="py-3 px-4"><a href="#" class="font-semibold underline">${row.no_dokumen}</a></td>
            <td class="py-3 px-4">${row.unit_nama||'-'}</td>
            <td class="py-3 px-4">${row.bulan} ${row.tahun}</td>
            <td class="py-3 px-4">${row.nama_bahan||'-'}</td>
            <td class="py-3 px-4">${row.jenis_pekerjaan||'-'}</td>
            <td class="py-3 px-4">${Number(row.jlh_diminta).toLocaleString(undefined,{maximumFractionDigits:2})}</td>
            <td class="py-3 px-4">${Number(row.jlh_fisik).toLocaleString(undefined,{maximumFractionDigits:2})}</td>
            <td class="py-3 px-4">
              ${row.dokumen_path ? `<a href="${row.dokumen_path}" target="_blank" class="text-violet-600 underline">Lihat</a>` : 'N/A'}
            </td>
            <td class="py-3 px-4">${row.keterangan||'-'}</td>
            <td class="py-3 px-4">
              <div class="flex items-center gap-3">
                <button class="btn-edit text-blue-600 underline" data-json='${JSON.stringify(row)}'>Edit</button>
                <button class="btn-delete text-red-600 underline" data-id="${row.id}">Hapus</button>
              </div>
            </td>
          </tr>
        `).join('');
      })
      .catch(err => {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-red-500">${err?.message||'Network error'}</td></tr>`;
      });
  }
  refreshList();
  [q, selUnit, selBulan, selTahun].forEach(el => el.addEventListener('input', refreshList));
  selBulan.addEventListener('change', refreshList);

  // ADD
  btnAdd.addEventListener('click', () => {
    form.reset();
    formId.value = '';
    formAction.value = 'store';
    title.textContent = 'Input Pemakaian Baru';
    if (selUnit.value) document.getElementById('unit_id').value = selUnit.value;
    if (selBulan.value) document.getElementById('bulan').value = selBulan.value;
    document.getElementById('tahun').value = selTahun.value;
    open();
  });
  btnClose.addEventListener('click', close);
  btnCancel.addEventListener('click', close);

  // EDIT/DELETE
  document.body.addEventListener('click', (e) => {
    const t = e.target;

    if (t.classList.contains('btn-edit')) {
      const row = JSON.parse(t.dataset.json);
      form.reset();
      formAction.value = 'update';
      formId.value = row.id;
      title.textContent = 'Edit Pemakaian';

      ['no_dokumen','bulan','tahun','nama_bahan','jenis_pekerjaan','jlh_diminta','jlh_fisik','keterangan'].forEach(k=>{
        const el = document.getElementById(k);
        if (el) el.value = row[k] ?? '';
      });
      document.getElementById('unit_id').value = row.unit_id ?? '';
      open();
    }

    if (t.classList.contains('btn-delete')) {
      const id = t.dataset.id;
      Swal.fire({
        title: 'Hapus data ini?',
        text: 'Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal'
      }).then((res)=>{
        if (res.isConfirmed) {
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete'); fd.append('id', id);
          fetch('pemakaian_crud.php',{method:'POST',body:fd})
            .then(r=>r.json()).then(j=>{
              if (j.success){ Swal.fire('Terhapus!','Data berhasil dihapus.','success'); refreshList(); }
              else Swal.fire('Gagal', j.message||'Tidak bisa menghapus','error');
            }).catch(err=> Swal.fire('Error', err?.message||'Network error', 'error'));
        }
      });
    }
  });

  // SUBMIT
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const req = ['no_dokumen','unit_id','bulan','tahun','nama_bahan','jenis_pekerjaan'];
    for (const id of req) {
      const el = document.getElementById(id);
      if (!el || !el.value) { Swal.fire('Validasi', `Field ${id.replace('_',' ')} wajib diisi.`, 'warning'); return; }
    }

    const fd = new FormData(form); // include file
    fetch('pemakaian_crud.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        if (j.success) {
          modal.classList.add('hidden');
          Swal.fire({icon:'success', title:'Berhasil', text:j.message, timer:1400, showConfirmButton:false});
          refreshList();
        } else {
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
          Swal.fire('Gagal', html||'Terjadi kesalahan.', 'error');
        }
      })
      .catch(err => Swal.fire('Error', err?.message||'Network error', 'error'));
  });

  // Export Excel & PDF (ikut filter)
  document.getElementById('btn-export-excel').addEventListener('click', () => {
    const qs = new URLSearchParams({
      csrf_token: '<?= htmlspecialchars($CSRF) ?>',
      q: document.getElementById('filter-q').value || '',
      unit_id: document.getElementById('filter-unit').value || '',
      bulan: document.getElementById('filter-bulan').value || '',
      tahun: document.getElementById('filter-tahun').value || ''
    }).toString();
    window.open('cetak/pemakaian_export_excel.php?' + qs, '_blank');
  });

  document.getElementById('btn-export-pdf').addEventListener('click', () => {
    const qs = new URLSearchParams({
      csrf_token: '<?= htmlspecialchars($CSRF) ?>',
      q: document.getElementById('filter-q').value || '',
      unit_id: document.getElementById('filter-unit').value || '',
      bulan: document.getElementById('filter-bulan').value || '',
      tahun: document.getElementById('filter-tahun').value || ''
    }).toString();
    window.open('cetak/pemakaian_export_pdf.php?' + qs, '_blank');
  });
});
</script>
