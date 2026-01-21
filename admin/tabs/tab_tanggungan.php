<style>
    /* Wrapper Table */
    .table-container-t {
        max-height: 65vh; 
        overflow: auto; 
        position: relative;
        border: 1px solid #cbd5e1; 
        border-radius: 0.75rem; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    
    table.table-grid-t {
        width: 100%; 
        border-collapse: separate; 
        border-spacing: 0; 
        min-width: 1500px;
    }
    
    table.table-grid-t th, table.table-grid-t td {
        padding: 0.75rem; 
        font-size: 0.85rem; 
        border-bottom: 1px solid #e2e8f0; 
        border-right: 1px solid #e2e8f0; 
        vertical-align: middle; 
        background-color: #fff;
    }

    /* Header Sticky (Top) - CYAN THEME */
    table.table-grid-t thead th {
        position: sticky; 
        top: 0; 
        background: #0891b2; /* Cyan-600 */
        color: #fff; 
        z-index: 40; 
        font-weight: 700; 
        text-transform: uppercase; 
        height: 45px;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.2);
    }

    /* Columns Sticky (Left) */
    th.sticky-col-t, td.sticky-col-t {
        position: sticky; 
        left: 0; 
        z-index: 20; 
        border-right: 2px solid #cbd5e1;
    }
    
    /* Header Intersection (Top Left) */
    thead th.sticky-col-t { 
        z-index: 50; 
        background: #0891b2; /* Cyan-600 */
    }
    
    /* Column Widths */
    .col-sap-t      { left: 0px; width: 100px; }
    .col-karyawan-t { left: 100px; width: 200px; }
    .col-keluarga-t { left: 300px; width: 200px; box-shadow: 4px 0 6px -2px rgba(0,0,0,0.1); }

    /* Hover Effect */
    tr:hover td { background-color: #ecfeff !important; }
</style>

<div class="space-y-4">
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
        <div class="bg-white p-3 rounded-xl border border-cyan-200 shadow-sm flex items-center gap-3">
            <div class="p-2 bg-cyan-100 rounded-lg text-cyan-600"><i class="ti ti-users-group text-2xl"></i></div>
            <div>
                <h3 class="text-lg font-bold text-slate-800">Data Tanggungan</h3>
                <p class="text-xs text-slate-500">Keluarga Karyawan</p>
            </div>
        </div>

        <div class="flex gap-2 flex-wrap">
            <a href="cetak/export_tanggungan_pdf.php" target="_blank" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-bold flex items-center gap-2 shadow-sm">
                <i class="ti ti-file-type-pdf"></i> PDF
            </a>
            <a href="cetak/export_tanggungan_excel.php" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-bold flex items-center gap-2 shadow-sm">
                <i class="ti ti-file-type-xls"></i> Excel
            </a>
            <?php if ($canInput): ?>
            <button onclick="openImportModalT()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-bold flex items-center gap-2 shadow-sm">
                <i class="ti ti-file-import"></i> Import
            </button>
            <button id="btn-add-tanggungan" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-bold flex items-center gap-2 shadow-sm">
                <i class="ti ti-plus"></i> Tambah
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
        <div class="flex gap-3 items-center w-full md:w-auto text-sm">
            <span class="text-gray-600 font-semibold">Show:</span>
            <select id="limit-t" class="bg-gray-50 border border-gray-300 rounded-lg p-2 w-20 cursor-pointer outline-none focus:ring-2 focus:ring-cyan-500">
                <option value="10">10</option><option value="25">25</option><option value="50">50</option>
            </select>
            <select id="f_afdeling_t" class="bg-gray-50 border border-gray-300 rounded-lg p-2 w-40 cursor-pointer outline-none focus:ring-2 focus:ring-cyan-500">
                <option value="">Semua Afdeling</option>
                </select>
        </div>
        <div class="relative w-full md:w-80">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
            <input type="text" id="q-tanggungan" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-cyan-500" placeholder="Cari Karyawan / Keluarga...">
        </div>
    </div>

    <div class="table-container-t bg-white">
        <table class="table-grid-t" id="table-tanggungan">
            <thead>
                <tr>
                    <th class="sticky-col-t col-sap-t">SAP ID</th>
                    <th class="sticky-col-t col-karyawan-t">Nama Karyawan</th>
                    <th class="sticky-col-t col-keluarga-t">Nama Keluarga</th>
                    
                    <th>Hubungan</th>
                    <th>Kebun</th>
                    <th>Afdeling</th>
                    <th>Tempat Lahir</th>
                    <th>Tgl Lahir</th>
                    <th>Pendidikan</th>
                    <th>Pekerjaan</th>
                    <th>Keterangan</th>
                    <?php if ($canAction): ?>
                    <th class="text-center sticky right-0 z-50 bg-cyan-600 text-white" style="border-left:2px solid #bae6fd">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-tanggungan" class="text-gray-700"></tbody>
        </table>
    </div>

    <div class="bg-gray-50 px-4 py-3 border border-gray-200 rounded-b-xl flex justify-between items-center mt-[-1rem] z-10 relative">
        <div class="text-xs text-gray-600">
            Menampilkan <span id="info-start-t" class="font-bold">0</span> - <span id="info-end-t" class="font-bold">0</span> dari <span id="info-total-t" class="font-bold">0</span> data
        </div>
        <div id="pagination-controls-t" class="flex gap-1"></div>
    </div>
</div>

<div id="import-modal-t" class="fixed inset-0 bg-gray-900/60 z-[60] hidden items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-cyan-700 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold flex items-center gap-2"><i class="ti ti-file-import"></i> Import Tanggungan</h3>
            <button onclick="closeImportModalT()"><i class="ti ti-x text-xl"></i></button>
        </div>
        <div class="p-6">
            <div class="bg-cyan-50 p-4 rounded text-sm text-cyan-800 mb-4 border border-cyan-100">
                Download <a href="cetak/template_tanggungan.php" class="font-bold underline">Template Excel</a>. Pastikan SAP ID Karyawan benar.
            </div>
            <form id="form-import-t">
                <input type="hidden" name="action" value="import">
                <input type="file" name="file_excel" accept=".xlsx,.xls" class="block w-full text-sm border p-2 rounded mb-4 text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100" required>
                <button type="submit" class="w-full bg-cyan-600 text-white py-2 rounded-lg font-bold hover:bg-cyan-700 shadow-md">Proses Import</button>
            </form>
        </div>
    </div>
</div>

<?php if ($canInput): ?>
<div id="modal-tanggungan" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 border-b bg-cyan-50 flex justify-between items-center">
                <h3 class="text-xl font-bold text-cyan-900 flex items-center gap-2"><i class="ti ti-user-plus"></i> Form Data Tanggungan</h3>
                <button id="close-tanggungan" class="text-gray-400 hover:text-red-500"><i class="ti ti-x text-2xl"></i></button>
            </div>
            
            <form id="form-tanggungan" class="p-6 grid grid-cols-2 gap-4">
                <input type="hidden" name="action" id="action-tanggungan">
                <input type="hidden" name="id" id="id-tanggungan">

                <div class="col-span-2">
                    <label class="lbl">Pilih Karyawan <span class="text-red-500">*</span></label>
                    <select name="karyawan_id" id="karyawan_id" class="inp cursor-pointer" required>
                        <option value="">-- Memuat Data Karyawan... --</option>
                    </select>
                </div>
                
                <div>
                    <label class="lbl">Nama Keluarga <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_batih" id="nama_batih" class="inp" required>
                </div>
                
                <div>
                    <label class="lbl">Hubungan <span class="text-red-500">*</span></label>
                    <select name="hubungan" id="hubungan" class="inp cursor-pointer" required>
                        <option value="Istri">Istri</option>
                        <option value="Suami">Suami</option>
                        <option value="Anak">Anak</option>
                        <option value="Orang Tua">Orang Tua</option>
                    </select>
                </div>
                
                <div><label class="lbl">Tempat Lahir</label><input type="text" name="tempat_lahir" id="tempat_lahir_t" class="inp"></div>
                <div><label class="lbl">Tgl Lahir</label><input type="date" name="tanggal_lahir" id="tanggal_lahir_t" class="inp"></div>
                <div><label class="lbl">Pendidikan</label><input type="text" name="pendidikan_terakhir" id="pendidikan_terakhir_t" class="inp"></div>
                <div><label class="lbl">Pekerjaan</label><input type="text" name="pekerjaan" id="pekerjaan_t" class="inp"></div>
                <div class="col-span-2"><label class="lbl">Keterangan</label><textarea name="keterangan" id="keterangan_t" class="inp" rows="2"></textarea></div>
            </form>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3">
                <button type="button" id="cancel-tanggungan" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-100 font-medium transition">Batal</button>
                <button type="button" id="save-tanggungan" class="px-6 py-2.5 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 shadow-md font-bold transition">Simpan</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>.lbl{display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:4px;}.inp{width:100%;border:1px solid #d1d5db;padding:6px;border-radius:6px;font-size:13px;} .inp:focus { outline:none; border-color:#0891b2; ring:2px solid #a5f3fc; }</style>

<script>
// State Management
let pageT = 1; 
let limitT = 10;
let searchTimeoutT = null;

// Ganti URL ke file backend yang BARU
const API_URL_T = 'data_tanggungan_crud.php'; // Atau sesuaikan path relatif

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial Load
    loadAfdelingOption();
    loadTanggungan();

    // 2. Listeners
    document.getElementById('limit-t').addEventListener('change', (e) => { 
        limitT = e.target.value; pageT=1; loadTanggungan(); 
    });
    
    document.getElementById('f_afdeling_t').addEventListener('change', () => { 
        pageT=1; loadTanggungan(); 
    });
    
    document.getElementById('q-tanggungan').addEventListener('input', (e) => {
        clearTimeout(searchTimeoutT);
        searchTimeoutT = setTimeout(() => { pageT=1; loadTanggungan(); }, 500);
    });
    
    // 3. Form Import Handler
    document.getElementById('form-import-t').addEventListener('submit', handleImportT);

    // 4. Modal Handlers
    if(document.getElementById('btn-add-tanggungan')) {
        document.getElementById('btn-add-tanggungan').onclick = () => openModalT('store');
        document.getElementById('close-tanggungan').onclick = () => document.getElementById('modal-tanggungan').classList.add('hidden');
        document.getElementById('cancel-tanggungan').onclick = () => document.getElementById('modal-tanggungan').classList.add('hidden');
        document.getElementById('save-tanggungan').onclick = saveTanggungan;
    }
});

// --- CORE FUNCTIONS ---

// 1. Load Data Table
async function loadTanggungan() {
    const tbody = document.getElementById('tbody-tanggungan');
    tbody.innerHTML = '<tr><td colspan="12" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-2xl"></i><br>Memuat data...</td></tr>';
    
    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('q', document.getElementById('q-tanggungan').value);
    fd.append('limit', limitT);
    fd.append('page', pageT);
    fd.append('f_afdeling', document.getElementById('f_afdeling_t').value);

    try {
        // PERHATIKAN: URL Fetch mengarah ke data_tanggungan_crud.php
        const res = await fetch(API_URL_T, {method:'POST', body:fd});
        const json = await res.json();

        if(json.success) {
            // Update Info
            const total = parseInt(json.total);
            const start = total === 0 ? 0 : ((pageT-1)*limitT)+1;
            const end = Math.min(pageT*limitT, total);
            
            document.getElementById('info-start-t').innerText = start;
            document.getElementById('info-end-t').innerText = end;
            document.getElementById('info-total-t').innerText = total;
            
            // Render Rows
            if(json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center py-10 text-gray-400 italic">Tidak ada data ditemukan.</td></tr>';
            } else {
                tbody.innerHTML = json.data.map(r => {
                    // Prepare data for Edit Button
                    const rowJson = encodeURIComponent(JSON.stringify(r));
                    
                    return `
                    <tr class="hover:bg-cyan-50 border-b transition">
                        <td class="p-3 sticky-col-t col-sap-t bg-white font-mono text-xs font-bold text-cyan-700">${r.sap_id}</td>
                        <td class="p-3 sticky-col-t col-karyawan-t bg-white font-bold text-gray-800 truncate">${r.nama_karyawan}</td>
                        <td class="p-3 sticky-col-t col-keluarga-t bg-white font-bold text-gray-900 truncate">${r.nama_batih}</td>
                        
                        <td class="p-3"><span class="bg-cyan-100 text-cyan-800 text-xs px-2 py-1 rounded font-bold">${r.hubungan}</span></td>
                        <td class="p-3 text-sm">${r.nama_kebun || '-'}</td>
                        <td class="p-3 text-sm">${r.afdeling || '-'}</td>
                        <td class="p-3 text-sm">${r.tempat_lahir || '-'}</td>
                        <td class="p-3 text-sm">${r.tanggal_lahir || '-'}</td>
                        <td class="p-3 text-sm">${r.pendidikan_terakhir || '-'}</td>
                        <td class="p-3 text-sm">${r.pekerjaan || '-'}</td>
                        <td class="p-3 text-sm text-gray-500 italic truncate max-w-xs">${r.keterangan || '-'}</td>
                        
                        <?php if($canAction): ?>
                        <td class="p-3 text-center sticky right-0 bg-white" style="border-left:2px solid #e2e8f0">
                            <div class="flex justify-center gap-1">
                                <button onclick="editT('${rowJson}')" class="w-7 h-7 flex items-center justify-center text-blue-600 hover:bg-blue-100 rounded transition"><i class="ti ti-pencil"></i></button>
                                <button onclick="delT(${r.id})" class="w-7 h-7 flex items-center justify-center text-red-600 hover:bg-red-100 rounded transition"><i class="ti ti-trash"></i></button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>`;
                }).join('');
            }
            renderPaginationT(Math.ceil(total/limitT));
        } else {
            console.error(json.message);
        }
    } catch(e) { console.error('Fetch Error:', e); }
}

// 2. Load Helper Options
async function loadAfdelingOption() {
    const fd = new FormData(); fd.append('action', 'list_afdeling');
    const res = await fetch(API_URL_T, {method:'POST', body:fd});
    const json = await res.json();
    if(json.success) {
        let html = '<option value="">Semua Afdeling</option>';
        json.data.forEach(a => html += `<option value="${a}">${a}</option>`);
        document.getElementById('f_afdeling_t').innerHTML = html;
    }
}

// 3. Render Pagination
function renderPaginationT(total) {
    let html = '';
    for(let i=1; i<=total; i++) {
        if(i===1 || i===total || (i>=pageT-1 && i<=pageT+1)) {
            let active = i===pageT ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-gray-600 hover:bg-gray-100 border-gray-300';
            html += `<button onclick="pageT=${i};loadTanggungan()" class="w-8 h-8 flex items-center justify-center border rounded text-sm font-bold transition ${active}">${i}</button>`;
        }
    }
    document.getElementById('pagination-controls-t').innerHTML = html;
}

// 4. Modal Logic
async function openModalT(mode) {
    document.getElementById('form-tanggungan').reset();
    document.getElementById('action-tanggungan').value = mode;
    document.getElementById('id-tanggungan').value = '';
    
    // Load Karyawan Dropdown (Fix "Manual gabisa")
    const sel = document.getElementById('karyawan_id');
    // Cek jika dropdown belum diisi atau perlu refresh
    const fd = new FormData(); 
    fd.append('action', 'list_karyawan_simple');
    
    // Tampilkan loading text
    sel.innerHTML = '<option value="">Memuat data karyawan...</option>';
    
    try {
        const res = await fetch(API_URL_T, {method:'POST', body:fd});
        const json = await res.json();
        if(json.success) {
            sel.innerHTML = '<option value="">-- Pilih Karyawan --</option>' + 
                json.data.map(k => `<option value="${k.id}">${k.sap_id} - ${k.nama_karyawan}</option>`).join('');
        }
    } catch (e) {
        sel.innerHTML = '<option value="">Gagal memuat data</option>';
    }

    document.getElementById('modal-tanggungan').classList.remove('hidden');
}

// 5. Edit Handler
window.editT = (json) => {
    const r = JSON.parse(decodeURIComponent(json));
    openModalT('update').then(() => {
        // Set values after dropdown loaded
        setTimeout(() => {
            document.getElementById('id-tanggungan').value = r.id;
            document.getElementById('karyawan_id').value = r.karyawan_id; // Set ID Karyawan
            document.getElementById('nama_batih').value = r.nama_batih;
            document.getElementById('hubungan').value = r.hubungan;
            document.getElementById('tempat_lahir_t').value = r.tempat_lahir;
            document.getElementById('tanggal_lahir_t').value = r.tanggal_lahir;
            document.getElementById('pendidikan_terakhir_t').value = r.pendidikan_terakhir;
            document.getElementById('pekerjaan_t').value = r.pekerjaan;
            document.getElementById('keterangan_t').value = r.keterangan;
        }, 300); // Slight delay ensures dropdown is populated
    });
};

// 6. Save Handler
async function saveTanggungan() {
    const btn = document.getElementById('save-tanggungan');
    const txt = btn.innerText;
    btn.innerText = 'Menyimpan...'; btn.disabled = true;

    const fd = new FormData(document.getElementById('form-tanggungan'));
    try {
        const res = await fetch(API_URL_T, {method:'POST', body:fd});
        const json = await res.json();
        if(json.success) {
            document.getElementById('modal-tanggungan').classList.add('hidden');
            Swal.fire('Sukses', 'Data berhasil disimpan', 'success');
            loadTanggungan();
        } else {
            Swal.fire('Gagal', 'Terjadi kesalahan sistem', 'error');
        }
    } catch(e) { console.error(e); } 
    finally { btn.innerText = txt; btn.disabled = false; }
}

// 7. Delete Handler
window.delT = (id) => {
    Swal.fire({title:'Hapus Data?', icon:'warning', showCancelButton:true, confirmButtonText:'Hapus', confirmButtonColor:'#d33'})
    .then(res => {
        if(res.isConfirmed) {
            const fd = new FormData(); 
            fd.append('action', 'delete'); // Generic 'delete' in dedicated file
            fd.append('id', id);
            fetch(API_URL_T, {method:'POST', body:fd}).then(r=>r.json()).then(j => {
                if(j.success) { Swal.fire('Terhapus','','success'); loadTanggungan(); }
            });
        }
    });
};

// 8. Import Handler
function handleImportT(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.innerHTML = 'Mengunggah...'; btn.disabled = true;
    
    const fd = new FormData(e.target);
    fetch(API_URL_T, {method:'POST', body:fd}).then(r=>r.json()).then(j => {
        btn.innerHTML = 'Proses Import'; btn.disabled = false;
        if(j.success) { 
            closeImportModalT(); 
            Swal.fire('Sukses', j.message, 'success'); 
            loadTanggungan(); 
        } else { 
            Swal.fire('Gagal', j.message, 'error'); 
        }
    });
}

function openImportModalT(){ document.getElementById('import-modal-t').classList.remove('hidden'); document.getElementById('import-modal-t').classList.add('flex'); }
function closeImportModalT(){ document.getElementById('import-modal-t').classList.add('hidden'); document.getElementById('import-modal-t').classList.remove('flex'); }
</script>