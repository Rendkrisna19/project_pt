<?php
/**
 * Script 1x pakai untuk RECALCULATE semua nilai S/D (Sampai Dengan / Kumulatif)
 * di tabel tr_pemetaan. 
 * 
 * Cara pakai: buka di browser → c:\laragon\www\ptpn\recalculate_sd.php
 * Setelah selesai, file ini bisa dihapus.
 */

require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "<h2>Recalculate S/D (Kumulatif) — tr_pemetaan</h2>";
echo "<pre>";

try {
    // Ambil semua data, diurutkan per kelompok (kebun, unit, jp) lalu per tanggal
    $sql = "SELECT id, kebun_id, unit_id, jenis_pekerjaan_id, blok_nama, tanggal_realisasi,
                   fisik_hari_ini, hk_hari_ini, bahan_kimia_hari_ini, campuran_hari_ini,
                   fisik_sd, hk_sd, bahan_kimia_sd, campuran_sd
            FROM tr_pemetaan 
            ORDER BY kebun_id ASC, unit_id ASC, jenis_pekerjaan_id ASC, 
                     tanggal_realisasi ASC, id ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total baris ditemukan: " . count($rows) . "\n\n";

    $updated = 0;
    $prevKey = null;
    $prevFisikSd = 0;
    $prevHkSd = 0;
    $prevBahanKimiaSd = 0;
    $prevCampuranSd = 0;

    foreach ($rows as $r) {
        // Key = kelompok kumulatif (kebun + unit + jenis pekerjaan, lintas blok)
        $key = $r['kebun_id'] . '_' . $r['unit_id'] . '_' . $r['jenis_pekerjaan_id'];

        if ($key !== $prevKey) {
            // Kelompok baru → reset kumulatif ke 0
            $prevFisikSd = 0;
            $prevHkSd = 0;
            $prevBahanKimiaSd = 0;
            $prevCampuranSd = 0;
        }

        // Hitung S/D yang benar
        $correctFisikSd = $prevFisikSd + (float)$r['fisik_hari_ini'];
        $correctHkSd = $prevHkSd + (float)$r['hk_hari_ini'];
        $correctBahanKimiaSd = $prevBahanKimiaSd + (float)$r['bahan_kimia_hari_ini'];
        $correctCampuranSd = $prevCampuranSd + (float)$r['campuran_hari_ini'];

        // Cek apakah perlu diupdate
        $needsUpdate = (
            abs((float)$r['fisik_sd'] - $correctFisikSd) > 0.001 ||
            abs((float)$r['hk_sd'] - $correctHkSd) > 0.001 ||
            abs((float)$r['bahan_kimia_sd'] - $correctBahanKimiaSd) > 0.001 ||
            abs((float)$r['campuran_sd'] - $correctCampuranSd) > 0.001
        );

        if ($needsUpdate) {
            $updSql = "UPDATE tr_pemetaan SET 
                        fisik_sd = ?, hk_sd = ?, bahan_kimia_sd = ?, campuran_sd = ?
                       WHERE id = ?";
            $updStmt = $conn->prepare($updSql);
            $updStmt->execute([$correctFisikSd, $correctHkSd, $correctBahanKimiaSd, $correctCampuranSd, $r['id']]);
            
            echo sprintf(
                "FIXED id=%d | %s | Blok %s | Tgl %s\n  fisik: %.2f → %.2f | hk: %.2f → %.2f | bk: %.2f → %.2f | cmp: %.2f → %.2f\n",
                $r['id'],
                "K{$r['kebun_id']}_U{$r['unit_id']}_JP{$r['jenis_pekerjaan_id']}",
                $r['blok_nama'],
                $r['tanggal_realisasi'],
                (float)$r['fisik_sd'], $correctFisikSd,
                (float)$r['hk_sd'], $correctHkSd,
                (float)$r['bahan_kimia_sd'], $correctBahanKimiaSd,
                (float)$r['campuran_sd'], $correctCampuranSd
            );
            $updated++;
        }

        // Update kumulatif untuk baris berikutnya
        $prevFisikSd = $correctFisikSd;
        $prevHkSd = $correctHkSd;
        $prevBahanKimiaSd = $correctBahanKimiaSd;
        $prevCampuranSd = $correctCampuranSd;
        $prevKey = $key;
    }

    echo "\n========================================\n";
    echo "SELESAI! Baris diupdate: $updated dari " . count($rows) . "\n";
    echo "========================================\n";
    echo "File ini aman dihapus sekarang.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";
