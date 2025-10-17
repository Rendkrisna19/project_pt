<?php
// admin/cetak/lm76_export_pdf.php
// PDF export LM-76 — tema hijau, header "PTPN 4 REGIONAL 3"
// Ikut filter ?kebun_id=&unit_id=&bulan=&tahun=&tt=

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* helper cek kolom ada */
function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  $hasKebun = col_exists($pdo, 'lm76', 'kebun_id');

  // ==== Filters ====
  $kebun_id = (isset($_GET['kebun_id']) && $_GET['kebun_id']!=='') ? (int)$_GET['kebun_id'] : null;
  $unit_id  = (isset($_GET['unit_id'])  && $_GET['unit_id'] !=='') ? (int)$_GET['unit_id']  : null;
  $bulan    = (isset($_GET['bulan'])    && $_GET['bulan']   !=='') ? trim($_GET['bulan'])   : null;
  $tahun    = (isset($_GET['tahun'])    && $_GET['tahun']   !=='') ? (int)$_GET['tahun']   : null;
  $tt       = (isset($_GET['tt'])       && $_GET['tt']      !=='') ? trim($_GET['tt'])      : null;

  // ==== Query ====
  $selectK = $hasKebun ? ", k.nama_kebun" : ", NULL AS nama_kebun";
  $joinK   = $hasKebun ? " LEFT JOIN md_kebun k ON k.id = l.kebun_id " : "";

  $sql = "SELECT l.*, u.nama_unit $selectK
          FROM lm76 l
          LEFT JOIN units u ON u.id = l.unit_id
          $joinK
          WHERE 1=1";
  $bind = [];
  if ($kebun_id !== null && $hasKebun) { $sql .= " AND l.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }
  if ($unit_id  !== null)              { $sql .= " AND l.unit_id  = :uid"; $bind[':uid'] = $unit_id; }
  if ($bulan    !== null)              { $sql .= " AND l.bulan    = :bln"; $bind[':bln'] = $bulan; }
  if ($tahun    !== null)              { $sql .= " AND l.tahun    = :thn"; $bind[':thn'] = $tahun; }
  if ($tt       !== null)              { $sql .= " AND l.tt       = :tt";  $bind[':tt']  = $tt; }

  $sql .= " ORDER BY l.tahun DESC,
            FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            u.nama_unit ASC, l.blok ASC";

  $st = $pdo->prepare($sql); $st->execute($bind);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  http_response_code(500);
  exit('DB Error: '.$e->getMessage());
}

ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 16mm 12mm; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#111; }
  .brand { background:#16a34a; color:#fff; padding:10px 14px; border-radius:8px; text-align:center; margin-bottom:8px; }
  .brand h1 { margin:0; font-size:18px; }
  .subtitle { text-align:center; font-weight:700; color:#065f46; margin:4px 0 12px; }
  table { width:100%; border-collapse: collapse; table-layout:fixed; }
  th, td { border:1px solid #e5e7eb; padding:6px 8px; vertical-align:top; }
  thead th { background:#16a34a; color:#fff; }
  tbody tr:nth-child(even) td { background:#f8fafc; }
  .text-right { text-align:right; }
  .wrap { word-wrap:break-word; overflow-wrap:anywhere; }
  tfoot td { font-weight:700; background:#ecfdf5; border-top:3px solid #16a34a; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN 4 REGIONAL 3</h1></div>
  <div class="subtitle">LM-76 — Statistik Panen Kelapa Sawit</div>

  <table>
    <colgroup>
      <col style="width:7%">
      <col style="width:12%">
      <col style="width:12%">
      <col style="width:10%">
      <col style="width:8%">
      <col style="width:8%">
      <col style="width:9%">
      <col style="width:9%">
      <col style="width:9%">
      <col style="width:8%">
      <col style="width:8%">
      <col style="width:9%">
      <col style="width:11%">
    </colgroup>
    <thead>
      <tr>
        <th>Tahun</th>
        <th>Kebun</th>
        <th>Unit/Defisi</th>
        <th>Periode</th>
        <th>T. Tanam</th>
        <th class="text-right">Luas (Ha)</th>
        <th class="text-right">Invt Pokok</th>
        <th class="text-right">Anggaran (Kg)</th>
        <th class="text-right">Realisasi (Kg)</th>
        <th class="text-right">Jumlah Tandan</th>
        <th class="text-right">Jumlah HK</th>
        <th class="text-right">Panen (Ha)</th>
        <th class="text-right">Frekuensi</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sumLuas=0; $sumPokok=0; $sumAngg=0; $sumReal=0; $sumTandan=0; $sumHK=0; $sumPanenHa=0;
      ?>
      <?php if (!$rows): ?>
        <tr><td colspan="13">Belum ada data.</td></tr>
      <?php else: foreach ($rows as $r):
        $periode  = trim(($r['bulan'] ?? '').' '.($r['tahun'] ?? ''));
        $luas     = (float)($r['luas_ha'] ?? 0);
        $panenHa  = (float)($r['panen_ha_sd'] ?? 0); if ($panenHa == 0) $panenHa = (float)($r['panen_ha_bi'] ?? 0);
        $freq     = $luas > 0 ? $panenHa / $luas : 0;

        $sumLuas    += $luas;
        $sumPokok   += (float)($r['jumlah_pohon'] ?? 0);
        $sumAngg    += (float)($r['prod_sd_anggaran'] ?? 0);
        $sumReal    += (float)($r['prod_sd_realisasi'] ?? 0);
        $sumTandan  += (float)($r['jumlah_tandan_bi'] ?? 0);
        $sumHK      += (float)($r['panen_hk_realisasi'] ?? 0);
        $sumPanenHa += $panenHa;
      ?>
        <tr>
          <td><?= htmlspecialchars($r['tahun'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['nama_kebun'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['nama_unit'] ?? '-') ?></td>
          <td><?= htmlspecialchars($periode ?: '-') ?></td>
          <td><?= htmlspecialchars($r['tt'] ?? '-') ?></td>
          <td class="text-right"><?= number_format($luas, 2) ?></td>
          <td class="text-right"><?= number_format((float)($r['jumlah_pohon'] ?? 0), 0) ?></td>
          <td class="text-right"><?= number_format((float)($r['prod_sd_anggaran'] ?? 0), 2) ?></td>
          <td class="text-right"><?= number_format((float)($r['prod_sd_realisasi'] ?? 0), 2) ?></td>
          <td class="text-right"><?= number_format((float)($r['jumlah_tandan_bi'] ?? 0), 0) ?></td>
          <td class="text-right"><?= number_format((float)($r['panen_hk_realisasi'] ?? 0), 2) ?></td>
          <td class="text-right"><?= number_format($panenHa, 2) ?></td>
          <td class="text-right"><?= number_format($freq, 2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <?php
      $totalFreq = $sumLuas > 0 ? ($sumPanenHa / $sumLuas) : 0;
    ?>
    <tfoot>
      <tr>
        <td colspan="5">TOTAL</td>
        <td class="text-right"><?= number_format($sumLuas, 2) ?></td>
        <td class="text-right"><?= number_format($sumPokok, 0) ?></td>
        <td class="text-right"><?= number_format($sumAngg, 2) ?></td>
        <td class="text-right"><?= number_format($sumReal, 2) ?></td>
        <td class="text-right"><?= number_format($sumTandan, 0) ?></td>
        <td class="text-right"><?= number_format($sumHK, 2) ?></td>
        <td class="text-right"><?= number_format($sumPanenHa, 2) ?></td>
        <td class="text-right"><?= number_format($totalFreq, 2) ?></td>
      </tr>
    </tfoot>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('lm76_'.date('Ymd_His').'.pdf', ['Attachment'=>false]);
exit;
