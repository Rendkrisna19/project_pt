<style>
    /* Table Wrapper agar scrollable */
    .table-container {
        overflow-x: auto;
        position: relative;
    }
    
    /* Sticky Column Logic */
    th.sticky-col, td.sticky-col {
        position: sticky;
        left: 0;
        z-index: 20;
        background-color: white; /* Penting agar tidak transparan */
        border-right: 2px solid #e5e7eb;
    }
    
    /* Sticky Header Logic (z-index lebih tinggi dari kolom) */
    thead th.sticky-col {
        z-index: 30; 
        background-color: #f9fafb; /* bg-gray-50 */
    }

    /* Pengaturan posisi masing-masing kolom yang di-freeze */
    .col-foto { left: 0px; width: 70px; }
    .col-sap  { left: 70px; width: 100px; }
    .col-old  { left: 170px; width: 100px; }
    .col-nama { left: 270px; width: 250px; } /* Batas Freeze */
    
    /* Shadow effect di kolom terakhir yang freeze */
    .col-nama {
        box-shadow: 4px 0 6px -2px rgba(0,0,0,0.1);
    }
    
    /* Hover effect fix untuk sticky rows */
    tr:hover td.sticky-col { background-color: #ecfeff; } /* bg-cyan-50 */
</style>

<div class="space-y-4">
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
        <div class="flex p-1 bg-gray-100 rounded-lg">
            <button onclick="switchView('active')" id="tab-active" class="px-4 py-2 text-sm font-bold rounded-md shadow bg-white text-cyan-700 transition">
                <i class="ti ti-users"></i> Karyawan Aktif
            </button>
            <button onclick="switchView('pension')" id="tab-pension" class="px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition">
                <i class="ti ti-user-off"></i> Monitoring Pensiun
            </button>
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
        <div class="flex flex-wrap gap-3 items-center w-full md:w-auto">
            <select id="limit" class="bg-gray-50 border border-gray-300 text-sm rounded-lg p-2 w-20">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            
            <select id="f_kebun" class="bg-gray-50 border border-gray-300 text-sm rounded-lg p-2 w-40">
                <option value="">Semua Kebun</option>
                </select>

            <select id="f_afdeling" class="bg-gray-50 border border-gray-300 text-sm rounded-lg p-2 w-40">
                <option value="">Semua Afdeling</option>
                </select>
        </div>

        <div class="relative w-full md:w-80">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
            <input type="text" id="q" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="Cari Nama, NIK, SAP ID...">
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col h-[65vh]">
        <div class="overflow-auto flex-1 custom-scrollbar table-container">
            <table class="w-full text-sm text-left text-gray-500 whitespace-nowrap">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 sticky top-0 z-30">
                    <tr>
                        <th class="px-4 py-3 text-center sticky-col col-foto">Foto</th>
                        <th class="px-4 py-3 sticky-col col-sap">SAP ID</th>
                        <th class="px-4 py-3 sticky-col col-old">Old Pers</th>
                        <th class="px-4 py-3 sticky-col col-nama">Nama Lengkap</th>
                        
                        <th class="px-4 py-3">Kebun</th> <th class="px-4 py-3">Afdeling</th>
                        <th class="px-4 py-3">Jabatan</th>
                        <th class="px-4 py-3">Status Tax</th> <th class="px-4 py-3 text-center">Dokumen</th> <th class="px-4 py-3">Pendidikan</th> <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">TMT Pensiun</th>
                        <th class="px-4 py-3 text-center sticky right-0 bg-gray-50 shadow-l z-20">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-data" class="divide-y divide-gray-100 text-gray-700">
                    </tbody>
            </table>
        </div>
        
        <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 flex justify-between items-center">
            <div class="text-xs text-gray-600">
                Data <span id="info-start" class="font-bold">0</span> - <span id="info-end" class="font-bold">0</span> dari <span id="info-total" class="font-bold">0</span>
            </div>
            <div id="pagination-controls" class="flex gap-1"></div>
        </div>
    </div>
</div>

<div id="import-modal" class="fixed inset-0 bg-gray-900/60 z-[60] hidden items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-cyan-700 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold flex items-center gap-2"><i class="ti ti-file-import"></i> Import Excel</h3>
            <button onclick="closeImportModal()"><i class="ti ti-x text-xl"></i></button>
        </div>
        <div class="p-6">
            <div class="bg-cyan-50 p-4 rounded text-sm text-cyan-800 mb-4">
                Download <a href="cetak/template_karyawan.php" class="font-bold underline">Template Terbaru</a> dengan kolom Pendidikan & Kebun.
            </div>
            <form id="form-import">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" value="import_excel_lib">
                <input type="file" name="file_excel" accept=".xlsx,.xls,.csv" class="block w-full text-sm border p-2 rounded mb-4" required>
                <button type="submit" class="w-full bg-cyan-600 text-white py-2 rounded-lg font-bold hover:bg-cyan-700">Proses Import</button>
            </form>
        </div>
    </div>
</div>

<?php if ($canInput): ?>
<div id="crud-modal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white w-full max-w-7xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh]">
            <div class="px-8 py-5 border-b flex justify-between items-center bg-gray-50 rounded-t-2xl">
                <h3 class="text-xl font-bold text-gray-800">Form Data Karyawan</h3>
                <button id="btn-close" class="text-gray-400 hover:text-red-500"><i class="ti ti-x text-2xl"></i></button>
            </div>
            
            <form id="crud-form" class="flex-1 overflow-y-auto p-8 grid grid-cols-1 md:grid-cols-4 gap-6">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="id" id="form-id">

                <div class="space-y-4">
                    <div class="text-center">
                        <div class="w-32 h-32 mx-auto bg-gray-100 rounded-full overflow-hidden border-4 border-white shadow relative group">
                            <img id="preview-foto" src="../assets/img/default-avatar.png" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/50 hidden group-hover:flex items-center justify-center text-white cursor-pointer" onclick="document.getElementById('foto_karyawan').click()">
                                <i class="ti ti-camera text-2xl"></i>
                            </div>
                        </div>
                        <input type="file" name="foto_karyawan" id="foto_karyawan" class="hidden" accept="image/*" onchange="previewImage(this)">
                        <p class="text-xs text-gray-500 mt-2">Klik foto untuk ubah</p>
                    </div>
                    
                    <div><label class="lbl">SAP ID *</label><input type="text" name="sap_id" id="sap_id" class="inp" required></div>
                    <div><label class="lbl">Old Pers No</label><input type="text" name="old_pers_no" id="old_pers_no" class="inp"></div>
                    <div><label class="lbl">Upload Dokumen (PDF/Doc)</label>
                        <input type="file" name="dokumen_file" id="dokumen_file" class="inp text-xs" accept=".pdf,.doc,.docx">
                        <div id="link-dokumen" class="text-xs mt-1"></div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div><label class="lbl">Nama Lengkap *</label><input type="text" name="nama_karyawan" id="nama_karyawan" class="inp" required></div>
                    <div><label class="lbl">NIK KTP</label><input type="text" name="nik_ktp" id="nik_ktp" class="inp" maxlength="16"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="lbl">Gender</label><select name="gender" id="gender" class="inp"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
                        <div><label class="lbl">Agama</label><select name="agama" id="agama" class="inp"><option value="Islam">Islam</option><option value="Kristen">Kristen</option></select></div>
                    </div>
                    <div><label class="lbl">Tempat Lahir</label><input type="text" name="tempat_lahir" id="tempat_lahir" class="inp"></div>
                    <div><label class="lbl">Tanggal Lahir</label><input type="date" name="tgl_lahir" id="tgl_lahir" class="inp"></div>
                    <div><label class="lbl">Status Tax (Ex: K/0)</label><input type="text" name="status_pajak" id="status_pajak" class="inp" placeholder="K/0, TK/0"></div>
                </div>

                <div class="space-y-4">
                    <div><label class="lbl">Kebun</label><select name="kebun_id" id="kebun_id" class="inp"><option value="">-Pilih Kebun-</option></select></div>
                    <div><label class="lbl">Afdeling</label><input type="text" name="afdeling" id="afdeling" class="inp"></div>
                    <div><label class="lbl">Jabatan Real</label><input type="text" name="jabatan_real" id="jabatan_real" class="inp"></div>
                    <div><label class="lbl">Jabatan SAP</label><input type="text" name="jabatan_sap" id="jabatan_sap" class="inp"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="lbl">Status</label><select name="status_karyawan" id="status_karyawan" class="inp"><option value="Tetap">Tetap</option><option value="Kontrak">Kontrak</option></select></div>
                        <div><label class="lbl">Grade</label><input type="text" name="person_grade" id="person_grade" class="inp"></div>
                    </div>
                    <div><label class="lbl">Status Keluarga</label><select name="status_keluarga" id="status_keluarga" class="inp"><option value="Menikah">Menikah</option><option value="Lajang">Lajang</option></select></div>
                </div>

                <div class="space-y-4">
                    <div><label class="lbl">Pendidikan Terakhir</label><select name="pendidikan_terakhir" id="pendidikan_terakhir" class="inp">
                        <option value="">-Pilih-</option><option value="SMA">SMA</option><option value="D3">D3</option><option value="S1">S1</option><option value="S2">S2</option>
                    </select></div>
                    <div><label class="lbl">Jurusan</label><input type="text" name="jurusan" id="jurusan" class="inp"></div>
                    <div><label class="lbl">Institusi</label><input type="text" name="institusi" id="institusi" class="inp"></div>
                    <hr class="border-gray-200">
                    <div><label class="lbl">TMT Kerja</label><input type="date" name="tmt_kerja" id="tmt_kerja" class="inp bg-blue-50"></div>
                    <div><label class="lbl">TMT MBT</label><input type="date" name="tmt_mbt" id="tmt_mbt" class="inp bg-orange-50"></div>
                    <div><label class="lbl">TMT Pensiun</label><input type="date" name="tmt_pensiun" id="tmt_pensiun" class="inp bg-red-50"></div>
                    <input type="hidden" name="no_rekening" id="no_rekening">
                    <input type="hidden" name="nama_bank" id="nama_bank">
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

<style>.lbl{display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:4px;}.inp{width:100%;border:1px solid #d1d5db;padding:6px;border-radius:6px;font-size:13px;}</style>

<script>
let currentPage = 1, perPage = 10, totalPages = 1, viewType = 'active';

document.addEventListener('DOMContentLoaded', () => {
    loadOptions(); // Load Kebun & Afdeling options
    loadData();

    // Event Listeners
    document.getElementById('limit').addEventListener('change', (e) => { perPage = e.target.value; currentPage=1; loadData(); });
    document.getElementById('f_kebun').addEventListener('change', () => { currentPage=1; loadData(); });
    document.getElementById('f_afdeling').addEventListener('change', () => { currentPage=1; loadData(); });
    document.getElementById('q').addEventListener('input', debounce(() => { currentPage=1; loadData(); }, 500));
    
    // Setup Modal Buttons
    if(document.getElementById('btn-add')) {
        document.getElementById('btn-add').onclick = () => openCrudModal('store');
        document.getElementById('btn-close').onclick = () => document.getElementById('crud-modal').classList.add('hidden');
        document.getElementById('btn-cancel').onclick = () => document.getElementById('crud-modal').classList.add('hidden');
        document.getElementById('btn-save').onclick = saveData;
    }
    
    // Form Import
    document.getElementById('form-import').addEventListener('submit', handleImport);
});

function switchView(type) {
    viewType = type;
    document.getElementById('tab-active').className = type === 'active' ? 'px-4 py-2 text-sm font-bold rounded-md shadow bg-white text-cyan-700 transition' : 'px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition';
    document.getElementById('tab-pension').className = type === 'pension' ? 'px-4 py-2 text-sm font-bold rounded-md shadow bg-white text-red-700 transition' : 'px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition';
    currentPage = 1;
    loadData();
}

async function loadOptions() {
    const fd = new FormData(); fd.append('action', 'list_options');
    const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
    const json = await res.json();
    if(json.success) {
        // Populate Kebun
        let htmlK = '<option value="">Semua Kebun</option>';
        json.kebun.forEach(k => htmlK += `<option value="${k.id}">${k.nama_kebun}</option>`);
        document.getElementById('f_kebun').innerHTML = htmlK;
        if(document.getElementById('kebun_id')) {
             let htmlForm = '<option value="">-Pilih-</option>';
             json.kebun.forEach(k => htmlForm += `<option value="${k.id}">${k.nama_kebun}</option>`);
             document.getElementById('kebun_id').innerHTML = htmlForm;
        }

        // Populate Afdeling Filter
        let htmlA = '<option value="">Semua Afdeling</option>';
        json.afdeling.forEach(a => htmlA += `<option value="${a}">${a}</option>`);
        document.getElementById('f_afdeling').innerHTML = htmlA;
    }
}

async function loadData() {
    const tbody = document.getElementById('tbody-data');
    tbody.innerHTML = '<tr><td colspan="15" class="text-center py-10">Memuat data...</td></tr>';
    
    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('view_type', viewType);
    fd.append('page', currentPage);
    fd.append('limit', perPage);
    fd.append('q', document.getElementById('q').value);
    fd.append('f_kebun', document.getElementById('f_kebun').value);
    fd.append('f_afdeling', document.getElementById('f_afdeling').value);

    const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
    const json = await res.json();

    if(json.success) {
        document.getElementById('info-start').innerText = ((currentPage-1)*perPage)+1;
        document.getElementById('info-end').innerText = Math.min(currentPage*perPage, json.total);
        document.getElementById('info-total').innerText = json.total;
        
        // Render Table
        if(json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="15" class="text-center py-10 text-gray-400">Tidak ada data.</td></tr>';
        } else {
            tbody.innerHTML = json.data.map(r => {
                const foto = r.foto_karyawan ? `../uploads/profil/${r.foto_karyawan}` : '../assets/img/default-avatar.png';
                const docIcon = r.dokumen_path ? `<a href="../uploads/dokumen/${r.dokumen_path}" target="_blank" class="text-blue-600 hover:underline"><i class="ti ti-file-check"></i> Ada</a>` : '<span class="text-gray-300">-</span>';
                const rowJson = encodeURIComponent(JSON.stringify(r));
                
                return `
                <tr class="hover:bg-cyan-50 border-b transition">
                    <td class="text-center p-2 sticky-col col-foto"><img src="${foto}" class="w-8 h-8 rounded-full object-cover mx-auto border"></td>
                    <td class="p-3 sticky-col col-sap font-mono text-xs">${r.sap_id}</td>
                    <td class="p-3 sticky-col col-old text-xs">${r.old_pers_no || '-'}</td>
                    <td class="p-3 sticky-col col-nama font-bold text-gray-800">${r.nama_karyawan}</td>
                    
                    <td class="p-3">${r.nama_kebun || '-'}</td>
                    <td class="p-3">${r.afdeling || '-'}</td>
                    <td class="p-3">${r.jabatan_real || '-'}</td>
                    <td class="p-3 text-center">${r.status_pajak}</td>
                    <td class="p-3 text-center">${docIcon}</td>
                    <td class="p-3">${r.pendidikan_terakhir || '-'}</td>
                    <td class="p-3"><span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">${r.status_karyawan}</span></td>
                    <td class="p-3 text-red-600 font-bold">${r.tmt_pensiun || '-'}</td>
                    <td class="p-3 text-center sticky right-0 bg-white shadow-l z-20">
                        <button onclick="editData('${rowJson}')" class="text-blue-600 hover:bg-blue-100 p-1 rounded"><i class="ti ti-pencil"></i></button>
                    </td>
                </tr>`;
            }).join('');
        }
        renderPagination(Math.ceil(json.total/perPage));
    }
}

function renderPagination(total) {
    let html = '';
    // Simplifikasi pagination 1, 2, 3...
    for(let i=1; i<=total; i++) {
        // Show current, first, last, and near pages
        if(i === 1 || i === total || (i >= currentPage-1 && i <= currentPage+1)) {
            let active = i===currentPage ? 'bg-cyan-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100';
            html += `<button onclick="currentPage=${i};loadData()" class="px-3 py-1 border ${active} rounded mx-1">${i}</button>`;
        }
    }
    document.getElementById('pagination-controls').innerHTML = html;
}

function openCrudModal(mode) {
    document.getElementById('crud-form').reset();
    document.getElementById('form-action').value = mode;
    document.getElementById('preview-foto').src = '../assets/img/default-avatar.png';
    document.getElementById('link-dokumen').innerHTML = '';
    document.getElementById('crud-modal').classList.remove('hidden');
}

window.editData = (jsonStr) => {
    const r = JSON.parse(decodeURIComponent(jsonStr));
    openCrudModal('update');
    document.getElementById('form-id').value = r.id;
    
    // Fill inputs
    const fields = ['sap_id','old_pers_no','nama_karyawan','nik_ktp','gender','tempat_lahir','tgl_lahir',
             'person_grade','phdp_golongan','status_keluarga','jabatan_sap','jabatan_real','afdeling', 'kebun_id',
             'status_karyawan','tmt_kerja','tmt_mbt','tmt_pensiun','status_pajak','pendidikan_terakhir','jurusan','institusi'];
    
    fields.forEach(id => {
        if(document.getElementById(id)) document.getElementById(id).value = r[id] || '';
    });

    if(r.foto_karyawan) document.getElementById('preview-foto').src = '../uploads/profil/' + r.foto_karyawan;
    if(r.dokumen_path) document.getElementById('link-dokumen').innerHTML = `<span class="text-green-600">File tersimpan: ${r.dokumen_path}</span>`;
};

async function saveData() {
    const fd = new FormData(document.getElementById('crud-form'));
    try {
        const res = await fetch('data_karyawan_crud.php', { method:'POST', body:fd });
        const json = await res.json();
        if(json.success) {
            document.getElementById('crud-modal').classList.add('hidden');
            Swal.fire('Berhasil', 'Data tersimpan', 'success');
            loadData();
        } else {
            Swal.fire('Gagal', json.message, 'error');
        }
    } catch(e) { console.error(e); }
}

function handleImport(e) {
    e.preventDefault();
    // Logic import sama seperti sebelumnya...
    // (Silakan gunakan logic import dari kode sebelumnya di sini)
    // Placeholder alert:
    alert("Gunakan form import yang sudah ada di kode sebelumnya");
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('preview-foto').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

function openImportModal(){ document.getElementById('import-modal').classList.remove('hidden'); document.getElementById('import-modal').classList.add('flex'); }
function closeImportModal(){ document.getElementById('import-modal').classList.add('hidden'); document.getElementById('import-modal').classList.remove('flex'); }

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => { clearTimeout(timeout); func(...args); };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>