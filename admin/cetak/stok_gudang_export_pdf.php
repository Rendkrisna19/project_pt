<?php
// pages/cetak/stok_gudang_export_pdf.php
// PDF rekap stok gudang (mengikuti filter GET: kebun_id, bahan_id, bulan, tahun) — header hijau PTPN IV REGIONAL 3
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db  = new Database();
$pdo = $db->getConnection();

/* ===== Params ===== */
$kebun_id = ($_GET['kebun_id'] ?? '') === '' ? '' : (int)$_GET['kebun_id'];
$bahan_id = ($_GET['bahan_id'] ?? '') === '' ? '' : (int)$_GET['bahan_id'];
$bulan    = trim((string)($_GET['bulan'] ?? '')); // boleh kosong = semua
$tahun    = (int)($_GET['tahun'] ?? date('Y'));

/* ===== Query =====
   Asumsi tabel rekap bernama `stok_gudang` dengan kolom:
   id, kebun_id, bahan_id, bulan, tahun, stok_awal, mutasi_masuk, mutasi_keluar, pasokan, dipakai
*/
$sql = "SELECT
          sg.*,
          k.kode AS kebun_kode, k.nama_kebun,
          b.kode AS bahan_kode, b.nama_bahan,
          s.nama AS satuan
        FROM stok_gudang sg
        JOIN md_kebun k ON k.id = sg.kebun_id
        JOIN md_bahan_kimia b ON b.id = sg.bahan_id
        JOIN md_satuan s ON s.id = b.satuan_id
        WHERE sg.tahun = :thn";
$P = [':thn'=>$tahun];

if ($kebun_id !== '') { $sql .= " AND sg.kebun_id = :kid"; $P[':kid'] = (int)$kebun_id; }
if ($bahan_id !== '') { $sql .= " AND sg.bahan_id = :bid"; $P[':bid'] = (int)$bahan_id; }
if ($bulan !== '')    { $sql .= " AND sg.bulan = :bln";   $P[':bln'] = $bulan; }

$sql .= " ORDER BY k.nama_kebun, b.nama_bahan, FIELD(sg.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), sg.id";

$st = $pdo->prepare($sql);
$st->execute($P);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== Nama filter ringkas ===== */
$kebunNama = 'Semua Kebun';
if ($kebun_id !== '') {
  $t = $pdo->prepare("SELECT CONCAT(kode,' — ',nama_kebun) FROM md_kebun WHERE id=:id");
  $t->execute([':id'=>(int)$kebun_id]);
  $kebunNama = $t->fetchColumn() ?: ('#'.$kebun_id);
}
$bahanNama = 'Semua Bahan';
if ($bahan_id !== '') {
  $t = $pdo->prepare("SELECT CONCAT(kode,' — ',nama_bahan) FROM md_bahan_kimia WHERE id=:id");
  $t->execute([':id'=>(int)$bahan_id]);
  $bahanNama = $t->fetchColumn() ?: ('#'.$bahan_id);
}

/* ===== HTML ===== */
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Stok Gudang</title>
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
  .right { text-align:right; }
  .center{ text-align:center; }
  .foot { margin-top:8px; font-size:10px; color:#666; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN IV REGIONAL 3</h1></div>
  <div class="sub">Rekap Stok Gudang Bahan Kimia</div>

  <table>
    <thead>
      <tr>
        <th>Kebun</th>
        <th>Bahan (Satuan)</th>
        <th>Periode</th>
        <th class="right">Stok Awal</th>
        <th class="right">Mutasi Masuk</th>
        <th class="right">Mutasi Keluar</th>
        <th class="right">Pasokan</th>
        <th class="right">Dipakai</th>
        <th class="right">Net Mutasi</th>
        <th class="right">Sisa Stok</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td class="center" colspan="10">Tidak ada data.</td></tr>
      <?php else: foreach ($rows as $r):
        $stok_awal = (float)($r['stok_awal'] ?? 0);
        $masuk     = (float)($r['mutasi_masuk'] ?? 0);
        $keluar    = (float)($r['mutasi_keluar'] ?? 0);
        $pasokan   = (float)($r['pasokan'] ?? 0);
        $dipakai   = (float)($r['dipakai'] ?? 0);
        $net       = ($masuk - $keluar) + ($pasokan - $dipakai);
        $sisa      = $stok_awal + $net;
      ?>
      <tr>
        <td><?= h(($r['kebun_kode'] ?? '').' — '.($r['nama_kebun'] ?? '')) ?></td>
        <td><?= h(($r['bahan_kode'] ?? '').' — '.($r['nama_bahan'] ?? '').' ('.($r['satuan'] ?? '').')') ?></td>
        <td class="center"><?= h(($r['bulan'] ?? '').' '.($r['tahun'] ?? '')) ?></td>
        <td class="right"><?= number_format($stok_awal, 2, ',', '.') ?></td>
        <td class="right"><?= number_format($masuk, 2, ',', '.') ?></td>
        <td class="right"><?= number_format($keluar, 2, ',', '.') ?></td>
        <td class="right"><?= number_format($pasokan, 2, ',', '.') ?></td>
        <td class="right"><?= number_format($dipakai, 2, ',', '.') ?></td>
        <td class="right"><?= number_format($net, 2, ',', '.') ?></td>
        <td class="right"><strong><?= number_format($sisa, 2, ',', '.') ?></strong></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <div class="foot">
    <strong>Filter:</strong>
    Kebun: <?= h($kebunNama) ?> | Bahan: <?= h($bahanNama) ?> |
    Bulan: <?= h($bulan !== '' ? $bulan : 'Semua Bulan') ?> | Tahun: <?= h($tahun) ?>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

/* ===== Dompdf ===== */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$fname = 'Stok_Gudang';
if ($kebun_id!=='') $fname .= '_K'.$kebun_id;
if ($bahan_id!=='') $fname .= '_B'.$bahan_id;
if ($bulan!=='')    $fname .= '_'.$bulan;
$fname .= '_'.$tahun.'.pdf';

$dompdf->stream($fname, ['Attachment'=>true]);
