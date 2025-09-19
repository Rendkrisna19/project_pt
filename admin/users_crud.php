<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true){ echo json_encode(['success'=>false,'message'=>'Login dulu']); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['success'=>false,'message'=>'Metode salah']); exit; }
if(empty($_SESSION['csrf_token']) || $_SESSION['csrf_token']!==($_POST['csrf_token']??'')){ echo json_encode(['success'=>false,'message'=>'CSRF salah']); exit; }

require_once '../config/database.php';
$db=new Database(); $conn=$db->getConnection();

$action=$_POST['action']??'';

try{
  if($action==='list'){
    $st=$conn->query("SELECT id,username,nama_lengkap,email,role,created_at FROM users ORDER BY id DESC");
    echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }
  if($action==='store' || $action==='update'){
    $username=trim($_POST['username']??'');
    $nama=trim($_POST['nama_lengkap']??'');
    $email=trim($_POST['email']??'');
    $role=$_POST['role']??'staf';
    $password=$_POST['password']??'';

    if($username==''||$nama==''||$email==''){ echo json_encode(['success'=>false,'message'=>'Wajib diisi']); exit; }

    if($action==='store'){
      $hash=$password? password_hash($password,PASSWORD_BCRYPT):null;
      $sql="INSERT INTO users (username,nama_lengkap,email,password,role,created_at) VALUES (:u,:n,:e,:p,:r,NOW())";
      $st=$conn->prepare($sql);
      $st->execute([':u'=>$username,':n'=>$nama,':e'=>$email,':p'=>$hash,':r'=>$role]);
      echo json_encode(['success'=>true,'message'=>'User berhasil ditambah']); exit;
    }else{
      $id=(int)($_POST['id']??0);
      if($id<=0){echo json_encode(['success'=>false,'message'=>'ID salah']); exit;}
      if($password){
        $hash=password_hash($password,PASSWORD_BCRYPT);
        $sql="UPDATE users SET username=:u,nama_lengkap=:n,email=:e,password=:p,role=:r WHERE id=:id";
        $st=$conn->prepare($sql);
        $st->execute([':u'=>$username,':n'=>$nama,':e'=>$email,':p'=>$hash,':r'=>$role,':id'=>$id]);
      }else{
        $sql="UPDATE users SET username=:u,nama_lengkap=:n,email=:e,role=:r WHERE id=:id";
        $st=$conn->prepare($sql);
        $st->execute([':u'=>$username,':n'=>$nama,':e'=>$email,':r'=>$role,':id'=>$id]);
      }
      echo json_encode(['success'=>true,'message'=>'User berhasil diperbarui']); exit;
    }
  }
  if($action==='delete'){
    $id=(int)($_POST['id']??0);
    if($id<=0){echo json_encode(['success'=>false,'message'=>'ID salah']); exit;}
    $conn->prepare("DELETE FROM users WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'User berhasil dihapus']); exit;
  }
}catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
