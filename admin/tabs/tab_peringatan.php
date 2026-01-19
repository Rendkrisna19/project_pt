<!-- TAB: SURAT PERINGATAN -->
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <div class="bg-white p-4 rounded-xl border border-red-200 shadow-sm flex items-center gap-3">
            <div class="p-3 bg-red-100 rounded-lg">
                <i class="ti ti-alert-triangle text-red-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-800">Data Surat Peringatan</h3>
                <p class="text-sm text-slate-500">Kelola surat peringatan karyawan (Teguran, SP1, SP2, SP3)</p>
            </div>
        </div>
        <?php if ($canInput): ?>
        <button id="btn-add-sp" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium flex items-center gap-2 shadow-sm">
            <i class="ti ti-plus"></i> Tambah Surat Peringatan
        </button>
        <?php endif; ?>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex justify-between items-center">
        <div class="relative w-96">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
            <input type="text" id="q-sp" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 outline-none" placeholder="Cari nama, no surat, atau jenis SP...">
        </div>
        <div class="text-sm text-gray-500" id="info-sp">Memuat data...</div>
    </div>

    <div class="sticky-container">
        <table class="table-grid">
            <thead>
                <tr>
                    <th>SAP ID</th>
                    <th>Nama Karyawan</th>
                    <th>No Surat</th>
                    <th>Jenis SP</th>
                    <th>Tanggal SP</th>
                    <th>Masa Berlaku</th>
                    <th>Pelanggaran</th>
                    <th>Sanksi</th>
                    <th>File Scan</th>
                    <?php if ($canAction): ?>
                    <th class="text-center">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-sp"></tbody>
        </table>
    </div>
</div>

<!-- MODAL SURAT PERINGATAN -->
<?php if ($canInput): ?>
<div id="modal-sp" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl">
            <div class="px-6 py-4 border-b bg-red-50 rounded-t-2xl flex justify-between items-center">
                <h3 id="title-sp" class="text-xl font-bold text-red-900">Form Surat Peringatan</h3>
                <button id="close-sp" class="text-gray-400 hover:text-red-500"><i class="ti ti-x text-2xl"></i></button>
            </div>
            
            <form id="form-sp" class="p-6 grid grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" value="store_peringatan" id="action-sp">
                <input type="hidden" name="id" id="id-sp">

                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Pilih Karyawan *</label>
                    <select name="karyawan_id" id="karyawan_id_sp" class="w-full border p-2 rounded" required>
                        <option value="">-- Pilih Karyawan --</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">No Surat *</label>
                    <input type="text" name="no_surat" id="no_surat" class="w-full border p-2 rounded" required placeholder="Contoh: SP/001/HRD/2025">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Jenis SP *</label>
                    <select name="jenis_sp" id="jenis_sp" class="w-full border p-2 rounded" required>
                        <option value="">-- Pilih --</option>
                        <option value="Teguran">Teguran Lisan</option>
                        <option value="SP1">SP 1 (Peringatan Pertama)</option>
                        <option value="SP2">SP 2 (Peringatan Kedua)</option>
                        <option value="SP3">SP 3 (Peringatan Terakhir)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Tanggal SP *</label>
                    <input type="date" name="tanggal_sp" id="tanggal_sp" class="w-full border p-2 rounded" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Masa Berlaku</label>
                    <input type="date" name="masa_berlaku" id="masa_berlaku" class="w-full border p-2 rounded">
                    <p class="text-xs text-gray-500 mt-1">Opsional, kosongkan jika permanen</p>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Pelanggaran</label>
                    <textarea name="pelanggaran" id="pelanggaran" rows="3" class="w-full border p-2 rounded" placeholder="Uraikan pelanggaran yang dilakukan..."></textarea>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Sanksi</label>
                    <textarea name="sanksi" id="sanksi" rows="3" class="w-full border p-2 rounded" placeholder="Uraikan sanksi yang diberikan..."></textarea>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Upload File Scan Surat (Opsional)</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-red-500 transition">
                        <input type="file" name="file_scan" id="file_scan" accept="image/*,application/pdf" class="hidden" onchange="previewFileSP(this)">
                        <button type="button" onclick="document.getElementById('file_scan').click()" class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 font-medium">
                            <i class="ti ti-upload mr-2"></i>Pilih File (JPG, PNG, PDF)
                        </button>
                        <p class="text-xs text-gray-500 mt-2">Maks. 5MB</p>
                        <div id="preview-file-sp" class="mt-3 text-sm text-gray-600"></div>
                    </div>
                </div>
            </form>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                <button type="button" id="cancel-sp" class="px-5 py-2 border rounded-lg hover:bg-gray-200">Batal</button>
                <button type="button" id="save-sp" class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Simpan</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function previewFileSP(input) {
    const preview = document.getElementById('preview-file-sp');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        preview.innerHTML = `<div class="flex items-center justify-center gap-2 text-green-600">
            <i class="ti ti-check-circle"></i>
            <span><strong>${fileName}</strong> (${fileSize} MB)</span>
        </div>`;
    } else {
        preview.innerHTML = '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const CAN_INPUT = <?= $canInput ? 'true' : 'false'; ?>;
    const CAN_ACTION = <?= $canAction ? 'true' : 'false'; ?>;
    const CSRF = '<?= $CSRF ?>';
    
    const tbody = document.getElementById('tbody-sp');
    const q = document.getElementById('q-sp');

    async function loadSP() {
        const query = q.value;
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-10">Memuat...</td></tr>';
        
        try {
            const fd = new FormData();
            fd.append('action', 'list_peringatan');
            fd.append('q', query);
            
            const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
            const json = await res.json();

            if (json.success) {
                if (json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-10 text-gray-400">Tidak ada data surat peringatan.</td></tr>';
                    document.getElementById('info-sp').innerText = '0 Data';
                    return;
                }

                tbody.innerHTML = json.data.map(r => {
                    const rowJson = encodeURIComponent(JSON.stringify(r));
                    
                    let badgeColor = 'bg-yellow-100 text-yellow-700';
                    if (r.jenis_sp === 'SP3') badgeColor = 'bg-red-100 text-red-700';
                    else if (r.jenis_sp === 'SP2') badgeColor = 'bg-orange-100 text-orange-700';
                    else if (r.jenis_sp === 'SP1') badgeColor = 'bg-blue-100 text-blue-700';

                    const fileLink = r.file_scan ? 
                        `<a href="../uploads/sp/${r.file_scan}" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1">
                            <i class="ti ti-file-text"></i> Lihat File
                        </a>` : '<span class="text-gray-400">-</span>';

                    let btns = '';
                    if (CAN_ACTION) {
                        btns = `
                        <button onclick="editSP('${rowJson}')" class="p-2 text-blue-600 hover:bg-blue-50 rounded"><i class="ti ti-pencil"></i></button>
                        <button onclick="deleteSP(${r.id})" class="p-2 text-red-600 hover:bg-red-50 rounded"><i class="ti ti-trash"></i></button>
                        `;
                    }

                    return `
                    <tr class="hover:bg-red-50">
                        <td class="font-mono text-xs">${r.sap_id || '-'}</td>
                        <td class="font-bold">${r.nama_karyawan || '-'}</td>
                        <td class="font-mono text-xs">${r.no_surat}</td>
                        <td><span class="${badgeColor} px-3 py-1 rounded-full text-xs font-bold">${r.jenis_sp}</span></td>
                        <td class="text-sm">${r.tanggal_sp}</td>
                        <td class="text-sm ${r.masa_berlaku ? 'text-orange-600 font-bold' : 'text-gray-400'}">${r.masa_berlaku || 'Permanen'}</td>
                        <td class="text-sm max-w-xs truncate">${r.pelanggaran || '-'}</td>
                        <td class="text-sm max-w-xs truncate">${r.sanksi || '-'}</td>
                        <td class="text-sm">${fileLink}</td>
                        ${CAN_ACTION ? '<td class="text-center flex justify-center gap-1">'+btns+'</td>' : ''}
                    </tr>`;
                }).join('');

                document.getElementById('info-sp').innerText = json.data.length + ' Data ditemukan';
            }
        } catch (e) { console.error(e); }
    }

    q.addEventListener('input', () => { clearTimeout(window.tSP); window.tSP = setTimeout(loadSP, 300); });
    loadSP();

    if (CAN_INPUT) {
        const modal = document.getElementById('modal-sp');
        const form = document.getElementById('form-sp');
        const selectKaryawan = document.getElementById('karyawan_id_sp');

        // Load Karyawan untuk dropdown
        async function loadKaryawanList() {
            const fd = new FormData();
            fd.append('action', 'list_karyawan_simple');
            const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
            const json = await res.json();
            if (json.success) {
                selectKaryawan.innerHTML = '<option value="">-- Pilih Karyawan --</option>' +
                    json.data.map(k => `<option value="${k.id}">${k.sap_id} - ${k.nama_karyawan}</option>`).join('');
            }
        }
        loadKaryawanList();

        document.getElementById('btn-add-sp').onclick = () => {
            form.reset();
            document.getElementById('preview-file-sp').innerHTML = '';
            document.getElementById('action-sp').value = 'store_peringatan';
            document.getElementById('id-sp').value = '';
            modal.classList.remove('hidden');
        };

        document.getElementById('close-sp').onclick = () => modal.classList.add('hidden');
        document.getElementById('cancel-sp').onclick = () => modal.classList.add('hidden');

        document.getElementById('save-sp').onclick = async () => {
            const fd = new FormData(form);
            try {
                const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
                const json = await res.json();
                if (json.success) {
                    modal.classList.add('hidden');
                    Swal.fire('Berhasil', 'Data surat peringatan tersimpan', 'success');
                    loadSP();
                } else {
                    Swal.fire('Gagal', json.message, 'error');
                }
            } catch (e) { alert('Error: ' + e); }
        };

        window.editSP = (jsonStr) => {
            const r = JSON.parse(decodeURIComponent(jsonStr));
            form.reset();
            document.getElementById('action-sp').value = 'update_peringatan';
            document.getElementById('id-sp').value = r.id;
            
            ['karyawan_id_sp','no_surat','jenis_sp','tanggal_sp','masa_berlaku','pelanggaran','sanksi'].forEach(id => {
                const field = id === 'karyawan_id_sp' ? 'karyawan_id' : id;
                if(document.getElementById(id)) document.getElementById(id).value = r[field] || '';
            });

            if (r.file_scan) {
                document.getElementById('preview-file-sp').innerHTML = `
                <div class="text-blue-600 flex items-center justify-center gap-2">
                    <i class="ti ti-file-text"></i>
                    <a href="../uploads/sp/${r.file_scan}" target="_blank" class="hover:underline">File Terlampir: ${r.file_scan}</a>
                </div>`;
            }

            modal.classList.remove('hidden');
        };

        window.deleteSP = (id) => {
            Swal.fire({title:'Hapus Surat Peringatan?', icon:'warning', showCancelButton:true, confirmButtonText:'Ya, Hapus', confirmButtonColor:'#d33'})
            .then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'delete_peringatan');
                    fd.append('id', id);
                    fd.append('csrf_token', CSRF);
                    fetch('data_karyawan_crud.php', {method:'POST', body:fd})
                    .then(r => r.json()).then(j => {
                        if(j.success) { Swal.fire('Terhapus','','success'); loadSP(); }
                        else Swal.fire('Gagal', j.message, 'error');
                    });
                }
            });
        };
    }
});
</script>