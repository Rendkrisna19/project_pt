<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* Styling Table Wrapper */
    .table-container-t {
        max-height: 70vh; 
        overflow: auto; 
        position: relative;
        border: 1px solid #cbd5e1; 
        border-radius: 0.75rem; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: white;
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
    }

    /* Header Cyan Theme */
    table.table-grid-t thead th {
        position: sticky; top: 0; 
        background: #0891b2; color: #fff; 
        z-index: 40; font-weight: 700; 
        text-transform: uppercase; height: 45px;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.2);
    }

    /* Sticky Columns */
    th.sticky-col-t, td.sticky-col-t {
        position: sticky; left: 0; z-index: 20; 
        border-right: 2px solid #cbd5e1;
    }
    thead th.sticky-col-t { z-index: 50; background: #0891b2; }
    
    .col-sap-t      { left: 0px; width: 100px; text-align: center; }
    .col-karyawan-t { left: 100px; width: 220px; }
    .col-keluarga-t { left: 320px; width: 200px; box-shadow: 4px 0 6px -2px rgba(0,0,0,0.05); }

    tr:hover td { background-color: #ecfeff !important; }

    /* Select2 Custom Style */
    .select2-container .select2-selection--single {
        height: 38px !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0.375rem !important;
        padding: 5px 0 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    
    /* Input Readonly Style */
    .inp-readonly { background-color: #f3f4f6; color: #6b7280; cursor: not-allowed; }
</style>

<div class="space-y-5">
    <div class="bg-white p-4 rounded-xl border border-cyan-100 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-3 w-full md:w-auto">
            <div class="p-3 bg-cyan-50 rounded-xl text-cyan-600 border border-cyan-100"><i class="ti ti-users-group text-2xl"></i></div>
            <div>
                <h3 class="text-lg font-bold text-slate-800">Data Tanggungan</h3>
                <p class="text-xs text-slate-500">Keluarga (Istri/Suami/Anak) Karyawan</p>
            </div>
        </div>

        <div class="flex gap-2 flex-wrap justify-end w-full md:w-auto">
            <a href="cetak/export_tanggungan_pdf.php" target="_blank" class="px-4 py-2 bg-white border border-red-200 text-red-600 rounded-lg hover:bg-red-50 text-sm font-bold flex items-center gap-2 shadow-sm transition group">
                <i class="ti ti-file-type-pdf text-lg group-hover:scale-110 transition"></i> PDF
            </a>
            <a href="cetak/export_tanggungan_excel.php" target="_blank" class="px-4 py-2 bg-white border border-green-200 text-green-600 rounded-lg hover:bg-green-50 text-sm font-bold flex items-center gap-2 shadow-sm transition group">
                <i class="ti ti-file-spreadsheet text-lg group-hover:scale-110 transition"></i> Excel
            </a>
            <?php if ($canInput): ?>
            <button onclick="openImportModalT()" class="px-4 py-2 bg-cyan-50 text-cyan-700 border border-cyan-200 rounded-lg hover:bg-cyan-100 text-sm font-bold flex items-center gap-2 shadow-sm transition">
                <i class="ti ti-file-import"></i> Import
            </button>
            <button id="btn-add-tanggungan" class="px-5 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-bold flex items-center gap-2 shadow-md hover:shadow-lg transition transform hover:-translate-y-0.5">
                <i class="ti ti-plus"></i> Tambah
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
        <div class="flex gap-3 items-center w-full md:w-auto text-sm">
            <div class="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg border">
                <span class="text-gray-500 font-bold uppercase text-xs"><i class="ti ti-list-numbers"></i> Show:</span>
                <select id="limit-t" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer">
                    <option value="10">10</option><option value="25">25</option><option value="50">50</option>
                </select>
            </div>
            <div class="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg border w-full md:w-auto">
                <span class="text-gray-500 font-bold uppercase text-xs"><i class="ti ti-filter"></i> Afd:</span>
                <select id="f_afdeling_t" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer w-32">
                    <option value="">Semua</option>
                </select>
            </div>
        </div>
        
        <div class="relative w-full md:w-80 group">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="ti ti-search text-gray-400 group-focus-within:text-cyan-500 transition"></i>
            </div>
            <input type="text" id="q-tanggungan" class="block w-full p-2.5 pl-10 text-sm border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition" placeholder="Cari Nama Karyawan / Keluarga...">
        </div>
    </div>

    <div class="table-container-t">
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
                    <th class="text-center sticky right-0 z-50 bg-cyan-600 text-white" style="border-left:2px solid #bae6fd; width:100px">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-tanggungan" class="text-gray-700"></tbody>
        </table>
    </div>

    <div class="flex justify-between items-center mt-1">
        <div class="text-xs text-gray-500 italic">Total Data: <span id="info-total-t" class="font-bold">0</span></div>
        <div id="pagination-controls-t" class="flex gap-1"></div>
    </div>
</div>

<div id="import-modal-t" class="fixed inset-0 bg-gray-900/60 z-[60] hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform scale-100 transition-transform">
        <div class="bg-cyan-700 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold flex items-center gap-2"><i class="ti ti-file-import"></i> Import Data</h3>
            <button onclick="closeImportModalT()" class="hover:text-cyan-200"><i class="ti ti-x text-xl"></i></button>
        </div>
        <div class="p-6">
            <div class="bg-cyan-50 p-4 rounded-lg text-sm text-cyan-800 mb-5 border border-cyan-100 flex items-start gap-3">
                <i class="ti ti-info-circle text-xl mt-0.5"></i>
                <div>Download <a href="cetak/template_tanggungan.php" class="font-bold underline hover:text-cyan-900">Template Excel</a>.<br>Pastikan SAP ID Karyawan sesuai database.</div>
            </div>
            <form id="form-import-t">
                <input type="hidden" name="action" value="import">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">File Excel (.xlsx)</label>
                <input type="file" name="file_excel" accept=".xlsx,.xls" class="block w-full text-sm border border-gray-300 p-2 rounded-lg mb-6 text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer" required>
                <button type="submit" class="w-full bg-cyan-600 text-white py-2.5 rounded-lg font-bold hover:bg-cyan-700 shadow-lg transition flex justify-center items-center gap-2">
                    <i class="ti ti-upload"></i> Proses Import
                </button>
            </form>
        </div>
    </div>
</div>

<?php if ($canInput): ?>
<div id="modal-tanggungan" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl overflow-hidden transform transition-all scale-100">
            <div class="px-6 py-4 border-b bg-cyan-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-cyan-900 flex items-center gap-2"><i class="ti ti-user-plus bg-cyan-100 p-1.5 rounded-lg text-cyan-600"></i> Form Data Tanggungan</h3>
                <button id="close-tanggungan" class="text-gray-400 hover:text-red-500 transition"><i class="ti ti-x text-2xl"></i></button>
            </div>
            
            <form id="form-tanggungan" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                <input type="hidden" name="action" id="action-tanggungan">
                <input type="hidden" name="id" id="id-tanggungan">

                <div class="md:col-span-2">
                    <label class="lbl">Pilih Karyawan <span class="text-red-500">*</span></label>
                    <select name="karyawan_id" id="karyawan_id" class="w-full" required>
                        <option value="">-- Ketik Nama / SAP ID --</option>
                    </select>
                </div>

                <div>
                    <label class="lbl">Kebun (Auto)</label>
                    <input type="text" id="view_kebun" class="inp inp-readonly" readonly placeholder="-">
                </div>
                <div>
                    <label class="lbl">Afdeling (Auto)</label>
                    <input type="text" id="view_afdeling" class="inp inp-readonly" readonly placeholder="-">
                </div>
                
                <div class="border-t col-span-2 my-1 border-dashed border-gray-200"></div>

                <div>
                    <label class="lbl">Nama Keluarga <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_batih" id="nama_batih" class="inp" required placeholder="Nama Istri/Anak">
                </div>
                <div>
                    <label class="lbl">Hubungan <span class="text-red-500">*</span></label>
                    <select name="hubungan" id="hubungan" class="inp cursor-pointer" required>
                        <option value="Istri">Istri</option>
                        <option value="Suami">Suami</option>
                        <option value="Anak">Anak</option>
                        <option value="Orang Tua">Orang Tua</option>
                        <option value="Mertua">Mertua</option>
                    </select>
                </div>
                
                <div>
                    <label class="lbl">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" id="tempat_lahir_t" class="inp">
                </div>
                <div>
                    <label class="lbl">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" id="tanggal_lahir_t" class="inp">
                </div>
                
                <div>
                    <label class="lbl">Pendidikan Terakhir</label>
                    <input type="text" name="pendidikan_terakhir" id="pendidikan_terakhir_t" class="inp" placeholder="SD/SMP/SMA...">
                </div>
                <div>
                    <label class="lbl">Pekerjaan</label>
                    <input type="text" name="pekerjaan" id="pekerjaan_t" class="inp" placeholder="Pelajar/Irt...">
                </div>
                
                <div class="md:col-span-2">
                    <label class="lbl">Keterangan Tambahan</label>
                    <textarea name="keterangan" id="keterangan_t" class="inp" rows="2" placeholder="Catatan khusus..."></textarea>
                </div>
            </form>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3">
                <button type="button" id="cancel-tanggungan" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-100 font-bold text-sm transition">Batal</button>
                <button type="button" id="save-tanggungan" class="px-6 py-2.5 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 shadow-md hover:shadow-lg font-bold text-sm transition flex items-center gap-2">
                    <i class="ti ti-device-floppy"></i> Simpan Data
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .lbl { display:block; font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.35rem; letter-spacing:0.025em; }
    .inp { width:100%; border:1px solid #cbd5e1; padding:0.6rem; border-radius:0.5rem; font-size:0.9rem; transition:all 0.2s; } 
    .inp:focus { outline:none; border-color:#0891b2; box-shadow:0 0 0 3px rgba(8,145,178,0.1); }
</style>

<script>
const API_URL_T = 'data_tanggungan_crud.php'; // Ganti jika path berbeda
let pageT = 1; limitT = 10; searchTimeoutT = null;

document.addEventListener('DOMContentLoaded', () => {
    loadAfdelingOption();
    loadTanggungan();

    // Init Select2 di Modal
    if (typeof jQuery !== 'undefined') {
        $('#karyawan_id').select2({
            dropdownParent: $('#modal-tanggungan'),
            placeholder: '-- Cari Karyawan (Ketik Nama/SAP) --',
            allowClear: true,
            width: '100%'
        });

        // Event Listener: Saat karyawan dipilih, ambil detail kebun
        $('#karyawan_id').on('select2:select', function (e) {
            const id = e.params.data.id;
            getKaryawanDetail(id);
        });
        
        // Reset saat clear
        $('#karyawan_id').on('select2:clear', function (e) {
            document.getElementById('view_kebun').value = '';
            document.getElementById('view_afdeling').value = '';
        });
    }

    // Listeners
    document.getElementById('limit-t').addEventListener('change', (e) => { limitT = e.target.value; pageT=1; loadTanggungan(); });
    document.getElementById('f_afdeling_t').addEventListener('change', () => { pageT=1; loadTanggungan(); });
    
    document.getElementById('q-tanggungan').addEventListener('input', (e) => {
        clearTimeout(searchTimeoutT);
        searchTimeoutT = setTimeout(() => { pageT=1; loadTanggungan(); }, 500);
    });
    
    // Form Import
    document.getElementById('form-import-t').addEventListener('submit', handleImportT);

    // Modal Actions
    if(document.getElementById('btn-add-tanggungan')) {
        document.getElementById('btn-add-tanggungan').onclick = () => openModalT('store_tanggungan');
        document.getElementById('close-tanggungan').onclick = closeModalT;
        document.getElementById('cancel-tanggungan').onclick = closeModalT;
        document.getElementById('save-tanggungan').onclick = saveTanggungan;
    }
});

// --- FITUR AUTO FILL KEBUN ---
async function getKaryawanDetail(id) {
    // Tampilkan loading sementara
    document.getElementById('view_kebun').value = 'Memuat...';
    document.getElementById('view_afdeling').value = 'Memuat...';

    const fd = new FormData();
    fd.append('action', 'get_karyawan_detail'); // Pastikan backend handle ini
    fd.append('id', id);

    try {
        const res = await fetch('data_karyawan_crud.php', {method: 'POST', body: fd}); // Pakai file CRUD karyawan
        const json = await res.json();
        
        if (json.success && json.data) {
            document.getElementById('view_kebun').value = json.data.nama_kebun || '-'; // Sesuaikan nama kolom DB
            document.getElementById('view_afdeling').value = json.data.afdeling || '-';
        } else {
            document.getElementById('view_kebun').value = '-';
            document.getElementById('view_afdeling').value = '-';
        }
    } catch (e) {
        console.error("Gagal load detail karyawan", e);
        document.getElementById('view_kebun').value = 'Error';
    }
}

// --- CRUD TABLE ---
async function loadTanggungan() {
    const tbody = document.getElementById('tbody-tanggungan');
    tbody.innerHTML = '<tr><td colspan="12" class="text-center py-12 text-gray-500"><i class="ti ti-loader animate-spin text-2xl text-cyan-600"></i><br>Sedang memuat data...</td></tr>';
    
    const fd = new FormData();
    fd.append('action', 'list_tanggungan');
    fd.append('q', document.getElementById('q-tanggungan').value);
    // fd.append('f_afdeling', document.getElementById('f_afdeling_t').value); // Jika backend support filter afdeling

    try {
        // Backend Tanggungan biasanya di 'data_karyawan_crud.php' action 'list_tanggungan'
        const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
        const json = await res.json();

        if(json.success) {
            // Client-side pagination (jika backend return all data)
            let allData = json.data;
            const filterAfd = document.getElementById('f_afdeling_t').value;
            
            if(filterAfd) {
                allData = allData.filter(item => item.afdeling === filterAfd);
            }

            const total = allData.length;
            const start = (pageT - 1) * limitT;
            const end = start + parseInt(limitT);
            const paginatedData = allData.slice(start, end);

            document.getElementById('info-total-t').innerText = total;
            
            if(total === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center py-10 text-gray-400 italic bg-gray-50">Tidak ada data ditemukan.</td></tr>';
                renderPaginationT(0);
                return;
            }

            tbody.innerHTML = paginatedData.map(r => {
                const rowJson = encodeURIComponent(JSON.stringify(r));
                
                // Badge Hubungan
                let badgeColor = 'bg-gray-100 text-gray-600';
                if(r.hubungan === 'Istri' || r.hubungan === 'Suami') badgeColor = 'bg-blue-100 text-blue-700 border border-blue-200';
                if(r.hubungan === 'Anak') badgeColor = 'bg-green-100 text-green-700 border border-green-200';

                return `
                <tr class="hover:bg-cyan-50 border-b transition duration-150">
                    <td class="p-3 sticky-col-t col-sap-t bg-white font-mono text-xs font-bold text-cyan-700 shadow-sm">${r.id_sap || '-'}</td>
                    <td class="p-3 sticky-col-t col-karyawan-t bg-white font-bold text-gray-800 truncate border-r shadow-sm">${r.nama_lengkap || '-'}</td>
                    <td class="p-3 sticky-col-t col-keluarga-t bg-white font-bold text-gray-900 truncate border-r shadow-md">${r.nama_batih}</td>
                    
                    <td class="p-3 text-center"><span class="${badgeColor} text-[10px] px-2 py-1 rounded font-bold uppercase tracking-wide">${r.hubungan}</span></td>
                    <td class="p-3 text-sm">${r.kebun_id || r.nama_kebun || '-'}</td>
                    <td class="p-3 text-sm text-center">${r.afdeling || '-'}</td>
                    <td class="p-3 text-sm">${r.tempat_lahir || '-'}</td>
                    <td class="p-3 text-sm text-center">${r.tanggal_lahir || '-'}</td>
                    <td class="p-3 text-sm text-center">${r.pendidikan_terakhir || '-'}</td>
                    <td class="p-3 text-sm">${r.pekerjaan || '-'}</td>
                    <td class="p-3 text-sm text-gray-500 italic truncate max-w-xs">${r.keterangan || '-'}</td>
                    
                    <?php if($canAction): ?>
                    <td class="p-3 text-center sticky right-0 bg-white border-l shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)]">
                        <div class="flex justify-center gap-2">
                            <button onclick="editT('${rowJson}')" class="w-8 h-8 flex items-center justify-center text-blue-600 hover:bg-blue-100 rounded-full transition shadow-sm border border-blue-100"><i class="ti ti-pencil"></i></button>
                            <button onclick="delT(${r.id})" class="w-8 h-8 flex items-center justify-center text-red-600 hover:bg-red-100 rounded-full transition shadow-sm border border-red-100"><i class="ti ti-trash"></i></button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>`;
            }).join('');
            
            renderPaginationT(Math.ceil(total/limitT));
        }
    } catch(e) { console.error('Fetch Error:', e); }
}

// --- MODAL & FORM ---
async function openModalT(mode) {
    document.getElementById('form-tanggungan').reset();
    document.getElementById('action-tanggungan').value = mode;
    document.getElementById('id-tanggungan').value = '';
    document.getElementById('view_kebun').value = '';
    document.getElementById('view_afdeling').value = '';
    
    // Reset Select2
    $('#karyawan_id').val(null).trigger('change');

    // Load Karyawan Data untuk Dropdown
    const sel = document.getElementById('karyawan_id');
    const fd = new FormData(); fd.append('action', 'list_karyawan_simple');
    
    try {
        const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
        const json = await res.json();
        if(json.success) {
            let html = '<option value="">-- Pilih Karyawan --</option>';
            json.data.forEach(k => {
                const sap = k.id_sap || k.sap_id || '-';
                html += `<option value="${k.id}">${sap} - ${k.nama_karyawan}</option>`;
            });
            sel.innerHTML = html;
        }
    } catch (e) { console.error(e); }

    document.getElementById('modal-tanggungan').classList.remove('hidden');
    document.getElementById('modal-tanggungan').classList.add('flex');
}

function closeModalT() {
    document.getElementById('modal-tanggungan').classList.add('hidden');
    document.getElementById('modal-tanggungan').classList.remove('flex');
}

window.editT = (json) => {
    const r = JSON.parse(decodeURIComponent(json));
    openModalT('update_tanggungan').then(() => {
        setTimeout(() => {
            document.getElementById('id-tanggungan').value = r.id;
            // Set Select2 & Trigger Detail
            $('#karyawan_id').val(r.karyawan_id).trigger('change');
            
            // Isi detail lain
            document.getElementById('nama_batih').value = r.nama_batih;
            document.getElementById('hubungan').value = r.hubungan;
            document.getElementById('tempat_lahir_t').value = r.tempat_lahir;
            document.getElementById('tanggal_lahir_t').value = r.tanggal_lahir;
            document.getElementById('pendidikan_terakhir_t').value = r.pendidikan_terakhir;
            document.getElementById('pekerjaan_t').value = r.pekerjaan;
            document.getElementById('keterangan_t').value = r.keterangan;
            
            // Trigger load kebun manual jika event on change select2 belum jalan
            getKaryawanDetail(r.karyawan_id);
        }, 500);
    });
};

async function saveTanggungan() {
    const btn = document.getElementById('save-tanggungan');
    const txt = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Menyimpan...'; btn.disabled = true;

    const fd = new FormData(document.getElementById('form-tanggungan'));
    try {
        const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
        const json = await res.json();
        if(json.success) {
            closeModalT();
            Swal.fire('Sukses', 'Data keluarga berhasil disimpan', 'success');
            loadTanggungan();
        } else {
            Swal.fire('Gagal', json.message || 'Terjadi kesalahan', 'error');
        }
    } catch(e) { console.error(e); } 
    finally { btn.innerHTML = txt; btn.disabled = false; }
}

window.delT = (id) => {
    Swal.fire({title:'Hapus Data?', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33'}).then(res => {
        if(res.isConfirmed) {
            const fd = new FormData(); fd.append('action', 'delete_tanggungan'); fd.append('id', id);
            fetch('data_karyawan_crud.php', {method:'POST', body:fd}).then(r=>r.json()).then(j => {
                if(j.success) { Swal.fire('Terhapus','','success'); loadTanggungan(); }
            });
        }
    });
};

async function loadAfdelingOption() {
    const fd = new FormData(); fd.append('action', 'list_options');
    const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
    const json = await res.json();
    if(json.success && json.afdeling) {
        let html = '<option value="">Semua</option>';
        json.afdeling.forEach(a => html += `<option value="${a}">${a}</option>`);
        document.getElementById('f_afdeling_t').innerHTML = html;
    }
}

function renderPaginationT(totalPages) {
    let html = '';
    for(let i=1; i<=totalPages; i++) {
        if(i===1 || i===totalPages || (i>=pageT-1 && i<=pageT+1)) {
            let active = i===pageT ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-gray-600 hover:bg-gray-100 border-gray-300';
            html += `<button onclick="pageT=${i};loadTanggungan()" class="w-8 h-8 flex items-center justify-center border rounded text-sm font-bold transition ${active}">${i}</button>`;
        }
    }
    document.getElementById('pagination-controls-t').innerHTML = html;
}

// Import Handler
function openImportModalT(){ document.getElementById('import-modal-t').classList.remove('hidden'); document.getElementById('import-modal-t').classList.add('flex'); }
function closeImportModalT(){ document.getElementById('import-modal-t').classList.add('hidden'); document.getElementById('import-modal-t').classList.remove('flex'); }

function handleImportT(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Memproses...'; btn.disabled = true;
    
    // Ganti action jika perlu penyesuaian di backend (misal: import_tanggungan)
    const fd = new FormData(e.target);
    
    // Pastikan backend support action 'import' atau sesuaikan valuenya
    // fd.append('action', 'import_tanggungan'); 

    fetch('data_karyawan_crud.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(j => {
            if(j.success) { 
                closeImportModalT(); 
                Swal.fire('Sukses', j.message, 'success'); 
                loadTanggungan(); 
            } else { 
                Swal.fire('Gagal', j.message, 'error'); 
            }
        })
        .catch(e => { console.error(e); Swal.fire('Error', 'Terjadi kesalahan jaringan', 'error'); })
        .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
}
</script>