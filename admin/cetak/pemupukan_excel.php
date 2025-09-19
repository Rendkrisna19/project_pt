<?php
// cetak/pemupukan_excel.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  http_response_code(401);
  exit('Unauthorized');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$printedBy = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'User';
$printedAt = date('d/m/Y H:i');

$tab = $_GET['tab'] ?? 'menabur';
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

$db = new Database();
$conn = $db->getConnection();

if ($tab === 'angkutan') {
  $reportTitle = 'Data Angkutan Pupuk Kimia';
  $sql = "SELECT a.tanggal, a.gudang_asal, u.nama_unit AS unit_tujuan, a.jenis_pupuk,
                 a.jumlah, a.nomor_do, a.supir
          FROM angkutan_pupuk a
          LEFT JOIN units u ON u.id = a.unit_tujuan_id
          ORDER BY a.tanggal DESC, a.id DESC";
  $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  $headers = ['Tanggal','Gudang Asal','Unit Tujuan','Jenis Pupuk','Jumlah (Kg)','Nomor DO','Supir'];
  $numericColsDecimal = [5];     // kolom index (1-based) yang 2 desimal
  $numericColsInteger = [];      // kolom index integer
  $mapRow = function($r){
    return [
      $r['tanggal'],
      $r['gudang_asal'],
      $r['unit_tujuan'] ?? '-',
      $r['jenis_pupuk'],
      (float)$r['jumlah'],
      $r['nomor_do'],
      $r['supir'],
    ];
  };
} else {
  $reportTitle = 'Data Penaburan Pupuk Kimia';
  $sql = "SELECT m.tanggal, u.nama_unit AS unit, m.blok, m.jenis_pupuk,
                 m.jumlah, m.luas, m.invt_pokok, m.catatan
          FROM menabur_pupuk m
          LEFT JOIN units u ON u.id = m.unit_id
          ORDER BY m.tanggal DESC, m.id DESC";
  $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  $headers = ['Tanggal','Unit','Blok','Jenis Pupuk','Jumlah (Kg)','Luas (Ha)','Invt. Pokok','Catatan'];
  $numericColsDecimal = [5,6];
  $numericColsInteger = [7];
  $mapRow = function($r){
    return [
      $r['tanggal'],
      $r['unit'] ?? '-',
      $r['blok'],
      $r['jenis_pupuk'],
      (float)$r['jumlah'],
      (float)$r['luas'],
      (int)$r['invt_pokok'],
      $r['catatan'],
    ];
  };
}

$company = 'PTPN 4 Regional 2';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// helper: set cell by numeric index
$set = function($colIndex, $rowIndex, $value) use ($sheet){
  $col = Coordinate::stringFromColumnIndex($colIndex);
  $sheet->setCellValue($col.$rowIndex, $value);
};

// layout rows
$rowTitle1 = 1; // company
$rowTitle2 = 2; // report title
$rowInfo1  = 3; // printed by
$rowInfo2  = 4; // printed at
$rowHead   = 6; // table header
$rowData   = $rowHead + 1;

$colCount = count($headers);
$lastCol  = Coordinate::stringFromColumnIndex($colCount);

// Company (judul hijau)
$sheet->mergeCells("A{$rowTitle1}:{$lastCol}{$rowTitle1}");
$sheet->setCellValue("A{$rowTitle1}", $company);
$sheet->getStyle("A{$rowTitle1}")->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle("A{$rowTitle1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$rowTitle1}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A'); // green

// Report title (strip hijau muda)
$sheet->mergeCells("A{$rowTitle2}:{$lastCol}{$rowTitle2}");
$sheet->setCellValue("A{$rowTitle2}", $reportTitle);
$sheet->getStyle("A{$rowTitle2}")->getFont()->setBold(true)->setSize(12);
$sheet->getStyle("A{$rowTitle2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$rowTitle2}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBBF7D0'); // green-100

// Info baris
$sheet->setCellValue("A{$rowInfo1}", "Dicetak oleh : {$printedBy}");
$sheet->setCellValue("A{$rowInfo2}", "Tanggal cetak : {$printedAt}");

// Header tabel
for ($c=1; $c<=$colCount; $c++){
  $set($c, $rowHead, $headers[$c-1]);
}
$sheet->getStyle("A{$rowHead}:{$lastCol}{$rowHead}")->getFont()->setBold(true);
$sheet->getStyle("A{$rowHead}:{$lastCol}{$rowHead}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$rowHead}:{$lastCol}{$rowHead}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFEFEF');

// Data
$r = $rowData;
foreach ($rows as $row){
  $vals = $mapRow($row);
  for ($c=1; $c<=$colCount; $c++){
    $set($c, $r, $vals[$c-1]);
  }
  $r++;
}
$lastRow = max($r-1, $rowHead);

// Format angka
foreach ($numericColsDecimal as $c){
  $col = Coordinate::stringFromColumnIndex($c);
  $sheet->getStyle("{$col}{$rowData}:{$col}{$lastRow}")
        ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle("{$col}{$rowData}:{$col}{$lastRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
foreach ($numericColsInteger as $c){
  $col = Coordinate::stringFromColumnIndex($c);
  $sheet->getStyle("{$col}{$rowData}:{$col}{$lastRow}")
        ->getNumberFormat()->setFormatCode('#,##0');
  $sheet->getStyle("{$col}{$rowData}:{$col}{$lastRow}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

// Borders & autofilter & freeze
$sheet->getStyle("A{$rowHead}:{$lastCol}{$lastRow}")
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->setAutoFilter("A{$rowHead}:{$lastCol}{$lastRow}");
$sheet->freezePane("A".($rowData));

// Auto width
for ($c=1; $c<=$colCount; $c++){
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
}

$sheet->setTitle(substr($reportTitle,0,31));

$filename = ($tab==='angkutan' ? 'angkutan' : 'menabur') . '_pupuk_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
