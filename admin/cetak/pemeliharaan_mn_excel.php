<?php
// pages/cetak/pemeliharaan_mn_excel.php
// Export Excel Pemeliharaan MN

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    exit('Unauthorized');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ===== 1. TANGKAP FILTER =====
    $f_tahun    = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int)$_GET['tahun'] : (int)date('Y');
    $f_kebun    = isset($_GET['kebun_id']) && $_GET['kebun_id'] != 0 ? (int)$_GET['kebun_id'] : null;
    $f_jenis    = isset($_GET['jenis']) ? $_GET['jenis'] : null;
    $f_hkId     = isset($_GET['hk']) && $_GET['hk'] !== '' ? (int)$_GET['hk'] : 0;
    $f_stoodId  = isset($_GET['stood_id']) && $_GET['stood_id'] != 0 ? (int)$_GET['stood_id'] : 0;
    $f_ket      = isset($_GET['ket']) ? $_GET['ket'] : '';

    // ===== 2. BUILD QUERY =====
    $where = "WHERE p.tahun = :tahun";
    $params = [':tahun' => $f_tahun];

    if ($f_jenis) { 
        $where .= " AND p.jenis_nama = :jenis"; 
        $params[':jenis'] = $f_jenis; 
    }
    
    // Filter HK (ID -> Kode)
    if ($f_hkId > 0) {
        $stmtHk = $conn->prepare("SELECT kode FROM md_tenaga WHERE id = ?");
        $stmtHk->execute([$f_hkId]);
        $kodeHk = $stmtHk->fetchColumn();

        if ($kodeHk) {
            $where .= " AND p.hk = :hk_kode";
            $params[':hk_kode'] = $kodeHk;
        } else {
            $where .= " AND 1=0"; 
        }
    }

    if ($f_kebun) { 
        $where .= " AND p.kebun_id = :kebun"; 
        $params[':kebun'] = $f_kebun; 
    }
    
    if ($f_ket) { 
        $where .= " AND p.ket LIKE :ket"; 
        $params[':ket'] = "%$f_ket%"; 
    }

    // Filter Stood
    $having = "";
    if ($f_stoodId > 0) {
        $having = "HAVING stood_id_fix = :sid";
        $params[':sid'] = $f_stoodId;
    }

    // QUERY TABLE: pemeliharaan_mn
    $sql = "SELECT 
                p.*, 
                COALESCE(NULLIF(p.stood_id,0), m.id) AS stood_id_fix,
                COALESCE(p.stood, m.nama)            AS stood_name_fix
            FROM pemeliharaan_mn p
            LEFT JOIN md_jenis_bibitmn m ON TRIM(UPPER(m.nama)) = TRIM(UPPER(p.stood))
            $where
            $having
            ORDER BY stood_name_fix ASC, p.kebun_nama ASC, p.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { exit("DB Error: " . $e->getMessage()); }

// 3. SETUP SPREADSHEET
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap MN ' . $f_tahun);

// --- STYLING VARS (CYAN THEME) ---
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF059FD3']], 
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

// --- HEADER JUDUL ---
$sheet->mergeCells('A1:V1');
$sheet->setCellValue('A1', 'REKAPITULASI PEMELIHARAAN MN TAHUN ' . $f_tahun);
$sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:V2');
$sheet->setCellValue('A2', 'Dicetak pada: ' . date('d-m-Y H:i'));
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// --- TABLE HEADERS ---
$headers = [
    'A' => 'Stood', 
    'B' => 'Kebun',
    'C' => 'Jenis Pekerjaan',
    'D' => 'Ket',
    'E' => 'HK',
    'F' => 'Sat',
    'G' => 'Anggaran',
    'H' => 'Jan', 'I' => 'Feb', 'J' => 'Mar', 'K' => 'Apr', 'L' => 'Mei', 'M' => 'Jun',
    'N' => 'Jul', 'O' => 'Agu', 'P' => 'Sep', 'Q' => 'Okt', 'R' => 'Nov', 'S' => 'Des',
    'T' => 'Total Real',
    'U' => '+/-',
    'V' => '%'
];

$row = 4;
foreach($headers as $col => $val) {
    $sheet->setCellValue($col . $row, $val);
}
$sheet->getStyle("A$row:V$row")->applyFromArray($styleHeader);
$sheet->setAutoFilter("A$row:V$row");

// --- ISI DATA ---
$row++;
$months = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'];
$colMonths = ['H','I','J','K','L','M','N','O','P','Q','R','S'];

foreach ($rows as $r) {
    // Hitungan
    $anggaran = (float)$r['anggaran_tahun'];
    $totalReal = 0;
    foreach($months as $m) $totalReal += (float)$r[$m];
    $delta = $totalReal - $anggaran;
    $persen = $anggaran > 0 ? ($totalReal/$anggaran) : 0; 

    // Tulis Cell
    $sheet->setCellValue('A' . $row, $r['stood_name_fix']);
    $sheet->setCellValue('B' . $row, $r['kebun_nama']);
    $sheet->setCellValue('C' . $row, $r['jenis_nama']);
    $sheet->setCellValue('D' . $row, $r['ket']);
    $sheet->setCellValue('E' . $row, $r['hk']);
    $sheet->setCellValue('F' . $row, $r['satuan']);
    $sheet->setCellValue('G' . $row, $anggaran);
    
    foreach($months as $idx => $m) {
        $sheet->setCellValue($colMonths[$idx] . $row, (float)$r[$m]);
    }
    
    $sheet->setCellValue('T' . $row, $totalReal);
    $sheet->setCellValue('U' . $row, $delta);
    $sheet->setCellValue('V' . $row, $persen);

    // Styling Conditional (Merah/Hijau)
    if($delta < 0) $sheet->getStyle('U'.$row)->getFont()->setColor(new Color('16A34A')); // Hijau
    if($delta > 0) $sheet->getStyle('U'.$row)->getFont()->setColor(new Color('DC2626')); // Merah

    $row++;
}

// --- FORMATTING ---
$lastRow = $row - 1;
if($lastRow >= 5) {
    $sheet->getStyle("G5:U$lastRow")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("V5:V$lastRow")->getNumberFormat()->setFormatCode('0.00%');
    $sheet->getStyle("A4:V$lastRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

foreach(range('A','V') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// --- OUTPUT ---
$filename = 'Rekap_MN_' . $f_tahun . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;