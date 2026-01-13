<?php
// pages/pemeliharaan_pn.php — Rekap PN (Sticky Header & Realtime Filter)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php");
  exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

/* ===== Inisialisasi Filter Awal (jika dibuka via link) ===== */
$f_tahun   = ($_GET['tahun'] ?? '') === '' ? (int)date('Y') : (int)$_GET['tahun'];
$f_hk      = trim((string)($_GET['hk'] ?? '')); 
$f_ket     = trim((string)($_GET['ket'] ?? ''));        
$f_jenis   = trim((string)($_GET['jenis'] ?? ''));      
$f_kebun   = (int)($_GET['kebun_id'] ?? 0);
$f_stoodId = (int)($_GET['stood_id'] ?? 0);       

/* Master dropdown untuk Filter & Modal */
$jenisMaster  = $conn->query("SELECT nama FROM md_pemeliharaan_pn ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
$kebunList    = $conn->query("SELECT id, nama_kebun AS nama FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$TENAGA       = $conn->query("SELECT id, kode FROM md_tenaga ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC);
$STOOD_ROWS   = $conn->query("SELECT id, COALESCE(NULLIF(kode,''), CONCAT('STD-',id)) AS kode, nama FROM md_jenis_bibitpn WHERE is_active=1 ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$monthKeys   = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des']; 

$COLS_TOTAL  = 6 + 1 + count($monthLabels) + 1 + 1 + 1 + ($isStaf ? 0 : 1);

$currentPage = 'pemeliharaan_pn';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* --- CONTAINER UTAMA STICKY --- */
  .sticky-container {
    max-height: 72vh;
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }

  /* Tabel Styling */
  table.rekap {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1600px;
  }

  /* --- BORDERS --- */
  table.rekap th, table.rekap td {
    padding: 0.65rem 0.75rem;
    font-size: 0.85rem;
    white-space: nowrap;
    vertical-align: middle;
    border-left: 1px solid #f3f4f6;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0; 
  }

  table.rekap th:last-child, 
  table.rekap td:last-child {
    border-right: none;
  }

  /* --- STICKY HEADER --- */
  table.rekap thead th {
    position: sticky;
    background: #059fd3;
    color: #fff;
    z-index: 10;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.75rem;
  }

  /* Baris 1: Tahun, Kebun, dll */
  table.rekap thead tr:nth-child(1) th {
    top: 0;
    height: 50px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
  }

  /* Baris 2: Bulan Jan-Des */
  table.rekap thead tr:nth-child(2) th {
    top: 50px;
    height: 40px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 9;
  }

  /* Styling Group & Data */
  tr.group-head td {
    background: linear-gradient(90deg, #eff6ff 0%, #f1f5f9 100%);
    border-top: 1px solid #bfdbfe;
    border-bottom: 1px solid #bfdbfe;
    color: #1e3a8a;
    font-weight: 700;
    padding-top: 0.8rem;
    padding-bottom: 0.8rem;
    position: sticky;
    left: 0;
  } 
  
  tr.sum-stood td { 
    background: #f0fdf4; 
    font-weight: 700; 
    color: #166534;
    border-top: 2px solid #bbf7d0;
  }

  table.rekap tbody tr:hover td { background-color: #f8fafc; }
  table.rekap tbody tr.sum-stood:hover td { background-color: #dcfce7; }

  /* Utilities */
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .delta-pos { color: #dc2626; font-weight: 700; } 
  .delta-neg { color: #16a34a; font-weight: 700; }
  .cell-anggaran { background: rgba(5, 159, 211, 0.05); font-weight: 600; color: #0c4a6e; }

  /* Toolbar Grid */
  .toolbar { display: grid; grid-template-columns: repeat(12, 1fr); gap: 0.75rem; margin-bottom: 1rem; }
  .toolbar > * { grid-column: span 12; }
  @media (min-width: 768px) {
    .toolbar > .md-span-2 { grid-column: span 2; }
    .toolbar > .md-span-3 { grid-column: span 3; }
    .toolbar > .md-span-4 { grid-column: span 4; }
  }
  .btn { display: inline-flex; align-items: center; gap: .5rem; border: none; background: #059fd3; color: #fff; border-radius: .5rem; padding: .5rem 1rem; font-size: 0.875rem; cursor: pointer; transition: all 0.2s; }
  .btn:hover { background: #0386b3; }
  .btn-gray { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
  .btn-gray:hover { background: #f1f5f9; }
  .act { display: inline-flex; justify-content: center; align-items: center; width: 32px; height: 32px; border-radius: .375rem; border: 1px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer; transition: 0.2s; }
  .act:hover { background: #f8fafc; border-color: #cbd5e1; }
  
  /* Loading Overlay Kecil di tabel saat filter jalan */
  .table-loading { opacity: 0.5; pointer-events: none; }
</style>

<div class="space-y-6">
  <div class="flex justify-between items-center">
  <h1 class="text-2xl font-bold text-gray-800">
      Pemeliharaan PN 
      <span id="loading-indicator" class="text-sm font-normal text-blue-600 hidden ml-2">
          <i class="ti ti-loader animate-spin"></i> Memuat...
      </span>
  </h1>

  <div class="flex gap-2">
      <button onclick="exportData('excel')" class="btn shadow-sm" style="background-color: #236f9eff;">
          <i class="ti ti-file-spreadsheet"></i> <span class="hidden md:inline">Excel</span>
      </button>

      <button onclick="exportData('pdf')" class="btn shadow-sm" style="background-color: #ef4444a7;">
          <i class="ti ti-file-type-pdf"></i> <span class="hidden md:inline">PDF</span>
      </button>

      <?php if (!$isStaf): ?>
        <button id="btn-add" class="btn shadow-md"><i class="ti ti-plus"></i> Tambah Data</button>
      <?php endif; ?>
  </div>
</div>

  <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100 grid grid-cols-2 md:grid-cols-12 gap-3 items-end mb-4">
    
    <div class="col-span-1 md:col-span-1">
      <label class="text-[11px] font-bold text-gray-500 uppercase mb-1 block truncate">Tahun</label>
      <input type="number" id="filter_tahun" min="2000" max="2100"
        value="<?= htmlspecialchars((string)$f_tahun, ENT_QUOTES) ?>" 
        class="filter-input w-full border border-gray-300 rounded px-2 py-1.5 focus:ring-2 focus:ring-blue-200 outline-none transition">
    </div>

    <div class="col-span-2 md:col-span-2">
      <label class="text-[11px] font-bold text-gray-500 uppercase mb-1 block truncate">Kebun</label>
      <select id="filter_kebun" class="filter-input w-full border border-gray-300 rounded px-2 py-1.5 focus:ring-2 focus:ring-blue-200 outline-none transition">
        <option value="0">Semua</option>
        <?php foreach ($kebunList as $kb): ?>
          <option value="<?= (int)$kb['id'] ?>" <?= $f_kebun === (int)$kb['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kb['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-span-2 md:col-span-3">
      <label class="text-[11px] font-bold text-gray-500 uppercase mb-1 block truncate">Jenis Pekerjaan</label>
      <select id="filter_jenis" class="filter-input w-full border border-gray-300 rounded px-2 py-1.5 focus:ring-2 focus:ring-blue-200 outline-none transition">
        <option value="">Semua Jenis</option>
        <?php foreach ($jenisMaster as $jn): ?>
          <option value="<?= htmlspecialchars($jn, ENT_QUOTES) ?>" <?= $f_jenis === $jn ? 'selected' : '' ?>><?= htmlspecialchars($jn) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-span-1 md:col-span-1">
      <label class="text-[11px] font-bold text-gray-500 uppercase mb-1 block truncate">HK</label>
      <select id="filter_hk" class="filter-input w-full border border-gray-300 rounded px-2 py-1.5 focus:ring-2 focus:ring-blue-200 outline-none transition">
        <option value="">Semua</option>
        <?php foreach ($TENAGA as $t): ?>
          <option value="<?= htmlspecialchars($t['id']) ?>" <?= $f_hk == $t['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['kode']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-span-2 md:col-span-2">
      <label class="text-[11px] font-bold text-gray-500 uppercase mb-1 block truncate">Stood</label>
      <select id="filter_stood" class="filter-input w-full border border-gray-300 rounded px-2 py-1.5 focus:ring-2 focus:ring-blue-200 outline-none transition">
        <option value="0">Semua</option>
        <?php foreach ($STOOD_ROWS as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $f_stoodId === (int)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-span-2 md:col-span-3">
      <label class="text-[11px] font-bold text-gray-500 uppercase mb-1 block truncate">Cari Ket.</label>
      <div class="relative">
        <input id="filter_ket" value="<?= htmlspecialchars($f_ket, ENT_QUOTES) ?>" class="filter-input w-full border border-gray-300 rounded px-2 py-1.5 outline-none transition pr-8" placeholder="Keterangan...">
        <button id="btn-reset" class="absolute right-1 top-1 text-gray-400 hover:text-red-500 p-0.5" title="Reset Semua Filter"><i class="ti ti-x"></i></button>
      </div>
    </div>

  </div>
  <div class="px-1 text-sm text-gray-600 font-medium flex justify-between">
      <span>Menampilkan Data PN • Tahun <b id="disp-tahun"><?= htmlspecialchars((string)$f_tahun) ?></b></span>
      <span class="text-xs text-gray-400 italic">*Data terfilter otomatis</span>
  </div>

  

  <div class="sticky-container">
      <table class="rekap" id="pn-table">
        <thead>
          <tr>
            <th rowspan="2" style="min-width:60px;">Tahun</th>
            <th rowspan="2" style="min-width:120px;">Kebun</th>
            <th rowspan="2" style="min-width:150px;">Jenis Pekerjaan</th>
            <th rowspan="2" style="min-width:150px;">Ket</th>
            <th rowspan="2" style="min-width:60px;">HK</th>
            <th rowspan="2" style="min-width:60px;">Sat</th>
            
            <th rowspan="2" class="text-right" style="min-width:110px;">Anggaran Thn</th>
            <th colspan="<?= count($monthLabels) ?>" class="text-center">Realisasi Bulanan</th>
            <th rowspan="2" class="text-right" style="min-width:110px;">Jumlah Realisasi</th>
            <th rowspan="2" class="text-right" style="min-width:100px;">+/- Anggaran</th>
            <th rowspan="2" class="text-center" style="min-width:80px;">%</th>
            <?php if (!$isStaf): ?><th rowspan="2" class="text-center" style="min-width:100px;">Aksi</th><?php endif; ?>
          </tr>
          <tr>
            <?php foreach ($monthLabels as $m): ?>
              <th class="text-center" style="min-width:80px;"><?= htmlspecialchars($m) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="pn-body">
          <tr>
            <td colspan="<?= (int)$COLS_TOTAL ?>" class="text-center py-10 text-gray-500"><i class="ti ti-loader animate-spin text-2xl"></i><br>Memuat Data...</td>
          </tr>
        </tbody>
      </table>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
    // --- FUNGSI EXPORT (DITARUH DI LUAR AGAR BISA DIAKSES HTML ONCLICK) ---
    function exportData(type) {
        // Ambil value dari elemen filter yang sudah ada di halaman
        const params = new URLSearchParams({
            tahun: document.getElementById('filter_tahun').value,
            kebun_id: document.getElementById('filter_kebun').value,
            jenis: document.getElementById('filter_jenis').value,
            hk: document.getElementById('filter_hk').value,
            stood_id: document.getElementById('filter_stood').value,
            ket: document.getElementById('filter_ket').value
        });

        // Tentukan file tujuan (pastikan folder 'cetak' sudah dibuat)
        const baseUrl = 'cetak/'; 
        const file = type === 'pdf' ? 'pemeliharaan_pn_pdf.php' : 'pemeliharaan_pn_excel.php';
        
        // Buka di tab baru
        window.open(baseUrl + file + '?' + params.toString(), '_blank');
    }

    // --- DOM CONTENT LOADED ---
    document.addEventListener('DOMContentLoaded', () => {
        const IS_STAF = <?= $isStaf ? 'true' : 'false' ?>;
        const CSRF = '<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>';
        const months = <?= json_encode($monthKeys) ?>;
        const COLS_TOTAL = <?= (int)$COLS_TOTAL ?>;

        const nf = (n) => Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const dash = (n) => (Number(n || 0) === 0 ? '<span class="text-gray-300">—</span>' : nf(n));
        const dashPct = (n) => (Number(n || 0) === 0 ? '<span class="text-gray-300">—</span>' : nf(n) + '%');

        // MAPPING DATA
        <?php
        $stoodMap = [];
        foreach ($STOOD_ROWS as $s) { $stoodMap[(int)$s['id']] = $s['nama']; }
        ?>
        const STOOD_MAP = <?= json_encode($stoodMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        // --- ELEMENTS ---
        const tbody = document.getElementById('pn-body');
        const loadingInd = document.getElementById('loading-indicator');
        const tableEl = document.getElementById('pn-table');
        const dispTahun = document.getElementById('disp-tahun');
        const btnAdd = document.getElementById('btn-add');

        // Filter Inputs
        const fTahun = document.getElementById('filter_tahun');
        const fKebun = document.getElementById('filter_kebun');
        const fJenis = document.getElementById('filter_jenis');
        const fHk = document.getElementById('filter_hk');
        const fStood = document.getElementById('filter_stood');
        const fKet = document.getElementById('filter_ket');
        const btnReset = document.getElementById('btn-reset');

        if (btnAdd) btnAdd.addEventListener('click', () => openForm({ tahun: fTahun.value }));

        // --- MAIN FUNCTION: LOAD DATA ---
        let abortController = null; 
        
        async function loadData() {
            // UI Loading State
            tbody.classList.add('table-loading');
            loadingInd.classList.remove('hidden');
            
            // Update Tahun Display
            dispTahun.textContent = fTahun.value;

            // Batalkan request sebelumnya jika ada
            if (abortController) abortController.abort();
            abortController = new AbortController();

            // Ambil value dari input
            const params = new URLSearchParams({
                action: 'list',
                tahun: fTahun.value,
                kebun_id: fKebun.value,
                jenis: fJenis.value,
                hk: fHk.value, 
                stood_id: fStood.value,
                ket: fKet.value
            });

            const newUrl = `${window.location.pathname}?${params.toString()}`;
            window.history.pushState({path: newUrl}, '', newUrl);

            try {
                const res = await fetch('pemeliharaan_pn_crud.php?' + params.toString(), { 
                    credentials: 'same-origin',
                    signal: abortController.signal
                });
                const j = await res.json();
                
                tbody.classList.remove('table-loading');
                loadingInd.classList.add('hidden');

                if (!j || j.success !== true) {
                    tbody.innerHTML = `<tr><td colspan="${COLS_TOTAL}" class="text-center py-6 text-red-600 font-bold"><i class="ti ti-alert-circle"></i> ${(j && (j.message||'')) || 'Error memuat data'}</td></tr>`;
                    return;
                }

                const rows = j.rows || [];
                if (!rows.length) {
                    tbody.innerHTML = `<tr><td colspan="${COLS_TOTAL}" class="text-center py-10 text-gray-400 italic">Tidak ada data ditemukan pada filter ini.</td></tr>`;
                    return;
                }

                renderTable(rows, j.kebun_map || {}, j.stood_map || STOOD_MAP, j.stood_order);
            } catch (err) {
                if (err.name === 'AbortError') return; 
                tbody.classList.remove('table-loading');
                loadingInd.classList.add('hidden');
                tbody.innerHTML = `<tr><td colspan="${COLS_TOTAL}" class="text-center py-6 text-red-600">Gagal: ${err.message}</td></tr>`;
            }
        }

        function renderTable(rows, kebunMap, stoodMap, stoodOrder) {
            const order = stoodOrder || Object.keys(stoodMap).map(k => Number(k));
            const byStood = {};
            
            order.forEach(id => byStood[id] = []);
            rows.forEach(r => {
            const sid = Number(r.stood_id || 0);
            if (!byStood[sid]) byStood[sid] = [];
            byStood[sid].push(r);
            });

            let html = '';
            const emptyRow = (nm) => `<tr class="text-gray-400 italic"><td colspan="${COLS_TOTAL}" class="text-center py-2">Tidak ada data untuk Stood <b>${nm}</b></td></tr>`;

            for (const sid of order) {
                const stoodName = stoodMap[sid] || '(Tanpa Stood)';
                html += `<tr class="group-head"><td colspan="${COLS_TOTAL}"><i class="ti ti-folder-open text-lg mr-1 align-text-bottom"></i> ${stoodName}</td></tr>`;

                const list = (byStood[sid] || []).sort((a, b) => String(a.kebun_nama || a.kebun_id || '').localeCompare(String(b.kebun_nama || b.kebun_id || '')) || (a.id - b.id));
                
                if (!list.length) {
                    html += emptyRow(stoodName);
                    continue;
                }

                const sumStood = { anggaran: 0, jumlah: 0 };
                const perBulan = Object.fromEntries(months.map(m => [m, 0]));

                for (const r of list) {
                    const totalRealisasi = months.reduce((a, m) => a + (parseFloat(r[m] || 0) || 0), 0);
                    const anggaran = (parseFloat(r.anggaran_tahun || 0) || 0);
                    const delt = totalRealisasi - anggaran;
                    const prog = anggaran > 0 ? (totalRealisasi / anggaran * 100) : 0;
                    const deltCls = (delt < 0) ? 'delta-neg' : (delt > 0 ? 'delta-pos' : '');

                    sumStood.anggaran += anggaran;
                    sumStood.jumlah += totalRealisasi; 
                    months.forEach(m => {
                        perBulan[m] += (parseFloat(r[m] || 0) || 0);
                    });

                    const safe = (x) => (x == null ? '' : String(x));

                    html += `
                    <tr>
                    <td>${safe(r.tahun)}</td>
                    <td>${safe(r.kebun_nama || kebunMap[r.kebun_id] || '')}</td>
                    <td>${safe(r.jenis_nama)}</td>
                    <td class="text-gray-600 italic">${safe(r.ket)}</td>
                    <td>${safe(r.hk)}</td>
                    <td>${safe(r.satuan)}</td>
                    <td class="text-right cell-anggaran">${dash(r.anggaran_tahun)}</td>
                    ${months.map(m=>`<td class="text-right text-gray-700">${dash(r[m])}</td>`).join('')}
                    <td class="text-right font-semibold text-gray-900">${dash(totalRealisasi)}</td>
                    <td class="text-right ${deltCls}">${Number(delt||0)===0?'—':nf(delt)}</td>
                    <td class="text-right text-sm">${anggaran>0 ? dashPct(prog) : '—'}</td>
                    ${!IS_STAF ? `
                    <td class="text-center">
                        <div class="flex justify-center gap-1">
                        <button class="act text-blue-600 hover:text-blue-800" title="Edit" data-edit='${JSON.stringify(r).replaceAll("'","&apos;")}'><i class="ti ti-pencil"></i></button>
                        <button class="act text-red-600 hover:text-red-800" title="Hapus" data-del="${safe(r.id)}"><i class="ti ti-trash"></i></button>
                        </div>
                    </td>` : ``}
                    </tr>`;
                }

                const deltaStood = sumStood.jumlah - sumStood.anggaran;
                
                html += `
                <tr class="sum-stood text-sm">
                    <td colspan="6" class="text-right pr-4 uppercase">Total ${stoodName}</td>
                    <td class="text-right cell-anggaran">${dash(sumStood.anggaran)}</td>
                    ${months.map(m=>`<td class="text-right">${dash(perBulan[m])}</td>`).join('')}
                    <td class="text-right">${dash(sumStood.jumlah)}</td>
                    <td class="text-right ${(deltaStood<0)?'delta-neg':(deltaStood>0?'delta-pos':'')}">${Number(deltaStood||0)===0?'—':nf(deltaStood)}</td>
                    <td class="text-right">${sumStood.anggaran>0 ? dashPct(sumStood.jumlah/sumStood.anggaran*100) : '—'}</td>
                    <?= $isStaf ? '' : '<td></td>' ?>
                </tr>`;
            }
            
            tbody.innerHTML = html;
            attachEvents(); // Attach tombol edit/hapus
        }

        // --- EVENT HANDLERS ---
        function debounce(func, timeout = 500){
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => { func.apply(this, args); }, timeout);
            };
        }
        const delayedLoad = debounce(() => loadData());

        [fKebun, fJenis, fStood, fHk].forEach(el => {
            el.addEventListener('change', loadData);
        });
        [fTahun, fKet].forEach(el => {
            el.addEventListener('input', delayedLoad);
        });

        btnReset.addEventListener('click', () => {
            const d = new Date();
            fTahun.value = d.getFullYear();
            fKebun.value = "0";
            fJenis.value = "";
            fHk.value = ""; 
            fStood.value = "0";
            fKet.value = "";
            loadData();
        });

        function attachEvents() {
            if (IS_STAF) return;
            
            tbody.querySelectorAll('[data-edit]').forEach(b => {
            b.addEventListener('click', () => {
                try { openForm(JSON.parse(b.dataset.edit || '{}')); } catch (e) {}
            });
            });

            tbody.querySelectorAll('[data-del]').forEach(b => {
            b.addEventListener('click', async () => {
                const id = b.dataset.del;
                if (!id) return;
                const y = await Swal.fire({
                title: 'Hapus data ini?', icon: 'warning',
                showCancelButton: true, confirmButtonColor:'#d33', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
                });
                if (!y.isConfirmed) return;
                
                const fd = new FormData();
                fd.append('csrf_token', CSRF);
                fd.append('action', 'delete');
                fd.append('id', id);
                
                try {
                    const r = await fetch('pemeliharaan_pn_crud.php', { method: 'POST', body: fd });
                    const jj = await r.json();
                    if (jj && jj.success) {
                        Swal.fire('Berhasil', 'Data dihapus', 'success');
                        loadData(); 
                    } else {
                        Swal.fire('Gagal', (jj && (jj.message || 'Error')) || 'Error', 'error');
                    }
                } catch(e) { Swal.fire('Gagal', 'Network error', 'error'); }
            });
            });
        }

        // --- INITIAL LOAD ---
        loadData();

        /* ===== Modal (Create/Update) ===== */
        let MODAL = null;
        function modalTpl() {
        return `
    <div id="pn-modal" class="fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl transform transition-all scale-100">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50 rounded-t-2xl">
        <h3 id="pn-title" class="font-bold text-xl text-gray-800">Form PN</h3>
        <button id="pn-x" class="text-gray-400 hover:text-gray-600 text-2xl transition" type="button">&times;</button>
        </div>
        <form id="pn-form" class="p-6 grid grid-cols-12 gap-4 max-h-[80vh] overflow-y-auto">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
        <input type="hidden" name="action" value="store">
        <input type="hidden" name="id" value="">

        <div class="col-span-2">
            <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Tahun</label>
            <input name="tahun" type="number" min="2000" max="2100" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none" required value="<?= htmlspecialchars((string)$f_tahun, ENT_QUOTES) ?>">
        </div>

        <div class="col-span-4">
            <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Kebun</label>
            <select name="kebun_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">— Pilih —</option>
            <?php foreach ($kebunList as $kb) { echo '<option value="' . (int)$kb['id'] . '">' . htmlspecialchars($kb['nama']) . '</option>'; } ?>
            </select>
        </div>

        <div class="col-span-6">
            <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Stood</label>
            <select name="stood_id" id="stood_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none" required>
            <option value="">— Pilih —</option>
            <?php foreach ($STOOD_ROWS as $s) { echo '<option value="' . (int)$s['id'] . '">' . htmlspecialchars($s['nama']) . '</option>'; } ?>
            </select>
            <input type="hidden" name="stood" id="stood_hidden" value="">
        </div>

        <div class="col-span-6">
            <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Jenis Pekerjaan</label>
            <input id="jenis_text" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none" list="jenis_datalist" placeholder="Ketik untuk mencari..." autocomplete="off" required>
            <datalist id="jenis_datalist">
            <?php foreach ($conn->query("SELECT id, nama FROM md_pemeliharaan_pn ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC) as $jr) {
                echo '<option data-id="' . $jr['id'] . '" value="' . htmlspecialchars($jr['nama']) . '"></option>';
            } ?>
            </datalist>
            <input type="hidden" name="jenis_id" id="jenis_id">
        </div>

        <div class="col-span-3">
            <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">HK (Tenaga)</label>
            <select name="hk_id" id="hk_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none">
            <option value="">— Pilih —</option>
            <?php foreach ($TENAGA as $t) { echo '<option value="' . (int)$t['id'] . '">' . htmlspecialchars($t['kode']) . '</option>'; } ?>
            </select>
            <input type="hidden" name="hk" id="hk_hidden" value="">
        </div>

        <div class="col-span-3">
            <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Satuan</label>
            <input name="satuan" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ha">
        </div>

        <div class="col-span-12">
            <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Anggaran 1 Tahun</label>
            <input name="anggaran_tahun" inputmode="decimal" class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono font-bold text-blue-800 focus:ring-2 focus:ring-blue-500 outline-none">
        </div>

        <div class="col-span-12 border-t my-2"></div>
        <div class="col-span-12 text-sm font-bold text-gray-500 mb-1">Realisasi Bulanan</div>

        <?php foreach ($monthKeys as $m): ?>
            <div class="col-span-2">
            <label class="text-[10px] font-bold text-gray-500 uppercase block text-center"><?= strtoupper($m) ?></label>
            <input name="<?= $m ?>" inputmode="decimal" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0">
            </div>
        <?php endforeach; ?>

        <div class="col-span-12 mt-2">
            <label class="text-xs font-bold text-gray-600 uppercase mb-1 block">Keterangan</label>
            <textarea name="ket" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Catatan..."></textarea>
        </div>

        <div class="col-span-12 flex justify-end gap-3 mt-4 pt-4 border-t border-gray-100">
            <button type="button" id="pn-cancel" class="btn btn-gray px-5">Batal</button>
            <button class="btn px-6 shadow-lg shadow-blue-500/30">Simpan Data</button>
        </div>
        </form>
    </div>
    </div>`
        }

        function openForm(d = {}) {
        if (MODAL) MODAL.remove();
        document.body.insertAdjacentHTML('beforeend', modalTpl());
        MODAL = document.getElementById('pn-modal');
        const F = document.getElementById('pn-form');
        const T = document.getElementById('pn-title');

        F.action.value = d.id ? 'update' : 'store';
        const fields = ['id', 'tahun', 'ket', 'satuan', 'anggaran_tahun', 'jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des'];
        fields.forEach(k => { if (F[k] !== undefined) F[k].value = (d[k] ?? ''); });

        if (F['kebun_id']) {
            if (d.kebun_id) { F['kebun_id'].value = d.kebun_id; }
            else if (d.kebun_nama) { [...F['kebun_id'].options].forEach(o => { if (o.text.trim() === String(d.kebun_nama || '').trim()) F['kebun_id'].value = o.value; }); }
        }

        const selStood = document.getElementById('stood_id');
        const hidStood = document.getElementById('stood_hidden');
        if (selStood && hidStood) {
            if (d.stood_id) { selStood.value = String(d.stood_id); hidStood.value = selStood.options[selStood.selectedIndex]?.text || ''; }
            else if (d.stood) { [...selStood.options].forEach(o => { if (o.text.trim() === String(d.stood || '').trim()) selStood.value = o.value; }); hidStood.value = d.stood || ''; }
            selStood.addEventListener('change', e => { hidStood.value = e.target.options[e.target.selectedIndex]?.text || ''; });
        }

        const jenisText = document.getElementById('jenis_text');
        const jenisId = document.getElementById('jenis_id');
        if (d.jenis_id) { jenisId.value = d.jenis_id; const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o => o.dataset.id == String(d.jenis_id)); if (opt) jenisText.value = opt.value; }
        else if (d.jenis_nama) { jenisText.value = d.jenis_nama; const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o => o.value.trim() === String(d.jenis_nama).trim()); if (opt) jenisId.value = opt.dataset.id || ''; }
        
        const syncJenis = () => { const val = jenisText.value.trim(); const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o => o.value.trim() === val); jenisId.value = opt ? (opt.dataset.id || '') : ''; };
        jenisText.addEventListener('change', syncJenis); jenisText.addEventListener('input', syncJenis);

        const selHK = document.getElementById('hk_id');
        const hidHK = document.getElementById('hk_hidden');
        if (selHK && hidHK) {
            if (d.hk_id) { selHK.value = String(d.hk_id); hidHK.value = selHK.options[selHK.selectedIndex]?.text || ''; }
            else if (d.hk) { [...selHK.options].forEach(o => { if (o.text.trim() === String(d.hk || '').trim()) selHK.value = o.value; }); hidHK.value = d.hk || ''; }
            selHK.addEventListener('change', e => { hidHK.value = e.target.options[e.target.selectedIndex]?.text || ''; });
        }

        T.textContent = (d.id ? 'Edit' : 'Tambah') + ' Data PN';

        const close = () => { MODAL?.remove(); MODAL = null; };
        document.getElementById('pn-x').onclick = close;
        document.getElementById('pn-cancel').onclick = close;

        F.onsubmit = async (e) => {
            e.preventDefault();
            if (!document.getElementById('stood_id').value) { await Swal.fire('Validasi', 'Silakan pilih Stood.', 'warning'); return; }
            if (!document.getElementById('jenis_id').value) { await Swal.fire('Validasi', 'Silakan pilih Jenis Pekerjaan dari daftar yang muncul.', 'warning'); jenisText.focus(); return; }

            const fd = new FormData(F);
            try {
            const r = await fetch('pemeliharaan_pn_crud.php', { method: 'POST', body: fd });
            const j = await r.json();
            if (j && j.success) { 
                await Swal.fire('Berhasil', j.message || 'Tersimpan', 'success'); 
                close(); 
                loadData(); 
            }
            else { const msg = (j && ((j.errors || []).map(x => '• ' + x).join('<br>') || j.message)) || 'Error'; Swal.fire('Gagal', msg, 'error'); }
            } catch (err) { Swal.fire('Gagal', err?.message || String(err), 'error'); }
        }
        }

        <?php if (!$isStaf): ?>
        document.addEventListener('keydown', (e) => {
            if (e.key.toLowerCase() === 'n' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault(); openForm({ tahun: fTahun.value });
            }
        });
        <?php endif; ?>
    });
</script>