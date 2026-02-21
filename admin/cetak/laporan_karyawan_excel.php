<?php
// admin/cetak/laporan_karyawan_excel.php
// EXPORT DATA LENGKAP 30 KOLOM (Sesuai Struktur Database & Template Import)

session_start();
// Paksa memori besar dan waktu eksekusi lama untuk data banyak
ini_set('memory_limit', '-1');
set_time_limit(300);

// Bersihkan Buffer Output agar file Excel tidak korup
@ob_end_clean();
if(ob_get_length() > 0) { ob_clean(); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // =========================================================
    // 1. AMBIL FILTER DARI URL (LOGIC SAMA DENGAN WEB/PDF)
    // =========================================================
    $viewType = $_GET['view'] ?? 'active';
    $q        = trim($_GET['q'] ?? '');
    $kebun    = $_GET['kebun'] ?? '';
    $afdeling = $_GET['afdeling'] ?? '';

    $where = " WHERE 1=1 ";
    $params = [];

    // Filter Status (Aktif/Pensiun)
    if ($viewType === 'pension') {
        $where .= " AND tmt_pensiun <= CURDATE() ";
        $filePrefix = "DATA_KARYAWAN_PENSIUN";
    } else {
        $where .= " AND (tmt_pensiun > CURDATE() OR tmt_pensiun IS NULL) ";
        $filePrefix = "DATA_KARYAWAN_AKTIF";
    }

    // Filter Pencarian
    if ($q) {
        $where .= " AND (nama_lengkap LIKE :q OR id_sap LIKE :q OR nik_ktp LIKE :q) ";
        $params[':q'] = "%$q%";
    }
    // Filter Kebun
    if ($kebun) {
        $where .= " AND kebun_id = :kebun ";
        $params[':kebun'] = $kebun;
    }
    // Filter Afdeling
    if ($afdeling) {
        $where .= " AND afdeling = :afd ";
        $params[':afd'] = $afdeling;
    }

    $sql = "SELECT * FROM data_karyawan $where ORDER BY nama_lengkap ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =========================================================
    // 2. SETUP SPREADSHEET & HEADER (30 KOLOM)
    // =========================================================
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Export');

    $headers = [
        'A' => 'ID SAP',
        'B' => 'Old Pers No',
        'C' => 'Nama Lengkap',
        'D' => 'NIK KTP',
        'E' => 'Gender (L/P)',
        'F' => 'Tempat Lahir',
        'G' => 'Tanggal Lahir',
        'H' => 'Person Grade',
        'I' => 'Golongan PHDP',
        'J' => 'Status Keluarga',
        'K' => 'Jabatan SAP',
        'L' => 'Jabatan Real',
        'M' => 'Nama Kebun',          // Text
        'N' => 'Afdeling/Unit',
        'O' => 'Status Karyawan',
        'P' => 'TMT Masuk Kerja',
        'Q' => 'TMT MBT',
        'R' => 'TMT Pensiun',
        'S' => 'Tax ID (NPWP)',
        'T' => 'BPJS ID',
        'U' => 'Jamsostek ID',
        'V' => 'Nama Bank',
        'W' => 'No Rekening',
        'X' => 'Nama Pemilik Rekening',
        'Y' => 'No HP',
        'Z' => 'Agama',
        'AA'=> 'Status Pajak (PTKP)',
        'AB'=> 'Pendidikan Terakhir',
        'AC'=> 'Jurusan',
        'AD'=> 'Institusi Kampus'
    ];

    // Style Header (Cyan Background)
    $styleHeader = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0e7490']], 
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    foreach ($headers as $col => $txt) {
        $sheet->setCellValue($col . '1', $txt);
        $sheet->getStyle($col . '1')->applyFromArray($styleHeader);
    }
    $sheet->getRowDimension(1)->setRowHeight(25);

    // =========================================================
    // 3. ISI DATA (LOOPING)
    // =========================================================
    $row = 2;
    $fmtDate = fn($d) => ($d && $d != '0000-00-00') ? $d : ''; // Helper date

    foreach ($data as $d) {
        // PENTING: Gunakan setCellValueExplicit untuk angka panjang (NIK, Rekening) agar jadi Text
        
        $sheet->setCellValueExplicit('A' . $row, $d['id_sap'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B' . $row, $d['old_pers_no'], DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $row, strtoupper($d['nama_lengkap']));
        $sheet->setCellValueExplicit('D' . $row, $d['nik_ktp'], DataType::TYPE_STRING);
        $sheet->setCellValue('E' . $row, $d['gender']);
        $sheet->setCellValue('F' . $row, $d['tempat_lahir']);
        $sheet->setCellValue('G' . $row, $fmtDate($d['tanggal_lahir']));
        
        $sheet->setCellValueExplicit('H' . $row, $d['person_grade'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('I' . $row, $d['phdp_golongan'], DataType::TYPE_STRING);
        $sheet->setCellValue('J' . $row, $d['s_kel']);
        
        $sheet->setCellValue('K' . $row, $d['jabatan_sap']);
        $sheet->setCellValue('L' . $row, $d['jabatan_real']);
        $sheet->setCellValue('M' . $row, $d['kebun_id']); 
        $sheet->setCellValue('N' . $row, $d['afdeling']);
        $sheet->setCellValue('O' . $row, $d['status_karyawan']);
        
        $sheet->setCellValue('P' . $row, $fmtDate($d['tmt_kerja']));
        $sheet->setCellValue('Q' . $row, $fmtDate($d['tmt_mbt']));
        $sheet->setCellValue('R' . $row, $fmtDate($d['tmt_pensiun']));
        
        $sheet->setCellValueExplicit('S' . $row, $d['tax_id'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('T' . $row, $d['bpjs_id'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('U' . $row, $d['jamsostek_id'], DataType::TYPE_STRING);
        
        $sheet->setCellValue('V' . $row, $d['nama_bank']);
        $sheet->setCellValueExplicit('W' . $row, $d['no_rekening'], DataType::TYPE_STRING);
        $sheet->setCellValue('X' . $row, $d['nama_pemilik_rekening']);
        $sheet->setCellValueExplicit('Y' . $row, $d['no_hp'], DataType::TYPE_STRING);
        
        $sheet->setCellValue('Z' . $row, $d['agama']);
        $sheet->setCellValue('AA' . $row, $d['status_pajak']); // Menggunakan status_pajak dari DB
        $sheet->setCellValue('AB' . $row, $d['pendidikan_terakhir']);
        $sheet->setCellValue('AC' . $row, $d['jurusan']);
        $sheet->setCellValue('AD' . $row, $d['institusi']);

        $row++;
    }

    // =========================================================
    // 4. FINISHING (BORDER & AUTOSIZE)
    // =========================================================
    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        // Beri border untuk seluruh data
        $styleData = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sheet->getStyle('A2:AD' . $lastRow)->applyFromArray($styleData);
    }

    // Auto Size semua kolom
    foreach (range('A', 'Z') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
    $sheet->getColumnDimension('AA')->setAutoSize(true);
    $sheet->getColumnDimension('AB')->setAutoSize(true);
    $sheet->getColumnDimension('AC')->setAutoSize(true);
    $sheet->getColumnDimension('AD')->setAutoSize(true);

    // =========================================================
    // 5. OUTPUT DOWNLOAD
    // =========================================================
    $filename = $filePrefix . '_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    echo "Gagal Export Excel: " . $e->getMessage();
}
?>