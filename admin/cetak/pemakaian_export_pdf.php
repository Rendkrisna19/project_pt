<?php
// admin/cetak/pemakaian_export_pdf.php
// PDF export Pemakaian Bahan Kimia (mengikuti semua filter)
// Theme hijau + header "PTPN 4 REGIONAL 3", TANPA info pencetak & tanggal.
// Parse [Kebun: ..] & [Fisik: ..] -> kebun_label, fisik_label, keterangan_clean

declare(strict_types=1);

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ================= Helpers ================= */
function likeParam(string $s): string {
  return '%'.str_replace(['%','_'], ['\\%','\\_'], trim($s)).'%';
}
function extract_tag_anywhere($ket, $label){
  if (!is_string($ket)) $ket = (string)$ket;
  $pattern = '/\[\s*' . preg_quote($label,'/') . '\s*:\s*([^\]]+)\]\s*/iu';
  if (preg_match($pattern, $ket, $m)) {
    $val = trim($m[1]);
    $clean = preg_replace($pattern, '', $ket, 1);
    return [$val !== '' ? $val : null, trim($clean)];
  }
  return [null, trim($ket)];
}
function parse_labels_and_clean($ketRaw){
  // urutan tag bebas & toleran
  [$kebun, $rest]  = extract_tag_anywhere($ketRaw, 'Kebun');
  [$fisik, $clean] = extract_tag_anywhere($rest,   'Fisik');
  if ($kebun === null && $fisik === null) {
    [$fisik2, $rest2] = extract_tag_anywhere($ketRaw, 'Fisik');
    [$kebun2, $clean2]= extract_tag_anywhere($rest2,  'Kebun');
    $kebun=$kebun2; $fisik=$fisik2; $clean=$clean2;
  }
  return [$kebun, $fisik, trim($clean ?? (string)$ketRaw)];
}
function validYear($y): bool {
  return (bool)preg_match('/^(19[7-9]\d|20\d{2}|2100)$/', (string)$y); // 1970..2100
}
/* =========================================== */

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  /* ============== Filters (GET) ============== */
  $q            = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
  $unit_id      = ($_GET['unit_id'] ?? '') === '' ? null : (int)$_GET['unit_id'];
  $bulan        = isset($_GET['bulan']) && $_GET['bulan']!=='' ? trim((string)$_GET['bulan']) : null;
  $tahun        = isset($_GET['tahun']) && $_GET['tahun']!=='' ? (string)$_GET['tahun'] : null;
  $nama_bahan   = isset($_GET['nama_bahan']) && $_GET['nama_bahan']!=='' ? trim((string)$_GET['nama_bahan']) : null;        // NEW
  $jenis_peker  = isset($_GET['jenis_pekerjaan']) && $_GET['jenis_pekerjaan']!=='' ? trim((string)$_GET['jenis_pekerjaan']) : null; // NEW
  $kebun_label  = isset($_GET['kebun_label']) && $_GET['kebun_label']!=='' ? trim((string)$_GET['kebun_label']) : null;     // optional

  if ($tahun !== null && !validYear($tahun)) { $tahun = null; } // amankan

  /* ============== Query ============== */
  $sql = "SELECT p.*, u.nama_unit AS unit_nama
          FROM pemakaian_bahan_kimia p
          LEFT JOIN units u ON u.id = p.unit_id
          WHERE 1=1";
  $bind = [];

  if ($q !== '') {
    $sql .= " AND (
                p.no_dokumen      LIKE :q
             OR p.nama_bahan      LIKE :q
             OR p.jenis_pekerjaan LIKE :q
             OR IFNULL(p.keterangan,'') LIKE :q
             )";
    $bind[':q'] = likeParam($q);
  }
  if ($unit_id !== null) { $sql .= " AND p.unit_id = :uid";   $bind[':uid']   = $unit_id; }
  if ($bulan   !== null) { $sql .= " AND p.bulan   = :bulan"; $bind[':bulan'] = $bulan; }
  if ($tahun   !== null) { $sql .= " AND p.tahun   = :tahun"; $bind[':tahun'] = $tahun; }

  // NEW: filter bahan & jenis (exact dari dropdown)
  if ($nama_bahan !== null) {
    $sql .= " AND p.nama_bahan = :nbF";
    $bind[':nbF'] = $nama_bahan;
  }
  if ($jenis_peker !== null) {
    $sql .= " AND p.jenis_pekerjaan = :jpF";
    $bind[':jpF'] = $jenis_peker;
  }

  // Opsional: filter kebun via tag prefix "[Kebun: X]" di keterangan
  if ($kebun_label !== null) {
    $sql .= " AND p.keterangan LIKE :kbntag";
    $bind[':kbntag'] = "[Kebun: ".$kebun_label."]%";
  }

  $sql .= " ORDER BY p.tahun DESC,
            FIELD(p.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            p.created_at DESC";

  $st = $pdo->prepare($sql);
  foreach($bind as $k=>$v) $st->bindValue($k, $v);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // post-process: parse tag -> kebun_label, fisik_label, keterangan_clean
  foreach ($rows as &$r) {
    [$r['kebun_label'], $r['fisik_label'], $r['keterangan_clean']] = parse_labels_and_clean($r['keterangan'] ?? '');
  }
  unset($r);

} catch (Throwable $e) {
  http_response_code(500);
  exit('DB Error: '.$e->getMessage());
}

/* ============== Build HTML ============== */
$subtitle = [];
if ($kebun_label !== null) $subtitle[] = 'Kebun: '.$kebun_label;
if ($unit_id     !== null) $subtitle[] = 'Unit: '.($rows[0]['unit_nama'] ?? '');
if ($bulan       !== null) $subtitle[] = 'Bulan: '.$bulan;
if ($tahun       !== null) $subtitle[] = 'Tahun: '.$tahun;
if ($nama_bahan  !== null) $subtitle[] = 'Bahan: '.$nama_bahan;
if ($jenis_peker !== null) $subtitle[] = 'Jenis: '.$jenis_peker;
if ($q !== '')              $subtitle[] = 'Cari: "'.$q.'"';
$subtitleText = $subtitle ? implode(' • ', $subtitle) : 'Tanpa filter';

ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 18mm 12mm; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#111; }
  .brand { background:#22c55e; color:#fff; padding:12px 16px; border-radius:8px; text-align:center; margin-bottom:8px; }
  .brand h1 { margin:0; font-size:18px; letter-spacing:.5px; }
  .subtitle { text-align:center; margin: 2px 0 12px; font-weight:bold; font-size:12px; color:#065f46; }
  table { width:100%; border-collapse:collapse; table-layout: fixed; }
  th, td { border:1px solid #e5e7eb; padding:6px 8px; vertical-align: top; }
  thead th { background:#ecfdf5; color:#065f46; font-weight:700; }
  tbody tr:nth-child(even) td { background:#f8fafc; }
  .text-right { text-align:right; }
  .wrap { word-wrap: break-word; overflow-wrap: anywhere; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN 4 REGIONAL 3</h1></div>
  <div class="subtitle">Permintaan / Pemakaian Bahan Kimia — <?= htmlspecialchars($subtitleText) ?></div>

  <table>
    <colgroup>
      <col style="width:10%"><col style="width:10%"><col style="width:12%"><col style="width:10%">
      <col style="width:12%"><col style="width:12%"><col style="width:8%"><col style="width:8%"><col style="width:10%"><col style="width:18%">
    </colgroup>
    <thead>
      <tr>
        <th>No. Dokumen</th>
        <th>Kebun</th>
        <th>Unit</th>
        <th>Periode</th>
        <th>Nama Bahan</th>
        <th>Jenis Pekerjaan</th>
        <th class="text-right">Jlh Diminta</th>
        <th class="text-right">Jlh Fisik</th>
        <th>Dokumen</th>
        <th>Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="10">Belum ada data untuk filter yang dipilih.</td></tr>
      <?php else: foreach ($rows as $r):
        $periode = trim(($r['bulan'] ?? '').' '.($r['tahun'] ?? ''));
        $dokumen = $r['dokumen_path'] ?? '';
        $dokumenShow = $dokumen ? basename($dokumen) : 'N/A';
        $fisikTxt = number_format((float)($r['jlh_fisik'] ?? 0), 2) . (!empty($r['fisik_label']) ? ' ('.$r['fisik_label'].')' : '');
      ?>
        <tr>
          <td><?= htmlspecialchars($r['no_dokumen'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['kebun_label'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['unit_nama']   ?? '-') ?></td>
          <td><?= htmlspecialchars($periode !== '' ? $periode : '-') ?></td>
          <td><?= htmlspecialchars($r['nama_bahan'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['jenis_pekerjaan'] ?? '-') ?></td>
          <td class="text-right"><?= number_format((float)($r['jlh_diminta'] ?? 0), 2) ?></td>
          <td class="text-right"><?= htmlspecialchars($fisikTxt) ?></td>
          <td class="wrap"><?= htmlspecialchars($dokumenShow) ?></td>
          <td class="wrap"><?= htmlspecialchars($r['keterangan_clean'] ?? '-') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();

/* ============== Render DOMPDF ============== */
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$fname = 'pemakaian_'.date('Ymd_His').'.pdf';
$dompdf->stream($fname, ['Attachment'=>false]);
exit;
