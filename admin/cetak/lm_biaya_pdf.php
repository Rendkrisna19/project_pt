<?php
// admin/cetak/lm_biaya_pdf.php
// PDF export LM Biaya — Tema hijau, header "PTPN 4 REGIONAL 3", tanpa footer cetak

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  $hasKebun = col_exists($pdo, 'lm_biaya', 'kebun_id');

  // ---- Optional filters (support future UI) ----
  $unit_id = isset($_GET['unit_id']) && $_GET['unit_id'] !== '' ? (int)$_GET['unit_id'] : null;
  $bulan   = isset($_GET['bulan'])   && $_GET['bulan']   !== '' ? trim($_GET['bulan'])   : null;
  $tahun   = isset($_GET['tahun'])   && $_GET['tahun']   !== '' ? (int)$_GET['tahun']   : null;
  $kebun_id= isset($_GET['kebun_id'])&& $_GET['kebun_id']!== '' ? (int)$_GET['kebun_id'] : null;

  $sql = "SELECT b.*,
                 u.nama_unit,
                 a.kode AS kode_aktivitas, a.nama AS nama_aktivitas,
                 j.nama AS nama_jenis
                 ".($hasKebun ? ", kb.nama_kebun " : "")."
          FROM lm_biaya b
          LEFT JOIN units u ON u.id = b.unit_id
          LEFT JOIN md_kode_aktivitas a ON a.id = b.kode_aktivitas_id
          LEFT JOIN md_jenis_pekerjaan j ON j.id = b.jenis_pekerjaan_id
          ".($hasKebun ? "LEFT JOIN md_kebun kb ON kb.id = b.kebun_id" : "")."
          WHERE 1=1";
  $bind = [];
  if (!is_null($unit_id)) { $sql .= " AND b.unit_id = :uid";   $bind[':uid'] = $unit_id; }
  if (!is_null($bulan))   { $sql .= " AND b.bulan = :bln";     $bind[':bln'] = $bulan; }
  if (!is_null($tahun))   { $sql .= " AND b.tahun = :thn";     $bind[':thn'] = $tahun; }
  if ($hasKebun && !is_null($kebun_id)) { $sql .= " AND b.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }

  $sql .= " ORDER BY b.tahun DESC,
            FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            b.id DESC";

  $st = $pdo->prepare($sql); $st->execute($bind);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // diff
  foreach ($rows as &$r) {
    $r['diff_bi']  = (float)$r['realisasi_bi'] - (float)$r['rencana_bi'];
    $r['diff_pct'] = ((float)$r['rencana_bi'])>0 ? (((float)$r['realisasi_bi']/(float)$r['rencana_bi'])-1)*100 : null;
  } unset($r);

} catch(Throwable $e){
  http_response_code(500); exit('DB Error: '.$e->getMessage());
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
  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #e5e7eb; padding:6px 8px; vertical-align:top; }
  thead th { background:#ecfdf5; color:#065f46; }
  tbody tr:nth-child(even) td { background:#f8fafc; }
  .text-right { text-align:right; }
  .wrap { word-wrap:break-word; overflow-wrap:anywhere; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN 4 REGIONAL 3</h1></div>
  <div class="subtitle">LM Biaya</div>

  <table>
    <thead>
      <tr>
        <th>Kode Aktivitas</th>
        <th>Jenis Pekerjaan</th>
        <th>Bulan</th>
        <th>Tahun</th>
        <?php if ($hasKebun): ?><th>Kebun</th><?php endif; ?>
        <th>Unit/Divisi</th>
        <th>Rencana BI</th>
        <th>Realisasi BI</th>
        <th>+/− Biaya</th>
        <th>+/− %</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= $hasKebun?10:9 ?>">Belum ada data.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="wrap"><?= htmlspecialchars(($r['kode_aktivitas'] ?? '').' - '.($r['nama_aktivitas'] ?? '')) ?></td>
          <td class="wrap"><?= htmlspecialchars($r['nama_jenis'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['bulan']) ?></td>
          <td><?= (int)$r['tahun'] ?></td>
          <?php if ($hasKebun): ?><td><?= htmlspecialchars($r['nama_kebun'] ?? '-') ?></td><?php endif; ?>
          <td><?= htmlspecialchars($r['nama_unit'] ?? '-') ?></td>
          <td class="text-right"><?= number_format((float)$r['rencana_bi'],2) ?></td>
          <td class="text-right"><?= number_format((float)$r['realisasi_bi'],2) ?></td>
          <td class="text-right"><?= number_format((float)$r['diff_bi'],2) ?></td>
          <td class="text-right"><?= is_null($r['diff_pct']) ? '-' : number_format((float)$r['diff_pct'],2).'%' ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();
$options = new Options(); $options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4','landscape');
$dompdf->render();
$dompdf->stream('lm_biaya_'.date('Ymd_His').'.pdf', ['Attachment'=>false]);
exit;
