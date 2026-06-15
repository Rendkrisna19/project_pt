<?php
// admin/imports/import_template_stok_barang_gudang.php
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
$sheet->setTitle('Template Stok Barang Gudang');

// ===== HEADER KOLOM (Baris 1) =====
$headers = [
    'A1' => 'Kebun',
    'B1' => 'Jenis Barang',
    'C1' => 'Bulan',
    'D1' => 'Tahun',
    'E1' => 'Stok Awal',
    'F1' => 'Mutasi Masuk',
    'G1' => 'Mutasi Keluar',
    'H1' => 'Pasokan',
    'I1' => 'Dipakai'
];

foreach ($headers as $cell => $label) {
    $sheet->setCellValue($cell, $label);
}

// Style Header
$headerRange = 'A1:I1';
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Arial'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0891B2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

// ===== CONTOH DATA (Baris 2-6) — 5 Data Dummy =====
$sampleData = [
    ['Kebun Andalas', 'Pupuk Barang', 'Januari',  '2026', 1000,  500,   200,   300,   150],
    ['Kebun Bahari',  'Pupuk Barang', 'Januari',  '2026', 2000,  800,   300,   500,   400],
    ['Kebun Cendana', 'Pupuk Barang', 'Februari', '2026', 1500,  600,   250,   400,   350],
    ['Kebun Damar',   'Pupuk Barang', 'Maret',    '2026', 3000,  1000,  500,   700,   600],
    ['Kebun Andalas', 'Pupuk Barang', 'April',    '2026', 500,   200,   100,   150,   80],
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
$dataRange = 'A2:I6';
$sheet->getStyle($dataRange)->applyFromArray([
    'font' => ['size' => 10, 'name' => 'Arial'],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F9FF']],
]);

$sheet->getStyle('A2:B6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('C2:D6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E2:I6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('E2:I6')->getNumberFormat()->setFormatCode('#,##0.00');

// ===== PANDUAN (Baris 9-18) =====
$sheet->setCellValue('A9', '📋 PANDUAN PENGISIAN:');
$sheet->getStyle('A9')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0E7490'));
$sheet->mergeCells('A9:I9');

$guides = [
    ['Kolom', 'Keterangan', 'Wajib?'],
    ['kebun', 'Nama kebun (harus sesuai master data)', '✅ Wajib'],
    ['jenis_barang', 'Nama jenis barang gudang (harus sesuai master data)', '✅ Wajib'],
    ['bulan', 'Nama bulan: Januari, Februari, ... Desember', '✅ Wajib'],
    ['tahun', 'Tahun data (contoh: 2026)', '✅ Wajib'],
    ['stok_awal', 'Stok awal periode (angka desimal)', '—'],
    ['mutasi_masuk', 'Mutasi masuk / barang masuk (angka desimal)', '—'],
    ['mutasi_keluar', 'Mutasi keluar / barang keluar (angka desimal)', '—'],
    ['pasokan', 'Pasokan tambahan (angka desimal)', '—'],
    ['dipakai', 'Jumlah yang dipakai (angka desimal)', '—'],
];

$guideRow = 10;
foreach ($guides as $idx => $g) {
    $sheet->setCellValue('A' . $guideRow, $g[0]);
    $sheet->setCellValue('C' . $guideRow, $g[1]);
    $sheet->setCellValue('I' . $guideRow, $g[2]);
    $sheet->mergeCells('A' . $guideRow . ':B' . $guideRow);
    $sheet->mergeCells('C' . $guideRow . ':H' . $guideRow);
    
    if ($idx === 0) {
        $sheet->getStyle("A{$guideRow}:I{$guideRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E7490']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0E7490']]],
        ]);
    } else {
        $bgColor = ($idx % 2 === 0) ? 'F0FDFA' : 'FFFFFF';
        $sheet->getStyle("A{$guideRow}:I{$guideRow}")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);
        $sheet->getStyle("A{$guideRow}:B{$guideRow}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0891B2'));
    }
    $guideRow++;
}

$noteRow = $guideRow + 1;
$sheet->setCellValue('A' . $noteRow, '⚠ Hapus baris contoh (baris 2-6) sebelum mengimpor data Anda.');
$sheet->mergeCells('A' . $noteRow . ':I' . $noteRow);
$sheet->getStyle('A' . $noteRow)->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('EF4444'));

// ===== AUTO-FIT KOLOM =====
$colWidths = ['A'=>22, 'B'=>22, 'C'=>14, 'D'=>10, 'E'=>16, 'F'=>16, 'G'=>18, 'H'=>14, 'I'=>14];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// ===== OUTPUT =====
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template_Import_Stok_Barang_Gudang.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
