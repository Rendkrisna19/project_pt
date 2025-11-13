<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// Role (asumsikan admin/non-staf bisa kelola kategori)
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

// Ganti $currentPage jadi 'laporan_mingguan' agar menu aktif
$currentPage = 'laporan_mingguan'; 
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">🗂️ Arsip Dokumen</h1>
            <p class="text-gray-500 mt-1">Kelola kategori (folder) arsip Anda.</p>
        </div>

        <div class="flex gap-3">
            <?php if (!$isStaf): ?>
            <button id="btn-add-kategori" class="bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 flex items-center gap-2">
                <i class="ti ti-folder-plus"></i>
                <span>Buat Kategori Baru</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-sm border">
        <label for="filter-q" class="block text-sm text-gray-600 mb-1">Pencarian Kategori</label>
        <input id="filter-q" type="text" class="w-full border rounded-lg px-3 py-2 text-gray-800" placeholder="Ketik nama kategori untuk mencari...">
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="py-3 px-4 text-left w-16">No</th>
                        <th class="py-3 px-4 text-left">Nama Dokumen (Kategori)</th>
                        <th class="py-3 px-4 text-left">Keterangan (Jumlah Dokumen)</th>
                        <th class="py-3 px-4 text-center w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-kategori" class="text-gray-800">
                    <tr><td colspan="4" class="text-center py-8 text-gray-500">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!$isStaf): ?>
<div id="kategori-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-lg">
        <div class="flex items-center justify-between mb-4">
            <h3 id="modal-title" class="text-xl font-bold text-gray-900">Buat Kategori Baru</h3>
            <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800" aria-label="Tutup">&times;</button>
        </div>
        <form id="kategori-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
            <input type="hidden" name="action" id="form-action">
            <input type="hidden" name="id" id="form-id">

            <div class="mt-4">
                <label for="nama_kategori" class="block text-sm mb-1">Nama Dokumen (Kategori) <span class="text-red-500">*</span></label>
                <input type="text" id="nama_kategori" name="nama_kategori" class="w-full border rounded px-3 py-2 text-gray-800" placeholder="Contoh: Laporan Bulanan" required>
            </div>
            
            <div class="mt-4">
                <label for="keterangan" class="block text-sm mb-1">Keterangan (Opsional)</label>
                <textarea id="keterangan" name="keterangan" rows="3" class="w-full border rounded px-3 py-2 text-gray-800" placeholder="Penjelasan singkat mengenai isi kategori ini..."></textarea>
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
    const CSRF_TOKEN = '<?= htmlspecialchars($CSRF) ?>';
    
    // ==========================================================
    // PERBAIKAN: API_URL harus menunjuk ke backend KATEGORI
    // ==========================================================
    const API_URL    = 'arsip_crud.php'; 
    
    const tbody = document.getElementById('tbody-kategori');
    const searchInput = document.getElementById('filter-q');
    
    let ALL_KATEGORI = [];

    function buildRowHTML(kategori, index) {
        const payload = encodeURIComponent(JSON.stringify(kategori || {}));
        const nama = htmlspecialchars(kategori.nama_kategori || '-');
        const ket = htmlspecialchars(kategori.keterangan || '');
        const jumlah = parseInt(kategori.jumlah_dokumen || 0);

        // ==========================================================
        // PERBAIKAN: Link "Buka" harus ke 'laporan_mingguan_detail.php'
        // ==========================================================
        let aksiHtml = `
            <a href="laporan_mingguan_detail.php?k_id=${kategori.id}" class="bg-emerald-600 text-white px-3 py-2 rounded-lg hover:bg-emerald-700 text-xs inline-flex items-center gap-1">
                <i class="ti ti-folder-open"></i> Buka
            </a>`;
        
        if (!IS_STAF) {
            aksiHtml += `
                <button class="btn-edit-kategori btn-icon text-blue-600 hover:text-blue-800" data-json="${payload}" title="Edit Kategori">
                    <i class="ti ti-pencil text-lg"></i>
                </button>
                <button class="btn-delete-kategori btn-icon text-red-600 hover:text-red-800" data-id="${kategori.id}" title="Hapus Kategori">
                    <i class="ti ti-trash text-lg"></i>
                </button>
            `;
        }

        return `
            <tr class="border-b hover:bg-gray-50" data-nama="${nama.toLowerCase()}">
                <td class="py-3 px-4">${index + 1}</td>
                <td class="py-3 px-4 font-semibold">${nama}</td>
                <td class="py-3 px-4">
                    <span class="bg-gray-200 text-gray-800 px-2 py-1 rounded-full text-xs font-medium">
                        ${jumlah} Dokumen
                    </span>
                    ${ket ? `<p class="text-gray-500 text-xs mt-1">${ket}</p>` : ''}
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center justify-center gap-2">
                        ${aksiHtml}
                    </div>
                </td>
            </tr>`;
    }

    function renderTable(kategoriList) {
        if (!kategoriList || kategoriList.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-8 text-gray-500">Belum ada kategori. ${!IS_STAF ? 'Klik "Buat Kategori Baru" untuk memulai.' : ''}</td></tr>`;
            return;
        }
        tbody.innerHTML = kategoriList.map(buildRowHTML).join('');
    }

    function applySearchFilter() {
        const query = searchInput.value.trim().toLowerCase();
        if (!query) {
            renderTable(ALL_KATEGORI);
            return;
        }
        const filtered = ALL_KATEGORI.filter(k => 
            (k.nama_kategori || '').toLowerCase().includes(query) ||
            (k.keterangan || '').toLowerCase().includes(query)
        );
        renderTable(filtered);
    }
    
    function refreshKategoriList() {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-8 text-gray-500">Memuat data...</td></tr>`;
        
        const fd = new FormData();
        fd.append('action', 'list');
        fd.append('csrf_token', CSRF_TOKEN);

        fetch(API_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
                if (j.success && Array.isArray(j.data)) {
                    ALL_KATEGORI = j.data;
                    applySearchFilter(); 
                } else {
                    // Jika error (misal token '<'), ini akan tampil
                    tbody.innerHTML = `<tr><td colspan="4" class="text-center py-8 text-red-500">${j.message || 'Gagal memuat data (format JSON tidak valid)'}</td></tr>`;
                }
            })
            .catch(err => {
                // Ini adalah tempat error 'Unexpected token <' ditangkap
                console.error('Fetch Error:', err);
                tbody.innerHTML = `<tr><td colspan="4" class="text-center py-8 text-red-500">Error: ${err.message || 'Network error'}. Pastikan API_URL benar.</td></tr>`;
            });
    }

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);
    }
    
    // Init
    refreshKategoriList();
    searchInput.addEventListener('input', applySearchFilter);

    // Modal Logic (hanya jika bukan Staf)
    if (!IS_STAF) {
        const modal = $('#kategori-modal');
        const btnClose = $('#btn-close');
        const btnCancel = $('#btn-cancel');
        const form = $('#kategori-form');
        const title = $('#modal-title');
        
        const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
        const close= () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

        $('#btn-add-kategori').addEventListener('click', () => {
            form.reset();
            $('#form-action').value = 'store';
            $('#form-id').value = '';
            title.textContent = 'Buat Kategori Baru';
            open();
        });
        
        btnClose.addEventListener('click', close);
        btnCancel.addEventListener('click', close);

        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;

            if (btn.classList.contains('btn-edit-kategori')) {
                const row = JSON.parse(decodeURIComponent(btn.dataset.json));
                form.reset();
                $('#form-action').value = 'update';
                $('#form-id').value = row.id;
                title.textContent = 'Edit Kategori';
                $('#nama_kategori').value = row.nama_kategori || '';
                $('#keterangan').value = row.keterangan || '';
                open();
            }

            if (btn.classList.contains('btn-delete-kategori')) {
                const id = btn.dataset.id;
                Swal.fire({
                    title: 'Hapus Kategori?',
                    text: 'Menghapus kategori TIDAK akan menghapus file di dalamnya (file akan jadi "tanpa kategori"). Yakin?',
                    icon: 'warning',
                    showCancelButton: true, confirmButtonColor: '#d33',
                    confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal'
                }).then((res) => {
                    if (!res.isConfirmed) return;
                    
                    const fd = new FormData();
                    fd.append('csrf_token', CSRF_TOKEN);
                    fd.append('action', 'delete');
                    fd.append('id', id);
                    
                    fetch(API_URL, { method: 'POST', body: fd })
                        .then(r => r.json()).then(j => {
                            if (j.success) {
                                Swal.fire('Terhapus!', j.message, 'success');
                                refreshKategoriList();
                            } else {
                                Swal.fire('Gagal', j.message, 'error');
                            }
                        }).catch(err => Swal.fire('Error', err.message, 'error'));
                });
            }
        });
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!$('#nama_kategori').value) {
                Swal.fire('Validasi', 'Nama Kategori wajib diisi.', 'warning');
                return;
            }
            
            const fd = new FormData(form);
            fetch(API_URL, { method: 'POST', body: fd })
                .then(r => r.json()).then(j => {
                    if (j.success) {
                        close();
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: j.message, timer: 1400, showConfirmButton: false });
                        refreshKategoriList();
                    } else {
                        Swal.fire('Gagal', j.message, 'error');
                    }
                }).catch(err => Swal.fire('Error', err.message, 'error'));
        });
    } // end if (!IS_STAF)
    
    function $(s) { return document.querySelector(s); }
});
</script>