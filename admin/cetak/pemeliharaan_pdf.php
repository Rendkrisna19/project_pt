<?php
// pages/cetak/pemeliharaan_pdf.php
// Output: PDF daftar pemeliharaan berdasarkan ?tab=
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Mpdf\Mpdf;

$tab = $_GET['tab'] ?? 'TU';
$allowed = ['TU','TBM','TM','BIBIT_PN','BIBIT_MN'];
if (!in_array($tab, $allowed, true)) $tab = 'TU';

$db = new Database(); $pdo = $db->getConnection();

$sql = "SELECT p.*, u.nama_unit AS unit_nama
        FROM pemeliharaan p
        LEFT JOIN units u ON u.id=p.unit_id
        WHERE p.kategori=:k
        ORDER BY p.tanggal DESC, p.id DESC";
$st = $pdo->prepare($sql);
$st->execute([':k'=>$tab]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$mapJudul = [
  'TU'=>'Pemeliharaan TU','TBM'=>'Pemeliharaan TBM','TM'=>'Pemeliharaan TM',
  'BIBIT_PN'=>'Pemeliharaan Bibit PN','BIBIT_MN'=>'Pemeliharaan Bibit MN'
];
$judul = $mapJudul[$tab] ?? 'Pemeliharaan';

$css = <<<CSS
body{font-family: sans-serif; font-size:11px; color:#222}
.header{font-size:16px; font-weight:bold; text-align:center; margin-bottom:6px}
.sub{font-size:11px; text-align:center; margin-bottom:12px; color:#666}
table{width:100%; border-collapse:collapse}
th,td{border:1px solid #ddd; padding:6px}
th{background:#E2F5EA; font-weight:bold}
.right{text-align:right}
.badge{display:inline-block; padding:2px 6px; border-radius:8px; font-size:10px}
.bg-selesai{background:#DCFCE7; color:#065F46}
.bg-berjalan{background:#DBEAFE; color:#1E3A8A}
.bg-tertunda{background:#FEF3C7; color:#92400E}
CSS;

$rowsHtml = '';
$no = 1;
foreach ($rows as $row) {
  $rencana  = (float)($row['rencana'] ?? 0);
  $realisasi= (float)($row['realisasi'] ?? 0);
  $progress = $rencana > 0 ? ($realisasi/$rencana)*100 : 0;
  $periode  = trim(($row['bulan'] ?? '').' '.($row['tahun'] ?? ''));

  $cls = 'bg-berjalan';
  if ($row['status']==='Selesai') $cls='bg-selesai';
  elseif ($row['status']==='Tertunda') $cls='bg-tertunda';

  $rowsHtml .= '<tr>'.
      '<td class="right">'.($no++).'</td>'.
      '<td>'.htmlspecialchars($row['jenis_pekerjaan']).'</td>'.
      '<td>'.htmlspecialchars($row['unit_nama'] ?: $row['afdeling']).'</td>'.
      '<td>'.htmlspecialchars($row['rayon']).'</td>'.
      '<td>'.htmlspecialchars($periode).'</td>'.
      '<td class="right">'.number_format($rencana,2).'</td>'.
      '<td class="right">'.number_format($realisasi,2).'</td>'.
      '<td class="right">'.number_format($progress,2).'%</td>'.
      '<td><span class="badge '.$cls.'">'.htmlspecialchars($row['status']).'</span></td>'.
    '</tr>';
}

$html = <<<HTML
<html>
<head><meta charset="utf-8"><style>{$css}</style></head>
<body>
  <div class="header">{$judul}</div>
  <div class="sub">Dicetak: {date('d/m/Y H:i')}</div>

  <table>
    <thead>
      <tr>
        <th style="width:30px">No</th>
        <th>Jenis Pekerjaan</th>
        <th style="width:120px">Unit/Devisi</th>
        <th style="width:90px">Rayon</th>
        <th style="width:90px">Periode</th>
        <th style="width:85px">Rencana</th>
        <th style="width:85px">Realisasi</th>
        <th style="width:85px">Progress (%)</th>
        <th style="width:85px">Status</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>
</body>
</html>
HTML;

$mpdf = new Mpdf(['format'=>'A4-L']); // landscape
$mpdf->SetTitle($judul);
$mpdf->WriteHTML($html);
$mpdf->Output('Pemeliharaan_'.$tab.'_'.date('Ymd_His').'.pdf','I');
exit;
