<?php
// pages/data_karyawan.php
// ENHANCED VERSION: Multi-Tab System (Data Karyawan, Monitoring MBT, Tanggungan, Surat Peringatan)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

$userRole = $_SESSION['user_role'] ?? 'viewer';
$isAdmin  = ($userRole === 'admin');
$isStaf   = ($userRole === 'staf');

$canInput = ($isAdmin || $isStaf);
$canAction= ($isAdmin); 

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$currentPage = 'data_karyawan';
include_once '../layouts/header.php';

// Detect Active Tab
$activeTab = $_GET['tab'] ?? 'karyawan';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<style>
  .sticky-container { max-height: 70vh; overflow: auto; border: 1px solid #cbd5e1; border-radius: 0.75rem; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
  table.table-grid { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 2000px; }
  table.table-grid th, table.table-grid td { padding: 0.75rem; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle; }
  table.table-grid thead th { position: sticky; top: 0; background: #0e7490; color: #fff; z-index: 10; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; height: 50px; }
  table.table-grid tbody tr:hover td { background-color: #ecfeff; } 
  .avatar-sm { width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 2px solid #e2e8f0; }
  .tab-btn { padding: 0.75rem 1.5rem; border-bottom: 3px solid transparent; font-weight: 600; transition: all 0.3s; }
  .tab-btn.active { border-bottom-color: #0891b2; color: #0891b2; background: #ecfeff; }
  .tab-btn:hover { background: #f0fdfa; }
</style>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-cyan-900">Manajemen Data Kepegawaian</h1>
            <p class="text-slate-500 text-sm">Kelola data karyawan, monitoring MBT, tanggungan keluarga, dan surat peringatan.</p>
        </div>
    </div>

    <!-- TAB NAVIGATION -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex border-b border-gray-200 overflow-x-auto">
            <a href="?tab=karyawan" class="tab-btn <?= $activeTab == 'karyawan' ? 'active' : 'text-gray-600' ?>">
                <i class="ti ti-users mr-2"></i>Data Karyawan
            </a>
            <a href="?tab=mbt" class="tab-btn <?= $activeTab == 'mbt' ? 'active' : 'text-gray-600' ?>">
                <i class="ti ti-calendar-event mr-2"></i>Monitoring MBT
            </a>
            <a href="?tab=tanggungan" class="tab-btn <?= $activeTab == 'tanggungan' ? 'active' : 'text-gray-600' ?>">
                <i class="ti ti-users-group mr-2"></i>Data Tanggungan
            </a>
            <a href="?tab=peringatan" class="tab-btn <?= $activeTab == 'peringatan' ? 'active' : 'text-gray-600' ?>">
                <i class="ti ti-alert-triangle mr-2"></i>Surat Peringatan
            </a>
        </div>
    </div>

    <!-- TAB CONTENT -->
    <div id="tab-content">
        <?php if ($activeTab == 'karyawan'): ?>
            <?php include 'tabs/tab_karyawan.php'; ?>
        <?php elseif ($activeTab == 'mbt'): ?>
            <?php include 'tabs/tab_mbt.php'; ?>
        <?php elseif ($activeTab == 'tanggungan'): ?>
            <?php include 'tabs/tab_tanggungan.php'; ?>
        <?php elseif ($activeTab == 'peringatan'): ?>
            <?php include 'tabs/tab_peringatan.php'; ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>