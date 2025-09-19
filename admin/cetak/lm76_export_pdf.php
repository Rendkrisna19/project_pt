<?php
// cetak/lm76_export_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Forbidden'); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use Mpdf\Mpdf;

// filters
$unit_id = trim($_GET['unit_id'] ?? '');
$bulan   = trim($_GET['bulan'] ?? '');
$tahun   = trim($_GET['tahun'] ?? '');

$db = new Database();
$conn = $db->getConnection();

$w = []; $p = [];
if ($unit_id !== '') { $w[] = 'l.unit_id = :u'; $p[':u'] = $unit_id; }
if ($bulan   !== '') { $w[] = 'l.bulan   = :b'; $p[':b'] = $bulan; }
if ($tahun   !== '') { $w[] = 'l.tahun   = :t'; $p[':t'] = (int)$tahun; }

$sql = "SELECT l.*, u.nama_unit
        FROM lm76 l
        JOIN units u ON u.id = l.unit_id
        ".(count($w) ? ' WHERE '.implode(' AND ',$w) : '')."
        ORDER BY l.tahun DESC,
                 FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
                 u.nama_unit ASC, l.blok ASC";
$st = $conn->prepare($sql); $st->execute($p);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$username = $_SESSION['username'] ?? 'Unknown User';
$now = date('d/m/Y H:i');
$company = 'PTPN 4 REGIONAL 2';
$title   = 'LM-76 — Statistik Panen Kelapa Sawit';

// HTML
ob_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  body{font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 10px; color:#111;}
  .title{ text-align:center; font-weight:bold; font-size:14px; color:#fff; padding:8px; background:#2E7D32; border-radius:4px; }
  .sub  { text-align:center; font-weight:bold; font-size:12px; color:#fff; padding:6px; background:#388E3C; border-radius:4px; margin:6px 0 10px; }
  .meta { font-size:10px; text-align:right; margin-bottom:6px; color:#555; }
  table{ width:100%; border-collapse:collapse; table-layout:fixed; }
  th,td{ border:1px solid #555; padding:3px; word-wrap:break-word; }
  th{ background:#2E7D32; color:#fff; text-align:center; }
  .center{text-align:center;} .right{text-align:right;}
</style>
</head>
<body>
  <div class="title"><?= htmlspecialchars($company) ?></div>
  <div class="sub"><?= htmlspecialchars($title) ?></div>
  <div class="meta">Dicetak oleh: <?= htmlspecialchars($username) ?> pada <?= $now ?></div>

  <table>
    <thead>
      <tr>
        <th style="width:24px;">No</th>
        <th style="width:100px;">Unit</th>
        <th style="width:50px;">Bulan</th>
        <th style="width:42px;">Tahun</th>
        <th style="width:45px;">T.T</th>
        <th style="width:90px;">Blok</th>
        <th style="width:55px;">Luas</th>
        <th style="width:60px;">Jml Pohon</th>
        <th style="width:70px;">Varietas</th>
        <th style="width:55px;">Prod BI R</th>
        <th style="width:55px;">Prod BI A</th>
        <th style="width:55px;">Prod SD R</th>
        <th style="width:55px;">Prod SD A</th>
        <th style="width:60px;">Jml Tandan BI</th>
        <th style="width:55px;">PSTB BI</th>
        <th style="width:55px;">PSTB TL</th>
        <th style="width:58px;">Panen HK R</th>
        <th style="width:58px;">Panen Ha BI</th>
        <th style="width:58px;">Panen Ha SD</th>
        <th style="width:45px;">Freq BI</th>
        <th style="width:45px;">Freq SD</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td class="center" colspan="21">Tidak ada data.</td></tr>
      <?php else: $i=0; foreach($rows as $r): $i++; ?>
        <tr>
          <td class="center"><?= $i ?></td>
          <td><?= htmlspecialchars($r['nama_unit'] ?? '') ?></td>
          <td class="center"><?= htmlspecialchars($r['bulan'] ?? '') ?></td>
          <td class="center"><?= htmlspecialchars($r['tahun'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['tt'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['blok'] ?? '') ?></td>
          <td class="right"><?= number_format((float)($r['luas_ha'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((int)($r['jumlah_pohon'] ?? 0)) ?></td>
          <td><?= htmlspecialchars($r['varietas'] ?? '') ?></td>
          <td class="right"><?= number_format((float)($r['prod_bi_realisasi'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((float)($r['prod_bi_anggaran'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((float)($r['prod_sd_realisasi'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((float)($r['prod_sd_anggaran'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((int)($r['jumlah_tandan_bi'] ?? 0)) ?></td>
          <td class="right"><?= number_format((float)($r['pstb_ton_ha_bi'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((float)($r['pstb_ton_ha_tl'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((float)($r['panen_hk_realisasi'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((float)($r['panen_ha_bi'] ?? 0),2) ?></td>
          <td class="right"><?= number_format((float)($r['panen_ha_sd'] ?? 0),2) ?></td>
          <td class="center"><?= (int)($r['frek_panen_bi'] ?? 0) ?></td>
          <td class="center"><?= (int)($r['frek_panen_sd'] ?? 0) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new Mpdf(['format' => 'A4-L']); // landscape
$mpdf->SetTitle('LM76_'.date('Ymd_His'));
$mpdf->SetFooter('Halaman {PAGENO} / {nb}');
$mpdf->WriteHTML($html);
$mpdf->Output('LM76_'.date('Ymd_His').'.pdf', \Mpdf\Output\Destination::INLINE);
exit;
