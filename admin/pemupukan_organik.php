<?php
// admin/pemupukan_organik.php
// MODIFIKASI FULL: Staf Bisa Input (Tambah), Tapi Tidak Bisa Edit/Hapus

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// ===== ROLE =====
$userRole = $_SESSION['user_role'] ?? 'viewer';

$isAdmin   = ($userRole === 'admin');
$isStaf    = ($userRole === 'staf');
$isViewer  = ($userRole === 'viewer');

// Hak Akses
$canInput  = ($isAdmin || $isStaf); // Admin & Staf Boleh Input
$canAction = ($isAdmin);            // Hanya Admin Boleh Edit/Hapus

require_once '../config/database.php';
$db   = new Database();
$pdo  = $db->getConnection();

/* ====== AJAX options (GET) ====== */
if (($_GET['ajax'] ?? '') === 'options') {
  header('Content-Type: application/json; charset=utf-8');
  $type = trim((string)($_GET['type'] ?? ''));
  try {
    if ($type === 'blok') {
      $unitId = (int)($_GET['unit_id'] ?? 0);
      $rows = [];
      if ($unitId > 0) {
        $st = $pdo->prepare("SELECT DISTINCT kode FROM md_blok WHERE unit_id=:u AND kode<>'' ORDER BY kode");
        $st->execute([':u'=>$unitId]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
      }
      echo json_encode(['success'=>true,'data'=>$rows]); exit;
    }
    if ($type === 'pupuk') {
      $st = $pdo->query("SELECT nama FROM md_pupuk WHERE nama<>'' ORDER BY nama");
      $rows = $st->fetchAll(PDO::FETCH_COLUMN);
      echo json_encode(['success'=>true,'data'=>$rows]); exit;
    }
    echo json_encode(['success'=>false,'message'=>'Tipe tidak dikenali']); exit;
  } catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]); exit;
  }
}

/* ====== Masters ====== */
$units       = $pdo->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$kebun       = $pdo->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$pupuk       = $pdo->query("SELECT nama FROM md_pupuk WHERE nama<>'' ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
$rayons      = $pdo->query("SELECT id, nama FROM md_rayon ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$gudangs     = $pdo->query("SELECT id, nama FROM md_asal_gudang ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$keterangans = $pdo->query("SELECT id, keterangan FROM md_keterangan ORDER BY keterangan")->fetchAll(PDO::FETCH_ASSOC);

/* ====== Tabs & Filters ====== */
$tab = $_GET['tab'] ?? 'menabur';
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

// LOGIKA TANGGAL DEFAULT (Server Time)
$bulanList = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$bulanNow  = (int)date('n');
$tahunNow  = (int)date('Y');

// Cek parameter URL
$f_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : $tahunNow;
$f_periode = isset($_GET['periode']) ? $_GET['periode'] : $bulanNow; 

$f_kebun_id       = ($_GET['kebun_id'] ?? '') === '' ? '' : (int)$_GET['kebun_id'];
$f_tanggal        = trim((string)($_GET['tanggal'] ?? ''));
$f_unit_id        = ($_GET['unit_id'] ?? '') === '' ? '' : (int)$_GET['unit_id'];
$f_jenis_pupuk    = trim((string)($_GET['jenis_pupuk'] ?? ''));
$f_rayon_id       = ($_GET['rayon_id'] ?? '') === '' ? '' : (int)$_GET['rayon_id'];
$f_asal_gudang_id = ($_GET['asal_gudang_id'] ?? '') === '' ? '' : (int)$_GET['asal_gudang_id'];
$f_keterangan_id  = ($_GET['keterangan_id'] ?? '') === '' ? '' : (int)$_GET['keterangan_id'];

/* ====== Pagination ====== */
$page     = max(1, (int)($_GET['page'] ?? 1));
$perOpts  = [10,25,50,100];
$per_page = (int)($_GET['per_page'] ?? 10);
if (!in_array($per_page, $perOpts, true)) $per_page = 10;
$offset   = ($page - 1) * $per_page;

/* ====== Query Data ====== */
if ($tab === 'angkutan') {
  $title = "Data Angkutan Pupuk";
  $where = " WHERE 1=1"; $p = [];
  
  if ($f_tahun !== '') { $where .= " AND YEAR(a.tanggal) = :thn"; $p[':thn'] = $f_tahun; }
  if ($f_kebun_id) { $where .= " AND a.kebun_id = :kid"; $p[':kid'] = $f_kebun_id; }
  if ($f_tanggal) { $where .= " AND a.tanggal = :tgl"; $p[':tgl'] = $f_tanggal; }
  
  if ($f_periode !== '' && $f_periode !== 0) { 
      $where .= " AND MONTH(a.tanggal) = :bln"; 
      $p[':bln'] = $f_periode; 
  }

  if ($f_unit_id) { $where .= " AND a.unit_tujuan_id = :uid"; $p[':uid'] = $f_unit_id; }
  if ($f_jenis_pupuk) { $where .= " AND a.jenis_pupuk = :jp"; $p[':jp'] = $f_jenis_pupuk; }
  if ($f_rayon_id) { $where .= " AND a.rayon_id = :rid"; $p[':rid'] = $f_rayon_id; }
  if ($f_asal_gudang_id) { $where .= " AND a.asal_gudang_id = :gid"; $p[':gid'] = $f_asal_gudang_id; }
  if ($f_keterangan_id) { $where .= " AND a.keterangan_id = :kid2"; $p[':kid2'] = $f_keterangan_id; }

  // count
  $stc = $pdo->prepare("SELECT COUNT(*) FROM angkutan_pupuk_organik a $where");
  $stc->execute($p);
  $total_rows = (int)$stc->fetchColumn();
  $total_pages = max(1, (int)ceil($total_rows / $per_page));

  // data
  $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama, k.nama_kebun AS kebun_nama, 
                 r.nama AS nama_rayon, g.nama AS nama_gudang, ket.keterangan AS nama_keterangan
          FROM angkutan_pupuk_organik a
          LEFT JOIN units u ON u.id = a.unit_tujuan_id
          LEFT JOIN md_kebun k ON k.id = a.kebun_id
          LEFT JOIN md_rayon r ON r.id = a.rayon_id
          LEFT JOIN md_asal_gudang g ON g.id = a.asal_gudang_id
          LEFT JOIN md_keterangan ket ON ket.id = a.keterangan_id
          $where ORDER BY a.tanggal DESC, a.id DESC LIMIT :limit OFFSET :offset";
  $st=$pdo->prepare($sql);
  foreach ($p as $k=>$v) $st->bindValue($k, $v);
  $st->bindValue(':limit',$per_page,PDO::PARAM_INT);
  $st->bindValue(':offset',$offset,PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

} else { // Menabur
  $title = "Data Menabur Pupuk";
  $where = " WHERE 1=1"; $p = [];
  
  if ($f_tahun !== '') { $where .= " AND YEAR(m.tanggal) = :thn"; $p[':thn'] = $f_tahun; }
  if ($f_kebun_id) { $where .= " AND m.kebun_id = :kid"; $p[':kid'] = $f_kebun_id; }
  if ($f_tanggal) { $where .= " AND m.tanggal = :tgl"; $p[':tgl'] = $f_tanggal; }
  
  if ($f_periode !== '' && $f_periode !== 0) { 
      $where .= " AND MONTH(m.tanggal) = :bln"; 
      $p[':bln'] = $f_periode; 
  }

  if ($f_unit_id) { $where .= " AND m.unit_id = :uid"; $p[':uid'] = $f_unit_id; }
  if ($f_jenis_pupuk) { $where .= " AND m.jenis_pupuk = :jp"; $p[':jp'] = $f_jenis_pupuk; }

  // count
  $stc=$pdo->prepare("SELECT COUNT(*) FROM menabur_pupuk_organik m $where");
  $stc->execute($p);
  $total_rows = (int)$stc->fetchColumn();
  $total_pages = max(1, (int)ceil($total_rows / $per_page));

  // data
  $sql = "SELECT m.*, u.nama_unit AS unit_nama, k.nama_kebun AS kebun_nama
          FROM menabur_pupuk_organik m
          LEFT JOIN units u ON u.id = m.unit_id
          LEFT JOIN md_kebun k ON k.id = m.kebun_id
          $where ORDER BY m.tanggal DESC, m.id DESC LIMIT :limit OFFSET :offset";
  $st=$pdo->prepare($sql);
  foreach ($p as $k=>$v) $st->bindValue($k, $v);
  $st->bindValue(':limit',$per_page,PDO::PARAM_INT);
  $st->bindValue(':offset',$offset,PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ====== Helper QS for Export ====== */
function qs_current($extra = []) {
  global $tab, $f_tahun, $f_kebun_id, $f_tanggal, $f_periode, $f_unit_id, $f_jenis_pupuk, $f_rayon_id, $f_asal_gudang_id, $f_keterangan_id;
  $q = [
    'tab' => $tab,
    'tahun' => $f_tahun,
    'periode' => $f_periode,
    'kebun_id' => $f_kebun_id,
    'unit_id' => $f_unit_id,
    'jenis_pupuk' => $f_jenis_pupuk,
    'tanggal' => $f_tanggal,
    'rayon_id' => $f_rayon_id,
    'asal_gudang_id' => $f_asal_gudang_id,
    'keterangan_id' => $f_keterangan_id,
  ];
  return http_build_query(array_merge($q, $extra));
}

$currentPage = 'pemupukan_organik';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* --- CONTAINER & TABLE GRID STYLE (TM Style) --- */
  .sticky-container {
    max-height: 70vh; /* Tinggi maksimal area scroll */
    overflow: auto;
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }

  table.table-grid {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1600px; 
  }

  /* Garis Grid Penuh */
  table.table-grid th, 
  table.table-grid td {
    padding: 0.65rem 0.75rem;
    font-size: 0.85rem;
    white-space: nowrap;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #e2e8f0;
  }

  table.table-grid th:last-child, 
  table.table-grid td:last-child {
    border-right: none;
  }

  /* Header Sticky */
  table.table-grid thead th {
    position: sticky;
    top: 0;
    background: #059fd3; /* Warna Biru */
    color: #fff;
    z-index: 10;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    height: 55px; 
    vertical-align: middle;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  /* Footer Sticky (TOTAL) */
  table.table-grid tfoot td {
    position: sticky;
    bottom: 0;
    background: #0370a2ff; /* Warna Hijau Muda */
    color: #ffffffff;
    z-index: 10;
    font-weight: 700;
    border-top: 2px solid #2a2c2cff;
    box-shadow: 0 -2px 4px rgba(0, 123, 180, 0.05);
  }

  table.table-grid tbody tr:hover td {
    background-color: #f0f9ff;
  }

  /* TAB NAVIGATION STYLE */
  .tab-nav { display: flex; gap: 0.5rem; background: #f1f5f9; padding: 0.5rem; border-radius: 0.75rem; display: inline-flex; }
  .tab-link {
    padding: 0.5rem 1.25rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.875rem; transition: all 0.2s;
    text-decoration: none; color: #64748b;
  }
  .tab-link.active { background: #fff; color: #059fd3; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
  .tab-link:hover:not(.active) { background: #e2e8f0; color: #334155; }

  /* Utilities */
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
  .btn-icon { background: transparent; border: none; padding: 0.25rem; cursor: pointer; }
  .text-right { text-align: right; }
</style>

<div class="space-y-6">
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Pemupukan Organik</h1>
        <p class="text-gray-500 text-sm mt-1">Kelola data menabur dan angkutan pupuk</p>
    </div>
    <div class="flex gap-2">
        <a href="cetak/pemupukan_organik_excel.php?<?= qs_current() ?>" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-700 flex items-center gap-2 shadow-sm transition no-underline text-sm font-medium">
            <i class="ti ti-file-spreadsheet"></i> Excel
        </a>
        <a href="cetak/pemupukan_organik_pdf.php?<?= qs_current() ?>" target="_blank" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 shadow-sm transition no-underline text-sm font-medium">
            <i class="ti ti-file-type-pdf"></i> PDF
        </a>
        
        <?php if ($canInput): ?>
        <button id="btn-add" class="bg-cyan-600 text-white px-4 py-2 rounded-lg hover:bg-cyan-900 flex items-center gap-2 shadow-sm transition ml-2 text-sm font-medium">
            <i class="ti ti-plus"></i> Tambah Data
        </button>
        <?php endif; ?>
    </div>
  </div>

  <div class="tab-nav">
    <a href="?tab=menabur&tahun=<?= $f_tahun ?>&periode=<?= $f_periode ?>" class="tab-link <?= $tab==='menabur' ? 'active' : '' ?>">Menabur Pupuk</a>
    <a href="?tab=angkutan&tahun=<?= $f_tahun ?>&periode=<?= $f_periode ?>" class="tab-link <?= $tab==='angkutan' ? 'active' : '' ?>">Angkutan Pupuk</a>
  </div>

  <form method="GET" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
        <select name="tahun" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">Semua Tahun</option>
            <?php for($y=2020; $y<=date('Y')+1; $y++): ?>
                <option value="<?= $y ?>" <?= (int)$f_tahun===$y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Periode</label>
        <select name="periode" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">— Semua Bulan —</option>
            <?php foreach ($bulanList as $k=>$v): ?>
                <option value="<?= $k ?>" <?= (string)$f_periode===(string)$k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kebun</label>
        <select name="kebun_id" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">— Semua Kebun —</option>
            <?php foreach ($kebun as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= ($f_kebun_id!=='' && (int)$f_kebun_id===(int)$k['id'])?'selected':'' ?>><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit / Defisi</label>
        <select name="unit_id" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">— Semua Unit —</option>
            <?php foreach ($units as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= ($f_unit_id!=='' && (int)$f_unit_id===(int)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Jenis Pupuk</label>
        <select name="jenis_pupuk" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">— Semua Jenis —</option>
            <?php foreach ($pupuk as $jp): ?>
                <option value="<?= htmlspecialchars($jp) ?>" <?= ($f_jenis_pupuk===$jp?'selected':'') ?>><?= htmlspecialchars($jp) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tgl Spesifik</label>
        <input type="date" name="tanggal" value="<?= htmlspecialchars($f_tanggal) ?>" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
    </div>

    <div class="md:col-span-6 flex justify-start items-center pt-2">
       <div class="flex items-center gap-2">
           <label class="text-xs font-bold text-gray-500 uppercase">Baris:</label>
           
           <select name="per_page" class="border rounded px-3 py-1 text-sm focus:ring-2 focus:ring-cyan-500 outline-none bg-white" onchange="this.form.submit()">
               <?php foreach($perOpts as $opt): ?>
                   <option value="<?= $opt ?>" <?= $per_page==$opt?'selected':'' ?>><?= $opt ?></option>
               <?php endforeach; ?>
           </select>
       </div>
    </div>

    <?php if ($tab === 'angkutan'): ?>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Rayon</label>
        <select name="rayon_id" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 outline-none" onchange="this.form.submit()">
            <option value="">— Semua Rayon —</option>
            <?php foreach ($rayons as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= ($f_rayon_id!=='' && (int)$f_rayon_id===(int)$r['id'])?'selected':'' ?>><?= htmlspecialchars($r['nama']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <?php endif; ?>
  </form>

  <div class="sticky-container">
    <table class="table-grid">
        <thead>
        <tr>
            <?php if ($tab==='angkutan'): ?>
                <th class="text-left" style="min-width:80px">Tahun</th>
                <th class="text-left" style="min-width:150px">Kebun</th>
                <th class="text-center" style="min-width:100px">Tanggal</th>
                <th class="text-center" style="min-width:100px">Periode</th>
                <th class="text-left" style="min-width:120px">Rayon</th>
                <th class="text-left" style="min-width:120px">Unit Tujuan</th>
                <th class="text-left" style="min-width:120px">Gudang Asal</th>
                <th class="text-left" style="min-width:120px">Jenis Pupuk</th>
                <th class="text-right" style="min-width:100px">Jumlah (Kg)</th>
                <th class="text-left" style="min-width:150px">Keterangan</th>
                <?php if ($canAction): ?><th class="text-center" style="min-width:100px">Aksi</th><?php endif; ?>
            <?php else: ?>
                <th class="text-left" style="min-width:80px">Tahun</th>
                <th class="text-left" style="min-width:150px">Kebun</th>
                <th class="text-center" style="min-width:100px">Tanggal</th>
                <th class="text-center" style="min-width:100px">Periode</th>
                <th class="text-left" style="min-width:120px">Unit/Defisi</th>
                <th class="text-center" style="min-width:80px">T. Tanam</th>
                <th class="text-center" style="min-width:80px">Blok</th>
                <th class="text-right" style="min-width:100px">Luas (Ha)</th>
                <th class="text-left" style="min-width:120px">Jenis Pupuk</th>
                <th class="text-right" style="min-width:80px">Dosis</th>
                <th class="text-right" style="min-width:100px">Jumlah (Kg)</th>
                <?php if ($canAction): ?><th class="text-center" style="min-width:100px">Aksi</th><?php endif; ?>
            <?php endif; ?>
        </tr>
        </thead>
        <tbody class="text-gray-800">
        <?php 
            $sumLuas = 0; 
            $sumDosis = 0; 
            $sumJumlah = 0;
        ?>

        <?php if (!$rows): ?>
            <tr><td colspan="<?= $tab==='angkutan' ? ($canAction?11:10) : ($canAction?12:11) ?>" class="text-center py-10 text-gray-500 italic">Tidak ada data.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <?php 
                if ($tab === 'angkutan') {
                    $sumJumlah += (float)$r['jumlah'];
                } else {
                    $sumLuas += (float)$r['luas'];
                    $sumDosis += (isset($r['dosis']) ? (float)$r['dosis'] : 0);
                    $sumJumlah += (float)$r['jumlah'];
                }
            ?>
            <tr class="hover:bg-blue-50 transition-colors">
            <?php if ($tab==='angkutan'): ?>
                <td><?= date('Y', strtotime($r['tanggal'])) ?></td>
                <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
                <td class="text-center"><?= htmlspecialchars($r['tanggal']) ?></td>
                <td class="text-center"><?php $m=(int)date('n', strtotime($r['tanggal'])); echo $bulanList[$m] ?? $m; ?></td>
                <td><?= htmlspecialchars($r['nama_rayon'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['nama_gudang'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                <td class="text-right font-mono"><?= number_format((float)$r['jumlah'],2) ?></td>
                <td><?= htmlspecialchars($r['nama_keterangan'] ?? '-') ?></td>
                
                <?php if ($canAction): ?>
                <td class="text-center">
                    <div class="flex justify-center gap-1">
                        <button class="btn-icon text-cyan-600 hover:text-cyan-800 btn-edit" data-tab="angkutan" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'><i class="ti ti-pencil"></i></button>
                        <button class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-tab="angkutan" data-id="<?= (int)$r['id'] ?>"><i class="ti ti-trash"></i></button>
                    </div>
                </td>
                <?php endif; ?>

            <?php else: ?>
                <td><?= date('Y', strtotime($r['tanggal'])) ?></td>
                <td><?= htmlspecialchars($r['kebun_nama'] ?? '-') ?></td>
                <td class="text-center"><?= htmlspecialchars($r['tanggal']) ?></td>
                <td class="text-center"><?php $m=(int)date('n', strtotime($r['tanggal'])); echo $bulanList[$m] ?? $m; ?></td>
                <td><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
                <td class="text-center"><?= htmlspecialchars($r['t_tanam'] ?? '-') ?></td>
                <td class="text-center"><?= htmlspecialchars($r['blok']) ?></td>
                <td class="text-right font-mono"><?= number_format((float)$r['luas'],2) ?></td>
                <td><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                <td class="text-right font-mono"><?= (isset($r['dosis']) && $r['dosis']!=='') ? number_format((float)$r['dosis'],2) : '-' ?></td>
                <td class="text-right font-mono"><?= number_format((float)$r['jumlah'],2) ?></td>
                
                <?php if ($canAction): ?>
                <td class="text-center">
                    <div class="flex items-center justify-center gap-1">
                        <button class="btn-icon text-cyan-600 hover:text-cyan-800 btn-edit" data-tab="menabur" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'><i class="ti ti-pencil"></i></button>
                        <button class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-tab="menabur" data-id="<?= (int)$r['id'] ?>"><i class="ti ti-trash"></i></button>
                    </div>
                </td>
                <?php endif; ?>
            <?php endif; ?>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        
        <?php if ($rows): ?>
        <tfoot>
            <tr>
                <?php if ($tab === 'angkutan'): ?>
                    <td colspan="8" class="text-right pr-4 uppercase tracking-wide">Total</td>
                    <td class="text-right font-mono"><?= number_format($sumJumlah, 2) ?></td>
                    <td colspan="<?= $canAction ? 2 : 1 ?>"></td>
                <?php else: ?>
                    <td colspan="7" class="text-right pr-4 uppercase tracking-wide">Total</td>
                    <td class="text-right font-mono"><?= number_format($sumLuas, 2) ?></td>
                    <td></td> <td class="text-right font-mono"><?= number_format($sumDosis, 2) ?></td>
                    <td class="text-right font-mono"><?= number_format($sumJumlah, 2) ?></td>
                    <?php if ($canAction): ?><td></td><?php endif; ?>
                <?php endif; ?>
            </tr>
        </footer>
        <?php endif; ?>
    </table>
  </div>

  <div class="flex flex-col md:flex-row justify-between items-center gap-3 text-sm">
    <div class="text-gray-600">
        Menampilkan <strong><?= ($total_rows > 0) ? $offset+1 : 0 ?></strong>–<strong><?= min($offset+$per_page, $total_rows) ?></strong> dari <strong><?= number_format($total_rows) ?></strong> data
    </div>
    <div class="flex items-center gap-2">
        <?php 
        function page_url($p) {
            $params = $_GET; $params['page'] = $p; return '?' . http_build_query($params);
        }
        ?>
        <a href="<?= $page > 1 ? page_url($page-1) : '#' ?>" class="px-3 py-1 border rounded hover:bg-gray-50 <?= $page<=1 ? 'pointer-events-none opacity-50' : '' ?>">Prev</a>
        <span class="px-2 text-gray-600">Hal <?= $page ?> / <?= $total_pages ?></span>
        <a href="<?= $page < $total_pages ? page_url($page+1) : '#' ?>" class="px-3 py-1 border rounded hover:bg-gray-50 <?= $page>=$total_pages ? 'pointer-events-none opacity-50' : '' ?>">Next</a>
    </div>
  </div>
</div>

<?php if ($canInput): ?>
<div id="crud-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-opacity">
  <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform scale-100 transition-transform">
    <div class="flex items-center justify-between mb-6 border-b pb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-900">Tambah Data</h3>
      <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600 transition">&times;</button>
    </div>

    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="tab" id="form-tab" value="<?= htmlspecialchars($tab) ?>">
      <input type="hidden" name="id" id="form-id">

      <div id="group-angkutan" class="<?= $tab==='angkutan' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Kebun</label>
                <select id="kebun_id_angkutan" name="kebun_id" class="i-select"><option value="">— Pilih —</option><?php foreach ($kebun as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Rayon</label>
                <select id="rayon_id_angkutan" name="rayon_id" class="i-select"><option value="">— Pilih —</option><?php foreach ($rayons as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Unit Tujuan *</label>
                <select name="unit_tujuan_id" id="unit_tujuan_id" class="i-select" required><option value="">— Pilih —</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Gudang Asal *</label>
                <select name="asal_gudang_id" id="asal_gudang_id" class="i-select" required><option value="">— Pilih —</option><?php foreach ($gudangs as $g): ?><option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['nama']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tanggal *</label><input type="date" name="tanggal" id="tanggal_angkutan" class="i-input" required></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Jenis Pupuk *</label>
                <select name="jenis_pupuk" id="jenis_pupuk_angkutan" class="i-select" required><option value="">— Pilih —</option><?php foreach ($pupuk as $p): ?><option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Jumlah (Kg)</label><input type="number" step="0.01" name="jumlah" id="jumlah_angkutan" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">No SPB</label><input type="text" name="no_spb" id="no_spb_angkutan" class="i-input"></div>
            <div class="md:col-span-2"><label class="block text-xs font-bold text-gray-600 mb-1">Keterangan</label>
                <select name="keterangan_id" id="keterangan_id_angkutan" class="i-select"><option value="">— Opsional —</option><?php foreach ($keterangans as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['keterangan']) ?></option><?php endforeach; ?></select>
            </div>
        </div>
      </div>

      <div id="group-menabur" class="<?= $tab==='menabur' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Unit *</label>
                <select name="unit_id" id="unit_id" class="i-select" required><option value="">— Pilih —</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Kebun</label>
                <select id="kebun_id_menabur" name="kebun_id" class="i-select"><option value="">— Pilih —</option><?php foreach ($kebun as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Blok *</label>
                <select name="blok" id="blok" class="i-select" required><option value="">— Pilih Unit Dulu —</option></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tanggal *</label><input type="date" name="tanggal" id="tanggal_menabur" class="i-input" required></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Tahun</label><input type="number" name="tahun" id="tahun" class="i-input" placeholder="YYYY"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Jenis Pupuk *</label>
                <select name="jenis_pupuk" id="jenis_pupuk_menabur" class="i-select" required><option value="">— Pilih —</option><?php foreach ($pupuk as $p): ?><option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Tahun Tanam</label>
                <input type="number" name="t_tanam" id="t_tanam" class="w-full border rounded px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cyan-500" placeholder="YYYY">
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Rayon</label>
                <select name="rayon_id" id="rayon_id_menabur" class="i-select"><option value="">— Pilih —</option><?php foreach ($rayons as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Dosis</label><input type="number" step="0.01" name="dosis" id="dosis" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Jumlah (Kg)</label><input type="number" step="0.01" name="jumlah" id="jumlah_menabur" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Luas (Ha)</label><input type="number" step="0.01" name="luas" id="luas" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Invt. Pokok</label><input type="number" step="1" name="invt_pokok" id="invt_pokok" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">No AU-58</label><input type="text" name="no_au_58" id="no_au_58_menabur" class="i-input"></div>
            <div class="md:col-span-3"><label class="block text-xs font-bold text-gray-600 mb-1">Keterangan</label>
                <select name="keterangan_id" id="keterangan_id_menabur" class="i-select"><option value="">— Opsional —</option><?php foreach ($keterangans as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['keterangan']) ?></option><?php endforeach; ?></select>
            </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50 transition">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 transition shadow-lg shadow-cyan-500/30">Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Pass Permission to JS ---
    const CAN_INPUT  = <?= $canInput ? 'true' : 'false'; ?>;  // Admin & Staf
    const CAN_ACTION = <?= $canAction ? 'true' : 'false'; ?>; // Admin Only
    
    const $ = s => document.querySelector(s);
    async function fetchJSON(url){ const r = await fetch(url); return r.json(); }
    function fillSelect(el, arr, placeholder='— Pilih —'){
        el.innerHTML='';
        const d=document.createElement('option'); d.value=''; d.textContent=placeholder; el.appendChild(d);
        (arr||[]).forEach(v=>{
            const val = (typeof v === 'object') ? (v.value || v.id || v) : v;
            const lbl = (typeof v === 'object') ? (v.label || v.nama_kebun || v) : v;
            const op=document.createElement('option'); op.value=val; op.textContent=lbl; el.appendChild(op);
        });
    }

    // Load Pupuk Options
    (async ()=>{
        try{
        const j = await fetchJSON('?ajax=options&type=pupuk');
        const list = (j.success && Array.isArray(j.data)) ? j.data : [];
        const a = $('#jenis_pupuk_angkutan'); if (a) fillSelect(a, list, '— Pilih Jenis Pupuk —');
        const m = $('#jenis_pupuk_menabur');  if (m) fillSelect(m, list, '— Pilih Jenis Pupuk —');
        }catch{}
    })();

    async function refreshBlok(){
        const uid = $('#unit_id')?.value || '';
        const sel = $('#blok');
        if (!uid){ if(sel) sel.innerHTML='<option value="">— Pilih Unit dulu —</option>'; return; }
        try{
        const j = await fetchJSON(`?ajax=options&type=blok&unit_id=${encodeURIComponent(uid)}`);
        const list = (j.success && Array.isArray(j.data)) ? j.data : [];
        if (sel) fillSelect(sel, list, '— Pilih Blok —');
        }catch{ if (sel) sel.innerHTML='<option value="">— Pilih Blok —</option>'; }
    }
    $('#unit_id')?.addEventListener('change', refreshBlok);

    /* ===== MODAL LOGIC (HANYA JIKA CAN_INPUT) ===== */
    if (CAN_INPUT) {
        const modal = $('#crud-modal'), form = $('#crud-form'), title = $('#modal-title');
        const open  = ()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); };
        const close = ()=>{ modal.classList.add('hidden');   modal.classList.remove('flex'); };

        if ($('#btn-add')) {
            $('#btn-add').addEventListener('click', ()=>{
                form.reset(); $('#form-action').value='store'; $('#form-id').value='';
                title.textContent='Tambah Data';
                const tab = $('#form-tab').value = '<?= htmlspecialchars($tab) ?>';
                document.getElementById('group-angkutan').classList.toggle('hidden', tab!=='angkutan');
                document.getElementById('group-menabur').classList.toggle('hidden',   tab!=='menabur');
                if (tab==='menabur') refreshBlok();
                open();
            });
        }
        $('#btn-close')?.addEventListener('click', close);
        $('#btn-cancel')?.addEventListener('click', close);

        // Edit & Delete (Hanya jika CAN_ACTION aka Admin)
        document.body.addEventListener('click', async (e)=>{
            const btnE = e.target.closest('.btn-edit');
            const btnD = e.target.closest('.btn-delete');

            if (btnE && CAN_ACTION){
                const row = JSON.parse(btnE.dataset.json); const tab = btnE.dataset.tab;
                form.reset(); $('#form-action').value='update'; $('#form-id').value=row.id; $('#form-tab').value = tab;
                title.textContent='Edit Data';
                document.getElementById('group-angkutan').classList.toggle('hidden', tab!=='angkutan');
                document.getElementById('group-menabur').classList.toggle('hidden',   tab!=='menabur');

                if (tab==='angkutan'){
                    $('#kebun_id_angkutan').value     = row.kebun_id ?? '';
                    $('#rayon_id').value              = row.rayon_id ?? '';
                    $('#unit_tujuan_id').value        = row.unit_tujuan_id ?? '';
                    $('#asal_gudang_id').value        = row.asal_gudang_id ?? '';
                    $('#tanggal_angkutan').value      = row.tanggal ?? '';
                    $('#jenis_pupuk_angkutan').value  = row.jenis_pupuk ?? '';
                    $('#jumlah_angkutan').value       = row.jumlah ?? '';
                    $('#keterangan_id').value         = row.keterangan_id ?? '';
                } else {
                    $('#kebun_id_menabur').value      = row.kebun_id ?? '';
                    $('#unit_id').value               = row.unit_id ?? '';
                    await refreshBlok();
                    $('#blok').value                  = row.blok ?? '';
                    $('#tanggal_menabur').value       = row.tanggal ?? '';
                    $('#t_tanam').value               = row.t_tanam ?? '';
                    $('#luas').value                  = row.luas ?? '';
                    $('#jenis_pupuk_menabur').value   = row.jenis_pupuk ?? '';
                    $('#dosis').value                 = row.dosis ?? '';
                    $('#jumlah_menabur').value        = row.jumlah ?? '';
                    $('#invt_pokok').value            = row.invt_pokok ?? '';
                }
                open();
            }

            if (btnD && CAN_ACTION){
                const id = btnD.dataset.id; const tab = btnD.dataset.tab;
                Swal.fire({title:'Hapus data?', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Hapus'})
                    .then(res=>{
                    if (!res.isConfirmed) return;
                    const fd = new FormData();
                    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
                    fd.append('action','delete'); fd.append('tab',tab); fd.append('id',id);
                    fetch('pemupukan_organik_crud.php',{method:'POST', body:fd})
                        .then(r=>r.json()).then(j=>{
                        if (j.success) Swal.fire('Terhapus!','', 'success').then(()=>location.reload());
                        else Swal.fire('Gagal', j.message||'Error', 'error');
                        });
                    });
            }
        });

        form.addEventListener('submit',(e)=>{
            e.preventDefault();
            const tab = $('#form-tab').value;
            const fd  = new FormData(form);
            fd.set('action', $('#form-action').value);
            fd.set('tab', tab);

            if (tab==='angkutan'){
                fd.set('kebun_id', $('#kebun_id_angkutan').value);
                fd.set('rayon_id', $('#rayon_id').value);
                fd.set('unit_tujuan_id', $('#unit_tujuan_id').value);
                fd.set('asal_gudang_id', $('#asal_gudang_id').value);
                fd.set('tanggal', $('#tanggal_angkutan').value);
                fd.set('jenis_pupuk', $('#jenis_pupuk_angkutan').value);
                fd.set('jumlah', $('#jumlah_angkutan').value || '');
                fd.set('keterangan_id', $('#keterangan_id').value || '');
            } else {
                fd.set('kebun_id', $('#kebun_id_menabur').value);
                fd.set('tanggal', $('#tanggal_menabur').value);
                fd.set('t_tanam', $('#t_tanam').value || '');
                fd.set('jenis_pupuk', $('#jenis_pupuk_menabur').value);
                fd.set('dosis', $('#dosis').value || '');
                fd.set('jumlah', $('#jumlah_menabur').value || '');
                fd.set('luas', $('#luas').value || '');
                fd.set('invt_pokok', $('#invt_pokok').value || '');
            }

            fetch('pemupukan_organik_crud.php',{method:'POST', body:fd})
            .then(r=>r.json()).then(j=>{
                if (j.success){ close(); Swal.fire({icon:'success', title:'Berhasil', timer:1200, showConfirmButton:false}).then(()=>location.reload()); }
                else { Swal.fire('Gagal', j.message||'Error', 'error'); }
            });
        });
    }
    
    $('#tanggal_menabur')?.addEventListener('change', e=>{
        const y = e.target.value.slice(0,4);
        if(y && $('#tahun') && !$('#tahun').value) $('#tahun').value=y;
    });
});
</script>