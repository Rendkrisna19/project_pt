<?php
// cetak/template_karyawan.php
// FULL FIXED VERSION: Mencegah Error 500 & Update Kolom Baru

// 1. Matikan display error agar tidak merusak file excel jika ada warning kecil
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. Mulai buffer output
ob_start();

// Sesuaikan path ini dengan struktur folder Anda
// Asumsi file ini ada di: /pages/cetak/template_karyawan.php
// Maka vendor ada di: /vendor/autoload.php (naik 2 level)
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Buat Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Karyawan');

// ---------------------------------------------------------
// A. DEFINISI HEADER (Sesuai Database & Logic Import Baru)
// ---------------------------------------------------------
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
    'Z' => 'Status Tax',       // UPDATE: Sebelumnya NPWP
    'AA'=> 'Pendidikan Akhir', // NEW
    'AB'=> 'Jurusan',          // NEW
    'AC'=> 'Institusi'         // NEW
];

// Style untuk Header
$headerStyle = [
    'font' => [
        'bold' => true, 
        'color' => ['rgb' => 'FFFFFF'], 
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID, 
        'startColor' => ['rgb' => '0891B2'] // Warna Cyan PTPN
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER, 
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
];

// Tulis Header
foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set Tinggi Baris Header
$sheet->getRowDimension(1)->setRowHeight(25);

// ---------------------------------------------------------
// B. DATA CONTOH (SAMPLE ROW)
// ---------------------------------------------------------
$sampleData = [
    '2024001',              // A: ID SAP
    'P001',                 // B: Old Pers No
    'John Doe',             // C: Nama Lengkap
    '1234567890123456',     // D: NIK KTP
    'L',                    // E: Gender (L/P)
    'Medan',                // F: Tempat Lahir
    '1990-01-15',           // G: Tanggal Lahir (YYYY-MM-DD)
    '5A',                   // H: Grade
    'II/A',                 // I: Golongan PHDP
    'Menikah',              // J: Status Keluarga
    'Manager Operasional',  // K: Jabatan SAP
    'Manager Lapangan',     // L: Jabatan Real
    'Afdeling I',           // M: Afdeling
    'Tetap',                // N: Status Karyawan
    '2015-06-01',           // O: TMT Kerja
    '2025-06-01',           // P: TMT MBT
    '2045-01-15',           // Q: TMT Pensiun
    'TAX001',               // R: Tax ID
    'BPJS001',              // S: BPJS ID
    'JMT001',               // T: Jamsostek ID
    'BCA',                  // U: Nama Bank
    '1234567890',           // V: No Rekening
    'John Doe',             // W: Nama Pemilik Rek
    '081234567890',         // X: No HP
    'Islam',                // Y: Agama
    'K/0',                  // Z: Status Tax (Updated)
    'S1',                   // AA: Pendidikan
    'Agroteknologi',        // AB: Jurusan
    'USU Medan'             // AC: Institusi
];

// Tulis Data Contoh
$row = 2;
foreach ($sampleData as $idx => $value) {
    // Logic untuk konversi index 0->A, 26->AA, 27->AB, dst.
    $col = '';
    if ($idx < 26) {
        $col = chr(65 + $idx);
    } else {
        // Handle kolom AA, AB, AC
        $col = 'A' . chr(65 + ($idx - 26)); 
    }
    
    // Tulis cell sebagai string agar format tanggal/angka tidak berubah otomatis
    $sheet->setCellValueExplicit($col . $row, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
}

// ---------------------------------------------------------
// C. SHEET PANDUAN (Instruction Sheet)
// ---------------------------------------------------------
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Panduan');
$sheet2->setCellValue('A1', 'PANDUAN IMPORT DATA KARYAWAN');
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$instructions = [
    '',
    'PETUNJUK PENGISIAN:',
    '1. Jangan mengubah urutan kolom atau menghapus header di Sheet "Template Karyawan".',
    '2. Kolom Gender: Isi dengan "L" untuk Laki-laki atau "P" untuk Perempuan.',
    '3. Format Tanggal wajib: YYYY-MM-DD (contoh: 2024-01-15). Gunakan format Text jika Excel otomatis mengubahnya.',
    '4. Status Tax (Kolom Z): Isi dengan kode PTKP, contoh: K/0, TK/0, K/1.',
    '5. ID SAP (Kolom A) wajib diisi dan harus unik.',
    '',
    'TIPS MENGHINDARI ERROR:',
    '- Pastikan tidak ada baris kosong di tengah data.',
    '- Hapus baris contoh (baris 2) sebelum mengisi data asli Anda.',
    '- Simpan file tetap dalam format .xlsx'
];

$instructionRow = 2;
foreach ($instructions as $instruction) {
    $sheet2->setCellValue('A' . $instructionRow, $instruction);
    $instructionRow++;
}
$sheet2->getColumnDimension('A')->setWidth(80);

// Set Sheet 1 (Template) sebagai aktif saat dibuka
$spreadsheet->setActiveSheetIndex(0);

// ---------------------------------------------------------
// D. OUTPUT FILE (PENTING UNTUK FIX ERROR 500)
// ---------------------------------------------------------

// Bersihkan buffer output sebelum mengirim header
// Ini kunci untuk mengatasi "The file is corrupted" atau Error 500
ob_end_clean(); 

$filename = 'Template_Import_Karyawan_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); // Fix untuk IE/Edge
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); 
header('Cache-Control: cache, must-revalidate'); 
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>