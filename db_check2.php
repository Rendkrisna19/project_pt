<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$result = [];
foreach($tables as $table) {
    if ($table == 'tr_pemetaan' || $table == 'units' || $table == 'tr_jenis_pekerjaan') {
        $stmt2 = $pdo->query("SHOW COLUMNS FROM $table");
        $cols = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $result[$table] = $cols;
    }
}
file_put_contents('db_schema.json', json_encode($result, JSON_PRETTY_PRINT));
?>
