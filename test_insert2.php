<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
try {
    $sql = "INSERT INTO tr_pemetaan_peta_dasar (unit_id, jenis_pekerjaan_id, peta_kerja_foto) VALUES (1, 1, 'test.jpg') ON DUPLICATE KEY UPDATE peta_kerja_foto = 'test2.jpg'";
    $pdo->exec($sql);
    echo "Insert MyISAM Success\n";
} catch(Exception $e) {
    echo "Insert MyISAM Failed: " . $e->getMessage() . "\n";
}
?>
