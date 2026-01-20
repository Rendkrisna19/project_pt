<?php
// pages/export_cv.php
// RE-DESIGNED PROFESSIONAL VERSION

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Cek Sesi (Sesuaikan dengan logic login Anda)
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

    // 1. Ambil Data Karyawan
    $stmt = $conn->prepare("SELECT * FROM data_karyawan WHERE id = ?");
    $stmt->execute([$id]);
    $k = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$k) {
        exit("Data karyawan dengan ID $id tidak ditemukan.");
    }

    // 2. Ambil Data Keluarga
    $stmtKel = $conn->prepare("SELECT * FROM data_keluarga WHERE karyawan_id = ? ORDER BY tanggal_lahir ASC");
    $stmtKel->execute([$id]);
    $keluarga = $stmtKel->fetchAll(PDO::FETCH_ASSOC);

    // 3. Ambil Data Peringatan
    $stmtSP = $conn->prepare("SELECT * FROM data_peringatan WHERE karyawan_id = ? ORDER BY tanggal_sp DESC");
    $stmtSP->execute([$id]);
    $riwayatSP = $stmtSP->fetchAll(PDO::FETCH_ASSOC);

    // Helper: Format Tanggal Indonesia
    function tgl_indo($tanggal){
        if(empty($tanggal) || $tanggal == '0000-00-00') return '-';
        $bulan = array (
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        );
        $pecahkan = explode('-', $tanggal);
        return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
    }

    // ---------------------------------------------------------
    // IMAGE HANDLING (BASE64) - Agar gambar muncul di PDF
    // ---------------------------------------------------------

    // A. LOGO PERUSAHAAN (Dari ../assets/images/)
    $pathLogo = '../assets/images/logo_ptpn.png'; // Pastikan path ini benar
    $base64Logo = '';

    if (file_exists($pathLogo)) {
        $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
        $data = file_get_contents($pathLogo);
        $base64Logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
    } else {
        // Fallback Logo SVG jika file tidak ditemukan
        $base64Logo = 'data:image/svg+xml;base64,' . base64_encode('
        <svg width="80" height="80" xmlns="http://www.w3.org/2000/svg">
            <circle cx="40" cy="40" r="38" fill="#006064"/>
            <text x="40" y="45" font-family="Arial" font-size="20" font-weight="bold" fill="white" text-anchor="middle">PTPN</text>
        </svg>');
    }

    // B. FOTO PROFIL
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
    
    // Fallback Foto Kosong
    if (empty($base64Foto)) {
        $base64Foto = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='; // 1x1 pixel grey
        // Atau gunakan SVG placeholder
        $base64Foto = 'data:image/svg+xml;base64,' . base64_encode('
        <svg width="150" height="200" xmlns="http://www.w3.org/2000/svg">
            <rect width="150" height="200" fill="#f3f4f6"/>
            <circle cx="75" cy="80" r="30" fill="#d1d5db"/>
            <path d="M75 120 Q35 120 35 160 L115 160 Q115 120 75 120" fill="#d1d5db"/>
        </svg>');
    }

    ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CV_<?= htmlspecialchars($k['nama_lengkap']) ?></title>
    <style>
        @page { margin: 0px; }
        body {
            margin: 0px;
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            font-size: 10pt;
            background: #fff;
            line-height: 1.4;
        }

        /* --- Header Section --- */
        .header-bg {
            background-color: #006064; /* PTPN Teal Color */
            color: #fff;
            padding: 30px 40px;
            height: 140px; /* Fixed height for header */
        }
        
        .logo-container {
            float: left;
            width: 80px;
        }
        
        .logo-img {
            width: 70px;
            height: auto;
            background: #fff;
            border-radius: 50%;
            padding: 2px;
        }

        .company-info {
            float: left;
            margin-left: 20px;
            margin-top: 5px;
        }

        .company-name {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .company-sub {
            font-size: 9pt;
            opacity: 0.9;
            margin-top: 2px;
        }

        .doc-label {
            float: right;
            text-align: right;
            margin-top: 10px;
        }

        .doc-title {
            font-size: 24pt;
            font-weight: bold;
            letter-spacing: 2px;
            opacity: 0.2; /* Watermark effect text */
            color: #ffffff;
        }
        
        /* --- Content Container --- */
        .container {
            padding: 30px 40px;
        }

        /* --- Profile Summary Section --- */
        .profile-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
        }

        .profile-photo {
            float: left;
            width: 110px;
            height: 140px;
            border: 1px solid #ddd;
            padding: 3px;
            border-radius: 4px;
        }
        
        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-title {
            margin-left: 140px; /* Offset for photo */
            padding-top: 5px;
        }

        .name-big {
            font-size: 20pt;
            font-weight: bold;
            color: #006064;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .position-big {
            font-size: 12pt;
            font-weight: bold;
            color: #555;
            background: #f0fdfa;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            border-left: 4px solid #006064;
        }

        .id-badge {
            margin-top: 8px;
            font-size: 9pt;
            color: #666;
        }
        
        .id-badge span {
            font-weight: bold;
            color: #006064;
        }

        /* --- Grid Layout (Two Columns) --- */
        .row::after {
            content: "";
            clear: both;
            display: table;
        }

        .col-left {
            float: left;
            width: 48%;
        }

        .col-right {
            float: right;
            width: 48%;
        }

        /* --- Section Styling --- */
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #006064;
            text-transform: uppercase;
            border-bottom: 1px solid #006064;
            padding-bottom: 5px;
            margin-bottom: 15px;
            margin-top: 10px;
        }

        .info-table {
            width: 100%;
            font-size: 9pt;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .info-label {
            width: 35%;
            color: #666;
            font-weight: bold;
        }

        .info-sep {
            width: 10px;
            text-align: center;
        }

        .info-val {
            color: #333;
        }

        /* --- Data Tables (Family & SP) --- */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-top: 5px;
        }

        .data-table th {
            background-color: #006064;
            color: #fff;
            padding: 8px;
            text-align: left;
            font-weight: normal;
            font-size: 8.5pt;
        }

        .data-table td {
            border-bottom: 1px solid #eee;
            padding: 8px;
            color: #444;
        }

        .data-table tr:nth-child(even) {
            background-color: #fcfcfc;
        }

        .badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .bg-green { background: #d1fae5; color: #065f46; }
        .bg-blue { background: #dbeafe; color: #1e40af; }
        .bg-orange { background: #ffedd5; color: #9a3412; }
        .bg-red { background: #fee2e2; color: #991b1b; }

        /* --- Footer & Signature --- */
        .footer-info {
            margin-top: 40px;
            text-align: right;
            font-size: 9pt;
        }
        
        .signature-area {
            display: inline-block;
            width: 200px;
            text-align: center;
        }
        
        .sign-line {
            margin-top: 70px;
            border-top: 1px solid #333;
            padding-top: 5px;
            font-weight: bold;
        }

        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #f9fafb;
            padding: 10px 40px;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #eee;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="header-bg">
        <div class="logo-container">
            <img src="<?= $base64Logo ?>" class="logo-img" alt="Logo">
        </div>
        <div class="company-info">
            <div class="company-name">PTPN IV - Regional 3</div>
            <div class="company-sub">PT Perkebunan Nusantara IV (Persero)</div>
            <div class="company-sub">Human Capital Management System</div>
        </div>
        <div class="doc-label">
            <div class="doc-title">CV</div>
            <div style="color: #b2dfdb; font-size: 9pt;">EMPLOYEE DATA SHEET</div>
        </div>
    </div>

    <div class="container">
        
        <div class="profile-header row">
            <div class="profile-photo">
                <img src="<?= $base64Foto ?>" alt="Foto Profil">
            </div>
            <div class="profile-title">
                <div class="name-big"><?= htmlspecialchars($k['nama_lengkap']) ?></div>
                <div class="position-big"><?= htmlspecialchars($k['jabatan_real'] ?: 'Posisi Belum Diatur') ?></div>
                <div class="id-badge">
                    SAP ID: <span><?= htmlspecialchars($k['id_sap']) ?></span> &nbsp;|&nbsp; 
                    NIK: <span><?= htmlspecialchars($k['nik_ktp']) ?></span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-left">
                <div class="section-title">Data Pribadi</div>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Tempat, Tgl Lahir</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['tempat_lahir']) ?>, <?= tgl_indo($k['tanggal_lahir']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Jenis Kelamin</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= $k['gender'] == 'L' ? 'Laki-Laki' : 'Perempuan' ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Agama</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['agama']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Status Nikah</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['s_kel']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">No. Ponsel</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['no_hp']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Alamat Email</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['email_pribadi'] ?? '-') ?></td>
                    </tr>
                </table>

                <div class="section-title" style="margin-top: 25px;">Data Keuangan</div>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Bank</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['nama_bank']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">No. Rekening</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['no_rekening']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">NPWP</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['npwp']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">BPJS Kes</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['bpjs_id']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">BPJS TK</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['jamsostek_id']) ?></td>
                    </tr>
                </table>
            </div>

            <div class="col-right">
                <div class="section-title">Data Kepegawaian</div>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Unit/Afdeling</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['afdeling']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Jabatan SAP</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['jabatan_sap']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Status</td>
                        <td class="info-sep">:</td>
                        <td class="info-val">
                            <span class="badge bg-green"><?= htmlspecialchars($k['status_karyawan']) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td class="info-label">Grade/Gol</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= htmlspecialchars($k['person_grade']) ?> / <?= htmlspecialchars($k['phdp_golongan']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">TMT Masuk</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= tgl_indo($k['tmt_kerja']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">TMT MBT</td>
                        <td class="info-sep">:</td>
                        <td class="info-val"><?= tgl_indo($k['tmt_mbt']) ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">TMT Pensiun</td>
                        <td class="info-sep">:</td>
                        <td class="info-val" style="color: #b91c1c; font-weight: bold;"><?= tgl_indo($k['tmt_pensiun']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div style="clear:both; margin-bottom: 20px;"></div>

        <div class="section-title">Data Keluarga (Tanggungan)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="30%">Nama Anggota Keluarga</th>
                    <th width="15%">Hubungan</th>
                    <th width="20%">TTL</th>
                    <th width="15%">Pendidikan</th>
                    <th width="15%">Pekerjaan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($keluarga)): ?>
                    <tr><td colspan="6" align="center" style="font-style: italic; color: #999;">Tidak ada data keluarga tercatat</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach($keluarga as $row): ?>
                    <tr>
                        <td align="center"><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_anggota']) ?></td>
                        <td align="center"><span class="badge bg-blue"><?= htmlspecialchars($row['hubungan']) ?></span></td>
                        <td><?= htmlspecialchars($row['tempat_lahir']) ?>, <?= date('d-m-Y', strtotime($row['tanggal_lahir'])) ?></td>
                        <td><?= htmlspecialchars($row['pendidikan']) ?></td>
                        <td><?= htmlspecialchars($row['pekerjaan']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="section-title" style="margin-top: 25px;">Riwayat Surat Peringatan (SP)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="20%">Nomor Surat</th>
                    <th width="10%">Jenis</th>
                    <th width="15%">Tgl Efektif</th>
                    <th width="15%">Berakhir</th>
                    <th width="35%">Pelanggaran</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($riwayatSP)): ?>
                    <tr><td colspan="6" align="center" style="font-style: italic; color: #10b981;">- Tidak ada riwayat pelanggaran (Clean Record) -</td></tr>
                <?php else: ?>
                    <?php $no=1; foreach($riwayatSP as $row): 
                        $badgeClass = match($row['jenis_sp']) {
                            'SP1' => 'bg-blue',
                            'SP2' => 'bg-orange',
                            'SP3' => 'bg-red',
                            default => 'bg-green'
                        };
                    ?>
                    <tr>
                        <td align="center"><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['no_surat']) ?></td>
                        <td align="center"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['jenis_sp']) ?></span></td>
                        <td><?= date('d M Y', strtotime($row['tanggal_sp'])) ?></td>
                        <td><?= !empty($row['masa_berlaku']) ? date('d M Y', strtotime($row['masa_berlaku'])) : 'Permanen' ?></td>
                        <td><?= htmlspecialchars($row['pelanggaran']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer-info">
            <div class="signature-area">
                <div style="margin-bottom: 10px;">Medan, <?= tgl_indo(date('Y-m-d')) ?></div>
                <div style="font-size: 8pt; color: #666;">Dicetak oleh Sistem</div>
                <div class="sign-line">
                    <?= htmlspecialchars($k['nama_lengkap']) ?><br>
                    <span style="font-weight: normal; font-size: 8pt;">Karyawan</span>
                </div>
            </div>
        </div>

    </div>

    <div class="page-footer">
        Dicetak melalui Sistem Informasi SDM PTPN IV Regional 3 pada <?= date('d/m/Y H:i') ?> WIB. <br>
        Dokumen ini sah dan dihasilkan secara komputerisasi.
    </div>

</body>
</html>
<?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // Penting untuk gambar
    $options->set('defaultFont', 'Helvetica');
    $options->set('chroot', realpath('../')); // Izin akses folder parent
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    
    // Set paper size A4
    $dompdf->setPaper('A4', 'portrait');
    
    $dompdf->render();

    $filename = 'CV_' . preg_replace('/[^A-Za-z0-9]/', '_', $k['nama_lengkap']) . '.pdf';
    $dompdf->stream($filename, ['Attachment' => 0]); // 0 = Preview di browser, 1 = Download
    
} catch (Throwable $e) {
    http_response_code(500);
    echo "<div style='font-family: monospace; background: #fee; padding: 20px; border: 1px solid red;'>";
    echo "<h3 style='color: red; margin-top: 0;'>Terjadi Kesalahan Sistem</h3>";
    echo "<strong>Pesan Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
    echo "<strong>Lokasi:</strong> " . $e->getFile() . " baris " . $e->getLine();
    echo "</div>";
}
?>