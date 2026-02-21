<?php
// admin/cetak/export_tanggungan_pdf.php
// Export PDF Data Tanggungan

session_start();
ini_set('memory_limit', '-1');
set_time_limit(300);

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Query Data (Sama dengan Excel)
    $sql = "SELECT t.*, k.nama_lengkap as nama_karyawan, k.id_sap, k.kebun_id as nama_kebun, k.afdeling
            FROM data_keluarga t
            LEFT JOIN data_karyawan k ON t.karyawan_id = k.id
            ORDER BY k.nama_lengkap ASC, t.nama_batih ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // HTML Structure
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Data Tanggungan</title>
        <style>
            body { font-family: sans-serif; font-size: 9pt; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0891b2; padding-bottom: 10px; }
            .header h2 { margin: 0; color: #0891b2; text-transform: uppercase; }
            .header p { margin: 2px 0; font-size: 10pt; }
            
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: middle; }
            
            thead th { 
                background-color: #0891b2; 
                color: white; 
                font-weight: bold; 
                text-align: center; 
                font-size: 8pt;
            }
            
            tr:nth-child(even) { background-color: #f8fafc; }
            .text-center { text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Laporan Data Tanggungan Karyawan</h2>
            <p>Dicetak Tanggal: '.date('d-m-Y H:i').'</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="8%">SAP ID</th>
                    <th width="15%">Nama Karyawan</th>
                    <th width="15%">Nama Keluarga</th>
                    <th width="8%">Hubungan</th>
                    <th width="10%">Kebun</th>
                    <th width="5%">Afd</th>
                    <th width="8%">Tgl Lahir</th>
                    <th width="8%">Pendidikan</th>
                    <th width="10%">Keterangan</th>
                </tr>
            </thead>
            <tbody>';

    $no = 1;
    if (empty($data)) {
        $html .= '<tr><td colspan="10" class="text-center" style="padding:20px;">Tidak ada data.</td></tr>';
    } else {
        foreach ($data as $r) {
            $tglLahir = $r['tanggal_lahir'] ? date('d/m/Y', strtotime($r['tanggal_lahir'])) : '-';
            
            $html .= '<tr>
                <td class="text-center">'.$no++.'</td>
                <td class="text-center">'.$r['id_sap'].'</td>
                <td>'.$r['nama_karyawan'].'</td>
                <td><b>'.$r['nama_batih'].'</b></td>
                <td class="text-center">'.$r['hubungan'].'</td>
                <td>'.$r['nama_kebun'].'</td>
                <td class="text-center">'.$r['afdeling'].'</td>
                <td class="text-center">'.$tglLahir.'</td>
                <td class="text-center">'.$r['pendidikan_terakhir'].'</td>
                <td>'.$r['keterangan'].'</td>
            </tr>';
        }
    }

    $html .= '</tbody></table></body></html>';

    // Render PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape'); // Landscape agar muat
    $dompdf->render();
    
    $dompdf->stream("Laporan_Tanggungan.pdf", ["Attachment" => false]); // Preview di browser

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>