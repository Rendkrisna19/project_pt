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

// Fungsi bantu kalkulasi frekuensi (Sama seperti UI & PDF)
$calcFreq = fn($r) => ((float)($r['luas_ha'] ?? 0) > 0 ? (float)($r['panen_ha'] ?? 0) / (float)($r['luas_ha'] ?? 0) : 0);

// Order bulan untuk sorting
$bulanOrder = array_flip(["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"]);

// ===== [2] KONEKSI & AMBIL DATA (Sesuai Filter) =====
try {
    $db = new Database(); $pdo = $db->getConnection();

    $kebun_id = $_GET['kebun_id'] ?? '';
    $unit_id = $_GET['unit_id'] ?? '';
    $bulan = $_GET['bulan'] ?? '';
    $tahun = $_GET['tahun'] ?? '';
    $tt = $_GET['tt'] ?? ''; // Filter TT mungkin masih ada di UI, jadi tetap diambil

    $where = " WHERE 1=1 ";
    $bind = [];
    if ($kebun_id !== '') { $where .= " AND l.kebun_id=:kid"; $bind['kid']=$kebun_id; }
    if ($unit_id !== '') { $where .= " AND l.unit_id=:uid"; $bind['uid']=$unit_id; }
    if ($bulan !== '') { $where .= " AND l.bulan=:bln"; $bind['bln']=$bulan; }
    if ($tahun !== '') { $where .= " AND l.tahun=:thn"; $bind['thn']=$tahun; }
    if ($tt !== '') { $where .= " AND l.tt=:tt"; $bind['tt']=$tt; } // Query tetap memfilter

    $sql = "SELECT l.*, u.nama_unit, k.nama_kebun FROM lm76 l
            LEFT JOIN units u ON u.id = l.unit_id
            LEFT JOIN md_kebun k ON k.id = l.kebun_id
            $where";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $allData = $st->fetchAll(PDO::FETCH_ASSOC);

    // ===== [3] GROUPING DATA (Logika BARU: Group by Unit, Sort by Tahun->Bulan) =====
    $unitBuckets = [];
    foreach ($allData as $r) {
        $unitKey = trim($r['nama_unit'] ?? '') ?: '(Unit Tidak Diketahui)';
        $unitBuckets[$unitKey][] = $r;
    }
    
    // Urutkan nama Unit A-Z
    ksort($unitBuckets, SORT_STRING);
    
    // Urutkan data DI DALAM setiap unit (Tahun -> Bulan)
    foreach ($unitBuckets as &$rows) { // pakai reference '&'
        usort($rows, function($a, $b) use ($bulanOrder) {
            // Urut 1: Tahun
            $ty = ($a['tahun'] ?? 0) <=> ($b['tahun'] ?? 0);
            if ($ty !== 0) return $ty;
            // Urut 2: Bulan
            $ba = $bulanOrder[$a['bulan']] ?? -1;
            $bb = $bulanOrder[$b['bulan']] ?? -1;
            return $ba <=> $bb;
        });
    }
    unset($rows); // Hapus reference

    // ===== [4] MEMBUAT SPREADSHEET =====
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $A = fn(int $c, int $r) => Coordinate::stringFromColumnIndex($c) . $r;

    // Headers (Kolom T. Tanam dihapus)
    $headers = ['Tahun', 'Kebun', 'Unit/Defisi', 'Periode', 'Luas (Ha)', 'Invt Pokok', 'Anggaran (Kg)', 'Realisasi (Kg)', 'Jumlah Tandan', 'Jumlah HK', 'Panen (Ha)', 'Frekuensi'];
    $cols = count($headers); // Sekarang 12
    $lastCol = Coordinate::stringFromColumnIndex($cols); // Sekarang 'L'

    // Judul
    $sheet->setCellValue($A(1,1), 'PTPN IV REGIONAL 3 ');
    $sheet->setCellValue($A(1,2), 'LM-76 — STATISTIK PANEN KELAPA SAWIT');
    $sheet->setCellValue($A(1,3), '(Grup per Unit/AFD, Urut Tahun -> Bulan)'); // Keterangan
    $sheet->mergeCells($A(1,1).':'.$lastCol.'1');
    $sheet->mergeCells($A(1,2).':'.$lastCol.'2');
    $sheet->mergeCells($A(1,3).':'.$lastCol.'3');
    $sheet->getStyle($A(1,1))->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF065F46'));
    $sheet->getStyle($A(1,2))->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF047857'));
    $sheet->getStyle($A(1,3))->getFont()->setItalic(true)->setSize(10);
    $sheet->getStyle($A(1,1).':'.$A(1,3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Header Tabel
    $row = 5; // Mulai row di 5
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

    // ===== [5] RENDER DATA & SUBTOTAL (Looping BARU) =====
    foreach ($unitBuckets as $unitKey => $rowsU) {
        // Header Unit
        $sheet->setCellValue($A(1, $row), 'Unit/AFD: ' . $unitKey);
        $sheet->mergeCells($A(1,$row).':'.$lastCol.$row);
        $sheet->getStyle($A(1,$row))->applyFromArray([ 'font' => ['bold' => true, 'color' => ['argb' => 'FF065F46']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1FAE5']] ]);
        $row++;
        
        // Baris Data
        foreach ($rowsU as $r) {
            $sheet->fromArray([
                $r['tahun'], 
                $r['nama_kebun'], 
                $r['nama_unit'], 
                $r['bulan'].' '.$r['tahun'], 
                // $r['tt'] Dihapus
                (float)$r['luas_ha'], 
                (int)$r['jumlah_pohon'], 
                (float)$r['anggaran_kg'], 
                (float)$r['realisasi_kg'], 
                (int)$r['jumlah_tandan'], 
                (float)$r['jumlah_hk'], 
                (float)$r['panen_ha'], 
                $calcFreq($r) // (float)$r['frekuensi'] Diganti dengan kalkulasi
            ], NULL, $A(1, $row));
            $row++;
        }

        // Subtotal Unit
        $luas_u = sum($rowsU, 'luas_ha');
        $panen_u = sum($rowsU, 'panen_ha');
        $sheet->fromArray([
            'Jumlah ('.$unitKey.')', 
            '', '', '', // Colspan 4
            // '' Dihapus
            $luas_u, 
            sum($rowsU, 'jumlah_pohon'), 
            sum($rowsU, 'anggaran_kg'), 
            sum($rowsU, 'realisasi_kg'), 
            sum($rowsU, 'jumlah_tandan'), 
            sum($rowsU, 'jumlah_hk'), 
            $panen_u, 
            $luas_u > 0 ? $panen_u / $luas_u : 0
        ], NULL, $A(1, $row), true);
        
        // Merge cell disesuaikan (dari 5 menjadi 4)
        $sheet->mergeCells($A(1,$row).':'.$A(4,$row)); 
        $sheet->getStyle($A(1,$row).':'.$lastCol.$row)->applyFromArray([ 
            'font' => ['bold' => true], 
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFECFDF5']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF065F46']]]
        ]);
        $row++;
    }

    // Subtotal TT Dihapus

    // Grand Total
    $luas_total = sum($allData, 'luas_ha');
    $panen_total = sum($allData, 'panen_ha');
    $sheet->fromArray([
        'GRAND TOTAL', 
        '', '', '', // Colspan 4
        // '' Dihapus
        $luas_total, 
        sum($allData, 'jumlah_pohon'), 
        sum($allData, 'anggaran_kg'), 
        sum($allData, 'realisasi_kg'), 
        sum($allData, 'jumlah_tandan'), 
        sum($allData, 'jumlah_hk'), 
        $panen_total, 
        $luas_total > 0 ? $panen_total / $luas_total : 0
    ], NULL, $A(1, $row), true);
    
    // Merge cell disesuaikan (dari 5 menjadi 4)
    $sheet->mergeCells($A(1,$row).':'.$A(4,$row));
    $sheet->getStyle($A(1,$row).':'.$lastCol.$row)->applyFromArray([ 'font' => ['bold' => true, 'color' => ['argb' => 'FF065F46']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFBBF7D0']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => 'FF065F46']]] ]);
    $row++;
    
    // Formatting & Auto-size
    // Kolom digeser ke kiri 1
    // Kolom E (Luas) s/d L (Frekuensi)
    $sheet->getStyle($A(5,6).':'.$lastCol.($row-1))->getNumberFormat()->setFormatCode('#,##0.00');
    // Kolom F (Invt Pokok)
    $sheet->getStyle($A(6,6).':'.$A(6,($row-1)))->getNumberFormat()->setFormatCode('#,##0');
    // Kolom I (Jlh Tandan)
    $sheet->getStyle($A(9,6).':'.$A(9,($row-1)))->getNumberFormat()->setFormatCode('#,##0');
    // Align Right (mulai dari Kolom E 'Luas')
    $sheet->getStyle($A(5,5).':'.$lastCol.($row-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
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
    // Hapus header excel jika gagal
    header_remove('Content-Type');
    header_remove('Content-Disposition');
    header_remove('Cache-Control');
    header_remove('Pragma');
    // Kirim error sebagai JSON
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Gagal membuat file Excel: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit;
}