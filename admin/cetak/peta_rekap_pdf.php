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

} catch (Exception $e) {
    exit('DB Error: ' . $e->getMessage());
}

function nf($v) {
    return (empty($v) || $v == 0) ? '-' : number_format((float)$v, 2, ',', '.');
}

$MONTH_NAMES = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
$MONTH_COLORS = [
    1=>'#eab308', 2=>'#22c55e', 3=>'#f97316', 4=>'#06b6d4',
    5=>'#84cc16', 6=>'#1e40af', 7=>'#374151', 8=>'#92400e',
    9=>'#ef4444', 10=>'#a855f7', 11=>'#10b981', 12=>'#0891b2'
];

// Group data by JP + Objek
$groups = [];
foreach ($mcs_data as $row) {
    $key = ($row['jp_nama'] ?? 'Tanpa JP') . '|' . ($row['objek_pekerjaan'] ?? '-');
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'jp_nama' => $row['jp_nama'] ?? '-',
            'objek' => $row['objek_pekerjaan'] ?? '-',
            'bulan' => array_fill(1, 12, 0)
        ];
    }
    for ($i = 1; $i <= 12; $i++) {
        $groups[$key]['bulan'][$i] += (float)($row['bulan_'.$i] ?? 0);
    }
}

// Grand totals
$grandTotal = array_fill(1, 12, 0);
foreach ($groups as $g) {
    for ($i = 1; $i <= 12; $i++) {
        $grandTotal[$i] += $g['bulan'][$i];
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

        .hdr-left { background: #1e3a8a; color: #fff; padding: 8px 10px; text-align: center; vertical-align: middle; }
        .hdr-left .company { font-size: 11px; font-weight: bold; }
        .hdr-left .region { font-size: 9px; }
        .hdr-left .kebun-name { font-size: 10px; font-weight: bold; margin-top: 2px; }

        .hdr-center { background: #eab308; color: #000; padding: 8px 10px; text-align: center; vertical-align: middle; }
        .hdr-center .title { font-size: 13px; font-weight: bold; }
        .hdr-center .luas { font-size: 11px; font-weight: bold; }

        .hdr-right { background: #1e3a8a; color: #fff; padding: 8px 10px; text-align: center; vertical-align: middle; font-size: 14px; font-weight: bold; letter-spacing: 2px; }

        .main-layout { width: 100%; border: none; }
        .main-layout td { border: none; vertical-align: top; }
        .map-cell { width: 70%; padding: 0; }
        .meta-cell { width: 30%; padding: 0 0 0 4px; }

        .map-img { width: 100%; height: auto; display: block; }

        .meta-box { border: 1px solid #000; padding: 6px 8px; margin-bottom: 4px; }
        .meta-info { width: 100%; font-size: 10px; margin-bottom: 4px; }
        .meta-info td { border: none; padding: 1px 0; vertical-align: top; }
        .meta-info .lbl { font-weight: bold; width: 95px; }

        .jp-title { font-size: 11px; font-weight: bold; color: #dc2626; text-align: center; padding: 4px 0; border-top: 1px solid #000; border-bottom: 1px solid #000; margin: 4px 0; letter-spacing: 0.5px; }

        .ket-label { font-size: 10px; font-weight: bold; margin: 4px 0 2px 0; }

        .ket-tbl { width: 100%; }
        .ket-tbl td { border: 1px solid #666; padding: 4px 5px; font-size: 10px; vertical-align: middle; }
        .ket-tbl .month-name { font-weight: bold; width: 55%; }
        .ket-tbl .month-val { text-align: right; width: 45%; font-weight: bold; }

        .total-row td { border: 1px solid #000; font-weight: bold; font-size: 10px; background: #f1f5f9; }

        .sig-tbl { width: 100%; margin-top: 8px; }
        .sig-tbl td { border: none; padding: 2px 0; font-size: 9px; vertical-align: top; }
        .sig-role { font-weight: bold; }
        .sig-line { border-bottom: 1px solid #000; height: 28px; }
        .sig-name { text-align: center; font-weight: bold; font-size: 9px; padding-top: 2px; }

        .motto { text-align: center; font-style: italic; font-size: 9px; margin-top: 6px; font-weight: bold; }

        .group-sep { border-top: 2px solid #0891b2; margin-top: 6px; padding-top: 4px; }
    </style>
</head>
<body>

<!-- === HEADER: 3 COLORED BANNERS === -->
<table width="100%">
    <tr>
        <td class="hdr-left" style="width:33%">
            <div class="company">PT. PERKEBUNAN NUSANTARA IV</div>
            <div class="region">REGIONAL III - DISTRIK BARAT</div>
            <div class="kebun-name">KEBUN <?= strtoupper($nama_kebun) ?></div>
        </td>
        <td class="hdr-center" style="width:34%">
            <div class="title">PETA <?= strtoupper($nama_unit) ?></div>
            <div class="luas">LUAS: <?= fmtHa($grandSum) ?> Ha</div>
        </td>
        <td class="hdr-right" style="width:33%">
            PETA KERJA
        </td>
    </tr>
</table>

<!-- === MAIN CONTENT: MAP (LEFT) + METADATA (RIGHT) === -->
<table class="main-layout">
    <tr>
        <!-- LEFT: MAP IMAGE -->
        <td class="map-cell">
            <img src="<?= $map_image ?>" class="map-img" alt="Peta Kerja">
        </td>

        <!-- RIGHT: METADATA PANEL -->
        <td class="meta-cell">
            <div class="meta-box">
                <!-- KEBUN INFO TABLE -->
                <table class="meta-info">
                    <tr>
                        <td class="lbl">KEBUN</td>
                        <td>: <?= strtoupper($nama_kebun) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Afdeling</td>
                        <td>: <?= strtoupper($nama_unit) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Skala</td>
                        <td>: 1 : 20.000</td>
                    </tr>
                    <?php
                    $firstObjek = '-';
                    foreach ($groups as $g) { $firstObjek = $g['objek']; break; }
                    ?>
                    <tr>
                        <td class="lbl">Objek Pekerjaan</td>
                        <td>: <?= htmlspecialchars(strtoupper($firstObjek)) ?></td>
                    </tr>
                </table>

                <?php foreach ($groups as $gi => $g): 
                    $groupTotal = array_sum($g['bulan']);
                ?>
                <?php if ($gi > 0): ?>
                <div class="group-sep"></div>
                <?php endif; ?>

                <!-- JP NAME AS RED TITLE -->
                <div class="jp-title"><?= htmlspecialchars(strtoupper($g['jp_nama'])) ?></div>

                <!-- KETERANGAN LABEL -->
                <div class="ket-label">KETERANGAN :</div>

                <!-- COLOR-CODED MONTH ROWS -->
                <table class="ket-tbl">
                    <?php 
                    $hasMonth = false;
                    for ($i = 1; $i <= 12; $i++): 
                        $v = $g['bulan'][$i];
                        if ($v > 0): 
                            $hasMonth = true;
                    ?>
                    <tr>
                        <td class="month-name" style="background:<?= $MONTH_COLORS[$i] ?>; color:#fff;"><?= $MONTH_NAMES[$i-1] ?></td>
                        <td class="month-val" style="background:<?= $MONTH_COLORS[$i] ?>; color:#fff;"><?= fmtHa($v) ?> HA</td>
                    </tr>
                    <?php 
                        endif;
                    endfor; 
                    if (!$hasMonth):
                    ?>
                    <tr>
                        <td class="month-name" colspan="2" style="text-align:center; color:#666;">Belum ada data</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($hasMonth): ?>
                    <tr class="total-row">
                        <td style="text-align:right; padding-right:6px;">TOTAL</td>
                        <td style="text-align:right;"><?= fmtHa($groupTotal) ?> HA</td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php endforeach; ?>

                <?php if (empty($groups)): ?>
                <div class="jp-title">BELUM ADA DATA</div>
                <div class="ket-label">KETERANGAN :</div>
                <table class="ket-tbl">
                    <tr><td class="month-name" colspan="2" style="text-align:center; color:#666;">Tidak ada data</td></tr>
                </table>
                <?php endif; ?>
            </div>

            <!-- SIGNATURE SECTION (outside meta-box) -->
            <table class="sig-tbl">
                <tr>
                    <td style="width:33%; text-align:center;">
                        <div>Dibuat Oleh,</div>
                        <div class="sig-role">Krani Afd</div>
                        <div class="sig-line"></div>
                        <div class="sig-name">( ...................... )</div>
                    </td>
                    <td style="width:34%; text-align:center;">
                        <div>Diperiksa Oleh,</div>
                        <div class="sig-role">Asisten Afd</div>
                        <div class="sig-line"></div>
                        <div class="sig-name">( ...................... )</div>
                    </td>
                    <td style="width:33%; text-align:center;">
                        <div>Disetujui Oleh,</div>
                        <div class="sig-role">Askep</div>
                        <div class="sig-line"></div>
                        <div class="sig-name">( ...................... )</div>
                    </td>
                </tr>
            </table>

            <!-- MOTTO -->
            <div class="motto">Jujur, Tulus, Ikhlas</div>
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
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'mcs_bulanan_' . strtolower(str_replace(' ','_', $nama_kebun)) . '_' . date('YmdHis') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
