<?php
// pemakaian_export_excel.php (REVISI pakai units)
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

$q       = trim($_GET['q'] ?? '');
$unit_id = (int)($_GET['unit_id'] ?? 0);
$bulan   = trim($_GET['bulan'] ?? '');
$tahun   = (int)($_GET['tahun'] ?? date('Y'));

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT p.no_dokumen, u.nama_unit, p.bulan, p.tahun, p.nama_bahan, p.jenis_pekerjaan,
               p.jlh_diminta, p.jlh_fisik, p.dokumen_path, p.keterangan
        FROM pemakaian_bahan_kimia p
        LEFT JOIN units u ON u.id = p.unit_id
        WHERE 1=1";
$bind = [];
if ($unit_id > 0) { $sql .= " AND p.unit_id = :uid"; $bind[':uid'] = $unit_id; }
if ($bulan !== '') { $sql .= " AND p.bulan = :bln"; $bind[':bln'] = $bulan; }
if ($tahun) { $sql .= " AND p.tahun = :thn"; $bind[':thn'] = $tahun; }
if ($q !== '') {
  $sql .= " AND (p.no_dokumen LIKE :q OR p.nama_bahan LIKE :q OR p.jenis_pekerjaan LIKE :q OR p.keterangan LIKE :q)";
  $bind[':q'] = "%{$q}%";
}
$sql .= " ORDER BY p.tahun DESC,
                 FIELD(p.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
                 u.nama_unit ASC, p.no_dokumen ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($bind);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Spreadsheet
$nowStr = date('d-m-Y');
$judul  = "PTPN 4 REGIONAL 2 — Pemakaian Bahan Kimia (Tanggal: {$nowStr})";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A1', $judul);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header
$headers = ['No','No. Dokumen','Unit','Periode','Nama Bahan','Jenis Pekerjaan','Jlh Diminta','Jumlah Fisik','Dokumen','Keterangan'];
$col = 'A'; $rowHeader = 3;
foreach ($headers as $h) {
  $sheet->setCellValue($col.$rowHeader, $h);
  $sheet->getStyle($col.$rowHeader)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($col.$rowHeader)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF16A34A');
  $sheet->getStyle($col.$rowHeader)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($col.$rowHeader)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $col++;
}

// Data
$startRow = 4; $i = 0;
foreach ($rows as $r) {
  $i++; $rr = $startRow + $i - 1;
  $periode = ($r['bulan'] ?? '').' '.($r['tahun'] ?? '');
  $sheet->setCellValue("A{$rr}", $i);
  $sheet->setCellValue("B{$rr}", $r['no_dokumen']);
  $sheet->setCellValue("C{$rr}", $r['nama_unit']);
  $sheet->setCellValue("D{$rr}", $periode);
  $sheet->setCellValue("E{$rr}", $r['nama_bahan']);
  $sheet->setCellValue("F{$rr}", $r['jenis_pekerjaan']);
  $sheet->setCellValue("G{$rr}", (float)$r['jlh_diminta']);
  $sheet->setCellValue("H{$rr}", (float)$r['jlh_fisik']);
  $sheet->setCellValue("I{$rr}", basename((string)$r['dokumen_path']) ?: '-');
  $sheet->setCellValue("J{$rr}", $r['keterangan']);

  foreach (range('A','J') as $cc) {
    $sheet->getStyle($cc.$rr)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  }
}

// Autosize
foreach (range('A','J') as $cc) {
  $sheet->getColumnDimension($cc)->setAutoSize(true);
}

// Output
$filename = 'Pemakaian_'.date('Ymd_His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
