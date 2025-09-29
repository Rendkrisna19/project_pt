<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true){ echo json_encode(['success'=>false,'message'=>'Silakan login.']); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['success'=>false,'message'=>'Metode tidak valid.']); exit; }
if(empty($_SESSION['csrf_token']) || $_SESSION['csrf_token']!==($_POST['csrf_token']??'')){ echo json_encode(['success'=>false,'message'=>'CSRF tidak valid.']); exit; }

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

function null_if_empty($v){ if(!isset($v)) return null; if(is_string($v)&&trim($v)==='') return null; return $v; }

$entity = $_POST['entity'] ?? '';
$action = $_POST['action'] ?? '';

$MAP = [
  // NEW: Nama Kebun
  'kebun' => [
    'table'=>'md_kebun','alias'=>'k',
    'cols'=>['kode','nama_kebun','keterangan'],
    'required'=>['kode','nama_kebun'],
    'select'=>'k.*','joins'=>'','order'=>'k.nama_kebun ASC'
  ],
  // NEW: Bahan Kimia
  'bahan_kimia' => [
    'table'=>'md_bahan_kimia','alias'=>'b',
    'cols'=>['kode','nama_bahan','satuan_id','keterangan'],
    'required'=>['kode','nama_bahan'],
    'select'=>'b.*, s.nama AS nama_satuan',
    'joins'=>'LEFT JOIN md_satuan s ON s.id=b.satuan_id',
    'order'=>'b.nama_bahan ASC'
  ],

  'jenis_pekerjaan' => [
    'table'=>'md_jenis_pekerjaan','alias'=>'t',
    'cols'=>['nama','keterangan'],
    'required'=>['nama'],
    'select'=>'t.*','joins'=>'','order'=>'t.id DESC'
  ],
  'unit' => [
    'table'=>'units','alias'=>'u',
    'cols'=>['nama_unit','keterangan'],
    'required'=>['nama_unit'],
    'select'=>'u.*','joins'=>'','order'=>'u.id DESC'
  ],
  'tahun_tanam' => [
    'table'=>'md_tahun_tanam','alias'=>'t',
    'cols'=>['tahun','keterangan'],
    'required'=>['tahun'],
    'select'=>'t.*','joins'=>'','order'=>'t.tahun DESC'
  ],
  'blok' => [
    'table'=>'md_blok','alias'=>'b',
    'cols'=>['unit_id','kode','tahun_tanam','luas_ha'],
    'required'=>['unit_id','kode'],
    'select'=>'b.*, u.nama_unit',
    'joins'=>'LEFT JOIN units u ON b.unit_id=u.id',
    'order'=>'b.id DESC'
  ],
  'fisik' => [
    'table'=>'md_fisik','alias'=>'f',
    'cols'=>['nama'],
    'required'=>['nama'],
    'select'=>'f.*','joins'=>'','order'=>'f.id DESC'
  ],
  'satuan' => [
    'table'=>'md_satuan','alias'=>'s',
    'cols'=>['nama'],
    'required'=>['nama'],
    'select'=>'s.*','joins'=>'','order'=>'s.id DESC'
  ],
  'tenaga' => [
    'table'=>'md_tenaga','alias'=>'t',
    'cols'=>['kode','nama'],
    'required'=>['kode','nama'],
    'select'=>'t.*','joins'=>'','order'=>'t.id DESC'
  ],
  'mobil' => [
    'table'=>'md_mobil','alias'=>'m',
    'cols'=>['kode','nama'],
    'required'=>['kode','nama'],
    'select'=>'m.*','joins'=>'','order'=>'m.id DESC'
  ],
  'alat_panen' => [
    'table'=>'md_jenis_alat_panen','alias'=>'a',
    'cols'=>['nama','keterangan'],
    'required'=>['nama'],
    'select'=>'a.*','joins'=>'','order'=>'a.id DESC'
  ],
  'sap' => [
    'table'=>'md_sap','alias'=>'s',
    'cols'=>['no_sap','deskripsi'],
    'required'=>['no_sap'],
    'select'=>'s.*','joins'=>'','order'=>'s.id DESC'
  ],
  'jabatan' => [
    'table'=>'md_jabatan','alias'=>'j',
    'cols'=>['nama'],
    'required'=>['nama'],
    'select'=>'j.*','joins'=>'','order'=>'j.id DESC'
  ],
  'pupuk' => [
    'table'=>'md_pupuk','alias'=>'p',
    'cols'=>['nama','satuan_id'],
    'required'=>['nama'],
    'select'=>'p.*, st.nama AS nama_satuan',
    'joins'=>'LEFT JOIN md_satuan st ON p.satuan_id=st.id',
    'order'=>'p.id DESC'
  ],
  'kode_aktivitas' => [
    'table'=>'md_kode_aktivitas','alias'=>'k',
    'cols'=>['kode','nama','no_sap_id'],
    'required'=>['kode','nama'],
    'select'=>'k.*, s.no_sap',
    'joins'=>'LEFT JOIN md_sap s ON k.no_sap_id=s.id',
    'order'=>'k.id DESC'
  ],
  'anggaran' => [
    'table'=>'md_anggaran','alias'=>'a',
    'cols'=>['tahun','bulan','unit_id','kode_aktivitas_id','pupuk_id','anggaran_bulan_ini','anggaran_tahun'],
    'required'=>['tahun','bulan','unit_id','kode_aktivitas_id','anggaran_bulan_ini','anggaran_tahun'],
    'select'=>'a.*, u.nama_unit, k.kode AS kode_aktivitas, p.nama AS nama_pupuk',
    'joins'=>'JOIN units u ON a.unit_id=u.id
              JOIN md_kode_aktivitas k ON a.kode_aktivitas_id=k.id
              LEFT JOIN md_pupuk p ON a.pupuk_id=p.id',
    'order'=>'a.tahun DESC, FIELD(a.bulan,"Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember") DESC, u.nama_unit ASC'
  ],
];

if(!isset($MAP[$entity])){ echo json_encode(['success'=>false,'message'=>'Entity tidak dikenali']); exit; }

try{
  $cfg=$MAP[$entity]; $table=$cfg['table']; $alias=$cfg['alias']??'t';
  $select=$cfg['select']??"$alias.*"; $joins=$cfg['joins']??''; $order=$cfg['order']??"$alias.id DESC";

  if ($action==='list'){
    $sql="SELECT $select FROM $table $alias ".($joins?" $joins ":"")." ORDER BY $order";
    $st=$conn->prepare($sql); $st->execute();
    echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  if ($action==='store' || $action==='update'){
    $data=[]; foreach(($cfg['cols']??[]) as $c){ $data[$c] = $_POST[$c] ?? null; }
    foreach($data as $k=>$v){ $data[$k]=null_if_empty($v); }

    // cast per entity
    if ($entity==='tahun_tanam'){ $data['tahun']=isset($data['tahun'])?(int)$data['tahun']:null; }
    if ($entity==='blok'){
      $data['unit_id']=isset($data['unit_id'])?(int)$data['unit_id']:null;
      $data['tahun_tanam']=isset($data['tahun_tanam'])?(int)$data['tahun_tanam']:null;
      $data['luas_ha']=isset($data['luas_ha'])?(float)$data['luas_ha']:null;
    }
    if ($entity==='pupuk'){ $data['satuan_id']=isset($data['satuan_id'])?(int)$data['satuan_id']:null; }
    if ($entity==='bahan_kimia'){ $data['satuan_id']=isset($data['satuan_id'])?(int)$data['satuan_id']:null; }
    if ($entity==='kode_aktivitas'){ $data['no_sap_id']=isset($data['no_sap_id'])?(int)$data['no_sap_id']:null; }
    if ($entity==='anggaran'){
      $data['tahun']=isset($data['tahun'])?(int)$data['tahun']:null;
      $data['unit_id']=isset($data['unit_id'])?(int)$data['unit_id']:null;
      $data['kode_aktivitas_id']=isset($data['kode_aktivitas_id'])?(int)$data['kode_aktivitas_id']:null;
      $data['pupuk_id']=isset($data['pupuk_id']) && $data['pupuk_id']!==null ? (int)$data['pupuk_id'] : null;
      $data['anggaran_bulan_ini']=isset($data['anggaran_bulan_ini'])?(float)$data['anggaran_bulan_ini']:null;
      $data['anggaran_tahun']=isset($data['anggaran_tahun'])?(float)$data['anggaran_tahun']:null;
      $allowedBulan=["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
      if(isset($data['bulan']) && !in_array($data['bulan'],$allowedBulan,true)){ echo json_encode(['success'=>false,'message'=>'Bulan tidak valid']); exit; }
    }

    // validasi wajib
    if(!empty($cfg['required'])){
      foreach($cfg['required'] as $req){
        $val=$data[$req]??null;
        if($val===null || (is_string($val)&&trim($val)==='')){ echo json_encode(['success'=>false,'message'=>"Field wajib '$req' belum diisi."]); exit; }
      }
    }

    // cek unik kode (untuk kebun & bahan_kimia)
    if (in_array($entity, ['kebun','bahan_kimia'], true) && isset($data['kode'])){
      $sqlC="SELECT id FROM $table WHERE kode=:kode ".($action==='update'?' AND id<>:id':'')." LIMIT 1";
      $stC=$conn->prepare($sqlC);
      $stC->bindValue(':kode',$data['kode'],PDO::PARAM_STR);
      if($action==='update'){ $stC->bindValue(':id',(int)($_POST['id']??0),PDO::PARAM_INT); }
      $stC->execute();
      if($stC->fetch()){ echo json_encode(['success'=>false,'message'=>'Kode sudah digunakan.']); exit; }
    }

    if($action==='store'){
      $cols=implode(',',array_keys($data));
      $vals=implode(',',array_map(fn($c)=>":$c",array_keys($data)));
      $sql="INSERT INTO $table ($cols) VALUES ($vals)";
      $st=$conn->prepare($sql);
      foreach($data as $k=>$v){ $param=is_null($v)?PDO::PARAM_NULL: (is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); $st->bindValue(":$k",$v,$param); }
      $st->execute();
      echo json_encode(['success'=>true,'message'=>'Data berhasil disimpan']); exit;
    }else{
      $id=(int)($_POST['id']??0); if($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
      $set=implode(',',array_map(fn($c)=>"$c=:$c",array_keys($data)));
      $sql="UPDATE $table SET $set WHERE id=:id";
      $st=$conn->prepare($sql);
      foreach($data as $k=>$v){ $param=is_null($v)?PDO::PARAM_NULL: (is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); $st->bindValue(":$k",$v,$param); }
      $st->bindValue(':id',$id,PDO::PARAM_INT);
      $st->execute();
      echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui']); exit;
    }
  }

  if ($action==='delete'){
    $id=(int)($_POST['id']??0);
    if($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
    try{
      $st=$conn->prepare("DELETE FROM $table WHERE id=:id");
      $st->execute([':id'=>$id]);
      echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus']); exit;
    }catch(PDOException $e){
      if($e->getCode()==='23000'){ echo json_encode(['success'=>false,'message'=>'Tidak bisa menghapus: data sedang dipakai (foreign key).']); exit; }
      throw $e;
    }
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali']);
}catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
