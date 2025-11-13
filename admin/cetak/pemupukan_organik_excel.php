<?php
// admin/cetak/pemupukan_excel.php
// Export Excel Pemupukan Organik (Menabur/Angkutan) mengikuti filter terbaru
// Filter: ?tab=&tahun=&kebun_id=&tanggal=&periode=&unit_id=&keterangan=&jenis_pupuk=
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

function qint($v){ return ($v===''||$v===null) ? null : (int)$v; }
function qstr($v){ $v = trim((string)$v); return $v==='' ? null : $v; }

$bulanNama = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // ===== Params =====
  $tab          = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';
  $f_tahun      = qint($_GET['tahun']      ?? null);          // YEAR(tanggal)
  $f_unit_id    = qint($_GET['unit_id']    ?? null);
  $f_kebun_id   = qint($_GET['kebun_id']   ?? null);
  $f_tanggal    = qstr($_GET['tanggal']    ?? null);          // yyyy-mm-dd
  $f_periode    = qint($_GET['periode']    ?? null);          // 1..12
  $f_keterangan = qstr($_GET['keterangan'] ?? null);          // LIKE (angkutan only)
  $f_jenis      = qstr($_GET['jenis_pupuk']?? null);

  if ($tab === 'angkutan') {
    $title = "Data Angkutan Pupuk Organik";
    $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama, k.nama_kebun AS kebun_nama,
                   YEAR(a.tanggal) AS tahun, MONTH(a.tanggal) AS bulan
            FROM angkutan_pupuk_organik a
            LEFT JOIN units u   ON u.id = a.unit_tujuan_id
            LEFT JOIN md_kebun k ON k.id = a.kebun_id
            WHERE 1=1";
    $p = [];
    if ($f_tahun   !== null) { $sql .= " AND YEAR(a.tanggal)=:th"; $p[':th']=$f_tahun; }
    if ($f_unit_id !== null) { $sql .= " AND a.unit_tujuan_id = :uid"; $p[':uid']=$f_unit_id; }
    if ($f_kebun_id!== null) { $sql .= " AND a.kebun_id       = :kid"; $p[':kid']=$f_kebun_id; }
    if ($f_tanggal !== null) { $sql .= " AND a.tanggal        = :tgl"; $p[':tgl']=$f_tanggal; }
    if ($f_periode !== null) { $sql .= " AND MONTH(a.tanggal) = :bln"; $p[':bln']=$f_periode; }
    if ($f_jenis   !== null) { $sql .= " AND a.jenis_pupuk    = :jp";  $p[':jp']=$f_jenis; }
    if ($f_keterangan!== null){ $sql .= " AND a.keterangan LIKE :ket"; $p[':ket']="%{$f_keterangan}%"; }
    $sql .= " ORDER BY a.tanggal DESC, a.id DESC";
    $st  = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $title = "Data Penaburan Pupuk Organik";
    $sql = "SELECT m.*, u.nama_unit AS unit_nama, k.nama_kebun AS kebun_nama,
                   YEAR(m.tanggal) AS tahun, MONTH(m.tanggal) AS bulan
            FROM menabur_pupuk_organik m
            LEFT JOIN units u   ON u.id = m.unit_id
            LEFT JOIN md_kebun k ON k.id = m.kebun_id
            WHERE 1=1";
    $p = [];
    if ($f_tahun   !== null) { $sql .= " AND YEAR(m.tanggal)=:th"; $p[':th']=$f_tahun; }
    if ($f_unit_id !== null) { $sql .= " AND m.unit_id = :uid";  $p[':uid']=$f_unit_id; }
    if ($f_kebun_id!== null) { $sql .= " AND m.kebun_id = :kid"; $p[':kid']=$f_kebun_id; }
    if ($f_tanggal !== null) { $sql .= " AND m.tanggal  = :tgl"; $p[':tgl']=$f_tanggal; }
    if ($f_periode !== null) { $sql .= " AND MONTH(m.tanggal) = :bln"; $p[':bln']=$f_periode; }
    if ($f_jenis   !== null) { $sql .= " AND m.jenis_pupuk = :jp"; $p[':jp']=$f_jenis; }
    // (menabur: tidak ada keterangan; catatan sudah dihapus)
    $sql .= " ORDER BY m.tanggal DESC, m.id DESC";
    $st  = $pdo->prepare($sql); $st->execute($p); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  }

} catch (Throwable $e) {
  http_response_code(500);
  exit("DB Error: " . $e->getMessage());
}

// ==== Build Spreadsheet ====
$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$addr = function(int $colIndex, int $rowIndex){
  return Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
};

// Headers (revisi)
$colHeadersAngkutan = [
  'Tahun','Kebun','Tanggal','Periode','Gudang Asal','Unit Tujuan','Jenis Pupuk',
  'Jumlah (Kg)','Nomor DO','Keterangan'
];
$colHeadersMenabur  = [
  'Tahun','Kebun','Tanggal','Periode','Unit/Defisi','T. Tanam','Blok',
  'Luas (Ha)','Jenis Pupuk','Dosis','Kilogram'
];
// Catatan: menabur sesuai CRUD: tidak ada keterangan/catatan

$headers   = ($tab==='angkutan') ? $colHeadersAngkutan : $colHeadersMenabur;
$lastColIx = count($headers);
$lastCol   = Coordinate::stringFromColumnIndex($lastColIx);

// Brand bar (green-700)
$sheet->setCellValue($addr(1,1), 'PTPN IV REGIONAL 3');
$sheet->mergeCells($addr(1,1) . ':' . $lastCol . '1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF065F46'); // green-700

// Subjudul (green-700)
$sheet->setCellValue($addr(1,2), $title);
$sheet->mergeCells($addr(1,2) . ':' . $lastCol . '2');
$sheet->getStyle($addr(1,2))->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header kolom (background green-600 tint)
$rowStart = 4;
for ($c=1; $c<=$lastColIx; $c++) {
  $sheet->setCellValue($addr($c,$rowStart), $headers[$c-1]);
}
$sheet->getStyle($addr(1,$rowStart) . ':' . $lastCol . $rowStart)->getFont()->setBold(true)->getColor()->setARGB('FF064E3B'); // deep
$sheet->getStyle($addr(1,$rowStart) . ':' . $lastCol . $rowStart)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD1FAE5'); // light green band
$sheet->getStyle($addr(1,$rowStart) . ':' . $lastCol . $rowStart)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('FFE5E7EB'));
$sheet->freezePane($addr(1, $rowStart + 1));

// Data
$r = $rowStart + 1;

$sum_jumlah = 0.0; $sum_luas=0.0; $sum_dosis=0.0; $cnt_dosis=0;
if (!empty($rows)) {
  foreach ($rows as $rec) {
    $tahun  = (int)($rec['tahun'] ?? 0);
    $bulan  = (int)($rec['bulan'] ?? 0);
    $periodeLabel = ($bulan>=1&&$bulan<=12) ? ($bulan.' - '.$bulanNama[$bulan]) : '-';

    if ($tab==='angkutan') {
      $sheet->setCellValue($addr(1,$r), $tahun);
      $sheet->setCellValue($addr(2,$r), $rec['kebun_nama'] ?? '-');
      $sheet->setCellValue($addr(3,$r), $rec['tanggal'] ?? '');
      $sheet->setCellValue($addr(4,$r), $periodeLabel);
      $sheet->setCellValue($addr(5,$r), $rec['gudang_asal'] ?? '');
      $sheet->setCellValue($addr(6,$r), $rec['unit_tujuan_nama'] ?? '-');
      $sheet->setCellValue($addr(7,$r), $rec['jenis_pupuk'] ?? '');
      $sheet->setCellValue($addr(8,$r), (float)($rec['jumlah'] ?? 0));
      $sheet->setCellValue($addr(9,$r), $rec['nomor_do'] ?? '');
      $sheet->setCellValue($addr(11,$r), $rec['keterangan'] ?? '');
      $sum_jumlah += (float)($rec['jumlah'] ?? 0);
    } else {
      $sheet->setCellValue($addr(1,$r), $tahun);
      $sheet->setCellValue($addr(2,$r), $rec['kebun_nama'] ?? '-');
      $sheet->setCellValue($addr(3,$r), $rec['tanggal'] ?? '');
      $sheet->setCellValue($addr(4,$r), $periodeLabel);
      $sheet->setCellValue($addr(5,$r), $rec['unit_nama'] ?? '-');
      $sheet->setCellValue($addr(6,$r), array_key_exists('t_tanam',$rec)? ($rec['t_tanam'] ?? null) : null);
      $sheet->setCellValue($addr(7,$r), $rec['blok'] ?? '');
      $sheet->setCellValue($addr(8,$r), (float)($rec['luas'] ?? 0));
      $sheet->setCellValue($addr(9,$r), $rec['jenis_pupuk'] ?? '');
      $dosis = (array_key_exists('dosis',$rec) && $rec['dosis']!=='') ? (float)$rec['dosis'] : null;
      $sheet->setCellValue($addr(10,$r), $dosis);
      $sheet->setCellValue($addr(11,$r), (float)($rec['jumlah'] ?? 0));

      $sum_luas   += (float)($rec['luas'] ?? 0);
      $sum_jumlah += (float)($rec['jumlah'] ?? 0);
      if ($dosis !== null){ $sum_dosis += $dosis; $cnt_dosis++; }
    }
    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data.');
  $sheet->mergeCells($addr(1,$r) . ':' . $lastCol . $r);
  $r++;
}

$endRow = max($r-1, $rowStart);
$sheet->getStyle($addr(1,$rowStart) . ':' . $lastCol . $endRow)
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('FFE5E7EB'));

// Format angka
if ($tab==='angkutan') {
  $rng = $addr(8, $rowStart+1) . ':' . $addr(8, $endRow);
  $sheet->getStyle($rng)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
} else {
  $rngLuas   = $addr(8, $rowStart+1) . ':' . $addr(8, $endRow);
  $rngDosis  = $addr(10, $rowStart+1) . ':' . $addr(10, $endRow);
  $rngJumlah = $addr(11, $rowStart+1) . ':' . $addr(11, $endRow);
  $sheet->getStyle($rngLuas)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rngDosis)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rngJumlah)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rngLuas)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $sheet->getStyle($rngDosis)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $sheet->getStyle($rngJumlah)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

// TOTAL
if ($tab==='angkutan') {
  $sheet->setCellValue($addr(1,$r), 'TOTAL');
  $sheet->mergeCells($addr(1,$r) . ':' . $addr(7,$r));
  $sheet->setCellValue($addr(8,$r), $sum_jumlah);
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getFont()->setBold(true);
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F5E9');
  $sheet->getStyle($addr(8,$r))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr(8,$r))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $r++;
} else {
  $avg_dosis = $cnt_dosis>0 ? $sum_dosis/$cnt_dosis : 0.0;
  $sheet->setCellValue($addr(1,$r), 'TOTAL');
  $sheet->mergeCells($addr(1,$r) . ':' . $addr(7,$r)); // label
  $sheet->setCellValue($addr(8,$r), $sum_luas);
  $sheet->setCellValue($addr(10,$r), $avg_dosis);
  $sheet->setCellValue($addr(11,$r), $sum_jumlah);
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getFont()->setBold(true);
  $sheet->getStyle($addr(1,$r) . ':' . $lastCol . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F5E9');
  $sheet->getStyle($addr(8,$r) . ':' . $addr(11,$r))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($addr(8,$r) . ':' . $addr(11,$r))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  $r++;
}

// Autosize
for ($i=1; $i<=$lastColIx; $i++){
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// Output
$fname = 'pemupukan_organik_'.$tab
        .($f_tahun   ? '_THN-'.$f_tahun   : '')
        .($f_unit_id ? '_UNIT-'.$f_unit_id: '')
        .($f_kebun_id? '_KEBUN-'.$f_kebun_id: '')
        .($f_periode ? '_BLN-'.$f_periode: '')
        .'.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
