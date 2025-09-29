<?php
// admin/cetak/permintaan_export_excel.php
// Excel export Pengajuan AU-58 (Permintaan Bahan) - tema hijau
// Mendukung filter ?q=&unit_id=&kebun_id=&tgl_from=&tgl_to=

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function likeParam($s){ return '%'.str_replace(['%','_'], ['\\%','\\_'], trim($s)).'%'; }

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // Filters
  $q        = isset($_GET['q']) ? trim($_GET['q']) : '';
  $unit_id  = (isset($_GET['unit_id']) && $_GET['unit_id']!=='') ? (int)$_GET['unit_id'] : null;
  $kebun_id = (isset($_GET['kebun_id']) && $_GET['kebun_id']!=='') ? (int)$_GET['kebun_id'] : null;
  $tgl_from = isset($_GET['tgl_from']) && $_GET['tgl_from']!=='' ? $_GET['tgl_from'] : null;
  $tgl_to   = isset($_GET['tgl_to'])   && $_GET['tgl_to']  !=='' ? $_GET['tgl_to']   : null;

  $sql = "SELECT p.*, u.nama_unit, k.nama_kebun
          FROM permintaan_bahan p
          LEFT JOIN units u    ON u.id = p.unit_id
          LEFT JOIN md_kebun k ON k.id = p.kebun_id
          WHERE 1=1";
  $bind = [];
  if ($q !== '') {
    $sql .= " AND (p.no_dokumen LIKE :q OR p.blok LIKE :q OR IFNULL(p.keterangan,'') LIKE :q)";
    $bind[':q'] = likeParam($q);
  }
  if ($unit_id !== null)  { $sql .= " AND p.unit_id  = :uid"; $bind[':uid'] = $unit_id; }
  if ($kebun_id !== null) { $sql .= " AND p.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }
  if ($tgl_from)          { $sql .= " AND p.tanggal >= :df";  $bind[':df']  = $tgl_from; }
  if ($tgl_to)            { $sql .= " AND p.tanggal <= :dt";  $bind[':dt']  = $tgl_to; }
  $sql .= " ORDER BY p.tanggal DESC, p.id DESC";

  $st = $pdo->prepare($sql);
  $st->execute($bind);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  http_response_code(500);
  exit('DB Error: '.$e->getMessage());
}

// ==== Spreadsheet ====
$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$addr = fn(int $c, int $r) => Coordinate::stringFromColumnIndex($c) . $r;

$headers = ['No. Dokumen','Kebun','Unit/Devisi','Tanggal','Blok','Pokok','Dosis/Norma','Jumlah Diminta','Keterangan'];
$lastColIdx = count($headers);
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

// Judul hijau
$sheet->setCellValue($addr(1,1), 'PTPN 4 REGIONAL 3');
$sheet->mergeCells($addr(1,1).':'.$lastCol.'1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF22C55E');

// Subjudul
$sheet->setCellValue($addr(1,2), 'Pengajuan AU-58 (Permintaan Bahan)');
$sheet->mergeCells($addr(1,2).':'.$lastCol.'2');
$sheet->getStyle($addr(1,2))->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header
$rowStart = 4;
for ($i=0; $i<$lastColIdx; $i++){
  $sheet->setCellValue($addr($i+1, $rowStart), $headers[$i]);
}
$rangeHeader = $addr(1,$rowStart).':'.$lastCol.$rowStart;
$sheet->getStyle($rangeHeader)->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
$sheet->getStyle($rangeHeader)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5');
$sheet->getStyle($rangeHeader)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($rangeHeader)->getBorders()->getAllBorders()->getColor()->setARGB('FFE5E7EB');

$sheet->freezePane($addr(1, $rowStart+1));

// Data
$r = $rowStart + 1;
if ($rows) {
  foreach ($rows as $x) {
    $c = 1;
    $sheet->setCellValue($addr($c++, $r), $x['no_dokumen'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['nama_kebun'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['nama_unit']  ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['tanggal']    ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['blok']       ?? '-');
    $sheet->setCellValue($addr($c++, $r), is_null($x['pokok']) ? null : (int)$x['pokok']);
    $sheet->setCellValue($addr($c++, $r), $x['dosis_norma'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), is_null($x['jumlah_diminta']) ? null : (float)$x['jumlah_diminta']);
    $sheet->setCellValue($addr($c++, $r), $x['keterangan'] ?? '-');
    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
  $sheet->mergeCells($addr(1,$r).':'.$lastCol.$r);
}

// Format angka & border area data
$endRow = max($r-1, $rowStart);
if ($endRow >= $rowStart+1) {
  // Pokok = kolom 6 (integer), Jumlah Diminta = kolom 8 (2 desimal)
  $sheet->getStyle($addr(6,$rowStart+1).':'.$addr(6,$endRow))->getNumberFormat()->setFormatCode('0');
  $sheet->getStyle($addr(8,$rowStart+1).':'.$addr(8,$endRow))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr(6,$rowStart+1).':'.$addr(6,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $sheet->getStyle($addr(8,$rowStart+1).':'.$addr(8,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
$rangeAll = $addr(1,$rowStart).':'.$lastCol.$endRow;
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->getColor()->setARGB('FFE5E7EB');

// Autosize
for ($i=1; $i<=$lastColIdx; $i++){
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Output
$fname = 'permintaan_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
