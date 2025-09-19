<?php
// stok_gudang.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}

// CSRF sederhana
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Data awal untuk filter
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');
$filter_bulan = $_GET['bulan'] ?? '';  // '' = semua
$filter_tahun = (int)($_GET['tahun'] ?? $tahunNow);
$filter_jenis = $_GET['jenis'] ?? '';  // '' = semua

// Ambil daftar distinct nama_bahan untuk filter
$opsiBahan = [];
try {
  $opsiBahan = $conn->query("SELECT DISTINCT nama_bahan, satuan FROM stok_gudang ORDER BY nama_bahan ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $opsiBahan = [];
}

$currentPage = 'stok_gudang';
include_once '../layouts/header.php';
?>
<!-- Pastikan SweetAlert2 terpasang di layout -->
<!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> -->

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-3xl font-bold text-gray-800">📦 Stok Gudang</h1>
      <p class="text-gray-500 mt-1">Kelola data rekapitulasi stok bahan di gudang</p>
    </div>
   <div class="flex gap-3">
  <!-- Export Excel -->
  <button id="btn-export-excel" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50">
    <!-- icon excel -->
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
      <path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
      <path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.4 1.8L7.2 12l-1.8 2.2h1.6l1.1-1.5 1.1 1.5H11L9.2 12l1.8-2.2H9.4L8.3 11 7.2 9.8H5.4z"/>
    </svg>
    <span>Export Excel</span>
  </button>

  <!-- Cetak PDF -->
  <button id="btn-export-pdf" class="flex items-center gap-2 border px-4 py-2 rounded-lg bg-white hover:bg-gray-50">
    <!-- icon pdf -->
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
      <path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/>
      <path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/>
    </svg>
    <span>Cetak PDF</span>
  </button>

  <!-- tombol input -->
  <button id="btn-add" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600">+ Input Stok</button>
</div>

  </div>

  <!-- Filter -->
  <div class="bg-white p-4 rounded-xl shadow-sm">
    <div class="flex items-center gap-2 text-gray-600 mb-3"><span>🧰</span><span class="font-semibold">Filter Data</span></div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm text-gray-600 mb-1">Jenis Bahan</label>
        <select id="filter-jenis" class="w-full border rounded-lg px-3 py-2">
          <option value="">Semua Jenis Bahan</option>
          <?php foreach ($opsiBahan as $b): ?>
            <option value="<?= htmlspecialchars($b['nama_bahan']) ?>"><?= htmlspecialchars($b['nama_bahan']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Bulan</label>
        <select id="filter-bulan" class="w-full border rounded-lg px-3 py-2">
          <option value="">Semua Bulan</option>
          <?php foreach ($bulanList as $b): ?>
            <option value="<?= $b ?>"><?= $b ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Tahun</label>
        <select id="filter-tahun" class="w-full border rounded-lg px-3 py-2">
          <?php for ($y = $tahunNow-3; $y <= $tahunNow+2; $y++): ?>
            <option value="<?= $y ?>" <?= $y===$filter_tahun?'selected':'' ?>><?= $y ?></option>
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
          <th class="py-3 px-4 text-left">Nama Bahan</th>
          <th class="py-3 px-4 text-left">Stok Awal</th>
          <th class="py-3 px-4 text-left">Masuk</th>
          <th class="py-3 px-4 text-left">Keluar</th>
          <th class="py-3 px-4 text-left">Pasokan</th>
          <th class="py-3 px-4 text-left">Dipakai</th>
          <th class="py-3 px-4 text-left">Sisa Stok</th>
          <th class="py-3 px-4 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-stok" class="text-gray-800">
        <tr><td colspan="8" class="text-center py-8 text-gray-500">Memuat data…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-3xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input Rekap Stok Baru</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>

    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-1">
          <label class="block text-sm mb-1">Jenis Bahan Kimia</label>
          <input type="text" name="nama_bahan" id="nama_bahan" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Satuan</label>
          <input type="text" name="satuan" id="satuan" class="w-full border rounded px-3 py-2" placeholder="ltr, kg">
        </div>
        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select name="bulan" id="bulan" class="w-full border rounded px-3 py-2">
            <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Tahun</label>
          <select name="tahun" id="tahun" class="w-full border rounded px-3 py-2">
            <?php for ($y = $tahunNow-1; $y <= $tahunNow+3; $y++): ?>
              <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Stok Awal</label>
          <input type="number" step="0.01" name="stok_awal" id="stok_awal" class="w-full border rounded px-3 py-2" min="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Mutasi Masuk</label>
          <input type="number" step="0.01" name="mutasi_masuk" id="mutasi_masuk" class="w-full border rounded px-3 py-2" min="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Mutasi Keluar</label>
          <input type="number" step="0.01" name="mutasi_keluar" id="mutasi_keluar" class="w-full border rounded px-3 py-2" min="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Pasokan</label>
          <input type="number" step="0.01" name="pasokan" id="pasokan" class="w-full border rounded px-3 py-2" min="0">
        </div>
        <div>
          <label class="block text-sm mb-1">Di Pakai</label>
          <input type="number" step="0.01" name="dipakai" id="dipakai" class="w-full border rounded px-3 py-2" min="0">
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);

  const tbody = $('#tbody-stok');
  const selJenis = $('#filter-jenis');
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

  const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }

  // ---- LIST (AJAX, realtime saat filter berubah)
  function refreshList() {
    const fd = new FormData();
    fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
    fd.append('action', 'list');
    fd.append('jenis', selJenis.value);
    fd.append('bulan', selBulan.value);
    fd.append('tahun', selTahun.value);

    fetch('stok_gudang_crud.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        if (!j.success) {
          tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-red-500">${j.message || 'Gagal memuat data'}</td></tr>`;
          return;
        }
        if (!j.data || j.data.length === 0) {
          tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`;
          return;
        }
        tbody.innerHTML = j.data.map(row => {
          const sisa = (parseFloat(row.stok_awal)||0) + (parseFloat(row.mutasi_masuk)||0) + (parseFloat(row.pasokan)||0)
                        - (parseFloat(row.mutasi_keluar)||0) - (parseFloat(row.dipakai)||0);
          const bold = 'class="font-semibold"';
          return `
            <tr class="border-b hover:bg-gray-50">
              <td class="py-3 px-4">
                <div class="font-semibold uppercase">${row.nama_bahan || '-'}</div>
                <div class="text-xs text-gray-500">${row.satuan || ''}</div>
              </td>
              <td class="py-3 px-4">${Number(row.stok_awal).toLocaleString(undefined,{maximumFractionDigits:2})}</td>
              <td class="py-3 px-4 text-green-600">${Number(row.mutasi_masuk).toLocaleString(undefined,{maximumFractionDigits:2})}</td>
              <td class="py-3 px-4 text-red-600">${Number(row.mutasi_keluar).toLocaleString(undefined,{maximumFractionDigits:2})}</td>
              <td class="py-3 px-4 text-blue-600">${Number(row.pasokan).toLocaleString(undefined,{maximumFractionDigits:2})}</td>
              <td class="py-3 px-4 text-red-600">${Number(row.dipakai).toLocaleString(undefined,{maximumFractionDigits:2})}</td>
              <td class="py-3 px-4 ${bold}">${Number(sisa).toLocaleString(undefined,{maximumFractionDigits:2})}</td>
              <td class="py-3 px-4">
                <div class="flex items-center gap-3">
                  <button class="btn-edit text-blue-600 underline" data-json='${JSON.stringify(row)}'>✏️</button>
                  <button class="btn-delete text-red-600 underline" data-id="${row.id}">🗑️</button>
                </div>
              </td>
            </tr>
          `;
        }).join('');
      })
      .catch(err => {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-red-500">${err?.message || 'Network error'}</td></tr>`;
      });
  }

  // Initial load + realtime filtering
  refreshList();
  [selJenis, selBulan, selTahun].forEach(el => el.addEventListener('change', refreshList));

  // ---- ADD
  btnAdd.addEventListener('click', () => {
    form.reset();
    formId.value = '';
    formAction.value = 'store';
    title.textContent = 'Input Rekap Stok Baru';
    // Prefill bulan/tahun sesuai filter sekarang
    if (selBulan.value) document.getElementById('bulan').value = selBulan.value;
    document.getElementById('tahun').value = selTahun.value;
    open();
  });
  btnClose.addEventListener('click', close);
  btnCancel.addEventListener('click', close);

  // ---- EDIT / DELETE
  document.body.addEventListener('click', (e) => {
    const t = e.target;

    if (t.classList.contains('btn-edit')) {
      const row = JSON.parse(t.dataset.json);
      form.reset();
      formAction.value = 'update';
      formId.value = row.id;
      title.textContent = 'Edit Rekap Stok';

      ['nama_bahan','satuan','bulan','tahun','stok_awal','mutasi_masuk','mutasi_keluar','pasokan','dipakai'].forEach(k => {
        if (document.getElementById(k)) document.getElementById(k).value = row[k] ?? '';
      });
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
      }).then((res) => {
        if (res.isConfirmed) {
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action', 'delete');
          fd.append('id', id);
          fetch('stok_gudang_crud.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
              if (j.success) { Swal.fire('Terhapus!', j.message, 'success'); refreshList(); }
              else Swal.fire('Gagal', j.message || 'Tidak bisa menghapus', 'error');
            })
            .catch(err => Swal.fire('Error', err?.message || 'Network error', 'error'));
        }
      });
    }
  });

  // ---- SUBMIT (store/update)
  form.addEventListener('submit', (e) => {
    e.preventDefault();

    // Validasi ringan
    const req = ['nama_bahan','satuan','bulan','tahun'];
    for (const id of req) {
      const el = document.getElementById(id);
      if (!el || !el.value) {
        Swal.fire('Validasi', `Field ${id.replace('_',' ')} wajib diisi.`, 'warning');
        return;
      }
    }

    const fd = new FormData(form);
    fetch('stok_gudang_crud.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        if (j.success) {
          modal.classList.add('hidden');
          Swal.fire({icon:'success', title:'Berhasil', text:j.message, timer:1400, showConfirmButton:false});
          refreshList(); // realtime update tabel
        } else {
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
          Swal.fire('Gagal', html || 'Terjadi kesalahan.', 'error');
        }
      })
      .catch(err => Swal.fire('Error', err?.message || 'Network error', 'error'));
  });

  // (Opsional) Ekspor
  document.getElementById('btn-export').addEventListener('click', () => {
    Swal.fire('Info', 'Fitur ekspor PDF belum diimplementasikan di contoh ini.', 'info');
  });
});

// Export Excel & PDF (bawa filter aktif via query string)
document.getElementById('btn-export-excel').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    jenis: document.getElementById('filter-jenis').value || '',
    bulan: document.getElementById('filter-bulan').value || '',
    tahun: document.getElementById('filter-tahun').value || ''
  }).toString();
  window.open('cetak/stok_gudang_export_excel.php?' + qs, '_blank');
});

document.getElementById('btn-export-pdf').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    jenis: document.getElementById('filter-jenis').value || '',
    bulan: document.getElementById('filter-bulan').value || '',
    tahun: document.getElementById('filter-tahun').value || ''
  }).toString();
  window.open('cetak/stok_gudang_export_pdf.php?' + qs, '_blank');
});

</script>
