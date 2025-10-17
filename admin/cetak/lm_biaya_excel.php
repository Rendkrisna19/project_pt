<?php
// admin/cetak/lm_biaya_excel.php
// Export Excel LM Biaya (PhpSpreadsheet) — tema hijau + ikut filter
// Versi aman stream: bersihkan output buffer, matikan zlib.output_compression, tanpa echo/var_dump

declare(strict_types=1);
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  http_response_code(403);
  exit('Unauthorized');
}

/* ==== Hardening output agar XLSX bisa dibuka ==== */
@ini_set('zlib.output_compression', 'Off'); // penting untuk stream binary
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_clean(); } // bersihkan buffer apapun

// Hindari notice/warning tampil di output (bisa merusak file biner)
error_reporting(E_ERROR | E_PARSE);

// ==== Dependencies ====
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  /* ===== Filters (ikut dari lm_biaya.php) ===== */
  $unit_id  = $_GET['unit_id']  ?? '';
  $tahun    = $_GET['tahun']    ?? '';
  $bulan    = $_GET['bulan']    ?? '';
  $kebun_id = $_GET['kebun_id'] ?? '';
  $q        = trim($_GET['q'] ?? '');

  /* ===== Query ===== */
  $where = " WHERE 1=1 ";
  $bind  = [];
  if ($unit_id  !== '') { $where .= " AND b.unit_id=:uid";  $bind[':uid'] = $unit_id; }
  if ($tahun    !== '') { $where .= " AND b.tahun=:thn";    $bind[':thn'] = $tahun; }
  if ($bulan    !== '') { $where .= " AND b.bulan=:bln";    $bind[':bln'] = $bulan; }
  if ($kebun_id !== '') { $where .= " AND b.kebun_id=:kid"; $bind[':kid'] = $kebun_id; }
  if ($q !== '') {
    $where .= " AND (b.alokasi LIKE :kw OR b.uraian_pekerjaan LIKE :kw)";
    $bind[':kw'] = "%$q%";
  }

  $sql = "SELECT b.*, u.nama_unit, kb.nama_kebun
          FROM lm_biaya b
          LEFT JOIN units u     ON u.id  = b.unit_id
          LEFT JOIN md_kebun kb ON kb.id = b.kebun_id
          $where
          ORDER BY b.tahun DESC,
            FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            b.id DESC";
  $st = $pdo->prepare($sql);
  foreach($bind as $k=>$v) $st->bindValue($k,$v);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $first = $rows[0] ?? [];

  /* ===== Build Spreadsheet ===== */
  $ss    = new Spreadsheet();
  $ss->getProperties()
     ->setCreator('PTPN 4 Regional 3')
     ->setTitle('LM BIAYA — REKAP')
     ->setSubject('LM Biaya Export')
     ->setDescription('Export LM Biaya dengan filter aktif');

  $sheet = $ss->getActiveSheet();
  $A = fn(int $col,int $row) => Coordinate::stringFromColumnIndex($col).$row;

  $headers = [
    'Kebun','Unit/Defisi','Alokasi','Uraian Pekerjaan','Bulan','Tahun',
    'Anggaran','Realisasi','+/- Biaya','%'
  ];
  $lastCol = Coordinate::stringFromColumnIndex(count($headers));

  // Brand bar
  $sheet->setCellValue($A(1,1), 'LM BIAYA — REKAP');
  $sheet->mergeCells($A(1,1).':'.$lastCol.'1');
  $sheet->getStyle($A(1,1))->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($A(1,1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
  $sheet->getRowDimension(1)->setRowHeight(28);
  $sheet->getStyle($A(1,1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF059669');

  // Subtitle (filter ringkas)
  $subtitle = [];
  if ($kebun_id!=='') $subtitle[]='Kebun='.($first['nama_kebun']??'');
  if ($unit_id!=='')  $subtitle[]='Unit='.($first['nama_unit']??'');
  if ($bulan!=='')    $subtitle[]='Bulan='.$bulan;
  if ($tahun!=='')    $subtitle[]='Tahun='.$tahun;
  $sheet->setCellValue($A(1,2), $subtitle ? implode('  •  ', $subtitle) : 'Tanpa filter');
  $sheet->mergeCells($A(1,2).':'.$lastCol.'2');
  $sheet->getStyle($A(1,2))->getFont()->setBold(true)->getColor()->setARGB('FF065F46');
  $sheet->getStyle($A(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Table header
  $row = 4;
  for ($i=0; $i<count($headers); $i++) {
    $sheet->setCellValue($A($i+1,$row), $headers[$i]);
  }
  $hdrRange = $A(1,$row).':'.$lastCol.$row;
  $sheet->getStyle($hdrRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
  $sheet->getStyle($hdrRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF10B981');
  $sheet->getStyle($hdrRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

  // Data rows
  $row++;
  $totalAnggaran=0; $totalRealisasi=0;
  $totalAnggaranIncl=0; $totalRealisasiIncl=0;
  $totalAnggaranExcl=0; $totalRealisasiExcl=0;

  if ($rows) {
    foreach ($rows as $r) {
      $rencana = (float)($r['rencana_bi'] ?? 0);
      $realis  = (float)($r['realisasi_bi'] ?? 0);
      $pct  = $rencana>0 ? ($realis/$rencana - 1) : null; // fraction, Excel format %
      $diff = $realis - $rencana;

      $sheet->setCellValue($A(1,$row),  $r['nama_kebun'] ?? '-');
      $sheet->setCellValue($A(2,$row),  $r['nama_unit'] ?? '-');
      $sheet->setCellValue($A(3,$row),  $r['alokasi'] ?? '');
      $sheet->setCellValue($A(4,$row),  $r['uraian_pekerjaan'] ?? '');
      $sheet->setCellValue($A(5,$row),  $r['bulan'] ?? '');
      $sheet->setCellValueExplicit($A(6,$row), (string)($r['tahun'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
      $sheet->setCellValue($A(7,$row),  $rencana);
      $sheet->setCellValue($A(8,$row),  $realis);
      $sheet->setCellValue($A(9,$row),  $diff);
      if ($pct !== null) $sheet->setCellValue($A(10,$row), $pct);

      // zebra
      if ((($row-4) % 2)===1) {
        $sheet->getStyle($A(1,$row).':'.$lastCol.$row)
              ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
      }

      $totalAnggaran  += $rencana;
      $totalRealisasi += $realis;

      $isPemupukan = (stripos($r['alokasi'] ?? '', 'pupuk') !== false) ||
                     (stripos($r['uraian_pekerjaan'] ?? '', 'pupuk') !== false);
      if ($isPemupukan) {
        $totalAnggaranIncl  += $rencana;
        $totalRealisasiIncl += $realis;
      } else {
        $totalAnggaranExcl  += $rencana;
        $totalRealisasiExcl += $realis;
      }
      $row++;
    }
  } else {
    // Tampilkan 1 baris info jika tidak ada data
    $sheet->setCellValue($A(1,$row), 'Tidak ada data untuk filter yang dipilih.');
    $sheet->mergeCells($A(1,$row).':'.$lastCol.$row);
    $sheet->getStyle($A(1,$row))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($A(1,$row))->getFont()->getColor()->setARGB('FF6B7280');
    $row++;
  }

  // Number formats & alignment
  if ($row > 5) {
    $sheet->getStyle($A(7,5).':'.$A(9,$row-1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
    $sheet->getStyle($A(10,5).':'.$A(10,$row-1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->getStyle($A(7,5).':'.$A(10,$row-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  }

  // Table border
  $sheet->getStyle($A(1,4).':'.$lastCol.($row-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

  // ===== Footer totals =====
  $makeFooter = function(string $title, float $anggaran, float $realisasi, int $rowIdx, string $bg, string $fg='FF111827') use ($sheet, $A, $lastCol) {
    $diff = $realisasi - $anggaran;
    $pct  = $anggaran>0 ? ($realisasi/$anggaran - 1) : null; // fraction

    $sheet->setCellValue($A(1,$rowIdx), $title);
    $sheet->mergeCells($A(1,$rowIdx).':'.$A(6,$rowIdx));
    $sheet->setCellValue($A(7,$rowIdx), $anggaran);
    $sheet->setCellValue($A(8,$rowIdx), $realisasi);
    $sheet->setCellValue($A(9,$rowIdx), $diff);
    if ($pct !== null) $sheet->setCellValue($A(10,$rowIdx), $pct);

    $sheet->getStyle($A(1,$rowIdx).':'.$lastCol.$rowIdx)->getFont()->setBold(true)->getColor()->setARGB($fg);
    $sheet->getStyle($A(1,$rowIdx).':'.$lastCol.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
    $sheet->getStyle($A(1,$rowIdx).':'.$lastCol.$rowIdx)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($A(7,$rowIdx).':'.$A(9,$rowIdx))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
    $sheet->getStyle($A(10,$rowIdx))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->getStyle($A(7,$rowIdx).':'.$A(10,$rowIdx))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  };

  // Footer hanya jika ada data
  if ($rows) {
    $makeFooter('Jlh By Tanaman Incl. Pemupukan', $totalAnggaranIncl, $totalRealisasiIncl, $row++, 'FFF3F4F6');
    $makeFooter('Jlh By Tanaman Excl. Pemupukan', $totalAnggaranExcl, $totalRealisasiExcl, $row++, 'FFF3F4F6');
    $makeFooter('Harga Pokok Incl. Pemupukan',     $totalAnggaranIncl, $totalRealisasiIncl, $row++, 'FFBBF7D0', 'FF065F46');
    $makeFooter('Harga Pokok Excl. Pemupukan',     $totalAnggaranExcl, $totalRealisasiExcl, $row++, 'FFFDE68A', 'FF7C2D12');
    $makeFooter('TOTAL',                            $totalAnggaran,     $totalRealisasi,     $row++, 'FFECFDF5', 'FF065F46');
  }

  // Autosize columns
  for ($c=1; $c<=count($headers); $c++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
  }

  // ==== Output stream XLSX ====
  $fname = 'lm_biaya_'.date('Ymd_His').'.xlsx';

  // Pastikan tidak ada output sama sekali sebelum header di bawah ini
  while (ob_get_level() > 0) { @ob_end_clean(); }

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  header('Cache-Control: max-age=0, must-revalidate');
  header('Pragma: public');

  $writer = new Xlsx($ss);
  // Opsi: percepat jika banyak formula
  // $writer->setPreCalculateFormulas(false);

  $writer->save('php://output');
  exit;

} catch (Throwable $e) {
  // Jika terjadi error, kirim respon JSON (bukan biner) agar tidak merusak stream Excel
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'status' => 'error',
    'message' => 'Gagal membuat file Excel.',
    'detail' => $e->getMessage()
  ]);
  exit;
}
