<?php
// admin/cetak/history_excel.php
session_start();
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Filter Parameters
    $limit = $_GET['limit'] ?? 1000;
    $q = trim($_GET['q'] ?? '');
    $kebun_id = $_GET['kebun_id'] ?? '';

    // Query Data History (Sesuaikan nama tabel Anda: data_history_jabatan / data_history)
    $sql = "SELECT h.*, k.nama_lengkap, k.id_sap, k.kebun_id as nama_kebun 
            FROM data_history_jabatan h 
            LEFT JOIN data_karyawan k ON h.karyawan_id = k.id 
            WHERE 1=1";
    
    $params = [];
    if ($q) {
        $sql .= " AND (k.nama_lengkap LIKE :q OR h.no_surat LIKE :q)";
        $params[':q'] = "%$q%";
    }
    if ($kebun_id) {
        $sql .= " AND h.kebun_id = :kid";
        $params[':kid'] = $kebun_id;
    }
    $sql .= " ORDER BY h.tgl_surat DESC LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Setup Spreadsheet
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle('Riwayat Jabatan');

    // Header Styles
    $styleHeader = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0E7490']], // Cyan 700
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    // Judul
    $sheet->setCellValue('A1', 'DATA RIWAYAT JABATAN & MUTASI');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Kolom Header
    $headers = ['No', 'Kebun', 'SAP ID', 'Nama Karyawan', 'Afdeling', 'No Surat', 'Tgl Surat', 'Jabatan Lama', 'Jabatan Baru', 'Keterangan'];
    $row = 3;
    $col = 'A';
    foreach($headers as $h) {
        $sheet->setCellValue($col.$row, $h);
        $col++;
    }
    $sheet->getStyle("A3:J3")->applyFromArray($styleHeader);

    // Isi Data
    $row++;
    $no = 1;
    foreach($data as $r) {
        $sheet->setCellValue('A'.$row, $no++);
        $sheet->setCellValue('B'.$row, $r['nama_kebun']);
        $sheet->setCellValue('C'.$row, $r['id_sap']); // Pastikan field ini id_sap
        $sheet->setCellValue('D'.$row, $r['nama_lengkap']);
        $sheet->setCellValue('E'.$row, $r['afdeling']);
        $sheet->setCellValue('F'.$row, $r['no_surat']);
        $sheet->setCellValue('G'.$row, $r['tgl_surat']);
        $sheet->setCellValue('H'.$row, $r['jabatan_lama']);
        $sheet->setCellValue('I'.$row, $r['jabatan_baru']);
        $sheet->setCellValue('J'.$row, $r['keterangan']);
        $row++;
    }

    // Auto Width
    foreach(range('A','J') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);

    // Output
    $filename = 'History_Jabatan_'.date('YmdHis').'.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'"');
    $writer = new Xlsx($ss);
    $writer->save('php://output');
    exit;

} catch(Exception $e) { echo "Error: ".$e->getMessage(); }