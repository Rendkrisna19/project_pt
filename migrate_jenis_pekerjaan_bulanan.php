<?php
/**
 * Migration: Create table md_jenis_pekerjaan_bulanan
 * Master data Jenis Pekerjaan khusus MCS Bulanan
 * 
 * Cara pakai: buka di browser → http://localhost/ptpn/migrate_jenis_pekerjaan_bulanan.php
 */
echo "<pre>";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=db_ptpn_tes', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table already exists
    $check = $pdo->query("SHOW TABLES LIKE 'md_jenis_pekerjaan_bulanan'")->fetch();
    if ($check) {
        echo "Table 'md_jenis_pekerjaan_bulanan' already exists. Skipping.\n";
        exit;
    }

    $pdo->exec("CREATE TABLE md_jenis_pekerjaan_bulanan (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kebun_id INT UNSIGNED NOT NULL,
        nama VARCHAR(255) NOT NULL,
        keterangan TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_kebun_nama (kebun_id, nama),
        CONSTRAINT fk_jpb_kebun FOREIGN KEY (kebun_id) REFERENCES md_kebun(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "SUCCESS: Table 'md_jenis_pekerjaan_bulanan' created.\n";
    echo "Columns: id, kebun_id, nama, keterangan, created_at, updated_at\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";
