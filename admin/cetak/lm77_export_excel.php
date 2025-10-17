<?php
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

$db  = new Database();
$pdo = $db->getConnection();

$unit_id = $_GET['unit_id'] ?? null;
$bulan   = $_GET['bulan'] ?? null;
$tahun   = $_GET['tahun'] ?? null;
$kebun_kode = $_GET['kebun_id'] ?? null;

$sql = "SELECT l.*, u.nama_unit, k.nama_kebun
        FROM lm77 l
        LEFT JOIN units u ON u.id = l.unit_id
        LEFT JOIN md_kebun k ON k.kode = l.kebun_kode
        WHERE 1=1";
$bind = [];
if ($unit_id) { $sql.=" AND l.unit_id = :uid"; $bind[':uid']=$unit_id; }
if ($bulan)   { $sql.=" AND l.bulan = :bln"; $bind[':bln']=$bulan; }
if ($tahun)   { $sql.=" AND l.tahun = :thn"; $bind[':thn']=$tahun; }
if ($kebun_kode) { $sql.=" AND l.kebun_kode = :kb"; $bind[':kb']=$kebun_kode; }

$sql .= " ORDER BY l.tahun DESC,
          FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
          u.nama_unit ASC, l.blok ASC";
$st=$pdo->prepare($sql); $st->execute($bind);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

// Rumus
function variancePct($bi,$sd){ return ($sd>0) ? (($bi/$sd)*100 - 100) : 0; }
function tandanPerPohon($tandan,$pokok){ return ($pokok>0)?($tandan/$pokok):0; }
function protasTon($real,$luas){ return ($luas>0)?($real/$luas)/1000:0; }
function btrHitung($real,$tandan){ return ($tandan>0)?($real/$tandan):0; }
function prestasiKgHK($real,$hk){ return ($hk>0)?($real/$hk):0; }
function prestasiTandanHK($tandan,$hk){ return ($hk>0)?($tandan/$hk):0; }

$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$addr = fn(int $c, int $r) => Coordinate::stringFromColumnIndex($c) . $r;

$headers = [
  'Kebun','Unit','Periode','Blok','Luas (Ha)','Pohon',
  'Variance (%)','Tandan/Pohon','Protas (Ton)','BTR',
  'Prestasi Kg/HK','Prestasi Tandan/HK'
];
$lastColIdx = count($headers);
$lastCol = Coordinate::stringFromColumnIndex($lastColIdx);

$sheet->setCellValue($addr(1,1), 'PTPN 4 REGIONAL 3');
$sheet->mergeCells($addr(1,1).':'.$lastCol.'1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF22C55E');

$sheet->setCellValue($addr(1,2), 'LM-77 — Statistik Panen (Rekap)');
$sheet->mergeCells($addr(1,2).':'.$lastCol.'2');
$sheet->getStyle($addr(1,2))->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowStart=4;
for ($i=0;$i<$lastColIdx;$i++) $sheet->setCellValue($addr($i+1,$rowStart),$headers[$i]);
$sheet->getStyle($addr(1,$rowStart).':'.$addr($lastColIdx,$rowStart))->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,$rowStart).':'.$addr($lastColIdx,$rowStart))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5');

$r=$rowStart+1;
if ($rows) {
  foreach ($rows as $x) {
    $v = variancePct($x['prod_tonha_bi'],$x['prod_tonha_sd_tl']);
    $tpp = tandanPerPohon($x['jtandan_per_pohon_bi']*$x['jumlah_pohon'],$x['jumlah_pohon']);
    $prot = protasTon($x['prod_tonha_bi']*1000,$x['luas_ha']);
    $btr = btrHitung($x['btr_bi']*$x['jtandan_per_pohon_bi']*$x['jumlah_pohon'],$x['jtandan_per_pohon_bi']*$x['jumlah_pohon']);
    $kg_hk = prestasiKgHK($x['prestasi_kg_hk_bi']*$x['panen_hk_realisasi'],$x['panen_hk_realisasi']);
    $tandan_hk = prestasiTandanHK($x['prestasi_tandan_hk_bi']*$x['panen_hk_realisasi'],$x['panen_hk_realisasi']);

    $c=1;
    $sheet->setCellValue($addr($c++,$r), $x['nama_kebun']);
    $sheet->setCellValue($addr($c++,$r), $x['nama_unit']);
    $sheet->setCellValue($addr($c++,$r), $x['bulan'].' '.$x['tahun']);
    $sheet->setCellValue($addr($c++,$r), $x['blok']);
    $sheet->setCellValue($addr($c++,$r), $x['luas_ha']);
    $sheet->setCellValue($addr($c++,$r), $x['jumlah_pohon']);
    $sheet->setCellValue($addr($c++,$r), $v);
    $sheet->setCellValue($addr($c++,$r), $tpp);
    $sheet->setCellValue($addr($c++,$r), $prot);
    $sheet->setCellValue($addr($c++,$r), $btr);
    $sheet->setCellValue($addr($c++,$r), $kg_hk);
    $sheet->setCellValue($addr($c++,$r), $tandan_hk);
    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
  $sheet->mergeCells($addr(1,$r).':'.$lastCol.$r);
}

$endRow = max($r-1,$rowStart);
$sheet->getStyle($addr(5,$rowStart+1).':'.$addr($lastColIdx,$endRow))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
$sheet->getStyle($addr(1,$rowStart).':'.$addr($lastColIdx,$endRow))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

for ($i=1;$i<=$lastColIdx;$i++) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);

$fname = 'lm77_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
