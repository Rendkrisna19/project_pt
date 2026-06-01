<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN tanggal_realisasi DATE NULL AFTER blok_id;");
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN fisik_hari_ini DECIMAL(10,2) DEFAULT 0;");
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN fisik_sd DECIMAL(10,2) DEFAULT 0;");
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN hk_hari_ini DECIMAL(10,2) DEFAULT 0;");
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN hk_sd DECIMAL(10,2) DEFAULT 0;");
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN bahan_kimia_hari_ini DECIMAL(10,2) DEFAULT 0;");
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN bahan_kimia_sd DECIMAL(10,2) DEFAULT 0;");
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN campuran_hari_ini DECIMAL(10,2) DEFAULT 0;");
    $conn->exec("ALTER TABLE tr_pemetaan ADD COLUMN campuran_sd DECIMAL(10,2) DEFAULT 0;");
    echo "Columns added successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
