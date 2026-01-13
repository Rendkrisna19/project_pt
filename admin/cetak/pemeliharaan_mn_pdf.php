<?php
// pages/cetak/pemeliharaan_mn_pdf.php
// Modifikasi: Format angka konsisten 2 desimal (xx,xx)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // ===== 1. TANGKAP FILTER =====
    $f_tahun    = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int)$_GET['tahun'] : (int)date('Y');
    $f_kebun    = isset($_GET['kebun_id']) && $_GET['kebun_id'] != 0 ? (int)$_GET['kebun_id'] : null;
    $f_jenis    = isset($_GET['jenis']) && $_GET['jenis'] !== '' ? $_GET['jenis'] : null;
    $f_hkId     = isset($_GET['hk']) && $_GET['hk'] !== '' ? (int)$_GET['hk'] : 0; 
    $f_stoodId  = isset($_GET['stood_id']) && $_GET['stood_id'] != 0 ? (int)$_GET['stood_id'] : 0;
    $f_ket      = isset($_GET['ket']) ? $_GET['ket'] : '';

    // ===== 2. BUILD QUERY =====
    $where = "WHERE p.tahun = :tahun";
    $params = [':tahun' => $f_tahun];

    if ($f_jenis) { 
        $where .= " AND p.jenis_nama = :jenis"; 
        $params[':jenis'] = $f_jenis; 
    }
    
    if ($f_hkId > 0) {
        $stmtHk = $conn->prepare("SELECT kode FROM md_tenaga WHERE id = ?");
        $stmtHk->execute([$f_hkId]);
        $kodeHk = $stmtHk->fetchColumn();

        if ($kodeHk) {
            $where .= " AND p.hk = :hk_kode";
            $params[':hk_kode'] = $kodeHk;
        } else {
            $where .= " AND 1=0"; 
        }
    }

    if ($f_kebun) { 
        $where .= " AND p.kebun_id = :kebun"; 
        $params[':kebun'] = $f_kebun; 
    }
    
    if ($f_ket) { 
        $where .= " AND p.ket LIKE :ket"; 
        $params[':ket'] = "%$f_ket%"; 
    }

    $having = "";
    if ($f_stoodId > 0) {
        $having = "HAVING stood_id_fix = :sid";
        $params[':sid'] = $f_stoodId;
    }

    // QUERY UTAMA
    $sql = "SELECT 
                p.*, 
                COALESCE(NULLIF(p.stood_id,0), m.id) AS stood_id_fix,
                COALESCE(p.stood, m.nama)            AS stood_name_fix
            FROM pemeliharaan_mn p
            LEFT JOIN md_jenis_bibitmn m ON TRIM(UPPER(m.nama)) = TRIM(UPPER(p.stood))
            $where
            $having
            ORDER BY stood_name_fix ASC, p.kebun_nama ASC, p.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouping Data
    $grouped = [];
    foreach($rows as $r) {
        $stood = $r['stood_name_fix'] ?? '(Tanpa Stood)';
        $grouped[$stood][] = $r;
    }

} catch (Exception $e) {
    exit("Error: " . $e->getMessage());
}

// ===== HELPER FORMAT (INTI MODIFIKASI) =====
// number_format(angka, jumlah_desimal, pemisah_desimal, pemisah_ribuan)
function nf($val) { 
    return number_format((float)$val, 2, ',', '.'); 
}

// Jika 0 tampilkan strip (-), jika tidak format angka desimal
function dash($val) { 
    return ((float)$val == 0) ? '-' : nf($val); 
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pemeliharaan MN</title>
    <style>
        @page { margin: 10mm 10mm; size: A4 landscape; }
        body { font-family: sans-serif; font-size: 9px; color: #333; }
        
        .header-box { background-color: #059fd3; color: white; padding: 10px; border-radius: 5px; margin-bottom: 10px; }
        .table-header { background-color: #059fd3; color: white; font-weight: bold; }
        .group-row { background-color: #e0f7fa; color: #0277bd; font-weight: bold; }
        .sum-row { background-color: #f0fdf4; font-weight: bold; color: #14532d; border-top: 2px solid #86efac; }
        
        h2 { margin: 0; font-size: 16px; }
        p { margin: 2px 0; font-size: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; vertical-align: middle; word-wrap: break-word; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .neg { color: #16a34a; } 
        .pos { color: #dc2626; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>

    <div class="header-box">
        <h2>REKAPITULASI PEMELIHARAAN MN</h2>
        <p>Tahun: <?= $f_tahun ?> | Dicetak: <?= date('d-m-Y H:i') ?></p>
        <?php if($f_kebun): ?><p>Filter Kebun ID: <?= $f_kebun ?></p><?php endif; ?>
    </div>

    <table>
        <thead>
            <tr class="table-header">
                <th rowspan="2" style="width: 8%">Kebun</th>
                <th rowspan="2" style="width: 10%">Jenis Pekerjaan</th>
                <th rowspan="2" style="width: 8%">Ket</th>
                <th rowspan="2" style="width: 3%">HK</th>
                <th rowspan="2" style="width: 3%">Sat</th>
                <th rowspan="2" style="width: 6%">Anggaran</th>
                <th colspan="12">Realisasi Bulanan</th>
                <th rowspan="2" style="width: 6%">Total Real</th>
                <th rowspan="2" style="width: 5%">+/-</th>
                <th rowspan="2" style="width: 4%">%</th>
            </tr>
            <tr class="table-header">
                <?php foreach(['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'] as $m): ?>
                    <th><?= $m ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="19" class="text-center">Tidak ada data.</td></tr>
            <?php else: ?>
                
                <?php foreach($grouped as $stoodName => $items): 
                    $sumAnggaran = 0;
                    $sumReal = 0;
                    $sumBulan = array_fill_keys(['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'], 0);
                ?>
                    <tr class="group-row">
                        <td colspan="19" style="text-align: left; padding-left: 10px;">
                            STOOD: <?= htmlspecialchars($stoodName) ?>
                        </td>
                    </tr>

                    <?php foreach($items as $row): 
                        $totalRow = 0;
                        $months = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'];
                        foreach($months as $m) {
                            $val = (float)($row[$m] ?? 0);
                            $totalRow += $val;
                            $sumBulan[$m] += $val;
                        }
                        
                        $anggaran = (float)($row['anggaran_tahun'] ?? 0);
                        $delta = $totalRow - $anggaran;
                        $persen = $anggaran > 0 ? ($totalRow / $anggaran * 100) : 0;
                        
                        $sumAnggaran += $anggaran;
                        $sumReal += $totalRow;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['kebun_nama']) ?></td>
                        <td><?= htmlspecialchars($row['jenis_nama']) ?></td>
                        <td><?= htmlspecialchars($row['ket']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['hk']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['satuan']) ?></td>
                        <td class="text-right"><?= dash($anggaran) ?></td>
                        
                        <?php foreach($months as $m): ?>
                            <td class="text-right"><?= dash($row[$m]) ?></td>
                        <?php endforeach; ?>

                        <td class="text-right font-bold"><?= dash($totalRow) ?></td>
                        <td class="text-right <?= $delta > 0 ? 'pos' : ($delta < 0 ? 'neg':'') ?>"><?= dash($delta) ?></td>
                        <td class="text-right"><?= dash($persen) ?>%</td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="sum-row">
                        <td colspan="5" class="text-right">TOTAL <?= strtoupper($stoodName) ?></td>
                        <td class="text-right"><?= dash($sumAnggaran) ?></td>
                        <?php foreach($months as $m): ?>
                            <td class="text-right"><?= dash($sumBulan[$m]) ?></td>
                        <?php endforeach; ?>
                        <td class="text-right"><?= dash($sumReal) ?></td>
                        <td class="text-right"><?= dash($sumReal - $sumAnggaran) ?></td>
                        
                        <?php 
                            $totalPersen = $sumAnggaran > 0 ? ($sumReal / $sumAnggaran * 100) : 0; 
                        ?>
                        <td class="text-right"><?= nf($totalPersen) ?>%</td>
                    </tr>

                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Rekap_MN_Tahun_{$f_tahun}.pdf", ["Attachment" => false]);
?>