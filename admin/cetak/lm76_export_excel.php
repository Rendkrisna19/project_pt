<?php
// admin/cetak/lm76_export_excel.php
// Excel export LM-76 — tema hijau, Fixed Table Name & Columns

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

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // Filters
  $kebun_id = (isset($_GET['kebun_id']) && $_GET['kebun_id']!=='') ? (int)$_GET['kebun_id'] : null;
  $unit_id  = (isset($_GET['unit_id'])  && $_GET['unit_id']  !=='') ? (int)$_GET['unit_id']  : null;
  $bulan    = (isset($_GET['bulan'])    && $_GET['bulan']    !=='') ? trim($_GET['bulan'])   : null;
  $tahun    = (isset($_GET['tahun'])    && $_GET['tahun']    !=='') ? (int)$_GET['tahun']    : null;
  $tt       = (isset($_GET['tt'])       && $_GET['tt']       !=='') ? trim($_GET['tt'])      : null;

  // Query Utama (FIX: Tabel lm76)
  $sql = "SELECT l.*, u.nama_unit, k.nama_kebun
          FROM lm76 l
          LEFT JOIN units u ON u.id = l.unit_id
          LEFT JOIN md_kebun k ON k.id = l.kebun_id
          WHERE 1=1";

  $bind = [];
  if ($kebun_id !== null) { $sql .= " AND l.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }
  if ($unit_id  !== null) { $sql .= " AND l.unit_id  = :uid"; $bind[':uid'] = $unit_id; }
  if ($bulan    !== null) { $sql .= " AND l.bulan    = :bln"; $bind[':bln'] = $bulan; }
  if ($tahun    !== null) { $sql .= " AND l.tahun    = :thn"; $bind[':thn'] = $tahun; }
  if ($tt       !== null) { $sql .= " AND l.tt       = :tt";  $bind[':tt']  = $tt; }

  // Sorting: Tahun, Urutan Bulan, Unit
  $sql .= " ORDER BY l.tahun DESC,
            FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            u.nama_unit ASC";

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

// Header list (13 kolom)
$headers = [
  'Tahun','Kebun','Unit/Defisi','Periode','T. Tanam',
  'Luas (Ha)','Invt Pokok','Anggaran (Kg)','Realisasi (Kg)',
  'Jumlah Tandan','Jumlah HK','Panen (Ha)','Frekuensi'
];

$lastColIdx = count($headers);
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

// 1. Judul Utama (Hijau)
$sheet->setCellValue($addr(1,1), 'PTPN 4 REGIONAL 3');
$sheet->mergeCells($addr(1,1).':'.$lastCol.'1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A');

// 2. Subjudul
$sheet->setCellValue($addr(1,2), 'LM-76 — Statistik Panen Kelapa Sawit');
$sheet->mergeCells($addr(1,2).':'.$lastCol.'2');
$sheet->getStyle($addr(1,2))->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 3. Table Header
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

// 4. Data Loop
$r = $rowStart + 1;
$sumLuas=0; $sumPokok=0; $sumAngg=0; $sumReal=0; $sumTandan=0; $sumHK=0; $sumPanenHa=0;

if ($rows) {
  foreach ($rows as $x) {
    $periode = trim(($x['bulan'] ?? '').' '.($x['tahun'] ?? ''));
    
    // FIX: Mapping kolom sesuai tabel lm76 yang benar
    $luas    = (float)($x['luas_ha'] ?? 0);
    $panenHa = (float)($x['panen_ha'] ?? 0); 
    $freq    = $luas > 0 ? $panenHa / $luas : 0;
    
    $pokok     = (float)($x['jumlah_pohon'] ?? 0);
    $anggaran  = (float)($x['anggaran_kg'] ?? 0);
    $realisasi = (float)($x['realisasi_kg'] ?? 0);
    $tandan    = (float)($x['jumlah_tandan'] ?? 0);
    $hk        = (float)($x['jumlah_hk'] ?? 0);

    $c = 1;
    $sheet->setCellValue($addr($c++, $r), $x['tahun'] ?? '');
    $sheet->setCellValue($addr($c++, $r), $x['nama_kebun'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['nama_unit'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $periode);
    $sheet->setCellValue($addr($c++, $r), $x['tt'] ?? '');

    $sheet->setCellValue($addr($c++, $r), $luas);
    $sheet->setCellValue($addr($c++, $r), $pokok);
    $sheet->setCellValue($addr($c++, $r), $anggaran);
    $sheet->setCellValue($addr($c++, $r), $realisasi);
    $sheet->setCellValue($addr($c++, $r), $tandan);
    $sheet->setCellValue($addr($c++, $r), $hk);
    $sheet->setCellValue($addr($c++, $r), $panenHa);
    $sheet->setCellValue($addr($c++, $r), $freq);

    // Akumulasi
    $sumLuas    += $luas;
    $sumPokok   += $pokok;
    $sumAngg    += $anggaran;
    $sumReal    += $realisasi;
    $sumTandan  += $tandan;
    $sumHK      += $hk;
    $sumPanenHa += $panenHa;

    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
  $sheet->mergeCells($addr(1,$r).':'.$lastCol.$r);
  $sheet->getStyle($addr(1,$r))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $r++;
}

// 5. Grand Total Row
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

// 6. Formatting
$endRow = $r;
$rangeAll = $addr(1,$rowStart).':'.$lastCol.$endRow;

// Border tipis semua data
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');

// Helper formats
$fmtRight = function($colIdx) use ($sheet, $rowStart, $endRow) {
  $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).($rowStart+1).':'.Coordinate::stringFromColumnIndex($colIdx).$endRow)
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
};
$fmtNum0  = function($colIdx) use ($sheet, $rowStart, $endRow) {
  $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).($rowStart+1).':'.Coordinate::stringFromColumnIndex($colIdx).$endRow)
        ->getNumberFormat()->setFormatCode('#,##0');
};
$fmtNum2  = function($colIdx) use ($sheet, $rowStart, $endRow) {
  $sheet->getStyle(Coordinate::stringFromColumnIndex($colIdx).($rowStart+1).':'.Coordinate::stringFromColumnIndex($colIdx).$endRow)
        ->getNumberFormat()->setFormatCode('#,##0.00');
};

// Apply Formats
// Luas(6), Angg(8), Real(9), JmlHK(11), PanenHa(12), Frek(13) => 2 desimal
foreach ([6,8,9,11,12,13] as $c){ $fmtRight($c); $fmtNum2($c); }
// Pokok(7), Tandan(10) => 0 desimal
foreach ([7,10] as $c){ $fmtRight($c); $fmtNum0($c); }

// Style Footer (Total)
$rangeTotal = $addr(1,$r).':'.$lastCol.$r;
$sheet->getStyle($rangeTotal)->getFont()->setBold(true);
$sheet->getStyle($rangeTotal)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5'); // Hijau muda
$sheet->getStyle($rangeTotal)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF16A34A');

// Autosize Columns
for ($i=1; $i<=$lastColIdx; $i++){
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Output
$fname = 'LM76_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;