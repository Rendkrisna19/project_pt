<?php
session_start();
// Pastikan user login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php"); exit;
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

<div class="p-6 bg-slate-50 min-h-screen font-sans">
    <div class="max-w-4xl mx-auto">
        
        <div class="mb-6 flex items-center gap-3">
            <a href="index.php" class="p-2 bg-white rounded-lg border hover:bg-slate-50 text-slate-600"><i class="ti ti-arrow-left"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Edit Profil</h1>
                <p class="text-sm text-slate-500">Kelola informasi akun dan kata sandi Anda.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 text-center">
                    <div class="relative inline-block group">
                        <?php if (!empty($user['foto_profil']) && file_exists("../uploads/profil/" . $user['foto_profil'])): ?>
                            <img id="preview-img" src="../uploads/profil/<?= $user['foto_profil'] ?>" class="w-32 h-32 rounded-full object-cover border-4 border-cyan-100 shadow-lg mx-auto">
                        <?php else: ?>
                            <div id="preview-placeholder" class="w-32 h-32 rounded-full bg-slate-100 flex items-center justify-center text-4xl font-bold text-slate-400 mx-auto border-4 border-slate-50">
                                <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                            </div>
                            <img id="preview-img" class="w-32 h-32 rounded-full object-cover border-4 border-cyan-100 shadow-lg mx-auto hidden">
                        <?php endif; ?>
                        
                        <label for="foto_profil" class="absolute bottom-0 right-0 bg-cyan-600 text-white p-2 rounded-full cursor-pointer shadow-lg hover:bg-cyan-700 transition transform hover:scale-110">
                            <i class="ti ti-camera"></i>
                        </label>
                    </div>
                    
                    <h3 class="mt-4 font-bold text-lg text-slate-800"><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
                    <p class="text-sm text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($user['role']) ?></p>
                    <p class="text-xs text-slate-400 mt-2">Format: JPG, PNG (Max 2MB)</p>
                </div>
            </div>

            <div class="md:col-span-2">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
                    <form action="profile_action.php" method="POST" enctype="multipart/form-data">
                        <input type="file" name="foto_profil" id="foto_profil" class="hidden" accept="image/*" onchange="previewFile()">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-bold text-slate-600 mb-2">Nama Lengkap</label>
                                <div class="relative">
                                    <i class="ti ti-user absolute left-3 top-3 text-slate-400"></i>
                                    <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none text-sm transition" required>
                                </div>
                            </div>
                            
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-bold text-slate-600 mb-2">Username (Login)</label>
                                <div class="relative">
                                    <i class="ti ti-id absolute left-3 top-3 text-slate-400"></i>
                                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl bg-slate-50 text-slate-500 cursor-not-allowed text-sm" readonly title="Username tidak bisa diganti">
                                </div>
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-bold text-slate-600 mb-2">NIK Karyawan</label>
                                <div class="relative">
                                    <i class="ti ti-badge-id absolute left-3 top-3 text-slate-400"></i>
                                    <input type="text" name="nik" value="<?= htmlspecialchars($user['nik'] ?? '') ?>" placeholder="Nomor Induk Karyawan" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none text-sm transition">
                                </div>
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-bold text-slate-600 mb-2">Email</label>
                                <div class="relative">
                                    <i class="ti ti-mail absolute left-3 top-3 text-slate-400"></i>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="email@ptpn4.co.id" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 outline-none text-sm transition">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-6 mb-6">
                            <h4 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <i class="ti ti-lock"></i> Ganti Password
                            </h4>
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <p class="text-xs text-slate-500 mb-3">Kosongkan jika tidak ingin mengganti password.</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <input type="password" name="password_baru" placeholder="Password Baru" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
                                    <input type="password" name="konfirmasi_password" placeholder="Ulangi Password" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-cyan-500 outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="index.php" class="px-6 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-bold hover:bg-slate-50 transition">Batal</a>
                            <button type="submit" class="px-6 py-2.5 rounded-xl bg-cyan-600 text-white font-bold hover:bg-cyan-700 shadow-lg shadow-cyan-200 transition flex items-center gap-2">
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

// Cek status sukses dari URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('status') === 'success') {
    Swal.fire('Berhasil!', 'Profil Anda telah diperbarui.', 'success');
} else if (urlParams.get('status') === 'error') {
    Swal.fire('Gagal!', urlParams.get('msg') || 'Terjadi kesalahan.', 'error');
}
</script>

<?php include_once '../layouts/footer.php'; ?>