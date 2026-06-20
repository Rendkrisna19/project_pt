<?php
// admin/cetak/peta_rekap_pdf.php
// Peta Rekap — rekap bulanan semua jenis pekerjaan per unit dalam 1 PDF (landscape A4)
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

    $unit_id  = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
    $kebun_id = isset($_POST['kebun_id']) ? (int)$_POST['kebun_id'] : 0;
    $bulan    = isset($_POST['bulan']) ? $_POST['bulan'] : date('Y-m');
    $map_image = isset($_POST['map_image']) ? $_POST['map_image'] : '';
    $rekap_json = isset($_POST['rekap_data']) ? $_POST['rekap_data'] : '{}';

    if (empty($unit_id) || empty($kebun_id) || empty($map_image)) {
        exit("Data tidak lengkap untuk diexport.");
    }

    // Parse rekap summary data
    $rekap_data = json_decode($rekap_json, true);
    if (!is_array($rekap_data)) $rekap_data = [];

    // Ambil Info Header
    $stmtInfo = $conn->prepare("SELECT u.nama_unit, k.nama_kebun 
                                FROM units u 
                                LEFT JOIN md_kebun k ON u.kebun_id = k.id 
                                WHERE u.id = ?");
    $stmtInfo->execute([$unit_id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    $nama_unit  = $info ? $info['nama_unit'] : 'UNIT';
    $nama_kebun = $info ? $info['nama_kebun'] : 'KEBUN';

    $bulan_tahun = date('F Y', strtotime($bulan . '-01'));

} catch (Exception $e) {
    exit('DB Error: ' . $e->getMessage());
}

function nf($v) {
    return (empty($v) || $v == 0) ? '-' : number_format((float)$v, 2, ',', '.');
}

// Hitung total
$totalBlok = 0; $totalFisik = 0; $totalHk = 0; $totalBahan = 0; $totalCampuran = 0;
$rekapRows = [];
foreach ($rekap_data as $jpId => $jp) {
    $rekapRows[] = $jp;
    $totalBlok    += (int)($jp['blok_count'] ?? 0);
    $totalFisik   += (float)($jp['fisik_sd'] ?? 0);
    $totalHk      += (float)($jp['hk_sd'] ?? 0);
    $totalBahan   += (float)($jp['bahan_kimia_sd'] ?? 0);
    $totalCampuran+= (float)($jp['campuran_sd'] ?? 0);
}

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Peta Rekap</title>
    <style>
        @page { margin: 8mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; color: #1e293b; }

        /* HEADER */
        .header-table { width: 100%; border-collapse: collapse; font-weight: bold; font-size: 11px; text-align: center; }
        .header-table td { border: 2px solid #000; padding: 8px 5px; }
        .bg-cyan { background-color: #0891b2; color: white; }
        .bg-yellow { background-color: #FBBF24; color: black; }
        .bg-dark { background-color: #164e63; color: white; }

        /* CONTENT 2-COL */
        .content-container { width: 100%; border-collapse: collapse; border: 2px solid #000; }
        .content-container > tbody > tr > td { vertical-align: top; padding: 0; border: 2px solid #000; }

        /* MAP */
        .map-wrapper { width: 100%; padding: 0; text-align: center; }
        .map-img { max-width: 100%; max-height: 430px; object-fit: contain; display: block; margin: 0 auto; }

        /* LEGEND (below map) */
        .legend-table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9px; }
        .legend-table td { padding: 3px 4px; border: none; }
        .legend-box { display: inline-block; width: 14px; height: 14px; border: 1.5px solid #000; border-radius: 3px; vertical-align: middle; margin-right: 4px; }

        /* SIGNATURE */
        .sig-table { width: 100%; border-collapse: collapse; font-size: 10px; text-align: center; margin-top: 8px; }
        .sig-table td { border: none; padding: 3px 4px; vertical-align: top; }

        /* DATA TABLE */
        .rekap-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .rekap-table th, .rekap-table td { border: 1px solid #000; padding: 7px 5px; text-align: center; vertical-align: middle; }
        .rekap-table thead th { background-color: #0891b2; color: white; font-weight: bold; font-size: 10px; }
        .rekap-table tfoot td { background-color: #ecfeff; font-weight: bold; border-top: 2px solid #0891b2; font-size: 10px; }
        .color-sq { display: inline-block; width: 14px; height: 14px; border: 1.5px solid #000; border-radius: 3px; margin-right: 5px; vertical-align: middle; }
    </style>
</head>
<body>

    <!-- HEADER -->
    <table class="header-table">
        <tr>
            <td class="bg-cyan" style="width:30%;">
                PT. PERKEBUNAN NUSANTARA IV<br>
                REGIONAL III - DISTRIK BARAT<br>
                KEBUN <?= strtoupper($nama_kebun) ?>
            </td>
            <td class="bg-yellow" style="width:40%; font-size:14px;">
                PETA REKAP (<?= strtoupper($nama_unit) ?>)
            </td>
            <td class="bg-dark" style="width:30%;">
                BULAN : <?= strtoupper($bulan_tahun) ?><br>
                REKAP SEMUA PEKERJAAN
            </td>
        </tr>
    </table>

    <!-- CONTENT -->
    <table class="content-container">
        <tr>
            <!-- LEFT: MAP + LEGEND + SIGNATURE -->
            <td style="width: 60%; padding: 6px;">
                <div class="map-wrapper">
                    <img src="<?= $map_image ?>" class="map-img" alt="Peta Rekap">
                </div>

                <!-- LEGEND -->
                <?php if (!empty($rekapRows)): ?>
                <table class="legend-table">
                    <tr>
                        <?php
                        $col = 0;
                        foreach ($rekapRows as $jp):
                            if ($col > 0 && $col % 3 === 0) echo '</tr><tr>';
                        ?>
                        <td style="width:33%">
                            <span class="legend-box" style="background-color:<?= htmlspecialchars($jp['color'] ?? '#999') ?>"></span>
                            <?= htmlspecialchars($jp['nama'] ?? '-') ?>
                        </td>
                        <?php $col++; endforeach; ?>
                        <!-- Fill remaining cells -->
                        <?php while ($col % 3 !== 0): ?>
                        <td>&nbsp;</td>
                        <?php $col++; endwhile; ?>
                    </tr>
                </table>
                <?php endif; ?>

                <!-- SIGNATURE (3 columns) -->
                <table class="sig-table">
                    <tr>
                        <td style="width:33%; padding-top:20px; padding-bottom:30px;">
                            Dibuat Oleh,<br><b>Krani Afd</b>
                        </td>
                        <td style="width:34%; padding-top:20px; padding-bottom:30px;">
                            Diperiksa Oleh,<br><b>Asisten Afd</b>
                        </td>
                        <td style="width:33%; padding-top:20px; padding-bottom:30px;">
                            Disetujui Oleh,<br><b>Askep</b>
                        </td>
                    </tr>
                    <tr>
                        <td>( ...................................... )</td>
                        <td>( ...................................... )</td>
                        <td>( ...................................... )</td>
                    </tr>
                </table>
            </td>

            <!-- RIGHT: SUMMARY TABLE -->
            <td style="width: 40%; padding: 6px;">
                <table class="rekap-table">
                    <thead>
                        <tr>
                            <th style="width:30px">No</th>
                            <th>Jenis Pekerjaan</th>
                            <th>Blok</th>
                            <th>Fisik S/D<br>(Ha)</th>
                            <th>HK<br>S/D</th>
                            <th>Bahan Kimia<br>S/D</th>
                            <th>Campuran<br>S/D</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rekapRows)): ?>
                        <tr>
                            <td colspan="7" style="padding:15px; color:#64748b;">Belum ada data rekap.</td>
                        </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($rekapRows as $jp): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td style="text-align:left; padding-left:8px;">
                                    <span class="color-sq" style="background-color:<?= htmlspecialchars($jp['color'] ?? '#999') ?>"></span>
                                    <?= htmlspecialchars($jp['nama'] ?? '-') ?>
                                </td>
                                <td><?= (int)($jp['blok_count'] ?? 0) ?></td>
                                <td><?= nf($jp['fisik_sd'] ?? 0) ?></td>
                                <td><?= nf($jp['hk_sd'] ?? 0) ?></td>
                                <td><?= nf($jp['bahan_kimia_sd'] ?? 0) ?></td>
                                <td><?= nf($jp['campuran_sd'] ?? 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($rekapRows)): ?>
                    <tfoot>
                        <tr>
                            <td colspan="2">TOTAL</td>
                            <td><?= $totalBlok ?></td>
                            <td><?= nf($totalFisik) ?></td>
                            <td><?= nf($totalHk) ?></td>
                            <td><?= nf($totalBahan) ?></td>
                            <td><?= nf($totalCampuran) ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>

                <!-- Footer motto -->
                <div style="text-align:center; margin-top:16px; font-style:italic; font-size:11px; color:#164e63; font-weight:bold;">
                    Jujur, Tulus, Ikhlas
                </div>
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
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('peta_rekap_' . date('YmdHis') . '.pdf', ['Attachment' => false]);
exit;
?>
