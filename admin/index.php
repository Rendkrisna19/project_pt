<?php
// admin/dashboard.php (FULL — Filtering Lengkap + KPI Fix + Seksi Baru)
// Versi: 2025-09-30
declare(strict_types=1);
session_start();

// ===== CSRF =====
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// ===== DB =====
require_once '../config/database.php';
$db  = new Database();
$pdo = $db->getConnection();

// ===== AJAX (API) =====
if (($_POST['ajax'] ?? '') === 'dashboard') {
  header('Content-Type: application/json; charset=utf-8');

  if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit;
  }

  $section  = trim($_POST['section']  ?? '');  // Section selector
  $afdeling = trim($_POST['afdeling'] ?? '');  // pakai units.nama_unit
  $bulan    = trim($_POST['bulan']    ?? '');  // enum IND
  $tahun    = trim($_POST['tahun']    ?? '');  // YEAR

  // helper enum bulan dari DATE column
  $bulanFromDate = "ELT(MONTH(tanggal),
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember')";

  try {
    /* =========================
     * 1) STOK GUDANG (FIXED)
     * =========================
     * join ke md_bahan_kimia & md_satuan agar ada nama_bahan + satuan
     */
    if ($section === 'gudang') {
      $sql = "
        SELECT
          b.nama_bahan,
          s.nama AS satuan,
          sg.bulan, sg.tahun,
          sg.stok_awal, sg.mutasi_masuk, sg.mutasi_keluar, sg.pasokan, sg.dipakai
        FROM stok_gudang sg
        JOIN md_bahan_kimia b ON b.id = sg.bahan_id
        LEFT JOIN md_satuan s ON s.id = b.satuan_id
        /* filter afdeling TIDAK diaplikasikan karena stok_gudang tidak punya unit/afdeling */
        WHERE (:bulan='' OR sg.bulan = :bulan)
          AND (:tahun='' OR sg.tahun = :tahun)
        ORDER BY sg.tahun DESC,
          FIELD(sg.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                          'Agustus','September','Oktober','November','Desember'),
          b.nama_bahan ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ==========================================
     * 2) PEMAKAIAN (gabungan untuk komponen lama)
     * ==========================================
     * (dikembalikan untuk kompatibilitas chart lama)
     * pbk + menabur_pupuk (anorganik) + menabur_pupuk_organik
     */
    if ($section === 'pemakaian') {
      $sql = "
        SELECT * FROM (
          /* 2.1 pemakaian bahan kimia */
          SELECT
            pbk.no_dokumen,
            u.nama_unit AS afdeling,
            pbk.bulan,
            pbk.tahun,
            pbk.nama_bahan,
            pbk.jenis_pekerjaan,
            COALESCE(pbk.jlh_diminta,0) AS jlh_diminta,
            COALESCE(pbk.jlh_fisik,0)   AS jlh_fisik
          FROM pemakaian_bahan_kimia pbk
          LEFT JOIN units u ON u.id = pbk.unit_id
          WHERE (:afdeling='' OR u.nama_unit = :afdeling)
            AND (:bulan='' OR pbk.bulan = :bulan)
            AND (:tahun='' OR pbk.tahun = :tahun)

          UNION ALL
          /* 2.2 menabur pupuk anorganik */
          SELECT
            CONCAT('MENABUR-', LPAD(CAST(mp.id AS CHAR),6,'0')) AS no_dokumen,
            mp.afdeling AS afdeling,
            {$bulanFromDate} AS bulan,
            YEAR(mp.tanggal) AS tahun,
            mp.jenis_pupuk   AS nama_bahan,
            'Pemupukan'      AS jenis_pekerjaan,
            COALESCE(mp.jumlah,0) AS jlh_diminta,
            COALESCE(mp.jumlah,0) AS jlh_fisik
          FROM menabur_pupuk mp
          WHERE (:afdeling='' OR mp.afdeling = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)

          UNION ALL
          /* 2.3 menabur pupuk organik */
          SELECT
            CONCAT('MENABURORG-', LPAD(CAST(mpo.id AS CHAR),6,'0')) AS no_dokumen,
            uo.nama_unit AS afdeling,
            {$bulanFromDate} AS bulan,
            YEAR(mpo.tanggal) AS tahun,
            mpo.jenis_pupuk    AS nama_bahan,
            'Pemupukan Organik' AS jenis_pekerjaan,
            COALESCE(mpo.jumlah,0) AS jlh_diminta,
            COALESCE(mpo.jumlah,0) AS jlh_fisik
          FROM menabur_pupuk_organik mpo
          LEFT JOIN units uo ON uo.id = mpo.unit_id
          WHERE (:afdeling='' OR uo.nama_unit = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mpo.tanggal) = :tahun)
        ) x
        ORDER BY
          tahun DESC,
          FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                        'Agustus','September','Oktober','November','Desember'),
          no_dokumen ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ======================================
     * 2b) PEMAKAIAN_KIMIA (khusus pbk saja)
     * ====================================== */
    if ($section === 'pemakaian_kimia') {
      $sql = "
        SELECT
          pbk.no_dokumen,
          u.nama_unit AS afdeling,
          pbk.bulan, pbk.tahun,
          pbk.nama_bahan, pbk.jenis_pekerjaan,
          COALESCE(pbk.jlh_diminta,0) AS jlh_diminta,
          COALESCE(pbk.jlh_fisik,0)   AS jlh_fisik
        FROM pemakaian_bahan_kimia pbk
        LEFT JOIN units u ON u.id = pbk.unit_id
        WHERE (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:bulan='' OR pbk.bulan = :bulan)
          AND (:tahun='' OR pbk.tahun = :tahun)
        ORDER BY pbk.tahun DESC,
          FIELD(pbk.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                           'Agustus','September','Oktober','November','Desember'),
          pbk.no_dokumen ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ========================
     * 3) PEKERJAAN (LM BIAYA)
     * ======================== */
    if ($section === 'pekerjaan') {
      $sql = "
        SELECT
          lb.bulan, lb.tahun,
          u.nama_unit AS afdeling,
          ka.kode AS kode_aktivitas, ka.nama AS nama_aktivitas,
          jp.nama AS jenis_pekerjaan,
          lb.rencana_bi, lb.realisasi_bi
        FROM lm_biaya lb
        LEFT JOIN units u ON u.id = lb.unit_id
        LEFT JOIN md_kode_aktivitas ka ON ka.id = lb.kode_aktivitas_id
        LEFT JOIN md_jenis_pekerjaan jp ON jp.id = lb.jenis_pekerjaan_id
        WHERE (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:bulan='' OR lb.bulan = :bulan)
          AND (:tahun='' OR lb.tahun = :tahun)
        ORDER BY lb.tahun DESC,
          FIELD(lb.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                           'Agustus','September','Oktober','November','Desember'),
          ka.kode ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* =======================
     * 4) PEMELIHARAAN (REAL)
     * ======================= */
    if ($section === 'pemeliharaan') {
      $sql = "
        SELECT
          COALESCE(NULLIF(pml.afdeling,''), u.nama_unit) AS afdeling,
          pml.kategori, pml.jenis_pekerjaan,
          pml.tanggal,
          {$bulanFromDate} AS bulan,
          YEAR(pml.tanggal) AS tahun,
          pml.rencana, pml.realisasi, pml.status
        FROM pemeliharaan pml
        LEFT JOIN units u ON u.id = pml.unit_id
        WHERE (:afdeling='' OR COALESCE(NULLIF(pml.afdeling,''), u.nama_unit) = :afdeling)
          AND (:bulan='' OR {$bulanFromDate} = :bulan)
          AND (:tahun='' OR YEAR(pml.tanggal) = :tahun)
        ORDER BY pml.tanggal DESC, pml.jenis_pekerjaan ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ===================================
     * 5) PEMUPUKAN (gabungan lama)
     * =================================== */
    if ($section === 'pemupukan') {
      $sql = "
        SELECT * FROM (
          SELECT
            'Anorganik' AS sumber,
            mp.afdeling AS afdeling,
            mp.tanggal,
            {$bulanFromDate} AS bulan,
            YEAR(mp.tanggal) AS tahun,
            mp.jenis_pupuk,
            COALESCE(mp.jumlah,0) AS jumlah,
            COALESCE(mp.luas,0)   AS luas,
            COALESCE(mp.invt_pokok,0) AS invt_pokok
          FROM menabur_pupuk mp
          WHERE (:afdeling='' OR mp.afdeling = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)

          UNION ALL

          SELECT
            'Organik' AS sumber,
            uo.nama_unit AS afdeling,
            mpo.tanggal,
            {$bulanFromDate} AS bulan,
            YEAR(mpo.tanggal) AS tahun,
            mpo.jenis_pupuk,
            COALESCE(mpo.jumlah,0) AS jumlah,
            COALESCE(mpo.luas,0)   AS luas,
            COALESCE(mpo.invt_pokok,0) AS invt_pokok
          FROM menabur_pupuk_organik mpo
          LEFT JOIN units uo ON uo.id = mpo.unit_id
          WHERE (:afdeling='' OR uo.nama_unit = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mpo.tanggal) = :tahun)
        ) x
        ORDER BY tahun DESC,
          FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                        'Agustus','September','Oktober','November','Desember'),
          tanggal DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ======================================
     * 5b) PEMUPUKAN_KIMIA (menabur_pupuk)
     * ====================================== */
    if ($section === 'pemupukan_kimia') {
      $sql = "
        SELECT
          mp.afdeling AS afdeling,
          mp.tanggal,
          {$bulanFromDate} AS bulan,
          YEAR(mp.tanggal) AS tahun,
          mp.jenis_pupuk,
          COALESCE(mp.jumlah,0) AS jumlah,
          COALESCE(mp.luas,0)   AS luas,
          COALESCE(mp.invt_pokok,0) AS invt_pokok
        FROM menabur_pupuk mp
        WHERE (:afdeling='' OR mp.afdeling = :afdeling)
          AND (:bulan='' OR {$bulanFromDate} = :bulan)
          AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)
        ORDER BY mp.tanggal DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* =========================================
     * 5c) PEMUPUKAN_ORGANIK (menabur_pupuk_organik)
     * ========================================= */
    if ($section === 'pemupukan_organik') {
      $sql = "
        SELECT
          uo.nama_unit AS afdeling,
          mpo.tanggal,
          {$bulanFromDate} AS bulan,
          YEAR(mpo.tanggal) AS tahun,
          mpo.jenis_pupuk,
          COALESCE(mpo.jumlah,0) AS jumlah,
          COALESCE(mpo.luas,0)   AS luas,
          COALESCE(mpo.invt_pokok,0) AS invt_pokok
        FROM menabur_pupuk_organik mpo
        LEFT JOIN units uo ON uo.id = mpo.unit_id
        WHERE (:afdeling='' OR uo.nama_unit = :afdeling)
          AND (:bulan='' OR {$bulanFromDate} = :bulan)
          AND (:tahun='' OR YEAR(mpo.tanggal) = :tahun)
        ORDER BY mpo.tanggal DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ===================================
     * 6) ANGKUTAN PUPUK (kimia & organik)
     * =================================== */
    if ($section === 'angkutan_kimia' || $section === 'angkutan_organik') {
      $isOrganik = ($section === 'angkutan_organik');

      // angkutan_pupuk: kolom 'jenis_pupuk' berisi string ('Organik', 'Urea', 'NPK ...', 'Dolomite', dll)
      // Kita anggap: jika jenis_pupuk = 'Organik' -> ORGANIK, selain itu -> KIMIA.
      $whereJenis = $isOrganik ? "ap.jenis_pupuk = 'Organik'" : "ap.jenis_pupuk <> 'Organik'";

      $sql = "
        SELECT
          ap.tanggal,
          {$bulanFromDate} AS bulan,
          YEAR(ap.tanggal) AS tahun,
          u.nama_unit AS unit_tujuan,
          ap.gudang_asal,
          ap.jenis_pupuk,
          COALESCE(ap.jumlah,0) AS jumlah,
          ap.nomor_do, ap.supir
        FROM angkutan_pupuk ap
        LEFT JOIN units u ON u.id = ap.unit_tujuan_id
        WHERE {$whereJenis}
          AND (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:bulan='' OR {$bulanFromDate} = :bulan)
          AND (:tahun='' OR YEAR(ap.tanggal) = :tahun)
        ORDER BY ap.tanggal DESC, ap.nomor_do ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ==========================
     * 7) PEMAKAIAN ALAT PANEN
     * ========================== */
    if ($section === 'alat_panen') {
      $sql = "
        SELECT
          u.nama_unit AS afdeling,
          ap.bulan, ap.tahun,
          ap.jenis_alat,
          COALESCE(ap.stok_awal,0)      AS stok_awal,
          COALESCE(ap.mutasi_masuk,0)   AS mutasi_masuk,
          COALESCE(ap.mutasi_keluar,0)  AS mutasi_keluar,
          COALESCE(ap.dipakai,0)        AS dipakai,
          COALESCE(ap.stok_akhir,0)     AS stok_akhir,
          ap.krani_afdeling
        FROM alat_panen ap
        LEFT JOIN units u ON u.id = ap.unit_id
        WHERE (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:bulan='' OR ap.bulan = :bulan)
          AND (:tahun='' OR ap.tahun = :tahun)
        ORDER BY ap.tahun DESC,
          FIELD(ap.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                           'Agustus','September','Oktober','November','Desember'),
          ap.jenis_alat ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    echo json_encode(['success'=>false,'message'=>'Section tidak dikenal']);
  } catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'DB error','error'=>$e->getMessage()]);
  }
  exit;
}

// ====== RENDER HALAMAN ======
$HARI_ID  = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
$BULAN_ID = ["","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$now = new DateTime('now');
$hariIni = $HARI_ID[(int)$now->format('w')] . ', ' . $now->format('d') . ' ' . $BULAN_ID[(int)$now->format('n')] . ' ' . $now->format('Y');
$tahunNow = (int)date('Y');
$bulanList = $BULAN_ID; array_shift($bulanList);

// Ambil daftar Afdeling (real)
$opsiAfdeling = [];
try {
  $sqlAfd = "
    SELECT afd FROM (
      SELECT u.nama_unit AS afd FROM units u
      UNION
      SELECT DISTINCT mp.afdeling AS afd FROM menabur_pupuk mp WHERE mp.afdeling IS NOT NULL AND mp.afdeling <> ''
      UNION
      SELECT DISTINCT u2.nama_unit AS afd FROM pemakaian_bahan_kimia pbk LEFT JOIN units u2 ON u2.id = pbk.unit_id WHERE u2.nama_unit IS NOT NULL AND u2.nama_unit <> ''
      UNION
      SELECT DISTINCT u3.nama_unit AS afd FROM menabur_pupuk_organik mpo LEFT JOIN units u3 ON u3.id = mpo.unit_id WHERE u3.nama_unit IS NOT NULL AND u3.nama_unit <> ''
      UNION
      SELECT DISTINCT COALESCE(NULLIF(pml.afdeling,''), u4.nama_unit) AS afd
      FROM pemeliharaan pml LEFT JOIN units u4 ON u4.id = pml.unit_id
      WHERE COALESCE(NULLIF(pml.afdeling,''), u4.nama_unit) IS NOT NULL AND COALESCE(NULLIF(pml.afdeling,''), u4.nama_unit) <> ''
    ) X
    WHERE afd IS NOT NULL AND afd <> ''
    ORDER BY afd
  ";
  $opsiAfdeling = $pdo->query($sqlAfd)->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $opsiAfdeling = []; }

include_once '../layouts/header.php';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard Utama</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800">

  <!-- Banner Cuaca (dummy) -->
  <div class="bg-slate-900 text-white">
    <div class="mx-auto max-w-7xl px-4 py-3">
      <div class="flex items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 0 0 4 4h9a5 5 0 0 0 0-10 6 6 0 0 0-11.3 2.5M8 20l1.5-2M12 20l1.5-2M16 20l1.5-2" />
        </svg>
        <div class="flex items-baseline gap-3">
          <span class="text-base font-medium">Cuaca Hari Ini</span>
          <span class="text-3xl font-extrabold leading-none">30°C</span>
        </div>
      </div>
    </div>
  </div>

  <div class="min-h-screen flex">
    <main class="flex-1">
      <header class="bg-white border-b sticky top-0 z-10">
        <div class="mx-auto max-w-7xl px-4 py-3 flex items-center justify-between">
          <div>
            <div class="text-sm text-slate-500">Dashboard Utama</div>
            <h1 class="text-2xl font-bold"><?= htmlspecialchars($hariIni) ?></h1>
          </div>
          <div class="flex items-center gap-3">
            <select id="f-afdeling" class="border rounded-lg px-3 py-2 min-w-[170px]">
              <option value="">Semua Afdeling</option>
              <?php foreach ($opsiAfdeling as $afd): ?>
                <option value="<?= htmlspecialchars($afd) ?>"><?= htmlspecialchars($afd) ?></option>
              <?php endforeach; ?>
            </select>
            <select id="f-bulan" class="border rounded-lg px-3 py-2 min-w-[140px]">
              <option value="">Semua Bulan</option>
              <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
            </select>
            <select id="f-tahun" class="border rounded-lg px-3 py-2 min-w-[110px]">
              <?php for ($y=$tahunNow-2; $y<=$tahunNow+2; $y++): ?>
                <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
            <button id="btn-refresh" class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg">Refresh</button>
          </div>
        </div>
      </header>

      <div class="mx-auto max-w-7xl px-4 py-6">
        <!-- KPIs -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white p-5 rounded-xl shadow-sm">
            <p class="text-slate-500 text-sm">Total Item Gudang</p>
            <h3 id="kpi-total-item" class="text-3xl font-extrabold mt-1">—</h3>
            <p class="text-xs text-slate-400 mt-1">Distinct nama bahan (periode dipilih)</p>
          </div>
          <div class="bg-white p-5 rounded-xl shadow-sm">
            <p class="text-slate-500 text-sm">Stok Bersih</p>
            <h3 id="kpi-stok-bersih" class="text-3xl font-extrabold mt-1 text-emerald-600">—</h3>
            <p class="text-xs text-slate-400 mt-1">Awal + Masuk + Pasokan − Keluar − Dipakai</p>
          </div>
          <div class="bg-white p-5 rounded-xl shadow-sm">
            <p class="text-slate-500 text-sm">Total Pemakaian (fisik)</p>
            <h3 id="kpi-pemakaian" class="text-3xl font-extrabold mt-1 text-sky-600">—</h3>
            <p class="text-xs text-slate-400 mt-1">Σ jlh_fisik (kimia + pemupukan)</p>
          </div>
          <div class="bg-white p-5 rounded-xl shadow-sm">
            <p class="text-slate-500 text-sm">Dokumen Pemakaian</p>
            <h3 id="kpi-doc" class="text-3xl font-extrabold mt-1 text-amber-600">—</h3>
            <p class="text-xs text-slate-400 mt-1">Count no_dokumen</p>
          </div>
        </section>

        <!-- STOK & PEMAKAIAN (lama) -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
          <div class="bg-white p-6 rounded-xl shadow">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold">Top Sisa Stok Gudang</h3>
              <select id="limit-stok" class="border rounded px-2 py-1 text-sm">
                <option value="5">Top 5</option><option value="10" selected>Top 10</option><option value="20">Top 20</option>
              </select>
            </div>
            <canvas id="ch-stok-top" height="180"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Tren Pemakaian (per Bulan)</h3>
            <canvas id="ch-pemakaian-tren" height="180"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Komposisi Pemakaian</h3>
            <canvas id="ch-pemakaian-komposisi" height="220"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Ringkasan Mutasi Gudang</h3>
            <canvas id="ch-mutasi-stacked" height="220"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pemakaian per Afdeling</h3>
            <canvas id="ch-per-afdeling" height="200"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Top Bahan: Diminta vs Fisik</h3>
            <canvas id="ch-diminta-fisik" height="200"></canvas>
          </div>
        </section>

        <!-- PEKERJAAN (LM Biaya) -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-8">
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pekerjaan: Rencana vs Realisasi per Aktivitas</h3>
            <canvas id="ch-pekerjaan-aktivitas" height="200"></canvas>
          </div>
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pekerjaan: Komposisi per Jenis</h3>
            <canvas id="ch-pekerjaan-jenis" height="220"></canvas>
          </div>
        </section>

        <!-- PEMELIHARAAN -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-8">
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pemeliharaan: Realisasi per Jenis</h3>
            <canvas id="ch-pemeliharaan-jenis" height="200"></canvas>
          </div>
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pemeliharaan: Status Pekerjaan</h3>
            <canvas id="ch-pemeliharaan-status" height="200"></canvas>
          </div>
        </section>

        <!-- PEMUPUKAN (kimia & organik) -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-8">
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pemupukan (Kimia): Tren Jumlah</h3>
            <canvas id="ch-pemupukan-tren-kimia" height="180"></canvas>
          </div>
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pemupukan (Organik): Tren Jumlah</h3>
            <canvas id="ch-pemupukan-tren-organik" height="180"></canvas>
          </div>
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pemupukan: Komposisi Jenis (Kimia)</h3>
            <canvas id="ch-pemupukan-komposisi-kimia" height="220"></canvas>
          </div>
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pemupukan: Komposisi Jenis (Organik)</h3>
            <canvas id="ch-pemupukan-komposisi-organik" height="220"></canvas>
          </div>
        </section>

        <!-- ANGKUTAN PUPUK -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-8">
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Angkutan Pupuk (Kimia): Tren Jumlah</h3>
            <canvas id="ch-angkutan-tren-kimia" height="180"></canvas>
          </div>
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Angkutan Pupuk (Organik): Tren Jumlah</h3>
            <canvas id="ch-angkutan-tren-organik" height="180"></canvas>
          </div>
        </section>

        <!-- ALAT PANEN -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-8">
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Alat Panen: Dipakai per Jenis</h3>
            <canvas id="ch-alat-panen-dipakai" height="200"></canvas>
          </div>
          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Alat Panen: Sisa Stok (Akhir) per Jenis</h3>
            <canvas id="ch-alat-panen-sisa" height="200"></canvas>
          </div>
        </section>
      </div>
    </main>
  </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);

  // Filter controls
  const fAfd = $('#f-afdeling');
  const fBulan = $('#f-bulan');
  const fTahun = $('#f-tahun');
  const limitStok = $('#limit-stok');
  $('#btn-refresh').addEventListener('click', () => refreshDashboard(false));

  // KPI nodes
  const KPI = {
    total:  $('#kpi-total-item'),
    stok:   $('#kpi-stok-bersih'),
    pakai:  $('#kpi-pemakaian'),
    doc:    $('#kpi-doc')
  };

  // chart refs
  let chStokTop, chPemTren, chKomposisi, chMutasi, chPerAfd, chDimintaFisik;
  let chPkjAkt, chPkjJenis, chPmlJenis, chPmlStatus;
  let chPpkTrenKimia, chPpkTrenOrganik, chPpkKomKimia, chPpkKomOrganik;
  let chAngTrenKimia, chAngTrenOrganik;
  let chAlatDipakai, chAlatSisa;

  const bulanOrder = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  const palette = n => Array.from({length:n}, (_,i)=>['#16a34a','#f59e0b','#0ea5e9','#a855f7','#ef4444','#10b981','#6366f1','#22c55e','#eab308','#06b6d4','#f97316','#84cc16'][i%12]);
  const fmt = x => Number(x||0).toLocaleString(undefined,{maximumFractionDigits:2});
  const sisaStok = r => (+r.stok_awal||0)+(+r.mutasi_masuk||0)+(+r.pasokan||0)-(+r.mutasi_keluar||0)-(+r.dipakai||0);

  async function postJSON(section, extra){
    const fd = new FormData();
    fd.append('ajax','dashboard');
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('section', section);
    fd.append('afdeling', fAfd.value || '');
    fd.append('bulan',    fBulan.value || '');
    fd.append('tahun',    fTahun.value || '');
    if (extra) Object.entries(extra).forEach(([k,v])=> fd.append(k,v));
    const ctrl = new AbortController(); const t = setTimeout(()=>ctrl.abort(), 15000);
    try {
      const res = await fetch(location.href, {method:'POST', body:fd, signal:ctrl.signal});
      clearTimeout(t);
      return await res.json();
    } catch (e) {
      clearTimeout(t);
      return {success:false,error:String(e)};
    }
  }

  // Loaders
  const fetchGudang   = async()=> (await postJSON('gudang'))?.data || [];
  const fetchMixPem   = async()=> (await postJSON('pemakaian'))?.data || [];
  const fetchPkj      = async()=> (await postJSON('pekerjaan'))?.data || [];
  const fetchPml      = async()=> (await postJSON('pemeliharaan'))?.data || [];
  const fetchPpkK     = async()=> (await postJSON('pemupukan_kimia'))?.data || [];
  const fetchPpkO     = async()=> (await postJSON('pemupukan_organik'))?.data || [];
  const fetchAngK     = async()=> (await postJSON('angkutan_kimia'))?.data || [];
  const fetchAngO     = async()=> (await postJSON('angkutan_organik'))?.data || [];
  const fetchAlat     = async()=> (await postJSON('alat_panen'))?.data || [];

  // chart helpers
  function renderOrUpdate(ctxId, cfg, holder){
    const ctx = document.getElementById(ctxId)?.getContext('2d');
    if (!ctx) return;
    if (holder.value) { holder.value.data = cfg.data; holder.value.options = cfg.options||holder.value.options; holder.value.update(); }
    else { holder.value = new Chart(ctx, cfg); }
  }
  const barCfg  = (labels, datasets, stacked=false, horizontal=false)=>({type:'bar', data:{labels,datasets}, options:{responsive:true, indexAxis:horizontal?'y':'x', plugins:{legend:{display:true}}, scales: stacked?{x:{stacked:true},y:{stacked:true}}:{}}});
  const lineCfg = (labels, dataset)=>({type:'line', data:{labels, datasets:[dataset]}, options:{responsive:true, plugins:{legend:{display:true}}, tension:.35, elements:{point:{radius:3}}, scales:{y:{beginAtZero:true}}}});
  const pieCfg  = (labels, data)=>({type:'pie', data:{labels, datasets:[{data, backgroundColor: palette(labels.length)}]}, options:{responsive:true, plugins:{legend:{position:'right'}}}});

  async function refreshDashboard(showLoading=true){
    if (showLoading) KPI.total.textContent = KPI.stok.textContent = KPI.pakai.textContent = KPI.doc.textContent = '…';

    const [
      rowsG, rowsMix, rowsPkj, rowsPml, rowsPpkK, rowsPpkO, rowsAngK, rowsAngO, rowsAlat
    ] = await Promise.all([
      fetchGudang(), fetchMixPem(), fetchPkj(), fetchPml(), fetchPpkK(), fetchPpkO(), fetchAngK(), fetchAngO(), fetchAlat()
    ]);

    /* ===== KPI (fixed) ===== */
    const distinctItems = new Set(rowsG.map(r => r.nama_bahan || '-'));
    KPI.total.textContent = fmt(distinctItems.size);
    KPI.stok.textContent  = fmt(rowsG.reduce((a,r)=> a + sisaStok(r), 0));

    KPI.pakai.textContent = fmt(rowsMix.reduce((a,r)=> a + (+r.jlh_fisik||0), 0));
    KPI.doc.textContent   = fmt(rowsMix.length);

    /* ===== STOK: Top sisa ===== */
    const aggStok = {};
    rowsG.forEach(r => { const k=r.nama_bahan||'-'; aggStok[k]=(aggStok[k]||0)+sisaStok(r); });
    const top = Object.entries(aggStok).sort((a,b)=>b[1]-a[1]).slice(0, parseInt(limitStok.value,10)||10);
    renderOrUpdate('ch-stok-top',
      barCfg(top.map(x=>x[0]), [{label:'Sisa Stok', data: top.map(x=>x[1]), backgroundColor: palette(top.length)}], false, true),
      {get value(){return chStokTop}, set value(v){chStokTop=v}}
    );

    /* ===== Pemakaian (gabungan) ===== */
    const bulanOrder = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const mapTren = new Map(bulanOrder.map(b=>[b,0]));
    rowsMix.forEach(r=> mapTren.set(r.bulan, (mapTren.get(r.bulan)||0) + (+r.jlh_fisik||0)));
    renderOrUpdate('ch-pemakaian-tren',
      lineCfg(bulanOrder, {label:`Pemakaian Total (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>mapTren.get(b)||0)}),
      {get value(){return chPemTren}, set value(v){chPemTren=v}}
    );

    const kom = {};
    rowsMix.forEach(r=> { const k=r.nama_bahan||'-'; kom[k]=(kom[k]||0)+(+r.jlh_fisik||0); });
    const komArr = Object.entries(kom).sort((a,b)=>b[1]-a[1]);
    const main = komArr.slice(0,9); const other = komArr.slice(9).reduce((a,b)=>a+(b[1]||0),0);
    if (other>0) main.push(['Lainnya',other]);
    renderOrUpdate('ch-pemakaian-komposisi',
      pieCfg(main.map(x=>x[0]), main.map(x=>x[1])),
      {get value(){return chKomposisi}, set value(v){chKomposisi=v}}
    );

    const sumMut = rowsG.reduce((o,r)=>{o.masuk+=(+r.mutasi_masuk||0);o.keluar+=(+r.mutasi_keluar||0);o.pasokan+=(+r.pasokan||0);o.dipakai+=(+r.dipakai||0);return o;},{masuk:0,keluar:0,pasokan:0,dipakai:0});
    renderOrUpdate('ch-mutasi-stacked',
      barCfg(['Mutasi'], [
        {label:'Masuk', data:[sumMut.masuk]},
        {label:'Keluar', data:[sumMut.keluar]},
        {label:'Pasokan', data:[sumMut.pasokan]},
        {label:'Dipakai', data:[sumMut.dipakai]}
      ], true, false),
      {get value(){return chMutasi}, set value(v){chMutasi=v}}
    );

    const perAfd = {};
    rowsMix.forEach(r=>{ const k=r.afdeling||'-'; perAfd[k]=(perAfd[k]||0)+(+r.jlh_fisik||0); });
    const afdArr = Object.entries(perAfd).sort((a,b)=>b[1]-a[1]);
    renderOrUpdate('ch-per-afdeling',
      barCfg(afdArr.map(x=>x[0]), [{label:'Fisik', data: afdArr.map(x=>x[1])}]),
      {get value(){return chPerAfd}, set value(v){chPerAfd=v}}
    );

    const df = {};
    rowsMix.forEach(r=>{ const k=r.nama_bahan||'-'; if(!df[k]) df[k]={diminta:0,fisik:0}; df[k].diminta+=(+r.jlh_diminta||0); df[k].fisik+=(+r.jlh_fisik||0); });
    const dfArr = Object.entries(df).sort((a,b)=>b[1].fisik-a[1].fisik).slice(0,10);
    renderOrUpdate('ch-diminta-fisik',
      barCfg(dfArr.map(x=>x[0]), [
        {label:'Diminta', data: dfArr.map(x=>x[1].diminta)},
        {label:'Fisik',   data: dfArr.map(x=>x[1].fisik)}
      ]),
      {get value(){return chDimintaFisik}, set value(v){chDimintaFisik=v}}
    );

    /* ===== Pekerjaan (LM Biaya) ===== */
    const actAgg = {};
    const jenisAgg = {};
    rowsPkj.forEach(r=>{
      const k = (r.kode_aktivitas||'-') + ' ' + (r.nama_aktivitas||'');
      actAgg[k] = actAgg[k] || {rencana:0, realisasi:0};
      actAgg[k].rencana += +r.rencana_bi||0;
      actAgg[k].realisasi += +r.realisasi_bi||0;
      const j = r.jenis_pekerjaan || '-';
      jenisAgg[j] = (jenisAgg[j]||0) + (+r.realisasi_bi||0);
    });
    const actArr = Object.entries(actAgg).map(([k,v])=>({k,...v})).sort((a,b)=>b.realisasi-a.realisasi).slice(0,10);
    renderOrUpdate('ch-pekerjaan-aktivitas',
      barCfg(actArr.map(x=>x.k), [
        {label:'Rencana', data: actArr.map(x=>x.rencana)},
        {label:'Realisasi', data: actArr.map(x=>x.realisasi)}
      ], false, true),
      {get value(){return chPkjAkt}, set value(v){chPkjAkt=v}}
    );
    const jenisArr = Object.entries(jenisAgg).sort((a,b)=>b[1]-a[1]);
    renderOrUpdate('ch-pekerjaan-jenis',
      pieCfg(jenisArr.map(x=>x[0]), jenisArr.map(x=>x[1])),
      {get value(){return chPkjJenis}, set value(v){chPkjJenis=v}}
    );

    /* ===== Pemeliharaan ===== */
    const pmlJenis = {};
    const pmlStatus= {Berjalan:0,Selesai:0,Tertunda:0};
    rowsPml.forEach(r=>{
      const k = r.jenis_pekerjaan || '-';
      pmlJenis[k] = (pmlJenis[k]||0) + (+r.realisasi||0);
      const s = r.status || 'Berjalan';
      if (pmlStatus[s] === undefined) pmlStatus[s] = 0;
      pmlStatus[s] += 1;
    });
    const pmlJenisArr = Object.entries(pmlJenis).sort((a,b)=>b[1]-a[1]).slice(0,12);
    renderOrUpdate('ch-pemeliharaan-jenis',
      barCfg(pmlJenisArr.map(x=>x[0]), [{label:'Realisasi', data:pmlJenisArr.map(x=>x[1])}], false, true),
      {get value(){return chPmlJenis}, set value(v){chPmlJenis=v}}
    );
    renderOrUpdate('ch-pemeliharaan-status',
      pieCfg(Object.keys(pmlStatus), Object.values(pmlStatus)),
      {get value(){return chPmlStatus}, set value(v){chPmlStatus=v}}
    );

    /* ===== Pemupukan Kimia ===== */
    const ppkTrenK = new Map(bulanOrder.map(b=>[b,0]));
    const ppkJenisK = {};
    rowsPpkK.forEach(r=>{
      ppkTrenK.set(r.bulan, (ppkTrenK.get(r.bulan)||0) + (+r.jumlah||0));
      const k = r.jenis_pupuk || '-';
      ppkJenisK[k] = (ppkJenisK[k]||0) + (+r.jumlah||0);
    });
    renderOrUpdate('ch-pemupukan-tren-kimia',
      lineCfg(bulanOrder, {label:`Pemupukan Kimia (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>ppkTrenK.get(b)||0)}),
      {get value(){return chPpkTrenKimia}, set value(v){chPpkTrenKimia=v}}
    );
    const ppkJenisArrK = Object.entries(ppkJenisK).sort((a,b)=>b[1]-a[1]);
    renderOrUpdate('ch-pemupukan-komposisi-kimia',
      pieCfg(ppkJenisArrK.map(x=>x[0]), ppkJenisArrK.map(x=>x[1])),
      {get value(){return chPpkKomKimia}, set value(v){chPpkKomKimia=v}}
    );

    /* ===== Pemupukan Organik ===== */
    const ppkTrenO = new Map(bulanOrder.map(b=>[b,0]));
    const ppkJenisO = {};
    rowsPpkO.forEach(r=>{
      ppkTrenO.set(r.bulan, (ppkTrenO.get(r.bulan)||0) + (+r.jumlah||0));
      const k = r.jenis_pupuk || '-';
      ppkJenisO[k] = (ppkJenisO[k]||0) + (+r.jumlah||0);
    });
    renderOrUpdate('ch-pemupukan-tren-organik',
      lineCfg(bulanOrder, {label:`Pemupukan Organik (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>ppkTrenO.get(b)||0)}),
      {get value(){return chPpkTrenOrganik}, set value(v){chPpkTrenOrganik=v}}
    );
    const ppkJenisArrO = Object.entries(ppkJenisO).sort((a,b)=>b[1]-a[1]);
    renderOrUpdate('ch-pemupukan-komposisi-organik',
      pieCfg(ppkJenisArrO.map(x=>x[0]), ppkJenisArrO.map(x=>x[1])),
      {get value(){return chPpkKomOrganik}, set value(v){chPpkKomOrganik=v}}
    );

    /* ===== Angkutan Pupuk Kimia/Organik ===== */
    const angTrenK = new Map(bulanOrder.map(b=>[b,0]));
    rowsAngK.forEach(r=> angTrenK.set(r.bulan, (angTrenK.get(r.bulan)||0) + (+r.jumlah||0)));
    renderOrUpdate('ch-angkutan-tren-kimia',
      lineCfg(bulanOrder, {label:`Angkutan Kimia (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>angTrenK.get(b)||0)}),
      {get value(){return chAngTrenKimia}, set value(v){chAngTrenKimia=v}}
    );

    const angTrenO = new Map(bulanOrder.map(b=>[b,0]));
    rowsAngO.forEach(r=> angTrenO.set(r.bulan, (angTrenO.get(r.bulan)||0) + (+r.jumlah||0)));
    renderOrUpdate('ch-angkutan-tren-organik',
      lineCfg(bulanOrder, {label:`Angkutan Organik (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>angTrenO.get(b)||0)}),
      {get value(){return chAngTrenOrganik}, set value(v){chAngTrenOrganik=v}}
    );

    /* ===== Alat Panen ===== */
    const alatDipakai = {};
    const alatSisa   = {};
    rowsAlat.forEach(r=>{
      const k = r.jenis_alat || '-';
      alatDipakai[k] = (alatDipakai[k]||0) + (+r.dipakai||0);
      alatSisa[k]    = (alatSisa[k]||0) + (+r.stok_akhir||0);
    });
    const alatD = Object.entries(alatDipakai).sort((a,b)=>b[1]-a[1]).slice(0,10);
    renderOrUpdate('ch-alat-panen-dipakai',
      barCfg(alatD.map(x=>x[0]), [{label:'Dipakai', data: alatD.map(x=>x[1])}], false, true),
      {get value(){return chAlatDipakai}, set value(v){chAlatDipakai=v}}
    );
    const alatS = Object.entries(alatSisa).sort((a,b)=>b[1]-a[1]).slice(0,10);
    renderOrUpdate('ch-alat-panen-sisa',
      barCfg(alatS.map(x=>x[0]), [{label:'Stok Akhir', data: alatS.map(x=>x[1])}], false, true),
      {get value(){return chAlatSisa}, set value(v){chAlatSisa=v}}
    );
  }

  // events
  [fAfd, fBulan, fTahun, limitStok].forEach(el => el.addEventListener('change', () => refreshDashboard(false)));

  // init
  refreshDashboard(true);
  setInterval(()=>refreshDashboard(false), 30000);
});
</script>
</body>
</html>
