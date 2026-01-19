<?php
// pages/export_cv.php
declare(strict_types=1);
session_start();

// 1. Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    http_response_code(403); 
    exit('Unauthorized'); 
}

require_once '../config/database.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 2. Ambil ID Karyawan
$id = $_GET['id'] ?? 0;
if (empty($id)) exit("ID Karyawan tidak ditemukan.");

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 3. Ambil Data Karyawan
    $stmt = $conn->prepare("SELECT * FROM data_karyawan WHERE id = ?");
    $stmt->execute([$id]);
    $k = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$k) exit("Data karyawan tidak ditemukan.");

    // 4. Ambil Data Keluarga
    $stmtKel = $conn->prepare("SELECT * FROM data_keluarga WHERE karyawan_id = ? ORDER BY tanggal_lahir ASC");
    $stmtKel->execute([$id]);
    $keluarga = $stmtKel->fetchAll(PDO::FETCH_ASSOC);

    // 5. Ambil Data Peringatan (SP)
    $stmtSP = $conn->prepare("SELECT * FROM data_peringatan WHERE karyawan_id = ? ORDER BY tanggal_sp DESC");
    $stmtSP->execute([$id]);
    $riwayatSP = $stmtSP->fetchAll(PDO::FETCH_ASSOC);

    // 6. Helper: Format Tanggal Indonesia
    function tgl_indo($tanggal){
        if(empty($tanggal) || $tanggal == '0000-00-00') return '-';
        $bulan = array (
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        );
        $pecahkan = explode('-', $tanggal);
        return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
    }

    // 7. Helper: Proses Foto ke Base64 (Agar tampil di PDF)
    $pathFoto = '../uploads/profil/' . ($k['foto_profil'] ?? 'default.png');
    $base64Foto = '';
    
    if (file_exists($pathFoto) && !is_dir($pathFoto)) {
        $type = pathinfo($pathFoto, PATHINFO_EXTENSION);
        $data = file_get_contents($pathFoto);
        $base64Foto = 'data:image/' . $type . ';base64,' . base64_encode($data);
    } else {
        // Fallback jika tidak ada foto (Placeholder abu-abu)
        $base64Foto = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='; 
    }

    // =========================================================================
    // HTML OUTPUT STARTS HERE
    // =========================================================================
    ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CV_<?= htmlspecialchars($k['nama_lengkap']) ?></title>
    <style>
        @page { margin: 30px; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        
        /* HEADER */
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #065f46; padding-bottom: 10px; }
        .header h1 { font-size: 16px; color: #065f46; margin: 0; text-transform: uppercase; font-weight: bold; }
        .header h2 { font-size: 12px; color: #047857; margin: 5px 0 0 0; font-weight: normal; }

        /* SECTIONS */
        .section-title { 
            background-color: #065f46; 
            color: white; 
            padding: 5px 10px; 
            font-weight: bold; 
            font-size: 11px; 
            margin-top: 20px; 
            margin-bottom: 10px; 
            border-radius: 2px;
        }

        /* TABLES */
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: middle; }
        table.data-table th { background-color: #ecfdf5; color: #065f46; font-weight: bold; text-align: center; }

        /* INFO LAYOUT (Foto Kanan) */
        .info-container { width: 100%; margin-bottom: 10px; }
        .info-left { float: left; width: 75%; }
        .info-right { float: right; width: 20%; text-align: right; }
        
        .row-info { margin-bottom: 4px; display: block; clear: both; }
        .label { float: left; width: 130px; font-weight: bold; color: #555; }
        .colon { float: left; width: 10px; }
        .value { float: left; width: calc(100% - 150px); }

        /* FOTO */
        .foto-profil { 
            width: 100%; 
            max-width: 113px; /* setara 3x4 cm approx */
            height: auto; 
            border: 1px solid #999; 
            padding: 3px; 
            background: #fff;
        }

        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>

    <div class="header">
        <h1>DAFTAR RIWAYAT HIDUP</h1>
        <h2>PT MULTIMAS NABATI ASAHAN</h2>
    </div>

    <div class="info-container clearfix">
        <div class="info-left">
            <div class="row-info">
                <div class="label">Nama Lengkap</div><div class="colon">:</div>
                <div class="value"><strong><?= htmlspecialchars($k['nama_lengkap']) ?></strong></div>
            </div>
            <div class="row-info">
                <div class="label">SAP ID</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['id_sap']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">NIK KTP</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['nik_ktp']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">Tempat, Tgl Lahir</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['tempat_lahir'] ?? '-') ?>, <?= tgl_indo($k['tanggal_lahir']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">Jenis Kelamin</div><div class="colon">:</div>
                <div class="value"><?= $k['gender'] == 'L' ? 'Laki-Laki' : 'Perempuan' ?></div>
            </div>
            <div class="row-info">
                <div class="label">Agama</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['agama']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">No. Handphone</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['no_hp']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">Status Pernikahan</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['s_kel']) ?></div>
            </div>
        </div>

        <div class="info-right">
            <img src="<?= $base64Foto ?>" class="foto-profil">
        </div>
    </div>

    <div class="section-title">DATA KEPEGAWAIAN</div>
    <div class="info-container clearfix">
        <div class="info-left" style="width: 100%;"> <div class="row-info">
                <div class="label">Jabatan Real</div><div class="colon">:</div>
                <div class="value"><strong><?= htmlspecialchars($k['jabatan_real']) ?></strong></div>
            </div>
            <div class="row-info">
                <div class="label">Jabatan SAP</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['jabatan_sap']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">Afdeling / Unit</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['afdeling']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">Status Karyawan</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['status_karyawan']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">Grade / Golongan</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['person_grade']) ?> / <?= htmlspecialchars($k['phdp_golongan']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">TMT Masuk Kerja</div><div class="colon">:</div>
                <div class="value"><?= tgl_indo($k['tmt_kerja']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">TMT MBT</div><div class="colon">:</div>
                <div class="value"><?= tgl_indo($k['tmt_mbt']) ?></div>
            </div>
            <div class="row-info">
                <div class="label">Rekening Bank</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['nama_bank']) ?> - <?= htmlspecialchars($k['no_rekening']) ?> (<?= htmlspecialchars($k['nama_pemilik_rekening']) ?>)</div>
            </div>
            <div class="row-info">
                <div class="label">BPJS / Jamsostek</div><div class="colon">:</div>
                <div class="value"><?= htmlspecialchars($k['bpjs_id']) ?> / <?= htmlspecialchars($k['jamsostek_id']) ?></div>
            </div>
        </div>
    </div>

    <div class="section-title">DATA KELUARGA / TANGGUNGAN</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Nama Anggota</th>
                <th style="width: 15%;">Hubungan</th>
                <th style="width: 15%;">TTL</th>
                <th style="width: 15%;">Pendidikan</th>
                <th style="width: 25%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($keluarga)): ?>
                <tr><td colspan="6" style="text-align: center;">- Tidak ada data keluarga -</td></tr>
            <?php else: ?>
                <?php $no=1; foreach($keluarga as $row): ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_anggota']) ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($row['hubungan']) ?></td>
                    <td><?= htmlspecialchars($row['tempat_lahir']) ?>, <?= date('d-m-Y', strtotime($row['tanggal_lahir'])) ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($row['pendidikan']) ?></td>
                    <td><?= htmlspecialchars($row['keterangan']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">RIWAYAT SURAT PERINGATAN (SP)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">No. Surat</th>
                <th style="width: 10%;">Jenis SP</th>
                <th style="width: 15%;">Tgl. SP</th>
                <th style="width: 15%;">Masa Berlaku</th>
                <th style="width: 40%;">Pelanggaran</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($riwayatSP)): ?>
                <tr><td colspan="6" style="text-align: center;">- Tidak ada riwayat sanksi -</td></tr>
            <?php else: ?>
                <?php $no=1; foreach($riwayatSP as $row): ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['no_surat']) ?></td>
                    <td style="text-align: center; color: red; font-weight: bold;"><?= htmlspecialchars($row['jenis_sp']) ?></td>
                    <td style="text-align: center;"><?= date('d-m-Y', strtotime($row['tanggal_sp'])) ?></td>
                    <td style="text-align: center;"><?= date('d-m-Y', strtotime($row['masa_berlaku'])) ?></td>
                    <td><?= htmlspecialchars($row['pelanggaran']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <br><br>
    <div style="width: 100%; text-align: right;">
        <p>Medan, <?= tgl_indo(date('Y-m-d')) ?></p>
        <br><br><br>
        <p><strong>( <?= htmlspecialchars($k['nama_lengkap']) ?> )</strong></p>
    </div>

</body>
</html>
<?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait'); // CV biasanya Portrait
    $dompdf->render();

    // Nama file saat didownload
    $filename = 'CV_' . str_replace(' ', '_', $k['nama_lengkap']) . '_' . date('Ymd') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => 0]); // 0 = Preview di browser, 1 = Download
    
} catch (Throwable $e) {
    http_response_code(500);
    exit("Gagal membuat PDF: " . $e->getMessage());
}
?>