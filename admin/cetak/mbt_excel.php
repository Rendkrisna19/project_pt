<?php
// admin/cetak/mbt_excel.php
// MODIFIKASI: Sesuai struktur tabel 'data_karyawan' di data_karyawan_crud.php

declare(strict_types=1);
session_start();

// 1. CLEAN BUFFER (PENTING AGAR FILE TIDAK CORRUPT)
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
    $db  = new Database();
    $pdo = $db->getConnection();

    // 2. AMBIL PARAMETER FILTER
    $year     = $_GET['year'] ?? date('Y');
    $afdeling = $_GET['afdeling'] ?? '';
    $q        = trim($_GET['q'] ?? '');

    // 3. QUERY DATA (DISESUAIKAN DENGAN TABLE 'data_karyawan')
    // Logic Query sama dengan 'list_mbt' di data_karyawan_crud.php
    $sql = "SELECT id_sap, nama_lengkap, kebun_id, afdeling, jabatan_real, tmt_kerja, tmt_mbt, status_karyawan 
            FROM data_karyawan 
            WHERE tmt_mbt IS NOT NULL";
            
    $params = [];

    // Filter Tahun (Berdasarkan tahun jatuh tempo MBT)
    if ($year) {
        $sql .= " AND YEAR(tmt_mbt) = :year";
        $params[':year'] = $year;
    }

    if ($afdeling) {
        $sql .= " AND afdeling = :afdeling";
        $params[':afdeling'] = $afdeling;
    }

    if ($q) {
        $sql .= " AND (nama_lengkap LIKE :q OR id_sap LIKE :q OR jabatan_real LIKE :q)";
        $params[':q'] = "%$q%";
    }

    $sql .= " ORDER BY tmt_mbt ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. SETUP SPREADSHEET
    $ss = new Spreadsheet();
    $ss->getProperties()->setTitle('Monitoring MBT');
    $sheet = $ss->getActiveSheet();

    // --- HEADER JUDUL ---
    $sheet->setCellValue('A1', 'MONITORING MASA BERLAKU TUNJANGAN (MBT)');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0891B2'); // Cyan 600
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // --- SUBTITLE ---
    $info = "Tahun: $year | Afdeling: " . ($afdeling ?: 'Semua') . " | Dicetak: " . date('d-m-Y H:i');
    $sheet->setCellValue('A2', $info);
    $sheet->mergeCells('A2:J2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setARGB('FF0E7490'); // Cyan 700

    // --- TABLE HEADERS ---
    $headers = ['No', 'SAP ID', 'Nama Lengkap', 'Kebun', 'Afdeling', 'Jabatan', 'TMT Kerja', 'Jatuh Tempo (MBT)', 'Sisa Waktu', 'Status'];
    $colChar = 'A';
    $row = 4;

    foreach ($headers as $h) {
        $sheet->setCellValue($colChar . $row, $h);
        $colChar++;
    }

    // Style Header Table
    $sheet->getStyle("A$row:J$row")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle("A$row:J$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF06B6D4'); // Cyan 500
    $sheet->getStyle("A$row:J$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // 5. ISI DATA & LOGIC WARNA
    $row++;
    $no = 1;
    $today = new DateTime();

    foreach ($data as $r) {
        // Hitung Sisa Hari
        $tmtMbtDate = new DateTime($r['tmt_mbt']);
        $diff = $today->diff($tmtMbtDate);
        $days = (int)$diff->format('%r%a'); // %r memberi tanda - jika lewat

        // Tentukan Status Text
        $statusText = '';
        if ($days < 0) $statusText = "Lewat " . abs($days) . " Hari";
        else $statusText = "$days Hari Lagi";

        // Format Tanggal
        $tglKerja = $r['tmt_kerja'] ? date('d-M-Y', strtotime($r['tmt_kerja'])) : '-';
        $tglMbt   = $r['tmt_mbt'] ? date('d-M-Y', strtotime($r['tmt_mbt'])) : '-';

        // Isi Cell
        $sheet->setCellValue("A$row", $no++);
        $sheet->setCellValue("B$row", $r['id_sap']);      // Sesuai data_karyawan
        $sheet->setCellValue("C$row", $r['nama_lengkap']);// Sesuai data_karyawan
        $sheet->setCellValue("D$row", $r['kebun_id']);    // Sesuai data_karyawan (Text)
        $sheet->setCellValue("E$row", $r['afdeling']);
        $sheet->setCellValue("F$row", $r['jabatan_real']);
        $sheet->setCellValue("G$row", $tglKerja);
        $sheet->setCellValue("H$row", $tglMbt);
        $sheet->setCellValue("I$row", $statusText);
        $sheet->setCellValue("J$row", $r['status_karyawan']);

        // LOGIC WARNA (CELL SISA WAKTU & TMT MBT)
        $sheet->getStyle("G$row:J$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Warna Background Jatuh Tempo (Orange Hint)
        $sheet->getStyle("H$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFEDD5');
        $sheet->getStyle("H$row")->getFont()->getColor()->setARGB('FFC2410C');

        // Logic Warna Badge (Kolom I / Sisa Waktu)
        if ($days < 0) {
            // Merah (Expired)
            $sheet->getStyle("I$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDC2626'); 
            $sheet->getStyle("I$row")->getFont()->getColor()->setARGB('FFFFFFFF'); 
        } elseif ($days <= 30) {
            // Merah Muda (Warning Keras)
            $sheet->getStyle("I$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEE2E2'); 
            $sheet->getStyle("I$row")->getFont()->getColor()->setARGB('FF991B1B'); 
        } elseif ($days <= 90) {
            // Orange (Warning)
            $sheet->getStyle("I$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFEDD5'); 
            $sheet->getStyle("I$row")->getFont()->getColor()->setARGB('FF9A3412'); 
        } else {
            // Hijau (Aman)
            $sheet->getStyle("I$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCFCE7'); 
            $sheet->getStyle("I$row")->getFont()->getColor()->setARGB('FF166534'); 
        }

        $row++;
    }

    // 6. FINISHING (BORDER & AUTO SIZE)
    $lastRow = $row - 1;
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FFCBD5E1'],
            ],
        ],
    ];
    $sheet->getStyle("A4:J$lastRow")->applyFromArray($styleArray);

    foreach (range('A', 'J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Output
    $filename = 'Monitoring_MBT_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($ss);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>