<?php
// pages/cetak/pemupukan_excel.php
// Output: Excel (.xlsx) data pemupukan kimia (menabur/angkutan)
// Menghormati filter: ?tab=&unit_id=&kebun_id=&tanggal=&bulan=&jenis_pupuk=
// Tambahan: baris TOTAL
// - Menabur: total Jumlah(kg), total Luas(ha), total Invt. Pokok, Rata2 Dosis (kg/ha)
// - Angkutan: total Jumlah(kg)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function qstr($v){ return trim((string)$v); }
function qintOrEmpty($v){ return ($v===''||$v===null) ? '' : (int)$v; }

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // ====== Filters ======
  $tab         = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

  $f_unit_id   = qintOrEmpty($_GET['unit_id']     ?? '');
  $f_kebun_id  = qintOrEmpty($_GET['kebun_id']    ?? '');
  $f_tanggal   = qstr($_GET['tanggal']            ?? '');
  $f_bulan     = qstr($_GET['bulan']              ?? '');
  $f_jenis     = qstr($_GET['jenis_pupuk']        ?? '');

  // ====== Helper deteksi kolom kebun ======
  $cacheCols = [];
  $columnExists = function(PDO $c, $table, $col) use (&$cacheCols){
    if (!isset($cacheCols[$table])) {
      $st = $c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t'=>$table]);
      $cacheCols[$table] = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    }
    return in_array($col, $cacheCols[$table] ?? [], true);
  };

  $hasKebunMenaburId  = $columnExists($pdo,'menabur_pupuk','kebun_id');
  $hasKebunMenaburKod = $columnExists($pdo,'menabur_pupuk','kebun_kode');
  $hasKebunAngkutId   = $columnExists($pdo,'angkutan_pupuk','kebun_id');
  $hasKebunAngkutKod  = $columnExists($pdo,'angkutan_pupuk','kebun_kode');

  // Mapping id->kode untuk filter jika tabel transaksi pakai kebun_kode
  $kebuns = $pdo->query("SELECT id, kode, nama_kebun FROM md_kebun")->fetchAll(PDO::FETCH_ASSOC);
  $idToKode = [];
  $idToNama = [];
  foreach ($kebuns as $k) { $idToKode[(int)$k['id']] = $k['kode']; $idToNama[(int)$k['id']] = $k['nama_kebun']; }

  // ====== Query builder ======
  if ($tab === 'angkutan') {
    $judul = "Data Angkutan Pupuk Kimia";

    $selectKebun = '';
    $joinKebun   = '';
    if     ($hasKebunAngkutId)  { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.id = a.kebun_id "; }
    elseif ($hasKebunAngkutKod) { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.kode = a.kebun_kode "; }

    $where = " WHERE 1=1";
    $p = [];
    if ($f_unit_id !== '') { $where .= " AND a.unit_tujuan_id = :uid"; $p[':uid'] = (int)$f_unit_id; }
    if ($f_kebun_id !== '') {
      if ($hasKebunAngkutId)      { $where .= " AND a.kebun_id = :kid";     $p[':kid']  = (int)$f_kebun_id; }
      elseif ($hasKebunAngkutKod) { $where .= " AND a.kebun_kode = :kkod";  $p[':kkod'] = (string)($idToKode[(int)$f_kebun_id] ?? ''); }
    }
    if ($f_tanggal !== '') { $where .= " AND a.tanggal = :tgl"; $p[':tgl'] = $f_tanggal; }
    if ($f_bulan !== '' && ctype_digit($f_bulan)) { $where .= " AND MONTH(a.tanggal) = :bln"; $p[':bln'] = (int)$f_bulan; }
    if ($f_jenis !== '') { $where .= " AND a.jenis_pupuk = :jp"; $p[':jp'] = $f_jenis; }

    $sql = "SELECT a.*, u.nama_unit AS unit_tujuan_nama $selectKebun
            FROM angkutan_pupuk a
            LEFT JOIN units u ON u.id = a.unit_tujuan_id
            $joinKebun
            $where
            ORDER BY a.tanggal DESC, a.id DESC";
    $st = $pdo->prepare($sql);
    foreach ($p as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $headers = ['Kebun','Gudang Asal','Unit Tujuan','Tanggal','Jenis Pupuk','Jumlah (Kg)','Nomor DO','Supir'];
  } else {
    $judul = "Data Penaburan Pupuk Kimia";

    $selectKebun = '';
    $joinKebun   = '';
    if     ($hasKebunMenaburId)  { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.id = m.kebun_id "; }
    elseif ($hasKebunMenaburKod) { $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.kode = m.kebun_kode "; }

    $where = " WHERE 1=1";
    $p = [];
    if ($f_unit_id !== '') { $where .= " AND m.unit_id = :uid"; $p[':uid'] = (int)$f_unit_id; }
    if ($f_kebun_id !== '') {
      if ($hasKebunMenaburId)      { $where .= " AND m.kebun_id = :kid";     $p[':kid']  = (int)$f_kebun_id; }
      elseif ($hasKebunMenaburKod) { $where .= " AND m.kebun_kode = :kkod";  $p[':kkod'] = (string)($idToKode[(int)$f_kebun_id] ?? ''); }
    }
    if ($f_tanggal !== '') { $where .= " AND m.tanggal = :tgl"; $p[':tgl'] = $f_tanggal; }
    if ($f_bulan !== '' && ctype_digit($f_bulan)) { $where .= " AND MONTH(m.tanggal) = :bln"; $p[':bln'] = (int)$f_bulan; }
    if ($f_jenis !== '') { $where .= " AND m.jenis_pupuk = :jp"; $p[':jp'] = $f_jenis; }

    $sql = "SELECT m.*, u.nama_unit AS unit_nama $selectKebun
            FROM menabur_pupuk m
            LEFT JOIN units u ON u.id = m.unit_id
            $joinKebun
            $where
            ORDER BY m.tanggal DESC, m.id DESC";
    $st = $pdo->prepare($sql);
    foreach ($p as $k=>$v) $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $headers = ['Kebun','Unit','Blok','Tanggal','Jenis Pupuk','Dosis (kg/ha)','Jumlah (Kg)','Luas (Ha)','Invt. Pokok','Catatan'];
  }

  // Ambil nama ringkas filter
  $unitNama = 'Semua Unit';
  if ($f_unit_id !== '') {
    $s = $pdo->prepare("SELECT nama_unit FROM units WHERE id=:id"); $s->execute([':id'=>(int)$f_unit_id]);
    $unitNama = $s->fetchColumn() ?: ('#'.$f_unit_id);
  }
  $kebunNama = 'Semua Kebun';
  if ($f_kebun_id !== '') {
    $kebunNama = $idToNama[(int)$f_kebun_id] ?? ('#'.$f_kebun_id);
  }

  // ============ Spreadsheet ============
  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle(substr($tab==='angkutan'?'Angkutan':'Menabur',0,31));

  // kolom
  $lastColIndex = count($headers)-1;
  $colLetters = [];
  for ($i=0;$i<=$lastColIndex;$i++){
    $colLetters[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1);
  }
  $firstCol = $colLetters[0];
  $lastCol  = $colLetters[$lastColIndex];

  // Brand header (hijau)
  $sheet->mergeCells($firstCol.'1:'.$lastCol.'1');
  $sheet->setCellValue($firstCol.'1', 'PTPN 4 REGIONAL 3');
  $sheet->getStyle($firstCol.'1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
  $sheet->getStyle($firstCol.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($firstCol.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F7B4F');

  // Subjudul
  $sheet->mergeCells($firstCol.'2:'.$lastCol.'2');
  $sheet->setCellValue($firstCol.'2', $judul);
  $sheet->getStyle($firstCol.'2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('0F7B4F');
  $sheet->getStyle($firstCol.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Filter ringkas
  $sheet->mergeCells($firstCol.'3:'.$lastCol.'3');
  $sheet->setCellValue(
    $firstCol.'3',
    'Filter: Tab '.$tab.
    ' | Unit: '.$unitNama.
    ' | Kebun: '.$kebunNama.
    ' | Tanggal: '.($f_tanggal?:'Semua').
    ' | Bulan: '.($f_bulan?:'Semua').
    ' | Jenis: '.($f_jenis?:'Semua')
  );
  $sheet->getStyle($firstCol.'3')->getFont()->setSize(10)->getColor()->setRGB('666666');
  $sheet->getStyle($firstCol.'3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Header tabel
  $row = 5;
  foreach ($headers as $i => $title) {
    $col = $colLetters[$i];
    $sheet->setCellValue($col.$row, $title);
  }
  $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getFont()->setBold(true);
  $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4EF');
  $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $row++;

  // Akumulasi total
  $tot_jumlah = 0.0;
  $tot_luas   = 0.0;
  $tot_invt   = 0;
  $tot_dosis  = 0.0;
  $cnt_dosis  = 0;

  // Body
  if (empty($rows)) {
    $sheet->mergeCells($firstCol.$row.':'.$lastCol.$row);
    $sheet->setCellValue($firstCol.$row, 'Tidak ada data.');
    $sheet->getStyle($firstCol.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
  } else {
    foreach ($rows as $r) {
      $i = 0;
      if ($tab==='angkutan') {
        $vals = [
          (string)($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')),
          (string)($r['gudang_asal'] ?? ''),
          (string)($r['unit_tujuan_nama'] ?? '-'),
          (string)($r['tanggal'] ?? ''),
          (string)($r['jenis_pupuk'] ?? ''),
          (float)($r['jumlah'] ?? 0),
          (string)($r['nomor_do'] ?? ''),
          (string)($r['supir'] ?? ''),
        ];
        $tot_jumlah += (float)($r['jumlah'] ?? 0);
      } else {
        $dosis = (array_key_exists('dosis',$r) && $r['dosis']!==null && $r['dosis']!=='') ? (float)$r['dosis'] : null;
        $vals = [
          (string)($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')),
          (string)($r['unit_nama'] ?? '-'),
          (string)($r['blok'] ?? ''),
          (string)($r['tanggal'] ?? ''),
          (string)($r['jenis_pupuk'] ?? ''),
          $dosis, // boleh null
          (float)($r['jumlah'] ?? 0),
          (float)($r['luas'] ?? 0),
          (int)($r['invt_pokok'] ?? 0),
          (string)($r['catatan'] ?? ''),
        ];

        if ($dosis !== null) { $tot_dosis += $dosis; $cnt_dosis++; }
        $tot_jumlah += (float)($r['jumlah'] ?? 0);
        $tot_luas   += (float)($r['luas'] ?? 0);
        $tot_invt   += (int)($r['invt_pokok'] ?? 0);
      }

      foreach ($vals as $v) {
        $sheet->setCellValue($colLetters[$i].$row, $v);
        $i++;
      }

      // border row
      $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

      // align numeric right
      if ($tab==='angkutan') {
        $sheet->getStyle($colLetters[5].$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Jumlah
      } else {
        foreach ([5,6,7,8] as $idx) { // Dosis, Jumlah, Luas, Invt
          $sheet->getStyle($colLetters[$idx].$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
      }

      $row++;
    }
  }

  // TOTAL row
  if ($tab === 'angkutan') {
    $labelEndColIndex = 4; // sampai "Jenis Pupuk"
    $sheet->mergeCells($firstCol.$row.':'.$colLetters[$labelEndColIndex].$row);
    $sheet->setCellValue($firstCol.$row, 'TOTAL JUMLAH (Kg)');
    $sheet->getStyle($firstCol.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($firstCol.$row)->getFont()->setBold(true);

    $sheet->setCellValue($colLetters[5].$row, $tot_jumlah);
    $sheet->getStyle($colLetters[5].$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1FAF6');
    $row++;
  } else {
    $avg_dosis = $cnt_dosis > 0 ? ($tot_dosis / $cnt_dosis) : 0;

    $labelEndColIndex = 4; // sampai "Jenis Pupuk"
    $sheet->mergeCells($firstCol.$row.':'.$colLetters[$labelEndColIndex].$row);
    $sheet->setCellValue($firstCol.$row, 'TOTAL');
    $sheet->getStyle($firstCol.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($firstCol.$row)->getFont()->setBold(true);

    $sheet->setCellValue($colLetters[5].$row, $avg_dosis);
    $sheet->setCellValue($colLetters[6].$row, $tot_jumlah);
    $sheet->setCellValue($colLetters[7].$row, $tot_luas);
    $sheet->setCellValue($colLetters[8].$row, $tot_invt);

    foreach ([5,6,7,8] as $idx) {
      $sheet->getStyle($colLetters[$idx].$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($firstCol.$row.':'.$lastCol.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1FAF6');
    $row++;
  }

  // Auto width
  foreach ($colLetters as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

  // Output
  $fname = 'Pemupukan_Kimia_'.$tab;
  if ($f_unit_id!=='')  $fname .= '_UNIT-'.$f_unit_id;
  if ($f_kebun_id!=='') $fname .= '_KEBUN-'.$f_kebun_id;
  if ($f_tanggal!=='')  $fname .= '_TGL-'.$f_tanggal;
  if ($f_bulan!=='')    $fname .= '_BLN-'.$f_bulan;
  if ($f_jenis!=='')    $fname .= '_JENIS-'.preg_replace('/[^A-Za-z0-9_\-]/','',$f_jenis);
  $fname .= '.xlsx';

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  header('Cache-Control: max-age=0');

  $writer = new Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo "Error: ".$e->getMessage();
}
