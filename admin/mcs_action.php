<?php
// pages/mcs_action.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// PENTING: Naikkan limit karena JSON Excel dengan desain itu BESAR
ini_set('memory_limit', '512M');
ini_set('post_max_size', '64M');
ini_set('upload_max_filesize', '64M');
ini_set('max_execution_time', 300);

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'upload') {
        $nama = $_POST['nama_file'];
        $json = $_POST['data_json'];
        
        // Validasi
        if(empty($json) || $json == '[]') throw new Exception("File kosong atau tidak terbaca.");

        $stmt = $conn->prepare("INSERT INTO mcs_sheets (nama_file, sheet_data) VALUES (?, ?)");
        $stmt->execute([$nama, $json]);
        
        echo json_encode(['success'=>true, 'message'=>'File berhasil diupload']);

    } elseif ($action === 'load') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("SELECT * FROM mcs_sheets WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$row) throw new Exception("Data tidak ditemukan");
        
        echo json_encode(['success'=>true, 'data'=>$row]);

    } elseif ($action === 'autosave') {
        $id = $_POST['id'];
        $json = $_POST['data_json'];
        
        // Update data
        $stmt = $conn->prepare("UPDATE mcs_sheets SET sheet_data = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$json, $id]);
        
        echo json_encode(['success'=>true]);

    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $conn->prepare("DELETE FROM mcs_sheets WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]);
    }
} catch (Exception $e) {
    // Tangkap error upload size jika ada
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>