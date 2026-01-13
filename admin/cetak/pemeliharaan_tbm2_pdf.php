<?php
// pages/cetak/pemeliharaan_tm_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../auth/login.php"); exit;
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$db = new Database();
$conn = $db->getConnection();

// --- 1. FILTER ---
$f_tahun = $_GET['tahun'] ?? date('Y');
$f_afd   = $_GET['afd'] ?? null;
$f_jenis = $_GET['jenis'] ?? null;
$f_hk    = $_GET['hk'] ?? null;
$f_ket   = $_GET['ket'] ?? null;

$where = "WHERE tahun = :tahun";
$params = [':tahun' => $f_tahun];
if ($f_afd) { $where .= " AND unit_kode = :afd"; $params[':afd'] = $f_afd; }
if ($f_jenis) { $where .= " AND jenis_nama = :jenis"; $params[':jenis'] = $f_jenis; }
if ($f_hk) { $where .= " AND hk = :hk"; $params[':hk'] = $f_hk; }
if ($f_ket) { $where .= " AND ket LIKE :ket"; $params[':ket'] = "%$f_ket%"; }

$stmt = $conn->prepare("SELECT * FROM pemeliharaan_tbm2 $where ORDER BY jenis_nama ASC, unit_kode ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouping
$grouped = [];
foreach($rows as $r) {
    $grouped[$r['jenis_nama'] ?? 'Lain-lain'][] = $r;
}

// Konfigurasi Rayon
$rayonA_list = ['AFD02','AFD03','AFD04','AFD05','AFD06'];
$rayonB_list = ['AFD01','AFD07','AFD08','AFD09','AFD10'];
$months = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'];

// Helper Format
function nf($val) { return number_format((float)$val, 2, ',', '.'); }
function dash($val) { return ((float)$val == 0) ? '-' : nf($val); }

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pemeliharaan TBM II</title>
    <style>
        @page { margin: 5mm; size: A4 landscape; }
        body { font-family: sans-serif; font-size: 8px; color: #333; }
        
        .header-box { background-color: #059fd3; color: white; padding: 8px; border-radius: 4px; margin-bottom: 10px; }
        h2 { margin: 0; font-size: 14px; }
        
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 3px; vertical-align: middle; word-wrap: break-word; }
        
        th { background-color: #059fd3; color: white; text-align: center; font-weight: bold; }
        .group-head { background-color: #eff6ff; color: #1e3a8a; font-weight: bold; text-align: left; }
        
        /* Warna Baris Total */
        .sum-jenis { background-color: #f0fdf4; color: #14532d; font-weight: bold; }
        .sum-rayon { background-color: #fff7ed; color: #7c2d12; font-weight: bold; font-style: italic; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .pos { color: #dc2626; } /* Merah jika + (Over budget) */
        .neg { color: #16a34a; } /* Hijau jika - (Under budget) */
    </style>
</head>
<body>
    <div class="header-box">
        <h2>REKAPITULASI PEMELIHARAAN TBM II - TAHUN <?= $f_tahun ?></h2>
        <div>Unit: <?= $f_afd ? $f_afd : 'Semua AFD' ?> | Dicetak: <?= date('d-m-Y H:i') ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:4%">Unit</th>
                <th rowspan="2" style="width:10%">Ket</th>
                <th rowspan="2" style="width:3%">HK</th>
                <th rowspan="2" style="width:3%">Sat</th>
                <th rowspan="2" style="width:6%">Anggaran</th>
                <th colspan="12">Realisasi Bulanan</th>
                <th rowspan="2" style="width:6%">Total</th>
                <th rowspan="2" style="width:5%">+/-</th>
                <th rowspan="2" style="width:4%">%</th>
            </tr>
            <tr>
                <?php foreach(['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'] as $m): ?>
                    <th><?= $m ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="20" class="text-center">Tidak ada data.</td></tr>
            <?php else: ?>
                <?php foreach($grouped as $jenis => $items): 
                    // Init Accumulator
                    $sumJenis = ['ang'=>0, 'real'=>0, 'm'=>array_fill_keys($months,0)];
                    $sumRa    = ['ang'=>0, 'real'=>0, 'm'=>array_fill_keys($months,0), 'cnt'=>0];
                    $sumRb    = ['ang'=>0, 'real'=>0, 'm'=>array_fill_keys($months,0), 'cnt'=>0];
                ?>
                    <tr class="group-head">
                        <td colspan="20">JENIS: <?= strtoupper(htmlspecialchars($jenis)) ?></td>
                    </tr>

                    <?php foreach($items as $row): 
                        $ang = (float)$row['anggaran_tahun'];
                        $realRow = 0;
                        $unit = $row['unit_kode'];
                        $isRa = in_array($unit, $rayonA_list);
                        $isRb = in_array($unit, $rayonB_list);
                        
                        // Hitung row & accumulate
                        foreach($months as $m) {
                            $val = (float)$row[$m];
                            $realRow += $val;
                            
                            $sumJenis['m'][$m] += $val;
                            if ($isRa) $sumRa['m'][$m] += $val;
                            if ($isRb) $sumRb['m'][$m] += $val;
                        }
                        
                        $sumJenis['ang'] += $ang; $sumJenis['real'] += $realRow;
                        if ($isRa) { $sumRa['ang'] += $ang; $sumRa['real'] += $realRow; $sumRa['cnt']++; }
                        if ($isRb) { $sumRb['ang'] += $ang; $sumRb['real'] += $realRow; $sumRb['cnt']++; }

                        $delta = $realRow - $ang;
                        $pct = $ang > 0 ? ($realRow/$ang*100) : 0;
                    ?>
                    <tr>
                        <td class="text-center"><?= $unit ?></td>
                        <td><?= htmlspecialchars($row['ket']) ?></td>
                        <td class="text-center"><?= $row['hk'] ?></td>
                        <td class="text-center"><?= $row['satuan'] ?></td>
                        <td class="text-right"><?= dash($ang) ?></td>
                        <?php foreach($months as $m): ?>
                            <td class="text-right"><?= dash($row[$m]) ?></td>
                        <?php endforeach; ?>
                        <td class="text-right"><?= dash($realRow) ?></td>
                        <td class="text-right <?= $delta>0?'pos':($delta<0?'neg':'') ?>"><?= dash($delta) ?></td>
                        <td class="text-right"><?= nf($pct) ?>%</td>
                    </tr>
                    <?php endforeach; // End Item Loop ?>

                    <tr class="sum-jenis">
                        <td colspan="4" class="text-right">TOTAL <?= strtoupper($jenis) ?></td>
                        <td class="text-right"><?= dash($sumJenis['ang']) ?></td>
                        <?php foreach($months as $m): ?>
                            <td class="text-right"><?= dash($sumJenis['m'][$m]) ?></td>
                        <?php endforeach; ?>
                        <td class="text-right"><?= dash($sumJenis['real']) ?></td>
                        <td class="text-right"><?= dash($sumJenis['real'] - $sumJenis['ang']) ?></td>
                        <td class="text-right"><?= $sumJenis['ang']>0 ? nf($sumJenis['real']/$sumJenis['ang']*100) : '0' ?>%</td>
                    </tr>

                    <?php if($sumRa['cnt'] > 0): ?>
                    <tr class="sum-rayon">
                        <td colspan="4" class="text-right">Subtotal Rayon A</td>
                        <td class="text-right"><?= dash($sumRa['ang']) ?></td>
                        <?php foreach($months as $m): ?>
                            <td class="text-right"><?= dash($sumRa['m'][$m]) ?></td>
                        <?php endforeach; ?>
                        <td class="text-right"><?= dash($sumRa['real']) ?></td>
                        <td class="text-right"><?= dash($sumRa['real'] - $sumRa['ang']) ?></td>
                        <td class="text-right"><?= $sumRa['ang']>0 ? nf($sumRa['real']/$sumRa['ang']*100) : '0' ?>%</td>
                    </tr>
                    <?php endif; ?>

                    <?php if($sumRb['cnt'] > 0): ?>
                    <tr class="sum-rayon">
                        <td colspan="4" class="text-right">Subtotal Rayon B</td>
                        <td class="text-right"><?= dash($sumRb['ang']) ?></td>
                        <?php foreach($months as $m): ?>
                            <td class="text-right"><?= dash($sumRb['m'][$m]) ?></td>
                        <?php endforeach; ?>
                        <td class="text-right"><?= dash($sumRb['real']) ?></td>
                        <td class="text-right"><?= dash($sumRb['real'] - $sumRb['ang']) ?></td>
                        <td class="text-right"><?= $sumRb['ang']>0 ? nf($sumRb['real']/$sumRb['ang']*100) : '0' ?>%</td>
                    </tr>
                    <?php endif; ?>

                <?php endforeach; // End Group Loop ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Rekap_TM_".date('YmdHis').".pdf", ["Attachment" => false]);
?>