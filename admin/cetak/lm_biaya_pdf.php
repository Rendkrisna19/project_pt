  <?php
  // admin/cetak/lm_biaya_pdf.php
  // UPDATE: Sinkronisasi dengan Web View (Logic Volume LM76, Incl/Excl, dan Style HPP Hijau)

  session_start();
  if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit('Unauthorized'); }

  require_once '../../config/database.php';
  require_once '../../vendor/autoload.php'; // Pastikan path vendor benar

  use Dompdf\Dompdf;
  use Dompdf\Options;

  $db  = new Database();
  $pdo = $db->getConnection();

  /* --- 1. HELPER FUNCTIONS --- */
  function col_exists($pdo, $table, $col){
      $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
      $st->execute([':t'=>$table, ':c'=>$col]);
      return (bool)$st->fetchColumn();
  }
  function find_col($pdo, $table, $candidates, $default='0') {
      foreach ($candidates as $col) { if (col_exists($pdo, $table, $col)) return $col; }
      return $default;
  }

  // --- 2. TERIMA FILTER ---
  $unit_id  = $_GET['unit_id']  ?? '';
  $tahun    = $_GET['tahun']    ?? date('Y');
  $bulan    = $_GET['bulan']    ?? '';
  $kebun_id = $_GET['kebun_id'] ?? '';
  $q        = trim($_GET['q'] ?? '');

  // --- 3. LOGIKA VOLUME (LM76) ---
  $vol_real = 0; $vol_ang = 0;
  if (col_exists($pdo, 'lm76', 'id')) {
      $col_r = find_col($pdo, 'lm76', ['prod_bi_realisasi','realisasi','prod_real','tbs_realisasi']);
      $col_a = find_col($pdo, 'lm76', ['prod_bi_anggaran','prod_bi_rkap','anggaran','rkap']);

      $wh76 = " WHERE 1=1 "; $bd76 = [];
      if ($tahun !== '')    { $wh76 .= " AND tahun=:t"; $bd76[':t'] = $tahun; }
      if ($bulan !== '')    { $wh76 .= " AND bulan=:b"; $bd76[':b'] = $bulan; }
      if ($unit_id !== '')  { $wh76 .= " AND unit_id=:u"; $bd76[':u'] = $unit_id; }
      if ($kebun_id !== '') { $wh76 .= " AND kebun_id=:k"; $bd76[':k'] = $kebun_id; }

      $sqlVol = "SELECT SUM(COALESCE($col_r,0)) as v_real, SUM(COALESCE($col_a,0)) as v_ang FROM lm76 $wh76";
      $stVol = $pdo->prepare($sqlVol);
      $stVol->execute($bd76);
      $dVol = $stVol->fetch(PDO::FETCH_ASSOC);
      $vol_real = (float)($dVol['v_real'] ?? 0);
      $vol_ang  = (float)($dVol['v_ang'] ?? 0);
  }

  // --- 4. DATA BIAYA ---
  $where = " WHERE 1=1 "; $bind = [];
  if ($unit_id !== '')  { $where .= " AND b.unit_id=:uid"; $bind[':uid'] = $unit_id; }
  if ($tahun !== '')    { $where .= " AND b.tahun=:thn";  $bind[':thn'] = $tahun; }
  if ($bulan !== '')    { $where .= " AND b.bulan=:bln";  $bind[':bln'] = $bulan; }
  if ($kebun_id !== '') { $where .= " AND b.kebun_id=:kid"; $bind[':kid'] = $kebun_id; }
  if ($q !== '') {
      $where .= " AND (b.alokasi LIKE :kw OR b.uraian_pekerjaan LIKE :kw)";
      $bind[':kw'] = "%$q%";
  }

  $sql = "SELECT b.*, u.nama_unit, kb.nama_kebun
          FROM lm_biaya b
          LEFT JOIN units u ON u.id=b.unit_id
          LEFT JOIN md_kebun kb ON kb.id=b.kebun_id
          $where
          ORDER BY b.tahun DESC, 
            FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
            b.alokasi ASC";
  $st = $pdo->prepare($sql);
  foreach($bind as $k=>$v) $st->bindValue($k,$v);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // --- 5. HITUNG SUMMARY & HPP ---
  $sumAnggaran = 0; $sumRealisasi = 0;
  $pupukAng = 0;    $pupukReal = 0;

  foreach($rows as $r){
      $a  = (float)$r['rencana_bi'];
      $re = (float)$r['realisasi_bi'];
      
      // Total Incl
      $sumAnggaran  += $a; 
      $sumRealisasi += $re;

      // Cek Pupuk
      $txt = strtolower(($r['alokasi']??'').' '.($r['uraian_pekerjaan']??''));
      if (strpos($txt, 'pupuk') !== false){ 
          $pupukAng  += $a; 
          $pupukReal += $re; 
      }
  }

  // Total Excl
  $exclAng  = $sumAnggaran - $pupukAng;
  $exclReal = $sumRealisasi - $pupukReal;

  // HPP Incl
  $hppInclA = ($vol_ang > 0)  ? ($sumAnggaran / $vol_ang) : 0;
  $hppInclR = ($vol_real > 0) ? ($sumRealisasi / $vol_real) : 0;

  // HPP Excl
  $hppExclA = ($vol_ang > 0)  ? ($exclAng / $vol_ang) : 0;
  $hppExclR = ($vol_real > 0) ? ($exclReal / $vol_real) : 0;

  // Helper Perhitungan
  $calc = function($real, $ang) {
      $diff = $real - $ang;
      $pct  = ($ang > 0) ? ($diff / $ang * 100) : null;
      return [$diff, $pct];
  };

  ob_start();
  ?>
  <!doctype html>
  <html>
  <head>
  <meta charset="utf-8">
  <style>
    @page { margin: 10mm 10mm; }
    body { font-family: sans-serif; font-size: 9px; color:#000; }
    .header { text-align:center; margin-bottom:15px; }
    .header h1 { margin:0; font-size:14px; text-transform:uppercase; }
    .header p { margin:2px 0; font-size:10px; }
    
    table { width:100%; border-collapse:collapse; margin-bottom:10px; }
    th, td { border:1px solid #000; padding:4px; vertical-align:middle; }
    
    th { background:#eee; font-weight:bold; text-align:center; height:20px; }
    
    .right { text-align:right; }
    .center { text-align:center; }
    
    /* Styling Khusus agar mirip Excel User */
    .row-vol td { background:#e0e0e0; font-weight:bold; }
    .row-hpp td { background:#86efac; font-weight:bold; } /* Warna Hijau */
    .bold { font-weight:bold; }
    
    .neg { color:#000; } /* PDF biasanya hitam saja, atau bisa dikasih kurung */
  </style>
  </head>
  <body>

    <div class="header">
      <h1>Laporan Biaya & Harga Pokok</h1>
      <p>
        <?= $kebun_id!=='' ? 'Kebun: '.htmlspecialchars($rows[0]['nama_kebun']??'').' | ' : '' ?>
        <?= $unit_id!==''  ? 'Unit: '.htmlspecialchars($rows[0]['nama_unit']??'').' | ' : '' ?>
        Bulan: <?= $bulan?:'Semua' ?> | Tahun: <?= $tahun ?>
      </p>
    </div>

    <table>
      <thead>
        <tr>
          <th rowspan="2">Unit/Devisi</th>
          <th colspan="2">Uraian</th>
          <th rowspan="2" style="width:6%">Bulan</th>
          <th rowspan="2" style="width:5%">Tahun</th>
          <th style="width:12%">Realisasi</th>
          <th style="width:12%">Anggaran</th>
          <th style="width:12%">+/-</th>
          <th style="width:7%">%</th>
        </tr>
        <tr>
          <th>No. ALOKASI</th>
          <th>Uraian Pekerjaan</th>
          <th>(Rp)</th>
          <th>(Rp)</th>
          <th>(Rp)</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php list($dVol, $pVol) = $calc($vol_real, $vol_ang); ?>
        <tr class="row-vol">
          <td></td>
          <td colspan="2" style="text-align:center">- Produksi TBS (KG)</td>
          <td></td>
          <td></td>
          <td class="right"><?= number_format($vol_real) ?></td>
          <td class="right"><?= number_format($vol_ang) ?></td>
          <td class="right"><?= number_format($dVol) ?></td>
          <td class="right"><?= is_null($pVol)?'-':number_format($pVol,2) ?></td>
        </tr>

        <?php if(empty($rows)): ?>
          <tr><td colspan="9" class="center">Data tidak ditemukan.</td></tr>
        <?php else: ?>
          <?php foreach($rows as $r): 
              list($diff, $pct) = $calc((float)$r['realisasi_bi'], (float)$r['rencana_bi']);
              $fmtDiff = ($diff<0) ? '('.number_format(abs($diff)).')' : number_format($diff);
          ?>
          <tr>
              <td><?= htmlspecialchars($r['nama_unit']??'-') ?></td>
              <td><?= htmlspecialchars($r['alokasi']) ?></td>
              <td><?= htmlspecialchars($r['uraian_pekerjaan']) ?></td>
              <td class="center"><?= $r['bulan'] ?></td>
              <td class="center"><?= $r['tahun'] ?></td>
              <td class="right"><?= number_format((float)$r['realisasi_bi']) ?></td>
              <td class="right"><?= number_format((float)$r['rencana_bi']) ?></td>
              <td class="right"><?= $fmtDiff ?></td>
              <td class="right"><?= is_null($pct)?'-':number_format($pct,2) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php 
          list($dInc, $pInc) = $calc($sumRealisasi, $sumAnggaran);
          $fmtInc = ($dInc<0)?'('.number_format(abs($dInc)).')':number_format($dInc);
        ?>
        <tr class="bold">
          <td colspan="5" class="right">Jlh By Tanaman Inc. Pemupukan</td>
          <td class="right"><?= number_format($sumRealisasi) ?></td>
          <td class="right"><?= number_format($sumAnggaran) ?></td>
          <td class="right"><?= $fmtInc ?></td>
          <td class="right"><?= is_null($pInc)?'-':number_format($pInc,2) ?></td>
        </tr>

        <?php 
          list($dExc, $pExc) = $calc($exclReal, $exclAng);
          $fmtExc = ($dExc<0)?'('.number_format(abs($dExc)).')':number_format($dExc);
        ?>
        <tr class="bold">
          <td colspan="5" class="right">Jlh By Tanaman Excl. Pemupukan</td>
          <td class="right"><?= number_format($exclReal) ?></td>
          <td class="right"><?= number_format($exclAng) ?></td>
          <td class="right"><?= $fmtExc ?></td>
          <td class="right"><?= is_null($pExc)?'-':number_format($pExc,2) ?></td>
        </tr>

        <?php 
          list($dHi, $pHi) = $calc($hppInclR, $hppInclA);
          $fmtHi = ($dHi<0)?'('.number_format(abs($dHi),2).')':number_format($dHi,2);
        ?>
        <tr class="row-hpp">
          <td colspan="5" class="right">Harga Pokok Incl. Pemupukan</td>
          <td class="right"><?= number_format($hppInclR,2) ?></td>
          <td class="right"><?= number_format($hppInclA,2) ?></td>
          <td class="right"><?= $fmtHi ?></td>
          <td class="right"><?= is_null($pHi)?'-':number_format($pHi,2) ?></td>
        </tr>

        <?php 
          list($dHe, $pHe) = $calc($hppExclR, $hppExclA);
          $fmtHe = ($dHe<0)?'('.number_format(abs($dHe),2).')':number_format($dHe,2);
        ?>
        <tr class="row-hpp">
          <td colspan="5" class="right">Harga Pokok Excl. Pemupukan</td>
          <td class="right"><?= number_format($hppExclR,2) ?></td>
          <td class="right"><?= number_format($hppExclA,2) ?></td>
          <td class="right"><?= $fmtHe ?></td>
          <td class="right"><?= is_null($pHe)?'-':number_format($pHe,2) ?></td>
        </tr>

      </tbody>
    </table>

    <div style="font-size:8px; margin-top:10px;">
      <i>Dicetak pada: <?= date('d-m-Y H:i:s') ?></i>
    </div>

  </body>
  </html>
  <?php
  $html = ob_get_clean();

  $opt = new Options();
  $opt->set('isRemoteEnabled', true);
  $opt->set('isHtml5ParserEnabled', true);

  $pdf = new Dompdf($opt);
  $pdf->loadHtml($html);
  $pdf->setPaper('A4', 'landscape'); // Landscape agar kolom muat
  $pdf->render();
  $pdf->stream('laporan_biaya_hpp.pdf', ['Attachment'=>false]);
  ?>