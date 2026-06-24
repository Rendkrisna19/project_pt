<?php
// admin/cetak/peta_rekap_pdf.php — MCS Bulanan PDF Export (Reference Format)
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    http_response_code(403); exit('Unauthorized'); 
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $db = new Database();
    $conn = $db->getConnection();

    $unit_id   = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
    $kebun_id  = isset($_POST['kebun_id']) ? (int)$_POST['kebun_id'] : 0;
    $tahun     = isset($_POST['tahun']) ? (int)$_POST['tahun'] : date('Y');
    $map_image = isset($_POST['map_image']) ? $_POST['map_image'] : '';
    $mcs_json  = isset($_POST['mcs_data']) ? $_POST['mcs_data'] : '[]';

    if (empty($unit_id) || empty($kebun_id) || empty($map_image)) {
        exit("Data tidak lengkap untuk diexport.");
    }

    $mcs_data = json_decode($mcs_json, true);
    if (!is_array($mcs_data)) $mcs_data = [];

    $stmtInfo = $conn->prepare("SELECT u.nama_unit, k.nama_kebun FROM units u LEFT JOIN md_kebun k ON u.kebun_id = k.id WHERE u.id = ?");
    $stmtInfo->execute([$unit_id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    $nama_unit  = $info ? $info['nama_unit'] : 'UNIT';
    $nama_kebun = $info ? $info['nama_kebun'] : 'KEBUN';

    // Query for manual legend list if exists
    $legends = [];
    try {
        $stmtLegend = $conn->prepare("SELECT * FROM peta_cetak_legend WHERE unit_id = ? AND kebun_id = ? AND tahun = ?");
        $stmtLegend->execute([$unit_id, $kebun_id, $tahun]);
        $legends = $stmtLegend->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist yet, ignore
    }

} catch (Exception $e) {
    exit('DB Error: ' . $e->getMessage());
}

function nf($v) {
    return (empty($v) || $v == 0) ? '-' : number_format((float)$v, 2, ',', '.');
}

$MONTH_NAMES = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
$MONTH_COLORS = [
    1=>'#FFFF00', 2=>'#00FF99', 3=>'#F4B183', 4=>'#9DC3E6',
    5=>'#A9D08E', 6=>'#00B0F0', 7=>'#3B3838', 8=>'#ED7D31',
    9=>'#FF0000', 10=>'#FF00FF', 11=>'#C6E0B4', 12=>'#00FFFF'
];

// Group data by JP (Sum all objects together)
$groups = [];
$uniqueObjek = [];

foreach ($mcs_data as $row) {
    $jp = trim($row['jp_nama'] ?? 'Tanpa JP');
    if (!isset($groups[$jp])) {
        $groups[$jp] = [
            'jp' => $jp,
            'satuan' => trim($row['satuan'] ?? 'HA'),
            'months' => array_fill(1, 12, 0)
        ];
    }
    for ($i=1; $i<=12; $i++) {
        $val = (float)($row['bulan_'.$i] ?? 0);
        $groups[$jp]['months'][$i] += $val;
    }
    
    $obj = trim($row['objek_pekerjaan'] ?? '');
    if ($obj !== '') {
        $uniqueObjek[$obj] = true;
    }
}

$strObjek = !empty($uniqueObjek) ? implode(', ', array_keys($uniqueObjek)) : '-';

// Grand totals
$grandTotal = array_fill(1, 12, 0);
foreach ($groups as $g) {
    for ($i = 1; $i <= 12; $i++) {
        $grandTotal[$i] += $g['months'][$i];
    }
}
$grandSum = array_sum($grandTotal);

// Format area value for display (comma as decimal)
function fmtHa($v) {
    if (empty($v) || $v == 0) return '0';
    return number_format((float)$v, 2, ',', '.');
}

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>MCS Bulanan</title>
    <style>
        @page { margin: 5mm 5mm 8mm 5mm; size: A4 portrait; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #000; }

        table { border-collapse: collapse; }

        .hdr-left { background: #1e3a8a; color: #fff; padding: 8px 10px; text-align: center; vertical-align: middle; border: 1px solid #000; }
        .hdr-left .company { font-size: 11px; font-weight: bold; }
        .hdr-left .region { font-size: 9px; }
        .hdr-left .kebun-name { font-size: 10px; font-weight: bold; margin-top: 2px; }

        .hdr-center { background: #FFFF00; color: #000; padding: 8px 10px; text-align: center; vertical-align: middle; border: 1px solid #000; }
        .hdr-center .title { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
        .hdr-center .luas { font-size: 11px; font-weight: bold; }

        .hdr-right { background: #1e3a8a; color: #fff; padding: 8px 10px; text-align: center; vertical-align: middle; font-size: 16px; font-weight: bold; letter-spacing: 1px; border: 1px solid #000; }

        .main-layout { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 5px; }
        .main-layout > tbody > tr > td { vertical-align: top; }
        .map-cell { width: 70%; padding: 5px; border: 1px solid #000; border-right: none; text-align: center; vertical-align: middle; }
        .meta-cell { width: 30%; padding: 0; border: 1px solid #000; border-left: 1px solid #000; }

        .map-img { width: 100%; max-height: 175mm; height: auto; display: block; margin: 0 auto; }

        /* RIGHT PANEL STYLES */
        .right-panel-tbl { width: 100%; border-collapse: collapse; border: none; }
        .right-panel-tbl td { border-bottom: 1px solid #000; padding: 5px; vertical-align: top; }
        .right-panel-tbl tr:last-child td { border-bottom: none; }
        
        .info-tbl { width: 100%; font-size: 10px; font-weight: bold; }
        .info-tbl td { padding: 2px; border: none; }
        
        .ket-label { font-size: 10px; font-weight: bold; text-decoration: underline; margin-bottom: 6px; }
        .ket-tbl { width: 100%; border-collapse: collapse; }
        .ket-tbl td { padding: 3px 5px; font-size: 10px; font-weight: bold; border: none; }
        .month-name { border: 1px solid #000 !important; }
        .month-val { text-align: right; }
        
        .sig-cell { padding: 8px 6px; font-size: 10px; line-height: 1.4; }
        .sig-space { height: 40px; }
        .motto-cell { font-size: 10px; font-weight: bold; padding: 6px; }
    </style>
</head>
<body>

<table width="100%" style="border-collapse: collapse;">
    <tr>
        <td class="hdr-left" style="width:35%">
            <div class="company">PT. PERKEBUNAN NUSANTARA IV</div>
            <div class="region">REGIONAL III - DISTRIK BARAT</div>
            <div class="kebun-name">KEBUN <?= strtoupper($nama_kebun) ?></div>
        </td>
        <td class="hdr-center" style="width:35%">
            <div class="title">PETA <?= strtoupper($nama_unit) ?></div>
<?php $luas_ha = isset($_POST['luas_ha']) ? (float)$_POST['luas_ha'] : 0; ?>
            <div class="luas">LUAS: <?= fmtHa($luas_ha) ?> Ha</div>
        </td>
        <td class="hdr-right" style="width:30%">
            PETA KERJA
        </td>
    </tr>
</table>

<table class="main-layout">
    <tr>
        <!-- LEFT: MAP IMAGE -->
        <td class="map-cell">
            <img src="<?= $map_image ?>" class="map-img" alt="Peta Kerja">
        </td>

        <!-- RIGHT: METADATA PANEL -->
        <td class="meta-cell">
            <table class="right-panel-tbl">
                <tr>
                    <td style="padding: 4px;">
                        <table class="info-tbl">
                            <tr><td style="width: 70px; vertical-align: top;">KEBUN</td><td style="vertical-align: top;">: <?= htmlspecialchars($nama_kebun) ?></td></tr>
                            <tr><td style="vertical-align: top;">Afdeling</td><td style="vertical-align: top;">: <?= htmlspecialchars($nama_unit) ?></td></tr>
                            <tr><td style="vertical-align: top;">Objek<br>Pekerjaan</td><td style="vertical-align: top;">: <?= htmlspecialchars(strtoupper($strObjek)) ?></td></tr>
                        </table>
                    </td>
                </tr>
                
                <?php
                // USE MANUAL LEGEND IF AVAILABLE, ELSE FALLBACK TO POLYGON DATA
                $dataToPrint = [];
                if (!empty($legends)) {
                    foreach ($legends as $leg) {
                        $months = [];
                        for ($i=1; $i<=12; $i++) $months[$i] = (float)$leg["bulan_$i"];
                        $dataToPrint[] = [
                            'judul' => strtoupper($leg['judul'] ?: 'KETERANGAN'),
                            'bulan' => $months
                        ];
                    }
                } else {
                    foreach ($groups as $g) {
                        $dataToPrint[] = [
                            'judul' => strtoupper($g['jp']),
                            'satuan' => strtoupper($g['satuan']),
                            'bulan' => $g['months']
                        ];
                    }
                }

                if (empty($dataToPrint)) {
                     $dataToPrint[] = [ 'judul' => 'BELUM ADA DATA', 'bulan' => array_fill(1,12,0) ];
                }
                
                foreach ($dataToPrint as $idx => $g):
                    $totalHa = array_sum($g['bulan']);
                ?>
                <tr>
                    <td style="text-align: center; color: red; font-weight: bold; font-size: 13px; padding: 6px;">
                        <?= htmlspecialchars($g['judul']) ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px;">
                        <div class="ket-label">KETERANGAN :</div>
                        <table class="ket-tbl">
                            <?php 
                            $hasMonth = false;
                            for ($i = 1; $i <= 12; $i++): 
                                $v = $g['bulan'][$i];
                                if ($v > 0): 
                                    $hasMonth = true;
                            ?>
                            <tr>
                                <td class="month-name" style="background:<?= $MONTH_COLORS[$i] ?>; color:#000; width: 45%;"><?= $MONTH_NAMES[$i-1] ?></td>
                                <td class="month-val" style="width: 55%;"><?= nf($v) ?> <?= htmlspecialchars($g['satuan'] ?? 'HA') ?></td>
                            </tr>
                            <?php 
                                endif;
                            endfor; 
                            if (!$hasMonth):
                            ?>
                            <tr>
                                <td colspan="2" style="text-align:center; color:#666;">Belum ada data</td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($hasMonth): ?>
                            <tr>
                                <td></td>
                                <td class="month-val" style="border-top: 1px solid #000 !important;"><?= nf($totalHa) ?> <?= htmlspecialchars($g['satuan'] ?? 'HA') ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </td>
                </tr>
                <?php endforeach; ?>

                <tr>
                    <td class="sig-cell">
                        Dibuat Oleh :<br>
                        <strong>Asisten Afdeling</strong>
                        <div class="sig-space"></div>
                        (............................................................)
                    </td>
                </tr>
                <tr>
                    <td class="sig-cell">
                        Diperiksa Oleh :<br>
                        <strong>Asisten Kepala</strong>
                        <div class="sig-space"></div>
                        (............................................................)
                    </td>
                </tr>
                <tr>
                    <td class="sig-cell">
                        Disetujui Oleh :<br>
                        <strong>Manager</strong>
                        <div class="sig-space"></div>
                        (............................................................)
                    </td>
                </tr>
                <tr>
                    <td class="motto-cell">
                        Jujur, Tulus, Ikhlas
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // Changed to landscape to fit map better like in image
$dompdf->render();

$filename = 'mcs_bulanan_' . strtolower(str_replace(' ','_', $nama_kebun)) . '_' . date('YmdHis') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
