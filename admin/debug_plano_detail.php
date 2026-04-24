<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Cek plano dengan id=1
$stmt = $conn->prepare("SELECT * FROM tr_kertas_kerja_plano WHERE id = ?");
$stmt->execute([1]);
$plano = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Plano id=1:\n";
print_r($plano);

// Cek semua plano untuk Des 2025
$kebun_id = 1;
$unit_id = 2;
$tahun = 2025;
$bulan = 12;

$sqlPlan = "SELECT * FROM tr_kertas_kerja_plano 
            WHERE kebun_id = :k AND unit_id = :u AND bulan = :b AND tahun = :t";

$st = $conn->prepare($sqlPlan);
$st->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':b'=>$bulan, ':t'=>$tahun]);
$plans = $st->fetchAll(PDO::FETCH_ASSOC);
echo "\nSemua plano untuk Des 2025:\n";
print_r($plans);
?>