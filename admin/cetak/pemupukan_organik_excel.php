<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  die("Akses ditolak");
}

require_once '../../config/database.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$tab = $_GET['tab'] ?? 'menabur';
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

$db = new Database();
$conn = $db->getConnection();

// ambil user
$username = $_SESSION['username'] ?? 'Unknown User';
$tanggalCetak = date('d/m/Y H:i');

if ($tab === 'angkutan') {
  $stmt = $conn->query("
    SELECT a.*, u.nama_unit AS unit_tujuan_nama
    FROM angkutan_pupuk_organik a
    LEFT JOIN units u ON u.id = a.unit_tujuan_id
    ORDER BY a.tanggal DESC, a.id DESC
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $title = "Data Angkutan Pupuk Organik";
  $headers = ['Gudang Asal','Unit Tujuan','Tanggal','Jenis Pupuk','Jumlah (Kg)','Nomor DO','Supir'];
} else {
  $stmt = $conn->query("
    SELECT m.*, u.nama_unit AS unit_nama
    FROM menabur_pupuk_organik m
    LEFT JOIN units u ON u.id = m.unit_id
    ORDER BY m.tanggal DESC, m.id DESC
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $title = "Data Penaburan Pupuk Organik";
  $headers = ['Unit','Blok','Tanggal','Jenis Pupuk','Jumlah (Kg)','Luas (Ha)','Invt. Pokok','Catatan'];
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul
$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'PTPN 4 REGIONAL 2');
$sheet->mergeCells('A2:H2');
$sheet->setCellValue('A2', $title);
$sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

// Info Cetak
$sheet->mergeCells('A3:H3');
$sheet->setCellValue('A3', "Dicetak oleh: $username pada $tanggalCetak");
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10);


// Header tabel
$col = 'A';
$row = 5;
foreach ($headers as $h) {
  $sheet->setCellValue($col.$row, $h);
  $col++;
}
$sheet->getStyle("A5:{$col}5")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle("A5:{$col}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E7D32');
$sheet->getStyle("A5:{$col}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Data
$r = 6;
foreach ($rows as $data) {
  $col = 'A';
  if ($tab==='angkutan') {
    $sheet->setCellValue($col++.$r, $data['gudang_asal']);
    $sheet->setCellValue($col++.$r, $data['unit_tujuan_nama']);
    $sheet->setCellValue($col++.$r, $data['tanggal']);
    $sheet->setCellValue($col++.$r, $data['jenis_pupuk']);
    $sheet->setCellValue($col++.$r, $data['jumlah']);
    $sheet->setCellValue($col++.$r, $data['nomor_do']);
    $sheet->setCellValue($col++.$r, $data['supir']);
  } else {
    $sheet->setCellValue($col++.$r, $data['unit_nama']);
    $sheet->setCellValue($col++.$r, $data['blok']);
    $sheet->setCellValue($col++.$r, $data['tanggal']);
    $sheet->setCellValue($col++.$r, $data['jenis_pupuk']);
    $sheet->setCellValue($col++.$r, $data['jumlah']);
    $sheet->setCellValue($col++.$r, $data['luas']);
    $sheet->setCellValue($col++.$r, $data['invt_pokok']);
    $sheet->setCellValue($col++.$r, $data['catatan']);
  }
  $r++;
}

// Autosize
foreach (range('A', $col) as $c) {
  $sheet->getColumnDimension($c)->setAutoSize(true);
}

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"pemupukan_organik_$tab.xlsx\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
