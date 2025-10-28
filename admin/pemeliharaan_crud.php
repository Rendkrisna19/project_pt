<?php
/**
 * pemeliharaan_crud.php
 * MODIFIED:
 * - Integrated PDF file upload, update, and delete logic.
 * - Added json_exit helper for consistent responses.
 * - Delete action now also removes associated PDF file.
 */
session_start();
header('Content-Type: application/json');

/* ===== HELPERS ===== */

/** JSON Response Helper */
function json_exit(bool $success, string $message, ?array $errors = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($errors) $response['errors'] = $errors;
    echo json_encode($response);
    exit;
}

function table_cols(PDO $pdo): array {
  static $cols=null;
  if ($cols===null){
    $st=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pemeliharaan'");
    $cols=array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC),'COLUMN_NAME'));
  }
  return $cols;
}
function col_exists(PDO $pdo,string $col): bool { return in_array(strtolower($col), table_cols($pdo), true); }
function pick_col(PDO $pdo,array $cands){ foreach($cands as $c) if(col_exists($pdo,$c)) return $c; return null; }

function namaById(PDO $pdo,string $table,int $id,string $nameField='nama'){
  if(!$id) return null;
  if($table==='md_kebun') $nameField='nama_kebun';
  $st=$pdo->prepare("SELECT $nameField AS n FROM $table WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$id]);
  return $st->fetchColumn() ?: null;
}
function bulanToNum(string $b): ?int {
  $list=["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
  $i=array_search($b,$list,true); return $i===false?null:$i+1;
}
function statusForDb(float $rencana,float $realisasi): string {
  $p = $rencana>0 ? ($realisasi/$rencana*100) : 0;
  if ($p>=100) return 'Selesai';
  if ($p<70)   return 'Tertunda';
  return 'Berjalan';
}

/* ===== FILE UPLOAD HELPER ===== */

$UPLOAD_DIR_BASE = '../uploads/'; // Relatif dari file CRUD ini
$UPLOAD_DIR_PUBLIC = 'uploads/';  // Path yang disimpan di DB

// Pastikan direktori ada
if (!is_dir($UPLOAD_DIR_BASE) && !mkdir($UPLOAD_DIR_BASE, 0775, true)) {
    json_exit(false, 'Gagal membuat direktori upload server.');
}

/**
 * @param ?string $oldFilePath Path relatif dari DB (e.g., 'uploads/file.pdf')
 * @return array ['path' => ?string, 'error' => ?string]
 */
function handle_pdf_upload(?string $oldFilePath): array {
    global $UPLOAD_DIR_BASE, $UPLOAD_DIR_PUBLIC;
    
    $deleteFile = !empty($_POST['_delete_pdf']);
    $file = $_FILES['file_pdf'] ?? null;
    $hasNewFile = $file && $file['error'] === UPLOAD_ERR_OK;

    // 1. Hapus file lama jika dicentang "Hapus"
    if ($deleteFile && $oldFilePath) {
        @unlink($UPLOAD_DIR_BASE . basename($oldFilePath)); // Hapus file lama
        $oldFilePath = null; // Set path jadi null untuk di-return
    }

    // 2. Jika tidak ada file baru, kembalikan path yang ada (atau null jika dihapus)
    if (!$hasNewFile) {
        return ['path' => $oldFilePath, 'error' => null];
    }

    // 3. Proses file baru
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['path' => $oldFilePath, 'error' => 'File harus berekstensi .pdf'];
    }
    if ($file['size'] > 10 * 1024 * 1024) { // 10 MB limit
        return ['path' => $oldFilePath, 'error' => 'Ukuran file maksimal 10 MB.'];
    }

    // 4. Hapus file lama (jika ada) sebelum ganti baru
    if ($oldFilePath) {
        @unlink($UPLOAD_DIR_BASE . basename($oldFilePath));
    }

    // 5. Buat nama unik dan pindahkan file baru
    $newFileName = uniqid() . '-' . preg_replace('/[^a-zA-Z0-9.\-]/', '_', basename($file['name']));
    $newServerPath = $UPLOAD_DIR_BASE . $newFileName;
    $newDbPath = $UPLOAD_DIR_PUBLIC . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $newServerPath)) {
        return ['path' => $newDbPath, 'error' => null];
    } else {
        return ['path' => $oldFilePath, 'error' => 'Gagal memindahkan file yang diunggah.'];
    }
}


/* ===== SECURITY CHECKS ===== */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { json_exit(false, 'Akses ditolak.'); }
if ($_SERVER['REQUEST_METHOD']!=='POST') { json_exit(false, 'Metode tidak valid.'); }
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token'])) { json_exit(false, 'CSRF tidak valid.'); }

// Staf tidak boleh melakukan operasi CRUD
$userRole = $_SESSION['user_role'] ?? 'staf';
if ($userRole === 'staf' && ($_POST['action'] ?? '') !== '') {
    json_exit(false, 'Anda tidak memiliki izin untuk melakukan tindakan ini.');
}

/* ===== DB & MAIN ===== */
require_once '../config/database.php';
$db=new Database(); $pdo=$db->getConnection();

$action = $_POST['action'] ?? '';

try {
  if (in_array($action, ['store','create','update'], true)) {
    $isUpdate = ($action==='update');
    $id = (int)($_POST['id'] ?? 0);

    // Kategori: sudah termasuk TK
    $kategori = trim((string)($_POST['kategori'] ?? 'TU'));
    $allowedKategori=['TU','TBM','TM','TK','BIBIT_PN','BIBIT_MN'];
    if (!in_array($kategori,$allowedKategori,true)) { json_exit(false, 'Kategori tidak valid'); }

    // Master IDs
    $jenis_id   = (int)($_POST['jenis_id'] ?? 0);
    $jenis_nama_input = trim((string)($_POST['jenis_nama'] ?? '')); // ✅ nama dari input searchable (fallback)
    $tenaga_id  = (int)($_POST['tenaga_id'] ?? 0);
    $unit_id    = (int)($_POST['unit_id'] ?? 0);
    $kebun_id   = (int)($_POST['kebun_id'] ?? 0);

    // Resolve JENIS: prioritas by ID, fallback by NAMA (case-insensitive juga dicoba)
    $jenis_nama = null;
    if ($jenis_id) {
      $jenis_nama = namaById($pdo,'md_jenis_pekerjaan',$jenis_id,'nama');
    } elseif ($jenis_nama_input !== '') {
      $st = $pdo->prepare("SELECT id, nama FROM md_jenis_pekerjaan WHERE nama = :n LIMIT 1");
      $st->execute([':n'=>$jenis_nama_input]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $jenis_id   = (int)$row['id'];
        $jenis_nama = $row['nama'];
      } else {
        // coba case-insensitive (LOWER)
        $st = $pdo->prepare("SELECT id, nama FROM md_jenis_pekerjaan WHERE LOWER(nama) = LOWER(:n) LIMIT 1");
        $st->execute([':n'=>$jenis_nama_input]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $jenis_id   = (int)$row['id'];
          $jenis_nama = $row['nama'];
        } else {
          // opsional: prefix unik
          $st = $pdo->prepare("SELECT id, nama FROM md_jenis_pekerjaan WHERE LOWER(nama) LIKE LOWER(CONCAT(:n, '%')) ORDER BY nama");
          $st->execute([':n'=>$jenis_nama_input]);
          $rows = $st->fetchAll(PDO::FETCH_ASSOC);
          if (count($rows)===1) {
            $jenis_id   = (int)$rows[0]['id'];
            $jenis_nama = $rows[0]['nama'];
          }
        }
      }
    }

    $tenaga_nama = namaById($pdo,'md_tenaga',$tenaga_id,'nama');
    $kebun_nama  = $kebun_id ? namaById($pdo,'md_kebun',$kebun_id,'nama_kebun') : null;

    $bulan     = trim((string)($_POST['bulan'] ?? ''));
    $tahun     = (int)($_POST['tahun'] ?? 0);
    $rencana   = ($_POST['rencana']===''||$_POST['rencana']===null)?0:(float)$_POST['rencana'];
    $realisasi = ($_POST['realisasi']===''||$_POST['realisasi']===null)?0:(float)$_POST['realisasi'];

    // Satuan & Keterangan (manual string)
    $satuan_rencana   = trim((string)($_POST['satuan_rencana'] ?? ''));
    $satuan_realisasi = trim((string)($_POST['satuan_realisasi'] ?? ''));
    $keterangan       = trim((string)($_POST['keterangan'] ?? ''));

    // Rayon/Bibit input (form menggunakan 'rayon' untuk keduanya di UI final)
    $rayon_in = trim((string)($_POST['rayon'] ?? '')); // bisa Rayon atau Stood/Jenis tergantung tab

    // Validasi
    $err=[];
    if(!$jenis_id || !$jenis_nama)   $err[]='Jenis pekerjaan wajib dipilih.';
    if(!$tenaga_id || !$tenaga_nama) $err[]='Tenaga wajib dipilih.';
    if(!$unit_id)                    $err[]='Unit/Devisi wajib dipilih.';
    $blnNum = bulanToNum($bulan);     if(!$blnNum) $err[]='Bulan tidak valid.';
    if($tahun<2000 || $tahun>2100)   $err[]='Tahun harus 2000–2100.';
    if($rencana<0 || $realisasi<0)   $err[]='Nilai tidak boleh negatif.';

    if($err){ json_exit(false, 'Validasi gagal', $err); }

    // [MODIFIED] Handle File Upload
    $oldFilePath = null;
    $hasFilePdf = col_exists($pdo, 'file_pdf');
    if ($isUpdate && $id && $hasFilePdf) {
        $stFile = $pdo->prepare("SELECT file_pdf FROM pemeliharaan WHERE id = :id");
        $stFile->execute([':id' => $id]);
        $oldFilePath = $stFile->fetchColumn() ?: null;
    }

    $fileResult = handle_pdf_upload($oldFilePath);
    if ($fileResult['error']) {
        json_exit(false, $fileResult['error']);
    }
    $newFilePath = $fileResult['path']; // Path baru untuk DB (bisa null)
    
    // Tanggal otomatis: pakai 'tanggal_auto' dari form bila ada, fallback 1st day
    $tanggal = isset($_POST['tanggal_auto']) && $_POST['tanggal_auto']
      ? $_POST['tanggal_auto']
      : sprintf('%04d-%02d-01', $tahun, $blnNum);

    $status   = statusForDb($rencana,$realisasi);

    // Kolom dinamis di tabel
    $hasKebunId = col_exists($pdo,'kebun_id');
    $colKebunNm = pick_col($pdo,['kebun_nama','kebun','nama_kebun','kebun_text']); // fallback nama kebun
    $colRayon   = pick_col($pdo,['rayon','rayon_nama','stood','stood_jenis','jenis_bibit','bibit']); // apa pun yg ada
    $hasSatR    = col_exists($pdo,'satuan_rencana');
    $hasSatE    = col_exists($pdo,'satuan_realisasi');
    $hasKet     = col_exists($pdo,'keterangan');

    if (!$isUpdate) {
      $cols=['kategori','jenis_pekerjaan','tenaga','unit_id','tanggal','bulan','tahun','rencana','realisasi','status','created_at','updated_at'];
      $vals=[':kategori',':jenis',':tenaga',':unit_id',':tanggal',':bulan',':tahun',':rencana',':realisasi',':status','NOW()','NOW()'];
      $bind=[':kategori'=>$kategori,':jenis'=>$jenis_nama,':tenaga'=>$tenaga_nama,':unit_id'=>$unit_id,':tanggal'=>$tanggal,':bulan'=>$bulan,':tahun'=>$tahun,':rencana'=>$rencana,':realisasi'=>$realisasi,':status'=>$status];

      if ($hasKebunId && $kebun_id){ $cols[]='kebun_id'; $vals[]=':kebun_id'; $bind[':kebun_id']=$kebun_id; }
      elseif ($colKebunNm && $kebun_nama!==null){ $cols[]=$colKebunNm; $vals[]=':kebun_nm'; $bind[':kebun_nm']=$kebun_nama; }

      if ($colRayon) { $cols[]=$colRayon; $vals[]=':rayon'; $bind[':rayon']=$rayon_in; }

      if ($hasSatR) { $cols[]='satuan_rencana'; $vals[]=':sat_r'; $bind[':sat_r']=$satuan_rencana; }
      if ($hasSatE) { $cols[]='satuan_realisasi'; $vals[]=':sat_e'; $bind[':sat_e']=$satuan_realisasi; }
      if ($hasKet)  { $cols[]='keterangan';       $vals[]=':ket';   $bind[':ket']=$keterangan; }
      
      if ($hasFilePdf) { $cols[]='file_pdf'; $vals[]=':file_pdf'; $bind[':file_pdf']=$newFilePath; } // [MODIFIED]

      $sql="INSERT INTO pemeliharaan (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$pdo->prepare($sql); $st->execute($bind);

      json_exit(true, 'Data berhasil ditambahkan.');
    }

    // UPDATE
    if ($id<=0){ json_exit(false, 'ID tidak valid untuk update'); }
    $sets=" kategori=:kategori, jenis_pekerjaan=:jenis, tenaga=:tenaga, unit_id=:unit_id,
            tanggal=:tanggal, bulan=:bulan, tahun=:tahun, rencana=:rencana, realisasi=:realisasi,
            status=:status, updated_at=NOW() ";
    $bind=[':kategori'=>$kategori,':jenis'=>$jenis_nama,':tenaga'=>$tenaga_nama,':unit_id'=>$unit_id,
           ':tanggal'=>$tanggal,':bulan'=>$bulan,':tahun'=>$tahun,':rencana'=>$rencana,':realisasi'=>$realisasi,
           ':status'=>$status,':id'=>$id];

    if ($hasKebunId){ $sets.=", kebun_id=:kid"; $bind[':kid']=$kebun_id?:null; }
    elseif ($colKebunNm){ $sets.=", $colKebunNm=:kname"; $bind[':kname']=$kebun_nama??''; }

    if ($colRayon){ $sets.=", $colRayon=:rayon"; $bind[':rayon']=$rayon_in; }

    if ($hasSatR){ $sets.=", satuan_rencana=:sat_r"; $bind[':sat_r']=$satuan_rencana; }
    if ($hasSatE){ $sets.=", satuan_realisasi=:sat_e"; $bind[':sat_e']=$satuan_realisasi; }
    if ($hasKet) { $sets.=", keterangan=:ket";       $bind[':ket']=$keterangan; }
    
    if ($hasFilePdf) { $sets.=", file_pdf=:file_pdf"; $bind[':file_pdf']=$newFilePath; } // [MODIFIED]

    $sql="UPDATE pemeliharaan SET $sets WHERE id=:id";
    $st=$pdo->prepare($sql); $st->execute($bind);

    json_exit(true, 'Data berhasil diperbarui.');
  }

  if ($action==='delete') {
    $id=(int)($_POST['id']??0);
    if($id<=0){ json_exit(false, 'ID tidak valid'); }
    
    // [MODIFIED] Hapus file PDF terkait sebelum hapus row
    $hasFilePdf = col_exists($pdo, 'file_pdf');
    if ($hasFilePdf) {
        $stFile = $pdo->prepare("SELECT file_pdf FROM pemeliharaan WHERE id = :id");
        $stFile->execute([':id' => $id]);
        $filePath = $stFile->fetchColumn() ?: null;
        if ($filePath) {
            @unlink($UPLOAD_DIR_BASE . basename($filePath));
        }
    }
    
    $pdo->prepare("DELETE FROM pemeliharaan WHERE id=:id")->execute([':id'=>$id]);
    json_exit(true, 'Data berhasil dihapus.');
  }

  json_exit(false, 'Aksi tidak dikenali.');
}
catch(PDOException $e){
  json_exit(false, 'Database error: '.$e->getMessage());
}
catch(Exception $e) {
  json_exit(false, 'Server error: '.$e->getMessage());
}