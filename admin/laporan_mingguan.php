<?php
// pages/laporan_mingguan.php

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php"); exit;
}

// --- SETUP ROLE ---
$userRole = $_SESSION['user_role'] ?? 'viewer'; 

// Definisi Boolean
$isAdmin   = ($userRole === 'admin');
$canInput  = ($isAdmin); 
$canAction = ($isAdmin); 

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

$currentPage = 'laporan_mingguan'; 
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  /* --- WINDOWS 11 FOLDER STYLE --- */
  
  /* Container Grid - Diatur agar mulai dari kiri (flex-start) */
  .win-folder-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 1.5rem;
    padding-top: 1rem;
    padding-bottom: 2rem;
  }

  /* Item Folder */
  .win-folder {
    width: 110px;
    padding: 12px 8px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    border: 1px solid transparent;
    position: relative;
    transition: all 0.1s ease-in-out;
  }

  .win-folder:hover {
    background-color: #e5f3ff; 
    border-color: #d8eafe;
  }

  /* Ikon Folder Utama */
  .win-folder-icon {
    font-size: 4.8rem;
    line-height: 1;
    margin-bottom: 8px;
    position: relative;
    transition: transform 0.2s;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .win-folder:hover .win-folder-icon {
    transform: scale(1.05);
  }

  /* Ikon kecil di dalam folder */
  .folder-inner-icon {
    position: absolute;
    top: 40%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 2;
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.95);
    text-shadow: 0 1px 2px rgba(0,0,0,0.25);
    pointer-events: none;
  }

  /* Efek tumpukan kertas putih */
  .win-folder-icon::before {
    content: '';
    position: absolute;
    top: 15%;
    width: 38%;
    height: 18%;
    background: rgba(255,255,255,0.7);
    border-radius: 2px;
    z-index: 1;
  }

  /* Variasi Warna Gradient Ikon Folder */
  .folder-blue i   { background: linear-gradient(180deg, #5db2ff 0%, #1a85ed 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 4px 4px rgba(26,133,237,0.25)); }
  .folder-green i  { background: linear-gradient(180deg, #4ad99a 0%, #15a567 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 4px 4px rgba(21,165,103,0.25)); }
  .folder-gray i   { background: linear-gradient(180deg, #a6b0bd 0%, #707b8a 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 4px 4px rgba(112,123,138,0.25)); }
  .folder-purple i { background: linear-gradient(180deg, #b975f8 0%, #8531d1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 4px 4px rgba(133,49,209,0.25)); }
  .folder-orange i { background: linear-gradient(180deg, #ffa85c 0%, #e6640c 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 4px 4px rgba(230,100,12,0.25)); }
  .folder-cyan i   { background: linear-gradient(180deg, #4de6e6 0%, #0ab3b3 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; filter: drop-shadow(0 4px 4px rgba(10,179,179,0.25)); }

  /* Teks Nama Folder (terpotong 2 baris) */
  .win-folder-name {
    font-size: 0.8rem;
    color: #202020;
    text-align: center;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2; 
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
    font-weight: 500;
    width: 100%;
    height: 1.9rem; /* Menjaga barisan tetap sejajar */
  }

  .win-folder-count {
    font-size: 0.68rem;
    color: #6b7280;
    margin-top: 4px;
  }

  /* Tombol Aksi */
  .win-folder-actions {
    position: absolute;
    top: 4px;
    right: 4px;
    display: none;
    flex-direction: column;
    gap: 4px;
    background: rgba(255, 255, 255, 0.95);
    padding: 4px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    border: 1px solid #e5e7eb;
    z-index: 10;
  }

  .win-folder:hover .win-folder-actions { display: flex; }

  .action-btn-sm {
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    cursor: pointer;
    background: transparent;
    border: none;
    transition: background 0.2s;
  }
  .action-btn-sm:hover { background: #f3f4f6; }
  .action-btn-sm.edit-btn { color: #2563eb; }
  .action-btn-sm.del-btn { color: #dc2626; }
</style>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-center bg-white p-6 rounded-xl border border-gray-200 shadow-sm relative overflow-hidden">
        <div class="relative z-10 w-full md:w-2/3">
            <h1 class="text-2xl font-bold text-[#144f7a]">File Explorer</h1>
            <p class="text-gray-500 text-sm mt-1 mb-4">Pusat data arsip, laporan mingguan, dan pengelolaan dokumen operasional.</p>
            
            <?php if ($canInput): ?>
            <button id="btn-add-kategori" class="bg-[#0f4c75] hover:bg-[#1b6b9e] text-white px-5 py-2.5 rounded shadow-md flex items-center gap-2 text-sm font-semibold transition-all">
                <i class="ti ti-folder-plus text-lg"></i> Tambah Kategori
            </button>
            <?php endif; ?>
        </div>

        <div class="hidden md:flex w-1/3 justify-end relative z-10 pr-4">
            <img src="../assets/images/arsip-assets.png" alt="Ilustrasi" class="h-32 object-contain drop-shadow-md">
        </div>
        
        <div class="absolute right-0 top-0 bottom-0 w-1/2 bg-gradient-to-l from-blue-50/50 to-transparent z-0"></div>
    </div>

    <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-200">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="ti ti-search text-gray-400"></i>
            </div>  
            <input id="filter-q" type="text" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 focus:ring-2 focus:ring-[#0f4c75] focus:border-[#0f4c75] outline-none transition-all shadow-sm" placeholder="Cari berdasarkan nama folder atau deskripsi...">
        </div>
    </div>

    <div id="grid-kategori" class="win-folder-container">
        <div class="w-full text-center py-16 text-gray-400 border-2 border-dashed border-gray-200 rounded-xl">
            <i class="ti ti-loader animate-spin text-2xl mb-2 block"></i> Memuat data...
        </div>
    </div>

</div>

<?php if ($canInput): ?>
<div id="kategori-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-lg border border-gray-100">
        <div class="flex items-center justify-between mb-5 border-b border-gray-100 pb-4">
            <h3 id="modal-title" class="text-xl font-bold text-[#144f7a]">Buat Kategori Baru</h3>
            <button id="btn-close" class="text-2xl text-gray-400 hover:text-red-500 transition">&times;</button>
        </div>
        <form id="kategori-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
            <input type="hidden" name="action" id="form-action">
            <input type="hidden" name="id" id="form-id">

            <div class="space-y-4">
                <div>
                    <label for="nama_kategori" class="block text-xs font-bold text-gray-600 uppercase mb-1">Nama Kategori <span class="text-red-500">*</span></label>
                    <input type="text" id="nama_kategori" name="nama_kategori" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:border-[#0f4c75] focus:ring-1 focus:ring-[#0f4c75] outline-none shadow-sm transition-all" placeholder="Contoh: Laporan Bulanan" required>
                </div>
                <div>
                    <label for="keterangan" class="block text-xs font-bold text-gray-600 uppercase mb-1">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:border-[#0f4c75] focus:ring-1 focus:ring-[#0f4c75] outline-none shadow-sm transition-all" placeholder="Deskripsi singkat..."></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4">
                <button type="button" id="btn-cancel" class="px-5 py-2 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium transition">Batal</button>
                <button type="submit" class="px-5 py-2 rounded bg-[#0f4c75] hover:bg-[#1b6b9e] text-white text-sm font-medium shadow-md transition">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const CAN_ACTION = <?= $canAction ? 'true' : 'false'; ?>; 
    const CAN_INPUT  = <?= $canInput ? 'true' : 'false'; ?>;
    const CSRF_TOKEN = '<?= htmlspecialchars($CSRF) ?>';
    const API_URL    = 'arsip_crud.php'; 
    
    const gridContainer = document.getElementById('grid-kategori');
    const searchInput = document.getElementById('filter-q');
    
    let ALL_KATEGORI = [];
    const FOLDER_COLORS = ['folder-blue', 'folder-green', 'folder-gray', 'folder-purple', 'folder-orange', 'folder-cyan'];

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);
    }

    // --- LOGIKA IKON DALAM FOLDER BERDASARKAN NAMA ---
    function getInnerFolderIcon(folderName) {
        const name = folderName.toLowerCase();
        
        if (name.includes('buku') || name.includes('mandor')) return 'ti-book';
        if (name.includes('surat') || name.includes('memo') || name.includes('se')) return 'ti-mail';
        if (name.includes('keuangan') || name.includes('biaya') || name.includes('rkap')) return 'ti-cash';
        if (name.includes('tanaman') || name.includes('plant')) return 'ti-plant-2';
        if (name.includes('hama') || name.includes('penyakit')) return 'ti-bug';
        if (name.includes('foto') || name.includes('gambar')) return 'ti-photo';
        if (name.includes('produksi')) return 'ti-chart-bar';
        if (name.includes('personalia') || name.includes('hrd')) return 'ti-users';
        if (name.includes('peta') || name.includes('map')) return 'ti-map-2';
        
        // Default jika tidak ada yang cocok
        return 'ti-file-description'; 
    }

    // --- FUNGSI BUILD CARD ---
    function buildCardHTML(kategori, index) {
        const payload = encodeURIComponent(JSON.stringify(kategori || {}));
        const nama = htmlspecialchars(kategori.nama_kategori || '-');
        const ket = htmlspecialchars(kategori.keterangan || '');
        const jumlah = parseInt(kategori.jumlah_dokumen || 0);
        
        // Title ini akan muncul secara FULL ketika cursor diam (hover) pada folder
        const tooltipFullText = `${kategori.nama_kategori || '-'}${ket ? '\nDeskripsi: ' + ket : ''}`;
        
        const colorClass = FOLDER_COLORS[index % FOLDER_COLORS.length];
        const innerIcon = getInnerFolderIcon(nama);
        const linkDetail = `laporan_mingguan_detail.php?k_id=${kategori.id}`;

        let actionsHtml = '';
        if (CAN_ACTION) {
            actionsHtml = `
            <div class="win-folder-actions" onclick="event.stopPropagation()">
                <button type="button" class="action-btn-sm edit-btn btn-edit-kategori" data-json="${payload}" title="Edit Kategori">
                    <i class="ti ti-pencil"></i>
                </button>
                <button type="button" class="action-btn-sm del-btn btn-delete-kategori" data-id="${kategori.id}" title="Hapus Kategori">
                    <i class="ti ti-trash"></i>
                </button>
            </div>`;
        }

        return `
        <div class="win-folder" onclick="window.location.href='${linkDetail}'" title="${tooltipFullText}">
            <div class="win-folder-icon ${colorClass}">
                <i class="ti ti-folder-filled"></i>
                <div class="folder-inner-icon">
                    <i class="ti ${innerIcon}"></i>
                </div>
            </div>
            
            <div class="win-folder-name">${nama}</div>
            <div class="win-folder-count">${jumlah} Files</div>

            ${actionsHtml}
        </div>
        `;
    }

    function renderGrid(kategoriList) {
        if (!kategoriList || kategoriList.length === 0) {
            gridContainer.innerHTML = `
                <div class="w-full flex flex-col items-center justify-center py-16 text-gray-400 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50">
                    <i class="ti ti-folder-off text-4xl mb-3 opacity-50"></i>
                    <p class="text-sm font-medium">Tidak ada folder yang ditemukan.</p>
                </div>`;
            return;
        }
        gridContainer.innerHTML = kategoriList.map(buildCardHTML).join('');
        if(CAN_ACTION) attachActionListeners();
    }

    function applySearchFilter() {
        const query = searchInput.value.trim().toLowerCase();
        if (!query) {
            renderGrid(ALL_KATEGORI);
            return;
        }
        const filtered = ALL_KATEGORI.filter(k => 
            (k.nama_kategori || '').toLowerCase().includes(query) ||
            (k.keterangan || '').toLowerCase().includes(query)
        );
        renderGrid(filtered);
    }
    
    function refreshKategoriList() {
        gridContainer.innerHTML = `<div class="w-full text-center py-16 text-gray-500"><i class="ti ti-loader animate-spin text-2xl mb-2 inline-block"></i><br>Memuat...</div>`;
        
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
                    gridContainer.innerHTML = `<div class="w-full text-center py-10 text-red-500 font-bold">${j.message || 'Gagal memuat data'}</div>`;
                }
            })
            .catch(err => {
                gridContainer.innerHTML = `<div class="w-full text-center py-10 text-red-500">Error Koneksi Server</div>`;
            });
    }
    
    // --- Event Listeners Action (Edit/Delete) ---
    function attachActionListeners() {
        if (!CAN_ACTION) return;

        document.querySelectorAll('.btn-edit-kategori').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const row = JSON.parse(decodeURIComponent(btn.dataset.json));
                const form = document.getElementById('kategori-form');
                
                form.reset();
                document.getElementById('form-action').value = 'update';
                document.getElementById('form-id').value = row.id;
                document.getElementById('modal-title').textContent = 'Edit Kategori';
                document.getElementById('nama_kategori').value = row.nama_kategori || '';
                document.getElementById('keterangan').value = row.keterangan || '';
                
                const modal = document.getElementById('kategori-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });

        document.querySelectorAll('.btn-delete-kategori').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                Swal.fire({
                    title: 'Hapus Folder?',
                    text: 'Folder beserta isinya akan terhapus.',
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
                        }).catch(err => Swal.fire('Error', 'Koneksi error', 'error'));
                });
            });
        });
    }

    // --- Init ---
    refreshKategoriList();
    searchInput.addEventListener('input', applySearchFilter);

    // --- Modal Logic ---
    if (CAN_INPUT) {
        const modal = document.getElementById('kategori-modal');
        const openModal = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
        const closeModal= () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

        const btnAdd = document.getElementById('btn-add-kategori');
        if (btnAdd) {
            btnAdd.addEventListener('click', () => {
                document.getElementById('kategori-form').reset();
                document.getElementById('form-action').value = 'store';
                document.getElementById('form-id').value = '';
                document.getElementById('modal-title').textContent = 'Buat Kategori Baru';
                openModal();
            });
        }
        
        document.getElementById('btn-close').addEventListener('click', closeModal);
        document.getElementById('btn-cancel').addEventListener('click', closeModal);
        
        document.getElementById('kategori-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            fetch(API_URL, { method: 'POST', body: fd })
                .then(r => r.json()).then(j => {
                    if (j.success) {
                        closeModal();
                        refreshKategoriList();
                    } else {
                        Swal.fire('Gagal', j.message, 'error');
                    }
                });
        });
    } 
});
</script>