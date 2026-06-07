<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $stmt = $conn->query('SHOW COLUMNS FROM tr_lm_biaya');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
