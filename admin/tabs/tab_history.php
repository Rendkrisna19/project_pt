<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Styling Custom untuk Select2 agar sesuai tema Tailwind */
    .select2-container .select2-selection--single {
        height: 42px !important;
        border: 1px solid #d1d5db !important; /* Gray-300 */
        border-radius: 0.5rem !important; /* Rounded-lg */
        padding: 6px 0 !important;
        background-color: #f9fafb !important; /* Gray-50 */
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    
    /* Header Table Styles */
    .thead-history th { background: #0e7490 !important; color: white; z-index: 40; }
    .sticky-col-h { position: sticky; left: 0; z-index: 20; background: #fff; border-right: 2px solid #cbd5e1 !important; }
    .thead-history .sticky-col-h { z-index: 50; background: #0e7490 !important; }

    .col-kebun-h { left: 0px; width: 120px; }
    .col-sap-h   { left: 120px; width: 100px; }
    .col-nama-h  { left: 220px; width: 250px; box-shadow: 4px 0 6px -2px rgba(0,0,0,0.1); }
    
    .sticky-action-h { position: sticky; right: 0; z-index: 20; background: #fff; border-left: 2px solid #cbd5e1 !important; text-align: center; }
    .thead-history .sticky-action-h { z-index: 50; background: #0e7490 !important; }

    tr:hover td { background-color: #ecfeff !important; }
</style>

<div class="space-y-4">
    <div class="bg-white p-4 rounded-xl border border-cyan-200 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-cyan-100 rounded-lg text-cyan-700"><i class="ti ti-history text-2xl"></i></div>
            <div>
                <h3 class="text-lg font-bold text-slate-800">Riwayat Jabatan & Mutasi</h3>
                <p class="text-sm text-slate-500">Mencatat promosi, mutasi, dan demosi karyawan</p>
            </div>
        </div>
        
        <div class="flex gap-2">
            <button onclick="doExportHistory('excel')" class="px-3 py-2 bg-cyan-600 text-white rounded-lg hover:bg-green-700 text-sm font-bold flex items-center gap-2 shadow-sm">
                <i class="ti ti-file-spreadsheet"></i> Excel
            </button>
            <button onclick="doExportHistory('pdf')" class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-bold flex items-center gap-2 shadow-sm">
                <i class="ti ti-file-type-pdf"></i> PDF
            </button>
            
            <?php if ($canInput): ?>
            <button onclick="openModalHistory('store')" class="px-4 py-2 bg-cyan-700 text-white rounded-lg hover:bg-cyan-800 text-sm font-bold flex items-center gap-2 shadow-md ml-2">
                <i class="ti ti-plus"></i> Tambah History
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex gap-2">
            <select id="limit_h" class="bg-gray-50 border rounded-lg p-2 text-sm w-20 outline-none focus:ring-2 focus:ring-cyan-500"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
            <select id="filter_kebun_h" class="bg-gray-50 border rounded-lg p-2 text-sm w-48 outline-none focus:ring-2 focus:ring-cyan-500"><option value="">Semua Kebun</option></select>
        </div>
        <div class="relative w-full md:w-80">
            <input type="text" id="q_history" class="w-full pl-10 pr-4 py-2 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-cyan-500" placeholder="Cari Nama / No Surat...">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
        </div>
    </div>

    <div class="sticky-container table-container-mbt" style="max-height: 70vh; overflow: auto;">
        <table class="table-grid-mbt w-full border-collapse">
            <thead class="thead-history">
                <tr>
                    <th class="sticky-col-h col-kebun-h p-3 text-left">Kebun</th>
                    <th class="sticky-col-h col-sap-h p-3 text-left">SAP ID</th>
                    <th class="sticky-col-h col-nama-h p-3 text-left">Nama Karyawan</th>
                    <th class="p-3 text-left">Afdeling</th>
                    <th class="p-3 text-left">Strata</th>
                    <th class="p-3 text-center">Tgl Surat</th>
                    <th class="p-3 text-left">No Surat</th>
                    <th class="p-3 text-left">Jabatan Lama</th>
                    <th class="p-3 text-left">Jabatan Baru</th>
                    <th class="p-3 text-left">Keterangan</th>
                    <th class="p-3 text-center">File</th>
                    <?php if ($canAction): ?>
                    <th class="sticky-action-h p-3 text-center">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-history" class="text-gray-700"></tbody>
        </table>
    </div>
    
    <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-200 mt-2">
        <div class="text-xs text-gray-500">Info: <span id="info-h">0 Data</span></div>
        <div id="pagination-h" class="flex gap-1"></div>
    </div>
</div>

<?php if ($canInput): ?>
<div id="modal-history" class="fixed inset-0 bg-gray-900/50 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b bg-cyan-50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-cyan-900">Form History Jabatan</h3>
            <button onclick="closeModalHistory()" class="text-gray-400 hover:text-red-500"><i class="ti ti-x text-2xl"></i></button>
        </div>
        
        <form id="form-history" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5 max-h-[75vh] overflow-y-auto">
            <input type="hidden" name="action" id="action-h">
            <input type="hidden" name="id" id="id-h">

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pilih Karyawan *</label>
                <select name="karyawan_id" id="karyawan_id_h" class="w-full" required style="width: 100%;">
                    <option value="">-- Ketik Nama / SAP ID --</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kebun</label>
                <select name="kebun_id" id="kebun_id_h" class="w-full border p-2 rounded-lg bg-gray-50">
                    <option value="">-- Pilih Kebun --</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Afdeling</label>
                <input type="text" name="afdeling" id="afdeling_h" class="w-full border p-2 rounded-lg">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">No Surat</label>
                <input type="text" name="no_surat" id="no_surat_h" class="w-full border p-2 rounded-lg" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tgl Surat</label>
                <input type="date" name="tgl_surat" id="tgl_surat_h" class="w-full border p-2 rounded-lg" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Jabatan Lama</label>
                <input type="text" name="jabatan_lama" id="jabatan_lama_h" class="w-full border p-2 rounded-lg">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Jabatan Baru</label>
                <input type="text" name="jabatan_baru" id="jabatan_baru_h" class="w-full border p-2 rounded-lg" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Strata / Golongan</label>
                <input type="text" name="strata" id="strata_h" class="w-full border p-2 rounded-lg">
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Keterangan (Mutasi/Promosi)</label>
                <textarea name="keterangan" id="keterangan_h" rows="2" class="w-full border p-2 rounded-lg"></textarea>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">File SK (PDF/Img)</label>
                <input type="file" name="file_sk" id="file_sk" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100">
                <div id="preview-file-h" class="mt-1 text-xs text-blue-600"></div>
            </div>
        </form>

        <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-2">
            <button onclick="closeModalHistory()" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-100">Batal</button>
            <button onclick="saveHistory()" id="btn-save-h" class="px-4 py-2 bg-cyan-700 text-white rounded-lg hover:bg-cyan-800 shadow-lg">Simpan</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const API_URL_H = './data_history_crud.php';
let pageH = 1, limitH = 10;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Load Data Awal
    loadOptionsH(); 
    loadHistory(); 

    // 2. Initialize Select2 untuk Dropdown Karyawan
    if(jQuery && jQuery.fn.select2) {
        $('#karyawan_id_h').select2({
            dropdownParent: $('#modal-history'), // Agar dropdown muncul di atas modal
            placeholder: '-- Ketik Nama / SAP ID --',
            allowClear: true,
            width: '100%'
        });
    } else {
        console.warn('Select2 belum dimuat dengan benar.');
    }

    // 3. Listeners
    document.getElementById('limit_h').addEventListener('change', (e) => { limitH = e.target.value; pageH=1; loadHistory(); });
    document.getElementById('filter_kebun_h').addEventListener('change', () => { pageH=1; loadHistory(); });
    document.getElementById('q_history').addEventListener('input', debounce(loadHistory, 500));
});

// Function Export
function doExportHistory(type) {
    const lim = document.getElementById('limit_h').value;
    const keb = document.getElementById('filter_kebun_h').value;
    const q   = document.getElementById('q_history').value;
    
    let url = type === 'excel' 
        ? `./cetak/history_excel.php?limit=${lim}&kebun_id=${keb}&q=${q}`
        : `./cetak/history_pdf.php?limit=${lim}&kebun_id=${keb}&q=${q}`;
        
    window.open(url, '_blank');
}

// Load Dropdowns Options
async function loadOptionsH() {
    const fd = new FormData(); fd.append('action', 'list_options');
    const res = await fetch(API_URL_H, {method: 'POST', body: fd});
    const json = await res.json();
    
    if (json.success) {
        // Filter Kebun
        let htmlK = '<option value="">Semua Kebun</option>';
        json.kebun.forEach(k => htmlK += `<option value="${k.id}">${k.nama_kebun}</option>`);
        document.getElementById('filter_kebun_h').innerHTML = htmlK;
        
        // Modal Kebun
        if(document.getElementById('kebun_id_h')) {
            document.getElementById('kebun_id_h').innerHTML = htmlK.replace('Semua Kebun', '-- Pilih Kebun --');
        }

        // Modal Karyawan (PERBAIKAN UNDEFINED)
        if(document.getElementById('karyawan_id_h')) {
            let htmlKar = '<option value="">-- Pilih Karyawan --</option>';
            json.karyawan.forEach(k => {
                // Gunakan id_sap jika sap_id undefined
                const sap = k.sap_id || k.id_sap || '-';
                htmlKar += `<option value="${k.id}">${sap} - ${k.nama_karyawan}</option>`;
            });
            document.getElementById('karyawan_id_h').innerHTML = htmlKar;
        }
    }
}

// Load Table Data
async function loadHistory() {
    const tbody = document.getElementById('tbody-history');
    tbody.innerHTML = '<tr><td colspan="12" class="text-center py-10">Memuat data...</td></tr>';

    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('page', pageH);
    fd.append('limit', limitH);
    fd.append('q', document.getElementById('q_history').value);
    fd.append('kebun_id', document.getElementById('filter_kebun_h').value);

    try {
        const res = await fetch(API_URL_H, {method: 'POST', body: fd});
        const json = await res.json();

        if (json.success) {
            if (json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center py-10 text-gray-400">Tidak ada data history.</td></tr>';
                document.getElementById('info-h').innerText = '0 Data';
                return;
            }

            tbody.innerHTML = json.data.map(r => {
                const rowJson = encodeURIComponent(JSON.stringify(r));
                const fileLink = r.file_sk ? `<a href="../uploads/sk/${r.file_sk}" target="_blank" class="text-blue-600 underline text-xs">Lihat</a>` : '-';
                // Fix sap_id vs id_sap di tabel juga
                const sapDisplay = r.sap_id || r.id_sap || '-';
                
                return `
                <tr class="hover:bg-cyan-50 border-b transition">
                    <td class="sticky-col-h col-kebun-h p-3 bg-white text-sm border-r">${r.nama_kebun || '-'}</td>
                    <td class="sticky-col-h col-sap-h p-3 bg-white font-mono text-xs font-bold text-cyan-700 border-r">${sapDisplay}</td>
                    <td class="sticky-col-h col-nama-h p-3 bg-white font-bold text-gray-800 text-sm truncate border-r">${r.nama_karyawan}</td>
                    
                    <td class="p-3 text-sm">${r.afdeling || '-'}</td>
                    <td class="p-3 text-center text-xs font-bold bg-slate-50 rounded">${r.strata || '-'}</td>
                    <td class="p-3 text-center text-sm">${r.tgl_surat}</td>
                    <td class="p-3 text-sm font-mono">${r.no_surat}</td>
                    <td class="p-3 text-sm text-gray-500">${r.jabatan_lama || '-'}</td>
                    <td class="p-3 text-sm font-bold text-gray-700">${r.jabatan_baru}</td>
                    <td class="p-3 text-sm italic">${r.keterangan || '-'}</td>
                    <td class="p-3 text-center">${fileLink}</td>
                    
                    <?php if($canAction): ?>
                    <td class="sticky-action-h p-3 bg-white border-l">
                        <button onclick="editHistory('${rowJson}')" class="text-blue-600 p-1 hover:bg-blue-50 rounded"><i class="ti ti-pencil"></i></button>
                        <button onclick="deleteHistory(${r.id})" class="text-red-600 p-1 hover:bg-red-50 rounded"><i class="ti ti-trash"></i></button>
                    </td>
                    <?php endif; ?>
                </tr>`;
            }).join('');
            
            document.getElementById('info-h').innerText = json.total + ' Data';
            renderPaginationH(Math.ceil(json.total/limitH));
        }
    } catch (e) { console.error(e); }
}

// Modal Functions
function openModalHistory(mode) {
    document.getElementById('form-history').reset();
    document.getElementById('action-h').value = mode;
    document.getElementById('id-h').value = '';
    document.getElementById('preview-file-h').innerHTML = '';
    
    // Reset Select2 value
    $('#karyawan_id_h').val(null).trigger('change');

    document.getElementById('modal-history').classList.remove('hidden');
    document.getElementById('modal-history').classList.add('flex');
}

function closeModalHistory() {
    document.getElementById('modal-history').classList.add('hidden');
    document.getElementById('modal-history').classList.remove('flex');
}

window.editHistory = (json) => {
    const r = JSON.parse(decodeURIComponent(json));
    openModalHistory('update');
    setTimeout(() => {
        document.getElementById('id-h').value = r.id;
        // Set Select2 Value dengan trigger change
        $('#karyawan_id_h').val(r.karyawan_id).trigger('change');
        
        document.getElementById('kebun_id_h').value = r.kebun_id;
        document.getElementById('afdeling_h').value = r.afdeling;
        document.getElementById('no_surat_h').value = r.no_surat;
        document.getElementById('tgl_surat_h').value = r.tgl_surat;
        document.getElementById('jabatan_lama_h').value = r.jabatan_lama;
        document.getElementById('jabatan_baru_h').value = r.jabatan_baru;
        document.getElementById('strata_h').value = r.strata;
        document.getElementById('keterangan_h').value = r.keterangan;
        if(r.file_sk) document.getElementById('preview-file-h').innerText = "File ada: " + r.file_sk;
    }, 100);
};

async function saveHistory() {
    const btn = document.getElementById('btn-save-h');
    btn.innerText = 'Menyimpan...'; btn.disabled = true;
    
    const fd = new FormData(document.getElementById('form-history'));
    try {
        const res = await fetch(API_URL_H, {method: 'POST', body: fd});
        const json = await res.json();
        if(json.success) {
            closeModalHistory();
            Swal.fire('Sukses', 'Data berhasil disimpan', 'success');
            loadHistory();
        } else {
            Swal.fire('Gagal', 'Gagal menyimpan data', 'error');
        }
    } catch(e) { console.error(e); }
    finally { btn.innerText = 'Simpan'; btn.disabled = false; }
}

window.deleteHistory = (id) => {
    Swal.fire({title:'Hapus Data?', icon:'warning', showCancelButton:true, confirmButtonText:'Hapus', confirmButtonColor:'#d33'}).then(r => {
        if(r.isConfirmed) {
            const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
            fetch(API_URL_H, {method:'POST', body:fd}).then(res=>res.json()).then(j => {
                if(j.success) { loadHistory(); Swal.fire('Terhapus','','success'); }
            });
        }
    });
};

function renderPaginationH(total) {
    let html = '';
    for(let i=1; i<=total; i++) {
        let active = i===pageH ? 'bg-cyan-700 text-white' : 'bg-white border hover:bg-gray-50';
        html += `<button onclick="pageH=${i};loadHistory()" class="px-3 py-1 rounded ${active}">${i}</button>`;
    }
    document.getElementById('pagination-h').innerHTML = html;
}

function debounce(func, wait) { let timeout; return function(...args) { clearTimeout(timeout); timeout = setTimeout(() => func.apply(this, args), wait); }; }
</script>