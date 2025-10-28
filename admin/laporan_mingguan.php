<?php
// laporan_mingguan.php (server-safe, role-aware, clean-empty rendering)
session_start();

/* ====== PRODUCTION-SAFE ERROR HANDLING ====== */
ini_set('display_errors','0');
ini_set('log_errors','1');
error_reporting(E_ALL);
set_error_handler(function($sev,$msg,$file,$line){
  throw new ErrorException($msg, 0, $sev, $file, $line);
});
set_exception_handler(function($e){
  http_response_code(500);
  error_log("[LM] ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine());
  echo "<h2 style='font-family:system-ui; padding:16px;'>Terjadi kesalahan pada server.</h2>";
  exit;
});

/* ====== SESSION & CSRF ====== */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');
$userId   = (int)($_SESSION['user_id'] ?? 0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

/* ====== DB CONNECT ====== */
require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();
if (!($conn instanceof PDO)) {
  throw new Exception('DB connection failed: PDO tidak tersedia atau kredensial salah.');
}
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ====== UTIL ====== */
function norm_bulan($b){
  $map = [
    'january'=>'Januari','february'=>'Februari','march'=>'Maret','april'=>'April',
    'may'=>'Mei','june'=>'Juni','july'=>'Juli','august'=>'Agustus','september'=>'September',
    'october'=>'Oktober','november'=>'November','december'=>'Desember',
    'januari'=>'Januari','februari'=>'Februari','maret'=>'Maret','mei'=>'Mei',
    'juni'=>'Juni','juli'=>'Juli','agustus'=>'Agustus','oktober'=>'Oktober','november'=>'November','desember'=>'Desember'
  ];
  $k = strtolower(trim((string)$b));
  return $map[$k] ?? 'Januari';
}

$bulanListID = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow    = (int)date('Y');

/* ====== MASTER LISTS (dengan pengaman) ====== */
try {
  $kebunList = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log("[LM] md_kebun: ".$e->getMessage()); $kebunList = []; }

try {
  $unitList  = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log("[LM] units: ".$e->getMessage()); $unitList = []; }

try {
  // gunakan master mingguan
  $jenisPekerjaanList = $conn->query("SELECT id, nama FROM md_jenis_pekerjaan_mingguan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log("[LM] md_jenis_pekerjaan_mingguan: ".$e->getMessage()); $jenisPekerjaanList = []; }

/* ====== FILTERS ====== */
$f_kebun_id           = $_GET['kebun_id']           ?? ($kebunList[0]['id'] ?? '');
$f_unit_id            = $_GET['unit_id']            ?? ($unitList[0]['id'] ?? '');
$f_jenis_pekerjaan_id = $_GET['jenis_pekerjaan_id'] ?? ($jenisPekerjaanList[0]['id'] ?? '');
$f_bulan_raw          = $_GET['bulan']              ?? date('F');
$f_bulan              = norm_bulan($f_bulan_raw);
$f_tahun              = (int)($_GET['tahun']        ?? $tahunNow);
$f_minggu             = (int)($_GET['minggu']       ?? 1);
if ($f_minggu < 1 || $f_minggu > 5) $f_minggu = 1;

/* ====== LOAD META & DATA (minggu aktif) ====== */
$data = [];
$meta = [
  'judul_laporan' => 'LAPORAN PEMELIHARAAN KEBUN',
  'catatan'       => 'BATAS AKHIR PENGISIAN SETIAP HARI SABTU JAM 9 PAGI',
  'judul_minggu_1'=> 'MINGGU I','judul_minggu_2'=> 'MINGGU II','judul_minggu_3'=> 'MINGGU III',
  'judul_minggu_4'=> 'MINGGU IV','judul_minggu_5'=> 'MINGGU V'
];

if ($f_kebun_id !== '' && $f_unit_id !== '' && $f_jenis_pekerjaan_id !== '') {
  try {
    $stmtMeta = $conn->prepare("
      SELECT judul_laporan, catatan,
             COALESCE(judul_minggu_1,'MINGGU I') jm1, COALESCE(judul_minggu_2,'MINGGU II') jm2,
             COALESCE(judul_minggu_3,'MINGGU III') jm3, COALESCE(judul_minggu_4,'MINGGU IV') jm4,
             COALESCE(judul_minggu_5,'MINGGU V') jm5
      FROM laporan_mingguan_meta
      WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b
      LIMIT 1
    ");
    $stmtMeta->execute([':k'=>$f_kebun_id, ':jp'=>$f_jenis_pekerjaan_id, ':t'=>$f_tahun, ':b'=>$f_bulan]);
    if ($m = $stmtMeta->fetch(PDO::FETCH_ASSOC)) {
      $meta['judul_laporan'] = $m['judul_laporan'] ?: $meta['judul_laporan'];
      $meta['catatan']       = $m['catatan']       ?: $meta['catatan'];
      $meta['judul_minggu_1']= $m['jm1']; $meta['judul_minggu_2']= $m['jm2'];
      $meta['judul_minggu_3']= $m['jm3']; $meta['judul_minggu_4']= $m['jm4']; $meta['judul_minggu_5']= $m['jm5'];
    }
  } catch (Throwable $e) { error_log("[LM] laporan_mingguan_meta: ".$e->getMessage()); }

  try {
    $stmtData = $conn->prepare("
      SELECT blok, ts, pkwt, kng, tp FROM laporan_mingguan
      WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b AND minggu=:m AND afdeling=:afd
      ORDER BY blok
    ");
    $stmtData->execute([':k'=>$f_kebun_id, ':jp'=>$f_jenis_pekerjaan_id, ':t'=>$f_tahun, ':b'=>$f_bulan, ':m'=>$f_minggu, ':afd'=>$f_unit_id]);
    foreach ($stmtData->fetchAll(PDO::FETCH_ASSOC) as $row) {
      // simpan persis apa adanya dari DB
      $data[$row['blok']] = [
        'blok' => (string)$row['blok'],
        'ts'   => $row['ts'],
        'pkwt' => $row['pkwt'],
        'kng'  => $row['kng'],
        'tp'   => $row['tp'],
      ];
    }
  } catch (Throwable $e) { error_log("[LM] laporan_mingguan: ".$e->getMessage()); }
}

/* ====== DISPLAY NAMES ====== */
$namaKebun = ''; foreach ($kebunList as $k) if ((string)$k['id']===(string)$f_kebun_id) { $namaKebun = strtoupper($k['nama_kebun']); break; }
$namaUnit  = ''; foreach ($unitList as $u)  if ((string)$u['id']===(string)$f_unit_id)  { $namaUnit  = $u['nama_unit']; break; }
$namaJenisPekerjaan = ''; foreach ($jenisPekerjaanList as $jp) if ((string)$jp['id']===(string)$f_jenis_pekerjaan_id) { $namaJenisPekerjaan = strtoupper($jp['nama']); break; }

/* ====== HEADER ====== */
$currentPage = 'laporan_mingguan';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.report-table input{border:1px solid transparent;background:transparent;width:100%;padding:4px;border-radius:4px}
.report-table input.num-input{text-align:right}
.report-table.edit-mode input{border-color:#cbd5e1;background:#f8fafc}
.report-table input:focus{outline:2px solid #2563eb}
.header-input{font-weight:bold;text-align:center;border:1px solid transparent;background:transparent;width:100%;padding:2px}
.edit-mode .header-input{border-color:#cbd5e1;background:#f8fafc}
.afd-cell{position:relative; min-width:160px; vertical-align:top;}
.afd-input{font-weight:bold; text-align:center; width:100%; margin:6px 0;}
@media print{
  body{background:#fff}
  .no-print, .no-print *{ display:none !important; }
  .report-table{width:100%; border-collapse:collapse}
  .report-table td, .report-table th{ border:1px solid #000 !important; padding:6px !important; }
  .edit-mode .header-input,.report-table input{ border:0 !important; background:transparent !important; outline:none !important;}
}
</style>

<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-bold">Laporan Mingguan</h1>
    <p class="text-gray-500">Input data pemeliharaan mingguan per Afdeling.</p>
  </div>

  <form class="no-print bg-white/90 backdrop-blur p-5 md:p-6 rounded-2xl shadow-lg border border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-4 items-end"
        method="GET" autocomplete="off">
    <div class="col-span-1">
      <label class="block text-sm font-semibold text-gray-700 mb-1">Kebun</label>
      <select name="kebun_id"
              class="block w-full rounded-xl border-gray-300 bg-white/60 shadow-sm text-sm
                     focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
        <?php foreach ($kebunList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$f_kebun_id===(string)$k['id']?'selected':'' ?>>
            <?= htmlspecialchars($k['nama_kebun']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-span-1">
      <label class="block text-sm font-semibold text-gray-700 mb-1">Afdeling (AFD)</label>
      <select name="unit_id"
              class="block w-full rounded-xl border-gray-300 bg-white/60 shadow-sm text-sm
                     focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
        <?php foreach ($unitList as $u): ?>
          <option value="<?= $u['id'] ?>" <?= (string)$f_unit_id===(string)$u['id']?'selected':'' ?>>
            <?= htmlspecialchars($u['nama_unit']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-span-1">
      <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Pekerjaan (Mingguan)</label>
      <select name="jenis_pekerjaan_id"
              class="block w-full rounded-xl border-gray-300 bg-white/60 shadow-sm text-sm
                     focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
        <?php foreach ($jenisPekerjaanList as $j): ?>
          <option value="<?= $j['id'] ?>" <?= (string)$f_jenis_pekerjaan_id===(string)$j['id']?'selected':'' ?>>
            <?= htmlspecialchars($j['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-span-1 flex md:justify-end">
      <button type="submit"
              class="w-full md:w-auto inline-flex items-center justify-center gap-2
                     bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800
                     text-white font-semibold px-4 py-2.5 rounded-xl shadow
                     transition focus:outline-none focus:ring-2 focus:ring-emerald-500">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
          <path d="M11 3a1 1 0 0 1 1 1v7h7a1 1 0 1 1 0 2h-7v7a1 1 0 1 1-2 0v-7H3a1 1 0 1 1 0-2h7V4a1 1 0 0 1 1-1z"/>
        </svg>
        Tampilkan
      </button>
    </div>
  </form>

  <div id="report-container" class="bg-white p-4 rounded-xl shadow-md">
    <div class="text-center space-y-1 mb-4">
      <input type="text" id="meta-judul_laporan" class="header-input text-lg" value="<?= htmlspecialchars($meta['judul_laporan']) ?> <?= $namaKebun ?>" readonly>
      <input type="text" id="meta-jenis_pekerjaan" class="header-input text-lg" value="<?= htmlspecialchars($namaJenisPekerjaan) ?>" readonly>
      <input type="text" id="meta-periode" class="header-input text-lg" value="BULAN <?= strtoupper($f_bulan) ?> <?= $f_tahun ?>" readonly>
      <input type="text" id="meta-judul_minggu" class="header-input text-lg" value="<?= htmlspecialchars($meta['judul_minggu_'.$f_minggu]) ?>" readonly>
      <div class="pt-2">
        <input type="text" id="meta-catatan" class="w-full text-center text-red-600 font-semibold border-2 border-transparent bg-transparent" value="<?= htmlspecialchars($meta['catatan']) ?>" readonly>
      </div>
    </div>

    <div class="flex flex-wrap justify-end gap-2 mb-2 no-print">
      <?php if (!$isStaf): ?>
        <button id="btn-edit"   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"><i class="ti ti-pencil"></i><span>Edit</span></button>
        <button id="btn-save"   class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 hidden items-center gap-2"><i class="ti ti-device-floppy"></i><span>Simpan Minggu Ini</span></button>
        <button id="btn-cancel" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 hidden">Batal</button>
        <button id="btn-clear-draft" type="button" class="bg-orange-100 text-orange-800 px-4 py-2 rounded-lg hover:bg-orange-200">Hapus Draft</button>
        <button id="btn-reset" type="button" class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded-lg hover:bg-yellow-200">Reset Nilai</button>
      <?php endif; ?>
      
      <button id="btn-print" type="button" class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 flex items-center gap-2">
          <i class="ti ti-printer"></i><span>Cetak</span>
      </button>
    </div>

    <div id="week-tabs" class="mb-4 flex border-b border-gray-200 no-print">
      <?php for ($m = 1; $m <= 5; $m++): ?>
        <button data-week="<?= $m ?>" class="week-tab -mb-px border-b-2 py-2 px-4 text-sm font-medium transition-colors duration-200 <?= $f_minggu == $m ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
          <?= htmlspecialchars($meta['judul_minggu_'.$m]) ?>
        </button>
      <?php endfor; ?>
    </div>

    <table class="report-table min-w-full border-collapse border border-gray-400">
      <thead class="bg-gray-200">
      <tr class="text-center font-bold">
        <td class="border border-gray-400 p-2">AFD</td>
        <td class="border border-gray-400 p-2">Blok</td>
        <td class="border border-gray-400 p-2">TS</td>
        <td class="border border-gray-400 p-2">PKWT</td>
        <td class="border border-gray-400 p-2">KNG</td>
        <td class="border border-gray-400 p-2">TP</td>
        <td class="border border-gray-400 p-2">JUMLAH</td>
      </tr>
      </thead>
      <tbody>
      <?php $rowCount = 21; $dataKeys = array_keys($data); ?>
      <tr>
        <td class="border border-gray-400 p-2 text-center font-bold afd-cell" rowspan="<?= $rowCount + 1 ?>">
          <input type="text" id="afd-nama" class="afd-input header-input" value="<?= htmlspecialchars($namaUnit) ?>" readonly>
        </td>
        <?php for($i=0;$i<$rowCount;$i++):
          $blok_name = $dataKeys[$i] ?? '';
          // >>> default kosong supaya UI tidak terlihat terisi jika DB tidak ada data
          $rowData   = $data[$blok_name] ?? ['blok'=>'','ts'=>'','pkwt'=>'','kng'=>'','tp'=>''];
        ?>
        <?= $i>0 ? '<tr>' : '' ?>
          <td class="border border-gray-400 p-1">
            <input type="text" class="data-input blok-input" value="<?= htmlspecialchars((string)$rowData['blok']) ?>" placeholder="Nama Blok..." readonly>
          </td>
          <td class="border border-gray-400 p-1">
            <input type="number" step="0.01" class="data-input num-input" data-field="ts"   value="<?= $rowData['ts'] === '' ? '' : (float)$rowData['ts'] ?>" readonly>
          </td>
          <td class="border border-gray-400 p-1">
            <input type="number" step="0.01" class="data-input num-input" data-field="pkwt" value="<?= $rowData['pkwt'] === '' ? '' : (float)$rowData['pkwt'] ?>" readonly>
          </td>
          <td class="border border-gray-400 p-1">
            <input type="number" step="0.01" class="data-input num-input" data-field="kng"  value="<?= $rowData['kng'] === '' ? '' : (float)$rowData['kng'] ?>" readonly>
          </td>
          <td class="border border-gray-400 p-1">
            <input type="number" step="0.01" class="data-input num-input" data-field="tp"   value="<?= $rowData['tp'] === '' ? '' : (float)$rowData['tp'] ?>" readonly>
          </td>
          <td class="border border-gray-400 p-1 text-right font-semibold row-total">0.00</td>
        <?= $i>0 ? '</tr>' : '' ?>
        <?php endfor; ?>
      </tr>
      <tr class="bg-yellow-100 font-bold">
        <td class="border border-gray-400 p-2 text-center">JUMLAH</td>
        <td class="border border-gray-400 p-2 text-right afd-total" data-field="ts">0.00</td>
        <td class="border border-gray-400 p-2 text-right afd-total" data-field="pkwt">0.00</td>
        <td class="border border-gray-400 p-2 text-right afd-total" data-field="kng">0.00</td>
        <td class="border border-gray-400 p-2 text-right afd-total" data-field="tp">0.00</td>
        <td class="border border-gray-400 p-2 text-right afd-total-grand">0.00</td>
      </tr>
      </tbody>
    </table>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('report-container');
  const table = container.querySelector('.report-table');
  const btnEdit = document.getElementById('btn-edit');
  const btnSave = document.getElementById('btn-save');
  const btnCancel = document.getElementById('btn-cancel');
  const btnClear = document.getElementById('btn-clear-draft');
  const btnReset = document.getElementById('btn-reset');
  // [SINKRONISASI] Dinonaktifkan agar sesuai HTML
  // const btnExcel = document.getElementById('btn-excel');
  // const btnExcelAll = document.getElementById('btn-excel-all');
  const btnPrint = document.getElementById('btn-print');
  const afdInput = document.getElementById('afd-nama');

  // ==== Role flag dari PHP ====
  const IS_STAF = <?= $isStaf ? 'true' : 'false' ?>;

  // ===== STATE =====
  let activeWeek = <?= (int)$f_minggu ?>;
  // [MODIFIED] Hapus reportCache, ganti initialMeta menjadi let
  let currentMeta = <?= json_encode($meta) ?>;
  // [MODIFIED] Data awal dari PHP (sudah tidak dimasukkan cache)
  const initialDetails = <?= json_encode(array_values($data)) ?>;

  // ===== UTIL =====
  const getDraftKey = (week) => [
    'lm', 'u<?= (int)$userId ?>', 'p' + location.pathname.replace(/\\/g, '/'),
    'k<?= (string)$f_kebun_id ?>', 'a<?= (string)$f_unit_id ?>', 'jp<?= (string)$f_jenis_pekerjaan_id ?>',
    'y<?= (int)$f_tahun ?>', 'b<?= (string)$f_bulan ?>', 'm' + week
  ].join(':');

  const setReadOnlyForAll = (isReadonly) => {
    container.querySelectorAll('.data-input, .header-input, #meta-catatan, #afd-nama')
      .forEach(el => el.readOnly = isReadonly);
  };

  const toggleEditMode = (on) => {
    // jika staf, tidak boleh edit
    if (IS_STAF) return;
    container.classList.toggle('edit-mode', on);
    setReadOnlyForAll(!on);
    if (btnEdit)   btnEdit.classList.toggle('hidden', on);
    if (btnSave)   btnSave.classList.toggle('hidden', !on);
    if (btnCancel) btnCancel.classList.toggle('hidden', !on);
  };

  const asNum = v => {
    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  };

  const calcTotals = () => {
    // total per-baris
    table.querySelectorAll('tbody tr:not(.bg-yellow-100)').forEach(row => {
      const nums = row.querySelectorAll('.num-input');
      if (!nums.length) return;
      let sum = 0;
      nums.forEach(i => sum += asNum(i.value));
      const cell = row.querySelector('.row-total');
      if (cell) cell.textContent = sum.toFixed(2);
    });
    // total kolom
    const fields = ['ts','pkwt','kng','tp']; let grand = 0;
    fields.forEach(f => {
      let tot = 0;
      table.querySelectorAll(`.num-input[data-field='${f}']`).forEach(i => tot += asNum(i.value));
      const cell = table.querySelector(`.afd-total[data-field='${f}']`);
      if (cell) cell.textContent = tot.toFixed(2);
      grand += tot;
    });
    const gcell = table.querySelector('.afd-total-grand'); if (gcell) gcell.textContent = grand.toFixed(2);
  };

  // ===== RENDER =====
  /** [MODIFIED] Fungsi ini hanya merender baris-baris tabel */
  const renderTable = (details) => {
    // kosongkan semua dulu
    table.querySelectorAll('.blok-input').forEach(el => el.value = '');
    table.querySelectorAll('.num-input').forEach(el => el.value = '');

    const rows = Array.from(table.querySelectorAll('tbody tr:not(.bg-yellow-100)'));
    const detailsData = Array.isArray(details) ? details : [];
    
    for (let i = 0; i < rows.length; i++) {
      const d = detailsData[i] || { blok:'', ts:'', pkwt:'', kng:'', tp:'' };
      const row = rows[i];
      row.querySelector('.blok-input').value = d.blok || '';
      row.querySelectorAll('.num-input').forEach(inp => {
        const v = d[inp.dataset.field];
        inp.value = (v === null || v === undefined || v === '') ? '' : v;
      });
    }
    calcTotals();
  };

  /** [BARU] Fungsi untuk update semua UI (meta, tab, dan tabel) */
  const updateDisplay = (data) => {
    const details = data.details || [];
    // Jika data.meta ada (dari fetch), update meta global
    if (data.meta) {
        currentMeta = data.meta;
    }
    
    // 1. Update Headers
    // Nama Kebun, Jenis Pek, Periode tidak berubah (dari filter PHP)
    document.getElementById('meta-judul_laporan').value = (currentMeta.judul_laporan || '') + ' <?= $namaKebun ?>';
    document.getElementById('meta-catatan').value = currentMeta.catatan || '';
    document.getElementById('meta-judul_minggu').value = currentMeta[`judul_minggu_${activeWeek}`] || `MINGGU ${activeWeek}`;

    // 2. Update Label Tab Mingguan
    for (let m = 1; m <= 5; m++) {
        const tab = document.querySelector(`.week-tab[data-week="${m}"]`);
        if (tab) {
            tab.textContent = currentMeta[`judul_minggu_${m}`] || `MINGGU ${m}`;
        }
    }
    
    // 3. Render baris-baris tabel
    renderTable(details);
  };


  const collectDraft = () => {
    const draft = { ts: Date.now(), details: [] };
    const rows = Array.from(table.querySelectorAll('tbody tr:not(.bg-yellow-100)'));
    rows.forEach(row => {
      const blok = row.querySelector('.blok-input')?.value.trim() || '';
      const obj = { blok, ts:'', pkwt:'', kng:'', tp:'' };
      row.querySelectorAll('.num-input').forEach(i => {
        obj[i.dataset.field] = (i.value === '' ? '' : asNum(i.value));
      });
      const hasVal = blok || obj.ts !== '' || obj.pkwt !== '' || obj.kng !== '' || obj.tp !== '';
      if (hasVal) draft.details.push(obj);
    });
    return draft;
  };

  let saveTimeout = null;
  const scheduleSave = () => {
    if (IS_STAF) return;
    if (!container.classList.contains('edit-mode')) return;
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
      try { localStorage.setItem(getDraftKey(activeWeek), JSON.stringify(collectDraft())); } catch (e) {}
    }, 300);
  };

  // ===== FETCH & WEEK SWITCH =====
  const fetchReportData = async (week) => {
    const params = new URLSearchParams({
      action: 'fetch_report', minggu: week,
      kebun_id: '<?= $f_kebun_id ?>', unit_id: '<?= $f_unit_id ?>',
      jenis_pekerjaan_id: '<?= $f_jenis_pekerjaan_id ?>',
      tahun: '<?= $f_tahun ?>', bulan: '<?= $f_bulan ?>',
    });
    try {
      const res = await fetch(`laporan_mingguan_crud.php?${params.toString()}`);
      if (!res.ok) throw new Error('Network response was not ok.');
      const json = await res.json();
      if (json.success) {
        return json.data; // [MODIFIED] akan berisi { details: [...], meta: {...} }
      }
      throw new Error(json.message || 'Gagal mengambil data dari server.');
    } catch (error) {
      console.error("Fetch error:", error);
      Swal.fire('Error', `Tidak dapat mengambil data untuk Minggu ${week}.`, 'error');
      return null;
    }
  };

  /** [MODIFIED] Alur ganti minggu dirombak total */
  const switchWeek = async (targetWeek) => {
    if (targetWeek === activeWeek) return;

    // Jika sedang edit, simpan draft minggu *sebelumnya*
    if (container.classList.contains('edit-mode')) {
        scheduleSave();
    }

    activeWeek = targetWeek;

    // Update visual tab
    document.querySelectorAll('.week-tab').forEach(tab => {
      const isSelected = tab.dataset.week == activeWeek;
      tab.classList.toggle('border-blue-600', isSelected);
      tab.classList.toggle('text-blue-600', isSelected);
      tab.classList.toggle('border-transparent', !isSelected);
      tab.classList.toggle('text-gray-500', !isSelected);
    });

    // Cek draft (HANYA UNTUK ADMIN)
    let draft = null;
    if (!IS_STAF) {
      draft = JSON.parse(localStorage.getItem(getDraftKey(activeWeek)) || 'null');
    }

    if (draft && draft.details) {
      // ADMIN: Muat data draft dari localStorage
      // Gunakan meta yang ada saat ini (currentMeta)
      updateDisplay({ details: draft.details, meta: currentMeta });
      // updateDisplay tidak tahu minggu aktif, jadi set judul minggu aktif manual
      document.getElementById('meta-judul_minggu').value = currentMeta[`judul_minggu_${activeWeek}`] || `MINGGU ${activeWeek}`;
    } else {
      // STAF: Selalu fetch data baru
      // ADMIN: Fetch data baru jika tidak ada draft
      const serverData = await fetchReportData(activeWeek);
      if (serverData) {
        // serverData berisi {details, meta}, updateDisplay akan mengurus sisanya
        updateDisplay(serverData);
      } else {
        // Gagal fetch, bersihkan tabel tapi pertahankan meta
        updateDisplay({ details: [], meta: currentMeta });
      }
    }
  };

  // ===== EVENTS =====
  if (btnEdit)   btnEdit.addEventListener('click', () => toggleEditMode(true));
  // Tombol Batal me-reload halaman. Ini adalah cara teraman untuk membatalkan
  // dan membuang semua state (termasuk draft yang mungkin baru diketik).
  if (btnCancel) btnCancel.addEventListener('click', () => location.reload());

  document.getElementById('week-tabs').addEventListener('click', (e) => {
    const tab = e.target.closest('.week-tab');
    if (tab && tab.dataset.week) switchWeek(parseInt(tab.dataset.week, 10));
  });

  container.addEventListener('input', e => {
    if (e.target.classList.contains('data-input') || e.target.classList.contains('header-input')) {
      calcTotals();
      scheduleSave();
    }
  });

  if (btnClear) btnClear.addEventListener('click', () => {
    if (IS_STAF) return;
    localStorage.removeItem(getDraftKey(activeWeek));
    Swal.fire({icon:'info', title:'Draft Minggu Ini Dihapus', text: 'Muat ulang halaman untuk melihat data tersimpan.', timer:2000, showConfirmButton:false});
    // delete reportCache[activeWeek]; // [MODIFIED] cache dihapus
    location.reload(); // [MODIFIED] Reload agar data server terbaru dimuat
  });

  if (btnReset) btnReset.addEventListener('click', () => {
    if (IS_STAF || !container.classList.contains('edit-mode')) {
      return Swal.fire('Info','Aktifkan mode Edit dulu (khusus admin).','info');
    }
    // [MODIFIED] Gunakan updateDisplay untuk membersihkan
    updateDisplay({ details: [], meta: currentMeta }); 
    scheduleSave();
  });

  const saveToServer = async () => {
    if (IS_STAF) return;
    
    // Ambil judul laporan tanpa nama kebun
    const judulLaporanFull = document.getElementById('meta-judul_laporan').value;
    const namaKebunSuffix = ' <?= $namaKebun ?>';
    let judulLaporanBase = judulLaporanFull;
    if (judulLaporanFull.endsWith(namaKebunSuffix)) {
        judulLaporanBase = judulLaporanFull.substring(0, judulLaporanFull.length - namaKebunSuffix.length);
    }
    
    const payload = {
      meta: {
        kebun_id: '<?= $f_kebun_id ?>', unit_id: '<?= $f_unit_id ?>',
        unit_nama: afdInput?.value || '', jenis_pekerjaan_id: '<?= $f_jenis_pekerjaan_id ?>',
        tahun: '<?= $f_tahun ?>', bulan: '<?= $f_bulan ?>',
        judul_laporan: judulLaporanBase.trim(),
        [`judul_minggu_${activeWeek}`]: document.getElementById('meta-judul_minggu').value,
        catatan: document.getElementById('meta-catatan').value
      },
      details: collectDraft().details
    };

    const fd = new FormData();
    fd.append('csrf_token','<?= $CSRF ?>');
    fd.append('action','save_report');
    fd.append('minggu', activeWeek);
    fd.append('payload', JSON.stringify(payload));

    try {
      const res = await fetch('laporan_mingguan_crud.php', { method:'POST', body: fd });
      const out = await res.json();
      if (out.success) {
        Swal.fire({icon:'success', title:'Tersimpan', timer:1200, showConfirmButton:false});
        localStorage.removeItem(getDraftKey(activeWeek)); // [MODIFIED] Hapus draft
        toggleEditMode(false);
        
        // [MODIFIED] Setelah simpan, fetch ulang data yang bersih dari server
        const serverData = await fetchReportData(activeWeek);
        if (serverData) {
            updateDisplay(serverData);
        }
        
      } else {
        Swal.fire('Gagal', out.message || 'Terjadi kesalahan.', 'error');
      }
    } catch(e) {
      Swal.fire('Error','Tidak dapat terhubung ke server.','error');
    }
  };
  if (btnSave) btnSave.addEventListener('click', saveToServer);

  // ===== PRINT =====
  if (btnPrint) {
    btnPrint.addEventListener('click', () => {
      window.print();
    });
  }

  // ===== EXCEL EXPORT =====
  const baseParams = () => new URLSearchParams({
    kebun_id: '<?= $f_kebun_id ?>',
    unit_id: '<?= $f_unit_id ?>',
    jenis_pekerjaan_id: '<?= $f_jenis_pekerjaan_id ?>',
    tahun: '<?= $f_tahun ?>',
    bulan: '<?= $f_bulan ?>'
  });

  // [SINKRONISASI] Event listener dinonaktifkan agar sesuai HTML
  /* btnExcel?.addEventListener('click', () => {
    const p = baseParams();
    p.set('mode','single');
    p.set('minggu', String(activeWeek));
    window.location.href = 'laporan_mingguan_export_excel.php?' + p.toString();
  });

  btnExcelAll?.addEventListener('click', () => {
    const p = baseParams();
    p.set('mode','all');
    window.location.href = 'laporan_mingguan_export_excel.php?' + p.toString();
  });
  */

  // ===== INIT =====
  // Staf: paksa readonly, sembunyikan tombol edit
  if (IS_STAF) {
    setReadOnlyForAll(true);
    container.classList.remove('edit-mode');
    if (btnEdit)   btnEdit.style.display = 'none';
    if (btnSave)   btnSave.style.display = 'none';
    if (btnCancel) btnCancel.style.display = 'none';
    if (btnClear)  btnClear.style.display = 'none';
    if (btnReset)  btnReset.style.display  = 'none';
  }

  // [MODIFIED] Logika Inisialisasi
  // Cek draft awal (hanya admin)
  if (!IS_STAF) {
    const initialDraft = JSON.parse(localStorage.getItem(getDraftKey(activeWeek)) || 'null');
    if (initialDraft && initialDraft.details) {
      // Admin: Muat draft saat load halaman
      updateDisplay({ details: initialDraft.details, meta: currentMeta });
      document.getElementById('meta-judul_minggu').value = currentMeta[`judul_minggu_${activeWeek}`] || `MINGGU ${activeWeek}`;
      // Jangan return, biarkan kode berlanjut (meskipun tidak akan melakukan apa-apa lagi)
    } else {
      // Admin tanpa draft: Render data awal dari server
      updateDisplay({ details: initialDetails, meta: currentMeta });
    }
  } else {
     // Staf: Render data awal dari server
     updateDisplay({ details: initialDetails, meta: currentMeta });
  }

});
</script>