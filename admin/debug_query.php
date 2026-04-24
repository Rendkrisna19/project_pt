<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Simulasi parameter dari frontend
$kebun_id = 1; // dari md_kebun LIMIT 1
$unit_id = 2;  // asumsikan unit_id=2
$tahun = 2026;
$bulan = 4;

echo "Parameter: kebun_id=$kebun_id, unit_id=$unit_id, tahun=$tahun, bulan=$bulan\n";

$tglStart = "$tahun-$bulan-01";
$tglEnd = date("Y-m-t", strtotime($tglStart));

echo "Rentang tanggal: $tglStart sampai $tglEnd\n";

// Query yang sama seperti di crud
$sqlDaily = "SELECT kertas_kerja_plano_id, DAY(tanggal) as hari, SUM(fisik) as val 
             FROM tr_kertas_kerja_harian 
             WHERE kebun_id=:k AND unit_id=:u AND tanggal BETWEEN :s AND :e
             GROUP BY kertas_kerja_plano_id, tanggal";

$st2 = $conn->prepare($sqlDaily);
$st2->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':s'=>$tglStart, ':e'=>$tglEnd]);
$dailies = $st2->fetchAll(PDO::FETCH_ASSOC);

echo "Hasil query daily: " . count($dailies) . " records\n";
print_r($dailies);

// Coba dengan bulan yang ada data
$tahun = 2025;
$bulan = 12;
$tglStart = "$tahun-$bulan-01";
$tglEnd = date("Y-m-t", strtotime($tglStart));

echo "\nCoba dengan bulan yang ada data: tahun=$tahun, bulan=$bulan\n";
echo "Rentang tanggal: $tglStart sampai $tglEnd\n";

$st2->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':s'=>$tglStart, ':e'=>$tglEnd]);
$dailies = $st2->fetchAll(PDO::FETCH_ASSOC);

echo "Hasil query daily: " . count($dailies) . " records\n";
print_r($dailies);
?>