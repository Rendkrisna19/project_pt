<?php
// admin/cetak/mbt_pdf.php
// MODIFIKASI: Sesuai struktur tabel 'data_karyawan' di data_karyawan_crud.php

session_start();
require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. DATA RETRIEVAL
$db = new Database();
$pdo = $db->getConnection();
$year = $_GET['year'] ?? date('Y');
$afdeling = $_GET['afdeling'] ?? '';
$q = trim($_GET['q'] ?? '');

// Logic Query sama dengan Excel & CRUD
$sql = "SELECT id_sap, nama_lengkap, kebun_id, afdeling, jabatan_real, tmt_mbt, status_karyawan 
        FROM data_karyawan 
        WHERE tmt_mbt IS NOT NULL";

$params = [];
if ($year) {
    $sql .= " AND YEAR(tmt_mbt) = :year";
    $params[':year'] = $year;
}
if ($afdeling) {
    $sql .= " AND afdeling = :afdeling";
    $params[':afdeling'] = $afdeling;
}
if ($q) {
    $sql .= " AND (nama_lengkap LIKE :q OR id_sap LIKE :q OR jabatan_real LIKE :q)";
    $params[':q'] = "%$q%";
}

$sql .= " ORDER BY tmt_mbt ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. HTML STRUCTURE
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 10pt; color: #334155; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #0891b2; text-transform: uppercase; }
        .header p { margin: 5px 0; color: #64748b; font-size: 9pt; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; vertical-align: middle; }
        
        thead th { background-color: #0891b2; color: white; font-weight: bold; text-align: center; font-size: 9pt; }
        
        /* Badges Logic */
        .badge { padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 8pt; display: inline-block; text-align: center; min-width: 60px; }
        .bg-red { background-color: #dc2626; color: white; }
        .bg-red-soft { background-color: #fee2e2; color: #991b1b; }
        .bg-orange { background-color: #ffedd5; color: #9a3412; }
        .bg-green { background-color: #dcfce7; color: #166534; }
        
        .text-center { text-align: center; }
        .tmt-col { background-color: #fff7ed; color: #c2410c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Monitoring MBT</h2>
        <p>Tahun: '.$year.' | Afdeling: '.($afdeling?:'Semua').' | Tgl Cetak: '.date('d/m/Y').'</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="10%">SAP ID</th>
                <th width="20%">Nama Lengkap</th>
                <th width="12%">Kebun</th>
                <th width="8%">Afd</th>
                <th width="20%">Jabatan</th>
                <th width="10%">TMT MBT</th>
                <th width="10%">Sisa Waktu</th>
                <th width="5%">Status</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
$today = new DateTime();

if(count($data) == 0) {
    $html .= '<tr><td colspan="9" class="text-center">Tidak ada data ditemukan.</td></tr>';
}

foreach($data as $r) {
    $tmtMbtDate = new DateTime($r['tmt_mbt']);
    $diff = $today->diff($tmtMbtDate);
    $days = (int)$diff->format('%r%a');

    $badgeClass = 'bg-green';
    $statusText = "$days Hari";

    if ($days < 0) {
        $badgeClass = 'bg-red';
        $statusText = "Lewat " . abs($days);
    } elseif ($days <= 30) {
        $badgeClass = 'bg-red-soft';
    } elseif ($days <= 90) {
        $badgeClass = 'bg-orange';
    }

    $tmtDisplay = date('d-m-Y', strtotime($r['tmt_mbt']));

    $html .= '<tr>
        <td class="text-center">'.$no++.'</td>
        <td>'.$r['id_sap'].'</td>
        <td>'.$r['nama_lengkap'].'</td>
        <td>'.$r['kebun_id'].'</td>
        <td class="text-center">'.$r['afdeling'].'</td>
        <td>'.$r['jabatan_real'].'</td>
        <td class="text-center tmt-col">'.$tmtDisplay.'</td>
        <td class="text-center"><span class="badge '.$badgeClass.'">'.$statusText.'</span></td>
        <td class="text-center">'.$r['status_karyawan'].'</td>
    </tr>';
}

$html .= '</tbody></table></body></html>';

// 3. RENDER PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Laporan_MBT_".date('Ymd').".pdf", array("Attachment" => true));
?>