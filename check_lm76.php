<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW COLUMNS FROM lm76");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Kolom LM76:\n";
print_r($cols);
