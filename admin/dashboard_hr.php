<?php
// pages/dashboard_hr.php
// FULL VERSION: HR Dashboard, Pension/MBT Month Filter, Job Gap Analysis

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// --- USER SESSION ---
$userNama  = $_SESSION['user_nama'] ?? 'Administrator';
$userRole  = $_SESSION['user_role'] ?? 'admin';
$userFoto  = $_SESSION['user_foto'] ?? '../assets/img/default-avatar.png';

// --- 1. RINGKASAN STATUS KARYAWAN ---
$totalKaryawan = $conn->query("SELECT COUNT(*) FROM data_karyawan")->fetchColumn();

function countStatus($conn, $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM data_karyawan WHERE status_karyawan = ?");
    $stmt->execute([$status]);
    return $stmt->fetchColumn();
}

$cntKarpim = countStatus($conn, 'KARPIM');
$cntTS     = countStatus($conn, 'TS');
$cntKNG    = countStatus($conn, 'KNG');
$cntPKWT   = countStatus($conn, 'PKWT');

// --- 2. ALERT PENSIUN & MBT (FILTER BULAN BERJALAN + 1 BULAN) ---
// Default: Bulan ini sampai bulan depan
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t', strtotime('+1 month'));

// Pensiun
$sqlPensiun = "SELECT nama_lengkap, jabatan_real, tmt_pensiun, foto_profil, DATEDIFF(tmt_pensiun, CURDATE()) as sisa_hari 
               FROM data_karyawan 
               WHERE tmt_pensiun BETWEEN '$monthStart' AND '$monthEnd'
               ORDER BY tmt_pensiun ASC";
$listPensiun = $conn->query($sqlPensiun)->fetchAll(PDO::FETCH_ASSOC);

// MBT
$sqlMBT = "SELECT nama_lengkap, jabatan_real, tmt_mbt, foto_profil, DATEDIFF(tmt_mbt, CURDATE()) as sisa_hari 
           FROM data_karyawan 
           WHERE tmt_mbt BETWEEN '$monthStart' AND '$monthEnd'
           ORDER BY tmt_mbt ASC";
$listMBT = $conn->query($sqlMBT)->fetchAll(PDO::FETCH_ASSOC);

// --- 3. CHART DATA ---
// Status Pie
$sqlChartStatus = "SELECT status_karyawan, COUNT(*) as jml FROM data_karyawan GROUP BY status_karyawan";
$stmtChartS = $conn->query($sqlChartStatus);
$labelStatus = []; $dataStatus = [];
while($r = $stmtChartS->fetch(PDO::FETCH_ASSOC)){
    $labelStatus[] = $r['status_karyawan'] ?: 'Lainnya';
    $dataStatus[] = (int)$r['jml'];
}

// Afdeling Bar
$sqlChartAfd = "SELECT afdeling, COUNT(*) as jml FROM data_karyawan WHERE afdeling IS NOT NULL AND afdeling != '' GROUP BY afdeling ORDER BY jml DESC";
$stmtChartA = $conn->query($sqlChartAfd);
$labelAfd = []; $dataAfd = [];
while($r = $stmtChartA->fetch(PDO::FETCH_ASSOC)){
    $labelAfd[] = $r['afdeling'];
    $dataAfd[] = (int)$r['jml'];
}

// Status Bar (Khusus KARPIM, TS, KNG, PKWT)
$labelStsBar = ['KARPIM', 'TS', 'KNG', 'PKWT'];
$dataStsBar  = [$cntKarpim, $cntTS, $cntKNG, $cntPKWT];

// --- 4. REKAP JABATAN & SELISIH (GAP ANALYSIS) ---
// Logic: Hitung jumlah orang per Jabatan SAP dan Jabatan Real
// Note: Ini simulasi karena struktur DB biasanya 1 row per orang.
// Kita grouping by Jabatan SAP untuk melihat berapa Real yang mengisi.
$sqlGap = "SELECT 
            IFNULL(jabatan_sap, 'Tidak Terdefinisi') as jabatan, 
            COUNT(jabatan_sap) as jml_sap,
            COUNT(jabatan_real) as jml_real,
            (COUNT(jabatan_real) - COUNT(jabatan_sap)) as selisih
           FROM data_karyawan 
           GROUP BY jabatan_sap 
           ORDER BY jml_real DESC";
$rekapGap = $conn->query($sqlGap)->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'dashboard_hr';
include_once '../layouts/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
    .card-hover:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(6, 182, 212, 0.15); border-color: #22d3ee; }
    .glass-header { background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%); }
    .avatar-sm { width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .custom-scroll { max-height: 380px; overflow-y: auto; }
</style>

<div class="space-y-6 pb-10">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Dashboard SDM</h1>
            <p class="text-slate-500 text-sm mt-1">Periode: <?= date('F Y') ?></p>
        </div>
        <div class="bg-cyan-50 text-cyan-700 px-4 py-2 rounded-lg font-bold text-sm border border-cyan-100">
            Total Aset SDM: <span class="text-lg"><?= number_format($totalKaryawan) ?></span> Orang
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm card-hover transition duration-300">
            <div class="flex justify-between items-center mb-4">
                <div class="p-2 bg-blue-100 text-blue-600 rounded-lg"><i class="ti ti-tie text-xl"></i></div>
                <span class="text-xs font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded">Pimpinan</span>
            </div>
            <h2 class="text-3xl font-bold text-slate-800"><?= number_format($cntKarpim) ?></h2>
            <p class="text-sm text-slate-500 font-medium">KARPIM</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm card-hover transition duration-300">
            <div class="flex justify-between items-center mb-4">
                <div class="p-2 bg-green-100 text-green-600 rounded-lg"><i class="ti ti-user-check text-xl"></i></div>
                <span class="text-xs font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded">TS</span>
            </div>
            <h2 class="text-3xl font-bold text-slate-800"><?= number_format($cntTS) ?></h2>
            <p class="text-sm text-slate-500 font-medium">TS</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm card-hover transition duration-300">
            <div class="flex justify-between items-center mb-4">
                <div class="p-2 bg-yellow-100 text-yellow-600 rounded-lg"><i class="ti ti-briefcase text-xl"></i></div>
                <span class="text-xs font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded">KNG</span>
            </div>
            <h2 class="text-3xl font-bold text-slate-800"><?= number_format($cntKNG) ?></h2>
            <p class="text-sm text-slate-500 font-medium">KNG</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm card-hover transition duration-300">
            <div class="flex justify-between items-center mb-4">
                <div class="p-2 bg-purple-100 text-purple-600 rounded-lg"><i class="ti ti-clock-hour-4 text-xl"></i></div>
                <span class="text-xs font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded">PKWT</span>
            </div>
            <h2 class="text-3xl font-bold text-slate-800"><?= number_format($cntPKWT) ?></h2>
            <p class="text-sm text-slate-500 font-medium">PKWT </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
            
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="ti ti-building-factory text-cyan-600"></i> Distribusi per Afdeling</h3>
                <div id="chartAfdeling" style="min-height: 320px;"></div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="ti ti-chart-bar text-cyan-600"></i> Detail Status Karyawan</h3>
                <div id="chartStatusBar" style="min-height: 300px;"></div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="ti ti-chart-pie text-cyan-600"></i> Komposisi Keseluruhan</h3>
                <div id="chartStatusPie" class="flex justify-center"></div>
            </div>
        </div>

        <div class="space-y-6">
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-red-50 px-5 py-3 border-b border-red-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-red-800 text-sm flex items-center gap-2"><i class="ti ti-user-minus"></i> Pensiun (Bulan Ini)</h3>
                        <p class="text-[10px] text-red-600 mt-0.5">Periode: <?= date('M Y') ?></p>
                    </div>
                    <a href="data_karyawan.php?tab=pension" class="text-xs text-red-600 hover:underline">Detail</a>
                </div>
                <div class="divide-y divide-slate-100 custom-scroll">
                    <?php if (count($listPensiun) > 0): ?>
                        <?php foreach($listPensiun as $p): 
                            $foto = $p['foto_profil'] ? '../uploads/profil/'.$p['foto_profil'] : '../assets/img/default-avatar.png';
                        ?>
                        <div class="p-3 hover:bg-slate-50 transition flex items-center gap-3">
                            <img src="<?= $foto ?>" class="avatar-sm">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($p['nama_lengkap']) ?></h4>
                                <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($p['jabatan_real']) ?></p>
                            </div>
                            <div class="text-right">
                                <span class="block text-xs font-bold text-red-600"><?= date('d M', strtotime($p['tmt_pensiun'])) ?></span>
                                <span class="block text-[10px] text-slate-400"><?= $p['sisa_hari'] ?> hari lg</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-6 text-center text-slate-400 text-xs italic">Tidak ada pensiun bulan ini.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-orange-50 px-5 py-3 border-b border-orange-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-orange-800 text-sm flex items-center gap-2"><i class="ti ti-calendar-time"></i> MBT Habis (Bulan Ini)</h3>
                        <p class="text-[10px] text-orange-600 mt-0.5">Periode: <?= date('M Y') ?></p>
                    </div>
                    <a href="data_karyawan.php?tab=mbt" class="text-xs text-orange-600 hover:underline">Detail</a>
                </div>
                <div class="divide-y divide-slate-100 custom-scroll">
                    <?php if (count($listMBT) > 0): ?>
                        <?php foreach($listMBT as $m): 
                            $fotoM = $m['foto_profil'] ? '../uploads/profil/'.$m['foto_profil'] : '../assets/img/default-avatar.png';
                        ?>
                        <div class="p-3 hover:bg-slate-50 transition flex items-center gap-3">
                            <img src="<?= $fotoM ?>" class="avatar-sm">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($m['nama_lengkap']) ?></h4>
                                <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($m['jabatan_real']) ?></p>
                            </div>
                            <div class="text-right">
                                <span class="block text-xs font-bold text-orange-600"><?= date('d M', strtotime($m['tmt_mbt'])) ?></span>
                                <span class="block text-[10px] text-slate-400"><?= $m['sisa_hari'] ?> hari lg</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-6 text-center text-slate-400 text-xs italic">Tidak ada MBT habis bulan ini.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mt-6">
        <div class="px-6 py-4 border-b border-slate-100 bg-cyan-50 flex justify-between items-center">
            <h3 class="font-bold text-cyan-800">Analisa Tenaga Kerja (SAP vs Real)</h3>
            <span class="text-xs text-cyan-600 bg-cyan-100 px-2 py-1 rounded">Selisih (+/-)</span>
        </div>
        <div class="overflow-x-auto max-h-[500px]">
            <table class="w-full text-sm text-left text-slate-600">
                <thead class="text-xs text-slate-700 uppercase bg-slate-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-6 py-3 border-b border-slate-200">Uraian Jabatan</th>
                        <th class="px-6 py-3 border-b border-slate-200 text-center">Jumlah SAP</th>
                        <th class="px-6 py-3 border-b border-slate-200 text-center">Jumlah Real</th>
                        <th class="px-6 py-3 border-b border-slate-200 text-center">Selisih (+/-)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($rekapGap as $rg): 
                        $selisih = $rg['jml_real'] - $rg['jml_sap'];
                        $colorClass = $selisih < 0 ? 'text-red-600' : ($selisih > 0 ? 'text-green-600' : 'text-slate-400');
                        $sign = $selisih > 0 ? '+' : '';
                    ?>
                    <tr class="hover:bg-cyan-50/30 transition">
                        <td class="px-6 py-3 font-medium text-slate-800"><?= $rg['jabatan'] ?></td>
                        <td class="px-6 py-3 text-center bg-slate-50"><?= $rg['jml_sap'] ?></td>
                        <td class="px-6 py-3 text-center font-bold"><?= $rg['jml_real'] ?></td>
                        <td class="px-6 py-3 text-center font-bold <?= $colorClass ?>">
                            <?= $sign . $selisih ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// Data
const afdLabels = <?= json_encode($labelAfd) ?>;
const afdSeries = <?= json_encode($dataAfd) ?>;
const stsLabels = <?= json_encode($labelStsBar) ?>;
const stsSeries = <?= json_encode($dataStsBar) ?>;
const pieLabels = <?= json_encode($labelStatus) ?>;
const pieSeries = <?= json_encode($dataStatus) ?>;

// 1. Chart Afdeling (Bar)
var optAfd = {
    series: [{ name: 'Karyawan', data: afdSeries }],
    chart: { type: 'bar', height: 350, toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { borderRadius: 4, horizontal: false, columnWidth: '55%' } },
    dataLabels: { enabled: false },
    stroke: { show: true, width: 2, colors: ['transparent'] },
    xaxis: { categories: afdLabels, labels: { rotate: -45, style: { fontSize: '10px' } } },
    fill: { opacity: 1, colors: ['#06b6d4'] },
    grid: { borderColor: '#f1f5f9' },
    tooltip: { theme: 'light' }
};
new ApexCharts(document.querySelector("#chartAfdeling"), optAfd).render();

// 2. Chart Status (Bar Breakdown)
var optStsBar = {
    series: [{ name: 'Jumlah', data: stsSeries }],
    chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '50%' } },
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, offsetX: 0 },
    xaxis: { categories: stsLabels },
    colors: ['#3b82f6', '#22c55e', '#eab308', '#a855f7'],
    grid: { borderColor: '#f1f5f9' }
};
new ApexCharts(document.querySelector("#chartStatusBar"), optStsBar).render();

// 3. Chart Pie Global
var optPie = {
    series: pieSeries,
    labels: pieLabels,
    chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
    colors: ['#0891b2', '#10b981', '#f59e0b', '#6366f1', '#64748b'],
    dataLabels: { enabled: false },
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '70%' } } }
};
new ApexCharts(document.querySelector("#chartStatusPie"), optPie).render();
</script>

<?php include_once '../layouts/footer.php'; ?>