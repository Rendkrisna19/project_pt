<?php
// admin/cetak/sp_excel.php
// Export Data Surat Peringatan

session_start();
ini_set('memory_limit', '-1');
set_time_limit(300);

@ob_end_clean();
if(ob_get_length() > 0) { ob_clean(); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // 1. QUERY DATA
    $sql = "SELECT sp.*, k.nama_lengkap, k.id_sap, k.kebun_id as nama_kebun, k.afdeling, k.status_karyawan 
            FROM data_peringatan sp
            LEFT JOIN data_karyawan k ON sp.karyawan_id = k.id
            ORDER BY sp.tanggal_sp DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. SETUP SPREADSHEET
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan SP');

    // Header Style (Merah)
    $styleHeader = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']], // Red-600
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    $headers = [
        'A' => 'No',
        'B' => 'SAP ID',
        'C' => 'Nama Karyawan',
        'D' => 'Kebun',
        'E' => 'Afdeling',
        'F' => 'Status',
        'G' => 'No Surat',
        'H' => 'Jenis SP',
        'I' => 'Tanggal SP',
        'J' => 'Masa Berlaku',
        'K' => 'Pelanggaran',
        'L' => 'Sanksi'
    ];

    foreach ($headers as $col => $text) {
        $sheet->setCellValue($col . '1', $text);
        $sheet->getStyle($col . '1')->applyFromArray($styleHeader);
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // 3. ISI DATA
    $row = 2;
    $no = 1;
    foreach ($data as $r) {
        $tglSP = $r['tanggal_sp'] ? date('d-m-Y', strtotime($r['tanggal_sp'])) : '-';
        $tglExp = $r['masa_berlaku'] ? date('d-m-Y', strtotime($r['masa_berlaku'])) : '-';

        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValueExplicit('B' . $row, $r['id_sap'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $row, $r['nama_lengkap']);
        $sheet->setCellValue('D' . $row, $r['nama_kebun']);
        $sheet->setCellValue('E' . $row, $r['afdeling']);
        $sheet->setCellValue('F' . $row, $r['status_karyawan']);
        $sheet->setCellValue('G' . $row, $r['no_surat']);
        $sheet->setCellValue('H' . $row, $r['jenis_sp']);
        $sheet->setCellValue('I' . $row, $tglSP);
        $sheet->setCellValue('J' . $row, $tglExp);
        $sheet->setCellValue('K' . $row, $r['pelanggaran']);
        $sheet->setCellValue('L' . $row, $r['sanksi']);
        $row++;
    }

    // Border Data
    $lastRow = $row - 1;
    $sheet->getStyle('A1:L' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // 4. OUTPUT
    $filename = 'Laporan_SP_' . date('YmdHis') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>