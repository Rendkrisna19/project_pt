<?php include_once '../layouts/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .karyawan-card { transition: transform 0.2s; border: 1px solid rgba(0,0,0,0.05); }
    .karyawan-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    .bg-gradient-cyan { background: linear-gradient(135deg, #0e7490, #06b6d4); color: white; }
    .bg-gradient-slate { background: linear-gradient(135deg, #334155, #475569); color: white; }
    .bg-gradient-orange { background: linear-gradient(135deg, #ea580c, #f97316); color: white; }
    .bg-gradient-red { background: linear-gradient(135deg, #be123c, #e11d48); color: white; }
    
    .nav-link.active { color: #0891b2; border-bottom: 2px solid #0891b2; }
    .nav-link { color: #64748b; font-weight: 600; border-bottom: 2px solid transparent; }
    
    .action-btn { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s; }
    .btn-edit-row { background: #e0f2fe; color: #0284c7; }
    .btn-edit-row:hover { background: #0284c7; color: white; }
    .btn-del-row { background: #fee2e2; color: #dc2626; }
    .btn-del-row:hover { background: #dc2626; color: white; }
</style>

<div class="space-y-6">

    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><i class="ti ti-users text-cyan-700"></i> Data Karyawan</h1>
            <p class="text-gray-500 text-sm">Manajemen SDM Full CRUD System.</p>
        </div>
        <div class="flex gap-2 flex-wrap justify-end">
            <button onclick="openModal('add')" class="bg-cyan-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-cyan-700 shadow-sm flex items-center gap-2">
                <i class="ti ti-plus"></i> Tambah Manual
            </button>
            
            <button onclick="downloadTemplate()" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-200 border border-slate-300 flex items-center gap-2">
                <i class="ti ti-download"></i> Template
            </button>
            <button onclick="openImportModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 shadow-sm flex items-center gap-2">
                <i class="ti ti-file-import"></i> Import
            </button>
            <button onclick="window.print()" class="bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 shadow-sm flex items-center gap-2">
                <i class="ti ti-printer"></i> PDF
            </button>
        </div>
    </div>

    <div id="notif-area" class="hidden bg-cyan-50 border border-cyan-200 text-cyan-800 p-3 rounded-lg text-sm items-center gap-2">
        <i class="ti ti-bell-ringing animate-pulse"></i> <span id="notif-text"></span>
    </div>

    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6" aria-label="Tabs">
            <button onclick="switchTab('dashboard')" id="tab-dashboard" class="nav-link active py-3 px-1">Dashboard</button>
            <button onclick="switchTab('aktif')" id="tab-aktif" class="nav-link py-3 px-1">Aktif</button>
            <button onclick="switchTab('mbt')" id="tab-mbt" class="nav-link py-3 px-1">MBT</button>
            <button onclick="switchTab('pensiun')" id="tab-pensiun" class="nav-link py-3 px-1">Pensiun</button>
            <button onclick="switchTab('phk')" id="tab-phk" class="nav-link py-3 px-1">PHK</button>
        </nav>
    </div>

    <div id="view-dashboard" class="tab-content block">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="karyawan-card bg-gradient-cyan p-4 rounded-xl">
                <div class="text-xs uppercase font-bold opacity-80">Total</div>
                <div class="text-3xl font-bold mt-1" id="stat-total">0</div>
            </div>
            <div class="karyawan-card bg-gradient-slate p-4 rounded-xl">
                <div class="text-xs uppercase font-bold opacity-80">Aktif</div>
                <div class="text-3xl font-bold mt-1" id="stat-aktif">0</div>
            </div>
            <div class="karyawan-card bg-gradient-orange p-4 rounded-xl">
                <div class="text-xs uppercase font-bold opacity-80">MBT (55 Th)</div>
                <div class="text-3xl font-bold mt-1" id="stat-mbt">0</div>
            </div>
            <div class="karyawan-card bg-gradient-red p-4 rounded-xl">
                <div class="text-xs uppercase font-bold opacity-80">Pensiun (56+)</div>
                <div class="text-3xl font-bold mt-1" id="stat-pensiun">0</div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2 bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="font-bold text-gray-700 mb-4">Grafik Bagian</h3>
                <div class="h-64"><canvas id="chartPersonil"></canvas></div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="font-bold text-gray-700 mb-4">Persentase Status</h3>
                <canvas id="chartStatus"></canvas>
            </div>
        </div>
    </div>

    <div id="view-table" class="tab-content hidden">
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <div class="flex justify-between mb-4">
                <h3 class="font-bold text-lg text-gray-800" id="table-title">Data Karyawan</h3>
                <input type="text" id="search-table" placeholder="Cari..." class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3">Nama / NIK</th>
                            <th class="px-4 py-3">Bagian</th>
                            <th class="px-4 py-3">Usia</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center" width="100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-karyawan">
                        <tr><td colspan="5" class="text-center py-4">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div id="modal-crud" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white p-6 rounded-xl w-full max-w-lg shadow-2xl transform scale-100 transition-transform">
        <div class="flex justify-between items-center mb-4 border-b pb-3">
            <h3 class="font-bold text-xl text-gray-800" id="modal-crud-title">Tambah Karyawan</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 text-2xl">&times;</button>
        </div>
        <form id="form-crud">
            <input type="hidden" name="action" id="crud-action">
            <input type="hidden" name="id" id="crud-id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Lengkap</label>
                    <input type="text" name="nama" id="inp-nama" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-cyan-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">SAP ID</label>
                    <input type="text" name="sap" id="inp-sap" class="w-full border border-gray-300 rounded px-3 py-2 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">NIK (KTP)</label>
                    <input type="text" name="nik" id="inp-nik" class="w-full border border-gray-300 rounded px-3 py-2 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tanggal Lahir</label>
                    <input type="date" name="tgl_lahir" id="inp-tgl" class="w-full border border-gray-300 rounded px-3 py-2 outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">TMT Kerja</label>
                    <input type="date" name="tmt_kerja" id="inp-tmt" class="w-full border border-gray-300 rounded px-3 py-2 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bagian</label>
                    <select name="bagian" id="inp-bagian" class="w-full border border-gray-300 rounded px-3 py-2 outline-none">
                        <option value="Panen">Panen</option>
                        <option value="Pemeliharaan">Pemeliharaan</option>
                        <option value="Teknik">Teknik</option>
                        <option value="Kantor">Kantor</option>
                        <option value="Gudang">Gudang</option>
                        <option value="Umum">Umum</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
                    <select name="status" id="inp-status" class="w-full border border-gray-300 rounded px-3 py-2 outline-none">
                        <option value="Aktif">Aktif</option>
                        <option value="MBT">MBT</option>
                        <option value="Pensiun">Pensiun</option>
                        <option value="PHK">PHK</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Batal</button>
                <button type="submit" class="bg-cyan-600 text-white px-6 py-2 rounded hover:bg-cyan-700 font-medium shadow">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-import" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl w-full max-w-md shadow-2xl">
        <h3 class="font-bold text-lg mb-4">Import Data CSV</h3>
        <form id="form-import">
            <input type="hidden" name="action" value="import">
            <div class="mb-4">
                <input type="file" name="file_excel" accept=".csv" class="w-full text-sm border border-gray-300 rounded-lg p-2 bg-gray-50">
                <p class="text-xs text-red-500 mt-1">*Upload file .csv (Save As CSV di Excel jika dari template)</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-import').classList.add('hidden')" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Batal</button>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Upload</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const API_URL = 'data_karyawan_menu_crud.php';
    let currentStatusFilter = 'all';

    document.addEventListener('DOMContentLoaded', () => loadStats());

    // --- TABS LOGIC ---
    function switchTab(tab) {
        document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));

        if (tab === 'dashboard') {
            document.getElementById('view-dashboard').classList.remove('hidden');
            loadStats();
        } else {
            document.getElementById('view-table').classList.remove('hidden');
            let status = 'all';
            if(tab !== 'dashboard') status = document.getElementById('tab-'+tab).innerText; 
            if(tab === 'mbt') status = 'MBT'; // Fix naming
            
            document.getElementById('table-title').innerText = 'Data ' + status;
            currentStatusFilter = status === 'Dashboard' ? 'all' : status;
            loadTable(currentStatusFilter);
        }
    }

    // --- LOAD STATS ---
    async function loadStats() {
        const fd = new FormData(); fd.append('action', 'stats');
        const res = await fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json());
        if(res.success) {
            const d = res.data;
            document.getElementById('stat-total').innerText = d.total;
            document.getElementById('stat-aktif').innerText = d.aktif;
            document.getElementById('stat-mbt').innerText = d.mbt;
            document.getElementById('stat-pensiun').innerText = d.pensiun;
            renderChart(d);
            
            if(d.notif_list.length > 0) {
                const names = d.notif_list.map(k => k.nama_karyawan).join(', ');
                document.getElementById('notif-text').innerText = `Update: ${names} masuk status baru bulan ini.`;
                document.getElementById('notif-area').classList.remove('hidden');
                document.getElementById('notif-area').classList.add('flex');
            }
        }
    }

    function renderChart(d) {
        // ... (Chart logic sama spt sebelumnya, diringkas) ...
        const ctxS = document.getElementById('chartStatus').getContext('2d');
        if(window.chS) window.chS.destroy();
        window.chS = new Chart(ctxS, {type:'doughnut', data: {
            labels:['Aktif','MBT','Pensiun','PHK'],
            datasets:[{data:[d.aktif,d.mbt,d.pensiun,d.phk], backgroundColor:['#334155','#f97316','#e11d48','#94a3b8']}]
        }});
        
        const ctxP = document.getElementById('chartPersonil').getContext('2d');
        if(window.chP) window.chP.destroy();
        window.chP = new Chart(ctxP, {type:'bar', data: {
            labels:['Panen','Teknik','Kantor','Gudang'],
            datasets:[{label:'Personil', data:[10,5,8,3], backgroundColor:'#0891b2'}]
        }});
    }

    // --- LOAD TABLE & ACTIONS ---
    async function loadTable(status) {
        const tbody = document.getElementById('tbody-karyawan');
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4">Loading...</td></tr>`;
        const fd = new FormData(); fd.append('action', 'list'); fd.append('status_filter', status);
        const res = await fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json());
        
        if(res.success && res.data.length > 0) {
            tbody.innerHTML = res.data.map(item => `
                <tr class="bg-white border-b hover:bg-gray-50 transition">
                    <td class="px-4 py-3">
                        <div class="font-bold text-gray-800">${item.nama_karyawan}</div>
                        <div class="text-xs text-gray-400">${item.sap_karyawan || '-'} | ${item.nik || ''}</div>
                    </td>
                    <td class="px-4 py-3">${item.bagian || '-'}</td>
                    <td class="px-4 py-3 text-xs">
                        ${item.tgl_lahir}<br><span class="font-bold text-slate-600">${item.usia} Thn</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs font-bold border ${getStatusColor(item.status_karyawan)}">
                            ${item.status_karyawan}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="editData(${item.id})" class="action-btn btn-edit-row mr-1"><i class="ti ti-pencil"></i></button>
                        <button onclick="deleteData(${item.id})" class="action-btn btn-del-row"><i class="ti ti-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-6 text-gray-400 italic">Data kosong</td></tr>`;
        }
    }

    function getStatusColor(s) {
        if(s==='Aktif') return 'bg-cyan-50 text-cyan-700 border-cyan-200';
        if(s==='MBT') return 'bg-orange-50 text-orange-700 border-orange-200';
        if(s==='Pensiun') return 'bg-red-50 text-red-700 border-red-200';
        return 'bg-gray-50 text-gray-600 border-gray-200';
    }

    // --- CRUD FUNCTIONS ---
    function openModal(mode) {
        document.getElementById('form-crud').reset();
        document.getElementById('modal-crud').classList.remove('hidden');
        document.getElementById('modal-crud').classList.add('flex');
        
        if(mode === 'add') {
            document.getElementById('modal-crud-title').innerText = 'Tambah Karyawan';
            document.getElementById('crud-action').value = 'store';
            document.getElementById('crud-id').value = '';
        }
    }
    
    function closeModal() {
        document.getElementById('modal-crud').classList.add('hidden');
        document.getElementById('modal-crud').classList.remove('flex');
    }

    async function editData(id) {
        const fd = new FormData(); fd.append('action', 'get_single'); fd.append('id', id);
        const res = await fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json());
        if(res.success) {
            const d = res.data;
            openModal('edit'); // Reset form first
            document.getElementById('modal-crud-title').innerText = 'Edit Data Karyawan';
            document.getElementById('crud-action').value = 'update';
            document.getElementById('crud-id').value = d.id;
            
            // Fill Inputs
            document.getElementById('inp-nama').value = d.nama_karyawan;
            document.getElementById('inp-sap').value = d.sap_karyawan;
            document.getElementById('inp-nik').value = d.nik;
            document.getElementById('inp-tgl').value = d.tgl_lahir;
            document.getElementById('inp-tmt').value = d.tmt_kerja;
            document.getElementById('inp-bagian').value = d.bagian;
            document.getElementById('inp-status').value = d.status_karyawan;
        }
    }

    document.getElementById('form-crud').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const res = await fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json());
        if(res.success) {
            Swal.fire('Berhasil', res.message, 'success');
            closeModal();
            loadStats();
            if(document.getElementById('view-table').style.display !== 'none') loadTable(currentStatusFilter);
        } else {
            Swal.fire('Error', 'Gagal menyimpan data', 'error');
        }
    });

    function deleteData(id) {
        Swal.fire({title:'Hapus Data?', text:'Data tidak bisa dikembalikan', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Hapus'}).then(async (result) => {
            if(result.isConfirmed) {
                const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
                const res = await fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json());
                if(res.success) {
                    Swal.fire('Terhapus', res.message, 'success');
                    loadStats();
                    loadTable(currentStatusFilter);
                }
            }
        });
    }

    // --- IMPORT & TEMPLATE ---
    function openImportModal() {
        document.getElementById('modal-import').classList.remove('hidden');
        document.getElementById('modal-import').classList.add('flex');
    }

    function downloadTemplate() {
        const form = document.createElement('form');
        form.method = 'POST'; form.action = API_URL;
        const inp = document.createElement('input'); 
        inp.type = 'hidden'; inp.name = 'action'; inp.value = 'download_template';
        form.appendChild(inp); document.body.appendChild(form);
        form.submit(); document.body.removeChild(form);
    }

    document.getElementById('form-import').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const res = await fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json());
        if(res.success) {
            Swal.fire('Sukses', res.message, 'success');
            document.getElementById('modal-import').classList.add('hidden');
            loadStats();
        } else {
            Swal.fire('Gagal Import', 'Cek format file / save as CSV', 'error');
        }
    });
    
    // Search Live
    document.getElementById('search-table').addEventListener('keyup', function() {
        const val = this.value.toLowerCase();
        document.querySelectorAll('#tbody-karyawan tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });
</script>

<?php include_once '../layouts/footer.php'; ?>