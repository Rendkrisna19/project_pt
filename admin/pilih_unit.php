<?php
// pages/pilih_unit.php
session_start();

// 1. CEK LOGIN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

// 2. AMBIL ROLE
$userRole = $_SESSION['user_role'] ?? 'staf'; // Default ke staf jika kosong

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// --- LOGIKA FILTER KEBUN ---
$f_kebun_id = isset($_GET['kebun_id']) ? (int)$_GET['kebun_id'] : 0;

// 1. Ambil data master kebun untuk Dropdown
$kebuns = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Ambil data unit berdasarkan filter
try {
    if ($f_kebun_id > 0) {
        // Jika kebun dipilih, filter unitnya
        $sql = "SELECT u.*, k.nama_kebun 
                FROM units u 
                LEFT JOIN md_kebun k ON u.kebun_id = k.id 
                WHERE u.kebun_id = ? 
                ORDER BY u.nama_unit ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$f_kebun_id]);
    } else {
        // Jika semua kebun, tampilkan semua unit
        $sql = "SELECT u.*, k.nama_kebun 
                FROM units u 
                LEFT JOIN md_kebun k ON u.kebun_id = k.id 
                ORDER BY k.nama_kebun ASC, u.nama_unit ASC";
        $stmt = $conn->query($sql);
    }
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Fallback Jaga-Jaga: Jika ternyata di tabel `units` belum ada kolom `kebun_id`
    $units = $conn->query("SELECT * FROM units ORDER BY nama_unit ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$currentPage = 'pilih_unit';
$pageTitle = "Pilih Unit Kertas Kerja";
include_once '../layouts/header.php'; 
?>

<div class="p-6 min-h-screen bg-slate-50">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800">Pilih Unit Kertas Kerja</h1>
            <p class="text-slate-500 text-sm mt-1">Silakan filter kebun dan pilih unit untuk mengelola kertas kerja.</p>
        </div>
        
        <form method="GET" class="w-full md:w-80">
            <label class="block text-xs font-bold text-cyan-700 uppercase mb-2 tracking-wider flex items-center gap-1">
                <i data-lucide="filter" class="w-3 h-3"></i> Filter Berdasarkan Kebun
            </label>
            <select name="kebun_id" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 outline-none focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/20 bg-slate-50 hover:bg-white transition-all cursor-pointer" onchange="this.form.submit()">
                <option value="">— Tampilkan Semua Kebun —</option>
                <?php foreach($kebuns as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $f_kebun_id === (int)$k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kebun']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if(empty($units)): ?>
        <div class="bg-white p-12 rounded-2xl border border-dashed border-slate-300 text-center shadow-sm">
            <div class="w-20 h-20 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-200">
                <i data-lucide="folder-search" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-700 mb-1">Tidak Ada Unit Ditemukan</h3>
            <p class="text-slate-500 text-sm">Belum ada unit yang terdaftar untuk kebun yang Anda pilih.</p>
            <a href="pilih_unit.php" class="inline-block mt-6 px-6 py-2.5 bg-cyan-600 text-white font-bold rounded-lg hover:bg-cyan-700 transition shadow-lg shadow-cyan-200">Tampilkan Semua Unit</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach($units as $u): ?>
            
            <a href="kertas_kerja.php?kebun_id=<?= $u['kebun_id'] ?? $f_kebun_id ?>&unit_id=<?= $u['id'] ?>" class="group block h-full">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 hover:border-cyan-500 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 p-6 flex flex-col h-full relative overflow-hidden">
                    
                    <div class="absolute -right-8 -top-8 w-32 h-32 bg-cyan-50 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>

                    <div class="flex items-start gap-4 relative z-10">
                        <div class="bg-slate-50 text-slate-400 rounded-xl p-3.5 group-hover:bg-cyan-600 group-hover:text-white transition-colors duration-300 border border-slate-100 group-hover:border-cyan-600 shadow-sm shrink-0">
                            <i data-lucide="layout-dashboard" class="w-7 h-7"></i>
                        </div>
                        
                        <div class="flex-1">
                            <h3 class="font-extrabold text-slate-800 text-lg mb-1 group-hover:text-cyan-700 transition-colors line-clamp-2">
                                <?= htmlspecialchars($u['nama_unit']) ?>
                            </h3>
                            
                            <?php if(!empty($u['nama_kebun'])): ?>
                            <div class="text-[10.5px] text-slate-500 font-bold uppercase tracking-wider flex items-center gap-1.5 mb-3 bg-slate-100 w-fit px-2 py-1 rounded">
                                <i data-lucide="map-pin" class="w-3 h-3 text-cyan-600"></i> <?= htmlspecialchars($u['nama_kebun']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-auto pt-4 relative z-10">
                        <div class="h-px w-full bg-gradient-to-r from-slate-100 via-slate-200 to-transparent mb-3"></div>
                        <div class="text-xs text-cyan-600 font-bold flex items-center gap-1.5 group-hover:text-cyan-700">
                            Buka Kertas Kerja 
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5 group-hover:translate-x-1.5 transition-transform duration-300"></i>
                        </div>
                    </div>
                    
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../layouts/footer.php'; ?>