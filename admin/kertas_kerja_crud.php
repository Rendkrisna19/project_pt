<?php
// kertas_kerja_crud.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

ini_set('display_errors', '0');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']); exit; 
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    // --- 1. LIST DATA (GROUPED) ---
    if ($action === 'list') {
        $kebun_id = $_POST['kebun_id'];
        $unit_id  = $_POST['unit_id'];
        $tahun    = $_POST['tahun'];
        $bulan    = $_POST['bulan'];

        // Ambil Data Master Pekerjaan (Semua yg aktif)
        $master = $conn->query("SELECT * FROM md_jenis_pekerjaan_kertas_kerja WHERE is_active=1 ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Ambil Rencana (Plano)
        $sqlPlan = "SELECT p.*, m.nama as nama_job, m.kategori, m.satuan as satuan_def
                    FROM tr_kertas_kerja_plano p
                    JOIN md_jenis_pekerjaan_kertas_kerja m ON m.id = p.jenis_pekerjaan_id
                    WHERE p.kebun_id = :k AND p.unit_id = :u AND p.bulan = :b AND p.tahun = :t
                    ORDER BY p.blok_rencana ASC";
        $st = $conn->prepare($sqlPlan);
        $st->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':b'=>$bulan, ':t'=>$tahun]);
        $plans = $st->fetchAll(PDO::FETCH_ASSOC);

        // Group Plans by Job ID
        $planGroup = [];
        foreach($plans as $p) $planGroup[$p['jenis_pekerjaan_id']][] = $p;

        // Ambil Realisasi Harian
        $tglStart = "$tahun-$bulan-01";
        $tglEnd   = date("Y-m-t", strtotime($tglStart));
        
        $sqlDaily = "SELECT kertas_kerja_plano_id, DAY(tanggal) as hari, SUM(fisik) as val 
                     FROM tr_kertas_kerja_harian 
                     WHERE kebun_id=:k AND unit_id=:u AND tanggal BETWEEN :s AND :e
                     GROUP BY kertas_kerja_plano_id, tanggal";
        $st2 = $conn->prepare($sqlDaily);
        $st2->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':s'=>$tglStart, ':e'=>$tglEnd]);
        $dailies = $st2->fetchAll(PDO::FETCH_ASSOC);

        $dailyMap = [];
        foreach($dailies as $d) $dailyMap[$d['kertas_kerja_plano_id']][$d['hari']] = $d['val'];

        // Build Response Structure
        $result = [];
        
        foreach($master as $m) {
            $jid = $m['id'];
            
            // [FIX] Jangan skip jika planGroup kosong!
            // Kita tetap buat struktur group-nya agar Header Pekerjaan muncul di Frontend.
            
            $items = [];
            $subtotal_fisik_rencana = 0;
            $subtotal_realisasi_hari = array_fill(1, 31, 0);
            $subtotal_realisasi_total = 0;

            if(isset($planGroup[$jid])) {
                foreach($planGroup[$jid] as $p) {
                    $pid = $p['id'];
                    $item = [
                        'id_plan'   => $pid,
                        'blok'      => $p['blok_rencana'],
                        'rencana'   => (float)$p['fisik_rencana'],
                        'satuan'    => $p['satuan_rencana'],
                        'days'      => []
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
                'job_id'   => $jid,
                'job_nama' => $m['nama'],
                'kategori' => $m['kategori'], 
                'satuan_default' => $m['satuan'],
                'items'    => $items, // Bisa kosong array-nya
                'subtotal' => [
                    'rencana'   => $subtotal_fisik_rencana,
                    'realisasi' => $subtotal_realisasi_total,
                    'days'      => $subtotal_realisasi_hari
                ]
            ];
        }

        echo json_encode(['success'=>true, 'data'=>$result]);
        exit;
    }

    // --- 2. AUTO SAVE (SIMPAN PER CELL) ---
    if ($action === 'store_cell') {
        $pid   = $_POST['id_plan'];
        $jid   = $_POST['id_job'];
        $day   = $_POST['day'];
        $val   = $_POST['value']; // Float
        
        $kebun = $_POST['kebun_id'];
        $unit  = $_POST['unit_id'];
        $tahun = $_POST['tahun'];
        $bulan = $_POST['bulan'];
        
        $date = "$tahun-$bulan-$day";

        // Logic: Delete then Insert (Upsert simple)
        $del = $conn->prepare("DELETE FROM tr_kertas_kerja_harian WHERE kertas_kerja_plano_id=? AND tanggal=?");
        $del->execute([$pid, $date]);

        if ($val > 0) {
            $ins = $conn->prepare("INSERT INTO tr_kertas_kerja_harian (kebun_id, unit_id, tanggal, jenis_pekerjaan_id, kertas_kerja_plano_id, fisik) VALUES (?,?,?,?,?,?)");
            $ins->execute([$kebun, $unit, $date, $jid, $pid, $val]);
        }

        echo json_encode(['success'=>true]);
        exit;
    }

    // --- 3. STORE PLAN (ADD/EDIT) ---
    if ($action === 'store_plan') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $kebun_id = $_POST['kebun_id'];
        $unit_id  = $_POST['unit_id'];
        $bulan    = $_POST['bulan'];
        $tahun    = $_POST['tahun'];
        $job_id   = $_POST['jenis_pekerjaan_id'];
        $blok     = strtoupper(trim($_POST['blok']));
        $fisik    = $_POST['fisik'];
        $satuan   = $_POST['satuan'];

        if ($id > 0) {
            $sql = "UPDATE tr_kertas_kerja_plano SET jenis_pekerjaan_id=:j, blok_rencana=:blk, fisik_rencana=:fis, satuan_rencana=:sat WHERE id=:id";
            $conn->prepare($sql)->execute([':j'=>$job_id, ':blk'=>$blok, ':fis'=>$fisik, ':sat'=>$satuan, ':id'=>$id]);
        } else {
            $sql = "INSERT INTO tr_kertas_kerja_plano (kebun_id, unit_id, bulan, tahun, jenis_pekerjaan_id, blok_rencana, fisik_rencana, satuan_rencana) VALUES (?,?,?,?,?,?,?,?) 
                    ON DUPLICATE KEY UPDATE fisik_rencana=VALUES(fisik_rencana)";
            $conn->prepare($sql)->execute([$kebun_id, $unit_id, $bulan, $tahun, $job_id, $blok, $fisik, $satuan]);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    // --- 4. DELETE PLAN ---
    if ($action === 'delete_plan') {
        $id = (int)$_POST['id'];
        $conn->prepare("DELETE FROM tr_kertas_kerja_harian WHERE kertas_kerja_plano_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM tr_kertas_kerja_plano WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>