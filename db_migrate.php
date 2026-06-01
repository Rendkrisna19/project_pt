<?php
require_once 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query("SHOW CREATE TABLE users");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'];
?>
