<?php
// auth/login.php
session_start();

// Jika sudah login langsung ke dashboard
if (!empty($_SESSION['loggedin'])) {
  header('Location: ../admin/index.php'); exit;
}

require_once '../config/database.php';

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Ambil input & sanitize
  $identity = trim($_POST['identity'] ?? '');   // NIK atau username
  $password = (string)($_POST['password'] ?? '');

  if ($identity === '' || $password === '') {
    $err = 'NIK/Username dan password wajib diisi.';
  } else {
    try {
      $db = new Database();
      $pdo = $db->getConnection();

      // Login via NIK ATAU Username
      $sql = "SELECT id, username, nik, nama_lengkap, password, role
              FROM users
              WHERE nik = :ident OR username = :ident
              LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([':ident' => $identity]);
      $user = $st->fetch(PDO::FETCH_ASSOC);

      if ($user && password_verify($password, $user['password'] ?? '')) {
        // Sukses login
        session_regenerate_id(true);
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_nik']      = $user['nik'];
        $_SESSION['user_nama']     = $user['nama_lengkap'];
        $_SESSION['user_role']     = $user['role'];   // 'admin' | 'staf'
        $_SESSION['loggedin']      = true;

        header('Location: ../admin/index.php'); exit;
      } else {
        $err = 'Kredensial tidak valid.';
      }
    } catch (Throwable $e) {
      $err = 'Terjadi masalah pada server.';
    }
  }

  if ($err) {
    $_SESSION['login_error'] = $err;
    header('Location: login.php'); exit;
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - Kebun Sei Rokan</title>
  <link rel="icon" href="../assets/images/logo.png" type="image/png"/>
  <meta name="theme-color" content="#16a34a"/>
  <meta name="color-scheme" content="light only"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Google Fonts (Poppins) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { poppins: ['Poppins','ui-sans-serif','system-ui'] },
          colors: { brand: { 600: '#16a34a', 700: '#15803d' } },
          boxShadow: { glass: '0 10px 30px rgba(0,0,0,.08)' },
          backdropBlur: { xs: '2px' }
        }
      }
    }
  </script>
</head>
<body class="font-poppins min-h-screen bg-gradient-to-br from-emerald-50 via-white to-emerald-100 flex items-center justify-center p-4">
  <div class="relative w-full max-w-md">
    <div class="absolute -top-10 -left-10 h-28 w-28 bg-emerald-300/30 rounded-full blur-2xl"></div>
    <div class="absolute -bottom-10 -right-10 h-32 w-32 bg-emerald-400/30 rounded-full blur-2xl"></div>

    <div class="relative bg-white/80 backdrop-blur-xs shadow-glass rounded-2xl p-6 md:p-8 border border-white/60">
      <div class="flex flex-col items-center text-center">
        <img src="../assets/images/logo.png" alt="Logo Kebun Sei Rokan" class="h-16 w-auto mb-3"/>
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Selamat Datang</h1>
        <p class="text-gray-500 mt-1">Masuk ke dashboard operasional kebun</p>
      </div>

      <form id="loginForm" method="POST" class="mt-7 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">NIK atau Username</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                      d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m12-10a4 4 0 10-8 0 4 4 0 008 0z"/>
              </svg>
            </span>
            <input type="text" name="identity" placeholder="contoh: 1987654321 atau username" required
                   class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-600 focus:border-brand-600 placeholder:text-gray-400">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                      d="M12 11c-1.657 0-3 1.343-3 3v3h6v-3c0-1.657-1.343-3-3-3z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                      d="M7 11V8a5 5 0 1110 0v3"/>
                <rect x="5" y="11" width="14" height="10" rx="2" ry="2" stroke-width="1.7"/>
              </svg>
            </span>
            <input type="password" id="password" name="password" placeholder="••••••••" required
                   class="w-full pl-10 pr-11 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-600 focus:border-brand-600 placeholder:text-gray-400">
            <button type="button" id="togglePwd"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                    aria-label="Tampilkan/Sembunyikan password">
              <svg id="eye" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                      d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z"/>
                <circle cx="12" cy="12" r="3" stroke-width="1.7"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between text-sm">
          <label class="inline-flex items-center gap-2 select-none">
            <input type="checkbox" class="rounded border-gray-300 text-brand-600 focus:ring-brand-600">
            <span class="text-gray-600">Ingat saya</span>
          </label>
          <span class="text-gray-400"> </span>
        </div>

        <button id="btnSubmit" type="submit"
                class="w-full bg-brand-600 hover:bg-brand-700 text-white py-3 rounded-lg font-semibold transition shadow-sm">
          Masuk
        </button>
      </form>

      <div class="mt-6 text-center text-xs text-gray-400">
        &copy; <?= date('Y') ?> Kebun Sei Rokan. Semua hak dilindungi.
      </div>
    </div>
  </div>

  <script>
    const pw  = document.getElementById('password');
    const btn = document.getElementById('togglePwd');
    btn.addEventListener('click', ()=>{
      pw.type = pw.type === 'password' ? 'text' : 'password';
      document.getElementById('eye').outerHTML =
        pw.type === 'password'
          ? `<svg id="eye" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                     d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z"/>
               <circle cx="12" cy="12" r="3" stroke-width="1.7"/>
             </svg>`
          : `<svg id="eye" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                     d="M3 3l18 18M10.584 10.587A3 3 0 0012 15a3 3 0 002.829-4.11M6.53 6.536C3.905 8.08 2.25 12 2.25 12s3.5 7 9.75 7c1.52 0 2.94-.3 4.2-.84M16.5 7.5C14.98 6.53 13.14 6 12 6 5 6 1.5 12 1.5 12c.49.922 1.17 1.91 2.02 2.85"/>
             </svg>`;
    });

    const form = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnSubmit');
    form.addEventListener('submit', ()=>{
      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Memproses…';
      btnSubmit.classList.add('opacity-80');
    });

    <?php if (!empty($_SESSION['login_error'])): ?>
      Swal.fire({
        icon: 'error',
        title: 'Login Gagal',
        text: '<?= addslashes($_SESSION['login_error']) ?>',
        confirmButtonColor: '#16a34a'
      });
      <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>
  </script>
</body>
</html>
