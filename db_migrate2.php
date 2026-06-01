<?php
require_once 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$queries = [
    "ALTER TABLE chats ADD COLUMN reply_to_id INT UNSIGNED NULL AFTER id;",
    "ALTER TABLE chats ADD COLUMN is_edited TINYINT(1) DEFAULT 0 AFTER file_type;",
    "ALTER TABLE chats ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER is_edited;",
    "ALTER TABLE chats ADD COLUMN deleted_by INT UNSIGNED NULL AFTER is_deleted;",
    "ALTER TABLE chats ADD COLUMN reactions JSON NULL AFTER deleted_by;"
];
foreach ($queries as $query) {
    try {
        $pdo->exec($query);
        echo "Executed: $query\n";
    } catch (Exception $e) {
        echo "Error on $query: " . $e->getMessage() . "\n";
    }
}
?>
