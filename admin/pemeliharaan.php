<?php
// pages/pemeliharaan.php — green header, kebun≠rayon/bibit (terpisah), optional rayon/bibit, SweetAlert on, edit OK

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* ==== helpers: kolom dinamis ==== */
function col_exists(PDO $c, string $table, string $col): bool {
  static $cache=[]; $key=$table.'|'.$c->query("SELECT DATABASE()")->fetchColumn();
  if (!isset($cache[$key])) {
    $st=$c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $st->execute([':t'=>$table]);
    $cache[$key]=array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME'));
  }
  return in_array(strtolower($col), $cache[$key]??[], true);
}
function first_col(PDO $c, string $table, array $cands) {
  foreach ($cands as $x) if (col_exists($c,$table,$x)) return $x;
  return null;
}

/* ==== tabs ==== */
$tab_aktif = $_GET['tab'] ?? 'TU';
$daftar_tab = [
  'TU'       => 'Pemeliharaan TU',
  'TBM'      => 'Pemeliharaan TBM',
  'TM'       => 'Pemeliharaan TM',
  'BIBIT_PN' => 'Pemeliharaan Bibit PN',
  'BIBIT_MN' => 'Pemeliharaan Bibit MN',
];
$judul_tab_aktif = $daftar_tab[$tab_aktif] ?? 'Pemeliharaan TU';
$isBibit = in_array($tab_aktif, ['BIBIT_PN','BIBIT_MN'], true);

/* ==== masters ==== */
$units         = $conn->query("SELECT id,nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$jenis_master  = $conn->query("SELECT id,nama FROM md_jenis_pekerjaan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$tenaga_master = $conn->query("SELECT id,nama FROM md_tenaga ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$kebun_master  = $conn->query("SELECT id,nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);

$jenis_map  = []; foreach($jenis_master as $r)  $jenis_map[(int)$r['id']]  = $r['nama'];
$tenaga_map = []; foreach($tenaga_master as $r) $tenaga_map[(int)$r['id']] = $r['nama'];
$kebun_map  = []; foreach($kebun_master as $r)  $kebun_map[(int)$r['id']]  = $r['nama_kebun'];

/* ==== filter ==== */
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$f_unit_id   = ($_GET['unit_id']??'')===''? '' : (int)$_GET['unit_id'];
$f_bulan     = trim((string)($_GET['bulan'] ?? ''));
$f_tahun     = ($_GET['tahun']??'')===''? '' : (int)$_GET['tahun'];
$f_jenis_id  = ($_GET['jenis_id']??'')===''? '' : (int)$_GET['jenis_id'];
$f_tenaga_id = ($_GET['tenaga_id']??'')===''? '' : (int)$_GET['tenaga_id'];
$f_kebun_id  = ($_GET['kebun_id']??'')===''? '' : (int)$_GET['kebun_id'];
$f_rayon     = trim((string)($_GET['rayon'] ?? ''));
$f_bibit     = trim((string)($_GET['bibit'] ?? ''));

/* ==== pagination ==== */
$page     = max(1, (int)($_GET['page'] ?? 1));
$perOpts  = [10,25,50,100];
$per_page = (int)($_GET['per_page'] ?? 10);
if (!in_array($per_page,$perOpts,true)) $per_page=10;
$offset   = ($page-1)*$per_page;

/* ==== kolom dinamis utama ==== */
$hasKebunId = col_exists($conn,'pemeliharaan','kebun_id');
$colKebunNm = first_col($conn,'pemeliharaan',['kebun_nama','kebun','nama_kebun','kebun_text']); // nama kebun (teks)
$colRayon   = first_col($conn,'pemeliharaan',['rayon','rayon_nama']);
$colBibit   = first_col($conn,'pemeliharaan',['stood','stood_jenis','jenis_bibit','bibit']);

/* ==== WHERE building ==== */
$where = " WHERE p.kategori=:k"; $params=[':k'=>$tab_aktif];
if ($f_unit_id !== '') { $where.=" AND p.unit_id=:uid"; $params[':uid']=(int)$f_unit_id; }
if ($f_bulan   !== '') { $where.=" AND p.bulan=:bln";   $params[':bln']=$f_bulan; }
if ($f_tahun   !== '') { $where.=" AND p.tahun=:thn";   $params[':thn']=(int)$f_tahun; }

if ($f_jenis_id !== '') { $jn=$jenis_map[(int)$f_jenis_id]??null;  if($jn){ $where.=" AND p.jenis_pekerjaan=:jn"; $params[':jn']=$jn; } }
if ($f_tenaga_id !== ''){ $tn=$tenaga_map[(int)$f_tenaga_id]??null; if($tn){ $where.=" AND p.tenaga=:tn";          $params[':tn']=$tn; } }

if ($f_kebun_id !== '') {
  if ($hasKebunId) { $where.=" AND p.kebun_id=:kid"; $params[':kid']=(int)$f_kebun_id; }
  elseif ($colKebunNm) { $where.=" AND p.$colKebunNm=:knm"; $params[':knm']=($kebun_map[(int)$f_kebun_id]??''); }
  // tidak pernah pakai rayon sebagai kebun
}

if (!$isBibit) {
  if ($f_rayon!=='') { $col = $colRayon ?: 'rayon'; $where.=" AND p.$col LIKE :ry"; $params[':ry']="%$f_rayon%"; }
} else {
  if ($f_bibit!=='') { $col = $colBibit ?: 'bibit'; $where.=" AND p.$col LIKE :bb"; $params[':bb']="%$f_bibit%"; }
}

/* ==== COUNT ==== */
$stc=$conn->prepare("SELECT COUNT(*) FROM pemeliharaan p $where");
foreach($params as $k=>$v){ $stc->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
$stc->execute();
$total_rows=(int)$stc->fetchColumn();
$total_pages=max(1,(int)ceil($total_rows/$per_page));

/* ==== SELECT (paged) ==== */
$kebunSel = $hasKebunId ? "kb.nama_kebun AS kebun_nama" : ($colKebunNm ? "p.$colKebunNm AS kebun_nama" : "NULL AS kebun_nama");
$rayonSel = $colRayon ? "p.$colRayon AS rayon_val" : "NULL AS rayon_val";
$bibitSel = $colBibit ? "p.$colBibit AS bibit_val" : "NULL AS bibit_val";

$sql = "SELECT p.*, u.nama_unit AS unit_nama, $kebunSel, $rayonSel, $bibitSel
        FROM pemeliharaan p
        LEFT JOIN units u ON u.id=p.unit_id
        ".($hasKebunId ? "LEFT JOIN md_kebun kb ON kb.id=p.kebun_id" : "")."
        $where
        ORDER BY p.tahun DESC,
                 FIELD(p.bulan,".implode(',',array_map(fn($b)=>$conn->quote($b),$bulanList))."),
                 p.id DESC
        LIMIT :limit OFFSET :offset";
$st=$conn->prepare($sql);
foreach($params as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
$st->bindValue(':limit',$per_page,PDO::PARAM_INT);
$st->bindValue(':offset',$offset,PDO::PARAM_INT);
$st->execute();
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* ==== totals (filtered all) ==== */
$stt=$conn->prepare("SELECT COALESCE(SUM(p.rencana),0) AS tr, COALESCE(SUM(p.realisasi),0) AS tre FROM pemeliharaan p $where");
foreach($params as $k=>$v){ $stt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
$stt->execute(); $tot=$stt->fetch(PDO::FETCH_ASSOC);
$tot_r = (float)($tot['tr']??0); $tot_e = (float)($tot['tre']??0);
$tot_d = $tot_e - $tot_r; $tot_p = $tot_r>0 ? ($tot_e/$tot_r*100) : 0;

/* ==== qs helper ==== */
function qs_keep(array $extra=[]){
  $base=[
    'tab'=>$_GET['tab']??'','unit_id'=>$_GET['unit_id']??'','bulan'=>$_GET['bulan']??'','tahun'=>$_GET['tahun']??'',
    'jenis_id'=>$_GET['jenis_id']??'','tenaga_id'=>$_GET['tenaga_id']??'',
    'kebun_id'=>$_GET['kebun_id']??'','rayon'=>$_GET['rayon']??'','bibit'=>$_GET['bibit']??'',
  ];
  return http_build_query(array_merge($base,$extra));
}

/* ==== UI ==== */
$currentPage='pemeliharaan';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  .tbl-wrap{max-height:60vh;overflow-y:auto}
  thead.sticky{position:sticky;top:0;z-index:10}
  .btn{display:inline-flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;border-radius:.6rem;background:#fff;padding:.5rem 1rem}
  .btn-dark{background:#059669;color:#fff;border-color:#059669}
</style>

<div class="space-y-6">
  <div>
    <h1 class="text-3xl font-bold text-gray-900">Pemeliharaan</h1>
    <p class="text-gray-600 mt-1">Kelola data pemeliharaan perkebunan PTPN IV Regional 3</p>
  </div>

  <div class="border-b border-gray-200">
    <nav class="-mb-px flex flex-wrap gap-4 md:space-x-6">
      <?php foreach($daftar_tab as $k=>$v): ?>
        <a href="?<?= qs_keep(['tab'=>$k]) ?>"
           class="py-3 px-2 border-b-2 text-sm font-medium <?= $tab_aktif===$k?'border-green-600 text-gray-900':'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
          <?= htmlspecialchars($v) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="bg-white p-6 rounded-xl shadow-md">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <div>
        <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($judul_tab_aktif) ?></h2>
        <p class="text-gray-600 mt-1">Kategori: <span class="px-2 py-0.5 rounded border"><?= htmlspecialchars($tab_aktif) ?></span></p>
      </div>
      <div class="flex gap-2">
        <a class="btn" href="cetak/pemeliharaan_pdf.php?<?= qs_keep() ?>"><i class="ti ti-file-type-pdf text-red-600 text-xl"></i>PDF</a>
        <a class="btn" href="cetak/pemeliharaan_excel.php?<?= qs_keep() ?>"><i class="ti ti-file-spreadsheet text-emerald-600 text-xl"></i>Excel</a>
        <button id="btn-add" class="btn btn-dark"><i class="ti ti-plus"></i> Input Baru</button>
      </div>
    </div>

    <!-- FILTERS -->
    <form class="grid grid-cols-1 md:grid-cols-10 gap-3 mb-4" method="GET">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab_aktif) ?>">
      <div><label class="block text-xs font-semibold mb-1">Unit/Devisi</label>
        <select name="unit_id" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($f_unit_id!==''&&(int)$f_unit_id===(int)$u['id'])?'selected':'' ?>>
              <?= htmlspecialchars($u['nama_unit']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="block text-xs font-semibold mb-1">Bulan</label>
        <select name="bulan" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($bulanList as $b): ?><option value="<?= $b ?>" <?= $f_bulan===$b?'selected':'' ?>><?= $b ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="block text-xs font-semibold mb-1">Tahun</label>
        <input type="number" name="tahun" class="w-full border rounded-lg px-3 py-2" min="2000" max="2100" value="<?= htmlspecialchars($f_tahun===''?'':$f_tahun) ?>">
      </div>
      <div><label class="block text-xs font-semibold mb-1">Jenis Pekerjaan</label>
        <select name="jenis_id" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($jenis_master as $j): ?>
            <option value="<?= (int)$j['id'] ?>" <?= ($f_jenis_id!==''&&(int)$f_jenis_id===(int)$j['id'])?'selected':'' ?>><?= htmlspecialchars($j['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="block text-xs font-semibold mb-1">Tenaga</label>
        <select name="tenaga_id" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($tenaga_master as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($f_tenaga_id!==''&&(int)$f_tenaga_id===(int)$t['id'])?'selected':'' ?>><?= htmlspecialchars($t['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="block text-xs font-semibold mb-1">Kebun</label>
        <select name="kebun_id" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($kebun_master as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= ($f_kebun_id!==''&&(int)$f_kebun_id===(int)$k['id'])?'selected':'' ?>><?= htmlspecialchars($k['nama_kebun']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if(!$isBibit): ?>
        <div class="md:col-span-1"><label class="block text-xs font-semibold mb-1">Rayon</label>
          <input type="text" name="rayon" value="<?= htmlspecialchars($f_rayon) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Cari Rayon">
        </div>
      <?php else: ?>
        <div class="md:col-span-2"><label class="block text-xs font-semibold mb-1">Stood / Jenis Bibit</label>
          <input type="text" name="bibit" value="<?= htmlspecialchars($f_bibit) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Cari Stood/Jenis">
        </div>
      <?php endif; ?>
      <div class="flex items-end gap-2">
        <button class="btn btn-dark" type="submit"><i class="ti ti-filter"></i> Terapkan</button>
        <a class="btn" href="?tab=<?= urlencode($tab_aktif) ?>"><i class="ti ti-restore"></i> Reset</a>
      </div>
    </form>

    <!-- info & per-page -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
      <div class="text-sm text-gray-700">
        <?php $from=$total_rows?($offset+1):0; $to=min($offset+$per_page,$total_rows); ?>
        Menampilkan <strong><?= $from ?></strong>–<strong><?= $to ?></strong> dari <strong><?= number_format($total_rows) ?></strong> data
      </div>
      <form method="GET" class="flex items-center gap-2">
        <?php foreach(['tab'=>$tab_aktif,'unit_id'=>$f_unit_id,'bulan'=>$f_bulan,'tahun'=>$f_tahun,'jenis_id'=>$f_jenis_id,'tenaga_id'=>$f_tenaga_id,'kebun_id'=>$f_kebun_id,'rayon'=>$f_rayon,'bibit'=>$f_bibit,'page'=>1] as $k=>$v): ?>
          <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
        <?php endforeach; ?>
        <label class="text-sm text-gray-700">Baris/hal</label>
        <select name="per_page" class="border rounded-lg px-3 py-2" onchange="this.form.submit()">
          <?php foreach($perOpts as $o): ?><option value="<?= $o ?>" <?= $per_page===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?>
        </select>
      </form>
    </div>

    <!-- TABLE -->
    <div class="border rounded-xl overflow-x-auto">
      <div class="tbl-wrap">
        <table class="min-w-full table-fixed text-sm bg-white">
          <thead class="sticky top-0 z-10">
            <tr class="bg-green-600 text-white">
              <th class="py-3 px-4 text-left w-[7rem]">TAHUN</th>
              <th class="py-3 px-4 text-left w-[14rem]">KEBUN</th>
              <th class="py-3 px-4 text-left w-[12rem]"><?= $isBibit?'STOOD / JENIS':'RAYON' ?></th>
              <th class="py-3 px-4 text-left w-[12rem]">UNIT/DEVISI</th>
              <th class="py-3 px-4 text-left w-[14rem]">JENIS PEKERJAAN</th>
              <th class="py-3 px-4 text-left w-[10rem]">PERIODE</th>
              <th class="py-3 px-4 text-left w-[10rem]">TENAGA</th>
              <th class="py-3 px-4 text-right w-[9rem]">RENCANA</th>
              <th class="py-3 px-4 text-right w-[9rem]">REALISASI</th>
              <th class="py-3 px-4 text-right w-[7rem]">+/-</th>
              <th class="py-3 px-4 text-right w-[9rem]">PROGRESS (%)</th>
              <th class="py-3 px-4 text-left w-[10rem]">STATUS</th>
              <th class="py-3 px-4 text-left w-[8rem]">AKSI</th>
            </tr>
            <tr class="bg-green-50 text-green-900 border-b border-green-200">
              <th class="py-2 px-4 text-xs font-bold" colspan="7">JUMLAH</th>
              <th class="py-2 px-4 text-right text-xs font-bold"><?= number_format($tot_r,2) ?></th>
              <th class="py-2 px-4 text-right text-xs font-bold"><?= number_format($tot_e,2) ?></th>
              <th class="py-2 px-4 text-right text-xs font-bold"><?= number_format($tot_d,2) ?></th>
              <th class="py-2 px-4 text-right text-xs font-bold"><?= number_format($tot_p,2) ?>%</th>
              <th class="py-2 px-4"></th><th class="py-2 px-4"></th>
            </tr>
          </thead>
          <tbody class="text-gray-900">
            <?php if(empty($rows)): ?>
              <tr><td colspan="13" class="text-center py-10 text-gray-500">Belum ada data sesuai filter.</td></tr>
            <?php else: foreach($rows as $r):
              $rencana=(float)($r['rencana']??0); $realisasi=(float)($r['realisasi']??0);
              $delta=$realisasi-$rencana; $progress=$rencana>0?($realisasi/$rencana*100):0;
              $status = $progress>=100?'Selesai':($progress<70?'Tertunda':'Berjalan');
              $badge  = $status==='Selesai'?'bg-emerald-100 text-emerald-900 border-emerald-300':($status==='Berjalan'?'bg-yellow-100 text-yellow-900 border-yellow-300':'bg-blue-100 text-blue-900 border-blue-300');
              $periode = trim(($r['bulan']??'').' '.($r['tahun']??''));
              $rayonOrBibit = $isBibit ? ($r['bibit_val']??'-') : ($r['rayon_val']??'-');
            ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="py-3 px-4"><?= (int)$r['tahun'] ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['kebun_nama']??'-') ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($rayonOrBibit ?: '-') ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['unit_nama']??'-') ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['jenis_pekerjaan']??'-') ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($periode) ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['tenaga']??'-') ?></td>
                <td class="py-3 px-4 text-right"><?= number_format($rencana,2) ?></td>
                <td class="py-3 px-4 text-right"><?= number_format($realisasi,2) ?></td>
                <td class="py-3 px-4 text-right"><?= number_format($delta,2) ?></td>
                <td class="py-3 px-4 text-right"><?= number_format($progress,2) ?>%</td>
                <td class="py-3 px-4"><span class="px-2 py-1 text-xs font-semibold rounded-full border <?= $badge ?>"><?= $status ?></span></td>
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <button class="btn-edit text-blue-700 hover:text-blue-900 underline"
                      data-json='<?= htmlspecialchars(json_encode($r),ENT_QUOTES,"UTF-8") ?>'>Edit</button>
                    <button class="btn-delete text-red-700 hover:text-red-900 underline"
                      data-id="<?= (int)$r['id'] ?>">Hapus</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- pagination -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between p-3">
      <div class="text-sm text-gray-700">Halaman <strong><?= $page ?></strong> dari <strong><?= $total_pages ?></strong></div>
      <?php
        function page_link($p){ return '?'.qs_keep(['page'=>$p]); }
      ?>
      <div class="inline-flex gap-2">
        <a class="btn" href="<?= $page>1?page_link($page-1):'javascript:void(0)' ?>" style="<?= $page>1?'':'opacity:.5;pointer-events:none' ?>">Prev</a>
        <a class="btn" href="<?= $page<$total_pages?page_link($page+1):'javascript:void(0)' ?>" style="<?= $page<$total_pages?'':'opacity:.5;pointer-events:none' ?>">Next</a>
      </div>
    </div>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-3xl">
    <div class="flex justify-between items-center mb-6">
      <h2 id="modal-title" class="text-2xl font-bold text-gray-900">Input Pekerjaan Baru</h2>
      <button id="btn-close" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
    </div>

    <form id="crud-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      <input type="hidden" name="kategori" value="<?= htmlspecialchars($tab_aktif) ?>">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="block text-sm font-semibold mb-1">Jenis Pekerjaan *</label>
          <select name="jenis_id" id="jenis_id" required class="w-full border rounded-lg px-3 py-2">
            <option value="">— Pilih —</option>
            <?php foreach($jenis_master as $j): ?><option value="<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="block text-sm font-semibold mb-1">Tenaga *</label>
          <select name="tenaga_id" id="tenaga_id" required class="w-full border rounded-lg px-3 py-2">
            <option value="">— Pilih —</option>
            <?php foreach($tenaga_master as $t): ?><option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="block text-sm font-semibold mb-1">Unit/Devisi *</label>
          <select name="unit_id" id="unit_id" required class="w-full border rounded-lg px-3 py-2">
            <option value="">— Pilih —</option>
            <?php foreach($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="block text-sm font-semibold mb-1">Nama Kebun</label>
          <select name="kebun_id" id="kebun_id" class="w-full border rounded-lg px-3 py-2">
            <option value="">— Pilih —</option>
            <?php foreach($kebun_master as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?>
          </select>
        </div>

        <?php if(!$isBibit): ?>
          <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Rayon (opsional)</label>
            <input type="text" name="rayon" id="rayon" class="w-full border rounded-lg px-3 py-2" placeholder="cth: Rayon 1 / Afd I">
          </div>
        <?php else: ?>
          <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Stood / Jenis Bibit (opsional)</label>
            <input type="text" name="bibit" id="bibit" class="w-full border rounded-lg px-3 py-2" placeholder="cth: PN Stood 2">
          </div>
        <?php endif; ?>

        <div><label class="block text-sm font-semibold mb-1">Bulan *</label>
          <select name="bulan" id="bulan" required class="w-full border rounded-lg px-3 py-2">
            <?php foreach($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="block text-sm font-semibold mb-1">Tahun *</label>
          <input type="number" name="tahun" id="tahun" min="2000" max="2100" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <div><label class="block text-sm font-semibold mb-1">Rencana</label>
          <input type="number" step="0.01" name="rencana" id="rencana" min="0" class="w-full border rounded-lg px-3 py-2">
        </div>
        <div><label class="block text-sm font-semibold mb-1">Realisasi</label>
          <input type="number" step="0.01" name="realisasi" id="realisasi" min="0" class="w-full border rounded-lg px-3 py-2">
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
  const modal=document.getElementById('crud-modal');
  const form =document.getElementById('crud-form');

  const open =()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); };
  const close=()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); };

  document.getElementById('btn-add').addEventListener('click', ()=>{
    form.reset(); document.getElementById('form-action').value='store'; document.getElementById('form-id').value='';
    document.getElementById('modal-title').textContent='Input Pekerjaan Baru';
    open();
  });
  document.getElementById('btn-close').addEventListener('click', close);
  document.getElementById('btn-cancel').addEventListener('click', close);

  // edit & delete
  document.querySelector('tbody').addEventListener('click', (e)=>{
    const btn=e.target.closest('button'); if(!btn) return;

    if (btn.classList.contains('btn-edit')) {
      const data = JSON.parse(btn.dataset.json);
      form.reset();
      document.getElementById('form-action').value='update';
      document.getElementById('form-id').value=data.id||'';
      document.getElementById('modal-title').textContent='Edit Pekerjaan';

      function selectByText(sel, txt){
        if(!sel) return; const t=(txt||'').toString().trim().toLowerCase();
        for (const o of sel.options){ if(o.text.trim().toLowerCase()===t){ sel.value=o.value; break; } }
      }
      if (data.unit_nama)       selectByText(form.unit_id, data.unit_nama);
      if (data.jenis_pekerjaan) selectByText(form.jenis_id, data.jenis_pekerjaan);
      if (data.tenaga)          selectByText(form.tenaga_id, data.tenaga);
      if (data.kebun_nama)      selectByText(form.kebun_id, data.kebun_nama);

      form.bulan.value   = data.bulan || '';
      form.tahun.value   = data.tahun || '';
      form.rencana.value = data.rencana ?? '';
      form.realisasi.value = data.realisasi ?? '';

      // rayon/bibit dari alias
      if (form.rayon) form.rayon.value = (data.rayon_val || data.rayon || data.rayon_nama || '');
      if (form.bibit) form.bibit.value = (data.bibit_val || data.stood || data.stood_jenis || data.jenis_bibit || data.bibit || '');

      open();
    }

    if (btn.classList.contains('btn-delete')) {
      const id = btn.dataset.id;
      Swal.fire({title:'Hapus data ini?', text:'Tindakan ini tidak dapat dibatalkan.', icon:'warning', showCancelButton:true})
        .then(res=>{
          if(!res.isConfirmed) return;
          const fd=new FormData(); fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); fd.append('action','delete'); fd.append('id',id);
          fetch('pemeliharaan_crud.php',{method:'POST',body:fd})
            .then(r=>r.json()).then(j=>{
              if(j.success){ Swal.fire({icon:'success',title:'Terhapus',timer:1200,showConfirmButton:false}).then(()=>location.reload()); }
              else Swal.fire('Gagal', j.message||'Error', 'error');
            }).catch(err=> Swal.fire('Error', String(err), 'error'));
        });
    }
  });

  // submit
  form.addEventListener('submit', (e)=>{
    e.preventDefault();
    const need = ['jenis_id','tenaga_id','unit_id','bulan','tahun'];
    for (const n of need) { if(!form[n].value){ Swal.fire('Validasi',`Field ${n.replace('_',' ')} wajib diisi.`,'warning'); return; } }

    // tanggal auto (server juga menghitung ulang)
    const bulanList = <?= json_encode($bulanList) ?>;
    const idx = bulanList.indexOf(form.bulan.value)+1;
    const pad=n=>String(n).padStart(2,'0');
    const tanggal = `${form.tahun.value}-${pad(idx)}-01`;
    const hid=document.createElement('input'); hid.type='hidden'; hid.name='tanggal_auto'; hid.value=tanggal; form.appendChild(hid);

    const fd=new FormData(form);
    fetch('pemeliharaan_crud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if(j.success){
          Swal.fire({icon:'success',title:'Berhasil',text:j.message||'Tersimpan',timer:1400,showConfirmButton:false})
            .then(()=>location.reload());
        }else{
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : (j.message||'Terjadi kesalahan');
          Swal.fire('Gagal', html, 'error');
        }
      }).catch(err=> Swal.fire('Error', String(err), 'error'));
  });
});
</script>
