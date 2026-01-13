<?php
// auth/login_process.php
session_start();
require_once '../config/database.php';

// 1. SET TIMEZONE (Wajib agar waktu masuk akal)
date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php'); exit;
}

// 2. AMBIL DATA
$identity = trim($_POST['identity'] ?? ''); // Bisa NIK atau Username
$password = (string)($_POST['password'] ?? '');

if ($identity === '' || $password === '') {
    $_SESSION['login_error'] = 'NIK/Username dan password wajib diisi.';
    header('Location: login.php'); exit;
}

try {
    // 3. KONEKSI DATABASE
    $db = new Database(); 
    $pdo = $db->getConnection();
    
    // 4. CARI USER
    $sql = "SELECT id, username, nik, nama_lengkap, password, role, last_login
            FROM users
            WHERE nik = :ident OR username = :ident
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':ident' => $identity]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    // 5. VERIFIKASI PASSWORD
    if ($user && password_verify($password, $user['password'] ?? '')) {

        // ============================================================
        // [MODIFIKASI FULL] LOGIC UPDATE LAST LOGIN + DEBUGGING
        // ============================================================
        try {
            $waktu_sekarang = date('Y-m-d H:i:s');
            
            // Query Update
            $updateSql = "UPDATE users SET last_login = :waktu WHERE id = :id";
            $stmtUp = $pdo->prepare($updateSql);
            
            // Eksekusi
            $berhasil = $stmtUp->execute([
                ':waktu' => $waktu_sekarang,
                ':id'    => $user['id']
            ]);

            // [PENTING] Validasi Error SQL
            if (!$berhasil) {
                // Ambil info error dari driver PDO
                $errorInfo = $stmtUp->errorInfo();
                die("<h2 style='color:red'>GAGAL UPDATE DATABASE!</h2>
                     <p>Penyebab: " . $errorInfo[2] . "</p>
                     <p>Cek apakah kolom 'last_login' ada di tabel 'users'?</p>");
            }

        } catch (Exception $e) {
            // Jika terjadi error sistem saat update, TAMPILKAN!
            die("<h2 style='color:red'>ERROR SISTEM SAAT UPDATE:</h2><p>" . $e->getMessage() . "</p>");
        }
        // ============================================================

        // 6. SET SESSION (Login Berhasil)
        session_regenerate_id(true);
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_nik']      = $user['nik'];
        $_SESSION['user_nama']     = $user['nama_lengkap'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['loggedin']      = true;
        
        // Redirect ke Dashboard
        header('Location: ../admin/portal.php'); exit;

    } else {
        // Password Salah / User Tidak Ditemukan
        $_SESSION['login_error'] = 'Username atau Password salah.';
        header('Location: login.php'); exit;
    }

} catch (PDOException $e) {
    // Error Koneksi Database Utama
    $_SESSION['login_error'] = 'Database Error: ' . $e->getMessage();
    header('Location: login.php'); exit;
} catch (Throwable $e) {
    $_SESSION['login_error'] = 'Terjadi kesalahan server.';
    header('Location: login.php'); exit;
}
?>