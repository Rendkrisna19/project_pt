<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Forbidden'); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$jenis = trim($_GET['jenis'] ?? '');
$bulan = trim($_GET['bulan'] ?? '');
$tahun = (int)($_GET['tahun'] ?? date('Y'));

$db = new Database();
$conn = $db->getConnection();

// Query data
$sql = "SELECT nama_bahan, satuan, bulan, tahun, stok_awal, mutasi_masuk, mutasi_keluar, pasokan, dipakai
        FROM stok_gudang WHERE 1=1";
$bind = [];
if ($jenis !== '') { $sql .= " AND nama_bahan = :nb"; $bind[':nb'] = $jenis; }
if ($bulan !== '') { $sql .= " AND bulan = :bln";   $bind[':bln'] = $bulan; }
if ($tahun)        { $sql .= " AND tahun = :thn";   $bind[':thn'] = $tahun; }
$sql .= " ORDER BY nama_bahan ASC, FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), tahun DESC, id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($bind);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nowStr = date('d-m-Y H:i');
$judul  = "PTPN REGIONAL 3 — Stok Gudang (Tanggal: ".date('d-m-Y').")";

// HTML
ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($judul) ?></title>
<style>
  @page { margin: 10px; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #111; }
  .title {
    text-align: center; font-weight: bold; font-size: 14px; color: #fff;
    padding: 8px; background: #16a34a; border-radius: 4px; margin-bottom: 8px;
  }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border: 1px solid #555; padding: 4px; word-wrap: break-word; }
  th {
    background: #16a34a; color: #fff; font-weight: bold; text-align: center;
  }
  .right { text-align: right; }
  .center { text-align: center; }
  footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: right; font-size: 10px; color: #555; }
</style>
</head>
<body>
  <div class="title"><?= htmlspecialchars($judul) ?></div>
  <table>
    <thead>
      <tr>
        <th style="width:25px;">No</th>
        <th style="width:120px;">Nama Bahan</th>
        <th style="width:45px;">Satuan</th>
        <th style="width:55px;">Bulan</th>
        <th style="width:45px;">Tahun</th>
        <th style="width:65px;">Stok Awal</th>
        <th style="width:55px;">Masuk</th>
        <th style="width:55px;">Keluar</th>
        <th style="width:55px;">Pasokan</th>
        <th style="width:55px;">Dipakai</th>
        <th style="width:65px;">Sisa Stok</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="11" class="center">Tidak ada data.</td></tr>
    <?php else: $i=0;
      foreach ($rows as $r): $i++;
        $sa=(float)$r['stok_awal']; $mm=(float)$r['mutasi_masuk'];
        $mk=(float)$r['mutasi_keluar']; $ps=(float)$r['pasokan']; $dp=(float)$r['dipakai'];
        $sisa = $sa+$mm+$ps-$mk-$dp;
    ?>
      <tr>
        <td class="center"><?= $i ?></td>
        <td><?= htmlspecialchars($r['nama_bahan']) ?></td>
        <td class="center"><?= htmlspecialchars($r['satuan']) ?></td>
        <td class="center"><?= htmlspecialchars($r['bulan']) ?></td>
        <td class="center"><?= (int)$r['tahun'] ?></td>
        <td class="right"><?= number_format($sa,2) ?></td>
        <td class="right"><?= number_format($mm,2) ?></td>
        <td class="right"><?= number_format($mk,2) ?></td>
        <td class="right"><?= number_format($ps,2) ?></td>
        <td class="right"><?= number_format($dp,2) ?></td>
        <td class="right"><?= number_format($sisa,2) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <p style="font-size:10px; margin-top:5px;">Dicetak oleh sistem pada <?= $nowStr ?>.</p>
  <footer>Halaman {PAGE_NUM} / {PAGE_COUNT}</footer>
</body>
</html>
<?php
$html = ob_get_clean();

// PDF render
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // lebih lebar supaya muat
$dompdf->render();
$canvas = $dompdf->getCanvas();
$canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
  $text = "Halaman $pageNumber / $pageCount";
  $font = $fontMetrics->get_font("Helvetica", "normal");
  $canvas->text(520, 570, $text, $font, 9);
});
$dompdf->stream('StokGudang_'.date('Ymd_His').'.pdf', ['Attachment'=>0]);
exit;
