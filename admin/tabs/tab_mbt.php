<style>
    .table-container-mbt {
        max-height: 70vh; overflow: auto; position: relative;
        border: 1px solid #cbd5e1; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    table.table-grid-mbt {
        width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1200px;
    }
    table.table-grid-mbt th, table.table-grid-mbt td {
        padding: 0.75rem; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; vertical-align: middle; background-color: #fff;
    }
    /* Header Cyan */
    table.table-grid-mbt thead th {
        position: sticky; top: 0; background: #0891b2; color: #fff; z-index: 40; font-weight: 700; text-transform: uppercase; height: 45px;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.2);
    }
    /* Freeze Column Left */
    th.sticky-col-m, td.sticky-col-m {
        position: sticky; left: 0; z-index: 20; border-right: 2px solid #cbd5e1;
    }
    thead th.sticky-col-m { z-index: 50; background: #0891b2; }
    
    /* Lebar Kolom Freeze */
    .col-foto-m { left: 0px; width: 70px; }
    .col-sap-m  { left: 70px; width: 100px; }
    .col-nama-m { left: 170px; width: 250px; box-shadow: 4px 0 6px -2px rgba(0,0,0,0.1); }

    tr:hover td { background-color: #ecfeff !important; }
</style>

<div class="space-y-4">
    <div class="bg-white p-4 rounded-xl border border-cyan-200 shadow-sm flex items-center gap-3">
        <div class="p-3 bg-cyan-100 rounded-lg text-cyan-700">
            <i class="ti ti-calendar-event text-2xl"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-slate-800">Monitoring MBT</h3>
            <p class="text-sm text-slate-500">Masa Berlaku Tunjangan Karyawan</p>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
        <div class="flex gap-3 items-center w-full md:w-auto text-sm">
            <div class="flex items-center gap-2">
                <span class="text-gray-600 font-semibold">Tahun:</span>
                <select id="f_year_mbt" class="bg-gray-50 border border-gray-300 rounded-lg p-2 w-24 cursor-pointer outline-none focus:ring-2 focus:ring-cyan-500">
                    <?php 
                    $currYear = date('Y');
                    for($y = $currYear - 1; $y <= $currYear + 5; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $currYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <select id="f_afdeling_mbt" class="bg-gray-50 border border-gray-300 rounded-lg p-2 w-48 cursor-pointer outline-none focus:ring-2 focus:ring-cyan-500">
                <option value="">Semua Afdeling</option>
                </select>
        </div>

        <div class="relative w-full md:w-80">
            <i class="ti ti-search absolute left-3 top-2.5 text-gray-400"></i>
            <input type="text" id="q-mbt" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-cyan-500" placeholder="Cari Nama / Jabatan...">
        </div>
    </div>

    <div class="table-container-mbt bg-white">
        <table class="table-grid-mbt">
            <thead>
                <tr>
                    <th class="sticky-col-m col-foto-m text-center">Foto</th>
                    <th class="sticky-col-m col-sap-m">SAP ID</th>
                    <th class="sticky-col-m col-nama-m">Nama Lengkap</th>
                    
                    <th>Kebun</th>
                    <th>Afdeling</th>
                    <th>Jabatan</th>
                    <th>TMT Kerja</th>
                    <th class="text-center bg-orange-500 text-white">Jatuh Tempo (MBT)</th>
                    <th class="text-center">Sisa Waktu</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody id="tbody-mbt" class="text-gray-700"></tbody>
        </table>
    </div>
    
    <div class="text-xs text-gray-500 mt-2 text-right" id="info-mbt">Memuat data...</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial Load
    loadAfdelingMBT();
    loadMBT();

    // 2. Event Listeners
    document.getElementById('f_year_mbt').addEventListener('change', loadMBT);
    document.getElementById('f_afdeling_mbt').addEventListener('change', loadMBT);
    
    let timeoutSearch;
    document.getElementById('q-mbt').addEventListener('input', (e) => {
        clearTimeout(timeoutSearch);
        timeoutSearch = setTimeout(loadMBT, 500);
    });
});

// Load Options Afdeling (Re-use backend logic)
async function loadAfdelingMBT() {
    const fd = new FormData(); 
    fd.append('action', 'list_options'); // Pakai fungsi list_options yg ada di data_karyawan_crud
    
    try {
        const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
        const json = await res.json();
        if(json.success) {
            let html = '<option value="">Semua Afdeling</option>';
            json.afdeling.forEach(a => html += `<option value="${a}">${a}</option>`);
            document.getElementById('f_afdeling_mbt').innerHTML = html;
        }
    } catch(e) { console.error("Gagal load afdeling", e); }
}

// Main Load Function
async function loadMBT() {
    const tbody = document.getElementById('tbody-mbt');
    const info = document.getElementById('info-mbt');
    
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-2xl"></i><br>Memuat data...</td></tr>';
    
    const fd = new FormData();
    fd.append('action', 'list_mbt');
    fd.append('q', document.getElementById('q-mbt').value);
    fd.append('year', document.getElementById('f_year_mbt').value);
    fd.append('f_afdeling', document.getElementById('f_afdeling_mbt').value);
    
    try {
        const res = await fetch('data_karyawan_crud.php', {method:'POST', body:fd});
        
        // Cek response text dulu jika bukan JSON valid
        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch (err) {
            console.error("Server Error:", text);
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-10 text-red-500">Terjadi kesalahan server (Lihat Console).</td></tr>`;
            return;
        }

        if (json.success) {
            if (json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-gray-400 italic">Tidak ada data MBT untuk kriteria ini.</td></tr>';
                info.innerText = '0 Data ditemukan';
                return;
            }

            tbody.innerHTML = json.data.map(r => {
                const foto = r.foto_karyawan ? `../uploads/profil/${r.foto_karyawan}` : '../assets/img/default-avatar.png';
                const sisa = parseInt(r.sisa_hari);
                
                // Logic Badge Warna
                let badge = '';
                let statusText = '';
                
                if (sisa < 0) {
                    badge = 'bg-red-600 text-white animate-pulse';
                    statusText = `Lewat ${Math.abs(sisa)} Hari`;
                } else if (sisa <= 30) {
                    badge = 'bg-red-100 text-red-700 border border-red-200';
                    statusText = `${sisa} Hari Lagi`;
                } else if (sisa <= 90) {
                    badge = 'bg-orange-100 text-orange-700 border border-orange-200';
                    statusText = `${sisa} Hari Lagi`;
                } else {
                    badge = 'bg-green-100 text-green-700 border border-green-200';
                    statusText = `${sisa} Hari Lagi`;
                }

                // Format Tanggal Indo
                const formatDate = (dateString) => {
                    if(!dateString) return '-';
                    const options = { day: 'numeric', month: 'short', year: 'numeric' };
                    return new Date(dateString).toLocaleDateString('id-ID', options);
                };

                return `
                <tr class="hover:bg-cyan-50 border-b transition">
                    <td class="sticky-col-m col-foto-m text-center p-2 bg-white">
                        <img src="${foto}" class="w-8 h-8 rounded-full object-cover mx-auto border shadow-sm">
                    </td>
                    <td class="sticky-col-m col-sap-m bg-white p-3 font-mono text-xs font-bold text-cyan-700">${r.sap_id}</td>
                    <td class="sticky-col-m col-nama-m bg-white p-3 font-bold text-gray-800 text-sm truncate">${r.nama_karyawan}</td>
                    
                    <td class="p-3 text-sm">${r.nama_kebun || '-'}</td>
                    <td class="p-3 text-sm">${r.afdeling || '-'}</td>
                    <td class="p-3 text-sm">${r.jabatan_real || '-'}</td>
                    <td class="p-3 text-center text-xs text-gray-500">${formatDate(r.tmt_kerja)}</td>
                    <td class="p-3 text-center text-sm font-bold text-orange-600 bg-orange-50 border-x border-orange-100">
                        ${formatDate(r.tmt_mbt)}
                    </td>
                    <td class="p-3 text-center">
                        <span class="${badge} px-3 py-1 rounded-full text-xs font-bold shadow-sm block w-fit mx-auto">${statusText}</span>
                    </td>
                    <td class="p-3 text-center">
                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs">${r.status_karyawan}</span>
                    </td>
                </tr>`;
            }).join('');

            info.innerText = json.data.length + ' Data ditemukan';
        }
    } catch (e) { 
        console.error(e); 
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-10 text-red-500">Gagal memuat data.</td></tr>';
    }
}
</script>