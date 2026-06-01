<?php
require_once 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$query = "ALTER TABLE tr_pemetaan ADD COLUMN jenis_pekerjaan_id INT UNSIGNED NULL AFTER blok_id";
try {
    $pdo->exec($query);
    echo "Column jenis_pekerjaan_id added.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
