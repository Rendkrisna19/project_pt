<?php
// Halaman aktif (ubah sesuai route yang sedang dibuka)
$currentPage = $currentPage ?? 'dashboard';
// Nama user dari session (fallback ke "Rendy Krisna")
$userName = $_SESSION['user_nama'] ?? 'Rendy Krisna';

// Helper class aktif
function activeClass($key, $current) {
    return $key === $current
        ? 'bg-green-600 text-white shadow-sm'
        : 'text-gray-700 hover:bg-gray-100';
}
?>
<aside class="w-72 bg-white shadow-md flex flex-col min-h-screen">
  <!-- Header -->
  <div class="flex items-center p-4 border-b">
    <img src="../assets/images/logo.png" alt="Logo" class="w-12 h-12 mr-3 rounded">
    <div class="leading-tight">
      <h2 class="font-extrabold text-gray-800 text-lg">KEBUN SEI ROKAN</h2>
      <p class="text-sm text-gray-600">Halo,
        <span class="font-semibold text-green-600"><?= htmlspecialchars($userName) ?></span>
      </p>
    </div>
  </div>

  <!-- Nav -->
  <nav class="flex-1 px-3 py-4 overflow-y-auto">
    <span class="text-[11px] tracking-wide text-gray-400 uppercase font-semibold px-2">Menu Utama</span>

    <ul class="mt-2 space-y-1">

      <!-- Dashboard -->
      <li>
        <a href="index.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('dashboard', $currentPage) ?>">
          <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Dashboard</span>
        </a>
      </li>

      <!-- Pemeliharaan -->
      <li>
        <a href="pemeliharaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemeliharaan', $currentPage) ?>">
          <i data-lucide="gauge" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Pemeliharaan</span>
        </a>
      </li>

      <!-- Pemupukan Kimia -->
      <li>
        <a href="pemupukan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan', $currentPage) ?>">
          <i data-lucide="flask-conical" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Pemupukan Kimia</span>
        </a>
      </li>

      <!-- Pemupukan Organik -->
      <li>
        <a href="pemupukan_organik.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan_organik', $currentPage) ?>">
          <i data-lucide="leaf" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Pemupukan Organik</span>
        </a>
      </li>

      <!-- Stok Gudang -->
      <li>
        <a href="stok_gudang.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('stok_gudang', $currentPage) ?>">
          <i data-lucide="package" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Stok Gudang</span>
        </a>
      </li>

      <!-- Pemakaian Bahan ... -->
      <li>
        <a href="pemakaian.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemakaian', $currentPage) ?>">
          <i data-lucide="clipboard-check" class="w-5 h-5 mr-3"></i>
          <span class="font-medium truncate">Pemakaian Bahan ...</span>
        </a>
      </li>

      <!-- Alat Panen -->
      <li>
        <a href="alat_panen.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('alat_panen', $currentPage) ?>">
          <i data-lucide="wrench" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Alat Panen</span>
        </a>
      </li>

      <!-- Pengajuan AU-58 -->
      <li>
        <a href="permintaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('permintaan', $currentPage) ?>">
          <i data-lucide="send" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Pengajuan AU-58</span>
        </a>
      </li>

      <!-- Laporan Manajemen (dropdown) -->
      <li x-data="{ open: false }" class="relative">
        <button @click="open = !open"
                class="flex items-center w-full p-3 rounded-lg transition-colors duration-200 <?= activeClass('laporan', $currentPage) ?>">
          <i data-lucide="bar-chart-3" class="w-5 h-5 mr-3"></i>
          <span class="font-medium flex-1 text-left">Laporan Manajemen</span>
          <i data-lucide="chevron-down" :class="{ 'rotate-180': open }" class="w-4 h-4 ml-2 transition-transform"></i>
        </button>

        <!-- Dropdown -->
        <ul x-show="open" x-transition
            class="mt-1 pl-10 space-y-1 text-sm text-gray-700">
          <li>
            <a href="lm76.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm76', $currentPage) ?>">LM76</a>
          </li>
          <li>
            <a href="lm77.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm77', $currentPage) ?>">LM77</a>
          </li>
          <li>
            <a href="lm_biaya.php" class="block p-2 rounded-md hover:bg-gray-100 <?= activeClass('lm_biaya', $currentPage) ?>">LM Biaya</a>
          </li>
        </ul>
      </li>

      <!-- Master Data -->
      <li>
        <a href="master_data.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('master_data', $currentPage) ?>">
          <i data-lucide="database" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Master Data</span>
        </a>
      </li>

      <!-- Data User -->
      <li>
        <a href="users.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('users', $currentPage) ?>">
          <i data-lucide="users" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Data User</span>
        </a>
      </li>

    </ul>
  </nav>

  <!-- Footer -->
  <div class="p-4 border-t">
    <a href="../auth/logout.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200">
      <i data-lucide="log-out" class="w-5 h-5 mr-3"></i>
      <span class="font-medium">Keluar</span>
    </a>

    <footer class="text-center mt-4">
      <p class="text-xs text-gray-500">&copy; <?= date('Y'); ?> Kebun Sei Rokan</p>
    </footer>
  </div>
</aside>

<!-- Pastikan ikon dirender meski CDN diletakkan di layout -->
<script>
  // Jika lucide sudah dimuat lewat CDN di layout, panggilan ini akan merender semua ikon.
  document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  });
</script>
