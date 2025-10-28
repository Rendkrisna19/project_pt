<?php
// admin/laporan_mingguan_export_excel.php
// SAFE XLSX STREAM (filter: Kebun, AFD, Jenis Pekerjaan Mingguan, Bulan, Tahun, Minggu)
declare(strict_types=1);
session_start();

/* ---------- KEBERSIHAN OUTPUT ---------- */
ini_set('display_errors','0');
ini_set('log_errors','1');
error_reporting(E_ALL);

// Matikan kompresi output agar header XLSX aman
if (function_exists('ini_get') && ini_get('zlib.output_compression')) {
  ini_set('zlib.output_compression', 'Off');
}
// Bersihkan buffer kalau ada
while (ob_get_level() > 0) { @ob_end_clean(); }

// Wajib login
if (empty($_SESSION['loggedin'])) { http_response_code(403); exit; }

require_once '../config/database.php';

// Lokasi autoload Composer (ubah jika vendor kamu ada di tempat lain)
$autoloadPath1 = __DIR__ . '/../vendor/autoload.php';     // proyek_root/vendor (umumnya benar)
$autoloadPath2 = __DIR__ . '/../../vendor/autoload.php';  // fallback jika struktur beda

if (file_exists($autoloadPath1)) {
  require_once $autoloadPath1;
} elseif (file_exists($autoloadPath2)) {
  require_once $autoloadPath2;
} else {
  error_log('[LM_EXPORT_XLSX] Autoload Composer tidak ditemukan.');
  http_response_code(500); exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function norm_bulan($b){
  $map = [
    'january'=>'Januari','february'=>'Februari','march'=>'Maret','april'=>'April',
    'may'=>'Mei','june'=>'Juni','july'=>'Juli','august'=>'Agustus','september'=>'September',
    'october'=>'Oktober','november'=>'November','december'=>'Desember',
    'januari'=>'Januari','februari'=>'Februari','maret'=>'Maret','mei'=>'Mei',
    'juni'=>'Juni','juli'=>'Juli','agustus'=>'Agustus','oktober'=>'Oktober','november'=>'November','desember'=>'Desember'
  ];
  $k = strtolower(trim((string)$b));
  return $map[$k] ?? 'Januari';
}

try {
  /* ---------- PARAM UI ---------- */
  $kebun_id = isset($_GET['kebun_id']) ? (string)$_GET['kebun_id'] : '';
  $unit_id  = isset($_GET['unit_id'])  ? (string)$_GET['unit_id']  : '';
  $jp_id    = isset($_GET['jenis_pekerjaan_id']) ? (string)$_GET['jenis_pekerjaan_id'] : '';
  $tahun    = (int)($_GET['tahun'] ?? 0);
  $bulan    = norm_bulan($_GET['bulan'] ?? '');
  $mode     = ($_GET['mode'] ?? 'single') === 'all' ? 'all' : 'single';
  $minggu   = (int)($_GET['minggu'] ?? 1);
  if ($mode !== 'all' && ($minggu < 1 || $minggu > 5)) $minggu = 1;

  if (!$kebun_id || !$unit_id || !$jp_id || !$tahun || !$bulan) {
    http_response_code(400); exit;
  }

  /* ---------- DB ---------- */
  $db   = new Database();
  $conn = $db->getConnection();
  if (!$conn) { throw new RuntimeException('DB connection null'); }
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  /* ---------- BACA MASTER ---------- */
  $meta = [
    'judul_laporan' => 'LAPORAN PEMELIHARAAN KEBUN',
    'catatan' => 'BATAS AKHIR PENGISIAN SETIAP HARI SABTU JAM 9 PAGI',
    'judul_minggu_1'=> 'MINGGU I','judul_minggu_2'=> 'MINGGU II','judul_minggu_3'=> 'MINGGU III',
    'judul_minggu_4'=> 'MINGGU IV','judul_minggu_5'=> 'MINGGU V'
  ];

  $stNama = $conn->prepare("SELECT nama_kebun FROM md_kebun WHERE id=:id LIMIT 1");
  $stNama->execute([':id'=>$kebun_id]);
  $namaKebun = (string)($stNama->fetchColumn() ?: '');

  $stUnit = $conn->prepare("SELECT nama_unit FROM units WHERE id=:id LIMIT 1");
  $stUnit->execute([':id'=>$unit_id]);
  $namaUnit = (string)($stUnit->fetchColumn() ?: '');

  // PENTING: sumber JP dari md_jenis_pekerjaan_mingguan
  $stJenis = $conn->prepare("SELECT nama FROM md_jenis_pekerjaan_mingguan WHERE id=:id LIMIT 1");
  $stJenis->execute([':id'=>$jp_id]);
  $namaJenis = (string)($stJenis->fetchColumn() ?: '');

  // Meta judul per minggu
  $stMeta = $conn->prepare("
    SELECT judul_laporan, catatan,
           COALESCE(judul_minggu_1,'MINGGU I') jm1, COALESCE(judul_minggu_2,'MINGGU II') jm2,
           COALESCE(judul_minggu_3,'MINGGU III') jm3, COALESCE(judul_minggu_4,'MINGGU IV') jm4,
           COALESCE(judul_minggu_5,'MINGGU V') jm5
    FROM laporan_mingguan_meta
    WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b
    LIMIT 1
  ");
  $stMeta->execute([':k'=>$kebun_id, ':jp'=>$jp_id, ':t'=>$tahun, ':b'=>$bulan]);
  if ($m = $stMeta->fetch(PDO::FETCH_ASSOC)) {
    $meta['judul_laporan'] = $m['judul_laporan'] ?: $meta['judul_laporan'];
    $meta['catatan']       = $m['catatan']       ?: $meta['catatan'];
    $meta['judul_minggu_1']= $m['jm1']; $meta['judul_minggu_2']= $m['jm2'];
    $meta['judul_minggu_3']= $m['jm3']; $meta['judul_minggu_4']= $m['jm4']; $meta['judul_minggu_5']= $m['jm5'];
  }

  // Helper ambil detail
  $getDetails = function(int $week) use ($conn,$kebun_id,$jp_id,$tahun,$bulan,$unit_id){
    $st = $conn->prepare("
      SELECT blok, ts, pkwt, kng, tp
      FROM laporan_mingguan
      WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b AND minggu=:m AND afdeling=:afd
      ORDER BY blok
    ");
    $st->execute([':k'=>$kebun_id, ':jp'=>$jp_id, ':t'=>$tahun, ':b'=>$bulan, ':m'=>$week, ':afd'=>$unit_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  };

  /* ---------- BUILD XLSX ---------- */
  $ss = new Spreadsheet();
  $ss->getProperties()
     ->setCreator('PTPN 4 Regional 3')
     ->setTitle('Laporan Mingguan PTPN 4 Regional 3');

  $GREEN='15803D'; $BORD='9CA3AF';
  $borderThin = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$BORD]]]];
  $thStyle=['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
            'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
            'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$GREEN]]];
  $titleStyle=['font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>$GREEN]],
               'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]];
  $subTitleBold=['font'=>['bold'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]];
  $noteStyle=['font'=>['bold'=>true,'color'=>['rgb'=>'B91C1C']],
              'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]];

  $buildSheet=function($sheet,int $week,array $details)
                 use($titleStyle,$subTitleBold,$noteStyle,$thStyle,$borderThin,$GREEN,$meta,$namaKebun,$namaUnit,$namaJenis,$bulan,$tahun){
    // Judul sheet (maks 31 char aman)
    $sheet->setTitle("Minggu $week");

    $r=1;
    $sheet->setCellValue("A{$r}","LAPORAN MINGGUAN PTPN 4 REGIONAL 3"); $sheet->mergeCells("A{$r}:G{$r}");
    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($titleStyle); $r++;
    $sheet->setCellValue("A{$r}", ($meta['judul_laporan']?:'LAPORAN PEMELIHARAAN KEBUN').' '.strtoupper($namaKebun)); $sheet->mergeCells("A{$r}:G{$r}");
    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($subTitleBold); $r++;
    $jpTitle = $namaJenis !== '' ? strtoupper($namaJenis) : 'JENIS PEKERJAAN';
    $sheet->setCellValue("A{$r}", $jpTitle." — AFD: ".$namaUnit); $sheet->mergeCells("A{$r}:G{$r}");
    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($subTitleBold); $r++;
    $sheet->setCellValue("A{$r}", "BULAN ".strtoupper($bulan)." ".$tahun); $sheet->mergeCells("A{$r}:G{$r}");
    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($subTitleBold); $r++;
    $jk="judul_minggu_{$week}";
    $sheet->setCellValue("A{$r}", $meta[$jk] ?? "MINGGU {$week}"); $sheet->mergeCells("A{$r}:G{$r}");
    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($subTitleBold); $r++;
    $sheet->setCellValue("A{$r}", $meta['catatan']??''); $sheet->mergeCells("A{$r}:G{$r}");
    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($noteStyle); $r+=2;

    // Header tabel
    foreach (['AFD','Blok','TS','PKWT','KNG','TP','JUMLAH'] as $i=>$h) {
      $col = chr(ord('A')+$i);
      $sheet->setCellValue("{$col}{$r}",$h);
    }
    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray($thStyle);
    $sheet->getRowDimension($r)->setRowHeight(22);
    $r++;

    // Minimal 21 baris, jika data >21, ikuti jumlah data
    $dataRows = max(21, count($details));
    $rowStart = $r;

    $totTS=$totPKWT=$totKNG=$totTP=0.0;

    if ($dataRows > 0) {
      // Merge kolom A di blok data
      $sheet->mergeCells("A{$r}:A".($r+$dataRows-1));
      $sheet->setCellValue("A{$r}", $namaUnit);
      $sheet->getStyle("A{$r}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_TOP);
      $sheet->getStyle("A{$r}")->getFont()->setBold(true);
    }

    for($i=0;$i<$dataRows;$i++){
      $d=$details[$i]??['blok'=>'','ts'=>0,'pkwt'=>0,'kng'=>0,'tp'=>0];

      $ts=(float)($d['ts']??0); $p=(float)($d['pkwt']??0); $k=(float)($d['kng']??0); $t=(float)($d['tp']??0);
      $sum=$ts+$p+$k+$t;

      $sheet->setCellValue("B{$r}", (string)($d['blok']??'')); // aman utk leading zero
      $sheet->setCellValue("C{$r}", $ts);
      $sheet->setCellValue("D{$r}", $p);
      $sheet->setCellValue("E{$r}", $k);
      $sheet->setCellValue("F{$r}", $t);
      $sheet->setCellValue("G{$r}", $sum);

      $sheet->getStyle("C{$r}:G{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
      $sheet->getStyle("C{$r}:G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
      $sheet->getStyle("G{$r}")->getFont()->setBold(true);

      $totTS+=$ts; $totPKWT+=$p; $totKNG+=$k; $totTP+=$t;
      $r++;
    }

    // Baris total
    $sheet->setCellValue("A{$r}","JUMLAH"); 
    $sheet->mergeCells("A{$r}:B{$r}");
    $sheet->setCellValue("C{$r}",$totTS);
    $sheet->setCellValue("D{$r}",$totPKWT);
    $sheet->setCellValue("E{$r}",$totKNG);
    $sheet->setCellValue("F{$r}",$totTP);
    $sheet->setCellValue("G{$r}",$totTS+$totPKWT+$totKNG+$totTP);

    $sheet->getStyle("A{$r}:G{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
    $sheet->getStyle("A{$r}:G{$r}")->getFont()->setBold(true);
    $sheet->getStyle("C{$r}:G{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("C{$r}:G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Border seluruh blok tabel
    $sheet->getStyle("A".($rowStart-1).":G{$r}")->applyFromArray($borderThin);

    // Lebar kolom
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(24);
    foreach(['C','D','E','F','G'] as $c){ $sheet->getColumnDimension($c)->setWidth(14); }

    // Freeze di header data
    $sheet->freezePane("A{$rowStart}");
  };

  // Build sheets
  if ($mode==='all'){
    for($w=1;$w<=5;$w++){
      $details=$getDetails($w);
      $sheet=($w===1)?$ss->getActiveSheet():$ss->createSheet();
      $buildSheet($sheet,$w,$details);
    }
    $ss->setActiveSheetIndex(0);
    $filename = sprintf(
      "Laporan_Mingguan_ALL_%s_%s_%s_%d_%s.xlsx",
      preg_replace('/[^\w\-]+/','_', $namaKebun ?: 'Kebun'),
      preg_replace('/[^\w\-]+/','_', $namaUnit  ?: 'AFD'),
      preg_replace('/[^\w\-]+/','_', $bulan     ?: 'Bulan'),
      $tahun ?: 0,
      preg_replace('/[^\w\-]+/','_', $namaJenis ?: 'JP_MGW')
    );
  } else {
    $details=$getDetails($minggu);
    $sheet=$ss->getActiveSheet();
    $buildSheet($sheet,$minggu,$details);
    $filename = sprintf(
      "Laporan_Mingguan_M%d_%s_%s_%s_%d_%s.xlsx",
      $minggu,
      preg_replace('/[^\w\-]+/','_', $namaKebun ?: 'Kebun'),
      preg_replace('/[^\w\-]+/','_', $namaUnit  ?: 'AFD'),
      preg_replace('/[^\w\-]+/','_', $bulan     ?: 'Bulan'),
      $tahun ?: 0,
      preg_replace('/[^\w\-]+/','_', $namaJenis ?: 'JP_MGW')
    );
  }

  /* ---------- STREAM XLSX ---------- */
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  header('Content-Transfer-Encoding: binary');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');

  $writer = new Xlsx($ss);
  $writer->save('php://output');
  exit;

} catch (Throwable $e) {
  // Log dulu biar gampang trace di error_log
  error_log('[LM_EXPORT_XLSX] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());

  // Mode debug opsional untuk lokal: tambahkan ?debug=1 di URL
  $isLocalhost = isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST']==='localhost' || $_SERVER['REMOTE_ADDR']==='127.0.0.1');
  if ($isLocalhost && isset($_GET['debug']) && $_GET['debug'] == '1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n";
  } else {
    http_response_code(500);
  }
  exit;
}
