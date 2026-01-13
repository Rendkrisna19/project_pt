<?php
// pages/cetak/alat_panen_export_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
use Mpdf\Mpdf;

// === 1. KONEKSI & HELPER ===
$db = new Database(); 
$pdo = $db->getConnection();

// === 2. AMBIL FILTER DARI URL ===
$kebun_id = isset($_GET['kebun_id']) && ctype_digit((string)$_GET['kebun_id']) ? (int)$_GET['kebun_id'] : null;
$unit_id  = isset($_GET['unit_id'])  && ctype_digit((string)$_GET['unit_id'])  ? (int)$_GET['unit_id']  : null;
$bulan    = trim($_GET['bulan'] ?? '');
$tahun    = isset($_GET['tahun']) && ctype_digit((string)$_GET['tahun']) ? (int)$_GET['tahun'] : null;
$id_jenis = isset($_GET['id_jenis_alat']) && ctype_digit((string)$_GET['id_jenis_alat']) ? (int)$_GET['id_jenis_alat'] : null;

// === 3. QUERY DATA ===
$sql = "SELECT ap.*, 
               k.nama_kebun, 
               u.nama_unit,
               mja.nama AS nama_alat_panen
        FROM alat_panen ap
        LEFT JOIN md_kebun k ON k.id = ap.kebun_id
        LEFT JOIN units u ON u.id = ap.unit_id
        LEFT JOIN md_jenis_alat_panen mja ON mja.id = ap.id_jenis_alat
        WHERE 1=1";

$params = [];

if ($kebun_id) { $sql .= " AND ap.kebun_id = :kid"; $params[':kid'] = $kebun_id; }
if ($unit_id)  { $sql .= " AND ap.unit_id = :uid";  $params[':uid'] = $unit_id; }
if ($bulan)    { $sql .= " AND ap.bulan = :bln";    $params[':bln'] = $bulan; }
if ($tahun)    { $sql .= " AND ap.tahun = :thn";    $params[':thn'] = $tahun; }
if ($id_jenis) { $sql .= " AND ap.id_jenis_alat = :ija"; $params[':ija'] = $id_jenis; }

$sql .= " ORDER BY ap.tahun DESC, FIELD(ap.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), ap.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// === 4. LOGIKA HITUNG TOTAL & ROW HTML ===
$rowsHtml = '';
$no = 1;

// Inisialisasi Variabel Total
$tot_awal   = 0;
$tot_masuk  = 0;
$tot_keluar = 0;
$tot_pakai  = 0;
$tot_akhir  = 0;

if (count($rows) > 0) {
    foreach($rows as $r) {
        // Format nama alat
        $alat = $r['nama_alat_panen'] ?? ($r['jenis_alat'] ?? '-');
        
        // Konversi ke float agar aman saat dijumlah
        $val_awal   = (float)($r['stok_awal'] ?? 0);
        $val_masuk  = (float)($r['mutasi_masuk'] ?? 0);
        $val_keluar = (float)($r['mutasi_keluar'] ?? 0);
        $val_pakai  = (float)($r['dipakai'] ?? 0);
        $val_akhir  = (float)($r['stok_akhir'] ?? 0);

        // Akumulasi Total
        $tot_awal   += $val_awal;
        $tot_masuk  += $val_masuk;
        $tot_keluar += $val_keluar;
        $tot_pakai  += $val_pakai;
        $tot_akhir  += $val_akhir;

        $rowsHtml .= '<tr>
            <td class="center">'. $no++ .'</td>
            <td class="center">'. htmlspecialchars($r['bulan'] . ' ' . $r['tahun']) .'</td>
            <td>'. htmlspecialchars($r['nama_kebun'] ?? '-') .'</td>
            <td>'. htmlspecialchars($r['nama_unit'] ?? '-') .'</td>
            <td>'. htmlspecialchars($alat) .'</td>
            
            <td class="right">'. number_format($val_awal, 2) .'</td>
            <td class="right" style="color:green;">'. number_format($val_masuk, 2) .'</td>
            <td class="right" style="color:red;">'. number_format($val_keluar, 2) .'</td>
            <td class="right" style="color:orange;">'. number_format($val_pakai, 2) .'</td>
            <td class="right" style="background-color:#f0f9ff; color:#059fd3;">'. number_format($val_akhir, 2) .'</td>
            
            <td>'. htmlspecialchars($r['krani_afdeling'] ?? '-') .'</td>
            <td style="font-size:8pt; font-style:italic;">'. htmlspecialchars($r['catatan'] ?? '-') .'</td>
        </tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="12" class="center" style="padding:20px; color:#777;">Tidak ada data ditemukan untuk filter ini.</td></tr>';
}

// === 5. PERSIAPAN TAMPILAN ===

$judulUtama = 'LAPORAN STOK ALAT PERTANIAN';
$subJudul   = 'PTPN 4 REGIONAL 3';

// Info Filter Header
$infoFilter = [];
if ($kebun_id) {
    $k = $pdo->query("SELECT nama_kebun FROM md_kebun WHERE id=$kebun_id")->fetchColumn();
    $infoFilter[] = "<b>Kebun:</b> " . htmlspecialchars($k);
}
if ($unit_id) {
    $u = $pdo->query("SELECT nama_unit FROM units WHERE id=$unit_id")->fetchColumn();
    $infoFilter[] = "<b>Unit:</b> " . htmlspecialchars($u);
}
if ($bulan) $infoFilter[] = "<b>Bulan:</b> $bulan";
if ($tahun) $infoFilter[] = "<b>Tahun:</b> $tahun";
if ($id_jenis) {
    $j = $pdo->query("SELECT nama FROM md_jenis_alat_panen WHERE id=$id_jenis")->fetchColumn();
    $infoFilter[] = "<b>Jenis Alat:</b> " . htmlspecialchars($j);
}

$filterString = empty($infoFilter) ? 'Semua Data' : implode(" &nbsp;|&nbsp; ", $infoFilter);
$tanggalCetak = date('d F Y, H:i');

// CSS Styles
$css = <<<CSS
    body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #333; }
    .header-container { text-align: center; margin-bottom: 20px; border-bottom: 3px double #059fd3; padding-bottom: 10px; }
    .company-name { font-size: 14pt; font-weight: bold; color: #555; letter-spacing: 1px; }
    .report-title { font-size: 16pt; font-weight: bold; color: #059fd3; margin-top: 5px; text-transform: uppercase; }
    .filter-box { background-color: #f0f9ff; border: 1px solid #bae6fd; padding: 8px; font-size: 9pt; margin-bottom: 15px; border-radius: 4px; color: #0369a1; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th { 
        background-color: #059fd3; color: #ffffff; padding: 8px 5px; 
        font-size: 9pt; font-weight: bold; text-transform: uppercase; 
        border: 1px solid #0487b4; vertical-align: middle;
    }
    td { padding: 6px 5px; font-size: 9pt; border: 1px solid #e2e8f0; vertical-align: middle; }
    
    /* Footer Total Row Style */
    .total-row td {
        background-color: #e0f2fe;
        border-top: 2px solid #059fd3;
        font-weight: bold;
        color: #0369a1;
    }

    tr:nth-child(even) { background-color: #f8fafc; }
    .center { text-align: center; }
    .right { text-align: right; font-family: 'Courier New', monospace; font-weight: bold; }
    .left { text-align: left; }
    
    /* Column Widths */
    .col-no { width: 5%; }
    .col-period { width: 10%; }
    .col-loc { width: 12%; }
    .col-unit { width: 12%; }
    .col-item { width: 13%; }
    .col-num { width: 7%; }
    .col-krani { width: 10%; }
    .col-note { width: 10%; }
CSS;

// === 6. TEMPLATE HTML ===
$html = <<<HTML
<html>
<head>
    <meta charset="utf-8">
    <style>{$css}</style>
</head>
<body>

    <div class="header-container">
        <div class="company-name">{$subJudul}</div>
        <div class="report-title">{$judulUtama}</div>
    </div>

    <div class="filter-box">
        <table style="width:100%; border:none; margin:0;">
            <tr>
                <td style="border:none; text-align:left; padding:0;">Filter: {$filterString}</td>
                <td style="border:none; text-align:right; padding:0;">Cetak: {$tanggalCetak}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-period">Periode</th>
                <th class="col-loc">Kebun</th>
                <th class="col-unit">Unit</th>
                <th class="col-item">Jenis Alat</th>
                <th class="col-num">Awal</th>
                <th class="col-num">Masuk</th>
                <th class="col-num">Keluar</th>
                <th class="col-num">Pakai</th>
                <th class="col-num">Akhir</th>
                <th class="col-krani">Krani</th>
                <th class="col-note">Ket</th>
            </tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="right" style="text-align:right; padding-right:10px;">TOTAL KESELURUHAN</td>
                <td class="right">{$tot_awal}</td> <td class="right">{$tot_masuk}</td>
                <td class="right">{$tot_keluar}</td>
                <td class="right">{$tot_pakai}</td>
                <td class="right">{$tot_akhir}</td>
                <td colspan="2" style="background-color:#f1f5f9;"></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
HTML;

// Perbaikan sedikit string interpolasi untuk format number di footer
$html = str_replace('{$tot_awal}', number_format($tot_awal, 2), $html);
$html = str_replace('{$tot_masuk}', number_format($tot_masuk, 2), $html);
$html = str_replace('{$tot_keluar}', number_format($tot_keluar, 2), $html);
$html = str_replace('{$tot_pakai}', number_format($tot_pakai, 2), $html);
$html = str_replace('{$tot_akhir}', number_format($tot_akhir, 2), $html);

// === 7. GENERATE PDF ===
try {
    $mpdf = new Mpdf([
        'format' => 'A4-L',
        'margin_top' => 10,
        'margin_bottom' => 15,
        'margin_left' => 10,
        'margin_right' => 10
    ]);
    
    $mpdf->SetTitle("Laporan Alat Pertanian - " . date('Ymd'));
    $mpdf->SetFooter('Halaman {PAGENO} dari {nbpg}');
    $mpdf->WriteHTML($html);
    $mpdf->Output('Laporan_Alat_Panen_'.date('Ymd_His').'.pdf', 'I');

} catch (\Mpdf\MpdfException $e) {
    echo $e->getMessage();
}
?>