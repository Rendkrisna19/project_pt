<?php
// admin/cetak/pemetaan_gabungan_pdf.php
// Cetak Gabungan — semua jenis pekerjaan per unit dalam 1 PDF
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
    $map_images = isset($_POST['map_images']) && is_array($_POST['map_images']) ? $_POST['map_images'] : [];
    $bulan    = isset($_POST['bulan']) ? $_POST['bulan'] : date('Y-m');

    if (empty($unit_id) || empty($kebun_id) || empty($map_images)) {
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

    // Bulan
    $bulan_tahun = date('F Y', strtotime($bulan . '-01'));

    // Ambil semua Jenis Pekerjaan yang PUNYA DATA di unit ini + bulan ini
    $sqlJp = "SELECT DISTINCT jp.id, jp.nama 
              FROM md_jenis_pekerjaan jp
              INNER JOIN tr_pemetaan tp ON tp.jenis_pekerjaan_id = jp.id
              WHERE tp.kebun_id = ? AND tp.unit_id = ?
                AND DATE_FORMAT(tp.tanggal_realisasi, '%Y-%m') = ?
              ORDER BY jp.nama ASC";
    $stmtJp = $conn->prepare($sqlJp);
    $stmtJp->execute([$kebun_id, $unit_id, $bulan]);
    $list_jp = $stmtJp->fetchAll(PDO::FETCH_ASSOC);

    if (empty($list_jp)) {
        exit("Tidak ada data realisasi untuk unit ini pada bulan $bulan_tahun.");
    }

    // Ambil data realisasi per JP
    $data_per_jp = [];
    foreach ($list_jp as $jp) {
        $sqlData = "SELECT * FROM tr_pemetaan 
                    WHERE kebun_id = ? AND unit_id = ? AND jenis_pekerjaan_id = ?
                      AND DATE_FORMAT(tanggal_realisasi, '%Y-%m') = ?
                    ORDER BY tanggal_realisasi ASC, id ASC LIMIT 50";
        $stmtData = $conn->prepare($sqlData);
        $stmtData->execute([$kebun_id, $unit_id, $jp['id'], $bulan]);
        $data_per_jp[$jp['id']] = [
            'nama' => $jp['nama'],
            'rows' => $stmtData->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

} catch (Exception $e) {
    exit('DB Error: ' . $e->getMessage());
}

// Helper format angka
function nf($v) {
    return (empty($v) || $v == 0) ? '-' : number_format((float)$v, 2, ',', '.');
}

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Peta & Realisasi — Gabungan</title>
    <style>
        @page { margin: 10mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; padding: 0; color: #1e293b; }

        /* ===== PAGE BREAK ===== */
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }

        /* ===== HEADER ===== */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            font-weight: bold;
            font-size: 11px;
            text-align: center;
        }
        .header-table td {
            border: 1.5px solid #000;
            padding: 6px 5px;
        }
        .bg-green { background-color: #065F46; color: white; }
        .bg-yellow { background-color: #FBBF24; color: black; }
        .bg-blue { background-color: #1E40AF; color: white; }

        /* ===== MAIN LAYOUT ===== */
        .content-container {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
        }
        .content-container > tbody > tr > td {
            vertical-align: top;
            padding: 0;
            border: 1.5px solid #000;
        }

        /* ===== MAP ===== */
        .map-wrapper {
            width: 100%;
            padding: 0;
            text-align: center;
        }
        .map-img {
            max-width: 100%;
            max-height: 380px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        /* ===== SIGNATURE TABLE ===== */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 10px;
            text-align: center;
            border-top: 1.5px solid #000;
        }
        .sig-table td {
            border: none;
            padding: 4px 5px;
        }
        .sig-table td.sig-left {
            width: 50%;
            padding-top: 15px;
            padding-bottom: 40px;
        }
        .sig-table td.sig-right {
            width: 50%;
            padding-top: 15px;
            padding-bottom: 40px;
        }

        /* ===== DATA TABLE ===== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 4px 3px;
            font-size: 8px;
            vertical-align: middle;
        }
        .data-table thead th {
            background-color: #f1f5f9;
            font-weight: bold;
            border: 1px solid #000;
        }
        .data-table tbody td {
            border: 1px solid #000;
        }
        .data-table tbody td.empty-cell {
            height: 15px;
            border: 1px solid #000;
        }
    </style>
</head>
<body>

<?php $pageCount = 0; ?>
<?php foreach ($data_per_jp as $jp_id => $jpData): ?>
<?php $pageCount++; ?>
<div class="page">

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
                PEKERJAAN : <?= strtoupper($jpData['nama']) ?>
            </td>
        </tr>
    </table>

    <table class="content-container">
        <tr>
            <!-- AREA KIRI: PETA -->
            <td style="width: 50%; padding-bottom: 0;">
                <div class="map-wrapper">
                    <img src="<?= $map_images[$jp_id] ?? '' ?>" class="map-img" alt="Peta Kerja - <?= htmlspecialchars($jpData['nama']) ?>">
                </div>
                <!-- TANDA TANGAN -->
                <table class="sig-table">
                    <tr>
                        <td class="sig-left">
                            Dibuat Oleh,<br>
                            <b>Asst Afdeling</b>
                        </td>
                        <td class="sig-right">
                            Diperiksa Oleh,<br>
                            <b>Asisten Kepala</b>
                        </td>
                    </tr>
                    <tr>
                        <td>( ...................................... )</td>
                        <td>( ...................................... )</td>
                    </tr>
                </table>
            </td>

            <!-- AREA KANAN: TABEL REALISASI -->
            <td style="width: 50%;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th rowspan="3" style="width: 12%;">TANGGAL</th>
                            <th rowspan="3" style="width: 15%;">BLOK</th>
                            <th colspan="8">RENCANA / REALISASI</th>
                        </tr>
                        <tr>
                            <th colspan="2">Fisik (Ha, pkk, dll)</th>
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
                        <?php $rows = $jpData['rows']; ?>
                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="10" style="padding: 10px; color:#64748b;">Belum ada data realisasi.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= $row['tanggal_realisasi'] ? date('d-m-Y', strtotime($row['tanggal_realisasi'])) : '-' ?></td>
                                <td style="font-weight:bold;"><?= htmlspecialchars($row['blok_nama'] ?? '') ?></td>
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
                        
                        <?php $rowCount = count($rows); ?>
                        <?php for($i = 0; $i < max(0, 20 - $rowCount); $i++): ?>
                        <tr>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                            <td class="empty-cell">&nbsp;</td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
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

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('peta_gabungan_' . $nama_unit . '_' . date('YmdHis') . '.pdf', ['Attachment' => false]);
exit;
?>
