<?php
// cetak/pemakaian_barang_gudang_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Pastikan path ini benar
use Mpdf\Mpdf;

$db = new Database();
$conn = $db->getConnection();

// --- Filter Dari Query String ---
$tahun          = $_GET['tahun'] ?? date('Y');
$bulan          = $_GET['bulan'] ?? ''; // Filter Bulan
$kebun_id       = $_GET['kebun_id'] ?? '';
$tanggal        = $_GET['tanggal'] ?? '';
$jenis_bahan_id = $_GET['jenis_bahan_id'] ?? '';

// --- Query Data ---
$where = " WHERE 1=1 ";
$params = [];

if ($tahun) { $where .= " AND YEAR(t.tanggal) = :thn"; $params[':thn'] = $tahun; }
if ($bulan) { $where .= " AND MONTH(t.tanggal) = :bln"; $params[':bln'] = $bulan; }
if ($kebun_id) { $where .= " AND t.kebun_id = :kbd"; $params[':kbd'] = $kebun_id; }
if ($tanggal) { $where .= " AND t.tanggal = :tgl"; $params[':tgl'] = $tanggal; }
if ($jenis_bahan_id) { $where .= " AND t.jenis_bahan_id = :jb"; $params[':jb'] = $jenis_bahan_id; }

// Query (HAPUS Join Kendaraan & Polisi)
$sql = "SELECT 
            t.tanggal, t.no_dokumen, t.jumlah, t.keterangan,
            k.nama_kebun, 
            b.nama AS nama_bahan, b.satuan
        FROM tr_pemakaian_barang_gudang t
        LEFT JOIN md_kebun k ON t.kebun_id = k.id
        LEFT JOIN md_jenis_bahan_bakar_pelumas b ON t.jenis_bahan_id = b.id
        $where
        ORDER BY t.tanggal DESC";

$st = $conn->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Helper Nama Bulan
$blnIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Generate HTML Rows dan Hitung Total
$htmlRows = '';
$totalJumlah = 0;
$no = 1;

foreach($rows as $r) {
    $d = new DateTime($r['tanggal']);
    $bln = $blnIndo[(int)$d->format('n')];
    $jumlah = (float)$r['jumlah'];
    $totalJumlah += $jumlah;
    
    // HAPUS Kolom Kendaraan & Polisi di sini
    $htmlRows .= '<tr>
        <td class="center">'.$no++.'</td>
        <td class="center">'.date('d-m-Y', strtotime($r['tanggal'])).'</td>
        <td class="center">'.$bln.'</td>
        <td>'.htmlspecialchars($r['nama_kebun'] ?? '-').'</td>
        <td>'.htmlspecialchars($r['no_dokumen'] ?? '-').'</td>
        <td>'.htmlspecialchars($r['nama_bahan'] ?? '-').'</td>
        <td class="center">'.htmlspecialchars($r['satuan'] ?? '').'</td>
        <td class="right jumlah-col">'.number_format($jumlah, 2).'</td>
        <td class="keterangan-col">'.htmlspecialchars($r['keterangan'] ?? '').'</td>
    </tr>';
}

if (empty($rows)) {
    $htmlRows = '<tr><td colspan="9" class="center" style="padding: 20px; color: #999;">Data pemakaian tidak ditemukan</td></tr>';
}

// --- CSS Style (Cyan Theme) ---
$css = '
    body { font-family: sans-serif; font-size: 10pt; }
    .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #06b6d4; padding-bottom: 10px; }
    .title { font-size: 14pt; font-weight: bold; color: #0e7490; text-transform: uppercase; }
    .subtitle { font-size: 11pt; color: #555; margin-top: 5px; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { 
        background-color: #059fd3; color: #fff; 
        padding: 8px 4px; font-size: 8.5pt; font-weight: bold; 
        border: 1px solid #0891b2; text-transform: uppercase;
    }
    td { 
        padding: 6px 4px; font-size: 8.5pt; 
        border: 1px solid #cbd5e1; vertical-align: middle;
    }
    
    /* Zebra Striping */
    tr:nth-child(even) { background-color: #ecfeff; }
    
    /* Column Specifics */
    .center { text-align: center; }
    .right { text-align: right; font-family: monospace; }
    .jumlah-col { background-color: #cffafe; color: #0e7490; font-weight: bold; }
    .keterangan-col { text-align: left; }
    
    .footer-total td {
        background-color: #f0f9ff; color: #0c4a6e; font-weight: bold; 
        border-top: 2px solid #bae6fd; font-size: 9pt;
    }
';

// --- HTML Template ---
$judulBulan = $bulan ? ' - Bulan: '.$blnIndo[$bulan] : '';

$html = '
<html>
<head><style>'.$css.'</style></head>
<body>
    <div class="header">
        <div class="title">LAPORAN PEMAKAIAN BARANG GUDANG</div>
        <div class="subtitle">Periode Tahun: '.$tahun.$judulBulan.($tanggal ? ' (Tgl Spesifik: '.date('d-m-Y', strtotime($tanggal)).')' : '').'</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="10%">Tanggal</th>
                <th width="8%">Bulan</th>
                <th width="15%">Kebun</th>
                <th width="12%">No. Dokumen</th>
                <th width="15%">Jenis Bahan</th>
                <th width="5%">Sat</th>
                <th width="10%">Jumlah</th>
                <th width="20%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            '.$htmlRows.'
        </tbody>
        <tfoot>
            <tr class="footer-total">
                <td colspan="7" class="right">Total Jumlah Pemakaian:</td>
                <td class="right">'.number_format($totalJumlah, 2).'</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    
    <div style="margin-top: 20px; font-size: 8pt; color: #777; text-align: right;">
        Dicetak pada: '.date('d F Y, H:i').'
    </div>
</body>
</html>';

// --- Generate PDF ---
try {
    $mpdf = new Mpdf([
        'format' => 'A4-L', // Landscape
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10
    ]);
    
    $mpdf->SetTitle('Laporan Pemakaian Barang Gudang');
    $mpdf->WriteHTML($html);
    $mpdf->Output('Laporan_Pemakaian_Gudang_'.date('YmdHis').'.pdf', 'I');

} catch (\Mpdf\MpdfException $e) {
    echo $e->getMessage();
}
?>