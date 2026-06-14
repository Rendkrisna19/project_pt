<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW CREATE TABLE tr_pemetaan");
print_r($stmt->fetch(PDO::FETCH_ASSOC)['Create Table']);
?>
