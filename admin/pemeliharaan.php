<?php
// pages/pemeliharaan.php

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// Dapatkan role user dari session
$userRole = $_SESSION['user_role'] ?? 'staf'; // Default ke 'staf' jika tidak ada untuk keamanan
$isStaf = ($userRole === 'staf');

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

/* ===== helpers ===== */
function col_exists(PDO $c, string $t, string $col): bool {
  static $cache=[];
  $dbName = $c->query("SELECT DATABASE()")->fetchColumn();
  $key = $t.'|'.$dbName;
  if (!isset($cache[$key])) {
    $st=$c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $st->execute([':t'=>$t]);
    $cache[$key]=array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME'));
  }
  return in_array(strtolower($col), $cache[$key]??[], true);
}
function enum_has(PDO $c, string $t, string $col, string $val): bool {
  $st=$c->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$t,':c'=>$col]);
  $type=$st->fetchColumn();
  if (!$type || stripos($type,'enum(')!==0) return true;
  preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/",$type,$m);
  return in_array($val, array_map('stripslashes',$m[1]??[]), true);
}

/* ===== tabs ===== */
$tabs = [
  'TU'       => 'Pemeliharaan TU',
  'TBM'      => 'Pemeliharaan TBM',
  'TM'       => 'Pemeliharaan TM',
  'TK'       => 'Pemeliharaan TK',
  'BIBIT_PN' => 'Pemeliharaan Bibit PN',
  'BIBIT_MN' => 'Pemeliharaan Bibit MN',
];
$tab = $_GET['tab'] ?? 'TU';
if (!isset($tabs[$tab])) $tab='TU';
$isBibit = in_array($tab,['BIBIT_PN','BIBIT_MN'],true);
$enumTKReady = enum_has($conn,'pemeliharaan','kategori','TK');

/* ===== masters ===== */
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$units  = $conn->query("SELECT id,nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$jenis  = $conn->query("SELECT id,nama FROM md_jenis_pekerjaan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$tenaga = $conn->query("SELECT id,nama FROM md_tenaga ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$kebun  = $conn->query("SELECT id,nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);

$jMap=[]; foreach($jenis as $r){ $jMap[(int)$r['id']]=$r['nama']; }
$tMap=[]; foreach($tenaga as $r){ $tMap[(int)$r['id']]=$r['nama']; }
$kMap=[]; foreach($kebun as $r){ $kMap[(int)$r['id']]=$r['nama_kebun']; }
$jenisNameToId = [];
foreach ($jenis as $j) { $jenisNameToId[$j['nama']] = (int)$j['id']; }

/* ===== filters ===== */
$f_unit   = ($_GET['unit_id']??'')===''? '' : (int)$_GET['unit_id'];
$f_bulan  = trim((string)($_GET['bulan']??''));
$f_tahun  = ($_GET['tahun']??'')===''? '' : (int)$_GET['tahun'];
$f_jenis  = ($_GET['jenis_id']??'')===''? '' : (int)$_GET['jenis_id'];
$f_tenaga = ($_GET['tenaga_id']??'')===''? '' : (int)$_GET['tenaga_id'];
$f_kebun  = ($_GET['kebun_id']??'')===''? '' : (int)$_GET['kebun_id'];
$f_rayon  = trim((string)($_GET['rayon']??''));
$f_bibit  = trim((string)($_GET['bibit']??''));
$f_ket    = trim((string)($_GET['keterangan']??''));

/* ===== paging ===== */
$page=max(1,(int)($_GET['page']??1));
$perOpts=[10,25,50,100];
$per=(int)($_GET['per_page']??10);
if(!in_array($per,$perOpts,true)) $per=10;
$offset=($page-1)*$per;

/* ===== dynamic existence ===== */
$hasKebunId    = col_exists($conn,'pemeliharaan','kebun_id');
$hasKeterangan = col_exists($conn,'pemeliharaan','keterangan');
$hasSatR       = col_exists($conn,'pemeliharaan','satuan_rencana');
$hasSatE       = col_exists($conn,'pemeliharaan','satuan_realisasi');

/* ===== where ===== */
$where=" WHERE p.kategori=:k"; $p=[":k"=>$tab];
if($f_unit!==''){ $where.=" AND p.unit_id=:u"; $p[':u']=(int)$f_unit; }
if($f_bulan!==''){ $where.=" AND p.bulan=:b"; $p[':b']=$f_bulan; }
if($f_tahun!==''){ $where.=" AND p.tahun=:t"; $p[':t']=(int)$f_tahun; }
if($f_jenis!==''){ $jn=$jMap[(int)$f_jenis]??null; if($jn){ $where.=" AND p.jenis_pekerjaan=:jn"; $p[':jn']=$jn; } }
if($f_tenaga!==''){ $tn=$tMap[(int)$f_tenaga]??null; if($tn){ $where.=" AND p.tenaga=:tn"; $p[':tn']=$tn; } }
if($f_kebun!==''){ if($hasKebunId){ $where.=" AND p.kebun_id=:kid"; $p[':kid']=(int)$f_kebun; } }
if(!$isBibit){
  if($f_rayon!==''){ $where.=" AND p.rayon LIKE :ry"; $p[':ry']="%$f_rayon%"; }
}else{
  if($f_bibit!==''){ $where.=" AND p.rayon LIKE :bb"; $p[':bb']="%$f_bibit%"; }
}
if($hasKeterangan && $f_ket!==''){ $where.=" AND p.keterangan LIKE :ket"; $p[':ket']="%$f_ket%"; }

/* ===== count ===== */
$stc=$conn->prepare("SELECT COUNT(*) FROM pemeliharaan p $where");
foreach($p as $k=>$v){ $stc->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
$stc->execute(); $total=(int)$stc->fetchColumn();
$pages=max(1,(int)ceil($total/$per));

/* ===== select ===== */
$kebunSel = $hasKebunId ? "kb.nama_kebun AS kebun_nama" : "NULL AS kebun_nama";
$sql="SELECT p.*, u.nama_unit AS unit_nama, $kebunSel
      FROM pemeliharaan p
      LEFT JOIN units u ON u.id=p.unit_id
      ".($hasKebunId?"LEFT JOIN md_kebun kb ON kb.id=p.kebun_id":"")."
      $where
      ORDER BY p.tahun DESC,
               FIELD(p.bulan,".implode(',',array_map(fn($b)=>$conn->quote($b),$bulanList))."),
               p.id DESC
      LIMIT :lim OFFSET :ofs";
$st=$conn->prepare($sql);
foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
$st->bindValue(':lim',$per,PDO::PARAM_INT);
$st->bindValue(':ofs',$offset,PDO::PARAM_INT);
$st->execute();
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* ===== totals ===== */
$stt=$conn->prepare("SELECT COALESCE(SUM(p.rencana),0) tr, COALESCE(SUM(p.realisasi),0) te FROM pemeliharaan p $where");
foreach($p as $k=>$v){ $stt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
$stt->execute(); $tot=$stt->fetch(PDO::FETCH_ASSOC);
$tot_r=(float)($tot['tr']??0); $tot_e=(float)($tot['te']??0);
$tot_d=$tot_e-$tot_r; $tot_p=$tot_r>0?($tot_e/$tot_r*100):0;

/* ===== qs helper ===== */
function qs_keep(array $extra=[]){
  $base=['tab'=>$_GET['tab']??'','unit_id'=>$_GET['unit_id']??'','bulan'=>$_GET['bulan']??'','tahun'=>$_GET['tahun']??'','jenis_id'=>$_GET['jenis_id']??'','tenaga_id'=>$_GET['tenaga_id']??'','kebun_id'=>$_GET['kebun_id']??'','rayon'=>$_GET['rayon']??'','bibit'=>$_GET['bibit']??'','keterangan'=>$_GET['keterangan']??''];
  return http_build_query(array_merge($base,$extra));
}

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
  .tabs{display:flex;flex-wrap:wrap;gap:.5rem}
  .tab-item{padding:.55rem .9rem;border-radius:.7rem;border:1px solid transparent;font-weight:600}
  .tab-active{background:#0ea5e9;color:#fff;border-color:#0ea5e9}
  .tab-inactive{background:#eaf6ff;color:#075985;border-color:#bae6fd}
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
  .underline:disabled { text-decoration: none; }
</style>

<div class="space-y-6">
  <div>
    <h1 class="text-3xl font-bold text-gray-900">Pemeliharaan</h1>
    <p class="text-gray-600 mt-1">Kelola data pemeliharaan perkebunan PTPN IV Regional 3</p>
  </div>

  <nav class="tabs">
    <?php foreach($tabs as $k=>$v): ?>
      <a href="?<?= qs_keep(['tab'=>$k,'page'=>1]) ?>" class="tab-item <?= $tab===$k?'tab-active':'tab-inactive' ?>"><?= htmlspecialchars($v) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="bg-white p-6 rounded-xl shadow-md">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <div>
        <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($tabs[$tab]) ?></h2>
        <p class="text-gray-600 mt-1">Kategori: <span class="px-2 py-0.5 rounded border"><?= htmlspecialchars($tab) ?></span></p>
      </div>
      <div class="flex gap-2">
        <a class="btn" href="cetak/pemeliharaan_pdf.php?<?= qs_keep() ?>"><i class="ti ti-file-type-pdf text-red-600 text-xl"></i>PDF</a>
        <a class="btn" href="cetak/pemeliharaan_excel.php?<?= qs_keep() ?>"><i class="ti ti-file-spreadsheet text-emerald-600 text-xl"></i>Excel</a>
        <button id="btn-add" class="btn btn-dark" <?= ($tab==='TK' && !$enumTKReady)?'disabled':'' ?>><i class="ti ti-plus"></i> Input Baru</button>
      </div>
    </div>

    <form class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4" method="GET">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1">Unit/Devisi</label>
        <select name="unit_id" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($f_unit!=='' && (int)$f_unit===(int)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1">Bulan</label>
        <select name="bulan" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($bulanList as $b): ?><option value="<?= $b ?>" <?= $f_bulan===$b?'selected':'' ?>><?= $b ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-1">
        <label class="block text-xs font-semibold mb-1">Tahun</label>
        <input type="number" name="tahun" class="w-full border rounded-lg px-3 py-2" min="2000" max="2100" value="<?= htmlspecialchars($f_tahun===''?'':$f_tahun) ?>">
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1">Jenis Pekerjaan</label>
        <select name="jenis_id" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($jenis as $j): ?><option value="<?= (int)$j['id'] ?>" <?= ($f_jenis!=='' && (int)$f_jenis===(int)$j['id'])?'selected':'' ?>><?= htmlspecialchars($j['nama']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1">Tenaga</label>
        <select name="tenaga_id" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($tenaga as $t): ?><option value="<?= (int)$t['id'] ?>" <?= ($f_tenaga!=='' && (int)$f_tenaga===(int)$t['id'])?'selected':'' ?>><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1">Kebun</label>
        <select name="kebun_id" class="w-full border rounded-lg px-3 py-2">
          <option value="">— Semua —</option>
          <?php foreach($kebun as $k): ?><option value="<?= (int)$k['id'] ?>" <?= ($f_kebun!=='' && (int)$f_kebun===(int)$k['id'])?'selected':'' ?>><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php if(!$isBibit): ?>
        <div class="md:col-span-2">
          <label class="block text-xs font-semibold mb-1">Rayon</label>
          <input type="text" name="rayon" value="<?= htmlspecialchars($f_rayon) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Cari Rayon">
        </div>
      <?php else: ?>
        <div class="md:col-span-2">
          <label class="block text-xs font-semibold mb-1">Stood / Jenis</label>
          <input type="text" name="bibit" value="<?= htmlspecialchars($f_bibit) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Cari Stood/Jenis">
        </div>
      <?php endif; ?>
      <div class="md:col-span-3">
        <label class="block text-xs font-semibold mb-1">Keterangan</label>
        <input type="text" name="keterangan" value="<?= htmlspecialchars($f_ket) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Cari Keterangan">
      </div>
      <div class="flex items-end gap-2 md:col-span-2">
        <button class="btn btn-dark" type="submit"><i class="ti ti-filter"></i> Terapkan</button>
        <a class="btn" href="?tab=<?= urlencode($tab) ?>"><i class="ti ti-restore"></i> Reset</a>
      </div>
    </form>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
      <div class="text-sm text-gray-700">
        <?php $from=$total?($offset+1):0; $to=min($offset+$per,$total); ?>
        Menampilkan <strong><?= $from ?></strong>–<strong><?= $to ?></strong> dari <strong><?= number_format($total) ?></strong> data
      </div>
      <form method="GET" class="flex items-center gap-2">
        <?php foreach(['tab'=>$tab,'unit_id'=>$f_unit,'bulan'=>$f_bulan,'tahun'=>$f_tahun,'jenis_id'=>$f_jenis,'tenaga_id'=>$f_tenaga,'kebun_id'=>$f_kebun,'rayon'=>$f_rayon,'bibit'=>$f_bibit,'keterangan'=>$f_ket,'page'=>1] as $k=>$v): ?>
          <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
        <?php endforeach; ?>
        <label class="text-sm text-gray-700">Baris/hal</label>
        <select name="per_page" class="border rounded-lg px-3 py-2" onchange="this.form.submit()">
          <?php foreach($perOpts as $o): ?><option value="<?= $o ?>" <?= $per===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?>
        </select>
      </form>
    </div>

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
              <th class="py-3 px-4 text-right w-[8rem]">RENCANA</th>
              <th class="py-3 px-4 text-left  w-[7rem]">SATUAN R.</th>
              <th class="py-3 px-4 text-right w-[8rem]">REALISASI</th>
              <th class="py-3 px-4 text-left  w-[7rem]">SATUAN E.</th>
              <th class="py-3 px-4 text-right w-[7rem]">+/-</th>
              <th class="py-3 px-4 text-right w-[8rem]">PROGRESS (%)</th>
              <th class="py-3 px-4 text-left w-[16rem]">KETERANGAN</th>
              <th class="py-3 px-4 text-left w-[8rem]">AKSI</th>
            </tr>
            <tr class="bg-green-50 text-green-900 border-b border-green-200">
              <th class="py-2 px-4 text-xs font-bold" colspan="7">JUMLAH</th>
              <th class="py-2 px-4 text-right text-xs font-bold"><?= number_format($tot_r,2) ?></th>
              <th class="py-2 px-4"></th>
              <th class="py-2 px-4 text-right text-xs font-bold"><?= number_format($tot_e,2) ?></th>
              <th class="py-2 px-4"></th>
              <th class="py-2 px-4 text-right text-xs font-bold"><?= number_format($tot_d,2) ?></th>
              <th class="py-2 px-4 text-right text-xs font-bold"><?= number_format($tot_p,2) ?>%</th>
              <th class="py-2 px-4"></th>
              <th class="py-2 px-4"></th>
            </tr>
          </thead>
          <tbody class="text-gray-900">
          <?php if(empty($rows)): ?>
            <tr><td colspan="15" class="text-center py-10 text-gray-500">Belum ada data sesuai filter.</td></tr>
          <?php else: foreach($rows as $r):
            $rencana=(float)($r['rencana']??0); $realisasi=(float)($r['realisasi']??0);
            $delta=$realisasi-$rencana; $progress=$rencana>0?($realisasi/$rencana*100):0;
            $periode=trim(($r['bulan']??'').' '.($r['tahun']??''));
            $rayOrBib = $r['rayon'] ?? '-';
          ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="py-3 px-4"><?= (int)$r['tahun'] ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($r['kebun_nama']??'-') ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($rayOrBib) ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($r['unit_nama']??'-') ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($r['jenis_pekerjaan']??'-') ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($periode) ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($r['tenaga']??'-') ?></td>
              <td class="py-3 px-4 text-right"><?= number_format($rencana,2) ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($hasSatR?$r['satuan_rencana']:'') ?></td>
              <td class="py-3 px-4 text-right"><?= number_format($realisasi,2) ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($hasSatE?$r['satuan_realisasi']:'') ?></td>
              <td class="py-3 px-4 text-right"><?= number_format($delta,2) ?></td>
              <td class="py-3 px-4 text-right"><?= number_format($progress,2) ?>%</td>
              <td class="py-3 px-4"><?= htmlspecialchars($hasKeterangan?($r['keterangan']??''):'') ?></td>
              <td class="py-3 px-4">
                <div class="flex items-center gap-3">
                  <button class="btn-edit text-blue-700 hover:text-blue-900 underline"
                          data-json='<?= htmlspecialchars(json_encode($r),ENT_QUOTES,"UTF-8") ?>'
                          <?= $isStaf ? 'disabled' : '' ?>
                          <?= ($tab==='TK' && !$enumTKReady) ? 'disabled' : '' ?>>Edit</button>
                  <button class="btn-delete text-red-700 hover:text-red-900 underline"
                          data-id="<?= (int)$r['id'] ?>"
                          <?= $isStaf ? 'disabled' : '' ?>
                          <?= ($tab==='TK' && !$enumTKReady) ? 'disabled' : '' ?>>Hapus</button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between p-3">
      <div class="text-sm text-gray-700">Halaman <strong><?= $page ?></strong> dari <strong><?= $pages ?></strong></div>
      <?php $plink=function($p){return '?'.qs_keep(['page'=>$p]);}; ?>
      <div class="inline-flex gap-2">
        <a class="btn" href="<?= $page>1?$plink($page-1):'javascript:void(0)' ?>" style="<?= $page>1?'':'opacity:.5;pointer-events:none' ?>">Prev</a>
        <a class="btn" href="<?= $page<$pages?$plink($page+1):'javascript:void(0)' ?>" style="<?= $page<$pages?'':'opacity:.5;pointer-events:none' ?>">Next</a>
      </div>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-3xl">
    <div class="flex justify-between items-center mb-6">
      <h2 id="modal-title" class="text-2xl font-bold text-gray-900">Input Pekerjaan Baru</h2>
      <button id="btn-close" class="text-gray-500 hover:text-gray-800 text-3xl" aria-label="Tutup">&times;</button>
    </div>

    <form id="crud-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      <input type="hidden" name="kategori" value="<?= htmlspecialchars($tab) ?>">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold mb-1">Jenis Pekerjaan <span class="text-red-500">*</span></label>
          <input type="hidden" name="jenis_id" id="jenis_id">
          <input
            type="text"
            id="jenis_nama"
            name="jenis_nama"
            list="jenis_list"
            class="w-full border rounded-lg px-3 py-2"
            placeholder="Ketik lalu pilih dari saran…"
            autocomplete="off"
            required
          >
          <datalist id="jenis_list">
            <?php foreach($jenis as $j): ?>
              <option value="<?= htmlspecialchars($j['nama']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <p class="text-xs text-gray-500 mt-1">Jika diketik manual, sistem akan mencocokkan otomatis.</p>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Tenaga <span class="text-red-500">*</span></label>
          <select name="tenaga_id" id="tenaga_id" required class="w-full border rounded-lg px-3 py-2">
            <option value="">— Pilih —</option>
            <?php foreach($tenaga as $t): ?><option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Unit/Devisi <span class="text-red-500">*</span></label>
          <select name="unit_id" id="unit_id" required class="w-full border rounded-lg px-3 py-2">
            <option value="">— Pilih —</option>
            <?php foreach($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Nama Kebun</label>
          <select name="kebun_id" id="kebun_id" class="w-full border rounded-lg px-3 py-2">
            <option value="">— Pilih —</option>
            <?php foreach($kebun as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?>
          </select>
        </div>

        <?php if(!$isBibit): ?>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-1">Rayon (opsional / untuk bibit isi Stood/Jenis)</label>
            <input type="text" name="rayon" id="rayon" class="w-full border rounded-lg px-3 py-2" placeholder="">
          </div>
        <?php else: ?>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold mb-1">Stood / Jenis</label>
            <input type="text" name="rayon" id="rayon" class="w-full border rounded-lg px-3 py-2" placeholder="cth: Jagug / PN Stood 2">
          </div>
        <?php endif; ?>
        <div>
          <label class="block text-sm font-semibold mb-1">Bulan <span class="text-red-500">*</span></label>
          <select name="bulan" id="bulan" required class="w-full border rounded-lg px-3 py-2">
            <?php foreach($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Tahun <span class="text-red-500">*</span></label>
          <input type="number" name="tahun" id="tahun" min="2000" max="2100" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Rencana</label>
          <input type="number" step="0.01" min="0" name="rencana" id="rencana" class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Satuan Rencana</label>
          <input type="text" name="satuan_rencana" id="satuan_rencana" class="w-full border rounded-lg px-3 py-2" placeholder="Ha / Kg / Pkk / dll">
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Realisasi</label>
          <input type="number" step="0.01" min="0" name="realisasi" id="realisasi" class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Satuan Realisasi</label>
          <input type="text" name="satuan_realisasi" id="satuan_realisasi" class="w-full border rounded-lg px-3 py-2" placeholder="Ha / Kg / Pkk / dll">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-semibold mb-1">Keterangan</label>
          <textarea name="keterangan" id="keterangan" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Catatan / detail pekerjaan"></textarea>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="btn">Batal</button>
        <button type="submit" class="btn btn-dark" <?= ($tab==='TK' && !$enumTKReady)?'disabled':'' ?>>Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const IS_STAF = <?= $isStaf ? 'true' : 'false' ?>;
  const modal=document.getElementById('crud-modal');
  const form =document.getElementById('crud-form');
  const open =()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); };
  const close=()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); };
  const btnAdd = document.getElementById('btn-add');
  const jenisId   = document.getElementById('jenis_id');
  const jenisNama = document.getElementById('jenis_nama');
  const norm = s => (s||'').toString().trim().replace(/\s+/g, ' ').toLowerCase();
  const exactMap = <?= json_encode($jenisNameToId, JSON_UNESCAPED_UNICODE) ?>;
  const looseMap = (()=>{ const m={}; for(const [k,v] of Object.entries(exactMap)){ m[norm(k)] = String(v); } return m; })();

  window._jenisFill = (nama, id) => {
    if (jenisNama) jenisNama.value = nama || '';
    if (jenisId)   jenisId.value   = id   || '';
  };

  function resolveJenisIdByName(rawName){
    const key = norm(rawName);
    if (!key) return {id:'', pickedName:''};
    if (looseMap[key]) {
      let official = rawName;
      for (const name of Object.keys(exactMap)){
        if (norm(name) === key) { official = name; break; }
      }
      return { id: looseMap[key], pickedName: official };
    }
    const candidates = Object.keys(exactMap).filter(n => norm(n).startsWith(key));
    if (candidates.length === 1) {
      const picked = candidates[0];
      return { id: looseMap[norm(picked)], pickedName: picked };
    }
    return { id:'', pickedName:'' };
  }

  function syncJenisIdFromNama(){
    const {id, pickedName} = resolveJenisIdByName(jenisNama?.value);
    if (id) {
      if (pickedName && pickedName !== jenisNama.value) jenisNama.value = pickedName;
      jenisId.value = id;
    } else {
      jenisId.value = '';
    }
  }

  if (jenisNama){
    jenisNama.addEventListener('input',  syncJenisIdFromNama);
    jenisNama.addEventListener('change', syncJenisIdFromNama);
    jenisNama.addEventListener('blur',   syncJenisIdFromNama);
  }

  if (btnAdd) btnAdd.addEventListener('click', ()=>{
    form.reset();
    document.getElementById('form-action').value='store';
    document.getElementById('form-id').value='';
    document.getElementById('modal-title').textContent='Input Pekerjaan Baru';
    window._jenisFill('', '');
    open();
    setTimeout(()=> jenisNama?.focus(), 60);
  });

  document.getElementById('btn-close').addEventListener('click', close);
  document.getElementById('btn-cancel').addEventListener('click', close);
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && !modal.classList.contains('hidden')) close(); });

  const tbody=document.querySelector('tbody');
  if (tbody) tbody.addEventListener('click', (e)=>{
    const btn=e.target.closest('button'); if(!btn) return;

    if (btn.classList.contains('btn-edit') && !btn.disabled && !IS_STAF) {
      const d=JSON.parse(btn.dataset.json||'{}');
      form.reset();
      document.getElementById('form-action').value='update';
      document.getElementById('form-id').value=d.id||'';
      document.getElementById('modal-title').textContent='Edit Pekerjaan';
      const pickByText=(sel, txt)=>{ if(!sel) return; const t=norm(txt); for(const o of sel.options){ if(norm(o.text)===t){ sel.value=o.value; break; } } };
      if(d.unit_nama)  pickByText(form.unit_id,  d.unit_nama);
      if(d.tenaga)     pickByText(form.tenaga_id,d.tenaga);
      if(d.kebun_nama) pickByText(form.kebun_id, d.kebun_nama);
      const {id, pickedName} = resolveJenisIdByName(d.jenis_pekerjaan);
      window._jenisFill(pickedName || (d.jenis_pekerjaan || ''), id || '');
      form.rayon.value             = d.rayon || '';
      form.bulan.value             = d.bulan || '';
      form.tahun.value             = d.tahun || '';
      form.rencana.value           = d.rencana ?? '';
      form.realisasi.value         = d.realisasi ?? '';
      form.satuan_rencana.value    = d.satuan_rencana || '';
      form.satuan_realisasi.value  = d.satuan_realisasi || '';
      form.keterangan.value        = d.keterangan || '';
      open();
      setTimeout(()=> jenisNama?.focus(), 60);
    }

    if (btn.classList.contains('btn-delete') && !btn.disabled && !IS_STAF) {
      const id = btn.dataset.id;
      Swal.fire({
        title: 'Hapus data ini?', text: 'Tindakan ini tidak dapat dibatalkan.', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
      }).then(res=>{
        if(!res.isConfirmed) return;
        const fd=new FormData();
        fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
        fd.append('action','delete');
        fd.append('id',id);
        fetch('pemeliharaan_crud.php',{method:'POST',body:fd})
          .then(r=>r.json())
          .then(j=>{
            if(j.success){
              Swal.fire({icon:'success',title:'Terhapus',timer:1200,showConfirmButton:false})
                .then(()=>location.reload());
            } else {
              Swal.fire('Gagal', j.message||'Error', 'error');
            }
          })
          .catch(err=> Swal.fire('Error', String(err), 'error'));
      });
    }
  });

  form.addEventListener('submit', (e)=>{
    e.preventDefault();
    syncJenisIdFromNama();

    const need=['tenaga_id','unit_id','bulan','tahun'];
    for(const n of need){
      if(!form[n].value){
        const labelMap={tenaga_id:'Tenaga', unit_id:'Unit/Devisi', bulan:'Bulan', tahun:'Tahun'};
        Swal.fire('Validasi',`Field ${labelMap[n]||n} wajib diisi.`, 'warning');
        return;
      }
    }
    if(!form.jenis_id.value){
      Swal.fire('Validasi','Field <b>Jenis Pekerjaan</b> wajib dipilih dari daftar master.', 'warning');
      jenisNama?.focus();
      return;
    }

    const bl=<?= json_encode($bulanList) ?>;
    const idx=bl.indexOf(form.bulan.value)+1;
    const pad=n=>String(n).padStart(2,'0');
    let hid=document.querySelector('input[name="tanggal_auto"]');
    if(!hid){ hid=document.createElement('input'); hid.type='hidden'; hid.name='tanggal_auto'; form.appendChild(hid); }
    hid.value = `${form.tahun.value}-${pad(idx)}-01`;

    const fd=new FormData(form);
    fetch('pemeliharaan_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        if(j.success){
          Swal.fire({icon:'success',title:'Berhasil',text:j.message||'Tersimpan',timer:1400,showConfirmButton:false})
            .then(()=>location.reload());
        } else {
          const html=j.errors?.length
            ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>`
            : (j.message||'Terjadi kesalahan');
          Swal.fire('Gagal', html, 'error');
        }
      })
      .catch(err=> Swal.fire('Error', String(err), 'error'));
  });
});
</script>