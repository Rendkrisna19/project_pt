<?php
// admin/cetak/pemetaan_excel.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    http_response_code(403); exit('Unauthorized'); 
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $db = new Database();
    $conn = $db->getConnection();

    $unit_id  = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
    $kebun_id = isset($_POST['kebun_id']) ? (int)$_POST['kebun_id'] : 0;
    $jp_id    = isset($_POST['jenis_pekerjaan_id']) ? (int)$_POST['jenis_pekerjaan_id'] : 0;
    $map_image = isset($_POST['map_image']) ? $_POST['map_image'] : '';

    if (empty($unit_id) || empty($kebun_id) || empty($map_image)) {
        exit("Data tidak lengkap untuk diexport.");
    }

    // Ambil Info Header
    $stmtInfo = $conn->prepare("SELECT u.nama_unit, k.nama_kebun 
                                FROM units u 
                                LEFT JOIN md_kebun k ON u.kebun_id = k.id 
                                WHERE u.id = ?");
    $stmtInfo->execute([$unit_id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    
    $nama_unit  = $info ? $info['nama_unit'] : 'UNIT';
    $nama_kebun = $info ? $info['nama_kebun'] : 'KEBUN';

    // Ambil Nama Pekerjaan
    $nama_pekerjaan = "SEMUA PEKERJAAN";
    if ($jp_id > 0) {
        $stmtJp = $conn->prepare("SELECT nama FROM md_jenis_pekerjaan WHERE id = ?");
        $stmtJp->execute([$jp_id]);
        $jpInfo = $stmtJp->fetch(PDO::FETCH_ASSOC);
        if ($jpInfo) {
            $nama_pekerjaan = $jpInfo['nama'];
        }
    }

    $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : date('Y-m');

    // Ambil Data Realisasi Pemetaan Hari Ini
    $sql = "SELECT * FROM tr_pemetaan WHERE kebun_id = ? AND unit_id = ? ";
    $params = [$kebun_id, $unit_id];
    if ($jp_id > 0) {
        $sql .= " AND jenis_pekerjaan_id = ? ";
        $params[] = $jp_id;
    }
    if (!empty($bulan)) {
        $sql .= " AND DATE_FORMAT(tanggal_realisasi, '%Y-%m') = ? ";
        $params[] = $bulan;
    }
    $sql .= " ORDER BY tanggal_realisasi ASC, id ASC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data_realisasi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bulan_tahun = date('F Y', strtotime($bulan . '-01'));

} catch (Exception $e) {
    exit('DB Error: ' . $e->getMessage());
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Peta & Realisasi');

// === HEADER ROW 1 (match PDF: 30% / 40% / 30%) ===
// Total 17 columns (A-Q): Green=A-E(5), Yellow=F-L(7), Blue=M-Q(5)
$sheet->mergeCells('A1:E1');
$sheet->setCellValue('A1', "PT. PERKEBUNAN NUSANTARA IV\nREGIONAL III - DISTRIK BARAT\nKEBUN " . strtoupper($nama_kebun));
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF065F46'); // Green
$sheet->getStyle('A1')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE))->setBold(true)->setSize(10);

$sheet->mergeCells('F1:L1');
$sheet->setCellValue('F1', "PETA KETERANGAN (" . strtoupper($nama_unit) . ")");
$sheet->getStyle('F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFBBF24'); // Yellow
$sheet->getStyle('F1')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK))->setBold(true)->setSize(14);

$sheet->mergeCells('M1:Q1');
$sheet->setCellValue('M1', "BULAN : " . strtoupper($bulan_tahun) . "\nPEKERJAAN : " . strtoupper($nama_pekerjaan));
$sheet->getStyle('M1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E40AF'); // Blue
$sheet->getStyle('M1')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE))->setBold(true)->setSize(10);

$sheet->getStyle('A1:Q1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('A1:Q1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getRowDimension(1)->setRowHeight(50);

// === TABLE HEADERS (Right side: columns H-Q, 10 columns) ===
// TANGGAL & BLOK span 3 rows vertically (rows 2-4)
$sheet->mergeCells('H2:H4'); $sheet->setCellValue('H2', 'TANGGAL');
$sheet->mergeCells('I2:I4'); $sheet->setCellValue('I2', 'BLOK');

// Super Header: RENCANA / REALISASI spanning 8 data columns (J-Q)
$sheet->mergeCells('J2:Q2'); $sheet->setCellValue('J2', 'RENCANA / REALISASI');

// Category Headers (Row 3)
$sheet->mergeCells('J3:K3'); $sheet->setCellValue('J3', 'Fisik (Ha, pkk, dll)');
$sheet->mergeCells('L3:M3'); $sheet->setCellValue('L3', 'HK');
$sheet->mergeCells('N3:O3'); $sheet->setCellValue('N3', 'Bahan Kimia');
$sheet->mergeCells('P3:Q3'); $sheet->setCellValue('P3', 'Campuran');

// Sub-headers (Row 4)
$sheet->setCellValue('J4', 'H. INI'); $sheet->setCellValue('K4', 'S/D');
$sheet->setCellValue('L4', 'H. INI'); $sheet->setCellValue('M4', 'S/D');
$sheet->setCellValue('N4', 'H. INI'); $sheet->setCellValue('O4', 'S/D');
$sheet->setCellValue('P4', 'H. INI'); $sheet->setCellValue('Q4', 'S/D');

$headerStyle = [
    'font' => ['bold' => true, 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']]
];
$sheet->getStyle('H2:Q4')->applyFromArray($headerStyle);

// === INSERT DATA (starts at row 5) ===
$rowNum = 5;
if (empty($data_realisasi)) {
    $sheet->mergeCells("H$rowNum:Q$rowNum");
    $sheet->setCellValue("H$rowNum", 'Belum ada data realisasi yang disimpan.');
    $sheet->getStyle("H$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("H$rowNum:Q$rowNum")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $rowNum++;
} else {
    foreach ($data_realisasi as $row) {
        $sheet->setCellValue('H' . $rowNum, $row['tanggal_realisasi'] ? date('d-m-Y', strtotime($row['tanggal_realisasi'])) : '-');
        $sheet->setCellValue('I' . $rowNum, $row['blok_nama']);
        $sheet->setCellValue('J' . $rowNum, $row['fisik_hari_ini']);
        $sheet->setCellValue('K' . $rowNum, $row['fisik_sd']);
        $sheet->setCellValue('L' . $rowNum, $row['hk_hari_ini']);
        $sheet->setCellValue('M' . $rowNum, $row['hk_sd']);
        $sheet->setCellValue('N' . $rowNum, $row['bahan_kimia_hari_ini']);
        $sheet->setCellValue('O' . $rowNum, $row['bahan_kimia_sd']);
        $sheet->setCellValue('P' . $rowNum, $row['campuran_hari_ini']);
        $sheet->setCellValue('Q' . $rowNum, $row['campuran_sd']);
        
        // Bold for BLOK column
        $sheet->getStyle('I' . $rowNum)->getFont()->setBold(true);
        // Number format for numeric columns
        $sheet->getStyle("J$rowNum:Q$rowNum")->getNumberFormat()->setFormatCode('#,##0.00');
        $rowNum++;
    }
}

// Add blank rows up to 20 rows total to mimic PDF look
$minRows = 20;
$actualDataCount = count($data_realisasi);
if ($actualDataCount < $minRows) {
    for ($i = 0; $i < ($minRows - $actualDataCount); $i++) {
        $rowNum++;
    }
}

$lastDataRow = $rowNum - 1; // Last row of the data table

// Apply borders to ALL data cells (including blank rows)
$sheet->getStyle('H4:Q' . $lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('H4:I' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('H4:H' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// === MAP AREA (dynamically sized to match data table height) ===
if (!empty($map_image)) {
    $imgData = explode(',', $map_image)[1];
    $tempFile = sys_get_temp_dir() . '/map_' . uniqid() . '.png';
    file_put_contents($tempFile, base64_decode($imgData));

    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setName('Map Image');
    $drawing->setDescription('Map Image');
    $drawing->setPath($tempFile);
    $drawing->setCoordinates('A2');
    $drawing->setOffsetX(10);
    $drawing->setOffsetY(10);
    $drawing->setHeight(350);
    $drawing->setWorksheet($sheet);
}
// Merge map area to match the data table height (A2:G{lastDataRow})
$sheet->mergeCells("A2:G{$lastDataRow}");
$sheet->getStyle("A2:G{$lastDataRow}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

// === SIGNATURE (right below the map area) ===
$signRow = $lastDataRow + 2;
$sheet->mergeCells("A{$signRow}:C{$signRow}");
$sheet->mergeCells("D{$signRow}:G{$signRow}");
$sheet->setCellValue("A{$signRow}", "Dibuat Oleh,");
$sheet->setCellValue("D{$signRow}", "Diperiksa Oleh,");

$signRow++;
$sheet->mergeCells("A{$signRow}:C{$signRow}");
$sheet->mergeCells("D{$signRow}:G{$signRow}");
$sheet->setCellValue("A{$signRow}", "Asst Afdeling");
$sheet->setCellValue("D{$signRow}", "Asisten Kepala");
$sheet->getStyle("A{$signRow}:G{$signRow}")->getFont()->setBold(true);

$signRow += 4;
$sheet->mergeCells("A{$signRow}:C{$signRow}");
$sheet->mergeCells("D{$signRow}:G{$signRow}");
$sheet->setCellValue("A{$signRow}", "( ...................................... )");
$sheet->setCellValue("D{$signRow}", "( ...................................... )");

$signStart = $lastDataRow + 2;
$sheet->getStyle("A{$signStart}:G{$signRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// === COLUMN WIDTHS (match PDF proportions) ===
// Map area: A-G (7 columns)
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setWidth(13);
}
// Data table: H-Q (10 columns)
$sheet->getColumnDimension('H')->setWidth(12); // TANGGAL
$sheet->getColumnDimension('I')->setWidth(10); // BLOK
foreach (range('J', 'Q') as $col) {
    $sheet->getColumnDimension($col)->setWidth(9); // Data columns
}

// Clean up Temp Image after script ends automatically by PHP or we can leave it in sys_get_temp_dir

// Output
ob_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="peta_realisasi_' . date('YmdHis') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
