<?php
// cetak/pemupukan_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  http_response_code(401);
  exit('Unauthorized');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Mpdf\Mpdf;

$printedBy = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'User';
$printedAt = date('d/m/Y H:i');

$tab = $_GET['tab'] ?? 'menabur';
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

$db = new Database();
$conn = $db->getConnection();

if ($tab === 'angkutan') {
  $title = 'Data Angkutan Pupuk Kimia';
  $sql = "SELECT a.tanggal, a.gudang_asal, u.nama_unit AS unit_tujuan, a.jenis_pupuk,
                 a.jumlah, a.nomor_do, a.supir
          FROM angkutan_pupuk a
          LEFT JOIN units u ON u.id = a.unit_tujuan_id
          ORDER BY a.tanggal DESC, a.id DESC";
  $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  $headers = ['Tanggal','Gudang Asal','Unit Tujuan','Jenis Pupuk','Jumlah (Kg)','Nomor DO','Supir'];
  $rowHtml = function($r){
    return sprintf(
      '<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="num">%s</td><td>%s</td><td>%s</td>',
      htmlspecialchars($r['tanggal']),
      htmlspecialchars($r['gudang_asal']),
      htmlspecialchars($r['unit_tujuan'] ?? '-'),
      htmlspecialchars($r['jenis_pupuk']),
      number_format((float)$r['jumlah'],2),
      htmlspecialchars($r['nomor_do']),
      htmlspecialchars($r['supir'])
    );
  };
} else {
  $title = 'Data Penaburan Pupuk Kimia';
  $sql = "SELECT m.tanggal, u.nama_unit AS unit, m.blok, m.jenis_pupuk,
                 m.jumlah, m.luas, m.invt_pokok, m.catatan
          FROM menabur_pupuk m
          LEFT JOIN units u ON u.id = m.unit_id
          ORDER BY m.tanggal DESC, m.id DESC";
  $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  $headers = ['Tanggal','Unit','Blok','Jenis Pupuk','Jumlah (Kg)','Luas (Ha)','Invt. Pokok','Catatan'];
  $rowHtml = function($r){
    return sprintf(
      '<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="num">%s</td><td class="num">%s</td><td class="numi">%s</td><td>%s</td>',
      htmlspecialchars($r['tanggal']),
      htmlspecialchars($r['unit'] ?? '-'),
      htmlspecialchars($r['blok']),
      htmlspecialchars($r['jenis_pupuk']),
      number_format((float)$r['jumlah'],2),
      number_format((float)$r['luas'],2),
      (int)$r['invt_pokok'],
      htmlspecialchars($r['catatan'])
    );
  };
}

$thead = '<tr>' . implode('', array_map(fn($h)=>'<th>'.$h.'</th>', $headers)) . '</tr>';
$tbody = '';
foreach ($rows as $r) $tbody .= '<tr>'.$rowHtml($r).'</tr>';

$html = '
<style>
  body{font-family: sans-serif; font-size: 10pt;}
  .bar{background:#16A34A;color:#fff;padding:8px 10px;font-size:14pt;font-weight:bold;text-align:center;}
  .subtitle{background:#BBF7D0;padding:6px 10px;font-weight:bold;text-align:center;}
  .meta{margin:6px 0 12px 0;}
  table{width:100%; border-collapse:collapse;}
  th,td{border:1px solid #666; padding:6px;}
  th{background:#efefef;}
  .num{text-align:right;}
  .numi{text-align:right;}
  tbody tr:nth-child(even){background:#fafafa;}
  footer{position:fixed;left:0;right:0;bottom:0;color:#777;font-size:8pt;text-align:right;padding:0 10px 5px 0;}
</style>

<div class="bar">PTPN 4 Regional 2</div>
<div class="subtitle">'.$title.'</div>
<div class="meta">
  Dicetak oleh: <b>'.htmlspecialchars($printedBy).'</b><br>
  Tanggal cetak: <b>'.$printedAt.'</b>
</div>
<table>
  <thead>'.$thead.'</thead>
  <tbody>'.$tbody.'</tbody>
</table>
<footer>Hal. {PAGENO} / {nbpg}</footer>
';

$mpdf = new Mpdf(['format'=>'A4-L','margin_top'=>35,'margin_bottom'=>15]);
$mpdf->SetTitle($title);
$mpdf->WriteHTML($html);
$mpdf->Output(($tab==='angkutan'?'angkutan':'menabur').'_pupuk_'.date('Ymd_His').'.pdf', 'I');
exit;
