<?php
// pages/cetak/pemupukan_organik_pdf.php
// Output: PDF data pemupukan organik (menabur/angkutan) sesuai filter ?tab=&unit_id=&kebun_id=
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$db  = new Database();
$pdo = $db->getConnection();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ============ Params & Query ============
$tab = $_GET['tab'] ?? 'menabur';
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

$f_unit_id  = ($_GET['unit_id']  ?? '') === '' ? '' : (int)$_GET['unit_id'];
$f_kebun_id = ($_GET['kebun_id'] ?? '') === '' ? '' : (int)$_GET['kebun_id'];

if ($tab === 'angkutan') {
  $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama, k.nama_kebun AS kebun_nama
          FROM angkutan_pupuk_organik a
          LEFT JOIN units u ON u.id = a.unit_tujuan_id
          LEFT JOIN md_kebun k ON k.id = a.kebun_id
          WHERE 1=1";
  $p = [];
  if ($f_unit_id !== '')  { $sql .= " AND a.unit_tujuan_id = :uid"; $p[':uid'] = (int)$f_unit_id; }
  if ($f_kebun_id !== '') { $sql .= " AND a.kebun_id = :kid";      $p[':kid'] = (int)$f_kebun_id; }
  $sql .= " ORDER BY a.tanggal DESC, a.id DESC";
  $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $judul = "Data Angkutan Pupuk Organik";

  // Totals angkutan
  $tot_jumlah = 0.0;
  foreach ($rows as $r) $tot_jumlah += (float)($r['jumlah'] ?? 0);

} else {
  $sql = "SELECT m.*, u.nama_unit AS unit_nama, k.nama_kebun AS kebun_nama
          FROM menabur_pupuk_organik m
          LEFT JOIN units u ON u.id = m.unit_id
          LEFT JOIN md_kebun k ON k.id = m.kebun_id
          WHERE 1=1";
  $p = [];
  if ($f_unit_id !== '')  { $sql .= " AND m.unit_id = :uid";  $p[':uid'] = (int)$f_unit_id; }
  if ($f_kebun_id !== '') { $sql .= " AND m.kebun_id = :kid"; $p[':kid'] = (int)$f_kebun_id; }
  $sql .= " ORDER BY m.tanggal DESC, m.id DESC";
  $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $judul = "Data Penaburan Pupuk Organik";

  // Totals menabur
  $tot_jumlah = 0.0; $tot_luas = 0.0; $tot_invt = 0.0;
  $sum_dosis = 0.0; $cnt_dosis = 0;
  foreach ($rows as $r) {
    $tot_jumlah += (float)($r['jumlah'] ?? 0);
    $tot_luas   += (float)($r['luas'] ?? 0);
    $tot_invt   += (float)($r['invt_pokok'] ?? 0);
    if (array_key_exists('dosis',$r) && $r['dosis'] !== null && $r['dosis']!=='') {
      $sum_dosis += (float)$r['dosis'];
      $cnt_dosis++;
    }
  }
  $avg_dosis = $cnt_dosis>0 ? $sum_dosis/$cnt_dosis : 0.0;
}

// Ambil nama unit/kebun utk footnote filter
$unitNama = 'Semua Unit';
if ($f_unit_id !== '') {
  $s = $pdo->prepare("SELECT nama_unit FROM units WHERE id=:id"); $s->execute([':id'=>(int)$f_unit_id]);
  $unitNama = $s->fetchColumn() ?: ('#'.$f_unit_id);
}
$kebunNama = 'Semua Kebun';
if ($f_kebun_id !== '') {
  $s2 = $pdo->prepare("SELECT nama_kebun FROM md_kebun WHERE id=:id"); $s2->execute([':id'=>(int)$f_kebun_id]);
  $kebunNama = $s2->fetchColumn() ?: ('#'.$f_kebun_id);
}

// ============ Render HTML ============
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= h($judul) ?></title>
<style>
  @page { size: A4 landscape; margin: 16mm 14mm 14mm 14mm; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
  .brand { background:#0f7b4f; color:#fff; padding:12px 16px; border-radius:10px; text-align:center; margin-bottom:10px; }
  .brand h1 { margin:0; font-size:18px; letter-spacing:.4px; }
  .sub { text-align:center; color:#0f7b4f; font-weight:bold; margin:6px 0 12px 0; font-size:14px; }

  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #e0e6e3; padding:6px 8px; }
  thead th { background:#e8f4ef; color:#0f2e22; font-weight:700; text-transform:uppercase; font-size:10px; }
  tbody tr:nth-child(even) { background:#fbfdfc; }
  tfoot td { background:#f1faf6; font-weight:700; }
  .right { text-align:right; }
  .center{ text-align:center; }
  .foot { margin-top:8px; font-size:10px; color:#666; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN IV REGIONAL 3</h1></div>
  <div class="sub"><?= h($judul) ?></div>

  <table>
    <thead>
      <tr>
        <th>Kebun</th>
        <?php if ($tab==='angkutan'): ?>
          <th>Gudang Asal</th>
          <th>Unit Tujuan</th>
          <th>Tanggal</th>
          <th>Jenis Pupuk</th>
          <th class="right">Jumlah (Kg)</th>
          <th>Nomor DO</th>
          <th>Supir</th>
        <?php else: ?>
          <th>Unit</th>
          <th>Blok</th>
          <th>Tanggal</th>
          <th>Jenis Pupuk</th>
          <th class="right">Dosis (kg/ha)</th>
          <th class="right">Jumlah (Kg)</th>
          <th class="right">Luas (Ha)</th>
          <th class="right">Invt. Pokok</th>
          <th>Catatan</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td class="center" colspan="<?= $tab==='angkutan' ? 8 : 10 ?>">Tidak ada data.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <?php if ($tab==='angkutan'): ?>
          <tr>
            <td><?= h($r['kebun_nama'] ?? '-') ?></td>
            <td><?= h($r['gudang_asal'] ?? '') ?></td>
            <td><?= h($r['unit_tujuan_nama'] ?? '-') ?></td>
            <td><?= h($r['tanggal'] ?? '') ?></td>
            <td><?= h($r['jenis_pupuk'] ?? '') ?></td>
            <td class="right"><?= number_format((float)($r['jumlah'] ?? 0), 2, ',', '.') ?></td>
            <td><?= h($r['nomor_do'] ?? '') ?></td>
            <td><?= h($r['supir'] ?? '') ?></td>
          </tr>
        <?php else: ?>
          <tr>
            <td><?= h($r['kebun_nama'] ?? '-') ?></td>
            <td><?= h($r['unit_nama'] ?? '-') ?></td>
            <td><?= h($r['blok'] ?? '') ?></td>
            <td><?= h($r['tanggal'] ?? '') ?></td>
            <td><?= h($r['jenis_pupuk'] ?? '') ?></td>
            <td class="right">
              <?php
                if (array_key_exists('dosis',$r) && $r['dosis']!==null && $r['dosis']!=='') echo number_format((float)$r['dosis'],2,',','.');
                else echo '-';
              ?>
            </td>
            <td class="right"><?= number_format((float)($r['jumlah'] ?? 0), 2, ',', '.') ?></td>
            <td class="right"><?= number_format((float)($r['luas'] ?? 0), 2, ',', '.') ?></td>
            <td class="right"><?= number_format((int)($r['invt_pokok'] ?? 0), 0, ',', '.') ?></td>
            <td><?= h($r['catatan'] ?? '') ?></td>
          </tr>
        <?php endif; ?>
      <?php endforeach; endif; ?>
    </tbody>

    <?php if (!empty($rows)): ?>
      <?php if ($tab==='angkutan'): // FOOTER TOTAL ANGKUTAN ?>
        <tfoot>
          <tr>
            <td colspan="5" class="right">TOTAL</td>
            <td class="right"><?= number_format($tot_jumlah, 2, ',', '.') ?></td>
            <td></td>
            <td></td>
          </tr>
        </tfoot>
      <?php else: // FOOTER TOTAL MENABUR ?>
        <tfoot>
          <tr>
            <td colspan="5" class="right">TOTAL</td>
            <td class="right"><?= number_format($avg_dosis, 2, ',', '.') ?></td>
            <td class="right"><?= number_format($tot_jumlah, 2, ',', '.') ?></td>
            <td class="right"><?= number_format($tot_luas,   2, ',', '.') ?></td>
            <td class="right"><?= number_format($tot_invt,   0, ',', '.') ?></td>
            <td></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    <?php endif; ?>
  </table>

  <div class="foot">
    <strong>Filter:</strong>
    Tab <?= h($tab) ?> | Unit: <?= h($unitNama) ?> | Kebun: <?= h($kebunNama) ?>
  </div>
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

$fname = 'Pemupukan_Organik_'.$tab;
if ($f_unit_id!=='')  $fname .= '_UNIT-'.$f_unit_id;
if ($f_kebun_id!=='') $fname .= '_KEBUN-'.$f_kebun_id;
$fname .= '.pdf';

$dompdf->stream($fname, ['Attachment'=>true]);
