<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// Role Check
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

$currentPage = 'laporan_mingguan'; 
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
  /* --- Grid Container --- */
  .grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.25rem;
    padding-bottom: 2rem;
  }

  /* --- Widget Card Base Style --- */
  .widget-card {
    border-radius: 12px;
    position: relative;
    color: #ffffff; /* Teks Putih Bersih */
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    display: flex;
    flex-direction: column;
    min-height: 150px; /* Sedikit lebih tinggi untuk menampung judul besar */
    border: 1px solid rgba(255,255,255,0.1);
  }

  /* Efek Hover */
  .widget-card:hover {
    transform: translateY(-7px);
    box-shadow: 0 20px 35px rgba(0,0,0,0.35);
    z-index: 10;
  }

  /* Konten Utama (Atas) */
  .widget-body {
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center; /* Center vertikal */
    flex: 1;
    position: relative;
    z-index: 2;
  }

  /* === MODIFIKASI: IKON JELAS & BESAR === */
  .widget-icon {
    font-size: 5rem; /* Sangat Besar */
    color: rgba(255, 255, 255, 0.9); /* Putih Terang (Hampir Solid) */
    position: absolute;
    left: 0.5rem;
    bottom: 2.5rem; 
    transition: all 0.4s ease;
    z-index: 1;
  }
  
  .widget-card:hover .widget-icon {
    transform: scale(1.1) rotate(-8deg);
    color: #ffffff; /* Solid Putih saat hover */
  }

  /* Bagian Teks di Kanan */
  .widget-text {
    text-align: right;
    width: 100%;
    z-index: 3;
    margin-left: auto;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    padding-left: 4rem; /* Ruang agar tidak menabrak ikon besar */
  }

  /* === MODIFIKASI: ANGKA DATA DIKECILIN === */
  .widget-count {
    font-size: 1rem; /* Lebih kecil dari sebelumnya */
    font-weight: 200;
    line-height: 1;
    margin-bottom: 0.5rem;
    opacity: 0.9;
    order: 1; /* Pindah ke atas judul */
  }

  /* === MODIFIKASI: JUDUL DIPERBESAR === */
  .widget-title {
    font-size: 1.25rem; /* Jauh Lebih Besar */
    font-weight: 700; /* Lebih Tebal (Bold) */
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.1;
    opacity: 1; /* Sangat Jelas */
    text-shadow: 0 2px 5px rgba(0,0,0,0.3); /* Shadow agar lebih pop-out */
    order: 2; /* Pindah ke bawah angka */
    word-break: break-word; /* Agar text panjang turun ke bawah */
  }

  /* Footer (Bagian Bawah "More Info") */
  .widget-footer {
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(5px);
    padding: 0.75rem 1rem;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid rgba(255,255,255,0.15);
    z-index: 4;
  }
  
  .widget-desc {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 85%;
    font-style: italic;
    opacity: 0.85;
    color: #f1f5f9;
  }

  /* Badge Nomor */
  .widget-number {
    position: absolute;
    top: 0;
    left: 0;
    background: rgba(0,0,0,0.4);
    color: #fff;
    padding: 4px 12px;
    font-size: 0.75rem;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    border-bottom-right-radius: 10px;
    z-index: 5;
    border-right: 1px solid rgba(255,255,255,0.2);
    border-bottom: 1px solid rgba(255,255,255,0.2);
  }

  /* --- PALET WARNA GELAP (TETAP) --- */
  .theme-0 { background: linear-gradient(135deg, #1e3a8a, #9da1a9ff); } /* Deep Ocean */
  .theme-1 { background: linear-gradient(135deg, #064e3b, #b2bdbaff); } /* Dark Emerald */
  .theme-2 { background: linear-gradient(135deg, #4c1d95, #9e9ba3ff); } /* Royal Purple */
  .theme-3 { background: linear-gradient(135deg, #7f1d1d, #b3aaaaff); } /* Burnt Maroon */
  .theme-4 { background: linear-gradient(135deg, #334155, #9e9fa3ff); } /* Charcoal Slate */
  .theme-5 { background: linear-gradient(135deg, #78350f, #c4c2c1ff); } /* Deep Bronze */

  /* Tombol Aksi Floating */
  .widget-actions {
    position: absolute;
    top: 12px;
    right: -50px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: right 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    z-index: 20;
  }

  .widget-card:hover .widget-actions {
    right: 12px;
  }

  .action-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: none;
    background: #ffffff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 8px rgba(0,0,0,0.4);
    font-size: 1rem;
  }
  .btn-edit { color: #1e40af; }
  .btn-del { color: #991b1b; }
  .action-btn:hover { transform: scale(1.15); }

</style>

<div class="space-y-6">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="ti ti-dashboard text-slate-700"></i> Dashboard Arsip
            </h1>
            <p class="text-gray-500 text-sm mt-1">Pantau dokumen dan kategori arsip.</p>
        </div>

        <div class="flex gap-3">
            <?php if (!$isStaf): ?>
            <button id="btn-add-kategori" class="bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-900 flex items-center gap-2 shadow-sm transition-all font-medium text-sm">
                <i class="ti ti-plus"></i>
                <span>Tambah Kategori</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-200">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="ti ti-search text-gray-400"></i>
            </div>
            <input id="filter-q" type="text" class="w-full pl-10 pr-4 py-2 border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-slate-600 outline-none transition-all" placeholder="Cari kategori...">
        </div>
    </div>

    <div id="grid-kategori" class="grid-container">
        <div class="col-span-full text-center py-16 text-gray-400 border-2 border-dashed border-gray-200 rounded-xl">
            <i class="ti ti-loader animate-spin text-2xl mb-2 block"></i> Memuat data...
        </div>
    </div>

</div>

<?php if (!$isStaf): ?>
<div id="kategori-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-opacity">
    <div class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-lg transform scale-100 transition-transform">
        <div class="flex items-center justify-between mb-6 border-b pb-4">
            <h3 id="modal-title" class="text-xl font-bold text-gray-900">Buat Kategori Baru</h3>
            <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600 transition">&times;</button>
        </div>
        <form id="kategori-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
            <input type="hidden" name="action" id="form-action">
            <input type="hidden" name="id" id="form-id">

            <div class="space-y-4">
                <div>
                    <label for="nama_kategori" class="block text-xs font-bold text-gray-600 uppercase mb-1">Nama Kategori <span class="text-red-500">*</span></label>
                    <input type="text" id="nama_kategori" name="nama_kategori" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-700 outline-none" placeholder="Contoh: Laporan Bulanan" required>
                </div>
                <div>
                    <label for="keterangan" class="block text-xs font-bold text-gray-600 uppercase mb-1">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-slate-700 outline-none" placeholder="Deskripsi singkat..."></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8 pt-4 border-t">
                <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50 text-sm font-medium transition">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-slate-800 text-white hover:bg-slate-900 text-sm font-medium shadow-lg transition">Simpan</button>
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
    const API_URL    = 'arsip_crud.php'; 
    
    const gridContainer = document.getElementById('grid-kategori');
    const searchInput = document.getElementById('filter-q');
    
    let ALL_KATEGORI = [];

    const ICONS_LIST = [
        'ti-folder-filled', 'ti-files', 'ti-archive', 'ti-briefcase', 
        'ti-chart-pie-2', 'ti-clipboard-data', 'ti-box-multiple', 'ti-folder-star', 
        'ti-notebook', 'ti-stack-3', 'ti-report-analytics', 'ti-database'
    ];

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);
    }

    // --- FUNGSI BUILD CARD WIDGET ---
    function buildCardHTML(kategori, index) {
        const payload = encodeURIComponent(JSON.stringify(kategori || {}));
        const nama = htmlspecialchars(kategori.nama_kategori || '-');
        const ket = htmlspecialchars(kategori.keterangan || 'Tidak ada deskripsi');
        const jumlah = parseInt(kategori.jumlah_dokumen || 0);
        
        const themeClass = `theme-${index % 6}`;
        const iconClass = ICONS_LIST[index % ICONS_LIST.length];
        const linkDetail = `laporan_mingguan_detail.php?k_id=${kategori.id}`;

        let actionsHtml = '';
        if (!IS_STAF) {
            actionsHtml = `
            <div class="widget-actions">
                <button class="action-btn btn-edit btn-edit-kategori" data-json="${payload}" title="Edit" onclick="event.stopPropagation()">
                    <i class="ti ti-pencil"></i>
                </button>
                <button class="action-btn btn-del btn-delete-kategori" data-id="${kategori.id}" title="Hapus" onclick="event.stopPropagation()">
                    <i class="ti ti-trash"></i>
                </button>
            </div>`;
        }

        // Perhatikan urutan: widget-count dulu, baru widget-title agar sesuai CSS order
        return `
        <div class="widget-card ${themeClass}" onclick="window.location.href='${linkDetail}'">
            <div class="widget-number">NO. ${index + 1}</div>

            <div class="widget-body">
                <div class="widget-icon">
                    <i class="ti ${iconClass}"></i>
                </div>
                
                <div class="widget-text">
                    <div class="widget-count">${jumlah} Files</div>
                    <div class="widget-title">${nama}</div>
                </div>
            </div>

            <div class="widget-footer">
                <span class="widget-desc">${ket}</span>
                <i class="ti ti-arrow-right text-white"></i>
            </div>

            ${actionsHtml}
        </div>
        `;
    }

    // --- Render Logic ---
    function renderGrid(kategoriList) {
        if (!kategoriList || kategoriList.length === 0) {
            gridContainer.innerHTML = `
                <div class="col-span-full flex flex-col items-center justify-center py-16 text-gray-400 border-2 border-dashed border-gray-200 rounded-xl bg-gray-50">
                    <i class="ti ti-folder-off text-4xl mb-3 opacity-50"></i>
                    <p class="text-sm font-medium">Tidak ada kategori ditemukan.</p>
                </div>`;
            return;
        }
        gridContainer.innerHTML = kategoriList.map(buildCardHTML).join('');
        attachActionListeners();
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
        gridContainer.innerHTML = `
            <div class="col-span-full text-center py-16 text-gray-500">
                <i class="ti ti-loader animate-spin text-2xl mb-2 inline-block"></i><br>Sedang memuat data...
            </div>`;
        
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
                    gridContainer.innerHTML = `<div class="col-span-full text-center py-10 text-red-500 font-bold">${j.message || 'Gagal memuat data'}</div>`;
                }
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                gridContainer.innerHTML = `<div class="col-span-full text-center py-10 text-red-500">Error Koneksi Server</div>`;
            });
    }
    
    // --- Event Listeners ---
    function attachActionListeners() {
        if (IS_STAF) return;

        document.querySelectorAll('.btn-edit-kategori').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const row = JSON.parse(decodeURIComponent(btn.dataset.json));
                const form = $('#kategori-form');
                
                form.reset();
                $('#form-action').value = 'update';
                $('#form-id').value = row.id;
                $('#modal-title').textContent = 'Edit Kategori';
                $('#nama_kategori').value = row.nama_kategori || '';
                $('#keterangan').value = row.keterangan || '';
                
                const modal = $('#kategori-modal');
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
                    text: 'Kategori dan isinya akan dihapus.',
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
                }).catch(err => Swal.fire('Error', 'Gagal menyimpan', 'error'));
        });
    } 
    
    function $(s) { return document.querySelector(s); }
});
</script>