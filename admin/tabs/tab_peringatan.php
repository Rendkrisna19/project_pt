<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* Custom Scrollbar */
    .table-container-sp {
        max-height: 70vh; 
        overflow: auto; 
        position: relative;
        border: 1px solid #fecaca; /* Red-200 */
        border-radius: 0.75rem; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: white;
    }

    /* Table Styling */
    table.table-grid-sp {
        width: 100%; 
        border-collapse: separate; 
        border-spacing: 0; 
        min-width: 1600px;
    }

    table.table-grid-sp th, 
    table.table-grid-sp td {
        padding: 0.85rem; 
        font-size: 0.85rem; 
        border-bottom: 1px solid #fee2e2; 
        border-right: 1px solid #fee2e2; 
        vertical-align: middle; 
    }

    /* Sticky Header (Red Theme) */
    table.table-grid-sp thead th {
        position: sticky; 
        top: 0; 
        background: #dc2626; /* Red-600 */
        color: #fff; 
        z-index: 40; 
        font-weight: 600; 
        text-transform: uppercase; 
        letter-spacing: 0.05em;
        height: 50px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Sticky Columns (Left) */
    th.sticky-col-sp, td.sticky-col-sp {
        position: sticky; 
        left: 0; 
        z-index: 20; 
        border-right: 2px solid #fecaca; 
    }

    /* Intersection Header */
    thead th.sticky-col-sp { z-index: 50; background: #dc2626; }
    
    /* Column Positions */
    .col-sap-sp  { left: 0px; width: 100px; text-align: center; }
    .col-nama-sp { left: 100px; width: 250px; box-shadow: 4px 0 6px -2px rgba(0,0,0,0.05); }

    /* Hover Rows */
    tr:hover td { background-color: #fef2f2 !important; }

    /* Form Label & Input */
    .lbl { display: block; font-size: 0.75rem; font-weight: 700; color: #4b5563; text-transform: uppercase; margin-bottom: 0.35rem; }
    .inp { width: 100%; border: 1px solid #d1d5db; padding: 0.6rem; border-radius: 0.5rem; font-size: 0.9rem; transition: all 0.2s; } 
    .inp:focus { outline: none; border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }

    /* Select2 Customization for Tailwind */
    .select2-container .select2-selection--single {
        height: 42px !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0.5rem !important;
        padding: 6px 0 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #374151 !important;
        font-size: 0.9rem;
    }
</style>

<div class="space-y-5">
    <div class="bg-white p-5 rounded-xl border border-red-100 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-4 w-full md:w-auto">
            <div class="p-3 bg-red-50 rounded-xl text-red-600 border border-red-100">
                <i class="ti ti-alert-triangle text-3xl"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">Surat Peringatan</h3>
                <p class="text-sm text-gray-500">Monitoring pelanggaran & sanksi karyawan</p>
            </div>
        </div>
        
        <div class="flex flex-wrap gap-2 w-full md:w-auto justify-end">
            <a href="cetak/sp_pdf.php" target="_blank" class="px-4 py-2 bg-white border border-red-200 text-red-700 rounded-lg hover:bg-red-50 text-sm font-bold flex items-center gap-2 shadow-sm transition group">
                <i class="ti ti-file-type-pdf text-lg group-hover:scale-110 transition"></i> PDF
            </a>
            
            <a href="cetak/sp_excel.php" target="_blank" class="px-4 py-2 bg-white border border-green-200 text-green-700 rounded-lg hover:bg-green-50 text-sm font-bold flex items-center gap-2 shadow-sm transition group">
                <i class="ti ti-file-spreadsheet text-lg group-hover:scale-110 transition"></i> Excel
            </a>

            <?php if ($canInput): ?>
            <button id="btn-add-sp" class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-bold flex items-center gap-2 shadow-md hover:shadow-lg transition transform hover:-translate-y-0.5">
                <i class="ti ti-plus"></i> Tambah SP
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col lg:flex-row gap-4 justify-between items-center">
        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
            <div class="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                <span class="text-gray-500 text-xs font-bold uppercase"><i class="ti ti-filter"></i> Filter:</span>
                <select id="f_afdeling_sp" class="bg-transparent border-none text-sm font-medium focus:ring-0 cursor-pointer text-gray-700">
                    <option value="">Semua Afdeling</option>
                </select>
            </div>
            
            <div class="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                <span class="text-gray-500 text-xs font-bold uppercase"><i class="ti ti-tag"></i> Jenis:</span>
                <select id="f_jenis_sp" class="bg-transparent border-none text-sm font-medium focus:ring-0 cursor-pointer text-gray-700">
                    <option value="">Semua</option>
                    <option value="Teguran">Teguran</option>
                    <option value="SP1">SP 1</option>
                    <option value="SP2">SP 2</option>
                    <option value="SP3">SP 3</option>
                </select>
            </div>
        </div>

        <div class="relative w-full lg:w-96 group">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="ti ti-search text-gray-400 group-focus-within:text-red-500 transition"></i>
            </div>
            <input type="text" id="q-sp" class="block w-full p-2.5 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition" placeholder="Cari Nama Karyawan / No Surat...">
        </div>
    </div>

    <div class="table-container-sp">
        <table class="table-grid-sp" id="table-sp">
            <thead>
                <tr>
                    <th class="sticky-col-sp col-sap-sp">SAP ID</th>
                    <th class="sticky-col-sp col-nama-sp">Nama Karyawan</th>
                    <th>Kebun</th>
                    <th>Afdeling</th>
                    <th>Status</th>
                    <th>No Surat</th>
                    <th>Jenis SP</th>
                    <th>Tgl SP</th>
                    <th>Masa Berlaku</th>
                    <th style="min-width: 200px;">Pelanggaran</th>
                    <th style="min-width: 150px;">Sanksi</th>
                    <th class="text-center">File</th>
                    <?php if ($canAction): ?>
                    <th class="text-center sticky right-0 z-50 bg-red-700 text-white shadow-lg" style="border-left:2px solid #fecaca; width: 100px;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-sp" class="text-gray-700 font-medium"></tbody>
        </table>
    </div>
    
    <div class="text-xs text-gray-500 text-right italic" id="info-sp">Memuat data...</div>
</div>

<?php if ($canInput): ?>
<div id="modal-sp" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-[60] hidden items-center justify-center p-4 transition-opacity">
    <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden transform scale-100 transition-transform">
        <div class="px-6 py-4 border-b bg-red-50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-red-900 flex items-center gap-2">
                <div class="p-1 bg-red-100 rounded text-red-600"><i class="ti ti-file-alert"></i></div>
                Form Surat Peringatan
            </h3>
            <button id="close-sp" class="text-gray-400 hover:text-red-500 transition"><i class="ti ti-x text-2xl"></i></button>
        </div>
        
        <form id="form-sp" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5 max-h-[80vh] overflow-y-auto">
            <input type="hidden" name="action" id="action-sp">
            <input type="hidden" name="id" id="id-sp">
            <div class="md:col-span-2 bg-red-50/50 border border-red-100 p-3 rounded-lg flex flex-wrap items-center gap-2 text-xs text-gray-600">
                <span class="font-bold text-red-700 uppercase mr-2"><i class="ti ti-info-circle"></i> Alur Sanksi:</span>
                <span class="bg-white px-2 py-1 rounded border shadow-sm">Teguran</span>
                <i class="ti ti-arrow-right text-gray-400"></i>
                <span class="bg-white px-2 py-1 rounded border shadow-sm">SP 1</span>
                <i class="ti ti-arrow-right text-gray-400"></i>
                <span class="bg-white px-2 py-1 rounded border shadow-sm">SP 2</span>
                <i class="ti ti-arrow-right text-gray-400"></i>
                <span class="bg-white px-2 py-1 rounded border border-red-200 text-red-600 font-bold shadow-sm">SP 3 (PHK)</span>
            </div>

            <div class="md:col-span-2">
                <label class="lbl">Pilih Karyawan <span class="text-red-500">*</span></label>
                <select name="karyawan_id" id="karyawan_id_sp" class="w-full" required>
                    <option value="">-- Ketik Nama / ID SAP --</option>
                </select>
            </div>

            <div>
                <label class="lbl">No. Surat <span class="text-red-500">*</span></label>
                <input type="text" name="no_surat" id="no_surat" class="inp" placeholder="Nomor Surat..." required>
            </div>

            <div>
                <label class="lbl">Jenis SP <span class="text-red-500">*</span></label>
                <select name="jenis_sp" id="jenis_sp" class="inp cursor-pointer bg-white" required>
                    <option value="Teguran">Teguran Lisan/Tertulis</option>
                    <option value="SP1">SP 1 (Peringatan I)</option>
                    <option value="SP2">SP 2 (Peringatan II)</option>
                    <option value="SP3">SP 3 (Peringatan III)</option>
                </select>
            </div>

            <div>
                <label class="lbl">Tanggal SP <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal_sp" id="tanggal_sp" class="inp" required>
            </div>

            <div>
                <label class="lbl">Masa Berlaku (Opsional)</label>
                <input type="date" name="masa_berlaku" id="masa_berlaku" class="inp">
            </div>

            <div class="md:col-span-2">
                <label class="lbl">Detail Pelanggaran</label>
                <textarea name="pelanggaran" id="pelanggaran" rows="3" class="inp" placeholder="Jelaskan detail pelanggaran..."></textarea>
            </div>

            <div class="md:col-span-2">
                <label class="lbl">Sanksi Diberikan</label>
                <textarea name="sanksi" id="sanksi" rows="2" class="inp" placeholder="Contoh: Potong Tunjangan / Skorsing..."></textarea>
            </div>

            <div class="md:col-span-2">
                <label class="lbl">Upload Scan Surat (PDF/IMG)</label>
                <input type="file" name="file_scan" id="file_scan" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100 transition cursor-pointer border border-gray-300 rounded-lg" accept=".pdf,image/*">
                <div id="preview-file-sp" class="mt-2 text-xs text-blue-600"></div>
            </div>
        </form>

        <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3">
            <button type="button" id="cancel-sp" class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 font-bold transition text-sm">Batal</button>
            <button type="button" id="save-sp" class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-lg hover:shadow-red-500/30 font-bold transition text-sm flex items-center gap-2">
                <i class="ti ti-device-floppy"></i> Simpan Data
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Backend URL
const API_URL_SP = 'data_karyawan_crud.php'; 

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial Load
    loadAfdelingSP();
    loadSP();

    // 2. Setup Select2 untuk Modal (Agar bisa diketik)
    // Cek jQuery karena Select2 butuh jQuery
    if (typeof jQuery !== 'undefined') {
        $('#karyawan_id_sp').select2({
            dropdownParent: $('#modal-sp'), // Penting agar dropdown muncul di atas modal
            placeholder: '-- Ketik Nama / ID SAP --',
            allowClear: true,
            width: '100%'
        });
    }

    // 3. Listeners
    document.getElementById('f_afdeling_sp').addEventListener('change', loadSP);
    document.getElementById('f_jenis_sp').addEventListener('change', loadSP);
    
    // Search Debounce
    let timerSP;
    document.getElementById('q-sp').addEventListener('input', () => {
        clearTimeout(timerSP);
        timerSP = setTimeout(loadSP, 500);
    });

    // 4. Modal Handlers
    if(document.getElementById('btn-add-sp')) {
        document.getElementById('btn-add-sp').onclick = () => openModalSP('store_peringatan');
        document.getElementById('close-sp').onclick = () => closeModalSP();
        document.getElementById('cancel-sp').onclick = () => closeModalSP();
        document.getElementById('save-sp').onclick = saveSP;
    }
});

// Load Options Filter
async function loadAfdelingSP() {
    const fd = new FormData(); fd.append('action', 'list_options'); 
    try {
        const res = await fetch(API_URL_SP, {method:'POST', body:fd});
        const json = await res.json();
        if(json.success) {
            let html = '<option value="">Semua Afdeling</option>';
            if(json.afdeling) {
                json.afdeling.forEach(a => html += `<option value="${a}">${a}</option>`);
            }
            document.getElementById('f_afdeling_sp').innerHTML = html;
        }
    } catch(e) { console.error("Err Afdeling SP", e); }
}

// Load Main Table
async function loadSP() {
    const tbody = document.getElementById('tbody-sp');
    tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-3xl text-red-500"></i><br>Sedang memuat data...</td></tr>';
    
    const fd = new FormData();
    fd.append('action', 'list_peringatan');
    fd.append('q', document.getElementById('q-sp').value);
    fd.append('f_afdeling', document.getElementById('f_afdeling_sp').value);
    
    try {
        const res = await fetch(API_URL_SP, {method:'POST', body:fd});
        const json = await res.json();

        if(json.success) {
            let data = json.data;
            const typeFilter = document.getElementById('f_jenis_sp').value;
            if(typeFilter) {
                data = data.filter(r => r.jenis_sp === typeFilter);
            }

            if(data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center py-12 text-gray-400 italic bg-gray-50">Tidak ada data ditemukan.</td></tr>';
                document.getElementById('info-sp').innerText = '0 Data';
                return;
            }

            tbody.innerHTML = data.map(r => {
                const rowJson = encodeURIComponent(JSON.stringify(r));
                
                let badgeClass = 'bg-gray-100 text-gray-700';
                if(r.jenis_sp === 'Teguran') badgeClass = 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                if(r.jenis_sp === 'SP1') badgeClass = 'bg-orange-100 text-orange-700 border border-orange-200';
                if(r.jenis_sp === 'SP2') badgeClass = 'bg-orange-600 text-white shadow-sm';
                if(r.jenis_sp === 'SP3') badgeClass = 'bg-red-600 text-white shadow-sm animate-pulse';

                const dateIndo = (d) => d ? new Date(d).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-';

                const fileLink = r.file_scan ? 
                    `<a href="../uploads/sp/${r.file_scan}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs flex items-center justify-center gap-1 bg-blue-50 px-2 py-1 rounded border border-blue-200 hover:bg-blue-100 transition"><i class="ti ti-file-text"></i> Lihat</a>` 
                    : '<span class="text-gray-300">-</span>';

                return `
                <tr class="hover:bg-red-50 border-b transition duration-150">
                    <td class="p-3 sticky-col-sp col-sap-sp bg-white font-mono text-xs font-bold text-red-700">${r.sap_id || '-'}</td>
                    <td class="p-3 sticky-col-sp col-nama-sp bg-white font-bold text-gray-800 truncate">${r.nama_karyawan || '-'}</td>
                    
                    <td class="p-3 text-sm">${r.nama_kebun || '-'}</td>
                    <td class="p-3 text-sm text-center">${r.afdeling || '-'}</td>
                    <td class="p-3 text-center"><span class="bg-gray-100 text-xs px-2 py-1 rounded font-mono border">${r.status_karyawan || '-'}</span></td>
                    
                    <td class="p-3 text-sm font-mono text-gray-600">${r.no_surat}</td>
                    <td class="p-3 text-center"><span class="${badgeClass} px-2.5 py-1 rounded text-xs font-bold tracking-wide">${r.jenis_sp}</span></td>
                    <td class="p-3 text-center text-sm">${dateIndo(r.tanggal_sp)}</td>
                    <td class="p-3 text-center text-sm text-red-600 font-bold">${dateIndo(r.masa_berlaku)}</td>
                    <td class="p-3 text-sm max-w-xs truncate text-gray-600" title="${r.pelanggaran}">${r.pelanggaran || '-'}</td>
                    <td class="p-3 text-sm max-w-xs truncate text-gray-600" title="${r.sanksi}">${r.sanksi || '-'}</td>
                    <td class="p-3 text-center">${fileLink}</td>
                    
                    <?php if($canAction): ?>
                    <td class="p-3 text-center sticky right-0 bg-white shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] border-l">
                        <div class="flex justify-center gap-2">
                            <button onclick="editSP('${rowJson}')" class="w-8 h-8 flex items-center justify-center text-blue-600 hover:bg-blue-100 rounded-full transition" title="Edit"><i class="ti ti-pencil"></i></button>
                            <button onclick="deleteSP(${r.id})" class="w-8 h-8 flex items-center justify-center text-red-600 hover:bg-red-100 rounded-full transition" title="Hapus"><i class="ti ti-trash"></i></button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>`;
            }).join('');
            
            document.getElementById('info-sp').innerText = json.data.length + ' Data ditampilkan';
        }
    } catch(e) { 
        console.error(e); 
        tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-red-500 font-bold">Gagal memuat data. Periksa koneksi/server.</td></tr>';
    }
}

// Open Modal & Load Dropdown with Select2
async function openModalSP(mode) {
    document.getElementById('form-sp').reset();
    document.getElementById('action-sp').value = mode;
    document.getElementById('id-sp').value = '';
    document.getElementById('preview-file-sp').innerHTML = '';
    
    // Reset Select2
    $('#karyawan_id_sp').val(null).trigger('change'); 

    // Load Data Karyawan
    const sel = document.getElementById('karyawan_id_sp');
    const fd = new FormData(); fd.append('action', 'list_karyawan_simple');
    
    try {
        const res = await fetch(API_URL_SP, {method:'POST', body:fd});
        const json = await res.json();
        
        if(json.success) {
            let opts = '<option value="">-- Pilih Karyawan --</option>';
            // Handle ID vs SAP_ID consistency
            json.data.forEach(k => {
               const sap = k.sap_id || k.id_sap || '-';
               opts += `<option value="${k.id}">${sap} - ${k.nama_karyawan}</option>`;
            });
            sel.innerHTML = opts;
        }
    } catch(e) { console.error(e); }

    document.getElementById('modal-sp').classList.remove('hidden');
    document.getElementById('modal-sp').classList.add('flex');
}

function closeModalSP() {
    document.getElementById('modal-sp').classList.add('hidden');
    document.getElementById('modal-sp').classList.remove('flex');
}

window.editSP = (json) => {
    const r = JSON.parse(decodeURIComponent(json));
    openModalSP('update_peringatan').then(() => {
        setTimeout(() => {
            document.getElementById('id-sp').value = r.id;
            // Set Select2 Value trigger Change
            $('#karyawan_id_sp').val(r.karyawan_id).trigger('change');
            
            document.getElementById('no_surat').value = r.no_surat;
            document.getElementById('jenis_sp').value = r.jenis_sp;
            document.getElementById('tanggal_sp').value = r.tanggal_sp;
            document.getElementById('masa_berlaku').value = r.masa_berlaku;
            document.getElementById('pelanggaran').value = r.pelanggaran;
            document.getElementById('sanksi').value = r.sanksi;
            
            if(r.file_scan) {
                document.getElementById('preview-file-sp').innerHTML = `<span class="text-green-600 font-bold bg-green-50 px-2 py-1 rounded text-xs"><i class="ti ti-check"></i> File Tersedia: ${r.file_scan}</span>`;
            }
        }, 300);
    });
};

async function saveSP() {
    const btn = document.getElementById('save-sp');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Menyimpan...'; 
    btn.disabled = true;
    
    const fd = new FormData(document.getElementById('form-sp'));
    try {
        const res = await fetch(API_URL_SP, {method:'POST', body:fd});
        const json = await res.json();
        if(json.success) {
            closeModalSP();
            Swal.fire('Sukses', 'Data SP berhasil disimpan', 'success');
            loadSP();
        } else {
            Swal.fire('Gagal', json.message || 'Terjadi kesalahan', 'error');
        }
    } catch(e) { 
        console.error(e); 
        Swal.fire('Error', 'Terjadi kesalahan sistem', 'error');
    } 
    finally { 
        btn.innerHTML = originalText; 
        btn.disabled = false; 
    }
}

window.deleteSP = (id) => {
    Swal.fire({
        title: 'Hapus Surat Peringatan?', 
        text: "Data yang dihapus tidak bisa dikembalikan!",
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonText: 'Ya, Hapus', 
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(res => {
        if(res.isConfirmed) {
            const fd = new FormData(); 
            fd.append('action', 'delete_peringatan');
            fd.append('id', id);
            
            fetch(API_URL_SP, {method:'POST', body:fd}).then(r=>r.json()).then(j => {
                if(j.success) { 
                    Swal.fire('Terhapus','Data berhasil dihapus','success'); 
                    loadSP(); 
                } else {
                    Swal.fire('Gagal', j.message, 'error');
                }
            });
        }
    });
};
</script>