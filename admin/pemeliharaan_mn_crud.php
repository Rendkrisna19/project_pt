<?php
// pages/pemeliharaan_mn_crud.php — LIST + CRUD mn (per STOOD master, JSON-safe)
// Versi: 2025-11-10 (robust JSON, error handler, no stray output)
// Modifikasi: 2025-11-10 (Filter stood_id dipindah ke HAVING)

declare(strict_types=1);

ob_start(); // tahan output liar
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
// Optional, biar frontend gak kena cache pas debug
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_start();

/* ===== Helper JSON-safe output & error handlers ===== */
function jout(int $code, bool $ok, string $msg, array $extra = []): void {
  http_response_code($code);
  // bersihkan output lain (HTML/warning) supaya JSON murni
  if (ob_get_length()) { ob_clean(); }
  echo json_encode(array_merge(['success'=>$ok,'message'=>$msg], $extra), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

// Ubah warning/notice jadi exception → ketangkap jadi JSON
set_error_handler(function(int $severity, string $message, string $file = '', int $line = 0) {
  // error_reporting() bisa 0 kalau di-@ supress → abaikan
  if (!(error_reporting() & $severity)) { return false; }
  throw new ErrorException($message, 0, $severity, $file, $line);
});

// Tangkap fatal error (parse/oom/dll) menjadi JSON
register_shutdown_function(function() {
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    jout(500, false, 'Fatal: '.$err['message'].' @'.$err['file'].':'.$err['line']);
  }
});

// Tangkap exception tak-tertangani jadi JSON
set_exception_handler(function(Throwable $e){
  jout(500, false, 'Server error: '.$e->getMessage());
});

/* ===== DB ===== */
require_once '../config/database.php';
$db  = new Database();
$pdo = $db->getConnection();
// pastikan PDO lempar exception
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

/* ===================== LIST ===================== */
if (($_GET['action'] ?? '') === 'list') {
  try {
    $tahun    = (int)($_GET['tahun'] ?? date('Y'));
    $jenis    = trim((string)($_GET['jenis'] ?? ''));
    $hk       = trim((string)($_GET['hk'] ?? ''));
    $ket      = trim((string)($_GET['ket'] ?? ''));
    $kebunId  = (int)($_GET['kebun_id'] ?? 0);
    $stood_id = (int)($_GET['stood_id'] ?? 0);

    if ($tahun < 2000 || $tahun > 2100) {
      jout(400, false, 'Tahun tidak valid');
    }

    $where = "WHERE p.tahun = :t";
    $p = [':t'=>$tahun];

    if ($jenis !== '')  { $where .= " AND p.jenis_nama = :j"; $p[':j']  = $jenis; }
    if ($hk    !== '')  { $where .= " AND p.hk = :hk";       $p[':hk'] = $hk; }
    if ($ket   !== '')  { $where .= " AND p.ket LIKE :k";    $p[':k']  = "%$ket%"; }
    if ($kebunId > 0) { $where .= " AND p.kebun_id = :kb";  $p[':kb'] = $kebunId; }
    
    // ===== MODIFIKASI =====
    // Filter stood_id dipindah ke HAVING agar memfilter berdasarkan
    // ID yang sudah di-COALESCE (stood_id_fix), bukan kolom mentah.
    $having = "";
    if ($stood_id > 0) {
        $having = "HAVING stood_id_fix = :sid";
        $p[':sid'] = $stood_id;
    }
    // ===== AKHIR MODIFIKASI =====

    /* Fallback SID:
       - jika p.stood_id NULL/0, coba ambil m.id (JOIN by name)
       - stood_name_fix juga difix dari master kalau ada
    */
    $sql = "SELECT
              p.*,
              COALESCE(NULLIF(p.stood_id,0), m.id)   AS stood_id_fix,
              COALESCE(p.stood, m.nama)             AS stood_name_fix
            FROM pemeliharaan_mn p
            LEFT JOIN md_jenis_bibitmn m
              ON TRIM(UPPER(m.nama)) = TRIM(UPPER(p.stood))
            $where
            $having
            ORDER BY stood_id_fix ASC, p.kebun_id ASC, p.id ASC";

    $st = $pdo->prepare($sql);
    foreach($p as $k=>$v){
      $st->bindValue($k, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Map kebun
    $kb_map = $pdo->query("SELECT id, nama_kebun FROM md_kebun")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Master stood aktif
    $stm = $pdo->query("SELECT id, nama FROM md_jenis_bibitmn WHERE is_active=1 ORDER BY nama");
    $stood_map = [];
    $stood_order_master = [];
    while($r = $stm->fetch(PDO::FETCH_ASSOC)){
      $sid = (int)$r['id'];
      $stood_map[$sid] = $r['nama'];
      $stood_order_master[] = $sid;
    }

    // UNION: pastikan semua stood yang ada di data (meski master non-aktif) tetap tampil
    $stood_ids_in_data = [];
    foreach ($rows as $r) {
      $sid = (int)($r['stood_id_fix'] ?? 0);
      if ($sid > 0) $stood_ids_in_data[$sid] = true;
    }
    $stood_order = $stood_order_master;
    foreach (array_keys($stood_ids_in_data) as $sid) {
      if (!in_array($sid, $stood_order, true)) {
        $stood_order[] = $sid;
      }
      // kalau stood_map belum punya nama (karena non-aktif), isi dari data
      if (!isset($stood_map[$sid])) {
        // ambil nama dari salah satu row yang sid-nya sama
        foreach ($rows as $r) {
          if ((int)$r['stood_id_fix'] === $sid) {
            $stood_map[$sid] = $r['stood_name_fix'] ?? ('STOOD '.$sid);
            break;
          }
        }
      }
    }

    // Kembalikan rows dengan kolom stood_id & stood yang sudah “fix”
    foreach ($rows as &$r) {
      $r['stood_id'] = (int)($r['stood_id_fix'] ?? 0);
      if (empty($r['stood'])) $r['stood'] = $r['stood_name_fix'] ?? null;
      unset($r['stood_id_fix'], $r['stood_name_fix']);
    } unset($r);

    jout(200, true, 'ok', [
      'rows'        => $rows,
      'kebun_map'   => $kb_map,
      'stood_map'   => $stood_map,
      'stood_order' => $stood_order,
    ]);
  } catch (Throwable $e) {
    jout(500, false, 'Server error: '.$e->getMessage());
  }
}


/* ============ POST (CRUD) ============ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jout(405, false, 'Method not allowed');
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  jout(401, false, 'Unauthorized');
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
  jout(403, false, 'CSRF tidak valid');
}

$role = $_SESSION['user_role'] ?? 'staf';
if ($role === 'staf') {
  jout(403, false, 'Tidak memiliki izin');
}

$act = $_POST['action'] ?? '';

/* Helpers */
function getNamaById(PDO $pdo, string $table, int $id, string $col='nama'){
  if ($id <= 0) return null;
  if ($table==='md_kebun') $col='nama_kebun';
  $st = $pdo->prepare("SELECT $col AS n FROM $table WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$id]);
  return $st->fetchColumn() ?: null;
}

try {
  if ($act==='store' || $act==='update'){
    $id    = (int)($_POST['id'] ?? 0);

    $tahun = (int)($_POST['tahun'] ?? 0);
    if ($tahun < 2000 || $tahun > 2100) {
      jout(422, false, 'Tahun tidak valid');
    }

    $kebun_id = (int)($_POST['kebun_id'] ?? 0);
    $kebun_nm = $kebun_id ? getNamaById($pdo,'md_kebun',$kebun_id,'nama_kebun') : null;

    // STOOD wajib (pakai stood_id dari master)
    $stood_id = (int)($_POST['stood_id'] ?? 0);
    if ($stood_id <= 0) {
      jout(422, false, 'Stood wajib dipilih');
    }
    $stood_nm = getNamaById($pdo,'md_jenis_bibitmn',$stood_id,'nama');
    if (!$stood_nm) {
      jout(422, false, 'Stood tidak valid');
    }

    // Jenis wajib (pakai jenis_id -> nama)
    $jenis_id = (int)($_POST['jenis_id'] ?? 0);
    $jenis_nm = $jenis_id ? getNamaById($pdo,'md_pemeliharaan_mn',$jenis_id,'nama') : null;
    if (!$jenis_nm) {
      jout(422, false, 'Jenis pekerjaan wajib dipilih');
    }

    $ket   = trim((string)($_POST['ket'] ?? ''));    // gunakan 'ket'
    $hk    = trim((string)($_POST['hk'] ?? ''));
    $sat   = trim((string)($_POST['satuan'] ?? ''));
    $angg  = (float)($_POST['anggaran_tahun'] ?? 0);

    $m = [];
    foreach(['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'] as $k){
      $m[$k] = (float)($_POST[$k] ?? 0);
    }

    // Opsional: kolom 'keterangan' jika masih ada di DB
    $ketera = trim((string)($_POST['keterangan'] ?? ''));

    if ($act==='store'){
      $sql="INSERT INTO pemeliharaan_mn
        (tahun, kebun_id, kebun_nama,
         stood_id, stood,
         jenis_nama, ket, hk, satuan, anggaran_tahun,
         jan,feb,mar,apr,mei,jun,jul,agu,sep,okt,nov,des,
         keterangan, created_at, updated_at)
        VALUES
        (:tahun, :kebun_id, :kebun_nama,
         :stood_id, :stood,
         :jenis_nama, :ket, :hk, :satuan, :anggaran,
         :jan,:feb,:mar,:apr,:mei,:jun,:jul,:agu,:sep,:okt,:nov,:des,
         :ketera, NOW(), NOW())";
      $st=$pdo->prepare($sql);
      $st->execute([
        ':tahun'=>$tahun,
        ':kebun_id'=>$kebun_id ?: null,
        ':kebun_nama'=>$kebun_nm,

        ':stood_id'=>$stood_id,
        ':stood'=>$stood_nm,

        ':jenis_nama'=>$jenis_nm,
        ':ket'=>$ket,
        ':hk'=>$hk,
        ':satuan'=>$sat,
        ':anggaran'=>$angg,

        ':jan'=>$m['jan'], ':feb'=>$m['feb'], ':mar'=>$m['mar'], ':apr'=>$m['apr'], ':mei'=>$m['mei'],
        ':jun'=>$m['jun'], ':jul'=>$m['jul'], ':agu'=>$m['agu'], ':sep'=>$m['sep'], ':okt'=>$m['okt'],
        ':nov'=>$m['nov'], ':des'=>$m['des'],

        ':ketera'=>$ketera
      ]);
      jout(200, true, 'Data berhasil ditambahkan');
    } else {
      if ($id<=0) {
        jout(422, false, 'ID tidak valid');
      }
      $sql="UPDATE pemeliharaan_mn SET
            tahun=:tahun,
            kebun_id=:kebun_id, kebun_nama=:kebun_nama,

            stood_id=:stood_id, stood=:stood,

            jenis_nama=:jenis_nama, ket=:ket, hk=:hk, satuan=:satuan, anggaran_tahun=:anggaran,
            jan=:jan, feb=:feb, mar=:mar, apr=:apr, mei=:mei, jun=:jun, jul=:jul, agu=:agu, sep=:sep, okt=:okt, nov=:nov, des=:des,
            keterangan=:ketera, updated_at=NOW()
            WHERE id=:id";
      $st=$pdo->prepare($sql);
      $st->execute([
        ':tahun'=>$tahun,
        ':kebun_id'=>$kebun_id ?: null,
        ':kebun_nama'=>$kebun_nm,

        ':stood_id'=>$stood_id,
        ':stood'=>$stood_nm,

        ':jenis_nama'=>$jenis_nm,
        ':ket'=>$ket,
        ':hk'=>$hk,
        ':satuan'=>$sat,
        ':anggaran'=>$angg,

        ':jan'=>$m['jan'], ':feb'=>$m['feb'], ':mar'=>$m['mar'], ':apr'=>$m['apr'], ':mei'=>$m['mei'],
        ':jun'=>$m['jun'], ':jul'=>$m['jul'], ':agu'=>$m['agu'], ':sep'=>$m['sep'], ':okt'=>$m['okt'],
        ':nov'=>$m['nov'], ':des'=>$m['des'],

        ':ketera'=>$ketera,
        ':id'=>$id
      ]);
      jout(200, true, 'Data berhasil diperbarui');
    }
  }

  if ($act==='delete'){
    $id=(int)($_POST['id']??0);
    if ($id<=0) {
      jout(422, false, 'ID tidak valid');
    }
    $pdo->prepare("DELETE FROM pemeliharaan_mn WHERE id=:id")->execute([':id'=>$id]);
    jout(200, true, 'Data berhasil dihapus');
  }

  jout(400, false, 'Action tidak dikenali');
} catch (Throwable $e) {
  jout(500, false, 'Server error: '.$e->getMessage());
}

// NOTE: tidak ada closing PHP tag untuk menghindari spasi/HTML nyasar