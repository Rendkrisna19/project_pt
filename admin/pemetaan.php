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

        <div class="flex gap-2">
            <button onclick="exportMapToPDF()" class="bg-gradient-to-r from-emerald-500 to-emerald-600 text-white border border-emerald-600 px-4 py-2 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center gap-2">
                <i class="ti ti-file-export"></i> Export PDF (Peta & Realisasi)
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
                    
                    <input type="hidden" name="kebun_id" value="<?= $kebun_id ?>">
                    <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
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
                        <div class="flex gap-3 px-1">
                            <input type="radio" name="warna" value="#0891b2" class="color-radio" style="background-color: #0891b2;" checked title="Cyan">
                            <input type="radio" name="warna" value="#e11d48" class="color-radio" style="background-color: #e11d48;" title="Merah">
                            <input type="radio" name="warna" value="#10b981" class="color-radio" style="background-color: #10b981;" title="Hijau">
                            <input type="radio" name="warna" value="#f59e0b" class="color-radio" style="background-color: #f59e0b;" title="Kuning">
                            <input type="radio" name="warna" value="#6366f1" class="color-radio" style="background-color: #6366f1;" title="Ungu">
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

        <div class="lg:col-span-3">
            <div id="map"></div>
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
                zoomControl: true
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
            map = L.map('map').setView([3.5952, 98.6722], 13); 
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

    // --- 3. AMBIL MASTER DATA BLOK ---
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
        } catch(e) { console.error("Kesalahan Fetch Blok:", e); }
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

        try {
            const response = await fetch(`be/pemetaan_api.php?action=get_map_data&kebun_id=${kebun_id}&unit_id=${unit_id}`);
            const res = await response.json();

            if(res.success) {
                // Inisialisasi peta berdasarkan foto base map
                initMap(res.peta_kerja_foto);
                applyColorToDrawings();
                
                // Tampilkan atau sembunyikan form upload base map
                if(!res.peta_kerja_foto) {
                    document.getElementById('card_upload_peta').classList.remove('hidden');
                } else {
                    document.getElementById('card_upload_peta').classList.add('hidden');
                }

                let bounds = [];

                res.data.forEach(titik => {
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
                Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
            }
        } catch(err) {
            Swal.fire('Error', 'Gagal mengupload peta.', 'error');
        } finally {
            btn.innerHTML = 'Upload & Pasang Peta';
            btn.disabled = false;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadBloks();
        loadSavedPoints();
    });

    // --- 9. FUNGSI EXPORT KE PDF DENGAN HTML2CANVAS ---
    async function exportMapToPDF() {
        const mapDiv = document.getElementById('map');
        
        // Sembunyikan kontrol Leaflet agar tidak ikut tercetak
        const controls = document.querySelectorAll('.leaflet-control-container');
        controls.forEach(c => c.style.display = 'none');
        
        Swal.fire({
            title: 'Menyiapkan Dokumen...',
            html: 'Sedang mengambil *screenshot* peta dan data realisasi.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const canvas = await html2canvas(mapDiv, {
                useCORS: true,
                allowTaint: true,
                scale: 2 // Resolusi tinggi
            });
            
            const base64image = canvas.toDataURL("image/png");
            
            // Tampilkan kembali kontrol Leaflet
            controls.forEach(c => c.style.display = '');

            // Buat form tersembunyi untuk mengirim base64 ke PHP
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'cetak/pemetaan_pdf.php';
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

            form.appendChild(inputImg);
            form.appendChild(inputUnit);
            form.appendChild(inputKebun);
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