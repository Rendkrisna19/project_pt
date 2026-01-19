<?php
// pages/dashboard_hr.php
// MODIFIKASI FULL: Modern HR Dashboard, Charts, Profile Modal, Pension Alert

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// --- DATA DUMMY SESSION (Jika belum ada di session login) ---
// Sesuaikan dengan session user Anda sebenarnya
$userNama  = $_SESSION['user_nama'] ?? 'Administrator';
$userRole  = $_SESSION['user_role'] ?? 'admin';
$userFoto  = $_SESSION['user_foto'] ?? '../assets/img/default-avatar.png'; // Path foto default

// --- 1. DATA RINGKASAN (COUNT) ---
$totalKaryawan = $conn->query("SELECT COUNT(*) FROM data_karyawan")->fetchColumn();
$totalTetap    = $conn->query("SELECT COUNT(*) FROM data_karyawan WHERE status_karyawan = 'Tetap'")->fetchColumn();
$totalKontrak  = $conn->query("SELECT COUNT(*) FROM data_karyawan WHERE status_karyawan = 'Kontrak'")->fetchColumn();
$totalPensiun  = $conn->query("SELECT COUNT(*) FROM data_karyawan WHERE tmt_pensiun IS NOT NULL AND tmt_pensiun <= DATE_ADD(CURDATE(), INTERVAL 1 YEAR) AND tmt_pensiun > CURDATE()")->fetchColumn();

// --- 2. DATA CHART 1: Status Karyawan (Pie Chart) ---
$sqlStatus = "SELECT status_karyawan, COUNT(*) as jumlah FROM data_karyawan GROUP BY status_karyawan";
$stmtStatus = $conn->query($sqlStatus);
$chartStatusLabels = [];
$chartStatusSeries = [];
while($row = $stmtStatus->fetch(PDO::FETCH_ASSOC)){
    $chartStatusLabels[] = $row['status_karyawan'] ? $row['status_karyawan'] : 'Tidak Ada Status';
    $chartStatusSeries[] = (int)$row['jumlah'];
}

// --- 3. DATA CHART 2: Afdeling/Unit (Bar Chart) ---
$sqlAfd = "SELECT afdeling, COUNT(*) as jumlah FROM data_karyawan GROUP BY afdeling ORDER BY jumlah DESC LIMIT 5";
$stmtAfd = $conn->query($sqlAfd);
$chartAfdLabels = [];
$chartAfdSeries = [];
while($row = $stmtAfd->fetch(PDO::FETCH_ASSOC)){
    $chartAfdLabels[] = $row['afdeling'] ? $row['afdeling'] : 'Non-Unit';
    $chartAfdSeries[] = (int)$row['jumlah'];
}

// --- 4. LIST PENSIUN (Alert) ---
$sqlPensiun = "SELECT nama_lengkap, jabatan_real, tmt_pensiun, DATEDIFF(tmt_pensiun, CURDATE()) as sisa_hari 
               FROM data_karyawan 
               WHERE tmt_pensiun IS NOT NULL 
               AND tmt_pensiun > CURDATE() 
               AND tmt_pensiun <= DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
               ORDER BY tmt_pensiun ASC LIMIT 5";
$listPensiun = $conn->query($sqlPensiun)->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'dashboard_hr';
include_once '../layouts/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
    .card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    .glass-effect { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    .avatar-xl { width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
</style>

<div class="space-y-6">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Dashboard HRD</h1>
            <p class="text-slate-500 mt-1">Selamat datang kembali, pantau performa SDM Anda hari ini.</p>
        </div>
        
        
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-5 text-white shadow-lg card-hover transition duration-300 relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-10 transform translate-x-2 -translate-y-2">
                <i class="ti ti-users text-8xl"></i>
            </div>
            <p class="text-blue-100 text-sm font-medium mb-1">Total Karyawan</p>
            <h2 class="text-4xl font-bold"><?= number_format($totalKaryawan) ?></h2>
            <div class="mt-4 text-xs text-blue-200 bg-blue-700/50 inline-block px-2 py-1 rounded">
                <i class="ti ti-database"></i> Database Aktif
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm card-hover transition duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-500 text-sm font-medium mb-1">Karyawan Tetap</p>
                    <h2 class="text-3xl font-bold text-slate-800"><?= number_format($totalTetap) ?></h2>
                </div>
                <div class="p-2 bg-green-100 text-green-600 rounded-lg"><i class="ti ti-user-check text-xl"></i></div>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-4">
                <div class="bg-green-500 h-1.5 rounded-full" style="width: <?= ($totalKaryawan>0 ? ($totalTetap/$totalKaryawan)*100 : 0) ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm card-hover transition duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-500 text-sm font-medium mb-1">Kontrak / HL</p>
                    <h2 class="text-3xl font-bold text-slate-800"><?= number_format($totalKontrak) ?></h2>
                </div>
                <div class="p-2 bg-yellow-100 text-yellow-600 rounded-lg"><i class="ti ti-clock text-xl"></i></div>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-4">
                <div class="bg-yellow-500 h-1.5 rounded-full" style="width: <?= ($totalKaryawan>0 ? ($totalKontrak/$totalKaryawan)*100 : 0) ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-red-100 shadow-sm card-hover transition duration-300 relative overflow-hidden">
            <div class="absolute right-0 top-0 w-16 h-16 bg-red-500 rotate-45 transform translate-x-8 -translate-y-8"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-slate-500 text-sm font-medium mb-1">Jelang Pensiun</p>
                    <h2 class="text-3xl font-bold text-red-600"><?= number_format($totalPensiun) ?></h2>
                </div>
                <div class="p-2 bg-red-50 text-red-600 rounded-lg"><i class="ti ti-alert-triangle text-xl"></i></div>
            </div>
            <p class="text-xs text-red-400 mt-4 font-medium">Dalam 1 tahun ke depan</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
            
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-slate-800">Distribusi Karyawan per Bagian</h3>
                    <button class="text-slate-400 hover:text-blue-600"><i class="ti ti-dots-vertical"></i></button>
                </div>
                <div id="chartAfdeling"></div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-800 mb-6">Komposisi Status Pegawai</h3>
                <div id="chartStatus" class="flex justify-center"></div>
            </div>

        </div>

        <div class="space-y-6">
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-red-50 px-5 py-4 border-b border-red-100 flex items-center justify-between">
                    <h3 class="font-bold text-red-800 flex items-center gap-2">
                        <i class="ti ti-calendar-time"></i> Masa Pensiun
                    </h3>
                    <span class="text-xs font-bold bg-white text-red-600 px-2 py-1 rounded-md shadow-sm"><?= count($listPensiun) ?> Org</span>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php if (count($listPensiun) > 0): ?>
                        <?php foreach($listPensiun as $p): ?>
                        <div class="p-4 hover:bg-slate-50 transition flex items-center gap-3">
                            <div class="bg-red-100 text-red-600 w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs">
                                <?= $p['sisa_hari'] ?>h
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($p['nama_lengkap']) ?></h4>
                                <p class="text-xs text-slate-500"><?= htmlspecialchars($p['jabatan_real']) ?></p>
                            </div>
                            <span class="text-xs font-mono text-slate-400"><?= date('d/m/y', strtotime($p['tmt_pensiun'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-slate-400 text-sm">Tidak ada data pensiun dekat.</div>
                    <?php endif; ?>
                </div>
                <a href="data_karyawan.php" class="block bg-slate-50 text-center py-3 text-xs font-bold text-slate-600 hover:text-blue-600 hover:bg-slate-100 transition">Lihat Seluruh Data &rarr;</a>
            </div>

            <div class="bg-gradient-to-br from-cyan-500 to-blue-600 rounded-2xl p-5 text-white shadow-md">
                <h3 class="font-bold mb-4">Aksi Cepat</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="data_karyawan.php" class="bg-white/20 hover:bg-white/30 p-3 rounded-xl flex flex-col items-center justify-center gap-2 transition backdrop-blur-sm cursor-pointer border border-white/10">
                        <i class="ti ti-user-plus text-2xl"></i>
                        <span class="text-xs font-medium">Input Karyawan</span>
                    </a>
                    <a href="laporan_mingguan.php" class="bg-white/20 hover:bg-white/30 p-3 rounded-xl flex flex-col items-center justify-center gap-2 transition backdrop-blur-sm cursor-pointer border border-white/10">
                        <i class="ti ti-file-text text-2xl"></i>
                        <span class="text-xs font-medium">Cek Arsip</span>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="profile-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-[99] hidden items-center justify-center p-4 transition-opacity duration-300">
    <div class="bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300" id="profile-content">
        <div class="h-32 bg-gradient-to-r from-cyan-500 to-blue-600 relative">
            <button onclick="closeProfileModal()" class="absolute top-4 right-4 text-white/80 hover:text-white bg-black/20 hover:bg-black/40 rounded-full p-1 transition">
                <i class="ti ti-x text-xl"></i>
            </button>
        </div>
        
        <div class="px-6 pb-6 text-center relative">
            <div class="relative -mt-16 mb-4">
                <img src="<?= $userFoto ?>" class="avatar-xl mx-auto bg-white">
                <div class="absolute bottom-0 right-1/2 translate-x-8 translate-y-1 bg-green-500 w-4 h-4 rounded-full border-2 border-white"></div>
            </div>

            <h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($userNama) ?></h2>
            <p class="text-sm text-slate-500 font-medium uppercase tracking-wide mb-6"><?= htmlspecialchars($userRole) ?></p>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <p class="text-xs text-slate-400">Status</p>
                    <p class="font-bold text-green-600 flex items-center justify-center gap-1"><i class="ti ti-circle-check-filled"></i> Online</p>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <p class="text-xs text-slate-400">Akses</p>
                    <p class="font-bold text-slate-700"><?= ucfirst($userRole) ?></p>
                </div>
            </div>

            <div class="space-y-3">
                <a href="#" class="block w-full py-2.5 rounded-xl bg-slate-800 text-white font-medium hover:bg-slate-900 transition shadow-lg shadow-slate-200">Edit Profil Saya</a>
                <a href="../auth/logout.php" class="block w-full py-2.5 rounded-xl border border-red-100 text-red-600 font-medium hover:bg-red-50 transition">Log Out</a>
            </div>
        </div>
    </div>
</div>

<script>
// --- CHART DATA FROM PHP ---
const statusData = {
    series: <?= json_encode($chartStatusSeries) ?>,
    labels: <?= json_encode($chartStatusLabels) ?>
};
const afdData = {
    series: [{ name: 'Jumlah', data: <?= json_encode($chartAfdSeries) ?> }],
    labels: <?= json_encode($chartAfdLabels) ?>
};

// --- CHART 1: PIE STATUS ---
var optionsStatus = {
    series: statusData.series,
    labels: statusData.labels,
    chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#6366f1'],
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: { show: true, label: 'Total', fontSize: '14px', fontWeight: 600, color: '#64748b' }
                }
            }
        }
    },
    dataLabels: { enabled: false },
    legend: { position: 'bottom' },
    stroke: { show: false }
};
var chartStatus = new ApexCharts(document.querySelector("#chartStatus"), optionsStatus);
chartStatus.render();

// --- CHART 2: BAR AFDELING ---
var optionsAfd = {
    series: afdData.series,
    chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: {
        bar: { borderRadius: 6, horizontal: false, columnWidth: '50%' }
    },
    dataLabels: { enabled: false },
    stroke: { show: true, width: 2, colors: ['transparent'] },
    xaxis: {
        categories: afdData.labels,
        axisBorder: { show: false },
        axisTicks: { show: false }
    },
    yaxis: { title: { text: 'Jumlah Orang' } },
    fill: { opacity: 1, colors: ['#06b6d4'] },
    tooltip: {
        y: { formatter: function (val) { return val + " Orang" } }
    },
    grid: { borderColor: '#f1f5f9' }
};
var chartAfd = new ApexCharts(document.querySelector("#chartAfdeling"), optionsAfd);
chartAfd.render();

// --- MODAL LOGIC ---
const profileModal = document.getElementById('profile-modal');
const profileContent = document.getElementById('profile-content');

function openProfileModal() {
    profileModal.classList.remove('hidden');
    profileModal.classList.add('flex');
    setTimeout(() => {
        profileContent.classList.remove('scale-95');
        profileContent.classList.add('scale-100');
    }, 10);
}

function closeProfileModal() {
    profileContent.classList.remove('scale-100');
    profileContent.classList.add('scale-95');
    setTimeout(() => {
        profileModal.classList.add('hidden');
        profileModal.classList.remove('flex');  
    }, 200); // Wait for transition
}

// Close on outside click
profileModal.addEventListener('click', (e) => {
    if (e.target === profileModal) closeProfileModal();
});
</script>

<?php include_once '../layouts/footer.php'; ?>