<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
try {
    $pdo->exec("DROP TABLE IF EXISTS tr_pemetaan_peta_dasar");
    $sql = "CREATE TABLE tr_pemetaan_peta_dasar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit_id INT NOT NULL,
        jenis_pekerjaan_id INT NOT NULL,
        peta_kerja_foto VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unit_jp (unit_id, jenis_pekerjaan_id)
    ) ENGINE=MyISAM";
    $pdo->exec($sql);
    echo "Table recreated as MyISAM\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
