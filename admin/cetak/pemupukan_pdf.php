<?php
// pages/cetak/pemupukan_pdf.php
// Cetak PDF Pemupukan (Menabur/Angkutan) – header hijau, hormati filter
// MOD: Support filter & join untuk rayon_id, apl_id, keterangan_id, gudang_asal_id
// Menabur: TAHUN, APL; T.TANAM dari md_tahun_tanam
// Tambahan kolom & filter: RAYON, NO AU-58, KETERANGAN
// Kompatibel kolom:
//  - Menabur: no_au_58 | no_au58 | catatan (dialias ke no_au_58)
//  - Angkutan: no_spb (baru) | no_au_58 | no_au58 | catatan (semua dialias ke no_spb)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  http_response_code(403);
  exit('Unauthorized');
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function qstr($v) { return trim((string)$v); }
function qintOrEmpty($v) { return ($v === '' || $v === null) ? '' : (int)$v; }

try {
  $db  = new Database();
  $pdo = $db->getConnection();

  // ===== Filters
  $tab         = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur', 'angkutan'], true)) $tab = 'menabur';
  $f_unit_id   = qintOrEmpty($_GET['unit_id']      ?? '');
  $f_kebun_id  = qintOrEmpty($_GET['kebun_id']     ?? '');
  $f_tanggal   = qstr($_GET['tanggal']             ?? '');
  $f_bulan     = qstr($_GET['bulan']               ?? '');
  $f_jenis     = qstr($_GET['jenis_pupuk']         ?? '');

  // Filter Teks Lama (legacy)
  $f_rayon        = qstr($_GET['rayon']        ?? '');
  $f_keterangan   = qstr($_GET['keterangan']   ?? '');

  // [MODIFIKASI] Filter ID Baru (dari pemupukan.php)
  $f_rayon_id      = qintOrEmpty($_GET['rayon_id']        ?? '');
  $f_apl_id        = qintOrEmpty($_GET['apl_id']          ?? '');
  $f_keterangan_id = qintOrEmpty($_GET['keterangan_id']   ?? '');
  $f_gudang_id     = qintOrEmpty($_GET['gudang_asal_id']  ?? '');

  // ===== Helper deteksi kolom
  $cacheCols = [];
  $columnExists = function (PDO $c, $table, $col) use (&$cacheCols) {
    $k = $table;
    if (!isset($cacheCols[$k])) {
      $st = $c->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = :t
      ");
      $st->execute([':t' => $table]);
      $cacheCols[$k] = array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
    }
    return in_array(strtolower($col), $cacheCols[$k] ?? [], true);
  };

  // Kebun availability
  $hasKebunMenaburId  = $columnExists($pdo, 'menabur_pupuk', 'kebun_id');
  $hasKebunMenaburKod = $columnExists($pdo, 'menabur_pupuk', 'kebun_kode');
  $hasKebunAngkutId   = $columnExists($pdo, 'angkutan_pupuk', 'kebun_id');
  $hasKebunAngkutKod  = $columnExists($pdo, 'angkutan_pupuk', 'kebun_kode');

  // Tahun & APL availability (Menabur)
  $hasTahunMenabur = $columnExists($pdo, 'menabur_pupuk', 'tahun');
  $hasTTId         = $columnExists($pdo, 'menabur_pupuk', 'tahun_tanam_id');
  $hasTTAngka      = $columnExists($pdo, 'menabur_pupuk', 'tahun_tanam');

  $aplCol = null;
  foreach (['apl', 'aplikator'] as $c) {
    if ($columnExists($pdo, 'menabur_pupuk', $c)) { $aplCol = $c; break; }
  }

  // Kolom Teks Lama (Menabur)
  $hasRayonMenabur      = $columnExists($pdo, 'menabur_pupuk', 'rayon');
  $hasKeteranganMenabur = $columnExists($pdo, 'menabur_pupuk', 'keterangan');
  $hasNoAU58MenaburMain = $columnExists($pdo, 'menabur_pupuk', 'no_au_58');
  $hasNoAU58MenaburAlt  = $columnExists($pdo, 'menabur_pupuk', 'no_au58');
  $hasCatatanMenabur    = $columnExists($pdo, 'menabur_pupuk', 'catatan');

  // Kolom Teks Lama (Angkutan)
  $hasRayonAngkutan      = $columnExists($pdo, 'angkutan_pupuk', 'rayon');
  $hasKeteranganAngkutan = $columnExists($pdo, 'angkutan_pupuk', 'keterangan');
  $hasNoSPBAngkut        = $columnExists($pdo, 'angkutan_pupuk', 'no_spb');    // baru
  $hasNoAU58AngkutMain   = $columnExists($pdo, 'angkutan_pupuk', 'no_au_58');  // legacy
  $hasNoAU58AngkutAlt    = $columnExists($pdo, 'angkutan_pupuk', 'no_au58');   // legacy
  $hasCatatanAngkut      = $columnExists($pdo, 'angkutan_pupuk', 'catatan');   // legacy
  $hasNomorDOAngkut      = $columnExists($pdo, 'angkutan_pupuk', 'nomor_do');
  $hasSupirAngkut        = $columnExists($pdo, 'angkutan_pupuk', 'supir');

  // [MODIFIKASI] Deteksi Kolom ID Baru
  $hasRayonIdM = $columnExists($pdo, 'menabur_pupuk', 'rayon_id');
  $hasAplIdM   = $columnExists($pdo, 'menabur_pupuk', 'apl_id');
  $hasKetIdM   = $columnExists($pdo, 'menabur_pupuk', 'keterangan_id');

  $hasRayonIdA  = $columnExists($pdo, 'angkutan_pupuk', 'rayon_id');
  $hasGudangIdA = $columnExists($pdo, 'angkutan_pupuk', 'gudang_asal_id');
  $hasKetIdA    = $columnExists($pdo, 'angkutan_pupuk', 'keterangan_id');

  // Map kebun id->kode untuk filter bila transaksi pakai kode
  $kebuns   = $pdo->query("SELECT id, kode, nama_kebun FROM md_kebun")->fetchAll(PDO::FETCH_ASSOC);
  $idToKode = [];
  foreach ($kebuns as $k) { $idToKode[(int)$k['id']] = $k['kode']; }

  if ($tab === 'angkutan') {
    $title = "Data Angkutan Pupuk Kimia";

    $selectKebun = ''; $joinKebun = '';
    if ($hasKebunAngkutId) {
      $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinKebun   = " LEFT JOIN md_kebun kb ON kb.id = a.kebun_id ";
    } elseif ($hasKebunAngkutKod) {
      $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinKebun   = " LEFT JOIN md_kebun kb ON kb.kode = a.kebun_kode ";
    }

    // Alias NO SPB (baru) – fallback kolom lama
    $selectSPB = '';
    if ($hasNoSPBAngkut)        $selectSPB = ", a.no_spb AS no_spb";
    elseif ($hasNoAU58AngkutMain) $selectSPB = ", a.no_au_58 AS no_spb";
    elseif ($hasNoAU58AngkutAlt)  $selectSPB = ", a.no_au58  AS no_spb";
    elseif ($hasCatatanAngkut)    $selectSPB = ", a.catatan  AS no_spb";

    $selectSupir = $hasSupirAngkut ? ", a.supir" : ", NULL AS supir";

    // [MODIFIKASI] Select & Join Master Baru (Angkutan)
    $selectRayon  = $hasRayonIdA  ? ", r.nama AS rayon_nama"        : "";
    $joinRayon    = $hasRayonIdA  ? " LEFT JOIN md_rayon r ON r.id = a.rayon_id" : "";
    $selectGudang = $hasGudangIdA ? ", g.nama AS gudang_asal_nama"  : "";
    $joinGudang   = $hasGudangIdA ? " LEFT JOIN md_asal_gudang g ON g.id = a.gudang_asal_id" : "";
    $selectKet    = $hasKetIdA    ? ", k.keterangan AS keterangan_text" : "";
    $joinKet      = $hasKetIdA    ? " LEFT JOIN md_keterangan k ON k.id = a.keterangan_id" : "";

    $where = " WHERE 1=1";
    $p = [];

    if ($f_unit_id !== '') {
      $where .= " AND a.unit_tujuan_id = :uid";
      $p[':uid'] = (int)$f_unit_id;
    }
    if ($f_kebun_id !== '') {
      if ($hasKebunAngkutId) {
        $where .= " AND a.kebun_id = :kid";
        $p[':kid'] = (int)$f_kebun_id;
      } elseif ($hasKebunAngkutKod) {
        $where .= " AND a.kebun_kode = :kkod";
        $p[':kkod'] = (string)($idToKode[(int)$f_kebun_id] ?? '');
      }
    }
    if ($f_tanggal !== '') {
      $where .= " AND a.tanggal = :tgl";
      $p[':tgl'] = $f_tanggal;
    }
    if ($f_bulan !== '' && ctype_digit($f_bulan)) {
      $where .= " AND MONTH(a.tanggal) = :bln";
      $p[':bln'] = (int)$f_bulan;
    }
    if ($f_jenis !== '') {
      $where .= " AND a.jenis_pupuk = :jp";
      $p[':jp'] = $f_jenis;
    }

    // [MODIFIKASI] Filter by ID (jika ada)
    if ($f_rayon_id !== '' && $hasRayonIdA) {
      $where .= " AND a.rayon_id = :rid";
      $p[':rid'] = $f_rayon_id;
    } elseif ($f_rayon !== '' && $hasRayonAngkutan) { // Fallback teks
      $where .= " AND a.rayon LIKE :ry";
      $p[':ry'] = "%$f_rayon%";
    }

    if ($f_gudang_id !== '' && $hasGudangIdA) {
      $where .= " AND a.gudang_asal_id = :gid";
      $p[':gid'] = $f_gudang_id;
    }

    if ($f_keterangan_id !== '' && $hasKetIdA) {
      $where .= " AND a.keterangan_id = :kid";
      $p[':kid'] = $f_keterangan_id;
    } elseif ($f_keterangan !== '' && $hasKeteranganAngkutan) { // Fallback teks
      $where .= " AND a.keterangan LIKE :ket";
      $p[':ket'] = "%$f_keterangan%";
    }

    // Gabungkan semua join
    $fullJoins = $joinKebun . $joinRayon . $joinGudang . $joinKet;

    $sql = "
      SELECT a.*, u.nama_unit AS unit_tujuan_nama
             $selectKebun $selectSPB $selectSupir
             $selectRayon $selectGudang $selectKet
      FROM angkutan_pupuk a
      LEFT JOIN units u ON u.id = a.unit_tujuan_id
      $fullJoins
      $where
      ORDER BY a.tanggal DESC, a.id DESC
    ";
    $st = $pdo->prepare($sql);
    foreach ($p as $k => $v) {
      $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $tot_kg = 0.0;
    foreach ($rows as $r) { $tot_kg += (float)($r['jumlah'] ?? 0); }

  } else {
    // ===== MENABUR
    $title = "Data Penaburan Pupuk Kimia";

    $selectKebun = ''; $joinKebun = '';
    if ($hasKebunMenaburId) {
      $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinKebun   = " LEFT JOIN md_kebun kb ON kb.id = m.kebun_id ";
    } elseif ($hasKebunMenaburKod) {
      $selectKebun = ", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";
      $joinKebun   = " LEFT JOIN md_kebun kb ON kb.kode = m.kebun_kode ";
    }

    // Tahun tanam & tahun input & APL
    $selectTT = ", COALESCE(tt.tahun, " . ($hasTTAngka ? "m.tahun_tanam" : "NULL") . ") AS t_tanam";
    $joinTT   = "";
    if     ($hasTTId)     $joinTT = " LEFT JOIN md_tahun_tanam tt ON tt.id   = m.tahun_tanam_id ";
    elseif ($hasTTAngka)  $joinTT = " LEFT JOIN md_tahun_tanam tt ON tt.tahun = m.tahun_tanam ";

    $selectTahun   = $hasTahunMenabur ? ", m.tahun AS tahun_input" : ", YEAR(m.tanggal) AS tahun_input";
    $selectAPL_Old = $aplCol ? ", m.`$aplCol` AS apl_text" : ", NULL AS apl_text"; // Teks APL lama

    // Alias NO AU-58 (menabur)
    $selectNoAU = '';
    if     ($hasNoAU58MenaburMain) $selectNoAU = ", m.no_au_58";
    elseif ($hasNoAU58MenaburAlt)  $selectNoAU = ", m.no_au58 AS no_au_58";
    elseif ($hasCatatanMenabur)    $selectNoAU = ", m.catatan AS no_au_58";

    // [MODIFIKASI] Select & Join Master Baru (Menabur)
    $selectRayon  = $hasRayonIdM ? ", r.nama AS rayon_nama" : "";
    $joinRayon    = $hasRayonIdM ? " LEFT JOIN md_rayon r ON r.id = m.rayon_id" : "";
    $selectAplNew = $hasAplIdM   ? ", apl.nama AS apl_nama" : "";
    $joinApl      = $hasAplIdM   ? " LEFT JOIN md_apl apl ON apl.id = m.apl_id" : "";
    $selectKet    = $hasKetIdM   ? ", k.keterangan AS keterangan_text" : "";
    $joinKet      = $hasKetIdM   ? " LEFT JOIN md_keterangan k ON k.id = m.keterangan_id" : "";

    $where = " WHERE 1=1";
    $p = [];

    if ($f_unit_id !== '') {
      $where .= " AND m.unit_id = :uid";
      $p[':uid'] = (int)$f_unit_id;
    }
    if ($f_kebun_id !== '') {
      if ($hasKebunMenaburId) {
        $where .= " AND m.kebun_id = :kid";
        $p[':kid'] = (int)$f_kebun_id;
      } elseif ($hasKebunMenaburKod) {
        $where .= " AND m.kebun_kode = :kkod";
        $p[':kkod'] = (string)($idToKode[(int)$f_kebun_id] ?? '');
      }
    }
    if ($f_tanggal !== '') {
      $where .= " AND m.tanggal = :tgl";
      $p[':tgl'] = $f_tanggal;
    }
    if ($f_bulan !== '' && ctype_digit($f_bulan)) {
      $where .= " AND MONTH(m.tanggal) = :bln";
      $p[':bln'] = (int)$f_bulan;
    }
    if ($f_jenis !== '') {
      $where .= " AND m.jenis_pupuk = :jp";
      $p[':jp'] = $f_jenis;
    }

    // [MODIFIKASI] Filter by ID (jika ada)
    if ($f_rayon_id !== '' && $hasRayonIdM) {
      $where .= " AND m.rayon_id = :rid";
      $p[':rid'] = $f_rayon_id;
    } elseif ($f_rayon !== '' && $hasRayonMenabur) { // Fallback teks
      $where .= " AND m.rayon LIKE :ry";
      $p[':ry'] = "%$f_rayon%";
    }

    if ($f_apl_id !== '' && $hasAplIdM) {
      $where .= " AND m.apl_id = :aid";
      $p[':aid'] = $f_apl_id;
    }
    // Tidak ada fallback teks untuk APL karena $f_apl_id sudah mencakupnya

    if ($f_keterangan_id !== '' && $hasKetIdM) {
      $where .= " AND m.keterangan_id = :kid";
      $p[':kid'] = $f_keterangan_id;
    } elseif ($f_keterangan !== '' && $hasKeteranganMenabur) { // Fallback teks
      $where .= " AND m.keterangan LIKE :ket";
      $p[':ket'] = "%$f_keterangan%";
    }

    // Gabungkan semua join
    $fullJoins = $joinKebun . $joinTT . $joinRayon . $joinApl . $joinKet;

    $sql = "
      SELECT m.*, u.nama_unit AS unit_nama
             $selectKebun
             $selectTT
             $selectTahun
             $selectAPL_Old
             $selectNoAU
             $selectRayon $selectAplNew $selectKet
      FROM menabur_pupuk m
      LEFT JOIN units u ON u.id = m.unit_id
      $fullJoins
      $where
      ORDER BY m.tanggal DESC, m.id DESC
    ";
    $st = $pdo->prepare($sql);
    foreach ($p as $k => $v) {
      $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $tot_kg = 0.0;
    $tot_luas = 0.0;
    $tot_invt = 0.0;
    $sum_dosis = 0.0;
    $cnt_dosis = 0;

    foreach ($rows as $r) {
      $tot_kg   += (float)($r['jumlah']     ?? 0);
      $tot_luas += (float)($r['luas']       ?? 0);
      $tot_invt += (float)($r['invt_pokok'] ?? 0);
      if (isset($r['dosis']) && $r['dosis'] !== '') {
        $sum_dosis += (float)$r['dosis'];
        $cnt_dosis++;
      }
    }
    $avg_dosis = $cnt_dosis ? ($sum_dosis / $cnt_dosis) : 0.0;
  }

} catch (Throwable $e) {
  http_response_code(500);
  exit("DB Error: " . $e->getMessage());
}

// ===== HTML
ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 20mm 15mm; size: A4 landscape; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
  .brand { background: #059669; color: #fff; padding: 12px 16px; border-radius: 8px; text-align: center; margin-bottom: 10px; }
  .brand h1 { margin: 0; font-size: 18px; letter-spacing: .5px; }
  .subtitle { text-align: center; margin: 4px 0 12px; font-weight: 700; font-size: 13px; color: #065f46; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #e5e7eb; padding: 6px 8px; word-wrap: break-word; }
  thead th { background: #ecfdf5; color: #065f46; font-weight: 700; text-align: left; }
  thead th.text-right { text-align: right; }
  tbody tr:nth-child(even) td { background: #f8fafc; }
  tfoot td { background: #f1faf6; font-weight: 700; }
  .text-right { text-align: right; }
</style>
</head>
<body>
  <div class="brand"><h1>PTPN 4 REGIONAL 3</h1></div>
  <div class="subtitle"><?= htmlspecialchars($title) ?></div>

  <table>
    <thead>
      <tr>
        <?php if ($tab === 'angkutan'): ?>
          <th>Kebun</th><th>Rayon</th><th>Gudang Asal</th><th>Unit Tujuan</th><th>Tanggal</th>
          <th>Jenis Pupuk</th><th class="text-right">Jumlah (Kg)</th><th>No SPB</th><th>Keterangan</th><th>Supir</th>
        <?php else: ?>
          <th>Tahun</th><th>Kebun</th><th>Unit</th><th>T.TANAM</th><th>Blok</th><th>Rayon</th>
          <th>Tanggal</th><th>Jenis Pupuk</th><th>APL</th>
          <th class="text-right">Dosis (kg/ha)</th><th class="text-right">Jumlah (Kg)</th>
          <th class="text-right">Luas (Ha)</th><th class="text-right">Invt. Pokok</th><th>No AU-58</th><th>Keterangan</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= $tab === 'angkutan' ? 10 : 15 ?>">Belum ada data.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php if ($tab === 'angkutan'):
          // [MODIFIKASI] Logika fallback untuk Rayon, Gudang, Keterangan
          $rayonDisplay  = $r['rayon_nama']      ?? ($r['rayon']       ?? '');
          $gudangDisplay = $r['gudang_asal_nama']?? ($r['gudang_asal'] ?? '');
          $ketDisplay    = $r['keterangan_text'] ?? ($r['keterangan']  ?? '');
        ?>
          <tr>
            <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
            <td><?= htmlspecialchars($rayonDisplay) ?></td>
            <td><?= htmlspecialchars($gudangDisplay) ?></td>
            <td><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['tanggal'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['jenis_pupuk'] ?? '') ?></td>
            <td class="text-right"><?= number_format((float)($r['jumlah'] ?? 0), 2) ?></td>
            <td><?= htmlspecialchars($r['no_spb'] ?? '') ?></td>
            <td><?= htmlspecialchars($ketDisplay) ?></td>
            <td><?= htmlspecialchars($r['supir'] ?? '') ?></td>
          </tr>
        <?php else:
          $tahunRow  = $r['tahun_input'] ?? '';
          $dosisVal  = (isset($r['dosis']) && $r['dosis'] !== '') ? number_format((float)$r['dosis'], 2) : '-';
          // [MODIFIKASI] Logika fallback untuk Rayon, APL, Keterangan
          $rayonDisplay = $r['rayon_nama'] ?? ($r['rayon'] ?? '');
          $aplDisplay   = $r['apl_nama']   ?? ($r['apl_text'] ?? '-'); // Prioritaskan APL baru, fallback ke teks lama
          $ketDisplay   = $r['keterangan_text'] ?? ($r['keterangan'] ?? '');
        ?>
          <tr>
            <td><?= htmlspecialchars($tahunRow) ?></td>
            <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
            <td><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['t_tanam'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['blok'] ?? '') ?></td>
            <td><?= htmlspecialchars($rayonDisplay) ?></td>
            <td><?= htmlspecialchars($r['tanggal'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['jenis_pupuk'] ?? '') ?></td>
            <td><?= htmlspecialchars($aplDisplay) ?></td>
            <td class="text-right"><?= $dosisVal ?></td>
            <td class="text-right"><?= number_format((float)($r['jumlah'] ?? 0), 2) ?></td>
            <td class="text-right"><?= number_format((float)($r['luas'] ?? 0), 2) ?></td>
            <td class="text-right"><?= number_format((float)($r['invt_pokok'] ?? 0), 0) ?></td>
            <td><?= htmlspecialchars($r['no_au_58'] ?? '') ?></td>
            <td><?= htmlspecialchars($ketDisplay) ?></td>
          </tr>
        <?php endif; endforeach; endif; ?>
    </tbody>
    <?php if (!empty($rows)): ?>
      <tfoot>
        <?php if ($tab === 'angkutan'): ?>
          <tr>
            <td colspan="6" class="text-right">TOTAL</td>
            <td class="text-right"><?= number_format($tot_kg, 2) ?></td>
            <td colspan="3"></td>
          </tr>
        <?php else: ?>
          <tr>
            <td colspan="9" class="text-right">TOTAL</td>
            <td class="text-right"><?= number_format($avg_dosis ?? 0, 2) ?></td>
            <td class="text-right"><?= number_format($tot_kg, 2) ?></td>
            <td class="text-right"><?= number_format($tot_luas, 2) ?></td>
            <td class="text-right"><?= number_format($tot_invt, 0) ?></td>
            <td colspan="2"></td>
          </tr>
        <?php endif; ?>
      </tfoot>
    <?php endif; ?>
  </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$pdf = new Dompdf($options);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4', 'landscape');
$pdf->render();

$fname = ($tab === 'angkutan' ? 'angkutan' : 'menabur') . '_pupuk_' . date('Ymd_His') . '.pdf';
$pdf->stream($fname, ['Attachment' => false]);
exit;
