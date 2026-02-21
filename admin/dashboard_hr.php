<?php
// pages/dashboard_hr.php
// FULL VERSION: HR Dashboard + Age Chart (3 Filters: Status, Afdeling, Jabatan Real)
// DESIGN: Sharp UI, Cyan Theme

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

// --- 2. ALERT PENSIUN & MBT (FILTER BULAN) ---
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t', strtotime('+1 month'));

$sqlPensiun = "SELECT nama_lengkap, jabatan_real, tmt_pensiun, foto_profil, DATEDIFF(tmt_pensiun, CURDATE()) as sisa_hari 
               FROM data_karyawan 
               WHERE tmt_pensiun BETWEEN '$monthStart' AND '$monthEnd'
               ORDER BY tmt_pensiun ASC";
$listPensiun = $conn->query($sqlPensiun)->fetchAll(PDO::FETCH_ASSOC);

$sqlMBT = "SELECT nama_lengkap, jabatan_real, tmt_mbt, foto_profil, DATEDIFF(tmt_mbt, CURDATE()) as sisa_hari 
           FROM data_karyawan 
           WHERE tmt_mbt BETWEEN '$monthStart' AND '$monthEnd'
           ORDER BY tmt_mbt ASC";
$listMBT = $conn->query($sqlMBT)->fetchAll(PDO::FETCH_ASSOC);

// --- 3. CHART DATA GENERAL ---
// A. Status Pie
$sqlChartStatus = "SELECT status_karyawan, COUNT(*) as jml FROM data_karyawan GROUP BY status_karyawan";
$stmtChartS = $conn->query($sqlChartStatus);
$labelStatus = []; $dataStatus = [];
while($r = $stmtChartS->fetch(PDO::FETCH_ASSOC)){
    $labelStatus[] = $r['status_karyawan'] ?: 'Lainnya';
    $dataStatus[] = (int)$r['jml'];
}

// B. Afdeling Bar (Total)
$sqlChartAfd = "SELECT afdeling, COUNT(*) as jml FROM data_karyawan WHERE afdeling IS NOT NULL AND afdeling != '' GROUP BY afdeling ORDER BY jml DESC";
$stmtChartA = $conn->query($sqlChartAfd);
$labelAfd = []; $dataAfd = [];
while($r = $stmtChartA->fetch(PDO::FETCH_ASSOC)){
    $labelAfd[] = $r['afdeling'];
    $dataAfd[] = (int)$r['jml'];
}

// C. Status Bar
$labelStsBar = ['KARPIM', 'TS', 'KNG', 'PKWT'];
$dataStsBar  = [$cntKarpim, $cntTS, $cntKNG, $cntPKWT];

// D. Stacked Bar
$sqlUnikAfd = "SELECT DISTINCT afdeling FROM data_karyawan WHERE afdeling IS NOT NULL AND afdeling != '' ORDER BY afdeling ASC";
$stmtUnikAfd = $conn->query($sqlUnikAfd);
$allAfdeling = $stmtUnikAfd->fetchAll(PDO::FETCH_COLUMN);

$seriesKarpim = []; $seriesTS = []; $seriesKNG = []; $seriesPKWT = [];
foreach($allAfdeling as $afd) {
    $seriesKarpim[] = (int)$conn->query("SELECT COUNT(*) FROM data_karyawan WHERE afdeling = '$afd' AND status_karyawan = 'KARPIM'")->fetchColumn();
    $seriesTS[]     = (int)$conn->query("SELECT COUNT(*) FROM data_karyawan WHERE afdeling = '$afd' AND status_karyawan = 'TS'")->fetchColumn();
    $seriesKNG[]    = (int)$conn->query("SELECT COUNT(*) FROM data_karyawan WHERE afdeling = '$afd' AND status_karyawan = 'KNG'")->fetchColumn();
    $seriesPKWT[]   = (int)$conn->query("SELECT COUNT(*) FROM data_karyawan WHERE afdeling = '$afd' AND status_karyawan = 'PKWT'")->fetchColumn();
}

// --- 5. DATA UMUR (3 FILTERS: STATUS, AFDELING, JABATAN REAL) ---

// 5a. Ambil List Options
// Afdeling
$sqlListAfd = "SELECT DISTINCT afdeling FROM data_karyawan WHERE afdeling IS NOT NULL AND afdeling != '' ORDER BY afdeling ASC";
$listAfdelingOptions = $conn->query($sqlListAfd)->fetchAll(PDO::FETCH_COLUMN);

// Jabatan Real
$sqlListJab = "SELECT DISTINCT jabatan_real FROM data_karyawan WHERE jabatan_real IS NOT NULL AND jabatan_real != '' ORDER BY jabatan_real ASC";
$listJabatanOptions = $conn->query($sqlListJab)->fetchAll(PDO::FETCH_COLUMN);

// 5b. Tangkap Parameter Filter
$filterAgeStatus   = isset($_GET['age_status']) ? $_GET['age_status'] : 'ALL';
$filterAgeAfdeling = isset($_GET['age_afdeling']) ? $_GET['age_afdeling'] : 'ALL';
$filterAgeJabatan  = isset($_GET['age_jabatan']) ? $_GET['age_jabatan'] : 'ALL';

// Validasi Status
$validStatus = ['ALL', 'KARPIM', 'TS', 'KNG', 'PKWT'];
if (!in_array($filterAgeStatus, $validStatus)) $filterAgeStatus = 'ALL';

// 5c. Build Query Umur
$sqlAgeBase = "SELECT TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) as age 
               FROM data_karyawan 
               WHERE tanggal_lahir > '1900-01-01'";

// Filter 1: Status
if ($filterAgeStatus !== 'ALL') {
    $sqlAgeBase .= " AND status_karyawan = '$filterAgeStatus'";
}
// Filter 2: Afdeling
if ($filterAgeAfdeling !== 'ALL') {
    $safeAfd = addslashes($filterAgeAfdeling);
    $sqlAgeBase .= " AND afdeling = '$safeAfd'";
}
// Filter 3: Jabatan Real
if ($filterAgeJabatan !== 'ALL') {
    $safeJab = addslashes($filterAgeJabatan);
    $sqlAgeBase .= " AND jabatan_real = '$safeJab'";
}

$stmtAge = $conn->query($sqlAgeBase);
$ageUnder40 = 0;
$age40to50 = 0;
$ageAbove50 = 0;

while($r = $stmtAge->fetch(PDO::FETCH_ASSOC)) {
    $age = (int)$r['age'];
    if ($age < 40) {
        $ageUnder40++;
    } elseif ($age >= 40 && $age <= 50) {
        $age40to50++;
    } else {
        $ageAbove50++;
    }
}
$dataAgeChart = [$ageUnder40, $age40to50, $ageAbove50];


// --- 4. ANALISA TENAGA KERJA (SAP vs REAL) ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; 
$offset = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(DISTINCT jabatan_sap) FROM data_karyawan WHERE jabatan_sap IS NOT NULL AND jabatan_sap != ''";
$totalRows = $conn->query($sqlCount)->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$sqlGap = "SELECT 
            jabatan_sap as jabatan, 
            COUNT(*) as jml_sap,
            (SELECT COUNT(*) FROM data_karyawan k2 WHERE k2.jabatan_real = data_karyawan.jabatan_sap) as jml_real
           FROM data_karyawan 
           WHERE jabatan_sap IS NOT NULL AND jabatan_sap != ''
           GROUP BY jabatan_sap 
           ORDER BY jml_sap DESC
           LIMIT $limit OFFSET $offset";

$rekapGap = $conn->query($sqlGap)->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'dashboard_hr';
include_once '../layouts/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>

<style>
    /* DESIGN SYSTEM: SHARP / SQUARE */
    :root { --primary-cyan: #0e7490; --light-cyan: #ecfeff; }
    .rounded-none { border-radius: 0px !important; }
    .rounded-sm, .rounded, .rounded-md, .rounded-lg, .rounded-xl, .rounded-2xl, .rounded-full { border-radius: 0px !important; }

    .card-sharp {
        background: white; border: 1px solid #e2e8f0; border-radius: 0;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: all 0.2s ease;
    }
    .card-sharp:hover { border-color: var(--primary-cyan); box-shadow: 0 4px 6px -1px rgba(14, 116, 144, 0.1); }

    .custom-scroll::-webkit-scrollbar { width: 6px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 0; }
    .custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .custom-scroll { max-height: 400px; overflow-y: auto; }

    .avatar-sharp { width: 42px; height: 42px; object-fit: cover; border-radius: 0; border: 1px solid #e2e8f0; }
    .table-sharp th { background-color: #f8fafc; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; border-radius: 0; }
    .table-sharp tr:hover td { background-color: #ecfeff; }

    /* Pagination Button Style */
    .btn-page {
        padding: 5px 12px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; font-size: 0.8rem;
        text-decoration: none; transition: all 0.2s;
    }
    .btn-page:hover { background: #f1f5f9; color: #0e7490; border-color: #cbd5e1; }
    .btn-page.active { background: #0e7490; color: #fff; border-color: #0e7490; }
    .btn-page.disabled { pointer-events: none; opacity: 0.5; }
</style>

<div class="space-y-6 pb-10 font-sans text-slate-800">

    <div class="flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4 bg-white p-6 card-sharp border-l-4 border-l-cyan-700">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight uppercase">Dashboard SDM</h1>
            <div class="flex items-center gap-2 mt-1 text-sm text-slate-500">
                <i class="ti ti-calendar"></i> Periode: <span class="font-semibold text-cyan-700"><?= date('F Y') ?></span>
            </div>
        </div>
        
        <div class="flex items-center gap-4">
             <a href="./cetak/dashboard_hr_excel.php" target="_blank" 
               class="bg-cyan-600 hover:bg-cyan-700 text-white px-5 py-3 font-bold text-sm flex items-center gap-2 transition shadow-md"
               style="border-radius: 8px !important; font-family: 'Poppins', sans-serif;">
                <i class="ti ti-file-spreadsheet text-xl"></i> Export Rekap Excel
            </a>

            <div class="bg-cyan-50 border border-cyan-200 px-6 py-3 flex flex-col items-end justify-center rounded-none">
                <span class="text-xs font-bold text-cyan-600 uppercase tracking-wider">Total Aset SDM</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-3xl font-extrabold text-cyan-800"><?= number_format($totalKaryawan) ?></span>
                    <span class="text-xs text-cyan-600">Personil</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card-sharp p-5 border-l-4 border-l-blue-600">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Pimpinan</p>
                    <h2 class="text-3xl font-extrabold text-slate-800"><?= number_format($cntKarpim) ?></h2>
                </div>
                <div class="p-2 bg-blue-50 text-blue-600 rounded-none border border-blue-100"><i class="ti ti-tie text-2xl"></i></div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Status:</span><span class="font-bold text-blue-700 bg-blue-50 px-2 py-0.5 border border-blue-200">KARPIM</span>
            </div>
        </div>

        <div class="card-sharp p-5 border-l-4 border-l-emerald-600">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">TS</p>
                    <h2 class="text-3xl font-extrabold text-slate-800"><?= number_format($cntTS) ?></h2>
                </div>
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-none border border-emerald-100"><i class="ti ti-user-check text-2xl"></i></div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Status:</span><span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 border border-emerald-200">TS</span>
            </div>
        </div>

        <div class="card-sharp p-5 border-l-4 border-l-amber-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">KNG</p>
                    <h2 class="text-3xl font-extrabold text-slate-800"><?= number_format($cntKNG) ?></h2>
                </div>
                <div class="p-2 bg-amber-50 text-amber-600 rounded-none border border-amber-100"><i class="ti ti-briefcase text-2xl"></i></div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Status:</span><span class="font-bold text-amber-700 bg-amber-50 px-2 py-0.5 border border-amber-200">KNG</span>
            </div>
        </div>

        <div class="card-sharp p-5 border-l-4 border-l-purple-600">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Kontrak</p>
                    <h2 class="text-3xl font-extrabold text-slate-800"><?= number_format($cntPKWT) ?></h2>
                </div>
                <div class="p-2 bg-purple-50 text-purple-600 rounded-none border border-purple-100"><i class="ti ti-clock-hour-4 text-2xl"></i></div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Status:</span><span class="font-bold text-purple-700 bg-purple-50 px-2 py-0.5 border border-purple-200">PKWT</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
            
            <div class="card-sharp p-6">
                <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-2">
                    <h3 class="font-bold text-slate-800 text-sm uppercase flex items-center gap-2">
                        <span class="w-2 h-2 bg-cyan-600 inline-block"></span> Distribusi Afdeling (Total)
                    </h3>
                </div>
                <div id="chartAfdeling" style="min-height: 320px;"></div>
            </div>

            <div class="card-sharp p-6 border-l-4 border-l-orange-500">
                <div class="flex justify-between items-center mb-6 border-b border-slate-100 pb-2">
                    <h3 class="font-bold text-slate-800 text-sm uppercase flex items-center gap-2">
                        <span class="w-2 h-2 bg-orange-500 inline-block"></span> Detail Status per Afdeling
                    </h3>
                </div>
                <div id="chartStackedAfd" style="min-height: 400px;"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="card-sharp p-6">
                    <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-2">
                        <h3 class="font-bold text-slate-800 text-sm uppercase flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-600 inline-block"></span> Status (Global)
                        </h3>
                    </div>
                    <div id="chartStatusBar" style="min-height: 250px;"></div>
                </div>

                <div class="card-sharp p-6">
                    <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-2">
                        <h3 class="font-bold text-slate-800 text-sm uppercase flex items-center gap-2">
                            <span class="w-2 h-2 bg-green-500 inline-block"></span> Komposisi
                        </h3>
                    </div>
                    <div id="chartStatusPie" class="flex justify-center"></div>
                </div>
            </div>

            <div class="card-sharp p-6 border-l-4 border-l-pink-600">
                <div class="mb-4 border-b border-slate-100 pb-3">
                    <h3 class="font-bold text-slate-800 text-sm uppercase flex items-center gap-2 mb-4">
                        <span class="w-2 h-2 bg-pink-600 inline-block"></span> Distribusi Umur Karyawan
                    </h3>
                    
                    <form action="" method="GET" class="bg-slate-50 p-4 border border-slate-200">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                    <i class="ti ti-filter"></i> Status Pekerjaan
                                </label>
                                <select name="age_status" onchange="this.form.submit()" class="w-full text-xs border border-slate-300 p-2 focus:outline-none bg-white font-semibold text-slate-700 cursor-pointer shadow-sm">
                                    <option value="ALL" <?= $filterAgeStatus === 'ALL' ? 'selected' : '' ?>>-- SEMUA STATUS --</option>
                                    <option value="KARPIM" <?= $filterAgeStatus === 'KARPIM' ? 'selected' : '' ?>>KARPIM</option>
                                    <option value="TS" <?= $filterAgeStatus === 'TS' ? 'selected' : '' ?>>TS</option>
                                    <option value="KNG" <?= $filterAgeStatus === 'KNG' ? 'selected' : '' ?>>KNG</option>
                                    <option value="PKWT" <?= $filterAgeStatus === 'PKWT' ? 'selected' : '' ?>>PKWT</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                    <i class="ti ti-map-pin"></i> Afdeling / Unit
                                </label>
                                <select name="age_afdeling" onchange="this.form.submit()" class="w-full text-xs border border-slate-300 p-2 focus:outline-none bg-white font-semibold text-slate-700 cursor-pointer shadow-sm">
                                    <option value="ALL" <?= $filterAgeAfdeling === 'ALL' ? 'selected' : '' ?>>-- SEMUA AFDELING --</option>
                                    <?php foreach($listAfdelingOptions as $afd): ?>
                                        <option value="<?= $afd ?>" <?= $filterAgeAfdeling === $afd ? 'selected' : '' ?>><?= $afd ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                    <i class="ti ti-id-badge-2"></i> Jabatan Real
                                </label>
                                <select name="age_jabatan" onchange="this.form.submit()" class="w-full text-xs border border-slate-300 p-2 focus:outline-none bg-white font-semibold text-slate-700 cursor-pointer shadow-sm">
                                    <option value="ALL" <?= $filterAgeJabatan === 'ALL' ? 'selected' : '' ?>>-- SEMUA JABATAN --</option>
                                    <?php foreach($listJabatanOptions as $jab): ?>
                                        <option value="<?= $jab ?>" <?= $filterAgeJabatan === $jab ? 'selected' : '' ?>><?= $jab ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                        </div>
                    </form>
                </div>
                
                <div id="chartAge" class="flex justify-center" style="min-height: 300px;"></div>
                
                <div class="text-center mt-3 text-[10px] text-slate-500">
                    <span class="bg-gray-100 px-2 py-1 border rounded-sm">Status: <b><?= $filterAgeStatus ?></b></span>
                    <span class="bg-gray-100 px-2 py-1 border rounded-sm ml-1">Afd: <b><?= $filterAgeAfdeling ?></b></span>
                    <span class="bg-gray-100 px-2 py-1 border rounded-sm ml-1">Jabatan: <b><?= $filterAgeJabatan ?></b></span>
                </div>
            </div>

        </div>

        <div class="space-y-6">
            
            <div class="card-sharp border-t-4 border-t-red-600">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 uppercase">
                            <i class="ti ti-user-minus text-red-600"></i> Pensiun
                        </h3>
                        <p class="text-[10px] text-slate-500 mt-0.5">Filter: Bulan Berjalan + 1 Bulan</p>
                    </div>
                    <a href="data_karyawan.php?view=pension" class="text-xs font-bold text-white bg-red-600 px-2 py-1 hover:bg-red-700 transition rounded-none">Lihat</a>
                </div>
                <div class="custom-scroll">
                    <?php if (count($listPensiun) > 0): ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($listPensiun as $p): 
                                $foto = $p['foto_profil'] ? '../uploads/profil/'.$p['foto_profil'] : '../assets/img/default-avatar.png';
                            ?>
                            <div class="p-4 hover:bg-red-50/50 transition flex items-center gap-3 group">
                                <img src="<?= $foto ?>" class="avatar-sharp">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-bold text-slate-800 truncate group-hover:text-red-700 transition"><?= htmlspecialchars($p['nama_lengkap']) ?></h4>
                                    <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($p['jabatan_real']) ?></p>
                                </div>
                                <div class="text-right">
                                    <div class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 border border-red-200">
                                        <?= date('d M', strtotime($p['tmt_pensiun'])) ?>
                                    </div>
                                    <span class="block text-[10px] text-slate-400 mt-1"><?= $p['sisa_hari'] ?> hari lg</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center border-b border-slate-100">
                            <i class="ti ti-mood-smile text-3xl text-slate-300 mb-2 block"></i>
                            <span class="text-xs text-slate-400">Aman, tidak ada pensiun.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-sharp border-t-4 border-t-orange-500">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2 uppercase">
                            <i class="ti ti-calendar-time text-orange-600"></i> MBT Habis
                        </h3>
                        <p class="text-[10px] text-slate-500 mt-0.5">Filter: Bulan Berjalan + 1 Bulan</p>
                    </div>
                    <a href="data_karyawan.php?tab=mbt" class="text-xs font-bold text-white bg-orange-600 px-2 py-1 hover:bg-orange-700 transition rounded-none">Lihat</a>
                </div>
                <div class="custom-scroll">
                    <?php if (count($listMBT) > 0): ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($listMBT as $m): 
                                $fotoM = $m['foto_profil'] ? '../uploads/profil/'.$m['foto_profil'] : '../assets/img/default-avatar.png';
                            ?>
                            <div class="p-4 hover:bg-orange-50/50 transition flex items-center gap-3 group">
                                <img src="<?= $fotoM ?>" class="avatar-sharp">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-bold text-slate-800 truncate group-hover:text-orange-700 transition"><?= htmlspecialchars($m['nama_lengkap']) ?></h4>
                                    <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($m['jabatan_real']) ?></p>
                                </div>
                                <div class="text-right">
                                    <div class="bg-orange-100 text-orange-700 text-[10px] font-bold px-2 py-0.5 border border-orange-200">
                                        <?= date('d M', strtotime($m['tmt_mbt'])) ?>
                                    </div>
                                    <span class="block text-[10px] text-slate-400 mt-1"><?= $m['sisa_hari'] ?> hari lg</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center border-b border-slate-100">
                            <i class="ti ti-check text-3xl text-slate-300 mb-2 block"></i>
                            <span class="text-xs text-slate-400">Semua MBT aman.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div class="card-sharp mt-6" id="table-analisa">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <h3 class="font-bold text-cyan-800 uppercase text-sm tracking-wide">
                <i class="ti ti-analyze mr-1"></i> Analisa Tenaga Kerja (SAP vs Real)
            </h3>
            <span class="text-[10px] font-bold text-slate-500 border border-slate-300 bg-white px-2 py-1 uppercase">Selisih (+/-)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-slate-600 table-sharp">
                <thead class="text-slate-700 bg-slate-100 sticky top-0 z-10">
                    <tr>
                        <th class="px-6 py-3 font-bold border-b-2 border-slate-300">Uraian Jabatan (SAP)</th>
                        <th class="px-6 py-3 text-center border-b-2 border-slate-300 w-32">Jml SAP</th>
                        <th class="px-6 py-3 text-center border-b-2 border-slate-300 w-32">Jml Real</th>
                        <th class="px-6 py-3 text-center border-b-2 border-slate-300 w-32">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($rekapGap)): ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400 italic">Belum ada data jabatan SAP.</td></tr>
                    <?php else: ?>
                        <?php foreach($rekapGap as $rg): 
                            $jml_sap = (int)$rg['jml_sap'];
                            $jml_real = (int)$rg['jml_real']; 
                            $selisih = $jml_real - $jml_sap;
                            
                            $textColor = $selisih < 0 ? 'text-red-600' : ($selisih > 0 ? 'text-emerald-600' : 'text-slate-400');
                            $bgColor = $selisih < 0 ? 'bg-red-50' : ($selisih > 0 ? 'bg-emerald-50' : 'bg-white');
                            $sign = $selisih > 0 ? '+' : '';
                        ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-3 font-medium text-slate-800 border-r border-slate-100">
                                <?= htmlspecialchars($rg['jabatan']) ?>
                            </td>
                            <td class="px-6 py-3 text-center border-r border-slate-100 bg-slate-50/50 font-mono">
                                <?= $jml_sap ?>
                            </td>
                            <td class="px-6 py-3 text-center border-r border-slate-100 font-mono font-semibold text-slate-700">
                                <?= $jml_real ?>
                            </td>
                            <td class="px-6 py-3 text-center font-mono <?= $bgColor ?> <?= $textColor ?> font-bold">
                                <?= $selisih == 0 ? '-' : $sign . $selisih ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-center gap-1">
            <?php 
                $prev = $page - 1;
                $next = $page + 1;
                
                echo '<a href="?page='.$prev.'#table-analisa" class="btn-page '.($page <= 1 ? 'disabled' : '').'"><i class="ti ti-chevron-left"></i></a>';
                
                for($i = 1; $i <= $totalPages; $i++) {
                    if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)) {
                        $active = $i == $page ? 'active' : '';
                        echo '<a href="?page='.$i.'#table-analisa" class="btn-page '.$active.'">'.$i.'</a>';
                    } elseif ($i == $page - 3 || $i == $page + 3) {
                         echo '<span class="btn-page disabled">...</span>';
                    }
                }
                
                echo '<a href="?page='.$next.'#table-analisa" class="btn-page '.($page >= $totalPages ? 'disabled' : '').'"><i class="ti ti-chevron-right"></i></a>';
            ?>
        </div>
        <?php endif; ?>
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

// Data Stacked Bar (All Afdelings)
const allAfdeling = <?= json_encode($allAfdeling) ?>;
const seriesKarpim = <?= json_encode($seriesKarpim) ?>;
const seriesTS     = <?= json_encode($seriesTS) ?>;
const seriesKNG    = <?= json_encode($seriesKNG) ?>;
const seriesPKWT   = <?= json_encode($seriesPKWT) ?>;

// Data Umur (New)
const ageSeries = <?= json_encode($dataAgeChart) ?>;
const ageLabels = ['< 40 Tahun', '40 - 50 Tahun', '> 50 Tahun'];

const sharpFont = 'inherit';

// 1. Chart Afdeling (Total)
var optAfd = {
    series: [{ name: 'Karyawan', data: afdSeries }],
    chart: { type: 'bar', height: 350, toolbar: { show: false }, fontFamily: sharpFont },
    plotOptions: { bar: { borderRadius: 0, horizontal: false, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    stroke: { show: true, width: 0, colors: ['transparent'] },
    xaxis: { 
        categories: afdLabels, 
        labels: { rotate: -45, style: { fontSize: '10px', colors: '#64748b' } },
        axisBorder: { show: true, color: '#cbd5e1' },
    },
    fill: { opacity: 1, colors: ['#0891b2'] },
    grid: { borderColor: '#f1f5f9' }
};
new ApexCharts(document.querySelector("#chartAfdeling"), optAfd).render();

// 2. Chart Stacked (Status per Afdeling)
var optStacked = {
    series: [
        { name: 'KARPIM', data: seriesKarpim },
        { name: 'TS', data: seriesTS },
        { name: 'KNG', data: seriesKNG },
        { name: 'PKWT', data: seriesPKWT }
    ],
    chart: { type: 'bar', height: 400, stacked: true, toolbar: { show: true }, fontFamily: sharpFont },
    plotOptions: { bar: { borderRadius: 0, horizontal: true, dataLabels: { position: 'center' } } }, 
    xaxis: { 
        categories: allAfdeling,
        labels: { style: { colors: '#64748b' } },
    },
    colors: ['#2563eb', '#059669', '#d97706', '#9333ea'], 
    fill: { opacity: 1 },
    legend: { position: 'top', horizontalAlign: 'left' },
    grid: { borderColor: '#f1f5f9' },
    dataLabels: { enabled: true, style: { fontSize: '10px' } } 
};
new ApexCharts(document.querySelector("#chartStackedAfd"), optStacked).render();

// 3. Chart Status (Global Bar)
var optStsBar = {
    series: [{ name: 'Jumlah', data: stsSeries }],
    chart: { type: 'bar', height: 250, toolbar: { show: false }, fontFamily: sharpFont },
    plotOptions: { bar: { borderRadius: 0, horizontal: true, barHeight: '60%' } },
    dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'], fontSize: '11px', fontWeight: 'bold' }, offsetX: 0 },
    xaxis: { 
        categories: stsLabels,
        labels: { style: { colors: '#64748b' } },
    },
    colors: ['#2563eb', '#059669', '#d97706', '#9333ea'], 
    grid: { borderColor: '#f1f5f9' }
};
new ApexCharts(document.querySelector("#chartStatusBar"), optStsBar).render();

// 4. Chart Pie Global
var optPie = {
    series: pieSeries,
    labels: pieLabels,
    chart: { type: 'donut', height: 320, fontFamily: sharpFont },
    colors: ['#0e7490', '#10b981', '#f59e0b', '#6366f1', '#64748b'],
    dataLabels: { enabled: false },
    legend: { position: 'bottom', markers: { radius: 0 } },
    plotOptions: { pie: { donut: { size: '65%' } } }
};
new ApexCharts(document.querySelector("#chartStatusPie"), optPie).render();

// 5. CHART UMUR (NEW)
var optAge = {
    series: ageSeries,
    labels: ageLabels,
    chart: { type: 'pie', height: 320, fontFamily: sharpFont },
    colors: ['#14b8a6', '#f97316', '#be123c'], // Teal, Orange, Rose
    dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 'bold' } },
    legend: { position: 'bottom', markers: { radius: 0 } },
    plotOptions: { pie: { expandOnClick: true } },
    tooltip: {
        y: {
            formatter: function(val) {
                return val + " Orang"
            }
        }
    }
};
new ApexCharts(document.querySelector("#chartAge"), optAge).render();

</script>

<?php include_once '../layouts/footer.php'; ?>