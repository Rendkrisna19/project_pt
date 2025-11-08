<?php
// pages/pemeliharaan_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* ==== helpers ==== */
function col_exists(PDO $c, string $t, string $col): bool {
  static $cache=[]; $key="$t";
  if (!isset($cache[$key])) {
    $st=$c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
    $st->execute([':t'=>$t]);
    $cache[$key]=array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME'));
  }
  return in_array(strtolower($col), $cache[$key]??[], true);
}
function qlike(string $s){ return "%$s%"; }

$action = $_GET['action'] ?? '';

/* =========================================================
 * ACTION: afd_list  (KHUSUS TM)
 * Param opsional: kebun_id
 * Return: daftar AFD dari md_afdeling + unit_id yang match
 * =======================================================*/
if ($action === 'afd_list') {
  $tab = $_GET['tab'] ?? 'TM';
  if ($tab !== 'TM') { echo json_encode(['success'=>true,'rows'=>[]]); exit; }

  $kebun_id = ($_GET['kebun_id']??'')==='' ? null : (int)$_GET['kebun_id'];

  $sql = "SELECT a.id AS afd_id, a.kode, a.nama,
                 a.kebun_id, a.rayon_id,
                 r.nama_rayon,
                 u.id AS unit_id
          FROM md_afdeling a
          LEFT JOIN md_rayon r ON r.id=a.rayon_id
          LEFT JOIN units u    ON u.nama_unit = a.kode
          ".($kebun_id? "WHERE a.kebun_id=:kid" : "")."
          ORDER BY a.kode";
  $st = $conn->prepare($sql);
  if ($kebun_id) $st->bindValue(':kid', $kebun_id, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['success'=>true,'rows'=>$rows]); exit;
}

/* =========================================================
 * ACTION: rows_by_afd  (digunakan ketika user expand AFD)
 * Param: unit_id (wajib), tab (default TM), + semua filter yg sama
 * =======================================================*/
if ($action === 'rows_by_afd') {

  $tab      = $_GET['tab'] ?? 'TM';
  $isBibit  = in_array($tab, ['BIBIT_PN','BIBIT_MN'], true);
  $unit_id  = (int)($_GET['unit_id'] ?? 0);
  if (!$unit_id) { echo json_encode(['success'=>false,'message'=>'unit_id wajib']); exit; }

  $f_bulan  = trim((string)($_GET['bulan']??''));
  $f_tahun  = ($_GET['tahun']??'')===''? '' : (int)$_GET['tahun'];
  $f_jenis  = ($_GET['jenis_id']??'')===''? '' : (int)$_GET['jenis_id'];
  $f_tenaga = ($_GET['tenaga_id']??'')===''? '' : (int)$_GET['tenaga_id'];
  $f_kebun  = ($_GET['kebun_id']??'')===''? '' : (int)$_GET['kebun_id'];
  $f_rayon  = trim((string)($_GET['rayon']??''));
  $f_bibit  = trim((string)($_GET['bibit']??''));
  $f_ket    = trim((string)($_GET['keterangan']??''));

  $hasKebunId = col_exists($conn,'pemeliharaan','kebun_id');
  $hasKet     = col_exists($conn,'pemeliharaan','keterangan');
  $hasSatR    = col_exists($conn,'pemeliharaan','satuan_rencana');
  $hasSatE    = col_exists($conn,'pemeliharaan','satuan_realisasi');
  $hasFilePdf = col_exists($conn,'pemeliharaan','file_pdf');

  $bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli",
                "Agustus","September","Oktober","November","Desember"];

  /* where */
  $where=" WHERE p.kategori=:k AND p.unit_id=:u";
  $p=[":k"=>$tab,":u"=>$unit_id];

  if($f_bulan!==''){ $where.=" AND p.bulan=:b"; $p[':b']=$f_bulan; }
  if($f_tahun!==''){ $where.=" AND p.tahun=:t"; $p[':t']=(int)$f_tahun; }

  if($f_jenis!==''){
    $st=$conn->prepare("SELECT nama FROM md_jenis_pekerjaan WHERE id=:id");
    $st->execute([':id'=>$f_jenis]);
    if ($jn=$st->fetchColumn()) { $where.=" AND p.jenis_pekerjaan=:jn"; $p[':jn']=$jn; }
  }
  if($f_tenaga!==''){
    $st=$conn->prepare("SELECT nama FROM md_tenaga WHERE id=:id");
    $st->execute([':id'=>$f_tenaga]);
    if ($tn=$st->fetchColumn()) { $where.=" AND p.tenaga=:tn"; $p[':tn']=$tn; }
  }
  if($f_kebun!==''){ if($hasKebunId){ $where.=" AND p.kebun_id=:kid"; $p[':kid']=(int)$f_kebun; } }
  if(!$isBibit){
    if($f_rayon!==''){ $where.=" AND p.rayon LIKE :ry"; $p[':ry']=qlike($f_rayon); }
  }else{
    if($f_bibit!==''){ $where.=" AND p.rayon LIKE :bb"; $p[':bb']=qlike($f_bibit); }
  }
  if($hasKet && $f_ket!==''){ $where.=" AND p.keterangan LIKE :ket"; $p[':ket']=qlike($f_ket); }

  $fileSel = $hasFilePdf ? "p.file_pdf" : "NULL AS file_pdf";
  $kebunSel= $hasKebunId ? "kb.nama_kebun AS kebun_nama" : "NULL AS kebun_nama";

  $sql="SELECT p.*, u.nama_unit AS unit_nama, $kebunSel, $fileSel,
               a.kode AS afd_kode, a.nama AS afd_nama, r.nama_rayon
        FROM pemeliharaan p
        LEFT JOIN units u        ON u.id=p.unit_id
        ".($hasKebunId?"LEFT JOIN md_kebun kb ON kb.id=p.kebun_id":"")."
        LEFT JOIN md_afdeling a  ON a.kode = u.nama_unit
        LEFT JOIN md_rayon r     ON r.id   = a.rayon_id
        $where
        ORDER BY p.tahun DESC,
                 FIELD(p.bulan,".implode(',',array_map(fn($b)=>$conn->quote($b),$bulanList))."),
                 p.id DESC";
  $st=$conn->prepare($sql);
  foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
  $st->execute();
  $rows=$st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'success'=>true,
    'rows'=>$rows,
    'flags'=>[
      'hasKebunId'=>$hasKebunId,
      'hasKet'=>$hasKet,
      'hasSatR'=>$hasSatR,
      'hasSatE'=>$hasSatE,
      'hasFilePdf'=>$hasFilePdf,
      'isBibit'=>$isBibit,
      'isStaf'=>($_SESSION['user_role'] ?? 'staf') === 'staf'
    ]
  ]); exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
