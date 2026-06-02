<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
try {
    $stmt = $pdo->query("INSERT INTO chats (user_id, message, created_at) VALUES (1, 'Test', NOW())");
    echo "Insert Chat Success\n";
} catch(Exception $e) {
    echo "Insert Chat Failed: " . $e->getMessage() . "\n";
}
?>
