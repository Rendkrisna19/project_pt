<!-- TAB: DATA TANGGUNGAN -->
<div class="space-y-4">
    <div class="flex justify-between items-center">
        <div class="bg-white p-4 rounded-xl border border-purple-200 shadow-sm flex items-center gap-3">
            <div class="p-3 bg-purple-100 rounded-lg">
                <i class="ti ti-users-group text-purple-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-800">Data Tanggungan Keluarga</h3>
                <p class="text-sm text-slate-500">Kelola data anggota keluarga karyawan</p>
            </div>
        </div>
        <?php if ($canInput): ?>
        <button id="btn-add-tanggungan" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium flex items-center gap-2 shadow-sm">
            <i class="ti ti-plus"></i> Tambah Tanggungan
        </button>
        <?php endif; ?>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex justify-between items-center">
        <div class="relative w-96">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
            <input type="text" id="q-tanggungan" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Cari nama karyawan atau anggota keluarga...">
        </div>
        <div class="text-sm text-gray-500" id="info-tanggungan">Memuat data...</div>
    </div>

    <div class="sticky-container">
        <table class="table-grid">
            <thead>
                <tr>
                    <th>SAP ID Karyawan</th>
                    <th>Nama Karyawan</th>
                    <th>Nama Anggota Keluarga</th>
                    <th>Hubungan</th>
                    <th>Tempat Lahir</th>
                    <th>Tanggal Lahir</th>
                    <th>Pendidikan Terakhir</th>
                    <th>Pekerjaan</th>
                    <th>Keterangan</th>
                    <?php if ($canAction): ?>
                    <th class="text-center">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-tanggungan"></tbody>
        </table>
    </div>
</div>

<!-- MODAL TANGGUNGAN -->
<?php if ($canInput): ?>
<div id="modal-tanggungan" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl">
            <div class="px-6 py-4 border-b bg-purple-50 rounded-t-2xl flex justify-between items-center">
                <h3 id="title-tanggungan" class="text-xl font-bold text-purple-900">Form Data Tanggungan</h3>
                <button id="close-tanggungan" class="text-gray-400 hover:text-red-500"><i class="ti ti-x text-2xl"></i></button>
            </div>
            
            <form id="form-tanggungan" class="p-6 grid grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" value="store_tanggungan" id="action-tanggungan">
                <input type="hidden" name="id" id="id-tanggungan">

                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Pilih Karyawan *</label>
                    <select name="karyawan_id" id="karyawan_id" class="w-full border p-2 rounded" required>
                        <option value="">-- Pilih Karyawan --</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Nama Anggota Keluarga *</label>
                    <input type="text" name="nama_batih" id="nama_batih" class="w-full border p-2 rounded" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Hubungan *</label>
                    <select name="hubungan" id="hubungan" class="w-full border p-2 rounded" required>
                        <option value="">-- Pilih --</option>
                        <option value="Istri">Istri</option>
                        <option value="Suami">Suami</option>
                        <option value="Anak">Anak</option>
                        <option value="Orang Tua">Orang Tua</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" id="tempat_lahir_t" class="w-full border p-2 rounded">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" id="tanggal_lahir_t" class="w-full border p-2 rounded">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Pendidikan Terakhir</label>
                    <input type="text" name="pendidikan_terakhir" id="pendidikan_terakhir" class="w-full border p-2 rounded">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Pekerjaan</label>
                    <input type="text" name="pekerjaan" id="pekerjaan" class="w-full border p-2 rounded">
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Keterangan</label>
                    <textarea name="keterangan" id="keterangan" rows="3" class="w-full border p-2 rounded"></textarea>
                </div>
            </form>

            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                <button type="button" id="cancel-tanggungan" class="px-5 py-2 border rounded-lg hover:bg-gray-200">Batal</button>
                <button type="button" id="save-tanggungan" class="px-5 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">Simpan</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const CAN_INPUT = <?= $canInput ? 'true' : 'false'; ?>;
    const CAN_ACTION = <?= $canAction ? 'true' : 'false'; ?>;
    const CSRF = '<?= $CSRF ?>';
    
    const tbody = document.getElementById('tbody-tanggungan');
    const q = document.getElementById('q-tanggungan');

    async function loadTanggungan() {
        const query = q.value;
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-10">Memuat...</td></tr>';
        
        try {
            const fd = new FormData();
            fd.append('action', 'list_tanggungan');
            fd.append('q', query);
            
            const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
            const json = await res.json();

            if (json.success) {
                if (json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-10 text-gray-400">Tidak ada data tanggungan.</td></tr>';
                    document.getElementById('info-tanggungan').innerText = '0 Data';
                    return;
                }

                tbody.innerHTML = json.data.map(r => {
                    const rowJson = encodeURIComponent(JSON.stringify(r));
                    let btns = '';
                    if (CAN_ACTION) {
                        btns = `
                        <button onclick="editTanggungan('${rowJson}')" class="p-2 text-blue-600 hover:bg-blue-50 rounded"><i class="ti ti-pencil"></i></button>
                        <button onclick="deleteTanggungan(${r.id})" class="p-2 text-red-600 hover:bg-red-50 rounded"><i class="ti ti-trash"></i></button>
                        `;
                    }

                    return `
                    <tr class="hover:bg-purple-50">
                        <td class="font-mono text-xs">${r.sap_id || '-'}</td>
                        <td class="font-bold">${r.nama_karyawan || '-'}</td>
                        <td class="font-bold text-purple-700">${r.nama_batih}</td>
                        <td><span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-xs font-bold">${r.hubungan}</span></td>
                        <td class="text-sm">${r.tempat_lahir || '-'}</td>
                        <td class="text-sm">${r.tanggal_lahir || '-'}</td>
                        <td class="text-sm">${r.pendidikan_terakhir || '-'}</td>
                        <td class="text-sm">${r.pekerjaan || '-'}</td>
                        <td class="text-sm text-slate-500">${r.keterangan || '-'}</td>
                        ${CAN_ACTION ? '<td class="text-center flex justify-center gap-1">'+btns+'</td>' : ''}
                    </tr>`;
                }).join('');

                document.getElementById('info-tanggungan').innerText = json.data.length + ' Data ditemukan';
            }
        } catch (e) { console.error(e); }
    }

    q.addEventListener('input', () => { clearTimeout(window.tTang); window.tTang = setTimeout(loadTanggungan, 300); });
    loadTanggungan();

    if (CAN_INPUT) {
        const modal = document.getElementById('modal-tanggungan');
        const form = document.getElementById('form-tanggungan');
        const selectKaryawan = document.getElementById('karyawan_id');

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

        document.getElementById('btn-add-tanggungan').onclick = () => {
            form.reset();
            document.getElementById('action-tanggungan').value = 'store_tanggungan';
            document.getElementById('id-tanggungan').value = '';
            modal.classList.remove('hidden');
        };

        document.getElementById('close-tanggungan').onclick = () => modal.classList.add('hidden');
        document.getElementById('cancel-tanggungan').onclick = () => modal.classList.add('hidden');

        document.getElementById('save-tanggungan').onclick = async () => {
            const fd = new FormData(form);
            try {
                const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
                const json = await res.json();
                if (json.success) {
                    modal.classList.add('hidden');
                    Swal.fire('Berhasil', 'Data tanggungan tersimpan', 'success');
                    loadTanggungan();
                } else {
                    Swal.fire('Gagal', json.message, 'error');
                }
            } catch (e) { alert('Error: ' + e); }
        };

        window.editTanggungan = (jsonStr) => {
            const r = JSON.parse(decodeURIComponent(jsonStr));
            form.reset();
            document.getElementById('action-tanggungan').value = 'update_tanggungan';
            document.getElementById('id-tanggungan').value = r.id;
            
            ['karyawan_id','nama_batih','hubungan','pendidikan_terakhir','pekerjaan','keterangan'].forEach(id => {
                if(document.getElementById(id)) document.getElementById(id).value = r[id] || '';
            });
            document.getElementById('tempat_lahir_t').value = r.tempat_lahir || '';
            document.getElementById('tanggal_lahir_t').value = r.tanggal_lahir || '';

            modal.classList.remove('hidden');
        };

        window.deleteTanggungan = (id) => {
            Swal.fire({title:'Hapus Data Tanggungan?', icon:'warning', showCancelButton:true, confirmButtonText:'Ya, Hapus', confirmButtonColor:'#d33'})
            .then(res => {
                if (res.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'delete_tanggungan');
                    fd.append('id', id);
                    fd.append('csrf_token', CSRF);
                    fetch('data_karyawan_crud.php', {method:'POST', body:fd})
                    .then(r => r.json()).then(j => {
                        if(j.success) { Swal.fire('Terhapus','','success'); loadTanggungan(); }
                        else Swal.fire('Gagal', j.message, 'error');
                    });
                }
            });
        };
    }
});
</script>