<?php
// admin/cetak/lm76_export_excel.php
// Excel export LM-76 — tema hijau, ikut filter ?unit_id=&bulan=&tahun=

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

/* helper cek kolom ada */
function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  $hasKebun = col_exists($pdo, 'lm76', 'kebun_id');

  // Filters
  $unit_id = (isset($_GET['unit_id']) && $_GET['unit_id']!=='') ? (int)$_GET['unit_id'] : null;
  $bulan   = (isset($_GET['bulan'])   && $_GET['bulan']  !=='') ? trim($_GET['bulan'])   : null;
  $tahun   = (isset($_GET['tahun'])   && $_GET['tahun']  !=='') ? (int)$_GET['tahun']   : null;

  // Query
  $selectK = $hasKebun ? ", k.nama_kebun" : "";
  $joinK   = $hasKebun ? " LEFT JOIN md_kebun k ON k.id = l.kebun_id " : "";

  $sql = "SELECT l.*, u.nama_unit $selectK
          FROM lm76 l
          LEFT JOIN units u ON u.id = l.unit_id
          $joinK
          WHERE 1=1";
  $bind = [];
  if ($unit_id !== null) { $sql .= " AND l.unit_id = :uid"; $bind[':uid'] = $unit_id; }
  if ($bulan   !== null) { $sql .= " AND l.bulan   = :bln"; $bind[':bln'] = $bulan; }
  if ($tahun   !== null) { $sql .= " AND l.tahun   = :thn"; $bind[':thn'] = $tahun; }

  $sql .= " ORDER BY l.tahun DESC,
            FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            u.nama_unit ASC, l.blok ASC";

  $st = $pdo->prepare($sql); $st->execute($bind);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  http_response_code(500);
  exit('DB Error: '.$e->getMessage());
}

// ==== Spreadsheet ====
$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$addr = fn(int $c, int $r) => Coordinate::stringFromColumnIndex($c) . $r;

// Header list (dinamis: kebun opsional)
$headers = [];
if ($hasKebun) $headers[] = 'Kebun';
$headers = array_merge($headers, [
  'Unit','Periode','Blok','Luas (Ha)','Jml Pohon',
  'Prod BI (Real/Angg)','Prod SD (Real/Angg)',
  'Jml Tandan (BI)','PSTB (BI/TL)','Panen HK',
  'Panen Ha (BI/SD)','Freq (BI/SD)'
]);

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
$sheet->setCellValue($addr(1,2), 'LM-76 — Statistik Panen Kelapa Sawit');
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
    if ($hasKebun) $sheet->setCellValue($addr($c++, $r), $x['nama_kebun'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['nama_unit'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), trim(($x['bulan'] ?? '').' '.($x['tahun'] ?? '')));
    $sheet->setCellValue($addr($c++, $r), $x['blok'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), is_null($x['luas_ha']) ? null : (float)$x['luas_ha']);
    $sheet->setCellValue($addr($c++, $r), is_null($x['jumlah_pohon']) ? null : (float)$x['jumlah_pohon']);
    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['prod_bi_realisasi'] ?? 0),2).'/'.number_format((float)($x['prod_bi_anggaran'] ?? 0),2));
    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['prod_sd_realisasi'] ?? 0),2).'/'.number_format((float)($x['prod_sd_anggaran'] ?? 0),2));
    $sheet->setCellValue($addr($c++, $r), is_null($x['jumlah_tandan_bi']) ? null : (float)$x['jumlah_tandan_bi']);
    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['pstb_ton_ha_bi'] ?? 0),2).'/'.number_format((float)($x['pstb_ton_ha_tl'] ?? 0),2));
    $sheet->setCellValue($addr($c++, $r), is_null($x['panen_hk_realisasi']) ? null : (float)$x['panen_hk_realisasi']);
    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['panen_ha_bi'] ?? 0),2).'/'.number_format((float)($x['panen_ha_sd'] ?? 0),2));
    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['frek_panen_bi'] ?? 0),0).'/'.number_format((float)($x['frek_panen_sd'] ?? 0),0));
    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
  $sheet->mergeCells($addr(1,$r).':'.$lastCol.$r);
}

// Format angka & border area data
$endRow = max($r-1, $rowStart);
if ($endRow >= $rowStart+1) {
  // Luas (Ha)
  $sheet->getStyle($addr($hasKebun?5:4,$rowStart+1).':'.$addr($hasKebun?5:4,$endRow))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr($hasKebun?5:4,$rowStart+1).':'.$addr($hasKebun?5:4,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  // Jml Pohon
  $sheet->getStyle($addr($hasKebun?6:5,$rowStart+1).':'.$addr($hasKebun?6:5,$endRow))->getNumberFormat()->setFormatCode('0');
  $sheet->getStyle($addr($hasKebun?6:5,$rowStart+1).':'.$addr($hasKebun?6:5,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  // Jml Tandan BI
  $colJmlTandan = $hasKebun ? 9 : 8;
  $sheet->getStyle($addr($colJmlTandan,$rowStart+1).':'.$addr($colJmlTandan,$endRow))->getNumberFormat()->setFormatCode('0');
  $sheet->getStyle($addr($colJmlTandan,$rowStart+1).':'.$addr($colJmlTandan,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  // Panen HK
  $colPanenHK = $hasKebun ? 11 : 10;
  $sheet->getStyle($addr($colPanenHK,$rowStart+1).':'.$addr($colPanenHK,$endRow))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr($colPanenHK,$rowStart+1).':'.$addr($colPanenHK,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
$rangeAll = $addr(1,$rowStart).':'.$lastCol.$endRow;
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->getColor()->setARGB('FFE5E7EB');

// Autosize
for ($i=1; $i<=$lastColIdx; $i++){
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Output
$fname = 'lm76_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
