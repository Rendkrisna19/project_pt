<?php
// admin/cetak/permintaan_export_pdf.php
// PDF export Pengajuan AU-58 (Permintaan Bahan) - tema hijau, header "PTPN 4 REGIONAL 3"
// Mendukung filter ?q=&unit_id=&kebun_id=&tgl_from=&tgl_to=

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function likeParam($s){ return '%'.str_replace(['%','_'], ['\\%','\\_'], trim($s)).'%'; }

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // ==== Filters (opsional) ====
  $q        = isset($_GET['q']) ? trim($_GET['q']) : '';
  $unit_id  = (isset($_GET['unit_id']) && $_GET['unit_id']!=='') ? (int)$_GET['unit_id'] : null;
  $kebun_id = (isset($_GET['kebun_id']) && $_GET['kebun_id']!=='') ? (int)$_GET['kebun_id'] : null;
  $tgl_from = isset($_GET['tgl_from']) && $_GET['tgl_from']!=='' ? $_GET['tgl_from'] : null; // YYYY-MM-DD
  $tgl_to   = isset($_GET['tgl_to'])   && $_GET['tgl_to']  !=='' ? $_GET['tgl_to']   : null;

  $sql = "SELECT p.*,
                 u.nama_unit,
                 k.nama_kebun
          FROM permintaan_bahan p
          LEFT JOIN units u   ON u.id = p.unit_id
          LEFT JOIN md_kebun k ON k.id = p.kebun_id
          WHERE 1=1";
  $bind = [];
  if ($q !== '') {
    $sql .= " AND (p.no_dokumen LIKE :q OR p.blok LIKE :q OR IFNULL(p.keterangan,'') LIKE :q)";
    $bind[':q'] = likeParam($q);
  }
  if ($unit_id !== null)  { $sql .= " AND p.unit_id  = :uid";  $bind[':uid']  = $unit_id; }
  if ($kebun_id !== null) { $sql .= " AND p.kebun_id = :kid";  $bind[':kid']  = $kebun_id; }
  if ($tgl_from)          { $sql .= " AND p.tanggal >= :df";   $bind[':df']   = $tgl_from; }
  if ($tgl_to)            { $sql .= " AND p.tanggal <= :dt";   $bind[':dt']   = $tgl_to; }

  $sql .= " ORDER BY p.tanggal DESC, p.id DESC";

  $st = $pdo->prepare($sql);
  $st->execute($bind);
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
  <div class="subtitle">Pengajuan AU-58 (Permintaan Bahan)</div>

  <table>
    <colgroup>
      <col style="width:10%"><col style="width:12%"><col style="width:12%"><col style="width:11%">
      <col style="width:13%"><col style="width:8%"><col style="width:12%"><col style="width:12%"><col style="width:10%">
    </colgroup>
    <thead>
      <tr>
        <th>No. Dokumen</th>
        <th>Kebun</th>
        <th>Unit/Devisi</th>
        <th>Tanggal</th>
        <th>Blok</th>
        <th>Pokok</th>
        <th>Dosis/Norma</th>
        <th class="text-right">Jumlah Diminta</th>
        <th>Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9">Belum ada data.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['no_dokumen'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['nama_kebun'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['nama_unit']  ?? '-') ?></td>
          <td><?= htmlspecialchars($r['tanggal']    ?? '-') ?></td>
          <td class="wrap"><?= htmlspecialchars($r['blok'] ?? '-') ?></td>
          <td class="text-right"><?= is_null($r['pokok']) ? '-' : number_format((float)$r['pokok'],0) ?></td>
          <td><?= htmlspecialchars($r['dosis_norma'] ?? '-') ?></td>
          <td class="text-right"><?= is_null($r['jumlah_diminta']) ? '-' : number_format((float)$r['jumlah_diminta'],2) ?></td>
          <td class="wrap"><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
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
$dompdf->stream('permintaan_'.date('Ymd_His').'.pdf', ['Attachment'=>false]);
exit;
