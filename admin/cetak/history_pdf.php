<?php
// admin/cetak/history_pdf.php
session_start();
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$db = new Database();
$pdo = $db->getConnection();

$limit = $_GET['limit'] ?? 1000;
$q = trim($_GET['q'] ?? '');
$kebun_id = $_GET['kebun_id'] ?? '';

// Query sama dengan excel
$sql = "SELECT h.*, k.nama_lengkap, k.id_sap, k.kebun_id as nama_kebun 
        FROM data_history_jabatan h 
        LEFT JOIN data_karyawan k ON h.karyawan_id = k.id 
        WHERE 1=1";
$params = [];
if ($q) { $sql .= " AND (k.nama_lengkap LIKE :q OR h.no_surat LIKE :q)"; $params[':q'] = "%$q%"; }
if ($kebun_id) { $sql .= " AND h.kebun_id = :kid"; $params[':kid'] = $kebun_id; }
$sql .= " ORDER BY h.tgl_surat DESC LIMIT $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 9pt; }
        .header { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 5px; }
        th { background-color: #0E7490; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <h3>RIWAYAT JABATAN & MUTASI</h3>
        <p>Tanggal Cetak: '.date('d-m-Y').'</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>No</th><th>Kebun</th><th>SAP ID</th><th>Nama</th>
                <th>No Surat</th><th>Tgl</th><th>Jabatan Lama</th><th>Jabatan Baru</th><th>Ket</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
foreach($data as $r) {
    $html .= '<tr>
        <td style="text-align:center">'.$no++.'</td>
        <td>'.$r['nama_kebun'].'</td>
        <td>'.$r['id_sap'].'</td>
        <td>'.$r['nama_lengkap'].'</td>
        <td>'.$r['no_surat'].'</td>
        <td>'.$r['tgl_surat'].'</td>
        <td>'.$r['jabatan_lama'].'</td>
        <td>'.$r['jabatan_baru'].'</td>
        <td>'.$r['keterangan'].'</td>
    </tr>';
}
$html .= '</tbody></table></body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("History_Jabatan.pdf", ["Attachment" => true]);