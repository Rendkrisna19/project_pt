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
          <span class="mr-3">
            <!-- squares-2x2 -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5Zm0 8a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H5Zm6-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V5Zm0 8a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2Z"/></svg>
          </span>
          <span class="font-medium">Dashboard</span>
        </a>
      </li>

      <!-- Pemeliharaan -->
      <li>
        <a href="pemeliharaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemeliharaan', $currentPage) ?>">
          <span class="mr-3">
            <!-- gauge / maintenance dial -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 7.938 6.99.75.75 0 1 0-1.486-.2A6.5 6.5 0 1 1 10 3.5a.75.75 0 0 0 0-1.5Zm4.555 5.168A1 1 0 0 0 13 6v1.034a1 1 0 0 0 .445.832l2.5 1.272a1 1 0 1 0 .89-1.79l-2.5-1.272a1 1 0 0 0-.78-.108Z" clip-rule="evenodd"/></svg>
          </span>
          <span class="font-medium">Pemeliharaan</span>
        </a>
      </li>

      <!-- Pemupukan Kimia -->
      <li>
        <a href="pemupukan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan', $currentPage) ?>">
          <span class="mr-3">
            <!-- beaker -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 2.5a.5.5 0 0 1 1 0V4h4V2.5a.5.5 0 0 1 1 0V4h.25A1.75 1.75 0 0 1 15 5.75v9.5A1.75 1.75 0 0 1 13.25 17h-6.5A1.75 1.75 0 0 1 5 15.25v-9.5A1.75 1.75 0 0 1 6.75 4H7V2.5Z"/><path d="M7 6h6v8a.5.5 0 0 1-.5.5h-5A.5.5 0 0 1 7 14V6Z"/></svg>
          </span>
          <span class="font-medium">Pemupukan Kimia</span>
        </a>
      </li>

      <!-- Pemupukan Organik -->
      <li>
        <a href="pemupukan_organik.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan_organik', $currentPage) ?>">
          <span class="mr-3">
            <!-- leaf/organic -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4.75 10a5.25 5.25 0 1 1 10.5 0 5.25 5.25 0 0 1-10.5 0Zm6.78-4.03a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l5.5-5.5a.75.75 0 0 1 1.06 0Z"/></svg>
          </span>
          <span class="font-medium">Pemupukan Organik</span>
        </a>
      </li>

      <!-- Stok Gudang -->
      <li>
        <a href="stok_gudang.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('stok_gudang', $currentPage) ?>">
          <span class="mr-3">
            <!-- warehouse bars -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 7.5a.5.5 0 0 1 .5.5v7a.5.5 0 1 1-1 0V8a.5.5 0 0 1 .5-.5Zm3 0a.5.5 0 0 1 .5.5v7a.5.5 0 1 1-1 0V8a.5.5 0 0 1 .5-.5ZM10 8a.5.5 0 0 1 .5.5v6.5a.5.5 0 1 1-1 0V8.5A.5.5 0 0 1 10 8Zm3 .5a.5.5 0 0 1 1 0v6.5a.5.5 0 1 1-1 0V8.5Z"/><path d="M5.5 4h9a.5.5 0 0 1 .5.5V7h-1.25V5H6.75v2H5.5V4.5a.5.5 0 0 1 .5-.5Z"/></svg>
          </span>
          <span class="font-medium">Stok Gudang</span>
        </a>
      </li>

      <!-- Pemakaian Bahan ... -->
      <li>
        <a href="pemakaian.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemakaian', $currentPage) ?>">
          <span class="mr-3">
            <!-- clipboard-check -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H7Zm1.75 6.75L6.5 12l1 1 1.25-1.25L12.5 8l1 1-3.75 3.75L8.5 15l-2-2 3.25-3.25z"/></svg>
          </span>
          <span class="font-medium truncate">Pemakaian Bahan ...</span>
        </a>
      </li>

      <!-- Alat Panen -->
      <li>
        <a href="alat_panen.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('alat_panen', $currentPage) ?>">
          <span class="mr-3">
            <!-- wrench/hammer -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M12.5 2.5a3 3 0 0 0-2.83 2H8.5a1.5 1.5 0 1 0 0 3h1.17a3 3 0 1 0 2.83-5Z"/><path d="M3.5 12.5 9 7l4 4-5.5 5.5a2 2 0 1 1-2.828-2.828Z"/></svg>
          </span>
          <span class="font-medium">Alat Panen</span>
        </a>
      </li>

      <!-- Pengajuan AU-58 -->
      <li>
        <a href="permintaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('permintaan', $currentPage) ?>">
          <span class="mr-3">
            <!-- paper-airplane / request -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2.94 9.376 16.4 3.424a1 1 0 0 1 1.36 1.24l-4.44 12.12a1 1 0 0 1-1.82.14l-2.2-4.07-4.07-2.2a1 1 0 0 1 .29-1.78Z"/><path d="M8.5 10.5 16 4.75"/></svg>
          </span>
          <span class="font-medium">Pengajuan AU-58</span>
        </a>
      </li>

      <!-- Laporan Manajemen -->
     <li x-data="{ open: false }" class="relative">
  <button @click="open = !open" 
          class="flex items-center w-full p-3 rounded-lg transition-colors duration-200 
                 <?= activeClass('laporan', $currentPage) ?>">
    <span class="mr-3">
      <!-- chart-bar -->
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" 
           viewBox="0 0 20 20" fill="currentColor">
        <path d="M4 13.5V17h2v-3.5H4Zm3.5-5V17h2V8.5h-2ZM11 11v6h2v-6h-2Zm3.5-4V17h2V7h-2Z"/>
      </svg>
    </span>
    <span class="font-medium flex-1 text-left">Laporan Manajemen</span>
    <!-- Icon panah -->
    <svg :class="{ 'rotate-180': open }" class="h-4 w-4 ml-2 transform transition-transform" 
         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
         stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
            d="M19 9l-7 7-7-7" />
    </svg>
  </button>

  <!-- Dropdown -->
  <ul x-show="open" x-transition 
      class="mt-1 pl-10 space-y-1 text-sm text-gray-700">
    <li>
      <a href="lm76.php" class="block p-2 rounded-md hover:bg-gray-100 
                 <?= activeClass('lm76', $currentPage) ?>">LM76</a>
    </li>
    <li>
      <a href="lm77.php" class="block p-2 rounded-md hover:bg-gray-100 
                 <?= activeClass('lm77', $currentPage) ?>">LM77</a>
    </li>
    <li>
      <a href="lm_biaya.php" class="block p-2 rounded-md hover:bg-gray-100 
                 <?= activeClass('lm_biaya', $currentPage) ?>">LM Biaya</a>
    </li>
  </ul>
</li>

      <!-- Master Data -->
      <li>
        <a href="master_data.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('master_data', $currentPage) ?>">
          <span class="mr-3">
            <!-- database stack -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2c-4.418 0-8 1.343-8 3s3.582 3 8 3 8-1.343 8-3-3.582-3-8-3Zm0 6c-4.418 0-8 1.343-8 3s3.582 3 8 3 8-1.343 8-3-3.582-3-8-3Zm0 6c-4.418 0-8 1.343-8 3s3.582 3 8 3 8-1.343 8-3-3.582-3-8-3Z"/></svg>
          </span>
          <span class="font-medium">Master Data</span>
        </a>
      </li>

      <!-- Data User -->
      <li>
        <a href="users.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('users', $currentPage) ?>">
          <span class="mr-3">
            <!-- users -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 8a3 3 0 1 1 6 0 3 3 0 0 1-6 0Z"/><path d="M3 15.5A4.5 4.5 0 0 1 7.5 11h5A4.5 4.5 0 0 1 17 15.5V17H3v-1.5Z"/></svg>
          </span>
          <span class="font-medium">Data User</span>
        </a>
      </li>

    </ul>
  </nav>

  <!-- Footer -->
  <div class="p-4 border-t">
    <a href="../auth/login.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200">
      <span class="mr-3">
        <!-- arrow-right-from-bracket -->
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 0 0-1 1v12a1 1 0 1 0 2 0V4a1 1 0 0 0-1-1Zm10.293 9.293a1 1 0 0 0 1.414 1.414l3-3a1 1 0 0 0 0-1.414l-3-3a1 1 0 1 0-1.414 1.414L14.586 9H7a1 1 0 1 0 0 2h7.586l-1.293 1.293Z" clip-rule="evenodd"/></svg>
      </span>
      <span class="font-medium">Keluar</span>
    </a>

    <footer class="text-center mt-4">
      <p class="text-xs text-gray-500">&copy; <?= date('Y'); ?> Kebun Sei Rokan</p>
    </footer>
  </div>
</aside>
