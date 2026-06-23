<?php
/**
 * Migration: Tambah kolom `satuan` pada md_jenis_pekerjaan_bulanan
 *
 * Kolom satuan dipakai untuk MCS Bulanan agar setiap jenis pekerjaan bisa
 * punya satuan berbeda (Ha, Pokok, Sak, Kg, dll) dan terbaca otomatis di export PDF.
 *
 * Cara pakai: buka di browser → http://localhost/ptpn/migrate_add_satuan_jp_bulanan.php
 */
require 'config/database.php';

echo "<pre>";
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cek apakah kolom satuan sudah ada (idempoten)
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'md_jenis_pekerjaan_bulanan'
          AND COLUMN_NAME = 'satuan'
    ");
    $check->execute();
    $exists = (int)$check->fetchColumn();

    if ($exists > 0) {
        echo "Kolom 'satuan' sudah ada di tabel md_jenis_pekerjaan_bulanan. Tidak ada perubahan.\n";
    } else {
        $pdo->exec("ALTER TABLE md_jenis_pekerjaan_bulanan ADD COLUMN satuan VARCHAR(50) DEFAULT NULL AFTER keterangan");
        echo "SUCCESS: Kolom 'satuan' (VARCHAR 50, nullable) ditambahkan ke md_jenis_pekerjaan_bulanan.\n";
    }

    echo "Struktur akhir kolom: id, kebun_id, nama, keterangan, satuan, created_at, updated_at\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";
