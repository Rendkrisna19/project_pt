<?php
// pages/alat_panen_crud.php
// MODIFIKASI FULL: Support Relasi & Filtering Jenis Alat Panen + Hak Akses Role

session_start();
header('Content-Type: application/json');

// 1. Cek Login
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true){
  echo json_encode(['success'=>false,'message'=>'Silakan login.']); exit;
}

// 2. Cek Method & CSRF
if($_SERVER['REQUEST_METHOD']!=='POST'){
  echo json_encode(['success'=>false,'message'=>'Metode request tidak valid.']); exit;
}
if(empty($_SESSION['csrf_token']) || $_SESSION['csrf_token']!==($_POST['csrf_token']??'')){
  echo json_encode(['success'=>false,'message'=>'CSRF token tidak valid.']); exit;
}

// 3. Ambil Role
$role = $_SESSION['user_role'] ?? 'viewer';

require_once '../config/database.php';
$db = new Database(); 
$conn = $db->getConnection();
$act = $_POST['action'] ?? '';

try {
  // --- ACTION: LIST (READ DATA) - Semua Role Boleh ---
  if ($act === 'list') {
    $w=[]; $p=[];
    
    // Filter Query Existing
    if(!empty($_POST['kebun_id'])){ $w[]='a.kebun_id=:k'; $p[':k']=(int)$_POST['kebun_id']; }
    if(!empty($_POST['unit_id'])){ $w[]='a.unit_id=:u'; $p[':u']=(int)$_POST['unit_id']; }
    if(!empty($_POST['bulan'])){ $w[]='a.bulan=:b'; $p[':b']=$_POST['bulan']; }
    if(!empty($_POST['tahun'])){ $w[]='a.tahun=:t'; $p[':t']=(int)$_POST['tahun']; }

    // MODIFIKASI: Filter Jenis Alat Panen
    if(!empty($_POST['id_jenis_alat'])){ 
        $w[]='a.id_jenis_alat=:idj'; 
        $p[':idj']=(int)$_POST['id_jenis_alat']; 
    }

    $sql="SELECT a.*, 
                 u.nama_unit, 
                 k.nama_kebun,
                 COALESCE(mjap.nama, a.jenis_alat) as display_jenis_alat,
                 mjap.nama as master_nama_alat
          FROM alat_panen a
          JOIN units u ON a.unit_id=u.id
          LEFT JOIN md_kebun k ON k.id=a.kebun_id
          LEFT JOIN md_jenis_alat_panen mjap ON a.id_jenis_alat = mjap.id ".
          (count($w) ? " WHERE ".implode(' AND ',$w) : "") .
          " ORDER BY a.tahun DESC,
              FIELD(a.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
              k.nama_kebun ASC, u.nama_unit ASC";
    
    $st=$conn->prepare($sql); 
    $st->execute($p);
    echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  // --- ACTION: STORE & UPDATE ---
  if (in_array($act, ['store','update'], true)) {
    
    // Validasi Hak Akses
    if ($act === 'store') {
        // Create: Admin & Staf Boleh
        if ($role !== 'admin' && $role !== 'staf') {
            echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki izin untuk menambah data.']); exit;
        }
    } elseif ($act === 'update') {
        // Update: HANYA Admin Boleh
        if ($role !== 'admin') {
            echo json_encode(['success'=>false,'message'=>'Hanya Admin yang boleh mengubah data.']); exit;
        }
    }

    // Ambil & sanitasi input
    $bulan      = $_POST['bulan'] ?? '';
    $tahun      = (int)($_POST['tahun'] ?? 0);
    $kebun      = (int)($_POST['kebun_id'] ?? 0);
    $unit       = (int)($_POST['unit_id'] ?? 0);
    $id_jenis   = (int)($_POST['id_jenis_alat'] ?? 0); 
    
    $sa         = (float)($_POST['stok_awal'] ?? 0);
    $mi         = (float)($_POST['mutasi_masuk'] ?? 0);
    $mk         = (float)($_POST['mutasi_keluar'] ?? 0);
    $dp         = (float)($_POST['dipakai'] ?? 0);
    $krani      = trim($_POST['krani_afdeling'] ?? '');
    $cat        = trim($_POST['catatan'] ?? '');

    if (!$bulan || !$tahun || !$kebun || !$unit || !$id_jenis) {
      echo json_encode(['success'=>false,'message'=>'Bulan, Tahun, Kebun, Unit, dan Jenis Alat wajib diisi.']); exit;
    }

    $cekK = $conn->prepare("SELECT 1 FROM md_kebun WHERE id=:id LIMIT 1");
    $cekK->execute([':id'=>$kebun]);
    if(!$cekK->fetchColumn()){ echo json_encode(['success'=>false,'message'=>'Kebun tidak ditemukan.']); exit; }

    $qJ = $conn->prepare("SELECT nama FROM md_jenis_alat_panen WHERE id=:id LIMIT 1");
    $qJ->execute([':id'=>$id_jenis]);
    $rowJ = $qJ->fetch(PDO::FETCH_ASSOC);
    
    if(!$rowJ){
        echo json_encode(['success'=>false,'message'=>'Jenis Alat Panen tidak valid di database master.']); exit;
    }
    
    $nama_jenis_text = $rowJ['nama'];
    $akhir = $sa + $mi - $mk - $dp;

    if ($act === 'store') {
      $sql="INSERT INTO alat_panen (bulan, tahun, kebun_id, unit_id, id_jenis_alat, jenis_alat, stok_awal, mutasi_masuk, mutasi_keluar, dipakai, stok_akhir, krani_afdeling, catatan)
            VALUES (:b, :t, :k, :u, :idj, :nmj, :sa, :mi, :mk, :dp, :ak, :kr, :ct)";
      $st=$conn->prepare($sql);
      $st->execute([
        ':b'=>$bulan, ':t'=>$tahun, ':k'=>$kebun, ':u'=>$unit, 
        ':idj'=>$id_jenis, ':nmj'=>$nama_jenis_text,
        ':sa'=>$sa, ':mi'=>$mi, ':mk'=>$mk, ':dp'=>$dp, 
        ':ak'=>$akhir, ':kr'=>$krani, ':ct'=>$cat
      ]);
      echo json_encode(['success'=>true,'message'=>'Data alat panen berhasil disimpan']); exit;

    } else {
      $id=(int)($_POST['id'] ?? 0);
      if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }

      $sql="UPDATE alat_panen 
            SET bulan=:b, tahun=:t, kebun_id=:k, unit_id=:u, 
                id_jenis_alat=:idj, jenis_alat=:nmj, 
                stok_awal=:sa, mutasi_masuk=:mi, mutasi_keluar=:mk, dipakai=:dp, stok_akhir=:ak, 
                krani_afdeling=:kr, catatan=:ct
            WHERE id=:id";
      $st=$conn->prepare($sql);
      $st->execute([
        ':b'=>$bulan, ':t'=>$tahun, ':k'=>$kebun, ':u'=>$unit, 
        ':idj'=>$id_jenis, ':nmj'=>$nama_jenis_text,
        ':sa'=>$sa, ':mi'=>$mi, ':mk'=>$mk, ':dp'=>$dp, 
        ':ak'=>$akhir, ':kr'=>$krani, ':ct'=>$cat, 
        ':id'=>$id
      ]);
      echo json_encode(['success'=>true,'message'=>'Data alat panen berhasil diperbarui']); exit;
    }
  }

  // --- ACTION: DELETE - HANYA Admin ---
  if ($act === 'delete') {
    if ($role !== 'admin') {
        echo json_encode(['success'=>false,'message'=>'Hanya Admin yang boleh menghapus data.']); exit;
    }

    $id=(int)($_POST['id'] ?? 0);
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
    $conn->prepare("DELETE FROM alat_panen WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data alat panen berhasil dihapus']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);

} catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage() ]);
}