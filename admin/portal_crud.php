<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';
$uploadDir = '../uploads/'; // Pastikan folder ini ada!

// --- FUNGSI HELPER UPLOAD ---
function handleUpload($file, $targetDir) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($ext, $allowed)) {
        throw new Exception("Hanya file gambar (JPG, PNG, WEBP) yang diperbolehkan.");
    }
    
    // Validasi Size (Max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception("Ukuran file terlalu besar (Maks 2MB).");
    }

    // Nama file unik
    $fileName = time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $targetPath = $targetDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath; // Mengembalikan path relatif untuk disimpan di DB
    }
    return null;
}

try {
    // 1. LIST DATA
    if ($action === 'list') {
        $stmt = $conn->prepare("SELECT * FROM web_apps ORDER BY id DESC");
        $stmt->execute();
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 2. TAMBAH DATA (STORE)
    if ($action === 'store') {
        $gambarUrl = null;
        if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === 0) {
            $gambarUrl = handleUpload($_FILES['gambar_file'], $uploadDir);
        }

        $sql = "INSERT INTO web_apps (nama_app, link_url, deskripsi, gambar_url) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $_POST['nama_app'],
            $_POST['link_url'],
            $_POST['deskripsi'],
            $gambarUrl // Simpan path file
        ]);
        echo json_encode(['success' => true, 'message' => 'Aplikasi berhasil ditambahkan']);
        exit;
    }

    // 3. UPDATE DATA
    if ($action === 'update') {
        $id = $_POST['id'];
        
        // Ambil data lama untuk cek gambar lama
        $stmt = $conn->prepare("SELECT gambar_url FROM web_apps WHERE id = ?");
        $stmt->execute([$id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $newGambarUrl = $oldData['gambar_url']; // Default pakai gambar lama

        // Jika ada upload gambar baru
        if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === 0) {
            // Hapus gambar lama jika ada
            if ($oldData['gambar_url'] && file_exists($oldData['gambar_url'])) {
                unlink($oldData['gambar_url']);
            }
            // Upload baru
            $newGambarUrl = handleUpload($_FILES['gambar_file'], $uploadDir);
        }

        $sql = "UPDATE web_apps SET nama_app=?, link_url=?, deskripsi=?, gambar_url=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $_POST['nama_app'],
            $_POST['link_url'],
            $_POST['deskripsi'],
            $newGambarUrl,
            $id
        ]);
        echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui']);
        exit;
    }

    // 4. HAPUS DATA
    if ($action === 'delete') {
        $id = $_POST['id'];
        
        // Hapus file fisiknya dulu
        $stmt = $conn->prepare("SELECT gambar_url FROM web_apps WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && $row['gambar_url'] && file_exists($row['gambar_url'])) {
            unlink($row['gambar_url']);
        }

        // Hapus dari DB
        $stmt = $conn->prepare("DELETE FROM web_apps WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Aplikasi dihapus']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>