<?php
/**
 * Script 1x pakai: Hapus UNIQUE constraint pada kolom nama_unit di tabel units.
 * Sekarang nama_unit hanya perlu unik per kebun_id (dicek di PHP, bukan di DB).
 * 
 * Cara pakai: buka di browser → http://localhost/ptpn/fix_units_unique.php
 * Setelah selesai, file ini bisa dihapus.
 */

require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "<h2>Fix UNIQUE constraint — units.nama_unit</h2><pre>";

try {
    // Cari nama index UNIQUE pada kolom nama_unit
    $stmt = $conn->query("SHOW INDEX FROM units WHERE Column_name = 'nama_unit' AND Non_unique = 0 AND Key_name != 'PRIMARY'");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($indexes)) {
        echo "Tidak ada UNIQUE index ditemukan pada units.nama_unit — sudah aman!\n";
    } else {
        foreach ($indexes as $idx) {
            $indexName = $idx['Key_name'];
            echo "Menemukan UNIQUE index: <b>$indexName</b> pada kolom nama_unit\n";
            
            // Drop index
            $conn->exec("ALTER TABLE units DROP INDEX `$indexName`");
            echo "  → Index <b>$indexName</b> berhasil dihapus!\n";
        }
    }

    echo "\n========================================\n";
    echo "SELESAI! Tabel units sekarang mengizinkan nama_unit yang sama di kebun berbeda.\n";
    echo "File ini aman dihapus sekarang.\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";
