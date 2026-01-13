<?php
session_start();

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php"); exit;
}

// --- PERBAIKAN LOGIC ROLE (DETECT OTOMATIS) ---
// Kita cek berbagai kemungkinan nama session agar tidak salah baca
$role = 'staf'; // Default anggap staf dulu

if (isset($_SESSION['user_role'])) {
    $role = $_SESSION['user_role']; // Cek nama 'user_role' (biasanya ini yg benar)
} elseif (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];      // Cek nama 'role'
} elseif (isset($_SESSION['level'])) {
    $role = $_SESSION['level'];     // Jaga-jaga kalau namanya 'level'
}

// Paksa huruf kecil semua biar aman (Admin/admin jadi sama)
$role = strtolower($role); 

// --- HAK AKSES ---
// Admin: Boleh Edit & Hapus
$canAction = ($role === 'admin' || $role === 'administrator'); 

// Create: Admin & Staf boleh
$canCreate = ($role === 'admin' || $role === 'administrator' || $role === 'staf'); 

$currentPage = 'portal_aplikasi';
include_once '../layouts/header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* --- GRID SYSTEM --- */
    .portal-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.75rem;
        padding: 1rem 0;
    }
    @media (min-width: 768px) { .portal-grid { grid-template-columns: repeat(6, 1fr); gap: 1rem; } }
    @media (min-width: 1024px) { .portal-grid { grid-template-columns: repeat(8, 1fr); } }

    /* --- ITEM CARD --- */
    .app-item {
        display: flex; flex-direction: column; align-items: center;
        text-decoration: none; position: relative; width: 100%; group: transition;
    }
    .app-box {
        width: 70%; aspect-ratio: 1 / 1; position: relative; overflow: hidden;
        border: 1px solid #cbd5e1; background-color: #fff;
        background-size: cover; background-position: center; background-repeat: no-repeat;
        border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.3s ease;
    }
    .app-item:hover .app-box {
        transform: translateY(-3px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-color: #059fd3;
    }
    .app-name {
        margin-top: 0.5rem; font-size: 0.75rem; font-weight: 600; color: #334155; 
        text-align: center; line-height: 1.1;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; 
        transition: color 0.2s;
    }
    .app-item:hover .app-name { color: #059fd3; }

    /* --- TOMBOL AKSI (EDIT/DELETE) --- */
    .admin-actions {
        position: absolute; top: 0; right: 0;
        display: flex; opacity: 0; transition: opacity 0.2s; z-index: 20;
    }
    .app-item:hover .admin-actions { opacity: 1; }

    .btn-mini {
        width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;
        color: white; font-size: 12px; cursor: pointer; border: none;
    }
    .btn-edit { background: #f59e0b; border-bottom-left-radius: 6px; } 
    .btn-del  { background: #ef4444; border-top-right-radius: 12px; } 
    .btn-mini:hover { filter: brightness(90%); }
</style>

<div class="space-y-6 mb-12">
    
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 uppercase tracking-wide flex items-center gap-2">
                <i class="ti ti-layout-grid text-cyan-600"></i> Portal Menu
            </h1>
            
            
        </div>
        
        <div class="flex items-center gap-3 w-full md:w-auto">
            <div class="relative w-full md:w-64">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="search-app" class="w-full pl-10 pr-4 py-2 border border-gray-300 text-sm focus:border-cyan-500 outline-none" placeholder="Cari menu...">
            </div>

            <?php if ($canCreate): ?>
            <button onclick="openModal('create')" class="bg-[#059fd3] hover:bg-[#0487b4] text-white px-4 py-2 text-sm font-medium flex items-center gap-2 shadow-sm transition rounded-sm">
                <i class="ti ti-plus"></i> Tambah
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="app-container" class="portal-grid">
        <div class="col-span-full text-center py-12 text-gray-400">
            <i class="ti ti-loader animate-spin text-2xl"></i> Memuat...
        </div>
    </div>
</div>

<?php if ($canCreate): ?>
<div id="modal-form" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white w-full max-w-md shadow-2xl rounded-xl">
        <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center rounded-t-xl">
            <h3 id="modal-title" class="font-bold text-gray-700 uppercase">Tambah Aplikasi</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 text-2xl">&times;</button>
        </div>

        <form id="form-app" class="p-6 space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="action" id="form-action" value="store">
            <input type="hidden" name="id" id="form-id">

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Aplikasi</label>
                <input type="text" name="nama_app" id="nama_app" class="w-full border border-gray-300 p-2 text-sm focus:border-cyan-500 outline-none rounded-sm" required placeholder="Contoh: E-Raport">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Link URL</label>
                <input type="url" name="link_url" id="link_url" class="w-full border border-gray-300 p-2 text-sm focus:border-cyan-500 outline-none rounded-sm" required placeholder="https://...">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Upload Ikon</label>
                <input type="file" name="gambar_file" id="gambar_file" accept="image/*" class="w-full border border-gray-300 p-2 text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 rounded-sm">
                <p class="text-[10px] text-gray-400 mt-1">*Format: JPG/PNG. Persegi 1:1.</p>
                <div id="current-img-info" class="hidden mt-2 text-xs text-blue-600 flex items-center gap-1">
                    <i class="ti ti-photo"></i> <span id="current-img-name">Gambar sudah ada</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Deskripsi Singkat</label>
                <textarea name="deskripsi" id="deskripsi" rows="2" class="w-full border border-gray-300 p-2 text-sm focus:border-cyan-500 outline-none rounded-sm"></textarea>
            </div>

            <div class="pt-4 border-t flex justify-end gap-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 rounded-sm">Batal</button>
                <button type="submit" class="px-4 py-2 bg-[#059fd3] text-white text-sm hover:bg-[#0487b4] shadow-md rounded-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>

<script>
// --- LOGIC JS ---
// CAN_ACTION: True jika Admin (Render tombol edit/hapus)
const CAN_ACTION = <?= $canAction ? 'true' : 'false'; ?>;
// CAN_CREATE: True jika Admin atau Staf (Buka modal)
const CAN_CREATE = <?= $canCreate ? 'true' : 'false'; ?>;

let APPS_DATA = [];

// --- 1. CRUD LOGIC ---

function loadApps() {
    const fd = new FormData();
    fd.append('action', 'list');
    
    fetch('portal_crud.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(text => {
            try {
                const res = JSON.parse(text);
                if(res.success) {
                    APPS_DATA = res.data;
                    renderApps(APPS_DATA);
                } else {
                    console.error("Server Error:", res.message);
                }
            } catch (e) {
                console.error("Invalid JSON:", text);
                document.getElementById('app-container').innerHTML = `<div class="col-span-full text-center text-red-500">Error: Gagal memuat data.</div>`;
            }
        })
        .catch(err => console.error("Fetch Error:", err));
}

function renderApps(data) {
    const container = document.getElementById('app-container');
    
    if(data.length === 0) {
        container.innerHTML = `<div class="col-span-full text-center py-10 text-gray-400 italic">Belum ada data aplikasi.</div>`;
        return;
    }

    container.innerHTML = data.map(app => {
        let bgUrl = 'https://via.placeholder.com/150/f1f5f9/94a3b8?text=MENU';
        if (app.gambar_url && app.gambar_url !== '') {
            bgUrl = app.gambar_url + '?t=' + new Date().getTime(); 
        }

        const jsonItem = encodeURIComponent(JSON.stringify(app));

        // LOGIC TOMBOL EDIT/HAPUS
        let adminBtns = '';
        if(CAN_ACTION) {
            adminBtns = `
            <div class="admin-actions">
                <button type="button" onclick="editApp('${jsonItem}', event)" class="btn-mini btn-edit" title="Edit"><i class="ti ti-pencil"></i></button>
                <button type="button" onclick="deleteApp(${app.id}, event)" class="btn-mini btn-del" title="Hapus"><i class="ti ti-trash"></i></button>
            </div>`;
        }

        return `
        <a href="${app.link_url}" target="_blank" class="app-item group" title="${app.deskripsi || app.nama_app}">
            <div class="app-box" style="background-image: url('${bgUrl}');">
                ${adminBtns}
                <div class="absolute inset-0 bg-black/5 group-hover:bg-transparent transition pointer-events-none"></div>
            </div>
            <div class="app-name">${app.nama_app}</div>
        </a>`;
    }).join('');
}

// Simpan Data
if (CAN_CREATE) {
    const formApp = document.getElementById('form-app');
    if (formApp) {
        formApp.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('portal_crud.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 1000, showConfirmButton: false });
                        closeModal();
                        loadApps();
                    } else {
                        Swal.fire('Gagal', res.message, 'error');
                    }
                });
        });
    }
}

// Hapus Data
function deleteApp(id, e) {
    e.preventDefault(); e.stopPropagation();
    if(!CAN_ACTION) {
        Swal.fire('Akses Ditolak', 'Hanya admin yang bisa menghapus.', 'error');
        return;
    }
    Swal.fire({
        title: 'Hapus Aplikasi?', text: "Data akan hilang permanen!", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('portal_crud.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(res => {
                    if(res.success) {
                        Swal.fire('Terhapus!', res.message, 'success');
                        loadApps();
                    }
                });
        }
    });
}

// Modal Logic
const modal = document.getElementById('modal-form');
const form = document.getElementById('form-app');

function openModal(mode) {
    if (!modal) return;
    modal.classList.remove('hidden'); modal.classList.add('flex');
    form.reset(); document.getElementById('current-img-info').classList.add('hidden');
    if(mode === 'create') {
        document.getElementById('form-action').value = 'store';
        document.getElementById('modal-title').innerText = 'TAMBAH APLIKASI';
    }
}

function editApp(jsonStr, e) {
    if(!CAN_ACTION) return; 
    e.preventDefault(); e.stopPropagation();
    const data = JSON.parse(decodeURIComponent(jsonStr));
    document.getElementById('form-id').value = data.id;
    document.getElementById('form-action').value = 'update';
    document.getElementById('nama_app').value = data.nama_app;
    document.getElementById('link_url').value = data.link_url;
    document.getElementById('deskripsi').value = data.deskripsi;
    document.getElementById('gambar_file').value = '';
    const imgInfo = document.getElementById('current-img-info');
    if (data.gambar_url) {
        imgInfo.classList.remove('hidden');
        document.getElementById('current-img-name').innerText = "Gambar sudah ada";
    } else { imgInfo.classList.add('hidden'); }
    document.getElementById('modal-title').innerText = 'EDIT APLIKASI';
    if(modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
}

function closeModal() {
    if(modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
}

document.getElementById('search-app').addEventListener('input', (e) => {
    const val = e.target.value.toLowerCase();
    const filtered = APPS_DATA.filter(item => item.nama_app.toLowerCase().includes(val));
    renderApps(filtered);
});
loadApps();
</script>