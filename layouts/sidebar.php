<?php
// sidebar.php — Sidebar responsif dengan hamburger (full overlay di mobile)

$currentPage = $currentPage ?? 'dashboard';
$userName    = $_SESSION['user_nama'] ?? 'Pengguna';
$userRole    = $_SESSION['user_role'] ?? 'admin';

function activeClass($key, $current) {
  return $key === $current
    ? 'bg-green-600 text-white shadow-sm'
    : 'text-gray-700 hover:bg-gray-100';
}

// daftar child pemeliharaan untuk kontrol "open" dropdown
$PEM_CHILD_KEYS = [
  'pemeliharaan', 'pemeliharaan_tm',
  'pemeliharaan_tu','pemeliharaan_tk',
  'pemeliharaan_tbm1','pemeliharaan_tbm2','pemeliharaan_tbm3',
  'pemeliharaan_pn','pemeliharaan_mn',
];

// ADDED: daftar child Laporan Manajemen (untuk kontrol open & state parent)
$LM_CHILD_KEYS = ['lm76','lm77','lm_biaya'];
?>
<aside class="fixed inset-y-0 left-0 z-40 hidden md:flex w-72 bg-white shadow-md flex-col">
  <div class="flex items-center p-4 border-b shrink-0">
    <img src="../assets/images/logo.png" alt="Logo" class="w-12 h-12 mr-3 rounded">
    <div class="leading-tight">
      <h2 class="font-extrabold text-gray-800 text-lg">KEBUN SEI ROKAN</h2>
      <p class="text-sm text-gray-600">Halo,
        <span class="font-semibold text-green-600"><?= htmlspecialchars($userName) ?></span>
      </p>
    </div>
  </div>

  <nav class="px-3 py-4 overflow-y-auto flex-1 scrollbar-hide">
    <span class="text-[11px] tracking-wide text-gray-400 uppercase font-semibold px-2">Menu Utama</span>
    <ul class="mt-2 space-y-1">
      <li>
        <a href="index.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('dashboard',$currentPage) ?>">
          <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">Dashboard</span>
        </a>
      </li>

      <!-- DROPDOWN: Pemeliharaan (umum + seluruh varian) -->
      <li x-data="{ open: <?= in_array($currentPage, $PEM_CHILD_KEYS, true) ? 'true' : 'false' ?> }" class="relative">
        <!-- Parent TIDAK aktif/hijau -->
        <button type="button"
                @click="open = !open"
                class="flex items-center w-full p-3 rounded-lg transition-colors duration-200 text-gray-700 hover:bg-gray-100">
          <i data-lucide="gauge" class="w-5 h-5 mr-3"></i>
          <span class="font-medium flex-1 text-left">Pemeliharaan</span>
          <i data-lucide="chevron-down" :class="{ 'rotate-180': open }" class="w-4 h-4 ml-2 transition-transform"></i>
        </button>
        <ul x-show="open" x-transition class="mt-1 pl-10 space-y-1 text-sm text-gray-700">
          <!-- <li><a href="pemeliharaan.php"     class="block p-2 rounded-md <?= activeClass('pemeliharaan',$currentPage) ?>">Pemeliharaan (Umum)</a></li> -->
          <li><a href="pemeliharaan_tm.php"  class="block p-2 rounded-md <?= activeClass('pemeliharaan_tm',$currentPage) ?>">Pemeliharaan TM</a></li>
          <li><a href="pemeliharaan_tu.php"  class="block p-2 rounded-md <?= activeClass('pemeliharaan_tu',$currentPage) ?>">Pemeliharaan TU</a></li>
          <li><a href="pemeliharaan_tk.php"  class="block p-2 rounded-md <?= activeClass('pemeliharaan_tk',$currentPage) ?>">Pemeliharaan TK</a></li>
          <li><a href="pemeliharaan_tbm1.php" class="block p-2 rounded-md <?= activeClass('pemeliharaan_tbm1',$currentPage) ?>">Pemeliharaan TBM I</a></li>
          <li><a href="pemeliharaan_tbm2.php" class="block p-2 rounded-md <?= activeClass('pemeliharaan_tbm2',$currentPage) ?>">Pemeliharaan TBM II</a></li>
          <li><a href="pemeliharaan_tbm3.php" class="block p-2 rounded-md <?= activeClass('pemeliharaan_tbm3',$currentPage) ?>">Pemeliharaan TBM III</a></li>
          <li><a href="pemeliharaan_pn.php"  class="block p-2 rounded-md <?= activeClass('pemeliharaan_pn',$currentPage) ?>">Pemeliharaan PN</a></li>
          <li><a href="pemeliharaan_mn.php"  class="block p-2 rounded-md <?= activeClass('pemeliharaan_mn',$currentPage) ?>">Pemeliharaan MN</a></li>
        </ul>
      </li>

      <li>
        <a href="laporan_mingguan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('laporan_mingguan',$currentPage) ?>">
          <i data-lucide="calendar-days" class="w-5 h-5 mr-3"></i>
          <span class="font-medium">ARSIP</span>
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

      <!-- ADDED: DROPDOWN Laporan Manajemen (DESKTOP) -->
      <li x-data="{ open: <?= in_array($currentPage, $LM_CHILD_KEYS, true) ? 'true' : 'false' ?> }" class="relative">
        <button type="button"
                @click="open = !open"
                class="flex items-center w-full p-3 rounded-lg transition-colors duration-200 <?= in_array($currentPage,$LM_CHILD_KEYS,true) ? 'bg-green-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100' ?>">
          <i data-lucide="bar-chart-3" class="w-5 h-5 mr-3"></i>
          <span class="font-medium flex-1 text-left">Laporan Manajemen</span>
          <i data-lucide="chevron-down" :class="{ 'rotate-180': open }" class="w-4 h-4 ml-2 transition-transform"></i>
        </button>
        <ul x-show="open" x-transition class="mt-1 pl-10 space-y-1 text-sm text-gray-700">
          <li><a href="lm76.php"     class="block p-2 rounded-md <?= activeClass('lm76',$currentPage) ?>">LM76</a></li>
          <li><a href="lm77.php"     class="block p-2 rounded-md <?= activeClass('lm77',$currentPage) ?>">LM77</a></li>
          <li><a href="lm_biaya.php" class="block p-2 rounded-md <?= activeClass('lm_biaya',$currentPage) ?>">LM Biaya</a></li>
        </ul>
      </li>
      <!-- END ADDED -->

      <?php if ($userRole !== 'staf'): ?>
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
      <?php endif; ?>
    </ul>
  </nav>

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

<!-- Overlay Mobile -->
<div class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm md:hidden" x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen=false" aria-hidden="true"></div>

<!-- Drawer Mobile -->
<aside class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl md:hidden flex flex-col transform transition-transform duration-300" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" role="dialog" aria-modal="true" aria-label="Menu navigasi">
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

  <nav class="px-3 py-4 overflow-y-auto flex-1">
    <span class="text-[11px] tracking-wide text-gray-400 uppercase font-semibold px-2">Menu Utama</span>
    <ul class="mt-2 space-y-1">
      <li>
        <a href="index.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('dashboard',$currentPage) ?>" @click="sidebarOpen=false">
          <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i><span class="font-medium">Dashboard</span>
        </a>
      </li>

      <!-- DROPDOWN Mobile: Pemeliharaan -->
      <li x-data="{ open: <?= in_array($currentPage, $PEM_CHILD_KEYS, true) ? 'true' : 'false' ?> }">
        <button type="button" @click="open=!open" class="flex items-center w-full p-3 rounded-lg transition-colors duration-200 text-gray-700 hover:bg-gray-100">
          <i data-lucide="gauge" class="w-5 h-5 mr-3"></i>
          <span class="font-medium flex-1 text-left">Pemeliharaan</span>
          <i data-lucide="chevron-down" :class="{ 'rotate-180': open }" class="w-4 h-4 ml-2 transition-transform"></i>
        </button>
        <ul x-show="open" x-transition class="mt-1 pl-10 space-y-1 text-sm text-gray-700">
          <!-- <li><a href="pemeliharaan.php"      class="block p-2 rounded-md <?= activeClass('pemeliharaan',$currentPage) ?>"       @click="sidebarOpen=false">Pemeliharaan (Umum)</a></li> -->
          <li><a href="pemeliharaan_tm.php"   class="block p-2 rounded-md <?= activeClass('pemeliharaan_tm',$currentPage) ?>"    @click="sidebarOpen=false">Pemeliharaan TM</a></li>
          <li><a href="pemeliharaan_tu.php"   class="block p-2 rounded-md <?= activeClass('pemeliharaan_tu',$currentPage) ?>"    @click="sidebarOpen=false">Pemeliharaan TU</a></li>
          <li><a href="pemeliharaan_tk.php"   class="block p-2 rounded-md <?= activeClass('pemeliharaan_tk',$currentPage) ?>"    @click="sidebarOpen=false">Pemeliharaan TK</a></li>
          <li><a href="pemeliharaan_tbm1.php" class="block p-2 rounded-md <?= activeClass('pemeliharaan_tbm1',$currentPage) ?>"  @click="sidebarOpen=false">Pemeliharaan TBM I</a></li>
          <li><a href="pemeliharaan_tbm2.php" class="block p-2 rounded-md <?= activeClass('pemeliharaan_tbm2',$currentPage) ?>"  @click="sidebarOpen=false">Pemeliharaan TBM II</a></li>
          <li><a href="pemeliharaan_tbm3.php" class="block p-2 rounded-md <?= activeClass('pemeliharaan_tbm3',$currentPage) ?>"  @click="sidebarOpen=false">Pemeliharaan TBM III</a></li>
          <li><a href="pemeliharaan_pn.php"   class="block p-2 rounded-md <?= activeClass('pemeliharaan_pn',$currentPage) ?>"    @click="sidebarOpen=false">Pemeliharaan PN</a></li>
          <li><a href="pemeliharaan_mn.php"   class="block p-2 rounded-md <?= activeClass('pemeliharaan_mn',$currentPage) ?>"    @click="sidebarOpen=false">Pemeliharaan MN</a></li>
        </ul>
      </li>

      <li><a href="pemupukan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan',$currentPage) ?>" @click="sidebarOpen=false"><i data-lucide="flask-conical" class="w-5 h-5 mr-3"></i><span class="font-medium">Pemupukan Kimia</span></a></li>
      <li><a href="pemupukan_organik.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemupukan_organik',$currentPage) ?>" @click="sidebarOpen=false"><i data-lucide="leaf" class="w-5 h-5 mr-3"></i><span class="font-medium">Pemupukan Organik</span></a></li>
      <li><a href="stok_gudang.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('stok_gudang',$currentPage) ?>" @click="sidebarOpen=false"><i data-lucide="package" class="w-5 h-5 mr-3"></i><span class="font-medium">Stok Gudang</span></a></li>
      <li><a href="pemakaian.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('pemakaian',$currentPage) ?>" @click="sidebarOpen=false"><i data-lucide="clipboard-check" class="w-5 h-5 mr-3"></i><span class="font-medium truncate">Pemakaian Bahan ...</span></a></li>
      <li><a href="alat_panen.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('alat_panen',$currentPage) ?>" @click="sidebarOpen=false"><i data-lucide="wrench" class="w-5 h-5 mr-3"></i><span class="font-medium">Alat Panen</span></a></li>
      <li><a href="laporan_mingguan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('laporan_mingguan',$currentPage) ?>" @click="sidebarOpen=false"><i data-lucide="calendar-days" class="w-5 h-5 mr-3"></i><span class="font-medium">ARSIP</span></a></li>
      <li><a href="permintaan.php" class="flex items-center p-3 rounded-lg transition-colors duration-200 <?= activeClass('permintaan',$currentPage) ?>" @click="sidebarOpen=false"><i data-lucide="send" class="w-5 h-5 mr-3"></i><span class="font-medium">Pengajuan AU-58</span></a></li>

      <li x-data="{ open:false }">
        <button type="button" @click="open=!open" class="flex items-center w-full p-3 rounded-lg transition-colors duration-200 <?= activeClass('laporan',$currentPage) ?>">
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

      <?php if ($userRole !== 'staf'): ?>
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
      <?php endif; ?>
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
