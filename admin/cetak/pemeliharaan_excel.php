<?php
// pages/cetak/pemeliharaan_excel.php
// Output: Excel (.xlsx) daftar pemeliharaan mengikuti filter ?tab= & ?unit_id=
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$db = new Database(); 
$pdo = $db->getConnection();

$allowedTab = ['TU','TBM','TM','BIBIT_PN','BIBIT_MN'];
$daftar_tab = [
  'TU'=>'Pemeliharaan TU',
  'TBM'=>'Pemeliharaan TBM',
  'TM'=>'Pemeliharaan TM',
  'BIBIT_PN'=>'Pemeliharaan Bibit PN',
  'BIBIT_MN'=>'Pemeliharaan Bibit MN'
];

$tab = $_GET['tab'] ?? 'TU';
if (!in_array($tab, $allowedTab, true)) $tab = 'TU';

$f_unit_id = isset($_GET['unit_id']) ? (($_GET['unit_id']==='') ? '' : (int)$_GET['unit_id']) : '';

$sql = "SELECT p.*, u.nama_unit AS unit_nama
        FROM pemeliharaan p
        LEFT JOIN units u ON u.id = p.unit_id
        WHERE p.kategori = :k";
$params = [':k'=>$tab];
if ($f_unit_id !== '' && $f_unit_id !== null) { 
  $sql .= " AND p.unit_id = :unit_id"; 
  $params[':unit_id'] = (int)$f_unit_id; 
}
$sql .= " ORDER BY p.tanggal DESC, p.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// === TOTALS (sesuai filter) ===
$tot_rencana = 0.0; $tot_realisasi = 0.0;
foreach ($rows as $r) {
  $tot_rencana   += (float)($r['rencana'] ?? 0);
  $tot_realisasi += (float)($r['realisasi'] ?? 0);
}
$tot_progress = $tot_rencana > 0 ? ($tot_realisasi / $tot_rencana) * 100 : 0.0;

// Spreadsheet setup
$sheetTitle = $daftar_tab[$tab] ?? 'Pemeliharaan';
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr($sheetTitle,0,31));

// Header brand (merge + hijau)
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', 'PTPN IV REGIONAL 3');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F7B4F');

// Subjudul
$sheet->mergeCells('A2:I2');
$sheet->setCellValue('A2', $sheetTitle);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('0F7B4F');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// (Opsional) baris filter ringkas
$filterLine = 'Filter: Kategori '.$tab.' | '.(($f_unit_id!=='')?('Unit '.$f_unit_id):'Semua Unit');
$sheet->mergeCells('A3:I3');
$sheet->setCellValue('A3', $filterLine);
$sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('666666');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header tabel
$headers = [
  'A' => 'Jenis Pekerjaan',
  'B' => 'Tenaga',
  'C' => 'Unit/Devisi',
  'D' => 'Kebun',
  'E' => 'Periode',
  'F' => 'Rencana',
  'G' => 'Realisasi',
  'H' => 'Progress (%)',
  'I' => 'Status',
];

$rowIdx = 5;
foreach ($headers as $col => $title) {
  $sheet->setCellValue($col.$rowIdx, $title);
}
$sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getFont()->setBold(true);
$sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4EF');
$sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$rowIdx++;

// Body
if (empty($rows)) {
  $sheet->mergeCells("A{$rowIdx}:I{$rowIdx}");
  $sheet->setCellValue("A{$rowIdx}", 'Tidak ada data.');
  $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $rowIdx++;
} else {
  foreach ($rows as $r) {
    $rencana   = (float)($r['rencana'] ?? 0);
    $realisasi = (float)($r['realisasi'] ?? 0);
    $progress  = $rencana > 0 ? ($realisasi/$rencana)*100 : 0;

    $sheet->setCellValue("A{$rowIdx}", (string)($r['jenis_pekerjaan'] ?? ''));
    $sheet->setCellValue("B{$rowIdx}", (string)($r['tenaga'] ?? ''));
    $sheet->setCellValue("C{$rowIdx}", (string)($r['unit_nama'] ?: ''));
    $sheet->setCellValue("D{$rowIdx}", (string)($r['rayon'] ?: ''));
    $sheet->setCellValue("E{$rowIdx}", trim(($r['bulan'] ?? '').' '.($r['tahun'] ?? '')));
    $sheet->setCellValue("F{$rowIdx}", $rencana);
    $sheet->setCellValue("G{$rowIdx}", $realisasi);
    $sheet->setCellValue("H{$rowIdx}", round($progress, 2));
    $sheet->setCellValue("I{$rowIdx}", (string)($r['status'] ?? ''));

    $sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("F{$rowIdx}:H{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $rowIdx++;
  }
}

// TOTAL row (jika ada data)
if (!empty($rows)) {
  $sheet->setCellValue("A{$rowIdx}", 'TOTAL');
  // merge TOTAL label across A..E
  $sheet->mergeCells("A{$rowIdx}:E{$rowIdx}");
  $sheet->setCellValue("F{$rowIdx}", $tot_rencana);
  $sheet->setCellValue("G{$rowIdx}", $tot_realisasi);
  $sheet->setCellValue("H{$rowIdx}", round($tot_progress, 2));
  $sheet->setCellValue("I{$rowIdx}", '');

  // style TOTAL row
  $sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getFont()->setBold(true);
  $sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1FAF6');
  $sheet->getStyle("A{$rowIdx}:I{$rowIdx}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $sheet->getStyle("F{$rowIdx}:H{$rowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $rowIdx++;
}

// Auto width
foreach (range('A','I') as $col) {
  $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Number formats for numeric columns
$sheet->getStyle("F6:F{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("G6:G{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("H6:H{$rowIdx}")->getNumberFormat()->setFormatCode('#,##0.00');

// Output
$fname = 'Pemeliharaan_'.$tab.(($f_unit_id!=='')?('_UNIT-'.$f_unit_id):'').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
