<?php
// pages/cetak/alat_panen_export_excel.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// === DB
$db = new Database(); $pdo = $db->getConnection();

function col_exists(PDO $pdo, $table, $col){
  $st=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

// === Filter
$kebun_id = isset($_GET['kebun_id']) && ctype_digit((string)$_GET['kebun_id']) ? (int)$_GET['kebun_id'] : null;
$unit_id  = isset($_GET['unit_id'])  && ctype_digit((string)$_GET['unit_id'])  ? (int)$_GET['unit_id']  : null;
$bulan    = trim($_GET['bulan'] ?? '');
$tahun    = isset($_GET['tahun']) && ctype_digit((string)$_GET['tahun']) ? (int)$_GET['tahun'] : null;
$blok_f   = trim($_GET['blok'] ?? '');
$tt_f     = trim($_GET['tt']   ?? '');

// === deteksi kolom
$hasBlokId = col_exists($pdo,'alat_panen','blok_id');
$hasBlokTx = col_exists($pdo,'alat_panen','blok');
$hasTtId   = col_exists($pdo,'alat_panen','tt_id');
$hasTtTx   = col_exists($pdo,'alat_panen','tt');

// === query dinamis
$selectParts = [
  "ap.*",
  "k.nama_kebun",
  "u.nama_unit"
];
$joins = [
  "LEFT JOIN md_kebun k ON k.id = ap.kebun_id",
  "LEFT JOIN units u ON u.id = ap.unit_id"
];

if ($hasBlokId) { $selectParts[]="mb.kode_blok AS blok_nama"; $joins[]="LEFT JOIN md_blok mb ON mb.id = ap.blok_id"; }
elseif ($hasBlokTx) { $selectParts[]="ap.blok AS blok_nama"; }
else { $selectParts[]="NULL AS blok_nama"; }

if ($hasTtId) { $selectParts[]="tt.tahun_tanam AS tt_nama"; $joins[]="LEFT JOIN md_tahun_tanam tt ON tt.id = ap.tt_id"; }
elseif ($hasTtTx){ $selectParts[]="ap.tt AS tt_nama"; }
else { $selectParts[]="NULL AS tt_nama"; }

$where = ["1=1"];
$params = [];
if ($kebun_id) { $where[]="ap.kebun_id = :kebun_id"; $params[':kebun_id']=$kebun_id; }
if ($unit_id)  { $where[]="ap.unit_id  = :unit_id";  $params[':unit_id']=$unit_id; }
if ($bulan!==''){ $where[]="ap.bulan    = :bulan";    $params[':bulan']=$bulan; }
if ($tahun)    { $where[]="ap.tahun    = :tahun";    $params[':tahun']=$tahun; }

if ($blok_f!==''){
  if ($hasBlokId) {
    if (ctype_digit($blok_f)) { $where[]="ap.blok_id = :blok_id"; $params[':blok_id']=(int)$blok_f; }
    else { $where[]="mb.kode_blok = :blok_tx"; $params[':blok_tx']=$blok_f; }
  } elseif ($hasBlokTx) {
    $where[]="ap.blok = :blok_tx"; $params[':blok_tx']=$blok_f;
  }
}
if ($tt_f!==''){
  if ($hasTtId) {
    if (ctype_digit($tt_f)) { $where[]="ap.tt_id = :tt_id"; $params[':tt_id']=(int)$tt_f; }
    else { $where[]="tt.tahun_tanam = :tt_tx"; $params[':tt_tx']=$tt_f; }
  } elseif ($hasTtTx) {
    $where[]="ap.tt = :tt_tx"; $params[':tt_tx']=$tt_f;
  }
}

$sql = "SELECT ".implode(', ',$selectParts)."
        FROM alat_panen ap
        ".implode("\n", $joins)."
        WHERE ".implode(' AND ', $where)."
        ORDER BY ap.tahun DESC,
                 FIELD(ap.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
                 ap.id DESC";
$st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

// === Spreadsheet
$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Alat Panen');

// Judul (tema hijau, tanpa tanggal/ nama)
$sheet->mergeCells('A1:N1');
$sheet->setCellValue('A1','PTPN 4 REGIONAL 3');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('065F46');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Subjudul filter ringkas
$sub = [];
if ($kebun_id) $sub[]='KebunID: '.$kebun_id;
if ($unit_id)  $sub[]='UnitID: '.$unit_id;
if ($bulan!=='')$sub[]='Bulan: '.$bulan;
if ($tahun)    $sub[]='Tahun: '.$tahun;
if ($blok_f!=='') $sub[]='Blok: '.$blok_f;
if ($tt_f!=='')   $sub[]='T.T: '.$tt_f;

$sheet->mergeCells('A2:N2');
$sheet->setCellValue('A2', implode(' • ', $sub));
$sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('059669');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header
$header = [
  'No','Periode','Kebun','Unit/Devisi','Blok','T.T','Jenis Alat',
  'Stok Awal','Mutasi Masuk','Mutasi Keluar','Dipakai','Stok Akhir','Krani Afdeling','Catatan'
];
$sheet->fromArray($header, null, 'A4');
$sheet->getStyle('A4:N4')->getFont()->setBold(true);
$sheet->getStyle('A4:N4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
$sheet->getStyle('A4:N4')->getFont()->getColor()->setRGB('065F46');
$sheet->getRowDimension(4)->setRowHeight(22);

// Body
$r=5; $no=1;
foreach($rows as $row){
  $sheet->setCellValue("A{$r}", $no++);
  $sheet->setCellValue("B{$r}", trim(($row['bulan']??'').' '.($row['tahun']??'')));
  $sheet->setCellValue("C{$r}", $row['nama_kebun']??'-');
  $sheet->setCellValue("D{$r}", $row['nama_unit']??'-');
  $sheet->setCellValue("E{$r}", $row['blok_nama']??'-');
  $sheet->setCellValue("F{$r}", $row['tt_nama']??'-');
  $sheet->setCellValue("G{$r}", $row['jenis_alat']??'-');
  $sheet->setCellValueExplicit("H{$r}", (float)($row['stok_awal']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  $sheet->setCellValueExplicit("I{$r}", (float)($row['mutasi_masuk']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  $sheet->setCellValueExplicit("J{$r}", (float)($row['mutasi_keluar']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  $sheet->setCellValueExplicit("K{$r}", (float)($row['dipakai']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  $sheet->setCellValueExplicit("L{$r}", (float)($row['stok_akhir']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
  $sheet->setCellValue("M{$r}", $row['krani_afdeling']??'-');
  $sheet->setCellValue("N{$r}", $row['catatan']??'-');
  $r++;
}

// Format angka
$sheet->getStyle("H5:L".($r-1))->getNumberFormat()->setFormatCode('#,##0.00');

// Border & autosize
$sheet->getStyle("A4:N".($r-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach(range('A','N') as $col){ $sheet->getColumnDimension($col)->setAutoSize(true); }

// Output
$filename = 'Alat_Panen_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
