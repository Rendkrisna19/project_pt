<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

$userRole = $_SESSION['user_role'] ?? 'viewer';
if ($userRole !== 'admin') {
    echo "Akses Ditolak. Halaman ini hanya untuk Administrator.";
    exit;
}

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Buat tabel jika belum ada (Opsional untuk jaga-jaga)
$conn->exec("CREATE TABLE IF NOT EXISTS md_pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login_bg_video VARCHAR(255) DEFAULT 'bg.mp4',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$conn->exec("INSERT IGNORE INTO md_pengaturan (id, login_bg_video) VALUES (1, 'bg.mp4')");

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['bg_video']) && $_FILES['bg_video']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['bg_video']['tmp_name'];
        $name = $_FILES['bg_video']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
            $newName = 'bg_' . time() . '.' . $ext;
            $uploadDir = '../uploads/pengaturan/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                $stmt = $conn->prepare("UPDATE md_pengaturan SET login_bg_video = :vid WHERE id = 1");
                $stmt->execute([':vid' => $newName]);
                $msg = "Background video berhasil diupdate!";
                $msgType = "success";
            } else {
                $msg = "Gagal memindahkan file.";
                $msgType = "error";
            }
        } else {
            $msg = "Format file tidak didukung. Harap upload MP4, WEBM, atau OGG.";
            $msgType = "error";
        }
    } else {
        $msg = "Gagal mengupload file atau file terlalu besar.";
        $msgType = "error";
    }
}

// Ambil data pengaturan
$stmt = $conn->query("SELECT login_bg_video FROM md_pengaturan WHERE id = 1 LIMIT 1");
$pengaturan = $stmt->fetch(PDO::FETCH_ASSOC);
$currentVideo = $pengaturan['login_bg_video'] ?? 'bg.mp4';

// Cek apakah file ada di uploads atau menggunakan bawaan
$videoPath = '../assets/images/' . $currentVideo;
if ($currentVideo !== 'bg.mp4' && file_exists('../uploads/pengaturan/' . $currentVideo)) {
    $videoPath = '../uploads/pengaturan/' . $currentVideo;
}

$currentPage = 'pengaturan_aplikasi';
include_once '../layouts/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Pengaturan Aplikasi</h1>
            <p class="text-sm text-gray-500 mt-1">Atur background login dan preferensi sistem.</p>
        </div>
    </div>

    <?php if($msg): ?>
    <div class="p-4 rounded-lg <?= $msgType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> mb-4 font-semibold text-sm">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-6 md:p-8 rounded-xl shadow-sm border border-gray-100 max-w-2xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2"><i class="ti ti-video text-cyan-600"></i> Ubah Background Video Login</h3>
        
        <div class="mb-6 relative rounded-lg overflow-hidden border-4 border-slate-100 shadow-md">
            <video autoplay muted loop playsinline class="w-full h-48 object-cover">
                <source src="<?= $videoPath ?>?t=<?= time() ?>" type="video/mp4">
            </video>
            <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 hover:opacity-100 transition-opacity">
                <span class="bg-black/60 text-white px-3 py-1 rounded-md text-xs font-bold tracking-widest uppercase">Preview Saat Ini</span>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Upload Video Baru (MP4, max 20MB)</label>
                <input type="file" name="bg_video" accept=".mp4,.webm,.ogg" required 
                       class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 cursor-pointer border border-slate-200 rounded-lg">
            </div>
            
            <button type="submit" class="w-full bg-cyan-600 text-white px-5 py-2.5 rounded-lg hover:bg-cyan-700 text-sm font-bold shadow-lg shadow-cyan-500/30 transition-all flex items-center justify-center gap-2">
                <i class="ti ti-upload"></i> Simpan & Update Video
            </button>
        </form>
    </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
