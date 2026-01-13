<?php
// master_data_crud.php
// MODIFIED: Removed (Mingguan, SAP, Kode Akt, Anggaran) + Added (Kendaraan, BBM/Pelumas)
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
    // --- UMUM ---
    'kebun' => [
        'table' => 'md_kebun',
        'alias' => 'k',
        'cols' => ['kode', 'nama_kebun', 'keterangan'],
        'required' => ['kode', 'nama_kebun'],
        'order' => 'k.nama_kebun ASC',
        'searchable' => ['k.kode', 'k.nama_kebun', 'k.keterangan'],
        'unique_cols' => ['kode']
    ],
    'unit' => [
        'table' => 'units',
        'alias' => 'u',
        'cols' => ['nama_unit', 'keterangan'],
        'required' => ['nama_unit'],
        'order' => 'u.nama_unit ASC',
        'searchable' => ['u.nama_unit', 'u.keterangan'],
        'unique_cols' => ['nama_unit']
    ],
    'rayon' => [
        'table' => 'md_rayon',
        'alias' => 'r',
        'cols' => ['nama'],
        'required' => ['nama'],
        'order' => 'r.nama ASC',
        'searchable' => ['r.nama'],
        'unique_cols' => ['nama']
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
    'tahun_tanam' => [
        'table' => 'md_tahun_tanam',
        'alias' => 't',
        'cols' => ['tahun', 'keterangan'],
        'required' => ['tahun'],
        'order' => 't.tahun DESC',
        'searchable' => ['t.tahun', 't.keterangan'],
        'unique_cols' => ['tahun']
    ],

    // --- MATERIAL & BARANG ---
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
    
    // [NEW] Jenis Kendaraan
    'jenis_kendaraan' => [
        'table' => 'md_jenis_kendaraan',
        'alias' => 'jk',
        'cols' => ['nama', 'keterangan'],
        'required' => ['nama'],
        'order' => 'jk.nama ASC',
        'searchable' => ['jk.nama', 'jk.keterangan'],
        'unique_cols' => ['nama']
    ],
    // [NEW] Jenis BBM & Pelumas
    'jenis_bahan_bakar_pelumas' => [
        'table' => 'md_jenis_bahan_bakar_pelumas',
        'alias' => 'bbm',
        'cols' => ['nama', 'satuan', 'keterangan'],
        'required' => ['nama', 'satuan'],
        'order' => 'bbm.nama ASC',
        'searchable' => ['bbm.nama', 'bbm.satuan', 'bbm.keterangan'],
        'unique_cols' => ['nama']
    ],

    'alat_panen' => [
        'table' => 'md_jenis_alat_panen',
        'alias' => 'a',
        'cols' => ['nama', 'keterangan'],
        'required' => ['nama'],
        'order' => 'a.nama ASC',
        'searchable' => ['a.nama', 'a.keterangan'],
        'unique_cols' => ['nama']
    ],
    'apl' => [
        'table' => 'md_apl',
        'alias' => 'apl',
        'cols' => ['nama'],
        'required' => ['nama'],
        'order' => 'apl.nama ASC',
        'searchable' => ['apl.nama'],
        'unique_cols' => ['nama']
    ],

    // --- BIBIT ---
    'bibit_tm' => [
        'table' => 'md_jenis_bibitmn', // Pastikan nama tabel sesuai DB Anda
        'alias' => 'btm',
        'cols' => ['kode', 'nama', 'is_active'],
        'required' => ['nama'],
        'order' => 'btm.nama ASC',
        'searchable' => ['btm.kode', 'btm.nama'],
        'unique_cols' => ['nama']
    ],
    'bibit_pn' => [
        'table' => 'md_jenis_bibitpn',
        'alias' => 'bpn',
        'cols' => ['kode', 'nama', 'is_active'],
        'required' => ['nama'],
        'order' => 'bpn.nama ASC',
        'searchable' => ['bpn.kode', 'bpn.nama'],
        'unique_cols' => ['nama']
    ],

    // --- PEKERJAAN & PEMELIHARAAN ---
    'jenis_pekerjaan' => [
        'table' => 'md_jenis_pekerjaan',
        'alias' => 't',
        'cols' => ['nama', 'keterangan'],
        'required' => ['nama'],
        'order' => 't.nama ASC',
        'searchable' => ['t.nama', 't.keterangan'],
        'unique_cols' => ['nama']
    ],
    // (Master Pemeliharaan: menggunakan struktur tabel yang sama)
    'pem_tm' =>   ['table'=>'md_pemeliharaan_tm',   'cols'=>['nama','deskripsi'], 'required'=>['nama'], 'searchable'=>['nama','deskripsi']],
    'pem_tu' =>   ['table'=>'md_pemeliharaan_tu',   'cols'=>['nama','deskripsi'], 'required'=>['nama'], 'searchable'=>['nama','deskripsi']],
    'pem_tk' =>   ['table'=>'md_pemeliharaan_tk',   'cols'=>['nama','deskripsi'], 'required'=>['nama'], 'searchable'=>['nama','deskripsi']],
    'pem_tbm1' => ['table'=>'md_pemeliharaan_tbm1', 'cols'=>['nama','deskripsi'], 'required'=>['nama'], 'searchable'=>['nama','deskripsi']],
    'pem_tbm2' => ['table'=>'md_pemeliharaan_tbm2', 'cols'=>['nama','deskripsi'], 'required'=>['nama'], 'searchable'=>['nama','deskripsi']],
    'pem_tbm3' => ['table'=>'md_pemeliharaan_tbm3', 'cols'=>['nama','deskripsi'], 'required'=>['nama'], 'searchable'=>['nama','deskripsi']],
    'pem_pn' =>   ['table'=>'md_pemeliharaan_pn',   'cols'=>['nama','deskripsi'], 'required'=>['nama'], 'searchable'=>['nama','deskripsi']],
    'pem_mn' =>   ['table'=>'md_pemeliharaan_mn',   'cols'=>['nama','deskripsi'], 'required'=>['nama'], 'searchable'=>['nama','deskripsi']],

    // --- LAINNYA ---
    'satuan' => [
        'table' => 'md_satuan',
        'alias' => 's',
        'cols' => ['nama'],
        'required' => ['nama'],
        'order' => 's.nama ASC',
        'searchable' => ['s.nama'],
        'unique_cols' => ['nama']
    ],
    'fisik' => [
        'table' => 'md_fisik',
        'alias' => 'f',
        'cols' => ['nama'],
        'required' => ['nama'],
        'order' => 'f.nama ASC',
        'searchable' => ['f.nama'],
        'unique_cols' => ['nama']
    ],
    'tenaga' => [
        'table' => 'md_tenaga',
        'alias' => 't',
        'cols' => ['kode', 'nama'],
        'required' => ['kode', 'nama'],
        'order' => 't.nama ASC',
        'searchable' => ['t.kode', 't.nama'],
        'unique_cols' => ['kode']
    ],
    'mobil' => [
        'table' => 'md_mobil',
        'alias' => 'm',
        'cols' => ['kode', 'nama'],
        'required' => ['kode', 'nama'],
        'order' => 'm.nama ASC',
        'searchable' => ['m.kode', 'm.nama'],
        'unique_cols' => ['kode']
    ],
    'jabatan' => [
        'table' => 'md_jabatan',
        'alias' => 'j',
        'cols' => ['nama'],
        'required' => ['nama'],
        'order' => 'j.nama ASC',
        'searchable' => ['j.nama'],
        'unique_cols' => ['nama']
    ],
    'asal_gudang' => [
        'table' => 'md_asal_gudang',
        'alias' => 'g',
        'cols' => ['nama'],
        'required' => ['nama'],
        'order' => 'g.nama ASC',
        'searchable' => ['g.nama'],
        'unique_cols' => ['nama']
    ],
    'keterangan' => [
        'table' => 'md_keterangan',
        'alias' => 'ket',
        'cols' => ['keterangan'],
        'required' => ['keterangan'],
        'order' => 'ket.id DESC',
        'searchable' => ['ket.keterangan'],
    ],
    'barang_gudang' => [
        'table' => 'md_jenis_barang_gudang',
        'alias' => 'bg',
        'cols' => ['nama', 'satuan', 'keterangan'],
        'required' => ['nama'],
        'order' => 'bg.nama ASC',
        'searchable' => ['bg.nama', 'bg.satuan'],
        'unique_cols' => ['nama']
    ],
    // [NEW] Master No Polisi
    'no_polisi' => [
        'table' => 'md_no_polisi',
        'alias' => 'np',
        'cols' => ['no_polisi', 'keterangan'],
        'required' => ['no_polisi'],
        'order' => 'np.no_polisi ASC',
        'searchable' => ['np.no_polisi', 'np.keterangan'],
        'unique_cols' => ['no_polisi']
    ],
    'jenis_pekerjaan_kertas_kerja' => [
        'table' => 'md_jenis_pekerjaan_kertas_kerja',
        'alias' => 'jpkk',
        'cols' => ['nama', 'satuan', 'kategori', 'urutan', 'is_active'],
        'required' => ['nama', 'kategori'],
        'order' => 'jpkk.urutan ASC, jpkk.nama ASC',
        'searchable' => ['jpkk.nama', 'jpkk.kategori', 'jpkk.satuan'],
        'unique_cols' => ['nama'] // Mencegah nama pekerjaan ganda
    ],
];

// Helper untuk normalisasi config pemeliharaan yang formatnya ringkas
foreach($MAP as $k => $v) {
    if (!isset($v['alias']) && strpos($k, 'pem_') === 0) {
        $MAP[$k]['alias'] = 't';
        $MAP[$k]['select'] = 't.*';
        $MAP[$k]['joins'] = '';
        $MAP[$k]['order'] = 't.nama ASC';
        $MAP[$k]['unique_cols'] = ['nama'];
    }
}

if (!isset($MAP[$entity])) {
    echo json_encode(['success' => false, 'message' => 'Entity tidak dikenali']);
    exit;
}

$cfg = $MAP[$entity];
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
            // Handle alias manual jika config ringkas
            if(strpos($col, '.') === false) $col = "$alias.$col";
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

if (in_array($entity, ['bibit_tm', 'bibit_pn', 'jenis_pekerjaan_kertas_kerja'])) {
        $data['is_active'] = (isset($_POST['is_active']) && $_POST['is_active'] !== '0') ? 1 : 0;
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

    // Casting Khusus
    if ($entity === 'tahun_tanam') {
        $data['tahun'] = int_or_null($data['tahun'] ?? null);
    }
    if ($entity === 'blok') {
        $data['unit_id']     = int_or_null($data['unit_id'] ?? null);
        $data['tahun_tanam'] = int_or_null($data['tahun_tanam'] ?? null);
        $data['luas_ha']     = float_or_null($data['luas_ha'] ?? null);
    }
    if ($entity === 'pupuk' || $entity === 'bahan_kimia') {
        $data['satuan_id'] = int_or_null($data['satuan_id'] ?? null);
    }
    // Normalisasi checkbox is_active
    if ($entity === 'bibit_tm' || $entity === 'bibit_pn') {
        $data['is_active'] = (isset($_POST['is_active']) && $_POST['is_active'] !== '0') ? 1 : 0;
    }

    // Validasi Wajib
    if (!empty($cfg['required'])) {
        foreach ($cfg['required'] as $req) {
            $val = $data[$req] ?? null;
            if ($val === null || (is_string($val) && trim($val) === '')) {
                echo json_encode(['success' => false, 'message' => "Field wajib '" . ucfirst(str_replace('_', ' ', $req)) . "' belum diisi."]);
                exit;
            }
        }
    }

    // Cek Unique
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