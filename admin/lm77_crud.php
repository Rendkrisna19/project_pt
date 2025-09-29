<?php
session_start();
header('Content-Type: application/json');
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true){ echo json_encode(['success'=>false,'message'=>'Login dulu']); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['success'=>false,'message'=>'Metode salah']); exit; }
if(empty($_SESSION['csrf_token']) || $_SESSION['csrf_token']!==($_POST['csrf_token']??'')){ echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit; }

require_once '../config/database.php';
$db=new Database(); $conn=$db->getConnection();
$act=$_POST['action']??'';

function i($k){ $v=$_POST[$k]??null; if($v===''||$v===null) return null; return ctype_digit((string)$v)?(int)$v:null; }
function s($k){ return trim((string)($_POST[$k]??'')); }

try{
  // === helper: cek kolom ===
  $cacheCols=[];
  $hasCol=function(PDO $c,$t,$col) use (&$cacheCols){
    if(!isset($cacheCols[$t])){
      $st=$c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t'=>$t]); $cacheCols[$t]=array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME');
    }
    return in_array($col,$cacheCols[$t]??[],true);
  };

  // ambil data kebun by id (untuk dapat kode + nama)
  $kebunById=function(PDO $c,$kid){
    if(!$kid) return [null,null,null];
    $st=$c->prepare("SELECT id, kode, nama_kebun FROM md_kebun WHERE id=:i");
    $st->execute([':i'=>$kid]);
    $r=$st->fetch(PDO::FETCH_ASSOC);
    return $r ? [(int)$r['id'], (string)$r['kode'], (string)$r['nama_kebun']] : [null,null,null];
  };

  // validasi: blok harus ada di md_blok untuk unit tsb
  $blokValid=function(PDO $c,$unitId,$blokKode){
    if(!$unitId || $blokKode==='') return false;
    $st=$c->prepare("SELECT 1 FROM md_blok WHERE unit_id=:u AND kode=:k LIMIT 1");
    $st->execute([':u'=>$unitId, ':k'=>$blokKode]);
    return (bool)$st->fetchColumn();
  };

  // validasi: tt harus ada di md_tahun_tanam (isian = angka tahun)
  $ttValid=function(PDO $c,$tt){
    if($tt==='') return true; // boleh kosong
    $st=$c->prepare("SELECT 1 FROM md_tahun_tanam WHERE tahun=:t LIMIT 1");
    $st->execute([':t'=>$tt]);
    return (bool)$st->fetchColumn();
  };

  if($act==='list'){
    $w=[];$p=[];

    if(($u=s('unit_id'))!==''){ $w[]='l.unit_id=:u'; $p[':u']=$u; }
    if(($b=s('bulan'))!==''){ $w[]='l.bulan=:b'; $p[':b']=$b; }
    if(($t=s('tahun'))!==''){ $w[]='l.tahun=:t'; $p[':t']=(int)$t; }

    // filter kebun_kode hanya jika kolomnya ada
    $hasKid  = $hasCol($conn,'lm77','kebun_id');
    $hasKkod = $hasCol($conn,'lm77','kebun_kode');
    if($hasKkod && ($kk=s('kebun_kode'))!==''){ $w[]='l.kebun_kode=:kk'; $p[':kk']=$kk; }

    // build join kebun aman
    $joinConds=[];
    if($hasKid)  $joinConds[]='k.id = l.kebun_id';
    if($hasKkod) $joinConds[]='k.kode = l.kebun_kode';
    $joinK = $joinConds ? ' LEFT JOIN md_kebun k ON ('.implode(' OR ',$joinConds).') ' : ' LEFT JOIN md_kebun k ON 1=0 ';

    $sql="SELECT l.*, u.nama_unit,
                 k.nama_kebun AS kebun_nama, k.kode AS kebun_kode
          FROM lm77 l
          JOIN units u ON l.unit_id=u.id
          $joinK ".
         (count($w)?" WHERE ".implode(' AND ',$w):"").
         " ORDER BY l.tahun DESC,
            FIELD(l.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";
    $st=$conn->prepare($sql); $st->execute($p);
    echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  if(in_array($act,['store','update'],true)){
    // kolom utama
    $cols=['unit_id','bulan','tahun','tt','blok','luas_ha','jumlah_pohon','pohon_ha','var_prod_bi','var_prod_sd','jtandan_per_pohon_bi','jtandan_per_pohon_sd','prod_tonha_bi','prod_tonha_sd_thi','prod_tonha_sd_tl','btr_bi','btr_sd_thi','btr_sd_tl','basis_borong_kg_hk','prestasi_kg_hk_bi','prestasi_kg_hk_sd','prestasi_tandan_hk_bi','prestasi_tandan_hk_sd'];
    $data=[]; foreach($cols as $c){ $data[$c]=$_POST[$c]??null; }

    // dukungan kebun (optional)
    $hasKid  = $hasCol($conn,'lm77','kebun_id');
    $hasKkod = $hasCol($conn,'lm77','kebun_kode');
    $kebun_id = i('kebun_id');                   // dari form
    [$kid,$kkod,] = $kebunById($conn,$kebun_id); // map ke kode

    if($hasKid){  $cols[]='kebun_id';   $data['kebun_id']=$kid; }
    if($hasKkod){ $cols[]='kebun_kode'; $data['kebun_kode']=$kkod; }

    // ===== VALIDASI MINIMAL =====
    $errors=[];
    $unit_id = i('unit_id');
    $blok    = s('blok');
    $tt      = s('tt');

    if(!$unit_id) $errors[]='Unit wajib dipilih.';
    if($blok!=='' && !$blokValid($conn,$unit_id,$blok)) $errors[]='Blok tidak ditemukan pada unit terpilih (cek md_blok).';
    if($tt!=='' && !$ttValid($conn,$tt)) $errors[]='Tahun tanam (T.T) tidak ada di master md_tahun_tanam.';

    if($errors){ echo json_encode(['success'=>false,'message'=>'Validasi gagal.','errors'=>$errors]); exit; }

    if($act==='store'){
      $sql="INSERT INTO lm77 (".implode(',',$cols).") VALUES (".implode(',',array_map(fn($c)=>":$c",$cols)).")";
      $st=$conn->prepare($sql);
      $st->execute(array_combine(array_map(fn($c)=>":$c",$cols), array_values($data)));
      echo json_encode(['success'=>true,'message'=>'Data LM-77 tersimpan']); exit;
    }else{
      $id=(int)($_POST['id']??0); if($id<=0){echo json_encode(['success'=>false,'message'=>'ID invalid']);exit;}
      $assign=implode(',', array_map(fn($c)=>"$c=:$c",$cols));
      $sql="UPDATE lm77 SET $assign WHERE id=:id";
      $params=array_combine(array_map(fn($c)=>":$c",$cols), array_values($data)); $params[':id']=$id;
      $st=$conn->prepare($sql); $st->execute($params);
      echo json_encode(['success'=>true,'message'=>'Data LM-77 diperbarui']); exit;
    }
  }

  if($act==='delete'){
    $id=(int)($_POST['id']??0); if($id<=0){echo json_encode(['success'=>false,'message'=>'ID invalid']);exit;}
    $conn->prepare("DELETE FROM lm77 WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data LM-77 dihapus']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali']);
}catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
