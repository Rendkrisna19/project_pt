<?php
// stok_gudang_export_excel.php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  http_response_code(403); exit('Forbidden');
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$jenis = trim($_GET['jenis'] ?? '');
$bulan = trim($_GET['bulan'] ?? '');
$tahun = (int)($_GET['tahun'] ?? date('Y'));

$db = new Database();
$conn = $db->getConnection();

// Ambil data sesuai filter
$sql = "SELECT nama_bahan, satuan, bulan, tahun, stok_awal, mutasi_masuk, mutasi_keluar, pasokan, dipakai
        FROM stok_gudang WHERE 1=1";
$bind = [];
if ($jenis !== '') { $sql .= " AND nama_bahan = :nb"; $bind[':nb'] = $jenis; }
if ($bulan !== '') { $sql .= " AND bulan = :bln";   $bind[':bln'] = $bulan; }
if ($tahun)        { $sql .= " AND tahun = :thn";   $bind[':thn'] = $tahun; }
$sql .= " ORDER BY nama_bahan ASC, FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), tahun DESC, id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($bind);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC); 

$nowStr = date('d-m-Y');
$judul  = "PTPN REGIONAL 3 — Stok Gudang (Tanggal: {$nowStr})";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Title (merge & style hijau)
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', $judul);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A'); // emerald-ish
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header tabel (baris 3, hijau)
$headers = ['No','Nama Bahan','Satuan','Bulan','Tahun','Stok Awal','Masuk','Keluar','Pasokan','Dipakai','Sisa Stok'];
$col = 'A';
$rowHeader = 3;
foreach ($headers as $h) {
  $sheet->setCellValue($col.$rowHeader, $h);
  $sheet->getStyle($col.$rowHeader)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($col.$rowHeader)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A');
  $sheet->getStyle($col.$rowHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($col.$rowHeader)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $col++;
}

// Data rows
$startRow = 4;
$i = 0;
foreach ($rows as $r) {
  $i++;
  $rr = $startRow + $i - 1;
  $sheet->setCellValue("A{$rr}", $i);
  $sheet->setCellValue("B{$rr}", $r['nama_bahan']);
  $sheet->setCellValue("C{$rr}", $r['satuan']);
  $sheet->setCellValue("D{$rr}", $r['bulan']);
  $sheet->setCellValue("E{$rr}", (int)$r['tahun']);
  $sheet->setCellValue("F{$rr}", (float)$r['stok_awal']);
  $sheet->setCellValue("G{$rr}", (float)$r['mutasi_masuk']);
  $sheet->setCellValue("H{$rr}", (float)$r['mutasi_keluar']);
  $sheet->setCellValue("I{$rr}", (float)$r['pasokan']);
  $sheet->setCellValue("J{$rr}", (float)$r['dipakai']);
  // Sisa Stok = F + G + I - H - J (pakai formula)
  $sheet->setCellValue("K{$rr}", "=F{$rr}+G{$rr}+I{$rr}-H{$rr}-J{$rr}");

  // border tipis untuk baris
  foreach (range('A','K') as $cc) {
    $sheet->getStyle($cc.$rr)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  }
}

// Autosize kolom
foreach (range('A','K') as $cc) {
  $sheet->getColumnDimension($cc)->setAutoSize(true);
}

// Output
$filename = 'StokGudang_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
