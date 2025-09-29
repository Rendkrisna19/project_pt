<?php
// admin/cetak/lm77_export_pdf.php
// PDF export LM-77 — tema hijau, header "PTPN 4 REGIONAL 3", ikut filter

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

  $hasKebunId   = col_exists($pdo,'lm77','kebun_id');
  $hasKebunKode = col_exists($pdo,'lm77','kebun_kode');

  // ==== Filters ====
  $unit_id   = (isset($_GET['unit_id'])   && $_GET['unit_id']   !=='') ? (int)$_GET['unit_id'] : null;
  $bulan     = (isset($_GET['bulan'])     && $_GET['bulan']     !=='') ? trim($_GET['bulan'])   : null;
  $tahun     = (isset($_GET['tahun'])     && $_GET['tahun']     !=='') ? (int)$_GET['tahun']   : null;
  $kb_kode   = (isset($_GET['kebun_kode'])&& $_GET['kebun_kode']!=='') ? trim($_GET['kebun_kode']) : null;

  // ==== Query ====
  $selectK = '';
  $joinK   = '';
  if ($hasKebunId)   { $selectK = ", kb.nama_kebun, kb.kode AS kebun_kode"; $joinK=" LEFT JOIN md_kebun kb ON kb.id   = l.kebun_id "; }
  elseif ($hasKebunKode) { $selectK = ", kb.nama_kebun, kb.kode AS kebun_kode"; $joinK=" LEFT JOIN md_kebun kb ON kb.kode = l.kebun_kode "; }

  $sql = "SELECT l.*, u.nama_unit $selectK
          FROM lm77 l
          LEFT JOIN units u ON u.id = l.unit_id
          $joinK
          WHERE 1=1";
  $bind = [];
  if ($unit_id !== null) { $sql .= " AND l.unit_id = :uid"; $bind[':uid'] = $unit_id; }
  if ($bulan   !== null) { $sql .= " AND l.bulan   = :bln"; $bind[':bln'] = $bulan; }
  if ($tahun   !== null) { $sql .= " AND l.tahun   = :thn"; $bind[':thn'] = $tahun; }
  if ($kb_kode !== null) {
    if     ($hasKebunKode) $sql .= " AND l.kebun_kode = :kb";
    elseif ($hasKebunId)   $sql .= " AND kb.kode = :kb";
    else                   $sql .= " AND 1=0"; // tidak ada info kebun
    $bind[':kb'] = $kb_kode;
  }

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
  table { width:100%; border-collapse: collapse; }
  th, td { border:1px solid #e5e7eb; padding:6px 8px; vertical-align:top; }
  thead th { background:#ecfdf5; color:#065f46; }
  tbody tr:nth-child(even) td { background:#f8fafc; }
  .text-right { text-align:right; }
  .wrap { word-wrap:break-word; overflow-wrap:anywhere; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN 4 REGIONAL 3</h1></div>
  <div class="subtitle">LM-77 — Statistik Panen (Rekap)</div>

  <table>
    <thead>
      <tr>
        <th>Kebun</th>
        <th>Unit</th>
        <th>Periode</th>
        <th>Blok</th>
        <th>Luas</th>
        <th>Pohon</th>
        <th>Var % (BI/SD)</th>
        <th>Tandan/Pohon (BI/SD)</th>
        <th>Prod Ton/Ha (BI/SD THI/TL)</th>
        <th>BTR (BI/SD THI/TL)</th>
        <th>Basis (Kg/HK)</th>
        <th>Prestasi Kg/HK (BI/SD)</th>
        <th>Prestasi Tandan/HK (BI/SD)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="13">Belum ada data.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="wrap">
            <?= htmlspecialchars($r['nama_kebun'] ?? '') ?>
            <?= isset($r['kebun_kode']) && $r['kebun_kode']!=='' ? ' ('.htmlspecialchars($r['kebun_kode']).')' : '' ?>
          </td>
          <td><?= htmlspecialchars($r['nama_unit'] ?? '-') ?></td>
          <td><?= htmlspecialchars(($r['bulan'] ?? '-').' '.($r['tahun'] ?? '-')) ?></td>
          <td class="wrap"><?= htmlspecialchars($r['blok'] ?? '-') ?></td>
          <td class="text-right"><?= is_null($r['luas_ha']) ? '-' : number_format((float)$r['luas_ha'],2) ?></td>
          <td class="text-right"><?= is_null($r['jumlah_pohon']) ? '-' : number_format((float)$r['jumlah_pohon'],0) ?></td>
          <td class="text-right"><?= number_format((float)($r['var_prod_bi'] ?? 0),2) ?>% / <?= number_format((float)($r['var_prod_sd'] ?? 0),2) ?>%</td>
          <td class="text-right"><?= number_format((float)($r['jtandan_per_pohon_bi'] ?? 0),4) ?> / <?= number_format((float)($r['jtandan_per_pohon_sd'] ?? 0),4) ?></td>
          <td class="text-right">
            <?= number_format((float)($r['prod_tonha_bi'] ?? 0),2) ?> /
            <?= number_format((float)($r['prod_tonha_sd_thi'] ?? 0),2) ?> /
            <?= number_format((float)($r['prod_tonha_sd_tl'] ?? 0),2) ?>
          </td>
          <td class="text-right">
            <?= number_format((float)($r['btr_bi'] ?? 0),2) ?> /
            <?= number_format((float)($r['btr_sd_thi'] ?? 0),2) ?> /
            <?= number_format((float)($r['btr_sd_tl'] ?? 0),2) ?>
          </td>
          <td class="text-right"><?= is_null($r['basis_borong_kg_hk']) ? '-' : number_format((float)$r['basis_borong_kg_hk'],2) ?></td>
          <td class="text-right"><?= number_format((float)($r['prestasi_kg_hk_bi'] ?? 0),2) ?> / <?= number_format((float)($r['prestasi_kg_hk_sd'] ?? 0),2) ?></td>
          <td class="text-right"><?= number_format((float)($r['prestasi_tandan_hk_bi'] ?? 0),2) ?> / <?= number_format((float)($r['prestasi_tandan_hk_sd'] ?? 0),2) ?></td>
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
$dompdf->stream('lm77_'.date('Ymd_His').'.pdf', ['Attachment'=>false]);
exit;
