<?php
// admin/cetak/lm_biaya_excel.php
// MODIFIKASI FINAL: Sinkron dengan Web (Volume LM76, HPP, Incl/Excl)
// Output: XLSX Binary Stream (Clean Buffer)

declare(strict_types=1);
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

/* ==== 1. CLEAN OUTPUT BUFFER (CRITICAL FOR EXCEL) ==== */
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); // Suppress warnings in binary output

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

    // --- HELPER UNTUK CEK KOLOM ---
    function col_exists($pdo, $table, $col){
        $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
        $st->execute([':t'=>$table, ':c'=>$col]);
        return (bool)$st->fetchColumn();
    }
    function find_col($pdo, $table, $candidates, $default='0') {
        foreach ($candidates as $col) { if (col_exists($pdo, $table, $col)) return $col; }
        return $default;
    }

    /* ===== 2. FILTER PARAMETERS ===== */
    $unit_id  = $_GET['unit_id']  ?? '';
    $tahun    = $_GET['tahun']    ?? date('Y');
    $bulan    = $_GET['bulan']    ?? 'Semua Bulan';
    $kebun_id = $_GET['kebun_id'] ?? '';
    $q        = trim($_GET['q'] ?? '');

    /* ===== 3. LOGIKA VOLUME TBS (LM76) ===== */
    $vol_real = 0; $vol_ang = 0;
    if (col_exists($pdo, 'lm76', 'id')) {
        $col_r = find_col($pdo, 'lm76', ['prod_bi_realisasi','realisasi','prod_real','tbs_realisasi']);
        $col_a = find_col($pdo, 'lm76', ['prod_bi_anggaran','prod_bi_rkap','anggaran','rkap']);

        $wh76 = " WHERE 1=1 "; $bd76 = [];
        if ($tahun !== '')        { $wh76 .= " AND tahun=:t"; $bd76[':t'] = $tahun; }
        if ($bulan !== 'Semua Bulan' && $bulan !== '') { $wh76 .= " AND bulan=:b"; $bd76[':b'] = $bulan; }
        if ($unit_id !== '')      { $wh76 .= " AND unit_id=:u"; $bd76[':u'] = $unit_id; }
        if ($kebun_id !== '')     { $wh76 .= " AND kebun_id=:k"; $bd76[':k'] = $kebun_id; }

        $sqlVol = "SELECT SUM(COALESCE($col_r,0)) as v_real, SUM(COALESCE($col_a,0)) as v_ang FROM lm76 $wh76";
        $stVol = $pdo->prepare($sqlVol);
        $stVol->execute($bd76);
        $dVol = $stVol->fetch(PDO::FETCH_ASSOC);
        $vol_real = (float)($dVol['v_real'] ?? 0);
        $vol_ang  = (float)($dVol['v_ang'] ?? 0);
    }

    /* ===== 4. QUERY DATA BIAYA ===== */
    $where = " WHERE 1=1 "; $bind = [];
    if ($unit_id !== '')  { $where .= " AND b.unit_id=:uid"; $bind[':uid'] = $unit_id; }
    if ($tahun !== '')    { $where .= " AND b.tahun=:thn";  $bind[':thn'] = $tahun; }
    if ($bulan !== 'Semua Bulan' && $bulan !== '') { $where .= " AND b.bulan=:bln";  $bind[':bln'] = $bulan; }
    if ($kebun_id !== '') { $where .= " AND b.kebun_id=:kid"; $bind[':kid'] = $kebun_id; }
    if ($q !== '') {
        $where .= " AND (b.alokasi LIKE :kw OR b.uraian_pekerjaan LIKE :kw)";
        $bind[':kw'] = "%$q%";
    }

    $sql = "SELECT b.*, u.nama_unit, kb.nama_kebun
            FROM lm_biaya b
            LEFT JOIN units u ON u.id=b.unit_id
            LEFT JOIN md_kebun kb ON kb.id=b.kebun_id
            $where
            ORDER BY b.tahun DESC,
             FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
             b.alokasi ASC";
    $st = $pdo->prepare($sql);
    foreach($bind as $k=>$v) $st->bindValue($k,$v);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $first = $rows[0] ?? [];

    /* ===== 5. START SPREADSHEET ===== */
    $ss = new Spreadsheet();
    $ss->getProperties()->setTitle('Laporan Biaya & HPP');
    $sheet = $ss->getActiveSheet();
    $A = fn($c,$r) => Coordinate::stringFromColumnIndex($c).$r;

    // --- Judul ---
    $sheet->setCellValue('A1', 'LAPORAN BIAYA & HARGA POKOK');
    $sheet->mergeCells('A1:J1'); // Asumsi kolom sampai J
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF059669'); // Emerald Green
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // --- Subtitle Filters ---
    $info = [];
    if($kebun_id) $info[] = 'Kebun: '.($first['nama_kebun']??'');
    if($unit_id)  $info[] = 'Unit: '.($first['nama_unit']??'');
    $info[] = 'Bulan: '.($bulan?:'Semua');
    $info[] = 'Tahun: '.$tahun;
    $sheet->setCellValue('A2', implode(' | ', $info));
    $sheet->mergeCells('A2:J2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setARGB('FF065F46');

    // --- Table Headers ---
    $headers = ['Kebun','Unit/Defisi','No. Alokasi','Uraian Pekerjaan','Bulan','Tahun','Anggaran','Realisasi','+/- Biaya','%'];
    $row = 4;
    foreach($headers as $k=>$v) $sheet->setCellValue($A($k+1, $row), $v);
    
    // Style Header (Biru Cyan seperti Web)
    $sheet->getStyle("A$row:J$row")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle("A$row:J$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0097E6'); // Cyan Blue
    $sheet->getStyle("A$row:J$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // --- Data Variables ---
    $sumAnggaran = 0; $sumRealisasi = 0;
    $pupukAng = 0;    $pupukReal = 0;

    $row++; 

    // --- BARIS 1: VOLUME TBS (Grey) ---
    $diffVol = $vol_real - $vol_ang;
    $pctVol  = ($vol_ang!=0) ? ($diffVol/$vol_ang) : 0; // Excel pake fraction 0.xx

    $sheet->setCellValue("B$row", "- Produksi TBS (KG)");
    $sheet->setCellValue("G$row", $vol_ang);
    $sheet->setCellValue("H$row", $vol_real);
    $sheet->setCellValue("I$row", $diffVol);
    $sheet->setCellValue("J$row", $pctVol);
    
    // Style Baris Volume
    $sheet->getStyle("A$row:J$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB'); // Grey
    $sheet->getStyle("A$row:J$row")->getFont()->setBold(true)->setItalic(true);
    $row++;

    // --- LOOP DATA BIAYA ---
    if(empty($rows)){
        $sheet->setCellValue("A$row", "Tidak ada data.");
        $sheet->mergeCells("A$row:J$row");
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
    } else {
        foreach($rows as $r){
            $ang = (float)$r['rencana_bi'];
            $rea = (float)$r['realisasi_bi'];
            $d   = $rea - $ang;
            $p   = ($ang!=0) ? ($d/$ang) : 0;

            // Isi Data
            $sheet->setCellValue("A$row", $r['nama_kebun']??'-');
            $sheet->setCellValue("B$row", $r['nama_unit']??'-');
            $sheet->setCellValue("C$row", $r['alokasi']??'-');
            $sheet->setCellValue("D$row", $r['uraian_pekerjaan']??'-');
            $sheet->setCellValue("E$row", $r['bulan']);
            $sheet->setCellValue("F$row", $r['tahun']);
            $sheet->setCellValue("G$row", $ang);
            $sheet->setCellValue("H$row", $rea);
            $sheet->setCellValue("I$row", $d);
            $sheet->setCellValue("J$row", $p);

            // Warna Text Variance
            if($d < 0) $sheet->getStyle("I$row")->getFont()->getColor()->setARGB('FF16A34A'); // Green text
            else       $sheet->getStyle("I$row")->getFont()->getColor()->setARGB('FFDC2626'); // Red text
            
            // Logika Summary
            $sumAnggaran  += $ang;
            $sumRealisasi += $rea;
            
            $txt = strtolower(($r['alokasi']??'').' '.($r['uraian_pekerjaan']??''));
            if (strpos($txt, 'pupuk') !== false){
                $pupukAng  += $ang;
                $pupukReal += $rea;
            }

            $row++;
        }
    }

    // --- CALCULATE EXCL & HPP ---
    $exclAng  = $sumAnggaran - $pupukAng;
    $exclReal = $sumRealisasi - $pupukReal;

    $hppInclA = ($vol_ang > 0) ? ($sumAnggaran / $vol_ang) : 0;
    $hppInclR = ($vol_real > 0)? ($sumRealisasi / $vol_real) : 0;
    $hppExclA = ($vol_ang > 0) ? ($exclAng / $vol_ang) : 0;
    $hppExclR = ($vol_real > 0)? ($exclReal / $vol_real) : 0;

    // --- FOOTER SECTION ---
    
    // Helper Footer
    $printFooter = function($title, $a, $r, $bg, $isHPP=false) use ($sheet, &$row) {
        $d = $r - $a;
        $p = ($a!=0) ? ($d/$a) : 0;

        $sheet->setCellValue("F$row", $title); 
        $sheet->mergeCells("A$row:F$row"); // Merge label sampai kolom F
        $sheet->getStyle("F$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->setCellValue("G$row", $a);
        $sheet->setCellValue("H$row", $r);
        $sheet->setCellValue("I$row", $d);
        $sheet->setCellValue("J$row", $p);

        // Style Row
        $style = $sheet->getStyle("A$row:J$row");
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
        
        // HPP Formatting (2 desimal)
        if($isHPP){
             $sheet->getStyle("G$row:I$row")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $row++;
    };

    // 1. Total Incl (Grey Light)
    $printFooter('Jlh By Tanaman Incl. Pemupukan', $sumAnggaran, $sumRealisasi, 'FFF3F4F6');

    // 2. Total Excl (Grey Light)
    $printFooter('Jlh By Tanaman Excl. Pemupukan', $exclAng, $exclReal, 'FFF3F4F6');

    // 3. HPP Incl (Green - HIJAU)
    $printFooter('Harga Pokok Incl. Pemupukan', $hppInclA, $hppInclR, 'FF86EFAC', true);

    // 4. HPP Excl (Green - HIJAU)
    $printFooter('Harga Pokok Excl. Pemupukan', $hppExclA, $hppExclR, 'FF86EFAC', true);

    // --- FINISHING STYLES ---
    $lastRow = $row - 1;
    
    // Border All
    $sheet->getStyle("A4:J$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Number Format (Accounting)
    $sheet->getStyle("G5:I$lastRow")->getNumberFormat()->setFormatCode('#,##0');
    // Percent Format
    $sheet->getStyle("J5:J$lastRow")->getNumberFormat()->setFormatCode('0.00%');
    
    // Alignment
    $sheet->getStyle("E5:F$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Auto Size Column
    foreach(range('A','J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --- OUTPUT ---
    $filename = 'Laporan_Biaya_HPP_'.date('YmdHis').'.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($ss);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Error generating Excel: " . $e->getMessage();
}