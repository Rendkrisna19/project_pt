<?php
session_start();
if (!isset($_SESSION['loggedin'])) { header("Location: ../auth/login.php"); exit; }

$unit_id = $_GET['unit_id'] ?? 0;
$kebun_id = $_GET['kebun_id'] ?? 0;

if(!$unit_id || !$kebun_id) { header("Location: pemetaan_pilih_unit.php"); exit; }

$currentPage = 'pemetaan'; 
include_once '../layouts/header.php'; 
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css" />
<script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<style>
    :root { --cyan-main: #0891b2; }
    body { background-color: #f1f5f9; }
    #map { height: calc(100vh - 140px); border-radius: 15px; border: 3px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 10; }
    .leaflet-container { background-color: #ffffff !important; }
    
    .card-glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(8, 145, 178, 0.1); }
    .input-custom { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; outline: none; }
    .input-custom:focus { border-color: var(--cyan-main); box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1); }
    
    .btn-cyan { background-color: var(--cyan-main); color: white; font-weight: 700; padding: 12px; border-radius: 10px; transition: all 0.3s; display: flex; justify-content: center; align-items: center; gap: 8px; }
    .btn-cyan:hover { background-color: #0e7490; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3); }

    .color-radio { width: 26px; height: 26px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: 0.2s; appearance: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .color-radio:checked { border-color: #1e293b; transform: scale(1.2); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }

    .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: bold; transition: 0.3s; }
    .status-empty { background: #fee2e2; color: #e11d48; }
    .status-filled { background: #dcfce3; color: #059669; }

    /* GPS Animation */
    .gps-dot { background: #2196F3; border: 3px solid #fff; border-radius: 50%; box-shadow: 0 0 5px rgba(0,0,0,0.5); }
    .gps-pulse { width: 20px; height: 20px; background: rgba(33, 150, 243, 0.4); border-radius: 50%; position: absolute; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(3); opacity: 0; } }
</style>

<div class="p-4 md:p-6 min-h-screen bg-slate-50">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div class="flex items-center gap-3">
            <a href="pemetaan_pilih_unit.php" class="bg-white p-2.5 rounded-xl border border-slate-200 text-slate-500 shadow-sm hover:text-cyan-600 transition">
                <i class="ti ti-chevron-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Geo-Mapping</h1>
                <p class="text-xs font-bold text-cyan-600 uppercase tracking-widest">Sistem Informasi Geografis Kebun</p>
            </div>
        </div>

        <div class="flex gap-2 items-center">
            <select id="filter_jenis_pekerjaan" class="input-custom min-w-[200px] border-cyan-300 font-bold text-slate-700" onchange="loadSavedPoints()">
                <option value="">— Semua Pekerjaan —</option>
            </select>

            <button onclick="exportMap('pdf')" class="bg-gradient-to-r from-emerald-500 to-emerald-600 text-white border border-emerald-600 px-4 py-2 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center gap-2">
                <i class="ti ti-file-export"></i> Export PDF
            </button>
            <button onclick="exportMap('excel')" class="bg-gradient-to-r from-green-500 to-green-600 text-white border border-green-600 px-4 py-2 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center gap-2">
                <i class="ti ti-file-spreadsheet"></i> Export Excel
            </button>
            <button onclick="detectMyLocation()" class="bg-white text-cyan-600 border border-cyan-200 px-4 py-2 rounded-xl text-sm font-bold shadow-sm hover:bg-cyan-50 transition flex items-center gap-2">
                <i class="ti ti-gps"></i> Temukan Saya (GPS)
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <div class="lg:col-span-1 space-y-4">
            
            <!-- Form Upload Peta Dasar -->
            <div id="card_upload_peta" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-fuchsia-500 hidden">
                <h3 class="font-extrabold text-slate-800 mb-2 flex items-center gap-2">
                    <i class="ti ti-photo-plus text-fuchsia-500 text-xl"></i> Peta Dasar (Base Map)
                </h3>
                <p class="text-xs text-slate-500 mb-4">Upload gambar denah/Peta Kerja untuk dijadikan alas peta.</p>
                <form id="form-upload-peta">
                    <input type="hidden" name="action" value="upload_peta_dasar">
                    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
                    <input type="hidden" name="jenis_pekerjaan_id" id="upload_jp_id" value="">
                    <input type="file" name="peta_dasar" accept=".jpg,.jpeg,.png,.webp" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-fuchsia-50 file:text-fuchsia-700 hover:file:bg-fuchsia-100 cursor-pointer mb-2" required>
                    <button type="submit" id="btn-upload-peta" class="w-full bg-fuchsia-500 text-white font-bold py-2 rounded-lg hover:bg-fuchsia-600 text-sm shadow-md transition">Upload & Pasang Peta</button>
                </form>
            </div>

            <div class="card-glass p-6 rounded-2xl shadow-sm border-t-4 border-cyan-600 h-fit">
                <h3 class="font-extrabold text-slate-800 mb-5 flex items-center gap-2">
                    <i class="ti ti-pencil text-cyan-600 text-xl"></i> Markup Lahan
                </h3>

                <form id="form-pemetaan" class="space-y-4">
                    <input type="hidden" name="action" value="save_map_data">
                    <input type="hidden" name="id" id="input_edit_id" value="">
                    
                    <input type="hidden" name="kebun_id" value="<?= $kebun_id ?>">
                    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
                    <input type="hidden" name="jenis_pekerjaan_id" id="input_jp_id">
                    <input type="hidden" name="geojson" id="input_geojson">

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5 ml-1">Blok (Master Data)</label>
                        <select name="blok_id" id="select_blok" class="input-custom cursor-pointer" required>
                            <option value="">— Memuat Blok... —</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5 ml-1">Jenis Aset / Lahan</label>
                        <select name="jenis_aset" class="input-custom cursor-pointer" required>
                            <option value="">— Pilih Kategori —</option>
                            <option value="Area Kebun">Area Lahan Kebun</option>
                            <option value="Jalan Utama">Jalan Utama</option>
                            <option value="Jalan Produksi">Jalan Produksi</option>
                            <option value="Parit/Drainase">Parit / Drainase</option>
                            <option value="Jembatan">Jembatan / Titi</option>
                            <option value="Bangunan">Bangunan / Kantor</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Warna Poligon/Garis</label>
                        <div class="flex flex-wrap gap-2 px-1 items-center">
                            <input type="radio" name="warna" value="#0891b2" class="color-radio" style="background-color: #0891b2;" checked title="Cyan">
                            <input type="radio" name="warna" value="#e11d48" class="color-radio" style="background-color: #e11d48;" title="Merah">
                            <input type="radio" name="warna" value="#10b981" class="color-radio" style="background-color: #10b981;" title="Hijau">
                            <input type="radio" name="warna" value="#f59e0b" class="color-radio" style="background-color: #f59e0b;" title="Kuning">
                            <input type="radio" name="warna" value="#6366f1" class="color-radio" style="background-color: #6366f1;" title="Ungu">
                            <input type="radio" name="warna" value="#ec4899" class="color-radio" style="background-color: #ec4899;" title="Pink">
                            <input type="radio" name="warna" value="#3b82f6" class="color-radio" style="background-color: #3b82f6;" title="Biru">
                            <input type="radio" name="warna" value="#14b8a6" class="color-radio" style="background-color: #14b8a6;" title="Teal">
                            <div class="flex items-center gap-1 border border-slate-200 p-0.5 rounded-lg ml-1">
                                <input type="radio" name="warna" value="#000000" id="radio_custom_color" class="color-radio hidden">
                                <label for="radio_custom_color" class="cursor-pointer text-[9px] font-bold text-slate-500 ml-1">Custom:</label>
                                <input type="color" id="input_custom_color" class="w-5 h-5 p-0 border-0 rounded cursor-pointer" value="#000000" title="Pilih Warna Bebas">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Lat Pusat</label>
                            <input type="text" name="latitude" id="input_lat" class="input-custom bg-slate-50 font-mono text-[10px]" readonly required placeholder="Auto">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Lng Pusat</label>
                            <input type="text" name="longitude" id="input_lng" class="input-custom bg-slate-50 font-mono text-[10px]" readonly required placeholder="Auto">
                        </div>
                    </div>

                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-center">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Data Spasial Map</label>
                        <div id="status_gambar" class="status-badge status-empty">
                            <i class="ti ti-x"></i> Belum Digambar
                        </div>
                        <p class="text-[9px] text-slate-400 mt-2">Bisa gambar >1 Poligon/Garis. Gunakan tools di kiri atas peta.</p>
                        <button type="button" onclick="clearDrawnItems()" class="mt-2 text-[10px] font-bold text-red-500 hover:underline">Hapus Semua Gambar Baru</button>
                    </div>

                    <!-- DATA REALISASI HARIAN -->
                    <div class="bg-cyan-50/50 p-3 rounded-xl border border-cyan-100 space-y-3">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="ti ti-clipboard-data text-cyan-600"></i>
                            <h4 class="text-[11px] font-extrabold text-slate-700 uppercase">Input Realisasi</h4>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Tgl Pengerjaan</label>
                            <input type="date" name="tanggal_realisasi" value="<?= date('Y-m-d') ?>" class="input-custom text-xs">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Fisik (H. Ini)</label>
                                <input type="number" step="0.01" name="fisik_hari_ini" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Fisik (S/D)</label>
                                <input type="number" step="0.01" name="fisik_sd" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">HK (H. Ini)</label>
                                <input type="number" step="0.01" name="hk_hari_ini" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">HK (S/D)</label>
                                <input type="number" step="0.01" name="hk_sd" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Kimia (H. Ini)</label>
                                <input type="number" step="0.01" name="bahan_kimia_hari_ini" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Kimia (S/D)</label>
                                <input type="number" step="0.01" name="bahan_kimia_sd" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Campuran (H. Ini)</label>
                                <input type="number" step="0.01" name="campuran_hari_ini" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Campuran (S/D)</label>
                                <input type="number" step="0.01" name="campuran_sd" class="input-custom text-xs" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5 ml-1">Keterangan Tambahan</label>
                        <textarea name="keterangan" class="input-custom" rows="2" placeholder="Catatan kondisi area..."></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5 ml-1">Lampiran Foto (Opsional)</label>
                        <input type="file" name="foto" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer">
                    </div>

                    <button type="submit" id="btn-submit" class="w-full btn-cyan shadow-lg shadow-cyan-200 mt-2">
                        <i class="ti ti-cloud-upload"></i> Simpan Data GIS
                    </button>
                </form>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <h4 class="text-xs font-extrabold text-slate-600 uppercase mb-4 text-center tracking-wider">Distribusi Aset Unit</h4>
                <div class="relative w-full aspect-square max-h-[220px] mx-auto">
                    <canvas id="assetChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 border-t-4 border-cyan-500">
                <h4 class="text-xs font-extrabold text-slate-600 uppercase mb-4 text-center tracking-wider">Panduan Pemetaan</h4>
                <ul class="text-xs text-slate-600 space-y-3">
                    <li class="flex gap-2 items-start">
                        <i class="ti ti-map-2 text-cyan-600 mt-0.5 text-lg"></i>
                        <span><strong class="text-slate-800 block">Peta Kerja</strong> Peta ini yang di tampilakan untuk kerja.</span>
                    </li>
                    <li class="flex gap-2 items-start">
                        <i class="ti ti-edit text-cyan-600 mt-0.5 text-lg"></i>
                        <span><strong class="text-slate-800 block">Markup Lahan</strong> Form ini juga buat kerja.</span>
                    </li>
                    <li class="flex gap-2 items-start">
                        <i class="ti ti-file-export text-cyan-600 mt-0.5 text-lg"></i>
                        <span><strong class="text-slate-800 block">Hasil Export</strong> Waktu export hasilnya bisa kayak gini peta udah di arsir dan udah terisi realisasi blok nya.</span>
                    </li>
                    <li class="flex gap-2 items-start">
                        <i class="ti ti-tools text-cyan-600 mt-0.5 text-lg"></i>
                        <span><strong class="text-slate-800 block">Alat Arsir</strong> Gunakan alat di kiri peta untuk arsir area.</span>
                    </li>
                </ul>
            </div>

        </div>

        <div class="lg:col-span-3 space-y-4">
            <div id="map"></div>

            <!-- Tabel Realisasi Pekerjaan -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200" id="card_table_realisasi">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-sm font-black text-slate-700 uppercase tracking-wider">
                        <i class="ti ti-table text-cyan-600 mr-1"></i> Data Realisasi Lahan
                    </h4>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-left border-collapse text-[11px] whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                <th rowspan="2" class="px-3 py-2 border-r border-slate-200 align-middle text-center">TANGGAL</th>
                                <th rowspan="2" class="px-3 py-2 border-r border-slate-200 align-middle text-center">BLOK</th>
                                <th colspan="2" class="px-3 py-1.5 border-r border-b border-slate-200 text-center bg-cyan-50/50">Fisik (Ha, Pkk)</th>
                                <th colspan="2" class="px-3 py-1.5 border-r border-b border-slate-200 text-center bg-teal-50/50">HK</th>
                                <th colspan="2" class="px-3 py-1.5 border-r border-b border-slate-200 text-center bg-amber-50/50">Bahan Kimia</th>
                                <th colspan="2" class="px-3 py-1.5 border-r border-b border-slate-200 text-center bg-rose-50/50">Campuran</th>
                                <th rowspan="2" class="px-3 py-2 border-slate-200 align-middle text-center">AKSI</th>
                            </tr>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] text-slate-500 font-bold text-center">
                                <th class="px-2 py-1.5 border-r border-slate-200 bg-cyan-50/50">H. INI</th>
                                <th class="px-2 py-1.5 border-r border-slate-200 bg-cyan-50/50">S/D</th>
                                <th class="px-2 py-1.5 border-r border-slate-200 bg-teal-50/50">H. INI</th>
                                <th class="px-2 py-1.5 border-r border-slate-200 bg-teal-50/50">S/D</th>
                                <th class="px-2 py-1.5 border-r border-slate-200 bg-amber-50/50">H. INI</th>
                                <th class="px-2 py-1.5 border-r border-slate-200 bg-amber-50/50">S/D</th>
                                <th class="px-2 py-1.5 border-r border-slate-200 bg-rose-50/50">H. INI</th>
                                <th class="px-2 py-1.5 border-r border-slate-200 bg-rose-50/50">S/D</th>
                            </tr>
                        </thead>
                        <tbody id="table-realisasi-body" class="divide-y divide-slate-100 text-slate-700">
                            <!-- Data dimuat lewat AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    let map = null;
    let drawnItems = null;
    let myLocationMarker = null;
    let myChart = null;

    // --- 1. INISIALISASI PETA (DINAMIS) ---
    function initMap(petaKerjaFoto) {
        if(map) {
            map.off();
            map.remove();
        }
        
        if (petaKerjaFoto) {
            // MODE: GAMBAR / DENAH KERJA (CRS SIMPLE)
            map = L.map('map', {
                crs: L.CRS.Simple,
                minZoom: -3,
                maxZoom: 2,
                zoomControl: true,
                preferCanvas: true
            });
            let imgUrl = `../uploads/pemetaan/base_map/${petaKerjaFoto}`;
            let img = new Image();
            img.src = imgUrl;
            img.onload = function() {
                let w = img.width, h = img.height;
                let bounds = [[0, 0], [h, w]];
                L.imageOverlay(imgUrl, bounds).addTo(map);
                map.fitBounds(bounds);
            }
        } else {
            // MODE: SATELIT GPS DEFAULT
            map = L.map('map', { preferCanvas: true }).setView([3.5952, 98.6722], 13); 
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri',
                maxZoom: 19
            }).addTo(map);
        }

        drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        // SETUP LEAFLET-GEOMAN (TOOLBAR DRAWING)
        map.pm.addControls({
            position: 'topleft',
            drawCircleMarker: false,
            drawCircle: false,
            drawText: false,
            cutPolygon: false,
            editMode: true,
            dragMode: true,
            removalMode: true 
        });

        map.on('pm:create', function(e) {
            drawnItems.addLayer(e.layer);
            updateGeoJsonStatus();
            applyColorToDrawings();
        });

        map.on('pm:remove', function(e) {
            drawnItems.removeLayer(e.layer);
            updateGeoJsonStatus();
        });
    }

    function updateGeoJsonStatus() {
        const geoData = drawnItems.toGeoJSON();
        const numItems = geoData.features.length;
        const statusEl = document.getElementById('status_gambar');
        
        if(numItems > 0) {
            document.getElementById('input_geojson').value = JSON.stringify(geoData);
            statusEl.className = 'status-badge status-filled';
            statusEl.innerHTML = `<i class="ti ti-check"></i> ${numItems} Markup Siap Disimpan`;

            const bounds = drawnItems.getBounds();
            const center = bounds.getCenter();
            document.getElementById('input_lat').value = center.lat.toFixed(8);
            document.getElementById('input_lng').value = center.lng.toFixed(8);

        } else {
            document.getElementById('input_geojson').value = '';
            document.getElementById('input_lat').value = '';
            document.getElementById('input_lng').value = '';
            statusEl.className = 'status-badge status-empty';
            statusEl.innerHTML = '<i class="ti ti-x"></i> Belum Digambar';
        }
    }

    function clearDrawnItems() {
        drawnItems.clearLayers();
        updateGeoJsonStatus();
    }

    document.querySelectorAll('.color-radio').forEach(radio => {
        radio.addEventListener('change', applyColorToDrawings);
    });

    document.getElementById('input_custom_color').addEventListener('input', function(e) {
        let color = e.target.value;
        let radioCustom = document.getElementById('radio_custom_color');
        radioCustom.value = color;
        radioCustom.checked = true;
        // trigger event change to apply color
        applyColorToDrawings();
    });

    function applyColorToDrawings() {
        if(!drawnItems) return;
        let selectedColor = document.querySelector('input[name="warna"]:checked').value;
        drawnItems.eachLayer(function(layer) {
            if(layer.setStyle) {
                layer.setStyle({ color: selectedColor, fillColor: selectedColor, weight: 3, fillOpacity: 0.5 });
            }
        });
        if(map && map.pm) {
            map.pm.setPathOptions({ color: selectedColor, fillColor: selectedColor, weight: 3, fillOpacity: 0.5 });
        }
    }

    // --- 3. AMBIL MASTER DATA BLOK & PEKERJAAN ---
    async function loadBloks() {
        try {
            const unitId = "<?= $unit_id ?>";
            const res = await fetch(`be/pemetaan_api.php?action=get_bloks&unit_id=${unitId}`);
            const json = await res.json();
            
            const sel = document.getElementById('select_blok');
            sel.innerHTML = '<option value="">— Pilih Blok —</option>';
            
            if(json.success && json.data.length > 0) {
                json.data.forEach(b => { 
                    sel.innerHTML += `<option value="${b.id}">${b.kode}</option>`; 
                });
            } else {
                sel.innerHTML = '<option value="">— Tidak ada blok ditemukan —</option>';
            }

            // Load Jenis Pekerjaan
            const resJp = await fetch(`be/pemetaan_api.php?action=get_jenis_pekerjaan`);
            const jsonJp = await resJp.json();
            const selJp = document.getElementById('filter_jenis_pekerjaan');
            if(jsonJp.success && jsonJp.data.length > 0) {
                jsonJp.data.forEach(jp => { 
                    selJp.innerHTML += `<option value="${jp.id}">${jp.nama}</option>`; 
                });
            }
        } catch(e) { console.error("Kesalahan Fetch Data Master:", e); }
    }

    // --- 4. DETEKSI LOKASI GPS ---
    function detectMyLocation() {
        if (!navigator.geolocation) { Swal.fire('Error', 'Browser tidak mendukung GPS', 'error'); return; }

        Swal.fire({ title: 'Mendeteksi Lokasi...', icon: 'info', showConfirmButton: false, allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

        navigator.geolocation.getCurrentPosition((position) => {
            Swal.close();
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            map.flyTo([lat, lng], 17);

            if (myLocationMarker) map.removeLayer(myLocationMarker);
            let customIcon = L.divIcon({
                className: 'custom-div-icon',
                html: '<div class="gps-pulse"></div><div class="gps-dot" style="width:12px; height:12px;"></div>',
                iconSize: [20, 20], iconAnchor: [10, 10]
            });
            myLocationMarker = L.marker([lat, lng], {icon: customIcon}).addTo(map).bindPopup("<b class='text-cyan-600'>Lokasi Anda</b>").openPopup();
        }, (error) => { Swal.close(); Swal.fire('Gagal', 'Pastikan GPS aktif.', 'error'); }, { enableHighAccuracy: true });
    }

    // --- 5. LOAD DATA TERSIMPAN ---
    async function loadSavedPoints() {
        const kebun_id = "<?= $kebun_id ?>";
        const unit_id = "<?= $unit_id ?>";
        const jp_id = document.getElementById('filter_jenis_pekerjaan').value;

        // Update hidden input untuk submit
        document.getElementById('input_jp_id').value = jp_id;
        document.getElementById('upload_jp_id').value = jp_id;

        // Reset peta
        if (drawnItems) drawnItems.clearLayers();

        if(jp_id === "") {
            document.getElementById('card_upload_peta').classList.add('hidden');
        }

        try {
            const response = await fetch(`be/pemetaan_api.php?action=get_map_data&kebun_id=${kebun_id}&unit_id=${unit_id}&jenis_pekerjaan_id=${jp_id}`);
            const res = await response.json();

            if(res.success) {
                // Inisialisasi peta berdasarkan foto base map
                initMap(res.peta_kerja_foto);
                applyColorToDrawings();
                
                // Tampilkan form upload base map JIKA JP Dipilih (bisa upload berkali-kali)
                if(jp_id !== "") {
                    document.getElementById('card_upload_peta').classList.remove('hidden');
                    let btnText = res.peta_kerja_foto ? 'Ganti Peta Dasar (Timpa)' : 'Upload & Pasang Peta';
                    document.getElementById('btn-upload-peta').innerText = btnText;
                } else {
                    document.getElementById('card_upload_peta').classList.add('hidden');
                }

                let tbody = document.getElementById('table-realisasi-body');
                if(tbody) tbody.innerHTML = '';
                
                let bounds = [];

                if (res.data.length === 0 && tbody) {
                    tbody.innerHTML = `<tr><td colspan="11" class="px-3 py-4 text-center text-slate-500 italic">Belum ada data realisasi.</td></tr>`;
                }

                res.data.forEach(titik => {
                    if(tbody) {
                        let tr = document.createElement('tr');
                        tr.className = "hover:bg-slate-50 transition";
                        const fmt = (num) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0);
                        const tgl = titik.tanggal_realisasi ? titik.tanggal_realisasi.split('-').reverse().join('-') : '-';
                        
                        // Menghindari issue kutip di JSON.stringify
                        let safeJson = JSON.stringify(titik).replace(/'/g, "&#39;").replace(/"/g, "&quot;");

                        tr.innerHTML = `
                            <td class="px-3 py-2 border-r border-slate-200 text-center">${tgl}</td>
                            <td class="px-3 py-2 border-r border-slate-200 text-center font-bold">${titik.nama_blok || '-'}</td>
                            <td class="px-2 py-2 border-r border-slate-200 text-right font-mono">${fmt(titik.fisik_hari_ini)}</td>
                            <td class="px-2 py-2 border-r border-slate-200 text-right font-mono">${fmt(titik.fisik_sd)}</td>
                            <td class="px-2 py-2 border-r border-slate-200 text-right font-mono">${fmt(titik.hk_hari_ini)}</td>
                            <td class="px-2 py-2 border-r border-slate-200 text-right font-mono">${fmt(titik.hk_sd)}</td>
                            <td class="px-2 py-2 border-r border-slate-200 text-right font-mono">${fmt(titik.bahan_kimia_hari_ini)}</td>
                            <td class="px-2 py-2 border-r border-slate-200 text-right font-mono">${fmt(titik.bahan_kimia_sd)}</td>
                            <td class="px-2 py-2 border-r border-slate-200 text-right font-mono">${fmt(titik.campuran_hari_ini)}</td>
                            <td class="px-2 py-2 border-slate-200 text-right font-mono">${fmt(titik.campuran_sd)}</td>
                            <td class="px-3 py-2 text-center border-l border-slate-200">
                                <button type="button" onclick="editMapData(${safeJson})" class="bg-amber-100 text-amber-600 hover:bg-amber-500 hover:text-white px-2 py-1 rounded shadow-sm text-xs transition" title="Edit">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <button type="button" onclick="deleteMapData(${titik.id})" class="bg-rose-100 text-rose-600 hover:bg-rose-500 hover:text-white px-2 py-1 rounded shadow-sm text-xs transition" title="Hapus">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    }

                    if(!titik.geojson) return;

                    let geojsonData = JSON.parse(titik.geojson);
                    let imgHtml = titik.foto ? `<img src="../uploads/pemetaan/${titik.foto}" class="w-full h-24 object-cover mt-2 rounded-lg">` : '';
                    let popupContent = `
                        <div class="p-1">
                            <div class="text-[10px] font-black uppercase mb-1" style="color:${titik.warna}">${titik.jenis_aset}</div>
                            <div class="font-bold text-slate-800 text-sm mb-1">${titik.nama_blok}</div>
                            <div class="text-[11px] text-slate-500">${titik.keterangan || '-'}</div>
                            ${imgHtml}
                        </div>
                    `;

                    let layer = L.geoJSON(geojsonData, {
                        style: function (feature) {
                            return { color: titik.warna, fillColor: titik.warna, weight: 3, fillOpacity: 0.4 };
                        },
                        pointToLayer: function (feature, latlng) {
                            return L.circleMarker(latlng, { radius: 7, fillColor: titik.warna, color: "#fff", weight: 2, fillOpacity: 0.9 });
                        }
                    }).bindPopup(popupContent).addTo(map);

                    let layerBounds = layer.getBounds ? layer.getBounds() : null;
                    if (layerBounds && layerBounds.isValid()) bounds.push(layerBounds);
                });

                if(bounds.length > 0) {
                    let mapBounds = bounds[0];
                    for(let i=1; i<bounds.length; i++) { mapBounds.extend(bounds[i]); }
                    
                    // Delay sedikit agar image overlay termuat jika peta berupa gambar
                    setTimeout(() => {
                        if(map) map.fitBounds(mapBounds, { padding: [50, 50] });
                    }, 500);
                }

                renderChart(res.stats);
            }
        } catch (err) { console.error(err); }
    }

    // --- 6. RENDER CHART JS ---
    function renderChart(statsData) {
        const ctx = document.getElementById('assetChart').getContext('2d');
        if(myChart) myChart.destroy(); 
        
        const colors = ['#0891b2', '#e11d48', '#10b981', '#f59e0b', '#6366f1', '#8b5cf6'];
        
        myChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: statsData.map(s => s.label),
                datasets: [{
                    data: statsData.map(s => s.value),
                    backgroundColor: colors.slice(0, statsData.length),
                    borderWidth: 2, borderColor: '#ffffff'
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15, font: { size: 10, family: 'Poppins' } } } },
                cutout: '65%'
            }
        });
    }

    // --- 7. HANDLE SUBMIT FORM ---
    document.getElementById('form-pemetaan').addEventListener('submit', async function(e) {
        e.preventDefault();

        if(document.getElementById('input_geojson').value === '') {
            Swal.fire('Peringatan!', 'Anda belum menggambar Poligon / Garis di peta.', 'warning');
            return;
        }

        if(document.getElementById('filter_jenis_pekerjaan').value === '') {
            Swal.fire('Peringatan!', 'Pilih "Jenis Pekerjaan" pada dropdown di atas tabel terlebih dahulu sebelum menyimpan data pemetaan.', 'warning');
            return;
        }

        let btn = document.getElementById('btn-submit');
        btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Menyimpan...';
        btn.disabled = true;

        let formData = new FormData(this);

        try {
            const response = await fetch('be/pemetaan_api.php', { method: 'POST', body: formData });
            const rawText = await response.text();
            
            let res;
            try {
                res = JSON.parse(rawText);
            } catch (parseError) {
                console.error("RAW ERROR DARI PHP:", rawText);
                Swal.fire({
                    icon: 'error',
                    title: 'System Error!',
                    html: `<div style="text-align:left; background:#f8f9fa; padding:10px; border-radius:8px; border:1px solid #dc3545; color:#dc3545; font-family:monospace; font-size:11px; max-height:200px; overflow-y:auto;">
                            ${rawText}
                           </div>`,
                    width: '600px'
                });
                btn.innerHTML = '<i class="ti ti-cloud-upload"></i> Simpan Data GIS';
                btn.disabled = false;
                return; 
            }

            if(res.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 1500, showConfirmButton: false });
                this.reset();
                
                // Kembalikan form ke mode tambah
                document.getElementById('input_edit_id').value = '';
                let btnSubmit = document.getElementById('btn-submit');
                btnSubmit.innerHTML = '<i class="ti ti-cloud-upload"></i> Simpan Data GIS';
                btnSubmit.classList.remove('bg-amber-500', 'hover:bg-amber-600', 'shadow-amber-200');
                btnSubmit.classList.add('btn-cyan', 'shadow-cyan-200');

                clearDrawnItems();
                loadSavedPoints();
                applyColorToDrawings(); 
            } else {
                let errorHtml = `<b class="text-slate-800">${res.message}</b>`;
                if (res.detail) {
                    errorHtml += `<br><br><div style="text-align:left; background:#fee2e2; padding:10px; border-radius:8px; font-size:12px; color:#991b1b; font-family:monospace; border: 1px solid #fca5a5;">
                                    <b>Detail Error:</b><br>${res.detail}
                                  </div>`;
                }
                Swal.fire({ icon: 'error', title: 'Gagal Tersimpan!', html: errorHtml });
            }

        } catch(err) {
            Swal.fire('Error Koneksi!', 'Gagal menghubungi server.', 'error');
        } finally {
            btn.innerHTML = '<i class="ti ti-cloud-upload"></i> Simpan Data GIS';
            btn.disabled = false;
        }
    });

    // --- 8. HANDLE SUBMIT PETA DASAR ---
    document.getElementById('form-upload-peta').addEventListener('submit', async function(e) {
        e.preventDefault();
        let btn = document.getElementById('btn-upload-peta');
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Mengupload...';
        btn.disabled = true;

        let formData = new FormData(this);
        try {
            const response = await fetch('be/pemetaan_api.php', { method: 'POST', body: formData });
            const res = await response.json();
            
            if(res.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 1500, showConfirmButton: false });
                loadSavedPoints(); // Reload peta
            } else {
                let errorHtml = `<b class="text-slate-800">${res.message}</b>`;
                if (res.detail) {
                    errorHtml += `<br><br><div style="text-align:left; background:#fee2e2; padding:10px; border-radius:8px; font-size:12px; color:#991b1b; font-family:monospace; border: 1px solid #fca5a5;">
                                    <b>Detail Error:</b><br>${res.detail}
                                  </div>`;
                }
                Swal.fire({ icon: 'error', title: 'Gagal!', html: errorHtml });
            }
        } catch(err) {
            Swal.fire('Error', 'Gagal mengupload peta.', 'error');
            btn.innerHTML = originalText;
        } finally {
            btn.disabled = false;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadBloks();
        loadSavedPoints();
    });

    // --- FUNGSI EDIT DATA ---
    window.editMapData = function(titik) {
        document.getElementById('input_edit_id').value = titik.id;
        document.getElementById('select_blok').value = titik.blok_id;
        document.querySelector(`select[name="jenis_aset"]`).value = titik.jenis_aset;
        document.getElementById('input_lat').value = titik.latitude;
        document.getElementById('input_lng').value = titik.longitude;
        document.getElementById('input_geojson').value = titik.geojson;
        
        let colorFound = false;
        document.querySelectorAll('input[name="warna"]').forEach(r => {
            if(r.value === titik.warna) {
                r.checked = true;
                colorFound = true;
            }
        });

        // Jika warna tidak ada di opsi default, set ke custom
        if (!colorFound) {
            let radioCustom = document.getElementById('radio_custom_color');
            let inputCustom = document.getElementById('input_custom_color');
            radioCustom.value = titik.warna;
            radioCustom.checked = true;
            inputCustom.value = titik.warna;
        }

        document.querySelector(`input[name="tanggal_realisasi"]`).value = titik.tanggal_realisasi || '';
        document.querySelector(`input[name="fisik_hari_ini"]`).value = titik.fisik_hari_ini || '';
        document.querySelector(`input[name="fisik_sd"]`).value = titik.fisik_sd || '';
        document.querySelector(`input[name="hk_hari_ini"]`).value = titik.hk_hari_ini || '';
        document.querySelector(`input[name="hk_sd"]`).value = titik.hk_sd || '';
        document.querySelector(`input[name="bahan_kimia_hari_ini"]`).value = titik.bahan_kimia_hari_ini || '';
        document.querySelector(`input[name="bahan_kimia_sd"]`).value = titik.bahan_kimia_sd || '';
        document.querySelector(`input[name="campuran_hari_ini"]`).value = titik.campuran_hari_ini || '';
        document.querySelector(`input[name="campuran_sd"]`).value = titik.campuran_sd || '';
        document.querySelector(`textarea[name="keterangan"]`).value = titik.keterangan || '';

        let btn = document.getElementById('btn-submit');
        btn.innerHTML = '<i class="ti ti-check"></i> Update Data GIS';
        btn.classList.remove('btn-cyan', 'shadow-cyan-200');
        btn.classList.add('bg-amber-500', 'hover:bg-amber-600', 'shadow-amber-200');

        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Mode Edit Aktif', showConfirmButton: false, timer: 1500 });
        document.getElementById('form-pemetaan').scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Render geojson ke form agar user tahu mana yang diedit
        if(drawnItems) drawnItems.clearLayers();
        if(titik.geojson) {
            let layer = L.geoJSON(JSON.parse(titik.geojson));
            layer.eachLayer(l => drawnItems.addLayer(l));
            applyColorToDrawings();
        }
    };

    // --- FUNGSI HAPUS DATA ---
    window.deleteMapData = function(id) {
        if (typeof confirmAlert === 'function') {
            confirmAlert(
                'Hapus Data Realisasi?',
                'Data pemetaan dan realisasi ini akan dihapus permanen!',
                'Ya, Hapus!',
                'Batal',
                () => executeDelete(id)
            );
        } else {
            Swal.fire({
                title: 'Hapus Data Realisasi?',
                text: 'Data pemetaan dan realisasi ini akan dihapus permanen!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) executeDelete(id);
            });
        }
    };

    async function executeDelete(id) {
        try {
            let formData = new FormData();
            formData.append('action', 'delete_map_data');
            formData.append('id', id);
            const response = await fetch('be/pemetaan_api.php', { method: 'POST', body: formData });
            const res = await response.json();
            if(res.success) {
                Swal.fire({ icon: 'success', title: 'Terhapus!', text: res.message, timer: 1500, showConfirmButton: false });
                loadSavedPoints();
            } else {
                Swal.fire('Gagal!', res.message, 'error');
            }
        } catch(err) {
            Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
        }
    }

    // --- 9. FUNGSI EXPORT KE PDF & EXCEL DENGAN HTML2CANVAS ---
    async function exportMap(format) {
        const mapDiv = document.getElementById('map');
        
        Swal.fire({
            title: 'Menyiapkan Dokumen...',
            html: 'Sedang memfokuskan peta dan mengambil *screenshot*.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        // Paskan map agar gambar maksimal sebelum screenshot
        let allBounds = [];
        if (drawnItems && drawnItems.getLayers().length > 0) {
            allBounds.push(drawnItems.getBounds());
        }
        map.eachLayer(layer => {
            if (layer instanceof L.ImageOverlay) {
                let b = layer.getBounds();
                if (b.isValid()) allBounds.push(b);
            }
        });

        if (allBounds.length > 0) {
            let finalBounds = allBounds[0];
            for (let i = 1; i < allBounds.length; i++) {
                finalBounds.extend(allBounds[i]);
            }
            map.fitBounds(finalBounds, { padding: [10, 10], animate: false });
            // Tunggu sebentar agar peta selesai merender zoom yang baru
            await new Promise(resolve => setTimeout(resolve, 800));
        }

        // Sembunyikan kontrol Leaflet agar tidak ikut tercetak
        const controls = document.querySelectorAll('.leaflet-control-container');
        controls.forEach(c => c.style.display = 'none');

        // PENTING: Reset scroll ke atas untuk menghindari bug offset di html2canvas
        window.scrollTo(0, 0);

        try {
            const canvas = await html2canvas(mapDiv, {
                useCORS: true,
                allowTaint: true,
                scale: 2, // Resolusi tinggi
                scrollY: -window.scrollY
            });
            
            const base64image = canvas.toDataURL("image/png");
            
            // Tampilkan kembali kontrol Leaflet
            controls.forEach(c => c.style.display = '');

            // Buat form tersembunyi untuk mengirim base64 ke PHP
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = format === 'excel' ? 'cetak/pemetaan_excel.php' : 'cetak/pemetaan_pdf.php';
            form.target = '_blank';

            const inputImg = document.createElement('input');
            inputImg.type = 'hidden';
            inputImg.name = 'map_image';
            inputImg.value = base64image;

            const inputUnit = document.createElement('input');
            inputUnit.type = 'hidden';
            inputUnit.name = 'unit_id';
            inputUnit.value = "<?= $unit_id ?>";

            const inputKebun = document.createElement('input');
            inputKebun.type = 'hidden';
            inputKebun.name = 'kebun_id';
            inputKebun.value = "<?= $kebun_id ?>";

            const inputJp = document.createElement('input');
            inputJp.type = 'hidden';
            inputJp.name = 'jenis_pekerjaan_id';
            inputJp.value = document.getElementById('filter_jenis_pekerjaan').value;

            form.appendChild(inputImg);
            form.appendChild(inputUnit);
            form.appendChild(inputKebun);
            form.appendChild(inputJp);
            document.body.appendChild(form);
            
            Swal.close();
            form.submit();
            document.body.removeChild(form);

        } catch (error) {
            console.error(error);
            controls.forEach(c => c.style.display = '');
            Swal.fire('Error', 'Gagal memproses gambar peta.', 'error');
        }
    }
</script>

<?php include_once '../layouts/footer.php'; ?>