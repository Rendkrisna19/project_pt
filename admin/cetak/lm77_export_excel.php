<?php
// admin/cetak/lm77_export_excel.php
// Excel export LM-77 — tema hijau, ikut filter

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

  $hasKebunId   = col_exists($pdo,'lm77','kebun_id');
  $hasKebunKode = col_exists($pdo,'lm77','kebun_kode');

  // Filters
  $unit_id = (isset($_GET['unit_id']) && $_GET['unit_id']!=='') ? (int)$_GET['unit_id'] : null;
  $bulan   = (isset($_GET['bulan'])   && $_GET['bulan']  !=='') ? trim($_GET['bulan'])   : null;
  $tahun   = (isset($_GET['tahun'])   && $_GET['tahun']  !=='') ? (int)$_GET['tahun']   : null;
  $kb_kode = (isset($_GET['kebun_kode']) && $_GET['kebun_kode']!=='') ? trim($_GET['kebun_kode']) : null;

  // Query
  $selectK = '';
  $joinK   = '';
  if ($hasKebunId)   { $selectK = ", kb.nama_kebun, kb.kode AS kebun_kode"; $joinK=" LEFT JOIN md_kebun kb ON kb.id   = l.kebun_id "; }
  elseif ($hasKebunKode) { $selectK = ", kb.nama_kebun, kb.kode AS kebun_kode"; $joinK=" LEFT JOIN md_kebun kb ON kb.kode = l.kebun_kode "; }

  $sql = "SELECT l.*, u.nama_unit $selectK
          FROM lm77 l
          LEFT JOIN units u ON u.id = l.unit_id
          $joinK
          WHERE 1=1";
  $bind = [];
  if ($unit_id !== null) { $sql .= " AND l.unit_id = :uid"; $bind[':uid'] = $unit_id; }
  if ($bulan   !== null) { $sql .= " AND l.bulan   = :bln"; $bind[':bln'] = $bulan; }
  if ($tahun   !== null) { $sql .= " AND l.tahun   = :thn"; $bind[':thn'] = $tahun; }
  if ($kb_kode !== null) {
    if     ($hasKebunKode) $sql .= " AND l.kebun_kode = :kb";
    elseif ($hasKebunId)   $sql .= " AND kb.kode = :kb";
    else                   $sql .= " AND 1=0";
    $bind[':kb'] = $kb_kode;
  }

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

// Header titles
$headers = [
  'Kebun','Unit','Periode','Blok','Luas','Pohon',
  'Var % (BI/SD)','Tandan/Pohon (BI/SD)',
  'Prod Ton/Ha (BI/SD THI/TL)','BTR (BI/SD THI/TL)',
  'Basis (Kg/HK)','Prestasi Kg/HK (BI/SD)','Prestasi Tandan/HK (BI/SD)'
];
$lastColIdx = count($headers);
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

// Brand header (green)
$sheet->setCellValue($addr(1,1), 'PTPN 4 REGIONAL 3');
$sheet->mergeCells($addr(1,1).':'.$lastCol.'1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF22C55E');

// Subtitle
$sheet->setCellValue($addr(1,2), 'LM-77 — Statistik Panen (Rekap)');
$sheet->mergeCells($addr(1,2).':'.$lastCol.'2');
$sheet->getStyle($addr(1,2))->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Table header
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
    // Kebun (nama (kode))
    $kebunLabel = ($x['nama_kebun'] ?? '');
    if (isset($x['kebun_kode']) && $x['kebun_kode']!=='') $kebunLabel .= ($kebunLabel!==''?' ':'').'('.$x['kebun_kode'].')';
    $sheet->setCellValue($addr($c++, $r), $kebunLabel!=='' ? $kebunLabel : '-');

    $sheet->setCellValue($addr($c++, $r), $x['nama_unit'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), trim(($x['bulan'] ?? '').' '.($x['tahun'] ?? '')));
    $sheet->setCellValue($addr($c++, $r), $x['blok'] ?? '-');

    $sheet->setCellValue($addr($c++, $r), is_null($x['luas_ha']) ? null : (float)$x['luas_ha']);
    $sheet->setCellValue($addr($c++, $r), is_null($x['jumlah_pohon']) ? null : (float)$x['jumlah_pohon']);

    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['var_prod_bi'] ?? 0),2).'%/'.number_format((float)($x['var_prod_sd'] ?? 0),2).'%');
    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['jtandan_per_pohon_bi'] ?? 0),4).'/'.number_format((float)($x['jtandan_per_pohon_sd'] ?? 0),4));

    $sheet->setCellValue($addr($c++, $r),
      number_format((float)($x['prod_tonha_bi'] ?? 0),2).'/'.number_format((float)($x['prod_tonha_sd_thi'] ?? 0),2).'/'.number_format((float)($x['prod_tonha_sd_tl'] ?? 0),2));

    $sheet->setCellValue($addr($c++, $r),
      number_format((float)($x['btr_bi'] ?? 0),2).'/'.number_format((float)($x['btr_sd_thi'] ?? 0),2).'/'.number_format((float)($x['btr_sd_tl'] ?? 0),2));

    $sheet->setCellValue($addr($c++, $r), is_null($x['basis_borong_kg_hk']) ? null : (float)$x['basis_borong_kg_hk']);

    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['prestasi_kg_hk_bi'] ?? 0),2).'/'.number_format((float)($x['prestasi_kg_hk_sd'] ?? 0),2));
    $sheet->setCellValue($addr($c++, $r), number_format((float)($x['prestasi_tandan_hk_bi'] ?? 0),2).'/'.number_format((float)($x['prestasi_tandan_hk_sd'] ?? 0),2));
    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
  $sheet->mergeCells($addr(1,$r).':'.$lastCol.$r);
}

// Number formats & alignment
$endRow = max($r-1, $rowStart);
if ($endRow >= $rowStart+1) {
  // Luas
  $sheet->getStyle($addr(5,$rowStart+1).':'.$addr(5,$endRow))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr(5,$rowStart+1).':'.$addr(5,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  // Pohon
  $sheet->getStyle($addr(6,$rowStart+1).':'.$addr(6,$endRow))->getNumberFormat()->setFormatCode('0');
  $sheet->getStyle($addr(6,$rowStart+1).':'.$addr(6,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  // Basis
  $sheet->getStyle($addr(11,$rowStart+1).':'.$addr(11,$endRow))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr(11,$rowStart+1).':'.$addr(11,$endRow))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
$rangeAll = $addr(1,$rowStart).':'.$lastCol.$endRow;
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->getColor()->setARGB('FFE5E7EB');

// Autosize
for ($i=1; $i<=$lastColIdx; $i++){
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Output
$fname = 'lm77_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
