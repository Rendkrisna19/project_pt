<?php
// cetak/pemakaian_barang_gudang_excel.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { exit('Unauthorized'); }

require_once '../../config/database.php';

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

// --- Konfigurasi Excel Header ---
$filename = 'Laporan_Pemakaian_Gudang_' . date('YmdHis') . '.xls';
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

// --- Judul Periode ---
$judulBulan = $bulan ? ' - Bulan: '.$blnIndo[$bulan] : '';

// --- Output HTML for Excel ---
$output = '
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .title { font-size: 16pt; font-weight: bold; color: #0c4a6e; }
        .subtitle { font-size: 12pt; color: #555; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 10pt; }
        th { background-color: #059fd3; color: white; font-weight: bold; text-transform: uppercase; }
        .text-right { text-align: right; }
        .footer-total td { background-color: #f0f9ff; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="9" class="title">LAPORAN PEMAKAIAN BARANG GUDANG</td>
        </tr>
        <tr>
            <td colspan="9" class="subtitle">Periode Tahun: '.$tahun.$judulBulan.($tanggal ? ' (Tgl Spesifik: '.date('d-m-Y', strtotime($tanggal)).')' : '').'</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Bulan</th>
                <th>Kebun</th>
                <th>No. Dokumen</th>
                <th>Jenis Bahan</th>
                <th>Satuan</th>
                <th>Jumlah Bahan</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>';

$totalJumlah = 0;
$no = 1;

foreach($rows as $r) {
    $d = new DateTime($r['tanggal']);
    $bln = $blnIndo[(int)$d->format('n')];
    $jumlah = (float)$r['jumlah'];
    $totalJumlah += $jumlah;

    $output .= '
            <tr>
                <td>'.$no++.'</td>
                <td>'.date('d-m-Y', strtotime($r['tanggal'])).'</td>
                <td>'.$bln.'</td>
                <td>'.htmlspecialchars($r['nama_kebun'] ?? '-').'</td>
                <td>'.htmlspecialchars($r['no_dokumen'] ?? '-').'</td>
                <td>'.htmlspecialchars($r['nama_bahan'] ?? '-').'</td>
                <td>'.htmlspecialchars($r['satuan'] ?? '').'</td>
                <td class="text-right">'.number_format($jumlah, 2).'</td>
                <td>'.htmlspecialchars($r['keterangan'] ?? '').'</td>
            </tr>';
}

$output .= '
        </tbody>
        <tfoot>
            <tr class="footer-total">
                <td colspan="7" class="text-right">Total Jumlah Pemakaian:</td>
                <td class="text-right">'.number_format($totalJumlah, 2).'</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    
    <div style="margin-top: 20px; font-size: 8pt; color: #777; text-align: right;">
        Dicetak pada: '.date('d F Y, H:i').'
    </div>
</body>
</html>';

echo $output;
exit;
?>