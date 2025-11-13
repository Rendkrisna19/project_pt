<?php
// admin/pemupukan_organik.php
// MODIFIKASI: +Rayon, +Gudang(select), +Keterangan(select)
// MODIFIKASI 2: Sembunyikan filter angkutan di tab menabur, Hapus supir dari angkutan

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// ===== ROLE =====
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

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
    if ($type === 'kebun') {
      $st = $pdo->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun");
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);
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
// BARU: Master untuk Angkutan
$rayons      = $pdo->query("SELECT id, nama FROM md_rayon ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$gudangs     = $pdo->query("SELECT id, nama FROM md_asal_gudang ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$keterangans = $pdo->query("SELECT id, keterangan FROM md_keterangan ORDER BY keterangan")->fetchAll(PDO::FETCH_ASSOC);

/* ====== Tabs & Filters ====== */
$tab = $_GET['tab'] ?? 'menabur';
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

/* ====== Filter ====== */
$f_tahun          = ($_GET['tahun'] ?? '') === '' ? '' : (int)$_GET['tahun'];
$f_kebun_id       = ($_GET['kebun_id'] ?? '') === '' ? '' : (int)$_GET['kebun_id'];
$f_tanggal        = trim((string)($_GET['tanggal'] ?? ''));
$f_periode        = trim((string)($_GET['periode'] ?? ''));
$f_unit_id        = ($_GET['unit_id'] ?? '') === '' ? '' : (int)$_GET['unit_id'];
$f_jenis_pupuk    = trim((string)($_GET['jenis_pupuk'] ?? ''));
// BARU: Filter Angkutan
$f_rayon_id       = ($_GET['rayon_id'] ?? '') === '' ? '' : (int)$_GET['rayon_id'];
$f_asal_gudang_id = ($_GET['asal_gudang_id'] ?? '') === '' ? '' : (int)$_GET['asal_gudang_id'];
$f_keterangan_id  = ($_GET['keterangan_id'] ?? '') === '' ? '' : (int)$_GET['keterangan_id'];

/* ====== Pagination ====== */
$page     = max(1, (int)($_GET['page'] ?? 1));
$perOpts  = [10,25,50,100];
$per_page = (int)($_GET['per_page'] ?? 10);
if (!in_array($per_page, $perOpts, true)) $per_page = 10;
$offset   = ($page - 1) * $per_page;

/* ====== Bulan ====== */
$bulanList = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

/* ====== Query Data ====== */
if ($tab === 'angkutan') {
  $title = "Data Angkutan Pupuk Organik";

  $where = " WHERE 1=1"; $p = [];
  if ($f_tahun !== '')                 { $where .= " AND YEAR(a.tanggal) = :thn";   $p[':thn'] = (int)$f_tahun; }
  if ($f_kebun_id !== '')              { $where .= " AND a.kebun_id = :kid_kebun";  $p[':kid_kebun'] = (int)$f_kebun_id; }
  if ($f_tanggal !== '')               { $where .= " AND a.tanggal = :tgl";         $p[':tgl'] = $f_tanggal; }
  if ($f_periode !== '' && ctype_digit($f_periode)) { $where .= " AND MONTH(a.tanggal) = :bln"; $p[':bln'] = (int)$f_periode; }
  if ($f_unit_id !== '')               { $where .= " AND a.unit_tujuan_id = :uid";  $p[':uid'] = (int)$f_unit_id; }
  if ($f_jenis_pupuk !== '')           { $where .= " AND a.jenis_pupuk = :jp";      $p[':jp']  = $f_jenis_pupuk; }
  // BARU
  if ($f_rayon_id !== '')              { $where .= " AND a.rayon_id = :rid";        $p[':rid'] = (int)$f_rayon_id; }
  if ($f_asal_gudang_id !== '')        { $where .= " AND a.asal_gudang_id = :gid";  $p[':gid'] = (int)$f_asal_gudang_id; }
  if ($f_keterangan_id !== '')         { $where .= " AND a.keterangan_id = :kid";   $p[':kid'] = (int)$f_keterangan_id; }

  // count
  $sql_count = "SELECT COUNT(*) FROM angkutan_pupuk_organik a LEFT JOIN md_kebun k ON k.id = a.kebun_id $where";
  $stc = $pdo->prepare($sql_count); $stc->execute($p);
  $total_rows  = (int)$stc->fetchColumn();
  $total_pages = max(1, (int)ceil($total_rows / $per_page));

  // data
  $sql = "SELECT a.*,
            u.nama_unit AS unit_tujuan_nama,
            k.nama_kebun AS kebun_nama,
            r.nama AS nama_rayon,
            g.nama AS nama_gudang,
            ket.keterangan AS nama_keterangan
          FROM angkutan_pupuk_organik a
          LEFT JOIN units u ON u.id = a.unit_tujuan_id
          LEFT JOIN md_kebun k ON k.id = a.kebun_id
          LEFT JOIN md_rayon r ON r.id = a.rayon_id
          LEFT JOIN md_asal_gudang g ON g.id = a.asal_gudang_id
          LEFT JOIN md_keterangan ket ON ket.id = a.keterangan_id
          $where
          ORDER BY a.tanggal DESC, a.id DESC
          LIMIT :limit OFFSET :offset";
  $st=$pdo->prepare($sql);
  foreach ($p as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
  $st->bindValue(':limit',$per_page,PDO::PARAM_INT);
  $st->bindValue(':offset',$offset,PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // totals
  $sql_tot = "SELECT COALESCE(SUM(a.jumlah),0) AS tot_kg
              FROM angkutan_pupuk_organik a
              LEFT JOIN md_kebun k ON k.id = a.kebun_id
              $where";
  $stt=$pdo->prepare($sql_tot);
  foreach ($p as $k=>$v) $stt->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
  $stt->execute();
  $tot_all    = $stt->fetch(PDO::FETCH_ASSOC);
  $tot_all_kg = (float)($tot_all['tot_kg'] ?? 0);

  $sum_page_kg = 0.0; foreach ($rows as $r) $sum_page_kg += (float)($r['jumlah'] ?? 0);

  // untuk konsistensi di template (variabel yang tidak relevan diset 0)
  $tot_all_luas = 0.0;
  $tot_all_invt = 0.0;

} else {
  $title = "Data Penaburan Pupuk Organik";

  $where = " WHERE 1=1"; $p = [];
  if ($f_tahun !== '')       { $where .= " AND YEAR(m.tanggal) = :thn"; $p[':thn'] = (int)$f_tahun; }
  if ($f_kebun_id !== '')    { $where .= " AND m.kebun_id = :kid";      $p[':kid'] = (int)$f_kebun_id; }
  if ($f_tanggal !== '')     { $where .= " AND m.tanggal = :tgl";       $p[':tgl'] = $f_tanggal; }
  if ($f_periode !== '' && ctype_digit($f_periode)) { $where .= " AND MONTH(m.tanggal) = :bln"; $p[':bln'] = (int)$f_periode; }
  if ($f_unit_id !== '')     { $where .= " AND m.unit_id = :uid";       $p[':uid'] = (int)$f_unit_id; }
  if ($f_jenis_pupuk !== '') { $where .= " AND m.jenis_pupuk = :jp";    $p[':jp']  = $f_jenis_pupuk; }
  // filter angkutan (rayon, gudang, ket) tidak berlaku di sini

  // count
  $sql_count = "SELECT COUNT(*) FROM menabur_pupuk_organik m LEFT JOIN md_kebun k ON k.id = m.kebun_id $where";
  $stc=$pdo->prepare($sql_count); $stc->execute($p);
  $total_rows  = (int)$stc->fetchColumn();
  $total_pages = max(1, (int)ceil($total_rows / $per_page));

  // data
  $sql = "SELECT m.*, u.nama_unit AS unit_nama, k.nama_kebun AS kebun_nama
          FROM menabur_pupuk_organik m
          LEFT JOIN units u ON u.id = m.unit_id
          LEFT JOIN md_kebun k ON k.id = m.kebun_id
          $where
          ORDER BY m.tanggal DESC, m.id DESC
          LIMIT :limit OFFSET :offset";
  $st=$pdo->prepare($sql);
  foreach ($p as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
  $st->bindValue(':limit',$per_page,PDO::PARAM_INT);
  $st->bindValue(':offset',$offset,PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // totals
  $sql_tot = "SELECT
                COALESCE(SUM(m.jumlah),0)     AS tot_kg,
                COALESCE(SUM(m.luas),0)       AS tot_luas,
                COALESCE(SUM(m.invt_pokok),0) AS tot_invt,
                AVG(NULLIF(m.dosis,0))        AS avg_dosis
              FROM menabur_pupuk_organik m
              LEFT JOIN md_kebun k ON k.id = m.kebun_id
              $where";
  $stt=$pdo->prepare($sql_tot);
  foreach ($p as $k=>$v) $stt->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
  $stt->execute();
  $tot_all        = $stt->fetch(PDO::FETCH_ASSOC);
  $tot_all_kg     = (float)($tot_all['tot_kg'] ?? 0);
  $tot_all_luas   = (float)($tot_all['tot_luas'] ?? 0);
  $tot_all_invt   = (float)($tot_all['tot_invt'] ?? 0);
  $tot_all_avgd   = (float)($tot_all['avg_dosis'] ?? 0);

  $sum_page_kg = $sum_page_luas = $sum_page_invt = $sum_page_avgd = 0.0; $cnt_dosis = 0;
  foreach ($rows as $r) {
    $sum_page_kg   += (float)($r['jumlah'] ?? 0);
    $sum_page_luas += (float)($r['luas'] ?? 0);
    $sum_page_invt += (float)($r['invt_pokok'] ?? 0);
    if (isset($r['dosis']) && $r['dosis']!=='') { $sum_page_avgd += (float)$r['dosis']; $cnt_dosis++; }
  }
  $avg_page_dosis = $cnt_dosis ? ($sum_page_avgd/$cnt_dosis) : 0.0;
}

/* ====== Helper QS ====== */
function qs_no_page(array $extra = []) {
  $base = [
    'tab'          => $_GET['tab']          ?? '',
    'tahun'        => $_GET['tahun']        ?? '',
    'kebun_id'     => $_GET['kebun_id']     ?? '',
    'tanggal'      => $_GET['tanggal']      ?? '',
    'periode'      => $_GET['periode']      ?? '',
    'unit_id'      => $_GET['unit_id']      ?? '',
    'jenis_pupuk'  => $_GET['jenis_pupuk']  ?? '',
    // BARU
    'rayon_id'       => $_GET['rayon_id']       ?? '',
    'asal_gudang_id' => $_GET['asal_gudang_id'] ?? '',
    'keterangan_id'  => $_GET['keterangan_id']  ?? '',
  ];
  return http_build_query(array_merge($base, $extra));
}

$currentPage = 'pemupukan_organik';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  :root{
    --tab-active: #111827;   /* teks aktif */
    --tab-muted:  #6b7280;   /* teks nonaktif */
    --tab-line:   #16a34a;   /* hijau underline */
  }

  .i-input,.i-select,.i-textarea{
    border:1px solid #e5e7eb;border-radius:.6rem;padding:.5rem .75rem;width:100%;outline:none
  }
  .i-input:focus,.i-select:focus,.i-textarea:focus{border-color:#34d399;box-shadow:0 0 0 3px rgba(16,185,129,.15)}

  .btn{display:inline-flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;background:#fff;color:#1f2937;border-radius:.6rem;padding:.5rem 1rem}
  .btn:hover{background:#f9fafb}
  .btn-dark{background:#059669;color:#fff;border-color:#059669}
  .btn-dark:hover{background:#047857}

  .btn-icon{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:.5rem;border:1px solid #e5e7eb;background:#fff}
  .btn-icon:hover{background:#f9fafb}
  .btn-icon[disabled]{opacity:.45;cursor:not-allowed}

  .tbl-wrap{max-height:60vh;overflow-y:auto}
  thead.sticky{position:sticky;top:0;z-index:10;background:#d1fae5}

  .tabbar{display:flex;gap:1.5rem;align-items:flex-end;border-bottom:1px solid #e5e7eb;padding-bottom:.25rem}
  .tablink{position:relative;padding:.4rem .25rem .6rem;border:none;background:transparent;color:var(--tab-muted);font-weight:500;font-size:.95rem;cursor:pointer;transition:color .25s}
  .tablink:hover{color:#374151}
  .tablink::after{content:"";position:absolute;left:0;bottom:-1px;width:100%;height:2.5px;background:var(--tab-line);border-radius:999px;transform:scaleX(0);transform-origin:left;transition:transform .25s}
  .tablink.active{color:var(--tab-active);font-weight:600}
  .tablink.active::after{transform:scaleX(1)}

  .thead-green{background:#007521!important;}
  .thead-green th{color:white!important;}
  .bg-blue-50{background:#eff6ff;}
</style>

<div class="space-y-6">
  <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">🌿 Pemupukan Organik</h1>

  <?php
    $qsPersist = [
      'tahun'=>$f_tahun,'kebun_id'=>$f_kebun_id,'tanggal'=>$f_tanggal,'periode'=>$f_periode,
      'unit_id'=>$f_unit_id,'jenis_pupuk'=>$f_jenis_pupuk,
      'rayon_id'=>$f_rayon_id,'asal_gudang_id'=>$f_asal_gudang_id,'keterangan_id'=>$f_keterangan_id
    ];
    $qsTabMen = http_build_query(array_merge(['tab'=>'menabur'],  $qsPersist));
    $qsTabAng = http_build_query(array_merge(['tab'=>'angkutan'],$qsPersist));
  ?>
  <div class="flex flex-wrap gap-2 md:gap-4 p-2">
    <a href="?<?= $qsTabMen ?>" class="tablink <?= $tab==='menabur' ? 'active' : '' ?>">Menabur Pupuk</a>
    <a href="?<?= $qsTabAng ?>" class="tablink <?= $tab==='angkutan' ? 'active' : '' ?>">Angkutan Pupuk</a>
  </div>

  <div class="bg-white p-5 shadow-sm">
    <form class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end" method="GET">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

      <div class="md:col-span-2">
        <label class="block text-sm text-gray-600 mb-1">Tahun</label>
        <input type="number" name="tahun" value="<?= htmlspecialchars($f_tahun) ?>" class="i-input" placeholder="YYYY" min="2000" max="2100">
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm text-gray-600 mb-1">Kebun</label>
        <select name="kebun_id" class="i-select">
          <option value="">— Semua Kebun —</option>
          <?php foreach ($kebun as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= ($f_kebun_id!=='' && (int)$f_kebun_id===(int)$k['id'])?'selected':'' ?>>
              <?= htmlspecialchars($k['nama_kebun']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm text-gray-600 mb-1">Tanggal</label>
        <input type="date" name="tanggal" value="<?= htmlspecialchars($f_tanggal) ?>" class="i-input">
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm text-gray-600 mb-1">Periode (Bulan)</label>
        <select name="periode" class="i-select">
          <option value="">— Semua Bulan —</option>
          <?php foreach ($bulanList as $num=>$name): ?>
            <option value="<?= $num ?>" <?= ($f_periode!=='' && (int)$f_periode===$num)?'selected':'' ?>><?= $name ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm text-gray-600 mb-1">Unit / Defisi</label>
        <select name="unit_id" class="i-select">
          <option value="">— Semua Unit —</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($f_unit_id!=='' && (int)$f_unit_id===(int)$u['id'])?'selected':'' ?>>
              <?= htmlspecialchars($u['nama_unit']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($tab === 'angkutan'): ?>
        <div class="md:col-span-2">
          <label class="block text-sm text-gray-600 mb-1">Rayon (Angkutan)</label>
          <select name="rayon_id" class="i-select">
            <option value="">— Semua Rayon —</option>
            <?php foreach ($rayons as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= ($f_rayon_id!=='' && (int)$f_rayon_id===(int)$r['id'])?'selected':'' ?>>
                <?= htmlspecialchars($r['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm text-gray-600 mb-1">Gudang Asal (Angkutan)</label>
          <select name="asal_gudang_id" class="i-select">
            <option value="">— Semua Gudang —</option>
            <?php foreach ($gudangs as $g): ?>
              <option value="<?= (int)$g['id'] ?>" <?= ($f_asal_gudang_id!=='' && (int)$f_asal_gudang_id===(int)$g['id'])?'selected':'' ?>>
                <?= htmlspecialchars($g['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm text-gray-600 mb-1">Keterangan (Angkutan)</label>
          <select name="keterangan_id" class="i-select">
            <option value="">— Semua Keterangan —</option>
            <?php foreach ($keterangans as $k): ?>
              <option value="<?= (int)$k['id'] ?>" <?= ($f_keterangan_id!=='' && (int)$f_keterangan_id===(int)$k['id'])?'selected':'' ?>>
                <?= htmlspecialchars($k['keterangan']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="md:col-span-2">
        <label class="block text-sm text-gray-600 mb-1">Jenis Pupuk</label>
        <select name="jenis_pupuk" class="i-select">
          <option value="">— Semua Jenis —</option>
          <?php foreach ($pupuk as $jp): ?>
            <option value="<?= htmlspecialchars($jp) ?>" <?= ($f_jenis_pupuk===$jp?'selected':'') ?>><?= htmlspecialchars($jp) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-12 flex gap-2 justify-between mt-2">
        <div class="flex items-center gap-3">
          <?php $from = $total_rows ? ($offset + 1) : 0; $to = min($offset + $per_page, $total_rows); ?>
          <span class="text-sm text-gray-700">Menampilkan <strong><?= $from ?></strong>–<strong><?= $to ?></strong> dari <strong><?= number_format($total_rows) ?></strong> data</span>
          <div>
            <input type="hidden" name="page" value="1">
            <label class="text-sm text-gray-700 mr-2">Baris/hal</label>
            <select name="per_page" class="i-select" style="width:auto;display:inline-block" onchange="this.form.submit()">
              <?php foreach ($perOpts as $opt): ?>
                <option value="<?= $opt ?>" <?= $per_page===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="flex gap-2">
          <?php $qs = qs_no_page(); ?>
          <a href="cetak/pemupukan_organik_excel.php?<?= $qs ?>" class="btn"><i class="ti ti-file-spreadsheet text-emerald-600"></i> Export Excel</a>
          <a href="cetak/pemupukan_organik_pdf.php?<?= $qs ?>" class="btn" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf text-red-600"></i> Cetak PDF</a>
          <button id="btn-add" type="button" class="btn btn-dark"><i class="ti ti-plus"></i> Tambah Data</button>
        </div>
      </div>

      <div class="md:col-span-12 flex gap-2">
        <button type="submit" class="btn btn-dark"><i class="ti ti-filter"></i> Terapkan</button>
        <a href="?tab=<?= urlencode($tab) ?>" class="btn"><i class="ti ti-restore"></i> Reset</a>
      </div>
    </form>
  </div>

  <div class="bg-white p-6 shadow-md">
    <h2 class="text-xl font-bold mb-4"><?= htmlspecialchars($title) ?></h2>

    <div class="overflow-x-auto border">
      <div class="tbl-wrap">
        <table class="min-w-full text-sm table-fixed" id="tbl">
          <thead class="sticky thead-green bg-green-700">
            <tr>
              <?php if ($tab==='angkutan'): ?>
                <th class="py-3 px-4 text-left w-[6rem]">Tahun</th>
                <th class="py-3 px-4 text-left w-[14rem]">Kebun</th>
                <th class="py-3 px-4 text-left w-[10rem]">Tanggal</th>
                <th class="py-3 px-4 text-left w-[10rem]">Periode</th>
                <th class="py-3 px-4 text-left w-[12rem]">Rayon</th>
                <th class="py-3 px-4 text-left w-[12rem]">Unit Tujuan</th>
                <th class="py-3 px-4 text-left w-[12rem]">Gudang Asal</th>
                <th class="py-3 px-4 text-left w-[12rem]">Jenis Pupuk</th>
                <th class="py-3 px-4 text-right w-[10rem]">Kilogram</th>
                <th class="py-3 px-4 text-left w-[14rem]">Keterangan</th>
                <th class="py-3 px-4 text-left w-[8rem]">Aksi</th>
              <?php else: ?>
                <th class="py-3 px-4 text-left w-[6rem]">Tahun</th>
                <th class="py-3 px-4 text-left w-[14rem]">Kebun</th>
                <th class="py-3 px-4 text-left w-[10rem]">Tanggal</th>
                <th class="py-3 px-4 text-left w-[10rem]">Periode</th>
                <th class="py-3 px-4 text-left w-[12rem]">Unit/Defisi</th>
                <th class="py-3 px-4 text-left w-[8rem]">T. Tanam</th>
                <th class="py-3 px-4 text-left w-[10rem]">Blok</th>
                <th class="py-3 px-4 text-right w-[10rem]">Luas (Ha)</th>
                <th class="py-3 px-4 text-left w-[12rem]">Jenis Pupuk</th>
                <th class="py-3 px-4 text-right w-[10rem]">Dosis</th>
                <th class="py-3 px-4 text-right w-[10rem]">Kilogram</th>
                <th class="py-3 px-4 text-left w-[8rem]">Aksi</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody class="text-gray-800" id="table-body">
            <?php if (!$rows): ?>
              <tr><td colspan="<?= $tab==='angkutan' ? 11 : 12 ?>" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr class="border-b hover:bg-blue-50/30">
                <?php if ($tab==='angkutan'): ?>
                  <td class="py-3 px-4"><?= htmlspecialchars(date('Y', strtotime($r['tanggal']))) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['kebun_nama'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['tanggal']) ?></td>
                  <td class="py-3 px-4"><?php $m=(int)date('n', strtotime($r['tanggal'])); echo $bulanList[$m] ?? $m; ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['nama_rayon'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['nama_gudang'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format((float)$r['jumlah'],2) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['nama_keterangan'] ?? '-') ?></td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-2">
                      <button class="btn-icon btn-edit" title="Edit" data-tab="angkutan"
                        data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'
                        <?= $isStaf ? 'disabled' : '' ?>>
                        <i class="ti ti-edit"></i>
                      </button>
                      <button class="btn-icon btn-delete" title="Hapus" data-tab="angkutan" data-id="<?= (int)$r['id'] ?>"
                        <?= $isStaf ? 'disabled' : '' ?>>
                        <i class="ti ti-trash"></i>
                      </button>
                    </div>
                  </td>
                <?php else: ?>
                  <td class="py-3 px-4"><?= htmlspecialchars(date('Y', strtotime($r['tanggal']))) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['kebun_nama'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['tanggal']) ?></td>
                  <td class="py-3 px-4"><?php $m=(int)date('n', strtotime($r['tanggal'])); echo $bulanList[$m] ?? $m; ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['t_tanam'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['blok']) ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format((float)$r['luas'],2) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                  <td class="py-3 px-4 text-right"><?= (isset($r['dosis']) && $r['dosis']!=='') ? number_format((float)$r['dosis'],2) : '-' ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format((float)$r['jumlah'],2) ?></td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-2">
                      <button class="btn-icon btn-edit" title="Edit" data-tab="menabur"
                        data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'
                        <?= $isStaf ? 'disabled' : '' ?>>
                        <i class="ti ti-edit"></i>
                      </button>
                      <button class="btn-icon btn-delete" title="Hapus" data-tab="menabur" data-id="<?= (int)$r['id'] ?>"
                        <?= $isStaf ? 'disabled' : '' ?>>
                        <i class="ti ti-trash"></i>
                      </button>
                    </div>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Ringkas total -->
      <div class="w-full border rounded-md bg-[#F6FFF8] overflow-hidden mt-3">
        <div class="h-1 bg-green-600"></div>
        <div class="px-3 md:px-4 py-2">
          <div class="flex items-center gap-6 text-sm font-semibold text-gray-900">
            <span class="text-gray-700">TOTAL</span>
            <div class="flex-1 overflow-x-auto">
              <div class="flex items-center gap-10 min-w-max pr-6">
                <div class="flex items-center gap-2">
                  <span class="text-gray-500 font-normal">Jumlah (Kg)</span>
                  <span class="font-semibold tabular-nums"><?= number_format($tot_all_kg ?? 0, 2) ?></span>
                </div>

                <?php if ($tab !== 'angkutan'): ?>
                  <div class="flex items-center gap-2">
                    <span class="text-gray-500 font-normal">Luas (Ha)</span>
                    <span class="font-semibold tabular-nums"><?= number_format($tot_all_luas ?? 0, 2) ?></span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-gray-500 font-normal">Invt. Pokok</span>
                    <span class="font-semibold tabular-nums"><?= number_format($tot_all_invt ?? 0, 0) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between p-3">
        <div class="text-sm text-gray-700">Halaman <span class="font-semibold"><?= $page ?></span> dari <span class="font-semibold"><?= $total_pages ?></span></div>
        <?php
          function page_link($p) {
            $q = [
              'tab'           => $_GET['tab']           ?? '',
              'tahun'         => $_GET['tahun']         ?? '',
              'kebun_id'      => $_GET['kebun_id']      ?? '',
              'tanggal'       => $_GET['tanggal']       ?? '',
              'periode'       => $_GET['periode']       ?? '',
              'unit_id'       => $_GET['unit_id']       ?? '',
              'jenis_pupuk'   => $_GET['jenis_pupuk']   ?? '',
              'rayon_id'      => $_GET['rayon_id']      ?? '',
              'asal_gudang_id'=> $_GET['asal_gudang_id']?? '',
              'keterangan_id' => $_GET['keterangan_id'] ?? '',
              'per_page'      => $_GET['per_page']      ?? '',
              'page'          => $p,
            ];
            return '?'.http_build_query($q);
          }
        ?>
        <div class="inline-flex gap-2">
          <a href="<?= $page>1 ? page_link($page-1) : 'javascript:void(0)' ?>" class="px-3 py-2 rounded-lg border <?= $page>1?'hover:bg-gray-50 text-gray-800':'opacity-50 cursor-not-allowed text-gray-400' ?>">Prev</a>
          <a href="<?= $page<$total_pages ? page_link($page+1) : 'javascript:void(0)' ?>" class="px-3 py-2 rounded-lg border <?= $page<$total_pages?'hover:bg-gray-50 text-gray-800':'opacity-50 cursor-not-allowed text-gray-400' ?>">Next</a>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 shadow-xl w-full max-w-3xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Tambah Data</h3>
      <button id="btn-close" class="text-3xl text-gray-500 hover:text-gray-800" aria-label="Close">&times;</button>
    </div>

    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="tab" id="form-tab" value="<?= htmlspecialchars($tab) ?>">
      <input type="hidden" name="id" id="form-id">

      <!-- GROUP ANGKUTAN -->
      <div id="group-angkutan" class="<?= $tab==='angkutan' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Kebun</label>
            <select name="kebun_id" id="kebun_id_angkutan" class="i-select">
              <option value="">— Pilih Kebun —</option>
              <?php foreach ($kebun as $k): ?>
                <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Rayon</label>
            <select name="rayon_id" id="rayon_id" class="i-select">
              <option value="">— Pilih Rayon —</option>
              <?php foreach ($rayons as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Unit Tujuan</label>
            <select name="unit_tujuan_id" id="unit_tujuan_id" class="i-select">
              <option value="">— Pilih Unit —</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Gudang Asal</label>
            <select name="asal_gudang_id" id="asal_gudang_id" class="i-select">
              <option value="">— Pilih Gudang Asal —</option>
              <?php foreach ($gudangs as $g): ?>
                <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Tanggal</label>
            <input type="date" name="tanggal" id="tanggal_angkutan" class="i-input">
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Jenis Pupuk</label>
            <select name="jenis_pupuk" id="jenis_pupuk_angkutan" class="i-select">
              <option value="">— Pilih Jenis Pupuk —</option>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Kilogram</label>
            <input type="number" step="0.01" name="jumlah" id="jumlah_angkutan" class="i-input" min="0">
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm text-gray-600 mb-1">Keterangan</label>
            <select name="keterangan_id" id="keterangan_id" class="i-select">
              <option value="">— Pilih Keterangan (Opsional) —</option>
              <?php foreach ($keterangans as $k): ?>
                <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['keterangan']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- GROUP MENABUR -->
      <div id="group-menabur" class="<?= $tab==='menabur' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Kebun</label>
            <select name="kebun_id" id="kebun_id_menabur" class="i-select">
              <option value="">— Pilih Kebun —</option>
              <?php foreach ($kebun as $k): ?>
                <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Unit / Defisi</label>
            <select name="unit_id" id="unit_id" class="i-select">
              <option value="">— Pilih Unit —</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Blok</label>
            <select name="blok" id="blok" class="i-select">
              <option value="">— Pilih Unit dulu —</option>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Tanggal</label>
            <input type="date" name="tanggal" id="tanggal_menabur" class="i-input">
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">T. Tanam</label>
            <input type="number" step="1" name="t_tanam" id="t_tanam" class="i-input" min="1900" max="2100" placeholder="YYYY">
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Luas (Ha)</label>
            <input type="number" step="0.01" name="luas" id="luas" class="i-input" min="0">
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Jenis Pupuk</label>
            <select name="jenis_pupuk" id="jenis_pupuk_menabur" class="i-select">
              <option value="">— Pilih Jenis Pupuk —</option>
            </select>
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Dosis</label>
            <input type="number" step="0.01" name="dosis" id="dosis" class="i-input" min="0">
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Kilogram</label>
            <input type="number" step="0.01" name="jumlah" id="jumlah_menabur" class="i-input" min="0">
          </div>

          <div>
            <label class="block text-sm text-gray-600 mb-1">Invt. Pokok</label>
            <input type="number" step="1" name="invt_pokok" id="invt_pokok" class="i-input" min="0">
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="btn">Batal</button>
        <button type="submit" class="btn btn-dark">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;

  const $ = s => document.querySelector(s);
  async function fetchJSON(url){ const r = await fetch(url); return r.json(); }
  function fillSelect(el, arr, placeholder='— Pilih —'){
    el.innerHTML='';
    const d=document.createElement('option'); d.value=''; d.textContent=placeholder; el.appendChild(d);
    (arr||[]).forEach(v=>{
      if (typeof v === 'object' && v !== null && 'value' in v && 'label' in v) {
        const op=document.createElement('option'); op.value=v.value; op.textContent=v.label; el.appendChild(op);
      } else if (typeof v === 'object' && v !== null && 'id' in v && 'nama_kebun' in v) {
        const op=document.createElement('option'); op.value=v.id; op.textContent=v.nama_kebun; el.appendChild(op);
      } else {
        const op=document.createElement('option'); op.value=v; op.textContent=v; el.appendChild(op);
      }
    });
  }

  // dropdown jenis pupuk
  (async ()=>{
    try{
      const j = await fetchJSON('?ajax=options&type=pupuk');
      const list = (j.success && Array.isArray(j.data)) ? j.data : [];
      const a = $('#jenis_pupuk_angkutan'); if (a) fillSelect(a, list, '— Pilih Jenis Pupuk —');
      const m = $('#jenis_pupuk_menabur');  if (m) fillSelect(m, list, '— Pilih Jenis Pupuk —');
    }catch{}
  })();

  // blok by unit
  async function refreshBlok(){
    const uid = $('#unit_id')?.value || '';
    const sel = $('#blok');
    if (!uid){ if(sel) sel.innerHTML='<option value="">— Pilih Unit dulu —</option>'; return; }
    try{
      const j = await fetchJSON(`?ajax=options&type=blok&unit_id=${encodeURIComponent(uid)}`);
      const list = (j.success && Array.isArray(j.data)) ? j.data : [];
      if (sel) fillSelect(sel, list, '— Pilih Blok —');
    }catch{
      if (sel) sel.innerHTML='<option value="">— Pilih Blok —</option>';
    }
  }
  $('#unit_id')?.addEventListener('change', refreshBlok);

  const modal = $('#crud-modal'), form = $('#crud-form'), title = $('#modal-title');
  const open  = ()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); };
  const close = ()=>{ modal.classList.add('hidden');   modal.classList.remove('flex'); };

  // Tambah (staf boleh)
  $('#btn-add')?.addEventListener('click', ()=>{
    form.reset(); $('#form-action').value='store'; $('#form-id').value='';
    title.textContent='Tambah Data';
    const tab = $('#form-tab').value = '<?= htmlspecialchars($tab) ?>';
    document.getElementById('group-angkutan').classList.toggle('hidden', tab!=='angkutan');
    document.getElementById('group-menabur').classList.toggle('hidden',   tab!=='menabur');
    if (tab==='menabur') refreshBlok();
    open();
  });
  $('#btn-close')?.addEventListener('click', close);
  $('#btn-cancel')?.addEventListener('click', close);

  // Edit / Delete — DIBLOK untuk staf
  document.body.addEventListener('click', async (e)=>{
    const btnE = e.target.closest('.btn-edit');
    const btnD = e.target.closest('.btn-delete');

    if (btnE){
      if (IS_STAF) { return; }
      const row = JSON.parse(btnE.dataset.json); const tab = btnE.dataset.tab;
      form.reset(); $('#form-action').value='update'; $('#form-id').value=row.id; $('#form-tab').value = tab;
      title.textContent='Edit Data';
      document.getElementById('group-angkutan').classList.toggle('hidden', tab!=='angkutan');
      document.getElementById('group-menabur').classList.toggle('hidden',   tab!=='menabur');

      if (tab==='angkutan'){
        $('#kebun_id_angkutan').value    = row.kebun_id ?? '';
        $('#rayon_id').value             = row.rayon_id ?? '';
        $('#unit_tujuan_id').value       = row.unit_tujuan_id ?? '';
        $('#asal_gudang_id').value       = row.asal_gudang_id ?? '';
        $('#tanggal_angkutan').value     = row.tanggal ?? '';
        $('#jenis_pupuk_angkutan').value = row.jenis_pupuk ?? '';
        $('#jumlah_angkutan').value      = row.jumlah ?? '';
        $('#keterangan_id').value        = row.keterangan_id ?? '';
      } else {
        $('#kebun_id_menabur').value     = row.kebun_id ?? '';
        $('#unit_id').value              = row.unit_id ?? '';
        await refreshBlok();
        $('#blok').value                 = row.blok ?? '';
        $('#tanggal_menabur').value      = row.tanggal ?? '';
        $('#t_tanam').value              = row.t_tanam ?? '';
        $('#luas').value                 = row.luas ?? '';
        $('#jenis_pupuk_menabur').value  = row.jenis_pupuk ?? '';
        $('#dosis').value                = (row.dosis ?? '') === null ? '' : row.dosis;
        $('#jumlah_menabur').value       = row.jumlah ?? '';
        $('#invt_pokok').value           = row.invt_pokok ?? '';
      }
      open();
    }

    if (btnD){
      if (IS_STAF) { return; }
      const id = btnD.dataset.id; const tab = btnD.dataset.tab;
      Swal.fire({title:'Hapus data ini?', icon:'warning', showCancelButton:true})
        .then(res=>{
          if (!res.isConfirmed) return;
          const fd = new FormData();
          fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete'); fd.append('tab',tab); fd.append('id',id);
          fetch('pemupukan_organik_crud.php',{method:'POST', body:fd})
            .then(r=>r.json()).then(j=>{
              if (j.success) Swal.fire('Terhapus!','', 'success').then(()=>location.reload());
              else Swal.fire('Gagal', j.message||'Error', 'error');
            }).catch(err=> Swal.fire('Error', String(err),'error'));
        });
    }
  });

  // Submit (staf boleh create)
  form.addEventListener('submit',(e)=>{
    e.preventDefault();
    const tab = $('#form-tab').value;
    const fd  = new FormData(form);
    fd.set('action', $('#form-action').value);
    fd.set('tab', tab);

    if (tab==='angkutan'){
      // Validasi BARU
      const need = {
        'kebun_id_angkutan': 'Kebun',
        'rayon_id': 'Rayon',
        'unit_tujuan_id': 'Unit Tujuan',
        'asal_gudang_id': 'Gudang Asal',
        'tanggal_angkutan': 'Tanggal',
        'jenis_pupuk_angkutan': 'Jenis Pupuk'
      };
      for (const id in need){
        const el=document.getElementById(id);
        if(!el || !el.value){ Swal.fire('Validasi',`${need[id]} wajib diisi.`,'warning'); return; }
      }
      // Set FormData BARU
      fd.set('kebun_id', $('#kebun_id_angkutan').value);
      fd.set('rayon_id', $('#rayon_id').value);
      fd.set('unit_tujuan_id', $('#unit_tujuan_id').value);
      fd.set('asal_gudang_id', $('#asal_gudang_id').value);
      fd.set('tanggal', $('#tanggal_angkutan').value);
      fd.set('jenis_pupuk', $('#jenis_pupuk_angkutan').value);
      fd.set('jumlah', $('#jumlah_angkutan').value || '');
      fd.set('keterangan_id', $('#keterangan_id').value || ''); // kirim ID
      // Catatan: Field supir dihapus sesuai MODIFIKASI
    } else {
      const need = ['kebun_id_menabur','unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'];
      for (const id of need){
        const el=document.getElementById(id);
        if(!el || !el.value){ Swal.fire('Validasi',`${id.replaceAll('_',' ')} wajib diisi.`,'warning'); return; }
      }
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
        if (j.success){
          close();
          Swal.fire({icon:'success', title:'Berhasil', timer:1200, showConfirmButton:false})
            .then(()=>location.reload());
        } else {
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : (j.message||'Error');
          Swal.fire('Gagal', html, 'error');
        }
      }).catch(err=> Swal.fire('Error', String(err),'error'));
  });
});
</script>
