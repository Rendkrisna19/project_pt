<?php
// auth/login.php
declare(strict_types=1);
session_start();

// Jika sudah login langsung ke dashboard
if (!empty($_SESSION['loggedin'])) {
  header('Location: ../admin/portal.php'); exit;
}

require_once '../config/database.php';

// Set Timezone
date_default_timezone_set('Asia/Jakarta');

$err = null;

// Siapkan CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identity = trim((string)($_POST['identity'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $csrf     = (string)($_POST['csrf_token'] ?? '');

  if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    $err = 'Sesi kedaluwarsa. Silakan muat ulang halaman.';
  } elseif ($identity === '' || $password === '') {
    $err = 'ID SAP/Username dan password wajib diisi.';
  } else {
    try {
      $db  = new Database();
      $pdo = $db->getConnection();

      $sql = "SELECT id, username, nik, nama_lengkap, password, role
              FROM users
              WHERE nik = :ident OR username = :ident
              LIMIT 1";
      $st  = $pdo->prepare($sql);
      $st->execute([':ident' => $identity]);
      $user = $st->fetch(PDO::FETCH_ASSOC);

      if ($user && password_verify($password, $user['password'] ?? '')) {
        
        // Update Last Login
        try {
            $now = date('Y-m-d H:i:s');
            $upSql = "UPDATE users SET last_login = :waktu WHERE id = :id";
            $upSt = $pdo->prepare($upSql);
            $upSt->execute([':waktu' => $now, ':id' => $user['id']]);
        } catch (Exception $e) {}

        session_regenerate_id(true);
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_nik']      = $user['nik'];
        $_SESSION['user_nama']     = $user['nama_lengkap'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['loggedin']      = true;
        $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
        header('Location: ../admin/portal.php'); exit;
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
  <title>Login - MCS System</title>
  <link rel="icon" href="../assets/images/logo.png" type="image/png"/>
  <meta name="theme-color" content="#06b6d4"/>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['Outfit', 'sans-serif'] },
          colors: {
            brand: {
              dark: '#0f172a',    // Slate 900
              glass: '#1e293b',   // Slate 800
              cyan: '#06b6d4',    // Cyan 500
              glow: '#22d3ee'     // Cyan 400
            }
          },
          animation: {
            'float': 'float 6s ease-in-out infinite',
          },
          keyframes: {
            float: {
              '0%, 100%': { transform: 'translateY(0)' },
              '50%': { transform: 'translateY(-10px)' },
            }
          }
        }
      }
    }
  </script>

  <style>
    /* CSS untuk Background Video */
    .video-bg {
      position: fixed;
      right: 0; bottom: 0;
      min-width: 100%; min-height: 100%;
      width: auto; height: auto;
      z-index: -50;
      object-fit: cover;
    }
    
    /* Overlay gelap di atas video agar tulisan terbaca */
    .video-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.75); /* Slate 900 dengan transparansi */
      backdrop-filter: blur(4px);
      z-index: -40;
    }

    /* Efek Kaca (Glassmorphism) */
    .glass-card {
      background: rgba(30, 41, 59, 0.6);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(34, 211, 238, 0.15); /* Border cyan tipis */
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
    }
    
    /* Input Autofill Fix untuk tema gelap */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active{
        -webkit-box-shadow: 0 0 0 30px #1e293b inset !important;
        -webkit-text-fill-color: white !important;
        caret-color: white;
    }
  </style>
</head>
<body class="font-sans text-slate-200 antialiased overflow-hidden">

  <video autoplay muted loop playsinline class="video-bg">
    <source src="../assets/images/bg.mp4" type="video/mp4">
    <img src="../assets/images/PTPN IV.png" alt="Background" class="w-full h-full object-cover">
  </video>

  <div class="video-overlay"></div>

  <main class="min-h-screen flex items-center justify-center p-4 relative z-10">
    
    <div class="absolute top-1/4 left-1/4 w-72 h-72 bg-brand-cyan/20 rounded-full blur-[100px] -z-10 animate-pulse"></div>
    <div class="absolute bottom-1/4 right-1/4 w-72 h-72 bg-blue-600/20 rounded-full blur-[100px] -z-10"></div>

    <section class="w-full max-w-[420px] glass-card rounded-2xl p-8 fade-in relative overflow-hidden">
      <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-brand-cyan to-transparent opacity-70"></div>

      <header class="flex flex-col items-center text-center mb-8">
        <div class="relative mb-4 group animate-float">
            <div class="absolute -inset-1 bg-brand-cyan rounded-full blur opacity-25 group-hover:opacity-50 transition duration-500"></div>
            <img src="../assets/images/PTPN IV.png" alt="Logo" class="relative h-20 w-auto drop-shadow-xl" />
        </div>
        
        <h1 class="text-3xl font-bold text-white tracking-wide">MCS Portal</h1>
        <p class="text-cyan-400 text-xs font-medium tracking-[0.2em] uppercase mt-1">Monitoring Control System</p>
      </header>

      <form id="loginForm" method="POST" class="space-y-5" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>"/>

        <div class="group">
          <label for="identity" class="block text-xs font-medium text-cyan-200/80 mb-1.5 ml-1">ID SAP / USERNAME</label>
          <div class="relative transition-all duration-300">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 group-focus-within:text-brand-cyan transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </span>
            <input
              id="identity"
              type="text"
              name="identity"
              required
              placeholder="Masukkan ID Anda"
              autocomplete="username"
              class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white placeholder-slate-500 focus:outline-none focus:border-brand-cyan focus:ring-1 focus:ring-brand-cyan transition-all"
            />
          </div>
        </div>

        <div class="group">
          <label for="password" class="block text-xs font-medium text-cyan-200/80 mb-1.5 ml-1">PASSWORD</label>
          <div class="relative transition-all duration-300">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 group-focus-within:text-brand-cyan transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </span>
            <input
              id="password"
              type="password"
              name="password"
              required
              placeholder="••••••••"
              autocomplete="current-password"
              class="w-full pl-11 pr-11 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white placeholder-slate-500 focus:outline-none focus:border-brand-cyan focus:ring-1 focus:ring-brand-cyan transition-all"
            />
            <button type="button" id="togglePwd"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-brand-cyan transition-colors cursor-pointer outline-none">
              <svg id="eye" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between text-sm mt-2">
          <label class="inline-flex items-center gap-2 select-none cursor-pointer group">
            <div class="relative flex items-center">
                <input type="checkbox" class="peer sr-only">
                <div class="w-4 h-4 border border-slate-500 rounded bg-transparent peer-checked:bg-brand-cyan peer-checked:border-brand-cyan transition-all"></div>
                <svg class="absolute w-3 h-3 text-white hidden peer-checked:block left-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
            </div>
            <span class="text-slate-400 group-hover:text-slate-200 transition-colors">Ingat saya</span>
          </label>
          <a href="#" class="text-brand-cyan hover:text-brand-glow hover:underline underline-offset-4 decoration-1 transition-colors">Lupa Password?</a>
        </div>

        <button id="btnSubmit" type="submit"
                class="w-full relative overflow-hidden bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-semibold py-3.5 rounded-xl shadow-[0_0_20px_rgba(6,182,212,0.3)] hover:shadow-[0_0_30px_rgba(6,182,212,0.5)] transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]">
          <span class="relative z-10">MASUK SEKARANG</span>
        </button>
      </form>

      <footer class="mt-8 text-center">
        <p class="text-[10px] text-slate-500 uppercase tracking-widest border-t border-slate-700/50 pt-4">
            &copy; <?= date('Y') ?> Monitoring Control System
        </p>
      </footer>
    </section>
  </main>

  <script>
    // --- Logic Toggle Password ---
    const pwInput = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePwd');
    const eyeIcon = document.getElementById('eye');

    toggleBtn.addEventListener('click', () => {
      const isPassword = pwInput.type === 'password';
      pwInput.type = isPassword ? 'text' : 'password';
      
      // Ganti icon SVG
      if (isPassword) {
        // Jadi icon 'Eye Off'
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
        `;
      } else {
        // Balik ke icon 'Eye'
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        `;
      }
    });

    // --- Prevent Double Submit ---
    const form = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnSubmit');
    form.addEventListener('submit', () => {
      btnSubmit.disabled = true;
      btnSubmit.innerHTML = '<span class="animate-pulse">Memproses...</span>';
      btnSubmit.classList.add('opacity-75', 'cursor-not-allowed');
    });

    // --- SweetAlert Error Handler (PHP Session) ---
    <?php if (!empty($_SESSION['login_error'])): ?>
      Swal.fire({
        icon: 'error',
        title: 'Akses Ditolak',
        text: '<?= addslashes($_SESSION['login_error']) ?>',
        background: '#1e293b',
        color: '#fff',
        confirmButtonColor: '#06b6d4',
        confirmButtonText: 'Coba Lagi'
      });
      <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>
  </script>
</body>
</html>