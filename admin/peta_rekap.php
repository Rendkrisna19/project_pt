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
<link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css" />
<script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<style>
    :root { --cyan-main: #0891b2; }
    body { background-color: #f1f5f9; }
    #map { height: calc(100vh - 140px); border-radius: 15px; border: 3px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 10; }
    .leaflet-container { background-color: #ffffff !important; }
    .card-glass { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border: 1px solid rgba(8,145,178,0.1); }
    .input-custom { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; outline: none; }
    .input-custom:focus { border-color: var(--cyan-main); box-shadow: 0 0 0 3px rgba(8,145,178,0.1); }
    .btn-cyan { background-color: var(--cyan-main); color: white; font-weight: 700; padding: 12px; border-radius: 10px; transition: all 0.3s; display: flex; justify-content: center; align-items: center; gap: 8px; }
    .btn-cyan:hover { background-color: #0e7490; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(8,145,178,0.3); }
    .month-input { width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 6px; font-size: 11px; text-align: center; outline: none; }
    .month-input:focus { border-color: var(--cyan-main); box-shadow: 0 0 0 2px rgba(8,145,178,0.15); }
    .month-label { font-size: 9px; font-weight: 800; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; text-align: center; }
    .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: bold; }
    .status-empty { background: #fee2e2; color: #e11d48; }
    .status-filled { background: #dcfce3; color: #059669; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 5px 8px; text-align: center; }
    .data-table thead th { background: #0891b2; color: white; font-weight: bold; font-size: 10px; text-transform: uppercase; }
    .data-table tbody tr:hover { background: #ecfeff; }
</style>

<div class="p-4 md:p-6 min-h-screen bg-slate-50">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
        <div class="flex items-center gap-3">
            <a href="peta_rekap_pilih_unit.php" class="bg-white p-2.5 rounded-xl border border-slate-200 text-slate-500 shadow-sm hover:text-cyan-600 transition">
                <i class="ti ti-chevron-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">MCS Bulanan</h1>
                <p class="text-xs font-bold text-cyan-600 uppercase tracking-widest" id="lbl-unit">Memuat info unit...</p>
            </div>
        </div>
        <div class="flex gap-2 items-center flex-wrap">
            <div class="flex items-center bg-white px-3 py-1.5 rounded-xl border border-cyan-300 shadow-sm">
                <span class="text-[10px] font-bold text-slate-500 uppercase mr-2">Luas (Ha):</span>
                <input type="number" step="0.01" id="input_luas" class="w-20 text-sm font-bold text-slate-800 outline-none bg-transparent" onchange="saveLuas()" placeholder="0.00" title="Tekan Enter / klik di luar untuk menyimpan">
            </div>
            <select id="filter_tahun" class="input-custom min-w-[100px] border-cyan-300 font-bold text-slate-700" onchange="changeYear()">
                <?php $curYear = (int)date('Y'); for($y = $curYear + 1; $y >= $curYear - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $y == $curYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <select id="filter_jp" class="input-custom min-w-[200px] border-cyan-300 font-bold text-slate-700" onchange="filterByJP()">
                <option value="">— Semua Pekerjaan —</option>
            </select>
            <button onclick="exportPDF()" class="bg-gradient-to-r from-cyan-500 to-cyan-600 text-white px-5 py-2 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center gap-2">
                <i class="ti ti-printer"></i> Cetak PDF
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- LEFT PANEL -->
        <div class="lg:col-span-1 space-y-4">
            <!-- Upload Base Map -->
            <div id="card_upload_peta" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-cyan-500 hidden">
                <h3 class="font-extrabold text-slate-800 mb-2 flex items-center gap-2">
                    <i class="ti ti-photo-plus text-cyan-500 text-xl"></i> Peta Dasar
                </h3>
                <p class="text-xs text-slate-500 mb-4">Upload gambar denah/Peta Kerja. <strong>Cukup 1x upload</strong>, berlaku untuk semua pekerjaan.</p>
                <form id="form-upload-peta">
                    <input type="hidden" name="action" value="upload_peta_dasar">
                    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
                    <input type="hidden" name="jenis_pekerjaan_id" value="99999">
                    <input type="file" name="peta_dasar" accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer mb-2" required>
                    <button type="submit" id="btn-upload-peta" class="w-full bg-cyan-500 text-white font-bold py-2 rounded-lg hover:bg-cyan-600 text-sm shadow-md transition">Upload & Pasang</button>
                </form>
            </div>

            <!-- Form Input -->
            <div class="card-glass p-6 rounded-2xl shadow-sm border-t-4 border-cyan-600 h-fit">
                <h3 class="font-extrabold text-slate-800 mb-5 flex items-center gap-2">
                    <i class="ti ti-pencil text-cyan-600 text-xl"></i> Input Realisasi Bulanan
                </h3>
                <form id="form-mcs" class="space-y-4">
                    <input type="hidden" name="action" value="save_mcs_bulanan">
                    <input type="hidden" name="id" id="input_edit_id" value="">
                    <input type="hidden" name="kebun_id" value="<?= $kebun_id ?>">
                    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
                    <input type="hidden" name="geojson" id="input_geojson">
                    <input type="hidden" name="latitude" id="input_lat">
                    <input type="hidden" name="longitude" id="input_lng">
                    <input type="hidden" name="tahun" id="input_tahun" value="<?= date('Y') ?>">

                    <!-- Jenis Pekerjaan (Hidden, synced with top filter) -->
                    <input type="hidden" name="jenis_pekerjaan_bulanan_id" id="input_jp" value="">

                    <!-- Objek Pekerjaan -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5 ml-1">Objek Pekerjaan</label>
                        <input type="text" name="objek_pekerjaan" id="input_objek" class="input-custom" placeholder="Contoh: Rotasi I, Pokok, dll">
                    </div>

                    <!-- Removed Bulan Aktif dropdown -->

                    <input type="hidden" name="warna" id="input_warna" value="#FFFF00">

                    <!-- 12 Month Inputs -->
                    <div class="bg-cyan-50/50 p-3 rounded-xl border border-cyan-100">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="ti ti-calendar text-cyan-600"></i>
                            <h4 class="text-[11px] font-extrabold text-slate-700 uppercase">Realisasi Per Bulan (Ha)</h4>
                        </div>
                        <div class="grid grid-cols-3 gap-2" id="month-inputs">
                            <!-- Generated by JS -->
                        </div>
                        <div class="mt-3 pt-2 border-t border-cyan-200 flex justify-between items-center">
                            <span class="text-[10px] font-black text-slate-600 uppercase">Total Ha:</span>
                            <span id="total_ha" class="text-sm font-black text-cyan-700">0.00</span>
                        </div>
                    </div>

                    <!-- Drawing Status -->
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-center">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Data Spasial Map</label>
                        <div id="status_gambar" class="status-badge status-empty">
                            <i class="ti ti-x"></i> Belum Digambar
                        </div>
                        <p class="text-[9px] text-slate-400 mt-2">Gunakan tools di kiri peta untuk menggambar area.</p>
                        <button type="button" onclick="clearDrawnItems()" class="mt-2 text-[10px] font-bold text-red-500 hover:underline">Hapus Semua Gambar</button>
                    </div>

                    <!-- Keterangan -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5 ml-1">Keterangan</label>
                        <textarea name="keterangan" id="input_keterangan" class="input-custom" rows="2" placeholder="Catatan..."></textarea>
                    </div>

                    <button type="submit" id="btn-submit" class="w-full btn-cyan shadow-lg shadow-cyan-200">
                        <i class="ti ti-cloud-upload"></i> Simpan Data
                    </button>
                    <button type="button" id="btn-cancel-edit" class="w-full bg-slate-200 text-slate-600 font-bold py-3 rounded-xl text-sm hidden hover:bg-slate-300 transition" onclick="cancelEdit()">
                        <i class="ti ti-x"></i> Batal Edit
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="lg:col-span-3 space-y-4">
            <div id="map"></div>

            <!-- Data Table -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-sm font-black text-slate-700 uppercase tracking-wider flex items-center gap-2">
                        <i class="ti ti-table text-cyan-600"></i> Data Realisasi Bulanan
                    </h4>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="data-table" id="table-data">
                        <thead>
                            <tr>
                                <th rowspan="2">Objek</th>
                                <th rowspan="2">Jenis Pek.</th>
                                <th colspan="12">Realisasi Per Bulan (Ha)</th>
                                <th rowspan="2">Total</th>
                                <th rowspan="2">Aksi</th>
                            </tr>
                            <tr id="month-header-row">
                                <!-- 12 month headers generated by JS -->
                            </tr>
                        </thead>
                        <tbody id="table-body">
                            <tr><td colspan="16" class="py-8 text-slate-400 italic text-center">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const KEBUN_ID = <?= $kebun_id ?>;
    const UNIT_ID  = <?= $unit_id ?>;
    let allData = [];
    let luasData = {};
    let activeYear = parseInt(document.getElementById('filter_tahun').value);

    // 12 Fixed Month Colors (Berdasarkan Template)
    const MONTH_COLORS = {
        1:  { color: '#FFFF00', name: 'Jan',  label: 'Januari'   },
        2:  { color: '#00FF99', name: 'Peb',  label: 'Pebruari'  },
        3:  { color: '#F4B183', name: 'Mar',  label: 'Maret'     },
        4:  { color: '#9DC3E6', name: 'Apr',  label: 'April'     },
        5:  { color: '#A9D08E', name: 'Mei',  label: 'Mei'       },
        6:  { color: '#00B0F0', name: 'Jun',  label: 'Juni'      },
        7:  { color: '#3B3838', name: 'Jul',  label: 'Juli'      },
        8:  { color: '#ED7D31', name: 'Agu',  label: 'Agustus'   },
        9:  { color: '#FF0000', name: 'Sep',  label: 'September' },
        10: { color: '#FF00FF', name: 'Okt',  label: 'Oktober'   },
        11: { color: '#C6E0B4', name: 'Nov',  label: 'November'  },
        12: { color: '#00FFFF', name: 'Des',  label: 'Desember'  }
    };

    let map = null;
    let drawnItems = null;
    let polygonLayers = [];
    let baseMapBounds = null;

    const fmt = (n) => { let v = parseFloat(n)||0; return v === 0 ? '-' : new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v); };

    function initMonthInputs() {
        const container = document.getElementById('month-inputs');
        const headerRow = document.getElementById('month-header-row');
        let inputsHTML = '', headerHTML = '';
        for (let i = 1; i <= 12; i++) {
            const m = MONTH_COLORS[i];
            inputsHTML += `<div>
                <div class="month-label" style="background:${m.color};color:white;margin-bottom:2px">${m.name}</div>
                <input type="number" step="0.01" min="0" name="bulan_${i}" id="input_b${i}" class="month-input" placeholder="0" oninput="calcTotal()">
            </div>`;
            headerHTML += `<th style="background:${m.color};color:white;font-size:9px">${m.name}</th>`;
        }
        container.innerHTML = inputsHTML;
        headerRow.innerHTML = headerHTML;
    }

    function calcTotal() {
        let total = 0;
        for (let i = 1; i <= 12; i++) total += parseFloat(document.getElementById('input_b'+i).value) || 0;
        document.getElementById('total_ha').textContent = fmt(total);
    }

    function updateColorFromInputs() {
        let primaryMonth = 1;
        for (let i = 1; i <= 12; i++) {
            let val = document.getElementById('input_b'+i) ? parseFloat(document.getElementById('input_b'+i).value) : 0;
            if (val > 0) {
                primaryMonth = i;
                break;
            }
        }
        let monthColor = MONTH_COLORS[primaryMonth].color;
        document.getElementById('input_warna').value = monthColor;
        
        if (map && map.pm) {
            map.pm.setPathOptions({ color: monthColor, fillColor: monthColor, weight: 2.5, fillOpacity: 0.4 });
        }

        if (drawnItems) {
            drawnItems.eachLayer(function(layer) {
                if (layer.setStyle) layer.setStyle({ color: monthColor, fillColor: monthColor, weight: 2.5, fillOpacity: 0.4 });
            });
        }
    }

    function setMonthValues(data) {
        for (let i = 1; i <= 12; i++) {
            document.getElementById('input_b'+i).value = parseFloat(data['bulan_'+i]) || '';
        }
        calcTotal();
    }

    function clearMonthValues() {
        for (let i = 1; i <= 12; i++) document.getElementById('input_b'+i).value = '';
        calcTotal();
    }

    function initMap(petaKerjaFoto) {
        if (map) { map.off(); map.remove(); }
        polygonLayers = [];
        baseMapBounds = null;
        drawnItems = new L.FeatureGroup();
        if (petaKerjaFoto) {
            map = L.map('map', {
                crs: L.CRS.Simple, minZoom: -3, maxZoom: 2,
                zoomControl: false, scrollWheelZoom: false,
                doubleClickZoom: false, touchZoom: false,
                boxZoom: false, keyboard: false, preferCanvas: true, zoomSnap: 0
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
                            let bounds = [[0,0],[viewport.height, viewport.width]];
                            L.imageOverlay(imgDataUrl, bounds).addTo(map);
                            map.fitBounds(bounds, { padding: [0,0] });
                            baseMapBounds = L.latLngBounds(bounds);
                            drawnItems.addTo(map);
                            attachDrawEvents();
                        });
                    });
                });
            } else {
                let img = new Image();
                img.src = fileUrl;
                img.onload = function() {
                    let bounds = [[0,0],[img.height, img.width]];
                    L.imageOverlay(fileUrl, bounds).addTo(map);
                    map.fitBounds(bounds, { padding: [0,0] });
                    baseMapBounds = L.latLngBounds(bounds);
                    drawnItems.addTo(map);
                    attachDrawEvents();
                }
            }
        } else {
            map = L.map('map', { preferCanvas: true, zoomControl: false }).setView([3.5952, 98.6722], 13);
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Esri', maxZoom: 19
            }).addTo(map);
            drawnItems.addTo(map);
            baseMapBounds = null;
            attachDrawEvents();
        }
    }

    function attachDrawEvents() {
        map.pm.addControls({
            position: 'topleft',
            drawPolygon: true, drawPolyline: true, drawCircle: false,
            drawRectangle: true, drawMarker: false, drawCircleMarker: false,
            editMode: true, dragMode: false, cutPolygon: false, removalMode: true,
            rotateMode: false
        });

        let selColor = document.getElementById('input_warna').value || '#0891b2';
        map.pm.setPathOptions({ color: selColor, fillColor: selColor, weight: 2.5, fillOpacity: 0.4 });

        map.on('pm:create', function(e) {
            drawnItems.addLayer(e.layer);
            document.getElementById('input_geojson').value = JSON.stringify(drawnItems.toGeoJSON());
            let bounds = drawnItems.getBounds();
            let center = bounds.isValid() ? bounds.getCenter() : (e.layer.getBounds ? e.layer.getBounds().getCenter() : e.layer.getLatLng());
            document.getElementById('input_lat').value = center.lat;
            document.getElementById('input_lng').value = center.lng;
            updateDrawStatus();
        });

        map.on('pm:remove', function(e) {
            drawnItems.removeLayer(e.layer);
            if (drawnItems.getLayers().length > 0) {
                document.getElementById('input_geojson').value = JSON.stringify(drawnItems.toGeoJSON());
            } else {
                document.getElementById('input_geojson').value = '';
                document.getElementById('input_lat').value = '';
                document.getElementById('input_lng').value = '';
            }
            updateDrawStatus();
        });
    }

    function updateDrawStatus() {
        const count = drawnItems ? drawnItems.getLayers().length : 0;
        const el = document.getElementById('status_gambar');
        if (count > 0) {
            el.className = 'status-badge status-filled';
            el.innerHTML = `<i class="ti ti-check"></i> ${count} Area Digambar`;
        } else {
            el.className = 'status-badge status-empty';
            el.innerHTML = '<i class="ti ti-x"></i> Belum Digambar';
        }
    }

    function clearDrawnItems() {
        if (drawnItems) drawnItems.clearLayers();
        document.getElementById('input_geojson').value = '';
        document.getElementById('input_lat').value = '';
        document.getElementById('input_lng').value = '';
        updateDrawStatus();
    }

    function changeYear() {
        activeYear = parseInt(document.getElementById('filter_tahun').value);
        document.getElementById('input_tahun').value = activeYear;
        cancelEdit();
        loadData();
    }

    async function loadData() {
        try {
            const res = await fetch(`be/pemetaan_api.php?action=get_mcs_bulanan_data&kebun_id=${KEBUN_ID}&unit_id=${UNIT_ID}&tahun=${activeYear}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Gagal memuat data');

            const info = json.info || {};
            document.getElementById('lbl-unit').textContent = `${info.nama_kebun||'KEBUN'} - ${info.nama_unit||'UNIT'}`;
            luasData = json.luas_data || {};
            updateLuasInput();

            const petaFoto = json.peta_kerja_foto;
            const uploadCard = document.getElementById('card_upload_peta');
            if (petaFoto) {
                uploadCard.classList.add('hidden');
            } else {
                uploadCard.classList.remove('hidden');
            }

            allData = json.data;
            initMap(petaFoto);
            loadJPDropdown();
            renderPolygons();
            renderTable();
        } catch(err) {
            console.error(err);
            document.getElementById('table-body').innerHTML = `<tr><td colspan="16" class="py-6 text-center text-red-500">${err.message}</td></tr>`;
        }
    }

    async function loadJPDropdown() {
        try {
            const res = await fetch(`be/pemetaan_api.php?action=get_jenis_pekerjaan_bulanan&kebun_id=${KEBUN_ID}`);
            const json = await res.json();
            if (!json.success) return;

            const filt = document.getElementById('filter_jp');
            const currentFilterVal = filt.value;
            filt.innerHTML = '<option value="">— Semua Pekerjaan —</option>';
            json.data.forEach(jp => { filt.innerHTML += `<option value="${jp.id}">${jp.nama}</option>`; });
            filt.value = currentFilterVal;
        } catch(e) { console.error('JP load error:', e); }
    }

    function renderPolygons() {
        polygonLayers.forEach(l => map.removeLayer(l));
        polygonLayers = [];

        const filterJP = document.getElementById('filter_jp').value;
        const filtered = filterJP ? allData.filter(d => d.jenis_pekerjaan_bulanan_id == filterJP) : [];

        filtered.forEach(row => {
            if (!row.geojson) return;
            try {
                let geojsonData = JSON.parse(row.geojson);
                if (geojsonData.type === 'FeatureCollection') {
                    geojsonData.features = geojsonData.features.filter(f =>
                        f.geometry && ['Polygon','MultiPolygon','LineString','MultiLineString'].includes(f.geometry.type)
                    );
                    if (geojsonData.features.length === 0) return;
                }

                let primaryMonth = 1;
                for (let i = 1; i <= 12; i++) {
                    if ((parseFloat(row['bulan_'+i]) || 0) > 0) { primaryMonth = i; break; }
                }
                let fillColor = MONTH_COLORS[primaryMonth].color;
                let total = 0;
                for (let i = 1; i <= 12; i++) total += parseFloat(row['bulan_'+i]) || 0;
                let fillOpacity = total > 0 ? 0.4 : 0.2;

                let layer = L.geoJSON(geojsonData, {
                    style: () => ({ color: fillColor, fillColor: fillColor, weight: 2.5, fillOpacity: fillOpacity }),
                    pointToLayer: (f, ll) => L.circleMarker(ll, { radius: 7, fillColor, color: '#fff', weight: 2, fillOpacity: 0.9 })
                });

                let bulanInfo = '';
                for (let i = 1; i <= 12; i++) {
                    let v = parseFloat(row['bulan_'+i]) || 0;
                    if (v > 0) {
                        let m = MONTH_COLORS[i];
                        bulanInfo += `<span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:${m.color};margin-right:2px"></span>${m.name}: <b>${fmt(v)}</b> Ha<br>`;
                    }
                }

                layer.bindPopup(`<div class="p-1" style="min-width:180px">
                    <div class="font-bold text-sm">${row.objek_pekerjaan || '-'}</div>
                    <div class="text-[11px] text-slate-500">${row.jp_nama || '-'}</div>
                    <div class="text-[10px] mt-1 border-t border-slate-200 pt-1">${bulanInfo || 'Belum ada data'}</div>
                    <div class="text-[10px] font-bold mt-1">Total: ${fmt(total)} Ha</div>
                </div>`).addTo(map);

                polygonLayers.push(layer);
            } catch(e) { console.error('GeoJSON parse error:', e); }
        });
        if (baseMapBounds) map.fitBounds(baseMapBounds, { padding: [0,0], animate: false });
    }

    function renderTable() {
        const filterJP = document.getElementById('filter_jp').value;
        const filtered = filterJP ? allData.filter(d => d.jenis_pekerjaan_bulanan_id == filterJP) : [];
        const tbody = document.getElementById('table-body');

        if (!filterJP) {
            tbody.innerHTML = '<tr><td colspan="16" class="py-8 text-center text-slate-400 italic">Silakan pilih Jenis Pekerjaan di atas untuk melihat data.</td></tr>';
            return;
        }

        if (!filtered.length) {
            tbody.innerHTML = '<tr><td colspan="16" class="py-8 text-center text-slate-400 italic">Belum ada data realisasi bulanan untuk pekerjaan ini.</td></tr>';
            return;
        }

        tbody.innerHTML = filtered.map(row => {
            let total = 0;
            let cells = '';
            for (let i = 1; i <= 12; i++) {
                let v = parseFloat(row['bulan_'+i]) || 0;
                total += v;
                let m = MONTH_COLORS[i];
                cells += `<td style="background:${v>0 ? m.color+'22' : ''};font-size:10px;font-weight:${v>0?'bold':'normal'}">${fmt(v)}</td>`;
            }
            return `<tr>
                <td class="text-left font-semibold text-[10px]">${row.objek_pekerjaan || '-'}</td>
                <td class="text-left text-[10px]">${row.jp_nama || '-'}</td>
                ${cells}
                <td class="font-bold text-cyan-700">${fmt(total)}</td>
                <td class="text-center">
                    <button onclick="editData(${row.id})" class="text-amber-500 hover:text-amber-700 mx-1" title="Edit"><i class="ti ti-edit text-lg"></i></button>
                    <button onclick="deleteData(${row.id})" class="text-red-500 hover:text-red-700 mx-1" title="Hapus"><i class="ti ti-trash text-lg"></i></button>
                </td>
            </tr>`;
        }).join('');
    }

    function filterByJP() { 
        document.getElementById('input_jp').value = document.getElementById('filter_jp').value;
        updateLuasInput();
        renderPolygons(); 
        renderTable(); 
    }

    function updateLuasInput() {
        const jpId = document.getElementById('filter_jp').value;
        const mappedId = jpId ? (100000 + parseInt(jpId)) : 99999;
        document.getElementById('input_luas').value = luasData[mappedId] || '';
    }

    function editData(id) {
        const row = allData.find(d => d.id == id);
        if (!row) return;
        document.getElementById('input_edit_id').value = row.id;
        document.getElementById('input_jp').value = row.jenis_pekerjaan_bulanan_id;
        document.getElementById('input_objek').value = row.objek_pekerjaan || '';
        document.getElementById('input_keterangan').value = row.keterangan || '';
        setMonthValues(row);

        if (row.geojson) {
            try {
                let geojson = JSON.parse(row.geojson);
                if (geojson.type === 'FeatureCollection') {
                    geojson.features = geojson.features.filter(f =>
                        f.geometry && ['Polygon','MultiPolygon','LineString','MultiLineString'].includes(f.geometry.type)
                    );
                }
                if (drawnItems) drawnItems.clearLayers();
                let geoLayer = L.geoJSON(geojson);
                geoLayer.eachLayer(l => drawnItems.addLayer(l));
                document.getElementById('input_geojson').value = JSON.stringify(geojson);
                document.getElementById('input_lat').value = row.latitude;
                document.getElementById('input_lng').value = row.longitude;
                updateColorFromInputs();
                updateDrawStatus();
            } catch(e) { console.error(e); }
        }

        document.querySelector('#form-mcs [name=action]').value = 'update_mcs_bulanan';
        document.getElementById('btn-cancel-edit').classList.remove('hidden');
        document.getElementById('btn-submit').innerHTML = '<i class="ti ti-device-floppy"></i> Update Data';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function cancelEdit() {
        document.getElementById('input_edit_id').value = '';
        document.getElementById('input_jp').value = '';
        document.getElementById('input_objek').value = '';
        document.getElementById('input_keterangan').value = '';
        clearMonthValues();
        clearDrawnItems();
        document.querySelector('#form-mcs [name=action]').value = 'save_mcs_bulanan';
        document.getElementById('btn-cancel-edit').classList.add('hidden');
        document.getElementById('btn-submit').innerHTML = '<i class="ti ti-cloud-upload"></i> Simpan Data';
    }

    async function deleteData(id) {
        const result = await Swal.fire({
            title: 'Hapus Data?', text: 'Data yang dihapus tidak bisa dikembalikan.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#e11d48',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        });
        if (!result.isConfirmed) return;

        try {
            const fd = new FormData();
            fd.append('action', 'delete_mcs_bulanan');
            fd.append('id', id);
            const res = await fetch('be/pemetaan_api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            Swal.fire('Berhasil!', json.message, 'success');
            loadData();
        } catch(err) {
            Swal.fire('Error', err.message, 'error');
        }
    }

    document.getElementById('form-upload-peta').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-upload-peta');
        btn.disabled = true; btn.textContent = 'Mengupload...';
        try {
            const fd = new FormData(this);
            const res = await fetch('be/pemetaan_api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            Swal.fire('Berhasil!', json.message, 'success');
            document.getElementById('card_upload_peta').classList.add('hidden');
            loadData();
        } catch(err) {
            Swal.fire('Error', err.message, 'error');
        }
        btn.disabled = false; btn.textContent = 'Upload & Pasang';
    });

    document.getElementById('form-mcs').addEventListener('submit', async function(e) {
        e.preventDefault();
        document.getElementById('input_jp').value = document.getElementById('filter_jp').value;
        
        if (!document.getElementById('input_jp').value) {
            Swal.fire('Perhatian', 'Silakan pilih Jenis Pekerjaan di menu filter atas terlebih dahulu sebelum menginput data.', 'warning');
            return;
        }

        const btn = document.getElementById('btn-submit');
        btn.disabled = true; btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Menyimpan...';
        try {
            const fd = new FormData(this);
            const res = await fetch('be/pemetaan_api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            Swal.fire('Berhasil!', json.message, 'success');
            cancelEdit();
            loadData();
        } catch(err) {
            Swal.fire('Error', err.message, 'error');
        }
        btn.disabled = false;
    });

    async function exportPDF() {
        const mapDiv = document.getElementById('map');
        if (!mapDiv) return;

        const selJp = document.getElementById('filter_jp');
        const originalJp = selJp.value;
        const jpOptions = [];
        
        // Hanya ambil jenis pekerjaan yang sudah ada datanya (sudah diarsir)
        const jpsWithData = new Set(allData.map(d => d.jenis_pekerjaan_bulanan_id.toString()));

        selJp.querySelectorAll('option').forEach(opt => {
            if (opt.value && jpsWithData.has(opt.value)) {
                jpOptions.push({ id: opt.value, nama: opt.textContent });
            }
        });

        if (jpOptions.length === 0) {
            Swal.fire('Perhatian', 'Belum ada data realisasi (arsir) yang bisa dicetak.', 'warning');
            return;
        }

        const mapImages = {};
        const totalJp = jpOptions.length;

        for (let i = 0; i < totalJp; i++) {
            const jp = jpOptions[i];
            const pct = Math.round(((i) / totalJp) * 100);

            Swal.fire({
                title: `Memproses Cetak (${pct}%)`,
                html: `<div style="margin-bottom:8px">Menangkap peta <b>${i + 1}/${totalJp}</b>: ${jp.nama}</div>
                       <div style="background:#e2e8f0;border-radius:999px;height:10px;overflow:hidden">
                         <div style="background:linear-gradient(90deg,#06b6d4,#8b5cf6);height:100%;width:${pct}%;transition:width .3s;border-radius:999px"></div>
                       </div>`,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            selJp.value = jp.id;
            filterByJP();
            
            await new Promise(resolve => setTimeout(resolve, 800));

            let allBounds = [];
            if (drawnItems && drawnItems.getBounds && drawnItems.getBounds().isValid()) {
                allBounds.push(drawnItems.getBounds());
            }
            if (baseMapBounds) allBounds.push(baseMapBounds);
            
            if (allBounds.length > 0) {
                let fb = allBounds[0];
                for(let j=1; j<allBounds.length; j++) fb.extend(allBounds[j]);
                map.fitBounds(fb, { padding: [10, 10], animate: false });
                await new Promise(resolve => setTimeout(resolve, 400));
            }

            const controls = document.querySelectorAll('.leaflet-control-container');
            controls.forEach(c => c.style.display = 'none');
            
            const oldW = mapDiv.style.width;
            const oldH = mapDiv.style.height;
            mapDiv.style.width = '1000px';
            mapDiv.style.height = '800px'; 
            map.invalidateSize();
            await new Promise(resolve => setTimeout(resolve, 400));

            try {
                let canvas = await html2canvas(mapDiv, { useCORS: true, allowTaint: true, scale: 1.1 });
                function trimCanvas(c) {
                    var ctx = c.getContext('2d', { willReadFrequently: true }), copy = document.createElement('canvas').getContext('2d'), pixels = ctx.getImageData(0, 0, c.width, c.height), bound = { top: null, left: null, right: null, bottom: null };
                    for (var k = 0; k < pixels.data.length; k += 4) {
                        if (pixels.data[k+3] !== 0 && !(pixels.data[k]>=250&&pixels.data[k+1]>=250&&pixels.data[k+2]>=250)) {
                            var x = (k / 4) % c.width, y = ~~((k / 4) / c.width);
                            if (bound.top === null) bound.top = y;
                            if (bound.left === null || x < bound.left) bound.left = x;
                            if (bound.right === null || x > bound.right) bound.right = x;
                            if (bound.bottom === null || y > bound.bottom) bound.bottom = y;
                        }
                    }
                    if (bound.top === null) return c;
                    var pad = 20;
                    bound.top = Math.max(0, bound.top - pad); bound.left = Math.max(0, bound.left - pad);
                    bound.bottom = Math.min(c.height, bound.bottom + pad); bound.right = Math.min(c.width, bound.right + pad);
                    var trimHeight = bound.bottom - bound.top, trimWidth = bound.right - bound.left;
                    var trimmed = ctx.getImageData(bound.left, bound.top, trimWidth, trimHeight);
                    copy.canvas.width = trimWidth; copy.canvas.height = trimHeight;
                    copy.putImageData(trimmed, 0, 0);
                    return copy.canvas;
                }
                canvas = trimCanvas(canvas);
                mapImages[jp.id] = canvas.toDataURL("image/jpeg", 0.5);
            } catch(e) { console.error('Screenshot error:', e); }

            mapDiv.style.width = oldW;
            mapDiv.style.height = oldH;
            map.invalidateSize();
            controls.forEach(c => c.style.display = '');
            if (baseMapBounds) map.fitBounds(baseMapBounds, { padding: [0,0], animate: false });
        }

        if (Object.keys(mapImages).length === 0) {
            Swal.fire('Error', 'Gagal memproses gambar peta.', 'error');
            return;
        }

        Swal.fire({
            title: 'Merender PDF...',
            html: '<div style="margin-bottom:8px">Mohon tunggu, menyusun dokumen...</div><div style="background:#e2e8f0;border-radius:999px;height:10px;overflow:hidden"><div style="background:linear-gradient(90deg,#0891b2,#06b6d4);height:100%;width:10%;border-radius:999px;animation:pdfProg 4s ease-in-out infinite"></div></div><style>@keyframes pdfProg{0%{width:10%}50%{width:70%}100%{width:95%}}</style>',
            allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading()
        });

        try {
            const fd = new FormData();
            fd.append('unit_id', UNIT_ID);
            fd.append('kebun_id', KEBUN_ID);
            fd.append('tahun', activeYear);
            fd.append('mcs_data', JSON.stringify(allData));
            fd.append('luas_data', JSON.stringify(luasData));
            Object.entries(mapImages).forEach(([jpId, img]) => fd.append(`map_images[${jpId}]`, img));

            const res = await fetch('cetak/peta_rekap_gabungan_pdf.php', { method: 'POST', body: fd });
            if (!res.ok) throw new Error(await res.text());
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
            Swal.close();
        } catch(err) {
            Swal.fire({ icon: 'error', title: 'Gagal', html: `<div style="text-align:left;font-size:12px;background:#fee2e2;padding:10px;border-radius:8px;color:#991b1b;max-height:150px;overflow:auto"><b>Error:</b><br>${err.message}</div>` });
        }
    }

    // === SAVE LUAS STATIK ===
    async function saveLuas() {
        const luas = document.getElementById('input_luas').value;
        const jpId = document.getElementById('filter_jp').value;
        const fd = new FormData();
        fd.append('action', 'save_luas_peta_dasar');
        fd.append('unit_id', UNIT_ID);
        fd.append('jp_id', jpId);
        fd.append('luas_ha', luas);
        
        try {
            const res = await fetch('be/pemetaan_api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                Toast.fire({ icon: 'success', title: 'Luas berhasil disimpan' });
                // Update local memory
                const mappedId = jpId ? (100000 + parseInt(jpId)) : 99999;
                luasData[mappedId] = luas;
            } else {
                Swal.fire('Gagal', json.message, 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Gagal menyimpan luas', 'error');
        }
    }

    // === INIT ===
    initMonthInputs();
    updateColorFromInputs();
    document.addEventListener('DOMContentLoaded', loadData);
</script>

<?php include_once '../layouts/footer.php'; ?>
