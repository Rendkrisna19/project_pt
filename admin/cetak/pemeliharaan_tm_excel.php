<?php
// 1. Buffer Output (PENTING: Agar file tidak corrupt saat didownload)
ob_start();

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// --- 2. SETUP DATA & KONEKSI ---
$db = new Database();
$conn = $db->getConnection();

// Ambil Filter URL
$f_tahun = $_GET['tahun'] ?? date('Y');
$f_afd   = $_GET['afd'] ?? null;
$f_jenis = $_GET['jenis'] ?? null;
$f_hk    = $_GET['hk'] ?? null;
$f_ket   = $_GET['ket'] ?? null;

// --- 3. QUERY DATABASE (DIPERBAIKI: TANPA JOIN ID) ---
$where = "WHERE tahun = :tahun";
$params = [':tahun' => $f_tahun];

if (!empty($f_afd)) { $where .= " AND unit_kode = :afd"; $params[':afd'] = $f_afd; }
if (!empty($f_jenis)) { $where .= " AND jenis_nama = :jenis"; $params[':jenis'] = $f_jenis; }
if (!empty($f_hk)) { $where .= " AND hk = :hk"; $params[':hk'] = $f_hk; }
if (!empty($f_ket)) { $where .= " AND ket LIKE :ket"; $params[':ket'] = "%$f_ket%"; }

// Query langsung ke tabel transaksi
$sql = "SELECT * FROM pemeliharaan_tm $where ORDER BY jenis_nama ASC, unit_kode ASC, rayon_nama ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 4. GROUPING DATA (PERBAIKAN LOGIKA) ---
$grouped = [];
foreach($rows as $r) {
    // Ambil nama jenis dari data transaksi
    $rawName = $r['jenis_nama'] ?? 'LAIN-LAIN';
    
    // BERSIHKAN NAMA: Ubah ke Huruf Besar & Hapus Spasi Kiri/Kanan
    // Ini membantu menyatukan "Sample" dan "sample "
    $cleanName = strtoupper(trim($rawName));

    // OPSI TAMBAHAN: MANUAL FIX (Jika data sangat berantakan)
    // Jika ada nama yang pasti salah input, bisa dipaksa di sini.
    // Contoh: Jika mengandung kata 'KCD', paksa masuk ke grup 'AMBIL SAMPEL...'
    /* if (strpos($cleanName, 'KCD') !== false) {
        $cleanName = '45-02 AMBIL SAMPEL DAUN & TANAH';
    } 
    */

    $grouped[$cleanName][] = $r;
}

// Config Rayon & Bulan
$rayonA_list = ['AFD02','AFD03','AFD04','AFD05','AFD06'];
$rayonB_list = ['AFD01','AFD07','AFD08','AFD09','AFD10'];
$monthKeys   = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'];

// --- 5. SETUP EXCEL ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap TM ' . $f_tahun);

// Header Judul
$sheet->setCellValue('A1', 'REKAPITULASI PEMELIHARAAN TM');
$sheet->setCellValue('A2', "Tahun Anggaran: $f_tahun");
$sheet->mergeCells('A1:W1'); 
$sheet->mergeCells('A2:W2');
$sheet->getStyle('A1:A2')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// Header Kolom Statis
$headers = [
    'A' => 'Tahun', 'B' => 'Kebun', 'C' => 'Rayon', 'D' => 'Unit',
    'E' => 'Keterangan', 'F' => 'HK', 'G' => 'Sat', 'H' => 'Anggaran Thn'
];
foreach ($headers as $col => $text) {
    $sheet->setCellValue($col . '4', $text);
    $sheet->mergeCells($col . '4:' . $col . '5');
}

// Header Bulan
$sheet->setCellValue('I4', 'Realisasi Bulanan');
$sheet->mergeCells('I4:T4');
$cIdx = 9; 
foreach (['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'] as $mLabel) {
    $colStr = Coordinate::stringFromColumnIndex($cIdx);
    $sheet->setCellValue($colStr . '5', $mLabel);
    $cIdx++;
}

// Header Total
$sheet->setCellValue('U4', 'Jml Realisasi'); $sheet->mergeCells('U4:U5');
$sheet->setCellValue('V4', '+/- Angg');      $sheet->mergeCells('V4:V5');
$sheet->setCellValue('W4', '%');             $sheet->mergeCells('W4:W5');

// Styling Header Table
$sheet->getStyle('A4:W5')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059FD3']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

// --- 6. PRINT DATA ---
$row = 6;

foreach ($grouped as $jenis => $items) {
    // Header Jenis Pekerjaan
    $sheet->setCellValue("A$row", "JENIS PEKERJAAN: " . $jenis);
    $sheet->mergeCells("A$row:W$row");
    $sheet->getStyle("A$row")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E3A8A']], 
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']], 
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
    ]);
    $row++;

    // Init Accumulator
    $sumJenis = ['angg' => 0, 'real' => 0, 'm' => array_fill_keys($monthKeys, 0)];
    $sumRa    = ['angg' => 0, 'real' => 0, 'm' => array_fill_keys($monthKeys, 0), 'count' => 0];
    $sumRb    = ['angg' => 0, 'real' => 0, 'm' => array_fill_keys($monthKeys, 0), 'count' => 0];

    // Loop Item
    foreach ($items as $d) {
        $unit = $d['unit_kode'];
        $rayonName = $d['rayon_nama'];
        
        $isRa = in_array($unit, $rayonA_list);
        $isRb = in_array($unit, $rayonB_list);
        if (empty($rayonName)) {
            if ($isRa) $rayonName = 'RY A';
            elseif ($isRb) $rayonName = 'RY B';
        }

        $angg = (float)$d['anggaran_tahun'];
        
        $rowReal = 0;
        foreach($monthKeys as $k) { $rowReal += (float)$d[$k]; }
        
        $selisih = $rowReal - $angg;
        $persen  = ($angg > 0) ? ($rowReal / $angg) : 0;

        // Print Row
        $sheet->setCellValue("A$row", $d['tahun']);
        $sheet->setCellValue("B$row", 'Sei Rokan');
        $sheet->setCellValue("C$row", $rayonName);
        $sheet->setCellValue("D$row", $unit);
        $sheet->setCellValue("E$row", $d['ket']);
        $sheet->setCellValue("F$row", $d['hk']);
        $sheet->setCellValue("G$row", $d['satuan']);
        $sheet->setCellValue("H$row", $angg);

        $colM = 9; 
        foreach($monthKeys as $k) {
            $val = (float)$d[$k];
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colM).$row, ($val == 0 ? '-' : $val));
            
            $sumJenis['m'][$k] += $val;
            if ($isRa) $sumRa['m'][$k] += $val;
            if ($isRb) $sumRb['m'][$k] += $val;
            $colM++;
        }

        $sheet->setCellValue("U$row", $rowReal);
        $sheet->setCellValue("V$row", $selisih);
        $sheet->setCellValue("W$row", $persen);

        // Warna & Format
        $color = ($selisih > 0) ? 'FFDC2626' : ($selisih < 0 ? 'FF16A34A' : 'FF000000');
        $sheet->getStyle("V$row")->getFont()->setColor(new Color($color));
        $sheet->getStyle("H$row:V$row")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("W$row")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        // Add Total
        $sumJenis['angg'] += $angg;
        $sumJenis['real'] += $rowReal;
        
        if ($isRa) { $sumRa['angg'] += $angg; $sumRa['real'] += $rowReal; $sumRa['count']++; }
        if ($isRb) { $sumRb['angg'] += $angg; $sumRb['real'] += $rowReal; $sumRb['count']++; }

        $row++;
    }

    // --- FUNGSI SUBTOTAL ---
    $printSub = function($label, $data, $bgHex, $fontHex) use (&$sheet, &$row, $monthKeys) {
        $sheet->setCellValue("A$row", $label);
        $sheet->mergeCells("A$row:G$row");
        $sheet->setCellValue("H$row", $data['angg']);

        $c = 9;
        foreach($monthKeys as $k) {
            $val = $data['m'][$k];
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c).$row, ($val==0?'-':$val));
            $c++;
        }
        
        $selisih = $data['real'] - $data['angg'];
        $persen  = ($data['angg'] > 0) ? ($data['real'] / $data['angg']) : 0;

        $sheet->setCellValue("U$row", $data['real']);
        $sheet->setCellValue("V$row", $selisih);
        $sheet->setCellValue("W$row", $persen);

        $style = [
            'font' => ['bold' => true, 'color' => ['argb' => $fontHex]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgHex]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sheet->getStyle("A$row:W$row")->applyFromArray($style);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $sheet->getStyle("H$row:V$row")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("W$row")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        
        $dColor = ($selisih > 0) ? 'FFDC2626' : ($selisih < 0 ? 'FF16A34A' : $fontHex);
        $sheet->getStyle("V$row")->getFont()->setColor(new Color($dColor));

        $row++;
    };

    // Cetak Total
    $printSub("TOTAL " . $jenis, $sumJenis, 'FFF0FDF4', 'FF14532D');

    if ($sumRa['count'] > 0) {
        $printSub("SUBTOTAL RAYON A", $sumRa, 'FFFFF7ED', 'FF7C2D12');
    }
    if ($sumRb['count'] > 0) {
        $printSub("SUBTOTAL RAYON B", $sumRb, 'FFFFF7ED', 'FF7C2D12');
    }
}

// --- 7. FINISHING ---
$lastRow = $row - 1;
$sheet->getStyle("A6:W$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle("D6:D$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("F6:G$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("E6:E$lastRow")->getAlignment()->setWrapText(true);

foreach (range('A','W') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getColumnDimension('E')->setAutoSize(false);
$sheet->getColumnDimension('E')->setWidth(35);

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Laporan_TM_'.date('Y-m-d_His').'.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;