<?php
// admin/imports/import_template_lm_biaya.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}

chdir(__DIR__ . '/..');
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template LM Biaya');

// ===== HEADER KOLOM (Baris 1) =====
$headers = [
    'A1' => 'Kebun',
    'B1' => 'Unit/Defisi',
    'C1' => 'No. Alokasi',
    'D1' => 'Uraian Pekerjaan',
    'E1' => 'Bulan',
    'F1' => 'Tahun',
    'G1' => 'Anggaran (Rp)',
    'H1' => 'Realisasi (Rp)'
];

foreach ($headers as $cell => $label) {
    $sheet->setCellValue($cell, $label);
}

// Style Header
$headerRange = 'A1:H1';
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Arial'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0891B2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

// ===== CONTOH DATA (Baris 2-3) =====
$sampleData = [
    ['Air Molek I', 'AFD01', '0101', 'Pemupukan NPK', 'Juni', '2026', 15000000, 14500000],
    ['Air Molek I', 'AFD01', '0102', 'Pruning Sawit', 'Juni', '2026', 8000000, 8500000],
    ['Air Molek II', 'AFD02', '0201', 'Penyemprotan Gulma', 'Juni', '2026', 5000000, 4800000],
];

$rowNum = 2;
foreach ($sampleData as $row) {
    $col = 'A';
    foreach ($row as $val) {
        $sheet->setCellValue($col . $rowNum, $val);
        $col++;
    }
    $rowNum++;
}

// Style data rows
$dataRange = 'A2:H4';
$sheet->getStyle($dataRange)->applyFromArray([
    'font' => ['size' => 10, 'name' => 'Arial'],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F9FF']],
]);

$sheet->getStyle('A2:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('E2:F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G2:H4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('G2:H4')->getNumberFormat()->setFormatCode('#,##0.00');

// ===== PANDUAN (Baris 7-16) =====
$sheet->setCellValue('A7', '📋 PANDUAN PENGISIAN:');
$sheet->getStyle('A7')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0E7490'));
$sheet->mergeCells('A7:H7');

$guides = [
    ['Kolom', 'Keterangan', 'Wajib?'],
    ['kebun', 'Nama kebun (harus sesuai master data)', 'Opsional'],
    ['unit', 'Nama unit/defisi (harus sesuai master data)', '✅ Wajib'],
    ['alokasi', 'Nomor alokasi (misal: 0101)', '✅ Wajib'],
    ['uraian_pekerjaan', 'Uraian jenis pekerjaan', '✅ Wajib'],
    ['bulan', 'Nama bulan: Januari, Februari, ... Desember', '✅ Wajib'],
    ['tahun', 'Tahun data (contoh: 2026)', '✅ Wajib'],
    ['anggaran', 'Anggaran biaya dalam Rp (angka desimal)', '—'],
    ['realisasi', 'Realisasi biaya dalam Rp (angka desimal)', '—'],
];

$guideRow = 8;
foreach ($guides as $idx => $g) {
    $sheet->setCellValue('A' . $guideRow, $g[0]);
    $sheet->setCellValue('C' . $guideRow, $g[1]);
    $sheet->setCellValue('H' . $guideRow, $g[2]);
    $sheet->mergeCells('A' . $guideRow . ':B' . $guideRow);
    $sheet->mergeCells('C' . $guideRow . ':G' . $guideRow);
    
    if ($idx === 0) {
        $sheet->getStyle("A{$guideRow}:H{$guideRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E7490']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0E7490']]],
        ]);
    } else {
        $bgColor = ($idx % 2 === 0) ? 'F0FDFA' : 'FFFFFF';
        $sheet->getStyle("A{$guideRow}:H{$guideRow}")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);
        $sheet->getStyle("A{$guideRow}:B{$guideRow}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0891B2'));
    }
    $guideRow++;
}

$noteRow = $guideRow + 1;
$sheet->setCellValue('A' . $noteRow, '⚠ Hapus baris contoh (baris 2-4) sebelum mengimpor data Anda.');
$sheet->mergeCells('A' . $noteRow . ':H' . $noteRow);
$sheet->getStyle('A' . $noteRow)->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('EF4444'));

// ===== AUTO-FIT KOLOM =====
$colWidths = ['A'=>22, 'B'=>18, 'C'=>14, 'D'=>30, 'E'=>12, 'F'=>10, 'G'=>18, 'H'=>18];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// ===== OUTPUT =====
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template_Import_LM_Biaya.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
