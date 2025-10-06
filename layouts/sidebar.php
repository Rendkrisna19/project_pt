<?php
// =======================
// Konfigurasi sederhana
// =======================
$currentPage = $currentPage ?? 'dashboard';
$userName    = $_SESSION['user_nama'] ?? 'Rendy Krisna';

function activeClass($key, $current) {
  return $key === $current
    ? 'bg-green-600 text-white shadow-sm'
    : 'text-gray-700 hover:bg-gray-100';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard - Kebun Sei Rokan</title>
  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Alpine.js -->
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<style>
    /* utilitas global */
    .scrollbar-hide { scrollbar-width: none; -ms-overflow-style: none; }
    .scrollbar-hide::-webkit-scrollbar { width: 0; height: 0; display: none; }
    /* opsional: halus saat scroll */
    html { scroll-behavior: smooth; }
  </style>

<body class="bg-gray-50" x-data="{ sidebarOpen:false }" @keydown.window.escape="sidebarOpen=false">

  <!-- ===================== -->
  <!-- SIDEBAR: Desktop (fixed) -->
  <!-- ===================== -->
  <aside
    class="fixed inset-y-0 left-0 z-40 hidden md:flex w-72 bg-white shadow-md flex-col h-screen">
    <!-- Header -->
    <div class="flex items-center p-4 border-b shrink-0">
      <img src="../assets/images/logo.png" alt="Logo" class="w-12 h-12 mr-3 rounded">
      <div class="leading-tight">
        <h2 class="font-extrabold text-gray-800 text-lg">KEBUN SEI ROKAN</h2>
        <p class="text-sm text-gray-600">Halo,
          <span class="font-semibold text-green-600"><?= htmlspecialchars($userName) ?></span>
        </p>
      </div>
    </div>

    <!-- Nav (scroll area) -->
    <nav class="px-3 py-4 overflow-y-auto flex-1 scrollbar-hide ">
      <span class="text-[11px] tracking-wide text-gray-400 uppercase font-semibold px-2">Menu Utama</span>
      <ul class="mt-2 space-y-1">

        <li>
          <a href="index.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('dashboard',$currentPage) ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Dashboard</span>
          </a>
        </li>

        <li>
          <a href="pemeliharaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemeliharaan',$currentPage) ?>">
            <i data-lucide="gauge" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Pemeliharaan</span>
          </a>
        </li>

        <li>
          <a href="pemupukan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan',$currentPage) ?>">
            <i data-lucide="flask-conical" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Pemupukan Kimia</span>
          </a>
        </li>

        <li>
          <a href="pemupukan_organik.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan_organik',$currentPage) ?>">
            <i data-lucide="leaf" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Pemupukan Organik</span>
          </a>
        </li>

        <li>
          <a href="stok_gudang.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('stok_gudang',$currentPage) ?>">
            <i data-lucide="package" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Stok Gudang</span>
          </a>
        </li>

        <li>
          <a href="pemakaian.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemakaian',$currentPage) ?>">
            <i data-lucide="clipboard-check" class="w-5 h-5 mr-3"></i>
            <span class="font-medium truncate">Pemakaian Bahan ...</span>
          </a>
        </li>

        <li>
          <a href="alat_panen.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('alat_panen',$currentPage) ?>">
            <i data-lucide="wrench" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Alat Panen</span>
          </a>
        </li>

        <li>
          <a href="permintaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('permintaan',$currentPage) ?>">
            <i data-lucide="send" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Pengajuan AU-58</span>
          </a>
        </li>

        <!-- Laporan Manajemen -->
        <li x-data="{ open: false }" class="relative">
          <button @click="open = !open"
                  class="flex items-center w-full p-3 rounded-lg transition-colors duration-200 <?= activeClass('laporan',$currentPage) ?>">
            <i data-lucide="bar-chart-3" class="w-5 h-5 mr-3"></i>
            <span class="font-medium flex-1 text-left">Laporan Manajemen</span>
            <i data-lucide="chevron-down" :class="{ 'rotate-180': open }" class="w-4 h-4 ml-2 transition-transform"></i>
          </button>

          <ul x-show="open" x-transition
              class="mt-1 pl-10 space-y-1 text-sm text-gray-700">
            <li>
              <a href="lm76.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm76',$currentPage) ?>">LM76</a>
            </li>
            <li>
              <a href="lm77.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm77',$currentPage) ?>">LM77</a>
            </li>
            <li>
              <a href="lm_biaya.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm_biaya',$currentPage) ?>">LM Biaya</a>
            </li>
          </ul>
        </li>

        <li>
          <a href="master_data.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('master_data',$currentPage) ?>">
            <i data-lucide="database" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Master Data</span>
          </a>
        </li>

        <li>
          <a href="users.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('users',$currentPage) ?>">
            <i data-lucide="users" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Data User</span>
          </a>
        </li>

      </ul>
    </nav>

    <!-- Footer -->
    <div class="p-4 border-t shrink-0">
      <a href="../auth/logout.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200">
        <i data-lucide="log-out" class="w-5 h-5 mr-3"></i>
        <span class="font-medium">Keluar</span>
      </a>
      <footer class="text-center mt-4">
        <p class="text-xs text-gray-500">&copy; <?= date('Y'); ?> Kebun Sei Rokan</p>
      </footer>
    </div>
  </aside>

  <!-- ===================== -->
  <!-- SIDEBAR: Mobile (slide-in) -->
  <!-- ===================== -->
  <!-- Overlay -->
  <div
    class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm md:hidden"
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen=false"
    aria-hidden="true"></div>

  <!-- Panel -->
  <aside
    class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl md:hidden flex flex-col h-screen transform transition-transform duration-300"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    <!-- Header -->
    <div class="flex items-center p-4 border-b shrink-0">
      <img src="../assets/images/logo.png" alt="Logo" class="w-12 h-12 mr-3 rounded">
      <div class="leading-tight">
        <h2 class="font-extrabold text-gray-800 text-lg">KEBUN SEI ROKAN</h2>
        <p class="text-sm text-gray-600">Halo,
          <span class="font-semibold text-green-600"><?= htmlspecialchars($userName) ?></span>
        </p>
      </div>
      <button class="ml-auto p-2 rounded hover:bg-gray-100" @click="sidebarOpen=false" aria-label="Tutup menu">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Nav (scroll area) -->
    <nav class="px-3 py-4 overflow-y-auto flex-1">
      <span class="text-[11px] tracking-wide text-gray-400 uppercase font-semibold px-2">Menu Utama</span>
      <ul class="mt-2 space-y-1">
        <!-- sama seperti desktop -->
        <li>
          <a href="index.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('dashboard',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Dashboard</span>
          </a>
        </li>

        <li>
          <a href="pemeliharaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemeliharaan',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="gauge" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Pemeliharaan</span>
          </a>
        </li>

        <li>
          <a href="pemupukan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="flask-conical" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Pemupukan Kimia</span>
          </a>
        </li>

        <li>
          <a href="pemupukan_organik.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan_organik',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="leaf" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Pemupukan Organik</span>
          </a>
        </li>

        <li>
          <a href="stok_gudang.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('stok_gudang',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="package" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Stok Gudang</span>
          </a>
        </li>

        <li>
          <a href="pemakaian.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemakaian',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="clipboard-check" class="w-5 h-5 mr-3"></i>
            <span class="font-medium truncate">Pemakaian Bahan ...</span>
          </a>
        </li>

        <li>
          <a href="alat_panen.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('alat_panen',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="wrench" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Alat Panen</span>
          </a>
        </li>

        <li>
          <a href="permintaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('permintaan',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="send" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Pengajuan AU-58</span>
          </a>
        </li>

        <li x-data="{ open:false }">
          <button @click="open=!open"
                  class="flex items-center w-full p-3 rounded-lg transition-colors duration-200 <?= activeClass('laporan',$currentPage) ?>">
            <i data-lucide="bar-chart-3" class="w-5 h-5 mr-3"></i>
            <span class="font-medium flex-1 text-left">Laporan Manajemen</span>
            <i data-lucide="chevron-down" :class="{ 'rotate-180': open }" class="w-4 h-4 ml-2 transition-transform"></i>
          </button>
          <ul x-show="open" x-transition class="mt-1 pl-10 space-y-1 text-sm text-gray-700">
            <li><a href="lm76.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm76',$currentPage) ?>" @click="sidebarOpen=false">LM76</a></li>
            <li><a href="lm77.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm77',$currentPage) ?>" @click="sidebarOpen=false">LM77</a></li>
            <li><a href="lm_biaya.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm_biaya',$currentPage) ?>" @click="sidebarOpen=false">LM Biaya</a></li>
          </ul>
        </li>

        <li>
          <a href="master_data.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('master_data',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="database" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Master Data</span>
          </a>
        </li>

        <li>
          <a href="users.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('users',$currentPage) ?>" @click="sidebarOpen=false">
            <i data-lucide="users" class="w-5 h-5 mr-3"></i>
            <span class="font-medium">Data User</span>
          </a>
        </li>
      </ul>
    </nav>

    <div class="p-4 border-t shrink-0">
      <a href="../auth/logout.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200" @click="sidebarOpen=false">
        <i data-lucide="log-out" class="w-5 h-5 mr-3"></i>
        <span class="font-medium">Keluar</span>
      </a>
      <footer class="text-center mt-4">
        <p class="text-xs text-gray-500">&copy; <?= date('Y'); ?> Kebun Sei Rokan</p>
      </footer>
    </div>
  </aside>

  <!-- ===================== -->
  <!-- TOPBAR -->
  <!-- ===================== -->
  <header class="sticky top-0 z-30 bg-white border-b md:ml-72">
    <div class="flex items-center h-14 px-4">
      <!-- Hamburger: tampil di mobile -->
    
    </div>
  </header>


  <!-- Render ikon Lucide -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
      }
    });
  </script>
</body>
</html>
