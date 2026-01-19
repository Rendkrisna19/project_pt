<?php
// pages/export_cv.php
session_start();
if (!isset($_SESSION['loggedin'])) exit("Access Denied");
require_once '../config/database.php';

$id = $_GET['id'] ?? 0;
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM data_karyawan WHERE id = ?");
$stmt->execute([$id]);
$k = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$k) die("Karyawan tidak ditemukan.");

$foto = $k['foto_profil'] ? "../uploads/profil/".$k['foto_profil'] : "../assets/img/default-avatar.png";
?>
<!DOCTYPE html>
<html>
<head>
    <title>CV - <?= htmlspecialchars($k['nama_lengkap']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #525659; margin: 0; padding: 20px; }
        .page { background: white; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 40px; box-shadow: 0 0 10px rgba(0,0,0,0.5); position: relative; }
        h1, h2, h3 { margin: 0; color: #333; }
        .header { display: flex; border-bottom: 2px solid #059fd3; padding-bottom: 20px; margin-bottom: 30px; }
        .foto { width: 120px; height: 150px; background: #ddd; object-fit: cover; border: 1px solid #ccc; margin-right: 20px; }
        .info-utama { flex: 1; }
        .info-utama h1 { font-size: 24px; text-transform: uppercase; margin-bottom: 5px; }
        .info-utama p { margin: 2px 0; font-size: 14px; color: #555; }
        
        .section { margin-bottom: 25px; }
        .section-title { background: #f0f9ff; color: #059fd3; padding: 5px 10px; font-size: 16px; font-weight: bold; border-left: 5px solid #059fd3; margin-bottom: 15px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table td { padding: 6px; vertical-align: top; }
        .label { width: 180px; font-weight: bold; color: #555; }
        .val { color: #000; }
        .titik { width: 10px; text-align: center; }

        @media print {
            body { background: white; padding: 0; }
            .page { box-shadow: none; margin: 0; width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="page">
        <div class="header">
            <img src="<?= $foto ?>" class="foto">
            <div class="info-utama">
                <h1><?= htmlspecialchars($k['nama_lengkap']) ?></h1>
                <p><strong>ID SAP:</strong> <?= htmlspecialchars($k['id_sap']) ?></p>
                <p><strong>Jabatan:</strong> <?= htmlspecialchars($k['jabatan_real']) ?></p>
                <p><strong>Unit/Bagian:</strong> <?= htmlspecialchars($k['afdeling']) ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($k['status_karyawan']) ?></p>
            </div>
        </div>

        <div class="section">
            <div class="section-title">DATA PRIBADI</div>
            <table>
                <tr><td class="label">NIK KTP</td><td class="titik">:</td><td class="val"><?= $k['nik_ktp'] ?></td></tr>
                <tr><td class="label">Tempat, Tanggal Lahir</td><td class="titik">:</td><td class="val"><?= $k['tempat_lahir'] ?>, <?= date('d F Y', strtotime($k['tanggal_lahir'])) ?></td></tr>
                <tr><td class="label">Jenis Kelamin</td><td class="titik">:</td><td class="val"><?= $k['gender']=='L'?'Laki-laki':'Perempuan' ?></td></tr>
                <tr><td class="label">Agama</td><td class="titik">:</td><td class="val"><?= $k['agama'] ?></td></tr>
                <tr><td class="label">No HP / Telp</td><td class="titik">:</td><td class="val"><?= $k['no_hp'] ?></td></tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">DATA KEPEGAWAIAN</div>
            <table>
                <tr><td class="label">Tanggal Masuk (TMT)</td><td class="titik">:</td><td class="val"><?= date('d F Y', strtotime($k['tmt_kerja'])) ?></td></tr>
                <tr><td class="label">TMT Pensiun</td><td class="titik">:</td><td class="val"><?= date('d F Y', strtotime($k['tmt_pensiun'])) ?></td></tr>
                <tr><td class="label">Person Grade</td><td class="titik">:</td><td class="val"><?= $k['person_grade'] ?></td></tr>
                <tr><td class="label">PHDP Golongan</td><td class="titik">:</td><td class="val"><?= $k['phdp_golongan'] ?></td></tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">ADMINISTRASI & BANK</div>
            <table>
                <tr><td class="label">NPWP</td><td class="titik">:</td><td class="val"><?= $k['npwp'] ?></td></tr>
                <tr><td class="label">BPJS Ketenagakerjaan</td><td class="titik">:</td><td class="val"><?= $k['jamsostek_id'] ?></td></tr>
                <tr><td class="label">BPJS Kesehatan</td><td class="titik">:</td><td class="val"><?= $k['bpjs_id'] ?></td></tr>
                <tr><td class="label">Nama Bank</td><td class="titik">:</td><td class="val"><?= $k['nama_bank'] ?></td></tr>
                <tr><td class="label">No. Rekening</td><td class="titik">:</td><td class="val"><?= $k['no_rekening'] ?></td></tr>
            </table>
        </div>
        
        <div style="margin-top: 50px; text-align: right;">
            <p>Dicetak pada: <?= date('d M Y H:i') ?></p>
        </div>
    </div>
</body>
</html>