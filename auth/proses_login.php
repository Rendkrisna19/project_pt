<?php
// auth/login_process.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: login.php'); exit;
}

$identity = trim($_POST['identity'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($identity === '' || $password === '') {
  $_SESSION['login_error'] = 'Email/Username dan password wajib diisi.';
  header('Location: login.php'); exit;
}

try {
  $db = new Database(); $pdo = $db->getConnection();
  $sql = "SELECT id, username, nama_lengkap, email, password, role
          FROM users
          WHERE email = :ident OR username = :ident
          LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':ident'=>$identity]);
  $user = $st->fetch(PDO::FETCH_ASSOC);

  if ($user && password_verify($password, $user['password'] ?? '')) {
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_nama']     = $user['nama_lengkap'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['loggedin']      = true;
    header('Location: ../admin/index.php'); exit;
  } else {
    $_SESSION['login_error'] = 'Kredensial tidak valid.';
    header('Location: login.php'); exit;
  }
} catch (Throwable $e) {
  $_SESSION['login_error'] = 'Terjadi masalah pada server.';
  header('Location: login.php'); exit;
}
