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
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold">Manajemen User</h1>
      <p class="text-gray-500">Kelola akun admin dan staf</p>
    </div>
    <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ Tambah User</button>
  </div>

  <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-gray-600">
          <th class="py-3 px-4 text-left">Username</th>
          <th class="py-3 px-4 text-left">Nama Lengkap</th>
          <th class="py-3 px-4 text-left">Email</th>
          <th class="py-3 px-4 text-left">Role</th>
          <th class="py-3 px-4 text-left">Dibuat</th>
          <th class="py-3 px-4 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-data">
        <tr><td colspan="6" class="py-10 text-center text-gray-500">Memuat data…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 rounded-xl shadow-xl w-full max-w-2xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Tambah User</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block mb-1">Username *</label>
          <input type="text" name="username" id="username" class="w-full border rounded px-3 py-2" required>
        </div>
        <div>
          <label class="block mb-1">Nama Lengkap *</label>
          <input type="text" name="nama_lengkap" id="nama_lengkap" class="w-full border rounded px-3 py-2" required>
        </div>
        <div>
          <label class="block mb-1">Email *</label>
          <input type="email" name="email" id="email" class="w-full border rounded px-3 py-2" required>
        </div>
        <div>
          <label class="block mb-1">Password <span class="text-gray-400">(kosongkan jika tidak ganti)</span></label>
          <input type="password" name="password" id="password" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block mb-1">Role *</label>
          <select name="role" id="role" class="w-full border rounded px-3 py-2" required>
            <option value="admin">Admin</option>
            <option value="staf">Staf</option>
          </select>
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
  const modal=document.getElementById('crud-modal');
  const tbody=document.getElementById('tbody-data');
  const form=document.getElementById('crud-form');
  const open=()=>{modal.classList.remove('hidden');modal.classList.add('flex');}
  const close=()=>{modal.classList.add('hidden');modal.classList.remove('flex');}

  function refresh(){
    const fd=new FormData(); fd.append('csrf_token','<?= $CSRF ?>'); fd.append('action','list');
    fetch('users_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
      if(j.success){
        if(!j.data.length){tbody.innerHTML='<tr><td colspan="6" class="py-10 text-center text-gray-500">Belum ada data.</td></tr>';return;}
        tbody.innerHTML=j.data.map(u=>`
          <tr class="border-b hover:bg-gray-50">
            <td class="py-2 px-3">${u.username}</td>
            <td class="py-2 px-3">${u.nama_lengkap}</td>
            <td class="py-2 px-3">${u.email}</td>
            <td class="py-2 px-3">${u.role}</td>
            <td class="py-2 px-3">${u.created_at}</td>
            <td class="py-2 px-3">
              <button class="text-blue-600 underline btn-edit" data-json='${JSON.stringify(u)}'>Edit</button>
              <button class="text-red-600 underline btn-del" data-id="${u.id}">Hapus</button>
            </td>
          </tr>`).join('');
      }
    });
  }

  document.getElementById('btn-add').addEventListener('click',()=>{
    form.reset(); document.getElementById('form-action').value='store';
    document.getElementById('form-id').value='';
    document.getElementById('modal-title').textContent='Tambah User';
    open();
  });
  document.getElementById('btn-close').onclick=close;
  document.getElementById('btn-cancel').onclick=close;

  tbody.addEventListener('click',e=>{
    if(e.target.classList.contains('btn-edit')){
      const u=JSON.parse(e.target.dataset.json);
      form.reset();
      document.getElementById('form-action').value='update';
      document.getElementById('form-id').value=u.id;
      document.getElementById('username').value=u.username;
      document.getElementById('nama_lengkap').value=u.nama_lengkap;
      document.getElementById('email').value=u.email;
      document.getElementById('role').value=u.role;
      document.getElementById('modal-title').textContent='Edit User';
      open();
    }
    if(e.target.classList.contains('btn-del')){
      const id=e.target.dataset.id;
      Swal.fire({title:'Hapus user?',icon:'warning',showCancelButton:true}).then(res=>{
        if(res.isConfirmed){
          const fd=new FormData(); fd.append('csrf_token','<?= $CSRF ?>'); fd.append('action','delete'); fd.append('id',id);
          fetch('users_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
            if(j.success){Swal.fire('Terhapus','', 'success');refresh();}
            else Swal.fire('Gagal',j.message,'error');
          });
        }
      });
    }
  });

  form.addEventListener('submit',e=>{
    e.preventDefault();
    const fd=new FormData(form);
    fetch('users_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
      if(j.success){close();Swal.fire('Berhasil',j.message,'success');refresh();}
      else Swal.fire('Gagal',j.message,'error');
    });
  });

  refresh();
});
</script>
