<?php
// admin/cetak/alat_panen_export_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Forbidden'); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ====== Filter
$unit_id = (int)($_GET['unit_id'] ?? 0);
$bulan   = trim($_GET['bulan'] ?? '');
$tahun   = (int)($_GET['tahun'] ?? 0);

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT a.*, u.nama_unit
        FROM alat_panen a
        JOIN units u ON a.unit_id=u.id
        WHERE 1=1";
$bind = [];
if ($unit_id > 0) { $sql .= " AND a.unit_id=:uid"; $bind[':uid']=$unit_id; }
if ($bulan !== '') { $sql .= " AND a.bulan=:bln";  $bind[':bln']=$bulan; }
if ($tahun > 0)    { $sql .= " AND a.tahun=:thn";  $bind[':thn']=$tahun; }

$sql .= " ORDER BY a.tahun DESC,
          FIELD(a.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
          u.nama_unit ASC, a.jenis_alat ASC";

$st = $conn->prepare($sql);
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Info
$username = $_SESSION['username'] ?? 'Unknown User';
$nowStr   = date('d-m-Y H:i');
$judul    = "PTPN 4 REGIONAL 2 — Laporan Alat Panen";

ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($judul) ?></title>
<style>
  @page { margin: 14px; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
  .box-title {
    background:#16a34a; color:#fff; padding:10px; border-radius:6px; text-align:center;
    font-weight:bold; font-size:14px; margin-bottom:8px;
  }
  .meta { font-size:10px; margin-bottom:6px; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; }
  th, td { border:1px solid #555; padding:4px; word-wrap:break-word; }
  th { background:#16a34a; color:#fff; text-align:center; }
  .center { text-align:center; } .right { text-align:right; }
  tfoot td { font-weight:bold; background:#e8f5e9; }
  footer { position: fixed; bottom: -8px; left: 0; right: 0; text-align: right; font-size: 10px; color: #555; }
</style>
</head>
<body>
  <div class="box-title"><?= htmlspecialchars($judul) ?></div>
  <div class="meta">
    Dicetak oleh: <?= htmlspecialchars($username) ?> — <?= htmlspecialchars($nowStr) ?><br>
    Filter:
    <?php
      $flt = [];
      if ($unit_id > 0) {
        $nm = $conn->prepare("SELECT nama_unit FROM units WHERE id=:id"); $nm->execute([':id'=>$unit_id]);
        $flt[] = 'Unit: '.htmlspecialchars($nm->fetchColumn() ?: '-');
      }
      if ($bulan!=='') $flt[] = 'Bulan: '.htmlspecialchars($bulan);
      if ($tahun>0)    $flt[] = 'Tahun: '.htmlspecialchars((string)$tahun);
      echo $flt ? implode(' — ', $flt) : 'Semua Unit / Periode';
    ?>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:70px;">Periode</th>
        <th style="width:110px;">Unit/Devisi</th>
        <th style="width:120px;">Jenis Alat Panen</th>
        <th style="width:70px;">Stok Awal</th>
        <th style="width:80px;">Mutasi Masuk</th>
        <th style="width:80px;">Mutasi Keluar</th>
        <th style="width:65px;">Dipakai</th>
        <th style="width:70px;">Stok Akhir</th>
        <th style="width:120px;">Krani Afdeling</th>
        <th>Catatan</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td class="center" colspan="10">Tidak ada data.</td></tr>
      <?php else:
        $tsa=0; $tmi=0; $tmk=0; $tdp=0; $tak=0;
        foreach ($rows as $d):
          $tsa += (float)$d['stok_awal'];
          $tmi += (float)$d['mutasi_masuk'];
          $tmk += (float)$d['mutasi_keluar'];
          $tdp += (float)$d['dipakai'];
          $tak += (float)$d['stok_akhir'];
      ?>
        <tr>
          <td class="center"><?= htmlspecialchars(($d['bulan']??'').' '.($d['tahun']??'')) ?></td>
          <td><?= htmlspecialchars($d['nama_unit']) ?></td>
          <td><?= htmlspecialchars($d['jenis_alat']) ?></td>
          <td class="right"><?= number_format((float)$d['stok_awal'], 2) ?></td>
          <td class="right"><?= number_format((float)$d['mutasi_masuk'], 2) ?></td>
          <td class="right"><?= number_format((float)$d['mutasi_keluar'], 2) ?></td>
          <td class="right"><?= number_format((float)$d['dipakai'], 2) ?></td>
          <td class="right"><?= number_format((float)$d['stok_akhir'], 2) ?></td>
          <td><?= htmlspecialchars($d['krani_afdeling'] ?? '-') ?></td>
          <td><?= htmlspecialchars($d['catatan'] ?? '-') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <?php if ($rows): ?>
    <tfoot>
      <tr>
        <td colspan="3" class="right">TOTAL</td>
        <td class="right"><?= number_format($tsa,2) ?></td>
        <td class="right"><?= number_format($tmi,2) ?></td>
        <td class="right"><?= number_format($tmk,2) ?></td>
        <td class="right"><?= number_format($tdp,2) ?></td>
        <td class="right"><?= number_format($tak,2) ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>

  <footer>Halaman {PAGE_NUM} / {PAGE_COUNT}</footer>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // tabel melebar
$dompdf->render();
$dompdf->stream('Alat_Panen_'.date('Ymd_His').'.pdf', ['Attachment' => 0]);
exit;
