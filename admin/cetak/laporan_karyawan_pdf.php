<?php
// admin/cetak/laporan_karyawan_pdf.php
// VERSI FULL DATA (LEGAL LANDSCAPE - 30 KOLOM DATA)

ini_set('memory_limit', '-1'); 
set_time_limit(300);

session_start();
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. KONEKSI & AMBIL DATA
try {
    $db = new Database();
    $pdo = $db->getConnection();

    // 2. PARAMETER FILTER (SAMA DENGAN CRUD)
    $viewType = $_GET['view'] ?? 'active';
    $q        = trim($_GET['q'] ?? '');
    $kebun    = $_GET['kebun'] ?? '';
    $afdeling = $_GET['afdeling'] ?? '';

    // 3. BUILD QUERY
    $where = " WHERE 1=1 ";
    $params = [];

    // Filter Status (Aktif vs Pensiun)
    if ($viewType === 'pension') {
        $where .= " AND tmt_pensiun <= CURDATE() ";
        $judulStatus = "DATA KARYAWAN PENSIUN";
    } else {
        $where .= " AND (tmt_pensiun > CURDATE() OR tmt_pensiun IS NULL) ";
        $judulStatus = "DATA KARYAWAN AKTIF";
    }

    // Filter Search
    if ($q) {
        $where .= " AND (nama_lengkap LIKE :q OR id_sap LIKE :q OR nik_ktp LIKE :q) ";
        $params[':q'] = "%$q%";
    }

    // Filter Kebun (Text)
    if ($kebun) {
        $where .= " AND kebun_id = :kebun ";
        $params[':kebun'] = $kebun;
    }

    // Filter Afdeling
    if ($afdeling) {
        $where .= " AND afdeling = :afd ";
        $params[':afd'] = $afdeling;
    }

    $sql = "SELECT * FROM data_karyawan $where ORDER BY nama_lengkap ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. SETUP HTML UNTUK PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Karyawan Lengkap</title>
        <style>
            body { font-family: Helvetica, Arial, sans-serif; font-size: 6pt; color: #1e293b; }
            
            .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #0e7490; padding-bottom: 10px; }
            .header h2 { margin: 0; color: #0e7490; font-size: 14pt; text-transform: uppercase; }
            .header p { margin: 2px 0; font-size: 8pt; font-weight: bold; }
            
            .meta-info { margin-bottom: 10px; font-size: 7pt; width: 100%; }
            .meta-info td { border: none; padding: 2px; }
            
            table.data-grid { width: 100%; border-collapse: collapse; margin-top: 5px; table-layout: fixed; }
            
            table.data-grid th, 
            table.data-grid td { 
                border: 0.5px solid #64748b; 
                padding: 4px 3px; 
                vertical-align: middle; 
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            table.data-grid thead th { 
                background-color: #0e7490; 
                color: white; 
                font-weight: bold; 
                text-align: center; 
                text-transform: uppercase;
                font-size: 5.5pt;
                vertical-align: middle;
                height: 20px;
            }
            
            table.data-grid tr:nth-child(even) { background-color: #f1f5f9; }
            
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-bold { font-weight: bold; }
            
            .badge { padding: 2px 4px; border-radius: 3px; color: white; font-weight: bold; display: inline-block; font-size: 5pt; }
            .bg-green { background-color: #166534; }
            .bg-yellow { background-color: #ca8a04; }
            .bg-gray { background-color: #475569; }

            /* Helper Text */
            .sub-text { display: block; font-size: 5pt; color: #64748b; margin-top: 1px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Laporan '.$judulStatus.'</h2>
            <p>PT PERKEBUNAN NUSANTARA IV (PERSERO)</p>
        </div>

        <table class="meta-info">
            <tr>
                <td width="15%"><strong>Kebun Filter:</strong> '.($kebun ?: 'Semua').'</td>
                <td width="15%"><strong>Afdeling Filter:</strong> '.($afdeling ?: 'Semua').'</td>
                <td width="40%"></td>
                <td width="30%" class="text-right">Total Data: <strong>'.count($data).'</strong> | Dicetak: '.date('d-m-Y H:i').'</td>
            </tr>
        </table>

        <table class="data-grid">
            <thead>
                <tr>
                    <th width="2%">No</th>
                    <th width="4%">SAP ID</th>
                    <th width="10%">Identitas Diri</th>
                    <th width="6%">TTL</th>
                    <th width="3%">Gdr</th>
                    <th width="3%">Agama</th>
                    <th width="4%">Status Kel</th>
                    <th width="5%">No HP</th>
                    
                    <th width="8%">Kebun & Afd</th>
                    <th width="7%">Jabatan</th>
                    <th width="4%">Status Kry</th>
                    <th width="3%">Grd</th>
                    <th width="3%">PHDP</th>
                    
                    <th width="4%">TMT Kerja</th>
                    <th width="4%">TMT MBT</th>
                    <th width="4%">TMT Pensiun</th>
                    
                    <th width="8%">Legalitas (NPWP/BPJS)</th>
                    <th width="8%">Keuangan (Bank)</th>
                    <th width="6%">Pendidikan</th>
                </tr>
            </thead>
            <tbody>';

    $no = 1;
    if (empty($data)) {
        $html .= '<tr><td colspan="19" class="text-center" style="padding: 20px;">Tidak ada data ditemukan.</td></tr>';
    } else {
        foreach ($data as $r) {
            // 1. Format Tanggal
            $fmt = function($d) { return ($d && $d!='0000-00-00') ? date('d-m-Y', strtotime($d)) : '-'; };
            
            // 2. Gabung Data (Agar muat)
            $identitas = "<strong>".strtoupper($r['nama_lengkap'])."</strong><br>
                          <span class='sub-text'>NIK: ".$r['nik_ktp']."</span>
                          <span class='sub-text'>Old: ".($r['old_pers_no']??'-')."</span>";
            
            $ttl = $r['tempat_lahir'] . "<br><span class='sub-text'>" . $fmt($r['tanggal_lahir']) . "</span>";
            
            $lokasi = "<strong>".$r['kebun_id']."</strong><br><span class='sub-text'>".$r['afdeling']."</span>";
            
            $jabatan = "<strong>".$r['jabatan_real']."</strong><br><span class='sub-text'>SAP: ".$r['jabatan_sap']."</span>";
            
            // Badge Status
            $cls = 'bg-gray';
            if($r['status_karyawan']=='KARPIM') $cls = 'bg-green';
            if($r['status_karyawan']=='TS') $cls = 'bg-yellow';
            $statusKry = "<span class='badge $cls'>".$r['status_karyawan']."</span>";
            
            // Legalitas Gabungan
            $legal = "Tax: ".($r['tax_id']??'-')."<br>
                      BPJS: ".($r['bpjs_id']??'-')."<br>
                      Jam: ".($r['jamsostek_id']??'-')."<br>
                      PTKP: ".($r['status_pajak']??'-');

            // Bank Gabungan
            $bank = "<strong>".($r['nama_bank']??'-')."</strong><br>
                     ".($r['no_rekening']??'-')."<br>
                     <span class='sub-text'>A.N: ".($r['nama_pemilik_rekening']??'-')."</span>";

            // Pendidikan Gabungan
            $pend = "<strong>".($r['pendidikan_terakhir']??'-')."</strong><br>
                     <span class='sub-text'>".($r['jurusan']??'-')."</span><br>
                     <span class='sub-text'>".($r['institusi']??'-')."</span>";

            // Warna baris pensiun
            $bgRow = ($viewType === 'pension') ? 'style="background-color: #fef2f2; color:#b91c1c;"' : '';

            $html .= '<tr '.$bgRow.'>
                <td class="text-center">'.$no++.'</td>
                <td class="text-center text-bold">'.$r['id_sap'].'</td>
                <td>'.$identitas.'</td>
                <td>'.$ttl.'</td>
                <td class="text-center">'.$r['gender'].'</td>
                <td class="text-center">'.$r['agama'].'</td>
                <td class="text-center">'.$r['s_kel'].'</td>
                <td class="text-center">'.$r['no_hp'].'</td>
                
                <td>'.$lokasi.'</td>
                <td>'.$jabatan.'</td>
                <td class="text-center">'.$statusKry.'</td>
                <td class="text-center">'.$r['person_grade'].'</td>
                <td class="text-center">'.$r['phdp_golongan'].'</td>
                
                <td class="text-center">'.$fmt($r['tmt_kerja']).'</td>
                <td class="text-center">'.$fmt($r['tmt_mbt']).'</td>
                <td class="text-center">'.$fmt($r['tmt_pensiun']).'</td>
                
                <td style="font-size: 5pt;">'.$legal.'</td>
                <td style="font-size: 5pt;">'.$bank.'</td>
                <td>'.$pend.'</td>
            </tr>';
        }
    }

    $html .= '</tbody></table></body></html>';

    // 5. RENDER PDF (LEGAL LANDSCAPE)
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    
    // MENGGUNAKAN UKURAN LEGAL LANDSCAPE AGAR LEBIH LUAS
    $dompdf->setPaper('legal', 'landscape');
    
    $dompdf->render();
    
    // Output
    $filename = 'Laporan_Lengkap_'.date('Ymd_His').'.pdf';
    $dompdf->stream($filename, ["Attachment" => false]);

} catch (Exception $e) {
    echo "Terjadi Kesalahan System: " . $e->getMessage();
}
?>