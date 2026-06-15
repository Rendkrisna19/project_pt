<?php
// admin/imports/import_template_alat_panen.php
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
$sheet->setTitle('Template Alat Panen');

// ===== HEADER KOLOM (Baris 1) =====
$headers = [
    'A1' => 'Kebun',
    'B1' => 'Unit',
    'C1' => 'Jenis Alat',
    'D1' => 'Bulan',
    'E1' => 'Tahun',
    'F1' => 'Stok Awal',
    'G1' => 'Mutasi Masuk',
    'H1' => 'Mutasi Keluar',
    'I1' => 'Dipakai',
    'J1' => 'Krani Afdeling',
    'K1' => 'Catatan'
];

foreach ($headers as $cell => $label) {
    $sheet->setCellValue($cell, $label);
}

// Style Header
$headerRange = 'A1:K1';
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Arial'],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0891B2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

// ===== CONTOH DATA (Baris 2-6) — 5 Data Dummy =====
// stok_akhir dihitung otomatis oleh sistem: stok_awal + mutasi_masuk - mutasi_keluar - dipakai
$sampleData = [
    ['Kebun Andalas', 'AFREADING 2',   'Egrek',  'Januari',  '2026', 50,  20, 10, 15, 'Budi',    'Penggunaan rutin'],
    ['Kebun Andalas', 'Afdeling-II',   'Dodos',  'Januari',  '2026', 30,  10,  5,  8, 'Andi',    'Distribusi afdeling'],
    ['Kebun Bahari',  'Afdeling-III',  'Gancu',  'Februari', '2026', 20,   5,  3,  4, 'Sari',    'Alat baru masuk'],
    ['Kebun Cendana', 'Afdeling-IV',   'Egrek',  'Maret',    '2026', 40,  15,  8, 12, 'Dedi',    'Stok mencukupi'],
    ['Kebun Damar',   'Afdeling-V',    'Dodos',  'April',    '2026', 25,   8,  4,  6, 'Rina',    'Pemeliharaan alat'],
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
$dataRange = 'A2:K6';
$sheet->getStyle($dataRange)->applyFromArray([
    'font' => ['size' => 10, 'name' => 'Arial'],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F9FF']],
]);

$sheet->getStyle('A2:C6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('D2:E6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F2:I6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('F2:I6')->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle('J2:K6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

// ===== PANDUAN (Baris 9-21) =====
$sheet->setCellValue('A9', '📋 PANDUAN PENGISIAN:');
$sheet->getStyle('A9')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0E7490'));
$sheet->mergeCells('A9:K9');

$guides = [
    ['Kolom', 'Keterangan', 'Wajib?'],
    ['kebun', 'Nama kebun (harus sesuai master data)', '✅ Wajib'],
    ['unit', 'Nama unit/afdeling (harus sesuai master data)', '✅ Wajib'],
    ['jenis_alat', 'Nama jenis alat panen: Egrek, Dodos, Gancu', '✅ Wajib'],
    ['bulan', 'Nama bulan: Januari, Februari, ... Desember', '✅ Wajib'],
    ['tahun', 'Tahun data (contoh: 2026)', '✅ Wajib'],
    ['stok_awal', 'Stok awal periode (angka)', '—'],
    ['mutasi_masuk', 'Mutasi masuk / alat masuk (angka)', '—'],
    ['mutasi_keluar', 'Mutasi keluar / alat keluar (angka)', '—'],
    ['dipakai', 'Jumlah alat yang dipakai (angka)', '—'],
    ['krani_afdeling', 'Nama krani afdeling (teks)', 'Opsional'],
    ['catatan', 'Catatan tambahan (teks)', 'Opsional'],
];

$guideRow = 10;
foreach ($guides as $idx => $g) {
    $sheet->setCellValue('A' . $guideRow, $g[0]);
    $sheet->setCellValue('C' . $guideRow, $g[1]);
    $sheet->setCellValue('K' . $guideRow, $g[2]);
    $sheet->mergeCells('A' . $guideRow . ':B' . $guideRow);
    $sheet->mergeCells('C' . $guideRow . ':J' . $guideRow);
    
    if ($idx === 0) {
        $sheet->getStyle("A{$guideRow}:K{$guideRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E7490']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0E7490']]],
        ]);
    } else {
        $bgColor = ($idx % 2 === 0) ? 'F0FDFA' : 'FFFFFF';
        $sheet->getStyle("A{$guideRow}:K{$guideRow}")->applyFromArray([
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
$sheet->mergeCells('A' . $noteRow . ':K' . $noteRow);
$sheet->getStyle('A' . $noteRow)->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('EF4444'));

$noteRow2 = $noteRow + 1;
$sheet->setCellValue('A' . $noteRow2, 'ℹ Stok Akhir dihitung otomatis oleh sistem (Stok Awal + Mutasi Masuk - Mutasi Keluar - Dipakai).');
$sheet->mergeCells('A' . $noteRow2 . ':K' . $noteRow2);
$sheet->getStyle('A' . $noteRow2)->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0891B2'));

// ===== AUTO-FIT KOLOM =====
$colWidths = ['A'=>20, 'B'=>18, 'C'=>16, 'D'=>14, 'E'=>10, 'F'=>14, 'G'=>16, 'H'=>16, 'I'=>12, 'J'=>18, 'K'=>24];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// ===== OUTPUT =====
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template_Import_Alat_Panen.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
