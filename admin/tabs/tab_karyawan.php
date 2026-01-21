<style>
    /* Container Tabel agar bisa di-scroll horizontal & vertikal */
    .table-container {
        max-height: 70vh; /* Sesuaikan tinggi tabel */
        overflow: auto;
        position: relative;
        border: 1px solid #cbd5e1;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }

    /* Style Dasar Tabel */
    table.table-grid {
        width: 100%;
        border-collapse: separate; /* Penting untuk sticky header */
        border-spacing: 0;
        min-width: 2500px; /* Lebar minimum agar scroll horizontal muncul */
    }

    table.table-grid th, 
    table.table-grid td {
        padding: 0.75rem;
        font-size: 0.85rem;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        vertical-align: middle;
        background-color: #fff; /* Default background */
    }

    /* HEADER STICKY (Freeze Top) - WARNA CYAN */
    table.table-grid thead th {
        position: sticky;
        top: 0;
        background: #0e7490; /* Cyan-700 sesuai request */
        color: #fff;
        z-index: 40; /* Z-index paling tinggi */
        font-weight: 700;
        text-transform: uppercase;
        height: 50px;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
    }

    /* KOLOM STICKY (Freeze Left) */
    th.sticky-col, td.sticky-col {
        position: sticky;
        left: 0;
        z-index: 20; /* Lebih tinggi dari sel biasa, lebih rendah dari header */
        border-right: 2px solid #cbd5e1; /* Pembatas visual */
    }

    /* Header yang juga Sticky Column (Pojok Kiri Atas) */
    thead th.sticky-col {
        z-index: 50; /* Harus paling atas agar menutupi scroll */
        background: #0e7490; /* Samakan dengan header */
    }

    /* Posisi Kolom yang di-Freeze (Lebar harus fix) */
    .col-foto { left: 0px; width: 70px; text-align: center; }
    .col-sap  { left: 70px; width: 100px; }
    .col-old  { left: 170px; width: 100px; }
    .col-nama { left: 270px; width: 250px; }

    /* Fix visual saat hover row */
    tr:hover td { background-color: #ecfeff !important; }
    
    /* Aksi Sticky di Kanan */
    th.sticky-action, td.sticky-action {
        position: sticky;
        right: 0;
        z-index: 20;
        border-left: 2px solid #cbd5e1;
        text-align: center;
        width: 100px;
    }
    thead th.sticky-action { z-index: 50; }
</style>

<div class="space-y-4">
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
        
        <div class="flex p-1 bg-gray-100 rounded-lg border border-gray-200">
            <button onclick="switchView('active')" id="tab-active" class="px-4 py-2 text-sm font-bold rounded-md shadow-sm bg-white text-cyan-800 transition flex items-center gap-2">
                <i class="ti ti-users"></i> Karyawan Aktif
            </button>
            <button onclick="switchView('pension')" id="tab-pension" class="px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition flex items-center gap-2">
                <i class="ti ti-user-off"></i> Monitoring Pensiun
            </button>
        </div>

        <div class="flex gap-2">
            <?php if ($canInput): ?>
            <button onclick="openImportModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-bold flex items-center gap-2 shadow-sm transition transform hover:-translate-y-0.5">
                <i class="ti ti-file-spreadsheet"></i> Import Excel
            </button>
            <button id="btn-add" class="px-4 py-2 bg-cyan-700 text-white rounded-lg hover:bg-cyan-800 text-sm font-bold flex items-center gap-2 shadow-sm transition transform hover:-translate-y-0.5">
                <i class="ti ti-plus"></i> Karyawan Baru
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
        <div class="flex flex-wrap gap-3 items-center w-full md:w-auto text-sm">
            <div class="flex items-center gap-2">
                <span class="text-gray-600 font-semibold">Show:</span>
                <select id="limit" class="bg-gray-50 border border-gray-300 text-gray-800 rounded-lg p-2 focus:ring-2 focus:ring-cyan-500 outline-none cursor-pointer">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            
            <select id="f_kebun" class="bg-gray-50 border border-gray-300 text-gray-800 rounded-lg p-2 w-40 cursor-pointer outline-none focus:ring-2 focus:ring-cyan-500">
                <option value="">Semua Kebun</option>
                </select>

            <select id="f_afdeling" class="bg-gray-50 border border-gray-300 text-gray-800 rounded-lg p-2 w-40 cursor-pointer outline-none focus:ring-2 focus:ring-cyan-500">
                <option value="">Semua Afdeling</option>
                </select>
        </div>

        <div class="relative w-full md:w-80 group">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="ti ti-search text-gray-400 group-focus-within:text-cyan-600"></i>
            </div>
            <input type="text" id="q" class="block w-full p-2.5 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition" placeholder="Cari Nama, NIK, atau SAP ID...">
        </div>
    </div>

    <div class="table-container bg-white">
        <table class="table-grid" id="table-karyawan">
            <thead>
                <tr>
                    <th class="sticky-col col-foto">Foto</th>
                    <th class="sticky-col col-sap">SAP ID</th>
                    <th class="sticky-col col-old">Old Pers</th>
                    <th class="sticky-col col-nama text-left">Nama Lengkap</th>
                    
                    <th class="text-left">Kebun</th>
                    <th class="text-left">Afdeling</th>
                    <th class="text-left">Jabatan Real</th>
                    <th class="text-center">Status Tax</th> <th class="text-center">Dokumen</th>
                    <th class="text-left">Pendidikan</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Grade</th>
                    <th class="text-center">TMT Kerja</th>
                    <th class="text-center">TMT MBT</th>
                    <th class="text-center">TMT Pensiun</th>
                    <th class="text-left">No HP</th>
                    <th class="text-left">Bank</th>
                    <th class="text-left">No Rek</th>
                    
                    <th class="sticky-action">Aksi</th>
                </tr>
            </thead>
            <tbody id="tbody-data" class="text-gray-700">
                </tbody>
        </table>
    </div>
    
    <div class="bg-gray-50 px-4 py-3 border border-gray-200 rounded-b-xl flex flex-col sm:flex-row justify-between items-center gap-4 mt-[-1rem] z-10 relative">
        <div class="text-sm text-gray-600">
            Menampilkan <span class="font-bold text-gray-900" id="info-start">0</span> 
            sampai <span class="font-bold text-gray-900" id="info-end">0</span> 
            dari <span class="font-bold text-gray-900" id="info-total">0</span> data
        </div>
        <div class="inline-flex rounded-md shadow-sm gap-1" id="pagination-controls">
            </div>
    </div>
</div>

<div id="import-modal" class="fixed inset-0 bg-gray-900/60 z-[60] hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform scale-100 transition-transform">
        <div class="bg-cyan-700 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center gap-2"><i class="ti ti-file-spreadsheet"></i> Import Data Excel</h3>
            <button onclick="closeImportModal()" class="hover:text-red-200 transition"><i class="ti ti-x text-xl"></i></button>
        </div>
        <div class="p-6">
            <div class="bg-cyan-50 border border-cyan-100 p-4 rounded-lg text-sm text-cyan-800 mb-5">
                <i class="ti ti-info-circle mr-1"></i>
                Gunakan <a href="cetak/template_karyawan.php" class="font-bold underline hover:text-cyan-900">Template Terbaru</a>. 
                Pastikan kolom <strong>Status Tax</strong>, <strong>Kebun</strong>, dan <strong>Pendidikan</strong> terisi.
            </div>
            <form id="form-import">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" value="import_excel_lib">
                
                <label class="block text-sm font-bold text-gray-700 mb-2">Pilih File (.xlsx / .csv)</label>
                <input type="file" name="file_excel" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-cyan-100 file:text-cyan-700 hover:file:bg-cyan-200 mb-6 cursor-pointer" required>
                
                <button type="submit" class="w-full bg-cyan-700 text-white py-2.5 rounded-lg font-bold hover:bg-cyan-800 shadow-lg transition flex justify-center items-center gap-2">
                    <i class="ti ti-upload"></i> Proses Import
                </button>
            </form>
        </div>
    </div>
</div>

<?php if ($canInput): ?>
<div id="crud-modal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white w-full max-w-7xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <div class="px-8 py-5 border-b flex justify-between items-center bg-gray-50">
                <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-user-edit text-cyan-600"></i> Form Data Karyawan
                </h3>
                <button id="btn-close" class="text-gray-400 hover:text-red-500 transition"><i class="ti ti-x text-2xl"></i></button>
            </div>
            
            <form id="crud-form" class="flex-1 overflow-y-auto p-8 grid grid-cols-1 md:grid-cols-4 gap-8 bg-white">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="id" id="form-id">

                <div class="space-y-5 border-r border-dashed border-gray-200 pr-4">
                    <h4 class="text-sm font-bold text-cyan-700 uppercase tracking-wider border-b pb-1">Identitas</h4>
                    
                    <div class="text-center group">
                        <div class="w-32 h-32 mx-auto bg-gray-100 rounded-full overflow-hidden border-4 border-white shadow-md relative">
                            <img id="preview-foto" src="../assets/img/default-avatar.png" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/50 hidden group-hover:flex items-center justify-center text-white cursor-pointer transition" onclick="document.getElementById('foto_karyawan').click()">
                                <i class="ti ti-camera text-3xl"></i>
                            </div>
                        </div>
                        <input type="file" name="foto_karyawan" id="foto_karyawan" class="hidden" accept="image/*" onchange="previewImage(this)">
                        <p class="text-xs text-gray-500 mt-2 italic">Klik foto untuk mengganti</p>
                    </div>
                    
                    <div>
                        <label class="lbl">SAP ID <span class="text-red-500">*</span></label>
                        <input type="text" name="sap_id" id="sap_id" class="inp font-mono font-bold" required placeholder="Ex: 2024001">
                    </div>
                    <div>
                        <label class="lbl">Old Pers No</label>
                        <input type="text" name="old_pers_no" id="old_pers_no" class="inp" placeholder="Ex: P-123">
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                        <label class="lbl text-blue-800">Upload Dokumen</label>
                        <input type="file" name="dokumen_file" id="dokumen_file" class="inp text-xs bg-white" accept=".pdf,.doc,.docx">
                        <div id="link-dokumen" class="text-xs mt-1 text-gray-500">Max 2MB (PDF/Doc)</div>
                    </div>
                </div>

                <div class="space-y-5 border-r border-dashed border-gray-200 pr-4">
                    <h4 class="text-sm font-bold text-cyan-700 uppercase tracking-wider border-b pb-1">Data Pribadi</h4>
                    
                    <div>
                        <label class="lbl">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_karyawan" id="nama_karyawan" class="inp" required>
                    </div>
                    <div>
                        <label class="lbl">NIK KTP (16 Digit)</label>
                        <input type="text" name="nik_ktp" id="nik_ktp" class="inp font-mono" maxlength="16">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">Gender</label>
                            <select name="gender" id="gender" class="inp cursor-pointer">
                                <option value="">-Pilih-</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl">Agama</label>
                            <select name="agama" id="agama" class="inp cursor-pointer">
                                <option value="Islam">Islam</option>
                                <option value="Kristen">Kristen</option>
                                <option value="Katolik">Katolik</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Buddha">Buddha</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" id="tempat_lahir" class="inp">
                        </div>
                        <div>
                            <label class="lbl">Tgl Lahir</label>
                            <input type="date" name="tgl_lahir" id="tgl_lahir" class="inp">
                        </div>
                    </div>
                    <div>
                        <label class="lbl">Status Tax (PTKP)</label>
                        <input type="text" name="status_pajak" id="status_pajak" class="inp" placeholder="Contoh: K/0, TK/0, K/1">
                        <small class="text-[10px] text-gray-400">Menggantikan kolom NPWP</small>
                    </div>
                </div>

                <div class="space-y-5 border-r border-dashed border-gray-200 pr-4">
                    <h4 class="text-sm font-bold text-cyan-700 uppercase tracking-wider border-b pb-1">Kepegawaian</h4>
                    
                    <div>
                        <label class="lbl">Kebun <span class="text-red-500">*</span></label>
                        <select name="kebun_id" id="kebun_id" class="inp cursor-pointer bg-gray-50">
                            <option value="">- Memuat Data -</option>
                            </select>
                    </div>
                    <div>
                        <label class="lbl">Afdeling / Unit</label>
                        <input type="text" name="afdeling" id="afdeling" class="inp" placeholder="Ex: Afdeling 1">
                    </div>
                    <div>
                        <label class="lbl">Jabatan Real</label>
                        <input type="text" name="jabatan_real" id="jabatan_real" class="inp">
                    </div>
                    <div>
                        <label class="lbl">Jabatan SAP</label>
                        <input type="text" name="jabatan_sap" id="jabatan_sap" class="inp">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">Status </label>
                            <select name="status_karyawan" id="status_karyawan" class="inp cursor-pointer">
                                <option value="KARPIM">KARPIM</option>
                                <option value="TS">TS</option>
                                <option value="KNG">KNG</option>
                                <option value="PKWT">PKWT</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl">Grade</label>
                            <input type="text" name="person_grade" id="person_grade" class="inp text-center">
                        </div>
                    </div>
                    <div>
                        <label class="lbl">Status Keluarga</label>
                        <select name="status_keluarga" id="status_keluarga" class="inp cursor-pointer">
                            <option value="Menikah">Menikah</option>
                            <option value="Lajang">Lajang</option>
                            <option value="Cerai">Cerai</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-5">
                    <h4 class="text-sm font-bold text-cyan-700 uppercase tracking-wider border-b pb-1">Pend. & Tanggal</h4>
                    
                    <div>
                        <label class="lbl">Pendidikan Terakhir</label>
                        <select name="pendidikan_terakhir" id="pendidikan_terakhir" class="inp cursor-pointer">
                            <option value="">-Pilih-</option>
                            <option value="SD">SD</option>
                            <option value="SMP">SMP</option>
                            <option value="SMA">SMA/SMK</option>
                            <option value="D3">D3</option>
                            <option value="S1">S1</option>
                            <option value="S2">S2</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">Jurusan</label>
                            <input type="text" name="jurusan" id="jurusan" class="inp" placeholder="Ex: Ekonomi">
                        </div>
                        <div>
                            <label class="lbl">Institusi</label>
                            <input type="text" name="institusi" id="institusi" class="inp" placeholder="Nama Kampus">
                        </div>
                    </div>
                    
                    <div class="pt-2 border-t border-gray-100"></div>

                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="lbl text-blue-700">TMT Masuk Kerja</label>
                            <input type="date" name="tmt_kerja" id="tmt_kerja" class="inp bg-blue-50 border-blue-200">
                        </div>
                        <div>
                            <label class="lbl text-orange-700">TMT MBT</label>
                            <input type="date" name="tmt_mbt" id="tmt_mbt" class="inp bg-orange-50 border-orange-200">
                        </div>
                        <div>
                            <label class="lbl text-red-700">TMT Pensiun</label>
                            <input type="date" name="tmt_pensiun" id="tmt_pensiun" class="inp bg-red-50 border-red-200">
                        </div>
                    </div>

                    <input type="hidden" name="no_rekening" id="no_rekening">
                    <input type="hidden" name="nama_bank" id="nama_bank">
                    <input type="hidden" name="npwp" id="npwp"> </div>
            </form>

            <div class="px-8 py-5 border-t bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                <button type="button" id="btn-cancel" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-100 font-medium transition">Batal</button>
                <button type="button" id="btn-save" class="px-6 py-2.5 bg-cyan-700 text-white rounded-lg hover:bg-cyan-800 shadow-md font-bold flex items-center gap-2 transition">
                    <i class="ti ti-device-floppy"></i> Simpan Data
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    .lbl { display: block; font-size: 0.7rem; font-weight: 700; color: #4b5563; text-transform: uppercase; margin-bottom: 0.25rem; letter-spacing: 0.025em; }
    .inp { width: 100%; border: 1px solid #d1d5db; padding: 0.5rem; border-radius: 0.5rem; font-size: 0.85rem; color: #1f2937; transition: border-color 0.2s; }
    .inp:focus { outline: none; border-color: #0891b2; ring: 2px solid #a5f3fc; }
</style>

<script>
// Global State
let currentPage = 1;
let perPage = 10;
let totalPages = 1;
let viewType = 'active'; // 'active' atau 'pension'
let searchTimeout = null;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial Load
    loadOptions(); // Load Kebun & Afdeling for dropdowns
    loadData();    // Load Table Data

    // 2. Event Listeners
    // Switch Limit
    document.getElementById('limit').addEventListener('change', (e) => { 
        perPage = e.target.value; 
        currentPage = 1; 
        loadData(); 
    });

    // Filters
    document.getElementById('f_kebun').addEventListener('change', () => { currentPage = 1; loadData(); });
    document.getElementById('f_afdeling').addEventListener('change', () => { currentPage = 1; loadData(); });

    // Search with Debounce
    document.getElementById('q').addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadData();
        }, 500); // Tunggu 500ms setelah ketik
    });
    
    // Modal Handlers (Only if authorized)
    if(document.getElementById('btn-add')) {
        document.getElementById('btn-add').onclick = () => openCrudModal('store');
        document.getElementById('btn-close').onclick = () => document.getElementById('crud-modal').classList.add('hidden');
        document.getElementById('btn-cancel').onclick = () => document.getElementById('crud-modal').classList.add('hidden');
        document.getElementById('btn-save').onclick = saveData;
    }
    
    // Import Form
    document.getElementById('form-import').addEventListener('submit', handleImport);
});

// --- FUNGSI UTAMA ---

// 1. Switch View (Aktif vs Pensiun)
function switchView(type) {
    viewType = type;
    
    // UI Update
    if(type === 'active') {
        document.getElementById('tab-active').className = 'px-4 py-2 text-sm font-bold rounded-md shadow-sm bg-white text-cyan-800 transition flex items-center gap-2 ring-1 ring-gray-200';
        document.getElementById('tab-pension').className = 'px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition flex items-center gap-2';
    } else {
        document.getElementById('tab-active').className = 'px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition flex items-center gap-2';
        document.getElementById('tab-pension').className = 'px-4 py-2 text-sm font-bold rounded-md shadow-sm bg-white text-red-700 transition flex items-center gap-2 ring-1 ring-gray-200';
    }
    
    currentPage = 1;
    loadData();
}

// 2. Load Option Helper (Kebun & Afdeling)
async function loadOptions() {
    const fd = new FormData(); 
    fd.append('action', 'list_options');
    
    try {
        const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
        const json = await res.json();
        
        if(json.success) {
            // Populate Filter Kebun
            let htmlK = '<option value="">Semua Kebun</option>';
            json.kebun.forEach(k => htmlK += `<option value="${k.id}">${k.nama_kebun}</option>`);
            document.getElementById('f_kebun').innerHTML = htmlK;
            
            // Populate Form Kebun (if modal exists)
            if(document.getElementById('kebun_id')) {
                 let htmlForm = '<option value="">-Pilih Kebun-</option>';
                 json.kebun.forEach(k => htmlForm += `<option value="${k.id}">${k.nama_kebun}</option>`);
                 document.getElementById('kebun_id').innerHTML = htmlForm;
            }

            // Populate Filter Afdeling
            let htmlA = '<option value="">Semua Afdeling</option>';
            json.afdeling.forEach(a => htmlA += `<option value="${a}">${a}</option>`);
            document.getElementById('f_afdeling').innerHTML = htmlA;
        }
    } catch(e) { console.error('Error loading options', e); }
}

// 3. Load Data Table
async function loadData() {
    const tbody = document.getElementById('tbody-data');
    tbody.innerHTML = '<tr><td colspan="15" class="text-center py-12 text-gray-500"><i class="ti ti-loader animate-spin text-2xl mb-2"></i><br>Memuat data...</td></tr>';
    
    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('view_type', viewType);
    fd.append('page', currentPage);
    fd.append('limit', perPage);
    fd.append('q', document.getElementById('q').value);
    fd.append('f_kebun', document.getElementById('f_kebun').value);
    fd.append('f_afdeling', document.getElementById('f_afdeling').value);

    try {
        const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
        const json = await res.json();

        if(json.success) {
            // Update Info
            const total = parseInt(json.total);
            const start = total === 0 ? 0 : ((currentPage-1)*perPage)+1;
            const end = Math.min(currentPage*perPage, total);
            
            document.getElementById('info-start').innerText = start;
            document.getElementById('info-end').innerText = end;
            document.getElementById('info-total').innerText = total;
            
            // Render Rows
            if(json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="15" class="text-center py-10 text-gray-400 italic">Tidak ada data ditemukan.</td></tr>';
            } else {
                tbody.innerHTML = json.data.map(r => {
                    const foto = r.foto_karyawan ? `../uploads/profil/${r.foto_karyawan}` : '../assets/img/default-avatar.png';
                    
                    // Logic Doc Icon
                    let docIcon = '<span class="text-gray-300 text-xs">-</span>';
                    if(r.dokumen_path) {
                        docIcon = `<a href="../uploads/dokumen/${r.dokumen_path}" target="_blank" class="text-cyan-600 hover:text-cyan-800 bg-cyan-50 px-2 py-1 rounded text-xs border border-cyan-200 flex items-center justify-center gap-1 w-fit mx-auto"><i class="ti ti-file-text"></i> Lihat</a>`;
                    }

                    // JSON for Edit
                    const rowJson = encodeURIComponent(JSON.stringify(r));
                    
                    // Styling Status
                    let statusClass = 'bg-gray-100 text-gray-600';
                    if(r.status_karyawan === 'KARPIM') statusClass = 'bg-green-100 text-green-700 border border-green-200';
                    if(r.status_karyawan === 'TS') statusClass = 'bg-yellow-100 text-yellow-700 border border-yellow-200';

                    return `
                    <tr class="hover:bg-cyan-50 border-b transition duration-150 group">
                        <td class="text-center p-2 sticky-col col-foto bg-white group-hover:bg-cyan-50">
                            <img src="${foto}" class="w-9 h-9 rounded-full object-cover mx-auto border shadow-sm">
                        </td>
                        <td class="p-3 sticky-col col-sap bg-white group-hover:bg-cyan-50 font-mono text-xs font-bold text-cyan-700">${r.sap_id}</td>
                        <td class="p-3 sticky-col col-old bg-white group-hover:bg-cyan-50 text-xs text-gray-500">${r.old_pers_no || '-'}</td>
                        <td class="p-3 sticky-col col-nama bg-white group-hover:bg-cyan-50 font-bold text-gray-800 text-sm truncate">${r.nama_karyawan}</td>
                        
                        <td class="p-3 text-sm">${r.nama_kebun || '<span class="text-gray-300 italic">N/A</span>'}</td>
                        <td class="p-3 text-sm">${r.afdeling || '-'}</td>
                        <td class="p-3 text-sm font-medium">${r.jabatan_real || '-'}</td>
                        
                        <td class="p-3 text-center text-sm font-mono">${r.status_pajak}</td> <td class="p-3 text-center">${docIcon}</td>
                        <td class="p-3 text-sm">${r.pendidikan_terakhir || '-'} <span class="text-xs text-gray-400">(${r.jurusan || '-'})</span></td>
                        
                        <td class="p-3 text-center"><span class="${statusClass} text-xs px-2 py-1 rounded font-bold">${r.status_karyawan}</span></td>
                        <td class="p-3 text-center text-xs font-mono">${r.person_grade || '-'}</td>
                        <td class="p-3 text-center text-xs">${r.tmt_kerja || '-'}</td>
                        <td class="p-3 text-center text-xs text-orange-600 font-bold">${r.tmt_mbt || '-'}</td>
                        <td class="p-3 text-center text-xs text-red-600 font-bold">${r.tmt_pensiun || '-'}</td>
                        
                        <td class="p-3 text-sm">${r.no_hp || '-'}</td>
                        <td class="p-3 text-sm">${r.nama_bank || '-'}</td>
                        <td class="p-3 text-sm font-mono text-xs">${r.no_rekening || '-'}</td>
                        
                        <td class="p-3 text-center sticky-action bg-white group-hover:bg-cyan-50">
                            <div class="flex justify-center gap-1">
                                <a href="export_cv.php?id=${r.id}" target="_blank" class="w-7 h-7 flex items-center justify-center text-green-600 hover:bg-green-100 rounded transition" title="Print CV"><i class="ti ti-printer"></i></a>
                                <?php if($canAction): ?>
                                <button onclick="editData('${rowJson}')" class="w-7 h-7 flex items-center justify-center text-blue-600 hover:bg-blue-100 rounded transition" title="Edit"><i class="ti ti-pencil"></i></button>
                                <button onclick="deleteData(${r.id})" class="w-7 h-7 flex items-center justify-center text-red-600 hover:bg-red-100 rounded transition" title="Hapus"><i class="ti ti-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>`;
                }).join('');
            }
            // Generate Pagination
            renderPagination(Math.ceil(total/perPage));
        } else {
            alert('Gagal memuat data: ' + json.message);
        }
    } catch(e) { console.error(e); }
}

// 4. Render Pagination Buttons
function renderPagination(total) {
    let html = '';
    // Previous
    html += `<button onclick="changePage(${currentPage-1})" ${currentPage===1?'disabled':''} class="w-8 h-8 flex items-center justify-center border rounded hover:bg-gray-100 disabled:opacity-50"><i class="ti ti-chevron-left"></i></button>`;
    
    // Page Numbers (Smart Logic)
    for(let i=1; i<=total; i++) {
        if(i === 1 || i === total || (i >= currentPage-1 && i <= currentPage+1)) {
            let active = i===currentPage ? 'bg-cyan-700 text-white border-cyan-700' : 'bg-white text-gray-600 hover:bg-gray-100';
            html += `<button onclick="changePage(${i})" class="w-8 h-8 flex items-center justify-center border rounded ${active} text-sm font-bold transition">${i}</button>`;
        } else if (i === currentPage-2 || i === currentPage+2) {
            html += `<span class="w-8 h-8 flex items-center justify-center text-gray-400">...</span>`;
        }
    }
    
    // Next
    html += `<button onclick="changePage(${currentPage+1})" ${currentPage===total || total===0?'disabled':''} class="w-8 h-8 flex items-center justify-center border rounded hover:bg-gray-100 disabled:opacity-50"><i class="ti ti-chevron-right"></i></button>`;
    
    document.getElementById('pagination-controls').innerHTML = html;
}

function changePage(p) {
    if(p < 1 || (document.getElementById('info-total').innerText > 0 && p > Math.ceil(document.getElementById('info-total').innerText/perPage))) return;
    currentPage = p;
    loadData();
}

// 5. Helper: Open/Close Modals
function openImportModal(){ document.getElementById('import-modal').classList.remove('hidden'); document.getElementById('import-modal').classList.add('flex'); }
function closeImportModal(){ document.getElementById('import-modal').classList.add('hidden'); document.getElementById('import-modal').classList.remove('flex'); }

function openCrudModal(mode) {
    document.getElementById('crud-form').reset();
    document.getElementById('form-action').value = mode;
    document.getElementById('preview-foto').src = '../assets/img/default-avatar.png';
    document.getElementById('link-dokumen').innerHTML = 'Max 2MB (PDF/Doc)';
    document.getElementById('crud-modal').classList.remove('hidden');
}

// 6. Handle Edit Data
window.editData = (jsonStr) => {
    const r = JSON.parse(decodeURIComponent(jsonStr));
    openCrudModal('update');
    document.getElementById('form-id').value = r.id;
    
    // Populate Fields
    const fields = [
        'sap_id','old_pers_no','nama_karyawan','nik_ktp','gender','agama','tempat_lahir','tgl_lahir',
        'status_pajak', // New
        'kebun_id', 'afdeling', 'jabatan_real', 'jabatan_sap', 'status_karyawan', 'person_grade', 'status_keluarga',
        'pendidikan_terakhir', 'jurusan', 'institusi',
        'tmt_kerja', 'tmt_mbt', 'tmt_pensiun', 'no_rekening', 'nama_bank'
    ];
    
    fields.forEach(id => {
        if(document.getElementById(id)) document.getElementById(id).value = r[id] || '';
    });

    if(r.foto_karyawan) document.getElementById('preview-foto').src = '../uploads/profil/' + r.foto_karyawan;
    if(r.dokumen_path) document.getElementById('link-dokumen').innerHTML = `<span class="text-green-600 font-bold"><i class="ti ti-check"></i> File saat ini: ${r.dokumen_path}</span>`;
};

// 7. Save Data (AJAX)
async function saveData() {
    const btn = document.getElementById('btn-save');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Menyimpan...';
    btn.disabled = true;

    const fd = new FormData(document.getElementById('crud-form'));
    try {
        const res = await fetch('data_karyawan_crud.php', { method:'POST', body:fd });
        const json = await res.json();
        
        if(json.success) {
            document.getElementById('crud-modal').classList.add('hidden');
            Swal.fire('Berhasil!', 'Data telah disimpan.', 'success');
            loadData();
        } else {
            Swal.fire('Gagal!', json.message, 'error');
        }
    } catch(e) { 
        console.error(e);
        Swal.fire('Error', 'Terjadi kesalahan sistem', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// 8. Import Handler
function handleImport(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Mengunggah...';
    btn.disabled = true;
    
    const fd = new FormData(e.target);
    fetch('data_karyawan_crud.php', {method:'POST', body:fd})
    .then(r=>r.json()).then(j => {
        if(j.success) { 
            closeImportModal(); 
            Swal.fire('Sukses', j.message, 'success'); 
            loadData(); 
        } else { 
            Swal.fire('Gagal Import', j.message, 'error'); 
        }
    })
    .catch(() => Swal.fire('Error', 'Gagal koneksi server', 'error'))
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// 9. Delete Handler
window.deleteData = (id) => {
    Swal.fire({
        title: 'Hapus Data?', text: "Data yang dihapus tidak bisa dikembalikan!", icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', confirmButtonColor: '#ef4444'
    }).then(res => {
        if(res.isConfirmed) {
            const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
            fetch('data_karyawan_crud.php', {method:'POST', body:fd}).then(r=>r.json()).then(j=>{
                if(j.success) { Swal.fire('Terhapus!','Data berhasil dihapus.','success'); loadData(); }
                else { Swal.fire('Gagal', 'Tidak bisa menghapus data', 'error'); }
            });
        }
    });
};

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('preview-foto').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}
</script>