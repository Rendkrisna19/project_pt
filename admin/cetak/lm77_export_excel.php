<?php
// cetak/lm77_export_excel.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Forbidden'); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$unit_id = trim($_GET['unit_id'] ?? '');
$bulan   = trim($_GET['bulan'] ?? '');
$tahun   = trim($_GET['tahun'] ?? '');

$db = new Database();
$conn = $db->getConnection();

$w=[]; $p=[];
if ($unit_id!=='') { $w[]='l.unit_id=:u'; $p[':u']=$unit_id; }
if ($bulan  !=='') { $w[]='l.bulan=:b';   $p[':b']=$bulan; }
if ($tahun  !=='') { $w[]='l.tahun=:t';   $p[':t']=(int)$tahun; }

$sql = "SELECT l.*, u.nama_unit
        FROM lm77 l
        JOIN units u ON u.id=l.unit_id
        ".(count($w)?" WHERE ".implode(' AND ',$w):"")."
        ORDER BY l.tahun DESC,
          FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
          u.nama_unit ASC, l.blok ASC";
$st=$conn->prepare($sql); $st->execute($p);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

// meta
$company='PTPN 4 REGIONAL 2';
$title='LM-77 — Statistik Panen (Rekap)';
$username=$_SESSION['username'] ?? 'Unknown User';
$now=date('d/m/Y H:i');

// sheet
$spreadsheet=new Spreadsheet();
$sheet=$spreadsheet->getActiveSheet();

$lastCol = 'X'; // 24 kolom (A..X)

// Judul
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1',$company);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E7D32');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells("A2:{$lastCol}2");
$sheet->setCellValue('A2',$title);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF388E3C');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells("A3:{$lastCol}3");
$sheet->setCellValue('A3',"Diekspor oleh: $username pada $now");
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);

// Header
$headers = [
  'No','Unit','Bulan','Tahun','T.T','Blok','Luas (Ha)','Jml Pohon','Pohon/Ha',
  'Var % BI','Var % SD','Tandan/Pohon BI','Tandan/Pohon SD',
  'Prod Ton/Ha BI','Prod Ton/Ha SD THI','Prod Ton/Ha SD TL',
  'BTR BI (Kg/Tdn)','BTR SD THI','BTR SD TL',
  'Basis (Kg/HK)',
  'Prestasi Kg/HK BI','Prestasi Kg/HK SD',
  'Prestasi Tandan/HK BI','Prestasi Tandan/HK SD'
];
$cols = range('A','X');
$hr=5;
foreach ($headers as $i=>$h){
  $c = $cols[$i].$hr;
  $sheet->setCellValue($c,$h);
  $sheet->getStyle($c)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($c)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E7D32');
  $sheet->getStyle($c)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// Data
$r0=$hr+1; $no=0;
foreach($rows as $row){
  $no++; $rr=$r0+$no-1;
  $data = [
    $no,
    $row['nama_unit']??'',
    $row['bulan']??'',
    $row['tahun']??'',
    $row['tt']??'',
    $row['blok']??'',
    (float)($row['luas_ha']??0),
    (int)($row['jumlah_pohon']??0),
    (float)($row['pohon_ha']??0),
    (float)($row['var_prod_bi']??0),
    (float)($row['var_prod_sd']??0),
    (float)($row['jtandan_per_pohon_bi']??0),
    (float)($row['jtandan_per_pohon_sd']??0),
    (float)($row['prod_tonha_bi']??0),
    (float)($row['prod_tonha_sd_thi']??0),
    (float)($row['prod_tonha_sd_tl']??0),
    (float)($row['btr_bi']??0),
    (float)($row['btr_sd_thi']??0),
    (float)($row['btr_sd_tl']??0),
    (float)($row['basis_borong_kg_hk']??0),
    (float)($row['prestasi_kg_hk_bi']??0),
    (float)($row['prestasi_kg_hk_sd']??0),
    (float)($row['prestasi_tandan_hk_bi']??0),
    (float)($row['prestasi_tandan_hk_sd']??0),
  ];
  foreach ($data as $i=>$v){
    $c = $cols[$i].$rr;
    $sheet->setCellValue($c,$v);
    $sheet->getStyle($c)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    // rata tengah utk No/Bulan/Tahun
    if (in_array($i,[0,2,3],true)) $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    // angka rata kanan
    if (in_array($i,[6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23],true)) {
      $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
  }
}

// Autosize
foreach($cols as $c){ $sheet->getColumnDimension($c)->setAutoSize(true); }

// Output
$filename='LM77_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer=new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
