<?php
// pages/cetak/pemeliharaan_pdf.php — FINAL
// PDF mengikuti SEMUA filter (termasuk Keterangan) & menampilkan Satuan R/E + Keterangan

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$db  = new Database();
$pdo = $db->getConnection();

/* ========= Helpers ========= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function colExists(PDO $pdo, $table, $col){
  static $cache = [];
  $k = $table;
  if (!isset($cache[$k])) {
    $st=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $st->execute([':t'=>$table]);
    $cache[$k]=array_flip(array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME')));
  }
  return isset($cache[$k][strtolower($col)]);
}
function pickCol(PDO $pdo, $table, array $cands){ foreach($cands as $c){ if(colExists($pdo,$table,$c)) return $c; } return null; }
$bulanNama=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$allowedTab=['TU','TBM','TM','TK','BIBIT_PN','BIBIT_MN'];
$titles=[
  'TU'=>'Pemeliharaan TU','TBM'=>'Pemeliharaan TBM','TM'=>'Pemeliharaan TM','TK'=>'Pemeliharaan TK',
  'BIBIT_PN'=>'Pemeliharaan Bibit PN','BIBIT_MN'=>'Pemeliharaan Bibit MN'
];
$tab = $_GET['tab'] ?? 'TU';
if (!in_array($tab,$allowedTab,true)) $tab='TU';
$isBibit = in_array($tab,['BIBIT_PN','BIBIT_MN'],true);

/* ========= Filters (sinkron) ========= */
$f_unit_id   = isset($_GET['unit_id'])   && $_GET['unit_id']   !== '' ? (int)$_GET['unit_id'] : '';
$f_bulan     = isset($_GET['bulan'])     && $_GET['bulan']     !== '' ? (string)$_GET['bulan'] : '';
$f_tahun     = isset($_GET['tahun'])     && $_GET['tahun']     !== '' ? (int)$_GET['tahun'] : '';
$f_jenis_id  = isset($_GET['jenis_id'])  && $_GET['jenis_id']  !== '' ? (int)$_GET['jenis_id'] : '';
$f_tenaga_id = isset($_GET['tenaga_id']) && $_GET['tenaga_id'] !== '' ? (int)$_GET['tenaga_id'] : '';
$f_kebun_id  = isset($_GET['kebun_id'])  && $_GET['kebun_id']  !== '' ? (int)$_GET['kebun_id'] : '';
$f_rayon     = isset($_GET['rayon'])     && $_GET['rayon']     !== '' ? (string)$_GET['rayon'] : '';
$f_bibit     = isset($_GET['bibit'])     && $_GET['bibit']     !== '' ? (string)$_GET['bibit'] : '';
$f_ket       = isset($_GET['keterangan'])&& $_GET['keterangan']!== '' ? (string)$_GET['keterangan'] : '';

/* ========= Map ID → Nama ========= */
$jenis_nama = '';
if ($f_jenis_id!==''){ $s=$pdo->prepare("SELECT nama FROM md_jenis_pekerjaan WHERE id=:i"); $s->execute([':i'=>$f_jenis_id]); $jenis_nama=(string)$s->fetchColumn(); }
$tenaga_nama= '';
if ($f_tenaga_id!==''){ $s=$pdo->prepare("SELECT nama FROM md_tenaga WHERE id=:i"); $s->execute([':i'=>$f_tenaga_id]); $tenaga_nama=(string)$s->fetchColumn(); }
$kebun_nama = '';
if ($f_kebun_id!==''){ $s=$pdo->prepare("SELECT nama_kebun FROM md_kebun WHERE id=:i"); $s->execute([':i'=>$f_kebun_id]); $kebun_nama=(string)$s->fetchColumn(); }

/* ========= Dynamic Columns ========= */
$hasTanggal   = colExists($pdo,'pemeliharaan','tanggal');
$hasBulanCol  = colExists($pdo,'pemeliharaan','bulan');
$hasTahunCol  = colExists($pdo,'pemeliharaan','tahun');
$hasKebunId   = colExists($pdo,'pemeliharaan','kebun_id');
$hasKebunKode = colExists($pdo,'pemeliharaan','kebun_kode');
$hasKet       = colExists($pdo,'pemeliharaan','keterangan');
$hasSatR      = colExists($pdo,'pemeliharaan','satuan_rencana');
$hasSatE      = colExists($pdo,'pemeliharaan','satuan_realisasi');

$colKebunText = pickCol($pdo,'pemeliharaan',['kebun_nama','kebun','nama_kebun','kebun_text']);
$colRayon     = pickCol($pdo,'pemeliharaan',['rayon','rayon_nama']);
$colBibit     = pickCol($pdo,'pemeliharaan',['stood','stood_jenis','jenis_bibit','bibit']);

/* ========= SELECT Pieces ========= */
$joinKebun=''; $selKebun='';
if ($hasKebunId)      { $joinKebun=" LEFT JOIN md_kebun kb ON kb.id=p.kebun_id ";    $selKebun=", kb.nama_kebun AS kebun_nama"; }
elseif ($hasKebunKode){ $joinKebun=" LEFT JOIN md_kebun kb ON kb.kode=p.kebun_kode ";$selKebun=", kb.nama_kebun AS kebun_nama"; }
elseif ($colKebunText){ $selKebun=", p.$colKebunText AS kebun_nama"; }
else { $selKebun=", NULL AS kebun_nama"; }

$selRayon  = $colRayon ? ", p.$colRayon AS rayon_val" : ", NULL AS rayon_val";
$selBibit  = $colBibit ? ", p.$colBibit AS bibit_val" : ", NULL AS bibit_val";

/* ========= BUILD QUERY + FILTERS ========= */
$sql="SELECT p.*, u.nama_unit AS unit_nama $selKebun $selRayon $selBibit
      FROM pemeliharaan p
      LEFT JOIN units u ON u.id=p.unit_id
      $joinKebun
      WHERE p.kategori=:k";
$params=[':k'=>$tab];

if ($f_unit_id!==''){ $sql.=" AND p.unit_id=:uid"; $params[':uid']=$f_unit_id; }

if ($f_bulan!==''){
  if ($hasBulanCol)    { $sql.=" AND p.bulan=:bln"; $params[':bln']=$f_bulan; }
  elseif ($hasTanggal) { $sql.=" AND MONTH(p.tanggal)=:blnnum"; $params[':blnnum']=array_search($f_bulan,$bulanNama,true) ?: array_search($f_bulan,array_values($bulanNama),true); }
}

if ($f_tahun!==''){
  if ($hasTahunCol)    { $sql.=" AND p.tahun=:th"; $params[':th']=$f_tahun; }
  elseif ($hasTanggal) { $sql.=" AND YEAR(p.tanggal)=:th"; $params[':th']=$f_tahun; }
}

if ($f_jenis_id!==''){ if($jenis_nama!==''){ $sql.=" AND p.jenis_pekerjaan=:jn"; $params[':jn']=$jenis_nama; } else { $sql.=" AND 1=0"; } }
if ($f_tenaga_id!==''){ if($tenaga_nama!==''){ $sql.=" AND p.tenaga=:tn"; $params[':tn']=$tenaga_nama; } else { $sql.=" AND 1=0"; } }

if ($f_kebun_id!==''){
  if     ($hasKebunId)   { $sql.=" AND p.kebun_id=:kid"; $params[':kid']=$f_kebun_id; }
  elseif ($joinKebun)    { $sql.=" AND kb.id=:kid";      $params[':kid']=$f_kebun_id; }
  elseif ($colKebunText && $kebun_nama!==''){ $sql.=" AND p.$colKebunText=:kname"; $params[':kname']=$kebun_nama; }
}

if ($isBibit){
  if ($f_bibit!==''){
    $col = $colBibit ?: 'bibit';
    $sql.=" AND p.$col LIKE :bb"; $params[':bb']="%{$f_bibit}%";
  }
} else {
  if ($f_rayon!==''){
    $col = $colRayon ?: 'rayon';
    $sql.=" AND p.$col LIKE :ry"; $params[':ry']="%{$f_rayon}%";
  }
}

if ($hasKet && $f_ket!==''){ $sql.=" AND p.keterangan LIKE :ket"; $params[':ket']="%{$f_ket}%"; }

$sql .= $hasTanggal ? " ORDER BY p.tanggal DESC, p.id DESC" : " ORDER BY p.tahun DESC, p.id DESC";
$st=$pdo->prepare($sql);
foreach($params as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
$st->execute();
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* ========= Totals ========= */
$tot_r=0.0; $tot_e=0.0;
foreach($rows as $r){ $tot_r+=(float)($r['rencana']??0); $tot_e+=(float)($r['realisasi']??0); }
$tot_d=$tot_e-$tot_r; $tot_p=$tot_r>0?($tot_e/$tot_r*100):0;

/* ========= Filter line ========= */
$parts=["Kategori $tab"];
if ($f_unit_id!==''){ $u=$pdo->prepare("SELECT nama_unit FROM units WHERE id=:i"); $u->execute([':i'=>$f_unit_id]); $parts[]='Unit '.$u->fetchColumn(); }
if ($f_bulan!==''){ $parts[]='Bulan '.$f_bulan; }
if ($f_tahun!==''){ $parts[]='Tahun '.$f_tahun; }
if ($f_jenis_id!==''){ $parts[]='Jenis '.$jenis_nama; }
if ($f_tenaga_id!==''){ $parts[]='Tenaga '.$tenaga_nama; }
if ($f_kebun_id!==''){ $parts[]='Kebun '.$kebun_nama; }
if ($isBibit){ if ($f_bibit!==''){ $parts[]='Stood/Jenis '.$f_bibit; } }
else { if ($f_rayon!==''){ $parts[]='Rayon '.$f_rayon; } }
if ($f_ket!==''){ $parts[]='Ket. '.$f_ket; }
$filterLine='Filter: '.implode(' | ',$parts);

/* ========= HTML ========= */
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= h($titles[$tab]) ?></title>
<style>
  @page { size: A4 landscape; margin: 18mm 15mm 15mm 15mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
  .brand { background:#0f7b4f; color:#fff; padding:14px 16px; border-radius:10px; text-align:center; margin-bottom:12px; }
  .brand h1 { margin:0; font-size:18px; letter-spacing:.5px; }
  .sub { text-align:center; color:#0f7b4f; font-weight:bold; font-size:14px; margin:6px 0 4px; }
  .filter { text-align:center; font-size:10px; color:#555; margin-bottom:10px; }
  table{ width:100%; border-collapse:collapse; }
  th,td{ border:1px solid #dfe5e2; padding:6px 8px; }
  thead th{ background:#1fab61; color:#fff; text-transform:uppercase; font-size:10px; }
  tbody tr:nth-child(even){ background:#fbfdfc; }
  tfoot td{ background:#f1faf6; font-weight:700; }
  .right{text-align:right}.center{text-align:center}
  .badge{display:inline-block;padding:2px 6px;border-radius:10px;font-size:10px;border:1px solid transparent}
  .b-green{background:#dbf5e9;color:#045e3e;border-color:#b9ead6}
  .b-blue{background:#e7f1fd;color:#0b4a88;border-color:#cfe2fb}
  .b-yellow{background:#fff7e0;color:#8a6b00;border-color:#fde8b3}
  .b-gray{background:#f1f3f2;color:#333;border-color:#e0e5e3}
</style>
</head>
<body>
  <div class="brand"><h1>PTPN IV REGIONAL 3</h1></div>
  <div class="sub"><?= h($titles[$tab]) ?></div>
  <div class="filter"><?= h($filterLine) ?></div>

  <table>
    <thead>
      <tr>
        <th>TAHUN</th>
        <th>KEBUN</th>
        <th><?= $isBibit ? 'STOOD / JENIS BIBIT' : 'RAYON' ?></th>
        <th>UNIT/DEVISI</th>
        <th>JENIS PEKERJAAN</th>
        <th>PERIODE</th>
        <th>TENAGA</th>
        <th class="right">RENCANA</th>
        <th>SATUAN R.</th>
        <th class="right">REALISASI</th>
        <th>SATUAN E.</th>
        <th class="right">+/-</th>
        <th class="right">PROGRESS (%)</th>
        <th>KETERANGAN</th>
        <th>STATUS</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="15" class="center">Tidak ada data.</td></tr>
      <?php else:
        foreach($rows as $r):
          $rencana=(float)($r['rencana']??0);
          $realisasi=(float)($r['realisasi']??0);
          $delta=$realisasi-$rencana;
          $th = $hasTahunCol ? (string)($r['tahun']??'') : ($hasTanggal && !empty($r['tanggal']) ? date('Y',strtotime($r['tanggal'])) : '');
          $bln= $hasBulanCol ? (string)($r['bulan']??'') : ($hasTanggal && !empty($r['tanggal']) ? ($bulanNama[(int)date('n',strtotime($r['tanggal']))]??'') : '');
          $periode=trim($bln.' '.$th);
          $progress=$rencana>0?($realisasi/$rencana*100):0;
          $status=(string)($r['status']??'');
          $cls='b-gray'; if($status==='Selesai') $cls='b-green'; elseif($status==='Berjalan') $cls='b-blue'; elseif($status==='Tertunda') $cls='b-yellow';
          $satR = $hasSatR ? (string)($r['satuan_rencana']??'') : '';
          $satE = $hasSatE ? (string)($r['satuan_realisasi']??'') : '';
          $ket  = $hasKet  ? (string)($r['keterangan']??'') : '';
      ?>
        <tr>
          <td><?= h($th) ?></td>
          <td><?= h($r['kebun_nama']??'-') ?></td>
          <td><?= h(($isBibit?($r['bibit_val']??''):($r['rayon_val']??'')) ?: '-') ?></td>
          <td><?= h($r['unit_nama']??'-') ?></td>
          <td><?= h($r['jenis_pekerjaan']??'-') ?></td>
          <td class="center"><?= h($periode) ?></td>
          <td><?= h($r['tenaga']??'-') ?></td>
          <td class="right"><?= number_format($rencana,2,',','.') ?></td>
          <td><?= h($satR) ?></td>
          <td class="right"><?= number_format($realisasi,2,',','.') ?></td>
          <td><?= h($satE) ?></td>
          <td class="right"><?= number_format($delta,2,',','.') ?></td>
          <td class="right"><?= number_format($progress,2,',','.') ?></td>
          <td><?= h($ket) ?></td>
          <td><span class="badge <?= $cls ?>"><?= h($status) ?></span></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <?php if(!empty($rows)): ?>
      <tfoot>
        <tr>
          <td colspan="7" class="right">TOTAL</td>
          <td class="right"><?= number_format($tot_r,2,',','.') ?></td>
          <td></td>
          <td class="right"><?= number_format($tot_e,2,',','.') ?></td>
          <td></td>
          <td class="right"><?= number_format($tot_d,2,',','.') ?></td>
          <td class="right"><?= number_format($tot_p,2,',','.') ?></td>
          <td></td>
          <td></td>
        </tr>
      </tfoot>
    <?php endif; ?>
  </table>
</body>
</html>
<?php
$html=ob_get_clean();

$options=new Options();
$options->set('isRemoteEnabled',true);
$options->set('isHtml5ParserEnabled',true);

$dompdf=new Dompdf($options);
$dompdf->loadHtml($html,'UTF-8');
$dompdf->setPaper('A4','landscape');
$dompdf->render();
$dompdf->stream('Pemeliharaan_'.$tab.'.pdf',['Attachment'=>true]);
