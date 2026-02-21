<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: ../auth/login.php"); exit;
}

$db = new Database();
$conn = $db->getConnection();

$id = $_SESSION['user_id'];
$nama = trim($_POST['nama_lengkap']);
$nik = trim($_POST['nik']);
$email = trim($_POST['email']);
$pass = $_POST['password_baru'];
$conf = $_POST['konfirmasi_password'];

try {
    // 1. Update Data Dasar
    $sql = "UPDATE users SET nama_lengkap = :nama, nik = :nik, email = :email WHERE id = :id";
    $params = [':nama' => $nama, ':nik' => $nik, ':email' => $email, ':id' => $id];
    
    // 2. Cek Ganti Password
    if (!empty($pass)) {
        if ($pass !== $conf) {
            header("Location: profile.php?status=error&msg=Password tidak cocok"); exit;
        }
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET nama_lengkap = :nama, nik = :nik, email = :email, password = :pass WHERE id = :id";
        $params[':pass'] = $hashed;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // 3. Handle Upload Foto
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['foto_profil']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Buat nama file unik: ID_TIMESTAMP.ext
            $newFilename = $id . '_' . time() . '.' . $ext;
            $uploadDir = '../uploads/profil/';
            
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            // Hapus foto lama jika ada
            if (isset($_SESSION['user_foto']) && file_exists($uploadDir . $_SESSION['user_foto'])) {
                unlink($uploadDir . $_SESSION['user_foto']);
            }

            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $uploadDir . $newFilename)) {
                $stmt = $conn->prepare("UPDATE users SET foto_profil = ? WHERE id = ?");
                $stmt->execute([$newFilename, $id]);
                $_SESSION['user_foto'] = $newFilename; // Update sesi foto
            }
        } else {
            header("Location: profile.php?status=error&msg=Format file tidak diizinkan"); exit;
        }
    }

    // Update data sesi nama agar langsung berubah di sidebar
    $_SESSION['user_nama'] = $nama;

    header("Location: profile.php?status=success");

} catch (Exception $e) {
    header("Location: profile.php?status=error&msg=" . urlencode($e->getMessage()));
}
?>