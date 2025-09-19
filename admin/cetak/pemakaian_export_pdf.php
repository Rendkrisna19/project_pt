<?php
// pemakaian_export_pdf.php (REVISI pakai units)
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Forbidden'); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$q       = trim($_GET['q'] ?? '');
$unit_id = (int)($_GET['unit_id'] ?? 0);
$bulan   = trim($_GET['bulan'] ?? '');
$tahun   = (int)($_GET['tahun'] ?? date('Y'));

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT p.no_dokumen, u.nama_unit, p.bulan, p.tahun, p.nama_bahan, p.jenis_pekerjaan,
               p.jlh_diminta, p.jlh_fisik, p.dokumen_path, p.keterangan
        FROM pemakaian_bahan_kimia p
        LEFT JOIN units u ON u.id = p.unit_id
        WHERE 1=1";
$bind = [];
if ($unit_id > 0) { $sql .= " AND p.unit_id = :uid"; $bind[':uid'] = $unit_id; }
if ($bulan !== '') { $sql .= " AND p.bulan = :bln"; $bind[':bln'] = $bulan; }
if ($tahun) { $sql .= " AND p.tahun = :thn"; $bind[':thn'] = $tahun; }
if ($q !== '') {
  $sql .= " AND (p.no_dokumen LIKE :q OR p.nama_bahan LIKE :q OR p.jenis_pekerjaan LIKE :q OR p.keterangan LIKE :q)";
  $bind[':q'] = "%{$q}%";
}
$sql .= " ORDER BY p.tahun DESC,
                 FIELD(p.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
                 u.nama_unit ASC, p.no_dokumen ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($bind);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nowStr = date('d-m-Y H:i');
$judul  = "PTPN 4 REGIONAL 2 — Pemakaian Bahan Kimia (Dicetak {$nowStr})";

ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($judul) ?></title>
<style>
  @page { margin: 15px; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
  .title {
    text-align:center; font-weight:bold; font-size:14px; color:#fff;
    padding:8px; background:#16a34a; border-radius:4px; margin-bottom:8px;
  }
  table { width:100%; border-collapse:collapse; table-layout:fixed; }
  th, td { border:1px solid #555; padding:4px; word-wrap:break-word; }
  th { background:#16a34a; color:#fff; text-align:center; }
  .center{ text-align:center; } .right{ text-align:right; }
  footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: right; font-size: 10px; color: #555; }
</style>
</head>
<body>
  <div class="title"><?= htmlspecialchars($judul) ?></div>
  <table>
    <thead>
      <tr>
        <th style="width:25px;">No</th>
        <th style="width:90px;">No. Dokumen</th>
        <th style="width:90px;">Unit</th>
        <th style="width:70px;">Periode</th>
        <th style="width:120px;">Nama Bahan</th>
        <th style="width:110px;">Jenis Pekerjaan</th>
        <th style="width:70px;">Jlh Diminta</th>
        <th style="width:70px;">Jml Fisik</th>
        <th style="width:100px;">Dokumen</th>
        <th style="width:130px;">Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td class="center" colspan="10">Tidak ada data.</td></tr>
      <?php else: $i=0; foreach ($rows as $r): $i++; ?>
        <tr>
          <td class="center"><?= $i ?></td>
          <td><?= htmlspecialchars($r['no_dokumen']) ?></td>
          <td class="center"><?= htmlspecialchars($r['nama_unit'] ?? '-') ?></td>
          <td class="center"><?= htmlspecialchars(($r['bulan']??'').' '.($r['tahun']??'')) ?></td>
          <td><?= htmlspecialchars($r['nama_bahan']) ?></td>
          <td><?= htmlspecialchars($r['jenis_pekerjaan']) ?></td>
          <td class="right"><?= number_format((float)$r['jlh_diminta'],2) ?></td>
          <td class="right"><?= number_format((float)$r['jlh_fisik'],2) ?></td>
          <td><?= htmlspecialchars(basename((string)$r['dokumen_path']) ?: '-') ?></td>
          <td><?= htmlspecialchars($r['keterangan']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
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
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Pemakaian_'.date('Ymd_His').'.pdf', ['Attachment' => 0]);
exit;
