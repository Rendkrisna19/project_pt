<?php
// admin/arsip_crud.php
// Backend untuk CRUD Kategori Arsip (FOLDER)

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

// Respon error
function json_error(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Cek Otorisasi (Hanya Non-Staf/Admin yg boleh CUD)
function cek_otorisasi(bool $isCUD = false): void {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        json_error('Anda harus login ulang.', 401);
    }
    if ($isCUD) {
        $userRole = $_SESSION['user_role'] ?? 'staf';
        if ($userRole === 'staf') {
            json_error('Anda tidak memiliki izin untuk melakukan tindakan ini.', 403);
        }
    }
}

// Cek CSRF
function cek_csrf(): void {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        json_error('Sesi tidak valid. Muat ulang halaman.', 403);
    }
}

// ======================================================
// MAIN LOGIC
// ======================================================

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

try {
    switch ($action) {
        // [R]EAD - List Kategori
        case 'list':
            cek_otorisasi(false); // Semua boleh lihat list

            // Query untuk mengambil Kategori + JUMLAH file di dalamnya
            $stmt = $pdo->query("
                SELECT 
                    k.id, 
                    k.nama_kategori, 
                    k.keterangan,
                    COUNT(lm.id) AS jumlah_dokumen
                FROM 
                    arsip_kategori k
                LEFT JOIN 
                    laporan_mingguan lm ON k.id = lm.kategori_id
                GROUP BY 
                    k.id, k.nama_kategori, k.keterangan
                ORDER BY 
                    k.nama_kategori ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            exit;

        // [C]REATE - Simpan Kategori Baru
        case 'store':
            cek_otorisasi(true);
            cek_csrf();

            $nama = trim($_POST['nama_kategori'] ?? '');
            $ket = trim($_POST['keterangan'] ?? '');
            if (empty($nama)) json_error('Nama Kategori wajib diisi.');

            $stmt = $pdo->prepare("INSERT INTO arsip_kategori (nama_kategori, keterangan) VALUES (?, ?)");
            $stmt->execute([$nama, $ket]);

            echo json_encode(['success' => true, 'message' => 'Kategori berhasil disimpan.']);
            exit;

        // [U]PDATE - Update Kategori
        case 'update':
            cek_otorisasi(true);
            cek_csrf();

            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama_kategori'] ?? '');
            $ket = trim($_POST['keterangan'] ?? '');
            if (empty($id)) json_error('ID Kategori tidak valid.');
            if (empty($nama)) json_error('Nama Kategori wajib diisi.');

            $stmt = $pdo->prepare("UPDATE arsip_kategori SET nama_kategori = ?, keterangan = ? WHERE id = ?");
            $stmt->execute([$nama, $ket, $id]);

            echo json_encode(['success' => true, 'message' => 'Kategori berhasil diperbarui.']);
            exit;

        // [D]ELETE - Hapus Kategori
        case 'delete':
            cek_otorisasi(true);
            cek_csrf();
            
            $id = (int)($_POST['id'] ?? 0);
            if (empty($id)) json_error('ID Kategori tidak valid.');

            // Note: Karena FK di set 'ON DELETE SET NULL',
            // menghapus kategori tidak akan menghapus file-filenya.
            $stmt = $pdo->prepare("DELETE FROM arsip_kategori WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Kategori berhasil dihapus.']);
            exit;

        default:
            json_error('Tindakan tidak valid.', 400);
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('Nama Kategori sudah ada. Gunakan nama lain.');
    }
    json_error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_error('Server error: ' . $e->getMessage(), 500);
}