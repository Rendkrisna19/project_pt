<?php
// master_data_crud.php — Generic CRUD + pagination + filter 'blok' + entity baru: RAYON, APL, KETERANGAN, ASAL GUDANG
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

/* ===== SAFETY ===== */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_error_handler(function ($sev, $msg, $file, $line) {
  throw new ErrorException($msg, 0, $sev, $file, $line);
});
set_exception_handler(function ($e) {
  http_response_code(500);
  error_log("Master Data CRUD Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
  echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
  exit;
});

/* ===== AUTH & CSRF ===== */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(['success' => false, 'message' => 'Silakan login.']);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Metode tidak valid.']);
  exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], ($_POST['csrf_token'] ?? ''))) {
  echo json_encode(['success' => false, 'message' => 'CSRF tidak valid.']);
  exit;
}

/* ===== DB ===== */
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== UTIL ===== */
function null_if_empty($v) {
  if (!isset($v)) return null;
  if (is_string($v) && trim($v) === '') return null;
  return $v;
}
function int_or_null($v) {
  $v = null_if_empty($v);
  return $v === null ? null : (int)$v;
}
function float_or_null($v) {
  $v = null_if_empty($v);
  return $v === null ? null : (float)$v;
}

/* ===== INPUT ===== */
$entity = $_POST['entity'] ?? '';
$action = $_POST['action'] ?? '';

/* ===== MAP ENTITY ===== */
$MAP = [
  // Nama Kebun
  'kebun' => [
    'table' => 'md_kebun',
    'alias' => 'k',
    'cols' => ['kode', 'nama_kebun', 'keterangan'],
    'required' => ['kode', 'nama_kebun'],
    'select' => 'k.*',
    'joins' => '',
    'order' => 'k.nama_kebun ASC',
    'searchable' => ['k.kode', 'k.nama_kebun', 'k.keterangan'],
    'unique_cols' => ['kode']
  ],

  // Bahan Kimia
  'bahan_kimia' => [
    'table' => 'md_bahan_kimia',
    'alias' => 'b',
    'cols' => ['kode', 'nama_bahan', 'satuan_id', 'keterangan'],
    'required' => ['kode', 'nama_bahan'],
    'select' => 'b.*, s.nama AS nama_satuan',
    'joins' => 'LEFT JOIN md_satuan s ON s.id=b.satuan_id',
    'order' => 'b.nama_bahan ASC',
    'searchable' => ['b.kode', 'b.nama_bahan', 's.nama', 'b.keterangan'],
    'unique_cols' => ['kode']
  ],

  // Jenis Pekerjaan (harian)
  'jenis_pekerjaan' => [
    'table' => 'md_jenis_pekerjaan',
    'alias' => 't',
    'cols' => ['nama', 'keterangan'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.keterangan'],
    'unique_cols' => ['nama']
  ],

  // Jenis Pekerjaan (Mingguan)
  'jenis_pekerjaan_mingguan' => [
    'table' => 'md_jenis_pekerjaan_mingguan',
    'alias' => 'jm',
    'cols' => ['nama', 'keterangan'],
    'required' => ['nama'],
    'select' => 'jm.*',
    'joins' => '',
    'order' => 'jm.nama ASC',
    'searchable' => ['jm.nama', 'jm.keterangan'],
    'unique_cols' => ['nama']
  ],

  'unit' => [
    'table' => 'units',
    'alias' => 'u',
    'cols' => ['nama_unit', 'keterangan'],
    'required' => ['nama_unit'],
    'select' => 'u.*',
    'joins' => '',
    'order' => 'u.nama_unit ASC',
    'searchable' => ['u.nama_unit', 'u.keterangan'],
    'unique_cols' => ['nama_unit']
  ],

  'tahun_tanam' => [
    'table' => 'md_tahun_tanam',
    'alias' => 't',
    'cols' => ['tahun', 'keterangan'],
    'required' => ['tahun'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.tahun DESC',
    'searchable' => ['t.tahun', 't.keterangan'],
    'unique_cols' => ['tahun']
  ],

  'blok' => [
    'table' => 'md_blok',
    'alias' => 'b',
    'cols' => ['unit_id', 'kode', 'tahun_tanam', 'luas_ha'],
    'required' => ['unit_id', 'kode'],
    'select' => 'b.*, u.nama_unit',
    'joins' => 'LEFT JOIN units u ON b.unit_id=u.id',
    'order' => 'u.nama_unit ASC, b.kode ASC',
    'searchable' => ['b.kode', 'u.nama_unit', 'b.tahun_tanam'],
    'unique_cols' => ['kode']
  ],

  'fisik' => [
    'table' => 'md_fisik',
    'alias' => 'f',
    'cols' => ['nama'],
    'required' => ['nama'],
    'select' => 'f.*',
    'joins' => '',
    'order' => 'f.nama ASC',
    'searchable' => ['f.nama'],
    'unique_cols' => ['nama']
  ],

  'satuan' => [
    'table' => 'md_satuan',
    'alias' => 's',
    'cols' => ['nama'],
    'required' => ['nama'],
    'select' => 's.*',
    'joins' => '',
    'order' => 's.nama ASC',
    'searchable' => ['s.nama'],
    'unique_cols' => ['nama']
  ],

  'tenaga' => [
    'table' => 'md_tenaga',
    'alias' => 't',
    'cols' => ['kode', 'nama'],
    'required' => ['kode', 'nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.kode', 't.nama'],
    'unique_cols' => ['kode', 'nama']
  ],

  'mobil' => [
    'table' => 'md_mobil',
    'alias' => 'm',
    'cols' => ['kode', 'nama'],
    'required' => ['kode', 'nama'],
    'select' => 'm.*',
    'joins' => '',
    'order' => 'm.nama ASC',
    'searchable' => ['m.kode', 'm.nama'],
    'unique_cols' => ['kode', 'nama']
  ],

  'alat_panen' => [
    'table' => 'md_jenis_alat_panen',
    'alias' => 'a',
    'cols' => ['nama', 'keterangan'],
    'required' => ['nama'],
    'select' => 'a.*',
    'joins' => '',
    'order' => 'a.nama ASC',
    'searchable' => ['a.nama', 'a.keterangan'],
    'unique_cols' => ['nama']
  ],

  'sap' => [
    'table' => 'md_sap',
    'alias' => 's',
    'cols' => ['no_sap', 'deskripsi'],
    'required' => ['no_sap'],
    'select' => 's.*',
    'joins' => '',
    'order' => 's.no_sap ASC',
    'searchable' => ['s.no_sap', 's.deskripsi'],
    'unique_cols' => ['no_sap']
  ],

  'jabatan' => [
    'table' => 'md_jabatan',
    'alias' => 'j',
    'cols' => ['nama'],
    'required' => ['nama'],
    'select' => 'j.*',
    'joins' => '',
    'order' => 'j.nama ASC',
    'searchable' => ['j.nama'],
    'unique_cols' => ['nama']
  ],

  'pupuk' => [
    'table' => 'md_pupuk',
    'alias' => 'p',
    'cols' => ['nama', 'satuan_id'],
    'required' => ['nama'],
    'select' => 'p.*, st.nama AS nama_satuan',
    'joins' => 'LEFT JOIN md_satuan st ON p.satuan_id=st.id',
    'order' => 'p.nama ASC',
    'searchable' => ['p.nama', 'st.nama'],
    'unique_cols' => ['nama']
  ],

  'kode_aktivitas' => [
    'table' => 'md_kode_aktivitas',
    'alias' => 'k',
    'cols' => ['kode', 'nama', 'no_sap_id'],
    'required' => ['kode', 'nama'],
    'select' => 'k.*, s.no_sap',
    'joins' => 'LEFT JOIN md_sap s ON k.no_sap_id=s.id',
    'order' => 'k.kode ASC',
    'searchable' => ['k.kode', 'k.nama', 's.no_sap'],
    'unique_cols' => ['kode']
  ],

  'anggaran' => [
    'table' => 'md_anggaran',
    'alias' => 'a',
    'cols' => ['tahun', 'bulan', 'unit_id', 'kode_aktivitas_id', 'pupuk_id', 'anggaran_bulan_ini', 'anggaran_tahun'],
    'required' => ['tahun', 'bulan', 'unit_id', 'kode_aktivitas_id', 'anggaran_bulan_ini', 'anggaran_tahun'],
    'select' => 'a.*, u.nama_unit, k.kode AS kode_aktivitas, p.nama AS nama_pupuk',
    'joins' => 'JOIN units u ON a.unit_id=u.id
              JOIN md_kode_aktivitas k ON a.kode_aktivitas_id=k.id
              LEFT JOIN md_pupuk p ON a.pupuk_id=p.id',
    'order' => 'a.tahun DESC, FIELD(a.bulan,"Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember") DESC, u.nama_unit ASC',
    'searchable' => ['a.tahun', 'a.bulan', 'u.nama_unit', 'k.kode', 'p.nama']
  ],

  // === Entity Baru Sederhana ===
  'rayon' => [
    'table' => 'md_rayon',
    'alias' => 'r',
    'cols' => ['nama'],
    'required' => ['nama'],
    'select' => 'r.*',
    'joins' => '',
    'order' => 'r.nama ASC',
    'searchable' => ['r.nama'],
    'unique_cols' => ['nama']
  ],
  'apl' => [
    'table' => 'md_apl',
    'alias' => 'apl',
    'cols' => ['nama'],
    'required' => ['nama'],
    'select' => 'apl.*',
    'joins' => '',
    'order' => 'apl.nama ASC',
    'searchable' => ['apl.nama'],
    'unique_cols' => ['nama']
  ],
  'keterangan' => [
    'table' => 'md_keterangan',
    'alias' => 'ket',
    'cols' => ['keterangan'],
    'required' => ['keterangan'],
    'select' => 'ket.*',
    'joins' => '',
    'order' => 'ket.id DESC',
    'searchable' => ['ket.keterangan'],
  ],
  'asal_gudang' => [
    'table' => 'md_asal_gudang',
    'alias' => 'g',
    'cols' => ['nama'],
    'required' => ['nama'],
    'select' => 'g.*',
    'joins' => '',
    'order' => 'g.nama ASC',
    'searchable' => ['g.nama'],
    'unique_cols' => ['nama']
  ],

  // === Pemeliharaan (nama + deskripsi) ===
  'pem_tm' => [
    'table' => 'md_pemeliharaan_tm',
    'alias' => 't',
    'cols' => ['nama', 'deskripsi'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.deskripsi'],
    'unique_cols' => ['nama']
  ],
  'pem_tu' => [
    'table' => 'md_pemeliharaan_tu',
    'alias' => 't',
    'cols' => ['nama', 'deskripsi'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.deskripsi'],
    'unique_cols' => ['nama']
  ],
  'pem_tk' => [
    'table' => 'md_pemeliharaan_tk',
    'alias' => 't',
    'cols' => ['nama', 'deskripsi'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.deskripsi'],
    'unique_cols' => ['nama']
  ],
  'pem_tbm1' => [
    'table' => 'md_pemeliharaan_tbm1',
    'alias' => 't',
    'cols' => ['nama', 'deskripsi'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.deskripsi'],
    'unique_cols' => ['nama']
  ],
  'pem_tbm2' => [
    'table' => 'md_pemeliharaan_tbm2',
    'alias' => 't',
    'cols' => ['nama', 'deskripsi'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.deskripsi'],
    'unique_cols' => ['nama']
  ],
  'pem_tbm3' => [
    'table' => 'md_pemeliharaan_tbm3',
    'alias' => 't',
    'cols' => ['nama', 'deskripsi'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.deskripsi'],
    'unique_cols' => ['nama']
  ],
  'pem_pn' => [
    'table' => 'md_pemeliharaan_pn',
    'alias' => 't',
    'cols' => ['nama', 'deskripsi'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.deskripsi'],
    'unique_cols' => ['nama']
  ],
  'pem_mn' => [
    'table' => 'md_pemeliharaan_mn',
    'alias' => 't',
    'cols' => ['nama', 'deskripsi'],
    'required' => ['nama'],
    'select' => 't.*',
    'joins' => '',
    'order' => 't.nama ASC',
    'searchable' => ['t.nama', 't.deskripsi'],
    'unique_cols' => ['nama']
  ],

  // === NEW: Bibit TM & Bibit PN ===
  'bibit_tm' => [
    'table' => 'md_jenis_bibitmn',
    'alias' => 'btm',
    'cols' => ['kode', 'nama', 'is_active'],
    'required' => ['nama'],
    'select' => 'btm.*',
    'joins' => '',
    'order' => 'btm.nama ASC',
    'searchable' => ['btm.kode', 'btm.nama'],
    'unique_cols' => ['nama', 'kode'] // kode unik jika diisi; jika null, dilewati
  ],
  'bibit_pn' => [
    'table' => 'md_jenis_bibitpn',
    'alias' => 'bpn',
    'cols' => ['kode', 'nama', 'is_active'],
    'required' => ['nama'],
    'select' => 'bpn.*',
    'joins' => '',
    'order' => 'bpn.nama ASC',
    'searchable' => ['bpn.kode', 'bpn.nama'],
    'unique_cols' => ['nama', 'kode']
  ],
];

if (!isset($MAP[$entity])) {
  echo json_encode(['success' => false, 'message' => 'Entity tidak dikenali']);
  exit;
}
$cfg   = $MAP[$entity];
$table = $cfg['table'];
$alias = $cfg['alias'] ?? 't';
$select = $cfg['select'] ?? "$alias.*";
$joins = $cfg['joins'] ?? '';
$order = $cfg['order'] ?? "$alias.id DESC";
$searchable = $cfg['searchable'] ?? [];
$uniqueCols = $cfg['unique_cols'] ?? [];

/* ========= LIST ========= */
if ($action === 'list') {
  $page = max(1, (int)($_POST['page'] ?? 1));
  $per  = min(500, max(1, (int)($_POST['per_page'] ?? 15)));
  $off  = ($page - 1) * $per;

  $q = trim((string)($_POST['q'] ?? ''));

  $where = ' WHERE 1=1 ';
  $params = [];

  if ($q !== '' && $searchable) {
    $likes = [];
    foreach ($searchable as $col) {
      $likes[] = "$col LIKE :q";
    }
    if ($likes) {
      $where .= ' AND (' . implode(' OR ', $likes) . ')';
      $params[':q'] = "%$q%";
    }
  }

  if ($entity === 'blok') {
    $unit_id = int_or_null($_POST['unit_id'] ?? '');
    $kode    = null_if_empty($_POST['kode'] ?? '');
    $tahun   = int_or_null($_POST['tahun'] ?? '');

    if ($unit_id !== null) { $where .= " AND b.unit_id = :unit_id"; $params[':unit_id'] = $unit_id; }
    if ($kode !== null)    { $where .= " AND b.kode LIKE :kode";   $params[':kode'] = "%$kode%"; }
    if ($tahun !== null)   { $where .= " AND b.tahun_tanam = :th"; $params[':th'] = $tahun; }
  }

  $from = " FROM $table $alias ";
  if ($joins) $from .= " $joins ";
  $sqlCount = "SELECT COUNT(*) AS c " . $from . $where;
  $stC = $conn->prepare($sqlCount);
  foreach ($params as $k => $v) $stC->bindValue($k, $v);
  $stC->execute();
  $total = (int)$stC->fetchColumn();

  $sql = "SELECT $select " . $from . $where . " ORDER BY $order LIMIT :lim OFFSET :off";
  $st  = $conn->prepare($sql);
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':lim', $per, PDO::PARAM_INT);
  $st->bindValue(':off', $off, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'success' => true,
    'data' => $rows,
    'total' => $total,
    'page' => $page,
    'per_page' => $per
  ]);
  exit;
}

/* ========= STORE / UPDATE ========= */
if ($action === 'store' || $action === 'update') {
  $data = [];
  foreach (($cfg['cols'] ?? []) as $c) {
    $data[$c] = $_POST[$c] ?? null;
  }
  foreach ($data as $k => $v) {
    $data[$k] = null_if_empty($v);
  }

  // casting khusus
  if ($entity === 'tahun_tanam') {
    $data['tahun'] = int_or_null($data['tahun'] ?? null);
  }
  if ($entity === 'blok') {
    $data['unit_id']     = int_or_null($data['unit_id'] ?? null);
    $data['tahun_tanam'] = int_or_null($data['tahun_tanam'] ?? null);
    $data['luas_ha']     = float_or_null($data['luas_ha'] ?? null);
  }
  if ($entity === 'pupuk') {
    $data['satuan_id'] = int_or_null($data['satuan_id'] ?? null);
  }
  if ($entity === 'bahan_kimia') {
    $data['satuan_id'] = int_or_null($data['satuan_id'] ?? null);
  }
  if ($entity === 'kode_aktivitas') {
    $data['no_sap_id'] = int_or_null($data['no_sap_id'] ?? null);
  }
  if ($entity === 'anggaran') {
    $data['tahun']               = int_or_null($data['tahun'] ?? null);
    $data['unit_id']             = int_or_null($data['unit_id'] ?? null);
    $data['kode_aktivitas_id']   = int_or_null($data['kode_aktivitas_id'] ?? null);
    $data['pupuk_id']            = int_or_null($data['pupuk_id'] ?? null);
    $data['anggaran_bulan_ini']  = float_or_null($data['anggaran_bulan_ini'] ?? null);
    $data['anggaran_tahun']      = float_or_null($data['anggaran_tahun'] ?? null);
    $allowedBulan = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
    if (isset($data['bulan']) && !in_array($data['bulan'], $allowedBulan, true)) {
      echo json_encode(['success' => false, 'message' => 'Bulan tidak valid']); exit;
    }
  }
  // NEW: normalisasi checkbox is_active (default 1)
  if ($entity === 'bibit_tm' || $entity === 'bibit_pn') {
    // jika tidak terkirim (edge case), anggap 0; namun dari UI kita kirim eksplisit 1/0
    $data['is_active'] = (isset($_POST['is_active']) && $_POST['is_active'] !== '0') ? 1 : 0;
  }

  // validasi wajib
  if (!empty($cfg['required'])) {
    foreach ($cfg['required'] as $req) {
      $val = $data[$req] ?? null;
      if ($val === null || (is_string($val) && trim($val) === '')) {
        echo json_encode(['success' => false, 'message' => "Field wajib '" . ucfirst(str_replace('_', ' ', $req)) . "' belum diisi."]);
        exit;
      }
    }
  }

  // cek unik
  $currentId = ($action === 'update') ? (int)($_POST['id'] ?? 0) : 0;
  if (!empty($uniqueCols)) {
    foreach ($uniqueCols as $col) {
      if (array_key_exists($col, $data) && $data[$col] !== null) {
        $sqlC = "SELECT id FROM {$table} WHERE {$col} = :val " . ($currentId > 0 ? ' AND id <> :id' : '') . " LIMIT 1";
        $stC = $conn->prepare($sqlC);
        $stC->bindValue(':val', $data[$col]);
        if ($currentId > 0) $stC->bindValue(':id', $currentId, PDO::PARAM_INT);
        $stC->execute();
        if ($stC->fetch()) {
          echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $col)) . " '{$data[$col]}' sudah digunakan."]);
          exit;
        }
      }
    }
  }

  if ($action === 'store') {
    $cols = array_keys($data);
    $vals = array_map(fn($c) => ":$c", $cols);

    $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $st  = $conn->prepare($sql);
    foreach ($data as $k => $v) {
      $param = is_null($v) ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : (is_float($v) ? PDO::PARAM_STR : PDO::PARAM_STR));
      $st->bindValue(":$k", $v, $param);
    }
    $st->execute();
    echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan', 'id' => $conn->lastInsertId()]);
    exit;
  } else {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'ID tidak valid']); exit; }

    $setParts = [];
    foreach (array_keys($data) as $c) $setParts[] = "$c = :$c";
    $set = implode(', ', $setParts);

    $sql = "UPDATE {$table} SET {$set} WHERE id=:id";
    $st  = $conn->prepare($sql);
    foreach ($data as $k => $v) {
      $param = is_null($v) ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : (is_float($v) ? PDO::PARAM_STR : PDO::PARAM_STR));
      $st->bindValue(":$k", $v, $param);
    }
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui']);
    exit;
  }
}

/* ========= DELETE ========= */
if ($action === 'delete') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'ID tidak valid']); exit; }
  try {
    $st = $conn->prepare("DELETE FROM {$table} WHERE id=:id");
    $st->execute([':id' => $id]);
    echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']); exit;
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') {
      echo json_encode(['success' => false, 'message' => 'Tidak bisa menghapus: data master ini sedang digunakan di tabel lain.']); exit;
    }
    error_log("Master Data Delete Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data karena masalah database.']); exit;
  }
}

/* ========= FALLBACK ========= */
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali']);
