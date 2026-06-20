<?php
session_start();
if (!isset($_SESSION['loggedin'])) { header("Location: ../auth/login.php"); exit; }

$unit_id = $_GET['unit_id'] ?? 0;
$kebun_id = $_GET['kebun_id'] ?? 0;

if(!$unit_id || !$kebun_id) { header("Location: peta_rekap_pilih_unit.php"); exit; }

$currentPage = 'peta_rekap';
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<style>
    :root { --cyan-main: #0891b2; }
    body { background-color: #f1f5f9; }
    #map { height: calc(100vh - 260px); min-height: 400px; border-radius: 15px; border: 3px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 10; }
    .leaflet-container { background-color: #ffffff !important; }

    .legend-item { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; font-size: 11px; }
    .legend-color { width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid rgba(0,0,0,0.2); flex-shrink: 0; }

    .rekap-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .rekap-table th, .rekap-table td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: center; }
    .rekap-table thead th { background: #0891b2; color: white; font-weight: bold; }
    .rekap-table tbody tr:hover { background: #ecfeff; }
    .rekap-table tfoot td { background: #ecfeff; font-weight: bold; border-top: 2px solid #0891b2; }
</style>

<div class="p-4 md:p-6 min-h-screen bg-slate-50">
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
        <div class="flex items-center gap-3">
            <a href="peta_rekap_pilih_unit.php" class="bg-white p-2.5 rounded-xl border border-slate-200 text-slate-500 shadow-sm hover:text-cyan-600 transition">
                <i class="ti ti-chevron-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Peta Rekap Bulanan</h1>
                <p class="text-xs font-bold text-cyan-600 uppercase tracking-widest" id="lbl-unit">Memuat info unit...</p>
            </div>
        </div>

        <div class="flex gap-2 items-center">
            <input type="month" id="filter_bulan" class="border border-slate-300 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 outline-none focus:border-cyan-500" value="<?= date('Y-m') ?>" onchange="loadRekapData()">
            <button onclick="cetakRekapPDF()" class="bg-gradient-to-r from-cyan-500 to-cyan-600 text-white px-5 py-2 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center gap-2">
                <i class="ti ti-printer"></i> Cetak Peta Rekap
            </button>
        </div>
    </div>

    <!-- MAP + LEGEND -->
    <div class="relative mb-4">
        <div id="map"></div>
        <!-- Legend overlay -->
        <div id="map-legend" class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-sm rounded-xl p-3 shadow-lg border border-slate-200 z-[1000] hidden">
            <div class="text-[10px] font-black text-slate-500 uppercase mb-2">Legenda Jenis Pekerjaan</div>
            <div id="legend-items"></div>
        </div>
    </div>

    <!-- SUMMARY TABLE -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                <i class="ti ti-table text-cyan-500"></i> Rekapitulasi Per Jenis Pekerjaan
            </h3>
            <span id="lbl-bulan" class="text-xs font-bold text-cyan-600 bg-cyan-50 px-3 py-1 rounded-full"></span>
        </div>
        <div class="overflow-x-auto p-4">
            <table class="rekap-table">
                <thead>
                    <tr>
                        <th style="width:40px">No</th>
                        <th>Jenis Pekerjaan</th>
                        <th>Jumlah Blok</th>
                        <th>Fisik S/D (Ha)</th>
                        <th>HK S/D</th>
                        <th>Bahan Kimia S/D</th>
                        <th>Campuran S/D</th>
                    </tr>
                </thead>
                <tbody id="rekap-tbody">
                    <tr><td colspan="7" class="py-8 text-slate-400 italic">Memuat data...</td></tr>
                </tbody>
                <tfoot id="rekap-tfoot" class="hidden">
                    <tr>
                        <td colspan="2">TOTAL</td>
                        <td id="total-blok">0</td>
                        <td id="total-fisik">0</td>
                        <td id="total-hk">0</td>
                        <td id="total-bahan">0</td>
                        <td id="total-campuran">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
    const KEBUN_ID = <?= $kebun_id ?>;
    const UNIT_ID  = <?= $unit_id ?>;

    const JP_COLORS = [
        '#0891b2', '#e11d48', '#10b981', '#f59e0b',
        '#6366f1', '#ec4899', '#3b82f6', '#14b8a6',
        '#ef4444', '#8b5cf6', '#f97316', '#06b6d4'
    ];

    let map = null;
    let rekapRawData = [];

    // Format number helper
    const fmt = (num) => {
        let n = parseFloat(num) || 0;
        if (n === 0) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
    };

    // === INIT MAP ===
    function initMap(petaKerjaFoto) {
        if (map) { map.off(); map.remove(); }

        if (petaKerjaFoto) {
            map = L.map('map', {
                crs: L.CRS.Simple,
                minZoom: -3, maxZoom: 2,
                zoomControl: false, scrollWheelZoom: false,
                doubleClickZoom: false, touchZoom: false,
                boxZoom: false, keyboard: false,
                preferCanvas: true, zoomSnap: 0
            });

            let fileUrl = `../uploads/pemetaan/base_map/${petaKerjaFoto}`;

            if (petaKerjaFoto.toLowerCase().endsWith('.pdf')) {
                pdfjsLib.getDocument(fileUrl).promise.then(function(pdf) {
                    pdf.getPage(1).then(function(page) {
                        let scale = 2, viewport = page.getViewport({ scale: scale });
                        let canvas = document.createElement('canvas');
                        canvas.width = viewport.width; canvas.height = viewport.height;
                        let ctx = canvas.getContext('2d');
                        page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function() {
                            let imgDataUrl = canvas.toDataURL('image/png');
                            let bounds = [[0, 0], [viewport.height, viewport.width]];
                            L.imageOverlay(imgDataUrl, bounds).addTo(map);
                            map.fitBounds(bounds, { padding: [0, 0] });
                        });
                    });
                });
            } else {
                let img = new Image();
                img.src = fileUrl;
                img.onload = function() {
                    let bounds = [[0, 0], [img.height, img.width]];
                    L.imageOverlay(fileUrl, bounds).addTo(map);
                    map.fitBounds(bounds, { padding: [0, 0] });
                }
            }
        } else {
            map = L.map('map', { preferCanvas: true, zoomControl: false }).setView([3.5952, 98.6722], 13);
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Esri', maxZoom: 19
            }).addTo(map);
        }
    }

    // === LOAD REKAP DATA ===
    async function loadRekapData() {
        const bulan = document.getElementById('filter_bulan').value;
        const tbody = document.getElementById('rekap-tbody');
        tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400"><i class="ti ti-loader animate-spin text-2xl"></i></td></tr>';

        try {
            const res = await fetch(`be/pemetaan_api.php?action=get_rekap_data&kebun_id=${KEBUN_ID}&unit_id=${UNIT_ID}&bulan=${bulan}`);
            const json = await res.json();

            if (!json.success) throw new Error(json.message || 'Gagal memuat data');

            // Set unit info
            const info = json.info || {};
            document.getElementById('lbl-unit').textContent = `${info.nama_kebun || 'KEBUN'} - ${info.nama_unit || 'UNIT'}`;
            document.getElementById('lbl-bulan').textContent = new Date(bulan + '-01').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });

            rekapRawData = json.data;

            // Init map
            initMap(json.peta_kerja_foto);

            // Build JP map: { jp_id: { nama, color, rows: [] } }
            const jpMap = {};
            let colorIdx = 0;

            json.data.forEach(row => {
                let jpId = row.jenis_pekerjaan_id;
                if (!jpMap[jpId]) {
                    jpMap[jpId] = {
                        nama: row.jp_nama || 'Tanpa Nama',
                        color: JP_COLORS[colorIdx % JP_COLORS.length],
                        rows: []
                    };
                    colorIdx++;
                }
                jpMap[jpId].rows.push(row);
            });

            // Render polygons on map
            let allBounds = [];

            Object.entries(jpMap).forEach(([jpId, jp]) => {
                jp.rows.forEach(row => {
                    if (!row.geojson) return;
                    try {
                        let geojsonData = JSON.parse(row.geojson);
                        let layer = L.geoJSON(geojsonData, {
                            style: function() {
                                return { color: jp.color, fillColor: jp.color, weight: 3, fillOpacity: 0.4 };
                            },
                            pointToLayer: function(feature, latlng) {
                                return L.circleMarker(latlng, { radius: 7, fillColor: jp.color, color: "#fff", weight: 2, fillOpacity: 0.9 });
                            }
                        }).bindPopup(`
                            <div class="p-1">
                                <div class="font-bold text-sm">${row.blok_nama || '-'}</div>
                                <div class="text-[11px] text-slate-500">${jp.nama}</div>
                                <div class="text-[10px] mt-1">
                                    Fisik S/D: <b>${fmt(row.fisik_sd)}</b> Ha<br>
                                    HK S/D: <b>${fmt(row.hk_sd)}</b><br>
                                    Bahan Kimia S/D: <b>${fmt(row.bahan_kimia_sd)}</b><br>
                                    Campuran S/D: <b>${fmt(row.campuran_sd)}</b>
                                </div>
                            </div>
                        `).addTo(map);

                        if (layer.getBounds && layer.getBounds().isValid()) {
                            allBounds.push(layer.getBounds());
                        }
                    } catch(e) { console.error('GeoJSON parse error:', e); }
                });
            });

            // Fit all bounds
            if (allBounds.length > 0) {
                let fb = allBounds[0];
                for (let i = 1; i < allBounds.length; i++) fb.extend(allBounds[i]);
                map.fitBounds(fb, { padding: [20, 20], animate: false });
            }

            // Build legend
            buildLegend(jpMap);

            // Build summary table
            buildTable(jpMap);

        } catch (err) {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500">Error: ${err.message}</td></tr>`;
        }
    }

    // === BUILD LEGEND ===
    function buildLegend(jpMap) {
        const container = document.getElementById('legend-items');
        const legendBox = document.getElementById('map-legend');
        container.innerHTML = '';

        if (Object.keys(jpMap).length === 0) {
            legendBox.classList.add('hidden');
            return;
        }

        legendBox.classList.remove('hidden');
        Object.entries(jpMap).forEach(([jpId, jp]) => {
            container.innerHTML += `
                <div class="legend-item">
                    <div class="legend-color" style="background:${jp.color}"></div>
                    <span class="font-semibold text-slate-700">${jp.nama}</span>
                </div>
            `;
        });
    }

    // === BUILD SUMMARY TABLE ===
    function buildTable(jpMap) {
        const tbody = document.getElementById('rekap-tbody');
        const tfoot = document.getElementById('rekap-tfoot');
        tbody.innerHTML = '';

        let grandBlok = 0, grandFisik = 0, grandHk = 0, grandBahan = 0, grandCampuran = 0;
        let no = 1;

        Object.entries(jpMap).forEach(([jpId, jp]) => {
            // Aggregate per JP: max S/D per row, count blocks
            let blokCount = 0;
            let maxFisik = 0, maxHk = 0, maxBahan = 0, maxCampuran = 0;

            jp.rows.forEach(row => {
                blokCount++;
                let f = parseFloat(row.fisik_sd) || 0;
                let h = parseFloat(row.hk_sd) || 0;
                let b = parseFloat(row.bahan_kimia_sd) || 0;
                let c = parseFloat(row.campuran_sd) || 0;
                if (f > maxFisik) maxFisik = f;
                if (h > maxHk) maxHk = h;
                if (b > maxBahan) maxBahan = b;
                if (c > maxCampuran) maxCampuran = c;
            });

            grandBlok += blokCount;
            grandFisik += maxFisik;
            grandHk += maxHk;
            grandBahan += maxBahan;
            grandCampuran += maxCampuran;

            tbody.innerHTML += `
                <tr>
                    <td>${no++}</td>
                    <td class="text-left font-semibold">
                        <span class="inline-block w-3 h-3 rounded mr-2" style="background:${jp.color}"></span>
                        ${jp.nama}
                    </td>
                    <td>${blokCount}</td>
                    <td class="font-mono">${fmt(maxFisik)}</td>
                    <td class="font-mono">${fmt(maxHk)}</td>
                    <td class="font-mono">${fmt(maxBahan)}</td>
                    <td class="font-mono">${fmt(maxCampuran)}</td>
                </tr>
            `;
        });

        if (Object.keys(jpMap).length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400 italic">Belum ada data realisasi untuk bulan ini.</td></tr>';
            tfoot.classList.add('hidden');
        } else {
            tfoot.classList.remove('hidden');
            document.getElementById('total-blok').textContent = grandBlok;
            document.getElementById('total-fisik').textContent = fmt(grandFisik);
            document.getElementById('total-hk').textContent = fmt(grandHk);
            document.getElementById('total-bahan').textContent = fmt(grandBahan);
            document.getElementById('total-campuran').textContent = fmt(grandCampuran);
        }
    }

    // === CETAK PDF ===
    async function cetakRekapPDF() {
        const mapDiv = document.getElementById('map');
        if (!mapDiv) { Swal.fire('Error', 'Peta tidak ditemukan.', 'error'); return; }

        Swal.fire({
            title: 'Memproses Cetak...',
            text: 'Menangkap gambar peta untuk PDF.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        // Hide legend temporarily
        const legend = document.getElementById('map-legend');
        const oldLegendDisplay = legend.style.display;
        legend.style.display = 'none';

        // Hide map controls
        const controls = document.querySelectorAll('.leaflet-control-container');
        controls.forEach(c => c.style.display = 'none');
        const oldBS = mapDiv.style.boxShadow;
        const oldBR = mapDiv.style.borderRadius;
        const oldBD = mapDiv.style.border;
        mapDiv.style.boxShadow = 'none';
        mapDiv.style.borderRadius = '0';
        mapDiv.style.border = 'none';
        window.scrollTo(0, 0);

        let mapImage = '';
        try {
            let canvas = await html2canvas(mapDiv, {
                useCORS: true, allowTaint: true, scale: 1.5, scrollY: -window.scrollY
            });
            mapImage = canvas.toDataURL("image/jpeg", 0.7);
        } catch (e) {
            controls.forEach(c => c.style.display = '');
            mapDiv.style.boxShadow = oldBS;
            mapDiv.style.borderRadius = oldBR;
            mapDiv.style.border = oldBD;
            legend.style.display = oldLegendDisplay;
            Swal.fire('Error', 'Gagal menangkap gambar peta.', 'error');
            return;
        }

        // Restore styles
        controls.forEach(c => c.style.display = '');
        mapDiv.style.boxShadow = oldBS;
        mapDiv.style.borderRadius = oldBR;
        mapDiv.style.border = oldBD;
        legend.style.display = oldLegendDisplay;

        // Show rendering spinner
        Swal.fire({
            title: 'Merender PDF...',
            html: '<div style="margin-bottom:8px">Mohon tunggu, sedang membuat file PDF.</div><div style="background:#e2e8f0;border-radius:999px;height:10px;overflow:hidden"><div style="background:linear-gradient(90deg,#0891b2,#06b6d4);height:100%;width:10%;border-radius:999px;animation:pdfProg 4s ease-in-out infinite"></div></div><style>@keyframes pdfProg{0%{width:10%}50%{width:70%}100%{width:95%}}</style>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        // Build rekap summary data to send to PHP
        const bulan = document.getElementById('filter_bulan').value;
        const rekapSummary = {};
        let colorIdx = 0;
        const jpMap = {};
        rekapRawData.forEach(row => {
            let jpId = row.jenis_pekerjaan_id;
            if (!jpMap[jpId]) {
                jpMap[jpId] = { nama: row.jp_nama || '-', color: JP_COLORS[colorIdx % JP_COLORS.length], rows: [] };
                colorIdx++;
            }
            jpMap[jpId].rows.push(row);
        });

        // Aggregate per JP
        Object.entries(jpMap).forEach(([jpId, jp]) => {
            let maxF = 0, maxH = 0, maxB = 0, maxC = 0;
            jp.rows.forEach(r => {
                if ((parseFloat(r.fisik_sd)||0) > maxF) maxF = parseFloat(r.fisik_sd)||0;
                if ((parseFloat(r.hk_sd)||0) > maxH) maxH = parseFloat(r.hk_sd)||0;
                if ((parseFloat(r.bahan_kimia_sd)||0) > maxB) maxB = parseFloat(r.bahan_kimia_sd)||0;
                if ((parseFloat(r.campuran_sd)||0) > maxC) maxC = parseFloat(r.campuran_sd)||0;
            });
            rekapSummary[jpId] = {
                nama: jp.nama,
                color: jp.color,
                blok_count: jp.rows.length,
                fisik_sd: maxF,
                hk_sd: maxH,
                bahan_kimia_sd: maxB,
                campuran_sd: maxC
            };
        });

        try {
            const formData = new FormData();
            formData.append('unit_id', UNIT_ID);
            formData.append('kebun_id', KEBUN_ID);
            formData.append('bulan', bulan);
            formData.append('map_image', mapImage);
            formData.append('rekap_data', JSON.stringify(rekapSummary));

            const response = await fetch('cetak/peta_rekap_pdf.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errText = await response.text();
                throw new Error(errText || `HTTP ${response.status}`);
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');

            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'PDF Peta Rekap berhasil dibuat.',
                timer: 2000,
                showConfirmButton: false
            });

        } catch (error) {
            console.error('PDF Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Membuat PDF',
                html: `<div style="text-align:left;font-size:12px;background:#fee2e2;padding:10px;border-radius:8px;color:#991b1b;max-height:150px;overflow:auto"><b>Error:</b><br>${error.message}</div>`
            });
        }
    }

    // === INIT ===
    document.addEventListener('DOMContentLoaded', loadRekapData);
</script>

<?php include_once '../layouts/footer.php'; ?>
