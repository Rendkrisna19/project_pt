<?php
// cetak/permintaan_export_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Forbidden'); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Mpdf\Mpdf;

$db = new Database();
$conn = $db->getConnection();

// Ambil data
$sql = "SELECT p.*, u.nama_unit
        FROM permintaan_bahan p
        JOIN units u ON u.id = p.unit_id
        ORDER BY p.tanggal DESC, p.id DESC";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$username = $_SESSION['username'] ?? 'Unknown User';
$now = date('d/m/Y H:i');
$company = 'PTPN 4 REGIONAL 2';
$title = 'Pengajuan AU-58 (Permintaan Bahan)';

$html = '<!doctype html>
<html><head><meta charset="utf-8"><style>
  body{font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111;}
  .title {text-align:center; font-weight:bold; font-size:14px; color:#fff; padding:8px; background:#2E7D32; border-radius:4px;}
  .sub   {text-align:center; font-weight:bold; font-size:12px; color:#fff; padding:6px; background:#388E3C; border-radius:4px; margin:6px 0 10px;}
  table{width:100%; border-collapse:collapse; table-layout:fixed; margin-top:6px;}
  th,td{border:1px solid #555; padding:5px; word-wrap:break-word;}
  th{background:#2E7D32; color:#fff; text-align:center;}
  .center{text-align:center;} .right{text-align:right;}
  .meta{font-size:10px; text-align:right; margin-top:6px; color:#555;}
</style></head><body>';

$html .= '<div class="title">'.$company.'</div>';
$html .= '<div class="sub">'.$title.'</div>';
$html .= '<div class="meta">Dicetak oleh: '.htmlspecialchars($username).' pada '.$now.'</div>';

$html .= '<table><thead><tr>
  <th style="width:28px;">No</th>
  <th style="width:110px;">No. Dokumen</th>
  <th style="width:110px;">Unit/Devisi</th>
  <th style="width:85px;">Tanggal</th>
  <th style="width:120px;">Blok</th>
  <th style="width:70px;">Pokok</th>
  <th style="width:110px;">Dosis/Norma</th>
  <th style="width:100px;">Jumlah Diminta</th>
  <th style="width:160px;">Keterangan</th>
</tr></thead><tbody>';

if (!$rows) {
  $html .= '<tr><td class="center" colspan="9">Tidak ada data.</td></tr>';
} else {
  $i=0;
  foreach ($rows as $r) {
    $i++;
    $html .= '<tr>
      <td class="center">'.$i.'</td>
      <td>'.htmlspecialchars($r['no_dokumen'] ?? '').'</td>
      <td>'.htmlspecialchars($r['nama_unit'] ?? '').'</td>
      <td class="center">'.htmlspecialchars($r['tanggal'] ?? '').'</td>
      <td>'.htmlspecialchars($r['blok'] ?? '').'</td>
      <td class="right">'.htmlspecialchars($r['pokok'] ?? '').'</td>
      <td>'.htmlspecialchars($r['dosis_norma'] ?? '').'</td>
      <td class="right">'.number_format((float)$r['jumlah_diminta'],2).'</td>
      <td>'.htmlspecialchars($r['keterangan'] ?? '').'</td>
    </tr>';
  }
}
$html .= '</tbody></table>';
$html .= '</body></html>';

$mpdf = new Mpdf(['format' => 'A4-L']); // Landscape
$mpdf->SetTitle('Permintaan_AU58_'.date('Ymd_His'));
$mpdf->SetFooter('Halaman {PAGENO} / {nb}');
$mpdf->WriteHTML($html);
$mpdf->Output('Permintaan_AU58_'.date('Ymd_His').'.pdf', \Mpdf\Output\Destination::INLINE);
exit;
