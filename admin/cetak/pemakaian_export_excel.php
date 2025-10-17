<?php
// admin/cetak/pemakaian_export_excel.php
// Excel export Pemakaian Bahan Kimia — mengikuti SEMUA filter (q, unit_id, bulan, tahun, nama_bahan, jenis_pekerjaan, kebun_label)
// Header hijau "PTPN 4 REGIONAL 3", TANPA info pencetak & tanggal.
// Parse tag [Kebun]/[Fisik] → kebun_label, fisik_label, keterangan_clean
// Stream aman (bersihkan buffer, matikan zlib), PHP 8.3 friendly

declare(strict_types=1);

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

/* ==== Hardening stream ==== */
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }
error_reporting(E_ERROR | E_PARSE); // hindari notice/warning ke output biner

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/* ================= Helpers ================= */
function likeParam(string $s): string {
  return '%'.str_replace(['%','_'], ['\\%','\\_'], trim($s)).'%';
}
function extract_tag_anywhere($ket, $label){
  if (!is_string($ket)) $ket = (string)$ket;
  $pattern = '/\[\s*' . preg_quote($label,'/') . '\s*:\s*([^\]]+)\]\s*/iu';
  if (preg_match($pattern, $ket, $m)) {
    $val = trim($m[1]);
    $clean = preg_replace($pattern, '', $ket, 1);
    return [$val !== '' ? $val : null, trim($clean)];
  }
  return [null, trim($ket)];
}
function parse_labels_and_clean($ketRaw){
  [$kebun, $rest]  = extract_tag_anywhere($ketRaw, 'Kebun');
  [$fisik, $clean] = extract_tag_anywhere($rest,   'Fisik');
  if ($kebun === null && $fisik === null) {
    [$fisik2, $rest2] = extract_tag_anywhere($ketRaw, 'Fisik');
    [$kebun2, $clean2]= extract_tag_anywhere($rest2,  'Kebun');
    $kebun=$kebun2; $fisik=$fisik2; $clean=$clean2;
  }
  return [$kebun, $fisik, trim($clean ?? (string)$ketRaw)];
}
function validYear($y): bool {
  return (bool)preg_match('/^(19[7-9]\d|20\d{2}|2100)$/', (string)$y);
}
/* =========================================== */

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  /* ============== Filters (GET) ============== */
  $q            = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
  $unit_id      = ($_GET['unit_id'] ?? '') === '' ? null : (int)$_GET['unit_id'];
  $bulan        = isset($_GET['bulan']) && $_GET['bulan']!=='' ? trim((string)$_GET['bulan']) : null;
  $tahunRaw     = isset($_GET['tahun']) && $_GET['tahun']!=='' ? (string)$_GET['tahun'] : null;
  $nama_bahan   = isset($_GET['nama_bahan']) && $_GET['nama_bahan']!=='' ? trim((string)$_GET['nama_bahan']) : null;          // NEW
  $jenis_peker  = isset($_GET['jenis_pekerjaan']) && $_GET['jenis_pekerjaan']!=='' ? trim((string)$_GET['jenis_pekerjaan']) : null; // NEW
  $kebun_label  = isset($_GET['kebun_label']) && $_GET['kebun_label']!=='' ? trim((string)$_GET['kebun_label']) : null;       // optional

  $tahun = null;
  if ($tahunRaw !== null && validYear($tahunRaw)) $tahun = (int)$tahunRaw;

  /* ============== Query ============== */
  $sql = "SELECT p.*, u.nama_unit AS unit_nama
          FROM pemakaian_bahan_kimia p
          LEFT JOIN units u ON u.id = p.unit_id
          WHERE 1=1";
  $bind = [];

  if ($q !== '') {
    $sql .= " AND (
      p.no_dokumen LIKE :q
      OR p.nama_bahan LIKE :q
      OR p.jenis_pekerjaan LIKE :q
      OR IFNULL(p.keterangan,'') LIKE :q
    )";
    $bind[':q'] = likeParam($q);
  }
  if ($unit_id !== null) { $sql .= " AND p.unit_id = :uid";   $bind[':uid'] = $unit_id; }
  if ($bulan   !== null) { $sql .= " AND p.bulan   = :bulan"; $bind[':bulan'] = $bulan; }
  if ($tahun   !== null) { $sql .= " AND p.tahun   = :tahun"; $bind[':tahun'] = $tahun; }

  // NEW: filter bahan & jenis (exact dari dropdown UI)
  if ($nama_bahan !== null) {
    $sql .= " AND p.nama_bahan = :nbF";
    $bind[':nbF'] = $nama_bahan;
  }
  if ($jenis_peker !== null) {
    $sql .= " AND p.jenis_pekerjaan = :jpF";
    $bind[':jpF'] = $jenis_peker;
  }

  // Opsional: filter kebun via tag prefix "[Kebun: X]" yang kita normalisasikan di CRUD
  if ($kebun_label !== null) {
    $sql .= " AND p.keterangan LIKE :kbntag";
    $bind[':kbntag'] = "[Kebun: ".$kebun_label."]%";
  }

  $sql .= " ORDER BY p.tahun DESC,
            FIELD(p.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            p.created_at DESC";

  $st = $pdo->prepare($sql);
  foreach($bind as $k=>$v) $st->bindValue($k, $v);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) {
    [$r['kebun_label'], $r['fisik_label'], $r['keterangan_clean']] = parse_labels_and_clean($r['keterangan'] ?? '');
  }
  unset($r);

} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success'=>false,'message'=>'DB Error: '.$e->getMessage()]);
  exit;
}

/* ============== Spreadsheet ============== */
$ss = new Spreadsheet();
$ss->getProperties()
   ->setCreator('PTPN 4 Regional 3')
   ->setTitle('Pemakaian Bahan Kimia')
   ->setSubject('Export Pemakaian');

$sheet = $ss->getActiveSheet();
$addr = fn(int $c, int $r) => Coordinate::stringFromColumnIndex($c) . $r;

$headers = [
  'No. Dokumen','Kebun','Unit','Periode',
  'Nama Bahan','Jenis Pekerjaan',
  'Jlh Diminta','Jlh Fisik','Dokumen','Keterangan'
];
$lastColIdx = count($headers);
$lastCol    = Coordinate::stringFromColumnIndex($lastColIdx);

/* Judul */
$sheet->setCellValue($addr(1,1), 'PTPN 4 REGIONAL 3');
$sheet->mergeCells($addr(1,1).':'.$lastCol.'1');
$sheet->getStyle($addr(1,1))->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle($addr(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getStyle($addr(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF22C55E');

/* Subjudul + subtitle filter */
$subtitleParts = [];
if ($kebun_label !== null) $subtitleParts[] = 'Kebun: '.$kebun_label;
if ($unit_id     !== null) $subtitleParts[] = 'Unit: '.($rows[0]['unit_nama'] ?? '');
if ($bulan       !== null) $subtitleParts[] = 'Bulan: '.$bulan;
if ($tahun       !== null) $subtitleParts[] = 'Tahun: '.$tahun;
if ($nama_bahan  !== null) $subtitleParts[] = 'Bahan: '.$nama_bahan;
if ($jenis_peker !== null) $subtitleParts[] = 'Jenis: '.$jenis_peker;
if ($q !== '')              $subtitleParts[] = 'Cari: "'.$q.'"';
$subtitleText = $subtitleParts ? implode(' • ', $subtitleParts) : 'Tanpa filter';

$sheet->setCellValue($addr(1,2), 'Permintaan / Pemakaian Bahan Kimia — '.$subtitleText);
$sheet->mergeCells($addr(1,2).':'.$lastCol.'2');
$sheet->getStyle($addr(1,2))->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF065F46');
$sheet->getStyle($addr(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* Header tabel */
$rowStart = 4;
for ($i=0; $i<$lastColIdx; $i++){
  $sheet->setCellValue($addr($i+1, $rowStart), $headers[$i]);
}
$rangeHeader = $addr(1,$rowStart).':'.$lastCol.$rowStart;
$sheet->getStyle($rangeHeader)->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
$sheet->getStyle($rangeHeader)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5');
$sheet->getStyle($rangeHeader)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($rangeHeader)->getBorders()->getAllBorders()->getColor()->setARGB('FFE5E7EB');

$sheet->freezePane($addr(1, $rowStart+1));

/* Data */
$r = $rowStart + 1;
if ($rows) {
  foreach ($rows as $x) {
    $c = 1;
    $periode   = trim(($x['bulan'] ?? '').' '.($x['tahun'] ?? ''));
    $fisikTxt  = number_format((float)($x['jlh_fisik'] ?? 0), 2) . (!empty($x['fisik_label']) ? ' ('.$x['fisik_label'].')' : '');
    $dokumenShow = !empty($x['dokumen_path']) ? basename($x['dokumen_path']) : 'N/A';

    $sheet->setCellValue($addr($c++, $r), $x['no_dokumen'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['kebun_label'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['unit_nama']   ?? '-');
    $sheet->setCellValue($addr($c++, $r), $periode !== '' ? $periode : '-');
    $sheet->setCellValue($addr($c++, $r), $x['nama_bahan'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), $x['jenis_pekerjaan'] ?? '-');
    $sheet->setCellValue($addr($c++, $r), (float)($x['jlh_diminta'] ?? 0));
    $sheet->setCellValue($addr($c++, $r), $fisikTxt); // angka + label fisik
    $sheet->setCellValue($addr($c++, $r), $dokumenShow);
    $sheet->setCellValue($addr($c++, $r), $x['keterangan_clean'] ?? '-');
    $r++;
  }
} else {
  $sheet->setCellValue($addr(1,$r), 'Belum ada data untuk filter yang dipilih.');
  $sheet->mergeCells($addr(1,$r).':'.$lastCol.$r);
}

/* Styling angka & border area data */
$endRow = max($r-1, $rowStart);
if ($endRow >= $rowStart+1) {
  $rngDiminta = $addr(7,$rowStart+1).':'.$addr(7,$endRow);
  $sheet->getStyle($rngDiminta)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
  $sheet->getStyle($rngDiminta)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

  $rngFisik = $addr(8,$rowStart+1).':'.$addr(8,$endRow);
  $sheet->getStyle($rngFisik)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
$rangeAll = $addr(1,$rowStart).':'.$lastCol.$endRow;
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($rangeAll)->getBorders()->getAllBorders()->getColor()->setARGB('FFE5E7EB');

/* Autosize */
for ($i=1; $i<=$lastColIdx; $i++){
  $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

/* ==== Output stream XLSX ==== */
$fname = 'pemakaian_'.date('Ymd_His').'.xlsx';
while (ob_get_level() > 0) { @ob_end_clean(); } // pastikan bersih sebelum header

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($ss);
// $writer->setPreCalculateFormulas(false); // opsional, bisa percepat
$writer->save('php://output');
exit;
