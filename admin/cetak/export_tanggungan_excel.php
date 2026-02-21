<?php
// admin/cetak/export_tanggungan_excel.php
// Export Data Tanggungan Lengkap

session_start();
// Paksa memori besar
ini_set('memory_limit', '-1');
set_time_limit(300);

// Clean Output Buffer
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

    // 1. QUERY DATA (Sesuaikan dengan Logic Tampilan)
    // Join ke tabel karyawan untuk ambil Nama & SAP ID
    $sql = "SELECT t.*, k.nama_lengkap as nama_karyawan, k.id_sap, k.kebun_id as nama_kebun, k.afdeling
            FROM data_keluarga t
            LEFT JOIN data_karyawan k ON t.karyawan_id = k.id
            ORDER BY k.nama_lengkap ASC, t.nama_batih ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. SETUP EXCEL
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Tanggungan');

    // Header Style
    $styleHeader = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0891b2']], // Cyan-600
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    // Kolom Header
    $headers = [
        'A' => 'No',
        'B' => 'SAP ID Karyawan',
        'C' => 'Nama Karyawan',
        'D' => 'Nama Keluarga',
        'E' => 'Hubungan',
        'F' => 'Kebun',
        'G' => 'Afdeling',
        'H' => 'Tempat Lahir',
        'I' => 'Tanggal Lahir',
        'J' => 'Pendidikan',
        'K' => 'Pekerjaan',
        'L' => 'Keterangan'
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
        $sheet->setCellValue('A' . $row, $no++);
        $sheet->setCellValueExplicit('B' . $row, $r['id_sap'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $row, $r['nama_karyawan']);
        $sheet->setCellValue('D' . $row, $r['nama_batih']);
        $sheet->setCellValue('E' . $row, $r['hubungan']);
        $sheet->setCellValue('F' . $row, $r['nama_kebun']);
        $sheet->setCellValue('G' . $row, $r['afdeling']);
        $sheet->setCellValue('H' . $row, $r['tempat_lahir']);
        $sheet->setCellValue('I' . $row, $r['tanggal_lahir']);
        $sheet->setCellValue('J' . $row, $r['pendidikan_terakhir']);
        $sheet->setCellValue('K' . $row, $r['pekerjaan']);
        $sheet->setCellValue('L' . $row, $r['keterangan']);
        $row++;
    }

    // Border Data
    $lastRow = $row - 1;
    $sheet->getStyle('A1:L' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // 4. OUTPUT DOWNLOAD
    $filename = 'Laporan_Tanggungan_' . date('YmdHis') . '.xlsx';
    
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