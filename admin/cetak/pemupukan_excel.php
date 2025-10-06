<?php
// pages/cetak/pemupukan_excel.php
// Excel Pemupukan – hormati semua filter
// Kolom baru (Menabur): TAHUN, APL; T.TANAM dari md_tahun_tanam

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

try{
  $db  = new Database();
  $pdo = $db->getConnection();

  // Filters
  $tab        = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab,['menabur','angkutan'],true)) $tab='menabur';
  $f_unit_id  = qintOrEmpty($_GET['unit_id']  ?? '');
  $f_kebun_id = qintOrEmpty($_GET['kebun_id'] ?? '');
  $f_tanggal  = qstr($_GET['tanggal'] ?? '');
  $f_bulan    = qstr($_GET['bulan']   ?? '');
  $f_jenis    = qstr($_GET['jenis_pupuk'] ?? '');

  // Column detection
  $cacheCols=[]; $columnExists=function(PDO $c,$table,$col)use(&$cacheCols){
    if(!isset($cacheCols[$table])){
      $st=$c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t'=>$table]);
      $cacheCols[$table]=array_map('strtolower',array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME'));
    }
    return in_array(strtolower($col),$cacheCols[$table]??[],true);
  };

  $hasKebunMenaburId  = $columnExists($pdo,'menabur_pupuk','kebun_id');
  $hasKebunMenaburKod = $columnExists($pdo,'menabur_pupuk','kebun_kode');
  $hasKebunAngkutId   = $columnExists($pdo,'angkutan_pupuk','kebun_id');
  $hasKebunAngkutKod  = $columnExists($pdo,'angkutan_pupuk','kebun_kode');

  $hasTahunMenabur    = $columnExists($pdo,'menabur_pupuk','tahun');
  $hasTTId            = $columnExists($pdo,'menabur_pupuk','tahun_tanam_id');
  $hasTTAngka         = $columnExists($pdo,'menabur_pupuk','tahun_tanam');
  $aplCol=null; foreach(['apl','aplikator'] as $c){ if($columnExists($pdo,'menabur_pupuk',$c)){ $aplCol=$c; break; } }

  // Map kebun id->kode/nama
  $kebuns=$pdo->query("SELECT id,kode,nama_kebun FROM md_kebun")->fetchAll(PDO::FETCH_ASSOC);
  $idToKode=[]; $idToNama=[];
  foreach($kebuns as $k){ $idToKode[(int)$k['id']]=$k['kode']; $idToNama[(int)$k['id']]=$k['nama_kebun']; }

  if ($tab==='angkutan'){
    $judul="Data Angkutan Pupuk Kimia";
    $selectK=''; $joinK='';
    if($hasKebunAngkutId){  $selectK=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinK=" LEFT JOIN md_kebun kb ON kb.id=a.kebun_id "; }
    elseif($hasKebunAngkutKod){ $selectK=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinK=" LEFT JOIN md_kebun kb ON kb.kode=a.kebun_kode "; }

    $where=" WHERE 1=1"; $p=[];
    if($f_unit_id!==''){ $where.=" AND a.unit_tujuan_id=:uid"; $p[':uid']=(int)$f_unit_id; }
    if($f_kebun_id!==''){
      if($hasKebunAngkutId){ $where.=" AND a.kebun_id=:kid"; $p[':kid']=(int)$f_kebun_id; }
      elseif($hasKebunAngkutKod){ $where.=" AND a.kebun_kode=:kkod"; $p[':kkod']=(string)($idToKode[(int)$f_kebun_id]??''); }
    }
    if($f_tanggal!==''){ $where.=" AND a.tanggal=:tgl"; $p[':tgl']=$f_tanggal; }
    if($f_bulan!=='' && ctype_digit($f_bulan)){ $where.=" AND MONTH(a.tanggal)=:bln"; $p[':bln']=(int)$f_bulan; }
    if($f_jenis!==''){ $where.=" AND a.jenis_pupuk=:jp"; $p[':jp']=$f_jenis; }

    $sql="SELECT a.*, u.nama_unit AS unit_tujuan_nama $selectK
          FROM angkutan_pupuk a
          LEFT JOIN units u ON u.id=a.unit_tujuan_id
          $joinK
          $where
          ORDER BY a.tanggal DESC, a.id DESC";
    $st=$pdo->prepare($sql); foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); } $st->execute();
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    $headers=['Kebun','Gudang Asal','Unit Tujuan','Tanggal','Jenis Pupuk','Jumlah (Kg)','Nomor DO','Supir'];

  } else {
    $judul="Data Penaburan Pupuk Kimia";
    $selectK=''; $joinK='';
    if($hasKebunMenaburId){  $selectK=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinK=" LEFT JOIN md_kebun kb ON kb.id=m.kebun_id "; }
    elseif($hasKebunMenaburKod){ $selectK=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinK=" LEFT JOIN md_kebun kb ON kb.kode=m.kebun_kode "; }

    $selectTT = ", COALESCE(tt.tahun, ".($hasTTAngka?"m.tahun_tanam":"NULL").") AS t_tanam";
    $joinTT   = "";
    if($hasTTId)       $joinTT=" LEFT JOIN md_tahun_tanam tt ON tt.id=m.tahun_tanam_id ";
    elseif($hasTTAngka)$joinTT=" LEFT JOIN md_tahun_tanam tt ON tt.tahun=m.tahun_tanam ";

    $selectTahun = $hasTahunMenabur ? ", m.tahun AS tahun_input" : ", YEAR(m.tanggal) AS tahun_input";
    $selectAPL   = $aplCol ? ", m.`$aplCol` AS apl" : ", NULL AS apl";

    $where=" WHERE 1=1"; $p=[];
    if($f_unit_id!==''){ $where.=" AND m.unit_id=:uid"; $p[':uid']=(int)$f_unit_id; }
    if($f_kebun_id!==''){
      if($hasKebunMenaburId){ $where.=" AND m.kebun_id=:kid"; $p[':kid']=(int)$f_kebun_id; }
      elseif($hasKebunMenaburKod){ $where.=" AND m.kebun_kode=:kkod"; $p[':kkod']=(string)($idToKode[(int)$f_kebun_id]??''); }
    }
    if($f_tanggal!==''){ $where.=" AND m.tanggal=:tgl"; $p[':tgl']=$f_tanggal; }
    if($f_bulan!=='' && ctype_digit($f_bulan)){ $where.=" AND MONTH(m.tanggal)=:bln"; $p[':bln']=(int)$f_bulan; }
    if($f_jenis!==''){ $where.=" AND m.jenis_pupuk=:jp"; $p[':jp']=$f_jenis; }

    $sql="SELECT m.*, u.nama_unit AS unit_nama
                 $selectK
                 $selectTT
                 $selectTahun
                 $selectAPL
          FROM menabur_pupuk m
          LEFT JOIN units u ON u.id=m.unit_id
          $joinK
          $joinTT
          $where
          ORDER BY m.tanggal DESC, m.id DESC";
    $st=$pdo->prepare($sql); foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); } $st->execute();
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    $headers=['Tahun','Kebun','Unit','T.TANAM','Blok','Tanggal','Jenis Pupuk','APL','Dosis (kg/ha)','Jumlah (Kg)','Luas (Ha)','Invt. Pokok','Catatan'];
  }

  // ===== Spreadsheet
  $ss=new Spreadsheet(); $sheet=$ss->getActiveSheet();
  $sheet->setTitle(substr($tab==='angkutan'?'Angkutan':'Menabur',0,31));

  $lastColIndex=count($headers)-1;
  $cols=[]; for($i=0;$i<=$lastColIndex;$i++){ $cols[]=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1); }
  $C1=$cols[0]; $CL=$cols[$lastColIndex];

  // Brand header
  $sheet->mergeCells($C1.'1:'.$CL.'1'); $sheet->setCellValue($C1.'1','PTPN 4 REGIONAL 3');
  $sheet->getStyle($C1.'1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
  $sheet->getStyle($C1.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($C1.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F7B4F');

  // Subjudul
  $sheet->mergeCells($C1.'2:'.$CL.'2');
  $sheet->setCellValue($C1.'2', $tab==='angkutan'?'Data Angkutan Pupuk Kimia':'Data Penaburan Pupuk Kimia');
  $sheet->getStyle($C1.'2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('0F7B4F');
  $sheet->getStyle($C1.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Ringkas filter
  $sheet->mergeCells($C1.'3:'.$CL.'3');
  $sheet->setCellValue($C1.'3',
    'Filter: tab='.$tab.
    ' | unit_id='.($f_unit_id===''?'Semua':$f_unit_id).
    ' | kebun_id='.($f_kebun_id===''?'Semua':$f_kebun_id).
    ' | tanggal='.($f_tanggal?:'Semua').
    ' | bulan='.($f_bulan?:'Semua').
    ' | jenis='.($f_jenis?:'Semua')
  );
  $sheet->getStyle($C1.'3')->getFont()->setSize(10)->getColor()->setRGB('666666');
  $sheet->getStyle($C1.'3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Header table
  $r=5; foreach($headers as $i=>$h){ $sheet->setCellValue($cols[$i].$r,$h); }
  $sheet->getStyle($C1.$r.':'.$CL.$r)->getFont()->setBold(true);
  $sheet->getStyle($C1.$r.':'.$CL.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $sheet->getStyle($C1.$r.':'.$CL.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F4EF');
  $sheet->getStyle($C1.$r.':'.$CL.$r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $r++;

  // Totals
  $tot_jumlah=0.0; $tot_luas=0.0; $tot_invt=0; $tot_dosis=0.0; $cnt_dosis=0;

  if (empty($rows)) {
    $sheet->mergeCells($C1.$r.':'.$CL.$r);
    $sheet->setCellValue($C1.$r,'Tidak ada data.');
    $sheet->getStyle($C1.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $r++;
  } else {
    foreach($rows as $row){
      if ($tab==='angkutan'){
        $vals=[
          (string)($row['kebun_nama'] ?? ($row['kebun_kode'] ?? '-')),
          (string)($row['gudang_asal'] ?? ''),
          (string)($row['unit_tujuan_nama'] ?? '-'),
          (string)($row['tanggal'] ?? ''),
          (string)($row['jenis_pupuk'] ?? ''),
          (float)($row['jumlah'] ?? 0),
          (string)($row['nomor_do'] ?? ''),
          (string)($row['supir'] ?? ''),
        ];
        $tot_jumlah += (float)($row['jumlah'] ?? 0);
      } else {
        $dosis = (array_key_exists('dosis',$row) && $row['dosis']!==null && $row['dosis']!=='') ? (float)$row['dosis'] : null;
        $vals=[
          (string)($row['tahun_input'] ?? ''),                                        // Tahun
          (string)($row['kebun_nama'] ?? ($row['kebun_kode'] ?? '-')),               // Kebun
          (string)($row['unit_nama'] ?? '-'),                                        // Unit
          (string)($row['t_tanam'] ?? '-'),                                          // T.TANAM
          (string)($row['blok'] ?? ''),                                              // Blok
          (string)($row['tanggal'] ?? ''),                                           // Tanggal
          (string)($row['jenis_pupuk'] ?? ''),                                       // Jenis
          (string)(($row['apl'] ?? '') === '' ? '-' : $row['apl']),                  // APL
          $dosis,                                                                     // Dosis
          (float)($row['jumlah'] ?? 0),                                              // Jumlah
          (float)($row['luas'] ?? 0),                                                // Luas
          (int)($row['invt_pokok'] ?? 0),                                            // Invt
          (string)($row['catatan'] ?? ''),                                           // Catatan
        ];
        if($dosis!==null){ $tot_dosis += $dosis; $cnt_dosis++; }
        $tot_jumlah += (float)($row['jumlah'] ?? 0);
        $tot_luas   += (float)($row['luas'] ?? 0);
        $tot_invt   += (int)($row['invt_pokok'] ?? 0);
      }

      foreach($vals as $i=>$v){ $sheet->setCellValue($cols[$i].$r,$v); }
      $sheet->getStyle($C1.$r.':'.$CL.$r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

      if ($tab==='angkutan'){
        $sheet->getStyle($cols[5].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
      } else {
        foreach([8,9,10,11] as $idx){ $sheet->getStyle($cols[$idx].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
      }
      $r++;
    }
  }

  // TOTAL
  if ($tab==='angkutan'){
    $sheet->mergeCells($cols[0].$r.':'.$cols[4].$r);
    $sheet->setCellValue($cols[0].$r,'TOTAL JUMLAH (Kg)');
    $sheet->getStyle($cols[0].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($cols[0].$r)->getFont()->setBold(true);
    $sheet->setCellValue($cols[5].$r,$tot_jumlah);
    $sheet->getStyle($cols[5].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  } else {
    $avg_dosis = $cnt_dosis ? ($tot_dosis/$cnt_dosis) : 0;
    $sheet->mergeCells($cols[0].$r.':'.$cols[7].$r); // sampai kolom APL
    $sheet->setCellValue($cols[0].$r,'TOTAL');
    $sheet->getStyle($cols[0].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($cols[0].$r)->getFont()->setBold(true);
    $sheet->setCellValue($cols[8].$r,$avg_dosis);     // Dosis rata2
    $sheet->setCellValue($cols[9].$r,$tot_jumlah);    // Jumlah
    $sheet->setCellValue($cols[10].$r,$tot_luas);     // Luas
    $sheet->setCellValue($cols[11].$r,$tot_invt);     // Invt
    foreach([8,9,10,11] as $idx){ $sheet->getStyle($cols[$idx].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
  }
  $sheet->getStyle($C1.$r.':'.$CL.$r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $sheet->getStyle($C1.$r.':'.$CL.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1FAF6');

  foreach($cols as $c){ $sheet->getColumnDimension($c)->setAutoSize(true); }

  $fname='Pemupukan_Kimia_'.$tab;
  if($f_unit_id!=='')  $fname.='_UNIT-'.$f_unit_id;
  if($f_kebun_id!=='') $fname.='_KEBUN-'.$f_kebun_id;
  if($f_tanggal!=='')  $fname.='_TGL-'.$f_tanggal;
  if($f_bulan!=='')    $fname.='_BLN-'.$f_bulan;
  if($f_jenis!=='')    $fname.='_JENIS-'.preg_replace('/[^A-Za-z0-9_\-]/','',$f_jenis);
  $fname.='.xlsx';

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  header('Cache-Control: max-age=0');
  (new Xlsx($ss))->save('php://output'); exit;

}catch(Throwable $e){
  http_response_code(500); echo "Error: ".$e->getMessage();
}
