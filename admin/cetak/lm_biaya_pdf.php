<?php
// admin/cetak/lm76_export_pdf.php
// Layout: seperti contoh (header kiri-kanan, Volume Produksi, Biaya Produksi/Afdeling dengan BI & S/D BI)
// Persentase = (Realisasi − RKAP)/RKAP × 100

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ==== helpers ==== */
function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}
function nf($n,$d=2){ return number_format((float)$n,$d,',','.'); }
function pct_str($num){ return is_null($num) ? '-' : sprintf('%s%s%%', $num>=0?'+':'', nf($num,2)); }
function bulan_index($b){
  static $m=['Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12];
  return $m[$b] ?? null;
}
function idx_to_bulan($i){
  $arr=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
  return $arr[$i] ?? '';
}

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // ===== Filters =====
  $kebun_id = (isset($_GET['kebun_id']) && $_GET['kebun_id']!=='') ? (int)$_GET['kebun_id'] : null;
  $unit_id  = (isset($_GET['unit_id'])  && $_GET['unit_id'] !=='') ? (int)$_GET['unit_id']  : null;
  $bulan    = (isset($_GET['bulan'])    && $_GET['bulan']   !=='') ? trim($_GET['bulan'])   : null;
  $tahun    = (isset($_GET['tahun'])    && $_GET['tahun']   !=='') ? (int)$_GET['tahun']   : (int)date('Y');
  $tt       = (isset($_GET['tt'])       && $_GET['tt']      !=='') ? trim($_GET['tt'])      : null;

  $bulanIdx = bulan_index($bulan ?: idx_to_bulan((int)date('n')));

  /* ===== Master name for header ===== */
  $nama_kebun = '-'; $nama_unit = '-';
  if ($kebun_id){
    $nama_kebun = $pdo->prepare("SELECT nama_kebun FROM md_kebun WHERE id=?");
    $nama_kebun->execute([$kebun_id]); $nama_kebun = $nama_kebun->fetchColumn() ?: '-';
  }
  if ($unit_id){
    $nama_unit = $pdo->prepare("SELECT nama_unit FROM units WHERE id=?");
    $nama_unit->execute([$unit_id]); $nama_unit = $nama_unit->fetchColumn() ?: '-';
  }

  /* ====================== VOLUME PRODUKSI (LM76) ======================= */
  $hasKebun76  = col_exists($pdo,'lm76','kebun_id');
  $colBIrkap   = col_exists($pdo,'lm76','prod_bi_anggaran') ? 'prod_bi_anggaran' :
                 (col_exists($pdo,'lm76','prod_bi_rkap') ? 'prod_bi_rkap' : '0');

  $sql76 = "SELECT l.*, u.nama_unit ".($hasKebun76?", k.nama_kebun":"")."
            FROM lm76 l
            LEFT JOIN units u ON u.id=l.unit_id
            ".($hasKebun76?"LEFT JOIN md_kebun k ON k.id=l.kebun_id":"")."
            WHERE l.tahun=:thn";
  $b76 = [':thn'=>$tahun];
  if ($kebun_id && $hasKebun76){ $sql76.=" AND l.kebun_id=:kid"; $b76[':kid']=$kebun_id; }
  if ($unit_id){ $sql76.=" AND l.unit_id=:uid"; $b76[':uid']=$unit_id; }
  if ($bulan){ $sql76.=" AND l.bulan=:bln"; $b76[':bln']=$bulan; }
  if ($tt){ $sql76.=" AND l.tt=:tt"; $b76[':tt']=$tt; }

  $st76=$pdo->prepare($sql76); $st76->execute($b76);
  $rows76=$st76->fetchAll(PDO::FETCH_ASSOC);

  $agg=['bi_real'=>0,'bi_rkap'=>0,'sd_real'=>0,'sd_rkap'=>0];
  foreach($rows76 as $r){
    $agg['bi_real'] += (float)($r['prod_bi_realisasi'] ?? 0);
    $agg['bi_rkap'] += (float)($r[$colBIrkap] ?? 0);
  }

  // S/D BI: ambil semua bulan <= target
  $sql76sd = "SELECT SUM(COALESCE(prod_sd_realisasi,0)) sd_real, SUM(COALESCE(prod_sd_anggaran,0)) sd_rkap
              FROM lm76 WHERE tahun=:thn ".($bulan?" AND FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') <= :idx":"");
  $b76sd=[':thn'=>$tahun]; if($bulan) $b76sd[':idx']=$bulanIdx;
  if ($kebun_id && $hasKebun76){ $sql76sd.=" AND kebun_id=:kid"; $b76sd[':kid']=$kebun_id; }
  if ($unit_id){ $sql76sd.=" AND unit_id=:uid"; $b76sd[':uid']=$unit_id; }
  if ($tt){ $sql76sd.=" AND tt=:tt"; $b76sd[':tt']=$tt; }

  $st76sd=$pdo->prepare($sql76sd); $st76sd->execute($b76sd);
  $sd = $st76sd->fetch(PDO::FETCH_ASSOC) ?: ['sd_real'=>0,'sd_rkap'=>0];
  $agg['sd_real'] = (float)$sd['sd_real']; $agg['sd_rkap']=(float)$sd['sd_rkap'];

  $pct_bi = ($agg['bi_rkap']>0) ? (($agg['bi_real']-$agg['bi_rkap'])/$agg['bi_rkap']*100) : null;
  $pct_sd = ($agg['sd_rkap']>0) ? (($agg['sd_real']-$agg['sd_rkap'])/$agg['sd_rkap']*100) : null;

  /* ====================== BIAYA PRODUKSI (LM_BIAYA) ======================= */
  $hasKebunB = col_exists($pdo,'lm_biaya','kebun_id');

  // BI (bulan ini)
  $sqlBI = "SELECT alokasi, uraian_pekerjaan,
                   SUM(COALESCE(rencana_bi,0)) ang, SUM(COALESCE(realisasi_bi,0)) real
            FROM lm_biaya WHERE tahun=:thn ".($bulan?" AND bulan=:bln":"")." ";
  $bBI=[':thn'=>$tahun]; if($bulan) $bBI[':bln']=$bulan;
  if ($unit_id){ $sqlBI.=" AND unit_id=:uid"; $bBI[':uid']=$unit_id; }
  if ($kebun_id && $hasKebunB){ $sqlBI.=" AND kebun_id=:kid"; $bBI[':kid']=$kebun_id; }
  $sqlBI.=" GROUP BY alokasi, uraian_pekerjaan ORDER BY alokasi";
  $stBI=$pdo->prepare($sqlBI); $stBI->execute($bBI);
  $rowsBI=$stBI->fetchAll(PDO::FETCH_ASSOC);

  // SD BI (Januari..bulan)
  $sqlSD = "SELECT alokasi, uraian_pekerjaan,
                   SUM(COALESCE(rencana_bi,0)) ang, SUM(COALESCE(realisasi_bi,0)) real
            FROM lm_biaya WHERE tahun=:thn ";
  $bSD=[':thn'=>$tahun];
  if ($bulan){
    $sqlSD.=" AND FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') <= :idx";
    $bSD[':idx']=$bulanIdx;
  }
  if ($unit_id){ $sqlSD.=" AND unit_id=:uid"; $bSD[':uid']=$unit_id; }
  if ($kebun_id && $hasKebunB){ $sqlSD.=" AND kebun_id=:kid"; $bSD[':kid']=$kebun_id; }
  $sqlSD.=" GROUP BY alokasi, uraian_pekerjaan ORDER BY alokasi";
  $stSD=$pdo->prepare($sqlSD); $stSD->execute($bSD);
  $rowsSD=$stSD->fetchAll(PDO::FETCH_ASSOC);

  // Index by alokasi+uraian untuk merge BI & SD
  $mapSD = [];
  foreach($rowsSD as $r){
    $k = ($r['alokasi']??'')."|".($r['uraian_pekerjaan']??'');
    $mapSD[$k] = $r;
  }

  // Totals + kelompok pemupukan
  $tot = ['bi_ang'=>0,'bi_real'=>0,'sd_ang'=>0,'sd_real'=>0];
  $incl = ['bi_ang'=>0,'bi_real'=>0,'sd_ang'=>0,'sd_real'=>0];
  $excl = ['bi_ang'=>0,'bi_real'=>0,'sd_ang'=>0,'sd_real'=>0];

  $rowsMerged = [];
  foreach($rowsBI as $r){
    $key = ($r['alokasi']??'')."|".($r['uraian_pekerjaan']??'');
    $sd  = $mapSD[$key] ?? ['ang'=>0,'real'=>0];
    $isPupuk = (strpos(strtolower(($r['alokasi']??'').' '.($r['uraian_pekerjaan']??'')),'pupuk')!==false);

    $bi_ang=(float)$r['ang']; $bi_real=(float)$r['real'];
    $sd_ang=(float)$sd['ang']; $sd_real=(float)$sd['real'];

    $tot['bi_ang']+=$bi_ang; $tot['bi_real']+=$bi_real;
    $tot['sd_ang']+=$sd_ang; $tot['sd_real']+=$sd_real;

    if ($isPupuk){
      $incl['bi_ang']+=$bi_ang; $incl['bi_real']+=$bi_real;
      $incl['sd_ang']+=$sd_ang; $incl['sd_real']+=$sd_real;
    }else{
      $excl['bi_ang']+=$bi_ang; $excl['bi_real']+=$bi_real;
      $excl['sd_ang']+=$sd_ang; $excl['sd_real']+=$sd_real;
    }

    $rowsMerged[] = [
      'alokasi'=>$r['alokasi'],'uraian'=>$r['uraian_pekerjaan'],
      'bi_ang'=>$bi_ang,'bi_real'=>$bi_real,
      'sd_ang'=>$sd_ang,'sd_real'=>$sd_real
    ];
  }

  // Baris agregat “Incl. Pemupukan” harus termasuk SEMUA biaya (by tanaman incl pemupukan = total keseluruhan)
  // sesuai catatan, kita gunakan total (semua item) sebagai "Incl".
  // Jadi penuhi juga jika ada item pemupukan tersebar: incl = tot
  $incl = $tot;

  // Kalkulasi persen (+/- dan %)
  $calcPct = function($real,$ang){
    $plus = $real - $ang;
    $pct  = $ang>0 ? ($plus/$ang*100) : null;
    return [$plus,$pct];
  };

  // HPP
  $hpp_bi_incl = $agg['bi_real']>0 ? ($tot['bi_real']/$agg['bi_real']) : null;
  $hpp_bi_excl = $agg['bi_real']>0 ? ($excl['bi_real']/$agg['bi_real']) : null;
  $hpp_sd_incl = $agg['sd_real']>0 ? ($tot['sd_real']/$agg['sd_real']) : null;
  $hpp_sd_excl = $agg['sd_real']>0 ? ($excl['sd_real']/$agg['sd_real']) : null;

} catch (Throwable $e) {
  http_response_code(500);
  exit('DB Error: '.$e->getMessage());
}

ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 10mm 10mm; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#111; }
.head-wrap{ display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px; }
.h-left{ font-weight:700; line-height:1.5; }
.h-right{ text-align:right; font-weight:700; line-height:1.5; }
.h-sep{ border-top:2px dotted #aaa; margin:6px 0 10px; }

.table { width:100%; border-collapse:collapse; table-layout:fixed; }
.table th,.table td{ border:1px solid #cfd8dc; padding:6px 8px; }
.table thead th{ background:#e8f5e9; color:#0d5b38; font-weight:700; }
.subhead { background:#d1fae5; font-weight:700; color:#0f5132; }
.center{ text-align:center; } .right{ text-align:right; }
.pos{ color:#059669; } .neg{ color:#dc2626; }
.row-green { background:#ecfdf5; font-weight:700; }
.row-orange{ background:#ffe9d6; font-weight:700; }
.small{ font-size:10px; color:#6b7280; }
</style>
</head>
<body>

<div class="head-wrap">
  <div class="h-left">
    PT. PERKEBUNAN NUSANTARA - V<br>
    KEBUN : <?= htmlspecialchars($nama_kebun) ?><br>
    BULAN : <?= strtoupper(htmlspecialchars(($bulan?:idx_to_bulan(date('n'))).' '.$tahun)) ?>
  </div>
  <div class="h-right">
    BIAYA PRODUKSI / AFDELING<br>
    AFDELING : <?= htmlspecialchars($nama_unit) ?>
  </div>
</div>
<div class="h-sep"></div>

<!-- ============== VOLUME PRODUKSI ============== -->
<table class="table">
  <thead>
    <tr><th colspan="9" class="center">VOLUME PRODUKSI</th></tr>
    <tr class="subhead">
      <th style="width:22%">Uraian</th>
      <th style="width:14%" class="center" colspan="4">BULAN INI</th>
      <th style="width:14%" class="center" colspan="4">S / D BULAN INI</th>
    </tr>
    <tr class="subhead">
      <th></th>
      <th class="right">REALISASI</th>
      <th class="right">RKAP</th>
      <th class="right">+/-</th>
      <th class="right">(%)</th>
      <th class="right">REALISASI</th>
      <th class="right">RKAP</th>
      <th class="right">+/-</th>
      <th class="right">(%)</th>
    </tr>
  </thead>
  <tbody>
    <?php
      $bi_plus = $agg['bi_real'] - $agg['bi_rkap'];
      $sd_plus = $agg['sd_real'] - $agg['sd_rkap'];
      $bi_pct_cls = is_null($pct_bi)?'':($pct_bi>=0?'pos':'neg');
      $sd_pct_cls = is_null($pct_sd)?'':($pct_sd>=0?'pos':'neg');
    ?>
    <tr>
      <td><b>- Produksi TBS (KG)</b></td>
      <td class="right"><b><?= nf($agg['bi_real']) ?></b></td>
      <td class="right"><b><?= nf($agg['bi_rkap']) ?></b></td>
      <td class="right <?= $bi_plus>=0?'pos':'neg' ?>"><b><?= nf($bi_plus) ?></b></td>
      <td class="right <?= $bi_pct_cls ?>"><b><?= pct_str($pct_bi) ?></b></td>

      <td class="right"><?= nf($agg['sd_real']) ?></td>
      <td class="right"><?= nf($agg['sd_rkap']) ?></td>
      <td class="right <?= $sd_plus>=0?'pos':'neg' ?>"><?= nf($sd_plus) ?></td>
      <td class="right <?= $sd_pct_cls ?>"><?= pct_str($pct_sd) ?></td>
    </tr>
  </tbody>
</table>

<br>

<!-- ============== BIAYA PRODUKSI / AFDELING ============== -->
<table class="table">
  <thead>
    <tr class="subhead">
      <th style="width:12%">No. ALOKASI</th>
      <th>Uraian</th>
      <th class="center" colspan="4">BULAN INI</th>
      <th class="center" colspan="4">S / D BULAN INI</th>
    </tr>
    <tr class="subhead">
      <th></th><th></th>
      <th class="right">REALISASI</th>
      <th class="right">RKAP</th>
      <th class="right">+/-</th>
      <th class="right">(%)</th>
      <th class="right">REALISASI</th>
      <th class="right">RKAP</th>
      <th class="right">+/-</th>
      <th class="right">(%)</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$rowsMerged): ?>
      <tr><td colspan="10" class="center small">Belum ada data.</td></tr>
    <?php else: foreach($rowsMerged as $r):
      list($bi_plus,$bi_pct) = $calcPct($r['bi_real'],$r['bi_ang']);
      list($sd_plus,$sd_pct) = $calcPct($r['sd_real'],$r['sd_ang']);
      $bi_pct_cls = is_null($bi_pct)?'':($bi_pct>=0?'pos':'neg');
      $sd_pct_cls = is_null($sd_pct)?'':($sd_pct>=0?'pos':'neg');
    ?>
      <tr>
        <td><?= htmlspecialchars($r['alokasi']) ?></td>
        <td><?= htmlspecialchars($r['uraian']) ?></td>

        <td class="right"><?= nf($r['bi_real']) ?></td>
        <td class="right"><?= nf($r['bi_ang']) ?></td>
        <td class="right <?= $bi_plus>=0?'pos':'neg' ?>"><?= nf($bi_plus) ?></td>
        <td class="right <?= $bi_pct_cls ?>"><?= pct_str($bi_pct) ?></td>

        <td class="right"><?= nf($r['sd_real']) ?></td>
        <td class="right"><?= nf($r['sd_ang']) ?></td>
        <td class="right <?= $sd_plus>=0?'pos':'neg' ?>"><?= nf($sd_plus) ?></td>
        <td class="right <?= $sd_pct_cls ?>"><?= pct_str($sd_pct) ?></td>
      </tr>
    <?php endforeach; endif; ?>

    <!-- Rekap: Jlh By Tanaman Incl/Excl -->
    <?php
      list($bi_plus_i,$bi_pct_i) = $calcPct($tot['bi_real'],$tot['bi_ang']);
      list($sd_plus_i,$sd_pct_i) = $calcPct($tot['sd_real'],$tot['sd_ang']);
      list($bi_plus_e,$bi_pct_e) = $calcPct($excl['bi_real'],$excl['bi_ang']);
      list($sd_plus_e,$sd_pct_e) = $calcPct($excl['sd_real'],$excl['sd_ang']);
    ?>
    <tr class="row-green">
      <td colspan="2"><b>Jlh By Tanaman Incl. Pemupukan</b></td>
      <td class="right"><b><?= nf($tot['bi_real']) ?></b></td>
      <td class="right"><b><?= nf($tot['bi_ang']) ?></b></td>
      <td class="right <?= $bi_plus_i>=0?'pos':'neg' ?>"><b><?= nf($bi_plus_i) ?></b></td>
      <td class="right <?= is_null($bi_pct_i)?'':($bi_pct_i>=0?'pos':'neg') ?>"><b><?= pct_str($bi_pct_i) ?></b></td>

      <td class="right"><b><?= nf($tot['sd_real']) ?></b></td>
      <td class="right"><b><?= nf($tot['sd_ang']) ?></b></td>
      <td class="right <?= $sd_plus_i>=0?'pos':'neg' ?>"><b><?= nf($sd_plus_i) ?></b></td>
      <td class="right <?= is_null($sd_pct_i)?'':($sd_pct_i>=0?'pos':'neg') ?>"><b><?= pct_str($sd_pct_i) ?></b></td>
    </tr>

    <tr class="row-green">
      <td colspan="2"><b>Jlh By Tanaman Excl. Pemupukan</b></td>
      <td class="right"><b><?= nf($excl['bi_real']) ?></b></td>
      <td class="right"><b><?= nf($excl['bi_ang']) ?></b></td>
      <td class="right <?= $bi_plus_e>=0?'pos':'neg' ?>"><b><?= nf($bi_plus_e) ?></b></td>
      <td class="right <?= is_null($bi_pct_e)?'':($bi_pct_e>=0?'pos':'neg') ?>"><b><?= pct_str($bi_pct_e) ?></b></td>

      <td class="right"><b><?= nf($excl['sd_real']) ?></b></td>
      <td class="right"><b><?= nf($excl['sd_ang']) ?></b></td>
      <td class="right <?= $sd_plus_e>=0?'pos':'neg' ?>"><b><?= nf($sd_plus_e) ?></b></td>
      <td class="right <?= is_null($sd_pct_e)?'':($sd_pct_e>=0?'pos':'neg') ?>"><b><?= pct_str($sd_pct_e) ?></b></td>
    </tr>

    <!-- HPP -->
    <tr class="row-orange">
      <td colspan="6"><b>Harga Pokok Incl. Pemupukan</b></td>
      <td class="right"><b><?= is_null($hpp_sd_incl)?'-':nf($hpp_sd_incl,6) ?></b></td>
      <td class="right" colspan="3" style="background:#fff7ed"><span class="small">S/D BI = total biaya SD ÷ produksi SD</span></td>
    </tr>
    <tr class="row-orange">
      <td colspan="6"><b>Harga Pokok Excl. Pemupukan</b></td>
      <td class="right"><b><?= is_null($hpp_sd_excl)?'-':nf($hpp_sd_excl,6) ?></b></td>
      <td class="right" colspan="3" style="background:#fff7ed"><span class="small">S/D BI = total biaya SD (excl) ÷ produksi SD</span></td>
    </tr>
  </tbody>
</table>

<p class="small">Catatan: Persen = (Realisasi − RKAP)/RKAP × 100. Angka merah = negatif; hijau = positif.</p>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$filename = 'biaya_produksi_'.($bulan?:idx_to_bulan(date('n'))).'_'.$tahun.'_'.date('Ymd_His').'.pdf';
$dompdf->stream($filename, ['Attachment'=>false]);
exit;
