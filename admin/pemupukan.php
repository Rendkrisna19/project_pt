<?php
// pemupukan.php — +Filter Tanggal/Bulan/Jenis Pupuk/Kebun, Pagination, Sticky Header, +RINGKASAN (kg/luas/invt/avg dosis) & (angkutan: kg)
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

function qstr($v){ return trim((string)$v); }
function qintOrEmpty($v){ return ($v===''||$v===null) ? '' : (int)$v; }

try {
  $db   = new Database();
  $conn = $db->getConnection();

  /* ===== helper cek kolom (cache) ===== */
  $cacheCols = [];
  $columnExists = function(PDO $c, $table, $col) use (&$cacheCols){
    if (!isset($cacheCols[$table])) {
      $st = $c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t'=>$table]);
      $cacheCols[$table] = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    }
    return in_array($col, $cacheCols[$table] ?? [], true);
  };

  /* ===== AJAX OPTIONS ===== */
  if (($_GET['ajax'] ?? '') === 'options') {
    header('Content-Type: application/json');
    $type = qstr($_GET['type'] ?? '');
    $unit_id = (isset($_GET['unit_id']) && $_GET['unit_id']!=='') ? (int)$_GET['unit_id'] : null;

    if ($type === 'blok') {
      $data = [];
      if ($unit_id) {
        try {
          $st = $conn->prepare("SELECT kode AS blok FROM md_blok WHERE unit_id=:u AND kode<>'' ORDER BY kode");
          $st->execute([':u'=>$unit_id]);
          $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'blok');
        } catch(Throwable $e){}
        if (!$data) {
          $st = $conn->prepare("SELECT DISTINCT blok FROM menabur_pupuk WHERE unit_id=:u AND blok<>'' ORDER BY blok");
          $st->execute([':u'=>$unit_id]);
          $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'blok');
        }
      }
      echo json_encode(['success'=>true,'data'=>$data]); exit;
    }

    if ($type === 'jenis') {
      $data = [];
      try {
        $st = $conn->query("SELECT nama FROM md_pupuk WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama");
        $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'nama');
      } catch(Throwable $e) {
        $st = $conn->query("SELECT DISTINCT nama FROM (
                              SELECT jenis_pupuk AS nama FROM menabur_pupuk
                              UNION ALL
                              SELECT jenis_pupuk AS nama FROM angkutan_pupuk
                            ) x
                            WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama");
        $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'nama');
      }
      echo json_encode(['success'=>true,'data'=>$data]); exit;
    }

    echo json_encode(['success'=>false,'message'=>'Tipe options tidak dikenali']); exit;
  }

  /* ===== TAB ===== */
  $tab = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

  /* ===== FILTERS ===== */
  $f_unit_id    = qintOrEmpty($_GET['unit_id'] ?? '');
  $f_kebun_id   = qintOrEmpty($_GET['kebun_id'] ?? '');      // filter kebun pakai id
  $f_tanggal    = qstr($_GET['tanggal'] ?? '');              // yyyy-mm-dd (exact)
  $f_bulan      = qstr($_GET['bulan'] ?? '');                // 1..12 (from tanggal)
  $f_jenis      = qstr($_GET['jenis_pupuk'] ?? '');          // exact

  /* ===== Master ===== */
  $units   = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
  $kebuns  = $conn->query("SELECT id, kode, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
  // jenis pupuk untuk filter (server side)
  try {
    $pupuks = $conn->query("SELECT nama FROM md_pupuk WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
  } catch (Throwable $e) {
    $pupuks = $conn->query("SELECT DISTINCT jenis_pupuk AS nama FROM (
                              SELECT jenis_pupuk FROM menabur_pupuk UNION ALL
                              SELECT jenis_pupuk FROM angkutan_pupuk
                            ) t WHERE jenis_pupuk<>'' ORDER BY jenis_pupuk")->fetchAll(PDO::FETCH_COLUMN);
  }

  /* ===== Deteksi kolom kebun di tabel transaksi ===== */
  $hasKebunMenaburId  = $columnExists($conn,'menabur_pupuk','kebun_id');
  $hasKebunMenaburKod = $columnExists($conn,'menabur_pupuk','kebun_kode');
  $hasKebunAngkutId   = $columnExists($conn,'angkutan_pupuk','kebun_id');
  $hasKebunAngkutKod  = $columnExists($conn,'angkutan_pupuk','kebun_kode');

  // mapping id->kode utk filter jika tabel pakai kebun_kode
  $kebunIdToKode = [];
  foreach ($kebuns as $kb) { $kebunIdToKode[(int)$kb['id']] = $kb['kode']; }

  /* ===== Pagination ===== */
  $page     = max(1, (int)($_GET['page'] ?? 1));
  $perOpts  = [10,25,50,100];
  $per_page = (int)($_GET['per_page'] ?? 10);
  if (!in_array($per_page, $perOpts, true)) $per_page = 10;
  $offset   = ($page - 1) * $per_page;

  $bulanList = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
  ];

  /* ===== Query + COUNT + TOTALS ===== */
  if ($tab === 'angkutan') {
    $title = "Data Angkutan Pupuk Kimia";

    $selectKebun = '';
    $joinKebun   = '';
    if     ($hasKebunAngkutId)  { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.id = a.kebun_id "; }
    elseif ($hasKebunAngkutKod) { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.kode = a.kebun_kode "; }

    $where = " WHERE 1=1";
    $p = [];
    if ($f_unit_id !== '') { $where .= " AND a.unit_tujuan_id = :uid"; $p[':uid'] = (int)$f_unit_id; }
    if ($f_kebun_id !== '') {
      if ($hasKebunAngkutId)       { $where .= " AND a.kebun_id = :kid";      $p[':kid'] = (int)$f_kebun_id; }
      elseif ($hasKebunAngkutKod)  { $where .= " AND a.kebun_kode = :kkod";   $p[':kkod'] = (string)($kebunIdToKode[(int)$f_kebun_id] ?? ''); }
    }
    if ($f_tanggal !== '') { $where .= " AND a.tanggal = :tgl"; $p[':tgl'] = $f_tanggal; }
    if ($f_bulan !== '' && ctype_digit($f_bulan)) { $where .= " AND MONTH(a.tanggal) = :bln"; $p[':bln'] = (int)$f_bulan; }
    if ($f_jenis !== '') { $where .= " AND a.jenis_pupuk = :jp"; $p[':jp'] = $f_jenis; }

    // COUNT
    $sql_count = "SELECT COUNT(*) FROM angkutan_pupuk a $joinKebun $where";
    $stc = $conn->prepare($sql_count);
    foreach ($p as $k=>$v) $stc->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stc->execute();
    $total_rows = (int)$stc->fetchColumn();
    $total_pages = max(1, (int)ceil($total_rows / $per_page));

    // DATA (paged)
    $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama $selectKebun
            FROM angkutan_pupuk a
            LEFT JOIN units u ON u.id = a.unit_tujuan_id
            $joinKebun
            $where
            ORDER BY a.tanggal DESC, a.id DESC
            LIMIT :limit OFFSET :offset";
    $st = $conn->prepare($sql);
    foreach ($p as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue(':limit',$per_page,PDO::PARAM_INT);
    $st->bindValue(':offset',$offset,PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // TOTALS (ALL filtered)
    $sql_tot = "SELECT COALESCE(SUM(a.jumlah),0) AS tot_kg
                FROM angkutan_pupuk a $joinKebun $where";
    $stt = $conn->prepare($sql_tot);
    foreach ($p as $k=>$v) $stt->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stt->execute();
    $tot_all = $stt->fetch(PDO::FETCH_ASSOC);
    $tot_all_kg = (float)($tot_all['tot_kg'] ?? 0);

    // TOTALS (PAGE only)
    $sum_page_kg = 0.0;
    foreach ($rows as $r) $sum_page_kg += (float)($r['jumlah'] ?? 0);

  } else {
    $title = "Data Penaburan Pupuk Kimia";

    $selectKebun = '';
    $joinKebun   = '';
    if     ($hasKebunMenaburId)  { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.id = m.kebun_id "; }
    elseif ($hasKebunMenaburKod) { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.kode = m.kebun_kode "; }

    $where = " WHERE 1=1";
    $p = [];
    if ($f_unit_id !== '') { $where .= " AND m.unit_id = :uid"; $p[':uid'] = (int)$f_unit_id; }
    if ($f_kebun_id !== '') {
      if ($hasKebunMenaburId)      { $where .= " AND m.kebun_id = :kid";     $p[':kid'] = (int)$f_kebun_id; }
      elseif ($hasKebunMenaburKod) { $where .= " AND m.kebun_kode = :kkod";  $p[':kkod'] = (string)($kebunIdToKode[(int)$f_kebun_id] ?? ''); }
    }
    if ($f_tanggal !== '') { $where .= " AND m.tanggal = :tgl"; $p[':tgl'] = $f_tanggal; }
    if ($f_bulan !== '' && ctype_digit($f_bulan)) { $where .= " AND MONTH(m.tanggal) = :bln"; $p[':bln'] = (int)$f_bulan; }
    if ($f_jenis !== '') { $where .= " AND m.jenis_pupuk = :jp"; $p[':jp'] = $f_jenis; }

    // COUNT
    $sql_count = "SELECT COUNT(*) FROM menabur_pupuk m $joinKebun $where";
    $stc = $conn->prepare($sql_count);
    foreach ($p as $k=>$v) $stc->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stc->execute();
    $total_rows = (int)$stc->fetchColumn();
    $total_pages = max(1, (int)ceil($total_rows / $per_page));

    // DATA (paged)
    $sql = "SELECT m.*, u.nama_unit AS unit_nama $selectKebun
            FROM menabur_pupuk m
            LEFT JOIN units u ON u.id = m.unit_id
            $joinKebun
            $where
            ORDER BY m.tanggal DESC, m.id DESC
            LIMIT :limit OFFSET :offset";
    $st = $conn->prepare($sql);
    foreach ($p as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue(':limit',$per_page,PDO::PARAM_INT);
    $st->bindValue(':offset',$offset,PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // TOTALS (ALL filtered)
    $sql_tot = "SELECT
                  COALESCE(SUM(m.jumlah),0)     AS tot_kg,
                  COALESCE(SUM(m.luas),0)       AS tot_luas,
                  COALESCE(SUM(m.invt_pokok),0) AS tot_invt,
                  AVG(NULLIF(m.dosis,0))        AS avg_dosis
                FROM menabur_pupuk m $joinKebun $where";
    $stt = $conn->prepare($sql_tot);
    foreach ($p as $k=>$v) $stt->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stt->execute();
    $tot_all = $stt->fetch(PDO::FETCH_ASSOC);
    $tot_all_kg   = (float)($tot_all['tot_kg'] ?? 0);
    $tot_all_luas = (float)($tot_all['tot_luas'] ?? 0);
    $tot_all_invt = (float)($tot_all['tot_invt'] ?? 0);
    $tot_all_avgd = (float)($tot_all['avg_dosis'] ?? 0);

    // TOTALS (PAGE only)
    $sum_page_kg = 0.0; $sum_page_luas = 0.0; $sum_page_invt = 0.0; $sum_page_avgd = 0.0; $cnt_dosis = 0;
    foreach ($rows as $r) {
      $sum_page_kg   += (float)($r['jumlah'] ?? 0);
      $sum_page_luas += (float)($r['luas'] ?? 0);
      $sum_page_invt += (float)($r['invt_pokok'] ?? 0);
      if (isset($r['dosis']) && $r['dosis'] !== null && $r['dosis'] !== '') { $sum_page_avgd += (float)$r['dosis']; $cnt_dosis++; }
    }
    $avg_page_dosis = $cnt_dosis>0 ? $sum_page_avgd/$cnt_dosis : 0.0;
  }

} catch (PDOException $e) {
  die("DB Error: " . $e->getMessage());
}

/* ===== Helper: QS tanpa pagination (untuk export) ===== */
function qs_no_page(array $extra = []) {
  $base = [
    'tab'         => $_GET['tab']         ?? '',
    'unit_id'     => $_GET['unit_id']     ?? '',
    'kebun_id'    => $_GET['kebun_id']    ?? '',
    'tanggal'     => $_GET['tanggal']     ?? '',
    'bulan'       => $_GET['bulan']       ?? '',
    'jenis_pupuk' => $_GET['jenis_pupuk'] ?? '',
  ];
  return http_build_query(array_merge($base, $extra));
}

$currentPage = 'pemupukan';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* UI abu-abu & tabel sticky header + scroll body */
  .i-input,.i-select{border:1px solid #e5e7eb;border-radius:.6rem;padding:.5rem .75rem;width:100%;outline:none}
  .i-input:focus,.i-select:focus{border-color:#9ca3af;box-shadow:0 0 0 3px rgba(156,163,175,.15)}
  .btn{display:inline-flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;background:#fff;color:#1f2937;border-radius:.6rem;padding:.5rem 1rem}
  .btn:hover{background:#f9fafb}
  .btn-dark{background:#111827;color:#fff;border-color:#111827}
  .btn-dark:hover{background:#0f172a}
  th{font-size:.75rem;font-weight:600;color:#6b7280;letter-spacing:.06em;text-transform:uppercase}
  .tbl-wrap{max-height:60vh;overflow-y:auto}     /* hanya body tabel yg scroll */
  thead.sticky{position:sticky;top:0;z-index:10;background:#f9fafb}
  table.table-fixed{table-layout:fixed}
</style>

<div class="space-y-6">
  <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">🔬 Pemupukan Kimia</h1>

  <div class="border-b border-gray-200 flex flex-wrap gap-2 md:gap-6">
    <?php
      $persist = ['unit_id'=>$f_unit_id,'kebun_id'=>$f_kebun_id,'tanggal'=>$f_tanggal,'bulan'=>$f_bulan,'jenis_pupuk'=>$f_jenis,'per_page'=>$per_page];
      $qsMen = http_build_query(array_merge(['tab'=>'menabur'], $persist));
      $qsAng = http_build_query(array_merge(['tab'=>'angkutan'], $persist));
    ?>
    <a href="?<?= $qsMen ?>" class="px-3 py-2 border-b-2 text-sm font-medium <?= $tab==='menabur' ? 'border-gray-800 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Menabur Pupuk</a>
    <a href="?<?= $qsAng ?>" class="px-3 py-2 border-b-2 text-sm font-medium <?= $tab==='angkutan' ? 'border-gray-800 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Angkutan Pupuk</a>
  </div>

  <div class="bg-white p-6 rounded-xl shadow-md">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
      <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($title) ?></h2>
      <div class="flex gap-2">
        <?php $qsExport = qs_no_page(); ?>
        <a href="cetak/pemupukan_excel.php?<?= $qsExport ?>" class="btn"><i class="ti ti-file-spreadsheet text-emerald-600 text-xl"></i><span>Export Excel</span></a>
        <a href="cetak/pemupukan_pdf.php?<?= $qsExport ?>" target="_blank" rel="noopener" class="btn"><i class="ti ti-file-type-pdf text-red-600 text-xl"></i><span>Cetak PDF</span></a>
        <button id="btn-add" class="btn btn-dark"><i class="ti ti-plus"></i><span>Tambah Data</span></button>
      </div>
    </div>

    <!-- FILTERS -->
    <form class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4" method="GET" id="filter-form">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

      <div class="md:col-span-3">
        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit</label>
        <select name="unit_id" class="i-select">
          <option value="">— Semua Unit —</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($f_unit_id!=='' && (int)$f_unit_id===(int)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-3">
        <label class="block text-xs font-semibold text-gray-700 mb-1">Kebun</label>
        <select name="kebun_id" class="i-select">
          <option value="">— Semua Kebun —</option>
          <?php foreach ($kebuns as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= ($f_kebun_id!=='' && (int)$f_kebun_id===(int)$k['id'])?'selected':'' ?>>
              <?= htmlspecialchars($k['nama_kebun']) ?> (<?= htmlspecialchars($k['kode']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal</label>
        <input type="date" name="tanggal" value="<?= htmlspecialchars($f_tanggal) ?>" class="i-input">
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-700 mb-1">Bulan (dari Tanggal)</label>
        <select name="bulan" class="i-select">
          <option value="">— Semua Bulan —</option>
          <?php foreach ($bulanList as $num=>$name): ?>
            <option value="<?= $num ?>" <?= ($f_bulan!=='' && (int)$f_bulan===$num)?'selected':'' ?>><?= $name ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-700 mb-1">Jenis Pupuk</label>
        <select name="jenis_pupuk" class="i-select">
          <option value="">— Semua Jenis —</option>
          <?php foreach ($pupuks as $jp): ?>
            <option value="<?= htmlspecialchars($jp) ?>" <?= ($f_jenis===$jp)?'selected':'' ?>><?= htmlspecialchars($jp) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-12 flex items-end justify-between gap-3">
        <div class="flex items-center gap-3">
          <?php $from = $total_rows ? ($offset + 1) : 0; $to = min($offset + $per_page, $total_rows); ?>
          <span class="text-sm text-gray-700">
            Menampilkan <strong><?= $from ?></strong>–<strong><?= $to ?></strong> dari <strong><?= number_format($total_rows) ?></strong> data
          </span>
          <div class="flex items-center gap-2">
            <input type="hidden" name="page" value="1">
            <label class="text-sm text-gray-700">Baris/hal</label>
            <select name="per_page" class="i-select" style="width:auto" onchange="this.form.submit()">
              <?php foreach ($perOpts as $opt): ?>
                <option value="<?= $opt ?>" <?= $per_page===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-dark" type="submit"><i class="ti ti-filter"></i> Terapkan</button>
          <a href="?tab=<?= urlencode($tab) ?>" class="btn"><i class="ti ti-restore"></i> Reset</a>
        </div>
      </div>
    </form>

    <!-- TABEL (sticky header + body scroll) -->
    <div class="overflow-x-auto border rounded-xl">
      <div class="tbl-wrap">
        <table class="min-w-full text-sm table-fixed">
          <thead class="bg-gray-50 sticky">
            <tr class="text-gray-700">
              <?php if ($tab==='angkutan'): ?>
                <th class="py-3 px-4 text-left w-[14rem]">Kebun</th>
                <th class="py-3 px-4 text-left w-[12rem]">Gudang Asal</th>
                <th class="py-3 px-4 text-left w-[12rem]">Unit Tujuan</th>
                <th class="py-3 px-4 text-left w-[10rem]">Tanggal</th>
                <th class="py-3 px-4 text-left w-[12rem]">Jenis Pupuk</th>
                <th class="py-3 px-4 text-right w-[10rem]">Jumlah (Kg)</th>
                <th class="py-3 px-4 text-left w-[12rem]">Nomor DO</th>
                <th class="py-3 px-4 text-left w-[12rem]">Supir</th>
                <th class="py-3 px-4 text-left w-[10rem]">Aksi</th>
              <?php else: ?>
                <th class="py-3 px-4 text-left w-[14rem]">Kebun</th>
                <th class="py-3 px-4 text-left w-[12rem]">Unit</th>
                <th class="py-3 px-4 text-left w-[10rem]">Blok</th>
                <th class="py-3 px-4 text-left w-[10rem]">Tanggal</th>
                <th class="py-3 px-4 text-left w-[12rem]">Jenis Pupuk</th>
                <th class="py-3 px-4 text-right w-[10rem]">Dosis (kg/ha)</th>
                <th class="py-3 px-4 text-right w-[10rem]">Jumlah (Kg)</th>
                <th class="py-3 px-4 text-right w-[10rem]">Luas (Ha)</th>
                <th class="py-3 px-4 text-right w-[10rem]">Invt. Pokok</th>
                <th class="py-3 px-4 text-left  w-[14rem]">Catatan</th>
                <th class="py-3 px-4 text-left  w-[10rem]">Aksi</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody class="text-gray-900">
            <?php if (empty($rows)): ?>
              <tr><td colspan="<?= $tab==='angkutan' ? 9 : 11 ?>" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr class="border-b hover:bg-gray-50">
                <?php if ($tab==='angkutan'): ?>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['gudang_asal']) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['tanggal']) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format((float)$r['jumlah'],2) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['nomor_do']) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['supir']) ?></td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <button class="btn-edit text-blue-700 hover:text-blue-900 underline" data-tab="angkutan" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'>Edit</button>
                      <button class="btn-delete text-red-700 hover:text-red-900 underline" data-tab="angkutan" data-id="<?= (int)$r['id'] ?>">Hapus</button>
                    </div>
                  </td>
                <?php else: ?>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['blok']) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['tanggal']) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                  <td class="py-3 px-4 text-right"><?= isset($r['dosis']) ? number_format((float)$r['dosis'],2) : '-' ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format((float)$r['jumlah'],2) ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format((float)$r['luas'],2) ?></td>
                  <td class="py-3 px-4 text-right"><?= (int)($r['invt_pokok'] ?? 0) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($r['catatan']) ?></td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <button class="btn-edit text-blue-700 hover:text-blue-900 underline" data-tab="menabur" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'>Edit</button>
                      <button class="btn-delete text-red-700 hover:text-red-900 underline" data-tab="menabur" data-id="<?= (int)$r['id'] ?>">Hapus</button>
                    </div>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- RINGKASAN (di bawah tabel) -->
      <div class="grid <?= $tab==='angkutan' ? 'grid-cols-1' : 'md:grid-cols-2' ?> gap-3 p-3">
        <?php if ($tab==='angkutan'): ?>
          <div class="rounded-xl border bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Halaman Ini</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Jumlah (Kg)</div>
                <div class="text-lg font-bold"><?= number_format($sum_page_kg ?? 0, 2) ?></div>
              </div>
            </div>
          </div>
          <div class="rounded-xl border bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Semua (sesuai filter)</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Jumlah (Kg)</div>
                <div class="text-lg font-bold"><?= number_format($tot_all_kg ?? 0, 2) ?></div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="rounded-xl border bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Halaman Ini</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Jumlah (Kg)</div>
                <div class="text-lg font-bold"><?= number_format($sum_page_kg ?? 0, 2) ?></div>
              </div>
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Luas (Ha)</div>
                <div class="text-lg font-bold"><?= number_format($sum_page_luas ?? 0, 2) ?></div>
              </div>
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Invt. Pokok</div>
                <div class="text-lg font-bold"><?= number_format($sum_page_invt ?? 0, 0) ?></div>
              </div>
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Rata-rata Dosis</div>
                <div class="text-lg font-bold"><?= number_format($avg_page_dosis ?? 0, 2) ?></div>
              </div>
            </div>
          </div>
          <div class="rounded-xl border bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Semua (sesuai filter)</div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Jumlah (Kg)</div>
                <div class="text-lg font-bold"><?= number_format($tot_all_kg ?? 0, 2) ?></div>
              </div>
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Luas (Ha)</div>
                <div class="text-lg font-bold"><?= number_format($tot_all_luas ?? 0, 2) ?></div>
              </div>
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Invt. Pokok</div>
                <div class="text-lg font-bold"><?= number_format($tot_all_invt ?? 0, 0) ?></div>
              </div>
              <div class="p-3 rounded-lg bg-white border text-center">
                <div class="text-xs text-gray-500">Rata-rata Dosis</div>
                <div class="text-lg font-bold"><?= number_format($tot_all_avgd ?? 0, 2) ?></div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Pagination controls -->
      <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between p-3">
        <div class="text-sm text-gray-700">Halaman <span class="font-semibold"><?= $page ?></span> dari <span class="font-semibold"><?= $total_pages ?></span></div>
        <?php
          // builder link halaman mempertahankan semua filter
          function page_link($p) {
            $q = [
              'tab'         => $_GET['tab']         ?? '',
              'unit_id'     => $_GET['unit_id']     ?? '',
              'kebun_id'    => $_GET['kebun_id']    ?? '',
              'tanggal'     => $_GET['tanggal']     ?? '',
              'bulan'       => $_GET['bulan']       ?? '',
              'jenis_pupuk' => $_GET['jenis_pupuk'] ?? '',
              'per_page'    => $_GET['per_page']    ?? '',
              'page'        => $p,
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

<!-- MODAL CRUD (fungsi tetap) -->
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-3xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-900">Tambah Data</h3>
      <button id="btn-close" class="text-3xl text-gray-500 hover:text-gray-800" aria-label="Close">&times;</button>
    </div>

    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="tab" id="form-tab" value="<?= htmlspecialchars($tab) ?>">
      <input type="hidden" name="id" id="form-id">

      <!-- ANGKUTAN -->
      <div id="group-angkutan" class="<?= $tab==='angkutan' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold mb-1">Kebun</label>
            <select name="kebun_id" id="kebun_id_angkutan" class="i-select">
              <option value="">-- Pilih Kebun --</option>
              <?php foreach ($kebuns as $k): ?>
                <option value="<?= (int)$k['id'] ?>" data-kode="<?= htmlspecialchars($k['kode']) ?>">
                  <?= htmlspecialchars($k['nama_kebun']) ?> (<?= htmlspecialchars($k['kode']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <!-- hidden utk tabel angkutan_pupuk yang pakai kebun_kode -->
            <input type="hidden" name="kebun_kode" id="kebun_kode_angkutan">
          </div>
          <div><label class="block text-sm font-semibold mb-1">Gudang Asal</label><input type="text" name="gudang_asal" id="gudang_asal" class="i-input"></div>
          <div>
            <label class="block text-sm font-semibold mb-1">Unit Tujuan</label>
            <select name="unit_tujuan_id" id="unit_tujuan_id" class="i-select">
              <option value="">-- Pilih Unit --</option>
              <?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label class="block text-sm font-semibold mb-1">Tanggal</label><input type="date" name="tanggal" id="tanggal_angkutan" class="i-input"></div>
          <div>
            <label class="block text-sm font-semibold mb-1">Jenis Pupuk</label>
            <select name="jenis_pupuk" id="jenis_pupuk_angkutan" class="i-select"><option value="">-- Pilih Jenis --</option></select>
          </div>
          <div><label class="block text-sm font-semibold mb-1">Jumlah (Kg)</label><input type="number" name="jumlah" step="0.01" id="jumlah_angkutan" class="i-input" min="0"></div>
          <div><label class="block text-sm font-semibold mb-1">Nomor DO</label><input type="text" name="nomor_do" id="nomor_do" class="i-input"></div>
          <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Supir</label><input type="text" name="supir" id="supir" class="i-input"></div>
        </div>
      </div>

      <!-- MENABUR -->
      <div id="group-menabur" class="<?= $tab==='menabur' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold mb-1">Kebun</label>
            <select name="kebun_id" id="kebun_id_menabur" class="i-select">
              <option value="">-- Pilih Kebun --</option>
              <?php foreach ($kebuns as $k): ?>
                <option value="<?= (int)$k['id'] ?>" data-kode="<?= htmlspecialchars($k['kode']) ?>">
                  <?= htmlspecialchars($k['nama_kebun']) ?> (<?= htmlspecialchars($k['kode']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-1">Unit</label>
            <select name="unit_id" id="unit_id" class="i-select">
              <option value="">-- Pilih Unit --</option>
              <?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-1">Blok</label>
            <select name="blok" id="blok" class="i-select" disabled><option value="">— pilih Unit dulu —</option></select>
          </div>
          <div><label class="block text-sm font-semibold mb-1">Tanggal</label><input type="date" name="tanggal" id="tanggal_menabur" class="i-input"></div>
          <div>
            <label class="block text-sm font-semibold mb-1">Jenis Pupuk</label>
            <select name="jenis_pupuk" id="jenis_pupuk_menabur" class="i-select"><option value="">-- Pilih Jenis --</option></select>
          </div>
          <div><label class="block textsm font-semibold mb-1">Dosis (kg/ha)</label><input type="number" name="dosis" step="0.01" id="dosis" class="i-input" min="0"></div>
          <div><label class="block text-sm font-semibold mb-1">Jumlah (Kg)</label><input type="number" name="jumlah" step="0.01" id="jumlah_menabur" class="i-input" min="0"></div>
          <div><label class="block text-sm font-semibold mb-1">Luas (Ha)</label><input type="number" name="luas" step="0.01" id="luas" class="i-input" min="0"></div>
          <div><label class="block text-sm font-semibold mb-1">Invt. Pokok</label><input type="number" name="invt_pokok" id="invt_pokok" class="i-input" min="0" step="1"></div>
          <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Catatan</label><textarea name="catatan" id="catatan" class="i-input" rows="2"></textarea></div>
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
  const $  = s => document.querySelector(s);

  async function loadJSON(url){ try{ const r=await fetch(url); return await r.json(); }catch{return null} }
  function fillSelect(el, list, placeholder='— Pilih —'){
    if (!el) return;
    el.innerHTML='';
    const d=document.createElement('option'); d.value=''; d.textContent=placeholder; el.appendChild(d);
    (list||[]).forEach(v=>{ const op=document.createElement('option'); op.value=v; op.textContent=v; el.appendChild(op); });
  }

  // muat jenis pupuk ke form modal
  (async ()=>{
    const j = await loadJSON('?ajax=options&type=jenis');
    const arr = (j && j.success && Array.isArray(j.data)) ? j.data : [];
    fillSelect($('#jenis_pupuk_angkutan'), arr, '-- Pilih Jenis --');
    fillSelect($('#jenis_pupuk_menabur'), arr, '-- Pilih Jenis --');
  })();

  // Menabur: blok by unit (AJAX)
  async function refreshFormBlokByUnit(){
    const uid = $('#unit_id').value || '';
    const sel = $('#blok');
    sel.disabled = !uid;
    if (!uid){ sel.innerHTML='<option value="">— pilih Unit dulu —</option>'; return; }
    const j = await loadJSON(`?ajax=options&type=blok&unit_id=${encodeURIComponent(uid)}`);
    const list = (j && j.success && Array.isArray(j.data)) ? j.data : [];
    fillSelect(sel, list, '-- Pilih Blok --');
  }
  $('#unit_id')?.addEventListener('change', refreshFormBlokByUnit);

  // ====== Tambahan: sinkron kebun_kode untuk TAB Angkutan ======
  function syncKebunKode(selectId, hiddenId){
    const sel = document.getElementById(selectId);
    const hid = document.getElementById(hiddenId);
    if (!sel || !hid) return;
    const kode = sel.options[sel.selectedIndex]?.dataset?.kode || '';
    hid.value = kode;
  }
  // saat ganti kebun di angkutan -> isi hidden kebun_kode
  document.getElementById('kebun_id_angkutan')?.addEventListener('change', ()=>{
    syncKebunKode('kebun_id_angkutan','kebun_kode_angkutan');
  });
  // =============================================================

  // Modal helpers
  const modal = $('#crud-modal'), form=$('#crud-form'), title=$('#modal-title');
  const open  = ()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); };
  const close = ()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); };

  function toggleGroup(tab){
    const ga=$('#group-angkutan'), gm=$('#group-menabur');
    ga.classList.toggle('hidden', tab!=='angkutan');
    gm.classList.toggle('hidden', tab!=='menabur');
    // set required dinamis (minimal)
    ['gudang_asal','unit_tujuan_id','tanggal_angkutan','jenis_pupuk_angkutan'].forEach(id=> $('#'+id)?.removeAttribute('required'));
    ['unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'].forEach(id=> $('#'+id)?.removeAttribute('required'));
    if (tab==='angkutan') ['gudang_asal','unit_tujuan_id','tanggal_angkutan','jenis_pupuk_angkutan'].forEach(id=> $('#'+id)?.setAttribute('required','required'));
    else ['unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'].forEach(id=> $('#'+id)?.setAttribute('required','required'));
  }

  // Tambah
  $('#btn-add')?.addEventListener('click', ()=>{
    form.reset(); $('#form-action').value='store'; $('#form-id').value=''; title.textContent='Tambah Data';
    toggleGroup($('#form-tab').value);
    if ($('#form-tab').value==='menabur') refreshFormBlokByUnit();
    // jaga-jaga kosongkan hidden kebun_kode saat buka tambah angkutan
    if ($('#form-tab').value==='angkutan') { $('#kebun_kode_angkutan').value = ''; }
    open();
  });
  $('#btn-close')?.addEventListener('click', close);
  $('#btn-cancel')?.addEventListener('click', close);

  function setKebunSelect(selectId, row){
    const sel = document.getElementById(selectId);
    if (!sel) return;
    if (row.kebun_id) { sel.value = row.kebun_id; return; }
    if (row.kebun_kode) {
      const opt = Array.from(sel.options).find(o => (o.dataset.kode||'') === String(row.kebun_kode));
      if (opt) sel.value = opt.value;
    }
  }

  // Edit & Delete
  document.body.addEventListener('click', async (e)=>{
    const t = e.target;
    if (t.classList.contains('btn-edit')) {
      form.reset();
      const row = JSON.parse(t.dataset.json);
      const tab = t.dataset.tab;
      $('#form-tab').value = tab; $('#form-action').value='update'; $('#form-id').value=row.id; title.textContent='Edit Data';
      toggleGroup(tab);

      if (tab==='angkutan'){
        setKebunSelect('kebun_id_angkutan', row);
        // sinkronkan hidden kebun_kode berdasarkan option terpilih
        syncKebunKode('kebun_id_angkutan','kebun_kode_angkutan');

        $('#gudang_asal').value = row.gudang_asal ?? '';
        $('#unit_tujuan_id').value = row.unit_tujuan_id ?? '';
        $('#tanggal_angkutan').value = row.tanggal ?? '';
        $('#jenis_pupuk_angkutan').value = row.jenis_pupuk ?? '';
        $('#jumlah_angkutan').value = row.jumlah ?? '';
        $('#nomor_do').value = row.nomor_do ?? '';
        $('#supir').value = row.supir ?? '';
      } else {
        setKebunSelect('kebun_id_menabur', row);
        $('#unit_id').value = row.unit_id ?? '';
        await refreshFormBlokByUnit();
        $('#blok').value = row.blok ?? '';
        $('#tanggal_menabur').value = row.tanggal ?? '';
        $('#jenis_pupuk_menabur').value = row.jenis_pupuk ?? '';
        $('#dosis').value = (row.dosis ?? '') === null ? '' : row.dosis;
        $('#jumlah_menabur').value = row.jumlah ?? '';
        $('#luas').value = row.luas ?? '';
        $('#invt_pokok').value = row.invt_pokok ?? '';
        $('#catatan').value = row.catatan ?? '';
      }
      open();
    }

    if (t.classList.contains('btn-delete')) {
      const id = t.dataset.id; const tab = t.dataset.tab;
      Swal.fire({title:'Hapus data ini?', text:'Tindakan ini tidak dapat dibatalkan.', icon:'warning', showCancelButton:true})
        .then(res=>{
          if (!res.isConfirmed) return;
          const fd = new FormData();
          fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete'); fd.append('tab',tab); fd.append('id',id);
          fetch('pemupukan_crud.php',{method:'POST', body:fd})
            .then(r=>r.json()).then(j=>{
              if (j.success) Swal.fire('Terhapus!', j.message||'', 'success').then(()=>location.reload());
              else Swal.fire('Gagal', j.message||'Error', 'error');
            }).catch(err=> Swal.fire('Error', String(err), 'error'));
        });
    }
  });

  // Submit
  $('#crud-form').addEventListener('submit', (e)=>{
    e.preventDefault();
    const tab = $('#form-tab').value;
    const fd  = new FormData(e.target);
    fd.set('tab', tab); fd.set('action', $('#form-action').value);

    if (tab==='angkutan') {
      const need = ['gudang_asal','unit_tujuan_id','tanggal_angkutan','jenis_pupuk_angkutan'];
      for (const id of need){
        const el=$('#'+id);
        if (!el || !el.value){
          Swal.fire('Validasi',`Field ${id.replaceAll('_',' ')} wajib diisi.`,'warning'); return;
        }
      }

      // pastikan hidden kebun_kode terisi berdasarkan pilihan kebun
      syncKebunKode('kebun_id_angkutan','kebun_kode_angkutan');

      fd.set('tanggal', $('#tanggal_angkutan').value);
      fd.set('jenis_pupuk', $('#jenis_pupuk_angkutan').value);
      fd.set('jumlah', $('#jumlah_angkutan').value||'');

      // kirimkan keduanya; backend gunakan kebun_kode untuk tabel angkutan_pupuk
      fd.set('kebun_id', $('#kebun_id_angkutan').value || '');
      fd.set('kebun_kode', $('#kebun_kode_angkutan').value || '');
    } else {
      const need = ['unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'];
      for (const id of need){
        const el=$('#'+id);
        if (!el || !el.value){
          Swal.fire('Validasi',`Field ${id.replaceAll('_',' ')} wajib diisi.`,'warning'); return;
        }
      }
      fd.set('tanggal', $('#tanggal_menabur').value);
      fd.set('jenis_pupuk', $('#jenis_pupuk_menabur').value);
      fd.set('dosis', $('#dosis').value||'');
      fd.set('jumlah', $('#jumlah_menabur').value||'');
      fd.set('luas', $('#luas').value||'');
      fd.set('invt_pokok', $('#invt_pokok').value||'');
      fd.set('catatan', $('#catatan').value||'');
    }

    fetch('pemupukan_crud.php',{method:'POST', body:fd})
      .then(r=>r.json()).then(j=>{
        if (j.success){
          close();
          Swal.fire({icon:'success',title:'Berhasil',text:j.message||'Tersimpan',timer:1400,showConfirmButton:false})
            .then(()=>location.reload());
        } else {
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : (j.message||'Terjadi kesalahan');
          Swal.fire('Gagal', html, 'error');
        }
      }).catch(err=> Swal.fire('Error', String(err), 'error'));
  });

  // init blok kalau group menabur terbuka
  if (document.querySelector('#group-menabur') && !document.querySelector('#group-menabur').classList.contains('hidden')) {
    refreshFormBlokByUnit();
  }
});
</script>
