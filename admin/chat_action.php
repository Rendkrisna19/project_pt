<?php
session_start();
require_once '../config/database.php';

// Cek Sesi
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$userId = $_SESSION['user_id'];

// Update last_seen
$pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'send') {
    $message = trim($_POST['message'] ?? '');
    $fileUrl = null;
    $fileType = null;

    // Handle File Upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/chat/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileInfo = pathinfo($_FILES['file']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

        if (in_array($ext, $allowedExts)) {
            $fileName = uniqid() . '.' . $ext;
            $targetFilePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFilePath)) {
                $fileUrl = $fileName;
                $fileType = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'document';
            }
        }
    }

    if ($message !== '' || $fileUrl !== null) {
        $stmt = $pdo->prepare("INSERT INTO chats (user_id, message, file_url, file_type) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$userId, $message, $fileUrl, $fileType])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengirim pesan']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Pesan kosong']);
    }
    exit;
}

if ($action === 'fetch') {
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    // Get Online Users Count (Active in last 2 minutes)
    $stmtOnline = $pdo->query("SELECT COUNT(*) as online_count FROM users WHERE last_seen >= NOW() - INTERVAL 2 MINUTE");
    $onlineCount = $stmtOnline->fetch(PDO::FETCH_ASSOC)['online_count'];

    $stmt = $pdo->prepare("
        SELECT c.*, u.nama_lengkap, u.foto_profil, u.role 
        FROM chats c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id > ? 
        ORDER BY c.id ASC 
        LIMIT 50
    ");
    $stmt->execute([$lastId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedMessages = [];
    foreach ($messages as $msg) {
        $formattedMessages[] = [
            'id' => $msg['id'],
            'is_me' => $msg['user_id'] == $userId,
            'sender' => $msg['nama_lengkap'],
            'role' => strtoupper($msg['role']),
            'foto' => $msg['foto_profil'] ? '../uploads/profil/' . $msg['foto_profil'] : null,
            'message' => htmlspecialchars($msg['message']),
            'file_url' => $msg['file_url'] ? '../uploads/chat/' . $msg['file_url'] : null,
            'file_type' => $msg['file_type'],
            'time' => date('H:i', strtotime($msg['created_at']))
        ];
    }

    echo json_encode([
        'success' => true, 
        'messages' => $formattedMessages,
        'online_count' => $onlineCount
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
