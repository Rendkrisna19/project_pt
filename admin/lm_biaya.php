<?php
// admin/lm_biaya.php
// MODIFIKASI: Grouping per Unit + Perbaikan Deteksi Pupuk

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

// --- HELPER FUNCTIONS ---
function col_exists(PDO $pdo, $table, $col){
    $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
    $st->execute([':t'=>$table, ':c'=>$col]);
    return (bool)$st->fetchColumn();
}
function find_col(PDO $pdo, $table, $candidates, $default='0') {
    foreach ($candidates as $col) { if (col_exists($pdo, $table, $col)) return $col; }
    return $default;
}
function build_url(array $params = []): string {
    $merged = array_merge($_GET, $params);
    foreach ($merged as $k=>$v) if ($v==='') unset($merged[$k]);
    return htmlspecialchars($_SERVER['PHP_SELF'].'?'.http_build_query($merged), ENT_QUOTES, 'UTF-8');
}

// --- INITIALIZATION ---
$bulanList = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$bulanNow  = $bulanList[date('n') - 1];
$tahunNow  = date('Y');

$db   = new Database();
$conn = $db->getConnection();

// --- 1. GET PARAMETERS ---
$unit_id  = $_GET['unit_id']  ?? '';
$kebun_id = $_GET['kebun_id'] ?? '';
$q        = trim($_GET['q'] ?? '');
$tahun    = $_GET['tahun'] ?? $tahunNow;
$bulan    = $_GET['bulan'] ?? 'Semua Bulan';

// Parameter Paging
$per_page = (int)($_GET['per_page'] ?? 100); // Default diperbanyak agar grouping terlihat
if (!in_array($per_page,[10,15,20,25,50,100,200],true)) $per_page = 100;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page-1)*$per_page;

$hasKebun = col_exists($conn,'lm_biaya','kebun_id');
$units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$kebuns = $hasKebun ? $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC) : [];

// --- 2. LOGIKA HPP (VOLUME TBS) ---
$vol_real = 0; $vol_ang = 0;
if (col_exists($conn, 'lm76', 'id')) {
    $col_r = find_col($conn, 'lm76', ['prod_bi_realisasi','realisasi','prod_real','tbs_realisasi']);
    $col_a = find_col($conn, 'lm76', ['prod_bi_anggaran','prod_bi_rkap','anggaran','rkap']);

    $wh76 = " WHERE 1=1 "; $bd76 = [];
    if ($tahun !== '')        { $wh76 .= " AND tahun=:t"; $bd76[':t'] = $tahun; }
    if ($bulan !== 'Semua Bulan' && $bulan !== '') { $wh76 .= " AND bulan=:b"; $bd76[':b'] = $bulan; }
    if ($unit_id !== '')      { $wh76 .= " AND unit_id=:u"; $bd76[':u'] = $unit_id; }
    if ($kebun_id !== '')     { $wh76 .= " AND kebun_id=:k"; $bd76[':k'] = $kebun_id; }

    $sqlVol = "SELECT SUM(COALESCE($col_r,0)) as v_real, SUM(COALESCE($col_a,0)) as v_ang FROM lm76 $wh76";
    $stVol = $conn->prepare($sqlVol);
    $stVol->execute($bd76);
    $dVol = $stVol->fetch(PDO::FETCH_ASSOC);
    $vol_real = (float)($dVol['v_real'] ?? 0);
    $vol_ang  = (float)($dVol['v_ang'] ?? 0);
}

// --- 3. AMBIL DATA BIAYA ---
$fromJoin = " FROM lm_biaya b LEFT JOIN units u ON u.id=b.unit_id ".($hasKebun ? "LEFT JOIN md_kebun kb ON kb.id=b.kebun_id" : "")." WHERE 1=1 ";
$where=""; $bind=[];

if ($unit_id!==''){ $where.=" AND b.unit_id=:uid"; $bind[':uid']=$unit_id; }
if ($tahun!==''){ $where.=" AND b.tahun=:thn"; $bind[':thn']=$tahun; }
if ($bulan!=='Semua Bulan' && $bulan!==''){ $where.=" AND b.bulan=:bln"; $bind[':bln']=$bulan; }
if ($hasKebun && $kebun_id!==''){ $where.=" AND b.kebun_id=:kid"; $bind[':kid']=$kebun_id; }
if ($q!==''){
    $where.=" AND (b.alokasi LIKE :kw OR b.uraian_pekerjaan LIKE :kw OR u.nama_unit LIKE :kw)";
    $bind[':kw'] = "%$q%";
}

// Hitung total rows
$stc = $conn->prepare("SELECT COUNT(*) ".$fromJoin.$where);
foreach($bind as $k=>$v) $stc->bindValue($k,$v);
$stc->execute();
$total_rows  = (int)$stc->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows/$per_page));
if ($page>$total_pages && $total_pages > 0){ $page=$total_pages; $offset=($page-1)*$per_page; }

// Query Data (Sort by Unit first for grouping)
$sql = "SELECT b.*, u.nama_unit ".($hasKebun ? ", kb.nama_kebun " : "")." ".$fromJoin.$where."
        ORDER BY u.nama_unit ASC, b.tahun DESC, 
        FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), 
        b.alokasi ASC
        LIMIT :lim OFFSET :off";
$st = $conn->prepare($sql);
foreach($bind as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':lim',$per_page,PDO::PARAM_INT);
$st->bindValue(':off',$offset,PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$fromRow = $total_rows ? $offset+1 : 0;
$toRow   = min($offset+$per_page,$total_rows);

// --- 4. DATA GROUPING & CALCULATION ---
$groupedData = [];
$sumAnggaran = 0; $sumRealisasi = 0; // Total Incl
$pupukAng = 0;    $pupukReal = 0;    // Total Pupuk

foreach($rows as $r){
    $unitName = $r['nama_unit'] ?? 'Tanpa Unit';
    
    // Siapkan data untuk view
    $ang = (float)$r['rencana_bi']; 
    $rea = (float)$r['realisasi_bi'];
    $diff = $rea - $ang;
    $pct  = ($ang!=0) ? (($rea - $ang) / $ang * 100) : null;
    
    $r['val_ang'] = $ang;
    $r['val_rea'] = $rea;
    $r['val_diff'] = $diff;
    $r['val_pct'] = $pct;

    // Masukkan ke Grouping
    $groupedData[$unitName][] = $r;

    // --- HITUNG GLOBAL INCL ---
    $sumAnggaran  += $ang; 
    $sumRealisasi += $rea;

    // --- HITUNG PUPUK (DETEKSI KATA KUNCI) ---
    // Pastikan kata kunci "pupuk" atau "pemupukan" ada di Uraian atau Alokasi
    $textCheck = strtolower(($r['alokasi']??'').' '.($r['uraian_pekerjaan']??''));
    if (strpos($textCheck, 'pupuk') !== false || strpos($textCheck, 'pemupukan') !== false || strpos($textCheck, 'fertilizer') !== false) {
        $pupukAng  += $ang;
        $pupukReal += $rea;
        $r['is_pupuk'] = true; // Flag untuk debug view
    } else {
        $r['is_pupuk'] = false;
    }
}

// Hitung Exclusive
$exclAng  = $sumAnggaran - $pupukAng;
$exclReal = $sumRealisasi - $pupukReal;

// Hitung HPP
$hppInclAng = ($vol_ang > 0) ? ($sumAnggaran / $vol_ang) : 0;
$hppInclReal= ($vol_real > 0)? ($sumRealisasi / $vol_real) : 0;
$hppExclAng = ($vol_ang > 0) ? ($exclAng / $vol_ang) : 0;
$hppExclReal= ($vol_real > 0)? ($exclReal / $vol_real) : 0;

$calcDiff = function($real, $ang) {
    $diff = $real - $ang;
    $pct  = ($ang > 0) ? (($diff) / $ang * 100) : null;
    return [$diff, $pct];
};

$currentPage = 'lm_biaya';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
  .table-container {
    background: #fff; border: 1px solid #cbd5e1; border-radius: 0.5rem;
    overflow-x: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
  }
  table.custom-table { width: 100%; border-collapse: collapse; min-width: 1200px; }
  
  /* Header */
  table.custom-table thead th {
    background-color: #0097e6; color: #fff; text-transform: uppercase; font-size: 0.75rem;
    font-weight: 700; padding: 0.75rem; text-align: left; border-right: 1px solid rgba(255,255,255,0.2);
    position: sticky; top: 0; z-index: 10;
  }
  
  table.custom-table tbody td {
    padding: 0.6rem 0.75rem; font-size: 0.85rem; border-bottom: 1px solid #f1f5f9; color: #334155;
  }
  table.custom-table tbody tr:hover { background-color: #f8fafc; }

  /* Group Header (Unit) */
  .group-header { background-color: #e0f2fe; color: #0284c7; font-weight: bold; border-top: 2px solid #bae6fd; }
  .group-header td { padding: 0.75rem; font-size: 0.9rem; }

  /* Footer */
  table.custom-table tfoot td { background-color: #f1f5f9; font-weight: bold; padding: 0.75rem; border-top: 2px solid #e2e8f0; }
  .bg-hpp { background-color: #86efac !important; color: #000 !important; }
  .bg-vol { background-color: #e2e8f0 !important; font-weight: bold; font-style: italic; }

  /* Colors */
  .text-red-custom { color: #dc2626; font-weight: 600; }
  .text-green-custom { color: #16a34a; font-weight: 600; }
  
  /* Filter Styles */
  .filter-label { display: block; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem; }
  .filter-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.5rem; font-size: 0.875rem; outline: none; }
  .filter-input:focus { border-color: #0097e6; box-shadow: 0 0 0 2px rgba(0, 151, 230, 0.1); }
</style>

<div class="space-y-6">
  
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800">LM Biaya (Grouping per Unit)</h1>
    <?php if (!$isStaf): ?>
      <button id="btn-add" class="px-4 py-2 bg-[#0097e6] text-white rounded-md hover:bg-[#0086cc] flex items-center gap-2 shadow-sm transition font-medium text-sm">
          <i class="ti ti-plus"></i> Tambah
      </button>
    <?php endif; ?>
  </div>

  <div class="flex gap-2">
    <a href="cetak/lm_biaya_pdf.php?<?= http_build_query($_GET) ?>" target="_blank" class="px-4 py-2 bg-[#dc2626] text-white rounded-md hover:bg-[#b91c1c] flex items-center gap-2 text-sm font-medium shadow-sm transition">
        <i class="ti ti-file-type-pdf"></i> Export PDF
    </a>
    <a href="cetak/lm_biaya_excel.php?<?= http_build_query($_GET) ?>" class="px-4 py-2 bg-[#0891b2] text-white rounded-md hover:bg-[#0e7490] flex items-center gap-2 text-sm font-medium shadow-sm transition">
        <i class="ti ti-file-spreadsheet"></i> Export Excel
    </a>
  </div>

  <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
    <form method="get" id="filter-form" class="grid grid-cols-12 gap-4 items-end">
      <?php if ($hasKebun): ?>
      <div class="col-span-12 md:col-span-2">
        <label class="filter-label">Kebun</label>
        <select name="kebun_id" class="filter-input" onchange="this.form.submit()">
            <option value="">Semua Kebun</option>
            <?php foreach($kebuns as $k): ?><option value="<?= $k['id'] ?>" <?= ($kebun_id == $k['id'])?'selected':'' ?>><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-span-12 md:col-span-2">
         <label class="filter-label">Unit/Defisi</label>
         <select name="unit_id" class="filter-input" onchange="this.form.submit()">
            <option value="">Semua Unit</option>
            <?php foreach($units as $u): ?><option value="<?= $u['id'] ?>" <?= ($unit_id == $u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
         </select>
      </div>
      <div class="col-span-12 md:col-span-2">
         <label class="filter-label">Bulan</label>
         <select name="bulan" class="filter-input" onchange="this.form.submit()">
            <option value="Semua Bulan">Semua Bulan</option>
            <?php foreach($bulanList as $b): ?><option value="<?= $b ?>" <?= ($bulan===$b)?'selected':'' ?>><?= $b ?></option><?php endforeach; ?>
         </select>
      </div>
      <div class="col-span-12 md:col-span-1">
         <label class="filter-label">Tahun</label>
         <select name="tahun" class="filter-input" onchange="this.form.submit()">
            <?php for($y=date('Y')-2;$y<=date('Y')+2;$y++): ?><option value="<?= $y ?>" <?= ($tahun==$y)?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
         </select>
      </div>
      <div class="col-span-12 md:col-span-<?php echo $hasKebun ? '4' : '6'; ?>">
         <label class="filter-label">Pencarian</label>
         <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="filter-input" placeholder="Cari..." onchange="this.form.submit()">
      </div>
      <div class="col-span-12 md:col-span-1">
         <label class="filter-label">Baris</label>
         <select name="per_page" class="filter-input text-center" onchange="this.form.submit()">
            <?php foreach([10,25,50,100,200] as $pp): ?><option value="<?= $pp ?>" <?= $per_page===$pp?'selected':'' ?>><?= $pp ?></option><?php endforeach; ?>
         </select>
      </div>
    </form>
  </div>

  <div class="table-container">
    <table class="custom-table">
      <thead>
        <tr>
          <?php if ($hasKebun): ?><th>Kebun</th><?php endif; ?>
          <th>Unit/Defisi</th>
          <th>Alokasi</th>
          <th>Uraian Pekerjaan</th>
          <th class="text-center">Bulan</th>
          <th class="text-center">Tahun</th>
          <th class="text-right">Anggaran</th>
          <th class="text-right">Realisasi</th>
          <th class="text-right">+/- Biaya</th>
          <th class="text-center">%</th>
          <?php if (!$isStaf): ?><th class="text-center">Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php list($diffVol, $pctVol) = $calcDiff($vol_real, $vol_ang); ?>
        <tr class="bg-vol">
             <td colspan="<?= ($hasKebun?3:2) ?>"></td>
             <td class="text-left" style="font-style:italic;">- Produksi TBS (KG)</td>
             <td></td>
             <td></td>
             <td class="text-right"><?= number_format($vol_ang) ?></td>
             <td class="text-right"><?= number_format($vol_real) ?></td>
             <td class="text-right"><?= number_format($diffVol) ?></td>
             <td class="text-center"><?= is_null($pctVol)?'-':number_format($pctVol,2).'%' ?></td>
             <?php if (!$isStaf): ?><td></td><?php endif; ?>
        </tr>

        <?php if (!$groupedData): ?>
          <tr><td colspan="12" class="text-center py-8 text-gray-500">Data tidak ditemukan.</td></tr>
        <?php else: ?>
          <?php foreach ($groupedData as $unitName => $items): ?>
            
            <tr class="group-header">
                <td colspan="<?= ($hasKebun?11:10) + (!$isStaf?1:0) ?>">
                    <i class="ti ti-folder"></i> Unit: <?= htmlspecialchars($unitName) ?>
                </td>
            </tr>

            <?php foreach ($items as $r): 
               $d = $r['val_diff']; $p = $r['val_pct'];
               $pctClass = is_null($p) ? 'text-gray-500' : ($p < 0 ? 'text-green-custom' : 'text-red-custom');
            ?>
            <tr>
                <?php if ($hasKebun): ?><td><?= htmlspecialchars($r['nama_kebun']??'-') ?></td><?php endif; ?>
                <td><?= htmlspecialchars($r['nama_unit']??'-') ?></td>
                <td><?= htmlspecialchars($r['alokasi']??'-') ?></td>
                <td>
                    <?= htmlspecialchars($r['uraian_pekerjaan']??'-') ?>
                    <?php if(isset($r['is_pupuk']) && $r['is_pupuk']): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-1 rounded ml-1">Pupuk</span>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= htmlspecialchars($r['bulan']) ?></td>
                <td class="text-center"><?= $r['tahun'] ?></td>
                <td class="text-right"><?= number_format($r['val_ang'], 2) ?></td>
                <td class="text-right"><?= number_format($r['val_rea'], 2) ?></td>
                <td class="text-right <?= $d<0 ? 'text-green-custom':'text-red-custom' ?>"><?= number_format($d, 2) ?></td>
                <td class="text-center <?= $pctClass ?>"><?= is_null($p)?'-':number_format($p,2).'%' ?></td>
                
                <?php if (!$isStaf): ?>
                <td class="text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button class="btn-edit text-[#0097e6] hover:text-blue-800 transition" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>'><i class="ti ti-pencil text-lg"></i></button>
                    <button class="btn-delete text-red-500 hover:text-red-700 transition" data-id="<?= (int)$r['id'] ?>"><i class="ti ti-trash text-lg"></i></button>
                  </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>

      <tfoot>
          <?php list($dInc, $pInc) = $calcDiff($sumRealisasi, $sumAnggaran); ?>
          <tr>
            <td colspan="<?= ($hasKebun?6:5) ?>" class="text-right uppercase">Total Incl. Pemupukan</td>
            <td class="text-right"><?= number_format($sumAnggaran,2) ?></td>
            <td class="text-right"><?= number_format($sumRealisasi,2) ?></td>
            <td class="text-right"><?= number_format($dInc,2) ?></td>
            <td class="text-center"><?= is_null($pInc)?'-':number_format($pInc,2).'%' ?></td>
            <?php if (!$isStaf): ?><td></td><?php endif; ?>
          </tr>
          
          <?php list($dExc, $pExc) = $calcDiff($exclReal, $exclAng); ?>
          <tr>
            <td colspan="<?= ($hasKebun?6:5) ?>" class="text-right uppercase">Total Excl. Pemupukan (Minus Biaya Pupuk: <?= number_format($pupukReal) ?>)</td>
            <td class="text-right"><?= number_format($exclAng,2) ?></td>
            <td class="text-right"><?= number_format($exclReal,2) ?></td>
            <td class="text-right"><?= number_format($dExc,2) ?></td>
            <td class="text-center"><?= is_null($pExc)?'-':number_format($pExc,2).'%' ?></td>
            <?php if (!$isStaf): ?><td></td><?php endif; ?>
          </tr>

          <?php list($dHppI, $pHppI) = $calcDiff($hppInclReal, $hppInclAng); ?>
          <tr class="bg-hpp">
            <td colspan="<?= ($hasKebun?6:5) ?>" class="text-right uppercase">HPP Incl. Pemupukan</td>
            <td class="text-right"><?= number_format($hppInclAng,2) ?></td>
            <td class="text-right"><?= number_format($hppInclReal,2) ?></td>
            <td class="text-right"><?= number_format($dHppI,2) ?></td>
            <td class="text-center"><?= is_null($pHppI)?'-':number_format($pHppI,2).'%' ?></td>
            <?php if (!$isStaf): ?><td></td><?php endif; ?>
          </tr>

          <?php list($dHppE, $pHppE) = $calcDiff($hppExclReal, $hppExclAng); ?>
          <tr class="bg-hpp">
            <td colspan="<?= ($hasKebun?6:5) ?>" class="text-right uppercase">HPP Excl. Pemupukan</td>
            <td class="text-right"><?= number_format($hppExclAng,2) ?></td>
            <td class="text-right"><?= number_format($hppExclReal,2) ?></td>
            <td class="text-right"><?= number_format($dHppE,2) ?></td>
            <td class="text-center"><?= is_null($pHppE)?'-':number_format($pHppE,2).'%' ?></td>
            <?php if (!$isStaf): ?><td></td><?php endif; ?>
          </tr>
      </tfoot>
    </table>
  </div>

  <div class="flex items-center justify-between text-sm text-gray-600 mt-2 px-1">
      <div>Total Data: <?= number_format($total_rows) ?></div>
      <div class="flex gap-1">
         <a href="<?= build_url(['page'=>max(1,$page-1)]) ?>" class="px-3 py-1 border rounded hover:bg-gray-100 transition">Prev</a>
         <a href="<?= build_url(['page'=>min($total_pages,$page+1)]) ?>" class="px-3 py-1 border rounded hover:bg-gray-100 transition">Next</a>
      </div>
  </div>

</div>

<?php if (!$isStaf): ?>
<div id="crud-modal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4 backdrop-blur-sm">
  <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-2xl transform scale-100 transition-all">
     <div class="flex justify-between items-center mb-5 border-b pb-3">
        <h3 class="font-bold text-lg text-gray-800" id="modal-title">Form Data Biaya</h3>
        <button id="btn-close" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
     </div>
     <form id="crud-form">
        <input type="hidden" name="csrf_token" value="<?= $CSRF ?>"> 
        <input type="hidden" name="action" id="form-action"> 
        <input type="hidden" name="id" id="form-id">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
             <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-bold text-gray-600 mb-1">Unit/Defisi</label>
                <select name="unit_id" id="unit_id" class="w-full border border-gray-300 p-2 rounded outline-none" required>
                    <option value="">-- Pilih Unit --</option>
                    <?php foreach($units as $u) echo "<option value='{$u['id']}'>{$u['nama_unit']}</option>"; ?>
                </select>
             </div>
             <?php if($hasKebun): ?>
             <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-bold text-gray-600 mb-1">Kebun</label>
                <select name="kebun_id" id="kebun_id" class="w-full border border-gray-300 p-2 rounded outline-none">
                    <option value="">-- Pilih Kebun --</option>
                    <?php foreach($kebuns as $k) echo "<option value='{$k['id']}'>{$k['nama_kebun']}</option>"; ?>
                </select>
             </div>
             <?php endif; ?>
             <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-bold text-gray-600 mb-1">No. Alokasi</label>
                <input name="alokasi" id="alokasi" class="w-full border border-gray-300 p-2 rounded outline-none" required>
             </div>
             <div class="col-span-2">
                <label class="block text-sm font-bold text-gray-600 mb-1">Uraian Pekerjaan</label>
                <input name="uraian_pekerjaan" id="uraian_pekerjaan" class="w-full border border-gray-300 p-2 rounded outline-none" required>
             </div>
             <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-bold text-gray-600 mb-1">Bulan</label>
                <select name="bulan" id="bulan" class="w-full border border-gray-300 p-2 rounded outline-none">
                    <?php foreach($bulanList as $b) echo "<option value='$b'>$b</option>"; ?>
                </select>
             </div>
             <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-bold text-gray-600 mb-1">Tahun</label>
                <input type="number" name="tahun" id="tahun" value="<?= $tahun ?>" class="w-full border border-gray-300 p-2 rounded outline-none">
             </div>
             <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-bold text-gray-600 mb-1">Anggaran (Rp)</label>
                <input type="number" step="0.01" name="rencana_bi" id="rencana_bi" class="w-full border border-gray-300 p-2 rounded outline-none">
             </div>
             <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-bold text-gray-600 mb-1">Realisasi (Rp)</label>
                <input type="number" step="0.01" name="realisasi_bi" id="realisasi_bi" class="w-full border border-gray-300 p-2 rounded outline-none">
             </div>
        </div>
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
            <button type="button" id="btn-cancel" class="px-5 py-2 border border-gray-300 rounded text-gray-600 hover:bg-gray-50 transition">Batal</button>
            <button type="submit" class="px-5 py-2 bg-[#0097e6] text-white rounded hover:bg-[#0086cc] shadow-md transition">Simpan Data</button>
        </div>
     </form>
  </div>
</div>
<?php endif; ?>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const $=s=>document.querySelector(s);
  <?php if (!$isStaf): ?>
  const modal=$('#crud-modal'), open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')}, close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};
  
  $('#btn-add').onclick=()=>{ 
      $('#crud-form').reset(); 
      $('#form-action').value='store'; 
      $('#form-id').value=''; 
      $('#modal-title').innerText='Tambah Data Biaya';
      open(); 
  };
  $('#btn-close').onclick=close; 
  $('#btn-cancel').onclick=close;
  
  document.body.addEventListener('click',e=>{
    const btnEdit=e.target.closest('.btn-edit'), btnDel=e.target.closest('.btn-delete');
    if(btnEdit){
        const r=JSON.parse(btnEdit.dataset.json); 
        $('#form-action').value='update'; $('#form-id').value=r.id;
        $('#modal-title').innerText='Edit Data Biaya';
        $('#unit_id').value=r.unit_id||''; $('#alokasi').value=r.alokasi; $('#uraian_pekerjaan').value=r.uraian_pekerjaan;
        $('#bulan').value=r.bulan; $('#tahun').value=r.tahun; $('#rencana_bi').value=r.rencana_bi; $('#realisasi_bi').value=r.realisasi_bi;
        if($('#kebun_id')) $('#kebun_id').value=r.kebun_id||'';
        open();
    }
    if(btnDel){
        Swal.fire({
            title:'Hapus Data?', text: "Data tidak bisa dikembalikan!", icon:'warning',
            showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#3085d6',
            confirmButtonText:'Ya, Hapus!'
        }).then(r=>{
            if(r.isConfirmed) fetch('lm_biaya_crud.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=delete&id=${btnDel.dataset.id}&csrf_token=<?= $CSRF ?>`
            }).then(res=>res.json()).then(j=>{
                if(j.success) Swal.fire('Terhapus!','','success').then(()=>location.reload());
                else Swal.fire('Gagal',j.message,'error');
            });
        });
    }
  });

  $('#crud-form').onsubmit=e=>{
     e.preventDefault(); const fd=new FormData(e.target);
     fetch('lm_biaya_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
         if(j.success){ 
             close(); 
             Swal.fire({icon:'success',title:'Berhasil',timer:1200,showConfirmButton:false}).then(()=>location.reload()); 
         }
         else Swal.fire('Gagal',j.message||'Error','error');
     });
  };
  <?php endif; ?>
});
</script>