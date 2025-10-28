<?php
/**
 * laporan_mingguan_crud.php (rev: fix stale data & meta sync)
 * - CSRF + role check
 * - Delete-orphan (stale) rows
 * - Normalisasi bulan & blok
 * - [MODIFIED] fetch_report kini mengirimkan 'meta' dan 'details'
 */
session_start();
header('Content-Type: application/json');

/* ====== PERMISSIONS & SECURITY ====== */
if (empty($_SESSION['loggedin'])) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Akses ditolak. Silakan login.']); exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
if ($action === 'save_report' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'message'=>'Metode tidak diizinkan.']); exit;
}
if ($action === 'save_report') {
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Token CSRF tidak valid.']); exit;
  }
  $userRole = $_SESSION['user_role'] ?? 'staf';
  if ($userRole === 'staf') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Anda tidak memiliki izin untuk menyimpan data.']); exit;
  }
}

require_once '../config/database.php';

/* ====== UTIL ====== */
function norm_bulan($b){
  $map = [
    'january'=>'Januari','february'=>'Februari','march'=>'Maret','april'=>'April',
    'may'=>'Mei','june'=>'Juni','july'=>'Juli','august'=>'Agustus','september'=>'September',
    'october'=>'Oktober','november'=>'November','december'=>'Desember',
    'januari'=>'Januari','februari'=>'Februari','maret'=>'Maret','mei'=>'Mei',
    'juni'=>'Juni','juli'=>'Juli','agustus'=>'Agustus','oktober'=>'Oktober','november'=>'November','desember'=>'Desember'
  ];
  $k = strtolower(trim((string)$b));
  return $map[$k] ?? 'Januari';
}

/** Normalisasi kode/nama blok: trim spasi, rapikan spasi tengah, uppercase */
function norm_blok($s){
  $s = preg_replace('/\s+/',' ', (string)$s);
  $s = trim($s);
  return strtoupper($s);
}

/** pastikan master ada */
function ensure_exists(PDO $conn, string $sql, array $params, string $err){
  $st = $conn->prepare($sql); $st->execute($params);
  if (!$st->fetchColumn()) { echo json_encode(['success'=>false,'message'=>$err]); exit; }
}

/* ====== ROUTING ====== */
if ($action === 'save_report') {
  $payload = json_decode($_POST['payload'] ?? '{}', true);
  $minggu  = (int)($_POST['minggu'] ?? 0);
  if (!$payload || empty($payload['meta']) || $minggu < 1 || $minggu > 5) {
    echo json_encode(['success'=>false,'message'=>'Data payload tidak lengkap.']); exit;
  }

  $meta    = $payload['meta'];
  $details = $payload['details'] ?? [];

  foreach (['kebun_id','unit_id','jenis_pekerjaan_id','tahun','bulan'] as $key) {
    if (!isset($meta[$key]) || $meta[$key]==='') {
      echo json_encode(['success'=>false,'message'=>"Meta '$key' kosong."]); exit;
    }
  }
  $meta['bulan'] = norm_bulan($meta['bulan']);
  $meta['kebun_id'] = (string)$meta['kebun_id'];
  $meta['unit_id']  = (string)$meta['unit_id'];
  $meta['jenis_pekerjaan_id'] = (string)$meta['jenis_pekerjaan_id'];
  $meta['tahun'] = (int)$meta['tahun'];

  $db   = new Database();
  $conn = $db->getConnection();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // ====== VALIDASI MASTER (PAKAI TABEL MINGGUAN) ======
  ensure_exists($conn, "SELECT 1 FROM md_kebun WHERE id = :id LIMIT 1", [':id'=>$meta['kebun_id']], 'Kebun tidak ditemukan.');
  ensure_exists($conn, "SELECT 1 FROM units WHERE id = :id LIMIT 1",     [':id'=>$meta['unit_id']],  'Unit/AFD tidak ditemukan.');
  ensure_exists(
    $conn,
    "SELECT 1 FROM md_jenis_pekerjaan_mingguan WHERE id = :id LIMIT 1",
    [':id'=>$meta['jenis_pekerjaan_id']],
    'Jenis Pekerjaan (Mingguan) tidak ditemukan.'
  );

  $conn->beginTransaction();
  try {
    // optional: update nama unit dari header jika diedit
    if (isset($meta['unit_nama']) && trim($meta['unit_nama']) !== '') {
      $conn->prepare("UPDATE units SET nama_unit=:n WHERE id=:id")->execute([
        ':n'=>trim($meta['unit_nama']), ':id'=>$meta['unit_id']
      ]);
    }

    // upsert meta
    $judulMingguKey = "judul_minggu_{$minggu}";
    $sqlMeta = "
      INSERT INTO laporan_mingguan_meta (kebun_id, jenis_pekerjaan_id, tahun, bulan, judul_laporan, {$judulMingguKey}, catatan)
      VALUES (:k, :jp, :t, :b, :jl, :jm, :c)
      ON DUPLICATE KEY UPDATE
        judul_laporan = VALUES(judul_laporan),
        {$judulMingguKey} = VALUES({$judulMingguKey}),
        catatan = VALUES(catatan)";
    $conn->prepare($sqlMeta)->execute([
      ':k' =>$meta['kebun_id'],
      ':jp'=>$meta['jenis_pekerjaan_id'],
      ':t' => $meta['tahun'],
      ':b' =>$meta['bulan'],
      ':jl'=> trim($meta['judul_laporan'] ?? ''),
      ':jm'=> trim($meta[$judulMingguKey] ?? ''),
      ':c' => trim($meta['catatan'] ?? '')
    ]);

    // --- Ambil blok yang sudah ada di DB untuk kombinasi filter ini
    $stExist = $conn->prepare("
      SELECT blok FROM laporan_mingguan
      WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b AND minggu=:m AND afdeling=:afd
    ");
    $stExist->execute([
      ':k'=>$meta['kebun_id'], ':jp'=>$meta['jenis_pekerjaan_id'],
      ':t'=>$meta['tahun'], ':b'=>$meta['bulan'], ':m'=>$minggu,
      ':afd'=>$meta['unit_id']
    ]);
    $existingBlocks = array_map(function($r){ return (string)$r['blok']; }, $stExist->fetchAll(PDO::FETCH_ASSOC));
    $existingSet = array_fill_keys($existingBlocks, true);

    // --- Normalisasi payload + daftar blok baru
    $incomingRows  = [];
    $incomingSet   = [];
    foreach ($details as $r) {
      $blok = norm_blok($r['blok'] ?? '');
      if ($blok === '') {
        // baris tanpa blok → tidak disimpan (dan kalau sebelumnya ada, akan terhapus via delete-orphan)
        continue;
      }
      $ts   = isset($r['ts'])   && $r['ts']   !== '' ? (float)$r['ts']   : 0.0;
      $pkwt = isset($r['pkwt']) && $r['pkwt'] !== '' ? (float)$r['pkwt'] : 0.0;
      $kng  = isset($r['kng'])  && $r['kng']  !== '' ? (float)$r['kng']  : 0.0;
      $tp   = isset($r['tp'])   && $r['tp']   !== '' ? (float)$r['tp']   : 0.0;

      $incomingRows[] = [
        'blok'=>$blok, 'ts'=>$ts, 'pkwt'=>$pkwt, 'kng'=>$kng, 'tp'=>$tp
      ];
      $incomingSet[$blok] = true;
    }

    // --- HAPUS ORPHAN/STale rows: semua blok yang ada di DB tapi tidak ada di payload
    if (!empty($existingSet)) {
      $toDelete = array_diff(array_keys($existingSet), array_keys($incomingSet));
      if (!empty($toDelete)) {
        // delete per batch
        $placeholders = rtrim(str_repeat('?,', count($toDelete)), ',');
        $sqlDel = "
          DELETE FROM laporan_mingguan
          WHERE kebun_id=? AND jenis_pekerjaan_id=? AND tahun=? AND bulan=? AND minggu=? AND afdeling=? AND blok IN ($placeholders)
        ";
        $params = [
          $meta['kebun_id'], $meta['jenis_pekerjaan_id'], $meta['tahun'],
          $meta['bulan'], $minggu, $meta['unit_id']
        ];
        $params = array_merge($params, array_values($toDelete));
        $conn->prepare($sqlDel)->execute($params);
      }
    }

    // --- UPSERT untuk semua row incoming
    if (!empty($incomingRows)) {
      $sqlUpsert = "
        INSERT INTO laporan_mingguan (kebun_id, jenis_pekerjaan_id, tahun, bulan, minggu, afdeling, blok, ts, pkwt, kng, tp)
        VALUES (:k, :jp, :t, :b, :m, :afd, :blok, :ts, :pkwt, :kng, :tp)
        ON DUPLICATE KEY UPDATE
          ts = VALUES(ts), pkwt = VALUES(pkwt), kng = VALUES(kng), tp = VALUES(tp)";
      $st = $conn->prepare($sqlUpsert);
      foreach ($incomingRows as $row) {
        $st->execute([
          ':k'   => $meta['kebun_id'],
          ':jp'  => $meta['jenis_pekerjaan_id'],
          ':t'   => $meta['tahun'],
          ':b'   => $meta['bulan'],
          ':m'   => $minggu,
          ':afd' => $meta['unit_id'],
          ':blok'=> $row['blok'],
          ':ts'  => $row['ts'],
          ':pkwt'=> $row['pkwt'],
          ':kng' => $row['kng'],
          ':tp'  => $row['tp'],
        ]);
      }
    }

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'Laporan berhasil disimpan!']);
  } catch (Exception $e) {
    $conn->rollBack();
    error_log("[LM_CRUD_SAVE] ".$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Database error: Terjadi kesalahan saat menyimpan.']);
  }

} else if ($action === 'fetch_report') {
  try {
    $minggu   = (int)($_GET['minggu'] ?? 0);
    $kebun_id = $_GET['kebun_id'] ?? '';
    $unit_id  = $_GET['unit_id'] ?? '';
    $jp_id    = $_GET['jenis_pekerjaan_id'] ?? '';
    $tahun    = (int)($_GET['tahun'] ?? 0);
    $bulan    = norm_bulan($_GET['bulan'] ?? '');

    if ($minggu < 1 || $minggu > 5 || empty($kebun_id) || empty($unit_id) || empty($jp_id) || empty($tahun)) {
      echo json_encode(['success' => false, 'message' => 'Filter tidak lengkap untuk fetch.']); exit;
    }

    $db = new Database();
    $conn = $db->getConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // VALIDASI MASTER
    ensure_exists($conn, "SELECT 1 FROM md_kebun WHERE id = :id LIMIT 1", [':id'=>$kebun_id], 'Kebun tidak ditemukan.');
    ensure_exists($conn, "SELECT 1 FROM units    WHERE id = :id LIMIT 1",  [':id'=>$unit_id],  'Unit/AFD tidak ditemukan.');
    ensure_exists(
      $conn,
      "SELECT 1 FROM md_jenis_pekerjaan_mingguan WHERE id = :id LIMIT 1",
      [':id'=>$jp_id],
      'Jenis Pekerjaan (Mingguan) tidak ditemukan.'
    );

    // [MODIFIED] Ambil data Meta terbaru
    $meta = [
        'judul_laporan' => 'LAPORAN PEMELIHARAAN KEBUN',
        'catatan'       => 'BATAS AKHIR PENGISIAN SETIAP HARI SABTU JAM 9 PAGI',
        'judul_minggu_1'=> 'MINGGU I','judul_minggu_2'=> 'MINGGU II','judul_minggu_3'=> 'MINGGU III',
        'judul_minggu_4'=> 'MINGGU IV','judul_minggu_5'=> 'MINGGU V'
    ];
    try {
        $stmtMeta = $conn->prepare("
          SELECT judul_laporan, catatan,
                 COALESCE(judul_minggu_1,'MINGGU I') jm1, COALESCE(judul_minggu_2,'MINGGU II') jm2,
                 COALESCE(judul_minggu_3,'MINGGU III') jm3, COALESCE(judul_minggu_4,'MINGGU IV') jm4,
                 COALESCE(judul_minggu_5,'MINGGU V') jm5
          FROM laporan_mingguan_meta
          WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b
          LIMIT 1
        ");
        $stmtMeta->execute([':k'=>$kebun_id, ':jp'=>$jp_id, ':t'=>$tahun, ':b'=>$bulan]);
        if ($m = $stmtMeta->fetch(PDO::FETCH_ASSOC)) {
          $meta['judul_laporan'] = $m['judul_laporan'] ?: $meta['judul_laporan'];
          $meta['catatan']       = $m['catatan']       ?: $meta['catatan'];
          $meta['judul_minggu_1']= $m['jm1']; $meta['judul_minggu_2']= $m['jm2'];
          $meta['judul_minggu_3']= $m['jm3']; $meta['judul_minggu_4']= $m['jm4']; $meta['judul_minggu_5']= $m['jm5'];
        }
    } catch (Throwable $e) { /* biarkan meta default jika query gagal */ }


    // Data detail (urut blok)
    $stmtData = $conn->prepare("
      SELECT blok, ts, pkwt, kng, tp
      FROM laporan_mingguan 
      WHERE kebun_id=:k AND jenis_pekerjaan_id=:jp AND tahun=:t AND bulan=:b AND minggu=:m AND afdeling=:afd
      ORDER BY blok
    ");
    $stmtData->execute([
      ':k' => $kebun_id,
      ':jp'=> $jp_id,
      ':t' => $tahun,
      ':b' => $bulan,
      ':m' => $minggu,
      ':afd'=> $unit_id
    ]);
    $details = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // [MODIFIED] Kirim 'meta' dan 'details'
    echo json_encode(['success' => true, 'data' => ['details' => $details, 'meta' => $meta]]);

  } catch (Exception $e) {
    http_response_code(500);
    error_log("[LM_CRUD_FETCH] ".$e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: Gagal mengambil data laporan.']);
  }

} else {
  echo json_encode(['success'=>false,'message'=>'Aksi tidak valid.']);
}