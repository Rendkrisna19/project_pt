<?php
// cetak/lm76_export_excel.php
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

// filters
$unit_id = trim($_GET['unit_id'] ?? '');
$bulan   = trim($_GET['bulan'] ?? '');
$tahun   = trim($_GET['tahun'] ?? '');

$db = new Database();
$conn = $db->getConnection();

$w = []; $p = [];
if ($unit_id !== '') { $w[] = 'l.unit_id = :u'; $p[':u'] = $unit_id; }
if ($bulan   !== '') { $w[] = 'l.bulan   = :b'; $p[':b'] = $bulan; }
if ($tahun   !== '') { $w[] = 'l.tahun   = :t'; $p[':t'] = (int)$tahun; }

$sql = "SELECT l.*, u.nama_unit
        FROM lm76 l
        JOIN units u ON u.id = l.unit_id
        ".(count($w) ? ' WHERE '.implode(' AND ',$w) : '')."
        ORDER BY l.tahun DESC,
                 FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
                 u.nama_unit ASC, l.blok ASC";
$st = $conn->prepare($sql); $st->execute($p);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// info
$username = $_SESSION['username'] ?? 'Unknown User';
$now = date('d/m/Y H:i');
$company = 'PTPN 4 REGIONAL 2';
$title   = 'LM-76 — Statistik Panen Kelapa Sawit';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Head
$sheet->mergeCells('A1:T1');
$sheet->setCellValue('A1', $company);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E7D32');

$sheet->mergeCells('A2:T2');
$sheet->setCellValue('A2', $title);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF388E3C');

$sheet->mergeCells('A3:T3');
$sheet->setCellValue('A3', "Diekspor oleh: $username pada $now");
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);

// Header
$headers = [
  'No','Unit','Bulan','Tahun','T.T','Blok','Luas (Ha)','Jml Pohon','Varietas',
  'Prod BI (Real)','Prod BI (Angg)','Prod SD (Real)','Prod SD (Angg)',
  'Jml Tandan (BI)','PSTB (BI)','PSTB (TL)',
  'Panen HK (Real)','Panen Ha (BI)','Panen Ha (SD)',
  'Freq (BI)','Freq (SD)'
];
$cols = range('A','U'); // 21 kolom (A..U)
$hr = 5;
foreach ($headers as $i=>$h) {
  $cell = $cols[$i].$hr;
  $sheet->setCellValue($cell, $h);
  $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E7D32');
  $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// Data
$r0 = $hr + 1; $no = 0;
foreach ($rows as $r) {
  $no++; $rr = $r0 + $no - 1;
  $data = [
    $no,
    $r['nama_unit'] ?? '',
    $r['bulan'] ?? '',
    $r['tahun'] ?? '',
    $r['tt'] ?? '',
    $r['blok'] ?? '',
    (float)($r['luas_ha'] ?? 0),
    (int)($r['jumlah_pohon'] ?? 0),
    $r['varietas'] ?? '',
    (float)($r['prod_bi_realisasi'] ?? 0),
    (float)($r['prod_bi_anggaran'] ?? 0),
    (float)($r['prod_sd_realisasi'] ?? 0),
    (float)($r['prod_sd_anggaran'] ?? 0),
    (int)($r['jumlah_tandan_bi'] ?? 0),
    (float)($r['pstb_ton_ha_bi'] ?? 0),
    (float)($r['pstb_ton_ha_tl'] ?? 0),
    (float)($r['panen_hk_realisasi'] ?? 0),
    (float)($r['panen_ha_bi'] ?? 0),
    (float)($r['panen_ha_sd'] ?? 0),
    (int)($r['frek_panen_bi'] ?? 0),
    (int)($r['frek_panen_sd'] ?? 0),
  ];
  foreach ($data as $i=>$val) {
    $cell = $cols[$i].$rr;
    $sheet->setCellValue($cell, $val);
    $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    if (in_array($i,[0,2,3,19,20])) { // No, Bulan, Tahun, Freq
      $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
    if (in_array($i,[6,7,9,10,11,12,14,15,16,17,18])) { // angka
      $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
  }
}

// Autosize
foreach ($cols as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

// Output
$filename = 'LM76_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
