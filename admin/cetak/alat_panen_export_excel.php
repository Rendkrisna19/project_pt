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

// Fungsi ini tidak lagi dipakai untuk blok/tt, tapi mungkin berguna untuk hal lain
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
// Filter $blok_f dan $tt_f DIHAPUS

// === deteksi kolom
// Deteksi untuk blok dan tt DIHAPUS

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

// Logika dinamis untuk $selectParts dan $joins terkait blok/tt DIHAPUS

$where = ["1=1"];
$params = [];
if ($kebun_id) { $where[]="ap.kebun_id = :kebun_id"; $params[':kebun_id']=$kebun_id; }
if ($unit_id)  { $where[]="ap.unit_id  = :unit_id";  $params[':unit_id']=$unit_id; }
if ($bulan!==''){ $where[]="ap.bulan    = :bulan";    $params[':bulan']=$bulan; }
if ($tahun)    { $where[]="ap.tahun    = :tahun";    $params[':tahun']=$tahun; }

// Logika dinamis untuk $where terkait $blok_f dan $tt_f DIHAPUS

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
$sheet->mergeCells('A1:L1'); // Modifikasi: N -> L
$sheet->setCellValue('A1','PTPN 4 REGIONAL 3');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('065F46');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Subjudul filter ringkas
$sub = [];
if ($kebun_id) $sub[]='KebunID: '.$kebun_id;
if ($unit_id)  $sub[]='UnitID: '.$unit_id;
if ($bulan!=='')$sub[]='Bulan: '.$bulan;
if ($tahun)    $sub[]='Tahun: '.$tahun;
// Filter $blok_f dan $tt_f DIHAPUS

$sheet->mergeCells('A2:L2'); // Modifikasi: N -> L
$sheet->setCellValue('A2', implode(' • ', $sub));
$sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('059669');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header
$header = [
  'No','Periode','Kebun','Unit/Devisi', // 'Blok','T.T' DIHAPUS
  'Jenis Alat',
  'Stok Awal','Mutasi Masuk','Mutasi Keluar','Dipakai','Stok Akhir','Krani Afdeling','Catatan'
];
$sheet->fromArray($header, null, 'A4');
$sheet->getStyle('A4:L4')->getFont()->setBold(true); // Modifikasi: N -> L
$sheet->getStyle('A4:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7'); // Modifikasi: N -> L
$sheet->getStyle('A4:L4')->getFont()->getColor()->setRGB('065F46'); // Modifikasi: N -> L
$sheet->getRowDimension(4)->setRowHeight(22);

// Body
$r=5; $no=1;
foreach($rows as $row){
  $sheet->setCellValue("A{$r}", $no++);
  $sheet->setCellValue("B{$r}", trim(($row['bulan']??'').' '.($row['tahun']??'')));
  $sheet->setCellValue("C{$r}", $row['nama_kebun']??'-');
  $sheet->setCellValue("D{$r}", $row['nama_unit']??'-');
  // Kolom E (blok_nama) dan F (tt_nama) DIHAPUS
  $sheet->setCellValue("E{$r}", $row['jenis_alat']??'-'); // Modifikasi: G -> E
  $sheet->setCellValueExplicit("F{$r}", (float)($row['stok_awal']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);     // Modifikasi: H -> F
  $sheet->setCellValueExplicit("G{$r}", (float)($row['mutasi_masuk']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);  // Modifikasi: I -> G
  $sheet->setCellValueExplicit("H{$r}", (float)($row['mutasi_keluar']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC); // Modifikasi: J -> H
  $sheet->setCellValueExplicit("I{$r}", (float)($row['dipakai']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);        // Modifikasi: K -> I
  $sheet->setCellValueExplicit("J{$r}", (float)($row['stok_akhir']??0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);    // Modifikasi: L -> J
  $sheet->setCellValue("K{$r}", $row['krani_afdeling']??'-'); // Modifikasi: M -> K
  $sheet->setCellValue("L{$r}", $row['catatan']??'-');         // Modifikasi: N -> L
  $r++;
}

// Format angka
$sheet->getStyle("F5:J".($r-1))->getNumberFormat()->setFormatCode('#,##0.00'); // Modifikasi: H5:L -> F5:J

// Border & autosize
$sheet->getStyle("A4:L".($r-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN); // Modifikasi: N -> L
foreach(range('A','L') as $col){ $sheet->getColumnDimension($col)->setAutoSize(true); } // Modifikasi: N -> L

// Output
$filename = 'Alat_Panen_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;