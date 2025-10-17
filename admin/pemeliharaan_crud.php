<?php
// pages/pemeliharaan_crud.php — FINAL
// - Simpan & edit: satuan_rencana, satuan_realisasi, keterangan
// - Kategori termasuk TK
// - Kebun: prefer kebun_id bila ada, fallback nama kebun
// - Rayon/Bibit: otomatis pilih kolom yang ada (rayon / stood / jenis_bibit / bibit)
// - Status dihitung otomatis, tanggal_auto didukung
// - ✅ UPDATE: dukung fallback jenis_nama bila jenis_id kosong (untuk input searchable)

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { echo json_encode(['success'=>false,'message'=>'Akses ditolak.']); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['success'=>false,'message'=>'Metode tidak valid.']); exit; }
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token'])) { echo json_encode(['success'=>false,'message'=>'CSRF tidak valid.']); exit; }

require_once '../config/database.php';
$db=new Database(); $pdo=$db->getConnection();

/* ===== helpers ===== */
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

/* ===== main ===== */
$action = $_POST['action'] ?? '';

try {
  if (in_array($action, ['store','create','update'], true)) {
    $isUpdate = ($action==='update');
    $id = (int)($_POST['id'] ?? 0);

    // Kategori: sudah termasuk TK
    $kategori = trim((string)($_POST['kategori'] ?? 'TU'));
    $allowedKategori=['TU','TBM','TM','TK','BIBIT_PN','BIBIT_MN'];
    if (!in_array($kategori,$allowedKategori,true)) { echo json_encode(['success'=>false,'message'=>'Kategori tidak valid']); exit; }

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

    $bulan    = trim((string)($_POST['bulan'] ?? ''));
    $tahun    = (int)($_POST['tahun'] ?? 0);
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
    $blnNum = bulanToNum($bulan);    if(!$blnNum) $err[]='Bulan tidak valid.';
    if($tahun<2000 || $tahun>2100)   $err[]='Tahun harus 2000–2100.';
    if($rencana<0 || $realisasi<0)   $err[]='Nilai tidak boleh negatif.';

    if($err){ echo json_encode(['success'=>false,'message'=>'Validasi gagal','errors'=>$err]); exit; }

    // Tanggal otomatis: pakai 'tanggal_auto' dari form bila ada, fallback 1st day
    $tanggal = isset($_POST['tanggal_auto']) && $_POST['tanggal_auto']
      ? $_POST['tanggal_auto']
      : sprintf('%04d-%02d-01', $tahun, $blnNum);

    $status  = statusForDb($rencana,$realisasi);

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
      if ($hasKet)  { $cols[]='keterangan';      $vals[]=':ket';   $bind[':ket']=$keterangan; }

      $sql="INSERT INTO pemeliharaan (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st=$pdo->prepare($sql); $st->execute($bind);

      echo json_encode(['success'=>true,'message'=>'Data berhasil ditambahkan.']); exit;
    }

    // UPDATE
    if ($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
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

    $sql="UPDATE pemeliharaan SET $sets WHERE id=:id";
    $st=$pdo->prepare($sql); $st->execute($bind);

    echo json_encode(['success'=>true,'message'=>'Data berhasil diperbarui.']); exit;
  }

  if ($action==='delete') {
    $id=(int)($_POST['id']??0);
    if($id<=0){ echo json_encode(['success'=>false,'message'=>'ID tidak valid']); exit; }
    $pdo->prepare("DELETE FROM pemeliharaan WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['success'=>true,'message'=>'Data berhasil dihapus.']); exit;
  }

  echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
}
catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
