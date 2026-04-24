<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Cek total data
$stmt = $conn->query("SELECT COUNT(*) as total FROM tr_kertas_kerja_harian");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total data di tr_kertas_kerja_harian: " . $result['total'] . "\n";

// Cek sample data
$stmt = $conn->query("SELECT * FROM tr_kertas_kerja_harian LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Sample data:\n";
print_r($rows);

// Cek kebun_id dan unit_id yang ada
$stmt = $conn->query("SELECT DISTINCT kebun_id, unit_id FROM tr_kertas_kerja_harian");
$distinct = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Distinct kebun_id dan unit_id:\n";
print_r($distinct);
?>