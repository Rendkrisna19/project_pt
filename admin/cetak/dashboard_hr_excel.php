<?php
// cetak/dashboard_hr_excel.php
// Export Rekap Karyawan per Afdeling & Status ke Excel

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    exit('Unauthorized Access'); 
}

require_once '../../vendor/autoload.php';
require_once '../../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 1. Setup Database
$db = new Database();
$conn = $db->getConnection();

// 2. Ambil Daftar Afdeling Unik dari Database
// Kita group by afdeling agar mendapatkan list unik
$sqlAfd = "SELECT DISTINCT afdeling FROM data_karyawan 
           WHERE afdeling IS NOT NULL AND afdeling != '' 
           ORDER BY afdeling ASC";
$stmtAfd = $conn->query($sqlAfd);
$afdelings = $stmtAfd->fetchAll(PDO::FETCH_COLUMN);

// 3. Query Hitung Data per Afdeling & Status
// Kita akan meloop setiap afdeling dan menghitung statusnya
$dataRekap = [];
$totalGlobal = ['KARPIM'=>0, 'TS'=>0, 'KNG'=>0, 'PKWT'=>0, 'TOTAL'=>0];

foreach ($afdelings as $afd) {
    // Hitung per status untuk afdeling ini
    $sqlCount = "SELECT 
                    SUM(CASE WHEN status_karyawan = 'KARPIM' THEN 1 ELSE 0 END) as karpim,
                    SUM(CASE WHEN status_karyawan = 'TS' THEN 1 ELSE 0 END) as ts,
                    SUM(CASE WHEN status_karyawan = 'KNG' THEN 1 ELSE 0 END) as kng,
                    SUM(CASE WHEN status_karyawan = 'PKWT' THEN 1 ELSE 0 END) as pkwt,
                    COUNT(*) as total
                 FROM data_karyawan 
                 WHERE afdeling = ?";
    
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute([$afd]);
    $res = $stmtCount->fetch(PDO::FETCH_ASSOC);

    // Simpan ke array data
    $row = [
        'bagian' => $afd,
        'karpim' => (int)$res['karpim'],
        'ts'     => (int)$res['ts'],
        'kng'    => (int)$res['kng'],
        'pkwt'   => (int)$res['pkwt'],
        'total'  => (int)$res['total']
    ];
    $dataRekap[] = $row;

    // Tambahkan ke total global
    $totalGlobal['KARPIM'] += $row['karpim'];
    $totalGlobal['TS']     += $row['ts'];
    $totalGlobal['KNG']    += $row['kng'];
    $totalGlobal['PKWT']   += $row['pkwt'];
    $totalGlobal['TOTAL']  += $row['total'];
}

// 4. Buat Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap SDM');

// --- HEADER JUDUL ---
$sheet->mergeCells('A1:F1');
$sheet->setCellValue('A1', 'REKAP KARYAWAN PTPN IV REGIONAL 3 KEBUN SEI ROKAN');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:F2');
$sheet->setCellValue('A2', 'PERIODE: ' . strtoupper(date('F Y')));
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// --- HEADER TABEL ---
$row = 4;
// Baris 1 Header
$sheet->mergeCells("A$row:A".($row+1)); // Bagian (Merge 2 baris)
$sheet->setCellValue("A$row", 'BAGIAN');

$sheet->mergeCells("B$row:E$row"); // Jumlah Karyawan (Merge 4 kolom)
$sheet->setCellValue("B$row", 'JUMLAH KARYAWAN');

$sheet->mergeCells("F$row:F".($row+1)); // Total (Merge 2 baris)
$sheet->setCellValue("F$row", 'TOTAL');

// Baris 2 Header (Sub-header Status)
$subRow = $row + 1;
$sheet->setCellValue("B$subRow", 'KARPIM');
$sheet->setCellValue("C$subRow", 'TS');
$sheet->setCellValue("D$subRow", 'KNG');
$sheet->setCellValue("E$subRow", 'PKWT');

// Style Header Table
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8fabdd']], // Warna biru muda seperti gambar
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER, 
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle("A$row:F$subRow")->applyFromArray($headerStyle);

// --- ISI DATA ---
$startDataRow = $subRow + 1;
$currentRow = $startDataRow;

foreach ($dataRekap as $d) {
    $sheet->setCellValue("A$currentRow", $d['bagian']);
    $sheet->setCellValue("B$currentRow", $d['karpim']);
    $sheet->setCellValue("C$currentRow", $d['ts']);
    $sheet->setCellValue("D$currentRow", $d['kng']);
    $sheet->setCellValue("E$currentRow", $d['pkwt']);
    $sheet->setCellValue("F$currentRow", $d['total']);
    
    // Style Baris Data (Center alignment untuk angka)
    $sheet->getStyle("B$currentRow:F$currentRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A$currentRow")->getFont()->setBold(true); // Nama bagian bold
    
    $currentRow++;
}

// --- ROW TOTAL ---
$sheet->setCellValue("A$currentRow", 'TOTAL');
$sheet->setCellValue("B$currentRow", $totalGlobal['KARPIM']);
$sheet->setCellValue("C$currentRow", $totalGlobal['TS']);
$sheet->setCellValue("D$currentRow", $totalGlobal['KNG']);
$sheet->setCellValue("E$currentRow", $totalGlobal['PKWT']);
$sheet->setCellValue("F$currentRow", $totalGlobal['TOTAL']);

// Style Row Total (Hijau)
$totalStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '92d050']], // Hijau muda
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle("A$currentRow:F$currentRow")->applyFromArray($totalStyle);

// --- BORDER UNTUK SELURUH DATA ---
$sheet->getStyle("A$startDataRow:F".($currentRow-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// --- LEBAR KOLOM ---
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(12);
$sheet->getColumnDimension('C')->setWidth(12);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(12);

// --- OUTPUT EXCEL ---
$filename = 'Rekap_SDM_' . date('Y_m_d') . '.xlsx';

ob_end_clean(); // Bersihkan buffer
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;