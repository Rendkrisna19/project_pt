<?php
// CATATAN: File ini sudah benar. 
// Nama kebun tidak muncul karena backend 'laporan_mingguan_crud.php' 
// perlu di-JOIN dengan tabel 'md_kebun' untuk mengirim 'kebun_nama'.

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php"); exit;
}

// ==========================================================
// MODIFIKASI: Ambil Kategori ID & Nama
// ==========================================================
$kategori_id = (int)($_GET['k_id'] ?? 0);
if ($kategori_id === 0) {
    header("Location: laporan_mingguan.php"); // Kembali ke halaman folder
    exit;
}

require_once '../config/database.php';
$db_temp = new Database();
$conn_temp = $db_temp->getConnection();
$stmt_kat = $conn_temp->prepare("SELECT nama_kategori FROM arsip_kategori WHERE id = ?");
$stmt_kat->execute([$kategori_id]);
$kategori_nama = $stmt_kat->fetchColumn();
if (!$kategori_nama) {
    // Jika Kategori ID tidak ditemukan
    header("Location: laporan_mingguan.php"); 
    exit;
}
// ==========================================================

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// Role
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

// Koneksi utama (bisa pakai $conn_temp juga)
$db   = $db_temp;
$conn = $conn_temp;

$tahunNow   = (int)date('Y');

// List kebun untuk form modal
$kebunList = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")
                   ->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'laporan_mingguan'; // Tetap aktifkan menu yang sama
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
  .table-sticky thead th { position: sticky; top: 0; z-index: 10; }
  .btn-icon { background: transparent; border: none; padding: .25rem; }
  button:disabled { opacity: .5; cursor: not-allowed !important; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">
            <a href="laporan_mingguan.php" class="text-emerald-700 hover:underline">🗂️ Arsip</a> / 
            <?= htmlspecialchars($kategori_nama) ?>
        </h1>
        <p class="text-gray-500 mt-1">Kelola data laporan untuk kategori <?= htmlspecialchars($kategori_nama) ?></p>
    </div>

    <div class="flex gap-3">
      <?php if (!$isStaf): ?>
      <button id="btn-add" class="bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 flex items-center gap-2">
        <i class="ti ti-plus"></i>
        <span>Input Laporan</span>
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm border">
    <div class="flex items-center gap-2 text-gray-700 mb-3">
      <span>🧭</span><span class="font-semibold">Filter & Pencarian</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="filter-year" class="block text-sm text-gray-600 mb-1">Tahun</label>
        <select id="filter-year" class="w-full border rounded-lg px-3 py-2 text-gray-800">
          <option value="all" selected>Semua Tahun</option>
         <?php for ($y = 2055; $y >= 2020; $y--): ?>
      <option value="<?= $y ?>" <?= $y === (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option>
    <?php endfor; ?>
        </select>
      </div>
      <div>
        <label for="filter-q" class="block text-sm text-gray-600 mb-1">Pencarian (uraian, link, kebun)</label>
        <input id="filter-q" type="text" class="w-full border rounded-lg px-3 py-2 text-gray-800" placeholder="Ketik untuk mencari...">
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm border">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 p-3">
      <div class="text-sm text-gray-700">
        Menampilkan <span id="info-from" class="font-semibold">0</span>–<span id="info-to" class="font-semibold">0</span>
        dari <span id="info-total" class="font-semibold">0</span> data
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-700">Baris per halaman</label>
        <select id="per-page" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-800">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>

    <div class="overflow-x-auto">
      <div class="max-h-[60vh] overflow-y-auto">
        <table class="min-w-full text-sm table-sticky">
          <thead class="bg-emerald-600 text-white">
            <tr>
              <th class="py-3 px-4 text-left">Tahun</th>
              <th class="py-3 px-4 text-left">Kebun</th>
              <th class="py-3 px-4 text-left">Uraian</th>
              <th class="py-3 px-4 text-left">Link Dokumen</th>
              <th class="py-3 px-4 text-left">File Upload</th>
              <?php if (!$isStaf): ?>
                <th class="py-3 px-4 text-center">Aksi</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody id="tbody-data" class="text-gray-800">
            <tr><td colspan="<?= $isStaf ? 5 : 6 ?>" class="text-center py-8 text-gray-500">Memuat data…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 p-3">
      <div class="text-sm text-gray-700">Halaman <span id="page-now" class="font-semibold">1</span> dari <span id="page-total" class="font-semibold">1</span></div>
      <div class="inline-flex gap-2">
        <button id="btn-prev" class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-gray-800" disabled>Prev</button>
        <button id="btn-next" class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-gray-800" disabled>Next</button>
      </div>
    </div>
  </div>
</div>

<?php if (!$isStaf): ?>
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-2xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-900">Input Laporan Baru</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800" aria-label="Tutup">&times;</button>
    </div>
    <form id="crud-form" novalidate enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      
      <input type="hidden" name="kategori_id" id="form-kategori-id" value="<?= $kategori_id ?>">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">1. Tahun</label>
          <?php $start = 2020; $end = 2055; ?>
<select id="tahun" name="tahun" class="w-full border rounded px-3 py-2 text-gray-800">
  <?php for ($y = $start; $y <= $end; $y++): ?>
    <option value="<?= $y ?>" <?= ($y === (int)date('Y')) ? 'selected' : '' ?>><?= $y ?></option>
  <?php endfor; ?>
</select>

        </div>
        <div>
          <label class="block text-sm mb-1">Kebun</label>
          <select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2 text-gray-800">
            <option value="">-- Pilih Kebun --</option>
            <?php foreach ($kebunList as $k): ?>
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mt-4">
        <label class="block text-sm mb-1">2. Uraian</label>
        <input type="text" id="uraian" name="uraian" class="w-full border rounded px-3 py-2 text-gray-800" placeholder="Tuliskan uraian pekerjaan / ringkasan laporan" maxlength="255">
      </div>

      <div class="mt-4">
        <label class="block text-sm mb-1">3. Upload Dokumen (Opsional)</label>
        <input type="file" id="upload_dokumen" name="upload_dokumen" class="w-full border rounded px-3 py-2 text-gray-800" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
        <div id="file-link-current" class="text-sm mt-2"></div>
      </div>

      <div class="mt-4">
        <label class="block text-sm mb-1">4. Link Dokumen (Opsional)</label>
        <input type="url" id="link_dokumen" name="link_dokumen" class="w-full border rounded px-3 py-2 text-gray-800" placeholder="https://docs.google.com/...">
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-700 text-white hover:bg-emerald-800">Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const IS_STAF    = <?= $isStaf ? 'true' : 'false'; ?>;
    const COLSPAN    = IS_STAF ? 5 : 6;
    const CSRF_TOKEN = '<?= htmlspecialchars($CSRF) ?>';
    
    // ==========================================================
    // MODIFIKASI: Ambil Kategori ID untuk dikirim ke API
    // ==========================================================
    const KATEGORI_ID = <?= $kategori_id ?>;
    
    const $ = s => document.querySelector(s);
    const tbody = $('#tbody-data');
    const q = $('#filter-q');
    const yearEl = $('#filter-year');

    const perPageEl = $('#per-page');
    const btnPrev = $('#btn-prev');
    const btnNext = $('#btn-next');
    const infoFrom = $('#info-from');
    const infoTo   = $('#info-to');
    const infoTotal= $('#info-total');
    const pageNow   = $('#page-now');
    const pageTotal= $('#page-total');

    let ALL_ROWS = [];
    let CURRENT_PAGE = 1;
    let PER_PAGE = parseInt(perPageEl.value, 10) || 10;

    // ==========================================================
    // API_URL menunjuk ke backend FILE
    // ==========================================================
    const API_URL = 'laporan_mingguan_crud.php';

    /* ROOT DETECTOR (stabil) */
    const APP_ROOT = (() => {
      const path = location.pathname;
      const adminIndex = path.indexOf('/admin/');
      if (adminIndex > -1) return path.substring(0, adminIndex);
      if (adminIndex === 0) return "";
      const segs = path.split('/').filter(Boolean);
      segs.pop();
      if (segs.length > 0 && segs[segs.length - 1] === 'admin') segs.pop();
      return segs.length > 0 ? '/' + segs.join('/') : '';
    })();

    function encodeLastSegment(path) {
      try {
        const parts = path.split('/');
        const file = parts.pop();
        parts.push(encodeURIComponent(file));
        return parts.join('/');
      } catch { return path; }
    }

    function normalizeUploadPath(p) {
      if (!p) return '';
      if (/^https?:\/\//i.test(p)) return p;
      p = p.replace(/^(\.\.\/)+/, '').replace(/^\.?\//, '').replace(/^admin\//, '');
      if (!p.startsWith('uploads/')) p = 'uploads/' + p;
      p = encodeLastSegment(p);
      const root = APP_ROOT ? APP_ROOT : '';
      return root + '/' + p.replace(/^\/+/, '');
    }

    function buildRowHTML(row) {
      const externalLink = row.link_dokumen
        ? `<a href="${row.link_dokumen}" target="_blank" rel="noopener noreferrer" class="underline text-blue-600 hover:text-blue-800 flex items-center gap-1"><i class="ti ti-link"></i> Link</a>`
        : 'N/A';

      const fileHref = row.upload_dokumen ? normalizeUploadPath(String(row.upload_dokumen)) : '';
      const fileLink = row.upload_dokumen
        ? `<a href="${fileHref}" download target="_blank" class="underline text-emerald-600 hover:text-emerald-800 flex items-center gap-1"><i class="ti ti-download"></i> Download</a>`
        : 'N/A';

      let actionCell = '';
      if (!IS_STAF) {
        const payload = encodeURIComponent(JSON.stringify(row || {}));
        actionCell = `
          <td class="py-3 px-4">
            <div class="flex items-center justify-center gap-2">
              <button class="btn-edit btn-icon text-blue-600 hover:text-blue-800" data-json="${payload}" title="Edit">
                <i class="ti ti-pencil text-lg"></i>
              </button>
              <button class="btn-delete btn-icon text-red-600 hover:text-red-800" data-id="${row.id}" title="Hapus">
                <i class="ti ti-trash text-lg"></i>
              </button>
            </div>
          </td>`;
      }

      return `
        <tr class="border-b hover:bg-gray-50">
          <td class="py-3 px-4">${row.tahun || '-'}</td>
          <td class="py-3 px-4">${row.kebun_nama || '-'}</td>
          <td class="py-3 px-4">${row.uraian ? String(row.uraian).replaceAll('<','&lt;').replaceAll('>','&gt;') : '-'}</td>
          <td class="py-3 px-4">${externalLink}</td>
          <td class="py-3 px-4">${fileLink}</td>
          ${actionCell}
        </tr>`;
    }

    function renderPage() {
      // Filter sisi klien tidak diperlukan lagi karena server sudah handle
      const total = ALL_ROWS.length;
      const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
      if (CURRENT_PAGE > totalPages) CURRENT_PAGE = totalPages;
      if (CURRENT_PAGE < 1) CURRENT_PAGE = 1;

      const startIdx = (CURRENT_PAGE - 1) * PER_PAGE;
      const endIdx   = Math.min(startIdx + PER_PAGE, total);

      infoTotal.textContent = total.toLocaleString();
      infoFrom.textContent  = total ? (startIdx + 1).toLocaleString() : 0;
      infoTo.textContent    = endIdx.toLocaleString();
      pageNow.textContent   = String(CURRENT_PAGE);
      pageTotal.textContent = String(totalPages);

      btnPrev.disabled = CURRENT_PAGE <= 1;
      btnNext.disabled = CURRENT_PAGE >= totalPages;

      const emptyRow = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-gray-500">Belum ada data untuk kategori ini.</td></tr>`;
      if (!total) { tbody.innerHTML = emptyRow; return; }

      const rows = ALL_ROWS.slice(startIdx, endIdx).map(buildRowHTML).join('');
      tbody.innerHTML = rows || emptyRow;
    }
    
    function refreshList() {
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'list');
      
      // ==========================================================
      // MODIFIKASI: Kirim Kategori ID, Tahun, dan Search ke backend
      // ==========================================================
      fd.append('kategori_id', KATEGORI_ID);
      
      const tahunVal = yearEl ? yearEl.value : 'all';
      const qVal = q ? q.value : '';

      if (qVal) fd.append('q', qVal);
      if (tahunVal && tahunVal !== 'all') fd.append('tahun', tahunVal);

      tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-gray-500">Memuat data…</td></tr>`;

      fetch(API_URL, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
          if (!j.success) {
            tbody.innerHTML = `<tr><td colspan="="${COLSPAN}" class="text-center py-8 text-red-500">${j.message || 'Gagal memuat data'}</td></tr>`;
            ALL_ROWS = []; renderPage(); return;
          }
          
          // Data sudah difilter server, tidak perlu applyClientFilter
          ALL_ROWS = Array.isArray(j.data) ? j.data : [];
          CURRENT_PAGE = 1;
          renderPage();
        })
        .catch(err => {
          tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-8 text-red-500">${(err && err.message) || 'Network error'}</td></tr>`;
          ALL_ROWS = []; renderPage();
        });
    }

    function debounce(fn, ms) {
      let t; 
      return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); };
    }
    const refreshListDebounced = debounce(refreshList, 250);

    // Init
    refreshList();
    if (q) q.addEventListener('input', refreshListDebounced);
    if (yearEl) yearEl.addEventListener('change', refreshList);

    perPageEl.addEventListener('change', () => {
      PER_PAGE = parseInt(perPageEl.value, 10) || 10;
      CURRENT_PAGE = 1;
      renderPage();
    });

    btnPrev.addEventListener('click', () => { if (CURRENT_PAGE > 1) { CURRENT_PAGE -= 1; renderPage(); } });
    btnNext.addEventListener('click', () => { CURRENT_PAGE += 1; renderPage(); });

    // --- Modal Logic ---
    if (!IS_STAF) {
      const modal = $('#crud-modal');
      const btnClose = $('#btn-close');
      const btnCancel = $('#btn-cancel');
      const form = $('#crud-form');
      const formAction = $('#form-action');
      const formId = $('#form-id');
      const title = $('#modal-title');
      const fileLinkCurrent = $('#file-link-current');
      
      // ==========================================================
      // MODIFIKASI: Ambil hidden input kategori
      // ==========================================================
      const formKategoriId = $('#form-kategori-id');

      const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
      const close= () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

      $('#btn-add').addEventListener('click', () => {
        form.reset();
        formId.value = '';
        formAction.value = 'store';
        title.textContent = 'Input Laporan Baru';
        fileLinkCurrent.innerHTML = '';
        $('#tahun').value = '<?= $tahunNow ?>';
        // ==========================================================
        // MODIFIKASI: Pastikan Kategori ID terisi
        // ==========================================================
        formKategoriId.value = KATEGORI_ID; 
        open();
      });
      btnClose.addEventListener('click', close);
      btnCancel.addEventListener('click', close);

      document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;

        if (btn.classList.contains('btn-edit')) {
          const row = JSON.parse(decodeURIComponent(btn.dataset.json));
          form.reset();
          formAction.value = 'update';
          formId.value = row.id;
          title.textContent = 'Edit Laporan';
          
          // ==========================================================
          // MODIFIKASI: Isi semua field termasuk Kategori ID
          // ==========================================================
          formKategoriId.value = row.kategori_id || KATEGORI_ID; // Ambil dari data, fallback ke ID halaman
          
          $('#tahun').value = row.tahun || '';
          $('#kebun_id').value = row.kebun_id || '';
          $('#uraian').value = row.uraian || '';
          $('#link_dokumen').value = row.link_dokumen || '';
          if (row.upload_dokumen) {
            const href = normalizeUploadPath(String(row.upload_dokumen));
            fileLinkCurrent.innerHTML =
              `<a href="${href}" download target="_blank" class="underline text-emerald-600">
                  Lihat / unduh file saat ini
                </a> <span class="text-gray-500">Kosongkan input file jika tidak ingin mengubah.</span>`;
          } else {
            fileLinkCurrent.innerHTML = '';
          }
          open();
        }

        if (btn.classList.contains('btn-delete')) {
          const id = btn.dataset.id;
          Swal.fire({
            title: 'Hapus data ini?', text: 'Tindakan ini tidak dapat dibatalkan.', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal'
          }).then((res) => {
            if (!res.isConfirmed) return;
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch(API_URL, { method: 'POST', body: fd })
              .then(r => r.json()).then(j => {
                if (j.success) { Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success'); refreshList(); }
                else Swal.fire('Gagal', j.message || 'Tidak bisa menghapus', 'error');
              }).catch(err => Swal.fire('Error', (err && err.message) || 'Network error', 'error'));
          });
        }
      });

      form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Validasi
        const req = ['tahun', 'kebun_id', 'uraian', 'kategori_id'];
        for (const id of req) {
          const el = document.querySelector('#' + (id === 'kategori_id' ? 'form-kategori-id' : id));
          if (!el || !el.value) {
            const label = (el && el.previousElementSibling) ? el.previousElementSibling.textContent : id;
            Swal.fire('Validasi', `Field "${label}" wajib diisi.`, 'warning');
            return;
          }
        }
        
        const fd = new FormData(form);
        fetch(API_URL, { method: 'POST', body: fd })
          .then(r => r.json()).then(j => {
            if (j.success) { close(); Swal.fire({ icon: 'success', title: 'Berhasil', text: j.message, timer: 1400, showConfirmButton: false }); refreshList(); }
            else { Swal.fire('Gagal', j.message || 'Terjadi kesalahan.', 'error'); }
          })
          .catch(err => Swal.fire('Error', (err && err.message) || 'Network error', 'error'));
      });
    }

  });
</script>