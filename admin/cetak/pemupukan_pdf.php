<?php
// pages/cetak/pemupukan_pdf.php
// Cetak PDF Pemupukan (Menabur/Angkutan) dengan header hijau "PTPN 4 REGIONAL 3"
// Tanpa info siapa mencetak & tanpa tanggal cetak. Filter respect ?tab=&unit_id=
// Dependencies: dompdf/dompdf via Composer

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function qstr($v){ return trim((string)$v); }
function qint($v){ return ($v===''||$v===null)? null : (int)$v; }

try {
  $db = new Database();
  $pdo = $db->getConnection();

  // Params
  $tab = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';
  $f_unit_id = isset($_GET['unit_id']) && $_GET['unit_id'] !== '' ? (int)$_GET['unit_id'] : null;

  // Helper deteksi kolom
  $cacheCols = [];
  $columnExists = function(PDO $c, $table, $col) use (&$cacheCols){
    if (!isset($cacheCols[$table])) {
      $st = $c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
      $st->execute([':t'=>$table]);
      $cacheCols[$table] = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    }
    return in_array($col, $cacheCols[$table] ?? [], true);
  };

  $hasKebunMenaburId  = $columnExists($pdo,'menabur_pupuk','kebun_id');
  $hasKebunMenaburKod = $columnExists($pdo,'menabur_pupuk','kebun_kode');
  $hasKebunAngkutId   = $columnExists($pdo,'angkutan_pupuk','kebun_id');
  $hasKebunAngkutKod  = $columnExists($pdo,'angkutan_pupuk','kebun_kode');

  // Query data
  if ($tab === 'angkutan') {
    $title = "Data Angkutan Pupuk Kimia";
    $selectKebun = '';
    $joinKebun   = '';
    if ($hasKebunAngkutId)  { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun = " LEFT JOIN md_kebun kb ON kb.id = a.kebun_id "; }
    elseif ($hasKebunAngkutKod) { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun = " LEFT JOIN md_kebun kb ON kb.kode = a.kebun_kode "; }

    $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama $selectKebun
            FROM angkutan_pupuk a
            LEFT JOIN units u ON u.id = a.unit_tujuan_id
            $joinKebun
            WHERE 1=1";
    $p = [];
    if ($f_unit_id !== null) { $sql .= " AND a.unit_tujuan_id = :uid"; $p[':uid'] = $f_unit_id; }
    $sql .= " ORDER BY a.tanggal DESC, a.id DESC";
    $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // ===== Totals (angkutan) =====
    $tot_kg = 0.0;
    foreach ($rows as $r) { $tot_kg += (float)($r['jumlah'] ?? 0); }

  } else {
    $title = "Data Penaburan Pupuk Kimia";
    $selectKebun = '';
    $joinKebun   = '';
    if ($hasKebunMenaburId)  { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun = " LEFT JOIN md_kebun kb ON kb.id = m.kebun_id "; }
    elseif ($hasKebunMenaburKod) { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun = " LEFT JOIN md_kebun kb ON kb.kode = m.kebun_kode "; }

    $sql = "SELECT m.*, u.nama_unit AS unit_nama $selectKebun
            FROM menabur_pupuk m
            LEFT JOIN units u ON u.id = m.unit_id
            $joinKebun
            WHERE 1=1";
    $p = [];
    if ($f_unit_id !== null) { $sql .= " AND m.unit_id = :uid"; $p[':uid'] = $f_unit_id; }
    $sql .= " ORDER BY m.tanggal DESC, m.id DESC";
    $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // ===== Totals (menabur) =====
    $tot_kg = 0.0; $tot_luas = 0.0; $tot_invt = 0.0;
    $sum_dosis = 0.0; $cnt_dosis = 0;
    foreach ($rows as $r) {
      $tot_kg   += (float)($r['jumlah'] ?? 0);
      $tot_luas += (float)($r['luas'] ?? 0);
      $tot_invt += (float)($r['invt_pokok'] ?? 0);
      // rata-rata dosis: abaikan null/''/0 agar sesuai AVG(NULLIF(dosis,0))
      if (isset($r['dosis']) && $r['dosis'] !== '' && (float)$r['dosis'] != 0) {
        $sum_dosis += (float)$r['dosis'];
        $cnt_dosis++;
      }
    }
    $avg_dosis = $cnt_dosis > 0 ? ($sum_dosis / $cnt_dosis) : 0.0;
  }

} catch (Throwable $e) {
  http_response_code(500);
  exit("DB Error: " . $e->getMessage());
}

// ===== Build HTML (tema hijau) =====
ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 20mm 15mm; size: A4 landscape; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#111; }
  .brand {
    background: #22c55e; /* Tailwind emerald-500-ish */
    color: #fff; padding: 12px 16px; border-radius: 8px; text-align:center; margin-bottom: 10px;
  }
  .brand h1 { margin:0; font-size: 18px; letter-spacing: .5px; }
  .subtitle { text-align:center; margin: 4px 0 12px; font-weight: bold; font-size: 13px; color:#065f46; } /* darker green */
  table { width:100%; border-collapse: collapse; }
  th, td { border: 1px solid #e5e7eb; padding: 6px 8px; }
  thead th {
    background: #ecfdf5; /* emerald-50 */
    color: #065f46;      /* emerald-800 */
    font-weight: 700;
  }
  tbody tr:nth-child(even) td { background:#f8fafc; }
  tfoot td {
    background: #f1faf6;
    font-weight: 700;
  }
  .text-right { text-align:right; }
  .text-left { text-align:left; }
</style>
</head>
<body>
  <div class="brand">
    <h1>PTPN 4 REGIONAL 3</h1>
  </div>
  <div class="subtitle"><?= htmlspecialchars($title) ?></div>

  <table>
    <thead>
      <tr>
      <?php if ($tab === 'angkutan'): ?>
        <th class="text-left">Kebun</th>
        <th class="text-left">Gudang Asal</th>
        <th class="text-left">Unit Tujuan</th>
        <th class="text-left">Tanggal</th>
        <th class="text-left">Jenis Pupuk</th>
        <th class="text-right">Jumlah (Kg)</th>
        <th class="text-left">Nomor DO</th>
        <th class="text-left">Supir</th>
      <?php else: ?>
        <th class="text-left">Kebun</th>
        <th class="text-left">Unit</th>
        <th class="text-left">Blok</th>
        <th class="text-left">Tanggal</th>
        <th class="text-left">Jenis Pupuk</th>
        <th class="text-right">Dosis (kg/ha)</th>
        <th class="text-right">Jumlah (Kg)</th>
        <th class="text-right">Luas (Ha)</th>
        <th class="text-right">Invt. Pokok</th>
        <th class="text-left">Catatan</th>
      <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= $tab==='angkutan' ? 8 : 10 ?>" class="text-left">Belum ada data.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php if ($tab==='angkutan'): ?>
          <tr>
            <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
            <td><?= htmlspecialchars($r['gudang_asal']) ?></td>
            <td><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['tanggal']) ?></td>
            <td><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
            <td class="text-right"><?= number_format((float)($r['jumlah'] ?? 0), 2) ?></td>
            <td><?= htmlspecialchars($r['nomor_do']) ?></td>
            <td><?= htmlspecialchars($r['supir']) ?></td>
          </tr>
        <?php else: ?>
          <tr>
            <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
            <td><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['blok']) ?></td>
            <td><?= htmlspecialchars($r['tanggal']) ?></td>
            <td><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
            <td class="text-right"><?= ($r['dosis'] !== null && $r['dosis']!=='') ? number_format((float)$r['dosis'], 2) : '-' ?></td>
            <td class="text-right"><?= number_format((float)($r['jumlah'] ?? 0), 2) ?></td>
            <td class="text-right"><?= number_format((float)($r['luas'] ?? 0), 2) ?></td>
            <td class="text-right"><?= number_format((float)($r['invt_pokok'] ?? 0), 0) ?></td>
            <td><?= htmlspecialchars($r['catatan']) ?></td>
          </tr>
        <?php endif; ?>
      <?php endforeach; endif; ?>
    </tbody>

    <?php if (!empty($rows)): ?>
    <tfoot>
      <?php if ($tab==='angkutan'): ?>
        <tr>
          <!-- Gabung 5 kolom pertama untuk label TOTAL -->
          <td colspan="5" class="text-right">TOTAL</td>
          <td class="text-right"><?= number_format($tot_kg, 2) ?></td>
          <td></td>
          <td></td>
        </tr>
      <?php else: ?>
        <tr>
          <!-- Gabung 5 kolom pertama untuk label TOTAL -->
          <td colspan="5" class="text-right">TOTAL</td>
          <td class="text-right"><?= number_format($avg_dosis ?? 0, 2) ?></td>
          <td class="text-right"><?= number_format($tot_kg, 2) ?></td>
          <td class="text-right"><?= number_format($tot_luas, 2) ?></td>
          <td class="text-right"><?= number_format($tot_invt, 0) ?></td>
          <td></td>
        </tr>
      <?php endif; ?>
    </tfoot>
    <?php endif; ?>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();

// Render DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$fname = ($tab==='angkutan' ? 'angkutan' : 'menabur') . '_pupuk_' . date('Ymd_His') . '.pdf';
$dompdf->stream($fname, ['Attachment' => false]); // inline preview
exit;
