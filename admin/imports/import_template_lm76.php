<?php
// admin/imports/import_template_lm76.php
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
use PhpOffice\PhpSpreadsheet\Style\Font;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template LM76');

// ===== HEADER KOLOM (Baris 1) =====
$headers = [
    'A1' => 'Tahun',
    'B1' => 'Kebun',
    'C1' => 'Unit/Defisi',
    'D1' => 'Bulan',
    'E1' => 'T. Tanam',
    'F1' => 'Luas (Ha)',
    'G1' => 'Invt Pokok',
    'H1' => 'Anggaran (Kg)',
    'I1' => 'Realisasi (Kg)',
    'J1' => 'Jlh Tandan',
    'K1' => 'Jlh HK',
    'L1' => 'Panen (Ha)',
];

foreach ($headers as $cell => $label) {
    $sheet->setCellValue($cell, $label);
}

// Style Header: Cyan background, white bold text, center aligned, borders
$headerRange = 'A1:L1';
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11,
        'name' => 'Arial',
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0891B2'], // Cyan
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '334155'],
        ],
    ],
]);

// Tinggi baris header
$sheet->getRowDimension(1)->setRowHeight(30);

// ===== CONTOH DATA (Baris 2-3) =====
$sampleData = [
    ['2026', 'Air Molek I', 'AFD01',  'Juni', '1998', 25.50, 3500, 15000, 12500, 850, 45, 22.00],
    ['2026', 'Air Molek II', 'AFD02', 'Juni', '2003', 30.00, 4200, 18000, 16000, 1200, 52, 28.50],
    ['2026', 'Lubuk Dalam', 'AFD03',  'Juni', '2004', 20.00, 2800, 12000, 10500, 750, 35, 18.00],
    ['2026', 'Sei Batu Langkah', 'AFD04', 'Juni', '2005', 35.00, 5000, 21000, 19000, 1500, 60, 32.50],
    ['2026', 'Sei Berlian', 'AFD05', 'Juni', '2006', 40.00, 5600, 24000, 22500, 1800, 70, 38.00],
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

// Style data rows: borders + light background
$dataRange = 'A2:L6';
$sheet->getStyle($dataRange)->applyFromArray([
    'font' => [
        'size' => 10,
        'name' => 'Arial',
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1'],
        ],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F9FF'], // Light cyan
    ],
]);

// Alignment: angka rata kanan, teks rata kiri
$sheet->getStyle('A2:E6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('F2:L6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('F2:L6')->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle('G2:G6')->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle('J2:J6')->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle('K2:K6')->getNumberFormat()->setFormatCode('#,##0.00');

// ===== PANDUAN (Baris 8-18) =====
$sheet->setCellValue('A8', '📋 PANDUAN PENGISIAN:');
$sheet->getStyle('A8')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0E7490'));
$sheet->mergeCells('A8:L8');

$guides = [
    ['Kolom', 'Keterangan', 'Wajib?'],
    ['tahun', 'Tahun data (contoh: 2026)', '✅ Wajib'],
    ['kebun', 'Nama kebun (harus sesuai master data)', 'Opsional'],
    ['unit', 'Nama unit/afdeling (harus sesuai master data)', '✅ Wajib'],
    ['bulan', 'Nama bulan: Januari, Februari, ... Desember', '✅ Wajib'],
    ['tahun_tanam', 'Tahun tanam (T. Tanam), harus ada di master', '✅ Wajib'],
    ['luas_ha', 'Luas area dalam hektar (angka desimal)', '—'],
    ['invt_pokok', 'Inventaris pokok / jumlah pohon (angka bulat)', '—'],
    ['anggaran_kg', 'Anggaran dalam Kg (angka desimal)', '—'],
    ['realisasi_kg', 'Realisasi dalam Kg (angka desimal)', '—'],
    ['jumlah_tandan', 'Jumlah tandan (angka bulat)', '—'],
    ['jumlah_hk', 'Jumlah hari kerja (angka desimal)', '—'],
    ['panen_ha', 'Luas panen dalam hektar (angka desimal)', '—'],
];

$guideRow = 9;
foreach ($guides as $idx => $g) {
    $sheet->setCellValue('A' . $guideRow, $g[0]);
    $sheet->setCellValue('D' . $guideRow, $g[1]);
    $sheet->setCellValue('I' . $guideRow, $g[2]);
    $sheet->mergeCells('A' . $guideRow . ':C' . $guideRow);
    $sheet->mergeCells('D' . $guideRow . ':H' . $guideRow);
    $sheet->mergeCells('I' . $guideRow . ':L' . $guideRow);
    
    if ($idx === 0) {
        // Header panduan
        $sheet->getStyle("A{$guideRow}:L{$guideRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E7490']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0E7490']]],
        ]);
    } else {
        $bgColor = ($idx % 2 === 0) ? 'F0FDFA' : 'FFFFFF';
        $sheet->getStyle("A{$guideRow}:L{$guideRow}")->applyFromArray([
            'font' => ['size' => 9, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);
        $sheet->getStyle("A{$guideRow}:C{$guideRow}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0891B2'));
    }
    $guideRow++;
}

// Catatan tambahan
$noteRow = $guideRow + 1;
$sheet->setCellValue('A' . $noteRow, '⚠ Frekuensi dihitung otomatis oleh sistem (Panen Ha ÷ Luas Ha). Tidak perlu diisi.');
$sheet->mergeCells('A' . $noteRow . ':L' . $noteRow);
$sheet->getStyle('A' . $noteRow)->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('EF4444'));

$noteRow2 = $noteRow + 1;
$sheet->setCellValue('A' . $noteRow2, '⚠ Hapus baris contoh (baris 2-6) sebelum mengimpor data Anda.');
$sheet->mergeCells('A' . $noteRow2 . ':L' . $noteRow2);
$sheet->getStyle('A' . $noteRow2)->getFont()->setItalic(true)->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('EF4444'));

// ===== AUTO-FIT KOLOM =====
$colWidths = ['A'=>10, 'B'=>22, 'C'=>18, 'D'=>14, 'E'=>12, 'F'=>12, 'G'=>12, 'H'=>16, 'I'=>16, 'J'=>13, 'K'=>10, 'L'=>12];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// ===== OUTPUT =====
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template_Import_LM76.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
