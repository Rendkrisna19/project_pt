<?php
// admin/cetak/lm76_export_pdf.php
// PDF export LM-76 — Preview in Browser

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    http_response_code(403); 
    exit('Unauthorized'); 
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ===== Helper Functions =====
function sum(array $arr, string $key): float {
    return array_reduce($arr, function($carry, $item) use ($key) {
        return $carry + (float)($item[$key] ?? 0);
    }, 0.0);
}

$bulanOrder = [
    'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4, 'Mei' => 5, 'Juni' => 6,
    'Juli' => 7, 'Agustus' => 8, 'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
];

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // ==== Ambil Filter ====
    $kebun_id = $_GET['kebun_id'] ?? '';
    $unit_id  = $_GET['unit_id']  ?? '';
    $bulan    = $_GET['bulan']    ?? '';
    $tahun    = $_GET['tahun']    ?? '';
    $tt       = $_GET['tt']       ?? '';

    $where = " WHERE 1=1 ";
    $bind = [];
    
    if ($kebun_id !== '') { $where .= " AND l.kebun_id=:kid"; $bind[':kid'] = $kebun_id; }
    if ($unit_id !== '')  { $where .= " AND l.unit_id=:uid";  $bind[':uid'] = $unit_id; }
    if ($bulan !== '')    { $where .= " AND l.bulan=:bln";    $bind[':bln'] = $bulan; }
    if ($tahun !== '')    { $where .= " AND l.tahun=:thn";    $bind[':thn'] = $tahun; }
    if ($tt !== '')       { $where .= " AND l.tt=:tt";        $bind[':tt']  = $tt; }

    // ==== FIX: Query Data (Nama Tabel diperbaiki dari lm_76 ke lm76) ====
    $sql = "SELECT l.*, u.nama_unit, k.nama_kebun 
            FROM lm76 l 
            LEFT JOIN units u ON u.id = l.unit_id
            LEFT JOIN md_kebun k ON k.id = l.kebun_id
            $where";
            
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $allData = $st->fetchAll(PDO::FETCH_ASSOC);

    // ==== Grouping & Sorting (PHP) ====
    // 1. Group by Unit
    $unitBuckets = [];
    foreach ($allData as $r) {
        $unitKey = trim($r['nama_unit'] ?? '') ?: '(Unit Tidak Diketahui)';
        $unitBuckets[$unitKey][] = $r;
    }
    
    // 2. Sort Unit A-Z
    ksort($unitBuckets, SORT_STRING);
    
    // 3. Sort Rows inside Unit
    foreach ($unitBuckets as $unitKey => &$rows) {
        usort($rows, function($a, $b) use ($bulanOrder) {
            // Tahun
            $ty = ((int)$a['tahun']) - ((int)$b['tahun']);
            if ($ty !== 0) return $ty;
            // Bulan
            $ba = $bulanOrder[$a['bulan'] ?? ''] ?? 0;
            $bb = $bulanOrder[$b['bulan'] ?? ''] ?? 0;
            if ($ba !== $bb) return $ba - $bb;
            // T.Tanam
            $tta = (int)preg_replace('/\D/', '', (string)($a['tt']??'0'));
            $ttb = (int)preg_replace('/\D/', '', (string)($b['tt']??'0'));
            return $tta - $ttb;
        });
    }
    unset($rows);

    // ==== Hitung Grand Total ====
    $gLuas   = sum($allData, 'luas_ha');
    $gPokok  = sum($allData, 'jumlah_pohon');
    $gAngg   = sum($allData, 'anggaran_kg');
    $gReal   = sum($allData, 'realisasi_kg');
    $gTandan = sum($allData, 'jumlah_tandan');
    $gHK     = sum($allData, 'jumlah_hk');
    $gPanen  = sum($allData, 'panen_ha');
    $gFreq   = ($gLuas > 0) ? ($gPanen / $gLuas) : 0;

} catch (Throwable $e) {
    http_response_code(500);
    exit('DB Error: '.$e->getMessage());
}

// Helper format angka
$fmt = fn($n) => number_format((float)$n, 2, ',', '.');
$fmt0 = fn($n) => number_format((float)$n, 0, ',', '.');
$calcFreq = fn($r) => ((float)($r['luas_ha'] ?? 0) > 0 ? (float)($r['panen_ha'] ?? 0) / (float)($r['luas_ha'] ?? 0) : 0);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>LM-76 Report</title>
<style>
    @page { margin: 10mm 10mm; }
    body { font-family: sans-serif; font-size: 10px; color:#111; }
    
    .header { text-align:center; margin-bottom:15px; }
    .header h1 { margin:0; font-size:16px; color:#065f46; text-transform: uppercase; }
    .header h2 { margin:2px 0; font-size:12px; color:#047857; }
    .header p { margin:0; font-size:9px; color:#555; }

    table { width:100%; border-collapse: collapse; table-layout:fixed; }
    th, td { border:1px solid #444; padding:4px 5px; vertical-align:middle; }
    
    /* Header Tabel Hijau */
    thead th { background-color:#16a34a; color:#fff; font-weight:bold; text-align:center; }
    
    /* Alignment */
    .text-right { text-align:right; }
    .text-center { text-align:center; }
    
    /* Grouping Styles */
    .unit-head td { background-color:#d1fae5; color:#065f46; font-weight:bold; border-top:2px solid #065f46; font-size:11px; }
    .unit-sub td { background-color:#ecfdf5; font-weight:bold; color:#064e3b; border-top:1px solid #999; }
    
    /* Footer Grand Total */
    tfoot td { background-color:#bbf7d0; font-weight:bold; border-top:2px solid #000; }
</style>
</head>
<body>

    <div class="header">
        <h1>PTPN 4 REGIONAL 3</h1>
        <h2>LM-76 — STATISTIK PANEN KELAPA SAWIT</h2>
        <p>Dicetak pada: <?= date('d-m-Y H:i') ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">Tahun</th>
                <th width="12%">Kebun</th>
                <th width="10%">Unit/Defisi</th>
                <th width="10%">Periode</th>
                <th width="6%">T. Tanam</th>
                <th class="text-right">Luas (Ha)</th>
                <th class="text-right">Invt Pokok</th>
                <th class="text-right">Anggaran (Kg)</th>
                <th class="text-right">Realisasi (Kg)</th>
                <th class="text-right">Jlh Tandan</th>
                <th class="text-right">Jlh HK</th>
                <th class="text-right">Panen (Ha)</th>
                <th class="text-right">Frekuensi</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($allData)): ?>
            <tr><td colspan="13" class="text-center" style="padding:20px;">Tidak ada data.</td></tr>
        <?php else: ?>
            
            <?php foreach ($unitBuckets as $unitKey => $rowsU): ?>
                <tr class="unit-head"><td colspan="13">Unit/AFD: <?= htmlspecialchars((string)$unitKey) ?></td></tr>
                
                <?php foreach ($rowsU as $r): 
                    $freq = $calcFreq($r);
                ?>
                <tr>
                    <td class="text-center"><?= htmlspecialchars((string)$r['tahun']) ?></td>
                    <td><?= htmlspecialchars((string)$r['nama_kebun']) ?></td>
                    <td><?= htmlspecialchars((string)$r['nama_unit']) ?></td>
                    <td><?= htmlspecialchars($r['bulan'] . ' ' . $r['tahun']) ?></td>
                    <td class="text-center"><?= htmlspecialchars((string)$r['tt']) ?></td>
                    
                    <td class="text-right"><?= $fmt($r['luas_ha']) ?></td>
                    <td class="text-right"><?= $fmt0($r['jumlah_pohon']) ?></td>
                    <td class="text-right"><?= $fmt($r['anggaran_kg']) ?></td>
                    <td class="text-right"><?= $fmt($r['realisasi_kg']) ?></td>
                    <td class="text-right"><?= $fmt0($r['jumlah_tandan']) ?></td>
                    <td class="text-right"><?= $fmt($r['jumlah_hk']) ?></td>
                    <td class="text-right"><?= $fmt($r['panen_ha']) ?></td>
                    <td class="text-right"><?= $fmt($freq) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php 
                    $uLuas   = sum($rowsU, 'luas_ha');
                    $uPanen  = sum($rowsU, 'panen_ha');
                    $uFreq   = ($uLuas > 0) ? ($uPanen / $uLuas) : 0;
                ?>
                <tr class="unit-sub">
                    <td colspan="5" class="text-right">Jumlah (<?= htmlspecialchars((string)$unitKey) ?>)</td>
                    <td class="text-right"><?= $fmt($uLuas) ?></td>
                    <td class="text-right"><?= $fmt0(sum($rowsU, 'jumlah_pohon')) ?></td>
                    <td class="text-right"><?= $fmt(sum($rowsU, 'anggaran_kg')) ?></td>
                    <td class="text-right"><?= $fmt(sum($rowsU, 'realisasi_kg')) ?></td>
                    <td class="text-right"><?= $fmt0(sum($rowsU, 'jumlah_tandan')) ?></td>
                    <td class="text-right"><?= $fmt(sum($rowsU, 'jumlah_hk')) ?></td>
                    <td class="text-right"><?= $fmt($uPanen) ?></td>
                    <td class="text-right"><?= $fmt($uFreq) ?></td>
                </tr>
            <?php endforeach; ?>

        <?php endif; ?>
        </tbody>
        
        <?php if (!empty($allData)): ?>
        <tfoot>
            <tr>
                <td colspan="5" class="text-right">GRAND TOTAL</td>
                <td class="text-right"><?= $fmt($gLuas) ?></td>
                <td class="text-right"><?= $fmt0($gPokok) ?></td>
                <td class="text-right"><?= $fmt($gAngg) ?></td>
                <td class="text-right"><?= $fmt($gReal) ?></td>
                <td class="text-right"><?= $fmt0($gTandan) ?></td>
                <td class="text-right"><?= $fmt($gHK) ?></td>
                <td class="text-right"><?= $fmt($gPanen) ?></td>
                <td class="text-right"><?= $fmt($gFreq) ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // A4 Landscape agar tabel muat
$dompdf->render();

// OUTPUT PDF KE BROWSER (PREVIEW)
// 'Attachment' => false berarti TIDAK download otomatis, tapi tampil di browser.
$dompdf->stream('LM76_Rekap_'.date('Ymd_His').'.pdf', ['Attachment' => false]);
exit;
?>