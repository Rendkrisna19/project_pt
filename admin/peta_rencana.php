<?php
session_start();
if (!isset($_SESSION['loggedin'])) { header("Location: ../auth/login.php"); exit; }

$unit_id = $_GET['unit_id'] ?? 0;
$kebun_id = $_GET['kebun_id'] ?? 0;

if(!$unit_id || !$kebun_id) { header("Location: peta_rencana_pilih_unit.php"); exit; }

$currentPage = 'rencana_pemel';
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<style>
    :root { --cyan-main: #0891b2; }
    body { background-color: #f1f5f9; }
    #map { height: calc(100vh - 260px); min-height: 380px; border-radius: 15px; border: 3px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 10; }
    .leaflet-container { background-color: #ffffff !important; }

    .legend-item { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; font-size: 11px; }
    .legend-color { width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid rgba(0,0,0,0.2); flex-shrink: 0; }

    .rencana-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .rencana-table th, .rencana-table td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: center; }
    .rencana-table thead th { background: #0891b2; color: white; font-weight: bold; font-size: 10px; text-transform: uppercase; }
    .rencana-table tbody tr:hover { background: #ecfeff; }
    .kategori-badge { display: inline-block; padding: 2px 8px; border-radius: 50px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
</style>

<div class="p-4 md:p-6 min-h-screen bg-slate-50">
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
        <div class="flex items-center gap-3">
            <a href="peta_rencana_pilih_unit.php" class="bg-white p-2.5 rounded-xl border border-slate-200 text-slate-500 shadow-sm hover:text-cyan-600 transition">
                <i class="ti ti-chevron-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Rencana Pemeliharaan</h1>
                <p class="text-xs font-bold text-cyan-600 uppercase tracking-widest" id="lbl-unit">Memuat info unit...</p>
            </div>
        </div>

        <div class="flex gap-2 items-center">
            <select id="filter_bulan" class="border border-slate-300 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 outline-none focus:border-cyan-500" onchange="loadRencanaData()">
                <option value="Januari">Januari</option><option value="Februari">Februari</option>
                <option value="Maret">Maret</option><option value="April">April</option>
                <option value="Mei">Mei</option><option value="Juni">Juni</option>
                <option value="Juli">Juli</option><option value="Agustus">Agustus</option>
                <option value="September">September</option><option value="Oktober">Oktober</option>
                <option value="November">November</option><option value="Desember">Desember</option>
            </select>
            <input type="number" id="filter_tahun" class="border border-slate-300 rounded-xl px-4 py-2 text-sm font-bold text-slate-700 outline-none focus:border-cyan-500 w-24" value="<?= date('Y') ?>" onchange="loadRencanaData()">
        </div>
    </div>

    <!-- MAP + LEGEND -->
    <div class="relative mb-4">
        <div id="map"></div>
        <!-- Legend overlay -->
        <div id="map-legend" class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-sm rounded-xl p-3 shadow-lg border border-slate-200 z-[1000] hidden">
            <div class="text-[10px] font-black text-slate-500 uppercase mb-2">Legenda Kategori</div>
            <div id="legend-items"></div>
        </div>
    </div>

    <!-- SUMMARY TABLE -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                <i class="ti ti-clipboard-list text-cyan-500"></i> Rencana Kegiatan Pemeliharaan
            </h3>
            <span id="lbl-bulan" class="text-xs font-bold text-cyan-600 bg-cyan-50 px-3 py-1 rounded-full"></span>
        </div>
        <div class="overflow-x-auto p-4">
            <table class="rencana-table">
                <thead>
                    <tr>
                        <th style="width:35px">No</th>
                        <th>Kategori</th>
                        <th>Jenis Pekerjaan</th>
                        <th>Keterangan</th>
                        <th>HK</th>
                        <th>Satuan</th>
                        <th>Anggaran</th>
                    </tr>
                </thead>
                <tbody id="rencana-tbody">
                    <tr><td colspan="7" class="py-8 text-slate-400 italic">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const KEBUN_ID = <?= $kebun_id ?>;
    const UNIT_ID  = <?= $unit_id ?>;

    const KATEGORI_COLORS = {
        'TM': '#0891b2', 'TU': '#e11d48', 'TK': '#10b981', 
        'TBM1': '#f59e0b', 'TBM2': '#8b5cf6', 'TBM3': '#ec4899',
        'PN': '#3b82f6', 'MN': '#14b8a6'
    };
    const KATEGORI_LABELS = {
        'TM': 'TM (Tanaman Menghasilkan)', 'TU': 'TU (Tanaman Belum Menghasilkan)',
        'TK': 'TK (Tanaman Konservasi)', 'TBM1': 'TBM I', 'TBM2': 'TBM II', 'TBM3': 'TBM III',
        'PN': 'PN (Pembibitan PN)', 'MN': 'MN (Pembibitan MN)'
    };

    let map = null;

    const fmt = (num) => {
        let n = parseFloat(num) || 0;
        if (n === 0) return '-';
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n);
    };
    const fmtRp = (num) => {
        let n = parseFloat(num) || 0;
        if (n === 0) return '-';
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
    };

    // Set default bulan to current month name
    const bulanIdx = parseInt('<?= date('n') ?>') - 1;
    const bulanNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('filter_bulan').value = bulanNames[bulanIdx];

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

    // === LOAD RENCANA DATA ===
    async function loadRencanaData() {
        const bulan = document.getElementById('filter_bulan').value;
        const tahun = document.getElementById('filter_tahun').value;
        const tbody = document.getElementById('rencana-tbody');
        tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400"><i class="ti ti-loader animate-spin text-2xl"></i></td></tr>';

        try {
            const res = await fetch(`be/pemetaan_api.php?action=get_rencana_data&unit_id=${UNIT_ID}&bulan=${bulan}&tahun=${tahun}`);
            const json = await res.json();

            if (!json.success) throw new Error(json.message || 'Gagal memuat data');

            // Set unit info
            const info = json.info || {};
            document.getElementById('lbl-unit').textContent = `${info.nama_kebun || 'KEBUN'} - ${info.nama_unit || 'UNIT'}`;
            document.getElementById('lbl-bulan').textContent = `${bulan} ${tahun}`;

            // Init map
            initMap(json.peta_kerja_foto);

            // Render geo polygons on map (from tr_pemetaan for same month)
            const geoData = json.geo_data || [];
            const jpColors = {};
            const jpNames = {};
            let colorIdx = 0;
            const palette = ['#0891b2','#e11d48','#10b981','#f59e0b','#6366f1','#ec4899','#3b82f6','#14b8a6','#ef4444','#8b5cf6','#f97316','#84cc16'];
            let allBounds = [];

            geoData.forEach(row => {
                let jpId = row.jenis_pekerjaan_id;
                if (!jpColors[jpId]) {
                    jpColors[jpId] = palette[colorIdx % palette.length];
                    jpNames[jpId] = row.jp_nama || '-';
                    colorIdx++;
                }
                if (!row.geojson) return;
                try {
                    let gd = JSON.parse(row.geojson);
                    let layer = L.geoJSON(gd, {
                        style: function() {
                            return { color: jpColors[jpId], fillColor: jpColors[jpId], weight: 3, fillOpacity: 0.35 };
                        },
                        pointToLayer: function(f, ll) {
                            return L.circleMarker(ll, { radius: 7, fillColor: jpColors[jpId], color: "#fff", weight: 2, fillOpacity: 0.9 });
                        }
                    }).bindPopup(`
                        <div class="p-1">
                            <div class="font-bold text-sm">${row.blok_nama || '-'}</div>
                            <div class="text-[11px] text-slate-500">${row.jp_nama || '-'}</div>
                            <div class="text-[10px] mt-1">
                                Fisik S/D: <b>${fmt(row.fisik_sd)}</b> Ha<br>
                                HK S/D: <b>${fmt(row.hk_sd)}</b>
                            </div>
                        </div>
                    `).addTo(map);
                    if (layer.getBounds && layer.getBounds().isValid()) allBounds.push(layer.getBounds());
                } catch(e) { console.error('GeoJSON parse error:', e); }
            });

            if (allBounds.length > 0) {
                let fb = allBounds[0];
                for (let i = 1; i < allBounds.length; i++) fb.extend(allBounds[i]);
                map.fitBounds(fb, { padding: [20, 20], animate: false });
            }

            // Build legend from geo data
            buildGeoLegend(jpColors, jpNames);

            // Build rencana table
            buildRencanaTable(json.data);

        } catch (err) {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-red-500">Error: ${err.message}</td></tr>`;
        }
    }

    // === BUILD GEO LEGEND ===
    function buildGeoLegend(jpColors, jpNames) {
        const container = document.getElementById('legend-items');
        const legendBox = document.getElementById('map-legend');
        container.innerHTML = '';

        const keys = Object.keys(jpColors);
        if (keys.length === 0) { legendBox.classList.add('hidden'); return; }

        legendBox.classList.remove('hidden');
        keys.forEach(jpId => {
            container.innerHTML += `
                <div class="legend-item">
                    <div class="legend-color" style="background:${jpColors[jpId]}"></div>
                    <span class="font-semibold text-slate-700">${jpNames[jpId]}</span>
                </div>
            `;
        });
    }

    // === BUILD RENCANA TABLE ===
    function buildRencanaTable(data) {
        const tbody = document.getElementById('rencana-tbody');
        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-400 italic">Belum ada rencana pemeliharaan untuk bulan ini.</td></tr>';
            return;
        }

        let no = 1;
        let lastKat = '';
        data.forEach(row => {
            const kat = row.kategori || '-';
            const color = KATEGORI_COLORS[kat] || '#64748b';
            const katLabel = KATEGORI_LABELS[kat] || kat;

            // Group header when kategori changes
            if (kat !== lastKat) {
                tbody.innerHTML += `
                    <tr class="bg-slate-50">
                        <td colspan="7" class="text-left font-black text-slate-600 text-[10px] uppercase tracking-wider px-4 py-2">
                            <span class="inline-block w-3 h-3 rounded mr-2" style="background:${color}"></span>
                            ${katLabel}
                        </td>
                    </tr>`;
                lastKat = kat;
            }

            tbody.innerHTML += `
                <tr>
                    <td>${no++}</td>
                    <td>
                        <span class="kategori-badge text-white" style="background:${color}">${kat}</span>
                    </td>
                    <td class="text-left font-semibold">${row.jenis_pekerjaan || '-'}</td>
                    <td class="text-left text-slate-500">${row.keterangan || '-'}</td>
                    <td class="font-mono">${fmt(row.hk)}</td>
                    <td>${row.satuan || '-'}</td>
                    <td class="font-mono text-right">${fmtRp(row.anggaran_tahun)}</td>
                </tr>
            `;
        });
    }

    // === INIT ===
    document.addEventListener('DOMContentLoaded', loadRencanaData);
</script>

<?php include_once '../layouts/footer.php'; ?>
