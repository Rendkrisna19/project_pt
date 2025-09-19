<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  die("Akses ditolak");
}

require_once '../../config/database.php';
require '../../vendor/autoload.php';

use Mpdf\Mpdf;

$tab = $_GET['tab'] ?? 'menabur';
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

$db = new Database();
$conn = $db->getConnection();

$username = $_SESSION['username'] ?? 'Unknown User';
$tanggalCetak = date('d/m/Y H:i');

if ($tab === 'angkutan') {
  $stmt = $conn->query("
    SELECT a.*, u.nama_unit AS unit_tujuan_nama
    FROM angkutan_pupuk_organik a
    LEFT JOIN units u ON u.id = a.unit_tujuan_id
    ORDER BY a.tanggal DESC, a.id DESC
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $title = "Data Angkutan Pupuk Organik";
  $headers = ['Gudang Asal','Unit Tujuan','Tanggal','Jenis Pupuk','Jumlah (Kg)','Nomor DO','Supir'];
} else {
  $stmt = $conn->query("
    SELECT m.*, u.nama_unit AS unit_nama
    FROM menabur_pupuk_organik m
    LEFT JOIN units u ON u.id = m.unit_id
    ORDER BY m.tanggal DESC, m.id DESC
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $title = "Data Penaburan Pupuk Organik";
  $headers = ['Unit','Blok','Tanggal','Jenis Pupuk','Jumlah (Kg)','Luas (Ha)','Invt. Pokok','Catatan'];
}

$html = "
<h3 style='text-align:center;'>PTPN 4 REGIONAL 2</h3>
<h4 style='text-align:center;'>$title</h4>
<p style='text-align:right; font-size:11px;'><i>Dicetak oleh: $username pada $tanggalCetak</i></p>
<table border='1' cellspacing='0' cellpadding='5' width='100%'>
  <tr style='background-color:#2E7D32; color:#fff; font-weight:bold;'>";
foreach ($headers as $h) {
  $html .= "<th>$h</th>";
}
$html .= "</tr>";

foreach ($rows as $d) {
  $html .= "<tr>";
  if ($tab==='angkutan') {
    $html .= "<td>{$d['gudang_asal']}</td>
              <td>{$d['unit_tujuan_nama']}</td>
              <td>{$d['tanggal']}</td>
              <td>{$d['jenis_pupuk']}</td>
              <td>{$d['jumlah']}</td>
              <td>{$d['nomor_do']}</td>
              <td>{$d['supir']}</td>";
  } else {
    $html .= "<td>{$d['unit_nama']}</td>
              <td>{$d['blok']}</td>
              <td>{$d['tanggal']}</td>
              <td>{$d['jenis_pupuk']}</td>
              <td>{$d['jumlah']}</td>
              <td>{$d['luas']}</td>
              <td>{$d['invt_pokok']}</td>
              <td>{$d['catatan']}</td>";
  }
  $html .= "</tr>";
}

$html .= "</table>";

$mpdf = new Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output("pemupukan_organik_$tab.pdf", "I");
exit;
