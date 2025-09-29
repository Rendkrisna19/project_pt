<?php
// pages/cetak/pemeliharaan_pdf.php
// Output: PDF daftar pemeliharaan mengikuti filter ?tab= & ?unit_id=
// Theme hijau, header: "PTPN IV REGIONAL 3", tanpa info pencetak/tanggal
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$db = new Database(); 
$pdo = $db->getConnection();

$allowedTab = ['TU','TBM','TM','BIBIT_PN','BIBIT_MN'];
$daftar_tab = [
  'TU'=>'Pemeliharaan TU',
  'TBM'=>'Pemeliharaan TBM',
  'TM'=>'Pemeliharaan TM',
  'BIBIT_PN'=>'Pemeliharaan Bibit PN',
  'BIBIT_MN'=>'Pemeliharaan Bibit MN'
];

$tab = $_GET['tab'] ?? 'TU';
if (!in_array($tab, $allowedTab, true)) $tab = 'TU';

$f_unit_id = isset($_GET['unit_id']) ? (($_GET['unit_id']==='') ? '' : (int)$_GET['unit_id']) : '';

$sql = "SELECT p.*, u.nama_unit AS unit_nama
        FROM pemeliharaan p
        LEFT JOIN units u ON u.id = p.unit_id
        WHERE p.kategori = :k";
$params = [':k'=>$tab];
if ($f_unit_id !== '' && $f_unit_id !== null) { 
  $sql .= " AND p.unit_id = :unit_id"; 
  $params[':unit_id'] = (int)$f_unit_id; 
}
$sql .= " ORDER BY p.tanggal DESC, p.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// === TOTALS (sesuai filter) ===
$tot_rencana = 0.0; $tot_realisasi = 0.0;
foreach ($rows as $r) {
  $tot_rencana   += (float)($r['rencana'] ?? 0);
  $tot_realisasi += (float)($r['realisasi'] ?? 0);
}
$tot_progress = $tot_rencana > 0 ? ($tot_realisasi / $tot_rencana) * 100 : 0.0;

// helper escape
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$judul = $daftar_tab[$tab] ?? 'Pemeliharaan';

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= h($judul) ?></title>
<style>
  @page { size: A4 landscape; margin: 18mm 15mm 15mm 15mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
  .brand {
    background:#0f7b4f; /* hijau tua */
    color:#fff;
    padding:14px 16px;
    border-radius:10px;
    text-align:center;
    margin-bottom:12px;
  }
  .brand h1 { margin:0; font-size:18px; letter-spacing:0.5px; }
  .sub {
    margin:6px 0 14px 0; 
    text-align:center;
    color:#0f7b4f;
    font-weight:bold;
    font-size:14px;
  }
  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #dfe5e2; padding:6px 8px; }
  thead th {
    background:#e8f4ef; color:#0f2e22; font-weight:700; text-transform:uppercase; font-size:10px;
  }
  tbody tr:nth-child(even) { background:#fbfdfc; }
  tfoot td { background:#f1faf6; font-weight:700; }
  .right { text-align:right; }
  .center { text-align:center; }
  .badge { display:inline-block; padding:2px 6px; border-radius:10px; font-size:10px; border:1px solid transparent; }
  .b-green { background:#dbf5e9; color:#045e3e; border-color:#b9ead6; }
  .b-blue  { background:#e7f1fd; color:#0b4a88; border-color:#cfe2fb; }
  .b-yellow{ background:#fff7e0; color:#8a6b00; border-color:#fde8b3; }
  .b-gray  { background:#f1f3f2; color:#333; border-color:#e0e5e3; }
  .footnote { margin-top:10px; font-size:10px; color:#666; }
</style>
</head>
<body>
  <div class="brand">
    <h1>PTPN IV REGIONAL 3</h1>
  </div>
  <div class="sub"><?= h($judul) ?></div>

  <table>
    <thead>
      <tr>
        <th>Jenis Pekerjaan</th>
        <th>Tenaga</th>
        <th>Unit/Devisi</th>
        <th>Kebun</th>
        <th class="center">Periode</th>
        <th class="right">Rencana</th>
        <th class="right">Realisasi</th>
        <th class="right">Progress (%)</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="center">Tidak ada data.</td></tr>
      <?php else: foreach ($rows as $r):
        $rencana   = (float)($r['rencana'] ?? 0);
        $realisasi = (float)($r['realisasi'] ?? 0);
        $progress  = $rencana>0 ? ($realisasi/$rencana)*100 : 0;
        $status = (string)($r['status'] ?? '');
        $cls = 'b-gray';
        if ($status==='Selesai') $cls='b-green';
        elseif ($status==='Berjalan') $cls='b-blue';
        elseif ($status==='Tertunda') $cls='b-yellow';
      ?>
        <tr>
          <td><?= h($r['jenis_pekerjaan']) ?></td>
          <td><?= h($r['tenaga'] ?? '-') ?></td>
          <td><?= h($r['unit_nama'] ?: '-') ?></td>
          <td><?= h($r['rayon'] ?: '-') ?></td>
          <td class="center"><?= h(($r['bulan'] ?? '').' '.($r['tahun'] ?? '')) ?></td>
          <td class="right"><?= number_format($rencana,2,',','.') ?></td>
          <td class="right"><?= number_format($realisasi,2,',','.') ?></td>
          <td class="right"><?= number_format($progress,2,',','.') ?></td>
          <td><span class="badge <?= $cls ?>"><?= h($status) ?></span></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($rows)): ?>
    <tfoot>
      <tr>
        <td colspan="5" class="right">TOTAL</td>
        <td class="right"><?= number_format($tot_rencana,2,',','.') ?></td>
        <td class="right"><?= number_format($tot_realisasi,2,',','.') ?></td>
        <td class="right"><?= number_format($tot_progress,2,',','.') ?></td>
        <td></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>

  <?php if ($f_unit_id !== '' && $f_unit_id !== null): 
    // tampilkan ringkas filter aktif (tanpa info pencetak/tanggal)
    $u = $pdo->prepare("SELECT nama_unit FROM units WHERE id=:id");
    $u->execute([':id'=>(int)$f_unit_id]);
    $unitNama = $u->fetchColumn() ?: ('#'.$f_unit_id);
  ?>
    <div class="footnote"><strong>Filter:</strong> Kategori <?= h($tab) ?> | Unit <?= h($unitNama) ?></div>
  <?php else: ?>
    <div class="footnote"><strong>Filter:</strong> Kategori <?= h($tab) ?> | Semua Unit</div>
  <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$fname = 'Pemeliharaan_'.$tab.(($f_unit_id!=='')?('_UNIT-'.$f_unit_id):'').'.pdf';
$dompdf->stream($fname, ['Attachment'=>true]);
