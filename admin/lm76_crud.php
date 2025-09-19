<?php
session_start();
header('Content-Type: application/json');
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true){ echo json_encode(['success'=>false,'message'=>'Login dulu']); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['success'=>false,'message'=>'Metode salah']); exit; }
if(empty($_SESSION['csrf_token']) || $_SESSION['csrf_token']!==($_POST['csrf_token']??'')){ echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit; }

require_once '../config/database.php';
$db=new Database(); $conn=$db->getConnection();
$act=$_POST['action']??'';

try{
  if($act==='list'){
    $w=[]; $p=[];
    if(!empty($_POST['unit_id'])){ $w[]='l.unit_id=:u'; $p[':u']=$_POST['unit_id']; }
    if(!empty($_POST['bulan'])){ $w[]='l.bulan=:b'; $p[':b']=$_POST['bulan']; }
    if(!empty($_POST['tahun'])){ $w[]='l.tahun=:t'; $p[':t']=(int)$_POST['tahun']; }
    $sql="SELECT l.*, u.nama_unit FROM lm76 l JOIN units u ON l.unit_id=u.id".
         (count($w)?" WHERE ".implode(' AND ',$w):"").
         " ORDER BY l.tahun DESC, FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";
    $st=$conn->prepare($sql); $st->execute($p);
    echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  if(in_array($act,['store','update'],true)){
    $cols = ['unit_id','bulan','tahun','tt','blok','luas_ha','jumlah_pohon','varietas','prod_bi_realisasi','prod_bi_anggaran','prod_sd_realisasi','prod_sd_anggaran','jumlah_tandan_bi','pstb_ton_ha_bi','pstb_ton_ha_tl','panen_hk_realisasi','panen_ha_bi','panen_ha_sd','frek_panen_bi','frek_panen_sd'];
    $data=[]; foreach($cols as $c){ $data[$c] = $_POST[$c] ?? null; }

    if($act==='store'){
      $sql="INSERT INTO lm76 (".implode(',',$cols).") VALUES (".implode(',',array_map(fn($c)=>":$c",$cols)).")";
      $st=$conn->prepare($sql); $st->execute(array_combine(array_map(fn($c)=>":$c",$cols), array_values($data)));
      echo json_encode(['success'=>true,'message'=>'Data LM-76 tersimpan']); exit;
    }else{
      $id=(int)($_POST['id']??0); if($id<=0){echo json_encode(['success'=>false,'message'=>'ID invalid']);exit;}
      $assign=implode(',', array_map(fn($c)=>"$c=:$c",$cols));
      $sql="UPDATE lm76 SET $assign WHERE id=:id"; $params=array_combine(array_map(fn($c)=>":$c",$cols), array_values($data)); $params[':id']=$id;
      $st=$conn->prepare($sql); $st->execute($params);
      echo json_encode(['success'=>true,'message'=>'Data LM-76 diperbarui']); exit;
    }
  }

  if($act==='delete'){
    $id=(int)($_POST['id']??0); if($id<=0){echo json_encode(['success'=>false,'message'=>'ID invalid']);exit;}
    $conn->prepare("DELETE FROM lm76 WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data LM-76 dihapus']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali']);
}catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
