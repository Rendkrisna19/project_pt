<?php
// pages/cetak/pemupukan_excel.php
// Excel Pemupukan – hormati semua filter (Unit, Kebun, Tanggal, Bulan, Jenis, Rayon, Keterangan)
// Menabur & Angkutan: RAYON, NO AU-58/NO SPB, KETERANGAN
// Kompatibel kolom:
//   - Menabur: no_au_58 | no_au58 | catatan (dialias ke no_au_58)
//   - Angkutan: no_spb (baru) | no_au_58 | no_au58 | catatan (semua dialias ke no_spb)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  http_response_code(403); exit('Unauthorized');
}

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

  // ===== Filters
  $tab        = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';
  $f_unit_id  = qintOrEmpty($_GET['unit_id']      ?? '');
  $f_kebun_id = qintOrEmpty($_GET['kebun_id']     ?? '');
  $f_tanggal  = qstr($_GET['tanggal']             ?? '');
  $f_bulan    = qstr($_GET['bulan']               ?? '');
  $f_jenis    = qstr($_GET['jenis_pupuk']         ?? '');

  // Filter teks legacy
  $f_rayon        = qstr($_GET['rayon']        ?? '');
  $f_keterangan   = qstr($_GET['keterangan']   ?? '');

  // Filter ID baru (opsional, mirip PDF)
  $f_rayon_id      = qintOrEmpty($_GET['rayon_id']        ?? '');
  $f_apl_id        = qintOrEmpty($_GET['apl_id']          ?? '');
  $f_keterangan_id = qintOrEmpty($_GET['keterangan_id']   ?? '');
  $f_gudang_id     = qintOrEmpty($_GET['gudang_asal_id']  ?? '');

  // ===== Deteksi kolom di DB
  $cacheCols=[];
  $columnExists=function(PDO $c,$table,$col)use(&$cacheCols){
    $key=$table;
    if(!isset($cacheCols[$key])){
      $st=$c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t'=>$table]);
      $cacheCols[$key]=array_map('strtolower',array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME'));
    }
    return in_array(strtolower($col),$cacheCols[$key]??[],true);
  };

  // Kebun availability
  $hasKebunMenaburId  = $columnExists($pdo,'menabur_pupuk','kebun_id');
  $hasKebunMenaburKod = $columnExists($pdo,'menabur_pupuk','kebun_kode');
  $hasKebunAngkutId   = $columnExists($pdo,'angkutan_pupuk','kebun_id');
  $hasKebunAngkutKod  = $columnExists($pdo,'angkutan_pupuk','kebun_kode');

  // Menabur: tahun & APL (lama)
  $hasTahunMenabur = $columnExists($pdo,'menabur_pupuk','tahun');
  $hasTTId         = $columnExists($pdo,'menabur_pupuk','tahun_tanam_id');
  $hasTTAngka      = $columnExists($pdo,'menabur_pupuk','tahun_tanam');
  $aplCol = null; foreach(['apl','aplikator'] as $c){ if($columnExists($pdo,'menabur_pupuk',$c)){ $aplCol=$c; break; } }

  // Legacy text columns
  $hasRayonMenabur      = $columnExists($pdo,'menabur_pupuk','rayon');
  $hasKeteranganMenabur = $columnExists($pdo,'menabur_pupuk','keterangan');
  $hasNoAU58MenaburMain = $columnExists($pdo,'menabur_pupuk','no_au_58');
  $hasNoAU58MenaburAlt  = $columnExists($pdo,'menabur_pupuk','no_au58');
  $hasCatatanMenabur    = $columnExists($pdo,'menabur_pupuk','catatan');

  $hasRayonAngkutan      = $columnExists($pdo,'angkutan_pupuk','rayon');
  $hasKeteranganAngkutan = $columnExists($pdo,'angkutan_pupuk','keterangan');
  $hasNoSPBAngkut        = $columnExists($pdo,'angkutan_pupuk','no_spb');
  $hasNoAU58AngkutMain   = $columnExists($pdo,'angkutan_pupuk','no_au_58');
  $hasNoAU58AngkutAlt    = $columnExists($pdo,'angkutan_pupuk','no_au58');
  $hasCatatanAngkut      = $columnExists($pdo,'angkutan_pupuk','catatan');
  $hasSupirAngkut        = $columnExists($pdo,'angkutan_pupuk','supir');

  // Kolom ID baru (untuk join master)
  $hasRayonIdM  = $columnExists($pdo, 'menabur_pupuk',  'rayon_id');
  $hasAplIdM    = $columnExists($pdo, 'menabur_pupuk',  'apl_id');
  $hasKetIdM    = $columnExists($pdo, 'menabur_pupuk',  'keterangan_id');

  $hasRayonIdA  = $columnExists($pdo, 'angkutan_pupuk', 'rayon_id');
  $hasGudangIdA = $columnExists($pdo, 'angkutan_pupuk', 'gudang_asal_id');
  $hasKetIdA    = $columnExists($pdo, 'angkutan_pupuk', 'keterangan_id');

  // Map kebun id->kode/nama
  $kebuns=$pdo->query("SELECT id,kode,nama_kebun FROM md_kebun")->fetchAll(PDO::FETCH_ASSOC);
  $idToKode=[]; $idToNama=[];
  foreach($kebuns as $k){ $idToKode[(int)$k['id']]=$k['kode']; $idToNama[(int)$k['id']]=$k['nama_kebun']; }

  // ===== Query + headers
  if ($tab==='angkutan'){
    $judul="Data Angkutan Pupuk Kimia";
    $selectK=''; $joinK='';
    if($hasKebunAngkutId){
      $selectK=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinK=" LEFT JOIN md_kebun kb ON kb.id=a.kebun_id ";
    } elseif($hasKebunAngkutKod){
      $selectK=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinK=" LEFT JOIN md_kebun kb ON kb.kode=a.kebun_kode ";
    }

    // No SPB (baru) dengan fallback kolom lama
    $selectSPB = '';
    if     ($hasNoSPBAngkut)       $selectSPB = ", a.no_spb AS no_spb";
    elseif ($hasNoAU58AngkutMain)  $selectSPB = ", a.no_au_58 AS no_spb";
    elseif ($hasNoAU58AngkutAlt)   $selectSPB = ", a.no_au58  AS no_spb";
    elseif ($hasCatatanAngkut)     $selectSPB = ", a.catatan  AS no_spb";

    $selectSupir = $hasSupirAngkut ? ", a.supir" : ", NULL AS supir";

    // JOIN master baru (rayon/gudang/keterangan)
    $selectRayon  = $hasRayonIdA  ? ", r.nama AS rayon_nama"        : "";
    $joinRayon    = $hasRayonIdA  ? " LEFT JOIN md_rayon r ON r.id = a.rayon_id" : "";
    $selectGudang = $hasGudangIdA ? ", g.nama AS gudang_asal_nama"  : "";
    $joinGudang   = $hasGudangIdA ? " LEFT JOIN md_asal_gudang g ON g.id = a.gudang_asal_id" : "";
    $selectKet    = $hasKetIdA    ? ", k.keterangan AS keterangan_text" : "";
    $joinKet      = $hasKetIdA    ? " LEFT JOIN md_keterangan k ON k.id = a.keterangan_id" : "";

    $where=" WHERE 1=1"; $p=[];
    if($f_unit_id!==''){ $where.=" AND a.unit_tujuan_id=:uid"; $p[':uid']=(int)$f_unit_id; }
    if($f_kebun_id!==''){
      if($hasKebunAngkutId){ $where.=" AND a.kebun_id=:kid"; $p[':kid']=(int)$f_kebun_id; }
      elseif($hasKebunAngkutKod){ $where.=" AND a.kebun_kode=:kkod"; $p[':kkod']=(string)($idToKode[(int)$f_kebun_id]??''); }
    }
    if($f_tanggal!==''){ $where.=" AND a.tanggal=:tgl"; $p[':tgl']=$f_tanggal; }
    if($f_bulan!=='' && ctype_digit($f_bulan)){ $where.=" AND MONTH(a.tanggal)=:bln"; $p[':bln']=(int)$f_bulan; }
    if($f_jenis!==''){ $where.=" AND a.jenis_pupuk=:jp"; $p[':jp']=$f_jenis; }

    // Filter Rayon/Keterangan via ID atau fallback teks (mirip PDF)
    if ($f_rayon_id !== '' && $hasRayonIdA) {
      $where .= " AND a.rayon_id = :rid"; $p[':rid'] = $f_rayon_id;
    } elseif ($f_rayon !== '' && $hasRayonAngkutan) {
      $where .= " AND a.rayon LIKE :ry"; $p[':ry'] = "%$f_rayon%";
    }
    if ($f_gudang_id !== '' && $hasGudangIdA) {
      $where .= " AND a.gudang_asal_id = :gid"; $p[':gid'] = $f_gudang_id;
    }
    if ($f_keterangan_id !== '' && $hasKetIdA) {
      $where .= " AND a.keterangan_id = :kid"; $p[':kid'] = $f_keterangan_id;
    } elseif ($f_keterangan !== '' && $hasKeteranganAngkutan) {
      $where .= " AND a.keterangan LIKE :ket"; $p[':ket'] = "%$f_keterangan%";
    }

    $fullJoins = $joinK . $joinRayon . $joinGudang . $joinKet;

    $sql="SELECT a.*, u.nama_unit AS unit_tujuan_nama
                 $selectK $selectSPB $selectSupir
                 $selectRayon $selectGudang $selectKet
          FROM angkutan_pupuk a
          LEFT JOIN units u ON u.id=a.unit_tujuan_id
          $fullJoins
          $where
          ORDER BY a.tanggal DESC, a.id DESC";
    $st=$pdo->prepare($sql);
    foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
    $st->execute();
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);

    $headers=['Kebun','Rayon','Gudang Asal','Unit Tujuan','Tanggal','Jenis Pupuk','Jumlah (Kg)','No SPB','Keterangan','Supir'];

  } else {
    // ===== MENABUR
    $judul="Data Penaburan Pupuk Kimia";
    $selectK=''; $joinK='';
    if($hasKebunMenaburId){
      $selectK=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinK=" LEFT JOIN md_kebun kb ON kb.id=m.kebun_id ";
    } elseif($hasKebunMenaburKod){
      $selectK=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinK=" LEFT JOIN md_kebun kb ON kb.kode=m.kebun_kode ";
    }

    $selectTT = ", COALESCE(tt.tahun, ".($hasTTAngka?"m.tahun_tanam":"NULL").") AS t_tanam";
    $joinTT   = "";
    if($hasTTId)       $joinTT=" LEFT JOIN md_tahun_tanam tt ON tt.id=m.tahun_tanam_id ";
    elseif($hasTTAngka)$joinTT=" LEFT JOIN md_tahun_tanam tt ON tt.tahun=m.tahun_tanam ";

    $selectTahun   = $hasTahunMenabur ? ", m.tahun AS tahun_input" : ", YEAR(m.tanggal) AS tahun_input";
    $selectAPL_Old = $aplCol ? ", m.`$aplCol` AS apl_text" : ", NULL AS apl_text"; // teks APL lama

    // Alias No AU-58
    $selectNoAU = '';
    if     ($hasNoAU58MenaburMain) $selectNoAU = ", m.no_au_58";
    elseif ($hasNoAU58MenaburAlt)  $selectNoAU = ", m.no_au58 AS no_au_58";
    elseif ($hasCatatanMenabur)    $selectNoAU = ", m.catatan AS no_au_58";

    // JOIN master baru (rayon/apl/keterangan)
    $selectRayon  = $hasRayonIdM ? ", r.nama AS rayon_nama" : "";
    $joinRayon    = $hasRayonIdM ? " LEFT JOIN md_rayon r ON r.id = m.rayon_id" : "";
    $selectAplNew = $hasAplIdM   ? ", apl.nama AS apl_nama" : "";
    $joinApl      = $hasAplIdM   ? " LEFT JOIN md_apl apl ON apl.id = m.apl_id" : "";
    $selectKet    = $hasKetIdM   ? ", k.keterangan AS keterangan_text" : "";
    $joinKet      = $hasKetIdM   ? " LEFT JOIN md_keterangan k ON k.id = m.keterangan_id" : "";

    $where=" WHERE 1=1"; $p=[];
    if($f_unit_id!==''){ $where.=" AND m.unit_id=:uid"; $p[':uid']=(int)$f_unit_id; }
    if($f_kebun_id!==''){
      if($hasKebunMenaburId){ $where.=" AND m.kebun_id=:kid"; $p[':kid']=(int)$f_kebun_id; }
      elseif($hasKebunMenaburKod){ $where.=" AND m.kebun_kode=:kkod"; $p[':kkod']=(string)($idToKode[(int)$f_kebun_id]??''); }
    }
    if($f_tanggal!==''){ $where.=" AND m.tanggal=:tgl"; $p[':tgl']=$f_tanggal; }
    if($f_bulan!=='' && ctype_digit($f_bulan)){ $where.=" AND MONTH(m.tanggal)=:bln"; $p[':bln']=(int)$f_bulan; }
    if($f_jenis!==''){ $where.=" AND m.jenis_pupuk=:jp"; $p[':jp']=$f_jenis; }

    // Filter Rayon/APL/Keterangan via ID atau fallback teks (mirip PDF)
    if ($f_rayon_id !== '' && $hasRayonIdM) {
      $where .= " AND m.rayon_id = :rid"; $p[':rid'] = $f_rayon_id;
    } elseif ($f_rayon !== '' && $hasRayonMenabur) {
      $where .= " AND m.rayon LIKE :ry"; $p[':ry'] = "%$f_rayon%";
    }
    if ($f_apl_id !== '' && $hasAplIdM) {
      $where .= " AND m.apl_id = :aid"; $p[':aid'] = $f_apl_id;
    }
    if ($f_keterangan_id !== '' && $hasKetIdM) {
      $where .= " AND m.keterangan_id = :kid"; $p[':kid'] = $f_keterangan_id;
    } elseif ($f_keterangan !== '' && $hasKeteranganMenabur) {
      $where .= " AND m.keterangan LIKE :ket"; $p[':ket'] = "%$f_keterangan%";
    }

    $fullJoins = $joinK . $joinTT . $joinRayon . $joinApl . $joinKet;

    $sql="SELECT m.*, u.nama_unit AS unit_nama
                 $selectK
                 $selectTT
                 $selectTahun
                 $selectAPL_Old
                 $selectNoAU
                 $selectRayon $selectAplNew $selectKet
          FROM menabur_pupuk m
          LEFT JOIN units u ON u.id=m.unit_id
          $fullJoins
          $where
          ORDER BY m.tanggal DESC, m.id DESC";
    $st=$pdo->prepare($sql);
    foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
    $st->execute();
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);

    $headers=['Tahun','Kebun','Unit','T.TANAM','Blok','Rayon','Tanggal','Jenis Pupuk','APL','Dosis (kg/ha)','Jumlah (Kg)','Luas (Ha)','Invt. Pokok','No AU-58','Keterangan'];
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
  $sheet->getStyle($C1.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('059669');

  // Subjudul
  $sheet->mergeCells($C1.'2:'.$CL.'2'); $sheet->setCellValue($C1.'2', $judul);
  $sheet->getStyle($C1.'2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('065F46');
  $sheet->getStyle($C1.'2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

  // Ringkasan filter (termasuk ID baru)
  $sheet->mergeCells($C1.'3:'.$CL.'3');
  $sheet->setCellValue($C1.'3', 'Filter: '
    .'tab='.$tab
    .' | unit_id='.($f_unit_id===''?'Semua':$f_unit_id)
    .' | kebun_id='.($f_kebun_id===''?'Semua':$f_kebun_id)
    .' | tanggal='.($f_tanggal?:'Semua')
    .' | bulan='.($f_bulan?:'Semua')
    .' | jenis='.($f_jenis?:'Semua')
    .' | rayon_id='.($f_rayon_id?:'Semua')
    .' | apl_id='.($f_apl_id?:'Semua')
    .' | ket_id='.($f_keterangan_id?:'Semua')
    .' | gudang_id='.($f_gudang_id?:'Semua')
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
        // Tampilkan nama master jika ada, fallback ke teks lama
        $rayonDisplay  = $row['rayon_nama']       ?? ($row['rayon']        ?? '');
        $gudangDisplay = $row['gudang_asal_nama'] ?? ($row['gudang_asal']  ?? '');
        $ketDisplay    = $row['keterangan_text']  ?? ($row['keterangan']   ?? '');

        $vals=[
          (string)($row['kebun_nama'] ?? ($row['kebun_kode'] ?? '-')),
          (string)$rayonDisplay,
          (string)$gudangDisplay,
          (string)($row['unit_tujuan_nama'] ?? '-'),
          (string)($row['tanggal'] ?? ''),
          (string)($row['jenis_pupuk'] ?? ''),
          (float) ($row['jumlah'] ?? 0),
          (string)($row['no_spb'] ?? ''),   // alias SPB
          (string)$ketDisplay,
          (string)($row['supir'] ?? ''),
        ];
        $tot_jumlah += (float)($row['jumlah'] ?? 0);

      } else {
        // Menabur: APL prioritas ke master (apl_nama), fallback apl_text (lama)
        $rayonDisplay = $row['rayon_nama'] ?? ($row['rayon'] ?? '');
        $aplDisplay   = $row['apl_nama']   ?? ($row['apl_text'] ?? '-');
        $ketDisplay   = $row['keterangan_text'] ?? ($row['keterangan'] ?? '');

        $dosis = (array_key_exists('dosis',$row) && $row['dosis']!==null && $row['dosis']!=='') ? (float)$row['dosis'] : null;

        $vals=[
          (string)($row['tahun_input'] ?? ''),                                        // Tahun
          (string)($row['kebun_nama'] ?? ($row['kebun_kode'] ?? '-')),               // Kebun
          (string)($row['unit_nama'] ?? '-'),                                        // Unit
          (string)($row['t_tanam'] ?? '-'),                                          // T.TANAM
          (string)($row['blok'] ?? ''),                                              // Blok
          (string)$rayonDisplay,                                                     // Rayon (master/teks)
          (string)($row['tanggal'] ?? ''),                                           // Tanggal
          (string)($row['jenis_pupuk'] ?? ''),                                       // Jenis
          (string)$aplDisplay,                                                       // APL (master/teks)
          $dosis,                                                                     // Dosis
          (float)($row['jumlah'] ?? 0),                                              // Jumlah
          (float)($row['luas'] ?? 0),                                                // Luas
          (int)  ($row['invt_pokok'] ?? 0),                                          // Invt
          (string)($row['no_au_58'] ?? ''),                                          // No AU-58
          (string)$ketDisplay,                                                       // Keterangan (master/teks)
        ];
        if($dosis!==null){ $tot_dosis += $dosis; $cnt_dosis++; }
        $tot_jumlah += (float)($row['jumlah'] ?? 0);
        $tot_luas   += (float)($row['luas'] ?? 0);
        $tot_invt   += (int)  ($row['invt_pokok'] ?? 0);
      }

      foreach($vals as $i=>$v){ $sheet->setCellValue($cols[$i].$r,$v); }
      $sheet->getStyle($C1.$r.':'.$CL.$r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

      if ($tab==='angkutan'){
        // Kolom Jumlah (index 6)
        $sheet->getStyle($cols[6].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
      } else {
        // Dosis, Jumlah, Luas, Invt (index 9..12)
        foreach([9,10,11,12] as $idx){
          $sheet->getStyle($cols[$idx].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
      }
      $r++;
    }
  }

  // ===== TOTAL
  if ($tab==='angkutan'){
    $sheet->mergeCells($cols[0].$r.':'.$cols[5].$r);
    $sheet->setCellValue($cols[0].$r,'TOTAL JUMLAH (Kg)');
    $sheet->getStyle($cols[0].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($cols[0].$r)->getFont()->setBold(true);
    $sheet->setCellValue($cols[6].$r,$tot_jumlah);   // kolom Jumlah
    $sheet->getStyle($cols[6].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
  } else {
    $avg_dosis = $cnt_dosis ? ($tot_dosis/$cnt_dosis) : 0;
    $sheet->mergeCells($cols[0].$r.':'.$cols[8].$r); // sampai kolom APL
    $sheet->setCellValue($cols[0].$r,'TOTAL');
    $sheet->getStyle($cols[0].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($cols[0].$r)->getFont()->setBold(true);
    $sheet->setCellValue($cols[9].$r,$avg_dosis);     // Dosis rata2
    $sheet->setCellValue($cols[10].$r,$tot_jumlah);   // Jumlah
    $sheet->setCellValue($cols[11].$r,$tot_luas);     // Luas
    $sheet->setCellValue($cols[12].$r,$tot_invt);     // Invt
    foreach([9,10,11,12] as $idx){
      $sheet->getStyle($cols[$idx].$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
  }
  $sheet->getStyle($C1.$r.':'.$CL.$r)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
  $sheet->getStyle($C1.$r.':'.$CL.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1FAF6');

  foreach($cols as $c){ $sheet->getColumnDimension($c)->setAutoSize(true); }

  // ===== Nama file ikut filter (termasuk ID baru)
  $fname='Pemupukan_Kimia_'.$tab;
  if($f_unit_id!=='')      $fname.='_UNIT-'.$f_unit_id;
  if($f_kebun_id!=='')     $fname.='_KEBUN-'.$f_kebun_id;
  if($f_tanggal!=='')      $fname.='_TGL-'.$f_tanggal;
  if($f_bulan!=='')        $fname.='_BLN-'.$f_bulan;
  if($f_jenis!=='')        $fname.='_JENIS-'.preg_replace('/[^A-Za-z0-9_\-]/','',$f_jenis);
  if($f_rayon_id!=='')     $fname.='_RAYONID-'.$f_rayon_id;
  if($f_apl_id!=='')       $fname.='_APLID-'.$f_apl_id;
  if($f_keterangan_id!=='')$fname.='_KETID-'.$f_keterangan_id;
  if($f_gudang_id!=='')    $fname.='_GUDANGID-'.$f_gudang_id;
  $fname.='.xlsx';

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  header('Cache-Control: max-age=0');
  (new Xlsx($ss))->save('php://output'); exit;

}catch(Throwable $e){
  http_response_code(500);
  echo "Error: ".$e->getMessage();
}
