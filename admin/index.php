<?php
session_start();

// SET TIMEZONE INDONESIA (WIB)
date_default_timezone_set('Asia/Jakarta');

// 1. Cek Sesi & Security
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

try {
    $db  = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

// ==========================================================================
// BACKEND AJAX HANDLER
// ==========================================================================
if (isset($_POST['ajax']) && $_POST['ajax'] === 'dashboard') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => false, 'message' => '', 'debug' => []];

    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF Token']);
        exit;
    }

    $section  = trim($_POST['section'] ?? '');
    $afdeling = trim($_POST['afdeling'] ?? '');
    $bulan    = trim($_POST['bulan'] ?? '');
    $tahun    = trim($_POST['tahun'] ?? date('Y'));
    $kebun    = trim($_POST['kebun'] ?? ''); 

    $response['debug']['request_params'] = $_POST;
    $bulanSQL = "ELT(MONTH(tanggal), 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";

    try {
        // --- 1. DATA PEMELIHARAAN (KPI & PROGRESS BAR) ---
        if ($section === 'pemeliharaan_data') {
            $tables = ['TM'=>'pemeliharaan_tm','TBM 1'=>'pemeliharaan_tbm1','TBM 2'=>'pemeliharaan_tbm2','TBM 3'=>'pemeliharaan_tbm3','TK'=>'pemeliharaan_tk','PN'=>'pemeliharaan_pn','MN'=>'pemeliharaan_mn'];
            $mapBulan = ['Januari'=>'jan','Februari'=>'feb','Maret'=>'mar','April'=>'apr','Mei'=>'mei','Juni'=>'jun','Juli'=>'jul','Agustus'=>'agu','September'=>'sep','Oktober'=>'okt','November'=>'nov','Desember'=>'des'];

            if (!empty($bulan) && isset($mapBulan[$bulan])) {
                $colRealisasi = "COALESCE(" . $mapBulan[$bulan] . ", 0)";
            } else {
                $colRealisasi = "(COALESCE(jan,0)+COALESCE(feb,0)+COALESCE(mar,0)+COALESCE(apr,0)+COALESCE(mei,0)+COALESCE(jun,0)+COALESCE(jul,0)+COALESCE(agu,0)+COALESCE(sep,0)+COALESCE(okt,0)+COALESCE(nov,0)+COALESCE(des,0))";
            }

            $kebunId = null;
            if (!empty($kebun)) {
                $stmtK = $pdo->prepare("SELECT id FROM md_kebun WHERE nama_kebun = ? LIMIT 1");
                $stmtK->execute([$kebun]);
                $kebunId = $stmtK->fetchColumn();
            }

            $kpi = [];
            foreach ($tables as $label => $tableName) {
                $conditions = ["1=1"];
                if (!empty($tahun)) $conditions[] = "tahun = " . intval($tahun);
                if (!empty($afdeling)) $conditions[] = "unit_kode = " . $pdo->quote($afdeling);
                if (!empty($kebun)) {
                    if ($kebunId) $conditions[] = "kebun_id = " . intval($kebunId);
                    else $conditions[] = "1=0"; 
                }
                $whereStr = implode(" AND ", $conditions);

                $sqlSum = "SELECT COALESCE(SUM(anggaran_tahun), 0) as rcn, COALESCE(SUM($colRealisasi), 0) as realis FROM $tableName WHERE $whereStr";
                $stmt = $pdo->query($sqlSum);
                $rencana = 0; $realisasi = 0;
                if ($stmt) {
                    $rowSum = $stmt->fetch(PDO::FETCH_ASSOC);
                    $rencana = floatval($rowSum['rcn'] ?? 0);
                    $realisasi = floatval($rowSum['realis'] ?? 0);
                }
                $persen = ($rencana > 0) ? round(($realisasi / $rencana) * 100, 1) : 0;
                $color = ($persen >= 100) ? 'text-cyan-600' : (($persen >= 80) ? 'text-blue-600' : 'text-red-500');

                $kpi[] = ['label' => $label, 'rencana' => $rencana, 'realisasi' => $realisasi, 'persen' => $persen, 'color_class' => $color];
            }

            $response['success'] = true;
            $response['kpi'] = $kpi;
            $response['rows'] = []; 
            echo json_encode($response);
            exit;
        }

        // --- 2. DATA PRODUKSI ---
        if ($section === 'produksi_data') {
            $params = [];
            $conditions = ["1=1"];

            if (!empty($afdeling)) { 
                $conditions[] = "u.nama_unit = :afdeling"; 
                $params[':afdeling'] = $afdeling; 
            }
            if (!empty($tahun)) { 
                $conditions[] = "lm.tahun = :tahun"; 
                $params[':tahun'] = $tahun; 
            }
            
            if (!empty($kebun)) {
                $stmtK = $pdo->prepare("SELECT id FROM md_kebun WHERE nama_kebun = ? LIMIT 1");
                $stmtK->execute([$kebun]);
                $kebunId = $stmtK->fetchColumn();
                
                if ($kebunId) {
                    $conditions[] = "lm.kebun_id = :kid";
                    $params[':kid'] = $kebunId;
                } else {
                    $conditions[] = "1=0";
                }
            }
            
            $whereChart = implode(" AND ", $conditions);
            
            $sqlChart = "SELECT lm.bulan, SUM(COALESCE(lm.anggaran_kg,0)) as rkap, SUM(COALESCE(lm.realisasi_kg,0)) as `real`
                         FROM lm76 lm 
                         LEFT JOIN units u ON u.id = lm.unit_id
                         WHERE $whereChart
                         GROUP BY lm.bulan 
                         ORDER BY FIELD(lm.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";
            
            $stmt = $pdo->prepare($sqlChart);
            $stmt->execute($params);
            $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($bulan)) { 
                $conditions[] = "lm.bulan = :bulan"; 
                $params[':bulan'] = $bulan; 
            }
            $whereTable = implode(" AND ", $conditions);

            $sqlTable = "SELECT 
                            lm.tahun, 
                            lm.bulan, 
                            u.nama_unit,
                            lm.tt,              
                            lm.luas_ha,         
                            lm.jumlah_pohon,
                            (CASE WHEN lm.jumlah_pohon > 0 THEN lm.jumlah_tandan / lm.jumlah_pohon ELSE 0 END) as tdn_pkk,
                            (CASE WHEN lm.luas_ha > 0 THEN (lm.realisasi_kg / lm.luas_ha) / 1000 ELSE 0 END) as protas,
                            (CASE WHEN lm.jumlah_hk > 0 THEN lm.realisasi_kg / lm.jumlah_hk ELSE 0 END) as kg_hk
                         FROM lm76 lm 
                         LEFT JOIN units u ON u.id = lm.unit_id
                         WHERE $whereTable
                         ORDER BY lm.tahun DESC, FIELD(lm.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') DESC
                         LIMIT 500";
            
            $stmtTable = $pdo->prepare($sqlTable);
            $stmtTable->execute($params);
            $tableData = $stmtTable->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['chart_data'] = $chartData;
            $response['table_data'] = $tableData;
            echo json_encode($response);
            exit;
        }

        // --- 3. DATA GUDANG & ALAT ---
        if ($section === 'gudang_ops') {
            $params = [];
            $conditions = ["1=1"];

            if (!empty($bulan)) { $conditions[] = "sg.bulan = :bulan"; $params[':bulan'] = $bulan; }
            if (!empty($tahun)) { $conditions[] = "sg.tahun = :tahun"; $params[':tahun'] = $tahun; }
            if (!empty($kebun)) { $conditions[] = "mk.nama_kebun = :kebun"; $params[':kebun'] = $kebun; }

            $whereGudang = implode(" AND ", $conditions);
            $sqlG = "SELECT mk.nama_kebun, b.nama_bahan, s.nama AS satuan, sg.bulan, sg.stok_awal, sg.mutasi_masuk, sg.mutasi_keluar, 
                    (sg.stok_awal + sg.mutasi_masuk + COALESCE(sg.pasokan,0) - sg.mutasi_keluar - COALESCE(sg.dipakai,0)) as stok_akhir
                    FROM stok_gudang sg 
                    JOIN md_bahan_kimia b ON b.id = sg.bahan_id 
                    LEFT JOIN md_satuan s ON s.id = b.satuan_id 
                    JOIN md_kebun mk ON mk.id = sg.kebun_id
                    WHERE $whereGudang ORDER BY mk.nama_kebun, b.nama_bahan";
            
            $stmt = $pdo->prepare($sqlG);
            $stmt->execute($params);
            $dataGudang = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stats = ['stok_gudang' => 0, 'stok_kimia' => 0, 'stok_alat' => 0];
            foreach ($dataGudang as $row) {
                $nama = strtolower($row['nama_bahan']);
                $stok = floatval($row['stok_akhir']);
                $stats['stok_gudang'] += $stok;
                if (preg_match('/(egrek|dodos|angkong|gancu|cangkul|parang|batu asah)/i', $nama)) {
                    $stats['stok_alat'] += $stok;
                } elseif (!preg_match('/(solar|bensin|dexlite|pertalite|pertamax|oli|pelumas)/i', $nama)) {
                    $stats['stok_kimia'] += $stok;
                }
            }

            $paramsAP = []; $condAP = ["1=1"];
            if (!empty($bulan)) { $condAP[] = "ap.bulan = :b"; $paramsAP[':b'] = $bulan; }
            if (!empty($tahun)) { $condAP[] = "ap.tahun = :t"; $paramsAP[':t'] = $tahun; }
            if (!empty($kebun)) { $condAP[] = "k.nama_kebun = :k"; $paramsAP[':k'] = $kebun; }
            
            $whereAP = implode(" AND ", $condAP);
            
            $sqlAlat = "SELECT COALESCE(SUM(ap.stok_akhir), 0) 
                        FROM alat_panen ap 
                        LEFT JOIN md_kebun k ON k.id = ap.kebun_id 
                        WHERE $whereAP";
            
            $stmtAlat = $pdo->prepare($sqlAlat);
            $stmtAlat->execute($paramsAP);
            $totalAlatPanen = floatval($stmtAlat->fetchColumn());

            $stats['stok_alat'] += $totalAlatPanen;

            $paramsPakai = [];
            $condPakai = ["1=1"];
            if (!empty($tahun)) { 
                $condPakai[] = "YEAR(tanggal) = :tahun"; 
                $paramsPakai[':tahun'] = $tahun; 
            }
            if (!empty($bulan)) { 
                $condPakai[] = "$bulanSQL = :bulan"; 
                $paramsPakai[':bulan'] = $bulan; 
            }
            $wherePakai = implode(" AND ", $condPakai);
            $sqlBBM = "SELECT COALESCE(SUM(jumlah), 0) FROM tr_pemakaian_barang_gudang WHERE $wherePakai";
            $stmtBBM = $pdo->prepare($sqlBBM);
            $stmtBBM->execute($paramsPakai);
            $pakaiBBM = floatval($stmtBBM->fetchColumn());

            $paramsP = []; $condP = ["1=1"];
            if (!empty($tahun)) { $condP[] = "YEAR(tanggal) = :t"; $paramsP[':t'] = $tahun; }
            if (!empty($bulan)) { $condP[] = "$bulanSQL = :b"; $paramsP[':b'] = $bulan; }
            $whereP = implode(" AND ", $condP);

            $s1 = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM menabur_pupuk WHERE $whereP"); $s1->execute($paramsP);
            $s2 = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM menabur_pupuk_organik WHERE $whereP"); $s2->execute($paramsP);
            $s3 = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM angkutan_pupuk WHERE jenis_pupuk NOT LIKE '%Organik%' AND $whereP"); $s3->execute($paramsP);
            $s4 = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM angkutan_pupuk WHERE jenis_pupuk LIKE '%Organik%' AND $whereP"); $s4->execute($paramsP);

            $response['success'] = true;
            $response['gudang_rows'] = $dataGudang;
            $response['stock_stats'] = $stats;
            $response['usage_stats'] = ['bbm' => $pakaiBBM, 'kimia' => 0];
            $response['pupuk_stats'] = [
                'tabur_kimia' => floatval($s1->fetchColumn()), 'tabur_org' => floatval($s2->fetchColumn()),
                'angkut_kimia' => floatval($s3->fetchColumn()), 'angkut_org' => floatval($s4->fetchColumn())
            ];
            echo json_encode($response);
            exit;
        }
        throw new Exception("Unknown Section");
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        $response['debug']['exception'] = $e->getTraceAsString();
        echo json_encode($response);
        exit;
    }
}

// ----------------------------------------------------------------------
// FRONTEND VIEW
// ----------------------------------------------------------------------
$opsiAfdeling = $pdo->query("SELECT nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_COLUMN);
$opsiKebun    = $pdo->query("SELECT nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_COLUMN);
$bulanList    = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$currentMonth = date('n') - 1; 
$currentYear  = date('Y');
$pageTitle = 'Dashboard Monitoring';

// LOGIKA SAPAAN WAKTU (PHP - Server Side, Backup jika JS lambat)
$hour = date('H');
if ($hour >= 3 && $hour < 11) {
    $sapaan = "Selamat Pagi";
} elseif ($hour >= 11 && $hour < 15) {
    $sapaan = "Selamat Siang";
} elseif ($hour >= 15 && $hour < 19) {
    $sapaan = "Selamat Sore";
} else {
    $sapaan = "Selamat Malam";
}

include_once '../layouts/header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
    :root { --primary: #0891b2; --primary-hover: #0e7490; --bg-body: #f8fafc; }
    body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; }
    .filter-bar { background: white; border-radius: 12px; padding: 16px 24px; box-shadow: 0 4px 15px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; margin-bottom: 24px; position: sticky; top: 10px; z-index: 50; }
    .filter-item { flex: 1; min-width: 140px; }
    .filter-item label { font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block; }
    .filter-select { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font-size: 0.9rem; color: #334155; outline: none; background-color: #f8fafc; transition: all 0.2s; }
    .filter-select:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(3,98,115,0.1); }
    .filter-btn { background: var(--primary); color: white; border-radius: 8px; padding: 0 24px; height: 42px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .filter-btn:hover { background: var(--primary-hover); transform: translateY(-1px); }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 24px; }
    .mini-card { background: white; border-radius: 12px; padding: 16px; border: 1px solid #f1f5f9; text-align: center; }
    .mini-lbl { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
    .mini-val { font-size: 1.5rem; font-weight: 800; color: #1e293b; margin: 4px 0; }
    .mini-sub { font-size: 0.7rem; color: #64748b; }
    .middle-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
    @media (max-width: 1024px) { .middle-grid { grid-template-columns: 1fr; } }
    .chart-panel { background: white; border-radius: 16px; padding: 24px; border: 1px solid #eef2f6; height: 400px; }
    .stat-card { background: white; border-radius: 16px; padding: 20px; border-left: 5px solid var(--primary); margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .stat-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.9rem; }
    .stock-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stock-card { background: white; border-radius: 12px; padding: 20px; border-top: 4px solid #cbd5e1; min-height: 120px; display: flex; flex-direction: column; justify-content: space-between; position: relative; }
    .stock-card h3 { font-size: 1.6rem; font-weight: 800; color: #1e293b; }
    .table-wrapper { background: white; border-radius: 16px; border: 1px solid #eef2f6; overflow: hidden; }
    .nav-tabs { display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .nav-btn { padding: 14px 24px; font-size: 0.9rem; font-weight: 600; color: #64748b; background: transparent; border: none; border-bottom: 3px solid transparent; cursor: pointer; }
    .nav-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: white; }
    .modern-table { width: 100%; border-collapse: collapse; }
    .modern-table th { background: #f8fafc; color: #475569; padding: 12px 16px; font-size: 0.75rem; text-transform: uppercase; text-align: left; font-weight: 700; }
    .modern-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; color: #334155; }
    .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 9999; display: none; align-items: center; justify-content: center; }
    .loading-overlay.active { display: flex; }
    .spinner { border: 4px solid #f3f4f6; border-top: 4px solid var(--primary); border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }

    .welcome-banner { 
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); 
        border-radius: 16px; padding: 24px 32px; color: white; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 25px -5px rgba(6, 182, 212, 0.3); position: relative; overflow: hidden; 
    }
    .welcome-banner::before { content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; }
    .weather-widget { background: rgba(255,255,255,0.2); backdrop-filter: blur(5px); padding: 10px 20px; border-radius: 12px; display: flex; align-items: center; gap: 12px; border: 1px solid rgba(255,255,255,0.3); }
    .quick-action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .qa-card { background: white; border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; cursor: pointer; text-decoration: none; position: relative; overflow: hidden; }
    .qa-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); border-color: var(--primary); }
    .qa-card:hover .qa-icon { background: var(--primary); color: white; transform: scale(1.1); }
    .qa-icon { width: 48px; height: 48px; border-radius: 12px; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; transition: all 0.3s ease; }
    .qa-title { font-size: 0.85rem; font-weight: 600; color: #334155; }
    .qa-desc { font-size: 0.7rem; color: #94a3b8; margin-top: 4px; }
</style>

<div class="loading-overlay" id="loading"><div class="spinner"></div></div>

<div class="pb-12 px-4 md:px-8 mt-4 relative z-0">

    <div class="welcome-banner">
        <div class="relative z-10">
            <h2 class="text-2xl font-bold mb-1">
                <span id="sapaan-waktu"><?= $sapaan ?></span>, <?= htmlspecialchars($_SESSION['user_nama'] ?? 'User') ?>!
            </h2>
            <p class="text-cyan-50 text-sm font-medium">
                <?= date('l, d F Y') ?> &bull; <span class="uppercase tracking-wider opacity-80"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Staff') ?></span>
            </p>
        </div>
        <div class="weather-widget hidden md:flex relative z-10">
            <i id="weather-icon" data-lucide="cloud" class="w-8 h-8 text-yellow-300"></i>
            <div class="text-right">
                <div id="weather-temp" class="text-xl font-bold">--°C</div>
                <div id="weather-desc" class="text-[10px] uppercase tracking-wide opacity-90">Memuat...</div>
            </div>
        </div>
    </div>

    <div class="filter-bar">
        <div class="filter-item">
            <label>Tahun</label>
            <select id="f-tahun" class="filter-select">
                <?php 
                $tahunSaatIni = date('Y');
                for($y = $tahunSaatIni; $y >= $tahunSaatIni - 3; $y--): 
                ?>
                    <option value="<?= $y ?>" <?= $y == $tahunSaatIni ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Bulan</label>
            <select id="f-bulan" class="filter-select">
                <option value="">Semua Bulan (YTD)</option>
                <?php foreach($bulanList as $idx => $b): ?>
                    <option value="<?= $b ?>" <?= $idx == $currentMonth ? 'selected' : '' ?>><?= $b ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item" style="flex: 1.5;">
            <label>Unit Kebun</label>
            <select id="f-kebun" class="filter-select">
                <option value="">Semua Kebun</option>
                <?php foreach($opsiKebun as $k): ?><option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item" style="flex: 1.5;">
            <label>Afdeling</label>
            <select id="f-afdeling" class="filter-select">
                <option value="">Semua Afdeling</option>
                <?php foreach($opsiAfdeling as $a): ?><option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
            </select>
        </div>
        <button id="btn-refresh" class="filter-btn"><i data-lucide="filter" class="w-4 h-4"></i> Terapkan Filter</button>
    </div>

    <div class="mb-2 text-xs font-bold text-slate-500 uppercase tracking-widest flex items-center gap-2">
        <i data-lucide="zap" class="w-3 h-3 text-amber-500"></i> Aksi Cepat
    </div>
    
    <div class="quick-action-grid">
        <?php if(($_SESSION['user_role'] ?? '') !== 'viewer'): ?>
            <a href="lm77.php" class="qa-card group">
                <div class="qa-icon text-cyan-600 bg-cyan-50 group-hover:bg-cyan-600 group-hover:text-white"><i data-lucide="sprout" class="w-6 h-6"></i></div>
                <div class="qa-title">Input Panen</div>
                <div class="qa-desc">Catat produksi harian</div>
            </a>
            <a href="pemeliharaan_tm.php" class="qa-card group">
                <div class="qa-icon text-emerald-600 bg-emerald-50 group-hover:bg-emerald-600 group-hover:text-white"><i data-lucide="axe" class="w-6 h-6"></i></div>
                <div class="qa-title">Rawat TM</div>
                <div class="qa-desc">Realisasi pemeliharaan</div>
            </a>
            <a href="pemakaian.php" class="qa-card group">
                <div class="qa-icon text-pink-600 bg-pink-50 group-hover:bg-pink-600 group-hover:text-white"><i data-lucide="clipboard-list" class="w-6 h-6"></i></div>
                <div class="qa-title">Pakai Bahan</div>
                <div class="qa-desc">Catat penggunaan kimia</div>
            </a>
            <a href="stok_barang_gudang.php" class="qa-card group">
                <div class="qa-icon text-amber-600 bg-amber-50 group-hover:bg-amber-600 group-hover:text-white"><i data-lucide="package-plus" class="w-6 h-6"></i></div>
                <div class="qa-title">Stok Gudang</div>
                <div class="qa-desc">Cek ketersediaan barang</div>
            </a>
        <?php else: ?>
            <a href="lm76.php" class="qa-card group">
                <div class="qa-icon text-indigo-600 bg-indigo-50 group-hover:bg-indigo-600 group-hover:text-white"><i data-lucide="file-bar-chart" class="w-6 h-6"></i></div>
                <div class="qa-title">Laporan LM76</div>
                <div class="qa-desc">Lihat rekap produksi</div>
            </a>
             <a href="laporan_mingguan.php" class="qa-card group">
                <div class="qa-icon text-blue-600 bg-blue-50 group-hover:bg-blue-600 group-hover:text-white"><i data-lucide="folder-search" class="w-6 h-6"></i></div>
                <div class="qa-title">Arsip Laporan</div>
                <div class="qa-desc">Cari data lama</div>
            </a>
        <?php endif; ?>
    </div>

    <div>
        <div class="mb-2 text-xs font-bold text-slate-500 uppercase"><i data-lucide="bar-chart-3" class="w-3 h-3"></i> Persentase Realisasi Pemeliharaan</div>
        <div id="kpi-container" class="kpi-grid"></div>

        <div class="middle-grid">
            <div class="chart-panel">
                <div class="flex justify-between items-center mb-4"><h3 class="font-bold text-slate-700">Tren Produksi (Ton)</h3></div>
                <div style="height: 300px; position: relative;"><canvas id="chart-produksi"></canvas></div>
            </div>
            <div>
                <div class="stat-card" style="border-color: #3b82f6;">
                    <div class="font-bold mb-2 text-slate-600">PUPUK KIMIA (KG)</div>
                    <div class="stat-row"><span>Menabur</span> <strong id="val-tabur-kimia" class="text-blue-600">0</strong></div>
                    <div class="stat-row"><span>Angkutan</span> <strong id="val-angkut-kimia">0</strong></div>
                </div>
                <div class="stat-card" style="border-color: #22c55e;">
                    <div class="font-bold mb-2 text-slate-600">PUPUK ORGANIK (KG)</div>
                    <div class="stat-row"><span>Menabur</span> <strong id="val-tabur-org" class="text-green-600">0</strong></div>
                    <div class="stat-row"><span>Angkutan</span> <strong id="val-angkut-org">0</strong></div>
                </div>
            </div>
        </div>

        <div class="stock-grid">
            <div class="stock-card" style="border-top-color: #64748b;">
                <h4>Stok Gudang</h4><h3 id="stok-gudang">0</h3><i data-lucide="package" class="absolute top-4 right-4 text-slate-300"></i>
            </div>
            <div class="stock-card" style="border-top-color: #0ea5e9;">
                <h4>Stok Kimia</h4><h3 id="stok-kimia">0</h3><i data-lucide="flask-conical" class="absolute top-4 right-4 text-sky-200"></i>
            </div>
            <div class="stock-card" style="border-top-color: #f59e0b;">
                <h4>Stok Alat</h4><h3 id="stok-alat">0</h3><i data-lucide="wrench" class="absolute top-4 right-4 text-amber-200"></i>
            </div>
            <div class="stock-card" style="border-top-color: #ef4444;">
                <h4>Pakai BBM (L)</h4><h3 id="pakai-bbm">0</h3><i data-lucide="fuel" class="absolute top-4 right-4 text-red-200"></i>
            </div>
            <div class="stock-card" style="border-top-color: #8b5cf6;">
                <h4>Pakai Kimia (Kg)</h4><h3 id="pakai-kimia">0</h3><i data-lucide="droplets" class="absolute top-4 right-4 text-violet-200"></i>
            </div>
        </div>

        <div class="table-wrapper">
            <div class="nav-tabs">
                <button onclick="App.switchTab('produksi')" class="nav-btn active" id="btn-tab-produksi">Produksi</button>
                <button onclick="App.switchTab('gudang')" class="nav-btn" id="btn-tab-gudang">Gudang</button>
            </div>
            <div class="bg-white min-h-[300px]">
                <div id="tab-produksi" class="tab-pane active">
                    <div class="overflow-x-auto">
                        <table class="modern-table">
                            <thead><tr><th>Bulan</th><th>Unit</th><th>TT</th><th>Luas(Ha)</th><th>Pkk</th><th class="text-right">Tandan/Pkk</th><th class="text-right">Protas</th><th class="text-right">Kg/HK</th></tr></thead>
                            <tbody id="tbl-prod-body"></tbody>
                        </table>
                    </div>
                    <div class="p-4 flex justify-between" id="pag-prod-ctl"></div>
                </div>

                <div id="tab-gudang" class="tab-pane">
                    <div class="overflow-x-auto"><table class="modern-table">
                        <thead><tr><th>Kebun</th><th>Bahan</th><th>Satuan</th><th class="text-right">Stok Awal</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th><th class="text-right">Akhir</th></tr></thead>
                        <tbody id="tbl-gudang-body"></tbody>
                    </table></div>
                    <div class="p-4 flex justify-between" id="pag-gudang-ctl"></div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script>
const CSRF_TOKEN = '<?= $CSRF ?>';

function logDebug(msg) {
    console.log(msg);
}

class Paginator {
    constructor(data, itemsPerPage, tbodyId, ctlId, renderRowFn) {
        this.data = data || [];
        this.perPage = itemsPerPage;
        this.currentPage = 1;
        this.tbody = document.getElementById(tbodyId);
        this.ctl = document.getElementById(ctlId);
        this.renderRow = renderRowFn;
        this.init();
    }
    init() {
        if(this.data.length === 0) {
            this.tbody.innerHTML = '<tr><td colspan="10" class="text-center py-8 text-slate-400 italic">Tidak ada data.</td></tr>';
            this.ctl.innerHTML = ''; return;
        }
        this.render();
    }
    render() {
        const total = Math.ceil(this.data.length / this.perPage);
        if(this.currentPage > total) this.currentPage = total;
        const start = (this.currentPage - 1) * this.perPage;
        const chunk = this.data.slice(start, start + this.perPage);
        this.tbody.innerHTML = chunk.map(this.renderRow).join('');
        this.ctl.innerHTML = `
            <span class="text-sm text-slate-500">Hal ${this.currentPage} dari ${total}</span>
            <div class="flex gap-2">
                <button class="px-3 py-1 border rounded hover:bg-slate-100" id="prev-${this.ctl.id}" ${this.currentPage===1?'disabled':''}>Prev</button>
                <button class="px-3 py-1 border rounded hover:bg-slate-100" id="next-${this.ctl.id}" ${this.currentPage===total?'disabled':''}>Next</button>
            </div>`;
        document.getElementById(`prev-${this.ctl.id}`).onclick = () => { this.currentPage--; this.render(); };
        document.getElementById(`next-${this.ctl.id}`).onclick = () => { this.currentPage++; this.render(); };
    }
}

const App = {
    filters: {
        kebun: document.getElementById('f-kebun'),
        afdeling: document.getElementById('f-afdeling'),
        bulan: document.getElementById('f-bulan'),
        tahun: document.getElementById('f-tahun')
    },
    init() {
        document.getElementById('btn-refresh').addEventListener('click', () => this.loadAll());
        this.loadAll();
        this.initWeather();
    },
    getPayload(section) {
        const fd = new FormData();
        fd.append('ajax', 'dashboard');
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('section', section);
        fd.append('kebun', this.filters.kebun.value);
        fd.append('afdeling', this.filters.afdeling.value);
        fd.append('bulan', this.filters.bulan.value);
        fd.append('tahun', this.filters.tahun.value);
        return fd;
    },
    loadAll() {
        document.getElementById('loading').classList.add('active');
        
        Promise.all([ this.loadKPI(), this.loadProduksi(), this.loadGudangOps() ])
        .finally(() => {
            document.getElementById('loading').classList.remove('active');
            if(window.lucide) lucide.createIcons();
        });
    },
    
    // --- FITUR BARU: CUACA REAL-TIME ---
    // --- FITUR CUACA REAL-TIME (FIXED) ---
    async initWeather() {
        // 1. Update Sapaan Waktu
        const updateGreeting = () => {
            const h = new Date().getHours();
            let s = "Selamat Malam";
            if(h >= 3 && h < 11) s = "Selamat Pagi";
            else if(h >= 11 && h < 15) s = "Selamat Siang";
            else if(h >= 15 && h < 19) s = "Selamat Sore";
            
            const el = document.getElementById('sapaan-waktu');
            if(el) el.innerText = s;
        };
        updateGreeting();
        
        // 2. Ambil Data Cuaca (Open-Meteo API)
        try {
            // KOORDINAT MEDAN (Ganti sesuai lokasi Kebun PTPN 4 Regional 3)
            const lat = 3.5833; 
            const lon = 98.6667;

            // Langsung fetch tanpa minta izin lokasi user (Lebih Cepat)
            const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,weather_code&timezone=auto`;
            
            const res = await fetch(url);
            if (!res.ok) throw new Error("Gagal mengambil data cuaca");
            
            const data = await res.json();

            if (data.current) {
                const temp = Math.round(data.current.temperature_2m);
                const code = data.current.weather_code;
                
                // Mapping kode cuaca WMO ke Bahasa Indonesia
                let desc = "Cerah";
                let icon = "sun"; // Default icon
                
                // 0: Cerah
                // 1-3: Berawan
                if (code >= 1 && code <= 3) { desc = "Berawan"; icon = "cloud-sun"; }
                // 45-48: Kabut
                else if (code >= 45 && code <= 48) { desc = "Berkabut"; icon = "cloud-fog"; }
                // 51-67: Gerimis/Hujan Ringan
                else if (code >= 51 && code <= 67) { desc = "Hujan Ringan"; icon = "cloud-drizzle"; }
                // 80-99: Hujan Deras
                else if (code >= 80 && code <= 99) { desc = "Hujan Deras"; icon = "cloud-rain"; }
                // 95+: Badai
                else if (code >= 95) { desc = "Badai Petir"; icon = "cloud-lightning"; }

                // Update UI
                const elTemp = document.getElementById('weather-temp');
                const elDesc = document.getElementById('weather-desc');
                const elIcon = document.getElementById('weather-icon');

                if(elTemp) elTemp.innerText = `${temp}°C`;
                if(elDesc) elDesc.innerText = desc;
                
                // Update Icon Lucide
                if(elIcon) {
                    elIcon.setAttribute('data-lucide', icon);
                    // Refresh icon render agar gambar berubah
                    if(window.lucide) lucide.createIcons();
                }
            }
        } catch(e) {
            console.error("Weather Error:", e);
            const elDesc = document.getElementById('weather-desc');
            if(elDesc) elDesc.innerText = "Offline";
        }
    },

    async loadKPI() {
        try {
            const res = await fetch('', { method:'POST', body: this.getPayload('pemeliharaan_data') }).then(r=>r.json());
            if(res.debug) logDebug({ section: 'KPI', debug: res.debug });
            if(res.success) {
                document.getElementById('kpi-container').innerHTML = res.kpi.map(k => `
                    <div class="mini-card">
                        <div class="mini-lbl">% ${k.label}</div>
                        <div class="mini-val ${k.color_class}">${k.persen}%</div>
                        <div class="mini-sub">Real: ${Number(k.realisasi).toLocaleString('id-ID')}</div>
                    </div>`).join('');
            }
        } catch(e) { logDebug({ error_js_kpi: e.message }); console.error(e); }
    },
    async loadProduksi() {
        try {
            const res = await fetch('', { method:'POST', body: this.getPayload('produksi_data') }).then(r=>r.json());
            if(res.debug) logDebug({ section: 'PRODUKSI', debug: res.debug });
            
            if(res.success) {
                const ctx = document.getElementById('chart-produksi').getContext('2d');
                if(window.prodChart) window.prodChart.destroy();
                window.prodChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: res.chart_data.map(x => x.bulan),
                        datasets: [
                            { label: 'Realisasi (Ton)', data: res.chart_data.map(x => Number(x.real)/1000), backgroundColor: '#06b6d4', borderRadius: 4, order: 2 },
                            { label: 'RKAP (Ton)', type: 'line', data: res.chart_data.map(x => Number(x.rkap)/1000), borderColor: '#ec4899', borderWidth: 2, tension: 0.3, pointRadius: 3, order: 1 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position:'bottom' } }, interaction: { mode: 'index', intersect: false } }
                });

                new Paginator(res.table_data, 10, 'tbl-prod-body', 'pag-prod-ctl', (r) => `
                    <tr>
                        <td class="font-bold text-xs text-slate-500">${r.bulan} ${r.tahun}</td>
                        <td class="font-medium">${r.nama_unit||'-'}</td>
                        <td class="text-center text-xs text-slate-600 font-mono">${r.tt||'-'}</td>
                        <td class="text-center text-xs text-slate-600 font-mono">${Number(r.luas_ha).toFixed(2)}</td>
                        <td class="text-center text-xs text-slate-600 font-mono">${Number(r.jumlah_pohon).toLocaleString()}</td>
                        <td class="text-right font-mono text-xs text-orange-500">${Number(r.tdn_pkk).toFixed(2)}</td>
                        <td class="text-right font-mono text-xs font-bold text-cyan-600">${Number(r.protas).toFixed(2)}</td>
                        <td class="text-right font-mono text-xs font-bold text-green-600">${Number(r.kg_hk).toFixed(2)}</td>
                    </tr>`);
            }
        } catch(e) { logDebug({ error_js_produksi: e.message }); console.error(e); }
    },
    async loadGudangOps() {
        try {
            const res = await fetch('', { method:'POST', body: this.getPayload('gudang_ops') }).then(r=>r.json());
            if(res.debug) logDebug({ section: 'GUDANG', debug: res.debug });
            if(res.success) {
                const fmt = (n) => Number(n).toLocaleString('id-ID');
                ['stok_gudang','stok_kimia','stok_alat'].forEach(k => document.getElementById(k.replace('_','-')).innerText = fmt(res.stock_stats[k]));
                ['bbm','kimia'].forEach(k => document.getElementById('pakai-'+k).innerText = fmt(res.usage_stats[k]));
                document.getElementById('val-tabur-kimia').innerText = fmt(res.pupuk_stats.tabur_kimia);
                document.getElementById('val-angkut-kimia').innerText = fmt(res.pupuk_stats.angkut_kimia);
                document.getElementById('val-tabur-org').innerText = fmt(res.pupuk_stats.tabur_org);
                document.getElementById('val-angkut-org').innerText = fmt(res.pupuk_stats.angkut_org);

                new Paginator(res.gudang_rows, 10, 'tbl-gudang-body', 'pag-gudang-ctl', (r) => `
                    <tr>
                        <td class="font-bold text-xs">${r.nama_kebun}</td>
                        <td class="text-xs font-medium">${r.nama_bahan}</td>
                        <td class="text-xs text-slate-400">${r.satuan||'-'}</td>
                        <td class="text-right font-mono text-xs text-slate-500">${fmt(r.stok_awal)}</td>
                        <td class="text-right font-mono text-xs text-green-600">+${fmt(r.mutasi_masuk)}</td>
                        <td class="text-right font-mono text-xs text-red-500">-${fmt(r.mutasi_keluar)}</td>
                        <td class="text-right font-mono text-xs font-black bg-slate-50">${fmt(r.stok_akhir)}</td>
                    </tr>`);
            }
        } catch(e) { logDebug({ error_js_gudang: e.message }); console.error(e); }
    },
    switchTab(tab) {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-'+tab).classList.add('active');
        document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('btn-tab-'+tab).classList.add('active');
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
</script>