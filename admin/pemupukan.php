<?php
// pemupukan.php — MOD: UI Grid TM Style, Auto Filter, Default Month, No Reload

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

function qstr($v){ return trim((string)$v); }
function qintOrEmpty($v){ return ($v===''||$v===null) ? '' : (int)$v; }

try {
  $db   = new Database();
  $conn = $db->getConnection();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // --- Cache Helper ---
  $cacheCols = [];
  $columnExists = function(PDO $c, $table, $col) use (&$cacheCols){
    $k="$table";
    if (!isset($cacheCols[$k])) {
      $st = $c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t");
      $st->execute([':t'=>$table]);
      $cacheCols[$k] = array_flip(array_map('strtolower', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME')));
    }
    return isset($cacheCols[$k][strtolower($col)]);
  };

  // --- AJAX Options Handler ---
  if (($_GET['ajax'] ?? '') === 'options') {
      header('Content-Type: application/json; charset=utf-8');
      $type = qstr($_GET['type'] ?? '');
      $unit_id = (isset($_GET['unit_id']) && $_GET['unit_id']!=='') ? (int)$_GET['unit_id'] : null;

      if ($type === 'blok') {
        $data = [];
        if ($unit_id) {
          try {
            $st = $conn->prepare("SELECT kode AS blok FROM md_blok WHERE unit_id=:u AND kode<>'' ORDER BY kode");
            $st->execute([':u'=>$unit_id]);
            $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'blok');
          } catch(Throwable $e){ error_log($e->getMessage()); }
        }
        echo json_encode(['success'=>true,'data'=>$data]); exit;
      }
      if ($type === 'jenis') {
         $data = [];
         try {
           $st = $conn->query("SELECT nama FROM md_pupuk WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama");
           $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'nama');
         } catch(Throwable $e) { error_log($e->getMessage()); }
         echo json_encode(['success'=>true,'data'=>$data]); exit;
       }
      echo json_encode(['success'=>false,'message'=>'Tipe options tidak dikenali']); exit;
  }

  // --- Initial Params ---
  $tab = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

  // [MOD] Default Bulan & Tahun
  $f_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
  
  // Jika ada parameter bulan di URL gunakan, jika tidak gunakan bulan sekarang
  $f_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('n'); 

  // Filters Lain
  $f_unit_id    = qintOrEmpty($_GET['unit_id'] ?? '');
  $f_kebun_id   = qintOrEmpty($_GET['kebun_id'] ?? '');
  $f_jenis      = qstr($_GET['jenis_pupuk'] ?? '');
  $f_rayon_id      = qintOrEmpty($_GET['rayon_id'] ?? '');
  $f_apl_id        = qintOrEmpty($_GET['apl_id'] ?? '');
  $f_keterangan_id = qintOrEmpty($_GET['keterangan_id'] ?? '');
  $f_gudang_id     = qintOrEmpty($_GET['gudang_asal_id'] ?? '');
  $f_tanggal       = qstr($_GET['tanggal'] ?? '');

  // --- Fetch Masters ---
  $units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
  $kebuns = $conn->query("SELECT id, kode, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
  try { $pupuks = $conn->query("SELECT nama FROM md_pupuk WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) { $pupuks = []; }
  try { $tahunTanamList = $conn->query("SELECT id, tahun, COALESCE(keterangan,'') AS ket FROM md_tahun_tanam ORDER BY tahun DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $tahunTanamList = []; }
  try { $rayons = $conn->query("SELECT id, nama FROM md_rayon ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $rayons = []; }
  try { $apls = $conn->query("SELECT id, nama FROM md_apl ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $apls = []; }
  try { $keterangans= $conn->query("SELECT id, keterangan FROM md_keterangan ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $keterangans = []; }
  try { $gudangs = $conn->query("SELECT id, nama FROM md_asal_gudang ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $gudangs = []; }

  // Cek Kolom DB
  $hasKebunMenaburId  = $columnExists($conn,'menabur_pupuk','kebun_id');
  $hasKebunMenaburKod = $columnExists($conn,'menabur_pupuk','kebun_kode');
  $hasKebunAngkutId   = $columnExists($conn,'angkutan_pupuk','kebun_id');
  $hasKebunAngkutKod  = $columnExists($conn,'angkutan_pupuk','kebun_kode');
  $hasTTIdMenabur   = $columnExists($conn,'menabur_pupuk','tahun_tanam_id');
  $hasTTValMenabur = $columnExists($conn,'menabur_pupuk','tahun_tanam');
  $aplField = null; foreach (['apl','aplikator'] as $cand) { if ($columnExists($conn,'menabur_pupuk',$cand)) { $aplField=$cand; break; } }
  $hasTahunMenabur = $columnExists($conn,'menabur_pupuk','tahun');
  $hasRayonIdM = $columnExists($conn, 'menabur_pupuk', 'rayon_id');
  $hasAplIdM = $columnExists($conn, 'menabur_pupuk', 'apl_id');
  $hasKetIdM = $columnExists($conn, 'menabur_pupuk', 'keterangan_id');
  $hasRayonIdA = $columnExists($conn, 'angkutan_pupuk', 'rayon_id');
  $hasGudangIdA = $columnExists($conn, 'angkutan_pupuk', 'gudang_asal_id');
  $hasKetIdA = $columnExists($conn, 'angkutan_pupuk', 'keterangan_id');

  // Pagination
  $page     = max(1, (int)($_GET['page'] ?? 1));
  $perOpts  = [10,25,50,100];
  $per_page = (int)($_GET['per_page'] ?? 10);
  if (!in_array($per_page, $perOpts, true)) $per_page = 10;
  $offset   = ($page - 1) * $per_page;
  $bulanList = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

  // --- Query Builder ---
  if ($tab === 'angkutan') {
    $title = "Data Angkutan Pupuk Kimia";
    $selectKebun = ''; $joinKebun = '';
    if($hasKebunAngkutId) {$selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.id = a.kebun_id ";}
    elseif($hasKebunAngkutKod){$selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";$joinKebun=" LEFT JOIN md_kebun kb ON kb.kode = a.kebun_kode ";}

    $selectRayon = $hasRayonIdA ? ", r.nama AS rayon_nama" : ""; $joinRayon = $hasRayonIdA ? " LEFT JOIN md_rayon r ON r.id = a.rayon_id" : "";
    $selectGudang = $hasGudangIdA ? ", g.nama AS gudang_asal_nama" : ""; $joinGudang = $hasGudangIdA ? " LEFT JOIN md_asal_gudang g ON g.id = a.gudang_asal_id" : "";
    $selectKet = $hasKetIdA ? ", k.keterangan AS keterangan_text" : ""; $joinKet = $hasKetIdA ? " LEFT JOIN md_keterangan k ON k.id = a.keterangan_id" : "";

    $where = " WHERE 1=1"; $p = [];
    if($f_unit_id!==''){$where.=" AND a.unit_tujuan_id = :uid";$p[':uid']=(int)$f_unit_id;}
    if($f_kebun_id!==''){ if($hasKebunAngkutId){$where.=" AND a.kebun_id = :kid";$p[':kid']=(int)$f_kebun_id;} elseif($hasKebunAngkutKod){$kode='';foreach($kebuns as $kbn)if($kbn['id']==$f_kebun_id)$kode=$kbn['kode'];if($kode){$where.=" AND a.kebun_kode = :kkod";$p[':kkod']=$kode;}} }
    // [MODIFIKASI 2] Logika Filter Tanggal (Prioritas) vs Bulan/Tahun
    if($f_tanggal !== ''){
        $where .= " AND a.tanggal = :tgl"; 
        $p[':tgl'] = $f_tanggal;
    } else {
        // Hanya filter Tahun & Bulan jika Tanggal Spesifik KOSONG
        if($f_tahun!==''){$where.=" AND YEAR(a.tanggal) = :thn";$p[':thn']=$f_tahun;}
        if($f_bulan!==''&&ctype_digit((string)$f_bulan)){$where.=" AND MONTH(a.tanggal) = :bln";$p[':bln']=(int)$f_bulan;}
    }
    if($f_jenis!==''){$where.=" AND a.jenis_pupuk = :jp";$p[':jp']=$f_jenis;}
    if($f_rayon_id !== '' && $hasRayonIdA) { $where .= " AND a.rayon_id = :rid"; $p[':rid'] = $f_rayon_id; }
    if($f_gudang_id !== '' && $hasGudangIdA) { $where .= " AND a.gudang_asal_id = :gid"; $p[':gid'] = $f_gudang_id; }
    if($f_keterangan_id !== '' && $hasKetIdA) { $where .= " AND a.keterangan_id = :kid"; $p[':kid'] = $f_keterangan_id; }

    $fullJoins = $joinKebun . $joinRayon . $joinGudang . $joinKet;
    $stc=$conn->prepare("SELECT COUNT(*) FROM angkutan_pupuk a $fullJoins $where");
    foreach($p as $k=>$v)$stc->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stc->execute();
    $total_rows=(int)$stc->fetchColumn(); $total_pages=max(1,(int)ceil($total_rows/$per_page));

    $sql="SELECT a.*, u.nama_unit AS unit_tujuan_nama $selectKebun $selectRayon $selectGudang $selectKet
          FROM angkutan_pupuk a
          LEFT JOIN units u ON u.id = a.unit_tujuan_id
          $fullJoins $where
          ORDER BY a.tanggal DESC, a.id DESC LIMIT :limit OFFSET :offset";
    $st=$conn->prepare($sql);
    foreach($p as $k=>$v)$st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue(':limit',$per_page,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT);
    $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

    $stt=$conn->prepare("SELECT COALESCE(SUM(a.jumlah),0) AS tot_kg FROM angkutan_pupuk a $fullJoins $where");
    foreach($p as $k=>$v)$stt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stt->execute(); $tot_all=$stt->fetch(PDO::FETCH_ASSOC); $tot_all_kg=(float)($tot_all['tot_kg']??0);

  } else { // Menabur
    $title = "Data Penaburan Pupuk Kimia";
    $selectKebun='';$joinKebun='';
    if($hasKebunMenaburId){$selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";$joinKebun=" LEFT JOIN md_kebun kb ON kb.id = m.kebun_id ";}
    elseif($hasKebunMenaburKod){$selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";$joinKebun=" LEFT JOIN md_kebun kb ON kb.kode = m.kebun_kode ";}

    $joinTT='';$selectTT='';$selectTTRaw='';$joinBlok='';
    if($hasTTIdMenabur){$joinTT=" LEFT JOIN md_tahun_tanam tt ON tt.id = m.tahun_tanam_id ";$selectTT=", tt.tahun AS t_tanam";$selectTTRaw=", m.tahun_tanam_id";}
    elseif($hasTTValMenabur){$selectTT=", m.tahun_tanam AS t_tanam";$selectTTRaw=", m.tahun_tanam AS tahun_tanam_val";}

    $selectAplOld=$aplField?", m.`$aplField` AS apl_text":", NULL AS apl_text";
    $selectTahun=$hasTahunMenabur?", m.tahun AS tahun_input":"";

    $selectRayon = $hasRayonIdM ? ", r.nama AS rayon_nama" : ""; $joinRayon = $hasRayonIdM ? " LEFT JOIN md_rayon r ON r.id = m.rayon_id" : "";
    $selectAplNew = $hasAplIdM ? ", apl.nama AS apl_nama" : ""; $joinApl = $hasAplIdM ? " LEFT JOIN md_apl apl ON apl.id = m.apl_id" : "";
    $selectKet = $hasKetIdM ? ", k.keterangan AS keterangan_text" : ""; $joinKet = $hasKetIdM ? " LEFT JOIN md_keterangan k ON k.id = m.keterangan_id" : "";

    $where=" WHERE 1=1";$p=[];
    if($f_unit_id!==''){$where.=" AND m.unit_id = :uid";$p[':uid']=(int)$f_unit_id;}
    if($f_kebun_id!==''){ if($hasKebunMenaburId){$where.=" AND m.kebun_id = :kid";$p[':kid']=(int)$f_kebun_id;} elseif($hasKebunMenaburKod){$kode='';foreach($kebuns as $kbn)if($kbn['id']==$f_kebun_id)$kode=$kbn['kode'];if($kode){$where.=" AND m.kebun_kode = :kkod";$p[':kkod']=$kode;}} }
    // [MODIFIKASI 3] Logika Filter Tanggal (Prioritas) vs Bulan/Tahun (Alias 'm')
    if($f_tanggal !== ''){
        $where .= " AND m.tanggal = :tgl"; 
        $p[':tgl'] = $f_tanggal;
    } else {
        if($f_tahun!==''){$where.=" AND YEAR(m.tanggal) = :thn";$p[':thn']=$f_tahun;}
        if($f_bulan!==''&&ctype_digit((string)$f_bulan)){$where.=" AND MONTH(m.tanggal) = :bln";$p[':bln']=(int)$f_bulan;}
    }
    if($f_jenis!==''){$where.=" AND m.jenis_pupuk = :jp";$p[':jp']=$f_jenis;}
    if($f_rayon_id !== '' && $hasRayonIdM) { $where .= " AND m.rayon_id = :rid"; $p[':rid'] = $f_rayon_id; }
    if($f_apl_id !== '' && $hasAplIdM) { $where .= " AND m.apl_id = :aid"; $p[':aid'] = $f_apl_id; }
    if($f_keterangan_id !== '' && $hasKetIdM) { $where .= " AND m.keterangan_id = :kid"; $p[':kid'] = $f_keterangan_id; }

    $fullJoins = $joinKebun . $joinTT . $joinBlok . $joinRayon . $joinApl . $joinKet;
    $stc=$conn->prepare("SELECT COUNT(*) FROM menabur_pupuk m $fullJoins $where");
    foreach($p as $k=>$v)$stc->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stc->execute(); $total_rows=(int)$stc->fetchColumn(); $total_pages=max(1,(int)ceil($total_rows/$per_page));

    $sql="SELECT m.*, u.nama_unit AS unit_nama
                  $selectKebun $selectTT $selectTTRaw $selectAplOld $selectTahun
                  $selectRayon $selectAplNew $selectKet
          FROM menabur_pupuk m
          LEFT JOIN units u ON u.id=m.unit_id
          $fullJoins $where
          ORDER BY m.tanggal DESC, m.id DESC LIMIT :limit OFFSET :offset";

    $st=$conn->prepare($sql);
    foreach($p as $k=>$v)$st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue(':limit',$per_page,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT);
    $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

    $sql_tot="SELECT COALESCE(SUM(m.jumlah),0) AS tot_kg, COALESCE(SUM(m.luas),0) AS tot_luas, COALESCE(SUM(m.invt_pokok),0) AS tot_invt FROM menabur_pupuk m $fullJoins $where";
    $stt=$conn->prepare($sql_tot);
    foreach($p as $k=>$v)$stt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stt->execute(); $tot_all=$stt->fetch(PDO::FETCH_ASSOC);
    $tot_all_kg=(float)($tot_all['tot_kg']??0); $tot_all_luas=(float)($tot_all['tot_luas']??0); $tot_all_invt=(int)($tot_all['tot_invt']??0);
  }
} catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }

// Helper QS untuk Export (Menyertakan semua parameter aktif)
// Helper QS untuk Export (Menyertakan semua parameter aktif)
function qs_current($extra = []) {
    // [MODIFIKASI 4] Tambahkan global $f_tanggal dan masukkan ke array
    global $tab, $f_unit_id, $f_kebun_id, $f_bulan, $f_tahun, $f_jenis, $f_rayon_id, $f_apl_id, $f_keterangan_id, $f_gudang_id, $f_tanggal;
    $q = [
        'tab'=>$tab, 'unit_id'=>$f_unit_id, 'kebun_id'=>$f_kebun_id,
        'bulan'=>$f_bulan, 'tahun'=>$f_tahun, 'jenis_pupuk'=>$f_jenis,
        'rayon_id'=>$f_rayon_id, 'apl_id'=>$f_apl_id, 'keterangan_id'=>$f_keterangan_id, 
        'gudang_asal_id'=>$f_gudang_id,
        'tanggal' => $f_tanggal // <--- Tambahan
    ];
    return http_build_query(array_merge($q, $extra));
}

// Jika request AJAX untuk Load Table Data
if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && !isset($_GET['ajax'])) {
    // Kita return partial HTML: TBODY, Pagination, dan Totals
    ob_start();
    include 'pemupukan_table_partial.php'; // Kita akan embed logic ini di file yang sama dengan switch logic sederhana
    // TAPI karena strukturnya satu file, kita handle render di bawah.
}

$currentPage='pemupukan'; 
// Jika bukan AJAX Table Load, load header
if(!isset($_GET['fetch_table'])) {
    include_once '../layouts/header.php'; 
}
?>
<?php if(!isset($_GET['fetch_table'])): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* --- STYLES UTAMA (Grid Sticky) --- */
  .sticky-container {
    max-height: 70vh; overflow: auto;
    border: 1px solid #cbd5e1; border-radius: 0.75rem;
    background: #fff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    position: relative;
  }
  table.table-grid { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1600px; }
  table.table-grid th, table.table-grid td {
    padding: 0.65rem 0.75rem; font-size: 0.85rem; white-space: nowrap; vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;
  }
  table.table-grid th:last-child, table.table-grid td:last-child { border-right: none; }
  
  /* Sticky Header - Biru Cyan */
  table.table-grid thead th {
    position: sticky; top: 0; z-index: 10;
    background: #059fd3; color: #fff;
    font-weight: 700; font-size: 0.75rem; text-transform: uppercase; height: 50px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  table.table-grid tbody tr:hover td { background-color: #f0f9ff; }

  /* Custom Form Input (Clean) */
  .i-input,.i-select{border:1px solid #cbd5e1;border-radius:.5rem;padding:.4rem .6rem;width:100%;outline:none; font-size: 0.875rem;}
  .i-input:focus,.i-select:focus{border-color:#059fd3;box-shadow:0 0 0 2px rgba(5,159,211,.15)}
  
  /* Buttons */
  .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; border: 1px solid transparent; }
  .btn-primary { background: #059fd3; color: white; } .btn-primary:hover { background: #0386b3; }
  .btn-excel { background: #0097e8ff; color: white; } .btn-excel:hover { background: #058596ff; }
  .btn-pdf { background: #ef4444; color: white; } .btn-pdf:hover { background: #dc2626; }
  
  .tab-nav { display: inline-flex; gap: 0.5rem; background: #f1f5f9; padding: 0.3rem; border-radius: 0.5rem; margin-bottom: 1rem; }
  .tab-link { padding: 0.5rem 1.25rem; border-radius: 0.3rem; font-weight: 600; font-size: 0.875rem; transition: all 0.2s; text-decoration: none; color: #64748b; }
  .tab-link.active { background: #fff; color: #059fd3; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
  
  /* Footer Total Sticky */
  .summary-totals {
    background: #f0f9ff; color: #0c4a6e; padding: 0.75rem 1rem;
    font-size: 0.875rem; font-weight: 700; text-align: right;
    display: flex; justify-content: flex-end; gap: 2rem;
    border-top: 2px solid #bae6fd;
  }
</style>

<div class="space-y-6">
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Pemupukan Kimia</h1>
        <p class="text-gray-500 text-sm mt-1">Kelola data menabur dan angkutan pupuk</p>
    </div>
    <div class="flex gap-2">
        <a id="btn-excel" href="cetak/pemupukan_excel.php?<?= qs_current() ?>" class="btn btn-excel no-underline">
            <i class="ti ti-file-spreadsheet"></i> Excel
        </a>
        <a id="btn-pdf" href="cetak/pemupukan_pdf.php?<?= qs_current() ?>" target="_blank" class="btn btn-pdf no-underline">
            <i class="ti ti-file-type-pdf"></i> PDF
        </a>
        
        <button id="btn-add" class="btn btn-primary"><i class="ti ti-plus"></i> Tambah Data</button>
    </div>
  </div>

  <div class="tab-nav">
    <a href="?tab=menabur" class="tab-link <?= $tab==='menabur'?'active':'' ?>">Menabur Pupuk</a>
    <a href="?tab=angkutan" class="tab-link <?= $tab==='angkutan'?'active':'' ?>">Angkutan Pupuk</a>
  </div>

  <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-9 gap-3 items-end" id="filter-container">
    <input type="hidden" id="f_tab" value="<?= htmlspecialchars($tab) ?>">
    
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
        <select id="f_tahun" class="i-select filter-input">
            <option value="">Semua Tahun</option>
            <?php for($y=2020; $y<=date('Y')+1; $y++): ?>
                <option value="<?= $y ?>" <?= (int)$f_tahun===$y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
        <select id="f_bulan" class="i-select filter-input">
            <option value="">— Semua Bulan —</option>
            <?php foreach($bulanList as $num=>$name): ?>
                <option value="<?= $num ?>" <?= (int)$f_bulan===$num ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">APL</label>
        <select id="f_apl_id" class="i-select filter-input">
            <option value="">— Semua APL —</option>
            <?php foreach($apls as $a): ?>
                <option value="<?= $a['id'] ?>" <?= (int)$f_apl_id===$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['nama']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Keterangan</label>
        <select id="f_keterangan_id" class="i-select filter-input">
            <option value="">— Semua Ket —</option>
            <?php foreach($keterangans as $k): ?>
                <option value="<?= $k['id'] ?>" <?= (int)$f_keterangan_id===$k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['keterangan']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tgl Spesifik</label>
        <input type="date" id="f_tanggal" class="i-input filter-input" value="<?= htmlspecialchars($f_tanggal) ?>">
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit</label>
        <select id="f_unit_id" class="i-select filter-input">
            <option value="">— Semua Unit —</option>
            <?php foreach($units as $u): ?>
                <option value="<?= $u['id'] ?>" <?= (int)$f_unit_id===$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kebun</label>
        <select id="f_kebun_id" class="i-select filter-input">
            <option value="">— Semua Kebun —</option>
            <?php foreach($kebuns as $k): ?>
                <option value="<?= $k['id'] ?>" <?= (int)$f_kebun_id===$k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Jenis Pupuk</label>
        <select id="f_jenis" class="i-select filter-input">
            <option value="">— Semua Jenis —</option>
            <?php foreach($pupuks as $jp): ?>
                <option value="<?= htmlspecialchars($jp) ?>" <?= $f_jenis===$jp ? 'selected' : '' ?>><?= htmlspecialchars($jp) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-1">
       </div>
    
    <div class="md:col-span-6 flex justify-start items-center pt-2">
       <div class="flex items-center gap-2">
           <label class="text-xs font-bold text-gray-500 uppercase">Baris:</label>
           <select id="f_per_page" class="i-select py-1 w-auto filter-input">
               <?php foreach($perOpts as $opt): ?>
                   <option value="<?= $opt ?>" <?= $per_page==$opt?'selected':'' ?>><?= $opt ?></option>
               <?php endforeach; ?>
           </select>
       </div>
    </div>
  </div>

  <div id="table-wrapper">
<?php endif; // End if fetch_table ?>

    <div class="sticky-container">
        <table class="table-grid">
            <thead>
                <tr>
                    <?php if ($tab==='angkutan'): ?>
                        <th style="min-width:140px">Kebun</th>
                        <th style="min-width:100px">Rayon</th>
                        <th style="min-width:120px">Gudang Asal</th>
                        <th style="min-width:120px">Unit Tujuan</th>
                        <th style="text-align:center; min-width:100px">Tanggal</th>
                        <th style="min-width:120px">Jenis Pupuk</th>
                        <th style="text-align:right; min-width:100px">Jumlah (Kg)</th>
                        <th style="min-width:100px">No SPB</th>
                        <th style="min-width:150px">Keterangan</th>
                        <th style="text-align:center; min-width:80px">Aksi</th>
                    <?php else: ?>
                        <th style="min-width:70px">Tahun</th>
                        <th style="min-width:140px">Kebun</th>
                        <th style="text-align:center; min-width:100px">Tanggal</th>
                        <th style="text-align:center; min-width:100px">Periode</th>
                        <th style="min-width:120px">Unit/Defisi</th>
                        <th style="text-align:center; min-width:80px">T.Tanam</th>
                        <th style="text-align:center; min-width:80px">Blok</th>
                        <th style="min-width:100px">Rayon</th>
                        <th style="text-align:right; min-width:90px">Luas (Ha)</th>
                        <th style="text-align:right; min-width:80px">Inv.Pkk</th>
                        <th style="min-width:120px">Jenis Pupuk</th>
                        <th style="min-width:80px">APL</th>
                        <th style="text-align:right; min-width:80px">Dosis</th>
                        <th style="min-width:100px">No AU-58</th>
                        <th style="min-width:150px">Keterangan</th>
                        <th style="text-align:right; min-width:100px">Jumlah (Kg)</th>
                        <th style="text-align:center; min-width:80px">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= $tab==='angkutan'?10:17 ?>" class="text-center py-8 italic text-gray-500">Data tidak ditemukan.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                    <?php if ($tab==='angkutan'): ?>
                        <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars($r['rayon_nama'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['gudang_asal_nama'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['tanggal']) ?></td>
                        <td><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                        <td class="text-right font-mono font-bold text-blue-600"><?= number_format((float)$r['jumlah'], 2) ?></td>
                        <td><?= htmlspecialchars($r['no_spb'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['keterangan_text'] ?? '') ?></td>
                        <td class="text-center">
                            <div class="flex justify-center gap-1">
                                <button class="btn-icon text-cyan-600 hover:text-cyan-800 btn-edit" data-tab="angkutan" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>' <?= $isStaf?'disabled':'' ?>><i class="ti ti-pencil"></i></button>
                                <button class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-tab="angkutan" data-id="<?= (int)$r['id'] ?>" <?= $isStaf?'disabled':'' ?>><i class="ti ti-trash"></i></button>
                            </div>
                        </td>
                    <?php else: 
                        $ts = strtotime($r['tanggal']);
                        $thn = $r['tahun_input'] ?? ($ts ? date('Y',$ts) : '-');
                        $per = $ts ? date('M Y', $ts) : '-';
                    ?>
                        <td><?= htmlspecialchars($thn) ?></td>
                        <td><?= htmlspecialchars($r['kebun_nama'] ?? ($r['kebun_kode'] ?? '-')) ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['tanggal']) ?></td>
                        <td class="text-center"><?= $per ?></td>
                        <td><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['t_tanam'] ?? '-') ?></td>
                        <td class="text-center"><?= htmlspecialchars($r['blok'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['rayon_nama'] ?? '-') ?></td>
                        <td class="text-right font-mono"><?= number_format((float)$r['luas'],2) ?></td>
                        <td class="text-right font-mono"><?= (int)$r['invt_pokok'] ?></td>
                        <td><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                        <td><?= htmlspecialchars($r['apl_nama'] ?? '-') ?></td>
                        <td class="text-right font-mono"><?= number_format((float)$r['dosis'],2) ?></td>
                        <td><?= htmlspecialchars($r['no_au_58'] ?? $r['no_au58'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['keterangan_text'] ?? '') ?></td>
                        <td class="text-right font-mono font-bold text-blue-600"><?= number_format((float)$r['jumlah'],2) ?></td>
                        <td class="text-center">
                            <div class="flex justify-center gap-1">
                                <button class="btn-icon text-cyan-600 hover:text-cyan-800 btn-edit" data-tab="menabur" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>' <?= $isStaf?'disabled':'' ?>><i class="ti ti-pencil"></i></button>
                                <button class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-tab="menabur" data-id="<?= (int)$r['id'] ?>" <?= $isStaf?'disabled':'' ?>><i class="ti ti-trash"></i></button>
                            </div>
                        </td>
                    <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_rows > 0): ?>
    <div class="summary-totals">
        <span>TOTAL JUMLAH: <?= number_format($tot_all_kg, 2) ?> Kg</span>
        <?php if($tab!=='angkutan'): ?>
            <span>TOTAL LUAS: <?= number_format($tot_all_luas, 2) ?> Ha</span>
            <span>TOTAL INVT: <?= number_format($tot_all_invt, 0) ?> Pkk</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <input type="hidden" id="current_page" value="<?= $page ?>">
    <input type="hidden" id="total_pages" value="<?= $total_pages ?>">

    <div class="flex justify-between items-center mt-2">
        <div class="text-sm text-gray-600">
            Menampilkan <strong><?= ($total_rows>0)?$offset+1:0 ?></strong>–<strong><?= min($offset+$per_page, $total_rows) ?></strong> dari <strong><?= number_format($total_rows) ?></strong> data
        </div>
        <div class="flex gap-1">
            <button onclick="changePage(<?= $page-1 ?>)" class="px-3 py-1 border rounded bg-white hover:bg-gray-50 text-sm <?= $page<=1?'opacity-50 cursor-not-allowed':'' ?>" <?= $page<=1?'disabled':'' ?>>Prev</button>
            <button onclick="changePage(<?= $page+1 ?>)" class="px-3 py-1 border rounded bg-white hover:bg-gray-50 text-sm <?= $page>=$total_pages?'opacity-50 cursor-not-allowed':'' ?>" <?= $page>=$total_pages?'disabled':'' ?>>Next</button>
        </div>
    </div>

<?php if(!isset($_GET['fetch_table'])): ?>
  </div> </div>

<div id="crud-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 transition-opacity">
  <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform scale-100 transition-transform">
    <div class="flex items-center justify-between mb-6 border-b pb-4">
      <h3 id="modal-title" class="text-xl font-bold text-gray-900">Tambah Data</h3>
      <button id="btn-close" class="text-2xl text-gray-400 hover:text-gray-600 transition">&times;</button>
    </div>
    
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">
      <input type="hidden" name="tab" id="form-tab" value="<?= htmlspecialchars($tab) ?>">

      <div id="group-angkutan" class="<?= $tab==='angkutan'?'':'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Kebun</label>
                <select id="kebun_id_angkutan" name="kebun_id" class="i-select"><option value="">— Pilih —</option><?php foreach ($kebuns as $k): ?><option value="<?= (int)$k['id'] ?>" data-kode="<?= $k['kode'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select>
                <input type="hidden" name="kebun_kode" id="kebun_kode_angkutan">
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Rayon</label>
                <select id="rayon_id_angkutan" name="rayon_id" class="i-select"><option value="">— Pilih —</option><?php foreach ($rayons as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Unit Tujuan *</label>
                <select name="unit_tujuan_id" id="unit_tujuan_id" class="i-select" required><option value="">— Pilih —</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Gudang Asal *</label>
                <select name="gudang_asal_id" id="gudang_asal_id" class="i-select" required><option value="">— Pilih —</option><?php foreach ($gudangs as $g): ?><option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['nama']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Tanggal *</label><input type="date" name="tanggal" id="tanggal_angkutan" class="i-input" required></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Jenis Pupuk *</label>
                <select name="jenis_pupuk" id="jenis_pupuk_angkutan" class="i-select" required><option value="">— Pilih —</option><?php foreach ($pupuks as $p): ?><option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Jumlah (Kg)</label><input type="number" step="0.01" name="jumlah" id="jumlah_angkutan" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">No SPB</label><input type="text" name="no_spb" id="no_spb_angkutan" class="i-input"></div>
            <div class="md:col-span-2"><label class="block text-xs font-bold text-gray-600 mb-1">Keterangan</label>
                <select name="keterangan_id" id="keterangan_id_angkutan" class="i-select"><option value="">— Opsional —</option><?php foreach ($keterangans as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['keterangan']) ?></option><?php endforeach; ?></select>
            </div>
        </div>
      </div>

      <div id="group-menabur" class="<?= $tab==='menabur'?'':'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Unit *</label>
                <select name="unit_id" id="unit_id" class="i-select" required><option value="">— Pilih —</option><?php foreach ($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Kebun</label>
                <select id="kebun_id_menabur" name="kebun_id" class="i-select"><option value="">— Pilih —</option><?php foreach ($kebuns as $k): ?><option value="<?= (int)$k['id'] ?>" data-kode="<?= $k['kode'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Blok *</label>
                <select name="blok" id="blok" class="i-select" required><option value="">— Pilih Unit Dulu —</option></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Tanggal *</label><input type="date" name="tanggal" id="tanggal_menabur" class="i-input" required></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Tahun</label><input type="number" name="tahun" id="tahun" class="i-input" placeholder="YYYY"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Jenis Pupuk *</label>
                <select name="jenis_pupuk" id="jenis_pupuk_menabur" class="i-select" required><option value="">— Pilih —</option><?php foreach ($pupuks as $p): ?><option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Tahun Tanam</label>
                <select id="tahun_tanam_select" class="i-select"><option value="">— Pilih —</option><?php foreach ($tahunTanamList as $tt): ?><option value="<?= htmlspecialchars($tt['tahun']) ?>" data-id="<?= $tt['id'] ?>"><?= $tt['tahun'] ?></option><?php endforeach; ?></select>
                <input type="hidden" name="tahun_tanam_id" id="tahun_tanam_id">
                <input type="hidden" name="tahun_tanam" id="tahun_tanam_val">
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">APL</label>
                <select name="apl_id" id="apl_id" class="i-select"><option value="">— Pilih —</option><?php foreach ($apls as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nama']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Rayon</label>
                <select name="rayon_id" id="rayon_id_menabur" class="i-select"><option value="">— Pilih —</option><?php foreach ($rayons as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?></option><?php endforeach; ?></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Dosis</label><input type="number" step="0.01" name="dosis" id="dosis" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Jumlah (Kg)</label><input type="number" step="0.01" name="jumlah" id="jumlah_menabur" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Luas (Ha)</label><input type="number" step="0.01" name="luas" id="luas" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">Invt. Pokok</label><input type="number" step="1" name="invt_pokok" id="invt_pokok" class="i-input"></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-1">No AU-58</label><input type="text" name="no_au_58" id="no_au_58_menabur" class="i-input"></div>
            <div class="md:col-span-3"><label class="block text-xs font-bold text-gray-600 mb-1">Keterangan</label>
                <select name="keterangan_id" id="keterangan_id_menabur" class="i-select"><option value="">— Opsional —</option><?php foreach ($keterangans as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['keterangan']) ?></option><?php endforeach; ?></select>
            </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
        <button type="button" id="btn-cancel" class="px-5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50 transition">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 transition shadow-lg shadow-cyan-500/30">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const IS_STAF = <?= $isStaf ? 'true' : 'false' ?>;
    const $ = s => document.querySelector(s);
    const $$ = s => document.querySelectorAll(s);

    // --- LIVE FILTER LOGIC (NO RELOAD) ---
    const filterInputs = $$('.filter-input');
    filterInputs.forEach(el => el.addEventListener('change', () => fetchData(1)));

    async function fetchData(page = 1) {
        // Ambil nilai filter
        const tab = $('#f_tab').value;
        const tahun = $('#f_tahun').value;
        const bulan = $('#f_bulan').value;
        const unit = $('#f_unit_id').value;
        const kebun = $('#f_kebun_id').value;
        const jenis = $('#f_jenis').value;
        const perPage = $('#f_per_page').value;
        const aplElement = $('#f_apl_id');
        const apl = aplElement ? aplElement.value : ''; 
        
        const ket = $('#f_keterangan_id').value;
        const tanggal = $('#f_tanggal').value;

        // Bangun URL
        const params = new URLSearchParams({
            fetch_table: 1, // Trigger partial load
            tab: tab,
            tahun: tahun,
            bulan: bulan,
            unit_id: unit,
            kebun_id: kebun,
            jenis_pupuk: jenis,
            per_page: perPage,
            page: page,
            apl_id: apl,
            keterangan_id: ket,
            tanggal: tanggal,
        });

        // Update URL Browser (History)
        const newUrl = `${window.location.pathname}?${params.toString()}`.replace('fetch_table=1&', '');
        window.history.pushState({}, '', newUrl);

        // Update Link Export
        $('#btn-excel').href = `cetak/pemupukan_excel.php?${params.toString()}`;
        $('#btn-pdf').href = `cetak/pemupukan_pdf.php?${params.toString()}`;

        // Fetch HTML
        try {
            const res = await fetch(`?${params.toString()}`);
            if (!res.ok) throw new Error('Network response error');
            const html = await res.text();
            
            // Ganti konten tabel
            $('#table-wrapper').innerHTML = html;
        } catch (err) {
            console.error('Fetch error:', err);
        }
    }

    // Global function agar bisa dipanggil tombol Pagination
    window.changePage = (p) => fetchData(p);


    // --- UTILS & MODAL LOGIC (SAMA SEPERTI SEBELUMNYA) ---
    async function loadJSON(url) { try{ const r=await fetch(url); return await r.json();}catch(e){return null;} }
    function fillSelect(el, list, ph='— Pilih —'){
        if(!el)return; el.innerHTML='';
        const d=document.createElement('option'); d.value=''; d.textContent=ph; el.appendChild(d);
        (list||[]).forEach(v=>{
            const op=document.createElement('option'); op.value=v; op.textContent=v; el.appendChild(op);
        });
    }
    
    // Refresh Blok Logic
    async function refreshBlok(){
        const uid = $('#unit_id')?.value || '';
        const sel = $('#blok');
        if(!sel) return;
        if(!uid){ sel.innerHTML='<option value="">— Pilih Unit Dulu —</option>'; return; }
        const j = await loadJSON(`?ajax=options&type=blok&unit_id=${uid}`);
        const list = (j?.success && Array.isArray(j.data)) ? j.data : [];
        fillSelect(sel, list, '— Pilih Blok —');
    }
    $('#unit_id')?.addEventListener('change', refreshBlok);

    function syncKebunKode(idSel, idHid){
        const sel = document.getElementById(idSel);
        const hid = document.getElementById(idHid);
        if(sel && hid) hid.value = sel.options[sel.selectedIndex]?.dataset?.kode || '';
    }
    $('#kebun_id_angkutan')?.addEventListener('change', ()=>syncKebunKode('kebun_id_angkutan','kebun_kode_angkutan'));

    const modal = $('#crud-modal'), form = $('#crud-form'), title = $('#modal-title');
    const open = ()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); }
    const close = ()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); }

    function toggleGroup(tab){
        const ga=$('#group-angkutan'), gm=$('#group-menabur');
        const isAng = (tab==='angkutan');
        ga.classList.toggle('hidden', !isAng);
        gm.classList.toggle('hidden', isAng);
        ga.querySelectorAll('input,select').forEach(e=>e.disabled = !isAng);
        gm.querySelectorAll('input,select').forEach(e=>e.disabled = isAng);
        ga.querySelectorAll('[required]').forEach(e=>e.required = isAng);
        gm.querySelectorAll('[required]').forEach(e=>e.required = !isAng);
    }

    const ttSel = $('#tahun_tanam_select');
    if(ttSel){
        ttSel.addEventListener('change', ()=>{
            $('#tahun_tanam_id').value = ttSel.options[ttSel.selectedIndex]?.dataset?.id || '';
            $('#tahun_tanam_val').value = ttSel.value || '';
        });
    }

    if($('#btn-add')){
        $('#btn-add').addEventListener('click', ()=>{
            form.reset();
            const tab = '<?= htmlspecialchars($tab) ?>';
            $('#form-action').value='store'; $('#form-id').value=''; $('#form-tab').value=tab;
            title.textContent = 'Tambah Data';
            toggleGroup(tab);
            if(tab==='menabur'){ refreshBlok(); if(ttSel)ttSel.dispatchEvent(new Event('change')); }
            open();
        });
    }

    $('#btn-close')?.addEventListener('click', close);
    $('#btn-cancel')?.addEventListener('click', close);

    // EVENT DELEGATION UNTUK TOMBOL DI DALAM TABEL (Karena tabel reload via AJAX)
    document.querySelector('#table-wrapper').addEventListener('click', async e => {
        const btnE = e.target.closest('.btn-edit');
        const btnD = e.target.closest('.btn-delete');

        if(btnE && !IS_STAF){
            const row = JSON.parse(btnE.dataset.json);
            const tab = btnE.dataset.tab;
            form.reset();
            $('#form-action').value='update'; $('#form-id').value=row.id; $('#form-tab').value=tab;
            title.textContent='Edit Data';
            toggleGroup(tab);

            if(tab==='angkutan'){
                $('#kebun_id_angkutan').value = row.kebun_id||''; syncKebunKode('kebun_id_angkutan','kebun_kode_angkutan');
                $('#rayon_id_angkutan').value = row.rayon_id||'';
                $('#unit_tujuan_id').value = row.unit_tujuan_id||'';
                $('#gudang_asal_id').value = row.gudang_asal_id||'';
                $('#tanggal_angkutan').value = row.tanggal||'';
                $('#jenis_pupuk_angkutan').value = row.jenis_pupuk||'';
                $('#jumlah_angkutan').value = row.jumlah||'';
                $('#no_spb_angkutan').value = row.no_spb||'';
                $('#keterangan_id_angkutan').value = row.keterangan_id||'';
            } else {
                $('#unit_id').value = row.unit_id||'';
                await refreshBlok();
                $('#blok').value = row.blok||'';
                $('#kebun_id_menabur').value = row.kebun_id||'';
                $('#rayon_id_menabur').value = row.rayon_id||'';
                $('#apl_id').value = row.apl_id||'';
                $('#tanggal_menabur').value = row.tanggal||'';
                $('#tahun').value = row.tahun_input || (row.tanggal?row.tanggal.substring(0,4):'');
                $('#jenis_pupuk_menabur').value = row.jenis_pupuk||'';
                $('#dosis').value = row.dosis||'';
                $('#jumlah_menabur').value = row.jumlah||'';
                $('#luas').value = row.luas||'';
                $('#invt_pokok').value = row.invt_pokok||'';
                $('#no_au_58_menabur').value = row.no_au_58||row.no_au58||'';
                $('#keterangan_id_menabur').value = row.keterangan_id||'';
                
                if(ttSel){
                    const tVal = row.tahun_tanam_val || row.t_tanam || '';
                    if(row.tahun_tanam_id) {
                        const op = Array.from(ttSel.options).find(o=>o.dataset.id==row.tahun_tanam_id);
                        if(op) ttSel.value = op.value;
                    } else if(tVal) {
                        ttSel.value = tVal;
                    }
                    ttSel.dispatchEvent(new Event('change'));
                }
            }
            open();
        }

        if(btnD && !IS_STAF){
            Swal.fire({title:'Hapus?', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Ya'})
            .then(res=>{
                if(!res.isConfirmed) return;
                const fd = new FormData();
                fd.append('csrf_token','<?= $CSRF ?>'); fd.append('action','delete'); 
                fd.append('tab', btnD.dataset.tab); fd.append('id', btnD.dataset.id);
                fetch('pemupukan_crud.php',{method:'POST', body:fd})
                .then(r=>r.json()).then(j=>{
                    if(j.success) Swal.fire('Terhapus','','success').then(()=> fetchData($('#current_page').value)); // Reload current page
                    else Swal.fire('Gagal', j.message||'Error', 'error');
                });
            });
        }
    });

    form.addEventListener('submit', e=>{
        e.preventDefault();
        const fd = new FormData(form);
        fetch('pemupukan_crud.php',{method:'POST', body:fd})
        .then(r=>r.json()).then(j=>{
            if(j.success){ 
                close(); 
                Swal.fire({icon:'success', title:'Berhasil', timer:1200, showConfirmButton:false})
                .then(()=> fetchData($('#current_page').value)); // Reload current page
            }
            else Swal.fire('Gagal', j.message||'Error', 'error');
        });
    });
    
    $('#tanggal_menabur')?.addEventListener('change', e=>{
        const y = e.target.value.slice(0,4);
        if(y && $('#tahun') && !$('#tahun').value) $('#tahun').value=y;
    });
});
</script>
<?php endif; ?>