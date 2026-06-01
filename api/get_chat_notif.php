<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.message, c.file_type, c.created_at, u.nama_lengkap as sender 
        FROM chats c
        JOIN users u ON c.user_id = u.id
        WHERE c.user_id != ? AND c.is_deleted = 0
        ORDER BY c.id DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error']);
}
