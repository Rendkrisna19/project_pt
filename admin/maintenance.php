<?php
session_start();
// Security: Hanya Admin
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$currentPage = 'maintenance';
include_once '../layouts/header.php';
?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="p-6 bg-slate-50 min-h-screen font-sans">
    
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-slate-800">System <span class="text-red-600">Maintenance</span></h1>
        <p class="text-slate-500 text-sm">Pusat kontrol pemeliharaan server dan keamanan data.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <div class="bg-white p-8 rounded-2xl shadow-lg border border-slate-200 hover:border-cyan-500 transition group relative overflow-hidden">
            <div class="absolute -right-10 -top-10 bg-cyan-50 w-40 h-40 rounded-full group-hover:scale-150 transition duration-500"></div>
            
            <div class="relative z-10">
                <div class="w-16 h-16 bg-cyan-100 text-cyan-600 rounded-2xl flex items-center justify-center mb-6 text-3xl shadow-sm">
                    <i class="ti ti-database-export"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">Backup Database Rutin</h2>
                <p class="text-slate-500 text-sm mb-6 leading-relaxed">
                    Download salinan lengkap database (SQL) untuk keamanan. Lakukan ini minimal seminggu sekali atau sebelum melakukan perubahan besar.
                </p>
                <form action="maintenance_action.php" method="POST">
                    <input type="hidden" name="action" value="backup_db">
                    <button type="submit" class="w-full py-3 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl font-bold shadow-lg shadow-cyan-200 transition flex items-center justify-center gap-2">
                        <i class="ti ti-download"></i> Download Database (.SQL)
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-lg border border-slate-200 hover:border-orange-500 transition group relative overflow-hidden">
            <div class="absolute -right-10 -top-10 bg-orange-50 w-40 h-40 rounded-full group-hover:scale-150 transition duration-500"></div>
            
            <div class="relative z-10">
                <div class="w-16 h-16 bg-orange-100 text-orange-600 rounded-2xl flex items-center justify-center mb-6 text-3xl shadow-sm">
                    <i class="ti ti-refresh-alert"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">Refresh System (Crash Fix)</h2>
                <p class="text-slate-500 text-sm mb-6 leading-relaxed">
                    Gunakan jika web terasa lambat, macet, atau error. Fitur ini akan membersihkan cache sistem, mengoptimalkan tabel database, dan mereset koneksi.
                </p>
                <button onclick="confirmRefresh()" class="w-full py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold shadow-lg shadow-orange-200 transition flex items-center justify-center gap-2">
                    <i class="ti ti-activity-heartbeat"></i> Refresh & Optimize
                </button>
            </div>
        </div>

    </div>

    <div class="mt-8 bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-start gap-3">
        <i class="ti ti-info-circle text-blue-600 text-xl mt-0.5"></i>
        <div>
            <h4 class="font-bold text-blue-800 text-sm">Informasi Admin</h4>
            <p class="text-xs text-blue-600 mt-1">File backup akan diberi nama format: <code>db_backup_ptpn_[TANGGAL].sql</code>. Simpan file tersebut di tempat aman (Google Drive/Harddisk External).</p>
        </div>
    </div>

</div>

<script>
function confirmRefresh() {
    Swal.fire({
        title: 'Refresh System?',
        text: "Proses ini akan mengoptimalkan database dan membersihkan cache sementara. Web mungkin reload.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f97316',
        cancelButtonColor: '#cbd5e1',
        confirmButtonText: 'Ya, Segarkan Sekarang!'
    }).then((result) => {
        if (result.isConfirmed) {
            performRefresh();
        }
    })
}

async function performRefresh() {
    // Tampilkan Loading
    Swal.fire({ title: 'Sedang Proses...', text: 'Mengoptimalkan Database...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });

    try {
        const fd = new FormData();
        fd.append('action', 'refresh_system');
        
        const res = await fetch('maintenance_action.php', { method: 'POST', body: fd }).then(r => r.json());

        if(res.success) {
            Swal.fire({
                title: 'Sistem Segar Kembali!',
                text: res.message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Hard Reload untuk clear cache browser
                window.location.reload(true);
            });
        } else {
            Swal.fire('Gagal', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Terjadi kesalahan koneksi.', 'error');
    }
}
</script>

<?php include_once '../layouts/footer.php'; ?>