<?php
// pages/users.php
// MODIFIKASI FULL: Support Role Viewer, Tabel Grid, Modal Blur

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}

// Pastikan hanya Admin yang bisa akses halaman ini (Opsional, hapus jika Viewer boleh lihat user lain)
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') {
    // Redirect ke dashboard atau tampilkan pesan error
    header("Location: index.php"); exit;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$currentPage = 'users';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  /* --- STYLE TABEL GRID (Full Border & Sticky) --- */
  .table-container {
    max-height: 75vh; /* Tinggi maksimal scroll */
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  }

  table.table-grid {
    width: 100%;
    border-collapse: separate; /* Wajib separate agar sticky jalan */
    border-spacing: 0;
  }

  /* Garis-garis tabel */
  table.table-grid th, 
  table.table-grid td {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    white-space: nowrap;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; /* Garis bawah */
    border-right: 1px solid #e2e8f0;  /* Garis kanan (vertikal) */
  }

  /* Hapus garis kanan pada kolom terakhir */
  table.table-grid th:last-child, 
  table.table-grid td:last-child {
    border-right: none;
  }

  /* Desain Header (Sticky & Lebih Tinggi) */
  table.table-grid thead th {
    position: sticky;
    top: 0;
    background: #0891b2; /* Cyan-600 */
    color: #fff;
    z-index: 20;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.75rem;
    height: 55px; 
    vertical-align: middle;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  /* Hover Effect Baris */
  table.table-grid tbody tr:hover td {
    background-color: #f0f9ff; /* Cyan-50 */
  }

  /* Tombol Aksi Kecil */
  .btn-icon {
    display: inline-flex; justify-content: center; align-items: center;
    width: 32px; height: 32px; border-radius: 6px;
    border: 1px solid #e2e8f0; background: #fff;
    cursor: pointer; transition: all 0.2s;
  }
  .btn-icon:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateY(-1px); }
</style>

<div class="space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Manajemen User</h1>
      <p class="text-gray-500 mt-1">Kelola akun Admin, Staf, dan Viewer</p>
    </div>
    <button id="btn-add" class="inline-flex items-center gap-2 bg-cyan-600 text-white px-5 py-2.5 rounded-lg shadow hover:bg-cyan-700 transition active:scale-[.98]">
      <i class="ti ti-user-plus text-lg"></i>
      <span class="font-medium">Tambah User</span>
    </button>
  </div>

  <div class="bg-white p-1 rounded-xl shadow-sm border border-gray-100">
    <div class="table-container">
      <table class="table-grid">
        <thead>
          <tr>
            <th class="text-left">Username</th>
            <th class="text-left">Nama Lengkap</th>
            <th class="text-left">NIK</th>
            <th class="text-center">Role</th>
            <th class="text-left">Dibuat</th>
            <th class="text-center" style="width: 100px;">Aksi</th>
          </tr>
        </thead>
        <tbody id="tbody-data">
          <tr><td colspan="6" class="py-10 text-center text-gray-500"><i class="ti ti-loader animate-spin text-xl"></i><br>Memuat data...</td></tr>
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-t bg-gray-50/50 rounded-b-xl">
      <div class="flex items-center gap-2">
        <label for="page-size" class="text-sm text-gray-600 font-medium">Tampilkan</label>
        <select id="page-size" class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
          <option value="5">5</option>
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="text-sm text-gray-600">baris</span>
      </div>

      <div class="flex items-center gap-2" id="pager"></div>

      <div class="text-sm text-gray-600 font-medium" id="range-info"></div>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-opacity">
  <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-2xl transform scale-100 transition-transform">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-800">Tambah User</h3>
      <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600 transition">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
          <label class="block mb-1.5 text-sm font-bold text-gray-700">Username <span class="text-red-500">*</span></label>
          <input type="text" name="username" id="username" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-cyan-500 outline-none transition" required>
        </div>
        <div>
          <label class="block mb-1.5 text-sm font-bold text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
          <input type="text" name="nama_lengkap" id="nama_lengkap" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-cyan-500 outline-none transition" required>
        </div>
        <div>
          <label class="block mb-1.5 text-sm font-bold text-gray-700">NIK <span class="text-red-500">*</span></label>
          <input type="text" name="nik" id="nik" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-cyan-500 outline-none transition" required>
        </div>
        <div>
          <label class="block mb-1.5 text-sm font-bold text-gray-700">Password <span class="text-gray-400 text-xs font-normal">(opsional jika edit)</span></label>
          <input type="password" name="password" id="password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-cyan-500 outline-none transition">
        </div>
        <div class="md:col-span-2">
          <label class="block mb-1.5 text-sm font-bold text-gray-700">Role <span class="text-red-500">*</span></label>
          <select name="role" id="role" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-cyan-500 outline-none transition" required>
            <option value="admin">Admin (Akses Penuh)</option>
            <option value="staf">Staf (Input & Operasional)</option>
            <option value="viewer">Viewer (Hanya Lihat Laporan)</option>
          </select>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-8 pt-4 border-t">
        <button type="button" id="btn-cancel" class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium transition">Batal</button>
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 font-medium shadow-lg shadow-cyan-500/30 transition">Simpan Data</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const modal = document.getElementById('crud-modal');
  const tbody = document.getElementById('tbody-data');
  const form  = document.getElementById('crud-form');

  const open  = ()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); };
  const close = ()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); };

  const CSRF = '<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>';

  function renderActions(u) {
    const payload = encodeURIComponent(JSON.stringify(u));
    return `
      <div class="flex items-center justify-center gap-2">
        <button class="btn-edit btn-icon text-blue-600 hover:text-blue-700"
          title="Edit" aria-label="Edit user" data-json="${payload}">
          <i class="ti ti-pencil"></i>
        </button>
        <button class="btn-del btn-icon text-red-600 hover:text-red-700"
          title="Hapus" aria-label="Hapus user" data-id="${u.id}">
          <i class="ti ti-trash"></i>
        </button>
      </div>
    `;
  }

  // ===== Helper Warna Badge Role =====
  function getRoleBadge(role) {
    if (role === 'admin') {
        return 'bg-blue-100 text-blue-700 border border-blue-200';
    } else if (role === 'viewer') {
        // Warna Hijau untuk Viewer
        return 'bg-green-100 text-green-700 border border-green-200';
    } else {
        // Staf (Default)
        return 'bg-gray-100 text-gray-700 border border-gray-200';
    }
  }

  // ===== Pagination (client-side) =====
  const pageSizeSel = document.getElementById('page-size');
  const pagerEl = document.getElementById('pager');
  const rangeInfoEl = document.getElementById('range-info');

  let allData = [];     
  let currentPage = 1;  

  function paginate(arr, size, page){
    const start = (page - 1) * size;
    return arr.slice(start, start + size);
  }

  function renderPager(page, totalPages){
    const windowSize = 5;
    let start = Math.max(1, page - Math.floor(windowSize/2));
    let end   = start + windowSize - 1;
    if (end > totalPages) { end = totalPages; start = Math.max(1, end - windowSize + 1); }

    const btn = (label, disabled, goPage, extra='') => `
      <button ${disabled ? 'disabled' : ''} data-goto="${goPage}"
        class="px-3 py-1 border rounded-md text-sm font-medium transition ${disabled ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'hover:bg-gray-100 bg-white'} ${extra}">
        ${label}
      </button>`;

    let html = '';
    html += btn('«', page <= 1, page - 1);
    for (let p = start; p <= end; p++){
      html += btn(p, false, p, p===page ? '!bg-cyan-600 !text-white !border-cyan-600' : '');
    }
    html += btn('»', page >= totalPages, page + 1);
    pagerEl.innerHTML = html;
  }

  function renderTable(){
    const size = parseInt(pageSizeSel.value || '10', 10);
    const total = allData.length;
    const totalPages = Math.max(1, Math.ceil(total / size));
    if (currentPage > totalPages) currentPage = totalPages;

    if (!total){
      tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-gray-500 italic">Belum ada data user.</td></tr>';
      renderPager(1,1);
      rangeInfoEl.textContent = '';
      return;
    }

    const rows = paginate(allData, size, currentPage);
    tbody.innerHTML = rows.map(u=>`
      <tr class="transition-colors">
        <td class="font-medium text-gray-800">${u.username ?? '-'}</td>
        <td class="text-gray-600">${u.nama_lengkap ?? '-'}</td>
        <td class="text-gray-600">${u.nik ?? '-'}</td>
        <td class="text-center">
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide shadow-sm
            ${getRoleBadge(u.role)}">
            ${u.role ?? '-'}
          </span>
        </td>
        <td class="text-gray-500 text-sm">${u.created_at ?? '-'}</td>
        <td class="text-center">${renderActions(u)}</td>
      </tr>
    `).join('');

    const startIdx = (currentPage - 1) * size + 1;
    const endIdx   = Math.min(currentPage * size, total);
    rangeInfoEl.textContent = `Menampilkan ${startIdx}–${endIdx} dari ${total} data`;

    renderPager(currentPage, totalPages);
  }

  function refresh(){
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('action', 'list');
    
    tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-gray-500"><i class="ti ti-loader animate-spin"></i> Memuat...</td></tr>';

    fetch('users_crud.php', { method:'POST', body: fd })
      .then(r=>r.json())
      .then(j=>{
        if(!j.success){
          tbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-red-500 font-medium">${j.message||'Error'}</td></tr>`;
          allData = [];
          renderPager(1,1);
          rangeInfoEl.textContent = '';
          return;
        }
        allData = j.data || [];
        currentPage = 1;
        renderTable();
      })
      .catch(_=>{
        tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-red-500 font-medium">Gagal memuat data.</td></tr>';
        allData = [];
        renderPager(1,1);
        rangeInfoEl.textContent = '';
      });
  }

  // ===== Open / Close modal =====
  document.getElementById('btn-add').addEventListener('click', ()=>{
    form.reset();
    document.getElementById('form-action').value = 'store';
    document.getElementById('form-id').value = '';
    document.getElementById('role').value = 'staf'; // Default role
    document.getElementById('modal-title').textContent = 'Tambah User';
    open();
    setTimeout(()=>document.getElementById('username').focus(), 50);
  });
  document.getElementById('btn-close').onclick = close;
  document.getElementById('btn-cancel').onclick = close;

  // ===== Delegated actions =====
  tbody.addEventListener('click', (e)=>{
    const btn = e.target.closest('button');
    if(!btn) return;

    if(btn.classList.contains('btn-edit')){
      try {
        const u = JSON.parse(decodeURIComponent(btn.dataset.json));
        form.reset();
        document.getElementById('form-action').value = 'update';
        document.getElementById('form-id').value = u.id;
        document.getElementById('username').value = u.username || '';
        document.getElementById('nama_lengkap').value = u.nama_lengkap || '';
        document.getElementById('nik').value = u.nik || '';
        document.getElementById('role').value = u.role || 'staf';
        document.getElementById('modal-title').textContent = 'Edit User';
        open();
        setTimeout(()=>document.getElementById('username').focus(), 50);
      } catch(err){
        Swal.fire('Error','Data tidak valid','error');
      }
    }

    if(btn.classList.contains('btn-del')){
      const id = btn.dataset.id;
      Swal.fire({
        title: 'Hapus user?',
        text: 'Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
      }).then(res=>{
        if(res.isConfirmed){
          const fd = new FormData();
          fd.append('csrf_token', CSRF);
          fd.append('action', 'delete');
          fd.append('id', id);
          fetch('users_crud.php', { method:'POST', body: fd })
            .then(r=>r.json())
            .then(j=>{
              if(j.success){
                Swal.fire('Terhapus','User berhasil dihapus.','success');
                refresh();
              } else {
                Swal.fire('Gagal', j.message || 'Error', 'error');
              }
            })
            .catch(err=> Swal.fire('Error', err?.message || 'Request gagal', 'error'));
        }
      });
    }
  });

  // ===== Submit form =====
  form.addEventListener('submit', (e)=>{
    e.preventDefault();
    const fd = new FormData(form);

    // Validasi cepat
    if(!fd.get('username') || !fd.get('nama_lengkap') || !fd.get('nik')){
      Swal.fire('Validasi','Username, Nama, dan NIK wajib diisi.','warning'); return;
    }

    fetch('users_crud.php', { method:'POST', body: fd })
      .then(r=>r.json())
      .then(j=>{
        if(j.success){
          close();
          Swal.fire({
            icon:'success',
            title:'Berhasil',
            text: j.message || 'Data user tersimpan.',
            timer: 1600,
            showConfirmButton: false
          });
          refresh();
        } else {
          Swal.fire('Gagal', j.message || 'Error', 'error');
        }
      })
      .catch(err=> Swal.fire('Error', err?.message || 'Request gagal', 'error'));
  });

  // ESC to close modal
  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape' && !modal.classList.contains('hidden')) close();
  });

  // Pagination events
  pageSizeSel.addEventListener('change', ()=>{ currentPage = 1; renderTable(); });
  pagerEl.addEventListener('click', (e)=>{
    const b = e.target.closest('button[data-goto]');
    if(!b || b.disabled) return;
    const goto = parseInt(b.dataset.goto, 10);
    if(!Number.isNaN(goto)){
      currentPage = goto;
      renderTable();
    }
  });

  // Initial load
  refresh();
});
</script>