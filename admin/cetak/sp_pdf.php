<?php
// admin/cetak/sp_pdf.php
// Export PDF Data SP

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

    // Query Data
    $sql = "SELECT sp.*, k.nama_lengkap, k.id_sap, k.kebun_id as nama_kebun, k.afdeling, k.status_karyawan 
            FROM data_peringatan sp
            LEFT JOIN data_karyawan k ON sp.karyawan_id = k.id
            ORDER BY sp.tanggal_sp DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // HTML Structure
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Surat Peringatan</title>
        <style>
            body { font-family: sans-serif; font-size: 9pt; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #dc2626; padding-bottom: 10px; }
            .header h2 { margin: 0; color: #dc2626; text-transform: uppercase; }
            .header p { margin: 2px 0; font-size: 10pt; }
            
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
            
            thead th { 
                background-color: #dc2626; 
                color: white; 
                font-weight: bold; 
                text-align: center; 
                font-size: 8pt;
            }
            
            tr:nth-child(even) { background-color: #fef2f2; } /* Red-50 */
            .text-center { text-align: center; }
            .badge { padding: 2px 5px; border-radius: 4px; font-weight: bold; font-size: 8pt; display:inline-block; }
            .bg-sp1 { background-color: #ffedd5; color: #c2410c; border: 1px solid #fdba74; }
            .bg-sp2 { background-color: #ea580c; color: white; }
            .bg-sp3 { background-color: #dc2626; color: white; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Laporan Surat Peringatan Karyawan</h2>
            <p>Dicetak Tanggal: '.date('d-m-Y H:i').'</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="8%">SAP ID</th>
                    <th width="15%">Nama Karyawan</th>
                    <th width="10%">Kebun</th>
                    <th width="5%">Afd</th>
                    <th width="10%">No Surat</th>
                    <th width="8%">Jenis SP</th>
                    <th width="8%">Tgl SP</th>
                    <th width="8%">Berlaku s/d</th>
                    <th width="15%">Pelanggaran</th>
                    <th width="10%">Sanksi</th>
                </tr>
            </thead>
            <tbody>';

    $no = 1;
    if (empty($data)) {
        $html .= '<tr><td colspan="11" class="text-center" style="padding:20px;">Tidak ada data SP.</td></tr>';
    } else {
        foreach ($data as $r) {
            $tglSP = $r['tanggal_sp'] ? date('d-m-Y', strtotime($r['tanggal_sp'])) : '-';
            $tglExp = $r['masa_berlaku'] ? date('d-m-Y', strtotime($r['masa_berlaku'])) : '-';
            
            // Badge Logic
            $class = '';
            if($r['jenis_sp'] == 'SP1') $class = 'bg-sp1';
            if($r['jenis_sp'] == 'SP2') $class = 'bg-sp2';
            if($r['jenis_sp'] == 'SP3') $class = 'bg-sp3';

            $html .= '<tr>
                <td class="text-center">'.$no++.'</td>
                <td class="text-center">'.$r['id_sap'].'</td>
                <td>'.$r['nama_lengkap'].'</td>
                <td>'.$r['nama_kebun'].'</td>
                <td class="text-center">'.$r['afdeling'].'</td>
                <td class="text-center">'.$r['no_surat'].'</td>
                <td class="text-center"><span class="badge '.$class.'">'.$r['jenis_sp'].'</span></td>
                <td class="text-center">'.$tglSP.'</td>
                <td class="text-center">'.$tglExp.'</td>
                <td>'.$r['pelanggaran'].'</td>
                <td>'.$r['sanksi'].'</td>
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
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    $dompdf->stream("Laporan_SP.pdf", ["Attachment" => false]);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>