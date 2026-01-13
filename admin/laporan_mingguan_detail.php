<?php
// laporan_mingguan_detail.php
// MODIFIKASI: Filter Tahun 2020-2027 & Limit Upload 20MB

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php"); exit;
}

// ==========================================================
// VALIDASI KATEGORI
// ==========================================================
$kategori_id = (int)($_GET['k_id'] ?? 0);
if ($kategori_id === 0) {
    header("Location: laporan_mingguan.php"); 
    exit;
}

require_once '../config/database.php';
$db_temp = new Database();
$conn_temp = $db_temp->getConnection();
$stmt_kat = $conn_temp->prepare("SELECT nama_kategori FROM arsip_kategori WHERE id = ?");
$stmt_kat->execute([$kategori_id]);
$kategori_nama = $stmt_kat->fetchColumn();

if (!$kategori_nama) {
    header("Location: laporan_mingguan.php"); 
    exit;
}
// ==========================================================

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// Role & Init
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');
$db   = $db_temp;
$conn = $conn_temp;

// Tahun saat ini untuk Logic Filter & Input
$tahunNow = (int)date('Y');

// List kebun untuk form modal
$kebunList = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'laporan_mingguan';
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  /* --- GRID TABLE STYLE --- */
  .sticky-container {
    max-height: 70vh; 
    overflow: auto;
    border: 1px solid #cbd5e1; 
    border-radius: 0.75rem;
    background: #fff; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }
  table.table-grid { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1000px; }
  table.table-grid th, table.table-grid td {
    padding: 0.75rem 1rem; font-size: 0.85rem; vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; color: #334155;
  }
  table.table-grid th:last-child, table.table-grid td:last-child { border-right: none; }
  table.table-grid thead th {
    position: sticky; top: 0; z-index: 10;
    background: #059fd3; color: #fff; font-weight: 700; font-size: 0.75rem;
    text-transform: uppercase; height: 50px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1); white-space: nowrap;
  }
  table.table-grid tbody tr:hover td { background-color: #f0f9ff; }
  .btn-icon { background: transparent; border: none; padding: 0.25rem; cursor: pointer; transition: transform 0.2s; }
  .btn-icon:hover { transform: scale(1.1); }
  button:disabled { opacity: .5; cursor: not-allowed !important; }
  .i-input:focus, .i-select:focus { border-color: #059fd3; box-shadow: 0 0 0 2px rgba(5, 159, 211, 0.2); outline: none; }
</style>

<div class="space-y-6">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="laporan_mingguan.php" class="hover:text-[#059fd3] flex items-center gap-1 transition-colors">
                    <i class="ti ti-arrow-left"></i> Kembali ke Arsip
                </a>
                <span>/</span>
                <span>Detail</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ti ti-folder-open text-[#059fd3]"></i> 
                <?= htmlspecialchars($kategori_nama) ?>
            </h1>
        </div>

        <div class="flex gap-3">
            <?php if (!$isStaf): ?>
            <button id="btn-add" class="bg-[#059fd3] text-white px-5 py-2.5 rounded-lg hover:bg-[#0487b4] flex items-center gap-2 shadow-sm transition text-sm font-medium">
                <i class="ti ti-plus"></i>
                <span>Input Laporan</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 relative overflow-hidden">
        <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#059fd3]"></div>
        
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100">
            <div class="p-1.5 bg-cyan-50 rounded text-[#059fd3]"><i class="ti ti-filter"></i></div>
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Filter & Pencarian</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="filter-year" class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Tahun</label>
                <select id="filter-year" class="i-select w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 bg-gray-50">
                    <option value="all">Semua Tahun</option>
                    <?php for ($y = 2027; $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= ($y === $tahunNow) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="filter-q" class="block text-xs font-bold text-gray-500 uppercase mb-1.5">Pencarian</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="ti ti-search"></i></span>
                    <input id="filter-q" type="text" class="i-input w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2 text-sm text-gray-700" placeholder="Cari uraian, link, atau nama kebun...">
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 p-4 bg-gray-50 border-b border-gray-200">
            <div class="text-sm text-gray-600">
                Menampilkan <strong id="info-from" class="text-gray-900">0</strong>–<strong id="info-to" class="text-gray-900">0</strong>
                dari <strong id="info-total" class="text-gray-900">0</strong> data
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-gray-500 uppercase">Show</label>
                <select id="per-page" class="i-select px-2 py-1 rounded border border-gray-300 text-sm bg-white">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="sticky-container border-0 rounded-none shadow-none">
            <table class="table-grid">
                <thead>
                    <tr>
                        <th style="width: 80px;" class="text-center">Tahun</th>
                        <th style="width: 150px;">Kebun</th>
                        <th style="min-width: 300px;">Uraian</th>
                        <th style="width: 100px;" class="text-center">Link</th>
                        <th style="width: 120px;" class="text-center">File</th>
                        <?php if (!$isStaf): ?>
                            <th style="width: 100px;" class="text-center">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="tbody-data">
                    <tr><td colspan="<?= $isStaf ? 5 : 6 ?>" class="text-center py-10 text-gray-500 italic">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 p-4 bg-gray-50 border-t border-gray-200">
            <div class="text-sm text-gray-600">Halaman <span id="page-now" class="font-semibold text-gray-900">1</span> dari <span id="page-total" class="font-semibold text-gray-900">1</span></div>
            <div class="inline-flex gap-2">
                <button id="btn-prev" class="px-3 py-1.5 rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 text-sm font-medium transition" disabled>Prev</button>
                <button id="btn-next" class="px-3 py-1.5 rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-100 text-sm font-medium transition" disabled>Next</button>
            </div>
        </div>
    </div>
</div>

<?php if (!$isStaf): ?>
<div id="crud-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-opacity">
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-2xl transform scale-100 transition-transform max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6 border-b pb-4">
            <div>
                <h3 id="modal-title" class="text-xl font-bold text-gray-900">Input Laporan Baru</h3>
                <p class="text-xs text-gray-500 mt-1">Isi detail laporan di bawah ini.</p>
            </div>
            <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600 transition">&times;</button>
        </div>
        
        <form id="crud-form" novalidate enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
            <input type="hidden" name="action" id="form-action">
            <input type="hidden" name="id" id="form-id">
            <input type="hidden" name="kategori_id" id="form-kategori-id" value="<?= $kategori_id ?>">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tahun</label>
                    <select id="tahun" name="tahun" class="i-select w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700">
                        <?php for ($y = 2020; $y <= 2027; $y++): ?>
                            <option value="<?= $y ?>" <?= ($y === $tahunNow) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Kebun</label>
                    <select id="kebun_id" name="kebun_id" class="i-select w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700">
                        <option value="">-- Pilih Kebun --</option>
                        <?php foreach ($kebunList as $k): ?>
                            <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Uraian</label>
                <textarea id="uraian" name="uraian" rows="3" class="i-input w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700" placeholder="Deskripsi singkat laporan..." maxlength="255"></textarea>
            </div>

            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Upload Dokumen</label>
                    <input type="file" id="upload_dokumen" name="upload_dokumen" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <p class="text-[10px] text-gray-400 mt-1 italic">* Maksimal ukuran file: 20 MB</p>
                    <div id="file-link-current" class="text-xs mt-2 text-gray-500"></div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Atau Link Eksternal</label>
                    <input type="url" id="link_dokumen" name="link_dokumen" class="i-input w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700" placeholder="https://drive.google.com/...">
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium transition">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-[#059fd3] text-white hover:bg-[#0487b4] text-sm font-medium shadow-lg shadow-cyan-500/30 transition">Simpan Data</button>
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

    const API_URL = 'laporan_mingguan_crud.php';

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
        ? `<a href="${row.link_dokumen}" target="_blank" rel="noopener noreferrer" class="text-[#059fd3] hover:underline flex items-center justify-center gap-1 font-medium"><i class="ti ti-link"></i> Buka</a>`
        : '<span class="text-gray-400 text-xs text-center block">-</span>';

      const fileHref = row.upload_dokumen ? normalizeUploadPath(String(row.upload_dokumen)) : '';
      const fileLink = row.upload_dokumen
        ? `<a href="${fileHref}" download target="_blank" class="text-green-600 hover:underline flex items-center justify-center gap-1 font-medium"><i class="ti ti-download"></i> Unduh</a>`
        : '<span class="text-gray-400 text-xs text-center block">-</span>';

      let actionCell = '';
      if (!IS_STAF) {
        const payload = encodeURIComponent(JSON.stringify(row || {}));
        actionCell = `
          <td class="text-center">
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
        <tr>
          <td class="text-center font-mono font-bold text-gray-600">${row.tahun || '-'}</td>
          <td class="font-medium text-gray-800">${row.kebun_nama || '-'}</td>
          <td class="whitespace-normal leading-relaxed text-gray-700">${row.uraian ? String(row.uraian).replaceAll('<','&lt;').replaceAll('>','&gt;') : '-'}</td>
          <td class="text-center">${externalLink}</td>
          <td class="text-center">${fileLink}</td>
          ${actionCell}
        </tr>`;
    }

    function renderPage() {
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

      const emptyRow = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-400 italic">Belum ada data untuk filter ini.</td></tr>`;
      if (!total) { tbody.innerHTML = emptyRow; return; }

      const rows = ALL_ROWS.slice(startIdx, endIdx).map(buildRowHTML).join('');
      tbody.innerHTML = rows || emptyRow;
    }
    
    function refreshList() {
      const fd = new FormData();
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('action', 'list');
      fd.append('kategori_id', KATEGORI_ID);
      
      const tahunVal = yearEl ? yearEl.value : 'all';
      const qVal = q ? q.value : '';

      if (qVal) fd.append('q', qVal);
      if (tahunVal && tahunVal !== 'all') fd.append('tahun', tahunVal);

      tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>`;

      fetch(API_URL, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
          if (!j.success) {
            tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-red-500">${j.message || 'Gagal memuat data'}</td></tr>`;
            ALL_ROWS = []; renderPage(); return;
          }
          
          ALL_ROWS = Array.isArray(j.data) ? j.data : [];
          CURRENT_PAGE = 1;
          renderPage();
        })
        .catch(err => {
          tbody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center py-10 text-red-500">${(err && err.message) || 'Network error'}</td></tr>`;
          ALL_ROWS = []; renderPage();
        });
    }

    function debounce(fn, ms) {
      let t; 
      return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); };
    }
    const refreshListDebounced = debounce(refreshList, 250);

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

    // --- Modal Logic (CRUD) ---
    if (!IS_STAF) {
      const modal = $('#crud-modal');
      const btnClose = $('#btn-close');
      const btnCancel = $('#btn-cancel');
      const form = $('#crud-form');
      const formAction = $('#form-action');
      const formId = $('#form-id');
      const title = $('#modal-title');
      const fileLinkCurrent = $('#file-link-current');
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
          formKategoriId.value = row.kategori_id || KATEGORI_ID; 
          
          $('#tahun').value = row.tahun || '';
          $('#kebun_id').value = row.kebun_id || '';
          $('#uraian').value = row.uraian || '';
          $('#link_dokumen').value = row.link_dokumen || '';
          if (row.upload_dokumen) {
            const href = normalizeUploadPath(String(row.upload_dokumen));
            fileLinkCurrent.innerHTML =
              `<div class="flex items-center gap-2 p-2 bg-blue-50 rounded border border-blue-100">
                 <i class="ti ti-file-check text-blue-600"></i>
                 <a href="${href}" download target="_blank" class="underline text-blue-600 text-xs font-bold">File Terupload</a>
                 <span class="text-xs text-gray-400">(Upload baru untuk mengganti)</span>
               </div>`;
          } else {
            fileLinkCurrent.innerHTML = '';
          }
          open();
        }

        if (btn.classList.contains('btn-delete')) {
          const id = btn.dataset.id;
          Swal.fire({
            title: 'Hapus data?', text: 'Tindakan ini tidak dapat dibatalkan.', icon: 'warning',
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
        const req = ['tahun', 'kebun_id', 'uraian', 'kategori_id'];
        for (const id of req) {
          const el = document.querySelector('#' + (id === 'kategori_id' ? 'form-kategori-id' : id));
          if (!el || !el.value) {
            Swal.fire('Validasi', 'Data wajib belum lengkap.', 'warning');
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