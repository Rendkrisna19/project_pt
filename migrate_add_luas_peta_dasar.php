<?php
/**
 * Migration: Tambah kolom `luas_ha` + buat `peta_kerja_foto` nullable
 * pada tr_pemetaan_peta_dasar
 *
 * - luas_ha: nilai LUAS statik per unit (untuk header PDF "PETA unit LUAS: ...").
 *   Disimpan di baris khusus MCS Bulanan (jenis_pekerjaan_id = 99999), berlaku semua tahun.
 * - peta_kerja_foto dibuat NULLABLE agar baris (unit_id, 99999) bisa ada hanya untuk
 *   menyimpan LUAS walaupun base map belum diupload.
 *
 * Cara pakai: buka di browser → http://localhost/ptpn/migrate_add_luas_peta_dasar.php
 */
require 'config/database.php';

echo "<pre>";
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1) Cek apakah kolom luas_ha sudah ada
    $checkLuas = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tr_pemetaan_peta_dasar'
          AND COLUMN_NAME = 'luas_ha'
    ");
    $checkLuas->execute();
    $luasExists = (int)$checkLuas->fetchColumn();

    if ($luasExists > 0) {
        echo "Kolom 'luas_ha' sudah ada di tabel tr_pemetaan_peta_dasar. Tidak ada perubahan.\n";
    } else {
        $pdo->exec("ALTER TABLE tr_pemetaan_peta_dasar ADD COLUMN luas_ha DECIMAL(10,2) DEFAULT NULL AFTER peta_kerja_foto");
        echo "SUCCESS: Kolom 'luas_ha' (DECIMAL 10,2, nullable) ditambahkan ke tr_pemetaan_peta_dasar.\n";
    }

    // 2) Buat peta_kerja_foto nullable (cek dulu apakah masih NOT NULL)
    $checkFoto = $pdo->prepare("
        SELECT IS_NULLABLE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tr_pemetaan_peta_dasar'
          AND COLUMN_NAME = 'peta_kerja_foto'
    ");
    $checkFoto->execute();
    $isNullable = $checkFoto->fetchColumn();

    if ($isNullable === 'NO') {
        $pdo->exec("ALTER TABLE tr_pemetaan_peta_dasar MODIFY peta_kerja_foto VARCHAR(255) NULL DEFAULT NULL");
        echo "SUCCESS: Kolom 'peta_kerja_foto' sekarang NULLABLE.\n";
    } else {
        echo "Kolom 'peta_kerja_foto' sudah nullable. Tidak ada perubahan.\n";
    }

    echo "Selesai. Baris (unit_id, jenis_pekerjaan_id=99999) kini bisa menyimpan LUAS tanpa base map.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";
