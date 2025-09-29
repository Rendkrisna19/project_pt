<?php
// pages/pemeliharaan.php (Filters + gray + Kebun->rayon + Pagination + Sticky Header + Summary + FILTER JENIS PEKERJAAN)
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* Helper: cek kolom ada/tidak */
function column_exists(PDO $c, string $table, string $col): bool {
  static $cache = [];
  if (!isset($cache[$table])) {
    $st = $c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $st->execute([':t'=>$table]);
    $cache[$table] = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
  }
  return in_array($col, $cache[$table] ?? [], true);
}

/* Tabs */
$tab_aktif = $_GET['tab'] ?? 'TU';
$daftar_tab = [
  'TU'=>'Pemeliharaan TU',
  'TBM'=>'Pemeliharaan TBM',
  'TM'=>'Pemeliharaan TM',
  'BIBIT_PN'=>'Pemeliharaan Bibit PN',
  'BIBIT_MN'=>'Pemeliharaan Bibit MN'
];
$judul_tab_aktif = $daftar_tab[$tab_aktif] ?? 'Pemeliharaan TU';

/* Master data untuk form */
$units         = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$jenis_master  = $conn->query("SELECT id, nama FROM md_jenis_pekerjaan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$tenaga_master = $conn->query("SELECT id, nama FROM md_tenaga ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$kebun_master  = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);

/* Map jenis_id -> nama (untuk fallback filter bila tidak ada kolom jenis_id) */
$jenis_map = [];
foreach ($jenis_master as $j) { $jenis_map[(int)$j['id']] = $j['nama']; }

/* ====== FILTERS ====== */
$f_unit_id = isset($_GET['unit_id']) ? (($_GET['unit_id']==='' )? '' : (int)$_GET['unit_id']) : '';
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$f_bulan   = isset($_GET['bulan']) ? trim((string)$_GET['bulan']) : '';
$f_tahun   = isset($_GET['tahun']) ? (($_GET['tahun']==='')? '' : (int)$_GET['tahun']) : '';
$f_tanggal = isset($_GET['tanggal']) ? trim((string)$_GET['tanggal']) : '';
/* NEW: Filter Jenis Pekerjaan (pakai id dari md_jenis_pekerjaan) */
$f_jenis_id = isset($_GET['jenis_id']) ? (($_GET['jenis_id']==='')? '' : (int)$_GET['jenis_id']) : '';

/* ====== PAGINATION ====== */
$page      = max(1, (int)($_GET['page'] ?? 1));
$perOpts   = [10,25,50,100];
$per_page  = (int)($_GET['per_page'] ?? 10);
if (!in_array($per_page, $perOpts, true)) $per_page = 10;
$offset    = ($page - 1) * $per_page;

/* ====== WHERE builder ====== */
$where = " WHERE p.kategori = :k";
$params = [':k'=>$tab_aktif];

if ($f_unit_id !== '' && $f_unit_id !== null) { $where .= " AND p.unit_id = :unit_id"; $params[':unit_id'] = (int)$f_unit_id; }
if ($f_bulan !== '')                           { $where .= " AND p.bulan = :bulan";    $params[':bulan']   = $f_bulan; }
if ($f_tahun !== '' && $f_tahun !== null)      { $where .= " AND p.tahun = :tahun";    $params[':tahun']   = (int)$f_tahun; }
if ($f_tanggal !== '')                         { $where .= " AND p.tanggal = :tgl";    $params[':tgl']     = $f_tanggal; }

/* NEW: WHERE untuk Jenis Pekerjaan */
if ($f_jenis_id !== '' && $f_jenis_id !== null) {
  if (column_exists($conn,'pemeliharaan','jenis_id')) {
    $where .= " AND p.jenis_id = :jid";
    $params[':jid'] = (int)$f_jenis_id;
  } else {
    /* fallback: cocokkan ke nama pada kolom jenis_pekerjaan */
    $namaJenis = $jenis_map[(int)$f_jenis_id] ?? null;
    if ($namaJenis) {
      $where .= " AND p.jenis_pekerjaan = :jnama";
      $params[':jnama'] = $namaJenis;
    }
  }
}

/* ====== COUNT total rows (untuk pagination) ====== */
$sql_count = "SELECT COUNT(*) FROM pemeliharaan p".$where;
$stc = $conn->prepare($sql_count);
$stc->execute($params);
$total_rows = (int)$stc->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

/* ====== DATA (paged) ====== */
$sql = "SELECT p.*, u.nama_unit AS unit_nama
        FROM pemeliharaan p
        LEFT JOIN units u ON u.id = p.unit_id" . $where . "
        ORDER BY p.tanggal DESC, p.id DESC
        LIMIT :limit OFFSET :offset";

$st = $conn->prepare($sql);
foreach ($params as $k => $v) {
  if (is_int($v)) $st->bindValue($k, $v, PDO::PARAM_INT);
  else            $st->bindValue($k, $v, PDO::PARAM_STR);
}
$st->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$st->bindValue(':offset', $offset,   PDO::PARAM_INT);
$st->execute();
$data_pemeliharaan = $st->fetchAll(PDO::FETCH_ASSOC);

/* ====== RINGKASAN: SUM semua (sesuai filter, tanpa pagination) ====== */
$sql_tot = "SELECT COALESCE(SUM(p.rencana),0) AS tot_rencana, COALESCE(SUM(p.realisasi),0) AS tot_realisasi
            FROM pemeliharaan p
            LEFT JOIN units u ON u.id = p.unit_id" . $where;
$stt = $conn->prepare($sql_tot);
foreach ($params as $k => $v) {
  if (is_int($v)) $stt->bindValue($k, $v, PDO::PARAM_INT);
  else            $stt->bindValue($k, $v, PDO::PARAM_STR);
}
$stt->execute();
$totals_all = $stt->fetch(PDO::FETCH_ASSOC);
$tot_all_rencana  = (float)($totals_all['tot_rencana'] ?? 0);
$tot_all_realisasi= (float)($totals_all['tot_realisasi'] ?? 0);
$tot_all_progress = $tot_all_rencana > 0 ? ($tot_all_realisasi / $tot_all_rencana) * 100 : 0;

/* ====== RINGKASAN: SUM halaman ini (berdasarkan data yang tampil) ====== */
$sum_page_rencana = 0.0; $sum_page_realisasi = 0.0;
foreach ($data_pemeliharaan as $row) {
  $sum_page_rencana  += (float)($row['rencana']  ?? 0);
  $sum_page_realisasi+= (float)($row['realisasi'] ?? 0);
}
$sum_page_progress = $sum_page_rencana > 0 ? ($sum_page_realisasi / $sum_page_rencana) * 100 : 0;

/* ====== Helper: build QS tanpa pagination (untuk export) ====== */
function qs_no_page(array $extra = []) {
  $base = [
    'tab'      => $_GET['tab']      ?? '',
    'unit_id'  => $_GET['unit_id']  ?? '',
    'bulan'    => $_GET['bulan']    ?? '',
    'tahun'    => $_GET['tahun']    ?? '',
    'tanggal'  => $_GET['tanggal']  ?? '',
    'jenis_id' => $_GET['jenis_id'] ?? '',
  ];
  return http_build_query(array_merge($base, $extra));
}

$currentPage='pemeliharaan';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="space-y-6">
  <div>
    <h1 class="text-3xl font-bold text-gray-900">Pemeliharaan</h1>
    <p class="text-gray-600 mt-1">Kelola data pemeliharaan perkebunan PTPN IV Regional 3</p>
  </div>

  <div class="border-b border-gray-200">
    <nav class="-mb-px flex flex-wrap gap-4 md:space-x-6">
      <?php foreach ($daftar_tab as $kode => $nama): ?>
        <?php
          $qsTab = http_build_query([
            'tab'      => $kode,
            'unit_id'  => $f_unit_id,
            'bulan'    => $f_bulan,
            'tahun'    => $f_tahun,
            'tanggal'  => $f_tanggal,
            'jenis_id' => $f_jenis_id,
          ]);
        ?>
        <a href="?<?= $qsTab ?>"
           class="py-3 px-2 border-b-2 font-medium text-sm <?= ($tab_aktif==$kode)?'border-gray-800 text-gray-900':'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
          <?= htmlspecialchars($nama) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="bg-white p-6 rounded-xl shadow-md">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3 mb-4">
      <div>
        <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($judul_tab_aktif) ?></h2>
        <p class="text-gray-600 mt-1">
          Kategori:
          <span class="px-2 py-0.5 rounded border border-gray-300 text-gray-800 font-semibold"><?= htmlspecialchars($tab_aktif) ?></span>
        </p>
      </div>
      <div class="flex gap-2">
        <a href="cetak/pemeliharaan_pdf.php?<?= qs_no_page() ?>"
           class="inline-flex items-center gap-2 bg-white border border-gray-300 px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50">
          <i class="ti ti-file-type-pdf text-red-600 text-xl"></i><span class="text-gray-800">PDF</span>
        </a>
        <a href="cetak/pemeliharaan_excel.php?<?= qs_no_page() ?>"
           class="inline-flex items-center gap-2 bg-white border border-gray-300 px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50">
          <i class="ti ti-file-spreadsheet text-emerald-600 text-xl"></i><span class="text-gray-800">Excel</span>
        </a>
        <button id="btn-input-baru"
                class="inline-flex items-center gap-2 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-gray-800">
          <i class="ti ti-plus"></i> Input Baru
        </button>
      </div>
    </div>

    <!-- FILTERS -->
    <form id="filter-form" class="grid grid-cols-1 md:grid-cols-7 gap-3 mb-4" method="GET">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab_aktif) ?>">

      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit/Devisi</label>
        <select name="unit_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
          <option value="">— Semua Unit —</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($f_unit_id!=='' && (int)$f_unit_id===(int)$u['id'])?'selected':'' ?>>
              <?= htmlspecialchars($u['nama_unit']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-700 mb-1">Bulan</label>
        <select name="bulan" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
          <option value="">— Semua Bulan —</option>
          <?php foreach ($bulanList as $b): ?>
            <option value="<?= $b ?>" <?= ($f_bulan===$b)?'selected':'' ?>><?= $b ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-700 mb-1">Tahun</label>
        <input type="number" name="tahun" min="2000" max="2100" value="<?= htmlspecialchars($f_tahun===''?'':$f_tahun) ?>"
               class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800" placeholder="Semua">
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal (opsional)</label>
        <input type="date" name="tanggal" value="<?= htmlspecialchars($f_tanggal) ?>"
               class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
      </div>

      <!-- NEW: Filter Jenis Pekerjaan -->
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-700 mb-1">Jenis Pekerjaan</label>
        <select name="jenis_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
          <option value="">— Semua Jenis —</option>
          <?php foreach ($jenis_master as $j): ?>
            <option value="<?= (int)$j['id'] ?>" <?= ($f_jenis_id!=='' && (int)$f_jenis_id===(int)$j['id'])?'selected':'' ?>>
              <?= htmlspecialchars($j['nama']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="flex items-end gap-2">
        <button class="bg-gray-900 text-white px-4 py-2 rounded-lg hover:bg-gray-800" type="submit">Terapkan</button>
        <a href="?tab=<?= urlencode($tab_aktif) ?>"
           class="px-4 py-2 rounded-lg border border-gray-300 text-gray-800 hover:bg-gray-50">Reset</a>
      </div>
    </form>

    <!-- INFO JUMLAH & PAGE SIZE -->
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-2">
      <div class="text-sm text-gray-700">
        <?php
          $from = $total_rows ? ($offset + 1) : 0;
          $to   = min($offset + $per_page, $total_rows);
        ?>
        Menampilkan <span class="font-semibold"><?= $from ?></span>–<span class="font-semibold"><?= $to ?></span>
        dari <span class="font-semibold"><?= number_format($total_rows) ?></span> data
      </div>
      <form method="GET" class="flex items-center gap-2">
        <?php
          $persist = [
            'tab'      => $tab_aktif,
            'unit_id'  => $f_unit_id,
            'bulan'    => $f_bulan,
            'tahun'    => $f_tahun,
            'tanggal'  => $f_tanggal,
            'jenis_id' => $f_jenis_id,
            'page'     => 1,
          ];
        ?>
        <?php foreach ($persist as $k=>$v): ?>
          <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
        <?php endforeach; ?>
        <label class="text-sm text-gray-700">Baris per halaman</label>
        <select name="per_page" class="px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800" onchange="this.form.submit()">
          <?php foreach ($perOpts as $opt): ?>
            <option value="<?= $opt ?>" <?= $per_page===$opt?'selected':'' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <!-- TABEL -->
    <div class="relative border rounded-xl">
      <div class="overflow-x-auto">
        <div class="max-h-[60vh] overflow-y-auto">
          <table class="min-w-full bg-white table-fixed">
            <thead class="bg-gray-50 sticky top-0 z-10">
              <tr>
                <th class="w-[16rem] py-3 px-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Jenis Pekerjaan</th>
                <th class="w-[12rem] py-3 px-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Tenaga</th>
                <th class="w-[12rem] py-3 px-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Unit/Devisi</th>
                <th class="w-[12rem] py-3 px-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Kebun</th>
                <th class="w-[10rem] py-3 px-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Periode</th>
                <th class="w-[9rem]  py-3 px-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Rencana</th>
                <th class="w-[9rem]  py-3 px-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Realisasi</th>
                <th class="w-[9rem]  py-3 px-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Progress (%)</th>
                <th class="w-[10rem] py-3 px-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="w-[8rem]  py-3 px-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="text-gray-800">
              <?php if (empty($data_pemeliharaan)): ?>
                <tr><td colspan="10" class="text-center py-10 text-gray-500">Belum ada data sesuai filter.</td></tr>
              <?php else: foreach ($data_pemeliharaan as $data):
                $rencana = (float)($data['rencana'] ?? 0);
                $realisasi = (float)($data['realisasi'] ?? 0);
                $progress = $rencana > 0 ? ($realisasi / $rencana) * 100 : 0;
                $badge = 'bg-gray-100 text-gray-900 border border-gray-200';
                if (($data['status'] ?? '') === 'Selesai')     $badge = 'bg-emerald-100 text-emerald-900 border-emerald-300';
                elseif (($data['status'] ?? '') === 'Berjalan') $badge = 'bg-blue-100 text-blue-900 border-blue-300';
                elseif (($data['status'] ?? '') === 'Tertunda') $badge = 'bg-yellow-100 text-yellow-900 border-yellow-300';
              ?>
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                  <td class="py-3 px-4"><?= htmlspecialchars($data['jenis_pekerjaan']) ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($data['tenaga'] ?? '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($data['unit_nama'] ?: '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars($data['rayon'] ?: '-') ?></td>
                  <td class="py-3 px-4"><?= htmlspecialchars(($data['bulan'] ?? '').' '.($data['tahun'] ?? '')) ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format($rencana, 2) ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format($realisasi, 2) ?></td>
                  <td class="py-3 px-4 text-right"><?= number_format($progress, 2) ?>%</td>
                  <td class="py-3 px-4"><span class="px-2 py-1 text-xs font-semibold rounded-full border <?= $badge ?>"><?= htmlspecialchars($data['status']) ?></span></td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <button class="btn-edit text-blue-700 hover:text-blue-900" title="Edit"
                              data-json='<?= htmlspecialchars(json_encode($data), ENT_QUOTES, "UTF-8") ?>'>
                        <i class="ti ti-edit"></i>
                      </button>
                      <button class="btn-delete text-red-700 hover:text-red-900" title="Hapus"
                              data-id="<?= (int)$data['id'] ?>">
                        <i class="ti ti-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ===== RINGKASAN DI BAWAH TABEL ===== -->
      <div class="grid md:grid-cols-2 gap-3 p-3">
        <!-- Ringkasan Halaman Ini -->
        <div class="rounded-xl border bg-gray-50 p-4">
          <div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Halaman Ini</div>
          <div class="grid grid-cols-3 gap-3 text-sm">
            <div class="p-3 rounded-lg bg-white border text-center">
              <div class="text-xs text-gray-500">Rencana</div>
              <div class="text-lg font-bold"><?= number_format($sum_page_rencana, 2) ?></div>
            </div>
            <div class="p-3 rounded-lg bg-white border text-center">
              <div class="text-xs text-gray-500">Realisasi</div>
              <div class="text-lg font-bold"><?= number_format($sum_page_realisasi, 2) ?></div>
            </div>
            <div class="p-3 rounded-lg bg-white border text-center">
              <div class="text-xs text-gray-500">Progress</div>
              <div class="text-lg font-bold"><?= number_format($sum_page_progress, 2) ?>%</div>
            </div>
          </div>
        </div>

        <!-- Ringkasan Semua (sesuai filter) -->
        <div class="rounded-xl border bg-gray-50 p-4">
          <div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Semua (sesuai filter)</div>
          <div class="grid grid-cols-3 gap-3 text-sm">
            <div class="p-3 rounded-lg bg-white border text-center">
              <div class="text-xs text-gray-500">Rencana</div>
              <div class="text-lg font-bold"><?= number_format($tot_all_rencana, 2) ?></div>
            </div>
            <div class="p-3 rounded-lg bg-white border text-center">
              <div class="text-xs text-gray-500">Realisasi</div>
              <div class="text-lg font-bold"><?= number_format($tot_all_realisasi, 2) ?></div>
            </div>
            <div class="p-3 rounded-lg bg-white border text-center">
              <div class="text-xs text-gray-500">Progress</div>
              <div class="text-lg font-bold"><?= number_format($tot_all_progress, 2) ?>%</div>
            </div>
          </div>
        </div>
      </div>
      <!-- ===== END RINGKASAN ===== -->

      <!-- PAGINATION CONTROLS -->
      <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between p-3">
        <div class="text-sm text-gray-700">
          Halaman <span class="font-semibold"><?= $page ?></span> dari <span class="font-semibold"><?= $total_pages ?></span>
        </div>
        <?php
          function page_link($p) {
            $q = [
              'tab'      => $_GET['tab']      ?? '',
              'unit_id'  => $_GET['unit_id']  ?? '',
              'bulan'    => $_GET['bulan']    ?? '',
              'tahun'    => $_GET['tahun']    ?? '',
              'tanggal'  => $_GET['tanggal']  ?? '',
              'jenis_id' => $_GET['jenis_id'] ?? '',
              'per_page' => $_GET['per_page'] ?? '',
              'page'     => $p,
            ];
            return '?'.http_build_query($q);
          }
        ?>
        <div class="inline-flex gap-2">
          <a href="<?= $page>1 ? page_link($page-1) : 'javascript:void(0)' ?>"
             class="px-3 py-2 rounded-lg border <?= $page>1?'hover:bg-gray-50 text-gray-800':'opacity-50 cursor-not-allowed text-gray-400' ?>">
            Prev
          </a>
          <a href="<?= $page<$total_pages ? page_link($page+1) : 'javascript:void(0)' ?>"
             class="px-3 py-2 rounded-lg border <?= $page<$total_pages?'hover:bg-gray-50 text-gray-800':'opacity-50 cursor-not-allowed text-gray-400' ?>">
            Next
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal CRUD (tetap) -->
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-3xl">
    <div class="flex justify-between items-center mb-6">
      <h2 id="modal-title" class="text-2xl font-bold text-gray-900">Input Pekerjaan Baru</h2>
      <button id="btn-close-modal" class="text-gray-500 hover:text-gray-800 text-3xl" aria-label="Close">&times;</button>
    </div>
    <form id="crud-form">
      <input type="hidden" name="id" id="modal-id">
      <input type="hidden" name="action" id="modal-action">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="kategori" value="<?= htmlspecialchars($tab_aktif) ?>">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Jenis Pekerjaan <span class="text-red-500">*</span></label>
          <select id="jenis_id" name="jenis_id" required
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
            <option value="">— Pilih Jenis Pekerjaan —</option>
            <?php foreach ($jenis_master as $j): ?>
              <option value="<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Tenaga <span class="text-red-500">*</span></label>
          <select id="tenaga_id" name="tenaga_id" required
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
            <option value="">— Pilih Tenaga —</option>
            <?php foreach ($tenaga_master as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Unit/Devisi <span class="text-red-500">*</span></label>
          <select id="unit_id" name="unit_id" required
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
            <option value="">— Pilih Unit —</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Field Kebun -> simpan ke 'rayon' -->
        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Nama Kebun</label>
          <select id="kebun_id" name="kebun_id"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
            <option value="">— Pilih Kebun —</option>
            <?php foreach ($kebun_master as $k): ?>
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" id="rayon" name="rayon">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Tanggal <span class="text-red-500">*</span></label>
          <input type="date" id="tanggal" name="tanggal" required
                 class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Bulan <span class="text-red-500">*</span></label>
          <select id="bulan" name="bulan" required
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
            <?php foreach ($bulanList as $b): ?>
              <option value="<?= $b ?>"><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Tahun <span class="text-red-500">*</span></label>
          <input type="number" id="tahun" name="tahun" min="2000" max="2100" required
                 class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Rencana Bulan Ini</label>
          <input type="number" step="0.01" id="rencana" name="rencana" min="0"
                 class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-800 mb-1">Realisasi Bulan Ini</label>
          <input type="number" step="0.01" id="realisasi" name="realisasi" min="0"
                 class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm font-semibold text-gray-800 mb-1">Status</label>
          <select id="status" name="status"
                  class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-0 focus:border-gray-500 bg-white text-gray-800">
            <option value="Berjalan">Berjalan</option>
            <option value="Selesai">Selesai</option>
            <option value="Tertunda">Tertunda</option>
          </select>
        </div>
      </div>

      <div class="flex justify-end gap-4 mt-8">
        <button type="button" id="btn-batal" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-800 hover:bg-gray-50">Batal</button>
        <button type="submit" class="px-6 py-2 rounded-lg bg-gray-900 text-white hover:bg-gray-800">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('crud-modal');
  const form  = document.getElementById('crud-form');
  const open  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

  document.getElementById('btn-input-baru').addEventListener('click', () => {
    form.reset();
    document.getElementById('modal-title').textContent = 'Input Pekerjaan Baru';
    document.getElementById('modal-action').value = 'store';
    document.getElementById('modal-id').value = '';
    form.rayon.value = '';
    open();
  });
  document.getElementById('btn-close-modal').addEventListener('click', close);
  document.getElementById('btn-batal').addEventListener('click', close);

  function selectByText(selectEl, text){
    const t = (text||'').toString().trim().toLowerCase();
    for (const op of selectEl.options) {
      if (op.text.trim().toLowerCase() === t) { selectEl.value = op.value; return; }
    }
    selectEl.value = '';
  }

  const kebunSelect = document.getElementById('kebun_id');
  function syncRayonFromKebun() {
    const sel = kebunSelect.options[kebunSelect.selectedIndex];
    const nama = sel && sel.text ? sel.text.trim() : '';
    form.rayon.value = nama;
  }
  kebunSelect.addEventListener('change', syncRayonFromKebun);

  document.querySelector('tbody').addEventListener('click', (e) => {
    const btn = e.target.closest('button'); if (!btn) return;

    if (btn.classList.contains('btn-edit')) {
      const data = JSON.parse(btn.dataset.json);
      form.reset();
      document.getElementById('modal-title').textContent = 'Edit Pekerjaan';
      document.getElementById('modal-action').value = 'update';
      ['id','tanggal','bulan','tahun','rencana','realisasi','status'].forEach(k => {
        if (form.elements[k] && data[k] !== undefined) form.elements[k].value = data[k];
      });
      if (data.unit_nama) selectByText(form.unit_id, data.unit_nama);
      if (data.jenis_pekerjaan) selectByText(form.jenis_id, data.jenis_pekerjaan);
      if (data.tenaga) selectByText(form.tenaga_id, data.tenaga);

      const rayonNama = (data.rayon || '').trim().toLowerCase();
      let matched = false;
      for (const op of kebunSelect.options) {
        if (op.text.trim().toLowerCase() === rayonNama) { kebunSelect.value = op.value; matched = true; break; }
      }
      if (!matched) kebunSelect.value = '';
      form.rayon.value = data.rayon || '';

      document.getElementById('modal-id').value = data.id || '';
      open();
    }

    if (btn.classList.contains('btn-delete')) {
      const id = btn.dataset.id;
      Swal.fire({title:'Anda yakin?', text:'Data yang dihapus tidak dapat dikembalikan!', icon:'warning', showCancelButton:true})
        .then(res => {
          if (!res.isConfirmed) return;
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete');
          fd.append('id', id);
          fetch('pemeliharaan_crud.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(j=>{
              if (j.success) Swal.fire('Terhapus!', j.message, 'success').then(()=>location.reload());
              else Swal.fire('Gagal', j.message||'Error', 'error');
            })
            .catch(err=> Swal.fire('Error', String(err), 'error'));
        });
    }
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const need = ['jenis_id','tenaga_id','unit_id','tanggal','bulan','tahun'];
    for (const n of need) { if (!form[n].value) { Swal.fire('Validasi', `Field ${n.replace('_',' ')} wajib diisi.`, 'warning'); return; } }
    syncRayonFromKebun();

    const fd = new FormData(form);
    fetch('pemeliharaan_crud.php', { method:'POST', body:fd })
      .then(r=>r.json()).then(j=>{
        if (j.success) {
          close();
          Swal.fire({icon:'success', title:'Berhasil', text:j.message, timer:1600, showConfirmButton:false})
            .then(()=>location.reload());
        } else {
          const msg = j.errors ? j.errors.join('\n') : (j.message||'Terjadi kesalahan');
          Swal.fire('Gagal', msg, 'error');
        }
      })
      .catch(err=> Swal.fire('Error', String(err), 'error'));
  });
});
</script>
