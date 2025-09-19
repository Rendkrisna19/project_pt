<?php
// admin/cetak/alat_panen_export_excel.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Forbidden'); }
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
  http_response_code(400); exit('CSRF invalid');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// ====== Filter
$unit_id = (int)($_GET['unit_id'] ?? 0);
$bulan   = trim($_GET['bulan'] ?? '');
$tahun   = (int)($_GET['tahun'] ?? 0);

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT a.*, u.nama_unit
        FROM alat_panen a
        JOIN units u ON a.unit_id=u.id
        WHERE 1=1";
$bind = [];
if ($unit_id > 0) { $sql .= " AND a.unit_id=:uid"; $bind[':uid'] = $unit_id; }
if ($bulan !== '') { $sql .= " AND a.bulan=:bln"; $bind[':bln'] = $bulan; }
if ($tahun > 0)    { $sql .= " AND a.tahun=:thn"; $bind[':thn'] = $tahun; }

$sql .= " ORDER BY a.tahun DESC,
          FIELD(a.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
          u.nama_unit ASC, a.jenis_alat ASC";

$st = $conn->prepare($sql);
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// ====== Spreadsheet
$username = $_SESSION['username'] ?? 'Unknown User';
$nowStr   = date('d/m/Y H:i');
$judul    = "Laporan Alat Panen";
$subTitle = "PTPN 4 REGIONAL 2";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul & Header perusahaan
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A1', $subTitle);
$sheet->mergeCells('A2:J2');
$sheet->setCellValue('A2', $judul);

$sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

// Info cetak
$sheet->mergeCells('A3:J3');
$sheet->setCellValue('A3', "Dicetak oleh: {$username} pada {$nowStr}");
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('A3')->getFont()->setItalic(true);

// Filter info (opsional tampilkan)
$filterLine = [];
if ($unit_id > 0) {
  // Ambil nama unit untuk info filter
  $name = $conn->prepare("SELECT nama_unit FROM units WHERE id=:id");
  $name->execute([':id'=>$unit_id]);
  $unitNama = $name->fetchColumn() ?: '-';
  $filterLine[] = "Unit: {$unitNama}";
}
if ($bulan !== '') $filterLine[] = "Bulan: {$bulan}";
if ($tahun > 0)    $filterLine[] = "Tahun: {$tahun}";

$sheet->mergeCells('A4:J4');
$sheet->setCellValue('A4', $filterLine ? implode(' — ', $filterLine) : 'Semua Unit / Periode');
$sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A4')->getFont()->setItalic(true)->setSize(9);

// Header tabel
$headers = ['Periode','Unit/Devisi','Jenis Alat Panen','Stok Awal','Mutasi Masuk','Mutasi Keluar','Dipakai','Stok Akhir','Krani Afdeling','Catatan'];
$rowHeader = 6;
$col = 'A';
foreach ($headers as $h) {
  $sheet->setCellValue($col.$rowHeader, $h);
  $sheet->getStyle($col.$rowHeader)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($col.$rowHeader)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A');
  $sheet->getStyle($col.$rowHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($col.$rowHeader)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $col++;
}

// Data
$startRow = $rowHeader + 1;
$r = $startRow;

$sum_sa = 0; $sum_mi = 0; $sum_mk = 0; $sum_dp = 0; $sum_ak = 0;

foreach ($rows as $d) {
  $periode = ($d['bulan'] ?? '').' '.($d['tahun'] ?? '');
  $sheet->setCellValue("A{$r}", $periode);
  $sheet->setCellValue("B{$r}", $d['nama_unit']);
  $sheet->setCellValue("C{$r}", $d['jenis_alat']);
  $sheet->setCellValue("D{$r}", (float)$d['stok_awal']);
  $sheet->setCellValue("E{$r}", (float)$d['mutasi_masuk']);
  $sheet->setCellValue("F{$r}", (float)$d['mutasi_keluar']);
  $sheet->setCellValue("G{$r}", (float)$d['dipakai']);
  $sheet->setCellValue("H{$r}", (float)$d['stok_akhir']);
  $sheet->setCellValue("I{$r}", $d['krani_afdeling']);
  $sheet->setCellValue("J{$r}", $d['catatan']);

  // border setiap baris
  foreach (range('A','J') as $cc) {
    $sheet->getStyle($cc.$r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($cc.$r)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
  }

  // align right untuk angka
  foreach (['D','E','F','G','H'] as $cc) {
    $sheet->getStyle($cc.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($cc.$r)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  }

  // totals
  $sum_sa += (float)$d['stok_awal'];
  $sum_mi += (float)$d['mutasi_masuk'];
  $sum_mk += (float)$d['mutasi_keluar'];
  $sum_dp += (float)$d['dipakai'];
  $sum_ak += (float)$d['stok_akhir'];

  $r++;
}

// Row TOTAL (jika ada data)
if ($r > $startRow) {
  $sheet->setCellValue("A{$r}", 'TOTAL');
  // merge A..C untuk label TOTAL
  $sheet->mergeCells("A{$r}:C{$r}");
  $sheet->getStyle("A{$r}:J{$r}")->getFont()->setBold(true);
  $sheet->getStyle("A{$r}:J{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F5E9');
  foreach (range('A','J') as $cc) {
    $sheet->getStyle($cc.$r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  }

  $sheet->setCellValue("D{$r}", $sum_sa);
  $sheet->setCellValue("E{$r}", $sum_mi);
  $sheet->setCellValue("F{$r}", $sum_mk);
  $sheet->setCellValue("G{$r}", $sum_dp);
  $sheet->setCellValue("H{$r}", $sum_ak);
  // format angka & align
  foreach (['D','E','F','G','H'] as $cc) {
    $sheet->getStyle($cc.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($cc.$r)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  }
}

// Autosize
foreach (range('A','J') as $cc) {
  $sheet->getColumnDimension($cc)->setAutoSize(true);
}
$sheet->getRowDimension(1)->setRowHeight(22);
$sheet->getRowDimension(2)->setRowHeight(20);

// Output
$filename = 'Alat_Panen_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
