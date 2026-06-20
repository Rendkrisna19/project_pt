<?php
/**
 * Migration: Create table tr_mcs_bulanan
 * Data realisasi pekerjaan bulanan (MCS Bulanan)
 * 
 * Cara pakai: buka di browser → http://localhost/ptpn/migrate_tr_mcs_bulanan.php
 */
echo "<pre>";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=db_ptpn_tes', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if table already exists
    $check = $pdo->query("SHOW TABLES LIKE 'tr_mcs_bulanan'")->fetch();
    if ($check) {
        echo "Table 'tr_mcs_bulanan' already exists.\n";
        echo "Dropping and recreating...\n";
        $pdo->exec("DROP TABLE tr_mcs_bulanan");
    }

    $pdo->exec("CREATE TABLE tr_mcs_bulanan (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kebun_id INT UNSIGNED NOT NULL,
        unit_id INT NOT NULL,
        jenis_pekerjaan_bulanan_id INT UNSIGNED NOT NULL,
        objek_pekerjaan VARCHAR(255) DEFAULT NULL,
        geojson LONGTEXT DEFAULT NULL,
        latitude VARCHAR(50) DEFAULT NULL,
        longitude VARCHAR(50) DEFAULT NULL,
        warna VARCHAR(20) DEFAULT NULL,
        bulan_1 DECIMAL(10,2) DEFAULT 0,
        bulan_2 DECIMAL(10,2) DEFAULT 0,
        bulan_3 DECIMAL(10,2) DEFAULT 0,
        bulan_4 DECIMAL(10,2) DEFAULT 0,
        bulan_5 DECIMAL(10,2) DEFAULT 0,
        bulan_6 DECIMAL(10,2) DEFAULT 0,
        bulan_7 DECIMAL(10,2) DEFAULT 0,
        bulan_8 DECIMAL(10,2) DEFAULT 0,
        bulan_9 DECIMAL(10,2) DEFAULT 0,
        bulan_10 DECIMAL(10,2) DEFAULT 0,
        bulan_11 DECIMAL(10,2) DEFAULT 0,
        bulan_12 DECIMAL(10,2) DEFAULT 0,
        keterangan TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_kebun_unit (kebun_id, unit_id),
        INDEX idx_jp_bulanan (jenis_pekerjaan_bulanan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "SUCCESS: Table 'tr_mcs_bulanan' created.\n";
    echo "Columns: id, kebun_id, unit_id, jenis_pekerjaan_bulanan_id, objek_pekerjaan,\n";
    echo "         geojson, latitude, longitude, warna,\n";
    echo "         bulan_1..bulan_12, keterangan, created_at, updated_at\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";
