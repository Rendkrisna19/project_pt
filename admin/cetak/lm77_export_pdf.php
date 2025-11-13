<?php
// admin/cetak/lm77_export_pdf.php
// Export PDF LM-77 — ambil sumber dari LM-76 (turunan), ikut filter UI

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
  $db  = new Database();
  $pdo = $db->getConnection();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  /* ============== FILTERS ============== */
  $unit_id    = (isset($_GET['unit_id'])    && $_GET['unit_id']    !== '') ? (int)$_GET['unit_id'] : null;
  $bulan      = (isset($_GET['bulan'])      && $_GET['bulan']      !== '') ? trim((string)$_GET['bulan']) : null;
  $tahun      = (isset($_GET['tahun'])      && $_GET['tahun']      !== '') ? (int)$_GET['tahun'] : null;

  // Utama dari UI
  $kebun_id   = (isset($_GET['kebun_id'])   && $_GET['kebun_id']   !== '') ? (int)$_GET['kebun_id'] : null;
  // Legacy opsional: jika ada kebun_kode, juga difilter
  $kebun_kode = (isset($_GET['kebun_kode']) && $_GET['kebun_kode'] !== '') ? trim((string)$_GET['kebun_kode']) : null;

  $bulanAllow = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  if ($bulan !== null && !in_array($bulan, $bulanAllow, true)) $bulan = null;

  /* ============== QUERY (Sumber: LM-76) ============== */
  // lm76 kolom yang digunakan: tahun, bulan, tt, luas_ha, jumlah_pohon, anggaran_kg, realisasi_kg, jumlah_tandan, jumlah_hk, panen_ha, unit_id, kebun_id
  $sql = "
    SELECT
      l76.tahun, l76.bulan, l76.tt,
      l76.luas_ha, l76.jumlah_pohon, l76.anggaran_kg, l76.realisasi_kg,
      l76.jumlah_tandan, l76.jumlah_hk, l76.panen_ha,
      u.nama_unit,
      k.nama_kebun, k.kode AS kebun_kode
    FROM lm76 l76
    LEFT JOIN units u     ON u.id = l76.unit_id
    LEFT JOIN md_kebun k  ON k.id = l76.kebun_id
    WHERE 1=1
  ";
  $bind = [];

  if ($unit_id !== null)  { $sql .= " AND l76.unit_id = :uid"; $bind[':uid'] = $unit_id; }
  if ($bulan   !== null)  { $sql .= " AND l76.bulan   = :bln"; $bind[':bln'] = $bulan; }
  if ($tahun   !== null)  { $sql .= " AND l76.tahun   = :thn"; $bind[':thn'] = $tahun; }
  if ($kebun_id !== null) { $sql .= " AND l76.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }
  if ($kebun_kode !== null) { $sql .= " AND k.kode = :kb"; $bind[':kb'] = $kebun_kode; }

  $sql .= "
    ORDER BY
      l76.tahun DESC,
      FIELD(l76.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
      u.nama_unit ASC,
      k.nama_kebun ASC,
      l76.tt ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($bind);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Label filter untuk header
  $label_unit  = null;
  if ($unit_id !== null) {
    $q = $pdo->prepare("SELECT nama_unit FROM units WHERE id = ?");
    $q->execute([$unit_id]);
    $label_unit = $q->fetchColumn() ?: (string)$unit_id;
  }
  $label_kebun = null;
  if ($kebun_id !== null) {
    $q = $pdo->prepare("SELECT nama_kebun FROM md_kebun WHERE id = ?");
    $q->execute([$kebun_id]);
    $label_kebun = $q->fetchColumn() ?: (string)$kebun_id;
  } elseif ($kebun_kode !== null) {
    $q = $pdo->prepare("SELECT nama_kebun FROM md_kebun WHERE kode = ?");
    $q->execute([$kebun_kode]);
    $label_kebun = $q->fetchColumn() ?: $kebun_kode;
  }

} catch (Throwable $e) {
  http_response_code(500);
  exit('DB Error: '.$e->getMessage());
}

/* ============== VIEW (HTML) ============== */
function nf($v, int $d = 2, string $dash='-') {
  if ($v === null || $v === '' || !is_numeric($v)) return $dash;
  return number_format((float)$v, $d);
}
function toNum($v){ $x = (float)$v; return is_nan($x) ? 0.0 : $x; }

ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 14mm 12mm; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#111; }
  .brand { background:#22c55e; color:#fff; padding:10px 14px; border-radius:8px; text-align:center; margin-bottom:6px; }
  .brand h1 { margin:0; font-size:18px; }
  .subtitle { text-align:center; font-weight:700; color:#065f46; margin:4px 0 10px; }
  .filters { margin: 6px 0 10px; font-size:10px; color:#064e3b; }
  .filters span { display:inline-block; margin-right:12px; }
  table { width:100%; border-collapse: collapse; table-layout: fixed; }
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

  <div class="filters">
    <?php if ($label_kebun !== null): ?><span><strong>Kebun:</strong> <?= htmlspecialchars($label_kebun) ?></span><?php endif; ?>
    <?php if ($label_unit  !== null): ?><span><strong>Unit:</strong> <?= htmlspecialchars($label_unit) ?></span><?php endif; ?>
    <?php if ($bulan       !== null): ?><span><strong>Bulan:</strong> <?= htmlspecialchars($bulan) ?></span><?php endif; ?>
    <?php if ($tahun       !== null): ?><span><strong>Tahun:</strong> <?= (int)$tahun ?></span><?php endif; ?>
    <span><strong>Dicetak:</strong> <?= date('Y-m-d H:i') ?></span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:13%">Kebun</th>
        <th style="width:10%">Unit</th>
        <th style="width:11%">Periode</th>
        <th style="width:10%">T. Tanam</th>
        <th style="width:7%">Luas (Ha)</th>
        <th style="width:7%">Pohon</th>
        <th style="width:9%">Variance (%) BI/SD</th>
        <th style="width:9%">Tandan/Pohon (BI/SD)</th>
        <th style="width:11%">Prod Ton/Ha (BI/SD THI/TL)</th>
        <th style="width:9%">BTR (BI/SD THI/TL)</th>
        <th style="width:7%">Prestasi Kg/HK</th>
        <th style="width:9%">Prestasi Tandan/HK</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="12">Belum ada data.</td></tr>
      <?php else: foreach ($rows as $r):
        $luas   = toNum($r['luas_ha'] ?? 0);
        $pokok  = toNum($r['jumlah_pohon'] ?? 0);
        $angg   = toNum($r['anggaran_kg'] ?? 0);
        $real   = toNum($r['realisasi_kg'] ?? 0);
        $tandan = toNum($r['jumlah_tandan'] ?? 0);
        $hk     = toNum($r['jumlah_hk'] ?? 0);

        // Hitungan turunan ala LM-77
        $varPct = ($angg>0) ? (($real/$angg)*100 - 100) : null;
        $tpp    = ($pokok>0) ? ($tandan/$pokok) : null;
        $prot   = ($luas>0)  ? (($real/$luas)/1000) : null; // ton/ha
        $btr    = ($tandan>0)? ($real/$tandan) : null;
        $kgHK   = ($hk>0)    ? ($real/$hk) : null;
        $tdnHK  = ($hk>0)    ? ($tandan/$hk) : null;
      ?>
        <tr>
          <td class="wrap">
            <?= htmlspecialchars($r['nama_kebun'] ?? '-') ?>
            <?php if (!empty($r['kebun_kode'])): ?> (<?= htmlspecialchars($r['kebun_kode']) ?>)<?php endif; ?>
          </td>
          <td><?= htmlspecialchars($r['nama_unit'] ?? '-') ?></td>
          <td><?= htmlspecialchars(($r['bulan'] ?? '-').' '.($r['tahun'] ?? '-')) ?></td>
          <td><?= htmlspecialchars($r['tt'] ?? '-') ?></td>

          <td class="text-right"><?= nf($luas, 2) ?></td>
          <td class="text-right"><?= nf($pokok, 0) ?></td>

          <td class="text-right"><?= ($varPct!==null? nf($varPct, 2).'%' : '-') ?></td>
          <td class="text-right"><?= ($tpp!==null   ? nf($tpp, 4)       : '-') ?></td>
          <td class="text-right"><?= ($prot!==null  ? nf($prot, 2)      : '-') ?></td>
          <td class="text-right"><?= ($btr!==null   ? nf($btr, 2)       : '-') ?></td>
          <td class="text-right"><?= ($kgHK!==null  ? nf($kgHK, 2)      : '-') ?></td>
          <td class="text-right"><?= ($tdnHK!==null ? nf($tdnHK, 2)     : '-') ?></td>
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
$options->set('isHtml5ParserEnabled', true);
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('lm77_'.date('Ymd_His').'.pdf', ['Attachment'=>false]);
exit;
