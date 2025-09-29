<?php
// admin/cetak/lm76_export_pdf.php
// PDF export LM-76 — tema hijau, header "PTPN 4 REGIONAL 3"
// Ikut filter ?unit_id=&bulan=&tahun=

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
  $unit_id = (isset($_GET['unit_id']) && $_GET['unit_id']!=='') ? (int)$_GET['unit_id'] : null;
  $bulan   = (isset($_GET['bulan'])   && $_GET['bulan']  !=='') ? trim($_GET['bulan'])   : null;
  $tahun   = (isset($_GET['tahun'])   && $_GET['tahun']  !=='') ? (int)$_GET['tahun']   : null;

  // ==== Query ====
  $selectK = $hasKebun ? ", k.nama_kebun" : "";
  $joinK   = $hasKebun ? " LEFT JOIN md_kebun k ON k.id = l.kebun_id " : "";

  $sql = "SELECT l.*, u.nama_unit $selectK
          FROM lm76 l
          LEFT JOIN units u ON u.id = l.unit_id
          $joinK
          WHERE 1=1";
  $bind = [];
  if ($unit_id !== null) { $sql .= " AND l.unit_id = :uid"; $bind[':uid'] = $unit_id; }
  if ($bulan   !== null) { $sql .= " AND l.bulan   = :bln"; $bind[':bln'] = $bulan; }
  if ($tahun   !== null) { $sql .= " AND l.tahun   = :thn"; $bind[':thn'] = $tahun; }

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
  .brand { background:#22c55e; color:#fff; padding:10px 14px; border-radius:8px; text-align:center; margin-bottom:8px; }
  .brand h1 { margin:0; font-size:18px; }
  .subtitle { text-align:center; font-weight:700; color:#065f46; margin:4px 0 12px; }
  table { width:100%; border-collapse: collapse; table-layout:fixed; }
  th, td { border:1px solid #e5e7eb; padding:6px 8px; vertical-align:top; }
  thead th { background:#ecfdf5; color:#065f46; }
  tbody tr:nth-child(even) td { background:#f8fafc; }
  .text-right { text-align:right; }
  .wrap { word-wrap:break-word; overflow-wrap:anywhere; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN 4 REGIONAL 3</h1></div>
  <div class="subtitle">LM-76 — Statistik Panen Kelapa Sawit</div>

  <table>
    <colgroup>
      <?php if ($hasKebun): ?><col style="width:11%"><?php endif; ?>
      <col style="width:12%"><col style="width:10%"><col style="width:9%"><col style="width:8%"><col style="width:9%">
      <col style="width:11%"><col style="width:11%"><col style="width:8%"><col style="width:10%"><col style="width:9%"><col style="width:11%"><col style="width:10%">
    </colgroup>
    <thead>
      <tr>
        <?php if ($hasKebun): ?><th>Kebun</th><?php endif; ?>
        <th>Unit</th>
        <th>Periode</th>
        <th>Blok</th>
        <th>Luas (Ha)</th>
        <th>Jml Pohon</th>
        <th>Prod BI (Real/Angg)</th>
        <th>Prod SD (Real/Angg)</th>
        <th>Jml Tandan (BI)</th>
        <th>PSTB (BI/TL)</th>
        <th>Panen HK</th>
        <th>Panen Ha (BI/SD)</th>
        <th>Freq (BI/SD)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= $hasKebun?13:12 ?>">Belum ada data.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <?php if ($hasKebun): ?><td><?= htmlspecialchars($r['nama_kebun'] ?? '-') ?></td><?php endif; ?>
          <td><?= htmlspecialchars($r['nama_unit'] ?? '-') ?></td>
          <td><?= htmlspecialchars(($r['bulan'] ?? '-').' '.($r['tahun'] ?? '-')) ?></td>
          <td class="wrap"><?= htmlspecialchars($r['blok'] ?? '-') ?></td>
          <td class="text-right"><?= is_null($r['luas_ha']) ? '-' : number_format((float)$r['luas_ha'],2) ?></td>
          <td class="text-right"><?= is_null($r['jumlah_pohon']) ? '-' : number_format((float)$r['jumlah_pohon'],0) ?></td>
          <td class="text-right"><?= number_format((float)($r['prod_bi_realisasi'] ?? 0),2) ?>/<?= number_format((float)($r['prod_bi_anggaran'] ?? 0),2) ?></td>
          <td class="text-right"><?= number_format((float)($r['prod_sd_realisasi'] ?? 0),2) ?>/<?= number_format((float)($r['prod_sd_anggaran'] ?? 0),2) ?></td>
          <td class="text-right"><?= is_null($r['jumlah_tandan_bi']) ? '-' : number_format((float)$r['jumlah_tandan_bi'],0) ?></td>
          <td class="text-right"><?= number_format((float)($r['pstb_ton_ha_bi'] ?? 0),2) ?>/<?= number_format((float)($r['pstb_ton_ha_tl'] ?? 0),2) ?></td>
          <td class="text-right"><?= is_null($r['panen_hk_realisasi']) ? '-' : number_format((float)$r['panen_hk_realisasi'],2) ?></td>
          <td class="text-right"><?= number_format((float)($r['panen_ha_bi'] ?? 0),2) ?>/<?= number_format((float)($r['panen_ha_sd'] ?? 0),2) ?></td>
          <td class="text-right"><?= number_format((float)($r['frek_panen_bi'] ?? 0),0) ?>/<?= number_format((float)($r['frek_panen_sd'] ?? 0),0) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
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
