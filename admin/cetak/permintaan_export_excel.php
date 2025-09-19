<?php
// cetak/permintaan_export_excel.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Forbidden'); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$db = new Database();
$conn = $db->getConnection();

// Ambil data
$sql = "SELECT p.*, u.nama_unit
        FROM permintaan_bahan p
        JOIN units u ON u.id = p.unit_id
        ORDER BY p.tanggal DESC, p.id DESC";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Info export
$username = $_SESSION['username'] ?? 'Unknown User';
$now = date('d/m/Y H:i');
$company = 'PTPN 4 REGIONAL 2';
$title = 'Pengajuan AU-58 (Permintaan Bahan)';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Baris judul & subjudul
$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', $company);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E7D32');

$sheet->mergeCells('A2:H2');
$sheet->setCellValue('A2', $title);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF388E3C');

// Info user
$sheet->mergeCells('A3:H3');
$sheet->setCellValue('A3', "Diekspor oleh: $username pada $now");
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);

// Header tabel
$headers = ['No', 'No. Dokumen', 'Unit/Devisi', 'Tanggal', 'Blok', 'Pokok', 'Dosis/Norma', 'Jumlah Diminta', 'Keterangan'];
$startCol = 'A';
$headerRow = 5;
$cols = ['A','B','C','D','E','F','G','H','I'];

foreach ($headers as $idx=>$h) {
  $cell = $cols[$idx].$headerRow;
  $sheet->setCellValue($cell, $h);
  $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E7D32');
  $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// Data
$dataRow = $headerRow + 1;
$no = 0;
foreach ($rows as $r) {
  $no++;
  $sheet->setCellValue("A{$dataRow}", $no);
  $sheet->setCellValue("B{$dataRow}", $r['no_dokumen']);
  $sheet->setCellValue("C{$dataRow}", $r['nama_unit']);
  $sheet->setCellValue("D{$dataRow}", $r['tanggal']);
  $sheet->setCellValue("E{$dataRow}", $r['blok']);
  $sheet->setCellValue("F{$dataRow}", $r['pokok']);
  $sheet->setCellValue("G{$dataRow}", $r['dosis_norma']);
  $sheet->setCellValue("H{$dataRow}", (float)$r['jumlah_diminta']);
  $sheet->setCellValue("I{$dataRow}", $r['keterangan']);

  foreach ($cols as $cc) {
    $sheet->getStyle($cc.$dataRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    if (in_array($cc, ['A','H'])) {
      $sheet->getStyle($cc.$dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
  }
  $sheet->getStyle("A{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $dataRow++;
}

// Autosize kolom
foreach ($cols as $cc) $sheet->getColumnDimension($cc)->setAutoSize(true);

// Output
$filename = 'Permintaan_AU58_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
