<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login.']);
  exit;
}

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');
$action   = $_POST['action'] ?? '';

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
  echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid.']);
  exit;
}

// Path absolut di server untuk menyimpan file
$uploadDirAbs = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$subDir       = 'laporan_mingguan';
$targetDir    = $uploadDirAbs . '/' . $subDir . '/';
if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
  echo json_encode(['success' => false, 'message' => 'Gagal membuat direktori upload.']);
  exit;
}

// Helper: validasi file
function allowExt($name) {
  $allowed = ['pdf','jpg','jpeg','png','doc','docx','xls','xlsx'];
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return in_array($ext, $allowed, true);
}

try {
  switch ($action) {
    case 'list': {
      $params = [];
      // HILANGKAN join ke md_jenis_pekerjaan; gunakan kolom teks l.uraian
      $sql = "SELECT l.*, k.nama_kebun AS kebun_nama
              FROM laporan_mingguan l
              LEFT JOIN md_kebun k ON l.kebun_id = k.id
              WHERE 1=1";

      if (!empty($_POST['q'])) {
        $search = '%' . $_POST['q'] . '%';
        $sql   .= " AND (k.nama_kebun LIKE ? OR l.uraian LIKE ? OR l.link_dokumen LIKE ?)";
        array_push($params, $search, $search, $search);
      }

      $sql .= " ORDER BY l.tahun DESC, l.id DESC";
      $stmt = $conn->prepare($sql);
      $stmt->execute($params);
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Normalisasi path file ke URL publik
      foreach ($data as &$row) {
        if (!empty($row['upload_dokumen'])) {
          $row['upload_dokumen'] = 'uploads/' . ltrim(str_replace('\\', '/', $row['upload_dokumen']), '/');
        }
      }
      echo json_encode(['success' => true, 'data' => $data]);
      break;
    }

    case 'store': {
      if ($isStaf) throw new Exception('Akses ditolak.');
      // Wajib: tahun, kebun_id, uraian
      if (empty($_POST['tahun']) || empty($_POST['kebun_id']) || empty($_POST['uraian'])) {
        throw new Exception('Tahun, Kebun, dan Uraian wajib diisi.');
      }

      $filePathWeb = null;
      if (!empty($_FILES['upload_dokumen']) && $_FILES['upload_dokumen']['error'] === UPLOAD_ERR_OK) {
        $name = $_FILES['upload_dokumen']['name'];
        if (!allowExt($name)) throw new Exception('Ekstensi file tidak diizinkan.');
        $safe = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($name));
        $fileName = time() . '_' . $safe;
        $filePathAbs = $targetDir . $fileName;
        if (!move_uploaded_file($_FILES['upload_dokumen']['tmp_name'], $filePathAbs)) {
          throw new Exception('Gagal mengupload file.');
        }
        $filePathWeb = $subDir . '/' . $fileName; // simpan relatif
      }

      $stmt = $conn->prepare("INSERT INTO laporan_mingguan
        (tahun, kebun_id, uraian, link_dokumen, upload_dokumen, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
      $stmt->execute([
        $_POST['tahun'],
        (int)$_POST['kebun_id'],
        trim((string)$_POST['uraian']),
        $_POST['link_dokumen'] ?: null,
        $filePathWeb
      ]);

      echo json_encode(['success' => true, 'message' => 'Laporan berhasil disimpan.']);
      break;
    }

    case 'update': {
      if ($isStaf) throw new Exception('Akses ditolak.');
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) throw new Exception('ID data tidak valid.');
      if (empty($_POST['tahun']) || empty($_POST['kebun_id']) || empty($_POST['uraian'])) {
        throw new Exception('Tahun, Kebun, dan Uraian wajib diisi.');
      }

      $stmt = $conn->prepare('SELECT upload_dokumen FROM laporan_mingguan WHERE id = ?');
      $stmt->execute([$id]);
      $oldFileRel = $stmt->fetchColumn();
      $filePathWeb = $oldFileRel;

      if (!empty($_FILES['upload_dokumen']) && $_FILES['upload_dokumen']['error'] === UPLOAD_ERR_OK) {
        $name = $_FILES['upload_dokumen']['name'];
        if (!allowExt($name)) throw new Exception('Ekstensi file tidak diizinkan.');

        if ($oldFileRel) {
          $oldFileAbs = $uploadDirAbs . '/' . $oldFileRel;
          if (file_exists($oldFileAbs)) @unlink($oldFileAbs);
        }

        $safe = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($name));
        $fileName = time() . '_' . $safe;
        $filePathAbs = $targetDir . $fileName;
        if (!move_uploaded_file($_FILES['upload_dokumen']['tmp_name'], $filePathAbs)) {
          throw new Exception('Gagal mengupload file baru.');
        }
        $filePathWeb = $subDir . '/' . $fileName;
      }

      $stmt = $conn->prepare("UPDATE laporan_mingguan
                              SET tahun=?, kebun_id=?, uraian=?, link_dokumen=?, upload_dokumen=?, updated_at=NOW()
                              WHERE id=?");
      $stmt->execute([
        $_POST['tahun'],
        (int)$_POST['kebun_id'],
        trim((string)$_POST['uraian']),
        $_POST['link_dokumen'] ?: null,
        $filePathWeb,
        $id
      ]);

      echo json_encode(['success' => true, 'message' => 'Laporan berhasil diperbarui.']);
      break;
    }

    case 'delete': {
      if ($isStaf) throw new Exception('Akses ditolak.');
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) throw new Exception('ID data tidak valid.');

      $stmt = $conn->prepare('SELECT upload_dokumen FROM laporan_mingguan WHERE id = ?');
      $stmt->execute([$id]);
      $fileRel = $stmt->fetchColumn();

      if ($fileRel) {
        $fileAbs = $uploadDirAbs . '/' . $fileRel;
        if (file_exists($fileAbs)) @unlink($fileAbs);
      }

      $stmt = $conn->prepare('DELETE FROM laporan_mingguan WHERE id = ?');
      $stmt->execute([$id]);

      echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus.']);
      break;
    }

    default:
      throw new Exception('Aksi tidak dikenal.');
  }
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
