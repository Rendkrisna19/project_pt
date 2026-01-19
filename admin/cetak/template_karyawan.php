<?php
// pages/cetak/template_karyawan.php
// Menggunakan Library PhpSpreadsheet untuk membuat file .xlsx valid dengan Style Cyan

require_once '../../vendor/autoload.php'; // Sesuaikan path vendor

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- 1. SETUP HEADER ---
$headers = [
    'A1' => 'ID SAP (Wajib)',
    'B1' => 'Old Pers. No',
    'C1' => 'Nama Lengkap (Wajib)',
    'D1' => 'NIK KTP',
    'E1' => 'Gender (L/P)',
    'F1' => 'Tempat Lahir',
    'G1' => 'Tgl Lahir (YYYY-MM-DD)',
    'H1' => 'Jabatan',
    'I1' => 'Afdeling',
    'J1' => 'Status',
    'K1' => 'TMT Kerja (YYYY-MM-DD)',
    'L1' => 'TMT Pensiun (YYYY-MM-DD)',
    'M1' => 'No HP',
    'N1' => 'Agama',
    'O1' => 'Bank',
    'P1' => 'No Rekening',
    'Q1' => 'NPWP'
];

foreach ($headers as $cell => $val) {
    $sheet->setCellValue($cell, $val);
}

// --- 2. STYLING HEADER (CYAN TUA) ---
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'], // Putih
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF0E7490'], // Cyan 700 (Hex: 0e7490)
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ],
];
$sheet->getStyle('A1:Q1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(30);

// --- 3. CONTOH DATA (CYAN MUDA) ---
$dataContoh = [
    ['10001', '8001', 'CONTOH BUDI', '1234567890123456', 'L', 'MEDAN', '1990-01-01', 'MANDOR', 'AFD I', 'TETAP', '2015-05-20', '2045-05-20', '08123456789', 'ISLAM', 'BRI', '1234567890', '12.345.678.9-000']
];

$row = 2;
foreach ($dataContoh as $d) {
    $col = 'A';
    foreach ($d as $val) {
        $sheet->setCellValue($col . $row, $val);
        $col++;
    }
}

// Styling Contoh Data
$dataStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFCFFAFE'], // Cyan 100 (Hex: cffafe)
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ]
];
$sheet->getStyle('A2:Q2')->applyFromArray($dataStyle);

// --- 4. FORMAT KOLOM TEKS (Agar NIK tidak berubah jadi eksponen) ---
// Set kolom A, B, D, M, P, Q jadi Text
$textCols = ['A','B','D','M','P','Q'];
foreach($textCols as $col) {
    $sheet->getStyle($col.'2:'.$col.'100')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
}

// Auto Size Columns
foreach (range('A', 'Q') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// --- 5. OUTPUT ---
$filename = 'Template_Data_Karyawan_Cyan.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;