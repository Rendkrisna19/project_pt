<?php
// pages/pilih_unit.php
session_start();

// 1. CEK LOGIN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

// 2. AMBIL ROLE (Sesuai nama session di pemupukan.php)
$userRole = $_SESSION['user_role'] ?? 'staf'; // Default ke staf jika kosong

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$units = $conn->query("SELECT * FROM units ORDER BY nama_unit ASC")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'pilih_unit';
$pageTitle = "Pilih Unit Kertas Kerja";
include_once '../layouts/header.php'; 
?>

<div class="p-6">
   

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-cyan-800">Pilih Unit Kertas Kerja</h1>
        <p class="text-cyan-500">Silakan pilih unit untuk mengisi atau melihat kertas kerja.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach($units as $u): ?>
        <a href="kertas_kerja.php?unit_id=<?= $u['id'] ?>" class="group block">
            <div class="bg-white rounded-lg shadow-sm border-l-4 border-cyan-500 hover:border-cyan-700 hover:shadow-md transition-all duration-200 p-5 flex items-start gap-4 h-full">
                <div class="bg-cyan-100 text-cyan-600 rounded-lg p-3 group-hover:bg-cyan-200 transition">
                    <i data-lucide="file-spreadsheet" class="w-8 h-8"></i>
                </div>
                <div>
                    <h3 class="font-bold text-cyan-800 text-lg mb-1 group-hover:text-blue-600 transition">
                        <?= htmlspecialchars($u['nama_unit']) ?>
                    </h3>
                    <div class="text-xs text-cyan-400 font-medium uppercase tracking-wider">
                        Klik untuk buka
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php include_once '../layouts/footer.php'; ?>