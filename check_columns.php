<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW COLUMNS FROM tr_pemetaan WHERE Field IN ('fisik_hari_ini', 'fisik_sd', 'hk_hari_ini', 'hk_sd', 'bahan_kimia_hari_ini', 'bahan_kimia_sd', 'campuran_hari_ini', 'campuran_sd')");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
