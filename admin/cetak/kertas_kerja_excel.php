<?php
// admin/cetak/kertas_kerja_excel.php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    http_response_code(403); exit('Unauthorized'); 
}

// Clear Buffer
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }
error_reporting(E_ALL); // Debugging

require_once '../../config/database.php';
// Pastikan path vendor autoload benar (sesuaikan jika beda folder)
require_once '../../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill, NumberFormat, Color};
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // 1. Tangkap Filter (Casting ke INT untuk keamanan)
    $unit_id  = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;
    $kebun_id = isset($_GET['kebun_id']) ? (int)$_GET['kebun_id'] : 0;
    $tahun    = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
    $bulan    = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');

    if (empty($unit_id) || empty($kebun_id)) die("Unit ID dan Kebun ID wajib diisi.");

    // 2. Ambil Info Header (Unit & Kebun) secara relasional
    $stmtInfo = $pdo->prepare("SELECT u.nama_unit, k.nama_kebun 
                               FROM units u 
                               LEFT JOIN md_kebun k ON u.kebun_id = k.id 
                               WHERE u.id = ?");
    $stmtInfo->execute([$unit_id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    
    $nama_unit  = $info ? $info['nama_unit'] : '-';
    $nama_kebun = $info ? $info['nama_kebun'] : '-';

    $nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $periode = strtoupper($nama_bulan[$bulan] . ' ' . $tahun);

    // 3. Ambil Data (Sama logic dengan CRUD List & PDF)
    
    // A. Master Pekerjaan
    $master = $pdo->query("SELECT * FROM md_jenis_pekerjaan_kertas_kerja WHERE is_active=1 ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC);

    // B. Rencana (Plano) - Filter by Kebun & Unit
    $sqlPlan = "SELECT p.*, m.nama as nama_job, m.kategori, m.satuan as satuan_def
                FROM tr_kertas_kerja_plano p
                JOIN md_jenis_pekerjaan_kertas_kerja m ON m.id = p.jenis_pekerjaan_id
                WHERE p.kebun_id = :k AND p.unit_id = :u AND p.bulan = :b AND p.tahun = :t
                ORDER BY p.blok_rencana ASC";
    $st = $pdo->prepare($sqlPlan);
    $st->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':b'=>$bulan, ':t'=>$tahun]);
    $plans = $st->fetchAll(PDO::FETCH_ASSOC);

    $planGroup = [];
    foreach($plans as $p) $planGroup[$p['jenis_pekerjaan_id']][] = $p;

    // C. Realisasi Harian (Gunakan SPRINTF agar bulan 1 dan 2 terbaca dengan benar)
    $tglStart = sprintf('%04d-%02d-01', $tahun, $bulan);
    $tglEnd   = date("Y-m-t", strtotime($tglStart));
    $daysInMonth = (int)date('t', strtotime($tglStart));

    $sqlDaily = "SELECT kertas_kerja_plano_id, DAY(tanggal) as hari, SUM(fisik) as val 
                 FROM tr_kertas_kerja_harian 
                 WHERE kebun_id=:k AND unit_id=:u AND tanggal BETWEEN :s AND :e
                 GROUP BY kertas_kerja_plano_id, tanggal";
    $st2 = $pdo->prepare($sqlDaily);
    $st2->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':s'=>$tglStart, ':e'=>$tglEnd]);
    $dailies = $st2->fetchAll(PDO::FETCH_ASSOC);

    $dailyMap = [];
    foreach($dailies as $d) $dailyMap[$d['kertas_kerja_plano_id']][$d['hari']] = $d['val'];


    // ===== BUILD EXCEL =====
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle(substr("KK_" . $nama_unit, 0, 30)); // Max 31 chars

    // Helper Column Letter
    $colBlok = 'A';
    $colRenc = 'B';
    $colSat  = 'C';
    // Column Days D ... (sesuai jumlah hari)
    $startDayColIndex = 4; // D
    $lastDayColIndex = $startDayColIndex + $daysInMonth - 1;
    $colTotal = Coordinate::stringFromColumnIndex($lastDayColIndex + 1);
    $colVar = Coordinate::stringFromColumnIndex($lastDayColIndex + 2);
    $lastCol = $colVar; // Last Column Letter

    // --- HEADERS ---
    // Title
    $sheet->setCellValue('A1', 'KERTAS KERJA REALISASI HARIAN');
    $sheet->setCellValue('A2', "KEBUN: $nama_kebun | UNIT: $nama_unit | PERIODE: $periode");
    $sheet->mergeCells("A1:{$lastCol}1");
    $sheet->mergeCells("A2:{$lastCol}2");
    
    $styleTitle = [
        'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF065F46']], // Hijau Tua
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];
    $sheet->getStyle("A1:A2")->applyFromArray($styleTitle);

    // Table Header Row 1
    $row = 4;
    $sheet->setCellValue("A$row", "DATA RENCANA");
    $sheet->mergeCells("A$row:C$row");
    
    $colD = Coordinate::stringFromColumnIndex($startDayColIndex);
    $colLastDay = Coordinate::stringFromColumnIndex($lastDayColIndex);
    $sheet->setCellValue("{$colD}$row", "REALISASI HARIAN");
    $sheet->mergeCells("{$colD}$row:{$colLastDay}$row");

    $sheet->setCellValue("{$colTotal}$row", "TOTAL");
    $sheet->mergeCells("{$colTotal}$row:{$colTotal}".($row+1)); // Merge down
    
    $sheet->setCellValue("{$colVar}$row", "+/-");
    $sheet->mergeCells("{$colVar}$row:{$colVar}".($row+1));

    // Table Header Row 2
    $row2 = 5;
    $sheet->setCellValue("A$row2", "BLOK");
    $sheet->setCellValue("B$row2", "RENCANA");
    $sheet->setCellValue("C$row2", "SAT");

    // Days Header (1..31)
    for($i=1; $i<=$daysInMonth; $i++) {
        $colIdx = $startDayColIndex + ($i - 1);
        $colLet = Coordinate::stringFromColumnIndex($colIdx);
        $sheet->setCellValue("{$colLet}{$row2}", $i);
        
        // Color Sunday Header (Diperbaiki pakai sprintf format)
        $date = sprintf('%04d-%02d-%02d', $tahun, $bulan, $i);
        if(date('w', strtotime($date)) == 0) {
            $sheet->getStyle("{$colLet}{$row2}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFCDD2']], // Merah muda
                'font' => ['color' => ['argb' => 'FFB71C1C']]
            ]);
        }
    }

    // Style Header Utama (Hijau PTPN)
    $styleHeaderMain = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF065F46']], // Hijau
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]]
    ];
    $sheet->getStyle("A4:{$lastCol}4")->applyFromArray($styleHeaderMain);

    // Style Sub Header (Putih/Abu)
    $styleHeaderSub = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FF374151']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF065F46']]]
    ];
    $sheet->getStyle("A5:{$lastCol}5")->applyFromArray($styleHeaderSub);

    // --- BODY DATA ---
    $currRow = 6;

    if (empty($master)) {
        $sheet->setCellValue("A$currRow", "Data Master Kosong");
        $currRow++;
    } else {
        foreach($master as $m) {
            $jid = $m['id'];
            $catMaster = strtoupper($m['kategori'] ?? 'FISIK');
            
            // Render Group Header (Nama Pekerjaan)
            $sheet->setCellValue("A$currRow", $m['nama']);
            $sheet->mergeCells("A$currRow:{$lastCol}$currRow");
            $sheet->getStyle("A$currRow")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF155E75']], // Cyan Dark
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F9FF']], // Cyan Light
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ]);
            $currRow++;

            $items = $planGroup[$jid] ?? [];
            
            // Penampung Subtotal
            $recaps = [
                'FISIK'    => ['rencana'=>0, 'real'=>0, 'days'=>array_fill(1,32,0), 'sat'=>''],
                'TENAGA'   => ['rencana'=>0, 'real'=>0, 'days'=>array_fill(1,32,0), 'sat'=>'HK'],
                'KIMIA'    => ['rencana'=>0, 'real'=>0, 'days'=>array_fill(1,32,0), 'sat'=>'KG'],
                'CAMPURAN' => ['rencana'=>0, 'real'=>0, 'days'=>array_fill(1,32,0), 'sat'=>'']
            ];
            $hasData = false;

            if(!empty($items)) {
                $hasData = true;
                foreach($items as $row) {
                    $pid = $row['id'];
                    $rencana = (float)$row['fisik_rencana'];
                    $satuan = $row['satuan_rencana'];
                    $blok = $row['blok_rencana'];
                    $totalRowReal = 0;

                    // Tentukan Kategori untuk Subtotal
                    $catKey = 'FISIK';
                    $satUp = strtoupper($satuan);
                    if ($catMaster == 'TENAGA' || $satUp == 'HK') $catKey = 'TENAGA';
                    else if ($catMaster == 'KIMIA' || in_array($satUp, ['KG','L','LITER','LTR','GR'])) $catKey = 'KIMIA';
                    else if ($catMaster == 'CAMPURAN') $catKey = 'CAMPURAN';

                    // Accumulate Rencana
                    $recaps[$catKey]['rencana'] += $rencana;
                    $recaps[$catKey]['sat'] = $satuan; // Last sat wins

                    // Tulis Kolom Kiri
                    $sheet->setCellValue("A$currRow", $blok);
                    $sheet->setCellValue("B$currRow", $rencana);
                    $sheet->setCellValue("C$currRow", $satuan);

                    // Loop Hari
                    for($d=1; $d<=$daysInMonth; $d++) {
                        $colIdx = $startDayColIndex + ($d - 1);
                        $colLet = Coordinate::stringFromColumnIndex($colIdx);
                        
                        $val = (float)($dailyMap[$pid][$d] ?? 0);
                        $totalRowReal += $val;
                        
                        // Accumulate Daily Subtotal
                        $recaps[$catKey]['days'][$d] += $val;

                        if($val != 0) {
                            $sheet->setCellValue("{$colLet}{$currRow}", $val);
                        } else {
                            $sheet->setCellValue("{$colLet}{$currRow}", '-'); // Tanda strip biar rapi
                            $sheet->getStyle("{$colLet}{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }

                        // Style Sunday Column Body
                        $date = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
                        if(date('w', strtotime($date)) == 0) {
                            $sheet->getStyle("{$colLet}{$currRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF1F2');
                        }
                    }

                    // Tulis Total Row
                    $recaps[$catKey]['real'] += $totalRowReal;
                    $var = $totalRowReal - $rencana;

                    $sheet->setCellValue("{$colTotal}{$currRow}", $totalRowReal);
                    $sheet->setCellValue("{$colVar}{$currRow}", $var);

                    // Style Row Variance
                    if($var < 0) $sheet->getStyle("{$colVar}{$currRow}")->getFont()->setColor(new Color(Color::COLOR_RED));
                    else $sheet->getStyle("{$colVar}{$currRow}")->getFont()->setColor(new Color(Color::COLOR_DARKGREEN));

                    $currRow++;
                }
            }

            // --- RENDER SUBTOTAL ROWS ---
            if($hasData) {
                foreach($recaps as $key => $data) {
                    if($data['rencana'] > 0 || $data['real'] > 0) {
                        $label = "TOTAL " . $key;
                        
                        // Style per kategori
                        $bgRow = 'FFF0FDFA'; // Hijau muda (Fisik)
                        if($key=='TENAGA') $bgRow = 'FFFFFBEB'; // Kuning muda
                        if($key=='KIMIA') $bgRow = 'FFEFF6FF'; // Biru muda
                        if($key=='CAMPURAN') $bgRow = 'FFFAF5FF'; // Ungu muda

                        $sheet->setCellValue("A$currRow", $label);
                        $sheet->setCellValue("B$currRow", $data['rencana']);
                        $sheet->setCellValue("C$currRow", $data['sat']);

                        $subTotalReal = 0;
                        for($d=1; $d<=$daysInMonth; $d++) {
                            $colIdx = $startDayColIndex + ($d - 1);
                            $colLet = Coordinate::stringFromColumnIndex($colIdx);
                            $val = $data['days'][$d];
                            $subTotalReal += $val;
                            $sheet->setCellValue("{$colLet}{$currRow}", ($val==0 ? '-' : $val));
                        }

                        $subVar = $subTotalReal - $data['rencana'];
                        $sheet->setCellValue("{$colTotal}{$currRow}", $subTotalReal);
                        $sheet->setCellValue("{$colVar}{$currRow}", $subVar);

                        // Apply Style Subtotal Row
                        $sheet->getStyle("A$currRow:{$lastCol}$currRow")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgRow]],
                            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]]
                        ]);
                        $sheet->getStyle("A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Label rata kanan

                        $currRow++;
                    }
                }
            }
        }
    }

    // --- FINAL FORMATTING ---
    // Border All Data
    $lastRowData = $currRow - 1;
    $sheet->getStyle("A4:{$lastCol}{$lastRowData}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A4:{$lastCol}{$lastRowData}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);

    // Number Format
    // Kolom Rencana, Harian, Total, Var
    // Range: B6 : LastCol LastRow
    $sheet->getStyle("B6:{$lastCol}{$lastRowData}")->getNumberFormat()->setFormatCode('#,##0.00');

    // Auto Size Columns
    $sheet->getColumnDimension('A')->setWidth(25); // Blok agak lebar
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(8);
    for($i=1; $i<=$daysInMonth; $i++) {
        $colIdx = $startDayColIndex + ($i - 1);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setWidth(5); // Kolom hari kecil
    }
    $sheet->getColumnDimension($colTotal)->setWidth(12);
    $sheet->getColumnDimension($colVar)->setWidth(12);


    // ===== OUTPUT FILE =====
    $filename = 'KK_' . preg_replace('/[^a-zA-Z0-9]/', '_', $nama_unit) . '_' . date('Ymd_His') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($ss);
    $writer->save('php://output');
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Gagal Export Excel: " . $e->getMessage();
    exit;
}