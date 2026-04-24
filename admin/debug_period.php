<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Cek data berdasarkan bulan dan tahun
$tahun = 2026;
$bulan = 4;
$tglStart = "$tahun-$bulan-01";
$tglEnd = date("Y-m-t", strtotime($tglStart));

echo "Mencari data untuk bulan $bulan tahun $tahun ($tglStart sampai $tglEnd)\n";

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM tr_kertas_kerja_harian WHERE tanggal BETWEEN ? AND ?");
$stmt->execute([$tglStart, $tglEnd]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total data untuk periode tersebut: " . $result['total'] . "\n";

// Cek semua bulan yang ada data
$stmt = $conn->query("SELECT DISTINCT YEAR(tanggal) as tahun, MONTH(tanggal) as bulan, tanggal FROM tr_kertas_kerja_harian ORDER BY tanggal");
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Periode yang ada data:\n";
foreach($periods as $p) {
    echo "Tahun: {$p['tahun']}, Bulan: {$p['bulan']}\n";
}
?>