<?php
// pages/pemeliharaan_pn_crud.php — LIST + CRUD PN
session_start();
header('Content-Type: application/json; charset=utf-8');
function out($ok,$msg,$extra=[]){ echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$extra)); exit; }

require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

$AFDS = ['AFD01','AFD02','AFD03','AFD04','AFD05','AFD06','AFD07','AFD08','AFD09','AFD10'];

/* ============ LIST ============ */
if (($_GET['action'] ?? '') === 'list') {
  $tahun   = (int)($_GET['tahun'] ?? date('Y'));
  $afd     = trim((string)($_GET['afd'] ?? ''));
  $jenis   = trim((string)($_GET['jenis'] ?? ''));
  $hk      = trim((string)($_GET['hk'] ?? ''));
  $ket     = trim((string)($_GET['keterangan'] ?? ''));
  $kebunId = (int)($_GET['kebun_id'] ?? 0);
  $stood   = trim((string)($_GET['stood'] ?? ''));

  $where = "WHERE tahun=:t";
  $p = [':t'=>$tahun];

  if ($afd!=='') {
    if (!in_array($afd,$AFDS,true)) out(false,'AFD tidak valid');
    $where.=" AND unit_kode=:u"; $p[':u']=$afd;
  }
  if ($jenis!==''){ $where.=" AND jenis_nama=:j"; $p[':j']=$jenis; }
  if ($hk!==''){ $where.=" AND hk=:hk"; $p[':hk']=$hk; }
  if ($ket!==''){ $where.=" AND keterangan LIKE :k"; $p[':k']="%$ket%"; }
  if ($kebunId>0){ $where.=" AND kebun_id=:kb"; $p[':kb']=$kebunId; }
  if ($stood!==''){ $where.=" AND stood LIKE :st"; $p[':st']="%$stood%"; }

  $sql="SELECT * FROM pemeliharaan_pn $where ORDER BY jenis_nama ASC, unit_kode ASC, id ASC";
  $st=$pdo->prepare($sql);
  foreach($p as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
  $st->execute();
  $rows=$st->fetchAll(PDO::FETCH_ASSOC);

  // peta kebun id->nama untuk tampilan cepat
  $kb = $pdo->query("SELECT id, nama_kebun FROM md_kebun")->fetchAll(PDO::FETCH_KEY_PAIR);

  out(true, 'ok', ['rows'=>$rows, 'kebun_map'=>$kb]);
}

/* ===== POST actions require auth + CSRF + role ===== */
if ($_SERVER['REQUEST_METHOD']!=='POST') out(false,'Method not allowed');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true) out(false,'Unauthorized');
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token'])) out(false,'CSRF tidak valid');

$role = $_SESSION['user_role'] ?? 'staf';
if ($role === 'staf') out(false,'Tidak memiliki izin');

$act = $_POST['action'] ?? '';

/* Helpers */
function getNamaById(PDO $pdo, string $table, int $id, string $col='nama'){
  if (!$id) return null;
  if ($table==='md_kebun') $col='nama_kebun';
  $st = $pdo->prepare("SELECT $col AS n FROM $table WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$id]);
  return $st->fetchColumn() ?: null;
}

try{
  if ($act==='store' || $act==='update'){
    $id   = (int)($_POST['id'] ?? 0);
    $unit = trim((string)($_POST['unit_kode'] ?? ''));
    if (!in_array($unit,$AFDS,true)) out(false,'AFD/unit_kode tidak valid');

    $tahun = (int)($_POST['tahun'] ?? 0);
    if ($tahun<2000 || $tahun>2100) out(false,'Tahun tidak valid');

    $kebun_id = (int)($_POST['kebun_id'] ?? 0);
    $kebun_nm = $kebun_id ? getNamaById($pdo,'md_kebun',$kebun_id,'nama_kebun') : null;

    $jenis_id = (int)($_POST['jenis_id'] ?? 0);
    $jenis_nm = $jenis_id ? getNamaById($pdo,'md_pemeliharaan_pn',$jenis_id,'nama') : null;
    if (!$jenis_nm) out(false,'Jenis pekerjaan wajib dipilih');

    $stood = trim((string)($_POST['stood'] ?? ''));
    $ket   = trim((string)($_POST['ket'] ?? ''));
    $hk    = trim((string)($_POST['hk'] ?? ''));
    $sat   = trim((string)($_POST['satuan'] ?? ''));
    $angg  = (float)($_POST['anggaran_tahun'] ?? 0);

    $m = [];
    foreach(['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'] as $k){
      $m[$k] = (float)($_POST[$k] ?? 0);
    }
    $ketera = trim((string)($_POST['keterangan'] ?? ''));

    if ($act==='store'){
      $sql="INSERT INTO pemeliharaan_pn
        (tahun, kebun_id, kebun_nama, unit_kode, stood, jenis_nama, ket, hk, satuan, anggaran_tahun,
         jan,feb,mar,apr,mei,jun,jul,agu,sep,okt,nov,des, keterangan, created_at, updated_at)
        VALUES
        (:tahun, :kebun_id, :kebun_nama, :unit_kode, :stood, :jenis_nama, :ket, :hk, :satuan, :anggaran,
         :jan,:feb,:mar,:apr,:mei,:jun,:jul,:agu,:sep,:okt,:nov,:des, :ketera, NOW(), NOW())";
      $st=$pdo->prepare($sql);
      $st->execute([
        ':tahun'=>$tahun, ':kebun_id'=>$kebun_id?:null, ':kebun_nama'=>$kebun_nm, ':unit_kode'=>$unit,
        ':stood'=>$stood, ':jenis_nama'=>$jenis_nm, ':ket'=>$ket, ':hk'=>$hk, ':satuan'=>$sat, ':anggaran'=>$angg,
        ':jan'=>$m['jan'], ':feb'=>$m['feb'], ':mar'=>$m['mar'], ':apr'=>$m['apr'], ':mei'=>$m['mei'],
        ':jun'=>$m['jun'], ':jul'=>$m['jul'], ':agu'=>$m['agu'], ':sep'=>$m['sep'], ':okt'=>$m['okt'],
        ':nov'=>$m['nov'], ':des'=>$m['des'], ':ketera'=>$ketera
      ]);
      out(true,'Data berhasil ditambahkan');
    } else {
      if ($id<=0) out(false,'ID tidak valid');
      $sql="UPDATE pemeliharaan_pn SET
            tahun=:tahun, kebun_id=:kebun_id, kebun_nama=:kebun_nama,
            unit_kode=:unit_kode, stood=:stood, jenis_nama=:jenis_nama, ket=:ket, hk=:hk, satuan=:satuan, anggaran_tahun=:anggaran,
            jan=:jan, feb=:feb, mar=:mar, apr=:apr, mei=:mei, jun=:jun, jul=:jul, agu=:agu, sep=:sep, okt=:okt, nov=:nov, des=:des,
            keterangan=:ketera, updated_at=NOW()
            WHERE id=:id";
      $st=$pdo->prepare($sql);
      $st->execute([
        ':tahun'=>$tahun, ':kebun_id'=>$kebun_id?:null, ':kebun_nama'=>$kebun_nm,
        ':unit_kode'=>$unit, ':stood'=>$stood, ':jenis_nama'=>$jenis_nm, ':ket'=>$ket, ':hk'=>$hk, ':satuan'=>$sat, ':anggaran'=>$angg,
        ':jan'=>$m['jan'], ':feb'=>$m['feb'], ':mar'=>$m['mar'], ':apr'=>$m['apr'], ':mei'=>$m['mei'],
        ':jun'=>$m['jun'], ':jul'=>$m['jul'], ':agu'=>$m['agu'], ':sep'=>$m['sep'], ':okt'=>$m['okt'], ':nov'=>$m['nov'], ':des'=>$m['des'],
        ':ketera'=>$ketera, ':id'=>$id
      ]);
      out(true,'Data berhasil diperbarui');
    }
  }

  if ($act==='delete'){
    $id=(int)($_POST['id']??0);
    if ($id<=0) out(false,'ID tidak valid');
    $pdo->prepare("DELETE FROM pemeliharaan_pn WHERE id=:id")->execute([':id'=>$id]);
    out(true,'Data berhasil dihapus');
  }

  out(false,'Action tidak dikenali');
}
catch(Throwable $e){
  out(false,'Server error: '.$e->getMessage());
}
