<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query("SHOW VARIABLES LIKE 'innodb_force_recovery'");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
echo "innodb_force_recovery is " . ($res ? $res['Value'] : 'unknown') . "\n";
?>
