<?php
// admin/cetak/pemupukan_excel.php
// Export Excel Pemupukan Organik (Menabur/Angkutan) mengikuti filter ?tab=&unit_id=&kebun_id=
// Dependensi: phpoffice/phpspreadsheet

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Color;

function qint($v){ return ($v===''||$v===null)? null : (int)$v; }

try {
  $db = new Database();
  $pdo = $db->getConnection();

  $tab = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';
  $f_unit_id  = isset($_GET['unit_id'])  && $_GET['unit_id']  !== '' ? (int)$_GET['unit_id']  : null;
  $f_kebun_id = isset($_GET['kebun_id']) && $_GET['kebun_id'] !== '' ? (int)$_GET['kebun_id'] : null;

  if ($tab === 'angkutan') {
    $title = "Data Angkutan Pupuk Organik";
    $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama, k.nama_kebun AS kebun_nama
            FROM angkutan_pupuk_organik a
            LEFT JOIN units u ON u.id = a.unit_tujuan_id
            LEFT JOIN md_kebun k ON k.id = a.kebun_id
            WHERE 1=1";
    $p = [];
    if ($f_unit_id !== null)  { $sql .= " AND a.unit_tujuan_id = :uid"; $p[':uid']=$f_unit_id; }
    if ($f_kebun_id !== null) { $sql .= " AND a.kebun_id       = :kid"; $p[':kid']=$f_kebun_id; }
    $sql .= " ORDER BY a.tanggal DESC, a.id DESC";
    $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $title = "Data Penaburan Pupuk Organik";
    $sql = "SELECT m.*, u.nama_unit AS unit_nama, k.nama_kebun AS kebun_nama
            FROM menabur_pupuk_organik m
            LEFT JOIN units u ON u.id = m.unit_id
            LEFT JOIN md_kebun k ON k.id = m.kebun_id
            WHERE 1=1";
    $p = [];
    if ($f_unit_id !== null)  { $sql .= " AND m.unit_id = :uid";  $p[':uid']=$f_unit_id; }
    if ($f_kebun_id !== null) { $sql .= " AND m.kebun_id = :kid"; $p[':kid']=$f_kebun_id; }
    $sql .= " ORDER BY m.tanggal DESC, m.id DESC";
    $st = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }

} catch (Throwable $e) {
  http_response_code(500);
  exit("DB Error: " . $e->getMessage());
}

// ==== Build Spreadsheet ====
$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();

// Helper alamat sel (A1, B2, dst.)
$addr = function(int $colIndex, int $rowIndex){
  return Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
};

// Header brand hijau (merge dinamis sesuai jumlah kolom)
$colHeadersAngkutan = ['Kebun','Gudang Asal','Unit Tujuan','Tanggal','Jenis Pupuk','Jumlah (Kg)','Nomor DO','Supir'];
$colHeadersMenabur  = ['Kebun','Unit','Blok','Tanggal','Jenis Pupuk','Dosis (kg/ha)','Jumlah (Kg)','Luas (Ha)','Invt. Pokok','Catatan'];
$headers = ($tab==='angkutan') ? $colHeadersAngkutan : $colHeadersMenabur;

$lastColIdx = count($headers);
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

// Brand
$sheet->setCellValue($addr(1,1), 'PTPN IV REGIONAL 3');
$sheet->mergeCells($addr(1,1) . ':' . $lastCol . '1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F7B4F');

// Subjudul
$sheet->setCellValue($addr(1,2), $title);
$sheet->mergeCells($addr(1,2) . ':' . $lastCol . '2');
$sheet->getStyle($addr(1,2))->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF0F7B4F');
$sheet->getStyle($addr(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header kolom
$rowStart = 4;
for ($c=1; $c<=$lastColIdx; $c++) {
  $sheet->setCellValue($addr($c,$rowStart), $headers[$c-1]);
}
$sheet->getStyle($addr(1,$rowStart) . ':' . $lastCol . $rowStart)->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,$rowStart) . ':' . $lastCol . $rowStart)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F4EF');
$sheet->getStyle($addr(1,$rowStart) . ':' . $lastCol . $rowStart)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('FFE5E7EB'));

// Freeze header (di bawah header kolom)
$sheet->freezePane($addr(1, $rowStart + 1));

// Data
$r = $rowStart + 1;

// ——— Totals accumulator
$sum_jumlah = 0.0;
if ($tab==='angkutan') {
  // write rows
  if (!empty($rows)) {
    foreach ($rows as $rec) {
      $sheet->setCellValue($addr(1,$r), $rec['kebun_nama'] ?? '-');
      $sheet->setCellValue($addr(2,$r), $rec['gudang_asal']);
      $sheet->setCellValue($addr(3,$r), $rec['unit_tujuan_nama'] ?? '-');
      $sheet->setCellValue($addr(4,$r), $rec['tanggal']);
      $sheet->setCellValue($addr(5,$r), $rec['jenis_pupuk']);
      $sheet->setCellValue($addr(6,$r), (float)($rec['jumlah'] ?? 0));
      $sheet->setCellValue($addr(7,$r), $rec['nomor_do']);
      $sheet->setCellValue($addr(8,$r), $rec['supir']);
      $sum_jumlah += (float)($rec['jumlah'] ?? 0);
      $r++;
    }
  } else {
    $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
    $sheet->mergeCells($addr(1,$r) . ':' . $lastCol . $r);
    $r++;
  }
} else {
  $sum_luas = 0.0; $sum_invt = 0.0; $sum_dosis = 0.0; $cnt_dosis = 0;
  if (!empty($rows)) {
    foreach ($rows as $rec) {
      $sheet->setCellValue($addr(1,$r), $rec['kebun_nama'] ?? '-');
      $sheet->setCellValue($addr(2,$r), $rec['unit_nama'] ?? '-');
      $sheet->setCellValue($addr(3,$r), $rec['blok']);
      $sheet->setCellValue($addr(4,$r), $rec['tanggal']);
      $sheet->setCellValue($addr(5,$r), $rec['jenis_pupuk']);
      $dosis = (array_key_exists('dosis',$rec) && $rec['dosis']!==null && $rec['dosis']!=='') ? (float)$rec['dosis'] : null;
      $sheet->setCellValue($addr(6,$r), $dosis);
      $sheet->setCellValue($addr(7,$r), (float)($rec['jumlah'] ?? 0));
      $sheet->setCellValue($addr(8,$r), (float)($rec['luas'] ?? 0));
      $sheet->setCellValue($addr(9,$r), (int)($rec['invt_pokok'] ?? 0));
      $sheet->setCellValue($addr(10,$r), $rec['catatan']);

      $sum_jumlah += (float)($rec['jumlah'] ?? 0);
      $sum_luas   += (float)($rec['luas'] ?? 0);
      $sum_invt   += (float)($rec['invt_pokok'] ?? 0);
      if ($dosis !== null) { $sum_dosis += (float)$dosis; $cnt_dosis++; }

      $r++;
    }
  } else {
    $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
    $sheet->mergeCells($addr(1,$r) . ':' . $lastCol . $r);
    $r++;
  }
  $avg_dosis = ($cnt_dosis>0) ? $sum_dosis/$cnt_dosis : 0.0;
}

// Border area data
$endRow = max($r-1, $rowStart);
$sheet->getStyle($addr(1,$rowStart) . ':' . $lastCol . $endRow)->getBorders()->getAllBorders()
      ->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('FFE5E7EB'));

// Format angka & rata kanan
if ($tab==='angkutan') {
  // Jumlah (Kg) = kolom 6
  $rng = $addr(6, $rowStart+1) . ':' . $addr(6, $endRow);
  $sheet->getStyle($rng)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
} else {
  // Dosis=6, Jumlah=7, Luas=8, Invt.=9
  $rng6 = $addr(6, $rowStart+1) . ':' . $addr(6, $endRow);
  $rng7 = $addr(7, $rowStart+1) . ':' . $addr(7, $endRow);
  $rng8 = $addr(8, $rowStart+1) . ':' . $addr(8, $endRow);
  $rng9 = $addr(9, $rowStart+1) . ':' . $addr(9, $endRow);

  $sheet->getStyle($rng6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rng7)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rng8)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rng9)->getNumberFormat()->setFormatCode('0');

  $sheet->getStyle($rng6)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $sheet->getStyle($rng7)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $sheet->getStyle($rng8)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $sheet->getStyle($rng9)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

// ===== TOTAL ROW =====
if ($tab==='angkutan') {
  $sheet->setCellValue($addr(1,$r), 'TOTAL');
  $sheet->mergeCells($addr(1,$r) . ':' . $addr(5,$r));
  $sheet->setCellValue($addr(6,$r), $sum_jumlah);
  // format & style
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getFont()->setBold(true);
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1FAF6');
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $sheet->getStyle($addr(6,$r))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr(6,$r))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $r++;
} else {
  $sheet->setCellValue($addr(1,$r), 'TOTAL');
  $sheet->mergeCells($addr(1,$r) . ':' . $addr(5,$r));      // label
  $sheet->setCellValue($addr(6,$r), $avg_dosis);            // rata-rata dosis
  $sheet->setCellValue($addr(7,$r), $sum_jumlah);           // jumlah
  $sheet->setCellValue($addr(8,$r), $sum_luas);             // luas
  $sheet->setCellValue($addr(9,$r), $sum_invt);             // invt
  // style
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getFont()->setBold(true);
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1FAF6');
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $sheet->getStyle($addr(6,$r) . ':' . $addr(8,$r))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr(9,$r))->getNumberFormat()->setFormatCode('0');
  $sheet->getStyle($addr(6,$r) . ':' . $addr(9,$r))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $r++;
}

// Autosize kolom
for ($i=1; $i<=$lastColIdx; $i++){
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Output
$fname = 'pemupukan_organik_'.$tab.( $f_unit_id ? '_UNIT-'.$f_unit_id : '' ).( $f_kebun_id ? '_KEBUN-'.$f_kebun_id : '' ).'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
