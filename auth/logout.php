<?php
// logout.php
declare(strict_types=1);

session_start();

// Hapus semua data session
$_SESSION = [];

// Hapus cookie PHPSESSID (jika ada)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session di server
session_destroy();

// Cegah halaman lama di-cache (back button)
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Arahkan ke halaman login
header('Location: login.php?loggedout=1');
exit;
