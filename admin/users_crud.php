<?php
// pages/users_crud.php
// MODIFIKASI FULL: Support role 'viewer' di database dan validasi

session_start();
header('Content-Type: application/json');

// 1. Cek Login
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin']!==true){
  echo json_encode(['success'=>false,'message'=>'Silakan login.']); exit;
}

// 2. Cek Method POST
if($_SERVER['REQUEST_METHOD']!=='POST'){
  echo json_encode(['success'=>false,'message'=>'Metode tidak valid.']); exit;
}

// 3. Cek CSRF Token
if(empty($_SESSION['csrf_token']) || $_SESSION['csrf_token']!==($_POST['csrf_token']??'')){
  echo json_encode(['success'=>false,'message'=>'CSRF tidak valid.']); exit;
}

require_once '../config/database.php';
$db = new Database(); 
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';

try {
  // --- LIST DATA ---
  if($action === 'list'){
    $st = $conn->query("SELECT id,username,nama_lengkap,nik,role,created_at FROM users ORDER BY id DESC");
    echo json_encode(['success'=>true, 'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
  }

  // --- TAMBAH (STORE) ATAU EDIT (UPDATE) ---
  if($action === 'store' || $action === 'update'){
    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $nik      = trim($_POST['nik'] ?? '');
    $role     = $_POST['role'] ?? 'staf';
    $password = $_POST['password'] ?? '';

    // Validasi Input Kosong
    if($username === '' || $nama === '' || $nik === ''){
      echo json_encode(['success'=>false, 'message'=>'Username, Nama, dan NIK wajib diisi.']); exit;
    }

    // Validasi Role (Security): Pastikan hanya admin, staf, atau viewer yang masuk
    $allowedRoles = ['admin', 'staf', 'viewer'];
    if(!in_array($role, $allowedRoles)){
        $role = 'viewer'; // Default fallback jika diinject role aneh
    }

    if($action === 'store'){
      // --- INSERT ---
      // Hash password wajib ada saat buat user baru (atau default)
      $passHash = $password ? password_hash($password, PASSWORD_BCRYPT) : password_hash('123456', PASSWORD_BCRYPT);
      
      $sql = "INSERT INTO users (username, nama_lengkap, nik, password, role, created_at, updated_at)
              VALUES (:u, :n, :k, :p, :r, NOW(), NOW())";
      $st = $conn->prepare($sql);
      $st->execute([
        ':u' => $username,
        ':n' => $nama,
        ':k' => $nik,
        ':p' => $passHash,
        ':r' => $role
      ]);
      echo json_encode(['success'=>true, 'message'=>'User berhasil ditambahkan']); exit;

    } else {
      // --- UPDATE ---
      $id = (int)($_POST['id'] ?? 0);
      if($id <= 0){ echo json_encode(['success'=>false, 'message'=>'ID tidak valid']); exit; }

      if($password !== ''){
        // Update dengan password baru
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = "UPDATE users SET username=:u, nama_lengkap=:n, nik=:k, password=:p, role=:r, updated_at=NOW() WHERE id=:id";
        $st = $conn->prepare($sql);
        $st->execute([':u'=>$username, ':n'=>$nama, ':k'=>$nik, ':p'=>$hash, ':r'=>$role, ':id'=>$id]);
      } else {
        // Update tanpa ganti password
        $sql = "UPDATE users SET username=:u, nama_lengkap=:n, nik=:k, role=:r, updated_at=NOW() WHERE id=:id";
        $st = $conn->prepare($sql);
        $st->execute([':u'=>$username, ':n'=>$nama, ':k'=>$nik, ':r'=>$role, ':id'=>$id]);
      }
      echo json_encode(['success'=>true, 'message'=>'User berhasil diperbarui']); exit;
    }
  }

  // --- DELETE ---
  if($action === 'delete'){
    $id = (int)($_POST['id'] ?? 0);
    if($id <= 0){ echo json_encode(['success'=>false, 'message'=>'ID tidak valid']); exit; }
    
    // Opsional: Cegah hapus diri sendiri jika ID sama dengan user login
    if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id){
       echo json_encode(['success'=>false, 'message'=>'Anda tidak bisa menghapus akun sendiri.']); exit; 
    }

    $conn->prepare("DELETE FROM users WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true, 'message'=>'User berhasil dihapus']); exit;
  }

  echo json_encode(['success'=>false, 'message'=>'Aksi tidak dikenali.']);

} catch(PDOException $e) {
  // Tangkap error misal duplikat username/NIK
  if(strpos($e->getMessage(), 'Duplicate entry') !== false){
      echo json_encode(['success'=>false, 'message'=>'Username atau NIK sudah digunakan user lain.']);
  } else {
      echo json_encode(['success'=>false, 'message'=>'Database Error: '.$e->getMessage()]);
  }
}