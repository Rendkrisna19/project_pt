<?php
// cetak/kertas_kerja_pdf.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // --- 1. Filter Parameter ---
    $unit_id  = $_GET['unit_id'] ?? '';
    $kebun_id = $_GET['kebun_id'] ?? '';
    $tahun    = $_GET['tahun'] ?? date('Y');
    $bulan    = $_GET['bulan'] ?? date('n');

    if (empty($unit_id) || empty($kebun_id)) exit("Parameter tidak lengkap.");

    // --- 2. Ambil Info Header ---
    $stmtUnit = $conn->prepare("SELECT nama_unit FROM units WHERE id = ?");
    $stmtUnit->execute([$unit_id]);
    $nama_unit = $stmtUnit->fetchColumn() ?: '-';

    $stmtKebun = $conn->prepare("SELECT nama_kebun FROM md_kebun WHERE id = ?");
    $stmtKebun->execute([$kebun_id]);
    $nama_kebun = $stmtKebun->fetchColumn() ?: '-';

    $nama_bulan_arr = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $str_bulan = $nama_bulan_arr[(int)$bulan] ?? $bulan;

    // --- 3. LOGIKA DATA (Optimized Fetch) ---
    // 3.1 Master
    $master = $conn->query("SELECT * FROM md_jenis_pekerjaan_kertas_kerja WHERE is_active=1 ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 3.2 Rencana
    $sqlPlan = "SELECT p.*, m.nama as nama_job, m.kategori, m.satuan as satuan_def
                FROM tr_kertas_kerja_plano p
                JOIN md_jenis_pekerjaan_kertas_kerja m ON m.id = p.jenis_pekerjaan_id
                WHERE p.kebun_id = :k AND p.unit_id = :u AND p.bulan = :b AND p.tahun = :t
                ORDER BY p.blok_rencana ASC";
    $st = $conn->prepare($sqlPlan);
    $st->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':b'=>$bulan, ':t'=>$tahun]);
    $plans = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $planGroup = [];
    foreach($plans as $p) $planGroup[$p['jenis_pekerjaan_id']][] = $p;

    // 3.3 Harian
    $tglStart = "$tahun-$bulan-01";
    $tglEnd   = date("Y-m-t", strtotime($tglStart));
    $daysInMonth = (int)date('t', strtotime($tglStart));

    $sqlDaily = "SELECT kertas_kerja_plano_id, DAY(tanggal) as hari, SUM(fisik) as val 
                 FROM tr_kertas_kerja_harian 
                 WHERE kebun_id=:k AND unit_id=:u AND tanggal BETWEEN :s AND :e
                 GROUP BY kertas_kerja_plano_id, tanggal";
    $st2 = $conn->prepare($sqlDaily);
    $st2->execute([':k'=>$kebun_id, ':u'=>$unit_id, ':s'=>$tglStart, ':e'=>$tglEnd]);
    $dailies = $st2->fetchAll(PDO::FETCH_ASSOC);

    $dailyMap = [];
    foreach($dailies as $d) $dailyMap[$d['kertas_kerja_plano_id']][$d['hari']] = $d['val'];

    // Helper
    function nf($v) { return ($v == 0 || $v == '') ? '-' : number_format((float)$v, 2, ',', '.'); }
    function isSunday($y, $m, $d) { return date('N', strtotime("$y-$m-$d")) == 7; }

} catch (Exception $e) { exit('DB Error: '.$e->getMessage()); }

ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 10mm 8mm; size: A4 landscape; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color:#1e293b; }
  
  /* --- THEME CYAN --- */
  .brand { background:#0891b2; color:#fff; padding:8px; border-radius:4px; text-align:center; margin-bottom:5px; }
  .brand h1 { margin:0; font-size:14px; text-transform: uppercase; letter-spacing: 1px; }
  
  .subtitle { text-align:center; font-weight:700; color:#155e75; margin:0 0 10px; font-size:10px; }
  
  table { width:100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border:0.5px solid #94a3b8; padding:3px 2px; vertical-align:middle; font-size: 8px; }
  
  /* Header Table (Cyan Theme) */
  thead th { background:#ecfeff; color:#0e7490; font-weight:bold; text-align:center; height: 20px; border-bottom: 1px solid #0891b2; }
  
  /* Sunday Headers (Red remains red for alert) */
  .sun-head { background:#fee2e2 !important; color:#991b1b !important; border-bottom: 1px solid #ef4444; }
  .sun-col { background:#fef2f2; }
  
  /* Utilities */
  .text-right { text-align:right; }
  .text-center { text-align:center; }
  .font-bold { font-weight:bold; }
  
  /* Grouping & Subtotal */
  .group-row td { background:#f8fafc; color:#334155; font-weight:bold; text-transform:uppercase; border-top:1.5px solid #cbd5e1; }
  
  /* Subtotal Styles */
  /* Main Physical (Cyan Light) */
  .sub-fisik { background:#cffafe; color:#0e7490; font-weight:bold; }
  /* Tenaga (Yellow/Orange - Contrast) */
  .sub-tenaga { background:#fef9c3; color:#a16207; font-weight:bold; }
  /* Kimia (Blue - Contrast) */
  .sub-kimia { background:#dbeafe; color:#1e40af; font-weight:bold; }
  /* Campuran (Purple - Contrast) */
  .sub-campuran { background:#f3e8ff; color:#7e22ce; font-weight:bold; }
  
  .var-neg { color:#dc2626; } /* Merah jika minus */
  .var-pos { color:#0891b2; } /* Cyan jika plus */
</style>
</head>
<body>
  <div class="brand"><h1>KERTAS KERJA PEMELIHARAAN (<?= htmlspecialchars($nama_unit) ?>)</h1></div>
  <div class="subtitle">KEBUN: <?= strtoupper($nama_kebun) ?> | PERIODE: <?= strtoupper($str_bulan) ?> <?= $tahun ?></div>

  <table>
    <thead>
      <tr>
        <th rowspan="2" style="width:70px;">BLOK</th>
        <th rowspan="2" style="width:40px;">RENCANA</th>
        <th rowspan="2" style="width:25px;">SAT</th>
        <th colspan="<?= $daysInMonth ?>">REALISASI HARIAN</th>
        <th rowspan="2" style="width:40px;">TOTAL</th>
        <th rowspan="2" style="width:35px;">+/-</th>
      </tr>
      <tr>
        <?php for($i=1; $i<=$daysInMonth; $i++): ?>
            <th class="<?= isSunday($tahun,$bulan,$i)?'sun-head':'' ?>"><?= $i ?></th>
        <?php endfor; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach($master as $m): 
            $jid = $m['id'];
            
            // Tentukan style subtotal
            $katKey = 'FISIK';
            if($m['kategori'] == 'TENAGA' || $m['satuan'] == 'HK') $katKey = 'TENAGA';
            else if($m['kategori'] == 'KIMIA' || in_array(strtoupper($m['satuan']), ['KG','L','LITER'])) $katKey = 'KIMIA';
            else if($m['kategori'] == 'CAMPURAN') $katKey = 'CAMPURAN';
            
            $subClass = 'sub-fisik';
            if($katKey=='TENAGA') $subClass='sub-tenaga';
            if($katKey=='KIMIA')  $subClass='sub-kimia';
            if($katKey=='CAMPURAN') $subClass='sub-campuran';

            $s_ren = 0; $s_real = 0; $s_day = array_fill(1,32,0);
      ?>
        <tr class="group-row">
            <td colspan="<?= $daysInMonth + 5 ?>"><?= htmlspecialchars($m['nama']) ?></td>
        </tr>

        <?php if(isset($planGroup[$jid])): 
            foreach($planGroup[$jid] as $p):
                $pid = $p['id'];
                $ren = (float)$p['fisik_rencana'];
                $s_ren += $ren;
                $rowReal = 0;
        ?>
            <tr>
                <td class="font-bold"><?= htmlspecialchars($p['blok_rencana']) ?></td>
                <td class="text-right"><?= nf($ren) ?></td>
                <td class="text-center"><?= htmlspecialchars($p['satuan_rencana']) ?></td>
                
                <?php for($d=1; $d<=$daysInMonth; $d++): 
                    $val = (float)($dailyMap[$pid][$d] ?? 0);
                    $rowReal += $val;
                    $s_day[$d] += $val;
                ?>
                    <td class="text-right <?= isSunday($tahun,$bulan,$d)?'sun-col':'' ?>">
                        <?= $val!=0 ? number_format($val, (fmod($val,1)!==0.00?2:0),',','.') : '-' ?>
                    </td>
                <?php endfor; ?>
                
                <?php 
                    $s_real += $rowReal;
                    $var = $rowReal - $ren; 
                ?>
                <td class="text-right font-bold"><?= nf($rowReal) ?></td>
                <td class="text-right font-bold <?= $var<0?'var-neg':'var-pos' ?>"><?= nf($var) ?></td>
            </tr>
        <?php endforeach; endif; ?>

        <?php if($s_ren > 0 || $s_real > 0): $varSub = $s_real - $s_ren; ?>
        <tr class="<?= $subClass ?>">
            <td class="text-right">TOTAL</td>
            <td class="text-right"><?= nf($s_ren) ?></td>
            <td class="text-center"><?= $m['satuan'] ?></td>
            <?php for($d=1; $d<=$daysInMonth; $d++): ?>
                <td class="text-right"><?= ($s_day[$d]!=0 ? number_format($s_day[$d], (fmod($s_day[$d],1)!==0.00?2:0),',','.') : '-') ?></td>
            <?php endfor; ?>
            <td class="text-right"><?= nf($s_real) ?></td>
            <td class="text-right <?= $varSub<0?'var-neg':'var-pos' ?>"><?= nf($varSub) ?></td>
        </tr>
        <?php endif; ?>

      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('kertas_kerja_'.date('YmdHis').'.pdf', ['Attachment'=>false]);
exit;
?>