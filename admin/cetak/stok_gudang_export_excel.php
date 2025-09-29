<?php
// pages/cetak/stok_gudang_export_excel.php
// Excel rekap stok gudang (mengikuti filter GET: kebun_id, bahan_id, bulan, tahun) — header hijau PTPN IV REGIONAL 3
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$db  = new Database();
$pdo = $db->getConnection();

/* ===== Params ===== */
$kebun_id = ($_GET['kebun_id'] ?? '') === '' ? '' : (int)$_GET['kebun_id'];
$bahan_id = ($_GET['bahan_id'] ?? '') === '' ? '' : (int)$_GET['bahan_id'];
$bulan    = trim((string)($_GET['bulan'] ?? ''));
$tahun    = (int)($_GET['tahun'] ?? date('Y'));

/* ===== Query ===== */
$sql = "SELECT
          sg.*,
          k.kode AS kebun_kode, k.nama_kebun,
          b.kode AS bahan_kode, b.nama_bahan,
          s.nama AS satuan
        FROM stok_gudang sg
        JOIN md_kebun k ON k.id = sg.kebun_id
        JOIN md_bahan_kimia b ON b.id = sg.bahan_id
        JOIN md_satuan s ON s.id = b.satuan_id
        WHERE sg.tahun = :thn";
$P = [':thn'=>$tahun];

if ($kebun_id !== '') { $sql .= " AND sg.kebun_id = :kid"; $P[':kid'] = (int)$kebun_id; }
if ($bahan_id !== '') { $sql .= " AND sg.bahan_id = :bid"; $P[':bid'] = (int)$bahan_id; }
if ($bulan !== '')    { $sql .= " AND sg.bulan = :bln";   $P[':bln'] = $bulan; }

$sql .= " ORDER BY k.nama_kebun, b.nama_bahan, FIELD(sg.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), sg.id";

$st = $pdo->prepare($sql);
$st->execute($P);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== Nama filter ringkas ===== */
$unitFilterLine = '';
$kebunNama = 'Semua Kebun';
if ($kebun_id !== '') {
  $t = $pdo->prepare("SELECT CONCAT(kode,' — ',nama_kebun) FROM md_kebun WHERE id=:id");
  $t->execute([':id'=>(int)$kebun_id]);
  $kebunNama = $t->fetchColumn() ?: ('#'.$kebun_id);
}
$bahanNama = 'Semua Bahan';
if ($bahan_id !== '') {
  $t = $pdo->prepare("SELECT CONCAT(kode,' — ',nama_bahan) FROM md_bahan_kimia WHERE id=:id");
  $t->execute([':id'=>(int)$bahan_id]);
  $bahanNama = $t->fetchColumn() ?: ('#'.$bahan_id);
}

/* ===== Spreadsheet ===== */
$sheetTitle = 'Stok Gudang';
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr($sheetTitle, 0, 31));

$headers = [
  'Kebun',
  'Bahan (Satuan)',
  'Bulan',
  'Tahun',
  'Stok Awal',
  'Mutasi Masuk',
  'Mutasi Keluar',
  'Pasokan',
  'Dipakai',
  'Net Mutasi',
  'Sisa Stok',
];

// kolom dinamis
$colLetters = [];
for ($i=0; $i<count($headers); $i++) {
  $colLetters[] = Coordinate::stringFromColumnIndex($i+1);
}
$firstCol = $colLetters[0];
$lastCol  = end($colLetters);

// Header brand hijau
$sheet->mergeCells($firstCol.'1:'.$lastCol.'1');
$sheet->setCellValue($firstCol.'1', 'PTPN IV REGIONAL 3');
$sheet->getStyle($firstCol.'1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($firstCol.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($firstCol.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F7B4F');

// Subjudul
$sheet->mergeCells($firstCol.'2:'.$lastCol.'2');
$sheet->setCellValue($firstCol.'2', 'Rekap Stok Gudang Bahan Kimia');
$sheet->getStyle($firstCol.'2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('0F7B4F');
$sheet->getStyle($firstCol.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Filter ringkas
$sheet->mergeCells($firstCol.'3:'.$lastCol.'3');
$sheet->setCellValue($firstCol.'3', 'Filter: Kebun: '.$kebunNama.' | Bahan: '.$bahanNama.' | Bulan: '.($bulan!==''?$bulan:'Semua Bulan').' | Tahun: '.$tahun);
$sheet->getStyle($firstCol.'3')->getFont()->setSize(10)->getColor()->setRGB('666666');
$sheet->getStyle($firstCol.'3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header tabel
$row = 5;
foreach ($headers as $i => $title) {
  $sheet->setCellValue($colLetters[$i].$row, $title);
}
$sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getFont()->setBold(true);
$sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4EF');
$sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$row++;

/* ===== Body ===== */
if (empty($rows)) {
  $sheet->mergeCells($firstCol.$row.':'.$lastCol.$row);
  $sheet->setCellValue($firstCol.$row, 'Tidak ada data.');
  $sheet->getStyle($firstCol.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
  foreach ($rows as $r) {
    $stok_awal = (float)($r['stok_awal'] ?? 0);
    $masuk     = (float)($r['mutasi_masuk'] ?? 0);
    $keluar    = (float)($r['mutasi_keluar'] ?? 0);
    $pasokan   = (float)($r['pasokan'] ?? 0);
    $dipakai   = (float)($r['dipakai'] ?? 0);
    $net       = ($masuk - $keluar) + ($pasokan - $dipakai);
    $sisa      = $stok_awal + $net;

    $vals = [
      (string)(($r['kebun_kode'] ?? '').' — '.($r['nama_kebun'] ?? '')),
      (string)(($r['bahan_kode'] ?? '').' — '.($r['nama_bahan'] ?? '').' ('.($r['satuan'] ?? '').')'),
      (string)($r['bulan'] ?? ''),
      (int)   ($r['tahun'] ?? 0),
      $stok_awal,
      $masuk,
      $keluar,
      $pasokan,
      $dipakai,
      $net,
      $sisa,
    ];

    foreach ($vals as $i => $v) {
      $sheet->setCellValue($colLetters[$i].$row, $v);
    }

    // border row
    $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    // align numeric right untuk kolom 5..11
    for ($i=4; $i<=10; $i++) {
      $sheet->getStyle($colLetters[$i].$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    $row++;
  }
}

// Auto width
foreach ($colLetters as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

/* ===== Output ===== */
$fname = 'Stok_Gudang';
if ($kebun_id!=='') $fname .= '_K'.$kebun_id;
if ($bahan_id!=='') $fname .= '_B'.$bahan_id;
if ($bulan!=='')    $fname .= '_'.$bulan;
$fname .= '_'.$tahun.'.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
