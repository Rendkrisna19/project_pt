<!-- TAB: MONITORING MBT -->
<div class="space-y-4">
    <div class="bg-white p-4 rounded-xl border border-orange-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-orange-100 rounded-lg">
                <i class="ti ti-calendar-event text-orange-600 text-2xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-800">Monitoring Masa Berlaku Tunjangan (MBT)</h3>
                <p class="text-sm text-slate-500">Daftar karyawan dengan TMT MBT dalam 6 bulan ke depan</p>
            </div>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex justify-between items-center">
        <div class="relative w-96">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
            <input type="text" id="q-mbt" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 outline-none" placeholder="Cari nama atau jabatan...">
        </div>
        <div class="text-sm text-gray-500" id="info-mbt">Memuat data...</div>
    </div>

    <div class="sticky-container">
        <table class="table-grid">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>SAP ID</th>
                    <th>Nama Lengkap</th>
                    <th>Jabatan Real</th>
                    <th>Afdeling</th>
                    <th>TMT Kerja</th>
                    <th class="bg-orange-600">TMT MBT</th>
                    <th>Sisa Hari</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="tbody-mbt"></tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('tbody-mbt');
    const q = document.getElementById('q-mbt');

    async function loadMBT() {
        const query = q.value;
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-10">Memuat...</td></tr>';
        
        try {
            const fd = new FormData();
            fd.append('action', 'list_mbt');
            fd.append('q', query);
            
            const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
            const json = await res.json();

            if (json.success) {
                if (json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-gray-400">Tidak ada data MBT dalam 6 bulan ke depan.</td></tr>';
                    document.getElementById('info-mbt').innerText = '0 Data';
                    return;
                }

                tbody.innerHTML = json.data.map(r => {
                    const foto = r.foto_karyawan ? '../uploads/profil/' + r.foto_karyawan : '../assets/img/default-avatar.png';
                    const sisaHari = r.sisa_hari;
                    let badgeColor = 'bg-green-100 text-green-700';
                    if (sisaHari <= 30) badgeColor = 'bg-red-100 text-red-700';
                    else if (sisaHari <= 90) badgeColor = 'bg-orange-100 text-orange-700';

                    return `
                    <tr class="hover:bg-orange-50">
                        <td class="text-center"><img src="${foto}" class="avatar-sm mx-auto"></td>
                        <td class="font-mono text-xs">${r.sap_id || '-'}</td>
                        <td class="font-bold">${r.nama_karyawan}</td>
                        <td>${r.jabatan_real || '-'}</td>
                        <td>${r.afdeling || '-'}</td>
                        <td class="text-sm text-slate-500">${r.tmt_kerja || '-'}</td>
                        <td class="font-bold text-orange-600">${r.tmt_mbt}</td>
                        <td class="text-center"><span class="${badgeColor} px-3 py-1 rounded-full text-xs font-bold">${sisaHari} hari</span></td>
                        <td><span class="bg-cyan-100 text-cyan-800 px-2 py-0.5 rounded text-xs font-bold">${r.status_karyawan}</span></td>
                    </tr>`;
                }).join('');

                document.getElementById('info-mbt').innerText = json.data.length + ' Data ditemukan';
            }
        } catch (e) { console.error(e); }
    }

    q.addEventListener('input', () => { clearTimeout(window.tMBT); window.tMBT = setTimeout(loadMBT, 300); });
    loadMBT();
});
</script>