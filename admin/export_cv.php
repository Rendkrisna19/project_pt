<?php
// pages/export_cv.php
// FULL FIXED VERSION: Sesuai Struktur CRUD & Database Import

declare(strict_types=1);
// Matikan display error agar tidak merusak binary PDF
ini_set('display_errors', '0'); 
error_reporting(E_ALL);

session_start();

// Cek Sesi
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    exit('Akses ditolak. Silakan login.'); 
}

require_once '../config/database.php';
require_once '../vendor/autoload.php'; // Pastikan dompdf terinstall via composer

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? 0;
if (empty($id)) exit("ID Karyawan tidak ditemukan.");

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. AMBIL DATA KARYAWAN (Sesuai kolom di data_karyawan_crud.php)
    // Catatan: Di CRUD Anda, 'kebun_id' disimpan sebagai TEXT (Nama Kebun), bukan ID angka.
    $sql = "SELECT * FROM data_karyawan WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $k = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$k) exit("Data karyawan tidak ditemukan di database.");

    // 2. AMBIL DATA KELUARGA
    $stmtKel = $conn->prepare("SELECT * FROM data_keluarga WHERE karyawan_id = ? ORDER BY tanggal_lahir ASC");
    $stmtKel->execute([$id]);
    $keluarga = $stmtKel->fetchAll(PDO::FETCH_ASSOC);

    // 3. AMBIL DATA SP (PERINGATAN)
    // Sesuai kolom di CRUD: jenis_sp, tanggal_sp, pelanggaran, sanksi
    $stmtSP = $conn->prepare("SELECT * FROM data_peringatan WHERE karyawan_id = ? ORDER BY tanggal_sp DESC");
    $stmtSP->execute([$id]);
    $riwayatSP = $stmtSP->fetchAll(PDO::FETCH_ASSOC);

    // 4. AMBIL HISTORY JABATAN (Jika ada tabelnya, opsional)
    $riwayatJabatan = [];
    try {
        $stmtHist = $conn->prepare("SELECT * FROM data_history_jabatan WHERE karyawan_id = ? ORDER BY tgl_surat DESC");
        $stmtHist->execute([$id]);
        $riwayatJabatan = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Abaikan jika tabel history belum ada
    }

    // --- HELPER FORMAT ---
    function e($str) { return htmlspecialchars((string)($str ?? '-'), ENT_QUOTES, 'UTF-8'); }
    
    function tgl_indo($tanggal){
        if(empty($tanggal) || $tanggal == '0000-00-00') return '-';
        $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        $pecahkan = explode('-', $tanggal);
        // Handle format Y-m-d
        if(count($pecahkan) == 3) {
            return $pecahkan[2] . ' ' . ($bulan[(int)$pecahkan[1]] ?? '') . ' ' . $pecahkan[0];
        }
        return $tanggal;
    }

    // --- LOGIKA FOTO (BASE64) ---
    // Sesuai path upload di crud: ../uploads/profil/
    $pathFoto = '../uploads/profil/' . ($k['foto_profil'] ?? '');
    $base64Foto = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='; // Blank pixel
    
    if (!empty($k['foto_profil']) && file_exists($pathFoto)) {
        $type = pathinfo($pathFoto, PATHINFO_EXTENSION);
        $data = file_get_contents($pathFoto);
        $base64Foto = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    // Mulai Buffer HTML
    ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CV_<?= e($k['nama_lengkap']) ?></title>
    <style>
        @page { margin: 0px; size: A4; }
        body { font-family: 'Helvetica', sans-serif; margin: 0; padding: 0; background-color: #fff; color: #333; line-height: 1.3; }
        
        /* HEADER ATAS (Unit Kerja) */
        .top-banner {
            background-color: #0e7490; /* Cyan Tua */
            color: white;
            padding: 20px 40px;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 5px solid #155e75;
        }

        /* GRID LAYOUT */
        table.layout-grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 0; }
        td.col-left { width: 32%; background-color: #f1f5f9; vertical-align: top; padding: 30px 20px; border-right: 1px solid #e2e8f0; }
        td.col-right { width: 68%; background-color: #ffffff; vertical-align: top; padding: 30px 35px; }

        /* PROFIL */
        .profile-box { text-align: center; margin-bottom: 25px; }
        .profile-img { width: 140px; height: 140px; border-radius: 50%; border: 4px solid #0e7490; object-fit: cover; background-color: #cbd5e1; }
        
        /* SIDEBAR INFO */
        .left-title { font-size: 10pt; font-weight: bold; color: #0e7490; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; padding-bottom: 3px; margin-bottom: 10px; margin-top: 20px; }
        .info-group { margin-bottom: 8px; }
        .info-label { font-size: 7.5pt; color: #64748b; text-transform: uppercase; font-weight: bold; display: block; }
        .info-val { font-size: 9pt; color: #334155; font-weight: bold; word-wrap: break-word; }

        /* MAIN CONTENT */
        .header-name { font-size: 22pt; font-weight: 800; text-transform: uppercase; color: #1e293b; margin-bottom: 5px; line-height: 1; }
        .header-role { font-size: 11pt; text-transform: uppercase; color: #0891b2; font-weight: bold; margin-bottom: 25px; letter-spacing: 1px; }
        
        .section-header { border-bottom: 2px solid #e2e8f0; margin-bottom: 10px; padding-bottom: 5px; margin-top: 20px; }
        .section-title { font-size: 11pt; font-weight: bold; text-transform: uppercase; color: #334155; display: inline-block; border-bottom: 2px solid #0e7490; padding-bottom: 5px; }

        /* TABLES */
        .data-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 5px; }
        .data-table th { text-align: left; background-color: #f8fafc; color: #475569; font-size: 7.5pt; text-transform: uppercase; border: 1px solid #cbd5e1; padding: 6px; }
        .data-table td { padding: 6px; border: 1px solid #cbd5e1; color: #334155; vertical-align: top; }
        
        .badge { display: inline-block; padding: 2px 5px; background: #e0f2fe; color: #0369a1; border-radius: 3px; font-size: 7pt; font-weight: bold; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        
        .highlight-box { background: #ecfeff; border-left: 4px solid #06b6d4; padding: 10px; font-size: 9pt; margin-bottom: 15px; color: #155e75; }
    </style>
</head>
<body>

<div class="top-banner">
    UNIT KERJA: <?= strtoupper(e($k['kebun_id'])) ?> - <?= strtoupper(e($k['afdeling'])) ?>
</div>

<table class="layout-grid">
    <tr>
        <td class="col-left">
            <div class="profile-box">
                <img src="<?= $base64Foto ?>" class="profile-img">
            </div>

            <div class="left-title">Identitas Diri</div>
            <div class="info-group"><span class="info-label">SAP ID</span><span class="info-val"><?= e($k['id_sap']) ?></span></div>
            <div class="info-group"><span class="info-label">Old Pers No</span><span class="info-val"><?= e($k['old_pers_no']) ?></span></div>
            <div class="info-group"><span class="info-label">NIK (KTP)</span><span class="info-val"><?= e($k['nik_ktp']) ?></span></div>
            <div class="info-group"><span class="info-label">Jenis Kelamin</span><span class="info-val"><?= ($k['gender'] ?? 'L') == 'L' ? 'Laki-Laki' : 'Perempuan' ?></span></div>
            <div class="info-group"><span class="info-label">Tempat, Tgl Lahir</span><span class="info-val"><?= e($k['tempat_lahir']) ?>, <br><?= tgl_indo($k['tanggal_lahir']) ?></span></div>
            <div class="info-group"><span class="info-label">Agama</span><span class="info-val"><?= e($k['agama']) ?></span></div>

            <div class="left-title">Kontak & Alamat</div>
            <div class="info-group"><span class="info-label">No. Ponsel</span><span class="info-val"><?= e($k['no_hp']) ?></span></div>
            <div class="info-group"><span class="info-label">NPWP</span><span class="info-val"><?= e($k['tax_id']) ?></span></div>
            
            <div class="left-title">Data Bank</div>
            <div class="info-group"><span class="info-label">Nama Bank</span><span class="info-val"><?= e($k['nama_bank']) ?></span></div>
            <div class="info-group"><span class="info-label">No. Rekening</span><span class="info-val"><?= e($k['no_rekening']) ?></span></div>
            <div class="info-group"><span class="info-label">Pemilik Rek.</span><span class="info-val"><?= e($k['nama_pemilik_rekening']) ?></span></div>

            <div class="left-title">Asuransi</div>
            <div class="info-group"><span class="info-label">BPJS Kesehatan</span><span class="info-val"><?= e($k['bpjs_id']) ?></span></div>
            <div class="info-group"><span class="info-label">BPJS TK (Jamsostek)</span><span class="info-val"><?= e($k['jamsostek_id']) ?></span></div>
        </td>

        <td class="col-right">
            <div class="header-name"><?= e($k['nama_lengkap']) ?></div>
            <div class="header-role"><?= e($k['jabatan_real']) ?> (<?= e($k['jabatan_sap']) ?>)</div>

            <div class="highlight-box">
                Status: <strong><?= e($k['status_karyawan']) ?></strong> &nbsp;|&nbsp; 
                Grade: <strong><?= e($k['person_grade']) ?></strong> &nbsp;|&nbsp; 
                Golongan: <strong><?= e($k['phdp_golongan']) ?></strong> &nbsp;|&nbsp; 
                Status Keluarga: <strong><?= e($k['s_kel']) ?></strong> / <strong><?= e($k['status_pajak']) ?></strong>
            </div>

            <div class="section-header"><div class="section-title">Masa Kerja & Pendidikan</div></div>
            <table class="data-table" style="border:none;">
                <tr>
                    <td style="border:none; width:33%;">
                        <span class="info-label">TMT Bekerja</span>
                        <strong style="color:#0f172a; font-size:10pt;"><?= tgl_indo($k['tmt_kerja']) ?></strong>
                    </td>
                    <td style="border:none; width:33%;">
                        <span class="info-label">TMT MBT (55 Thn)</span>
                        <strong style="color:#ea580c; font-size:10pt;"><?= tgl_indo($k['tmt_mbt']) ?></strong>
                    </td>
                    <td style="border:none; width:33%;">
                        <span class="info-label">TMT Pensiun (56 Thn)</span>
                        <strong style="color:#dc2626; font-size:10pt;"><?= tgl_indo($k['tmt_pensiun']) ?></strong>
                    </td>
                </tr>
            </table>
            
            <div style="margin-top:10px;">
                <span class="info-label">Pendidikan Terakhir:</span>
                <strong><?= e($k['pendidikan_terakhir']) ?></strong> - <?= e($k['jurusan']) ?> (<?= e($k['institusi']) ?>)
            </div>

            <div class="section-header"><div class="section-title">Data Keluarga</div></div>
            <?php if(empty($keluarga)): ?>
                <p style="font-style:italic; color:#94a3b8; font-size:9pt;">Belum ada data keluarga tercatat.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Hubungan</th>
                            <th>Tgl Lahir</th>
                            <th>Pendidikan</th>
                            <th>Pekerjaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($keluarga as $kel): ?>
                        <tr>
                            <td><strong><?= e($kel['nama_batih']) ?></strong></td>
                            <td><?= e($kel['hubungan']) ?></td>
                            <td><?= tgl_indo($kel['tanggal_lahir']) ?></td>
                            <td><?= e($kel['pendidikan_terakhir']) ?></td>
                            <td><?= e($kel['pekerjaan']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="section-header"><div class="section-title">Riwayat Kedisiplinan (SP)</div></div>
            <?php if(empty($riwayatSP)): ?>
                <div style="padding:10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:5px; color:#166534; font-size:9pt;">
                    <strong>&#10003; Clean Record</strong>: Tidak ada riwayat Surat Peringatan.
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="15%">No. Surat</th>
                            <th width="15%">Jenis</th>
                            <th width="20%">Tanggal</th>
                            <th>Pelanggaran / Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($riwayatSP as $sp): ?>
                        <tr>
                            <td><?= e($sp['no_surat']) ?></td>
                            <td><span class="badge badge-red"><?= e($sp['jenis_sp']) ?></span></td>
                            <td><?= tgl_indo($sp['tanggal_sp']) ?></td>
                            <td>
                                <strong><?= e($sp['pelanggaran']) ?></strong><br>
                                <span style="font-size:8pt; color:#64748b;">Sanksi: <?= e($sp['sanksi']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if(!empty($riwayatJabatan)): ?>
                <div class="section-header"><div class="section-title">Riwayat Jabatan</div></div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tgl SK</th>
                            <th>No Surat</th>
                            <th>Mutasi / Promosi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($riwayatJabatan as $h): ?>
                        <tr>
                            <td><?= tgl_indo($h['tgl_surat']) ?></td>
                            <td><?= e($h['no_surat']) ?></td>
                            <td><?= e($h['jabatan_lama']) ?> &#8594; <strong><?= e($h['jabatan_baru']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </td>
    </tr>
</table>

</body>
</html>
<?php
    $html = ob_get_clean();
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Nama file bersih dari karakter aneh
    $cleanName = preg_replace('/[^A-Za-z0-9 ]/', '', $k['nama_lengkap']);
    $filename = 'CV_' . str_replace(' ', '_', $cleanName) . '.pdf';
    
    $dompdf->stream($filename, ['Attachment' => 0]); // 0 = Preview di browser, 1 = Download
} catch (Throwable $e) {
    exit("Terjadi Kesalahan PDF: " . $e->getMessage());
}
?>