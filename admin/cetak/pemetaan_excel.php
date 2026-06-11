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

// Set Header Row (Row 1)
$sheet->mergeCells('A1:E1');
$sheet->setCellValue('A1', "PT. PERKEBUNAN NUSANTARA IV\nREGIONAL III - DISTRIK BARAT\nKEBUN " . strtoupper($nama_kebun));
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF065F46'); // Green
$sheet->getStyle('A1')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE))->setBold(true);

$sheet->mergeCells('F1:I1');
$sheet->setCellValue('F1', "PETA KETERANGAN (" . strtoupper($nama_unit) . ")");
$sheet->getStyle('F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFBBF24'); // Yellow
$sheet->getStyle('F1')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK))->setBold(true)->setSize(14);

$sheet->mergeCells('J1:O1');
$sheet->setCellValue('J1', "BULAN : " . strtoupper($bulan_tahun) . "\nPEKERJAAN : " . strtoupper($nama_pekerjaan));
$sheet->getStyle('J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E40AF'); // Blue
$sheet->getStyle('J1')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE))->setBold(true);

$sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getRowDimension(1)->setRowHeight(50);

// Set Map Image
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
    $drawing->setHeight(400); 
    $drawing->setWorksheet($sheet);
}
// Merge cells for map area to make it look clean
$sheet->mergeCells('A2:E25');
$sheet->getStyle('A2:E25')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

// Set Table Headers
$sheet->mergeCells('F2:F3'); $sheet->setCellValue('F2', 'TANGGAL');
$sheet->mergeCells('G2:G3'); $sheet->setCellValue('G2', 'BLOK');
$sheet->mergeCells('H2:I2'); $sheet->setCellValue('H2', 'Fisik (Ha, Pkk)');
$sheet->mergeCells('J2:K2'); $sheet->setCellValue('J2', 'HK');
$sheet->mergeCells('L2:M2'); $sheet->setCellValue('L2', 'Bahan Kimia');
$sheet->mergeCells('N2:O2'); $sheet->setCellValue('N2', 'Campuran');

$sheet->setCellValue('H3', 'H. INI'); $sheet->setCellValue('I3', 'S/D');
$sheet->setCellValue('J3', 'H. INI'); $sheet->setCellValue('K3', 'S/D');
$sheet->setCellValue('L3', 'H. INI'); $sheet->setCellValue('M3', 'S/D');
$sheet->setCellValue('N3', 'H. INI'); $sheet->setCellValue('O3', 'S/D');

$headerStyle = [
    'font' => ['bold' => true, 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']]
];
$sheet->getStyle('F2:O3')->applyFromArray($headerStyle);

// Insert Data
$rowNum = 4;
if (empty($data_realisasi)) {
    $sheet->mergeCells("F$rowNum:O$rowNum");
    $sheet->setCellValue("F$rowNum", 'Belum ada data realisasi yang disimpan.');
    $sheet->getStyle("F$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("F$rowNum:O$rowNum")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $rowNum++;
} else {
    foreach ($data_realisasi as $row) {
        $sheet->setCellValue('F' . $rowNum, $row['tanggal_realisasi'] ? date('d-m-Y', strtotime($row['tanggal_realisasi'])) : '-');
        $sheet->setCellValue('G' . $rowNum, $row['blok_nama']);
        $sheet->setCellValue('H' . $rowNum, $row['fisik_hari_ini']);
        $sheet->setCellValue('I' . $rowNum, $row['fisik_sd']);
        $sheet->setCellValue('J' . $rowNum, $row['hk_hari_ini']);
        $sheet->setCellValue('K' . $rowNum, $row['hk_sd']);
        $sheet->setCellValue('L' . $rowNum, $row['bahan_kimia_hari_ini']);
        $sheet->setCellValue('M' . $rowNum, $row['bahan_kimia_sd']);
        $sheet->setCellValue('N' . $rowNum, $row['campuran_hari_ini']);
        $sheet->setCellValue('O' . $rowNum, $row['campuran_sd']);
        
        $sheet->getStyle("H$rowNum:O$rowNum")->getNumberFormat()->setFormatCode('#,##0.00');
        $rowNum++;
    }
}

// Add blank rows up to 20 rows total if data is small, to mimic PDF look
$minRows = 20;
$actualDataCount = count($data_realisasi);
if ($actualDataCount < $minRows) {
    for ($i = 0; $i < ($minRows - $actualDataCount); $i++) {
        $rowNum++;
    }
}

// Apply borders to all data cells
$sheet->getStyle('F4:O' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('F4:G' . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Column Widths
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(12);
foreach (range('H', 'O') as $col) {
    $sheet->getColumnDimension($col)->setWidth(11);
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
