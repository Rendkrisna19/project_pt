<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Cek data plano untuk Desember 2025
$kebun_id = 1;
$unit_id = 2;
$tahun = 2025;
$bulan = 12;

$sqlPlan = "SELECT COUNT(*) as total FROM tr_kertas_kerja_plano 
            WHERE kebun_id = :k AND unit_id = :u AND bulan = :b AND tahun = :t";

$st = $conn->prepare($sqlPlan);
$st->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':b'=>$bulan, ':t'=>$tahun]);
$result = $st->fetch(PDO::FETCH_ASSOC);
echo "Total plano untuk Des 2025: " . $result['total'] . "\n";

// Cek untuk April 2026
$tahun = 2026;
$bulan = 4;
$st->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':b'=>$bulan, ':t'=>$tahun]);
$result = $st->fetch(PDO::FETCH_ASSOC);
echo "Total plano untuk Apr 2026: " . $result['total'] . "\n";
?>