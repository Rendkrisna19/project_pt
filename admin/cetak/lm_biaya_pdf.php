<?php
// admin/cetak/lm_biaya_pdf.php
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

$sql = "
  SELECT b.*,
         u.nama_unit,
         a.kode AS kode_aktivitas, a.nama AS nama_aktivitas,
         j.nama AS nama_jenis,
         (b.realisasi_bi - b.rencana_bi) AS diff_bi,
         CASE WHEN b.rencana_bi = 0 THEN NULL
              ELSE ((b.realisasi_bi / b.rencana_bi) - 1) * 100 END AS diff_pct
  FROM lm_biaya b
  LEFT JOIN units u ON u.id = b.unit_id
  LEFT JOIN md_kode_aktivitas a ON a.id = b.kode_aktivitas_id
  LEFT JOIN md_jenis_pekerjaan j ON j.id = b.jenis_pekerjaan_id
  ORDER BY b.tahun DESC,
           FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
           b.id DESC
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$username = $_SESSION['username'] ?? 'Unknown User';
$now = date('d/m/Y H:i');

ob_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  body{font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:10px; color:#111;}
  .title{ text-align:center; font-weight:bold; font-size:14px; color:#fff; padding:8px; background:#2E7D32; border-radius:4px;}
  .sub  { text-align:center; font-weight:bold; font-size:12px; color:#fff; padding:6px; background:#388E3C; border-radius:4px; margin:6px 0 10px;}
  .meta { font-size:10px; text-align:right; margin-bottom:6px; color:#555;}
  table{ width:100%; border-collapse:collapse; table-layout:fixed;}
  th,td{ border:1px solid #555; padding:3px; word-wrap:break-word;}
  th{ background:#2E7D32; color:#fff; text-align:center;}
  .center{text-align:center;} .right{text-align:right;}
</style>
</head>
<body>
  <div class="title">PTPN 4 REGIONAL 2</div>
  <div class="sub">LM Biaya</div>
  <div class="meta">Dicetak oleh: <?= htmlspecialchars($username) ?> pada <?= $now ?></div>

  <table>
    <thead>
      <tr>
        <th style="width:120px;">Kode Aktivitas</th>
        <th style="width:110px;">Jenis Pekerjaan</th>
        <th style="width:55px;">Bulan</th>
        <th style="width:45px;">Tahun</th>
        <th style="width:100px;">Unit/Divisi</th>
        <th style="width:85px;">Rencana BI</th>
        <th style="width:85px;">Realisasi BI</th>
        <th style="width:80px;">+/- Biaya</th>
        <th style="width:65px;">+/- %</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$rows): ?>
        <tr><td class="center" colspan="9">Tidak ada data.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars(trim(($r['kode_aktivitas']??'').' - '.($r['nama_aktivitas']??''))) ?></td>
          <td><?= htmlspecialchars($r['nama_jenis'] ?? '-') ?></td>
          <td class="center"><?= htmlspecialchars($r['bulan']) ?></td>
          <td class="center"><?= (int)$r['tahun'] ?></td>
          <td><?= htmlspecialchars($r['nama_unit'] ?? '-') ?></td>
          <td class="right"><?= number_format((float)$r['rencana_bi'],2) ?></td>
          <td class="right"><?= number_format((float)$r['realisasi_bi'],2) ?></td>
          <td class="right"><?= number_format((float)$r['diff_bi'],2) ?></td>
          <td class="right"><?= is_null($r['diff_pct']) ? '-' : number_format((float)$r['diff_pct'],2).'%' ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new Mpdf(['format' => 'A4-L']); // landscape
$mpdf->SetTitle('LM_Biaya_'.date('Ymd_His'));
$mpdf->SetFooter('Halaman {PAGENO} / {nb}');
$mpdf->WriteHTML($html);
$mpdf->Output('LM_Biaya_'.date('Ymd_His').'.pdf', \Mpdf\Output\Destination::INLINE);
exit;
