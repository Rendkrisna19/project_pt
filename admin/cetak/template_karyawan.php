<?php
// cetak/template_karyawan.php
// FIXED VERSION: 30 Kolom (A - AD)

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Import');

// =========================================================
// A. DEFINISI HEADER (30 KOLOM - LENGKAP)
// =========================================================
$headers = [
    'A' => 'ID SAP *',
    'B' => 'Old Pers No',
    'C' => 'Nama Lengkap *',
    'D' => 'NIK KTP',
    'E' => 'Gender (L/P)',
    'F' => 'Tempat Lahir',
    'G' => 'Tanggal Lahir (YYYY-MM-DD)',
    'H' => 'Person Grade',
    'I' => 'Golongan PHDP',
    'J' => 'Status Keluarga',
    'K' => 'Jabatan SAP',
    'L' => 'Jabatan Real',
    'M' => 'Nama Kebun',          // <-- User input Nama, sistem cari ID
    'N' => 'Afdeling/Unit',
    'O' => 'Status Karyawan',
    'P' => 'TMT Masuk Kerja',
    'Q' => 'TMT MBT',
    'R' => 'TMT Pensiun',
    'S' => 'Tax ID',
    'T' => 'BPJS ID',
    'U' => 'Jamsostek ID',
    'V' => 'Nama Bank',
    'W' => 'No Rekening',
    'X' => 'Nama Pemilik Rekening', // <-- New
    'Y' => 'No HP',
    'Z' => 'Agama',
    'AA'=> 'Status Pajak (PTKP)',
    'AB'=> 'Pendidikan Terakhir',
    'AC'=> 'Jurusan',
    'AD'=> 'Institusi Kampus'
];

// Style Header
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0e7490']], // Cyan-700
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

foreach ($headers as $col => $txt) {
    $sheet->setCellValue($col . '1', $txt);
    $sheet->getStyle($col . '1')->applyFromArray($styleHeader);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getRowDimension(1)->setRowHeight(30);

// =========================================================
// B. CONTOH DATA (Baris 2)
// =========================================================
$sample = [
    'A' => '2024001',           // ID SAP
    'B' => 'OLD-001',           // Old Pers
    'C' => 'BUDI SANTOSO',      // Nama
    'D' => '1234567890123456',  // NIK
    'E' => 'L',                 // Gender
    'F' => 'MEDAN',             // Tmp Lahir
    'G' => '1990-01-01',        // Tgl Lahir
    'H' => '4A',                // Grade
    'I' => 'II/A',              // PHDP
    'J' => 'K/1',               // Stat Keluarga
    'K' => 'MANDOR',            // Jab SAP
    'L' => 'MANDOR 1',          // Jab Real
    'M' => 'KEBUN SEI PUTIH',   // Nama Kebun (Harus sama dgn Master Kebun)
    'N' => 'AFDELING I',        // Afdeling
    'O' => 'PKWT',              // Stat Karyawan
    'P' => '2020-01-01',        // TMT Kerja
    'Q' => '2025-01-01',        // TMT MBT
    'R' => '2045-01-01',        // TMT Pensiun
    'S' => 'TAX-001',           // Tax ID
    'T' => 'BPJS-001',          // BPJS
    'U' => 'JAM-001',           // Jamsostek
    'V' => 'MANDIRI',           // Bank
    'W' => '1234567890',        // No Rek
    'X' => 'BUDI SANTOSO',      // Pemilik Rek
    'Y' => '08123456789',       // HP
    'Z' => 'ISLAM',             // Agama
    'AA'=> 'K/1',               // Stat Tax
    'AB'=> 'SMA',               // Pendidikan
    'AC'=> 'IPA',               // Jurusan
    'AD'=> 'SMA N 1 MEDAN'      // Institusi
];

foreach ($sample as $col => $val) {
    $sheet->setCellValueExplicit($col . '2', $val, DataType::TYPE_STRING);
}

// Output File
ob_end_clean();
$filename = 'TEMPLATE_KARYAWAN.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>