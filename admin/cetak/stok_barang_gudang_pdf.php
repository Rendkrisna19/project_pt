<?php
// cetak/stok_barang_gudang_pdf.php
// MODIFIKASI FULL: Support Bulan/Tahun, Tema Cyan/Teal

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Pastikan path vendor benar

use Mpdf\Mpdf;

$db = new Database();
$conn = $db->getConnection();

// --- 1. TERIMA FILTER ---
$kebun_id = $_GET['kebun_id'] ?? '';
$tahun    = $_GET['tahun'] ?? date('Y');
$bulan    = $_GET['bulan'] ?? '';
$jenis_barang_id = $_GET['jenis_barang_id'] ?? '';

// --- 2. QUERY DATA ---
$where = " WHERE 1=1 ";
$params = [];

// Filter Tahun
if ($tahun) { 
    $where .= " AND t.tahun = :thn"; 
    $params[':thn'] = $tahun; 
}

// Filter Bulan
if ($bulan && $bulan !== 'Semua Bulan') { 
    $where .= " AND t.bulan = :bln"; 
    $params[':bln'] = $bulan; 
}

// Filter Kebun
if ($kebun_id) { 
    $where .= " AND t.kebun_id = :kbd"; 
    $params[':kbd'] = $kebun_id; 
}

// Filter Barang
if ($jenis_barang_id) { 
    $where .= " AND t.jenis_barang_id = :jbi"; 
    $params[':jbi'] = $jenis_barang_id; 
}

$sql = "SELECT t.*, k.nama_kebun, b.nama AS nama_barang, b.satuan
        FROM tr_stok_barang_gudang t
        LEFT JOIN md_kebun k ON t.kebun_id = k.id
        LEFT JOIN md_jenis_barang_gudang b ON t.jenis_barang_id = b.id
        $where
        ORDER BY t.tahun DESC, k.nama_kebun ASC, b.nama ASC";

$st = $conn->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// --- 3. SIAPKAN HTML ROW ---
$htmlRows = '';
$no = 1;

foreach($rows as $r) {
    // Hitung Sisa
    $sisa = (float)$r['stok_awal'] + (float)$r['mutasi_masuk'] - (float)$r['mutasi_keluar'] + (float)$r['pasokan'] - (float)$r['dipakai'];
    
    // Warna teks jika sisa negatif (warning)
    $sisaColor = ($sisa < 0) ? '#dc2626' : '#155e75'; 

    $htmlRows .= '<tr>
        <td class="center">'.$no++.'</td>
        <td>'.htmlspecialchars($r['nama_kebun']).'</td>
        <td>'.htmlspecialchars($r['nama_barang']).'</td>
        <td class="center">'.htmlspecialchars($r['satuan']).'</td>
        <td class="center">'.$r['bulan'].'</td>
        <td class="center">'.$r['tahun'].'</td>
        <td class="right">'.number_format($r['stok_awal'], 2).'</td>
        <td class="right text-green">'.number_format($r['mutasi_masuk'], 2).'</td>
        <td class="right text-red">'.number_format($r['mutasi_keluar'], 2).'</td>
        <td class="right text-blue">'.number_format($r['pasokan'], 2).'</td>
        <td class="right text-orange">'.number_format($r['dipakai'], 2).'</td>
        <td class="right sisa-col" style="color:'.$sisaColor.'">'.number_format($sisa, 2).'</td>
    </tr>';
}

if (empty($rows)) {
    $htmlRows = '<tr><td colspan="12" class="center" style="padding: 30px; color: #9ca3af; font-style: italic;">Tidak ada data ditemukan untuk filter ini.</td></tr>';
}

// --- 4. CSS STYLE (TEMA CYAN) ---
$css = '
    body { font-family: sans-serif; font-size: 9pt; color: #334155; }
    
    /* Header Report */
    .header { text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #06b6d4; }
    .title { font-size: 14pt; font-weight: bold; color: #0e7490; text-transform: uppercase; margin-bottom: 5px; }
    .subtitle { font-size: 10pt; color: #64748b; }

    /* Table Styling */
    table { width: 100%; border-collapse: collapse; margin-top: 5px; }
    
    th { 
        background-color: #06b6d4; /* Cyan-500 */
        color: #ffffff; 
        padding: 10px 5px; 
        font-size: 8pt; 
        font-weight: bold; 
        border: 1px solid #0891b2; 
        text-transform: uppercase;
        vertical-align: middle;
    }
    
    td { 
        padding: 8px 5px; 
        font-size: 8pt; 
        border: 1px solid #cbd5e1; 
        vertical-align: middle;
    }
    
    /* Zebra Striping (Cyan Light) */
    tr:nth-child(even) { background-color: #ecfeff; } /* Cyan-50 */

    /* Utilities */
    .center { text-align: center; }
    .right { text-align: right; font-family: monospace; font-size: 9pt; }
    .bold { font-weight: bold; }
    
    /* Text Colors */
    .text-green { color: #16a34a; }
    .text-red { color: #dc2626; }
    .text-blue { color: #2563eb; }
    .text-orange { color: #d97706; }
    
    /* Sisa Column Highlight */
    .sisa-col { background-color: #cffafe; font-weight: bold; border-left: 2px solid #06b6d4; }
';

// Label Subtitle
$infoFilters = [];
if($kebun_id && !empty($rows)) $infoFilters[] = "Kebun: " . $rows[0]['nama_kebun'];
$infoFilters[] = "Bulan: " . ($bulan ?: 'Semua Bulan');
$infoFilters[] = "Tahun: " . $tahun;
$strFilter = implode(' | ', $infoFilters);

// --- 5. HTML TEMPLATE ---
$html = '
<html>
<head><style>'.$css.'</style></head>
<body>
    <div class="header">
        <div class="title">Laporan Stok Barang Gudang</div>
        <div class="subtitle">'.$strFilter.'</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="4%">No</th>
                <th width="12%">Kebun</th>
                <th width="16%">Barang</th>
                <th width="6%">Sat</th>
                <th width="8%">Bulan</th>
                <th width="6%">Tahun</th>
                <th width="8%">Awal</th>
                <th width="8%">Masuk</th>
                <th width="8%">Keluar</th>
                <th width="8%">Pasok</th>
                <th width="8%">Pakai</th>
                <th width="10%">SISA</th>
            </tr>
        </thead>
        <tbody>
            '.$htmlRows.'
        </tbody>
    </table>
    
    <div style="margin-top: 20px; font-size: 8pt; color: #94a3b8; text-align: right; font-style: italic;">
        Dicetak pada: '.date('d F Y, H:i').' | User: '.($_SESSION['username'] ?? 'System').'
    </div>
</body>
</html>';

// --- 6. GENERATE PDF ---
try {
    $mpdf = new Mpdf([
        'format' => 'A4-L', // Landscape agar muat banyak kolom
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10
    ]);
    
    $mpdf->SetTitle('Laporan Stok Gudang - '.$tahun);
    $mpdf->SetCreator('Sistem Informasi');
    $mpdf->WriteHTML($html);
    $mpdf->Output('Laporan_Stok_Gudang_'.date('YmdHis').'.pdf', 'I');

} catch (\Mpdf\MpdfException $e) {
    echo "Gagal membuat PDF: " . $e->getMessage();
}
?>