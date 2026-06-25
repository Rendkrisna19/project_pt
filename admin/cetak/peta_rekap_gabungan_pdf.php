<?php
// admin/cetak/peta_rekap_gabungan_pdf.php — MCS Bulanan PDF Export (Gabungan/Per JP)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('memory_limit', '1024M'); // DomPDF takes a lot of RAM for images
ini_set('max_execution_time', '300'); // Prevent timeout

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
    $map_images = isset($_POST['map_images']) ? $_POST['map_images'] : [];
    $mcs_json  = isset($_POST['mcs_data']) ? $_POST['mcs_data'] : '[]';
    $luas_json = isset($_POST['luas_data']) ? $_POST['luas_data'] : '{}';

    if (empty($unit_id) || empty($kebun_id) || empty($map_images)) {
        exit("Data tidak lengkap untuk diexport.");
    }

    $mcs_data = json_decode($mcs_json, true);
    if (!is_array($mcs_data)) $mcs_data = [];

    $luas_data = json_decode($luas_json, true);
    if (!is_array($luas_data)) $luas_data = [];

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
    1=>'#FFFF00', 2=>'#00FF99', 3=>'#F4B183', 4=>'#9DC3E6',
    5=>'#A9D08E', 6=>'#00B0F0', 7=>'#3B3838', 8=>'#ED7D31',
    9=>'#FF0000', 10=>'#FF00FF', 11=>'#C6E0B4', 12=>'#00FFFF'
];

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
    <title>MCS Bulanan Gabungan</title>
    <style>
        @page { margin: 5mm 5mm 8mm 5mm; size: A4 portrait; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #000; }

        table { border-collapse: collapse; }

        .page-break { page-break-after: always; }
        .page-break:last-child { page-break-after: auto; }

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
        .map-cell { width: 70%; padding: 5px; border: 1px solid #000; border-right: none; text-align: center; vertical-align: top; }
        .meta-cell { width: 30%; padding: 0; border: 1px solid #000; border-left: 1px solid #000; }

        .map-img { max-width: 100%; max-height: 250mm; width: auto; height: auto; display: block; margin: 0 auto; object-fit: contain; }

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

<?php foreach ($map_images as $jpId => $map_image): 
    // Filter mcs_data for this JP
    $filteredData = array_filter($mcs_data, function($row) use ($jpId) {
        return $row['jenis_pekerjaan_bulanan_id'] == $jpId;
    });

    $group = [
        'jp' => 'Tanpa JP',
        'satuan' => 'HA',
        'months' => array_fill(1, 12, 0)
    ];
    $uniqueObjek = [];

    foreach ($filteredData as $row) {
        $group['jp'] = trim($row['jp_nama'] ?? 'Tanpa JP');
        $group['satuan'] = trim($row['satuan'] ?? 'HA');
        for ($i=1; $i<=12; $i++) {
            $group['months'][$i] += (float)($row['bulan_'.$i] ?? 0);
        }
        $obj = trim($row['objek_pekerjaan'] ?? '');
        if ($obj !== '') {
            $uniqueObjek[$obj] = true;
        }
    }
    
    $strObjek = !empty($uniqueObjek) ? implode(', ', array_keys($uniqueObjek)) : '-';
    
    // Fallback if no data was actually found but the map exists (e.g. they selected a JP but no data is filled)
    if (empty($filteredData)) {
        // We can look up the JP name from db if we wanted, but let's assume if it reached here, we just put a placeholder.
        $stmtJp = $conn->prepare("SELECT nama, satuan FROM md_jenis_pekerjaan_bulanan WHERE id = ?");
        $stmtJp->execute([$jpId]);
        $jpRow = $stmtJp->fetch(PDO::FETCH_ASSOC);
        $group['jp'] = $jpRow ? $jpRow['nama'] : "PEKERJAAN";
        $group['satuan'] = $jpRow ? $jpRow['satuan'] : "HA";
    }

    $mappedId = 100000 + (int)$jpId;
    $luas_ha = isset($luas_data[$mappedId]) ? (float)$luas_data[$mappedId] : 0;
?>

<div class="page-break">
    <table width="100%" style="border-collapse: collapse;">
        <tr>
            <td class="hdr-left" style="width:35%">
                <div class="company">PT. PERKEBUNAN NUSANTARA IV</div>
                <div class="region">REGIONAL III - DISTRIK BARAT</div>
                <div class="kebun-name">KEBUN <?= strtoupper($nama_kebun) ?></div>
            </td>
            <td class="hdr-center" style="width:35%">
                <div class="title">PETA <?= strtoupper($nama_unit) ?></div>
                <div class="luas">LUAS: <?= fmtHa($luas_ha) ?> Ha</div>
            </td>
            <td class="hdr-right" style="width:30%">
                PETA KERJA
            </td>
        </tr>
    </table>

    <table class="main-layout">
        <tr>
            <td class="map-cell">
                <img src="<?= $map_image ?>" class="map-img" alt="Peta Kerja">
            </td>

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
                    
                    <tr>
                        <td style="text-align: center; color: red; font-weight: bold; font-size: 13px; padding: 6px;">
                            <?= htmlspecialchars(strtoupper($group['jp'])) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 6px;">
                            <div class="ket-label">KETERANGAN :</div>
                            <table class="ket-tbl">
                                <?php 
                                $hasMonth = false;
                                $totalHa = array_sum($group['months']);
                                for ($i = 1; $i <= 12; $i++): 
                                    $v = $group['months'][$i];
                                    if ($v > 0): 
                                        $hasMonth = true;
                                ?>
                                <tr>
                                    <td class="month-name" style="background:<?= $MONTH_COLORS[$i] ?>; color:#000; width: 45%;"><?= $MONTH_NAMES[$i-1] ?></td>
                                    <td class="month-val" style="width: 55%;"><?= nf($v) ?> <?= htmlspecialchars($group['satuan']) ?></td>
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
                                    <td class="month-val" style="border-top: 1px solid #000 !important;"><?= nf($totalHa) ?> <?= htmlspecialchars($group['satuan']) ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>

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
</div>

<?php endforeach; ?>

</body>
</html>
<?php
$html = ob_get_clean();

try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="MCS_Bulanan_Gabungan_'.$nama_unit.'.pdf"');
    echo $dompdf->output();
} catch (Exception $e) {
    http_response_code(500);
    echo "<h1>Error rendering PDF</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
