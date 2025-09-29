<?php
// pages/cetak/alat_panen_export_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use Mpdf\Mpdf;

// === DB
$db = new Database(); $pdo = $db->getConnection();

// === helper: cek kolom
function col_exists(PDO $pdo, $table, $col){
  $st=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

// === Ambil filter
$kebun_id = isset($_GET['kebun_id']) && ctype_digit((string)$_GET['kebun_id']) ? (int)$_GET['kebun_id'] : null;
$unit_id  = isset($_GET['unit_id'])  && ctype_digit((string)$_GET['unit_id'])  ? (int)$_GET['unit_id']  : null;
$bulan    = trim($_GET['bulan'] ?? '');
$tahun    = isset($_GET['tahun']) && ctype_digit((string)$_GET['tahun']) ? (int)$_GET['tahun'] : null;
$blok_f   = trim($_GET['blok'] ?? ''); // bila filter by text blok
$tt_f     = trim($_GET['tt']   ?? ''); // bila filter by text tahun tanam

// === deteksi kolom di tabel alat_panen
$hasBlokId = col_exists($pdo,'alat_panen','blok_id');
$hasBlokTx = col_exists($pdo,'alat_panen','blok');
$hasTtId   = col_exists($pdo,'alat_panen','tt_id');
$hasTtTx   = col_exists($pdo,'alat_panen','tt');

// === Build JOIN & SELECT dinamis untuk BLOK / T.T
$selectParts = [
  "ap.*",
  "k.nama_kebun",
  "u.nama_unit"
];
$joins = [
  "LEFT JOIN md_kebun k ON k.id = ap.kebun_id",
  "LEFT JOIN units u ON u.id = ap.unit_id"
];

if ($hasBlokId) {
  $selectParts[] = "mb.kode_blok AS blok_nama";
  $joins[] = "LEFT JOIN md_blok mb ON mb.id = ap.blok_id";
} elseif ($hasBlokTx) {
  $selectParts[] = "ap.blok AS blok_nama";
} else {
  $selectParts[] = "NULL AS blok_nama";
}

if ($hasTtId) {
  $selectParts[] = "tt.tahun_tanam AS tt_nama";
  $joins[] = "LEFT JOIN md_tahun_tanam tt ON tt.id = ap.tt_id";
} elseif ($hasTtTx) {
  $selectParts[] = "ap.tt AS tt_nama";
} else {
  $selectParts[] = "NULL AS tt_nama";
}

$where = ["1=1"];
$params = [];

if ($kebun_id) { $where[]="ap.kebun_id = :kebun_id"; $params[':kebun_id']=$kebun_id; }
if ($unit_id)  { $where[]="ap.unit_id  = :unit_id";  $params[':unit_id']=$unit_id; }
if ($bulan!==''){ $where[]="ap.bulan    = :bulan";    $params[':bulan']=$bulan; }
if ($tahun)    { $where[]="ap.tahun    = :tahun";    $params[':tahun']=$tahun; }
if ($blok_f!==''){
  if ($hasBlokId) { // izinkan filter numeric id
    if (ctype_digit($blok_f)) { $where[]="ap.blok_id = :blok_id"; $params[':blok_id']=(int)$blok_f; }
    else { $where[]="mb.kode_blok = :blok_tx"; $params[':blok_tx']=$blok_f; }
  } elseif ($hasBlokTx) {
    $where[]="ap.blok = :blok_tx"; $params[':blok_tx']=$blok_f;
  }
}
if ($tt_f!==''){
  if ($hasTtId) {
    if (ctype_digit($tt_f)) { $where[]="ap.tt_id = :tt_id"; $params[':tt_id']=(int)$tt_f; }
    else { $where[]="tt.tahun_tanam = :tt_tx"; $params[':tt_tx']=$tt_f; }
  } elseif ($hasTtTx) {
    $where[]="ap.tt = :tt_tx"; $params[':tt_tx']=$tt_f;
  }
}

$sql = "SELECT ".implode(', ',$selectParts)."
        FROM alat_panen ap
        ".implode("\n", $joins)."
        WHERE ".implode(' AND ', $where)."
        ORDER BY ap.tahun DESC,
                 FIELD(ap.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
                 ap.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// === Tema hijau + judul
$judul = 'PTPN 4 REGIONAL 3';
$css = <<<CSS
body{font-family: sans-serif; font-size:11px; color:#1f2937}
.header{font-size:18px; font-weight:bold; text-align:center; margin-bottom:6px; color:#065F46}
.sub{font-size:11px; text-align:center; margin-bottom:12px; color:#059669}
table{width:100%; border-collapse:collapse}
th,td{border:1px solid #e5e7eb; padding:6px}
th{background:#DCFCE7; color:#065F46; font-weight:bold}
.right{text-align:right}
CSS;

// sub filter line (tanpa nama & tanggal cetak)
$subs = [];
if ($kebun_id) $subs[]='KebunID: '.$kebun_id;
if ($unit_id)  $subs[]='UnitID: '.$unit_id;
if ($bulan!=='')$subs[]='Bulan: '.$bulan;
if ($tahun)    $subs[]='Tahun: '.$tahun;
if ($blok_f!=='') $subs[]='Blok: '.$blok_f;
if ($tt_f!=='')   $subs[]='T.T: '.$tt_f;
$subHtml = $subs ? '<div class="sub">'.htmlspecialchars(implode(' • ', $subs)).'</div>' : '';

$rowsHtml='';
$no=1;
foreach($rows as $r){
  $rowsHtml.='<tr>'.
    '<td class="right">'.($no++).'</td>'.
    '<td>'.htmlspecialchars(($r['bulan']??'')." ".($r['tahun']??'')).'</td>'.
    '<td>'.htmlspecialchars($r['nama_kebun']??'-').'</td>'.
    '<td>'.htmlspecialchars($r['nama_unit']??'-').'</td>'.
    '<td>'.htmlspecialchars($r['blok_nama']??'-').'</td>'.
    '<td>'.htmlspecialchars($r['tt_nama']??'-').'</td>'.
    '<td>'.htmlspecialchars($r['jenis_alat']??'-').'</td>'.
    '<td class="right">'.number_format((float)($r['stok_awal']??0),2).'</td>'.
    '<td class="right">'.number_format((float)($r['mutasi_masuk']??0),2).'</td>'.
    '<td class="right">'.number_format((float)($r['mutasi_keluar']??0),2).'</td>'.
    '<td class="right">'.number_format((float)($r['dipakai']??0),2).'</td>'.
    '<td class="right">'.number_format((float)($r['stok_akhir']??0),2).'</td>'.
    '<td>'.htmlspecialchars($r['krani_afdeling']??'-').'</td>'.
    '<td>'.htmlspecialchars($r['catatan']??'-').'</td>'.
  '</tr>';
}

$html = <<<HTML
<html>
<head><meta charset="utf-8"><style>{$css}</style></head>
<body>
  <div class="header">{$judul}</div>
  {$subHtml}
  <table>
    <thead>
      <tr>
        <th style="width:30px">No</th>
        <th style="width:90px">Periode</th>
        <th style="width:140px">Kebun</th>
        <th style="width:130px">Unit/Devisi</th>
        <th style="width:70px">Blok</th>
        <th style="width:60px">T.T</th>
        <th>Jenis Alat</th>
        <th style="width:85px">Stok Awal</th>
        <th style="width:95px">Mutasi Masuk</th>
        <th style="width:95px">Mutasi Keluar</th>
        <th style="width:75px">Dipakai</th>
        <th style="width:85px">Stok Akhir</th>
        <th style="width:120px">Krani Afdeling</th>
        <th style="width:150px">Catatan</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>
</body>
</html>
HTML;

$mpdf = new Mpdf(['format'=>'A4-L']);
$mpdf->SetTitle($judul);
$mpdf->WriteHTML($html);
$mpdf->Output('Alat_Panen_'.date('Ymd_His').'.pdf','I');
exit;
