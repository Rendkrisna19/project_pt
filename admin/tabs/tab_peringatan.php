<style>
    /* Container dengan Scroll */
    .table-container-sp {
        max-height: 70vh; 
        overflow: auto; 
        position: relative;
        border: 1px solid #fca5a5; /* Red-300 */
        border-radius: 0.75rem; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }

    /* Tabel Grid */
    table.table-grid-sp {
        width: 100%; 
        border-collapse: separate; 
        border-spacing: 0; 
        min-width: 1800px; /* Lebar total agar scroll muncul */
    }

    table.table-grid-sp th, 
    table.table-grid-sp td {
        padding: 0.75rem; 
        font-size: 0.85rem; 
        border-bottom: 1px solid #fee2e2; /* Red-100 */
        border-right: 1px solid #fee2e2; 
        vertical-align: middle; 
        background-color: #fff;
    }

    /* HEADER STICKY (MERAH) */
    table.table-grid-sp thead th {
        position: sticky; 
        top: 0; 
        background: #dc2626; /* Red-600 */
        color: #fff; 
        z-index: 40; 
        font-weight: 700; 
        text-transform: uppercase; 
        height: 50px;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.2);
    }

    /* KOLOM STICKY KIRI */
    th.sticky-col-sp, td.sticky-col-sp {
        position: sticky; 
        left: 0; 
        z-index: 20; 
        border-right: 2px solid #fecaca; /* Red-200 */
    }

    /* Header Pojok Kiri Atas (Intersection) */
    thead th.sticky-col-sp { 
        z-index: 50; 
        background: #dc2626; 
    }
    
    /* Pengaturan Posisi Kolom Freeze */
    .col-sap-sp  { left: 0px; width: 100px; }
    .col-nama-sp { left: 100px; width: 250px; box-shadow: 4px 0 6px -2px rgba(0,0,0,0.1); }

    /* Hover Effect Merah Muda */
    tr:hover td { background-color: #fef2f2 !important; } /* Red-50 */
</style>

<div class="space-y-4">
    <div class="bg-white p-4 rounded-xl border border-red-200 shadow-sm flex items-center gap-3">
        <div class="p-3 bg-red-100 rounded-lg text-red-600">
            <i class="ti ti-alert-triangle text-2xl"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-slate-800">Data Surat Peringatan</h3>
            <p class="text-sm text-slate-500">Monitoring pelanggaran dan sanksi karyawan</p>
        </div>
        
        <div class="ml-auto">
            <?php if ($canInput): ?>
            <button id="btn-add-sp" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-bold flex items-center gap-2 shadow-sm transition transform hover:-translate-y-0.5">
                <i class="ti ti-plus"></i> Tambah SP
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
        <div class="flex gap-3 items-center w-full md:w-auto text-sm">
            <span class="text-gray-600 font-semibold">Filter:</span>
            <select id="f_afdeling_sp" class="bg-gray-50 border border-gray-300 rounded-lg p-2 w-48 cursor-pointer outline-none focus:ring-2 focus:ring-red-500">
                <option value="">Semua Afdeling</option>
            </select>
            <select id="f_jenis_sp" class="bg-gray-50 border border-gray-300 rounded-lg p-2 w-32 cursor-pointer outline-none focus:ring-2 focus:ring-red-500">
                <option value="">Semua Jenis</option>
                <option value="Teguran">Teguran</option>
                <option value="SP1">SP 1</option>
                <option value="SP2">SP 2</option>
                <option value="SP3">SP 3</option>
            </select>
        </div>
        <div class="relative w-full md:w-80">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
            <input type="text" id="q-sp" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-red-500" placeholder="Cari Nama / No Surat...">
        </div>
    </div>

    <div class="table-container-sp bg-white">
        <table class="table-grid-sp" id="table-sp">
            <thead>
                <tr>
                    <th class="sticky-col-sp col-sap-sp">SAP ID</th>
                    <th class="sticky-col-sp col-nama-sp">Nama Karyawan</th>
                    
                    <th>Kebun</th>
                    <th>Afdeling</th>
                    <th>Status Emp</th> <th>No Surat</th>
                    <th>Jenis SP</th>
                    <th>Tanggal SP</th>
                    <th>Masa Berlaku</th>
                    <th style="min-width: 200px;">Pelanggaran</th>
                    <th style="min-width: 150px;">Sanksi</th>
                    <th class="text-center">File</th>
                    
                    <?php if ($canAction): ?>
                    <th class="text-center sticky right-0 z-50 bg-red-700 text-white" style="border-left:2px solid #fecaca; width: 100px;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-sp" class="text-gray-700"></tbody>
        </table>
    </div>
    
    <div class="text-xs text-gray-500 mt-2 text-right" id="info-sp">Memuat data...</div>
</div>

<?php if ($canInput): ?>
<div id="modal-sp" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 border-b bg-red-50 flex justify-between items-center">
                <h3 class="text-xl font-bold text-red-900 flex items-center gap-2"><i class="ti ti-file-alert"></i> Form Surat Peringatan</h3>
                <button id="close-sp" class="text-gray-400 hover:text-red-500 transition"><i class="ti ti-x text-2xl"></i></button>
            </div>
            
            <form id="form-sp" class="p-6 grid grid-cols-2 gap-5">
                <input type="hidden" name="action" id="action-sp">
                <input type="hidden" name="id" id="id-sp">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="col-span-2 bg-white border border-red-100 p-3 rounded-lg flex flex-col md:flex-row justify-between items-center gap-2 text-xs text-gray-600 mb-2">
                    <span class="font-bold text-red-700 uppercase">Alur Sanksi:</span>
                    <div class="flex items-center gap-2">
                        <span class="bg-yellow-50 text-yellow-700 px-2 py-1 rounded border border-yellow-200">Teguran</span>
                        <i class="ti ti-arrow-right text-gray-400"></i>
                        <span class="bg-orange-50 text-orange-700 px-2 py-1 rounded border border-orange-200">SP 1</span>
                        <i class="ti ti-arrow-right text-gray-400"></i>
                        <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded border border-orange-300">SP 2</span>
                        <i class="ti ti-arrow-right text-gray-400"></i>
                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded border border-red-300 font-bold">SP 3 (PHK)</span>
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="lbl">Pilih Karyawan <span class="text-red-500">*</span></label>
                    <select name="karyawan_id" id="karyawan_id_sp" class="inp cursor-pointer" required>
                        <option value="">-- Memuat Data --</option>
                    </select>
                </div>

                <div>
                    <label class="lbl">No. Surat <span class="text-red-500">*</span></label>
                    <input type="text" name="no_surat" id="no_surat" class="inp" placeholder="Nomor Surat..." required>
                </div>

                <div>
                    <label class="lbl">Jenis SP <span class="text-red-500">*</span></label>
                    <select name="jenis_sp" id="jenis_sp" class="inp cursor-pointer" required>
                        <option value="Teguran">Teguran</option>
                        <option value="SP1">SP 1</option>
                        <option value="SP2">SP 2</option>
                        <option value="SP3">SP 3</option>
                    </select>
                </div>

                <div>
                    <label class="lbl">Tanggal SP <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_sp" id="tanggal_sp" class="inp" required>
                </div>

                <div>
                    <label class="lbl">Masa Berlaku (Opsional)</label>
                    <input type="date" name="masa_berlaku" id="masa_berlaku" class="inp">
                    <small class="text-[10px] text-gray-400">Biarkan kosong jika tidak ada expired</small>
                </div>

                <div class="col-span-2">
                    <label class="lbl">Detail Pelanggaran</label>
                    <textarea name="pelanggaran" id="pelanggaran" rows="3" class="inp" placeholder="Jelaskan detail pelanggaran..."></textarea>
                </div>

                <div class="col-span-2">
                    <label class="lbl">Sanksi Diberikan</label>
                    <textarea name="sanksi" id="sanksi" rows="2" class="inp" placeholder="Contoh: Potong Tunjangan / Skorsing..."></textarea>
                </div>

                <div class="col-span-2 bg-red-50 p-3 rounded border border-dashed border-red-300">
                    <label class="lbl text-red-700">Upload Scan Surat (PDF/IMG)</label>
                    <input type="file" name="file_scan" id="file_scan" class="text-sm w-full text-slate-500" accept=".pdf,image/*">
                    <div id="preview-file-sp" class="mt-2 text-xs text-blue-600"></div>
                </div>
            </form>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3">
                <button type="button" id="cancel-sp" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-100 font-medium transition">Batal</button>
                <button type="button" id="save-sp" class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-md font-bold transition">Simpan SP</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>.lbl{display:block;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:4px;}.inp{width:100%;border:1px solid #d1d5db;padding:6px;border-radius:6px;font-size:13px;} .inp:focus { outline:none; border-color:#dc2626; ring:2px solid #fecaca; }</style>

<script>
// Backend URL (Pastikan path ini benar relatif terhadap file yang memanggilnya)
// Jika tab ini di-include dari index, path biasanya 'pages/data_karyawan_crud.php' atau 'data_karyawan_crud.php'
const API_URL_SP = 'data_karyawan_crud.php'; 

document.addEventListener('DOMContentLoaded', () => {
    loadAfdelingSP();
    loadSP();

    // Event Listeners Filter
    const fAfd = document.getElementById('f_afdeling_sp');
    if(fAfd) fAfd.addEventListener('change', loadSP);

    const fJenis = document.getElementById('f_jenis_sp');
    if(fJenis) fJenis.addEventListener('change', loadSP);
    
    // Search Debounce
    let timerSP;
    const qSP = document.getElementById('q-sp');
    if(qSP) {
        qSP.addEventListener('input', () => {
            clearTimeout(timerSP);
            timerSP = setTimeout(loadSP, 500);
        });
    }

    // Modal Handlers
    if(document.getElementById('btn-add-sp')) {
        document.getElementById('btn-add-sp').onclick = () => openModalSP('store_peringatan');
        document.getElementById('close-sp').onclick = () => document.getElementById('modal-sp').classList.add('hidden');
        document.getElementById('cancel-sp').onclick = () => document.getElementById('modal-sp').classList.add('hidden');
        document.getElementById('save-sp').onclick = saveSP;
    }
});

// Load Filter Afdeling
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

// Load Table Data
async function loadSP() {
    const tbody = document.getElementById('tbody-sp');
    tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-2xl"></i><br>Memuat data...</td></tr>';
    
    const fd = new FormData();
    fd.append('action', 'list_peringatan');
    fd.append('q', document.getElementById('q-sp').value);
    fd.append('f_afdeling', document.getElementById('f_afdeling_sp').value);
    
    try {
        const res = await fetch(API_URL_SP, {method:'POST', body:fd});
        if(!res.ok) throw new Error("Server Error");
        
        // Cek response text dulu jika perlu debugging
        // const text = await res.text(); console.log(text); const json = JSON.parse(text);
        
        const json = await res.json();

        if(json.success) {
            let data = json.data;
            const typeFilter = document.getElementById('f_jenis_sp').value;
            if(typeFilter) {
                data = data.filter(r => r.jenis_sp === typeFilter);
            }

            if(data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-gray-400 italic">Tidak ada data ditemukan.</td></tr>';
                document.getElementById('info-sp').innerText = '0 Data';
                return;
            }

            tbody.innerHTML = data.map(r => {
                const rowJson = encodeURIComponent(JSON.stringify(r));
                
                let badgeClass = 'bg-gray-100 text-gray-700';
                if(r.jenis_sp === 'Teguran') badgeClass = 'bg-yellow-100 text-yellow-700 border border-yellow-200';
                if(r.jenis_sp === 'SP1') badgeClass = 'bg-orange-100 text-orange-700 border border-orange-200';
                if(r.jenis_sp === 'SP2') badgeClass = 'bg-orange-600 text-white border border-orange-700';
                if(r.jenis_sp === 'SP3') badgeClass = 'bg-red-600 text-white border border-red-700 animate-pulse';

                const dateIndo = (d) => d ? new Date(d).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-';

                const fileLink = r.file_scan ? 
                    `<a href="../uploads/sp/${r.file_scan}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs flex items-center justify-center gap-1 bg-blue-50 px-2 py-1 rounded border border-blue-200"><i class="ti ti-file"></i> View</a>` 
                    : '<span class="text-gray-300">-</span>';

                return `
                <tr class="hover:bg-red-50 border-b transition">
                    <td class="p-3 sticky-col-sp col-sap-sp bg-white font-mono text-xs font-bold text-red-700">${r.sap_id || '-'}</td>
                    <td class="p-3 sticky-col-sp col-nama-sp bg-white font-bold text-gray-800 truncate">${r.nama_karyawan || '-'}</td>
                    
                    <td class="p-3 text-sm">${r.nama_kebun || '-'}</td>
                    <td class="p-3 text-sm">${r.afdeling || '-'}</td>
                    <td class="p-3 text-center"><span class="bg-gray-100 text-xs px-2 py-1 rounded font-mono">${r.status_karyawan || '-'}</span></td>
                    
                    <td class="p-3 text-sm font-mono">${r.no_surat}</td>
                    <td class="p-3 text-center"><span class="${badgeClass} px-2 py-1 rounded text-xs font-bold shadow-sm">${r.jenis_sp}</span></td>
                    <td class="p-3 text-center text-sm">${dateIndo(r.tanggal_sp)}</td>
                    <td class="p-3 text-center text-sm text-red-600 font-bold">${dateIndo(r.masa_berlaku)}</td>
                    <td class="p-3 text-sm max-w-xs truncate" title="${r.pelanggaran}">${r.pelanggaran || '-'}</td>
                    <td class="p-3 text-sm max-w-xs truncate" title="${r.sanksi}">${r.sanksi || '-'}</td>
                    <td class="p-3 text-center">${fileLink}</td>
                    
                    <?php if($canAction): ?>
                    <td class="p-3 text-center sticky right-0 bg-white" style="border-left:2px solid #fee2e2">
                        <div class="flex justify-center gap-1">
                            <button onclick="editSP('${rowJson}')" class="w-7 h-7 flex items-center justify-center text-blue-600 hover:bg-blue-100 rounded transition"><i class="ti ti-pencil"></i></button>
                            <button onclick="deleteSP(${r.id})" class="w-7 h-7 flex items-center justify-center text-red-600 hover:bg-red-100 rounded transition"><i class="ti ti-trash"></i></button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>`;
            }).join('');
            
            document.getElementById('info-sp').innerText = json.data.length + ' Data';
        }
    } catch(e) { 
        tbody.innerHTML = '<tr><td colspan="13" class="text-center py-10 text-red-500">Gagal memuat data. Server Error.</td></tr>';
        console.error(e); 
    }
}

// Modal Logic
async function openModalSP(mode) {
    document.getElementById('form-sp').reset();
    document.getElementById('action-sp').value = mode;
    document.getElementById('id-sp').value = '';
    document.getElementById('preview-file-sp').innerHTML = '';

    // Load Dropdown Karyawan
    const sel = document.getElementById('karyawan_id_sp');
    sel.innerHTML = '<option value="">Memuat Data...</option>';
    
    // FETCH YANG MENYEBABKAN ERROR SEBELUMNYA
    const fd = new FormData(); fd.append('action', 'list_karyawan_simple');
    
    try {
        const res = await fetch(API_URL_SP, {method:'POST', body:fd});
        // Cek jika response kosong
        if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);
        
        const json = await res.json(); // Error "Unexpected end of JSON" terjadi di sini jika PHP tidak me-return apa2
        
        if(json.success) {
            sel.innerHTML = '<option value="">-- Pilih Karyawan --</option>' + 
                json.data.map(k => `<option value="${k.id}">${k.id_sap} - ${k.nama_karyawan}</option>`).join('');
        } else {
            sel.innerHTML = '<option value="">Gagal memuat data</option>';
        }
    } catch(e) {
        console.error("Error Open Modal SP:", e);
        sel.innerHTML = '<option value="">Error Server</option>';
        Swal.fire('Error', 'Gagal memuat data karyawan. Pastikan backend sudah diperbarui.', 'error');
    }

    document.getElementById('modal-sp').classList.remove('hidden');
}

window.editSP = (json) => {
    const r = JSON.parse(decodeURIComponent(json));
    openModalSP('update_peringatan').then(() => {
        // Delay sedikit agar dropdown terisi dulu
        setTimeout(() => {
            document.getElementById('id-sp').value = r.id;
            document.getElementById('karyawan_id_sp').value = r.karyawan_id;
            document.getElementById('no_surat').value = r.no_surat;
            document.getElementById('jenis_sp').value = r.jenis_sp;
            document.getElementById('tanggal_sp').value = r.tanggal_sp;
            document.getElementById('masa_berlaku').value = r.masa_berlaku;
            document.getElementById('pelanggaran').value = r.pelanggaran;
            document.getElementById('sanksi').value = r.sanksi;
            
            if(r.file_scan) {
                document.getElementById('preview-file-sp').innerHTML = `<span class="text-green-600 font-bold"><i class="ti ti-check"></i> File ada: ${r.file_scan}</span>`;
            }
        }, 500);
    });
};

async function saveSP() {
    const btn = document.getElementById('save-sp');
    btn.innerText = 'Menyimpan...'; btn.disabled = true;
    
    const fd = new FormData(document.getElementById('form-sp'));
    try {
        const res = await fetch(API_URL_SP, {method:'POST', body:fd});
        const json = await res.json();
        if(json.success) {
            document.getElementById('modal-sp').classList.add('hidden');
            Swal.fire('Sukses', 'Data SP berhasil disimpan', 'success');
            loadSP();
        } else {
            Swal.fire('Gagal', json.message || 'Terjadi kesalahan', 'error');
        }
    } catch(e) { 
        console.error(e); 
        Swal.fire('Error', 'Terjadi kesalahan sistem', 'error');
    } 
    finally { btn.innerText = 'Simpan SP'; btn.disabled = false; }
}

window.deleteSP = (id) => {
    Swal.fire({title:'Hapus SP?', icon:'warning', showCancelButton:true, confirmButtonText:'Hapus', confirmButtonColor:'#d33'})
    .then(res => {
        if(res.isConfirmed) {
            const fd = new FormData(); 
            fd.append('action', 'delete_peringatan');
            fd.append('id', id);
            const csrf = document.querySelector('input[name="csrf_token"]').value;
            fd.append('csrf_token', csrf);
            
            fetch(API_URL_SP, {method:'POST', body:fd}).then(r=>r.json()).then(j => {
                if(j.success) { Swal.fire('Terhapus','','success'); loadSP(); }
            });
        }
    });
};
</script>