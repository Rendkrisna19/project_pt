<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ===== FUNGSI BANTU & DATA =====
function sum(array $arr, string $key): float {
    return array_reduce($arr, function($carry, $item) use ($key) {
        return $carry + (float)($item[$key] ?? 0);
    }, 0.0);
}

function cleanTT($tt) {
    if ($tt === null || $tt === '') return null;
    $num = preg_replace('/[^\d]/', '', (string)$tt);
    return is_numeric($num) ? (int)$num : null;
}

$bulanOrder = [
    'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4, 'Mei' => 5, 'Juni' => 6,
    'Juli' => 7, 'Agustus' => 8, 'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
];

try {
    // ===== KONEKSI & AMBIL DATA (Sesuai Filter) =====
    $db = new Database();
    $pdo = $db->getConnection();

    $kebun_id = $_GET['kebun_id'] ?? '';
    $unit_id  = $_GET['unit_id']  ?? '';
    $bulan    = $_GET['bulan']    ?? '';
    $tahun    = $_GET['tahun']    ?? '';
    $tt       = $_GET['tt']       ?? '';

    $where = " WHERE 1=1 ";
    $bind = [];
    if ($kebun_id !== '') { $where .= " AND l.kebun_id=:kid"; $bind[':kid']=$kebun_id; }
    if ($unit_id !== '') { $where .= " AND l.unit_id=:uid"; $bind[':uid']=$unit_id; }
    if ($bulan !== '') { $where .= " AND l.bulan=:bln"; $bind[':bln']=$bulan; }
    if ($tahun !== '') { $where .= " AND l.tahun=:thn"; $bind[':thn']=$tahun; }
    if ($tt !== '') { $where .= " AND l.tt=:tt"; $bind[':tt']=$tt; }

    $sql = "SELECT l.*, u.nama_unit, k.nama_kebun FROM lm76 l
            LEFT JOIN units u ON u.id = l.unit_id
            LEFT JOIN md_kebun k ON k.id = l.kebun_id
            $where";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $allData = $st->fetchAll(PDO::FETCH_ASSOC);

    // ===== GROUPING DATA =====
    $ttBuckets = [];
    foreach ($allData as $r) {
        $ttKey = cleanTT($r['tt']) ?? '__NA__';
        $unitKey = trim($r['nama_unit'] ?? '') ?: '(Unit Tidak Diketahui)';
        $ttBuckets[$ttKey][$unitKey][] = $r;
    }
    
    ksort($ttBuckets, SORT_NUMERIC);
    
    foreach ($ttBuckets as $ttKey => &$units) {
        ksort($units, SORT_STRING);
        foreach ($units as $unitKey => &$rows) {
            usort($rows, function($a, $b) use ($bulanOrder) {
                $tahunCompare = ($a['tahun'] ?? 0) <=> ($b['tahun'] ?? 0);
                if ($tahunCompare !== 0) return $tahunCompare;
                $bulanA = $bulanOrder[$a['bulan']] ?? 0;
                $bulanB = $bulanOrder[$b['bulan']] ?? 0;
                return $bulanA <=> $bulanB;
            });
        }
    }

    // ===== MEMBUAT HTML UNTUK PDF =====
    $fmt = fn($n) => number_format((float)$n, 2, ',', '.');
    $fmt0 = fn($n) => number_format((float)$n, 0, ',', '.');

    ob_start();
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>LM-76 Report</title>
    <style>
        @page { margin: 20px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 9px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 18px; color: #065f46; margin: 0; }
        .header h2 { font-size: 14px; color: #047857; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; vertical-align: middle; }
        th { background-color: #065f46; color: white; text-align: center; }
        .text-right { text-align: right; }
        .group-head td { background-color: #065f46; color: white; font-weight: bold; }
        .unit-head td { background-color: #d1fae5; color: #065f46; font-weight: bold; }
        .unit-sub { background-color: #ecfdf5; font-weight: bold; }
        .group-sub { background-color: #ecfdf5; border-top: 2px solid #065f46; font-weight: bold; }
        .grand-total { background-color: #bbf7d0; border-top: 3px double #065f46; font-weight: bold; font-size: 10px; }
    </style>
    </head><body>
        <div class="header">
            <h1>PTPN IV REGIONAL 3</h1>
            <h2>LM-76 — STATISTIK PANEN KELAPA SAWIT</h2>
        </div>
        <table>
            <thead><tr>
                <th>Tahun</th><th>Kebun</th><th>Unit/Defisi</th><th>Periode</th><th>T.Tanam</th>
                <th class="text-right">Luas(Ha)</th><th class="text-right">Invt Pokok</th><th class="text-right">Anggaran(Kg)</th>
                <th class="text-right">Realisasi(Kg)</th><th class="text-right">Jlh Tandan</th><th class="text-right">Jlh HK</th>
                <th class="text-right">Panen(Ha)</th><th class="text-right">Frekuensi</th>
            </tr></thead>
            <tbody>
            <?php if (empty($allData)): ?>
                <tr><td colspan="13" style="text-align: center; padding: 20px;">Tidak ada data untuk filter yang dipilih.</td></tr>
            <?php else: ?>
                <?php foreach ($ttBuckets as $ttKey => $units): $rowsTT = array_merge(...array_values($units)); ?>
                    <tr class="group-head"><td colspan="13">Tahun Tanam: <?= htmlspecialchars((string)($ttKey === '__NA__' ? 'N/A' : $ttKey)) ?></td></tr>
                    <?php foreach ($units as $unitKey => $rowsU): ?>
                        <tr class="unit-head"><td colspan="13">Unit/AFD: <?= htmlspecialchars((string)$unitKey) ?></td></tr>
                        <?php foreach ($rowsU as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($r['tahun'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['nama_kebun'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['nama_unit'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)(($r['bulan'] ?? '').' '.($r['tahun'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars((string)($r['tt'] ?? '')) ?></td>
                                <td class="text-right"><?= $fmt($r['luas_ha']) ?></td><td class="text-right"><?= $fmt0($r['jumlah_pohon']) ?></td>
                                <td class="text-right"><?= $fmt($r['anggaran_kg']) ?></td><td class="text-right"><?= $fmt($r['realisasi_kg']) ?></td>
                                <td class="text-right"><?= $fmt0($r['jumlah_tandan']) ?></td><td class="text-right"><?= $fmt($r['jumlah_hk']) ?></td>
                                <td class="text-right"><?= $fmt($r['panen_ha']) ?></td><td class="text-right"><?= $fmt($r['frekuensi']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="unit-sub">
                            <td colspan="5">Jumlah (<?= htmlspecialchars((string)$unitKey) ?>)</td>
                            <td class="text-right"><?= $fmt($luas_u = sum($rowsU, 'luas_ha')) ?></td>
                            <td class="text-right"><?= $fmt0(sum($rowsU, 'jumlah_pohon')) ?></td>
                            <td class="text-right"><?= $fmt(sum($rowsU, 'anggaran_kg')) ?></td>
                            <td class="text-right"><?= $fmt(sum($rowsU, 'realisasi_kg')) ?></td>
                            <td class="text-right"><?= $fmt0(sum($rowsU, 'jumlah_tandan')) ?></td>
                            <td class="text-right"><?= $fmt(sum($rowsU, 'jumlah_hk')) ?></td>
                            <td class="text-right"><?= $fmt($panen_u = sum($rowsU, 'panen_ha')) ?></td>
                            <td class="text-right"><?= $fmt($luas_u > 0 ? $panen_u / $luas_u : 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="group-sub">
                        <td colspan="5">Subtotal TT: <?= htmlspecialchars((string)($ttKey === '__NA__' ? 'N/A' : $ttKey)) ?></td>
                        <td class="text-right"><?= $fmt($luas_tt = sum($rowsTT, 'luas_ha')) ?></td>
                        <td class="text-right"><?= $fmt0(sum($rowsTT, 'jumlah_pohon')) ?></td>
                        <td class="text-right"><?= $fmt(sum($rowsTT, 'anggaran_kg')) ?></td>
                        <td class="text-right"><?= $fmt(sum($rowsTT, 'realisasi_kg')) ?></td>
                        <td class="text-right"><?= $fmt0(sum($rowsTT, 'jumlah_tandan')) ?></td>
                        <td class="text-right"><?= $fmt(sum($rowsTT, 'jumlah_hk')) ?></td>
                        <td class="text-right"><?= $fmt($panen_tt = sum($rowsTT, 'panen_ha')) ?></td>
                        <td class="text-right"><?= $fmt($luas_tt > 0 ? sum($rowsTT, 'panen_ha') / $luas_tt : 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="grand-total">
                    <td colspan="5">GRAND TOTAL</td>
                    <td class="text-right"><?= $fmt($luas_total = sum($allData, 'luas_ha')) ?></td>
                    <td class="text-right"><?= $fmt0(sum($allData, 'jumlah_pohon')) ?></td>
                    <td class="text-right"><?= $fmt(sum($allData, 'anggaran_kg')) ?></td>
                    <td class="text-right"><?= $fmt(sum($allData, 'realisasi_kg')) ?></td>
                    <td class="text-right"><?= $fmt0(sum($allData, 'jumlah_tandan')) ?></td>
                    <td class="text-right"><?= $fmt(sum($allData, 'jumlah_hk')) ?></td>
                    <td class="text-right"><?= $fmt($panen_total = sum($allData, 'panen_ha')) ?></td>
                    <td class="text-right"><?= $fmt($luas_total > 0 ? $panen_total / $luas_total : 0) ?></td>
                </tr>
            </tfoot>
        </table>
    </body></html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $fname = 'lm76_rekap_'.date('Ymd_His').'.pdf';
    $dompdf->stream($fname, ['Attachment' => 1]); // 1=download, 0=preview

} catch (Throwable $e) {
    http_response_code(500);
    exit("Gagal membuat PDF: " . $e->getMessage());
}