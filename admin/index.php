<?php
// admin/dashboard.php (FULL — +Filter Kebun + KPI tambahan + Section Pemeliharaan & Chart LM)
// Versi: 2025-10-01
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
  $afdeling = trim($_POST['afdeling'] ?? '');  // pakai units.nama_unit / string afdeling
  $bulan    = trim($_POST['bulan']    ?? '');  // enum IND
  $tahun    = trim($_POST['tahun']    ?? '');  // YEAR
  $kebun    = trim($_POST['kebun']    ?? '');  // md_kebun.nama_kebun

  // helper enum bulan dari DATE column
  $bulanFromDate = "ELT(MONTH(tanggal),
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember')";

  try {
    /* =========================
     * 1) STOK GUDANG
     * ========================= */
    if ($section === 'gudang') {
      $sql = "
        SELECT
          mk.nama_kebun,
          b.nama_bahan,
          s.nama AS satuan,
          sg.bulan, sg.tahun,
          sg.stok_awal, sg.mutasi_masuk, sg.mutasi_keluar, sg.pasokan, sg.dipakai
        FROM stok_gudang sg
        JOIN md_bahan_kimia b ON b.id = sg.bahan_id
        LEFT JOIN md_satuan s ON s.id = b.satuan_id
        JOIN md_kebun mk ON mk.id = sg.kebun_id
        WHERE (:bulan='' OR sg.bulan = :bulan)
          AND (:tahun='' OR sg.tahun = :tahun)
          AND (:kebun='' OR mk.nama_kebun = :kebun)
        ORDER BY sg.tahun DESC,
          FIELD(sg.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                          'Agustus','September','Oktober','November','Desember'),
          b.nama_bahan ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ==========================================
     * 1b) KPI Pemeliharaan ringkas (TU/TBM/TM/PN/MN)
     * ========================================== */
    if ($section === 'pemeliharaan_kpi') {
      // Map label tampil -> nilai enum tabel
      $map = [
        'TU' => 'TU',
        'TBM'=> 'TBM',
        'TM' => 'TM',
        'PN' => 'BIBIT_PN',
        'MN' => 'BIBIT_MN',
      ];
      $rows = [];
      foreach ($map as $label => $kategori) {
        $sql = "
          SELECT
            :label AS label,
            COALESCE(SUM(pml.rencana),0)  AS rencana,
            COALESCE(SUM(pml.realisasi),0) AS realisasi
          FROM pemeliharaan pml
          LEFT JOIN units u ON u.id = pml.unit_id
          WHERE pml.kategori = :kat
            AND (:afdeling='' OR COALESCE(NULLIF(pml.afdeling,''), u.nama_unit) = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(pml.tanggal) = :tahun)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([
          ':label'=>$label, ':kat'=>$kategori,
          ':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun
        ]);
        $rows[] = $st->fetch(PDO::FETCH_ASSOC);
      }
      echo json_encode(['success'=>true,'data'=>$rows]); exit;
    }

    /* ==========================================
     * 1c) Tren LM76 per bulan (sum prod_bi_realisasi)
     * ========================================== */
    if ($section === 'lm76_tren') {
      $sql = "
        SELECT lm.bulan, lm.tahun, SUM(COALESCE(lm.prod_bi_realisasi,0)) AS total
        FROM lm76 lm
        LEFT JOIN units u ON u.id = lm.unit_id
        WHERE (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:tahun='' OR lm.tahun = :tahun)
          AND (:bulan='' OR lm.bulan = :bulan)
        GROUP BY lm.tahun, FIELD(lm.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), lm.bulan
        ORDER BY lm.tahun DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':tahun'=>$tahun, ':bulan'=>$bulan]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ==========================================
     * 1d) Tren LM77 per bulan (sum prestasi_kg_hk_bi)
     * ========================================== */
    if ($section === 'lm77_tren') {
      $sql = "
        SELECT lm.bulan, lm.tahun, SUM(COALESCE(lm.prestasi_kg_hk_bi,0)) AS total
        FROM lm77 lm
        LEFT JOIN units u ON u.id = lm.unit_id
        WHERE (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:tahun='' OR lm.tahun = :tahun)
          AND (:bulan='' OR lm.bulan = :bulan)
        GROUP BY lm.tahun, FIELD(lm.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), lm.bulan
        ORDER BY lm.tahun DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':tahun'=>$tahun, ':bulan'=>$bulan]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ==========================================
     * 1e) Tren LM Biaya per bulan (sum realisasi_bi)
     * ========================================== */
    if ($section === 'lm_biaya_tren') {
      $sql = "
        SELECT lb.bulan, lb.tahun, SUM(COALESCE(lb.realisasi_bi,0)) AS total
        FROM lm_biaya lb
        LEFT JOIN units u ON u.id = lb.unit_id
        LEFT JOIN md_kebun mk ON mk.id = lb.kebun_id
        WHERE (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:kebun='' OR mk.nama_kebun = :kebun)
          AND (:tahun='' OR lb.tahun = :tahun)
          AND (:bulan='' OR lb.bulan = :bulan)
        GROUP BY lb.tahun, FIELD(lb.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), lb.bulan
        ORDER BY lb.tahun DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':kebun'=>$kebun, ':tahun'=>$tahun, ':bulan'=>$bulan]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ==========================================
     * 2) PEMAKAIAN (gabungan kompat lama)
     * ========================================== */
    if ($section === 'pemakaian') {
      $sql = "
        SELECT * FROM (
          /* 2.1 pemakaian bahan kimia (TIDAK ada kolom kebun di tabel ini) */
          SELECT
            pbk.no_dokumen,
            u.nama_unit AS afdeling,
            pbk.bulan,
            pbk.tahun,
            pbk.nama_bahan,
            pbk.jenis_pekerjaan,
            COALESCE(pbk.jlh_diminta,0) AS jlh_diminta,
            COALESCE(pbk.jlh_fisik,0)   AS jlh_fisik,
            NULL AS nama_kebun
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
            COALESCE(mp.jumlah,0) AS jlh_fisik,
            mk.nama_kebun
          FROM menabur_pupuk mp
          JOIN md_kebun mk ON mk.kode = mp.kebun_kode
          WHERE (:afdeling='' OR mp.afdeling = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)
            AND (:kebun='' OR mk.nama_kebun = :kebun)

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
            COALESCE(mpo.jumlah,0) AS jlh_fisik,
            mk2.nama_kebun
          FROM menabur_pupuk_organik mpo
          LEFT JOIN units uo ON uo.id = mpo.unit_id
          LEFT JOIN md_kebun mk2 ON mk2.id = mpo.kebun_id
          WHERE (:afdeling='' OR uo.nama_unit = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mpo.tanggal) = :tahun)
            AND (:kebun='' OR mk2.nama_kebun = :kebun)
        ) x
        ORDER BY
          tahun DESC,
          FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                        'Agustus','September','Oktober','November','Desember'),
          no_dokumen ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
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
          mk.nama_kebun,
          ka.kode AS kode_aktivitas, ka.nama AS nama_aktivitas,
          jp.nama AS jenis_pekerjaan,
          lb.rencana_bi, lb.realisasi_bi
        FROM lm_biaya lb
        LEFT JOIN units u ON u.id = lb.unit_id
        LEFT JOIN md_kebun mk ON mk.id = lb.kebun_id
        LEFT JOIN md_kode_aktivitas ka ON ka.id = lb.kode_aktivitas_id
        LEFT JOIN md_jenis_pekerjaan jp ON jp.id = lb.jenis_pekerjaan_id
        WHERE (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:bulan='' OR lb.bulan = :bulan)
          AND (:tahun='' OR lb.tahun = :tahun)
          AND (:kebun='' OR mk.nama_kebun = :kebun)
        ORDER BY lb.tahun DESC,
          FIELD(lb.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                           'Agustus','September','Oktober','November','Desember'),
          ka.kode ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
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
          pml.rencana, pml.realisasi, pml.status,
          mk.nama_kebun
        FROM pemeliharaan pml
        LEFT JOIN units u ON u.id = pml.unit_id
        LEFT JOIN md_kebun mk ON mk.id = NULL  /* placeholder: tidak ada kolom kebun di tabel ini */
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
            COALESCE(mp.invt_pokok,0) AS invt_pokok,
            mk.nama_kebun
          FROM menabur_pupuk mp
          JOIN md_kebun mk ON mk.kode = mp.kebun_kode
          WHERE (:afdeling='' OR mp.afdeling = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)
            AND (:kebun='' OR mk.nama_kebun = :kebun)

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
            COALESCE(mpo.invt_pokok,0) AS invt_pokok,
            mk2.nama_kebun
          FROM menabur_pupuk_organik mpo
          LEFT JOIN units uo ON uo.id = mpo.unit_id
          LEFT JOIN md_kebun mk2 ON mk2.id = mpo.kebun_id
          WHERE (:afdeling='' OR uo.nama_unit = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mpo.tanggal) = :tahun)
            AND (:kebun='' OR mk2.nama_kebun = :kebun)
        ) x
        ORDER BY tahun DESC,
          FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                        'Agustus','September','Oktober','November','Desember'),
          tanggal DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
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
          COALESCE(mp.invt_pokok,0) AS invt_pokok,
          mk.nama_kebun
        FROM menabur_pupuk mp
        JOIN md_kebun mk ON mk.kode = mp.kebun_kode
        WHERE (:afdeling='' OR mp.afdeling = :afdeling)
          AND (:bulan='' OR {$bulanFromDate} = :bulan)
          AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)
          AND (:kebun='' OR mk.nama_kebun = :kebun)
        ORDER BY mp.tanggal DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
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
          COALESCE(mpo.invt_pokok,0) AS invt_pokok,
          mk.nama_kebun
        FROM menabur_pupuk_organik mpo
        LEFT JOIN units uo ON uo.id = mpo.unit_id
        LEFT JOIN md_kebun mk ON mk.id = mpo.kebun_id
        WHERE (:afdeling='' OR uo.nama_unit = :afdeling)
          AND (:bulan='' OR {$bulanFromDate} = :bulan)
          AND (:tahun='' OR YEAR(mpo.tanggal) = :tahun)
          AND (:kebun='' OR mk.nama_kebun = :kebun)
        ORDER BY mpo.tanggal DESC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ===================================
     * 6) ANGKUTAN PUPUK (kimia & organik)
     * =================================== */
    if ($section === 'angkutan_kimia' || $section === 'angkutan_organik') {
      $isOrganik = ($section === 'angkutan_organik');
      $whereJenis = $isOrganik ? "ap.jenis_pupuk = 'Organik'" : "ap.jenis_pupuk <> 'Organik'";

      $sql = "
        SELECT
          ap.tanggal,
          {$bulanFromDate} AS bulan,
          YEAR(ap.tanggal) AS tahun,
          u.nama_unit AS unit_tujuan,
          mk.nama_kebun,
          ap.gudang_asal,
          ap.jenis_pupuk,
          COALESCE(ap.jumlah,0) AS jumlah,
          ap.nomor_do, ap.supir
        FROM angkutan_pupuk ap
        LEFT JOIN units u ON u.id = ap.unit_tujuan_id
        LEFT JOIN md_kebun mk ON mk.kode = ap.kebun_kode
        WHERE {$whereJenis}
          AND (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:bulan='' OR {$bulanFromDate} = :bulan)
          AND (:tahun='' OR YEAR(ap.tanggal) = :tahun)
          AND (:kebun='' OR mk.nama_kebun = :kebun)
        ORDER BY ap.tanggal DESC, ap.nomor_do ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    /* ==========================
     * 7) PEMAKAIAN ALAT PANEN
     * ========================== */
    if ($section === 'alat_panen') {
      $sql = "
        SELECT
          mk.nama_kebun,
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
        LEFT JOIn md_kebun mk ON mk.id = ap.kebun_id
        WHERE (:afdeling='' OR u.nama_unit = :afdeling)
          AND (:bulan='' OR ap.bulan = :bulan)
          AND (:tahun='' OR ap.tahun = :tahun)
          AND (:kebun='' OR mk.nama_kebun = :kebun)
        ORDER BY ap.tahun DESC,
          FIELD(ap.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                           'Agustus','September','Oktober','November','Desember'),
          ap.jenis_alat ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
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

// Ambil daftar Afdeling & Kebun
$opsiAfdeling = [];
$opsiKebun    = [];
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

  $opsiKebun = $pdo->query("SELECT nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $opsiAfdeling = []; $opsiKebun = []; }

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
  <div class="min-h-screen flex">
    <main class="flex-1">
      <header class="bg-white border-b sticky top-0 z-10">
        <div class="mx-auto max-w-7xl px-4 py-3 flex items-center justify-between">
          <div>
            <div class="text-sm text-slate-500">Dashboard Utama</div>
            <h1 class="text-2xl font-bold"><?= htmlspecialchars($hariIni) ?></h1>
          </div>
          <div class="flex flex-wrap gap-3 items-center">
            <select id="f-kebun" class="border rounded-lg px-3 py-2 min-w-[180px]">
              <option value="">Semua Kebun</option>
              <?php foreach ($opsiKebun as $kb): ?>
                <option value="<?= htmlspecialchars($kb) ?>"><?= htmlspecialchars($kb) ?></option>
              <?php endforeach; ?>
            </select>
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
        <!-- KPIs (12 kotak existing) -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Total Item Gudang</p><h3 id="kpi-total-item" class="text-3xl font-extrabold mt-1">—</h3><p class="text-xs text-slate-400 mt-1">Distinct nama bahan (periode dipilih)</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Stok Bersih</p><h3 id="kpi-stok-bersih" class="text-3xl font-extrabold mt-1 text-emerald-600">—</h3><p class="text-xs text-slate-400 mt-1">Awal + Masuk + Pasokan − Keluar − Dipakai</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Total Pemakaian (fisik)</p><h3 id="kpi-pemakaian" class="text-3xl font-extrabold mt-1 text-sky-600">—</h3><p class="text-xs text-slate-400 mt-1">Σ jlh_fisik (kimia + pemupukan)</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Dokumen Pemakaian</p><h3 id="kpi-doc" class="text-3xl font-extrabold mt-1 text-amber-600">—</h3><p class="text-xs text-slate-400 mt-1">Count no_dokumen</p></div>

          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Pemupukan Kimia (Σ jumlah)</p><h3 id="kpi-ppk-kimia" class="text-2xl font-extrabold mt-1 text-indigo-600">—</h3><p class="text-xs text-slate-400 mt-1">menabur_pupuk</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Pemupukan Organik (Σ jumlah)</p><h3 id="kpi-ppk-organik" class="text-2xl font-extrabold mt-1 text-teal-600">—</h3><p class="text-xs text-slate-400 mt-1">menabur_pupuk_organik</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Angkutan Kimia (Σ jumlah)</p><h3 id="kpi-ang-kimia" class="text-2xl font-extrabold mt-1 text-fuchsia-600">—</h3><p class="text-xs text-slate-400 mt-1">angkutan_pupuk ≠ Organik</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Angkutan Organik (Σ jumlah)</p><h3 id="kpi-ang-organik" class="text-2xl font-extrabold mt-1 text-rose-600">—</h3><p class="text-xs text-slate-400 mt-1">angkutan_pupuk = Organik</p></div>

          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Distinct Bahan Terpakai</p><h3 id="kpi-bahan-pakai" class="text-2xl font-extrabold mt-1 text-slate-700">—</h3><p class="text-xs text-slate-400 mt-1">Dari pemakaian & pemupukan</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Afdeling Aktif</p><h3 id="kpi-afdeling-aktif" class="text-2xl font-extrabold mt-1 text-slate-700">—</h3><p class="text-xs text-slate-400 mt-1">Memiliki transaksi</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Alat Panen Dipakai (Σ)</p><h3 id="kpi-alat-dipakai" class="text-2xl font-extrabold mt-1 text-emerald-700">—</h3><p class="text-xs text-slate-400 mt-1">Σ dipakai</p></div>
          <div class="bg-white p-5 rounded-xl shadow-sm"><p class="text-slate-500 text-sm">Sisa Alat Panen (Σ stok akhir)</p><h3 id="kpi-alat-sisa" class="text-2xl font-extrabold mt-1 text-orange-700">—</h3><p class="text-xs text-slate-400 mt-1">Σ stok_akhir</p></div>
        </section>

        <!-- =======================================================
             NEW: Ringkasan Pemeliharaan + Chart LM (di atas bagian lain)
             ======================================================= -->
        <section class="mt-6">
          <!-- 5 kartu pemeliharaan -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <?php
              // helper untuk render kartu (kanvas donut + angka)
              function cardPem($id,$title){
                echo '
                <div class="bg-white p-4 rounded-xl shadow">
                  <div class="text-sm font-semibold">'.htmlspecialchars($title).'</div>
                  <div class="mt-2 grid grid-cols-[1fr_80px] gap-2 items-center">
                    <div>
                      <div class="text-xs text-slate-500">Rencana</div>
                      <div class="text-xl font-bold text-red-600"><span id="rcn-'.$id.'">0</span></div>
                      <div class="mt-2 text-xs text-slate-500">Realisasi</div>
                      <div class="text-xl font-bold text-sky-700"><span id="real-'.$id.'">0</span></div>
                    </div>
                    <div class="flex items-center justify-center">
                      <canvas id="donut-'.$id.'" width="80" height="80" aria-label="Progress"></canvas>
                    </div>
                  </div>
                </div>';
              }
              cardPem('TU','Pemeliharaan TU');
              cardPem('TBM','Pemeliharaan TBM');
              cardPem('TM','Pemeliharaan TM');
              cardPem('PN','Pemeliharaan PN');
              cardPem('MN','Pemeliharaan MN');
            ?>
          </div>

          <!-- 3 chart LM -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
            <div class="bg-white p-6 rounded-xl shadow">
              <div class="font-semibold mb-2">Chart LM 76</div>
              <canvas id="ch-lm76" height="180"></canvas>
            </div>
            <div class="bg-white p-6 rounded-xl shadow">
              <div class="font-semibold mb-2">Chart LM 77</div>
              <canvas id="ch-lm77" height="180"></canvas>
            </div>
            <div class="bg-white p-6 rounded-xl shadow">
              <div class="font-semibold mb-2">Chart LM Biaya</div>
              <canvas id="ch-lmbiaya" height="180"></canvas>
            </div>
          </div>
        </section>
        <!-- ======================================================= -->

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
  const fKebun = $('#f-kebun');
  const fAfd   = $('#f-afdeling');
  const fBulan = $('#f-bulan');
  const fTahun = $('#f-tahun');
  const limitStok = $('#limit-stok');
  $('#btn-refresh').addEventListener('click', () => refreshDashboard(false));

  // KPI nodes
  const KPI = {
    total:  $('#kpi-total-item'),
    stok:   $('#kpi-stok-bersih'),
    pakai:  $('#kpi-pemakaian'),
    doc:    $('#kpi-doc'),
    ppkK:   $('#kpi-ppk-kimia'),
    ppkO:   $('#kpi-ppk-organik'),
    angK:   $('#kpi-ang-kimia'),
    angO:   $('#kpi-ang-organik'),
    bahan:  $('#kpi-bahan-pakai'),
    afd:    $('#kpi-afdeling-aktif'),
    alatD:  $('#kpi-alat-dipakai'),
    alatS:  $('#kpi-alat-sisa'),
  };

  // chart refs
  let chStokTop, chPemTren, chKomposisi, chMutasi, chPerAfd, chDimintaFisik;
  let chPkjAkt, chPkjJenis, chPmlJenis, chPmlStatus;
  let chPpkTrenKimia, chPpkTrenOrganik, chPpkKomKimia, chPpkKomOrganik;
  let chAngTrenKimia, chAngTrenOrganik;
  let chAlatDipakai, chAlatSisa;

  // NEW: refs donut + chart LM
  const donut = {TU:null,TBM:null,TM:null,PN:null,MN:null};
  let chLM76, chLM77, chLMBiaya;

  const bulanOrder = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  const palette = n => Array.from({length:n}, (_,i)=>['#16a34a','#f59e0b','#0ea5e9','#a855f7','#ef4444','#10b981','#6366f1','#22c55e','#eab308','#06b6d4','#f97316','#84cc16'][i%12]);
  const fmt = x => Number(x||0).toLocaleString(undefined,{maximumFractionDigits:2});
  const sisaStok = r => (+r.stok_awal||0)+(+r.mutasi_masuk||0)+(+r.pasokan||0)-(+r.mutasi_keluar||0)-(+r.dipakai||0);

  async function postJSON(section, extra){
    const fd = new FormData();
    fd.append('ajax','dashboard');
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('section', section);
    fd.append('kebun',    fKebun.value || '');
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

  // NEW loaders
  const fetchPemKPI   = async()=> (await postJSON('pemeliharaan_kpi'))?.data || [];
  const fetchLM76     = async()=> (await postJSON('lm76_tren'))?.data || [];
  const fetchLM77     = async()=> (await postJSON('lm77_tren'))?.data || [];
  const fetchLMBiaya  = async()=> (await postJSON('lm_biaya_tren'))?.data || [];

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

  /* ====== Tambahan: Plugin teks di tengah donut ====== */
  const CenterTextPlugin = {
    id: 'centerText',
    beforeDraw(chart, args, opts) {
      const txt = chart?.options?.plugins?.centerText?.text;
      if (!txt) return;
      const {ctx} = chart;
      const meta = chart.getDatasetMeta(0);
      if (!meta || !meta.data || !meta.data[0]) return;
      const {x, y} = meta.data[0];
      ctx.save();
      // ukuran font adaptif berdasar diameter hole
      const cutout = chart.getDatasetMeta(0).controller.innerRadius || 28;
      const size = Math.max(12, Math.min(24, Math.floor(cutout * 0.6)));
      ctx.font = `600 ${size}px system-ui, -apple-system, Segoe UI, Roboto, sans-serif`;
      ctx.fillStyle = (chart.options.plugins.centerText.color || '#111827');
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(txt, x, y);
      ctx.restore();
    }
  };
  Chart.register(CenterTextPlugin);
  /* ==================================================== */

  // NEW: donut config (ditambah centerText)
  const donutCfg = (percent)=>({
    type:'doughnut',
    data:{ labels:['Progress','Sisa'], datasets:[{ data:[percent, 100-percent], borderWidth:0, backgroundColor:['#3b82f6','#e5e7eb'] }]},
    options:{
      cutout:'70%',
      responsive:true,
      plugins:{
        legend:{display:false},
        tooltip:{enabled:false},
        centerText:{ text: `${Math.round(percent)}%`, color:'#111827' } // << teks persen di tengah
      }
    }
  });

  function setPemCard(id, rcn, real){
    const p = rcn>0 ? Math.max(0, Math.min(100, (real/rcn)*100)) : 0;
    document.getElementById('rcn-'+id).textContent  = fmt(rcn);
    document.getElementById('real-'+id).textContent = fmt(real);
    const holder = { get value(){ return donut[id]; }, set value(v){ donut[id]=v; } };
    renderOrUpdate('donut-'+id, donutCfg(+p.toFixed(2)), holder);
  }

  async function refreshDashboard(showLoading=true){
    if (showLoading) Object.values(KPI).forEach(el => el.textContent = '…');

    const [
      rowsG, rowsMix, rowsPkj, rowsPml, rowsPpkK, rowsPpkO, rowsAngK, rowsAngO, rowsAlat,
      pemKPI, lm76, lm77, lmbiaya
    ] = await Promise.all([
      fetchGudang(), fetchMixPem(), fetchPkj(), fetchPml(), fetchPpkK(), fetchPpkO(), fetchAngK(), fetchAngO(), fetchAlat(),
      fetchPemKPI(), fetchLM76(), fetchLM77(), fetchLMBiaya()
    ]);

    /* ===== KPI (lebih banyak) ===== */
    const distinctItems = new Set(rowsG.map(r => r.nama_bahan || '-'));
    KPI.total.textContent = fmt(distinctItems.size);
    KPI.stok.textContent  = fmt(rowsG.reduce((a,r)=> a + sisaStok(r), 0));

    KPI.pakai.textContent = fmt(rowsMix.reduce((a,r)=> a + (+r.jlh_fisik||0), 0));
    KPI.doc.textContent   = fmt(rowsMix.length);

    KPI.ppkK.textContent  = fmt(rowsPpkK.reduce((a,r)=> a + (+r.jumlah||0), 0));
    KPI.ppkO.textContent  = fmt(rowsPpkO.reduce((a,r)=> a + (+r.jumlah||0), 0));
    KPI.angK.textContent  = fmt(rowsAngK.reduce((a,r)=> a + (+r.jumlah||0), 0));
    KPI.angO.textContent  = fmt(rowsAngO.reduce((a,r)=> a + (+r.jumlah||0), 0));

    const bahanSet = new Set();
    rowsMix.forEach(r => { if (r.nama_bahan) bahanSet.add(r.nama_bahan); });
    KPI.bahan.textContent = fmt(bahanSet.size);

    const afdSet = new Set();
    rowsMix.forEach(r => { if (r.afdeling) afdSet.add(r.afdeling); });
    rowsPpkK.forEach(r => { if (r.afdeling) afdSet.add(r.afdeling); });
    rowsPpkO.forEach(r => { if (r.afdeling) afdSet.add(r.afdeling); });
    rowsPml.forEach(r => { if (r.afdeling) afdSet.add(r.afdeling); });
    KPI.afd.textContent = fmt(afdSet.size);

    KPI.alatD.textContent = fmt(rowsAlat.reduce((a,r)=> a + (+r.dipakai||0), 0));
    KPI.alatS.textContent = fmt(rowsAlat.reduce((a,r)=> a + (+r.stok_akhir||0), 0));

    /* ======= NEW: Kartu Pemeliharaan ======= */
    const map = {}; // label -> {rencana,realisasi}
    pemKPI.forEach(r => { map[r.label] = {rencana:+r.rencana||0, realisasi:+r.realisasi||0}; });
    setPemCard('TU',  map.TU?.rencana||0,  map.TU?.realisasi||0);
    setPemCard('TBM', map.TBM?.rencana||0, map.TBM?.realisasi||0);
    setPemCard('TM',  map.TM?.rencana||0,  map.TM?.realisasi||0);
    setPemCard('PN',  map.PN?.rencana||0,  map.PN?.realisasi||0);
    setPemCard('MN',  map.MN?.rencana||0,  map.MN?.realisasi||0);

    /* ===== STOK: Top sisa ===== */
    const aggStok = {};
    rowsG.forEach(r => { const k=r.nama_bahan||'-'; aggStok[k]=(aggStok[k]||0)+sisaStok(r); });
    const top = Object.entries(aggStok).sort((a,b)=>b[1]-a[1]).slice(0, parseInt(limitStok.value,10)||10);
    renderOrUpdate('ch-stok-top',
      barCfg(top.map(x=>x[0]), [{label:'Sisa Stok', data: top.map(x=>x[1]), backgroundColor: palette(top.length)}], false, true),
      {get value(){return chStokTop}, set value(v){chStokTop=v}}
    );

    /* ===== Pemakaian (gabungan) ===== */
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

    /* ===== NEW: Chart LM 76, 77, LM Biaya ===== */
    const lm76Map = new Map(bulanOrder.map(b=>[b,0])); lm76.forEach(r=> lm76Map.set(r.bulan, (+lm76Map.get(r.bulan)||0) + (+r.total||0)));
    const lm77Map = new Map(bulanOrder.map(b=>[b,0])); lm77.forEach(r=> lm77Map.set(r.bulan, (+lm77Map.get(r.bulan)||0) + (+r.total||0)));
    const lmbMap  = new Map(bulanOrder.map(b=>[b,0])); lmbiaya.forEach(r=> lmbMap.set(r.bulan, (+lmbMap.get(r.bulan)||0) + (+r.total||0)));

    renderOrUpdate('ch-lm76',   lineCfg(bulanOrder, {label:`Pemakaian Total (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>lm76Map.get(b)||0)}), {get value(){return chLM76}, set value(v){chLM76=v}});
    renderOrUpdate('ch-lm77',   lineCfg(bulanOrder, {label:`Pemakaian Total (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>lm77Map.get(b)||0)}), {get value(){return chLM77}, set value(v){chLM77=v}});
    renderOrUpdate('ch-lmbiaya',lineCfg(bulanOrder, {label:`Pemakaian Total (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>lmbMap.get(b)||0)}),  {get value(){return chLMBiaya}, set value(v){chLMBiaya=v}});

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
  [fKebun, fAfd, fBulan, fTahun, limitStok].forEach(el => el.addEventListener('change', () => refreshDashboard(false)));

  // init
  refreshDashboard(true);
  setInterval(()=>refreshDashboard(false), 30000);
});
</script>

</body>
</html>
