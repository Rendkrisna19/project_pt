<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$st = $conn->query("DESCRIBE tr_pemetaan");
print_r($st->fetchAll(PDO::FETCH_ASSOC));
?>
