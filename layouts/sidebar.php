<?php
// layouts/sidebar.php

$currentPage = $currentPage ?? 'dashboard';
$userName    = $_SESSION['user_nama'] ?? 'User';
$userRole    = $_SESSION['user_role'] ?? 'viewer'; 

// Helper function menu aktif
function navClass($key, $current) {
    // Mempertahankan style aktif dengan efek glowing cyan
    return $key === $current
        ? 'bg-gradient-to-r from-cyan-500/20 to-transparent border-l-4 border-cyan-400 text-white shadow-[0_0_15px_rgba(6,182,212,0.3)] font-medium tracking-wide'
        : 'border-l-4 border-transparent text-slate-400 hover:bg-white/5 transition-all duration-300'; 
}

// Logika Dropdown (Array Menu)
$PEM_KEYS    = ['pemeliharaan', 'pemeliharaan_tm', 'pemeliharaan_tu','pemeliharaan_tk', 'pemeliharaan_tbm1','pemeliharaan_tbm2','pemeliharaan_tbm3', 'pemeliharaan_pn','pemeliharaan_mn'];
$GUDANG_KEYS = ['stok_gudang', 'alat_panen', 'stok_barang_gudang'];
$LM_KEYS     = ['lm76', 'lm77', 'lm_biaya'];
$SDM_KEYS    = ['data_karyawan']; // Array untuk Menu Baru

// Cek apakah dropdown harus terbuka berdasarkan halaman aktif
$isPemOpen    = in_array($currentPage, $PEM_KEYS);
$isGudangOpen = in_array($currentPage, $GUDANG_KEYS);
$isLmOpen     = in_array($currentPage, $LM_KEYS);
$isSdmOpen    = in_array($currentPage, $SDM_KEYS); // Cek Menu Baru
?>

<div x-show="sidebarOpen" 
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-slate-900/80 backdrop-blur-sm md:hidden" 
     style="display: none;"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-50 w-72 bg-[#021019] text-white transition-transform duration-300 ease-in-out shadow-2xl md:translate-x-0 flex flex-col border-r border-cyan-900/30">

    <div class="absolute inset-0 z-0 pointer-events-none overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0c4a6e] via-[#021019] to-[#020617] opacity-90"></div>
        <div class="absolute inset-0 opacity-[0.05]" 
             style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 100 100\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M0 50 C 25 75, 75 75, 100 50 C 75 25, 25 25, 0 50 Z\' fill=\'none\' stroke=\'%2322d3ee\' stroke-width=\'2\'/%3E%3C/svg%3E'); background-size: 30px 30px;">
        </div>
    </div>

    <div class="relative z-10 flex flex-col h-full">
        
        <div class="flex items-center justify-between h-16 px-6 border-b border-cyan-500/20 bg-[#000000]/20 backdrop-blur-sm shrink-0">
            <div class="flex items-center gap-3">
                <img src="../assets/images/PTPN IV.png" alt="Logo" class="w-8 h-8 rounded bg-white/10 p-0.5 border border-white/20 shadow-lg shadow-cyan-500/20">
                <div>
                    <h1 class="font-bold text-[12px] tracking-wider text-white">MONITORING CONTROL</h1>
                    <p class="text-[9px] text-cyan-400 uppercase tracking-widest">System</p>
                </div>
            </div>
            <button @click="sidebarOpen = false" class="md:hidden text-cyan-200 hover:text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="px-5 py-6">
             <div class="flex items-center gap-3 p-3 rounded-xl bg-gradient-to-r from-cyan-900/20 to-slate-900/20 border border-cyan-500/10 backdrop-blur-sm">
                <div class="w-9 h-9 rounded-full bg-cyan-700 flex items-center justify-center text-white font-bold shadow-inner">
                    <?= strtoupper(substr($userName, 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-semibold truncate text-cyan-50"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[10px] text-cyan-300/70 uppercase"><?= htmlspecialchars($userRole) ?></p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-3 space-y-1 overflow-y-auto custom-scrollbar pb-6">

            <a href="portal.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-teal-300 <?= navClass('portal_aplikasi', $currentPage) ?>">
                <i data-lucide="home" class="w-5 h-5 mr-3 group-hover:text-teal-400 transition-colors <?= $currentPage=='portal_aplikasi'?'text-white':'' ?>"></i>
                <span>Home</span>
            </a>
            
            <a href="index.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-cyan-300 <?= navClass('dashboard', $currentPage) ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3 group-hover:text-cyan-400 transition-colors"></i>
                <span>Dashboard</span>
            </a>

            <div class="px-4 mt-6 mb-2 flex items-center gap-2">
                <span class="text-[10px] font-bold text-cyan-600/70 uppercase tracking-widest">Operasional</span>
                <div class="h-px bg-cyan-900/30 flex-1"></div>
            </div>

            <div x-data="{ open: <?= $isPemOpen ? 'true' : 'false' ?> }">
                <button @click="open = !open" type="button" 
                    class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium text-slate-400 hover:text-cyan-300 hover:bg-white/5 rounded-r-lg transition-all group">
                    <div class="flex items-center">
                        <i data-lucide="gauge" class="w-5 h-5 mr-3 group-hover:text-cyan-400 transition-colors"></i>
                        <span>Anggaran vs Real.</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180 text-cyan-400' : ''"></i>
                </button>
                <div x-show="open" x-collapse class="space-y-1 relative mt-1">
                    <div class="absolute left-6 top-0 bottom-0 w-px bg-cyan-800/30"></div>
                    <?php 
                    $subs = [
                        ['url'=>'pemeliharaan_tm.php',   'lbl'=>'Pemeliharaan TM',   'k'=>'pemeliharaan_tm'],
                        ['url'=>'pemeliharaan_tu.php',   'lbl'=>'Pemeliharaan TU',   'k'=>'pemeliharaan_tu'],
                        ['url'=>'pemeliharaan_tk.php',   'lbl'=>'Pemeliharaan TK',   'k'=>'pemeliharaan_tk'],
                        ['url'=>'pemeliharaan_tbm1.php', 'lbl'=>'Pemeliharaan TBM I','k'=>'pemeliharaan_tbm1'],
                        ['url'=>'pemeliharaan_tbm2.php', 'lbl'=>'Pemeliharaan TBM II','k'=>'pemeliharaan_tbm2'],
                        ['url'=>'pemeliharaan_tbm3.php', 'lbl'=>'Pemeliharaan TBM III','k'=>'pemeliharaan_tbm3'],
                        ['url'=>'pemeliharaan_pn.php',   'lbl'=>'Pemeliharaan PN',   'k'=>'pemeliharaan_pn'],
                        ['url'=>'pemeliharaan_mn.php',   'lbl'=>'Pemeliharaan MN',   'k'=>'pemeliharaan_mn'],
                    ];
                    foreach($subs as $s): ?>
                        <a href="<?= $s['url'] ?>" class="block pl-10 pr-3 py-2 text-xs rounded-r-md transition-all <?= $currentPage == $s['k'] ? 'text-cyan-300 font-bold bg-cyan-900/20 border-r-2 border-cyan-500' : 'text-slate-500 hover:text-cyan-200' ?>">
                           <?= $s['lbl'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <a href="pilih_unit.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-orange-300 <?= navClass('pilih_unit', $currentPage) ?>">
                <i data-lucide="file-spreadsheet" class="w-5 h-5 mr-3 group-hover:text-orange-400 transition-colors <?= $currentPage=='kertas_kerja'?'text-white':'' ?>"></i>
                <span>Kertas Kerja</span>
            </a>

            <a href="pemupukan.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-purple-300 <?= navClass('pemupukan', $currentPage) ?>">
                <i data-lucide="flask-conical" class="w-5 h-5 mr-3 group-hover:text-purple-400 transition-colors <?= $currentPage=='pemupukan'?'text-white':'' ?>"></i>
                <span>Pemupukan Kimia</span>
            </a>

            <a href="pemupukan_organik.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-green-300 <?= navClass('pemupukan_organik', $currentPage) ?>">
                <i data-lucide="leaf" class="w-5 h-5 mr-3 group-hover:text-green-400 transition-colors <?= $currentPage=='pemupukan_organik'?'text-white':'' ?>"></i>
                <span>Pemupukan Organik</span>
            </a>

            <div class="px-4 mt-6 mb-2 flex items-center gap-2">
                <span class="text-[10px] font-bold text-cyan-600/70 uppercase tracking-widest">Logistik</span>
                <div class="h-px bg-cyan-900/30 flex-1"></div>
            </div>

            <div x-data="{ open: <?= $isGudangOpen ? 'true' : 'false' ?> }">
                <button @click="open = !open" type="button" 
                    class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium text-slate-400 hover:text-yellow-300 hover:bg-white/5 rounded-r-lg transition-all group">
                    <div class="flex items-center">
                        <i data-lucide="package" class="w-5 h-5 mr-3 group-hover:text-yellow-400 transition-colors"></i>
                        <span>Stok Gudang</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180 text-yellow-400' : ''"></i>
                </button>
                <div x-show="open" x-collapse class="space-y-1 relative mt-1">
                    <div class="absolute left-6 top-0 bottom-0 w-px bg-cyan-800/30"></div>
                    <a href="stok_barang_gudang.php" class="block pl-10 pr-3 py-2 text-xs rounded-r-md transition-all <?= $currentPage == 'stok_barang_gudang' ? 'text-yellow-300 font-bold bg-cyan-900/20 border-r-2 border-yellow-500' : 'text-slate-500 hover:text-yellow-200' ?>">Barang Gudang</a>
                    <a href="stok_gudang.php" class="block pl-10 pr-3 py-2 text-xs rounded-r-md transition-all <?= $currentPage == 'stok_gudang' ? 'text-yellow-300 font-bold bg-cyan-900/20 border-r-2 border-yellow-500' : 'text-slate-500 hover:text-yellow-200' ?>">Bahan Kimia</a>
                    <a href="alat_panen.php" class="block pl-10 pr-3 py-2 text-xs rounded-r-md transition-all <?= $currentPage == 'alat_panen' ? 'text-yellow-300 font-bold bg-cyan-900/20 border-r-2 border-yellow-500' : 'text-slate-500 hover:text-yellow-200' ?>">Alat Pertanian</a>
                </div>
            </div>

            <a href="pemakaian_barang_gudang.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-red-300 <?= navClass('pemakaian_barang_gudang', $currentPage) ?>">
                <i data-lucide="fuel" class="w-5 h-5 mr-3 group-hover:text-red-400 transition-colors <?= $currentPage=='pemakaian_barang_gudang'?'text-white':'' ?>"></i>
                <span>Pemakaian BBM</span>
            </a>

            <a href="pemakaian.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-pink-300 <?= navClass('pemakaian', $currentPage) ?>">
                <i data-lucide="clipboard-check" class="w-5 h-5 mr-3 group-hover:text-pink-400 transition-colors <?= $currentPage=='pemakaian'?'text-white':'' ?>"></i>
                <span>Pemakaian Bahan Kimia </span>
            </a>

            <div class="px-4 mt-6 mb-2 flex items-center gap-2">
                <span class="text-[10px] font-bold text-cyan-600/70 uppercase tracking-widest">SDM & Personalia</span>
                <div class="h-px bg-cyan-900/30 flex-1"></div>
            </div>

            <a href="data_karyawan_menu.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-teal-300 <?= navClass('data_karyawan', $currentPage) ?>">
                <i data-lucide="briefcase" class="w-5 h-5 mr-3 group-hover:text-teal-400 transition-colors <?= $currentPage=='data_karyawan'?'text-white':'' ?>"></i>
                <span>Data Karyawan</span>
            </a>

            <div class="px-4 mt-6 mb-2 flex items-center gap-2">
                <span class="text-[10px] font-bold text-cyan-600/70 uppercase tracking-widest">Laporan</span>
                <div class="h-px bg-cyan-900/30 flex-1"></div>
            </div>

            <div x-data="{ open: <?= $isLmOpen ? 'true' : 'false' ?> }">
                <button @click="open = !open" type="button" 
                    class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium text-slate-400 hover:text-indigo-300 hover:bg-white/5 rounded-r-lg transition-all group">
                    <div class="flex items-center">
                        <i data-lucide="bar-chart-3" class="w-5 h-5 mr-3 group-hover:text-indigo-400 transition-colors"></i>
                        <span>Laporan Manajemen</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180 text-indigo-400' : ''"></i>
                </button>
                <div x-show="open" x-collapse class="space-y-1 relative mt-1">
                    <div class="absolute left-6 top-0 bottom-0 w-px bg-cyan-800/30"></div>
                    <a href="lm76.php" class="block pl-10 pr-3 py-2 text-xs rounded-r-md transition-all <?= $currentPage == 'lm76' ? 'text-indigo-300 font-bold bg-cyan-900/20 border-r-2 border-indigo-500' : 'text-slate-500 hover:text-indigo-200' ?>">LM76</a>
                    <a href="lm77.php" class="block pl-10 pr-3 py-2 text-xs rounded-r-md transition-all <?= $currentPage == 'lm77' ? 'text-indigo-300 font-bold bg-cyan-900/20 border-r-2 border-indigo-500' : 'text-slate-500 hover:text-indigo-200' ?>">LM77</a>
                    <a href="lm_biaya.php" class="block pl-10 pr-3 py-2 text-xs rounded-r-md transition-all <?= $currentPage == 'lm_biaya' ? 'text-indigo-300 font-bold bg-cyan-900/20 border-r-2 border-indigo-500' : 'text-slate-500 hover:text-indigo-200' ?>">LM Biaya</a>
                </div>
            </div>

            <a href="laporan_mingguan.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-amber-300 <?= navClass('laporan_mingguan', $currentPage) ?>">
                <i data-lucide="archive" class="w-5 h-5 mr-3 group-hover:text-amber-400 transition-colors <?= $currentPage=='laporan_mingguan'?'text-white':'' ?>"></i>
                <span>Arsip</span>
            </a>

            <?php if ($userRole === 'admin'): ?>
                <div class="px-4 mt-6 mb-2 flex items-center gap-2">
                    <span class="text-[10px] font-bold text-cyan-600/70 uppercase tracking-widest">System</span>
                    <div class="h-px bg-cyan-900/30 flex-1"></div>
                </div>
                <a href="master_data.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-gray-300 <?= navClass('master_data', $currentPage) ?>">
                    <i data-lucide="database" class="w-5 h-5 mr-3 group-hover:text-gray-200 transition-colors <?= $currentPage=='master_data'?'text-white':'' ?>"></i>
                    <span>Master Data</span>
                </a>
                <a href="users.php" class="flex items-center px-4 py-3 text-sm rounded-r-lg group hover:text-gray-300 <?= navClass('users', $currentPage) ?>">
                    <i data-lucide="users" class="w-5 h-5 mr-3 group-hover:text-gray-200 transition-colors <?= $currentPage=='users'?'text-white':'' ?>"></i>
                    <span>Data User</span>
                </a>
            <?php endif; ?>

            <div class="mt-8 mb-4 px-4">
                <a href="../auth/logout.php" class="flex items-center justify-center w-full px-4 py-3 text-sm font-semibold text-white transition-all bg-gradient-to-r from-red-600 to-red-800 rounded-lg hover:from-red-500 hover:to-red-700 shadow-lg shadow-red-900/40">
                    <i data-lucide="log-out" class="w-5 h-5 mr-2"></i>
                    Keluar Aplikasi
                </a>
            </div>

        </nav>
        
        <div class="p-3 text-center border-t border-cyan-500/10 bg-[#000000]/30 text-[10px] text-slate-500">
            &copy; <?= date('Y') ?> Kebun Sei Rokan <br>
            <span class="text-slate-600">v.1.2.0 Ocean Build</span>
        </div>
    </div>
</aside>