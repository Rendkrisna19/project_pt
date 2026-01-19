<?php
// pages/export_cv.php
// FIXED VERSION with Better Error Handling

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    http_response_code(403); 
    exit('Unauthorized'); 
}

require_once '../config/database.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? 0;
if (empty($id)) {
    exit("ID Karyawan tidak ditemukan.");
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Ambil Data Karyawan
    $stmt = $conn->prepare("SELECT * FROM data_karyawan WHERE id = ?");
    $stmt->execute([$id]);
    $k = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$k) {
        exit("Data karyawan dengan ID $id tidak ditemukan.");
    }

    // Ambil Data Keluarga
    $stmtKel = $conn->prepare("SELECT * FROM data_keluarga WHERE karyawan_id = ? ORDER BY tanggal_lahir ASC");
    $stmtKel->execute([$id]);
    $keluarga = $stmtKel->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Data Peringatan
    $stmtSP = $conn->prepare("SELECT * FROM data_peringatan WHERE karyawan_id = ? ORDER BY tanggal_sp DESC");
    $stmtSP->execute([$id]);
    $riwayatSP = $stmtSP->fetchAll(PDO::FETCH_ASSOC);

    // Helper: Format Tanggal
    function tgl_indo($tanggal){
        if(empty($tanggal) || $tanggal == '0000-00-00') return '-';
        $bulan = array (
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        );
        $pecahkan = explode('-', $tanggal);
        return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
    }

    // Proses Foto - FIXED
    $pathFoto = '../uploads/profil/' . ($k['foto_profil'] ?? '');
    $base64Foto = '';
    
    if (!empty($k['foto_profil']) && file_exists($pathFoto) && !is_dir($pathFoto)) {
        $imageInfo = @getimagesize($pathFoto);
        if ($imageInfo !== false) {
            $type = image_type_to_extension($imageInfo[2], false);
            $data = file_get_contents($pathFoto);
            $base64Foto = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
    }
    
    // Fallback jika foto tidak ada
    if (empty($base64Foto)) {
        $base64Foto = 'data:image/svg+xml;base64,' . base64_encode('
        <svg width="150" height="200" xmlns="http://www.w3.org/2000/svg">
            <rect width="150" height="200" fill="#e5e7eb"/>
            <text x="50%" y="50%" font-family="Arial" font-size="14" fill="#9ca3af" text-anchor="middle" dy=".3em">No Photo</text>
        </svg>');
    }

    // Logo PTPN IV
    $logoPTPN = 'data:image/svg+xml;base64,' . base64_encode('
    <svg width="80" height="80" xmlns="http://www.w3.org/2000/svg">
        <circle cx="40" cy="40" r="38" fill="#0891b2" stroke="#0e7490" stroke-width="2"/>
        <text x="40" y="35" font-family="Arial" font-size="24" font-weight="bold" fill="white" text-anchor="middle">PTPN</text>
        <text x="40" y="55" font-family="Arial" font-size="16" font-weight="bold" fill="#ecfeff" text-anchor="middle">IV</text>
    </svg>');

    ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CV_<?= htmlspecialchars($k['nama_lengkap']) ?></title>
    <style>
        @page { margin: 20px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            font-size: 9.5px; 
            color: #1f2937; 
            line-height: 1.5; 
        }

        .header-wrapper {
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
            padding: 20px;
            margin: -20px -20px 20px -20px;
            border-bottom: 4px solid #06b6d4;
        }

        .header-content { width: 100%; }
        
        .logo-section {
            float: left;
            width: 90px;
            padding-right: 15px;
        }

        .logo-img {
            width: 70px;
            height: 70px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
        }

        .company-section {
            margin-left: 90px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: white;
            margin-bottom: 3px;
        }

        .company-subtitle {
            font-size: 11px;
            color: #e0f2fe;
            margin-bottom: 2px;
        }

        .doc-title {
            font-size: 13px;
            color: #fef3c7;
            font-weight: bold;
            margin-top: 8px;
            text-transform: uppercase;
        }

        .profile-card {
            background: #ecfeff;
            border: 2px solid #06b6d4;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            clear: both;
        }

        .profile-info {
            float: left;
            width: 65%;
            padding-right: 15px;
        }

        .profile-photo {
            float: right;
            width: 35%;
            text-align: center;
        }

        .foto-profil {
            width: 120px;
            height: 160px;
            border: 3px solid #0891b2;
            border-radius: 8px;
            object-fit: cover;
        }

        .employee-name {
            font-size: 16px;
            font-weight: bold;
            color: #0e7490;
            margin-bottom: 5px;
            border-bottom: 2px solid #06b6d4;
            padding-bottom: 5px;
        }

        .employee-id {
            font-size: 10px;
            color: #0891b2;
            font-weight: bold;
            background: #cffafe;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 8px;
        }

        .info-row {
            margin-bottom: 5px;
            clear: both;
        }

        .info-label {
            float: left;
            width: 140px;
            font-weight: bold;
            color: #0e7490;
            padding: 3px 0;
        }

        .info-colon {
            float: left;
            width: 15px;
        }

        .info-value {
            margin-left: 155px;
            color: #374151;
        }

        .section-header {
            background: linear-gradient(to right, #0891b2, #06b6d4);
            color: white;
            padding: 8px 12px;
            margin: 15px 0 10px 0;
            border-radius: 5px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            clear: both;
        }

        .content-box {
            background: #f0fdfa;
            border-left: 4px solid #0891b2;
            padding: 12px;
            margin-bottom: 12px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9px;
        }

        .data-table thead {
            background: linear-gradient(to right, #0891b2, #06b6d4);
            color: white;
        }

        .data-table th {
            padding: 7px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #0891b2;
        }

        .data-table td {
            padding: 6px;
            border: 1px solid #d1d5db;
            background: white;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f0fdfa;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 8px;
        }

        .badge-tetap { background: #d1fae5; color: #065f46; }
        .badge-sp1 { background: #dbeafe; color: #1e40af; }
        .badge-sp2 { background: #fed7aa; color: #9a3412; }
        .badge-sp3 { background: #fecaca; color: #991b1b; }

        .signature-section {
            margin-top: 25px;
            text-align: right;
            padding-right: 20px;
            clear: both;
        }

        .signature-box {
            display: inline-block;
            text-align: center;
            min-width: 200px;
        }

        .signature-line {
            border-top: 1px solid #374151;
            margin-top: 60px;
            padding-top: 5px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #0891b2;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
        }

        .highlight {
            background: #fef3c7;
            padding: 2px 4px;
            border-radius: 2px;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>

    <div class="header-wrapper">
        <div class="header-content clearfix">
            <div class="logo-section">
                <img src="<?= $logoPTPN ?>" class="logo-img" alt="Logo">
            </div>
            <div class="company-section">
                <div class="company-name">PTPN IV - REGIONAL 3</div>
                <div class="company-subtitle">PT Perkebunan Nusantara IV (Persero)</div>
                <div class="company-subtitle">Jl. Letjen Suprapto No.2, Medan, Sumatera Utara</div>
                <div class="doc-title">CURRICULUM VITAE KARYAWAN</div>
            </div>
        </div>
    </div>

    <div class="profile-card clearfix">
        <div class="profile-info">
            <div class="employee-name"><?= strtoupper(htmlspecialchars($k['nama_lengkap'])) ?></div>
            <div class="employee-id">SAP ID: <?= htmlspecialchars($k['id_sap']) ?></div>
            
            <div class="info-row clearfix">
                <div class="info-label">NIK KTP</div>
                <div class="info-colon">:</div>
                <div class="info-value"><?= htmlspecialchars($k['nik_ktp'] ?: '-') ?></div>
            </div>
            <div class="info-row clearfix">
                <div class="info-label">Tempat, Tgl Lahir</div>
                <div class="info-colon">:</div>
                <div class="info-value"><?= htmlspecialchars($k['tempat_lahir'] ?: '-') ?>, <?= tgl_indo($k['tanggal_lahir']) ?></div>
            </div>
            <div class="info-row clearfix">
                <div class="info-label">Jenis Kelamin</div>
                <div class="info-colon">:</div>
                <div class="info-value"><?= $k['gender'] == 'L' ? 'Laki-Laki' : 'Perempuan' ?></div>
            </div>
            <div class="info-row clearfix">
                <div class="info-label">Agama</div>
                <div class="info-colon">:</div>
                <div class="info-value"><?= htmlspecialchars($k['agama'] ?: '-') ?></div>
            </div>
            <div class="info-row clearfix">
                <div class="info-label">Status Pernikahan</div>
                <div class="info-colon">:</div>
                <div class="info-value"><?= htmlspecialchars($k['s_kel'] ?: '-') ?></div>
            </div>
            <div class="info-row clearfix">
                <div class="info-label">No. Handphone</div>
                <div class="info-colon">:</div>
                <div class="info-value"><strong><?= htmlspecialchars($k['no_hp'] ?: '-') ?></strong></div>
            </div>
        </div>
        <div class="profile-photo">
            <img src="<?= $base64Foto ?>" class="foto-profil" alt="Foto">
            <div style="margin-top: 5px; font-size: 8px; color: #6b7280;">Foto Karyawan</div>
        </div>
    </div>

    <div class="section-header">DATA KEPEGAWAIAN</div>
    <div class="content-box">
        <div class="info-row clearfix">
            <div class="info-label">Jabatan Real</div>
            <div class="info-colon">:</div>
            <div class="info-value"><strong class="highlight"><?= htmlspecialchars($k['jabatan_real'] ?: '-') ?></strong></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">Jabatan SAP</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= htmlspecialchars($k['jabatan_sap'] ?: '-') ?></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">Afdeling / Unit</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= htmlspecialchars($k['afdeling'] ?: '-') ?></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">Status Karyawan</div>
            <div class="info-colon">:</div>
            <div class="info-value">
                <span class="badge badge-tetap"><?= htmlspecialchars($k['status_karyawan']) ?></span>
            </div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">Grade / Golongan</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= htmlspecialchars($k['person_grade'] ?: '-') ?> / <?= htmlspecialchars($k['phdp_golongan'] ?: '-') ?></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">TMT Masuk Kerja</div>
            <div class="info-colon">:</div>
            <div class="info-value"><strong><?= tgl_indo($k['tmt_kerja']) ?></strong></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">TMT MBT</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= tgl_indo($k['tmt_mbt']) ?></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">TMT Pensiun</div>
            <div class="info-colon">:</div>
            <div class="info-value" style="color: #dc2626; font-weight: bold;"><?= tgl_indo($k['tmt_pensiun']) ?></div>
        </div>
    </div>

    <div class="section-header">DATA KEUANGAN & ASURANSI</div>
    <div class="content-box">
        <div class="info-row clearfix">
            <div class="info-label">Rekening Bank</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= htmlspecialchars($k['nama_bank'] ?: '-') ?> - <?= htmlspecialchars($k['no_rekening'] ?: '-') ?></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">Nama Pemilik Rek</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= htmlspecialchars($k['nama_pemilik_rekening'] ?: '-') ?></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">NPWP</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= htmlspecialchars($k['npwp'] ?: '-') ?></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">BPJS Kesehatan</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= htmlspecialchars($k['bpjs_id'] ?: '-') ?></div>
        </div>
        <div class="info-row clearfix">
            <div class="info-label">BPJS Ketenagakerjaan</div>
            <div class="info-colon">:</div>
            <div class="info-value"><?= htmlspecialchars($k['jamsostek_id'] ?: '-') ?></div>
        </div>
    </div>

    <div class="section-header">DATA KELUARGA / TANGGUNGAN</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Nama Lengkap</th>
                <th style="width: 12%;">Hubungan</th>
                <th style="width: 23%;">Tempat, Tgl Lahir</th>
                <th style="width: 13%;">Pendidikan</th>
                <th style="width: 22%;">Pekerjaan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($keluarga)): ?>
                <tr><td colspan="6" style="text-align: center; color: #9ca3af; font-style: italic;">Tidak ada data tanggungan keluarga</td></tr>
            <?php else: ?>
                <?php $no=1; foreach($keluarga as $row): ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_anggota']) ?></td>
                    <td style="text-align: center;"><span class="badge badge-tetap"><?= htmlspecialchars($row['hubungan']) ?></span></td>
                    <td><?= htmlspecialchars($row['tempat_lahir'] ?: '-') ?>, <?= !empty($row['tanggal_lahir']) ? date('d-m-Y', strtotime($row['tanggal_lahir'])) : '-' ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($row['pendidikan'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['pekerjaan'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-header">RIWAYAT SURAT PERINGATAN</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">No. Surat</th>
                <th style="width: 12%;">Jenis SP</th>
                <th style="width: 15%;">Tanggal SP</th>
                <th style="width: 15%;">Masa Berlaku</th>
                <th style="width: 33%;">Pelanggaran</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($riwayatSP)): ?>
                <tr><td colspan="6" style="text-align: center; color: #10b981; font-weight: bold;">Tidak ada riwayat surat peringatan</td></tr>
            <?php else: ?>
                <?php $no=1; foreach($riwayatSP as $row): 
                    $badgeClass = 'badge-sp1';
                    if($row['jenis_sp'] == 'SP2') $badgeClass = 'badge-sp2';
                    if($row['jenis_sp'] == 'SP3') $badgeClass = 'badge-sp3';
                ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['no_surat']) ?></td>
                    <td style="text-align: center;"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['jenis_sp']) ?></span></td>
                    <td style="text-align: center;"><?= date('d M Y', strtotime($row['tanggal_sp'])) ?></td>
                    <td style="text-align: center;"><?= !empty($row['masa_berlaku']) ? date('d M Y', strtotime($row['masa_berlaku'])) : 'Permanen' ?></td>
                    <td style="font-size: 8px;"><?= htmlspecialchars($row['pelanggaran'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div>Medan, <?= tgl_indo(date('Y-m-d')) ?></div>
            <div class="signature-line">
                <strong><?= htmlspecialchars($k['nama_lengkap']) ?></strong><br>
                <small style="color: #6b7280;">Karyawan Yang Bersangkutan</small>
            </div>
        </div>
    </div>

    <div class="footer">
        <div>Dokumen ini dicetak otomatis oleh Sistem Informasi SDM PTPN IV Regional 3</div>
        <div>Tanggal Cetak: <?= date('d F Y, H:i:s') ?> WIB</div>
    </div>

</body>
</html>
<?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $options->set('chroot', realpath('../'));
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'CV_' . str_replace(' ', '_', $k['nama_lengkap']) . '_' . date('Ymd') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => 0]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h2>Error Details:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>