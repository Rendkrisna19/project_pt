<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $columns = [
        'fisik_hari_ini', 'fisik_sd', 
        'hk_hari_ini', 'hk_sd', 
        'bahan_kimia_hari_ini', 'bahan_kimia_sd', 
        'campuran_hari_ini', 'campuran_sd'
    ];
    
    foreach ($columns as $col) {
        $conn->query("ALTER TABLE tr_pemetaan MODIFY COLUMN $col DECIMAL(20,2) NULL DEFAULT 0.00");
    }
    echo "Columns altered successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
