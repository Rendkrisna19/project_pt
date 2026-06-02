<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$sql = "CREATE TABLE IF NOT EXISTS tr_pemetaan_peta_dasar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    jenis_pekerjaan_id INT NOT NULL,
    peta_kerja_foto VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unit_jp (unit_id, jenis_pekerjaan_id)
)";
$pdo->exec($sql);
echo "Success\n";
?>
