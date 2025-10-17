<?php
// admin/cetak/lm76_export_excel.php
// Excel export LM-76 — tema hijau, ikut filter ?kebun_id=&unit_id=&bulan=&tahun=&tt=

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
  $kebun_id = (isset($_GET['kebun_id']) && $_GET['kebun_id']!=='') ? (int)$_GET['kebun_id'] : null;
  $unit_id  = (isset($_GET['unit_id'])  && $_GET['unit_id']  !=='') ? (int)$_GET['unit_id']  : null;
  $bulan    = (isset($_GET['bulan'])    && $_GET['bulan']    !=='') ? trim($_GET['bulan'])   : null;
  $tahun    = (isset($_GET['tahun'])    && $_GET['tahun']    !=='') ? (int)$_GET['tahun']   : null;
  $tt       = (isset($_GET['tt'])       && $_GET['tt']       !=='') ? trim($_GET['tt'])      : null;

  // Query
  $selectK = $hasKebun ? ", k.nama_kebun" : ", NULL AS nama_kebun";
  $joinK   = $hasKebun ? " LEFT JOIN md_kebun k ON k.id = l.kebun_id " : "";

  $sql = "SELECT l.*, u.nama_unit $selectK
          FROM lm76 l
          LEFT JOIN units u ON u.id = l.unit_id
          $joinK
          WHERE 1=1";
  $bind = [];
  if ($kebun_id !== null && $hasKebun) { $sql .= " AND l.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }
  if ($unit_id  !== null)              { $sql .= " AND l.unit_id  = :uid"; $bind[':uid'] = $unit_id; }
  if ($bulan    !== null)              { $sql .= " AND l.bulan    = :bln"; $bind[':bln'] = $bulan; }
  if ($tahun    !== null)              { $sql .= " AND l.tahun    = :thn"; $bind[':thn'] = $tahun; }
  if ($tt       !== null)              { $sql .= " AND l.tt       = :tt";  $bind[':tt']  = $tt; }

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

// Header list (fix 13 kolom)
$headers = [
  'Tahun','Kebun','Unit/Defisi','Periode','T. Tanam',
  'Luas (Ha)','Invt Pokok','Anggaran (Kg)','Realisasi (Kg)',
  'Jumlah Tandan','Jumlah HK','Panen (Ha)','Frekuensi'
];

$lastColIdx = count($headers);
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

// Judul hijau
$sheet->setCellValue($addr(1,1), 'PTPN 4 REGIONAL 3');
$sheet->mergeCells($addr(1,1).':'.$lastCol.'1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A');

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
$sheet->getStyle($rangeHeader)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($rangeHeader)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A');
$sheet->getStyle($rangeHeader)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');
$sheet->getStyle($rangeHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

$sheet->freezePane($addr(1, $rowStart+1));

// Data
$r = $rowStart + 1;
$sumLuas=0; $sumPokok=0; $sumAngg=0; $sumReal=0; $sumTandan=0; $sumHK=0; $sumPanenHa=0;

if ($rows) {
  foreach ($rows as $x) {
    $periode = trim(($x['bulan'] ?? '').' '.($x['tahun'] ?? ''));
    $luas    = (float)($x['luas_ha'] ?? 0);
    $panenHa = (float)($x['panen_ha_sd'] ?? 0); if ($panenHa == 0) $panenHa = (float)($x['panen_ha_bi'] ?? 0);
    $freq    = $luas > 0 ? $panenHa / $luas : 0;

    $c = 1;
    $sheet->setCellValue($addr($c++, $r), $x['tahun'] ?? '');
    $sheet->setCellValue($addr($c++, $r), $x['nama_kebun'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['nama_unit'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $periode);
    $sheet->setCellValue($addr($c++, $r), $x['tt'] ?? '');

    $sheet->setCellValue($addr($c++, $r), $luas);
    $sheet->setCellValue($addr($c++, $r), (float)($x['jumlah_pohon'] ?? 0));
    $sheet->setCellValue($addr($c++, $r), (float)($x['prod_sd_anggaran'] ?? 0));
    $sheet->setCellValue($addr($c++, $r), (float)($x['prod_sd_realisasi'] ?? 0));
    $sheet->setCellValue($addr($c++, $r), (float)($x['jumlah_tandan_bi'] ?? 0));
    $sheet->setCellValue($addr($c++, $r), (float)($x['panen_hk_realisasi'] ?? 0));
    $sheet->setCellValue($addr($c++, $r), $panenHa);
    $sheet->setCellValue($addr($c++, $r), $freq);

    // akumulasi
    $sumLuas    += $luas;
    $sumPokok   += (float)($x['jumlah_pohon'] ?? 0);
    $sumAngg    += (float)($x['prod_sd_anggaran'] ?? 0);
    $sumReal    += (float)($x['prod_sd_realisasi'] ?? 0);
    $sumTandan  += (float)($x['jumlah_tandan_bi'] ?? 0);
    $sumHK      += (float)($x['panen_hk_realisasi'] ?? 0);
    $sumPanenHa += $panenHa;

    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
  $sheet->mergeCells($addr(1,$r).':'.$lastCol.$r);
  $r++;
}

// Totals row
$totalFreq = $sumLuas > 0 ? ($sumPanenHa / $sumLuas) : 0;
$sheet->setCellValue($addr(1,$r), 'TOTAL');
$sheet->mergeCells($addr(1,$r).':'.$addr(5,$r));
$sheet->setCellValue($addr(6,$r),  $sumLuas);
$sheet->setCellValue($addr(7,$r),  $sumPokok);
$sheet->setCellValue($addr(8,$r),  $sumAngg);
$sheet->setCellValue($addr(9,$r),  $sumReal);
$sheet->setCellValue($addr(10,$r), $sumTandan);
$sheet->setCellValue($addr(11,$r), $sumHK);
$sheet->setCellValue($addr(12,$r), $sumPanenHa);
$sheet->setCellValue($addr(13,$r), $totalFreq);

// Format angka & border area data
$endRow = $r;
$rangeAll = $addr(1,$rowStart).':'.$lastCol.$endRow;
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');

// Format kolom numerik
$fmtRight = function($colIdx) use ($sheet, $rowStart, $endRow) {
  $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).($rowStart+1).':'.Coordinate::stringFromColumnIndex($colIdx).$endRow)
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
};
$fmtNum0  = function($colIdx) use ($sheet, $rowStart, $endRow) {
  $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).($rowStart+1).':'.Coordinate::stringFromColumnIndex($colIdx).$endRow)
        ->getNumberFormat()->setFormatCode('0');
};
$fmtNum2  = function($colIdx) use ($sheet, $rowStart, $endRow) {
  $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).($rowStart+1).':'.Coordinate::stringFromColumnIndex($colIdx).$endRow)
        ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
};

// Luas(6), Angg(8), Real(9), PanenHa(12), Frek(13) => 2 des
foreach ([6,8,9,12,13] as $c){ $fmtRight($c); $fmtNum2($c); }
// Invt Pokok(7), Jml Tandan(10) => 0 des
foreach ([7,10] as $c){ $fmtRight($c); $fmtNum0($c); }
// Jumlah HK(11) => 2 des
$fmtRight(11); $fmtNum2(11);

// Header & total styling
$sheet->getStyle($addr(1,$r).':'.$lastCol.$r)->getFont()->setBold(true);
$sheet->getStyle($addr(1,$r).':'.$lastCol.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5');
$sheet->getStyle($addr(1,$r).':'.$lastCol.$r)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF16A34A');

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
