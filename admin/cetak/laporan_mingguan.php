<?php
// admin/cetak/laporan_mingguan_pdf.php
// Export PDF: Laporan Mingguan per AFD (PDO + Dompdf)

declare(strict_types=1);
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  http_response_code(403);
  exit('Unauthorized');
}

/* ==== Hardening output supaya stream PDF bersih ==== */
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }
error_reporting(E_ERROR | E_PARSE);

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function norm_bulan(string $b): string {
  $map = [
    'january'=>'Januari','february'=>'Februari','march'=>'Maret','april'=>'April',
    'may'=>'Mei','june'=>'Juni','july'=>'Juli','august'=>'Agustus','september'=>'September',
    'october'=>'Oktober','november'=>'November','december'=>'Desember',
    'januari'=>'Januari','februari'=>'Februari','maret'=>'Maret','mei'=>'Mei',
    'juni'=>'Juni','juli'=>'Juli','agustus'=>'Agustus','oktober'=>'Oktober','november'=>'November','desember'=>'Desember'
  ];
  $k = strtolower(trim($b));
  return $map[$k] ?? 'Januari';
}

try {
  /* ===== Ambil filter dari query string ===== */
  $kebun_id = $_GET['kebun_id'] ?? '';
  $unit_id  = $_GET['unit_id'] ?? '';
  $jenis_id = $_GET['jenis_pekerjaan_id'] ?? '';
  $bulan    = norm_bulan($_GET['bulan'] ?? date('F'));
  $tahun    = (int)($_GET['tahun'] ?? date('Y'));
  $minggu   = (int)($_GET['minggu'] ?? 1);
  if ($minggu < 1 || $minggu > 5) $minggu = 1;

  /* ===== Koneksi ===== */
  $db  = new Database();
  $pdo = $db->getConnection();

  /* ===== Nama Kebun / Unit / Jenis ===== */
  $namaKebun = $pdo->prepare("SELECT UPPER(nama_kebun) nk FROM md_kebun WHERE id=:id");
  $namaKebun->execute([':id'=>$kebun_id]);
  $NK = $namaKebun->fetch(PDO::FETCH_ASSOC)['nk'] ?? '';

  $namaUnit = $pdo->prepare("SELECT nama_unit FROM units WHERE id=:id");
  $namaUnit->execute([':id'=>$unit_id]);
  $NU = $namaUnit->fetch(PDO::FETCH_ASSOC)['nama_unit'] ?? '';

  $namaJenis = $pdo->prepare("SELECT UPPER(nama) nj FROM md_jenis_pekerjaan WHERE id=:id");
  $namaJenis->execute([':id'=>$jenis_id]);
  $NJ = $namaJenis->fetch(PDO::FETCH_ASSOC)['nj'] ?? '';

  /* ===== Meta laporan ===== */
  $stMeta = $pdo->prepare("
    SELECT judul_laporan, catatan,
      COALESCE(judul_minggu_1,'MINGGU I') jm1,
      COALESCE(judul_minggu_2,'MINGGU II') jm2,
      COALESCE(judul_minggu_3,'MINGGU III') jm3,
      COALESCE(judul_minggu_4,'MINGGU IV') jm4,
      COALESCE(judul_minggu_5,'MINGGU V') jm5
    FROM laporan_mingguan_meta
    WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b
    LIMIT 1
  ");
  $stMeta->execute([':k'=>$kebun_id,':jp'=>$jenis_id,':t'=>$tahun,':b'=>$bulan]);
  $meta = $stMeta->fetch(PDO::FETCH_ASSOC) ?: [];
  $judulLaporan = ($meta['judul_laporan'] ?? 'LAPORAN PEMELIHARAAN KEBUN') . ($NK ? " $NK" : '');
  $judulMinggu  = $meta['jm'.$minggu] ?? ('MINGGU '.$minggu);
  $catatan      = $meta['catatan'] ?? 'BATAS AKHIR PENGISIAN SETIAP HARI SABTU JAM 9 PAGI';

  /* ===== Data detail ===== */
  $st = $pdo->prepare("
    SELECT blok, ts, pkwt, kng, tp
    FROM laporan_mingguan
    WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b AND minggu=:m AND afdeling=:afd
    ORDER BY blok
  ");
  $st->execute([
    ':k'=>$kebun_id, ':jp'=>$jenis_id, ':t'=>$tahun, ':b'=>$bulan, ':m'=>$minggu, ':afd'=>$unit_id
  ]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Hitung total
  $tot = ['ts'=>0.0,'pkwt'=>0.0,'kng'=>0.0,'tp'=>0.0];
  foreach ($rows as $r) {
    $tot['ts']   += (float)($r['ts'] ?? 0);
    $tot['pkwt'] += (float)($r['pkwt'] ?? 0);
    $tot['kng']  += (float)($r['kng'] ?? 0);
    $tot['tp']   += (float)($r['tp'] ?? 0);
  }
  $grand = $tot['ts'] + $tot['pkwt'] + $tot['kng'] + $tot['tp'];

  // HTML table rows
  $trs = '';
  if ($rows) {
    foreach ($rows as $r) {
      $rowTotal = (float)$r['ts'] + (float)$r['pkwt'] + (float)$r['kng'] + (float)$r['tp'];
      $trs .= '<tr>
        <td class="c">'.$NU.'</td>
        <td>'.htmlspecialchars($r['blok'] ?? '').'</td>
        <td class="r">'.number_format((float)$r['ts'],2).'</td>
        <td class="r">'.number_format((float)$r['pkwt'],2).'</td>
        <td class="r">'.number_format((float)$r['kng'],2).'</td>
        <td class="r">'.number_format((float)$r['tp'],2).'</td>
        <td class="r">'.number_format($rowTotal,2).'</td>
      </tr>';
    }
  } else {
    $trs .= '<tr><td class="c" colspan="7" style="color:#6B7280;padding:14px">Tidak ada data untuk filter ini.</td></tr>';
  }

  // HTML & CSS
  $periode = 'BULAN '.strtoupper($bulan).' '.$tahun;
  $catatanHtml = htmlspecialchars($catatan);

  $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { size: A4 portrait; margin: 18mm 14mm 16mm 14mm; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#111827; }
  h1,h2,h3 { margin: 4px 0; }
  .title { text-align:center; font-weight:700; font-size:14px; }
  .subtitle { text-align:center; font-weight:700; font-size:12px; }
  .note { text-align:center; color:#B91C1C; font-weight:700; margin:6px 0 12px; }
  table { width:100%; border-collapse:collapse; }
  thead { display: table-header-group; }
  th, td { border:1px solid #9CA3AF; padding:6px 8px; }
  th { background:#E5E7EB; font-weight:700; text-align:center; }
  td.c { text-align:center; }
  td.r { text-align:right; }
  tfoot td { font-weight:700; background:#FEF3C7; }
  .small { font-size:10px; color:#6B7280; }
</style>
</head>
<body>
  <div class="title">{$judulLaporan}</div>
  <div class="subtitle">{$NJ}</div>
  <div class="subtitle">{$periode}</div>
  <div class="subtitle">{$judulMinggu}</div>
  <div class="note">{$catatanHtml}</div>

  <table>
    <thead>
      <tr>
        <th style="width:16%">AFD</th>
        <th style="width:20%">Blok</th>
        <th style="width:12%">TS</th>
        <th style="width:12%">PKWT</th>
        <th style="width:12%">KNG</th>
        <th style="width:12%">TP</th>
        <th style="width:16%">JUMLAH</th>
      </tr>
    </thead>
    <tbody>
      {$trs}
    </tbody>
    <tfoot>
      <tr>
        <td class="c">JUMLAH</td>
        <td></td>
        <td class="r">{$tot['ts']}</td>
        <td class="r">{$tot['pkwt']}</td>
        <td class="r">{$tot['kng']}</td>
        <td class="r">{$tot['tp']}</td>
        <td class="r">{$grand}</td>
      </tr>
    </tfoot>
  </table>

  <div class="small" style="margin-top:8px">
    Dicetak: {date('d/m/Y H:i')} • AFD: {$NU}
  </div>
</body>
</html>
HTML;

  // Perapihan angka total di footer (format 2 desimal)
  $html = str_replace(
    [$tot['ts'], $tot['pkwt'], $tot['kng'], $tot['tp'], $grand],
    [
      number_format($tot['ts'],2),
      number_format($tot['pkwt'],2),
      number_format($tot['kng'],2),
      number_format($tot['tp'],2),
      number_format($grand,2),
    ],
    $html
  );

  /* ===== Render PDF ===== */
  $opt = new Options();
  $opt->set('isRemoteEnabled', true);
  $opt->set('isHtml5ParserEnabled', true);
  $dompdf = new Dompdf($opt);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();

  $fname = 'laporan_mingguan_'.date('Ymd_His').'.pdf';

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.$fname.'"');
  echo $dompdf->output();
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'status'  => 'error',
    'message' => 'Gagal membuat PDF.',
    'detail'  => $e->getMessage()
  ]);
  exit;
}
