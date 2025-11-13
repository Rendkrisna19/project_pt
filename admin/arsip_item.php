<?php
// CATATAN: File ini SUDAH BENAR dan sudah menggunakan LEFT JOIN.
// Masalah "nama kebun tidak muncul" ada di file 'laporan_mingguan_crud.php'
// dan kemungkinan besar disebabkan oleh data di 'md_kebun' (tidak ada id=1).

session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { echo json_encode(['success'=>false,'message'=>'Akses ditolak.']); exit; }
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
  echo json_encode(['success'=>false,'message'=>'Token keamanan tidak valid.']); exit;
}
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');
$action   = $_POST['action'] ?? '';

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

$uploadDirAbs = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$subDir = 'laporan_mingguan_item'; $targetDir = $uploadDirAbs . '/' . $subDir . '/';
if (!is_dir($targetDir) && !mkdir($targetDir,0777,true)) { echo json_encode(['success'=>false,'message'=>'Gagal membuat direktori upload.']); exit; }

function allowExt($n){ $ok=['pdf','jpg','jpeg','png','doc','docx','xls','xlsx']; $e=strtolower(pathinfo($n,PATHINFO_EXTENSION)); return in_array($e,$ok,true); }

try{
  switch($action){
    case 'list':{
      $params=[]; $sql="
        SELECT i.*, i.laporan_id,
               CONCAT(l.tahun,' – ',COALESCE(k.nama_kebun,'-'),' – ',COALESCE(l.uraian,'')) AS parent_label
        FROM laporan_mingguan_item i
        JOIN laporan_mingguan l ON l.id=i.laporan_id
        LEFT JOIN md_kebun k ON k.id=l.kebun_id
        WHERE 1=1";
      if(!empty($_POST['q'])){
        $s='%'.$_POST['q'].'%';
        $sql.=" AND (i.judul LIKE ? OR i.nomor_dokumen LIKE ? OR i.keterangan LIKE ? OR l.uraian LIKE ? OR k.nama_kebun LIKE ?)";
        array_push($params,$s,$s,$s,$s,$s);
      }
      $sql.=" ORDER BY i.id DESC";
      $st=$conn->prepare($sql); $st->execute($params);
      $rows=$st->fetchAll(PDO::FETCH_ASSOC);

      foreach($rows as &$r){
        if(!empty($r['upload_dokumen'])){
          $p=str_replace('\\','/',$r['upload_dokumen']);
          $p=preg_replace('~^/?uploads/~i','',ltrim($p,'/'));
          $r['upload_dokumen']='uploads/'.$p;
        }
      }
      echo json_encode(['success'=>true,'data'=>$rows]); break;
    }

    case 'store':{
      if($isStaf) throw new Exception('Akses ditolak.');
      if(empty($_POST['laporan_id']) || empty($_POST['judul'])) throw new Exception('Induk dan Judul wajib diisi.');
      $fileRel=null;
      if(!empty($_FILES['upload_dokumen']) && $_FILES['upload_dokumen']['error']===UPLOAD_ERR_OK){
        $nm=$_FILES['upload_dokumen']['name']; if(!allowExt($nm)) throw new Exception('Ekstensi file tidak diizinkan.');
        $safe=preg_replace("/[^a-zA-Z0-9.\-_]/","_",basename($nm)); $fn=time().'_'.$safe;
        if(!move_uploaded_file($_FILES['upload_dokumen']['tmp_name'],$targetDir.$fn)) throw new Exception('Gagal upload file.');
        $fileRel=$subDir.'/'.$fn;
      }
      $st=$conn->prepare("INSERT INTO laporan_mingguan_item(laporan_id,judul,nomor_dokumen,keterangan,link_dokumen,upload_dokumen,created_at,updated_at)
                           VALUES(?,?,?,?,?,?,NOW(),NOW())");
      $st->execute([(int)$_POST['laporan_id'],trim((string)$_POST['judul']),($_POST['nomor_dokumen']?:null),
                   ($_POST['keterangan']?:null),($_POST['link_dokumen']?:null),$fileRel]);
      echo json_encode(['success'=>true,'message'=>'Item berhasil disimpan.']); break;
    }

    case 'update':{
      if($isStaf) throw new Exception('Akses ditolak.');
      $id=(int)($_POST['id']??0); if(!$id) throw new Exception('ID tidak valid.');
      if(empty($_POST['laporan_id']) || empty($_POST['judul'])) throw new Exception('Induk dan Judul wajib diisi.');
      $st=$conn->prepare("SELECT upload_dokumen FROM laporan_mingguan_item WHERE id=?"); $st->execute([$id]);
      $old=$st->fetchColumn(); $fileRel=$old;

      if(!empty($_FILES['upload_dokumen']) && $_FILES['upload_dokumen']['error']===UPLOAD_ERR_OK){
        $nm=$_FILES['upload_dokumen']['name']; if(!allowExt($nm)) throw new Exception('Ekstensi file tidak diizinkan.');
        if($old){ $abs=$uploadDirAbs.'/'.ltrim($old,'/'); if(file_exists($abs)) @unlink($abs); }
        $safe=preg_replace("/[^a-zA-Z0-9.\-_]/","_",basename($nm)); $fn=time().'_'.$safe;
        if(!move_uploaded_file($_FILES['upload_dokumen']['tmp_name'],$targetDir.$fn)) throw new Exception('Gagal upload file baru.');
        $fileRel=$subDir.'/'.$fn;
      }

      $st=$conn->prepare("UPDATE laporan_mingguan_item
                           SET laporan_id=?, judul=?, nomor_dokumen=?, keterangan=?, link_dokumen=?, upload_dokumen=?, updated_at=NOW()
                           WHERE id=?");
      $st->execute([(int)$_POST['laporan_id'],trim((string)$_POST['judul']),
                   ($_POST['nomor_dokumen']?:null),($_POST['keterangan']?:null),($_POST['link_dokumen']?:null),
                   $fileRel,$id]);
      echo json_encode(['success'=>true,'message'=>'Item berhasil diperbarui.']); break;
    }

    case 'delete':{
      if($isStaf) throw new Exception('Akses ditolak.');
      $id=(int)($_POST['id']??0); if(!$id) throw new Exception('ID tidak valid.');
      $st=$conn->prepare("SELECT upload_dokumen FROM laporan_mingguan_item WHERE id=?"); $st->execute([$id]);
      $old=$st->fetchColumn(); if($old){ $abs=$uploadDirAbs.'/'.ltrim($old,'/'); if(file_exists($abs)) @unlink($abs); }
      $conn->prepare("DELETE FROM laporan_mingguan_item WHERE id=?")->execute([$id]);
      echo json_encode(['success'=>true,'message'=>'Item dihapus.']); break;
    }

    default: throw new Exception('Aksi tidak dikenal.');
  }
}catch(Exception $e){
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}