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
    $replyToId = isset($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
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
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp3', 'wav', 'm4a', 'ogg', 'mp4', 'webm'];

        if (in_array($ext, $allowedExts)) {
            $fileName = uniqid() . '.' . $ext;
            $targetFilePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFilePath)) {
                $fileUrl = $fileName;
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $fileType = 'image';
                elseif (in_array($ext, ['mp3', 'wav', 'm4a', 'ogg'])) $fileType = 'audio';
                elseif (in_array($ext, ['mp4', 'webm'])) $fileType = 'video';
                else $fileType = 'document';
            }
        }
    }

    if ($message !== '' || $fileUrl !== null) {
        $createdAt = date('Y-m-d H:i:s'); // Memaksa jam WIB dari PHP
        $stmt = $pdo->prepare("INSERT INTO chats (user_id, reply_to_id, message, file_url, file_type, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $replyToId, $message, $fileUrl, $fileType, $createdAt])) {
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
        SELECT c.*, u.nama_lengkap, u.foto_profil, u.role,
               rc.message AS reply_to_message,
               ru.nama_lengkap AS reply_to_sender
        FROM chats c 
        JOIN users u ON c.user_id = u.id 
        LEFT JOIN chats rc ON c.reply_to_id = rc.id
        LEFT JOIN users ru ON rc.user_id = ru.id
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
            'user_id' => $msg['user_id'],
            'is_me' => $msg['user_id'] == $userId,
            'sender' => $msg['nama_lengkap'],
            'role' => strtoupper($msg['role']),
            'foto' => $msg['foto_profil'] ? '../uploads/profil/' . $msg['foto_profil'] : null,
            'message' => htmlspecialchars((string)$msg['message']),
            'reply_to_sender' => $msg['reply_to_sender'],
            'reply_to_message' => htmlspecialchars((string)$msg['reply_to_message']),
            'file_url' => $msg['file_url'] ? '../uploads/chat/' . $msg['file_url'] : null,
            'file_type' => $msg['file_type'],
            'time' => date('H:i', strtotime($msg['created_at'])),
            'is_edited' => (bool)$msg['is_edited'],
            'is_deleted' => (bool)$msg['is_deleted']
        ];
    }

    echo json_encode([
        'success' => true, 
        'messages' => $formattedMessages,
        'online_count' => $onlineCount
    ]);
    exit;
}

if ($action === 'fetch_old') {
    $firstId = isset($_GET['first_id']) ? (int)$_GET['first_id'] : 0;
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.nama_lengkap, u.foto_profil, u.role,
               rc.message AS reply_to_message,
               ru.nama_lengkap AS reply_to_sender
        FROM chats c 
        JOIN users u ON c.user_id = u.id 
        LEFT JOIN chats rc ON c.reply_to_id = rc.id
        LEFT JOIN users ru ON rc.user_id = ru.id
        WHERE c.id < ? 
        ORDER BY c.id DESC 
        LIMIT 20
    ");
    $stmt->execute([$firstId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Messages are fetched DESC, we should reverse them so they are ASC
    $messages = array_reverse($messages);

    $formattedMessages = [];
    foreach ($messages as $msg) {
        $formattedMessages[] = [
            'id' => $msg['id'],
            'is_me' => $msg['user_id'] == $userId,
            'sender' => $msg['nama_lengkap'],
            'role' => strtoupper($msg['role']),
            'foto' => $msg['foto_profil'] ? '../uploads/profil/' . $msg['foto_profil'] : null,
            'message' => htmlspecialchars((string)$msg['message']),
            'reply_to_sender' => $msg['reply_to_sender'],
            'reply_to_message' => htmlspecialchars((string)$msg['reply_to_message']),
            'file_url' => $msg['file_url'] ? '../uploads/chat/' . $msg['file_url'] : null,
            'file_type' => $msg['file_type'],
            'time' => date('H:i', strtotime($msg['created_at']))
        ];
    }

    echo json_encode([
        'success' => true, 
        'messages' => $formattedMessages
    ]);
    exit;
}

if ($action === 'edit') {
    $msgId = isset($_POST['msg_id']) ? (int)$_POST['msg_id'] : 0;
    $newMessage = trim($_POST['message'] ?? '');

    if ($msgId > 0 && $newMessage !== '') {
        $stmt = $pdo->prepare("UPDATE chats SET message = ?, is_edited = 1 WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$newMessage, $msgId, $userId])) {
            
            // Ambil data terbaru
            $stmtGet = $pdo->prepare("SELECT c.*, u.nama_lengkap, u.foto_profil, u.role, rc.message AS reply_to_message, ru.nama_lengkap AS reply_to_sender FROM chats c JOIN users u ON c.user_id = u.id LEFT JOIN chats rc ON c.reply_to_id = rc.id LEFT JOIN users ru ON rc.user_id = ru.id WHERE c.id = ?");
            $stmtGet->execute([$msgId]);
            $msg = $stmtGet->fetch(PDO::FETCH_ASSOC);
            
            $updatedMsg = [
                'id' => $msg['id'],
                'is_me' => $msg['user_id'] == $userId,
                'sender' => $msg['nama_lengkap'],
                'role' => strtoupper($msg['role']),
                'foto' => $msg['foto_profil'] ? '../uploads/profil/' . $msg['foto_profil'] : null,
                'message' => htmlspecialchars((string)$msg['message']),
                'reply_to_sender' => $msg['reply_to_sender'],
                'reply_to_message' => htmlspecialchars((string)$msg['reply_to_message']),
                'file_url' => $msg['file_url'] ? '../uploads/chat/' . $msg['file_url'] : null,
                'file_type' => $msg['file_type'],
                'time' => date('H:i', strtotime($msg['created_at'])),
                'is_edited' => (bool)$msg['is_edited'],
                'is_deleted' => (bool)$msg['is_deleted']
            ];

            echo json_encode(['success' => true, 'updated_message' => $updatedMsg]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah pesan.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    }
    exit;
}

if ($action === 'delete') {
    $msgId = isset($_POST['msg_id']) ? (int)$_POST['msg_id'] : 0;
    $userRole = $_SESSION['role'] ?? '';

    if ($msgId > 0 && strtoupper($userRole) === 'ADMIN') {
        $stmt = $pdo->prepare("UPDATE chats SET is_deleted = 1, deleted_by = ? WHERE id = ?");
        if ($stmt->execute([$userId, $msgId])) {
            
            // Ambil data terbaru
            $stmtGet = $pdo->prepare("SELECT c.*, u.nama_lengkap, u.foto_profil, u.role, rc.message AS reply_to_message, ru.nama_lengkap AS reply_to_sender FROM chats c JOIN users u ON c.user_id = u.id LEFT JOIN chats rc ON c.reply_to_id = rc.id LEFT JOIN users ru ON rc.user_id = ru.id WHERE c.id = ?");
            $stmtGet->execute([$msgId]);
            $msg = $stmtGet->fetch(PDO::FETCH_ASSOC);
            
            $updatedMsg = [
                'id' => $msg['id'],
                'is_me' => $msg['user_id'] == $userId,
                'sender' => $msg['nama_lengkap'],
                'role' => strtoupper($msg['role']),
                'foto' => $msg['foto_profil'] ? '../uploads/profil/' . $msg['foto_profil'] : null,
                'message' => htmlspecialchars((string)$msg['message']),
                'reply_to_sender' => $msg['reply_to_sender'],
                'reply_to_message' => htmlspecialchars((string)$msg['reply_to_message']),
                'file_url' => $msg['file_url'] ? '../uploads/chat/' . $msg['file_url'] : null,
                'file_type' => $msg['file_type'],
                'time' => date('H:i', strtotime($msg['created_at'])),
                'is_edited' => (bool)$msg['is_edited'],
                'is_deleted' => (bool)$msg['is_deleted']
            ];

            echo json_encode(['success' => true, 'updated_message' => $updatedMsg]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus pesan.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Hanya Admin yang dapat menghapus pesan.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
