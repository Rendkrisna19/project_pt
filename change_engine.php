<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
try {
    $pdo->exec("ALTER TABLE tr_pemetaan_peta_dasar ENGINE=MyISAM");
    echo "Engine changed to MyISAM\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
