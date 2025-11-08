<?php
  // admin/dashboard.php (UI + API selaras skema db_ptpn)
  // Rapi + Donut konsisten + KPI angkutan tampil
  // Versi: 2025-10-13 (Perbaikan chart & sumber data angkutan organik + FIX layout chart bawah)
  // UPDATE: Produksi TBS, Tandan/Pokok, PROTAS, BTR, Prestasi sesuai kolom nyata (lm76/lm77)
  //
  // MODIFIKASI 1: Menambahkan 'break-words' pada card KPI untuk mencegah teks/angka besar
  //               keluar dari card (overflow) saat di-zoom atau pada angka yang sangat panjang.
  // MODIFIKASI 2: Menghapus 'max-w-7xl' dan 'mx-auto' dari header dan content wrapper
  //               agar layout menjadi full-width (fluid) dan mengikuti lebar viewport.
  //
  declare(strict_types=1);
  session_start();

  // ===== CSRF =====
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

    $section  = trim($_POST['section']  ?? '');
    $afdeling = trim($_POST['afdeling'] ?? '');
    $bulan    = trim($_POST['bulan']    ?? '');
    $tahun    = trim($_POST['tahun']    ?? '');
    $kebun    = trim($_POST['kebun']    ?? '');

    // Nama bulan dari kolom DATE
    $bulanFromDate = "ELT(MONTH(tanggal),
      'Januari','Februari','Maret','April','Mei','Juni',
      'Juli','Agustus','September','Oktober','November','Desember')";

    try {
      // ==================== GUDANG ====================
      if ($section === 'gudang') {
        $sql = "
          SELECT mk.nama_kebun, b.nama_bahan, s.nama AS satuan, sg.bulan, sg.tahun,
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
            b.nama_bahan ASC";
        $st = $pdo->prepare($sql);
        $st->execute([':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ============== KPI Pemeliharaan (TU/TBM/TM/PN/MN) ==============
      if ($section === 'pemeliharaan_kpi') {
        $map = ['TU'=>'TU','TBM'=>'TBM','TM'=>'TM','PN'=>'BIBIT_PN','MN'=>'BIBIT_MN'];
        $rows = [];
        foreach ($map as $label=>$kat) {
          $sql = "
            SELECT :label AS label,
                   COALESCE(SUM(pml.rencana),0)   AS rencana,
                   COALESCE(SUM(pml.realisasi),0) AS realisasi
            FROM pemeliharaan pml
            LEFT JOIN units u ON u.id = pml.unit_id
            WHERE pml.kategori = :kat
              AND (:afdeling='' OR COALESCE(NULLIF(pml.afdeling,''), u.nama_unit) = :afdeling)
              AND (:bulan='' OR {$bulanFromDate} = :bulan)
              AND (:tahun='' OR YEAR(pml.tanggal) = :tahun)";
          $st = $pdo->prepare($sql);
          $st->execute([':label'=>$label, ':kat'=>$kat, ':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
          $rows[] = $st->fetch(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success'=>true,'data'=>$rows]); exit;
      }

      // ================== LM 76 Tren (TBS — pakai anggaran_kg & realisasi_kg) ==================
      if ($section === 'lm76_tren') {
        $sql = "
          SELECT lm.bulan, lm.tahun,
                 SUM(COALESCE(lm.anggaran_kg,0))  AS rkap,
                 SUM(COALESCE(lm.realisasi_kg,0)) AS real
          FROM lm76 lm
          LEFT JOIN units u ON u.id = lm.unit_id
          WHERE (:afdeling='' OR u.nama_unit = :afdeling)
            AND (:tahun='' OR lm.tahun = :tahun)
            AND (:bulan='' OR lm.bulan = :bulan)
          GROUP BY lm.tahun, lm.bulan
          ORDER BY lm.tahun,
            FIELD(lm.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':tahun'=>$tahun, ':bulan'=>$bulan]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== LM 77 Tren (Tandan/Pokok, PROTAS, BTR, Prestasi) ==================
      if ($section === 'lm77_tren') {
        $sql = "
          SELECT lm.bulan, lm.tahun,
                 AVG(NULLIF(lm.jtandan_per_pohon_bi,0))    AS tandan_pokok_bi,
                 AVG(NULLIF(lm.prod_tonha_bi,0))          AS prod_tonha_bi,
                 AVG(NULLIF(lm.btr_bi,0))                  AS btr_bi,
                 SUM(COALESCE(lm.prestasi_kg_hk_bi,0))     AS prestasi_kg_hk_bi,
                 SUM(COALESCE(lm.prestasi_tandan_hk_bi,0)) AS prestasi_tandan_hk_bi
          FROM lm77 lm
          LEFT JOIN units u ON u.id = lm.unit_id
          WHERE (:afdeling='' OR u.nama_unit = :afdeling)
            AND (:tahun='' OR lm.tahun = :tahun)
            AND (:bulan='' OR lm.bulan = :bulan)
          GROUP BY lm.tahun, lm.bulan
          ORDER BY lm.tahun,
            FIELD(lm.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':tahun'=>$tahun, ':bulan'=>$bulan]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== LM Biaya Tren (tetap) ==================
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
          GROUP BY lb.tahun, lb.bulan
          ORDER BY lb.tahun,
            FIELD(lb.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':kebun'=>$kebun, ':tahun'=>$tahun, ':bulan'=>$bulan]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== PEMAKAIAN (gabungan) ==================
      if ($section === 'pemakaian') {
        $sql = "
          SELECT * FROM (
            SELECT pbk.no_dokumen, u.nama_unit AS afdeling, pbk.bulan, pbk.tahun,
                   pbk.nama_bahan, pbk.jenis_pekerjaan,
                   COALESCE(pbk.jlh_diminta,0) AS jlh_diminta,
                   COALESCE(pbk.jlh_fisik,0)   AS jlh_fisik,
                   NULL AS nama_kebun
            FROM pemakaian_bahan_kimia pbk
            LEFT JOIN units u ON u.id = pbk.unit_id
            WHERE (:afdeling='' OR u.nama_unit = :afdeling)
              AND (:bulan='' OR pbk.bulan = :bulan)
              AND (:tahun='' OR pbk.tahun = :tahun)
            UNION ALL
            SELECT CONCAT('MENABUR-', LPAD(CAST(mp.id AS CHAR),6,'0')) AS no_dokumen,
                   mp.afdeling AS afdeling, {$bulanFromDate} AS bulan, YEAR(mp.tanggal) AS tahun,
                   mp.jenis_pupuk AS nama_bahan, 'Pemupukan' AS jenis_pekerjaan,
                   COALESCE(mp.jumlah,0) AS jlh_diminta, COALESCE(mp.jumlah,0) AS jlh_fisik,
                   mk.nama_kebun
            FROM menabur_pupuk mp
            JOIN md_kebun mk ON mk.kode = mp.kebun_kode
            WHERE (:afdeling='' OR mp.afdeling = :afdeling)
              AND (:bulan='' OR {$bulanFromDate} = :bulan)
              AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)
              AND (:kebun='' OR mk.nama_kebun = :kebun)
            UNION ALL
            SELECT CONCAT('MENABURORG-', LPAD(CAST(mpo.id AS CHAR),6,'0')) AS no_dokumen,
                   uo.nama_unit AS afdeling, {$bulanFromDate} AS bulan, YEAR(mpo.tanggal) AS tahun,
                   mpo.jenis_pupuk AS nama_bahan, 'Pemupukan Organik' AS jenis_pekerjaan,
                   COALESCE(mpo.jumlah,0) AS jlh_diminta, COALESCE(mpo.jumlah,0) AS jlh_fisik,
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
            no_dokumen ASC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== KHUSUS KIMIA ==================
      if ($section === 'pemakaian_kimia') {
        $sql = "
          SELECT pbk.no_dokumen, u.nama_unit AS afdeling, pbk.bulan, pbk.tahun,
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
            pbk.no_dokumen ASC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== PEKERJAAN (LM Biaya) ==================
      if ($section === 'pekerjaan') {
        $sql = "
          SELECT lb.bulan, lb.tahun,
                 u.nama_unit AS afdeling, mk.nama_kebun,
                 lb.alokasi, lb.uraian_pekerjaan,
                 lb.rencana_bi, lb.realisasi_bi
          FROM lm_biaya lb
          LEFT JOIN units u ON u.id = lb.unit_id
          LEFT JOIN md_kebun mk ON mk.id = lb.kebun_id
          WHERE (:afdeling='' OR u.nama_unit = :afdeling)
            AND (:bulan='' OR lb.bulan = :bulan)
            AND (:tahun='' OR lb.tahun = :tahun)
            AND (:kebun='' OR mk.nama_kebun = :kebun)
          ORDER BY lb.tahun DESC,
            FIELD(lb.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                             'Agustus','September','Oktober','November','Desember'),
            lb.id ASC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== PEMELIHARAAN (REAL) ==================
      if ($section === 'pemeliharaan') {
        $sql = "
          SELECT COALESCE(NULLIF(pml.afdeling,''), u.nama_unit) AS afdeling,
                 pml.kategori, pml.jenis_pekerjaan, pml.tanggal,
                 {$bulanFromDate} AS bulan, YEAR(pml.tanggal) AS tahun,
                 pml.rencana, pml.realisasi, pml.status
          FROM pemeliharaan pml
          LEFT JOIN units u ON u.id = pml.unit_id
          WHERE (:afdeling='' OR COALESCE(NULLIF(pml.afdeling,''), u.nama_unit) = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(pml.tanggal) = :tahun)
          ORDER BY pml.tanggal DESC, pml.jenis_pekerjaan ASC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== PEMUPUKAN ==================
      if ($section === 'pemupukan') {
        $sql = "
          SELECT * FROM (
            SELECT 'Anorganik' AS sumber, mp.afdeling AS afdeling, mp.tanggal,
                   {$bulanFromDate} AS bulan, YEAR(mp.tanggal) AS tahun, mp.jenis_pupuk,
                   COALESCE(mp.jumlah,0) AS jumlah, COALESCE(mp.luas,0) AS luas,
                   COALESCE(mp.invt_pokok,0) AS invt_pokok, mk.nama_kebun
            FROM menabur_pupuk mp
            JOIN md_kebun mk ON mk.kode = mp.kebun_kode
            WHERE (:afdeling='' OR mp.afdeling = :afdeling)
              AND (:bulan='' OR {$bulanFromDate} = :bulan)
              AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)
              AND (:kebun='' OR mk.nama_kebun = :kebun)
            UNION ALL
            SELECT 'Organik' AS sumber, uo.nama_unit AS afdeling, mpo.tanggal,
                   {$bulanFromDate} AS bulan, YEAR(mpo.tanggal) AS tahun, mpo.jenis_pupuk,
                   COALESCE(mpo.jumlah,0) AS jumlah, COALESCE(mpo.luas,0) AS luas,
                   COALESCE(mpo.invt_pokok,0) AS invt_pokok, mk2.nama_kebun
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
            tanggal DESC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      if ($section === 'pemupukan_kimia') {
        $sql = "
          SELECT mp.afdeling AS afdeling, mp.tanggal, {$bulanFromDate} AS bulan,
                 YEAR(mp.tanggal) AS tahun, mp.jenis_pupuk,
                 COALESCE(mp.jumlah,0) AS jumlah, COALESCE(mp.luas,0) AS luas,
                 COALESCE(mp.invt_pokok,0) AS invt_pokok, mk.nama_kebun
          FROM menabur_pupuk mp
          JOIN md_kebun mk ON mk.kode = mp.kebun_kode
          WHERE (:afdeling='' OR mp.afdeling = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mp.tanggal) = :tahun)
            AND (:kebun='' OR mk.nama_kebun = :kebun)
          ORDER BY mp.tanggal DESC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      if ($section === 'pemupukan_organik') {
        $sql = "
          SELECT uo.nama_unit AS afdeling, mpo.tanggal, {$bulanFromDate} AS bulan,
                 YEAR(mpo.tanggal) AS tahun, mpo.jenis_pupuk,
                 COALESCE(mpo.jumlah,0) AS jumlah, COALESCE(mpo.luas,0) AS luas,
                 COALESCE(mpo.invt_pokok,0) AS invt_pokok, mk.nama_kebun
          FROM menabur_pupuk_organik mpo
          LEFT JOIN units uo ON uo.id = mpo.unit_id
          LEFT JOIN md_kebun mk ON mk.id = mpo.kebun_id
          WHERE (:afdeling='' OR uo.nama_unit = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(mpo.tanggal) = :tahun)
            AND (:kebun='' OR mk.nama_kebun = :kebun)
          ORDER BY mpo.tanggal DESC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== ANGKUTAN ==================
      if ($section === 'angkutan_kimia') {
        $sql = "
          SELECT ap.tanggal, {$bulanFromDate} AS bulan, YEAR(ap.tanggal) AS tahun,
                 u.nama_unit AS unit_tujuan, mk.nama_kebun, ap.gudang_asal, ap.jenis_pupuk,
                 COALESCE(ap.jumlah,0) AS jumlah
          FROM angkutan_pupuk ap
          LEFT JOIN units u ON u.id = ap.unit_tujuan_id
          LEFT JOIN md_kebun mk ON mk.kode = ap.kebun_kode
          WHERE ap.jenis_pupuk <> 'Organik'
            AND (:afdeling='' OR u.nama_unit = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(ap.tanggal) = :tahun)
            AND (:kebun='' OR mk.nama_kebun = :kebun)
          ORDER BY ap.tanggal DESC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      if ($section === 'angkutan_organik') {
        $sql = "
          SELECT apo.tanggal, {$bulanFromDate} AS bulan, YEAR(apo.tanggal) AS tahun,
                 u.nama_unit AS unit_tujuan, mk.nama_kebun, apo.gudang_asal, 'Organik' as jenis_pupuk,
                 COALESCE(apo.jumlah,0) AS jumlah
          FROM angkutan_pupuk_organik apo
          LEFT JOIN units u ON u.id = apo.unit_tujuan_id
          LEFT JOIN md_kebun mk ON mk.id = apo.kebun_id
          WHERE (:afdeling='' OR u.nama_unit = :afdeling)
            AND (:bulan='' OR {$bulanFromDate} = :bulan)
            AND (:tahun='' OR YEAR(apo.tanggal) = :tahun)
            AND (:kebun='' OR mk.nama_kebun = :kebun)
          ORDER BY apo.tanggal DESC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      // ================== ALAT PANEN ==================
      if ($section === 'alat_panen') {
        $sql = "
          SELECT mk.nama_kebun, u.nama_unit AS afdeling, ap.bulan, ap.tahun, ap.jenis_alat,
                 COALESCE(ap.stok_awal,0) AS stok_awal,
                 COALESCE(ap.mutasi_masuk,0) AS mutasi_masuk,
                 COALESCE(ap.mutasi_keluar,0) AS mutasi_keluar,
                 COALESCE(ap.dipakai,0) AS dipakai,
                 COALESCE(ap.stok_akhir,0) AS stok_akhir,
                 ap.krani_afdeling
          FROM alat_panen ap
          LEFT JOIN units u ON u.id = ap.unit_id
          LEFT JOIN md_kebun mk ON mk.id = ap.kebun_id
          WHERE (:afdeling='' OR u.nama_unit = :afdeling)
            AND (:bulan='' OR ap.bulan = :bulan)
            AND (:tahun='' OR ap.tahun = :tahun)
            AND (:kebun='' OR mk.nama_kebun = :kebun)
          ORDER BY ap.tahun DESC,
            FIELD(ap.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli',
                             'Agustus','September','Oktober','November','Desember'),
            ap.jenis_alat ASC";
        $st = $pdo->prepare($sql);
        $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun, ':kebun'=>$kebun]);
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
      }

      echo json_encode(['success'=>false,'message'=>'Section tidak dikenal']);
    } catch (Throwable $e) {
      echo json_encode(['success'=>false,'message'=>'DB error','error'=>$e->getMessage() ]);
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
  try {
    $opsiAfdeling = $pdo->query("SELECT nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_COLUMN);
    $opsiKebun    = $pdo->query("SELECT nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_COLUMN);
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
    <style>
      /* Pastikan kanvas donut tidak kepotong */
      .donut-wrap{ width:96px;height:96px; display:flex; align-items:center; justify-content:center; }
      .donut-wrap canvas{ width:96px !important; height:96px !important; display:block; }

      /* ====== FIX CHART MEMANJANG KE BAWAH ====== */
      .chart-card{
        min-height: 240px;
        height: 240px;
        display: flex;
        flex-direction: column;
      }
      .chart-card > .card-title{ margin-bottom: .5rem; font-weight: 600; }
      .chart-card canvas{
        flex-grow: 1;
        height: 200px !important;
        width: 100% !important;
        display: block;
      }
    </style>
  </head>
  <body class="bg-[#f6f9fc] text-slate-800">
    <div class="min-h-screen flex">
      <main class="flex-1">
        <header class="bg-white border-b sticky top-0 z-10">
          
          <div class="px-4 py-3 flex items-center justify-between">
            <div>
              <div class="text-xs uppercase tracking-wide text-slate-500">Dashboard Utama</div>
              <h1 class="text-xl font-bold"><?= htmlspecialchars($hariIni) ?></h1>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
              <select id="f-kebun" class="border rounded-lg px-3 py-2 text-sm min-w-[180px]">
                <option value="">Semua Kebun</option>
                <?php foreach ($opsiKebun as $kb): ?>
                  <option value="<?= htmlspecialchars($kb) ?>"><?= htmlspecialchars($kb) ?></option>
                <?php endforeach; ?>
              </select>
              <select id="f-afdeling" class="border rounded-lg px-3 py-2 text-sm min-w-[170px]">
                <option value="">Semua Afdeling</option>
                <?php foreach ($opsiAfdeling as $afd): ?>
                  <option value="<?= htmlspecialchars($afd) ?>"><?= htmlspecialchars($afd) ?></option>
                <?php endforeach; ?>
              </select>
              <select id="f-bulan" class="border rounded-lg px-3 py-2 text-sm min-w-[140px]">
                <option value="">Semua Bulan</option>
                <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
              </select>
              <select id="f-tahun" class="border rounded-lg px-3 py-2 text-sm min-w-[110px]">
                <?php for ($y=$tahunNow-2; $y<=$tahunNow+2; $y++): ?>
                  <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
              <button id="btn-refresh" class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm">Refresh</button>
            </div>
          </div>
        </header>

        <div class="px-4 py-6 space-y-6">

          <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <?php
              function pemCard($id,$title){
                echo '
                <div class="bg-white rounded-xl shadow p-4 border">
                  <div class="text-[12px] font-semibold">'.htmlspecialchars($title).'</div>
                  <div class="mt-2 grid grid-cols-[1fr_auto] gap-3 items-center">
                    <div class="min-w-0"> <div class="text-[11px] text-slate-500">Rencana</div>
                      <div class="text-[20px] font-bold text-rose-600 leading-tight break-words"><span id="rcn-'.$id.'">0</span></div>
                      <div class="mt-1 text-[11px] text-slate-500">Realisasi</div>
                      <div class="text-[20px] font-bold text-sky-700 leading-tight break-words"><span id="real-'.$id.'">0</span></div>
                    </div>
                    <div class="donut-wrap"><canvas id="donut-'.$id.'" aria-label="Progress"></canvas></div>
                  </div>
                </div>';
              }
              pemCard('TU','Pemeliharaan TU');
              pemCard('TBM','Pemeliharaan TBM');
              pemCard('TM','Pemeliharaan TM');
              pemCard('PN','Pemeliharaan PN');
              pemCard('MN','Pemeliharaan MN');
            ?>
          </section>

          <section id="biaya-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"></section>

          <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow px-6 py-4 border">
              <div class="text-sm text-slate-500">Pemupukan Kimia (Σ jumlah)</div>
              <div class="text-2xl font-extrabold text-indigo-600 mt-1"><span id="kpi-ppk-kimia">0</span></div>
              <div class="text-[11px] mt-1 text-slate-400">menabur_pupuk</div>
            </div>
            <div class="bg-white rounded-xl shadow px-6 py-4 border">
              <div class="text-sm text-slate-500">Pemupukan Organik (Σ jumlah)</div>
              <div class="text-2xl font-extrabold text-teal-600 mt-1"><span id="kpi-ppk-organik">0</span></div>
              <div class="text-[11px] mt-1 text-slate-400">menabur_pupuk_organik</div>
            </div>
            <div class="bg-white rounded-xl shadow px-6 py-4 border">
              <div class="text-sm text-slate-500">Angkutan Kimia (Σ jumlah)</div>
              <div class="text-2xl font-extrabold text-fuchsia-600 mt-1"><span id="kpi-ang-kimia">0</span></div>
              <div class="text-[11px] mt-1 text-slate-400">angkutan_pupuk ≠ Organik</div>
            </div>
            <div class="bg-white rounded-xl shadow px-6 py-4 border">
              <div class="text-sm text-slate-500">Angkutan Organik (Σ jumlah)</div>
              <div class="text-2xl font-extrabold text-rose-600 mt-1"><span id="kpi-ang-organik">0</span></div>
              <div class="text-[11px] mt-1 text-slate-400">angkutan_pupuk_organik</div>
            </div>
          </section>

          <section class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
              <div class="bg-white rounded-xl shadow p-4 border">
                <div class="text-sm font-semibold">Produksi TBS (Kg)</div>
                <div class="mt-2 grid grid-cols-[1fr_auto] gap-3 items-center">
                  <div class="min-w-0"> <div class="text-[11px] text-slate-500">RKAP</div>
                    <div class="text-[20px] font-bold text-rose-600 leading-tight break-words">
                      <span id="prd-tbs-rkap">0</span>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Realisasi</div>
                    <div class="text-[20px] font-bold text-sky-700 leading-tight break-words">
                      <span id="prd-tbs-real">0</span>
                    </div>
                  </div>
                  <div class="donut-wrap"><canvas id="donut-tbs" aria-label="TBS"></canvas></div>
                </div>
              </div>
              <?php
                function smallCard($id,$title,$subTop='',$subBottom=''){
                  echo '
                  <div class="bg-white rounded-xl shadow p-4 border">
                    <div class="text-sm font-semibold">'.htmlspecialchars($title).'</div>
                    <div class="mt-2 grid grid-cols-[1fr_auto] gap-3 items-center">
                      <div class="min-w-0"> '.($subTop!==''?'<div class="text-[11px] text-slate-500">'.htmlspecialchars($subTop).'</div>':'<div class="text-[11px] text-slate-500">Nilai</div>').'
                        <div class="text-[20px] font-bold text-rose-600 leading-tight break-words"><span id="'.htmlspecialchars($id).'">0</span></div>
                        '.($subBottom!==''?'<div class="mt-1 text-[11px] text-slate-500">'.htmlspecialchars($subBottom).'</div>':'').'
                      </div>
                      <div class="donut-wrap"><canvas id="donut-'.htmlspecialchars($id).'" aria-label="'.htmlspecialchars($title).'"></canvas></div>
                    </div>
                  </div>';
                }
                smallCard('prd-tandan','Tandan','Tandan/Pokok');
                smallCard('prd-hk','Jumlah Hk Panen','Kilogram','Prestasi');
                smallCard('prd-protas','Protas (Ton/Ha)');
                smallCard('prd-btr','BTR');
              ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
              <div class="bg-white rounded-xl shadow p-4 border chart-card">
                <div class="card-title">Produksi (kg)</div>
                <canvas id="ch-prod"></canvas>
              </div>
              <div class="bg-white rounded-xl shadow p-4 border chart-card">
                <div class="card-title">TDN/PKK</div>
                <canvas id="ch-tdn"></canvas>
              </div>
              <div class="bg-white rounded-xl shadow p-4 border chart-card">
                <div class="card-title">PROTAS (TON/HA)</div>
                <canvas id="ch-protas"></canvas>
              </div>
            </div>
          </section>

          <section>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div class="bg-white rounded-xl shadow p-5 border">
                <div class="text-slate-500 text-sm">Total Item Gudang</div>
                <div class="text-3xl font-extrabold mt-1"><span id="kpi-total-item">0</span></div>
                <div class="text-[11px] text-slate-400 mt-1">Distinct nama bahan (periode dipilih)</div>
              </div>
              <div class="bg-white rounded-xl shadow p-5 border">
                <div class="text-slate-500 text-sm">Stok Bersih</div>
                <div class="text-3xl font-extrabold mt-1 text-emerald-600"><span id="kpi-stok-bersih">0</span></div>
                <div class="text-[11px] text-slate-400 mt-1">Awal + Masuk + Pasokan − Keluar − Dipakai</div>
              </div>
              <div class="bg-white rounded-xl shadow p-5 border">
                <div class="text-slate-500 text-sm">Total Pemakaian (fisik)</div>
                <div class="text-3xl font-extrabold mt-1 text-sky-600"><span id="kpi-pemakaian">0</span></div>
                <div class="text-[11px] text-slate-400 mt-1">Σ jlh_fisik (kimia + pemupukan)</div>
              </div>
              <div class="bg-white rounded-xl shadow p-5 border">
                <div class="text-slate-500 text-sm">Dokumen Pemakaian</div>
                <div class="text-3xl font-extrabold mt-1 text-amber-600"><span id="kpi-doc">0</span></div>
                <div class="text-[11px] text-slate-400 mt-1">Count no_dokumen</div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
              <div class="bg-white rounded-xl shadow p-4 border chart-card">
                <div class="flex items-center justify-between mb-2">
                  <div class="card-title">Top Sisa Stok Gudang</div>
                  <select id="limit-stok" class="border rounded px-2 py-1 text-xs">
                    <option value="5">Top 5</option>
                    <option value="10" selected>Top 10</option>
                    <option value="20">Top 20</option>
                  </select>
                </div>
                <canvas id="ch-stok-top"></canvas>
              </div>
              <div class="bg-white rounded-xl shadow p-4 border chart-card">
                <div class="card-title">Tren Pemakaian (per Bulan)</div>
                <canvas id="ch-pemakaian-tren"></canvas>
              </div>
              <div class="bg-white rounded-xl shadow p-4 border chart-card">
                <div class="card-title">Pemeliharaan: Realisasi per Jenis</div>
                <canvas id="ch-pemeliharaan-jenis"></canvas>
              </div>
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
    };

    // chart refs
    let chStokTop, chPemTren, chPmlJenis;
    let chProd, chTDN, chProtas;

    // Donut refs
    const donut = {TU:null,TBM:null,TM:null,PN:null,MN:null, TBS:null};

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
      } catch (e) { clearTimeout(t); return {success:false,error:String(e)}; }
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
    const fetchPemKPI   = async()=> (await postJSON('pemeliharaan_kpi'))?.data || [];
    const fetchLM76     = async()=> (await postJSON('lm76_tren'))?.data || [];
    const fetchLM77     = async()=> (await postJSON('lm77_tren'))?.data || [];

    // ===== CHART RENDER HELPER (FIX utama) =====
    function renderOrUpdate(ctxId, cfg, holder){
      const canvas = document.getElementById(ctxId);
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      if (!ctx) return;

      if (holder.value instanceof Chart) holder.value.destroy();

      cfg.options = Object.assign({
        responsive: true,
        maintainAspectRatio: false,
        resizeDelay: 100,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true } }
      }, cfg.options || {});

      holder.value = new Chart(ctx, cfg);
    }

    const barCfg  = (labels, datasets, stacked=false, horizontal=false)=>({
      type:'bar',
      data:{labels,datasets},
      options:{
        indexAxis:horizontal?'y':'x',
        plugins:{legend:{display:true}},
        scales: stacked?{x:{stacked:true},y:{stacked:true}}:{}
      }
    });

    const lineCfg = (labels, dataset)=>({
      type:'line',
      data:{labels, datasets:[dataset]},
      options:{
        plugins:{legend:{display:true}},
        tension:.35,
        elements:{point:{radius:3}},
        scales:{y:{beginAtZero:true}}
      }
    });

    // Plugin donut – text tengah
    const CenterTextPlugin = {
      id: 'centerText',
      beforeDraw(chart) {
        const txt = chart?.options?.plugins?.centerText?.text;
        if (!txt) return;
        const {ctx, chartArea} = chart;
        const radius = chart.getDatasetMeta(0)?.controller?.innerRadius || Math.min(chartArea.width, chartArea.height)/3;
        ctx.save();
        const size = Math.max(12, Math.min(26, Math.floor(radius*0.6)));
        ctx.font = `600 ${size}px system-ui, -apple-system, Segoe UI, Roboto, sans-serif`;
        ctx.fillStyle = (chart.options.plugins.centerText.color || '#111827');
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        const cx = (chartArea.left + chartArea.right)/2;
        const cy = (chartArea.top + chartArea.bottom)/2;
        ctx.fillText(txt, cx, cy); ctx.restore();
      }
    };
    Chart.register(CenterTextPlugin);

    const donutCfg = (percent)=>({
      type:'doughnut',
      data:{ labels:['Progress','Sisa'], datasets:[{ data:[percent, Math.max(0,100-percent)], borderWidth:0, backgroundColor:['#3b82f6','#e5e7eb'] }]},
      options:{ cutout:'70%', plugins:{ legend:{display:false}, tooltip:{enabled:false}, centerText:{ text: `${Math.round(percent)}%`, color:'#111827' } } }
    });

    function setPemCard(id, rcn, real){
      const p = rcn>0 ? Math.max(0, Math.min(100, (real/rcn)*100)) : 0;
      document.getElementById('rcn-'+id).textContent  = fmt(rcn);
      document.getElementById('real-'+id).textContent = fmt(real);
      const holder = { get value(){ return donut[id]; }, set value(v){ donut[id]=v; } };
      renderOrUpdate('donut-'+id, donutCfg(+p.toFixed(2)), holder);
    }

    // Kartu biaya/HPP builder (mini donut)
    function renderBiayaCards(list) {
      const wrap = document.getElementById('biaya-grid');
      wrap.innerHTML = '';
      list.forEach((it,idx) => {
        const id = `mini-${it.key}-${idx}`;
        const html = `
          <div class="bg-white rounded-xl shadow p-4 border">
            <div class="text-[12px] font-semibold">${it.title}</div>
            <div class="mt-2 grid grid-cols-[1fr_auto] gap-3 items-center">
              <div class="min-w-0"> <div class="text-[11px] text-slate-500">Rencana</div>
                <div class="text-[20px] font-bold text-rose-600 leading-tight break-words">${fmt(it.anggaran)}</div>
                <div class="mt-1 text-[11px] text-slate-500">Realisasi</div>
                <div class="text-[20px] font-bold text-sky-700 leading-tight break-words">${fmt(it.realisasi)}</div>
              </div>
              <div class="donut-wrap"><canvas id="${id}" aria-label="${it.title}"></canvas></div>
            </div>
          </div>`;
        wrap.insertAdjacentHTML('beforeend', html);
        const percent = it.anggaran > 0 ? Math.min(100, (it.realisasi / it.anggaran) * 100) : 0;
        const dummyHolder = { value: null };
        renderOrUpdate(id, donutCfg(percent), dummyHolder);
      });
    }

    async function refreshDashboard(showLoading=true){
      if (showLoading) Object.values(KPI).forEach(el => el && (el.textContent = '…'));

      const [
        rowsG, rowsMix, rowsPkj, rowsPml, rowsPpkK, rowsPpkO, rowsAngK, rowsAngO,
        pemKPI, lm76, lm77
      ] = await Promise.all([
        fetchGudang(), fetchMixPem(), fetchPkj(), fetchPml(), fetchPpkK(), fetchPpkO(), fetchAngK(), fetchAngO(),
        fetchPemKPI(), fetchLM76(), fetchLM77()
      ]);

      /* === KPI Utama Gudang === */
      const distinctItems = new Set(rowsG.map(r => r.nama_bahan || '-'));
      if (KPI.total) KPI.total.textContent = fmt(distinctItems.size);
      if (KPI.stok)  KPI.stok.textContent  = fmt(rowsG.reduce((a,r)=> a + sisaStok(r), 0));
      if (KPI.pakai) KPI.pakai.textContent = fmt(rowsMix.reduce((a,r)=> a + (+r.jlh_fisik||0), 0));
      if (KPI.doc)   KPI.doc.textContent   = fmt(rowsMix.length);

      // === Angka pemupukan & angkutan ===
      document.getElementById('kpi-ppk-kimia').textContent   = fmt(rowsPpkK.reduce((a,r)=> a + (+r.jumlah||0), 0));
      document.getElementById('kpi-ppk-organik').textContent = fmt(rowsPpkO.reduce((a,r)=> a + (+r.jumlah||0), 0));
      document.getElementById('kpi-ang-kimia').textContent   = fmt(rowsAngK.reduce((a,r)=> a + (+r.jumlah||0), 0));
      document.getElementById('kpi-ang-organik').textContent = fmt(rowsAngO.reduce((a,r)=> a + (+r.jumlah||0), 0));

      /* === Donut Pemeliharaan (5) === */
      const map = {}; (pemKPI||[]).forEach(r => { map[r.label] = {rencana:+r.rencana||0, realisasi:+r.realisasi||0}; });
      setPemCard('TU',  map.TU?.rencana||0,  map.TU?.realisasi||0);
      setPemCard('TBM', map.TBM?.rencana||0, map.TBM?.realisasi||0);
      setPemCard('TM',  map.TM?.rencana||0,  map.TM?.realisasi||0);
      setPemCard('PN',  map.PN?.rencana||0,  map.PN?.realisasi||0);
      setPemCard('MN',  map.MN?.rencana||0,  map.MN?.realisasi||0);

      /* === Kartu Biaya/HPP — grouping by text === */
      const tot = rowsPkj.reduce((o,r)=>{ o.R+=(+r.rencana_bi||0); o.X+=(+r.realisasi_bi||0); return o; }, {R:0,X:0});
      const match = (r,kw)=> ((r.alokasi||'').toLowerCase().includes(kw) || (r.uraian_pekerjaan||'').toLowerCase().includes(kw));
      const incPem = rowsPkj.filter(r=> match(r,'pupuk')).reduce((o,r)=>{ o.R+=+r.rencana_bi||0; o.X+=+r.realisasi_bi||0; return o; }, {R:0,X:0});
      const exclPem = {R: tot.R - incPem.R, X: tot.X - incPem.X};
      const gajiKarpim = rowsPkj.filter(r=> match(r,'gaji') || match(r,'karpim')).reduce((o,r)=>{ o.R+=+r.rencana_bi||0; o.X+=+r.realisasi_bi||0; return o; }, {R:0,X:0});
      const biayaPemel = rowsPkj.filter(r=> match(r,'pemel')).reduce((o,r)=>{ o.R+=+r.rencana_bi||0; o.X+=+r.realisasi_bi||0; return o; }, {R:0,X:0});
      const panenPengumpul = rowsPkj.filter(r=> match(r,'panen') || match(r,'pengumpul')).reduce((o,r)=>{ o.R+=+r.rencana_bi||0; o.X+=+r.realisasi_bi||0; return o; }, {R:0,X:0});
      const biayaUmum = rowsPkj.filter(r=> match(r,'umum') || match(r,'alokasi')).reduce((o,r)=>{ o.R+=+r.rencana_bi||0; o.X+=+r.realisasi_bi||0; return o; }, {R:0,X:0});

      renderBiayaCards([
        {key:'inc',    title:'Biaya incl. Pemupukan',    anggaran:incPem.R,        realisasi:incPem.X},
        {key:'excl',   title:'Biaya excl. Pemupukan',    anggaran:exclPem.R,       realisasi:exclPem.X},
        {key:'hppinc', title:'HPP Incl Pemupukan',       anggaran:incPem.R,        realisasi:incPem.X},
        {key:'hppexc', title:'HPP Excl Pemupukan',       anggaran:exclPem.R,       realisasi:exclPem.X},
        {key:'gaji',   title:'Gaji & Bisos Karpim',      anggaran:gajiKarpim.R,    realisasi:gajiKarpim.X},
        {key:'tm',     title:'Biaya Pemeliharaan',       anggaran:biayaPemel.R,    realisasi:biayaPemel.X},
        {key:'panen',  title:'Biaya Panen/Pengumpul',    anggaran:panenPengumpul.R, realisasi:panenPengumpul.X},
        {key:'umum',   title:'Alokasi Biaya Umum',       anggaran:biayaUmum.R,     realisasi:biayaUmum.X},
      ]);

      /* === STOK: Top sisa === */
      const aggStok = {};
      rowsG.forEach(r => { const k=r.nama_bahan||'-'; aggStok[k]=(aggStok[k]||0)+sisaStok(r); });
      const top = Object.entries(aggStok).sort((a,b)=>b[1]-a[1]).slice(0, parseInt((limitStok?.value)||10,10));
      renderOrUpdate('ch-stok-top',
        barCfg(top.map(x=>x[0]), [{label:'Sisa Stok', data: top.map(x=>x[1]), backgroundColor: palette(top.length)}], false, true),
        {get value(){return chStokTop}, set value(v){chStokTop=v}}
      );

      /* === PRODUKSI charts & KPI berbasis field sebenarnya === */
      // Map bulan → nilai dari lm76 (rkap/real)
      const lm76Rkap = new Map(bulanOrder.map(b=>[b,0]));
      const lm76Real = new Map(bulanOrder.map(b=>[b,0]));
      (lm76||[]).forEach(r=>{
        lm76Rkap.set(r.bulan, (+lm76Rkap.get(r.bulan)||0) + (+r.rkap||0));
        lm76Real.set(r.bulan, (+lm76Real.get(r.bulan)||0) + (+r.real||0));
      });

      // lm77: avg tandan/pokok, protas, btr; sum prestasi
      const lm77Tandan = new Map(bulanOrder.map(b=>[b,0]));
      const lm77Protas = new Map(bulanOrder.map(b=>[b,0]));
      const lm77Btr    = new Map(bulanOrder.map(b=>[b,0]));
      const lm77PrestHk= new Map(bulanOrder.map(b=>[b,0]));
      (lm77||[]).forEach(r=>{
        lm77Tandan.set(r.bulan, (+r.tandan_pokok_bi||0));
        lm77Protas.set(r.bulan, (+r.prod_tonha_bi||0));
        lm77Btr.set(r.bulan,    (+r.btr_bi||0));
        lm77PrestHk.set(r.bulan,(+r.prestasi_kg_hk_bi||0));
      });

      // Render 3 chart bawah
      renderOrUpdate(
        'ch-prod',
        lineCfg(
          bulanOrder,
          { label:'Realisasi TBS (Kg)', data: bulanOrder.map(b=> lm76Real.get(b)||0),
            borderColor:'#16a34a', backgroundColor:'#16a34a20', fill:true }
        ),
        { get value(){return chProd}, set value(v){chProd=v} }
      );

      renderOrUpdate(
        'ch-tdn',
        lineCfg(
          bulanOrder,
          { label:'Tandan/Pokok (BI)', data: bulanOrder.map(b=> lm77Tandan.get(b)||0),
            borderColor:'#f97316', backgroundColor:'#f9731620', fill:true }
        ),
        { get value(){return chTDN}, set value(v){chTDN=v} }
      );

      renderOrUpdate(
        'ch-protas',
        lineCfg(
          bulanOrder,
          { label:'PROTAS (Ton/Ha, BI)', data: bulanOrder.map(b=> lm77Protas.get(b)||0),
            borderColor:'#6366f1', backgroundColor:'#6366f120', fill:true }
        ),
        { get value(){return chProtas}, set value(v){chProtas=v} }
      );

      // ==== PRODUKSI TBS (RKAP vs REAL) + DONUT ====
      const selBulan = fBulan.value || null;
      const totalRkap = [...lm76Rkap.values()].reduce((a,b)=>a+(+b||0),0);
      const totalReal = [...lm76Real.values()].reduce((a,b)=>a+(+b||0),0);
      const rkap  = selBulan ? (+lm76Rkap.get(selBulan)||0) : totalRkap;
      const real  = selBulan ? (+lm76Real.get(selBulan)||0) : totalReal;
      $('#prd-tbs-rkap').textContent  = fmt(rkap);
      $('#prd-tbs-real').textContent  = fmt(real);
      renderOrUpdate('donut-tbs', (()=>{
        const pct = rkap>0 ? Math.min(100, (real/rkap)*100) : 0;
        return donutCfg(pct);
      })(), {get value(){return donut.TBS}, set value(v){donut.TBS=v}});

      // ==== Angka ringkas akurat (ikut filter) ====
      if (fBulan.value) {
        $('#prd-tandan').textContent = fmt(lm77Tandan.get(fBulan.value) || 0);
        $('#prd-protas').textContent = fmt(lm77Protas.get(fBulan.value) || 0);
        $('#prd-btr').textContent    = fmt(lm77Btr.get(fBulan.value)    || 0);
        $('#prd-hk').textContent     = fmt(lm77PrestHk.get(fBulan.value)|| 0);
      } else {
        const avg = map => {
          const arr = [...map.values()].filter(v=>Number.isFinite(+v) && +v>0);
          return arr.length ? (arr.reduce((a,b)=>a+ +b,0) / arr.length) : 0;
        };
        $('#prd-tandan').textContent = fmt(avg(lm77Tandan));
        $('#prd-protas').textContent = fmt(avg(lm77Protas));
        $('#prd-btr').textContent    = fmt(avg(lm77Btr));
        $('#prd-hk').textContent     = fmt([...lm77PrestHk.values()].reduce((a,b)=>a+(+b||0),0));
      }

      /* === Pemakaian (gabungan) chart tren === */
      const mapTren = new Map(bulanOrder.map(b=>[b,0]));
      rowsMix.forEach(r=> mapTren.set(r.bulan, (mapTren.get(r.bulan)||0) + (+r.jlh_fisik||0)));
      renderOrUpdate('ch-pemakaian-tren',
        lineCfg(bulanOrder, {label:`Pemakaian Total (${fTahun.value||'Tahun ini'})`, data: bulanOrder.map(b=>mapTren.get(b)||0), borderColor:'#0ea5e9', backgroundColor:'#0ea5e920', fill:true}),
        {get value(){return chPemTren}, set value(v){chPemTren=v}}
      );

      /* === Pemeliharaan: Realisasi per Jenis === */
      const pmlJenis = {};
      rowsPml.forEach(r=>{ const k=r.jenis_pekerjaan || '-'; pmlJenis[k] = (pmlJenis[k]||0) + (+r.realisasi||0); });
      const pmlJenisArr = Object.entries(pmlJenis).sort((a,b)=>b[1]-a[1]).slice(0,12);
      renderOrUpdate('ch-pemeliharaan-jenis',
        barCfg(pmlJenisArr.map(x=>x[0]), [{label:'Realisasi', data:pmlJenisArr.map(x=>x[1]), backgroundColor: palette(pmlJenisArr.length)}], false, true),
        {get value(){return chPmlJenis}, set value(v){chPmlJenis=v}}
      );
    }

    [fKebun, fAfd, fBulan, fTahun].forEach(el => el.addEventListener('change', () => refreshDashboard(false)));
    if (limitStok) limitStok.addEventListener('change', ()=>refreshDashboard(false));

    // Render awal & auto refresh
    refreshDashboard(true);
    setInterval(()=>refreshDashboard(false), 30000);
  });
  </script>
  </body>
  </html>