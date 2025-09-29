<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$currentPage = 'users';
include_once '../layouts/header.php';
?>
<div class="space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Manajemen User</h1>
      <p class="text-gray-500">Kelola akun admin dan staf (login pakai NIK/username)</p>
    </div>
    <button id="btn-add" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-emerald-700 active:scale-[.98] focus:outline-none focus:ring-2 focus:ring-emerald-500">
      <!-- Plus icon -->
      <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      <span class="font-medium">Tambah User</span>
    </button>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-gray-600">
        <tr>
          <th class="py-3 px-4 text-left font-semibold">Username</th>
          <th class="py-3 px-4 text-left font-semibold">Nama Lengkap</th>
          <th class="py-3 px-4 text-left font-semibold">NIK</th>
          <th class="py-3 px-4 text-left font-semibold">Role</th>
          <th class="py-3 px-4 text-left font-semibold">Dibuat</th>
          <th class="py-3 px-4 text-left font-semibold w-28">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-data">
        <tr><td colspan="6" class="py-10 text-center text-gray-500">Memuat data…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-2xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Tambah User</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800" aria-label="Tutup modal">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block mb-1 font-medium">Username <span class="text-red-500">*</span></label>
          <input type="text" name="username" id="username" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500" required>
        </div>
        <div>
          <label class="block mb-1 font-medium">Nama Lengkap <span class="text-red-500">*</span></label>
          <input type="text" name="nama_lengkap" id="nama_lengkap" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500" required>
        </div>
        <div>
          <label class="block mb-1 font-medium">NIK <span class="text-red-500">*</span></label>
          <input type="text" name="nik" id="nik" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500" required>
        </div>
        <div>
          <label class="block mb-1 font-medium">Password <span class="text-gray-400 text-xs">(kosongkan jika tidak ganti)</span></label>
          <input type="password" name="password" id="password" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500">
        </div>
        <div>
          <label class="block mb-1 font-medium">Role <span class="text-red-500">*</span></label>
          <select name="role" id="role" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500" required>
            <option value="admin">Admin</option>
            <option value="staf">Staf</option>
          </select>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Simpan</button>
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

  // ===== Helpers: Icons (inline SVG) =====
  const Icon = {
    edit: `<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
               d="M16.862 4.487l1.95 1.95M7 17l-1 4 4-1 9.293-9.293a1.5 1.5 0 0 0 0-2.121l-2.879-2.879a1.5 1.5 0 0 0-2.121 0L7 17z"/>
           </svg>`,
    trash:`<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
               d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0l1-3h6l1 3"/>
           </svg>`
  };

  function renderActions(u) {
    const payload = encodeURIComponent(JSON.stringify(u)); // aman untuk dataset
    return `
      <div class="flex items-center gap-2">
        <button
          class="btn-edit inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-gray-700 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
          title="Edit" aria-label="Edit user" data-json="${payload}">
          ${Icon.edit}
        </button>
        <button
          class="btn-del inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-gray-700 hover:bg-red-50 hover:text-red-700 hover:border-red-200 focus:outline-none focus:ring-2 focus:ring-red-500"
          title="Hapus" aria-label="Hapus user" data-id="${u.id}">
          ${Icon.trash}
        </button>
      </div>
    `;
  }

  function refresh(){
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('action', 'list');

    fetch('users_crud.php', { method:'POST', body: fd })
      .then(r=>r.json())
      .then(j=>{
        if(!j.success){
          tbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-red-500">${j.message||'Error'}</td></tr>`;
          return;
        }
        const rows = j.data || [];
        if(!rows.length){
          tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-gray-500">Belum ada data.</td></tr>';
          return;
        }
        tbody.innerHTML = rows.map(u=>`
          <tr class="border-b last:border-0 hover:bg-gray-50">
            <td class="py-3 px-4">${u.username ?? '-'}</td>
            <td class="py-3 px-4">${u.nama_lengkap ?? '-'}</td>
            <td class="py-3 px-4">${u.nik ?? '-'}</td>
            <td class="py-3 px-4">
              <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                ${u.role==='admin' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-50 text-slate-700 ring-1 ring-slate-200'}">
                ${u.role ?? '-'}
              </span>
            </td>
            <td class="py-3 px-4">${u.created_at ?? '-'}</td>
            <td class="py-3 px-4">${renderActions(u)}</td>
          </tr>
        `).join('');
      })
      .catch(_=>{
        tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-red-500">Gagal memuat data.</td></tr>';
      });
  }

  // ===== Open / Close modal =====
  document.getElementById('btn-add').addEventListener('click', ()=>{
    form.reset();
    document.getElementById('form-action').value = 'store';
    document.getElementById('form-id').value = '';
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

  refresh();
});
</script>
