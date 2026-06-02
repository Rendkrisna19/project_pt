<?php
require 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
try {
    // 1. Get all data
    $stmt = $pdo->query("SELECT * FROM tr_pemetaan");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Drop table
    $pdo->exec("DROP TABLE IF EXISTS tr_pemetaan");

    // 3. Create table as MyISAM
    $sql = "CREATE TABLE `tr_pemetaan` (
      `id` int NOT NULL AUTO_INCREMENT,
      `kebun_id` int NOT NULL,
      `unit_id` int NOT NULL,
      `blok_id` int NOT NULL,
      `jenis_pekerjaan_id` int unsigned DEFAULT NULL,
      `jenis_aset` varchar(100) NOT NULL,
      `geojson` longtext,
      `latitude` varchar(50) DEFAULT NULL,
      `longitude` varchar(50) DEFAULT NULL,
      `warna` varchar(20) DEFAULT '#0891b2',
      `foto` varchar(255) DEFAULT NULL,
      `keterangan` text,
      `tanggal_realisasi` date DEFAULT NULL,
      `fisik_hari_ini` decimal(10,2) DEFAULT NULL,
      `fisik_sd` decimal(10,2) DEFAULT NULL,
      `hk_hari_ini` decimal(10,2) DEFAULT NULL,
      `hk_sd` decimal(10,2) DEFAULT NULL,
      `bahan_kimia_hari_ini` decimal(10,2) DEFAULT NULL,
      `bahan_kimia_sd` decimal(10,2) DEFAULT NULL,
      `campuran_hari_ini` decimal(10,2) DEFAULT NULL,
      `campuran_sd` decimal(10,2) DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `file_type` enum('satelit','image') DEFAULT 'satelit',
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $pdo->exec($sql);

    // 4. Re-insert data
    if (!empty($data)) {
        $cols = array_keys($data[0]);
        $colStr = implode(", ", $cols);
        $placeholders = implode(", ", array_fill(0, count($cols), "?"));
        $insertSql = "INSERT INTO tr_pemetaan ($colStr) VALUES ($placeholders)";
        $insertStmt = $pdo->prepare($insertSql);
        
        foreach($data as $row) {
            $insertStmt->execute(array_values($row));
        }
    }

    echo "tr_pemetaan converted to MyISAM successfully. Rows restored: " . count($data) . "\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
