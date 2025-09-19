<?php
// admin/cetak/lm_biaya_excel.php
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$db = new Database();
$conn = $db->getConnection();

$sql = "
  SELECT b.*,
         u.nama_unit,
         a.kode AS kode_aktivitas, a.nama AS nama_aktivitas,
         j.nama AS nama_jenis,
         (b.realisasi_bi - b.rencana_bi) AS diff_bi,
         CASE WHEN b.rencana_bi = 0 THEN NULL
              ELSE ((b.realisasi_bi / b.rencana_bi) - 1) * 100 END AS diff_pct
  FROM lm_biaya b
  LEFT JOIN units u ON u.id = b.unit_id
  LEFT JOIN md_kode_aktivitas a ON a.id = b.kode_aktivitas_id
  LEFT JOIN md_jenis_pekerjaan j ON j.id = b.jenis_pekerjaan_id
  ORDER BY b.tahun DESC,
           FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
           b.id DESC
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$username = $_SESSION['username'] ?? 'Unknown User';
$now = date('d/m/Y H:i');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$lastCol = 'I'; // A..I
// Judul hijau
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'PTPN 4 REGIONAL 2');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E7D32');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells("A2:{$lastCol}2");
$sheet->setCellValue('A2', 'LM Biaya');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF388E3C');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells("A3:{$lastCol}3");
$sheet->setCellValue('A3', "Diekspor oleh: {$username} pada {$now}");
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);

// Header
$headers = [
  'Kode Aktivitas','Jenis Pekerjaan','Bulan','Tahun','Unit/Divisi',
  'Rencana Bulan Ini','Realisasi Bulan Ini','+/- Biaya','+/- %'
];
$startRow = 5;
$col = 'A';
foreach ($headers as $h) {
  $sheet->setCellValue($col.$startRow, $h);
  $sheet->getStyle($col.$startRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($col.$startRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($col.$startRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E7D32');
  $sheet->getStyle($col.$startRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $col++;
}

// Data
$r = $startRow + 1;
foreach ($rows as $row) {
  $sheet->setCellValue("A{$r}", trim(($row['kode_aktivitas']??'').' - '.($row['nama_aktivitas']??'')));
  $sheet->setCellValue("B{$r}", $row['nama_jenis'] ?? '-');
  $sheet->setCellValue("C{$r}", $row['bulan']);
  $sheet->setCellValue("D{$r}", (int)$row['tahun']);
  $sheet->setCellValue("E{$r}", $row['nama_unit'] ?? '-');
  $sheet->setCellValue("F{$r}", (float)$row['rencana_bi']);
  $sheet->setCellValue("G{$r}", (float)$row['realisasi_bi']);
  $sheet->setCellValue("H{$r}", (float)$row['diff_bi']);
  $sheet->setCellValue("I{$r}", is_null($row['diff_pct']) ? null : (float)$row['diff_pct']/100); // gunakan format %

  // Border & align
  foreach (range('A','I') as $c) {
    $sheet->getStyle($c.$r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  }
  $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle("F{$r}:H{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
  $sheet->getStyle("I{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

  $r++;
}

// Autosize
foreach (range('A','I') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

$filename = 'LM_Biaya_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
