<?php
// pages/cetak/pemupukan_pdf.php
// Cetak PDF Pemupukan (Menabur/Angkutan) – header hijau, hormati filter
// Kolom baru (Menabur): TAHUN, APL; T.TANAM dari md_tahun_tanam

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function qstr($v){ return trim((string)$v); }
function qintOrEmpty($v){ return ($v===''||$v===null) ? '' : (int)$v; }

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // ===== Filters
  $tab        = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';
  $f_unit_id  = qintOrEmpty($_GET['unit_id']     ?? '');
  $f_kebun_id = qintOrEmpty($_GET['kebun_id']    ?? '');
  $f_tanggal  = qstr($_GET['tanggal']            ?? '');
  $f_bulan    = qstr($_GET['bulan']              ?? '');
  $f_jenis    = qstr($_GET['jenis_pupuk']        ?? '');

  // ===== Helper deteksi kolom
  $cacheCols=[];
  $columnExists=function(PDO $c,$table,$col)use(&$cacheCols){
    if(!isset($cacheCols[$table])){
      $st=$c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t'=>$table]);
      $cacheCols[$table]=array_map('strtolower',array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME'));
    }
    return in_array(strtolower($col),$cacheCols[$table]??[],true);
  };

  // Kebun availability
  $hasKebunMenaburId  = $columnExists($pdo,'menabur_pupuk','kebun_id');
  $hasKebunMenaburKod = $columnExists($pdo,'menabur_pupuk','kebun_kode');
  $hasKebunAngkutId   = $columnExists($pdo,'angkutan_pupuk','kebun_id');
  $hasKebunAngkutKod  = $columnExists($pdo,'angkutan_pupuk','kebun_kode');

  // Tahun & APL availability (Menabur)
  $hasTahunMenabur    = $columnExists($pdo,'menabur_pupuk','tahun');
  $hasTTId            = $columnExists($pdo,'menabur_pupuk','tahun_tanam_id');
  $hasTTAngka         = $columnExists($pdo,'menabur_pupuk','tahun_tanam');
  $aplCol = null; foreach(['apl','aplikator'] as $c){ if($columnExists($pdo,'menabur_pupuk',$c)){ $aplCol=$c; break; } }

  // Map kebun id->kode untuk filter bila transaksi pakai kode
  $kebuns=$pdo->query("SELECT id,kode,nama_kebun FROM md_kebun")->fetchAll(PDO::FETCH_ASSOC);
  $idToKode=[]; foreach($kebuns as $k){ $idToKode[(int)$k['id']]=$k['kode']; }

  if ($tab==='angkutan'){
    $title="Data Angkutan Pupuk Kimia";
    $selectKebun=''; $joinKebun='';
    if($hasKebunAngkutId){  $selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.id=a.kebun_id "; }
    elseif($hasKebunAngkutKod){ $selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.kode=a.kebun_kode "; }

    $where=" WHERE 1=1"; $p=[];
    if($f_unit_id!==''){ $where.=" AND a.unit_tujuan_id=:uid"; $p[':uid']=(int)$f_unit_id; }
    if($f_kebun_id!==''){
      if($hasKebunAngkutId){ $where.=" AND a.kebun_id=:kid"; $p[':kid']=(int)$f_kebun_id; }
      elseif($hasKebunAngkutKod){ $where.=" AND a.kebun_kode=:kkod"; $p[':kkod']=(string)($idToKode[(int)$f_kebun_id]??''); }
    }
    if($f_tanggal!==''){ $where.=" AND a.tanggal=:tgl"; $p[':tgl']=$f_tanggal; }
    if($f_bulan!=='' && ctype_digit($f_bulan)){ $where.=" AND MONTH(a.tanggal)=:bln"; $p[':bln']=(int)$f_bulan; }
    if($f_jenis!==''){ $where.=" AND a.jenis_pupuk=:jp"; $p[':jp']=$f_jenis; }

    $sql="SELECT a.*, u.nama_unit AS unit_tujuan_nama $selectKebun
          FROM angkutan_pupuk a
          LEFT JOIN units u ON u.id=a.unit_tujuan_id
          $joinKebun
          $where
          ORDER BY a.tanggal DESC, a.id DESC";
    $st=$pdo->prepare($sql);
    foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
    $st->execute();
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);

    $tot_kg=0.0; foreach($rows as $r){ $tot_kg+=(float)($r['jumlah']??0); }

  } else {
    $title="Data Penaburan Pupuk Kimia";

    $selectKebun=''; $joinKebun='';
    if($hasKebunMenaburId){  $selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.id=m.kebun_id "; }
    elseif($hasKebunMenaburKod){ $selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.kode=m.kebun_kode "; }

    // Tahun tanam & tahun input & APL
    $selectTT = ", COALESCE(tt.tahun, ".($hasTTAngka ? "m.tahun_tanam" : "NULL").") AS t_tanam";
    $joinTT   = "";
    if ($hasTTId)       $joinTT = " LEFT JOIN md_tahun_tanam tt ON tt.id = m.tahun_tanam_id ";
    elseif ($hasTTAngka) $joinTT = " LEFT JOIN md_tahun_tanam tt ON tt.tahun = m.tahun_tanam ";

    $selectTahun = $hasTahunMenabur ? ", m.tahun AS tahun_input" : ", YEAR(m.tanggal) AS tahun_input";
    $selectAPL   = $aplCol ? ", m.`$aplCol` AS apl" : ", NULL AS apl";

    $where=" WHERE 1=1"; $p=[];
    if($f_unit_id!==''){ $where.=" AND m.unit_id=:uid"; $p[':uid']=(int)$f_unit_id; }
    if($f_kebun_id!==''){
      if($hasKebunMenaburId){ $where.=" AND m.kebun_id=:kid"; $p[':kid']=(int)$f_kebun_id; }
      elseif($hasKebunMenaburKod){ $where.=" AND m.kebun_kode=:kkod"; $p[':kkod']=(string)($idToKode[(int)$f_kebun_id]??''); }
    }
    if($f_tanggal!==''){ $where.=" AND m.tanggal=:tgl"; $p[':tgl']=$f_tanggal; }
    if($f_bulan!=='' && ctype_digit($f_bulan)){ $where.=" AND MONTH(m.tanggal)=:bln"; $p[':bln']=(int)$f_bulan; }
    if($f_jenis!==''){ $where.=" AND m.jenis_pupuk=:jp"; $p[':jp']=$f_jenis; }

    $sql="SELECT m.*, u.nama_unit AS unit_nama
                 $selectKebun
                 $selectTT
                 $selectTahun
                 $selectAPL
          FROM menabur_pupuk m
          LEFT JOIN units u ON u.id=m.unit_id
          $joinKebun
          $joinTT
          $where
          ORDER BY m.tanggal DESC, m.id DESC";
    $st=$pdo->prepare($sql);
    foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
    $st->execute();
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);

    $tot_kg=0.0; $tot_luas=0.0; $tot_invt=0.0; $sum_dosis=0.0; $cnt_dosis=0;
    foreach($rows as $r){
      $tot_kg   += (float)($r['jumlah'] ?? 0);
      $tot_luas += (float)($r['luas'] ?? 0);
      $tot_invt += (float)($r['invt_pokok'] ?? 0);
      if(isset($r['dosis']) && $r['dosis']!=='' && (float)$r['dosis']!=0){ $sum_dosis+=(float)$r['dosis']; $cnt_dosis++; }
    }
    $avg_dosis = $cnt_dosis ? $sum_dosis/$cnt_dosis : 0.0;
  }

} catch(Throwable $e){
  http_response_code(500); exit("DB Error: ".$e->getMessage());
}

// ===== HTML
ob_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin:20mm 15mm; size:A4 landscape; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size:11px; color:#111; }
  .brand{background:#22c55e;color:#fff;padding:12px 16px;border-radius:8px;text-align:center;margin-bottom:10px;}
  .brand h1{margin:0;font-size:18px;letter-spacing:.5px}
  .subtitle{text-align:center;margin:4px 0 12px;font-weight:700;font-size:13px;color:#065f46}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid #e5e7eb;padding:6px 8px}
  thead th{background:#ecfdf5;color:#065f46;font-weight:700}
  tbody tr:nth-child(even) td{background:#f8fafc}
  tfoot td{background:#f1faf6;font-weight:700}
  .text-right{text-align:right}
</style>
</head>
<body>
  <div class="brand"><h1>PTPN 4 REGIONAL 3</h1></div>
  <div class="subtitle"><?= htmlspecialchars($title) ?></div>

  <table>
    <thead>
      <tr>
        <?php if($tab==='angkutan'): ?>
          <th>Kebun</th><th>Gudang Asal</th><th>Unit Tujuan</th><th>Tanggal</th>
          <th>Jenis Pupuk</th><th class="text-right">Jumlah (Kg)</th><th>Nomor DO</th><th>Supir</th>
        <?php else: ?>
          <th>Tahun</th><th>Kebun</th><th>Unit</th><th>T.TANAM</th><th>Blok</th>
          <th>Tanggal</th><th>Jenis Pupuk</th><th>APL</th>
          <th class="text-right">Dosis (kg/ha)</th><th class="text-right">Jumlah (Kg)</th>
          <th class="text-right">Luas (Ha)</th><th class="text-right">Invt. Pokok</th><th>Catatan</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if(empty($rows)): ?>
        <tr><td colspan="<?= $tab==='angkutan'?8:13 ?>">Belum ada data.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <?php if($tab==='angkutan'): ?>
          <tr>
            <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
            <td><?= htmlspecialchars($r['gudang_asal'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['tanggal'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['jenis_pupuk'] ?? '') ?></td>
            <td class="text-right"><?= number_format((float)($r['jumlah'] ?? 0),2) ?></td>
            <td><?= htmlspecialchars($r['nomor_do'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['supir'] ?? '') ?></td>
          </tr>
        <?php else:
          $tahunRow = $r['tahun_input'] ?? '';
        ?>
          <tr>
            <td><?= htmlspecialchars($tahunRow) ?></td>
            <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
            <td><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['t_tanam'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['blok'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['tanggal'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['jenis_pupuk'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['apl'] ?? '-') ?></td>
            <td class="text-right"><?= ($r['dosis']!==null && $r['dosis']!=='')?number_format((float)$r['dosis'],2):'-' ?></td>
            <td class="text-right"><?= number_format((float)($r['jumlah'] ?? 0),2) ?></td>
            <td class="text-right"><?= number_format((float)($r['luas'] ?? 0),2) ?></td>
            <td class="text-right"><?= number_format((float)($r['invt_pokok'] ?? 0),0) ?></td>
            <td><?= htmlspecialchars($r['catatan'] ?? '') ?></td>
          </tr>
        <?php endif; endforeach; endif; ?>
    </tbody>
    <?php if(!empty($rows)): ?>
      <tfoot>
        <?php if($tab==='angkutan'): ?>
          <tr><td colspan="5" class="text-right">TOTAL</td><td class="text-right"><?= number_format($tot_kg,2) ?></td><td></td><td></td></tr>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-right">TOTAL</td>
            <td class="text-right"><?= number_format($avg_dosis ?? 0,2) ?></td>
            <td class="text-right"><?= number_format($tot_kg,2) ?></td>
            <td class="text-right"><?= number_format($tot_luas,2) ?></td>
            <td class="text-right"><?= number_format($tot_invt,0) ?></td>
            <td></td>
          </tr>
        <?php endif; ?>
      </tfoot>
    <?php endif; ?>
  </table>
</body>
</html>
<?php
$html=ob_get_clean();
$options=new Options(); $options->set('isRemoteEnabled',true);
$pdf=new Dompdf($options); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4','landscape'); $pdf->render();
$pdf->stream(($tab==='angkutan'?'angkutan':'menabur').'_pupuk_'.date('Ymd_His').'.pdf',['Attachment'=>false]); exit;
