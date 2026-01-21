<?php
// cetak/template_tanggungan.php
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Tanggungan');

$headers = [
    'A' => 'SAP ID Karyawan (Wajib)',
    'B' => 'Nama Anggota Keluarga',
    'C' => 'Hubungan (Istri/Suami/Anak)',
    'D' => 'Tempat Lahir',
    'E' => 'Tanggal Lahir (YYYY-MM-DD)',
    'F' => 'Pendidikan Terakhir',
    'G' => 'Pekerjaan',
    'H' => 'Keterangan'
];

// Style Header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8E44AD']], // Purple
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];

foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Sample Data
$sheet->setCellValue('A2', '2024001');
$sheet->setCellValue('B2', 'Siti Aminah');
$sheet->setCellValue('C2', 'Istri');
$sheet->setCellValue('D2', 'Medan');
$sheet->setCellValue('E2', '1995-05-20');
$sheet->setCellValue('F2', 'S1');
$sheet->setCellValue('G2', 'Ibu Rumah Tangga');
$sheet->setCellValue('H2', '-');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Template_Import_Tanggungan.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>