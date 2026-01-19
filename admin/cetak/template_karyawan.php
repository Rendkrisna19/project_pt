<?php
// cetak/template_karyawan.php
// TEMPLATE EXCEL IMPORT - Sesuai Struktur Database Baru

require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set Title
$sheet->setTitle('Template Karyawan');

// Header Columns (sesuai urutan import)
$headers = [
    'A' => 'ID SAP',
    'B' => 'Old Pers No',
    'C' => 'Nama Lengkap',
    'D' => 'NIK KTP',
    'E' => 'Gender',
    'F' => 'Tempat Lahir',
    'G' => 'Tanggal Lahir',
    'H' => 'Grade',
    'I' => 'Golongan PHDP',
    'J' => 'Status Keluarga',
    'K' => 'Jabatan SAP',
    'L' => 'Jabatan Real',
    'M' => 'Afdeling',
    'N' => 'Status Karyawan',
    'O' => 'TMT Kerja',
    'P' => 'TMT MBT',
    'Q' => 'TMT Pensiun',
    'R' => 'Tax ID',
    'S' => 'BPJS ID',
    'T' => 'Jamsostek ID',
    'U' => 'Nama Bank',
    'V' => 'No Rekening',
    'W' => 'Nama Pemilik Rek',
    'X' => 'No HP',
    'Y' => 'Agama',
    'Z' => 'NPWP'
];

// Style Header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0891B2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

// Write Headers
foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(25);
}

// Set Column Widths
$widths = [
    'A' => 15, 'B' => 15, 'C' => 25, 'D' => 18, 'E' => 8, 'F' => 18, 
    'G' => 14, 'H' => 10, 'I' => 12, 'J' => 14, 'K' => 20, 'L' => 20,
    'M' => 15, 'N' => 15, 'O' => 14, 'P' => 14, 'Q' => 14, 'R' => 15,
    'S' => 15, 'T' => 15, 'U' => 15, 'V' => 18, 'W' => 20, 'X' => 15,
    'Y' => 12, 'Z' => 20
];

foreach ($widths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Sample Data Row (Contoh)
$sampleData = [
    '2024001',              // A: ID SAP
    'P001',                 // B: Old Pers No
    'John Doe',             // C: Nama Lengkap
    '1234567890123456',     // D: NIK KTP
    'L',                    // E: Gender (L/P)
    'Jakarta',              // F: Tempat Lahir
    '1990-01-15',           // G: Tanggal Lahir (YYYY-MM-DD)
    '5A',                   // H: Grade
    'II/A',                 // I: Golongan PHDP
    'Menikah',              // J: Status Keluarga
    'Manager Operasional',  // K: Jabatan SAP
    'Manager Lapangan',     // L: Jabatan Real
    'Afdeling 1',           // M: Afdeling
    'Tetap',                // N: Status Karyawan
    '2015-06-01',           // O: TMT Kerja (YYYY-MM-DD)
    '2025-06-01',           // P: TMT MBT (YYYY-MM-DD)
    '2045-01-15',           // Q: TMT Pensiun (YYYY-MM-DD)
    'TAX001',               // R: Tax ID
    'BPJS001',              // S: BPJS ID
    'JMT001',               // T: Jamsostek ID
    'BCA',                  // U: Nama Bank
    '1234567890',           // V: No Rekening
    'John Doe',             // W: Nama Pemilik Rek
    '081234567890',         // X: No HP
    'Islam',                // Y: Agama
    '12.345.678.9-012.000'  // Z: NPWP
];

// Write Sample Data
$row = 2;
foreach ($sampleData as $idx => $value) {
    $col = chr(65 + $idx); // A=65
    if ($idx >= 26) $col = 'A' . chr(65 + ($idx - 26)); // Handle AA, AB, etc.
    $sheet->setCellValue($col . $row, $value);
}

// Add Instructions Sheet
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Panduan');
$sheet2->setCellValue('A1', 'PANDUAN IMPORT DATA KARYAWAN');
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$instructions = [
    '',
    'PETUNJUK PENGISIAN:',
    '1. Jangan mengubah urutan kolom atau menghapus header',
    '2. Kolom Gender: Isi dengan "L" untuk Laki-laki atau "P" untuk Perempuan',
    '3. Format Tanggal: YYYY-MM-DD (contoh: 2024-01-15)',
    '4. Status Karyawan: Tetap, Kontrak, PKWT, KARPIM, TS, KNG, HL',
    '5. Status Keluarga: Lajang, Menikah, Cerai',
    '6. ID SAP wajib diisi dan tidak boleh duplikat',
    '',
    'KOLOM WAJIB (Tidak boleh kosong):',
    '- ID SAP (Kolom A)',
    '- Nama Lengkap (Kolom C)',
    '- Status Karyawan (Kolom N)',
    '',
    'TIPS:',
    '- Gunakan format .xlsx atau .xls',
    '- Maksimal 1000 baris per file',
    '- Pastikan tidak ada baris kosong di tengah data',
    '- Hapus baris contoh (baris 2) sebelum mengisi data Anda',
    '',
    'Jika ada error saat import, periksa:',
    '1. Format tanggal sudah benar (YYYY-MM-DD)',
    '2. Gender hanya berisi L atau P',
    '3. Tidak ada karakter khusus yang tidak valid',
    '4. ID SAP tidak duplikat dengan data yang sudah ada'
];

$instructionRow = 1;
foreach ($instructions as $instruction) {
    $sheet2->setCellValue('A' . $instructionRow, $instruction);
    $instructionRow++;
}

$sheet2->getColumnDimension('A')->setWidth(70);

// Set Active Sheet to Template
$spreadsheet->setActiveSheetIndex(0);

// Output Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Template_Import_Karyawan_' . date('Ymd') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>