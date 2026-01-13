<?php
// admin/laporan_mingguan_crud.php
// Backend CRUD arsip per-kategori (list/store/update/delete)
// Versi: 2025-11-11 — FIX alias kebun_nama, upload path, validasi CUD, Max Upload 25MB

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

/* ========== RESPON & GUARD ==========\ */
function json_error(string $message, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}
function json_ok($payload = []): void {
  echo json_encode(array_merge(['success' => true], $payload), JSON_UNESCAPED_UNICODE);
  exit;
}
function cek_login(): void {
  if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    json_error('Sesi Anda berakhir. Silakan login ulang.', 401);
  }
}
function cek_izin_cud(): void {
  $role = $_SESSION['user_role'] ?? 'staf';
  if ($role === 'staf') json_error('Anda tidak memiliki izin untuk aksi ini.', 403);
}
function cek_csrf_if_cud(): void {
  $csrf = $_POST['csrf_token'] ?? '';
  if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    json_error('Sesi tidak valid (CSRF). Muat ulang halaman.', 403);
  }
}

/* ========== PERSIAPAN UPLOAD ==========\ */
// root: /admin/..../uploads/laporan_mingguan/
$uploadRootAbs = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$subDir        = 'laporan_mingguan';
$targetDirAbs  = rtrim($uploadRootAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR;
$dbPrefix      = 'uploads/' . $subDir . '/';

if (!is_dir($targetDirAbs) && !@mkdir($targetDirAbs, 0775, true)) {
  json_error('Gagal membuat folder ' . $dbPrefix . '. Periksa izin.', 500);
}
if (!is_writable($targetDirAbs)) {
  json_error('Folder ' . $dbPrefix . ' tidak bisa ditulis. Periksa izin.', 500);
}

function hapus_file_lama(?string $db_path): void {
  global $dbPrefix, $targetDirAbs;
  if (!$db_path) return;
  $base = basename($db_path);
  // Pastikan path sesuai prefix dan berada di folder upload yang benar
  if ($db_path === $dbPrefix . $base) {
    $abs = $targetDirAbs . $base;
    if (is_file($abs)) @unlink($abs);
  }
}

function handle_upload(): ?string {
  global $targetDirAbs, $dbPrefix;
  if (empty($_FILES['upload_dokumen']) || $_FILES['upload_dokumen']['error'] === UPLOAD_ERR_NO_FILE) {
    return null;
  }
  $f = $_FILES['upload_dokumen'];
  if ($f['error'] !== UPLOAD_ERR_OK) json_error('Upload gagal. Kode: '.$f['error'], 500);

  // MODIFIKASI: Mengubah limit menjadi 25MB
  $max = 25 * 1024 * 1024; // 25MB
  
  if ($f['size'] > $max) {
      json_error('Ukuran file melebihi batas 25MB.');
  }

  $allow = ['pdf','jpg','jpeg','png','doc','docx','xls','xlsx'];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $allow, true)) {
    json_error('Tipe file tidak diizinkan: '.implode(', ', $allow));
  }

  $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '', basename($f['name']));
  $newFile  = 'lap_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($ext ? '.'.$ext : '');
  $absPath  = $targetDirAbs . $newFile;

  if (!move_uploaded_file($f['tmp_name'], $absPath)) {
    json_error('Gagal menyimpan file.', 500);
  }
  return $dbPrefix . $newFile;
}

/* ========== DB ==========\ */
require_once '../config/database.php';
$pdo = (new Database())->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function qint($v){ return ($v===''||$v===null) ? null : (int)$v; }
function qstr($v){ return trim((string)$v); }

/* ========== ACTION ==========\ */
cek_login();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

try {
  if ($action === 'list') {
    // List boleh tanpa CSRF (read-only)
    $k_id = qint($_POST['kategori_id'] ?? $_GET['kategori_id'] ?? null);
    if (!$k_id) json_error('Kategori ID tidak valid.');

    $tahun = qstr($_POST['tahun'] ?? $_GET['tahun'] ?? '');
    $q     = qstr($_POST['q']     ?? $_GET['q']     ?? '');

    $where = ['lm.kategori_id = :k_id'];
    $bind  = [':k_id' => $k_id];

    if ($tahun !== '' && strtolower($tahun) !== 'all') {
      $where[] = 'lm.tahun = :tahun';
      $bind[':tahun'] = (int)$tahun;
    }
    if ($q !== '') {
      $where[] = '(lm.uraian LIKE :q OR mk.nama_kebun LIKE :q OR lm.link_dokumen LIKE :q)';
      $bind[':q'] = '%'.$q.'%';
    }

    $sql = "
      SELECT
        lm.id, lm.kategori_id, lm.tahun, lm.kebun_id,
        lm.uraian, lm.link_dokumen, lm.upload_dokumen,
        mk.nama_kebun AS kebun_nama
      FROM laporan_mingguan lm
      LEFT JOIN md_kebun mk ON mk.id = lm.kebun_id
      WHERE ".implode(' AND ', $where)."
      ORDER BY lm.tahun DESC, lm.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    json_ok(['data' => $rows]);
  }

  if ($action === 'store') {
    cek_izin_cud();
    cek_csrf_if_cud();

    $k_id     = qint($_POST['kategori_id'] ?? null);
    $tahun    = qint($_POST['tahun'] ?? null);
    $kebun_id = qint($_POST['kebun_id'] ?? null);
    $uraian   = qstr($_POST['uraian'] ?? '');
    $link     = qstr($_POST['link_dokumen'] ?? '') ?: null;

    if (!$k_id)     json_error('Kategori ID wajib.');
    if (!$tahun)    json_error('Tahun wajib.');
    if (!$kebun_id) json_error('Kebun wajib.');
    if ($uraian==='') json_error('Uraian wajib.');

    // (Opsional) validasi kebun ada
    $chk = $pdo->prepare('SELECT COUNT(*) FROM md_kebun WHERE id = ?');
    $chk->execute([$kebun_id]);
    if ((int)$chk->fetchColumn() === 0) json_error('Kebun tidak ditemukan.');

    $upload = handle_upload();

    $ins = $pdo->prepare("
      INSERT INTO laporan_mingguan
      (kategori_id, tahun, kebun_id, uraian, link_dokumen, upload_dokumen)
      VALUES (:k, :t, :kid, :u, :l, :up)
    ");
    $ins->execute([
      ':k'=>$k_id, ':t'=>$tahun, ':kid'=>$kebun_id, ':u'=>$uraian, ':l'=>$link, ':up'=>$upload
    ]);

    json_ok(['message'=>'Laporan berhasil disimpan.']);
  }

  if ($action === 'update') {
    cek_izin_cud();
    cek_csrf_if_cud();

    $id       = qint($_POST['id'] ?? null);
    $k_id     = qint($_POST['kategori_id'] ?? null);
    $tahun    = qint($_POST['tahun'] ?? null);
    $kebun_id = qint($_POST['kebun_id'] ?? null);
    $uraian   = qstr($_POST['uraian'] ?? '');
    $link     = qstr($_POST['link_dokumen'] ?? '') ?: null;

    if (!$id)      json_error('ID tidak valid.');
    if (!$k_id)    json_error('Kategori ID wajib.');
    if (!$tahun)   json_error('Tahun wajib.');
    if (!$kebun_id)json_error('Kebun wajib.');
    if ($uraian==='') json_error('Uraian wajib.');

    $old = $pdo->prepare('SELECT upload_dokumen FROM laporan_mingguan WHERE id = ?');
    $old->execute([$id]);
    $oldPath = $old->fetchColumn();

    // (Opsional) validasi kebun ada
    $chk = $pdo->prepare('SELECT COUNT(*) FROM md_kebun WHERE id = ?');
    $chk->execute([$kebun_id]);
    if ((int)$chk->fetchColumn() === 0) json_error('Kebun tidak ditemukan.');

    $newUpload = handle_upload();

    $set = "
      kategori_id = :k, tahun = :t, kebun_id = :kid,
      uraian = :u, link_dokumen = :l
    ";
    $bind = [':k'=>$k_id, ':t'=>$tahun, ':kid'=>$kebun_id, ':u'=>$uraian, ':l'=>$link, ':id'=>$id];

    if ($newUpload !== null) {
      $set .= ', upload_dokumen = :up';
      $bind[':up'] = $newUpload;
    }

    $upd = $pdo->prepare("UPDATE laporan_mingguan SET $set WHERE id = :id");
    $upd->execute($bind);

    if ($newUpload !== null) hapus_file_lama($oldPath);

    json_ok(['message'=>'Laporan berhasil diperbarui.']);
  }

  if ($action === 'delete') {
    cek_izin_cud();
    cek_csrf_if_cud();

    $id = qint($_POST['id'] ?? null);
    if (!$id) json_error('ID tidak valid.');

    $sel = $pdo->prepare('SELECT upload_dokumen FROM laporan_mingguan WHERE id = ?');
    $sel->execute([$id]);
    $oldPath = $sel->fetchColumn();

    $del = $pdo->prepare('DELETE FROM laporan_mingguan WHERE id = ?');
    $del->execute([$id]);

    hapus_file_lama($oldPath);

    json_ok(['message'=>'Laporan berhasil dihapus.']);
  }

  json_error('Aksi tidak dikenal.', 400);

} catch (PDOException $e) {
  json_error('Database error: '.$e->getMessage(), 500);
} catch (Throwable $e) {
  json_error('Server error: '.$e->getMessage(), 500);
}