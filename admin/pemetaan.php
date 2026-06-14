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
                <h1 class="text-2xl font-black text-slate-800 tracking-tight">Kertas Kerja</h1>
                <p class="text-xs font-bold text-cyan-600 uppercase tracking-widest">Kertas Kerja</p>
            </div>
        </div>

        <div class="flex gap-2 items-center">
            <input type="month" id="filter_bulan" class="input-custom min-w-[150px] border-cyan-300 font-bold text-slate-700" onchange="loadSavedPoints()" value="<?= date('Y-m') ?>">
            
            <select id="filter_jenis_pekerjaan" class="input-custom min-w-[200px] border-cyan-300 font-bold text-slate-700" onchange="loadSavedPoints()">
                <option value="">— Semua Pekerjaan —</option>
            </select>

            <button onclick="exportMap('pdf')" class="bg-gradient-to-r from-emerald-500 to-emerald-600 text-white border border-emerald-600 px-4 py-2 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center gap-2">
                <i class="ti ti-file-export"></i> Export PDF
            </button>
            <button onclick="exportMap('excel')" class="bg-gradient-to-r from-green-500 to-green-600 text-white border border-green-600 px-4 py-2 rounded-xl text-sm font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center gap-2">
                <i class="ti ti-file-spreadsheet"></i> Export Excel
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
                    <input type="file" name="peta_dasar" accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-fuchsia-50 file:text-fuchsia-700 hover:file:bg-fuchsia-100 cursor-pointer mb-2" required>
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
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5 ml-1">Nama Blok</label>
                        <input type="text" name="blok_nama" id="input_blok" class="input-custom" required placeholder="Masukkan Nama Blok">
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
                            <input type="date" name="tanggal_realisasi" id="input_tgl" value="<?= date('Y-m-d') ?>" class="input-custom text-xs" required>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Fisik (H. Ini)</label>
                                <input type="number" step="0.01" name="fisik_hari_ini" id="f_hi" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Fisik (S/D)</label>
                                <input type="number" step="0.01" name="fisik_sd" id="f_sd" class="input-custom text-xs bg-slate-100 cursor-not-allowed" placeholder="0.00" readonly title="Dihitung Otomatis">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">HK (H. Ini)</label>
                                <input type="number" step="0.01" name="hk_hari_ini" id="hk_hi" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">HK (S/D)</label>
                                <input type="number" step="0.01" name="hk_sd" id="hk_sd" class="input-custom text-xs bg-slate-100 cursor-not-allowed" placeholder="0.00" readonly title="Dihitung Otomatis">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Kimia (H. Ini)</label>
                                <input type="number" step="0.01" name="bahan_kimia_hari_ini" id="k_hi" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Kimia (S/D)</label>
                                <input type="number" step="0.01" name="bahan_kimia_sd" id="k_sd" class="input-custom text-xs bg-slate-100 cursor-not-allowed" placeholder="0.00" readonly title="Dihitung Otomatis">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Campuran (H. Ini)</label>
                                <input type="number" step="0.01" name="campuran_hari_ini" id="c_hi" class="input-custom text-xs" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-slate-500 uppercase mb-1">Campuran (S/D)</label>
                                <input type="number" step="0.01" name="campuran_sd" id="c_sd" class="input-custom text-xs bg-slate-100 cursor-not-allowed" placeholder="0.00" readonly title="Dihitung Otomatis">
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

        <!-- CROP MODAL -->
        <div id="cropModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
            <div class="bg-white p-5 rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh]">
                <h3 class="text-xl font-extrabold text-slate-800 mb-2">Potong (Crop) Peta Dasar</h3>
                <p class="text-xs text-slate-500 mb-4">Buang bagian tepi atau kertas yang tidak diperlukan sebelum diupload.</p>
                
                <div class="flex-1 bg-slate-100 overflow-hidden relative rounded-xl border border-slate-200 flex items-center justify-center min-h-[400px]">
                    <img id="imageToCrop" src="" alt="Peta" class="max-w-full max-h-[60vh] object-contain">
                </div>
                
                <div class="flex justify-end gap-3 mt-5 pt-4 border-t border-slate-100">
                    <button type="button" id="btnCancelCrop" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold transition">Batal</button>
                    <button type="button" id="btnApplyCrop" class="px-5 py-2.5 rounded-xl bg-cyan-600 text-white hover:bg-cyan-700 font-bold shadow-lg shadow-cyan-500/30 transition flex items-center gap-2">
                        <i class="ti ti-crop"></i> Crop & Gunakan
                    </button>
                </div>
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
                zoomControl: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                touchZoom: false,
                boxZoom: false,
                keyboard: false,
                preferCanvas: true,
                zoomSnap: 0
            });
            let fileUrl = `../uploads/pemetaan/base_map/${petaKerjaFoto}`;
            
            if (petaKerjaFoto.toLowerCase().endsWith('.pdf')) {
                // MODE PDF: Render halaman 1 PDF ke canvas menggunakan pdf.js
                pdfjsLib.getDocument(fileUrl).promise.then(function(pdf) {
                    pdf.getPage(1).then(function(page) {
                        let scale = 2;
                        let viewport = page.getViewport({ scale: scale });
                        let canvas = document.createElement('canvas');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        let ctx = canvas.getContext('2d');
                        page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function() {
                            let imgDataUrl = canvas.toDataURL('image/png');
                            let bounds = [[0, 0], [viewport.height, viewport.width]];
                            L.imageOverlay(imgDataUrl, bounds).addTo(map);
                            // Mengatur ukuran zoom awal agar tidak terlalu dekat dengan memberikan padding
                            map.fitBounds(bounds, { padding: [0, 0] });
                        });
                    });
                }).catch(function(err) {
                    console.error('Gagal memuat PDF:', err);
                });
            } else {
                // MODE GAMBAR: JPG/PNG/WEBP
                let img = new Image();
                img.src = fileUrl;
                img.onload = function() {
                    let w = img.width, h = img.height;
                    let bounds = [[0, 0], [h, w]];
                    L.imageOverlay(fileUrl, bounds).addTo(map);
                    // Mengatur ukuran zoom awal agar tidak terlalu dekat dengan memberikan padding
                    map.fitBounds(bounds, { padding: [0, 0] });
                }
            }
        } else {
            // MODE: SATELIT GPS DEFAULT
            map = L.map('map', { 
                preferCanvas: true, 
                zoomControl: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                touchZoom: false,
                boxZoom: false,
                keyboard: false
            }).setView([3.5952, 98.6722], 13); 
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

    // --- 3. AMBIL MASTER DATA PEKERJAAN ---
    async function loadMasterData() {
        try {
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
        const bulan = document.getElementById('filter_bulan').value;

        // Update hidden input untuk submit
        document.getElementById('input_jp_id').value = jp_id;
        document.getElementById('upload_jp_id').value = jp_id;

        // Reset peta
        if (drawnItems) drawnItems.clearLayers();

        if(jp_id === "") {
            document.getElementById('card_upload_peta').classList.add('hidden');
        }

        try {
            const response = await fetch(`be/pemetaan_api.php?action=get_map_data&kebun_id=${kebun_id}&unit_id=${unit_id}&jenis_pekerjaan_id=${jp_id}&bulan=${bulan}`);
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
                            <td class="px-3 py-2 border-r border-slate-200 text-center font-bold">${titik.blok_nama || '-'}</td>
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
                            <div class="font-bold text-slate-800 text-sm mb-1">${titik.blok_nama}</div>
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

                });

                // Set otomatis nilai Fisik (S/D) dari nilai terbesar sebelumnya + 1
                if (res.data.length > 0) {
                    let latestFisikSd = 0;
                    res.data.forEach(titik => {
                        let cur = parseFloat(titik.fisik_sd) || 0;
                        if (cur > latestFisikSd) latestFisikSd = cur;
                    });
                    
                    // Hanya set otomatis jika form sedang mode tambah baru (bukan edit)
                    if (document.getElementById('input_edit_id').value === '') {
                        document.querySelector('input[name="fisik_sd"]').value = (latestFisikSd + 1).toFixed(2);
                        document.querySelector('input[name="fisik_hari_ini"]').value = ""; // Reset ke kosong
                    }
                } else {
                    if (document.getElementById('input_edit_id').value === '') {
                        document.querySelector('input[name="fisik_sd"]').value = "1.00";
                        document.querySelector('input[name="fisik_hari_ini"]').value = "";
                    }
                }
            }
        } catch (err) { console.error(err); }
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

    // --- 8. HANDLE SUBMIT PETA DASAR & CROPPER ---
    let cropper = null;
    let croppedBlob = null;
    const inputPetaDasar = document.querySelector('input[name="peta_dasar"]');
    const cropModal = document.getElementById('cropModal');
    const imageToCrop = document.getElementById('imageToCrop');

    window.addEventListener('resize', function() {
        if(map) { map.invalidateSize(); }
    });

    // --- 10. AUTO KALKULASI S/D BERDASARKAN INPUT H. INI ---
    let previousSdData = { fisik_sd: 0, hk_sd: 0, bahan_kimia_sd: 0, campuran_sd: 0 };

    async function fetchPreviousSd() {
        const kebun_id = "<?= $kebun_id ?>";
        const unit_id = "<?= $unit_id ?>";
        const jp_id = document.getElementById('filter_jenis_pekerjaan').value;
        const blok = document.getElementById('input_blok').value;
        const tgl = document.getElementById('input_tgl').value;
        const current_id = document.getElementById('input_edit_id').value;

        if (!jp_id || !tgl) {
            previousSdData = { fisik_sd: 0, hk_sd: 0, bahan_kimia_sd: 0, campuran_sd: 0 };
            return;
        }

        try {
            const response = await fetch(`be/pemetaan_api.php?action=get_previous_sd&kebun_id=${kebun_id}&unit_id=${unit_id}&jenis_pekerjaan_id=${jp_id}&blok_nama=${encodeURIComponent(blok)}&tanggal_realisasi=${tgl}&current_id=${current_id}`);
            const res = await response.json();
            if (res.success && res.data) {
                previousSdData.fisik_sd = parseFloat(res.data.fisik_sd) || 0;
                previousSdData.hk_sd = parseFloat(res.data.hk_sd) || 0;
                previousSdData.bahan_kimia_sd = parseFloat(res.data.bahan_kimia_sd) || 0;
                previousSdData.campuran_sd = parseFloat(res.data.campuran_sd) || 0;
            } else {
                previousSdData = { fisik_sd: 0, hk_sd: 0, bahan_kimia_sd: 0, campuran_sd: 0 };
            }
            recalculateSd();
        } catch(err) {
            console.error("Gagal fetch S/D:", err);
        }
    }

    function recalculateSd() {
        const f_hi_raw = document.getElementById('f_hi').value;
        const hk_hi_raw = document.getElementById('hk_hi').value;
        const k_hi_raw = document.getElementById('k_hi').value;
        const c_hi_raw = document.getElementById('c_hi').value;

        if (f_hi_raw !== "") {
            document.getElementById('f_sd').value = (previousSdData.fisik_sd + parseFloat(f_hi_raw || 0)).toFixed(2);
        } else {
            document.getElementById('f_sd').value = "";
        }

        if (hk_hi_raw !== "") {
            document.getElementById('hk_sd').value = (previousSdData.hk_sd + parseFloat(hk_hi_raw || 0)).toFixed(2);
        } else {
            document.getElementById('hk_sd').value = "";
        }

        if (k_hi_raw !== "") {
            document.getElementById('k_sd').value = (previousSdData.bahan_kimia_sd + parseFloat(k_hi_raw || 0)).toFixed(2);
        } else {
            document.getElementById('k_sd').value = "";
        }

        if (c_hi_raw !== "") {
            document.getElementById('c_sd').value = (previousSdData.campuran_sd + parseFloat(c_hi_raw || 0)).toFixed(2);
        } else {
            document.getElementById('c_sd').value = "";
        }
    }

    document.getElementById('input_blok').addEventListener('blur', fetchPreviousSd);
    document.getElementById('input_tgl').addEventListener('change', fetchPreviousSd);
    document.getElementById('f_hi').addEventListener('input', recalculateSd);
    document.getElementById('hk_hi').addEventListener('input', recalculateSd);
    document.getElementById('k_hi').addEventListener('input', recalculateSd);
    document.getElementById('c_hi').addEventListener('input', recalculateSd);

    inputPetaDasar.addEventListener('change', function(e) {
        let files = e.target.files;
        if (files && files.length > 0) {
            let file = files[0];
            // Jika PDF, CropperJS tidak bisa
            if(file.type === 'application/pdf') {
                croppedBlob = null;
                return;
            }

            let reader = new FileReader();
            reader.onload = function(event) {
                imageToCrop.src = event.target.result;
                cropModal.classList.remove('hidden');
                cropModal.classList.add('flex');
                
                if (cropper) { cropper.destroy(); }
                cropper = new Cropper(imageToCrop, {
                    viewMode: 1,
                    autoCropArea: 0.9,
                    background: true,
                    responsive: true,
                    restore: false
                });
            };
            reader.readAsDataURL(file);
        }
    });

    document.getElementById('btnCancelCrop').addEventListener('click', () => {
        cropModal.classList.add('hidden');
        cropModal.classList.remove('flex');
        if (cropper) { cropper.destroy(); cropper = null; }
        inputPetaDasar.value = ''; 
        croppedBlob = null;
    });

    document.getElementById('btnApplyCrop').addEventListener('click', () => {
        if (!cropper) return;
        cropper.getCroppedCanvas({
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        }).toBlob((blob) => {
            croppedBlob = blob;
            cropModal.classList.add('hidden');
            cropModal.classList.remove('flex');
            if (cropper) { cropper.destroy(); cropper = null; }
            Swal.fire({
                icon: 'success',
                title: 'Berhasil dipotong!',
                text: 'Silakan klik tombol "Upload & Pasang Peta".',
                timer: 2000,
                showConfirmButton: false
            });
        }, 'image/png', 0.9);
    });

    document.getElementById('form-upload-peta').addEventListener('submit', async function(e) {
        e.preventDefault();
        let btn = document.getElementById('btn-upload-peta');
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Mengupload...';
        btn.disabled = true;

        let formData = new FormData(this);
        
        // Timpa file asli dengan hasil crop jika ada
        if (croppedBlob) {
            formData.set('peta_dasar', croppedBlob, 'map_cropped.png');
        }

        try {
            const response = await fetch('be/pemetaan_api.php', { method: 'POST', body: formData });
            const res = await response.json();
            
            if(res.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 1500, showConfirmButton: false });
                this.reset();
                croppedBlob = null;
                loadSavedPoints(); // Refresh map with new base image
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
            Swal.fire('Error Koneksi!', 'Gagal menghubungi server.', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadMasterData();
        loadSavedPoints();
    });

    // --- FUNGSI EDIT DATA ---
    window.editMapData = function(titik) {
        document.getElementById('input_edit_id').value = titik.id;
        document.getElementById('input_blok').value = titik.blok_nama || '';
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

        // PENTING: Hilangkan shadow dan border karena akan tertangkap oleh html2canvas (garis burem)
        const oldBoxShadow = mapDiv.style.boxShadow;
        const oldBorderRadius = mapDiv.style.borderRadius;
        const oldBorder = mapDiv.style.border;
        mapDiv.style.boxShadow = 'none';
        mapDiv.style.borderRadius = '0';
        mapDiv.style.border = 'none';

        // PENTING: Reset scroll ke atas untuk menghindari bug offset di html2canvas
        window.scrollTo(0, 0);

        try {
            let canvas = await html2canvas(mapDiv, {
                useCORS: true,
                allowTaint: true,
                scale: 2, // Resolusi tinggi
                scrollY: -window.scrollY
            });
            
            // Fungsi untuk auto-crop margin putih bawaan dari div peta
            function trimCanvas(c) {
                var ctx = c.getContext('2d', { willReadFrequently: true }),
                    copy = document.createElement('canvas').getContext('2d'),
                    pixels = ctx.getImageData(0, 0, c.width, c.height),
                    l = pixels.data.length,
                    i, bound = { top: null, left: null, right: null, bottom: null }, x, y;
                
                // Deteksi warna background putih
                function isBg(i) { return pixels.data[i] >= 250 && pixels.data[i+1] >= 250 && pixels.data[i+2] >= 250; }

                for (i = 0; i < l; i += 4) {
                    if (!isBg(i)) {
                        x = (i / 4) % c.width; y = ~~((i / 4) / c.width);
                        if (bound.top === null) bound.top = y;
                        if (bound.left === null) bound.left = x; else if (x < bound.left) bound.left = x;
                        if (bound.right === null) bound.right = x; else if (bound.right < x) bound.right = x;
                        if (bound.bottom === null) bound.bottom = y; else if (bound.bottom < y) bound.bottom = y;
                    }
                }
                if (bound.top === null) return c;
                var pad = 20; // Beri ruang margin putih secukupnya agar peta tidak nabrak pinggir
                bound.top = Math.max(0, bound.top - pad); bound.left = Math.max(0, bound.left - pad);
                bound.bottom = Math.min(c.height, bound.bottom + pad); bound.right = Math.min(c.width, bound.right + pad);
                var trimHeight = bound.bottom - bound.top, trimWidth = bound.right - bound.left;
                var trimmed = ctx.getImageData(bound.left, bound.top, trimWidth, trimHeight);
                copy.canvas.width = trimWidth; copy.canvas.height = trimHeight;
                copy.putImageData(trimmed, 0, 0);
                return copy.canvas;
            }

            canvas = trimCanvas(canvas);
            const base64image = canvas.toDataURL("image/png");
            
            // Tampilkan kembali kontrol Leaflet dan kembalikan style peta
            controls.forEach(c => c.style.display = '');
            mapDiv.style.boxShadow = oldBoxShadow;
            mapDiv.style.borderRadius = oldBorderRadius;
            mapDiv.style.border = oldBorder;

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

            const inputBulan = document.createElement('input');
            inputBulan.type = 'hidden';
            inputBulan.name = 'bulan';
            inputBulan.value = document.getElementById('filter_bulan').value;

            form.appendChild(inputImg);
            form.appendChild(inputUnit);
            form.appendChild(inputKebun);
            form.appendChild(inputJp);
            form.appendChild(inputBulan);
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