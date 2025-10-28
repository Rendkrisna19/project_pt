<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }
error_reporting(E_ERROR | E_PARSE);

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, NumberFormat};
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ===== [1] FUNGSI BANTU & DATA =====
function sum(array $arr, string $key): float {
    return array_reduce($arr, fn($sum, $item) => $sum + (float)($item[$key] ?? 0), 0.0);
}

function cleanTT($tt) {
    if ($tt === null || $tt === '') return null;
    $num = preg_replace('/[^\d]/', '', (string)$tt);
    return is_numeric($num) ? (int)$num : null;
}

$bulanOrder = array_flip(["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"]);

// ===== [2] KONEKSI & AMBIL DATA (Sesuai Filter) =====
try {
    $db = new Database(); $pdo = $db->getConnection();

    $kebun_id = $_GET['kebun_id'] ?? '';
    $unit_id = $_GET['unit_id'] ?? '';
    $bulan = $_GET['bulan'] ?? '';
    $tahun = $_GET['tahun'] ?? '';
    $tt = $_GET['tt'] ?? '';

    $where = " WHERE 1=1 ";
    $bind = [];
    if ($kebun_id !== '') { $where .= " AND l.kebun_id=:kid"; $bind[':kid']=$kebun_id; }
    if ($unit_id !== '') { $where .= " AND l.unit_id=:uid"; $bind[':uid']=$unit_id; }
    if ($bulan !== '') { $where .= " AND l.bulan=:bln"; $bind[':bln']=$bulan; }
    if ($tahun !== '') { $where .= " AND l.tahun=:thn"; $bind[':thn']=$tahun; }
    if ($tt !== '') { $where .= " AND l.tt=:tt"; $bind[':tt']=$tt; }

    $sql = "SELECT l.*, u.nama_unit, k.nama_kebun FROM lm76 l
            LEFT JOIN units u ON u.id = l.unit_id
            LEFT JOIN md_kebun k ON k.id = l.kebun_id
            $where";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $allData = $st->fetchAll(PDO::FETCH_ASSOC);

    // ===== [3] GROUPING DATA (Logika dari Javascript direplikasi di PHP) =====
    $ttBuckets = [];
    foreach ($allData as $r) {
        $ttKey = cleanTT($r['tt']) ?? '__NA__';
        $unitKey = trim($r['nama_unit'] ?? '') ?: '(Unit Tidak Diketahui)';
        $ttBuckets[$ttKey][$unitKey][] = $r;
    }
    // Urutkan TT (ASC) & Unit (Alphabetical)
    ksort($ttBuckets, SORT_NUMERIC);
    foreach ($ttBuckets as &$units) {
        ksort($units, SORT_STRING);
        foreach ($units as &$rows) {
            usort($rows, function($a, $b) use ($bulanOrder) {
                $ty = ($a['tahun'] ?? 0) <=> ($b['tahun'] ?? 0);
                if ($ty !== 0) return $ty;
                $ba = $bulanOrder[$a['bulan']] ?? -1;
                $bb = $bulanOrder[$b['bulan']] ?? -1;
                return $ba <=> $bb;
            });
        }
    }

    // ===== [4] MEMBUAT SPREADSHEET =====
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $A = fn(int $c, int $r) => Coordinate::stringFromColumnIndex($c) . $r;

    $headers = ['Tahun', 'Kebun', 'Unit/Defisi', 'Periode', 'T. Tanam', 'Luas (Ha)', 'Invt Pokok', 'Anggaran (Kg)', 'Realisasi (Kg)', 'Jumlah Tandan', 'Jumlah HK', 'Panen (Ha)', 'Frekuensi'];
    $cols = count($headers);
    $lastCol = Coordinate::stringFromColumnIndex($cols);

    // Judul
    $sheet->setCellValue($A(1,1), 'PTPN IV REGIONAL 3 ');
    $sheet->setCellValue($A(1,2), 'LM-76 — STATISTIK PANEN KELAPA SAWIT');
    $sheet->mergeCells($A(1,1).':'.$lastCol.'1');
    $sheet->mergeCells($A(1,2).':'.$lastCol.'2');
    $sheet->getStyle($A(1,1))->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF065F46'));
    $sheet->getStyle($A(1,2))->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF047857'));
    $sheet->getStyle($A(1,1).':'.$A(1,2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Header Tabel
    $row = 4;
    foreach ($headers as $i => $h) {
        $sheet->setCellValue($A($i+1, $row), $h);
    }
    $sheet->getStyle($A(1,$row).':'.$lastCol.$row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF065F46']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    $row++;
    if (empty($allData)) {
        $sheet->setCellValue($A(1, $row), 'Tidak ada data.');
        $sheet->mergeCells($A(1,$row).':'.$lastCol.$row);
        $sheet->getStyle($A(1,$row))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
    }

    // ===== [5] RENDER DATA & SUBTOTAL =====
    foreach ($ttBuckets as $ttKey => $units) {
        $rowsTT = array_merge(...array_values($units));
        // Header Group TT
        $sheet->setCellValue($A(1, $row), 'Tahun Tanam: ' . ($ttKey === '__NA__' ? 'N/A' : $ttKey));
        $sheet->mergeCells($A(1,$row).':'.$lastCol.$row);
        $sheet->getStyle($A(1,$row))->applyFromArray([ 'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF065F46']] ]);
        $row++;

        foreach ($units as $unitKey => $rowsU) {
            // Header Unit
            $sheet->setCellValue($A(1, $row), 'Unit/AFD: ' . $unitKey);
            $sheet->mergeCells($A(1,$row).':'.$lastCol.$row);
            $sheet->getStyle($A(1,$row))->applyFromArray([ 'font' => ['bold' => true, 'color' => ['argb' => 'FF065F46']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1FAE5']] ]);
            $row++;
            
            // Baris Data
            foreach ($rowsU as $r) {
                $sheet->fromArray([$r['tahun'], $r['nama_kebun'], $r['nama_unit'], $r['bulan'].' '.$r['tahun'], $r['tt'], (float)$r['luas_ha'], (int)$r['jumlah_pohon'], (float)$r['anggaran_kg'], (float)$r['realisasi_kg'], (int)$r['jumlah_tandan'], (float)$r['jumlah_hk'], (float)$r['panen_ha'], (float)$r['frekuensi']], NULL, $A(1, $row));
                $row++;
            }

            // Subtotal Unit
            $luas_u = sum($rowsU, 'luas_ha');
            $sheet->fromArray(['Jumlah ('.$unitKey.')', '', '', '', '', $luas_u, sum($rowsU, 'jumlah_pohon'), sum($rowsU, 'anggaran_kg'), sum($rowsU, 'realisasi_kg'), sum($rowsU, 'jumlah_tandan'), sum($rowsU, 'jumlah_hk'), $panen_u = sum($rowsU, 'panen_ha'), $luas_u > 0 ? $panen_u / $luas_u : 0], NULL, $A(1, $row), true);
            $sheet->mergeCells($A(1,$row).':'.$A(5,$row));
            $sheet->getStyle($A(1,$row).':'.$lastCol.$row)->applyFromArray([ 'font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFECFDF5']] ]);
            $row++;
        }

        // Subtotal TT
        $luas_tt = sum($rowsTT, 'luas_ha');
        $sheet->fromArray(['Subtotal TT: '.($ttKey === '__NA__' ? 'N/A' : $ttKey), '', '', '', '', $luas_tt, sum($rowsTT, 'jumlah_pohon'), sum($rowsTT, 'anggaran_kg'), sum($rowsTT, 'realisasi_kg'), sum($rowsTT, 'jumlah_tandan'), sum($rowsTT, 'jumlah_hk'), $panen_tt = sum($rowsTT, 'panen_ha'), $luas_tt > 0 ? $panen_tt / $luas_tt : 0], NULL, $A(1, $row), true);
        $sheet->mergeCells($A(1,$row).':'.$A(5,$row));
        $sheet->getStyle($A(1,$row).':'.$lastCol.$row)->applyFromArray([ 'font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFECFDF5']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['argb' => 'FF065F46']]] ]);
        $row++;
    }

    // Grand Total
    $luas_total = sum($allData, 'luas_ha');
    $sheet->fromArray(['GRAND TOTAL', '', '', '', '', $luas_total, sum($allData, 'jumlah_pohon'), sum($allData, 'anggaran_kg'), sum($allData, 'realisasi_kg'), sum($allData, 'jumlah_tandan'), sum($allData, 'jumlah_hk'), $panen_total = sum($allData, 'panen_ha'), $luas_total > 0 ? $panen_total / $luas_total : 0], NULL, $A(1, $row), true);
    $sheet->mergeCells($A(1,$row).':'.$A(5,$row));
    $sheet->getStyle($A(1,$row).':'.$lastCol.$row)->applyFromArray([ 'font' => ['bold' => true, 'color' => ['argb' => 'FF065F46']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFBBF7D0']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => 'FF065F46']]] ]);
    $row++;
    
    // Formatting & Auto-size
    $sheet->getStyle($A(6,5).':'.$lastCol.($row-1))->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle($A(7,5).':'.$A(7,($row-1)))->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle($A(10,5).':'.$A(10,($row-1)))->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle($A(6,4).':'.$lastCol.($row-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    foreach (range(1, $cols) as $col) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
    }

    // ===== [6] OUTPUT STREAM =====
    $fname = 'lm76_rekap_'.date('Ymd_His').'.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$fname.'"');
    header('Cache-Control: max-age=0, must-revalidate');
    header('Pragma: public');
    $writer = new Xlsx($ss);
    $writer->save('php://output');
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Gagal membuat file Excel: ' . $e->getMessage()]);
    exit;
}