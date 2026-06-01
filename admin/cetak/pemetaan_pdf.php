<?php
// admin/cetak/pemetaan_pdf.php
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
    $map_image = isset($_POST['map_image']) ? $_POST['map_image'] : '';

    if (empty($unit_id) || empty($kebun_id) || empty($map_image)) {
        exit("Data tidak lengkap untuk diexport.");
    }

    // Ambil Info Header
    $stmtInfo = $conn->prepare("SELECT u.nama_unit, k.nama_kebun 
                                FROM units u 
                                LEFT JOIN md_kebun k ON u.kebun_id = k.id 
                                WHERE u.id = ?");
    $stmtInfo->execute([$unit_id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    
    $nama_unit  = $info ? $info['nama_unit'] : 'UNIT';
    $nama_kebun = $info ? $info['nama_kebun'] : 'KEBUN';

    // Ambil Data Realisasi Pemetaan Hari Ini
    $sql = "SELECT p.*, b.kode as nama_blok 
            FROM tr_pemetaan p
            LEFT JOIN md_blok b ON p.blok_id = b.id
            WHERE p.kebun_id = ? AND p.unit_id = ? 
            ORDER BY p.tanggal_realisasi DESC, p.id DESC LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$kebun_id, $unit_id]);
    $data_realisasi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Bulan Pekerjaan
    $bulan_tahun = date('F Y');

} catch (Exception $e) {
    exit('DB Error: ' . $e->getMessage());
}

// Function Helper untuk format angka
function nf($v) {
    return (empty($v) || $v == 0) ? '-' : number_format((float)$v, 2, ',', '.');
}

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Peta & Realisasi</title>
    <style>
        @page { margin: 10mm; size: A4 landscape; }
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; padding: 0; color: #1e293b; }
        
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 11px;
            text-align: center;
        }
        .header-table td {
            border: 1px solid #000;
            padding: 5px;
        }
        .bg-green { background-color: #065F46; color: white; }
        .bg-yellow { background-color: #FBBF24; color: black; }
        .bg-blue { background-color: #1E40AF; color: white; }

        .content-container {
            width: 100%;
            border-collapse: collapse;
        }
        .content-container > tbody > tr > td {
            vertical-align: top;
            padding: 0;
            border: 1px solid #000;
        }

        .map-wrapper {
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
            text-align: center;
        }
        .map-img {
            max-width: 100%;
            max-height: 480px; /* Batasi agar tidak terlalu memakan A4 */
            object-fit: contain;
            border: 1px solid #ccc;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 8px;
        }
        .data-table th {
            background-color: #f1f5f9;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="bg-green" style="width:30%;">
                PT. PERKEBUNAN NUSANTARA IV<br>
                REGIONAL III - DISTRIK BARAT<br>
                KEBUN <?= strtoupper($nama_kebun) ?>
            </td>
            <td class="bg-yellow" style="width:40%; font-size:14px;">
                PETA KETERANGAN (<?= strtoupper($nama_unit) ?>)
            </td>
            <td class="bg-blue" style="width:30%;">
                BULAN : <?= strtoupper($bulan_tahun) ?><br>
                PEKERJAAN : GIS & PEMETAAN
            </td>
        </tr>
    </table>

    <table class="content-container">
        <tr>
            <!-- AREA KIRI: PETA -->
            <td style="width: 50%;">
                <div class="map-wrapper">
                    <img src="<?= $map_image ?>" class="map-img" alt="Peta Kerja">
                </div>
            </td>

            <!-- AREA KANAN: TABEL REALISASI -->
            <td style="width: 50%;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 12%;">TANGGAL</th>
                            <th rowspan="2" style="width: 15%;">BLOK</th>
                            <th colspan="2">Fisik (Ha, Pkk)</th>
                            <th colspan="2">HK</th>
                            <th colspan="2">Bahan Kimia</th>
                            <th colspan="2">Campuran</th>
                        </tr>
                        <tr>
                            <th>H. INI</th>
                            <th>S/D</th>
                            <th>H. INI</th>
                            <th>S/D</th>
                            <th>H. INI</th>
                            <th>S/D</th>
                            <th>H. INI</th>
                            <th>S/D</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data_realisasi)): ?>
                        <tr>
                            <td colspan="10" style="padding: 10px; color:#64748b;">Belum ada data realisasi yang disimpan.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($data_realisasi as $row): ?>
                            <tr>
                                <td><?= $row['tanggal_realisasi'] ? date('d-m-Y', strtotime($row['tanggal_realisasi'])) : '-' ?></td>
                                <td style="font-weight:bold;"><?= htmlspecialchars($row['nama_blok'] ?? '') ?></td>
                                <td><?= nf($row['fisik_hari_ini']) ?></td>
                                <td><?= nf($row['fisik_sd']) ?></td>
                                <td><?= nf($row['hk_hari_ini']) ?></td>
                                <td><?= nf($row['hk_sd']) ?></td>
                                <td><?= nf($row['bahan_kimia_hari_ini']) ?></td>
                                <td><?= nf($row['bahan_kimia_sd']) ?></td>
                                <td><?= nf($row['campuran_hari_ini']) ?></td>
                                <td><?= nf($row['campuran_sd']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Extra blank rows just to mimic the paper look -->
                        <?php for($i=0; $i < (20 - count($data_realisasi)); $i++): ?>
                        <tr>
                            <td style="height: 15px;"></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
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
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('peta_realisasi_' . date('YmdHis') . '.pdf', ['Attachment' => false]);
exit;
?>
