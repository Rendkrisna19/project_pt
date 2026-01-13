<?php
// cetak/stok_barang_gudang_excel.php
// FIXED: Menggunakan PhpSpreadsheet (XLSX Asli) agar tidak error/corrupt
// Desain: Tema Cyan/Teal + Kolom Bulan/Tahun

declare(strict_types=1);
session_start();

// 1. BERSIHKAN BUFFER (PENTING AGAR FILE TIDAK RUSAK)
@ini_set('display_errors', '0');
while (ob_get_level() > 0) { @ob_end_clean(); }

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Pastikan library terload

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // --- 2. FILTER DATA ---
    $kebun_id = $_GET['kebun_id'] ?? '';
    $tahun    = $_GET['tahun'] ?? date('Y');
    $bulan    = $_GET['bulan'] ?? '';
    $jenis_barang_id = $_GET['jenis_barang_id'] ?? '';

    // --- 3. QUERY SQL ---
    $where = " WHERE 1=1 ";
    $params = [];

    if ($tahun) { $where .= " AND t.tahun = :thn"; $params[':thn'] = $tahun; }
    if ($bulan && $bulan !== 'Semua Bulan') { $where .= " AND t.bulan = :bln"; $params[':bln'] = $bulan; }
    if ($kebun_id) { $where .= " AND t.kebun_id = :kbd"; $params[':kbd'] = $kebun_id; }
    if ($jenis_barang_id) { $where .= " AND t.jenis_barang_id = :jbi"; $params[':jbi'] = $jenis_barang_id; }

    $sql = "SELECT t.*, k.nama_kebun, b.nama AS nama_barang, b.satuan
            FROM tr_stok_barang_gudang t
            LEFT JOIN md_kebun k ON t.kebun_id = k.id
            LEFT JOIN md_jenis_barang_gudang b ON t.jenis_barang_id = b.id
            $where
            ORDER BY t.tahun DESC, p
            FIELD(t.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), 
            k.nama_kebun ASC";

    $st = $conn->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. BUAT EXCEL ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Stok');

    // -- JUDUL LAPORAN --
    $sheet->setCellValue('A1', 'LAPORAN STOK BARANG GUDANG');
    $sheet->mergeCells('A1:L1'); // Merge sampai kolom L
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0891B2'); // Cyan Gelap
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // -- SUBTITLE FILTER --
    $infoFilters = [];
    if($kebun_id && !empty($rows)) $infoFilters[] = "Kebun: " . $rows[0]['nama_kebun'];
    $infoFilters[] = "Bulan: " . ($bulan ?: 'Semua Bulan');
    $infoFilters[] = "Tahun: " . $tahun;
    
    $sheet->setCellValue('A2', implode(' | ', $infoFilters));
    $sheet->mergeCells('A2:L2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setARGB('FF555555');

    // -- HEADER TABEL (Baris 4) --
    $headers = [
        'No', 'Kebun', 'Jenis Barang', 'Satuan', 'Bulan', 'Tahun',
        'Stok Awal', 'Mutasi Masuk', 'Mutasi Keluar', 'Pasokan', 'Dipakai', 'SISA'
    ];
    $colChar = 'A';
    foreach($headers as $h){
        $sheet->setCellValue($colChar.'4', $h);
        $colChar++;
    }

    // Styling Header (Cyan Theme)
    $headerRange = 'A4:L4';
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF06B6D4'); // Cyan 500
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // -- ISI DATA --
    $row = 5;
    $no = 1;

    if (empty($rows)) {
        $sheet->setCellValue('A5', 'Tidak ada data ditemukan.');
        $sheet->mergeCells('A5:L5');
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    } else {
        foreach($rows as $r){
            // Hitung Sisa
            $sisa = (float)$r['stok_awal'] + (float)$r['mutasi_masuk'] - (float)$r['mutasi_keluar'] + (float)$r['pasokan'] - (float)$r['dipakai'];

            $sheet->setCellValue('A'.$row, $no++);
            $sheet->setCellValue('B'.$row, $r['nama_kebun']);
            $sheet->setCellValue('C'.$row, $r['nama_barang']);
            $sheet->setCellValue('D'.$row, $r['satuan']);
            $sheet->setCellValue('E'.$row, $r['bulan']);
            $sheet->setCellValue('F'.$row, $r['tahun']);
            
            // Angka
            $sheet->setCellValue('G'.$row, $r['stok_awal']);
            $sheet->setCellValue('H'.$row, $r['mutasi_masuk']);
            $sheet->setCellValue('I'.$row, $r['mutasi_keluar']);
            $sheet->setCellValue('J'.$row, $r['pasokan']);
            $sheet->setCellValue('K'.$row, $r['dipakai']);
            $sheet->setCellValue('L'.$row, $sisa);

            // Warna Angka
            if($r['mutasi_masuk'] > 0) $sheet->getStyle('H'.$row)->getFont()->getColor()->setARGB('FF16A34A'); // Hijau
            if($r['mutasi_keluar'] > 0) $sheet->getStyle('I'.$row)->getFont()->getColor()->setARGB('FFDC2626'); // Merah
            if($sisa < 0) $sheet->getStyle('L'.$row)->getFont()->getColor()->setARGB('FFFF0000'); // Sisa Merah jika minus

            // Zebra Striping (Genap)
            if ($row % 2 == 0) {
                $sheet->getStyle('A'.$row.':L'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFEFF'); // Cyan Tipis
            }

            $row++;
        }
    }

    // -- FORMATTING AKHIR --
    $lastRow = $row - 1;
    if ($lastRow < 5) $lastRow = 5;

    // Border Data
    $sheet->getStyle('A5:L'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Alignment Tengah (No, Sat, Bln, Thn)
    $sheet->getStyle('A5:A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D5:F'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Format Angka (Accounting/Number)
    $sheet->getStyle('G5:L'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');

    // Highlight Kolom Sisa
    $sheet->getStyle('L5:L'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCFFAFE'); // Cyan Highlight
    $sheet->getStyle('L5:L'.$lastRow)->getFont()->setBold(true);

    // Auto Size Columns
    foreach (range('A','L') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --- 5. OUTPUT FILE ---
    $filename = "Laporan_Stok_Gudang_" . date('Ymd_His') . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo "Terjadi kesalahan saat membuat Excel: " . $e->getMessage();
}