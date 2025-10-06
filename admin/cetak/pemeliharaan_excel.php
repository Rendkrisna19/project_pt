<?php
// pages/cetak/pemeliharaan_excel.php
// Excel mengikuti SEMUA filter dari pages/pemeliharaan.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$db  = new Database(); 
$pdo = $db->getConnection();

/* Helpers */
function colExists(PDO $pdo,$table,$col){
  static $cache=[]; $k=$table;
  if(!isset($cache[$k])){
    $st=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $st->execute([':t'=>$table]);
    $cache[$k]=array_flip(array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME')));
  }
  return isset($cache[$k][strtolower($col)]);
}
function pickCol(PDO $pdo,$table,array $cands){ foreach($cands as $c){ if(colExists($pdo,$table,$c)) return $c; } return null; }
$bulanNama=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$allowedTab=['TU','TBM','TM','BIBIT_PN','BIBIT_MN'];
$titles=['TU'=>'Pemeliharaan TU','TBM'=>'Pemeliharaan TBM','TM'=>'Pemeliharaan TM','BIBIT_PN'=>'Pemeliharaan Bibit PN','BIBIT_MN'=>'Pemeliharaan Bibit MN'];
$tab=$_GET['tab']??'TU'; if(!in_array($tab,$allowedTab,true)) $tab='TU';
$isBibit=in_array($tab,['BIBIT_PN','BIBIT_MN'],true);

/* Filters (sinkron dgn halaman) */
$f_unit_id   = isset($_GET['unit_id'])   && $_GET['unit_id']   !== '' ? (int)$_GET['unit_id'] : '';
$f_bulan     = isset($_GET['bulan'])     && $_GET['bulan']     !== '' ? (string)$_GET['bulan'] : '';
$f_tahun     = isset($_GET['tahun'])     && $_GET['tahun']     !== '' ? (int)$_GET['tahun'] : '';
$f_jenis_id  = isset($_GET['jenis_id'])  && $_GET['jenis_id']  !== '' ? (int)$_GET['jenis_id'] : '';
$f_tenaga_id = isset($_GET['tenaga_id']) && $_GET['tenaga_id'] !== '' ? (int)$_GET['tenaga_id'] : '';
$f_kebun_id  = isset($_GET['kebun_id'])  && $_GET['kebun_id']  !== '' ? (int)$_GET['kebun_id'] : '';
$f_rayon     = isset($_GET['rayon'])     && $_GET['rayon']     !== '' ? (string)$_GET['rayon'] : '';
$f_bibit     = isset($_GET['bibit'])     && $_GET['bibit']     !== '' ? (string)$_GET['bibit'] : '';

/* Map ID→Nama */
$jenis_nama = ''; if($f_jenis_id!==''){ $s=$pdo->prepare("SELECT nama FROM md_jenis_pekerjaan WHERE id=:i"); $s->execute([':i'=>$f_jenis_id]); $jenis_nama=(string)$s->fetchColumn(); }
$tenaga_nama= ''; if($f_tenaga_id!==''){ $s=$pdo->prepare("SELECT nama FROM md_tenaga WHERE id=:i"); $s->execute([':i'=>$f_tenaga_id]); $tenaga_nama=(string)$s->fetchColumn(); }
$kebun_nama = ''; if($f_kebun_id!==''){ $s=$pdo->prepare("SELECT nama_kebun FROM md_kebun WHERE id=:i"); $s->execute([':i'=>$f_kebun_id]); $kebun_nama=(string)$s->fetchColumn(); }

/* Dynamic cols */
$hasTanggal   = colExists($pdo,'pemeliharaan','tanggal');
$hasBulanCol  = colExists($pdo,'pemeliharaan','bulan');
$hasTahunCol  = colExists($pdo,'pemeliharaan','tahun');
$hasKebunId   = colExists($pdo,'pemeliharaan','kebun_id');
$hasKebunKode = colExists($pdo,'pemeliharaan','kebun_kode');

$colKebunText = pickCol($pdo,'pemeliharaan',['kebun_nama','kebun','nama_kebun','kebun_text']);
$colRayon     = pickCol($pdo,'pemeliharaan',['rayon','rayon_nama']);
$colBibit     = pickCol($pdo,'pemeliharaan',['stood','stood_jenis','jenis_bibit','bibit']);

/* SELECT pieces */
$joinKebun=''; $selKebun='';
if ($hasKebunId)      { $joinKebun=" LEFT JOIN md_kebun kb ON kb.id=p.kebun_id ";    $selKebun=", kb.nama_kebun AS kebun_nama"; }
elseif ($hasKebunKode){ $joinKebun=" LEFT JOIN md_kebun kb ON kb.kode=p.kebun_kode ";$selKebun=", kb.nama_kebun AS kebun_nama"; }
elseif ($colKebunText){ $selKebun=", p.$colKebunText AS kebun_nama"; }
else { $selKebun=", NULL AS kebun_nama"; }
$selRayon=$colRayon?", p.$colRayon AS rayon_val":", NULL AS rayon_val";
$selBibit=$colBibit?", p.$colBibit AS bibit_val":", NULL AS bibit_val";

/* Query + filters */
$sql="SELECT p.*, u.nama_unit AS unit_nama $selKebun $selRayon $selBibit
      FROM pemeliharaan p
      LEFT JOIN units u ON u.id=p.unit_id
      $joinKebun
      WHERE p.kategori=:k";
$params=[':k'=>$tab];

if ($f_unit_id!==''){ $sql.=" AND p.unit_id=:uid"; $params[':uid']=$f_unit_id; }
if ($f_bulan!==''){
  if ($hasBulanCol)    { $sql.=" AND p.bulan=:bln"; $params[':bln']=$f_bulan; }
  elseif ($hasTanggal) { $sql.=" AND MONTH(p.tanggal)=:blnnum"; $params[':blnnum']=array_search($f_bulan,$bulanNama,true) ?: array_search($f_bulan,array_values($bulanNama),true); }
}
if ($f_tahun!==''){
  if ($hasTahunCol)    { $sql.=" AND p.tahun=:th"; $params[':th']=$f_tahun; }
  elseif ($hasTanggal) { $sql.=" AND YEAR(p.tanggal)=:th"; $params[':th']=$f_tahun; }
}
if ($f_jenis_id!==''){ if($jenis_nama!==''){ $sql.=" AND p.jenis_pekerjaan=:jn"; $params[':jn']=$jenis_nama; } else { $sql.=" AND 1=0"; } }
if ($f_tenaga_id!==''){ if($tenaga_nama!==''){ $sql.=" AND p.tenaga=:tn"; $params[':tn']=$tenaga_nama; } else { $sql.=" AND 1=0"; } }
if ($f_kebun_id!==''){
  if     ($hasKebunId)   { $sql.=" AND p.kebun_id=:kid"; $params[':kid']=$f_kebun_id; }
  elseif ($joinKebun)    { $sql.=" AND kb.id=:kid";      $params[':kid']=$f_kebun_id; }
  elseif ($colKebunText && $kebun_nama!==''){ $sql.=" AND p.$colKebunText=:kname"; $params[':kname']=$kebun_nama; }
}
if ($isBibit){
  if ($f_bibit!==''){ $col = $colBibit ?: 'bibit'; $sql.=" AND p.$col LIKE :bb"; $params[':bb']="%{$f_bibit}%"; }
}else{
  if ($f_rayon!==''){ $col = $colRayon ?: 'rayon'; $sql.=" AND p.$col LIKE :ry"; $params[':ry']="%{$f_rayon}%"; }
}
$sql .= $hasTanggal ? " ORDER BY p.tanggal DESC, p.id DESC" : " ORDER BY p.tahun DESC, p.id DESC";
$st=$pdo->prepare($sql);
foreach($params as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
$st->execute();
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* Totals */
$tot_r=0.0; $tot_e=0.0;
foreach($rows as $r){ $tot_r+=(float)($r['rencana']??0); $tot_e+=(float)($r['realisasi']??0); }
$tot_d=$tot_e-$tot_r; $tot_p=$tot_r>0?($tot_e/$tot_r*100):0;

/* Spreadsheet */
$spreadsheet=new Spreadsheet();
$sheet=$spreadsheet->getActiveSheet();
$sheet->setTitle(substr($titles[$tab],0,31));

$sheet->mergeCells('A1:L1');
$sheet->setCellValue('A1','PTPN IV REGIONAL 3');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F7B4F');

$sheet->mergeCells('A2:L2');
$sheet->setCellValue('A2',$titles[$tab]);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('0F7B4F');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* filter line */
$parts=["Kategori $tab"];
if ($f_unit_id!==''){ $u=$pdo->prepare("SELECT nama_unit FROM units WHERE id=:i"); $u->execute([':i'=>$f_unit_id]); $parts[]='Unit '.$u->fetchColumn(); }
if ($f_bulan!==''){ $parts[]='Bulan '.$f_bulan; }
if ($f_tahun!==''){ $parts[]='Tahun '.$f_tahun; }
if ($f_jenis_id!==''){ $parts[]='Jenis '.$jenis_nama; }
if ($f_tenaga_id!==''){ $parts[]='Tenaga '.$tenaga_nama; }
if ($f_kebun_id!==''){ $parts[]='Kebun '.$kebun_nama; }
if ($isBibit){ if ($f_bibit!==''){ $parts[]='Stood/Jenis '.$f_bibit; } } else { if ($f_rayon!==''){ $parts[]='Rayon '.$f_rayon; } }
$sheet->mergeCells('A3:L3');
$sheet->setCellValue('A3','Filter: '.implode(' | ',$parts));
$sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('666666');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* Headers */
$headers=[
  'A'=>'Tahun',
  'B'=>'Kebun',
  'C'=>$isBibit?'Stood / Jenis Bibit':'Rayon',
  'D'=>'Unit/Devisi',
  'E'=>'Jenis Pekerjaan',
  'F'=>'Periode',
  'G'=>'Tenaga',
  'H'=>'Rencana',
  'I'=>'Realisasi',
  'J'=>'+/-',
  'K'=>'Progress (%)',
  'L'=>'Status',
];
$row=5; foreach($headers as $c=>$t){ $sheet->setCellValue($c.$row,$t); }
$sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle("A{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1FAB61');
$sheet->getStyle("A{$row}:L{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$row++;

/* Body */
if (empty($rows)){
  $sheet->mergeCells("A{$row}:L{$row}");
  $sheet->setCellValue("A{$row}",'Tidak ada data.');
  $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $row++;
}else{
  foreach($rows as $r){
    $rencana=(float)($r['rencana']??0);
    $realisasi=(float)($r['realisasi']??0);
    $delta=$realisasi-$rencana;
    $th = $hasTahunCol ? (string)($r['tahun']??'') : ($hasTanggal && !empty($r['tanggal']) ? date('Y',strtotime($r['tanggal'])) : '');
    $bln= $hasBulanCol ? (string)($r['bulan']??'') : ($hasTanggal && !empty($r['tanggal']) ? ($bulanNama[(int)date('n',strtotime($r['tanggal']))]??'') : '');
    $periode=trim($bln.' '.$th);
    $progress=$rencana>0?($realisasi/$rencana*100):0;

    $sheet->setCellValue("A{$row}", $th);
    $sheet->setCellValue("B{$row}", (string)($r['kebun_nama']??''));
    $sheet->setCellValue("C{$row}", (string)( $isBibit?($r['bibit_val']??''):($r['rayon_val']??'') ));
    $sheet->setCellValue("D{$row}", (string)($r['unit_nama']??''));
    $sheet->setCellValue("E{$row}", (string)($r['jenis_pekerjaan']??''));
    $sheet->setCellValue("F{$row}", $periode);
    $sheet->setCellValue("G{$row}", (string)($r['tenaga']??''));
    $sheet->setCellValue("H{$row}", $rencana);
    $sheet->setCellValue("I{$row}", $realisasi);
    $sheet->setCellValue("J{$row}", $delta);
    $sheet->setCellValue("K{$row}", round($progress,2));
    $sheet->setCellValue("L{$row}", (string)($r['status']??''));

    $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("H{$row}:K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $row++;
  }
}

/* Totals */
if (!empty($rows)){
  $sheet->setCellValue("A{$row}",'TOTAL');
  $sheet->mergeCells("A{$row}:G{$row}");
  $sheet->setCellValue("H{$row}", $tot_r);
  $sheet->setCellValue("I{$row}", $tot_e);
  $sheet->setCellValue("J{$row}", $tot_d);
  $sheet->setCellValue("K{$row}", round($tot_p,2));
  $sheet->setCellValue("L{$row}", '');
  $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
  $sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1FAF6');
  $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $sheet->getStyle("H{$row}:K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $row++;
}

/* width & format */
foreach(range('A','L') as $c){ $sheet->getColumnDimension($c)->setAutoSize(true); }
$sheet->getStyle("H6:H{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("I6:I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("J6:J{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("K6:K{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

/* Output */
$fname='Pemeliharaan_'.$tab.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer=new Xlsx($spreadsheet); $writer->save('php://output'); exit;
