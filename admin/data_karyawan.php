<?php
// pages/data_karyawan.php
// MODIFIKASI: Import Universal (XLSX, XLS, CSV) via Library

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

$userRole = $_SESSION['user_role'] ?? 'viewer';
$isAdmin  = ($userRole === 'admin');
$isStaf   = ($userRole === 'staf');

// Admin & Staf boleh Input/Import
$canInput = ($isAdmin || $isStaf);
$canAction= ($isAdmin); 

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$currentPage = 'data_karyawan';
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
  .sticky-container { max-height: 75vh; overflow: auto; border: 1px solid #cbd5e1; border-radius: 0.75rem; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
  table.table-grid { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 2000px; }
  table.table-grid th, table.table-grid td { padding: 0.75rem; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle; }
  table.table-grid thead th { position: sticky; top: 0; background: #0e7490; color: #fff; z-index: 10; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; height: 50px; }
  table.table-grid tbody tr:hover td { background-color: #ecfeff; } 
  .avatar-sm { width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 2px solid #e2e8f0; }
</style>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-cyan-900">Database Karyawan</h1>
            <p class="text-slate-500 text-sm">Kelola data personalia, jabatan, dan status.</p>
        </div>
        <div class="flex gap-2">
             <?php if ($canInput): ?>
            <button onclick="openImportModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium flex items-center gap-2 shadow-sm transition">
                <i class="ti ti-file-spreadsheet"></i> Import Excel
            </button>
            
            <button id="btn-add" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium flex items-center gap-2 shadow-sm transition">
                <i class="ti ti-plus"></i> Karyawan Baru
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
        <div class="relative w-full md:w-96">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
            <input type="text" id="q" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Cari Nama, NIK, atau Jabatan...">
        </div>
        <div class="text-sm text-gray-500" id="info-total">Memuat data...</div>
    </div>

    <div class="sticky-container">
        <table class="table-grid" id="table-karyawan">
            <thead>
                <tr>
                    <th class="text-center" style="width: 60px;">Foto</th>
                    <th>ID SAP</th>
                    <th>Nama Lengkap</th>
                    <th>Jabatan</th>
                    <th>Afdeling</th>
                    <th>Status</th>
                    <th>TMT Kerja</th>
                    <th>TMT Pensiun</th>
                    <th>No HP</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tbody-data" class="text-gray-700"></tbody>
        </table>
    </div>
</div>

<div id="import-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-[60] hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform scale-100 transition-all">
        <div class="bg-green-700 px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white flex items-center gap-2"><i class="ti ti-file-import"></i> Import Data Excel</h3>
            <button onclick="closeImportModal()" class="text-white hover:text-red-200"><i class="ti ti-x text-xl"></i></button>
        </div>
        <div class="p-6">
            <div class="mb-4 p-4 bg-green-50 rounded-lg border border-green-100 text-sm text-green-800">
                <strong>Panduan Import:</strong>
                <ol class="list-decimal ml-4 mt-1 space-y-1">
                    <li>Download <a href="cetak/template_karyawan.php" class="font-bold underline text-green-700 hover:text-green-900">Template Excel Terbaru</a>.</li>
                    <li>Isi data tanpa mengubah urutan kolom.</li>
                    <li>Simpan file (Bisa format <strong>.xlsx</strong>, <strong>.xls</strong>, atau <strong>.csv</strong>).</li>
                    <li>Upload file tersebut di bawah ini.</li>
                </ol>
            </div>
            <form id="form-import">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" value="import_excel_lib">
                
                <label class="block text-sm font-bold text-gray-700 mb-2">Pilih File Excel</label>
                <input type="file" name="file_excel" accept=".xlsx, .xls, .csv" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-green-100 file:text-green-700 hover:file:bg-green-200" required>
                
                <button type="submit" class="w-full mt-6 bg-green-600 text-white py-2 rounded-lg font-bold hover:bg-green-700 shadow-lg transition flex justify-center items-center gap-2">
                    <i class="ti ti-upload"></i> Proses Import
                </button>
            </form>
        </div>
    </div>
</div>

<?php if ($canInput): ?>
<div id="crud-modal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh]">
            <div class="px-8 py-5 border-b flex justify-between items-center bg-gray-50 rounded-t-2xl">
                <h3 id="modal-title" class="text-xl font-bold text-gray-800">Form Karyawan</h3>
                <button id="btn-close" class="text-gray-400 hover:text-red-500"><i class="ti ti-x text-2xl"></i></button>
            </div>
            
            <form id="crud-form" class="flex-1 overflow-y-auto p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="id" id="form-id">

                <div class="space-y-4">
                    <div class="text-center">
                        <div class="w-32 h-32 mx-auto bg-gray-100 rounded-full overflow-hidden border-4 border-white shadow-md relative group">
                            <img id="preview-foto" src="../assets/img/default-avatar.png" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/50 hidden group-hover:flex items-center justify-center text-white cursor-pointer" onclick="document.getElementById('foto_profil').click()">
                                <i class="ti ti-camera text-2xl"></i>
                            </div>
                        </div>
                        <input type="file" name="foto_profil" id="foto_profil" class="hidden" accept="image/*" onchange="previewImage(this)">
                        <p class="text-xs text-gray-500 mt-2">Klik gambar untuk ubah foto</p>
                    </div>

                    <div><label class="block text-xs font-bold text-gray-500 uppercase">ID SAP</label>
                    <input type="text" name="id_sap" id="id_sap" class="w-full border p-2 rounded text-sm font-bold" required></div>
                    
                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Old Pers. No</label>
                    <input type="text" name="old_pers_no" id="old_pers_no" class="w-full border p-2 rounded text-sm"></div>

                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="nama_lengkap" class="w-full border p-2 rounded text-sm" required></div>
                </div>

                <div class="space-y-4">
                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Jabatan Real</label>
                    <input type="text" name="jabatan_real" id="jabatan_real" class="w-full border p-2 rounded text-sm"></div>
                    
                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Afdeling</label>
                    <input type="text" name="afdeling" id="afdeling" class="w-full border p-2 rounded text-sm"></div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-xs font-bold text-gray-500 uppercase">Status</label>
                        <select name="status_karyawan" id="status_karyawan" class="w-full border p-2 rounded text-sm">
                            <option value="Tetap">Tetap</option>
                            <option value="Kontrak">Kontrak</option>
                            <option value="HL">HL</option>
                        </select></div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase">Grade</label>
                        <input type="text" name="person_grade" id="person_grade" class="w-full border p-2 rounded text-sm"></div>
                    </div>

                    <div><label class="block text-xs font-bold text-gray-500 uppercase">TMT Kerja</label>
                    <input type="date" name="tmt_kerja" id="tmt_kerja" class="w-full border p-2 rounded text-sm"></div>

                    <div><label class="block text-xs font-bold text-gray-500 uppercase">TMT Pensiun</label>
                    <input type="date" name="tmt_pensiun" id="tmt_pensiun" class="w-full border p-2 rounded text-sm bg-orange-50"></div>
                </div>

                <div class="space-y-4">
                    <div><label class="block text-xs font-bold text-gray-500 uppercase">NIK KTP</label>
                    <input type="text" name="nik_ktp" id="nik_ktp" class="w-full border p-2 rounded text-sm"></div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-xs font-bold text-gray-500 uppercase">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" id="tempat_lahir" class="w-full border p-2 rounded text-sm"></div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase">Tgl Lahir</label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="w-full border p-2 rounded text-sm"></div>
                    </div>

                    <div><label class="block text-xs font-bold text-gray-500 uppercase">No HP</label>
                    <input type="text" name="no_hp" id="no_hp" class="w-full border p-2 rounded text-sm"></div>

                    <div><label class="block text-xs font-bold text-gray-500 uppercase">Bank & No Rek</label>
                    <div class="flex gap-2">
                        <input type="text" name="nama_bank" id="nama_bank" class="w-1/3 border p-2 rounded text-sm" placeholder="Bank">
                        <input type="text" name="no_rekening" id="no_rekening" class="w-2/3 border p-2 rounded text-sm" placeholder="No Rek">
                    </div></div>
                </div>
            </form>

            <div class="px-8 py-5 border-t bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                <button type="button" id="btn-cancel" class="px-5 py-2 border rounded-lg hover:bg-gray-200">Batal</button>
                <button type="button" id="btn-save" class="px-5 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 shadow-lg">Simpan Data</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Logic Modal Import
function openImportModal(){ document.getElementById('import-modal').classList.remove('hidden'); document.getElementById('import-modal').classList.add('flex'); }
function closeImportModal(){ document.getElementById('import-modal').classList.add('hidden'); document.getElementById('import-modal').classList.remove('flex'); }

document.addEventListener('DOMContentLoaded', () => {
    const CAN_INPUT  = <?= $canInput ? 'true' : 'false'; ?>;
    const CAN_ACTION = <?= $canAction ? 'true' : 'false'; ?>;
    const CSRF       = '<?= $CSRF ?>';
    
    const tbody = document.getElementById('tbody-data');
    const q     = document.getElementById('q');

    // --- FETCH DATA ---
    async function loadData() {
        const query = q.value;
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-10">Memuat...</td></tr>';
        
        try {
            const fd = new FormData();
            fd.append('action', 'list');
            fd.append('q', query);
            
            const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
            const json = await res.json();

            if (json.success) {
                renderTable(json.data);
                document.getElementById('info-total').innerText = json.data.length + ' Karyawan ditemukan';
            } else {
                alert('Gagal: ' + json.message);
            }
        } catch (e) { console.error(e); }
    }

    function renderTable(data) {
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-10 text-gray-400">Tidak ada data.</td></tr>';
            return;
        }

        tbody.innerHTML = data.map(r => {
            const foto = r.foto_profil ? '../uploads/profil/' + r.foto_profil : '../assets/img/default-avatar.png';
            const rowJson = encodeURIComponent(JSON.stringify(r));

            // Tombol Aksi
            let btns = `<a href="export_cv.php?id=${r.id}" target="_blank" class="p-2 text-green-600 hover:bg-green-50 rounded" title="Cetak CV"><i class="ti ti-printer"></i></a>`;
            
            // Edit/Hapus hanya Admin
            if (CAN_ACTION) {
                btns += `
                <button onclick="editData('${rowJson}')" class="p-2 text-blue-600 hover:bg-blue-50 rounded" title="Edit"><i class="ti ti-pencil"></i></button>
                <button onclick="deleteData(${r.id})" class="p-2 text-red-600 hover:bg-red-50 rounded" title="Hapus"><i class="ti ti-trash"></i></button>
                `;
            }

            return `
            <tr class="hover:bg-cyan-50 border-b transition">
                <td class="text-center p-2"><img src="${foto}" class="avatar-sm mx-auto shadow-sm"></td>
                <td class="p-3 font-mono text-xs text-slate-600">${r.id_sap}</td>
                <td class="p-3 font-bold text-gray-800">${r.nama_lengkap}</td>
                <td class="p-3 text-sm">${r.jabatan_real || '-'}</td>
                <td class="p-3 text-sm">${r.afdeling || '-'}</td>
                <td class="p-3 text-sm"><span class="bg-cyan-100 text-cyan-800 px-2 py-0.5 rounded text-xs font-bold">${r.status_karyawan || '-'}</span></td>
                <td class="p-3 text-sm text-slate-500">${r.tmt_kerja || '-'}</td>
                <td class="p-3 text-sm text-orange-600 font-bold">${r.tmt_pensiun || '-'}</td>
                <td class="p-3 text-sm text-slate-500">${r.no_hp || '-'}</td>
                <td class="p-3 text-center flex justify-center gap-1">${btns}</td>
            </tr>`;
        }).join('');
    }

    q.addEventListener('input', () => { clearTimeout(window.t); window.t = setTimeout(loadData, 300); });
    loadData();

    // --- FORM IMPORT ---
    document.getElementById('form-import').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        
        const btn = e.target.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Memproses...';
        btn.disabled = true;

        try {
            const res = await fetch('data_karyawan_crud.php', { method:'POST', body:fd });
            const json = await res.json();
            
            if (json.success) {
                closeImportModal();
                Swal.fire('Import Berhasil', json.message, 'success');
                loadData();
            } else {
                Swal.fire('Gagal Import', json.message, 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'Terjadi kesalahan server', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // --- CRUD LOGIC ---
    if (CAN_INPUT) {
        const modal = document.getElementById('crud-modal');
        const form = document.getElementById('crud-form');
        const preview = document.getElementById('preview-foto');

        window.previewImage = function(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => preview.src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }

        document.getElementById('btn-add').onclick = () => {
            form.reset();
            document.getElementById('form-action').value = 'store';
            document.getElementById('form-id').value = '';
            preview.src = '../assets/img/default-avatar.png';
            modal.classList.remove('hidden');
        };

        document.getElementById('btn-close').onclick = () => modal.classList.add('hidden');
        document.getElementById('btn-cancel').onclick = () => modal.classList.add('hidden');

        document.getElementById('btn-save').onclick = async () => {
            const fd = new FormData(form);
            try {
                const res = await fetch('data_karyawan_crud.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    modal.classList.add('hidden');
                    Swal.fire('Berhasil', 'Data tersimpan', 'success');
                    loadData();
                } else {
                    Swal.fire('Gagal', json.message, 'error');
                }
            } catch (e) { alert('Error: ' + e); }
        };

        window.editData = (jsonStr) => {
            const r = JSON.parse(decodeURIComponent(jsonStr));
            form.reset();
            document.getElementById('form-action').value = 'update';
            document.getElementById('form-id').value = r.id;
            
            ['id_sap','old_pers_no','nama_lengkap','jabatan_real','afdeling','status_karyawan','person_grade',
             'tmt_kerja','tmt_pensiun','nik_ktp','tempat_lahir','tanggal_lahir','no_hp','nama_bank','no_rekening'
            ].forEach(id => {
                if(document.getElementById(id)) document.getElementById(id).value = r[id] || '';
            });

            if (r.foto_profil) preview.src = '../uploads/profil/' + r.foto_profil;
            else preview.src = '../assets/img/default-avatar.png';

            modal.classList.remove('hidden');
        };

        window.deleteData = (id) => {
            Swal.fire({title:'Hapus?', icon:'warning', showCancelButton:true, confirmButtonText:'Ya', confirmButtonColor:'#d33'})
            .then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'delete');
                    fd.append('id', id);
                    fd.append('csrf_token', CSRF);
                    fetch('data_karyawan_crud.php', {method:'POST', body:fd})
                    .then(r => r.json()).then(j => {
                        if(j.success) { Swal.fire('Terhapus','','success'); loadData(); }
                        else Swal.fire('Gagal', j.message, 'error');
                    });
                }
            })
        };
    }
});
</script>