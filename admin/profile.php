<?php
session_start();
// Pastikan user login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Ambil data user terbaru dari DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$currentPage = 'profile'; // Agar sidebar tidak error
include_once '../layouts/header.php';
?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 font-sans">
    <!-- Konten utama: tanpa mx-auto agar tetap di kiri -->
    <div class="w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header dengan breadcrumb yang lebih menarik -->
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="index.php" class="p-2 bg-white rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 shadow-sm transition-all duration-200 hover:shadow">
                    <i class="ti ti-arrow-left text-lg"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Edit Profil</h1>
                    <p class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">
                        <i class="ti ti-info-circle"></i> Kelola informasi akun dan kata sandi Anda
                    </p>
                </div>
            </div>
            <!-- Indikasi role / status -->
            <div class="bg-white/60 backdrop-blur-sm px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-sm font-medium text-slate-600 flex items-center gap-2">
                    <i class="ti ti-shield-check text-cyan-600"></i>
                    <?= htmlspecialchars($user['role']) ?>
                </span>
            </div>
        </div>

        <!-- Grid utama: profil + form -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Sidebar profil (kolom kiri) -->
            <div class="lg:col-span-1">
                <div class="bg-white/80 backdrop-blur-sm p-6 rounded-2xl border border-slate-200 shadow-lg hover:shadow-xl transition-shadow duration-300">
                    <div class="text-center">
                        <div class="relative inline-block group">
                            <?php if (!empty($user['foto_profil']) && file_exists("../uploads/profil/" . $user['foto_profil'])): ?>
                                <img id="preview-img" src="../uploads/profil/<?= $user['foto_profil'] ?>" 
                                     class="w-32 h-32 rounded-full object-cover border-4 border-cyan-100 shadow-lg mx-auto ring-2 ring-cyan-200/50">
                            <?php else: ?>
                                <div id="preview-placeholder" 
                                     class="w-32 h-32 rounded-full bg-gradient-to-br from-cyan-100 to-blue-100 flex items-center justify-center text-4xl font-bold text-cyan-700 mx-auto border-4 border-white shadow-lg ring-2 ring-cyan-200/50">
                                    <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <img id="preview-img" class="w-32 h-32 rounded-full object-cover border-4 border-cyan-100 shadow-lg mx-auto hidden ring-2 ring-cyan-200/50">
                            <?php endif; ?>
                            
                            <label for="foto_profil" 
                                   class="absolute bottom-0 right-0 bg-cyan-600 text-white p-2.5 rounded-full cursor-pointer shadow-lg hover:bg-cyan-700 transition-all transform hover:scale-110 border-2 border-white">
                                <i class="ti ti-camera text-sm"></i>
                            </label>
                        </div>
                        
                        <h3 class="mt-4 font-bold text-lg text-slate-800"><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
                        <p class="text-sm text-cyan-600 uppercase tracking-widest font-medium"><?= htmlspecialchars($user['role']) ?></p>
                        
                        <div class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-400 flex items-center justify-center gap-2">
                            <i class="ti ti-info-circle"></i>
                            <span>Format: JPG, PNG (Max 2MB)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form edit profil (kolom kanan) -->
            <div class="lg:col-span-2">
                <div class="bg-white/80 backdrop-blur-sm p-6 md:p-8 rounded-2xl border border-slate-200 shadow-lg">
                    <form action="profile_action.php" method="POST" enctype="multipart/form-data">
                        <input type="file" name="foto_profil" id="foto_profil" class="hidden" accept="image/*" onchange="previewFile()">

                        <!-- Field-field data diri -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nama Lengkap -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-1">
                                    <i class="ti ti-user text-cyan-600"></i> Nama Lengkap
                                </label>
                                <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" 
                                       class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none text-sm transition bg-white/50">
                            </div>
                            
                            <!-- Username (readonly) -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-1">
                                    <i class="ti ti-id text-cyan-600"></i> Username
                                </label>
                                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                                       class="w-full px-4 py-2.5 border border-slate-300 rounded-xl bg-slate-100 text-slate-500 cursor-not-allowed text-sm" readonly>
                            </div>

                            <!-- NIK -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-1">
                                    <i class="ti ti-badge-id text-cyan-600"></i> NIK Karyawan
                                </label>
                                <input type="text" name="nik" value="<?= htmlspecialchars($user['nik'] ?? '') ?>" placeholder="Nomor Induk Karyawan" 
                                       class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none text-sm transition bg-white/50">
                            </div>

                            <!-- Email -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-1">
                                    <i class="ti ti-mail text-cyan-600"></i> Email
                                </label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="email@ptpn4.co.id" 
                                       class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none text-sm transition bg-white/50">
                            </div>
                        </div>

                        <!-- Bagian Ganti Password -->
                        <div class="border-t border-slate-200 pt-6 mt-6">
                            <h4 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <span class="bg-cyan-100 p-1.5 rounded-lg"><i class="ti ti-lock text-cyan-600"></i></span>
                                Ganti Password
                            </h4>
                            <div class="bg-slate-50/80 p-4 rounded-xl border border-slate-200">
                                <p class="text-xs text-slate-500 mb-3 flex items-center gap-1">
                                    <i class="ti ti-alert-circle"></i> Kosongkan jika tidak ingin mengganti password.
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <input type="password" name="password_baru" placeholder="Password Baru" 
                                               class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition bg-white">
                                    </div>
                                    <div>
                                        <input type="password" name="konfirmasi_password" placeholder="Ulangi Password" 
                                               class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-cyan-500 outline-none transition bg-white">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol aksi -->
                        <div class="flex justify-end gap-3 mt-8">
                            <a href="index.php" 
                               class="px-6 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-slate-100 transition-all duration-200 flex items-center gap-2">
                                <i class="ti ti-x"></i> Batal
                            </a>
                            <button type="submit" 
                                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 text-white font-semibold hover:from-cyan-700 hover:to-blue-700 shadow-lg shadow-cyan-200/50 transition-all duration-200 flex items-center gap-2 transform hover:scale-105">
                                <i class="ti ti-device-floppy"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewFile() {
    const preview = document.getElementById('preview-img');
    const placeholder = document.getElementById('preview-placeholder');
    const file = document.getElementById('foto_profil').files[0];
    const reader = new FileReader();

    reader.addEventListener("load", function () {
        preview.src = reader.result;
        preview.classList.remove('hidden');
        if(placeholder) placeholder.classList.add('hidden');
    }, false);

    if (file) {
        reader.readAsDataURL(file);
    }
}

// Notifikasi dari URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('status') === 'success') {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Profil Anda telah diperbarui.',
        timer: 2000,
        showConfirmButton: false
    });
} else if (urlParams.get('status') === 'error') {
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: urlParams.get('msg') || 'Terjadi kesalahan.',
    });
}
</script>

<?php include_once '../layouts/footer.php'; ?>