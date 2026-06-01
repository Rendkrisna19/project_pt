<?php
session_start();
if (!isset($_SESSION['loggedin'])) { header("Location: ../auth/login.php"); exit; }
$currentPage = 'pemetaan'; // Untuk sidebar
include_once '../layouts/header.php'; 
?>

<div class="p-6 min-h-screen bg-slate-50">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800">Pemetaan Kebun (GIS)</h1>
            <p class="text-slate-500 text-sm mt-1">Pilih unit untuk mulai memetakan aset dan blok kebun.</p>
        </div>
        
        <div class="w-full md:w-80">
            <label class="block text-xs font-bold text-cyan-700 uppercase mb-2">Filter Berdasarkan Kebun</label>
            <select id="filter_kebun" onchange="loadUnits()" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold outline-none focus:border-cyan-500 bg-slate-50">
                <option value="">— Loading... —</option>
            </select>
        </div>
    </div>

    <div id="unit-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        </div>
</div>

<script>
    // FUNGSI MENGAMBIL DATA DARI BACKEND API
    async function loadUnits() {
        const kebunId = document.getElementById('filter_kebun').value;
        const container = document.getElementById('unit-container');
        
        container.innerHTML = '<div class="col-span-full text-center py-10 text-cyan-600"><i class="ti ti-loader animate-spin text-4xl"></i></div>';

        try {
           const res = await fetch(`be/pemetaan_api.php?action=get_units&kebun_id=${kebunId}`);
            const json = await res.json();

            if(json.success) {
                // Render Dropdown (Hanya jika awal buka)
                if(!kebunId) {
                    const sel = document.getElementById('filter_kebun');
                    sel.innerHTML = '<option value="">— Tampilkan Semua Kebun —</option>';
                    json.kebuns.forEach(k => {
                        sel.innerHTML += `<option value="${k.id}">${k.nama_kebun}</option>`;
                    });
                }

                // Render Card Unit
                container.innerHTML = '';
                if(json.units.length === 0) {
                    container.innerHTML = `<div class="col-span-full text-center text-slate-400 py-10">Tidak ada unit ditemukan.</div>`;
                    return;
                }

                json.units.forEach(u => {
                    container.innerHTML += `
                    <a href="pemetaan.php?kebun_id=${u.kebun_id}&unit_id=${u.id}" class="group block h-full">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 hover:border-cyan-500 hover:shadow-xl transition-all duration-300 p-6 flex flex-col h-full relative overflow-hidden">
                            <div class="flex items-start gap-4 relative z-10">
                                <div class="bg-slate-50 text-slate-400 rounded-xl p-3.5 group-hover:bg-cyan-600 group-hover:text-white transition-colors border border-slate-100 shadow-sm shrink-0">
                                    <i class="ti ti-map-2 text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-extrabold text-slate-800 text-lg mb-1 group-hover:text-cyan-700">${u.nama_unit}</h3>
                                    <div class="text-[10px] text-slate-500 font-bold uppercase bg-slate-100 w-fit px-2 py-1 rounded">
                                        ${u.nama_kebun}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-auto pt-4 relative z-10">
                                <div class="h-px w-full bg-slate-100 mb-3"></div>
                                <div class="text-xs text-cyan-600 font-bold">Buka Peta &rarr;</div>
                            </div>
                        </div>
                    </a>`;
                });
            }
        } catch (e) {
            console.error("Error Fetching Data:", e);
            container.innerHTML = `<div class="col-span-full text-center text-red-500">Gagal memuat data sistem.</div>`;
        }
    }

    // Jalankan saat web dibuka
    document.addEventListener('DOMContentLoaded', loadUnits);
</script>

<?php include_once '../layouts/footer.php'; ?>