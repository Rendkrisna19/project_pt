<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { echo json_encode(['success'=>false,'message'=>'Login dulu']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Metode salah']); exit; }
if (empty($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== ($_POST['csrf_token'] ?? '')) {
  echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit;
}

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* helpers */
function col_exists(PDO $pdo, $table, $col){
  $st=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]); return (bool)$st->fetchColumn();
}
$hasKebun = col_exists($conn, 'lm76', 'kebun_id');

function s($k){ return trim((string)($_POST[$k] ?? '')); }
function i($k){ $v=$_POST[$k]??null; return ($v===''||$v===null)?null:(ctype_digit((string)$v)||is_int($v)?(int)$v:null); }
function d($k){ $v=$_POST[$k]??null; return ($v===''||$v===null)?null:(is_numeric($v)?(string)$v:null); }

$ttExists = function(PDO $c, $tt){
  if ($tt==='') return false;
  $st=$c->prepare("SELECT 1 FROM md_tahun_tanam WHERE tahun=:t LIMIT 1");
  $st->execute([':t'=>$tt]); return (bool)$st->fetchColumn();
};

$act = $_POST['action'] ?? '';

try {

  /* LIST */
  if ($act==='list'){
    $w=[]; $p=[];
    if ($hasKebun && s('kebun_id')!==''){ $w[]='l.kebun_id=:kid'; $p[':kid']=(int)$_POST['kebun_id']; }
    if (s('unit_id')!==''){ $w[]='l.unit_id=:uid'; $p[':uid']=(int)$_POST['unit_id']; }
    if (s('bulan')  !==''){ $w[]='l.bulan=:bln';  $p[':bln']=s('bulan'); }
    if (s('tahun')  !==''){ $w[]='l.tahun=:thn';  $p[':thn']=(int)$_POST['tahun']; }
    if (s('tt')     !==''){ $w[]='l.tt=:tt';      $p[':tt']=s('tt'); }

    $sql = "SELECT l.*, u.nama_unit".($hasKebun?", kb.nama_kebun":"")."
            FROM lm76 l
            JOIN units u ON u.id=l.unit_id
            ".($hasKebun?"LEFT JOIN md_kebun kb ON kb.id=l.kebun_id":"")."
            ".(count($w)?"WHERE ".implode(' AND ',$w):"")."
            ORDER BY l.tahun DESC,
            FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            ".($hasKebun?"kb.nama_kebun, ":"")."u.nama_unit";
    $st=$conn->prepare($sql); $st->execute($p);
    echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  /* STORE / UPDATE */
  if (in_array($act,['store','update'],true)){

    $errors=[];
    $unit_id=i('unit_id'); $bulan=s('bulan'); $tahun=i('tahun'); $tt=s('tt');
    if(!$unit_id) $errors[]='Unit wajib dipilih.';
    if($bulan==='') $errors[]='Bulan wajib dipilih.';
    if($tahun===null) $errors[]='Tahun wajib diisi.';
    if($tt==='') $errors[]='Tahun tanam wajib dipilih.';
    elseif(!$ttExists($conn,$tt)) $errors[]='Tahun tanam tidak valid (tidak ada di master).';
    if($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    // kolom final (13 input)
    $cols = [
      ...($hasKebun?['kebun_id']:[]),
      'unit_id','bulan','tahun','tt','luas_ha','jumlah_pohon','anggaran_kg','realisasi_kg',
      'jumlah_tandan','jumlah_hk','panen_ha','frekuensi'
    ];

    $payload=[];
    if($hasKebun) $payload['kebun_id']=i('kebun_id');
    $payload['unit_id']=$unit_id; $payload['bulan']=$bulan; $payload['tahun']=$tahun; $payload['tt']=$tt;

    $payload['luas_ha']       = d('luas_ha');
    $payload['jumlah_pohon']  = i('jumlah_pohon');
    $payload['anggaran_kg']   = d('anggaran_kg');
    $payload['realisasi_kg']  = d('realisasi_kg');
    $payload['jumlah_tandan'] = i('jumlah_tandan');
    $payload['jumlah_hk']     = d('jumlah_hk');
    $payload['panen_ha']      = d('panen_ha');
    $payload['frekuensi']     = d('frekuensi');

    $params=[]; foreach($cols as $c){ $params[":$c"]=$payload[$c]??null; }

    if ($act==='store'){
      $sql="INSERT INTO lm76 (".implode(',',$cols).") VALUES (".implode(',',array_map(fn($c)=>":$c",$cols)).")";
      $st=$conn->prepare($sql); $st->execute($params);
      echo json_encode(['success'=>true,'message'=>'Data LM-76 tersimpan']); exit;
    } else {
      $id=i('id'); if(!$id){ echo json_encode(['success'=>false,'message'=>'ID invalid']); exit; }
      $assign=implode(',',array_map(fn($c)=>"$c=:$c",$cols));
      $sql="UPDATE lm76 SET $assign WHERE id=:id"; $params[':id']=$id;
      $st=$conn->prepare($sql); $st->execute($params);
      echo json_encode(['success'=>true,'message'=>'Data LM-76 diperbarui']); exit;
    }
  }

  /* DELETE */
  if ($act==='delete'){
    $id=i('id'); if(!$id){ echo json_encode(['success'=>false,'message'=>'ID invalid']); exit; }
    $conn->prepare("DELETE FROM lm76 WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data LM-76 dihapus']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali']);
}
catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
