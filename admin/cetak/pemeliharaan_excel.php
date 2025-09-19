<?php
// pages/cetak/pemeliharaan_excel.php
// Output: XLSX daftar pemeliharaan berdasarkan ?tab=TU|TBM|TM|BIBIT_PN|BIBIT_MN
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$tab = $_GET['tab'] ?? 'TU';
$allowed = ['TU','TBM','TM','BIBIT_PN','BIBIT_MN'];
if (!in_array($tab, $allowed, true)) $tab = 'TU';

$db = new Database(); $pdo = $db->getConnection();

$sql = "SELECT p.*, u.nama_unit AS unit_nama
        FROM pemeliharaan p
        LEFT JOIN units u ON u.id=p.unit_id
        WHERE p.kategori=:k
        ORDER BY p.tanggal DESC, p.id DESC";
$st = $pdo->prepare($sql);
$st->execute([':k'=>$tab]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$mapJudul = [
  'TU'=>'Pemeliharaan TU','TBM'=>'Pemeliharaan TBM','TM'=>'Pemeliharaan TM',
  'BIBIT_PN'=>'Pemeliharaan Bibit PN','BIBIT_MN'=>'Pemeliharaan Bibit MN'
];
$judul = $mapJudul[$tab] ?? 'Pemeliharaan';

$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Pemeliharaan');

$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', $judul . ' - Export ' . date('d/m/Y H:i'));
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header
$header = ['No','Jenis Pekerjaan','Unit/Devisi','Rayon','Periode','Rencana','Realisasi','Progress (%)','Status'];
$sheet->fromArray($header, null, 'A3');
$sheet->getStyle('A3:I3')->getFont()->setBold(true);
$sheet->getStyle('A3:I3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2F5EA');
$sheet->getRowDimension(3)->setRowHeight(22);

// Body
$r = 4; $no = 1;
foreach ($rows as $row) {
  $rencana  = (float)($row['rencana'] ?? 0);
  $realisasi= (float)($row['realisasi'] ?? 0);
  $progress = $rencana > 0 ? ($realisasi/$rencana)*100 : 0;
  $periode  = trim(($row['bulan'] ?? '').' '.($row['tahun'] ?? ''));

  $sheet->setCellValue("A{$r}", $no++);
  $sheet->setCellValue("B{$r}", $row['jenis_pekerjaan']);
  $sheet->setCellValue("C{$r}", $row['unit_nama'] ?: $row['afdeling']);
  $sheet->setCellValue("D{$r}", $row['rayon']);
  $sheet->setCellValue("E{$r}", $periode);
  $sheet->setCellValueExplicit("F{$r}", $rencana, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  $sheet->setCellValueExplicit("G{$r}", $realisasi, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  $sheet->setCellValueExplicit("H{$r}", round($progress,2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  $sheet->setCellValue("I{$r}", $row['status']);
  $r++;
}

// Format angka
$sheet->getStyle("F4:H{$r}")->getNumberFormat()->setFormatCode('#,##0.00');

// Border & auto width
$sheet->getStyle("A3:I".($r-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach (range('A','I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

// Output
$file = 'Pemeliharaan_'.$tab.'_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"{$file}\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
