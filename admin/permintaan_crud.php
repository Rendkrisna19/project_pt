<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true){
  echo json_encode(['success'=>false,'message'=>'Login dulu']); exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){
  echo json_encode(['success'=>false,'message'=>'Metode salah']); exit;
}
if(empty($_SESSION['csrf_token']) || $_SESSION['csrf_token']!==($_POST['csrf_token']??'')){
  echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit;
}

require_once '../config/database.php';
$db=new Database(); $conn=$db->getConnection();
$act=$_POST['action']??'';

try {
  if($act==='list'){
    $sql="SELECT p.*, u.nama_unit, k.nama_kebun
          FROM permintaan_bahan p
          JOIN units u ON p.unit_id=u.id
          LEFT JOIN md_kebun k ON k.id=p.kebun_id
          ORDER BY p.tanggal DESC, p.id DESC";
    $stmt=$conn->query($sql);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  if($act==='store'){
    // validasi minimal
    if (empty($_POST['no_dokumen']) || empty($_POST['unit_id']) || empty($_POST['kebun_id']) || empty($_POST['tanggal'])) {
      echo json_encode(['success'=>false,'message'=>'No. Dokumen, Kebun, Unit, dan Tanggal wajib diisi.']); exit;
    }

    // opsional: pastikan kebun ada
    $cek=$conn->prepare("SELECT 1 FROM md_kebun WHERE id=:id LIMIT 1");
    $cek->execute([':id'=>(int)$_POST['kebun_id']]);
    if(!$cek->fetchColumn()){
      echo json_encode(['success'=>false,'message'=>'Kebun tidak ditemukan.']); exit;
    }

    $stmt=$conn->prepare("INSERT INTO permintaan_bahan
      (no_dokumen, kebun_id, unit_id, tanggal, blok, pokok, dosis_norma, jumlah_diminta, keterangan)
      VALUES (:no,:kebun,:unit,:tgl,:blok,:pokok,:dosis,:jml,:ket)");
    $stmt->execute([
      ':no'=>$_POST['no_dokumen'],
      ':kebun'=>(int)$_POST['kebun_id'],
      ':unit'=>(int)$_POST['unit_id'],
      ':tgl'=>$_POST['tanggal'],
      ':blok'=>$_POST['blok'] ?? null,
      ':pokok'=>$_POST['pokok'] ?? null,
      ':dosis'=>$_POST['dosis_norma'] ?? null,
      ':jml'=>$_POST['jumlah_diminta'] ?? null,
      ':ket'=>$_POST['keterangan'] ?? null
    ]);
    echo json_encode(['success'=>true,'message'=>'Pengajuan berhasil disimpan']); exit;
  }

  if($act==='update'){
    if (empty($_POST['id'])) { echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
    if (empty($_POST['no_dokumen']) || empty($_POST['unit_id']) || empty($_POST['kebun_id']) || empty($_POST['tanggal'])) {
      echo json_encode(['success'=>false,'message'=>'No. Dokumen, Kebun, Unit, dan Tanggal wajib diisi.']); exit;
    }

    $cek=$conn->prepare("SELECT 1 FROM md_kebun WHERE id=:id LIMIT 1");
    $cek->execute([':id'=>(int)$_POST['kebun_id']]);
    if(!$cek->fetchColumn()){
      echo json_encode(['success'=>false,'message'=>'Kebun tidak ditemukan.']); exit;
    }

    $stmt=$conn->prepare("UPDATE permintaan_bahan SET
        no_dokumen=:no, kebun_id=:kebun, unit_id=:unit, tanggal=:tgl, blok=:blok,
        pokok=:pokok, dosis_norma=:dosis, jumlah_diminta=:jml, keterangan=:ket
      WHERE id=:id");
    $stmt->execute([
      ':no'=>$_POST['no_dokumen'],
      ':kebun'=>(int)$_POST['kebun_id'],
      ':unit'=>(int)$_POST['unit_id'],
      ':tgl'=>$_POST['tanggal'],
      ':blok'=>$_POST['blok'] ?? null,
      ':pokok'=>$_POST['pokok'] ?? null,
      ':dosis'=>$_POST['dosis_norma'] ?? null,
      ':jml'=>$_POST['jumlah_diminta'] ?? null,
      ':ket'=>$_POST['keterangan'] ?? null,
      ':id'=>(int)$_POST['id']
    ]);
    echo json_encode(['success'=>true,'message'=>'Pengajuan berhasil diperbarui']); exit;
  }

  if($act==='delete'){
    $stmt=$conn->prepare("DELETE FROM permintaan_bahan WHERE id=:id");
    $stmt->execute([':id'=>(int)$_POST['id']]);
    echo json_encode(['success'=>true,'message'=>'Pengajuan berhasil dihapus']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali']);
} catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
 