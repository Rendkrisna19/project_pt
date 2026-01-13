<?php
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

// --- 1. SETUP DATABASE & FILTER ---
$db = new Database();
$conn = $db->getConnection();

$f_tahun = $_GET['tahun'] ?? date('Y');
$f_afd   = $_GET['afd'] ?? null;
$f_jenis = $_GET['jenis'] ?? null;
$f_hk    = $_GET['hk'] ?? null;
$f_ket   = $_GET['ket'] ?? null;

// Build Query
$where = "WHERE tahun = :tahun";
$params = [':tahun' => $f_tahun];

if ($f_afd) { $where .= " AND unit_kode = :afd"; $params[':afd'] = $f_afd; }
if ($f_jenis) { $where .= " AND jenis_nama = :jenis"; $params[':jenis'] = $f_jenis; }
if ($f_hk) { 
    // Asumsi filter HK mengirim kode, jika ID sesuaikan query
    $where .= " AND hk = :hk"; $params[':hk'] = $f_hk; 
}
if ($f_ket) { $where .= " AND ket LIKE :ket"; $params[':ket'] = "%$f_ket%"; }

$sql = "SELECT * FROM pemeliharaan_tbm1 $where ORDER BY jenis_nama ASC, unit_kode ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouping by Jenis
$grouped = [];
foreach($rows as $r) {
    $jenis = $r['jenis_nama'] ?? 'Lain-lain';
    $grouped[$jenis][] = $r;
}

// Logic Rayon (Hardcoded sesuai JS Anda)
$rayonA_list = ['AFD02','AFD03','AFD04','AFD05','AFD06'];
$rayonB_list = ['AFD01','AFD07','AFD08','AFD09','AFD10'];

// --- 2. CREATE EXCEL ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap TM');

// Header Info
$sheet->setCellValue('A1', 'REKAPITULASI PEMELIHARAAN TBM 1');
$sheet->setCellValue('A2', "Tahun: $f_tahun");
$sheet->mergeCells('A1:T1');
$sheet->mergeCells('A2:T2');
$sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);

// Table Header
$headers = [
    'A4' => 'Tahun', 'B4' => 'Kebun', 'C4' => 'Rayon', 'D4' => 'Unit', 
    'E4' => 'Keterangan', 'F4' => 'HK', 'G4' => 'Sat', 'H4' => 'Anggaran'
];
foreach($headers as $cell => $val) {
    $sheet->setCellValue($cell, $val);
    $sheet->mergeCells("$cell:".substr($cell,0,1)."5"); // Merge vertikal
}

// Header Bulan
$sheet->setCellValue('I4', 'Realisasi Bulanan');
$sheet->mergeCells('I4:T4');
$months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$colIdx = 9; // Column I
foreach($months as $m) {
    $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
    $sheet->setCellValue($colStr.'5', $m);
    $colIdx++;
}

// Header Total
$sheet->setCellValue('U4', 'Total Real');
$sheet->mergeCells('U4:U5');
$sheet->setCellValue('V4', '+/-');
$sheet->mergeCells('V4:V5');
$sheet->setCellValue('W4', '%');
$sheet->mergeCells('W4:W5');

// Styling Header
$headStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059FD3']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A4:W5')->applyFromArray($headStyle);

// --- 3. ISI DATA ---
$rowNum = 6;
$monthKeys = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'];

foreach($grouped as $jenis => $items) {
    // Header Group Jenis
    $sheet->setCellValue("A$rowNum", "JENIS: " . strtoupper($jenis));
    $sheet->mergeCells("A$rowNum:W$rowNum");
    $sheet->getStyle("A$rowNum")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A')); // Blue-900
    $sheet->getStyle("A$rowNum")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('EFF6FF'); // Blue-50
    $rowNum++;

    // Init Statistik per Jenis
    $sumJenis = ['anggaran'=>0, 'real'=>0, 'months'=>array_fill_keys($monthKeys,0)];
    $sumRa    = ['anggaran'=>0, 'real'=>0, 'months'=>array_fill_keys($monthKeys,0), 'count'=>0];
    $sumRb    = ['anggaran'=>0, 'real'=>0, 'months'=>array_fill_keys($monthKeys,0), 'count'=>0];

    foreach($items as $item) {
        $anggaran = (float)$item['anggaran_tahun'];
        $totalRealRow = 0;
        
        // Tentukan Rayon Label & Bucket
        $unit = $item['unit_kode'];
        $rayonLabel = '';
        $isRa = in_array($unit, $rayonA_list);
        $isRb = in_array($unit, $rayonB_list);
        
        if($item['rayon_nama']) $rayonLabel = $item['rayon_nama'];
        else if ($isRa) $rayonLabel = 'RY A';
        else if ($isRb) $rayonLabel = 'RY B';

        // Tulis Baris Data
        $sheet->setCellValue("A$rowNum", $item['tahun']);
        $sheet->setCellValue("B$rowNum", $item['kebun_nama'] ?? 'Sei Rokan');
        $sheet->setCellValue("C$rowNum", $rayonLabel);
        $sheet->setCellValue("D$rowNum", $unit);
        $sheet->setCellValue("E$rowNum", $item['ket']);
        $sheet->setCellValue("F$rowNum", $item['hk']);
        $sheet->setCellValue("G$rowNum", $item['satuan']);
        $sheet->setCellValue("H$rowNum", $anggaran);

        // Loop Bulan
        $cIdx = 9;
        foreach($monthKeys as $mk) {
            $val = (float)$item[$mk];
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx);
            $sheet->setCellValue($col.$rowNum, $val);
            
            $totalRealRow += $val;
            
            // Aggregates
            $sumJenis['months'][$mk] += $val;
            if ($isRa) $sumRa['months'][$mk] += $val;
            if ($isRb) $sumRb['months'][$mk] += $val;
            
            $cIdx++;
        }

        // Kalkulasi Baris
        $selisih = $totalRealRow - $anggaran;
        $persen = $anggaran > 0 ? ($totalRealRow / $anggaran) : 0;

        $sheet->setCellValue("U$rowNum", $totalRealRow);
        $sheet->setCellValue("V$rowNum", $selisih);
        $sheet->setCellValue("W$rowNum", $persen);
        
        // Format Persen Row
        $sheet->getStyle("W$rowNum")->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);

        // Aggregates Total
        $sumJenis['anggaran'] += $anggaran;
        $sumJenis['real'] += $totalRealRow;
        
        if ($isRa) { $sumRa['anggaran'] += $anggaran; $sumRa['real'] += $totalRealRow; $sumRa['count']++; }
        if ($isRb) { $sumRb['anggaran'] += $anggaran; $sumRb['real'] += $totalRealRow; $sumRb['count']++; }

        $rowNum++;
    }

    // --- HELPER FUNCTION PRINT SUBROW ---
    $printSubRow = function($label, $data, $bgParams) use (&$sheet, &$rowNum, $monthKeys) {
        $sheet->setCellValue("A$rowNum", $label);
        $sheet->mergeCells("A$rowNum:G$rowNum");
        $sheet->getStyle("A$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue("H$rowNum", $data['anggaran']);
        
        $c = 9;
        foreach($monthKeys as $mk) {
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$rowNum, $data['months'][$mk]);
            $c++;
        }
        
        $selisih = $data['real'] - $data['anggaran'];
        $persen = $data['anggaran'] > 0 ? ($data['real'] / $data['anggaran']) : 0;
        
        $sheet->setCellValue("U$rowNum", $data['real']);
        $sheet->setCellValue("V$rowNum", $selisih);
        $sheet->setCellValue("W$rowNum", $persen);
        $sheet->getStyle("W$rowNum")->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);

        // Style
        $sheet->getStyle("A$rowNum:W$rowNum")->getFont()->setBold(true);
        $sheet->getStyle("A$rowNum:W$rowNum")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bgParams['color']);
        $sheet->getStyle("A$rowNum:W$rowNum")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($bgParams['font']));
        
        // Number format baris ini
        $sheet->getStyle("H$rowNum:V$rowNum")->getNumberFormat()->setFormatCode('#,##0.00');

        $rowNum++;
    };

    // Print Total Jenis
    $printSubRow("TOTAL ".strtoupper($jenis), $sumJenis, ['color'=>'F0FDF4', 'font'=>'14532D']); // Green

    // Print Rayon A (Jika ada data)
    if($sumRa['count'] > 0) {
        $printSubRow("SUBTOTAL RAYON A", $sumRa, ['color'=>'FFF7ED', 'font'=>'7C2D12']); // Orange
    }

    // Print Rayon B (Jika ada data)
    if($sumRb['count'] > 0) {
        $printSubRow("SUBTOTAL RAYON B", $sumRb, ['color'=>'FFF7ED', 'font'=>'7C2D12']); // Orange
    }
}

// Auto Width & Border Final
$lastRow = $rowNum - 1;
$styleBorder = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle("A4:W$lastRow")->applyFromArray($styleBorder);
$sheet->getStyle("H6:V$lastRow")->getNumberFormat()->setFormatCode('#,##0.00');

foreach (range('A','W') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Laporan_TBM 1_'.date('YmdHis').'.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>