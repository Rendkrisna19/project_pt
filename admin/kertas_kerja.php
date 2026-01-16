<?php
// pages/kertas_kerja.php
session_start();

// 1. Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

// 2. AMBIL ROLE PENGGUNA
$user_role = $_SESSION['user_role'] ?? 'staf'; 
$isStaf = ($user_role === 'staf'); 

// 3. Cek Unit ID
if (!isset($_GET['unit_id']) || empty($_GET['unit_id'])) { header("Location: pilih_unit.php"); exit; }
$unit_id = $_GET['unit_id'];

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// --- DATA UNIT & KEBUN ---
$stmt = $conn->prepare("SELECT id, nama_unit FROM units WHERE id = ?");
$stmt->execute([$unit_id]);
$unitData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$unitData) { die("Data Unit tidak ditemukan."); }

// Ambil ID Kebun
$kebunData = $conn->query("SELECT id, nama_kebun FROM md_kebun LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kebun_id_fix = $kebunData['id'] ?? 1;
$kebun_nama_fix = $kebunData['nama_kebun'] ?? 'Default Kebun';

// Config Tanggal
$f_tahun = date('Y');
$f_bulan = date('n');

$currentPage = 'kertas_kerja';
include_once '../layouts/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<input type="hidden" id="h_kebun_id" value="<?= $kebun_id_fix ?>">
<input type="hidden" id="h_unit_id" value="<?= $unit_id ?>">

<style>
    body { font-family: "Poppins", sans-serif; background-color: #f1f5f9; }
    :root { --main: #0891b2; --bg-head: #ecfeff; --group-bg: #f8fafc; }
    
    /* --- TABLE CONTAINER & SCROLL (KUNCI STICKY) --- */
    .sheet-wrapper { 
        background: white; 
        border: 1px solid #cbd5e1; 
        border-radius: 12px; 
        display: flex; 
        flex-direction: column; 
        /* Penting: Set max-height agar scrollbar muncul di tabel, bukan di body */
        height: calc(100vh - 220px); 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        position: relative;
        overflow: hidden;
    }
    
    .sheet-scroll { 
        flex: 1; 
        overflow: auto; /* Scrollbar otomatis muncul disini */
        position: relative; 
        width: 100%;
    }
    
    /* --- TABLE STYLE --- */
    table.sheet { 
        border-collapse: separate; /* Wajib separate agar sticky border render benar */
        border-spacing: 0; 
        font-size: 11px; 
        font-family: 'Poppins', sans-serif; 
        min-width: 100%;
    }
    
    table.sheet th, table.sheet td { 
        border-right: 1px solid #e2e8f0; 
        border-bottom: 1px solid #e2e8f0; 
        padding: 6px 8px; 
        white-space: nowrap; 
        vertical-align: middle; 
        box-sizing: border-box;
    }
    
    /* --- STICKY HEADERS (FREEZE PANES TOP) --- */
    /* Baris 1: Header Utama */
    .sticky-top-1 th { 
        position: sticky; 
        top: 0; 
        z-index: 40; /* Z-index tinggi agar di atas konten */
        background: var(--main); 
        color: white; 
        height: 40px; 
        text-transform: uppercase; 
        font-size: 11px;
        border-bottom: 1px solid rgba(255,255,255,0.2); 
    }
    
    /* Baris 2: Sub Header (Angka Tanggal) */
    .sticky-top-2 th { 
        position: sticky; 
        top: 40px; /* Sesuai tinggi baris 1 */
        z-index: 39; 
        background: #f1f5f9; 
        color: #334155; 
        border-bottom: 2px solid var(--main); 
        font-weight: 700; 
        height: 30px; 
    }
    
    /* --- STICKY COLUMNS (FREEZE PANES LEFT) --- */
    /* Kolom kiri yang diam saat scroll horizontal */
    .sticky-col { 
        position: sticky; 
        background: white; 
        z-index: 20; 
    }
    
    /* Persimpangan Sticky Top & Left (Pojok Kiri Atas) */
    .sticky-top-1 .sticky-col { z-index: 50; } 
    .sticky-top-2 .sticky-col { z-index: 49; background: #f8fafc; }

    /* Pengaturan Posisi & Lebar Kolom Kiri */
    .c-blok    { left: 0; width: 120px; min-width: 120px; max-width: 120px; font-weight: 600; color: var(--main); border-right: 1px solid #cbd5e1; }
    .c-rencana { left: 120px; width: 90px; min-width: 90px; text-align: right; font-weight: 600; }
    .c-sat     { left: 210px; width: 50px; min-width: 50px; text-align: center; border-right: 2px solid var(--main); }

    /* Group & Subtotal Rows */
    .group-row td { 
        background-color: var(--group-bg); 
        font-weight: 800; 
        color: #334155; 
        text-transform: uppercase; 
        border-top: 1px solid #94a3b8; 
        font-size: 11px; 
        padding: 8px 10px; 
    }
    /* Sticky untuk Judul Group */
    .group-sticky { position: sticky; left: 0; z-index: 15; background-color: var(--group-bg); }
    
    .subtotal-row td { font-weight: 700; border-top: 2px solid #cbd5e1; }
    .subtotal-sticky { position: sticky; left: 0; z-index: 15; text-align: right; padding-right: 12px; font-style: italic; }

    .sub-fisik { background-color: #f0fdfa; color: #047857; }
    .sub-tenaga { background-color: #fffbeb; color: #b45309; }
    .sub-kimia { background-color: #eff6ff; color: #1d4ed8; }
    .sub-campuran { background-color: #faf5ff; color: #7e22ce; }

    /* Inputs & Helper */
    .inp-cell { width: 100%; text-align: right; background: transparent; border: 1px solid transparent; outline: none; font-family: monospace; font-size: 13px; color: #334155; }
    .inp-cell:hover, .inp-cell:focus { background: #cffafe; border-radius: 2px; }
    
    .sunday-col { background-color: #fff1f2 !important; }
    .sunday-header { background-color: #e11d48 !important; color: white !important; }
    
    /* Scrollbar Style */
    .sheet-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
    .sheet-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
    .sheet-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .sheet-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<div class="space-y-4 p-2 pb-2">
    
    <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm gap-4">
        <div class="w-full md:w-auto">
            <a href="pilih_unit.php" class="text-xs font-bold text-slate-400 hover:text-cyan-600 mb-1 flex items-center gap-1 transition"><i class="ti ti-arrow-left"></i> Kembali ke Unit</a>
            <h1 class="text-xl md:text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="ti ti-file-analytics text-cyan-600"></i>
                <span class="text-cyan-600 uppercase"><?= htmlspecialchars($unitData['nama_unit']) ?></span>
            </h1>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto justify-end">
            <span id="save-status" class="text-xs font-bold text-cyan-600 hidden flex items-center gap-1 bg-cyan-50 px-2 py-1 rounded border border-cyan-100 animate-pulse">
                <i class="ti ti-check"></i> Tersimpan
            </span>

            <div class="flex bg-slate-100 p-1 rounded-lg border border-slate-200">
                <button onclick="downloadExcel()" class="flex items-center gap-2 px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-white hover:bg-emerald-600 rounded transition shadow-sm" title="Export Excel">
                    <i class="ti ti-file-spreadsheet text-lg"></i> <span class="hidden sm:inline">Excel</span>
                </button>
                <div class="w-px bg-slate-300 my-1 mx-1"></div>
                <button onclick="downloadPDF()" class="flex items-center gap-2 px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-white hover:bg-red-600 rounded transition shadow-sm" title="Cetak PDF">
                    <i class="ti ti-file-type-pdf text-lg"></i> <span class="hidden sm:inline">PDF</span>
                </button>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 items-center bg-white p-3 rounded-xl border border-slate-200 shadow-sm text-sm">
        
        <div class="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
            <i class="ti ti-calendar text-slate-400"></i>
            <select id="f_tahun" class="bg-transparent font-bold text-slate-700 outline-none filter-act cursor-pointer hover:text-cyan-600">
                <?php for($y=2023; $y<=date('Y')+1; $y++): ?><option value="<?= $y ?>" <?= $y==$f_tahun?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
            </select>
            <span class="text-slate-300">/</span>
            <select id="f_bulan" class="bg-transparent font-bold text-slate-700 outline-none filter-act cursor-pointer hover:text-cyan-600">
                <?php foreach(['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'] as $i=>$b): ?>
                    <option value="<?= $i+1 ?>" <?= $i+1==$f_bulan?'selected':'' ?>><?= $b ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
            <span class="text-xs font-bold text-slate-500 uppercase">TGL:</span>
            <select id="f_d_start" class="bg-transparent font-bold text-slate-700 outline-none filter-act cursor-pointer w-10 text-center">
                <?php for($d=1;$d<=31;$d++) echo "<option value='$d'>$d</option>"; ?>
            </select>
            <span class="text-slate-400">-</span>
            <select id="f_d_end" class="bg-transparent font-bold text-slate-700 outline-none filter-act cursor-pointer w-10 text-center">
                <?php 
                for($d=1;$d<=31;$d++) {
                    $selected = ($d == 15) ? 'selected' : ''; 
                    echo "<option value='$d' $selected>$d</option>"; 
                }
                ?>
            </select>
        </div>

        <div class="flex-1 relative min-w-[200px]">
            <i class="ti ti-search absolute left-3 top-2.5 text-slate-400"></i>
            <input type="text" id="search_input" placeholder="Cari Blok / Pekerjaan..." 
                   class="w-full pl-9 pr-4 py-1.5 border border-slate-300 rounded-lg text-sm font-medium focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition">
        </div>
    </div>

    <div class="sheet-wrapper">
        <div id="loading" class="absolute inset-0 bg-white/90 z-[100] hidden flex-col items-center justify-center">
            <i class="ti ti-loader animate-spin text-4xl text-cyan-600 mb-2"></i>
            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Memuat Data...</span>
        </div>
        
        <div class="sheet-scroll">
            <table class="sheet">
                <thead>
                    <tr class="sticky-top-1">
                        <th colspan="3" class="sticky-col border-r-2 border-cyan-400/50 shadow-sm">DATA RENCANA</th>
                        <th id="hdr-col-dates" colspan="1">REALISASI HARIAN</th>
                        <th rowspan="2" style="min-width:80px">TOTAL</th>
                        <th rowspan="2" style="min-width:70px">+/-</th>
                        <th rowspan="2" style="min-width:60px; text-align:center;">AKSI</th>
                    </tr>
                    <tr class="sticky-top-2" id="row-dates">
                        <th class="sticky-col c-blok">BLOK</th>
                        <th class="sticky-col c-rencana">RENCANA</th>
                        <th class="sticky-col c-sat">SAT</th>
                        </tr>
                </thead>
                <tbody id="sheet-body"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal-form" class="fixed inset-0 z-[150] hidden flex items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity">
    <div class="bg-white w-full max-w-lg rounded-xl shadow-2xl overflow-hidden transform scale-100 border border-slate-200">
        <div class="bg-cyan-600 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-lg uppercase tracking-wide flex items-center gap-2">
                <i class="ti ti-edit"></i> <span id="modal-title">Form Data</span>
            </h3>
            <button onclick="closeModal()" class="hover:bg-white/20 p-1 rounded-full transition"><i class="ti ti-x text-xl"></i></button>
        </div>
        <form id="form-data" class="p-6 space-y-5">
            <input type="hidden" name="action" value="store_plan">
            <input type="hidden" name="id" id="form_id">
            <input type="hidden" name="jenis_pekerjaan_id" id="form_job_id">
            
            <input type="hidden" name="kebun_id" id="form_kebun">
            <input type="hidden" name="unit_id" id="form_unit">
            <input type="hidden" name="tahun" id="form_tahun">
            <input type="hidden" name="bulan" id="form_bulan">

            <div class="bg-cyan-50 p-4 rounded-lg border border-cyan-100 flex items-start gap-3">
                <i class="ti ti-briefcase text-cyan-600 text-xl mt-0.5"></i>
                <div>
                    <span class="block text-xs font-bold text-cyan-600 uppercase mb-0.5">Jenis Pekerjaan</span>
                    <span id="form_job_nama" class="text-base font-bold text-slate-800 leading-tight"></span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5 ml-1">Nama Blok</label>
                <input type="text" name="blok" id="form_blok" class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-bold uppercase focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 outline-none transition" required placeholder="Contoh: A01">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5 ml-1">Jumlah Rencana</label>
                    <input type="number" step="0.01" name="fisik" id="form_fisik" class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-bold text-right focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 outline-none transition" required placeholder="0.00">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5 ml-1">Satuan</label>
                    <input type="text" name="satuan" id="form_satuan" class="w-full border border-slate-200 bg-slate-100 text-slate-500 rounded-lg px-4 py-2.5 text-sm font-bold text-center cursor-not-allowed" readonly>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-cyan-600 text-white py-3 rounded-lg font-bold text-sm hover:bg-cyan-700 shadow-md transition active:scale-[0.98] flex justify-center items-center gap-2">
                    <i class="ti ti-device-floppy"></i> SIMPAN DATA
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const $ = id => document.getElementById(id);

// --- ROLE CONSTANT ---
const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;

let GLOBAL_DATA = []; 

// --- 1. RENDER HEADER (With Sticky Logic) ---
function renderHeaders() {
    const start = parseInt($('f_d_start').value);
    const end = parseInt($('f_d_end').value);
    const safeStart = Math.min(start, end);
    const safeEnd = Math.max(start, end);
    
    const row = $('row-dates');
    // Hapus kolom tanggal lama (sisakan 3 kolom sticky: blok, rencana, sat)
    while(row.children.length > 3) row.removeChild(row.lastChild);

    const year = $('f_tahun').value;
    const month = $('f_bulan').value;
    const daysInM = new Date(year, month, 0).getDate();
    const limit = Math.min(safeEnd, daysInM);

    $('hdr-col-dates').colSpan = (limit - safeStart + 1);

    for(let i=safeStart; i<=limit; i++) {
        const th = document.createElement('th');
        th.innerText = i;
        th.className = 'text-center text-xs font-bold text-slate-600 bg-[#f1f5f9] border-b-2 border-cyan-500';
        th.style.minWidth = '45px';
        
        const date = new Date(year, month-1, i);
        if(date.getDay() === 0) {
            th.classList.remove('bg-[#f1f5f9]','text-slate-600','border-cyan-500');
            th.classList.add('sunday-header'); 
        }
        row.appendChild(th);
    }
    return {start: safeStart, end: limit};
}

// --- 2. LOAD DATA ---
async function loadSheet() {
    $('loading').classList.remove('hidden'); $('loading').classList.add('flex');
    
    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('kebun_id', $('h_kebun_id').value);
    fd.append('unit_id', $('h_unit_id').value);
    fd.append('tahun', $('f_tahun').value);
    fd.append('bulan', $('f_bulan').value);

    try {
        const res = await fetch('kertas_kerja_crud.php', {method:'POST', body:fd});
        const json = await res.json();
        $('loading').classList.add('hidden');
        if(json.success) {
            GLOBAL_DATA = json.data;
            applySearchAndRender();
        } else {
            Swal.fire('Error', json.message, 'error');
        }
    } catch(e) { $('loading').classList.add('hidden'); console.error(e); }
}

// --- 3. FILTER SEARCH ---
function applySearchAndRender() {
    const keyword = $('search_input').value.toLowerCase();
    const range = renderHeaders();

    let filteredData = GLOBAL_DATA;
    if(keyword) {
        filteredData = GLOBAL_DATA.map(group => {
            const jobMatch = group.job_nama.toLowerCase().includes(keyword);
            const matchingItems = group.items.filter(item => item.blok.toLowerCase().includes(keyword));

            if (jobMatch) {
                if (matchingItems.length > 0) return {...group, items: matchingItems};
                return {...group, items: group.items};
            } else {
                if (matchingItems.length > 0) return {...group, items: matchingItems};
            }
            return null;
        }).filter(group => group !== null);
    }
    renderGroupedTable(filteredData, range.start, range.end);
}

// --- 4. RENDER TABLE UTAMA ---
function renderGroupedTable(data, start, end) {
    const tbody = $('sheet-body');
    tbody.innerHTML = '';

    const year = $('f_tahun').value;
    const month = $('f_bulan').value;
    const colCount = (end - start + 1) + 6;

    if(data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colCount}" class="p-8 text-center text-slate-400 italic">Data tidak ditemukan untuk periode ini.</td></tr>`;
        return;
    }

    data.forEach((group) => {
        let addButtonHtml = `
            <button onclick="addBlock('${group.job_id}', '${group.job_nama}', '${group.satuan_default}')" class="bg-white border border-cyan-300 text-cyan-600 px-2 py-0.5 rounded text-[10px] font-bold hover:bg-cyan-600 hover:text-white shadow-sm transition uppercase flex items-center gap-1">
                <i class="ti ti-plus"></i> Item
            </button>`;

        const trHead = document.createElement('tr');
        trHead.className = 'group-row';
        trHead.innerHTML = `
            <td colspan="${colCount}" class="group-sticky border-b border-cyan-200">
                <div class="flex items-center justify-between pl-1">
                    <span class="text-cyan-900 text-xs font-bold tracking-wide flex items-center gap-2">
                        <i class="ti ti-folder text-cyan-500"></i> ${group.job_nama}
                    </span>
                    ${addButtonHtml}
                </div>
            </td>`;
        tbody.appendChild(trHead);

        let recaps = {
            'FISIK':    { label: 'TOTAL FISIK',    rencana: 0, realisasi: 0, days: new Array(32).fill(0), satuan: '' },
            'TENAGA':   { label: 'TOTAL HK',       rencana: 0, realisasi: 0, days: new Array(32).fill(0), satuan: 'HK' },
            'KIMIA':    { label: 'TOTAL BAHAN',    rencana: 0, realisasi: 0, days: new Array(32).fill(0), satuan: 'Kg/L' },
            'CAMPURAN': { label: 'TOTAL CAMPURAN', rencana: 0, realisasi: 0, days: new Array(32).fill(0), satuan: '' }
        };
        let hasData = false;

        if(group.items.length > 0) {
            hasData = true;
            group.items.forEach(row => {
                let cells = '';
                let dynamicTotal = 0;

                for(let i=start; i<=end; i++) {
                    const val = row.days[i] || 0;
                    dynamicTotal += val;
                    const isSun = new Date(year, month-1, i).getDay() === 0 ? 'sunday-col' : '';
                    
                    cells += `
                    <td class="${isSun}">
                        <input type="text" class="inp-cell"
                            data-pid="${row.id_plan}" 
                            data-jid="${group.job_id}" 
                            data-day="${i}"
                            value="${val==0?'':val}"
                            onchange="saveCell(this)" 
                            onkeypress="validateKey(event)"
                            placeholder="-">
                    </td>`;
                }

                const variance = dynamicTotal - row.rencana;
                const rowJson = encodeURIComponent(JSON.stringify({...row, job_id: group.job_id, job_nama: group.job_nama}));

                // Logic Subtotal
                let catKey = 'FISIK'; 
                const sat = (row.satuan || '').toUpperCase();
                const katMaster = (group.kategori || '').toUpperCase();

                if(katMaster === 'TENAGA' || sat === 'HK') catKey = 'TENAGA';
                else if(katMaster === 'KIMIA' || ['KG','LITER','LTR','L'].includes(sat)) catKey = 'KIMIA';
                else if(katMaster === 'CAMPURAN') catKey = 'CAMPURAN';
                
                recaps[catKey].rencana += row.rencana;
                recaps[catKey].realisasi += dynamicTotal;
                recaps[catKey].satuan = row.satuan;
                for(let i=start; i<=end; i++) recaps[catKey].days[i] += (row.days[i] || 0);

                let actionButtons = '';
                if(!IS_STAF) {
                    actionButtons = `
                        <div class="flex justify-center items-center gap-1">
                            <button onclick="editBlock('${rowJson}')" class="text-blue-500 hover:bg-blue-50 p-1 rounded transition" title="Edit"><i class="ti ti-pencil"></i></button>
                            <button onclick="deletePlan(${row.id_plan})" class="text-red-400 hover:bg-red-50 p-1 rounded transition" title="Hapus"><i class="ti ti-trash"></i></button>
                        </div>
                    `;
                } else {
                    actionButtons = `<div class="text-center"><i class="ti ti-lock text-slate-300 text-xs" title="Terkunci"></i></div>`;
                }
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="sticky-col c-blok bg-white text-xs font-bold pl-4 text-slate-700 border-l-4 border-l-transparent hover:border-l-cyan-500 transition">${row.blok}</td>
                    <td class="sticky-col c-rencana text-xs text-slate-600 bg-slate-50/50">${Number(row.rencana).toLocaleString('id-ID')}</td>
                    <td class="sticky-col c-sat text-[10px] text-slate-400 bg-slate-50 text-center font-bold">${row.satuan}</td>
                    ${cells}
                    <td class="text-right font-bold text-xs bg-slate-50 border-l border-slate-200 text-slate-800 px-2" id="tot-${row.id_plan}">${Number(dynamicTotal).toLocaleString('id-ID')}</td>
                    <td class="text-right font-bold text-xs ${variance<0?'text-red-500':'text-cyan-600'} px-2" id="var-${row.id_plan}">${Number(variance).toLocaleString('id-ID')}</td>
                    <td class="bg-white">
                        ${actionButtons}
                    </td>`;
                tbody.appendChild(tr);
            });
        }

        // RENDER SUBTOTAL
        if(hasData) {
            for (const [key, data] of Object.entries(recaps)) {
                if(data.rencana > 0 || data.realisasi > 0) {
                    let subCells = '';
                    let subTotalFilter = 0;
                    for(let i=start; i<=end; i++) {
                        const val = data.days[i];
                        subTotalFilter += val;
                        subCells += `<td class="text-right text-[10px] font-bold text-slate-700 border-r border-slate-200 bg-inherit">${val==0?'-':Number(val).toLocaleString('id-ID')}</td>`;
                    }
                    const subVar = subTotalFilter - data.rencana;
                    
                    let bgClass = 'sub-fisik';
                    if(key === 'TENAGA') bgClass = 'sub-tenaga';
                    if(key === 'KIMIA') bgClass = 'sub-kimia';
                    if(key === 'CAMPURAN') bgClass = 'sub-campuran';

                    const trSub = document.createElement('tr');
                    trSub.className = `subtotal-row border-t border-slate-300 ${bgClass}`;
                    trSub.innerHTML = `
                        <td class="subtotal-sticky font-bold text-[10px] text-slate-600 uppercase text-right pr-4 italic" style="background:inherit">${data.label}</td>
                        <td class="subtotal-sticky text-right text-[10px] font-bold text-slate-700" style="background:inherit">${Number(data.rencana).toLocaleString('id-ID')}</td>
                        <td class="subtotal-sticky text-center text-[10px] text-slate-500 font-bold" style="background:inherit">${data.satuan}</td>
                        ${subCells}
                        <td class="text-right font-bold text-xs text-slate-800 border-l border-slate-300 px-2" style="background:inherit">${Number(subTotalFilter).toLocaleString('id-ID')}</td>
                        <td class="text-right font-bold text-xs ${subVar<0?'text-red-600':'text-cyan-700'} px-2" style="background:inherit">${Number(subVar).toLocaleString('id-ID')}</td>
                        <td style="background:inherit"></td>
                    `;
                    tbody.appendChild(trSub);
                }
            }
        }
    });
}

// --- 5. LOGIC LAINNYA ---
async function saveCell(input) {
    const val = parseFloat(input.value) || 0;
    input.value = val === 0 ? '' : val; 

    const statusEl = $('save-status');
    statusEl.innerHTML = '<i class="ti ti-loader animate-spin"></i> Menyimpan...';
    statusEl.classList.remove('hidden', 'text-cyan-600', 'bg-cyan-50', 'border-cyan-100');
    statusEl.classList.add('text-amber-600', 'bg-amber-50', 'border-amber-100');

    const fd = new FormData();
    fd.append('action', 'store_cell');
    fd.append('id_plan', input.dataset.pid);
    fd.append('id_job', input.dataset.jid);
    fd.append('day', input.dataset.day);
    fd.append('value', val);
    
    // Context Info
    fd.append('kebun_id', $('h_kebun_id').value);
    fd.append('unit_id', $('h_unit_id').value);
    fd.append('tahun', $('f_tahun').value);
    fd.append('bulan', $('f_bulan').value);

    try {
        const res = await fetch('kertas_kerja_crud.php', {method:'POST', body:fd});
        const j = await res.json();
        
        if(j.success) {
            statusEl.innerHTML = '<i class="ti ti-check"></i> Tersimpan';
            statusEl.classList.remove('text-amber-600', 'bg-amber-50', 'border-amber-100');
            statusEl.classList.add('text-cyan-600', 'bg-cyan-50', 'border-cyan-100');
            
            setTimeout(() => statusEl.classList.add('hidden'), 2000);
            updateGlobalDataLocal(input.dataset.pid, input.dataset.day, val);
            recalcRow(input);
        } else {
            Swal.fire('Gagal Simpan', j.message, 'error');
        }
    } catch(e) { console.error(e); }
}

function updateGlobalDataLocal(planId, day, value) {
    GLOBAL_DATA.forEach(group => {
        group.items.forEach(item => {
            if(item.id_plan == planId) {
                if(!item.days) item.days = [];
                item.days[day] = value;
            }
        });
    });
}

function recalcRow(input) {
    const tr = input.closest('tr');
    if(!tr) return;
    const inputs = tr.querySelectorAll('.inp-cell');
    let total = 0;
    inputs.forEach(i => total += (parseFloat(i.value) || 0));
    
    const pid = input.dataset.pid;
    const tdTot = document.getElementById(`tot-${pid}`);
    const tdVar = document.getElementById(`var-${pid}`);
    const rencanaRaw = tr.querySelector('.c-rencana').innerText.replace(/\./g,'').replace(/,/g,'.');
    const rencana = parseFloat(rencanaRaw) || 0;
    
    if(tdTot) tdTot.innerText = total.toLocaleString('id-ID');
    const variance = total - rencana;
    if(tdVar) {
        tdVar.innerText = variance.toLocaleString('id-ID');
        tdVar.className = `text-right font-bold text-xs ${variance<0?'text-red-500':'text-cyan-600'} px-2`;
    }
}

function downloadExcel() {
    const unitId = $('h_unit_id').value;
    const kebunId = $('h_kebun_id').value;
    const tahun = $('f_tahun').value;
    const bulan = $('f_bulan').value;
    window.location.href = `cetak/kertas_kerja_excel.php?unit_id=${unitId}&kebun_id=${kebunId}&tahun=${tahun}&bulan=${bulan}`;
}

function downloadPDF() {
    const unitId = $('h_unit_id').value;
    const kebunId = $('h_kebun_id').value;
    const tahun = $('f_tahun').value;
    const bulan = $('f_bulan').value;
    window.open(`cetak/kertas_kerja_pdf.php?unit_id=${unitId}&kebun_id=${kebunId}&tahun=${tahun}&bulan=${bulan}`, '_blank');
}

function validateKey(evt) {
    if (!/[0-9.]/.test(evt.key)) evt.preventDefault();
}

// --- CRUD MODAL LOGIC ---

function addBlock(jid, jname, sat) {
    $('form-data').reset();
    $('modal-title').innerText = 'TAMBAH BLOK BARU';
    $('form_id').value = ''; 
    
    $('form_kebun').value = $('h_kebun_id').value;
    $('form_unit').value = $('h_unit_id').value;
    $('form_tahun').value = $('f_tahun').value;
    $('form_bulan').value = $('f_bulan').value;
    
    $('form_job_id').value = jid;
    $('form_job_nama').innerText = jname;
    $('form_satuan').value = sat;
    
    $('modal-form').classList.remove('hidden'); $('modal-form').classList.add('flex');
}

function editBlock(json) {
    if(IS_STAF) {
        Swal.fire('Akses Ditolak', 'Staf tidak memiliki izin untuk mengedit data.', 'error');
        return;
    }

    const r = JSON.parse(decodeURIComponent(json));
    $('form-data').reset();
    $('form_kebun').value = $('h_kebun_id').value;
    $('form_unit').value = $('h_unit_id').value;
    $('form_tahun').value = $('f_tahun').value;
    $('form_bulan').value = $('f_bulan').value;
    
    $('form_job_id').value = r.job_id;
    $('form_job_nama').innerText = r.job_nama;
    $('form_satuan').value = r.satuan;
    
    $('modal-title').innerText = 'EDIT RENCANA BLOK';
    $('form_id').value = r.id_plan; 
    $('form_blok').value = r.blok;
    $('form_fisik').value = r.rencana;
    
    $('modal-form').classList.remove('hidden'); $('modal-form').classList.add('flex');
}

function closeModal() { $('modal-form').classList.add('hidden'); }

$('form-data').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const currentId = $('form_id').value;
    if(IS_STAF && currentId !== "") {
        Swal.fire('Akses Ditolak', 'Staf tidak boleh mengubah data yang sudah ada.', 'error');
        return;
    }

    const fd = new FormData(e.target);
    const res = await fetch('kertas_kerja_crud.php', {method:'POST', body:fd});
    const j = await res.json();
    if(j.success) { closeModal(); loadSheet(); } else Swal.fire('Gagal', j.message, 'error');
});

async function deletePlan(id) {
    if(IS_STAF) {
        Swal.fire('Akses Ditolak', 'Staf tidak memiliki izin untuk menghapus data.', 'error');
        return;
    }

    if(await Swal.fire({title:'Hapus?', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33'}).then(r=>r.isConfirmed)) {
        const fd = new FormData(); fd.append('action','delete_plan'); fd.append('id',id);
        const res = await fetch('kertas_kerja_crud.php', {method:'POST', body:fd});
        if((await res.json()).success) loadSheet();
    }
}

document.querySelectorAll('.filter-act').forEach(el => el.addEventListener('change', loadSheet));
$('search_input').addEventListener('keyup', applySearchAndRender);
loadSheet();
</script>

<?php include_once '../layouts/footer.php'; ?>