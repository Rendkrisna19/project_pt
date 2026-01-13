<?php
// layouts/header.php
$currentPage = $currentPage ?? 'dashboard';
$pageTitle   = $pageTitle ?? ucfirst(str_replace('_', ' ', $currentPage));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - System Sei Rokan</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> 
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" sizes="32x32" href="../assets/images/logo.png">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />

    <style>
        body { font-family: "Poppins", sans-serif; background-color: #f3f4f6; }
        
        /* Custom Scrollbar untuk Modal & Dropdown */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .bg-sea-pattern {
            background-color: #0ea5e9;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 50 C 25 75, 75 75, 100 50 C 75 25, 25 25, 0 50 Z M0 75 C 25 100, 75 100, 100 75 C 75 50, 25 50, 0 75 Z M0 25 C 25 50, 75 50, 100 25 C 75 0, 25 0, 0 25 Z' fill='none' stroke='%23bae6fd' stroke-width='2' opacity='0.5'/%3E%3C/svg%3E"), linear-gradient(to bottom, #0ea5e9, #0284c7);
            background-repeat: repeat;
            background-size: 80px 80px, cover;
        }
        
        /* Animasi Lonceng */
        @keyframes ring {
            0%, 100% { transform: rotate(0); }
            10%, 30%, 50%, 70%, 90% { transform: rotate(10deg); }
            20%, 40%, 60%, 80% { transform: rotate(-10deg); }
        }
        .bell-ring { animation: ring 2s ease-in-out infinite; }
    </style>
</head>

<body class="text-gray-800 antialiased" x-data="{ sidebarOpen: false, notifOpen: false, showModal: false }">

    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <div class="flex flex-col min-h-screen transition-all duration-300 md:ml-72">

        <header class="sticky top-0 z-50 bg-sea-pattern shadow-md h-16 flex items-center justify-between px-4 md:px-8 text-white relative overflow-visible">            
            <div class="absolute inset-0 bg-cyan-900/10 pointer-events-none overflow-hidden"></div>

            <div class="flex items-center gap-4 z-10">
                <button @click="sidebarOpen = true" class="md:hidden p-2 rounded-lg text-white hover:bg-white/20 focus:outline-none transition-colors">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>

                <div class="md:hidden">
                    <span class="text-sm font-bold text-gray-100">Sei Rokan App</span>
                </div>

                <div class="hidden md:flex items-center gap-3">
                    <img src="../assets/images/logo3.png" onerror="this.style.display='none'" alt="Logo 1" class="h-10 w-auto rounded-md p-1 transition-transform duration-200">
                    <img src="../assets/images/logo4.png" onerror="this.style.display='none'" alt="Logo 2" class="h-10 w-auto rounded-md p-1 transition-transform duration-200">
                    <img src="../assets/images/logo5.png" onerror="this.style.display='none'" alt="Logo 3" class="h-20 w-auto rounded-md p-1 transition-transform duration-200">
                </div>
            </div>

            <div class="flex items-center gap-4 z-20">
                <div class="hidden md:flex flex-col items-end">
                    <span class="text-xs font-semibold text-cyan-100"><?= date('l') ?></span>
                    <span class="text-xs font-bold text-white"><?= date('d F Y') ?></span>
                </div>

                <div class="relative" @click.away="notifOpen = false">
                    <button @click="notifOpen = !notifOpen; resetBadge()" class="relative p-2 rounded-full hover:bg-white/20 text-white transition-colors focus:outline-none">
                        <i data-lucide="bell" class="w-6 h-6" id="bell-icon"></i>
                        <span id="notif-badge" class="absolute top-1 right-1 flex h-3 w-3 hidden">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white"></span>
                        </span>
                    </button>

                    <div x-show="notifOpen" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-2"
                         class="absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden z-[60] text-gray-800 origin-top-right">
                        
                        <div class="bg-cyan-600 px-4 py-3 text-white flex justify-between items-center">
                            <h3 class="font-bold text-sm">Aktivitas Login</h3>
                            <span class="text-[10px] bg-white/20 px-2 py-0.5 rounded-full">Terbaru</span>
                        </div>

                        <div id="notif-list" class="max-h-64 overflow-y-auto custom-scrollbar divide-y divide-gray-100">
                            <div class="p-4 text-center text-gray-400 text-xs italic">Memuat data...</div>
                        </div>

                        <div class="bg-gray-50 p-3 text-center border-t border-gray-100">
                            <button @click="showModal = true; notifOpen = false; fetchAllHistory()" 
                                    class="text-xs font-semibold text-cyan-600 hover:text-cyan-800 hover:underline transition-all">
                                Lihat Semua Aktivitas
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 md:p-8 overflow-x-hidden">

            <div x-show="showModal" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-black/50 z-[70] backdrop-blur-sm"
                 style="display: none;"></div>

            <div x-show="showModal"
                 @click.away="showModal = false"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-90 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-90 translate-y-4"
                 class="fixed inset-0 z-[80] flex items-center justify-center p-4 pointer-events-none"
                 style="display: none;">
                
                <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl flex flex-col max-h-[85vh] pointer-events-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <div>
                            <h2 class="text-lg font-bold text-gray-800">Riwayat Aktivitas Login</h2>
                            <p class="text-xs text-gray-500">Semua riwayat login pengguna sistem</p>
                        </div>
                        <button @click="showModal = false" class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors">
                            <i data-lucide="x" class="w-5 h-5 text-gray-600"></i>
                        </button>
                    </div>

                    <div class="overflow-y-auto custom-scrollbar p-0 flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">User</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Aktivitas</th>
                                    <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase text-right">Waktu</th>
                                </tr>
                            </thead>
                            <tbody id="modal-activity-list" class="divide-y divide-gray-100">
                                <tr>
                                    <td colspan="3" class="px-6 py-8 text-center text-gray-400 text-sm">
                                        <div class="flex flex-col items-center gap-2">
                                            <i data-lucide="loader-2" class="w-6 h-6 animate-spin"></i>
                                            <span>Memuat riwayat...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-gray-50 px-6 py-3 rounded-b-2xl border-t border-gray-100 flex justify-end">
                        <button @click="showModal = false" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    lucide.createIcons();
                    fetchNotif(); // Load dropdown awal
                    setInterval(fetchNotif, 15000); // Polling dropdown
                });

                let lastCount = 0;

                // --- 1. Fetch untuk Dropdown (Limit 5) ---
                function fetchNotif() {
                    $.ajax({
                        url: '../api/get_latest_login.php', 
                        method: 'GET',
                        data: { limit: 5 }, // Kirim parameter limit jika API mendukung
                        dataType: 'json',
                        success: function(response) {
                            if(response.success) {
                                let html = '';
                                if(response.data.length > 0) {
                                    // Ambil maksimal 5 data saja untuk dropdown
                                    const dropdownData = response.data.slice(0, 5); 
                                    
                                    dropdownData.forEach(user => {
                                        let timeAgo = timeSince(new Date(user.last_login));
                                        html += `
                                        <div class="flex items-start gap-3 p-3 hover:bg-cyan-50 transition-colors group cursor-pointer">
                                            <div class="flex-shrink-0">
                                                <div class="w-8 h-8 rounded-full bg-cyan-100 text-cyan-600 flex items-center justify-center font-bold text-xs uppercase border border-cyan-200">
                                                    ${user.username.substring(0,2)}
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate group-hover:text-cyan-700">
                                                    ${user.nama_lengkap || user.username}
                                                </p>
                                                <p class="text-xs text-gray-500">Login ke sistem</p>
                                                <span class="text-[10px] text-cyan-500 mt-1 inline-block bg-cyan-50 px-1.5 py-0.5 rounded border border-cyan-100">
                                                    ${timeAgo}
                                                </span>
                                            </div>
                                            <div class="h-2 w-2 rounded-full bg-green-500 mt-1.5 shadow-[0_0_5px_rgba(34,197,94,0.6)]" title="Online"></div>
                                        </div>`;
                                    });
                                } else {
                                    html = '<div class="p-8 text-center text-gray-400 text-xs flex flex-col items-center gap-2"><i data-lucide="bell-off" class="w-5 h-5 opacity-50"></i>Belum ada aktivitas.</div>';
                                }
                                $('#notif-list').html(html);
                                lucide.createIcons(); // Refresh icons if any

                                // Logic Badge
                                if(response.data.length > 0 && response.data.length !== lastCount) {
                                    $('#notif-badge').removeClass('hidden');
                                    $('#bell-icon').addClass('bell-ring');
                                    lastCount = response.data.length;
                                }
                            }
                        },
                        error: function(err) { console.log("Gagal memuat notifikasi", err); }
                    });
                }

                // --- 2. Fetch untuk Modal (Semua Data) ---
                function fetchAllHistory() {
                    // Tampilkan loading state dulu
                    $('#modal-activity-list').html(`
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400 text-sm">
                            <div class="flex flex-col items-center gap-2">
                                <span class="loading-spinner"></span> Memuat data lengkap...
                            </div>
                        </td></tr>
                    `);

                    $.ajax({
                        url: '../api/get_latest_login.php', // Gunakan endpoint yang sama atau khusus history
                        method: 'GET',
                        data: { limit: 100 }, // Minta lebih banyak data (misal 100)
                        dataType: 'json',
                        success: function(response) {
                            if(response.success && response.data.length > 0) {
                                let html = '';
                                response.data.forEach(user => {
                                    // Format tanggal lengkap untuk modal (DD MMM YYYY HH:mm)
                                    let dateObj = new Date(user.last_login);
                                    let formattedDate = dateObj.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                                    let formattedTime = dateObj.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

                                    html += `
                                    <tr class="hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                                        <td class="px-6 py-3 align-middle">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-bold text-xs uppercase">
                                                    ${user.username.substring(0,2)}
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">${user.nama_lengkap || user.username}</p>
                                                    <p class="text-xs text-gray-500">${user.role || 'User'}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 align-middle">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100">
                                                <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> Login Berhasil
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-right align-middle">
                                            <p class="text-xs font-medium text-gray-700">${formattedTime}</p>
                                            <p class="text-[10px] text-gray-400">${formattedDate}</p>
                                        </td>
                                    </tr>`;
                                });
                                $('#modal-activity-list').html(html);
                            } else {
                                $('#modal-activity-list').html('<tr><td colspan="3" class="px-6 py-8 text-center text-gray-500 text-sm">Tidak ada riwayat ditemukan.</td></tr>');
                            }
                        },
                        error: function() {
                            $('#modal-activity-list').html('<tr><td colspan="3" class="px-6 py-8 text-center text-red-500 text-sm">Gagal mengambil data history.</td></tr>');
                        }
                    });
                }

                function resetBadge() {
                    $('#notif-badge').addClass('hidden');
                    $('#bell-icon').removeClass('bell-ring');
                }

                function timeSince(date) {
                    var seconds = Math.floor((new Date() - date) / 1000);
                    var interval = seconds / 31536000;
                    if (interval > 1) return Math.floor(interval) + " thn lalu";
                    interval = seconds / 2592000;
                    if (interval > 1) return Math.floor(interval) + " bln lalu";
                    interval = seconds / 86400;
                    if (interval > 1) return Math.floor(interval) + " hr lalu";
                    interval = seconds / 3600;
                    if (interval > 1) return Math.floor(interval) + " jam lalu";
                    interval = seconds / 60;
                    if (interval > 1) return Math.floor(interval) + " mnt lalu";
                    return "Baru saja";
                }
            </script>