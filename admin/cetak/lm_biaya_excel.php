<?php
// admin/cetak/lm_biaya_excel.php
// Excel export LM Biaya — Tema hijau, header brand, ikut filter opsional

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

function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  $hasKebun = col_exists($pdo, 'lm_biaya', 'kebun_id');

  // Filters (opsional)
  $unit_id = isset($_GET['unit_id']) && $_GET['unit_id'] !== '' ? (int)$_GET['unit_id'] : null;
  $bulan   = isset($_GET['bulan'])   && $_GET['bulan']   !== '' ? trim($_GET['bulan'])   : null;
  $tahun   = isset($_GET['tahun'])   && $_GET['tahun']   !== '' ? (int)$_GET['tahun']   : null;
  $kebun_id= isset($_GET['kebun_id'])&& $_GET['kebun_id']!== '' ? (int)$_GET['kebun_id'] : null;

  $sql = "SELECT b.*,
                 u.nama_unit,
                 a.kode AS kode_aktivitas, a.nama AS nama_aktivitas,
                 j.nama AS nama_jenis
                 ".($hasKebun ? ", kb.nama_kebun " : "")."
          FROM lm_biaya b
          LEFT JOIN units u ON u.id = b.unit_id
          LEFT JOIN md_kode_aktivitas a ON a.id = b.kode_aktivitas_id
          LEFT JOIN md_jenis_pekerjaan j ON j.id = b.jenis_pekerjaan_id
          ".($hasKebun ? "LEFT JOIN md_kebun kb ON kb.id = b.kebun_id" : "")."
          WHERE 1=1";
  $bind = [];
  if (!is_null($unit_id)) { $sql .= " AND b.unit_id = :uid";   $bind[':uid'] = $unit_id; }
  if (!is_null($bulan))   { $sql .= " AND b.bulan = :bln";     $bind[':bln'] = $bulan; }
  if (!is_null($tahun))   { $sql .= " AND b.tahun = :thn";     $bind[':thn'] = $tahun; }
  if ($hasKebun && !is_null($kebun_id)) { $sql .= " AND b.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }

  $sql .= " ORDER BY b.tahun DESC,
            FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            b.id DESC";

  $st = $pdo->prepare($sql); $st->execute($bind);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e){
  http_response_code(500); exit('DB Error: '.$e->getMessage());
}

// ===== Spreadsheet =====
$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$addr = fn(int $c, int $r) => Coordinate::stringFromColumnIndex($c).$r;

// Header list (dinamis kebun)
$headers = [
  'Kode Aktivitas','Jenis Pekerjaan','Bulan','Tahun'
];
if ($hasKebun) $headers[] = 'Kebun';
$headers = array_merge($headers, ['Unit/Divisi','Rencana BI','Realisasi BI','+/− Biaya','+/− %']);

$lastColIdx = count($headers);
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

// Brand header
$sheet->setCellValue($addr(1,1), 'PTPN 4 REGIONAL 3');
$sheet->mergeCells($addr(1,1).':'.$lastCol.'1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF22C55E');

// Subtitle
$sheet->setCellValue($addr(1,2), 'LM Biaya');
$sheet->mergeCells($addr(1,2).':'.$lastCol.'2');
$sheet->getStyle($addr(1,2))->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Table header
$rowStart = 4;
for ($i=0; $i<$lastColIdx; $i++) {
  $sheet->setCellValue($addr($i+1,$rowStart), $headers[$i]);
}
$hdrRange = $addr(1,$rowStart).':'.$lastCol.$rowStart;
$sheet->getStyle($hdrRange)->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
$sheet->getStyle($hdrRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5');
$sheet->getStyle($hdrRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');
$sheet->freezePane($addr(1,$rowStart+1));

// Data
$r = $rowStart + 1;
if ($rows) {
  foreach ($rows as $row) {
    $c = 1;
    $sheet->setCellValue($addr($c++,$r), trim(($row['kode_aktivitas'] ?? '').' - '.($row['nama_aktivitas'] ?? '')));
    $sheet->setCellValue($addr($c++,$r), $row['nama_jenis'] ?? '-');
    $sheet->setCellValue($addr($c++,$r), $row['bulan'] ?? '-');
    $sheet->setCellValue($addr($c++,$r), (int)($row['tahun'] ?? 0));
    if ($hasKebun) { $sheet->setCellValue($addr($c++,$r), $row['nama_kebun'] ?? '-'); }
    $sheet->setCellValue($addr($c++,$r), $row['nama_unit'] ?? '-');

    $rencana  = (float)($row['rencana_bi'] ?? 0);
    $realis   = (float)($row['realisasi_bi'] ?? 0);
    $diffBi   = $realis - $rencana;
    $diffPct  = $rencana>0 ? ($realis/$rencana - 1) * 100 : null;

    $sheet->setCellValue($addr($c++,$r), $rencana);
    $sheet->setCellValue($addr($c++,$r), $realis);
    $sheet->setCellValue($addr($c++,$r), $diffBi);
    $sheet->setCellValue($addr($c++,$r), $diffPct);

    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
  $sheet->mergeCells($addr(1,$r).':'.$lastCol.$r);
}

// Styles numbers
$endRow = max($r-1, $rowStart);
if ($rows) {
  // kolom index untuk angka: rencana, realisasi, diffBi, diffPct
  $colRencana = $lastColIdx - 3; // posisi relatif dari belakang: ... , 'Rencana','Realisasi','+/− Biaya','+/− %'
  $colRealis  = $lastColIdx - 2;
  $colDiffBi  = $lastColIdx - 1;
  $colDiffPct = $lastColIdx;

  // format 2 desimal untuk uang
  $sheet->getStyle($addr($colRencana,$rowStart+1).':'.$addr($colDiffBi,$endRow))
        ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr($colRencana,$rowStart+1).':'.$addr($colDiffBi,$endRow))
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

  // persen 2 desimal
  $sheet->getStyle($addr($colDiffPct,$rowStart+1).':'.$addr($colDiffPct,$endRow))
        ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr($colDiffPct,$rowStart+1).':'.$addr($colDiffPct,$endRow))
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

// border keseluruhan
$allRange = $addr(1,$rowStart).':'.$lastCol.$endRow;
$sheet->getStyle($allRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');

// autosize
for ($i=1; $i<=$lastColIdx; $i++) {
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

$fname = 'lm_biaya_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
