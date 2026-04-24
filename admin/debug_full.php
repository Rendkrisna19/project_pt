<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Simulasi action 'list' untuk Desember 2025
$kebun_id = 1;
$unit_id = 2;
$tahun = 2025;
$bulan = 12;

// Ambil Data Master Pekerjaan
$master = $conn->query("SELECT * FROM md_jenis_pekerjaan_kertas_kerja WHERE is_active=1 ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "Master pekerjaan: " . count($master) . " records\n";

// Ambil Rencana (Plano)
$sqlPlan = "SELECT p.*, m.nama as nama_job, m.kategori, m.satuan as satuan_def
            FROM tr_kertas_kerja_plano p
            JOIN md_jenis_pekerjaan_kertas_kerja m ON m.id = p.jenis_pekerjaan_id
            WHERE p.kebun_id = :k AND p.unit_id = :u AND p.bulan = :b AND p.tahun = :t
            ORDER BY p.blok_rencana ASC";
$st = $conn->prepare($sqlPlan);
$st->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':b'=>$bulan, ':t'=>$tahun]);
$plans = $st->fetchAll(PDO::FETCH_ASSOC);
echo "Plans: " . count($plans) . " records\n";

// Group Plans by Job ID
$planGroup = [];
foreach($plans as $p) $planGroup[$p['jenis_pekerjaan_id']][] = $p;

// Ambil Realisasi Harian
$tglStart = "$tahun-$bulan-01";
$tglEnd = date("Y-m-t", strtotime($tglStart));

$sqlDaily = "SELECT kertas_kerja_plano_id, DAY(tanggal) as hari, SUM(fisik) as val 
             FROM tr_kertas_kerja_harian 
             WHERE kebun_id=:k AND unit_id=:u AND tanggal BETWEEN :s AND :e
             GROUP BY kertas_kerja_plano_id, tanggal";
$st2 = $conn->prepare($sqlDaily);
$st2->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':s'=>$tglStart, ':e'=>$tglEnd]);
$dailies = $st2->fetchAll(PDO::FETCH_ASSOC);
echo "Dailies: " . count($dailies) . " records\n";

$dailyMap = [];
foreach($dailies as $d) $dailyMap[$d['kertas_kerja_plano_id']][$d['hari']] = $d['val'];

// Build Response Structure
$result = [];

foreach($master as $m) {
    $jid = $m['id'];
    $items = [];
    $subtotal_fisik_rencana = 0;
    $subtotal_realisasi_hari = array_fill(1, 31, 0);
    $subtotal_realisasi_total = 0;

    if(isset($planGroup[$jid])) {
        foreach($planGroup[$jid] as $p) {
            $pid = $p['id'];
            $item = [
                'id_plan' => $pid,
                'blok' => $p['blok_rencana'],
                'rencana' => (float)$p['fisik_rencana'],
                'satuan' => $p['satuan_rencana'],
                'days' => []
            ];
            
            $rowTotal = 0;
            for($i=1; $i<=31; $i++) {
                $val = (float)($dailyMap[$pid][$i] ?? 0);
                $item['days'][$i] = $val;
                $rowTotal += $val;
                $subtotal_realisasi_hari[$i] += $val;
            }
            $item['total_realisasi'] = $rowTotal;
            
            $subtotal_fisik_rencana += $item['rencana'];
            $subtotal_realisasi_total += $rowTotal;
            
            $items[] = $item;
        }
    }

    $result[] = [
        'job_id' => $jid,
        'job_nama' => $m['nama'],
        'items' => $items,
        'subtotal_rencana' => $subtotal_fisik_rencana,
        'subtotal_realisasi' => $subtotal_realisasi_total,
        'subtotal_hari' => $subtotal_realisasi_hari
    ];
}

echo "\nResult structure:\n";
print_r($result);
?>