<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = 'chats'");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
echo "chats Engine: " . $res['Engine'] . "\n";
$stmt2 = $pdo->query("SHOW TABLE STATUS WHERE Name = 'tr_pemetaan_peta_dasar'");
$res2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "tr_pemetaan_peta_dasar Engine: " . $res2['Engine'] . "\n";
?>
