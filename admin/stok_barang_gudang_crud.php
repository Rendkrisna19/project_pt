<?php
// stok_barang_gudang_crud.php
// MODIFIKASI FULL: Support kolom Bulan & Tahun, Hapus Tanggal

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

// Matikan display error agar tidak merusak JSON, log error saja
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']); 
    exit; 
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

// Sesuaikan nama tabel Anda di sini (sesuai perintah SQL sebelumnya: stok_barang_gudang)
$table = 'tr_stok_barang_gudang'; 

// --- 1. LIST DATA ---
if ($action === 'list') {
    $where = " WHERE 1=1 ";
    $params = [];

    // Filter Tahun (Wajib ada di frontend, tapi kita jaga-jaga)
    if (!empty($_POST['tahun'])) { 
        $where .= " AND t.tahun = :thn"; 
        $params[':thn'] = $_POST['tahun']; 
    }
    
    // Filter Bulan
    if (!empty($_POST['bulan']) && $_POST['bulan'] !== 'Semua Bulan') { 
        $where .= " AND t.bulan = :bln"; 
        $params[':bln'] = $_POST['bulan']; 
    }
    
    // Filter Kebun
    if (!empty($_POST['kebun_id'])) { 
        $where .= " AND t.kebun_id = :kbd"; 
        $params[':kbd'] = $_POST['kebun_id']; 
    }

    // Filter Barang
    if (!empty($_POST['jenis_barang_id'])) { 
        $where .= " AND t.jenis_barang_id = :jbi"; 
        $params[':jbi'] = $_POST['jenis_barang_id']; 
    }

    // Query: Join ke Kebun & Barang untuk ambil nama
    // Sort berdasarkan Tahun DESC, lalu ID DESC (sebagai urutan input terbaru)
    $sql = "SELECT t.*, 
                   k.nama_kebun, 
                   b.nama AS nama_barang, 
                   b.satuan
            FROM $table t
            LEFT JOIN md_kebun k ON t.kebun_id = k.id
            LEFT JOIN md_jenis_barang_gudang b ON t.jenis_barang_id = b.id
            $where
            ORDER BY t.tahun DESC, t.id DESC";

    try {
        $st = $conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Hitung Sisa Stok (Realtime Calculation)
        foreach ($rows as &$r) {
            $r['sisa'] = (float)$r['stok_awal'] 
                       + (float)$r['mutasi_masuk'] 
                       - (float)$r['mutasi_keluar'] 
                       + (float)$r['pasokan'] 
                       - (float)$r['dipakai'];
        }

        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 2. STORE (TAMBAH) / UPDATE (EDIT) ---
if ($action === 'store' || $action === 'update') {
    // Validasi input dasar
    if (empty($_POST['kebun_id']) || empty($_POST['jenis_barang_id']) || empty($_POST['bulan']) || empty($_POST['tahun'])) {
        echo json_encode(['success' => false, 'message' => 'Data Kebun, Barang, Bulan, dan Tahun wajib diisi.']);
        exit;
    }

    $data = [
        ':kbd' => $_POST['kebun_id'],
        ':jbi' => $_POST['jenis_barang_id'],
        ':bln' => $_POST['bulan'],
        ':thn' => $_POST['tahun'],
        ':sa'  => $_POST['stok_awal'] ?? 0,
        ':mm'  => $_POST['mutasi_masuk'] ?? 0,
        ':mk'  => $_POST['mutasi_keluar'] ?? 0,
        ':pask'=> $_POST['pasokan'] ?? 0,
        ':pakai'=> $_POST['dipakai'] ?? 0,
    ];

    if ($action === 'store') {
        $sql = "INSERT INTO $table (kebun_id, jenis_barang_id, bulan, tahun, stok_awal, mutasi_masuk, mutasi_keluar, pasokan, dipakai) 
                VALUES (:kbd, :jbi, :bln, :thn, :sa, :mm, :mk, :pask, :pakai)";
    } else {
        $sql = "UPDATE $table 
                SET kebun_id=:kbd, 
                    jenis_barang_id=:jbi, 
                    bulan=:bln, 
                    tahun=:thn, 
                    stok_awal=:sa, 
                    mutasi_masuk=:mm, 
                    mutasi_keluar=:mk, 
                    pasokan=:pask, 
                    dipakai=:pakai 
                WHERE id=:id";
        $data[':id'] = $_POST['id'];
    }

    try {
        $st = $conn->prepare($sql);
        $st->execute($data);
        echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    exit;
}

// --- 3. DELETE ---
if ($action === 'delete') {
    if (empty($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan.']);
        exit;
    }

    try {
        $st = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $st->execute([(int)$_POST['id']]);
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
    }
    exit;
}
?>