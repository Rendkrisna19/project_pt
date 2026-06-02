<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $table) {
    echo "TABLE: $table\n";
    $stmt2 = $pdo->query("SHOW COLUMNS FROM $table");
    $cols = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
?>
