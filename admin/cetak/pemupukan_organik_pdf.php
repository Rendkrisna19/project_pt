<?php
// admin/cetak/pemupukan_organik_pdf.php
// PDF Pemupukan Organik (Menabur/Angkutan) — filter terbaru: tahun, kebun, tanggal, periode, unit, keterangan(angkutan), jenis_pupuk
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$db  = new Database();
$pdo = $db->getConnection();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function qint($v){ return ($v===''||$v===null) ? null : (int)$v; }
function qstr($v){ $v = trim((string)$v); return $v==='' ? null : $v; }

$bulanNama = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

// ============ Params & Query ============
$tab = $_GET['tab'] ?? 'menabur';
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

$f_tahun      = qint($_GET['tahun']      ?? null);
$f_unit_id    = qint($_GET['unit_id']    ?? null);
$f_kebun_id   = qint($_GET['kebun_id']   ?? null);
$f_tanggal    = qstr($_GET['tanggal']    ?? null);
$f_periode    = qint($_GET['periode']    ?? null);
$f_keterangan = qstr($_GET['keterangan'] ?? null); // only angkutan
$f_jenis      = qstr($_GET['jenis_pupuk']?? null);

if ($tab === 'angkutan') {
  $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama, k.nama_kebun AS kebun_nama,
                 YEAR(a.tanggal) AS tahun, MONTH(a.tanggal) AS bulan
          FROM angkutan_pupuk_organik a
          LEFT JOIN units u ON u.id = a.unit_tujuan_id
          LEFT JOIN md_kebun k ON k.id = a.kebun_id
          WHERE 1=1";
  $p = [];
  if ($f_tahun   !== null) { $sql .= " AND YEAR(a.tanggal)=:th"; $p[':th'] = $f_tahun; }
  if ($f_unit_id !== null) { $sql .= " AND a.unit_tujuan_id = :uid"; $p[':uid'] = $f_unit_id; }
  if ($f_kebun_id!== null) { $sql .= " AND a.kebun_id = :kid";      $p[':kid'] = $f_kebun_id; }
  if ($f_tanggal !== null) { $sql .= " AND a.tanggal  = :tgl";      $p[':tgl'] = $f_tanggal; }
  if ($f_periode !== null) { $sql .= " AND MONTH(a.tanggal) = :bln";$p[':bln'] = $f_periode; }
  if ($f_jenis   !== null) { $sql .= " AND a.jenis_pupuk = :jp";    $p[':jp']  = $f_jenis; }
  if ($f_keterangan!== null){$sql .= " AND a.keterangan LIKE :ket"; $p[':ket'] = "%{$f_keterangan}%"; }
  $sql .= " ORDER BY a.tanggal DESC, a.id DESC";
  $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $judul = "Data Angkutan Pupuk Organik";

  // Totals
  $tot_jumlah = 0.0;
  foreach ($rows as $r) $tot_jumlah += (float)($r['jumlah'] ?? 0);

} else {
  $sql = "SELECT m.*, u.nama_unit AS unit_nama, k.nama_kebun AS kebun_nama,
                 YEAR(m.tanggal) AS tahun, MONTH(m.tanggal) AS bulan
          FROM menabur_pupuk_organik m
          LEFT JOIN units u ON u.id = m.unit_id
          LEFT JOIN md_kebun k ON k.id = m.kebun_id
          WHERE 1=1";
  $p = [];
  if ($f_tahun   !== null) { $sql .= " AND YEAR(m.tanggal)=:th"; $p[':th'] = $f_tahun; }
  if ($f_unit_id !== null) { $sql .= " AND m.unit_id = :uid";    $p[':uid'] = $f_unit_id; }
  if ($f_kebun_id!== null) { $sql .= " AND m.kebun_id = :kid";   $p[':kid'] = $f_kebun_id; }
  if ($f_tanggal !== null) { $sql .= " AND m.tanggal  = :tgl";   $p[':tgl'] = $f_tanggal; }
  if ($f_periode !== null) { $sql .= " AND MONTH(m.tanggal) = :bln"; $p[':bln'] = $f_periode; }
  if ($f_jenis   !== null) { $sql .= " AND m.jenis_pupuk = :jp"; $p[':jp']  = $f_jenis; }
  $sql .= " ORDER BY m.tanggal DESC, m.id DESC";
  $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $judul = "Data Penaburan Pupuk Organik";

  // Totals
  $tot_jumlah = 0.0; $tot_luas = 0.0; $sum_dosis = 0.0; $cnt_dosis = 0;
  foreach ($rows as $r) {
    $tot_jumlah += (float)($r['jumlah'] ?? 0);
    $tot_luas   += (float)($r['luas'] ?? 0);
    if (array_key_exists('dosis',$r) && $r['dosis']!=='') { $sum_dosis += (float)$r['dosis']; $cnt_dosis++; }
  }
  $avg_dosis = $cnt_dosis>0 ? $sum_dosis/$cnt_dosis : 0.0;
}

// Ambil nama unit/kebun utk footnote filter
$unitNama = 'Semua Unit';
if ($f_unit_id !== null) {
  $s = $pdo->prepare("SELECT nama_unit FROM units WHERE id=:id"); $s->execute([':id'=>$f_unit_id]);
  $unitNama = $s->fetchColumn() ?: ('#'.$f_unit_id);
}
$kebunNama = 'Semua Kebun';
if ($f_kebun_id !== null) {
  $s2 = $pdo->prepare("SELECT nama_kebun FROM md_kebun WHERE id=:id"); $s2->execute([':id'=>$f_kebun_id]);
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
  .brand { background:#065f46; color:#fff; padding:12px 16px; border-radius:10px; text-align:center; margin-bottom:10px; }
  .brand h1 { margin:0; font-size:18px; letter-spacing:.4px; }
  .sub { text-align:center; color:#065f46; font-weight:bold; margin:6px 0 12px 0; font-size:14px; }

  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #e0e6e3; padding:6px 8px; }
  thead th { background:#d1fae5; color:#064e3b; font-weight:700; text-transform:uppercase; font-size:10px; }
  tbody tr:nth-child(even) { background:#fbfdfc; }
  tfoot td { background:#e8f5e9; font-weight:700; }
  .right { text-align:right; }
  .center{ text-align:center; }
  .foot { margin-top:8px; font-size:10px; color:#555; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN IV REGIONAL 3</h1></div>
  <div class="sub"><?= h($judul) ?></div>

  <table>
    <thead>
      <?php if ($tab==='angkutan'): ?>
        <tr>
          <th>Tahun</th>
          <th>Kebun</th>
          <th>Tanggal</th>
          <th>Periode</th>
          <th>Gudang Asal</th>
          <th>Unit Tujuan</th>
          <th>Jenis Pupuk</th>
          <th class="right">Jumlah (Kg)</th>
          <th>Nomor DO</th>
          <th>Supir</th>
          <th>Keterangan</th>
        </tr>
      <?php else: ?>
        <tr>
          <th>Tahun</th>
          <th>Kebun</th>
          <th>Tanggal</th>
          <th>Periode</th>
          <th>Unit/Defisi</th>
          <th>T. Tanam</th>
          <th>Blok</th>
          <th class="right">Luas (Ha)</th>
          <th>Jenis Pupuk</th>
          <th class="right">Dosis</th>
          <th class="right">Kilogram</th>
        </tr>
      <?php endif; ?>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td class="center" colspan="<?= $tab==='angkutan' ? 11 : 11 ?>">Tidak ada data.</td></tr>
      <?php else: foreach($rows as $r):
        $bulan = (int)($r['bulan'] ?? 0);
        $periode = ($bulan>=1 && $bulan<=12) ? ($bulan.' - '.$bulanNama[$bulan]) : '-';
      ?>
        <?php if ($tab==='angkutan'): ?>
          <tr>
            <td><?= h($r['tahun'] ?? '') ?></td>
            <td><?= h($r['kebun_nama'] ?? '-') ?></td>
            <td><?= h($r['tanggal'] ?? '') ?></td>
            <td><?= h($periode) ?></td>
            <td><?= h($r['gudang_asal'] ?? '') ?></td>
            <td><?= h($r['unit_tujuan_nama'] ?? '-') ?></td>
            <td><?= h($r['jenis_pupuk'] ?? '') ?></td>
            <td class="right"><?= number_format((float)($r['jumlah'] ?? 0), 2, ',', '.') ?></td>
            <td><?= h($r['nomor_do'] ?? '') ?></td>
            <td><?= h($r['supir'] ?? '') ?></td>
            <td><?= h($r['keterangan'] ?? '') ?></td>
          </tr>
        <?php else: ?>
          <tr>
            <td><?= h($r['tahun'] ?? '') ?></td>
            <td><?= h($r['kebun_nama'] ?? '-') ?></td>
            <td><?= h($r['tanggal'] ?? '') ?></td>
            <td><?= h($periode) ?></td>
            <td><?= h($r['unit_nama'] ?? '-') ?></td>
            <td><?= h(array_key_exists('t_tanam',$r)?($r['t_tanam']??''):'') ?></td>
            <td><?= h($r['blok'] ?? '') ?></td>
            <td class="right"><?= number_format((float)($r['luas'] ?? 0), 2, ',', '.') ?></td>
            <td><?= h($r['jenis_pupuk'] ?? '') ?></td>
            <td class="right"><?= ($r['dosis']===''||$r['dosis']===null) ? '-' : number_format((float)$r['dosis'], 2, ',', '.') ?></td>
            <td class="right"><?= number_format((float)($r['jumlah'] ?? 0), 2, ',', '.') ?></td>
          </tr>
        <?php endif; ?>
      <?php endforeach; endif; ?>
    </tbody>

    <?php if (!empty($rows)): ?>
      <?php if ($tab==='angkutan'): ?>
        <tfoot>
          <tr>
            <td colspan="7" class="right">TOTAL</td>
            <td class="right"><?= number_format($tot_jumlah, 2, ',', '.') ?></td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
      <?php else: ?>
        <tfoot>
          <tr>
            <td colspan="7" class="right">TOTAL</td>
            <td class="right"><?= number_format($tot_luas,   2, ',', '.') ?></td>
            <td></td>
            <td class="right"><?= number_format($avg_dosis,  2, ',', '.') ?></td>
            <td class="right"><?= number_format($tot_jumlah, 2, ',', '.') ?></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    <?php endif; ?>
  </table>

  <div class="foot">
    <strong>Filter:</strong>
    Tab <?= h($tab) ?>
    <?php if ($f_tahun!==null): ?> | Tahun: <?= h($f_tahun) ?><?php endif; ?>
    | Unit: <?= h($unitNama) ?> | Kebun: <?= h($kebunNama) ?>
    <?php if ($f_periode!==null): ?> | Periode: <?= h($f_periode) ?><?php endif; ?>
    <?php if ($f_tanggal!==null): ?> | Tanggal: <?= h($f_tanggal) ?><?php endif; ?>
    <?php if ($f_jenis!==null): ?> | Jenis: <?= h($f_jenis) ?><?php endif; ?>
    <?php if ($tab==='angkutan' && $f_keterangan!==null): ?> | Keterangan: <?= h($f_keterangan) ?><?php endif; ?>
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

$fname = 'Pemupukan_Organik_'.$tab
        .($f_tahun   ? '_THN-'.$f_tahun   : '')
        .($f_unit_id ? '_UNIT-'.$f_unit_id: '')
        .($f_kebun_id? '_KEBUN-'.$f_kebun_id: '')
        .($f_periode ? '_BLN-'.$f_periode: '')
        .'.pdf';

$dompdf->stream($fname, ['Attachment'=>true]);
