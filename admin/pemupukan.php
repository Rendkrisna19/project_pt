<?php
// pemupukan.php — MOD: Role 'staf' tidak bisa edit/hapus + tombol ikon

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

// --- MODIFIKASI: Dapatkan role user ---
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');
// ------------------------------------

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

function qstr($v){ return trim((string)$v); }
function qintOrEmpty($v){ return ($v===''||$v===null) ? '' : (int)$v; }

try {
  $db   = new Database();
  $conn = $db->getConnection();

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

  if (($_GET['ajax'] ?? '') === 'options') {
    header('Content-Type: application/json');
    $type = qstr($_GET['type'] ?? '');
    $unit_id = (isset($_GET['unit_id']) && $_GET['unit_id']!=='') ? (int)$_GET['unit_id'] : null;

    if ($type === 'blok') {
      $data = [];
      if ($unit_id) {
        try {
          $st = $conn->prepare("SELECT kode AS blok FROM md_blok WHERE unit_id=:u AND kode<>'' ORDER BY kode");
          $st->execute([':u'=>$unit_id]);
          $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'blok');
        } catch(Throwable $e){}
        if (!$data) {
          $st = $conn->prepare("SELECT DISTINCT blok FROM menabur_pupuk WHERE unit_id=:u AND blok<>'' ORDER BY blok");
          $st->execute([':u'=>$unit_id]);
          $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'blok');
        }
      }
      echo json_encode(['success'=>true,'data'=>$data]); exit;
    }

    if ($type === 'jenis') {
      $data = [];
      try {
        $st = $conn->query("SELECT nama FROM md_pupuk WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama");
        $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'nama');
      } catch(Throwable $e) {
        $st = $conn->query("SELECT DISTINCT nama FROM (SELECT jenis_pupuk AS nama FROM menabur_pupuk UNION ALL SELECT jenis_pupuk AS nama FROM angkutan_pupuk) x WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama");
        $data = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'nama');
      }
      echo json_encode(['success'=>true,'data'=>$data]); exit;
    }

    echo json_encode(['success'=>false,'message'=>'Tipe options tidak dikenali']); exit;
  }

  $tab = $_GET['tab'] ?? 'menabur';
  if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

  $f_unit_id    = qintOrEmpty($_GET['unit_id'] ?? '');
  $f_kebun_id   = qintOrEmpty($_GET['kebun_id'] ?? '');
  $f_tanggal    = qstr($_GET['tanggal'] ?? '');
  $f_bulan      = qstr($_GET['bulan'] ?? '');
  $f_jenis      = qstr($_GET['jenis_pupuk'] ?? '');
  $f_rayon      = qstr($_GET['rayon'] ?? '');
  $f_keterangan = qstr($_GET['keterangan'] ?? '');

  $units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
  $kebuns = $conn->query("SELECT id, kode, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
  try {
    $pupuks = $conn->query("SELECT nama FROM md_pupuk WHERE nama IS NOT NULL AND nama<>'' ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
  } catch (Throwable $e) {
    $pupuks = $conn->query("SELECT DISTINCT jenis_pupuk AS nama FROM (SELECT jenis_pupuk FROM menabur_pupuk UNION ALL SELECT jenis_pupuk FROM angkutan_pupuk) t WHERE jenis_pupuk<>'' ORDER BY jenis_pupuk")->fetchAll(PDO::FETCH_COLUMN);
  }

  try {
    $tahunTanamList = $conn->query("SELECT id, tahun, COALESCE(keterangan,'') AS ket FROM md_tahun_tanam ORDER BY tahun DESC")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $tahunTanamList = []; }

  $hasKebunMenaburId  = $columnExists($conn,'menabur_pupuk','kebun_id');
  $hasKebunMenaburKod = $columnExists($conn,'menabur_pupuk','kebun_kode');
  $hasKebunAngkutId   = $columnExists($conn,'angkutan_pupuk','kebun_id');
  $hasKebunAngkutKod  = $columnExists($conn,'angkutan_pupuk','kebun_kode');
  $hasTTIdMenabur   = $columnExists($conn,'menabur_pupuk','tahun_tanam_id');
  $hasTTValMenabur = $columnExists($conn,'menabur_pupuk','tahun_tanam');

  $aplField = null;
  foreach (['apl','aplikator'] as $cand) { if ($columnExists($conn,'menabur_pupuk',$cand)) { $aplField=$cand; break; } }
  $hasTahunMenabur = $columnExists($conn,'menabur_pupuk','tahun');

  $kebunIdToKode = [];
  foreach ($kebuns as $kb) { $kebunIdToKode[(int)$kb['id']] = $kb['kode']; }

  $page     = max(1, (int)($_GET['page'] ?? 1));
  $perOpts  = [10,25,50,100];
  $per_page = (int)($_GET['per_page'] ?? 10);
  if (!in_array($per_page, $perOpts, true)) $per_page = 10;
  $offset   = ($page - 1) * $per_page;
  $bulanList = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

  if ($tab === 'angkutan') {
    $title = "Data Angkutan Pupuk Kimia";
    $selectKebun = ''; $joinKebun = '';
    if($hasKebunAngkutId) {$selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode"; $joinKebun=" LEFT JOIN md_kebun kb ON kb.id = a.kebun_id ";}
    elseif($hasKebunAngkutKod){$selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";$joinKebun=" LEFT JOIN md_kebun kb ON kb.kode = a.kebun_kode ";}
    $where = " WHERE 1=1"; $p = [];
    if($f_unit_id!==''){$where.=" AND a.unit_tujuan_id = :uid";$p[':uid']=(int)$f_unit_id;}
    if($f_kebun_id!==''){if($hasKebunAngkutId){$where.=" AND a.kebun_id = :kid";$p[':kid']=(int)$f_kebun_id;}elseif($hasKebunAngkutKod){$where.=" AND a.kebun_kode = :kkod";$p[':kkod']=(string)($kebunIdToKode[(int)$f_kebun_id]??'');}}
    if($f_tanggal!==''){$where.=" AND a.tanggal = :tgl";$p[':tgl']=$f_tanggal;}
    if($f_bulan!==''&&ctype_digit($f_bulan)){$where.=" AND MONTH(a.tanggal) = :bln";$p[':bln']=(int)$f_bulan;}
    if($f_jenis!==''){$where.=" AND a.jenis_pupuk = :jp";$p[':jp']=$f_jenis;}
    if($f_rayon!==''){$where.=" AND a.rayon LIKE :ry";$p[':ry']="%$f_rayon%";}
    if($f_keterangan!==''){$where.=" AND a.keterangan LIKE :ket";$p[':ket']="%$f_keterangan%";}

    $stc=$conn->prepare("SELECT COUNT(*) FROM angkutan_pupuk a $joinKebun $where");
    foreach($p as $k=>$v)$stc->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stc->execute();
    $total_rows=(int)$stc->fetchColumn(); $total_pages=max(1,(int)ceil($total_rows/$per_page));
    $sql="SELECT a.*, u.nama_unit AS unit_tujuan_nama $selectKebun FROM angkutan_pupuk a LEFT JOIN units u ON u.id = a.unit_tujuan_id $joinKebun $where ORDER BY a.tanggal DESC, a.id DESC LIMIT :limit OFFSET :offset";
    $st=$conn->prepare($sql);
    foreach($p as $k=>$v)$st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue(':limit',$per_page,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT);
    $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

    $stt=$conn->prepare("SELECT COALESCE(SUM(a.jumlah),0) AS tot_kg FROM angkutan_pupuk a $joinKebun $where");
    foreach($p as $k=>$v)$stt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stt->execute(); $tot_all=$stt->fetch(PDO::FETCH_ASSOC); $tot_all_kg=(float)($tot_all['tot_kg']??0);
    $sum_page_kg=0.0; foreach($rows as $r)$sum_page_kg+=(float)($r['jumlah']??0);
  } else {
    $title = "Data Penaburan Pupuk Kimia";
    $selectKebun='';$joinKebun='';
    if($hasKebunMenaburId){$selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";$joinKebun=" LEFT JOIN md_kebun kb ON kb.id = m.kebun_id ";}
    elseif($hasKebunMenaburKod){$selectKebun=", kb.nama_kebun AS kebun_nama, kb.kode AS kebun_kode";$joinKebun=" LEFT JOIN md_kebun kb ON kb.kode = m.kebun_kode ";}
    $joinTT='';$selectTT='';$selectTTRaw='';
    if($hasTTIdMenabur){$joinTT=" LEFT JOIN md_tahun_tanam tt ON tt.id = m.tahun_tanam_id ";$selectTT=", tt.tahun AS t_tanam";$selectTTRaw=", m.tahun_tanam_id";$joinBlok='';}
    elseif($hasTTValMenabur){$selectTT=", m.tahun_tanam AS t_tanam";$selectTTRaw=", m.tahun_tanam AS tahun_tanam";$joinBlok='';}
    else{$hasBlokMaster=$columnExists($conn,'md_blok','kode');$hasBlokUnitId=$columnExists($conn,'md_blok','unit_id');$ttanamField=null;foreach(['tahun_tanam','t_tanam','ttanam']as $cand){if($columnExists($conn,'md_blok',$cand)){$ttanamField=$cand;break;}}$joinBlok='';if($hasBlokMaster){$cond=$hasBlokUnitId?" AND mb.unit_id = m.unit_id":"";$joinBlok=" LEFT JOIN md_blok mb ON mb.kode = m.blok $cond ";if($ttanamField)$selectTT=", mb.`$ttanamField` AS t_tanam";}}
    $selectApl=$aplField?", m.`$aplField` AS apl":", NULL AS apl"; $selectTahun=$hasTahunMenabur?", m.tahun AS tahun_input":"";

    $where=" WHERE 1=1";$p=[];
    if($f_unit_id!==''){$where.=" AND m.unit_id = :uid";$p[':uid']=(int)$f_unit_id;}
    if($f_kebun_id!==''){if($hasKebunMenaburId){$where.=" AND m.kebun_id = :kid";$p[':kid']=(int)$f_kebun_id;}elseif($hasKebunMenaburKod){$where.=" AND m.kebun_kode = :kkod";$p[':kkod']=(string)($kebunIdToKode[(int)$f_kebun_id]??'');}}
    if($f_tanggal!==''){$where.=" AND m.tanggal = :tgl";$p[':tgl']=$f_tanggal;}
    if($f_bulan!==''&&ctype_digit($f_bulan)){$where.=" AND MONTH(m.tanggal) = :bln";$p[':bln']=(int)$f_bulan;}
    if($f_jenis!==''){$where.=" AND m.jenis_pupuk = :jp";$p[':jp']=$f_jenis;}
    if($f_rayon!==''){$where.=" AND m.rayon LIKE :ry";$p[':ry']="%$f_rayon%";}
    if($f_keterangan!==''){$where.=" AND m.keterangan LIKE :ket";$p[':ket']="%$f_keterangan%";}

    $stc=$conn->prepare("SELECT COUNT(*) FROM menabur_pupuk m $joinKebun $joinTT $joinBlok $where");
    foreach($p as $k=>$v)$stc->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stc->execute(); $total_rows=(int)$stc->fetchColumn(); $total_pages=max(1,(int)ceil($total_rows/$per_page));

    $sql="SELECT m.*, u.nama_unit AS unit_nama $selectKebun $selectTT $selectTTRaw $selectApl $selectTahun FROM menabur_pupuk m LEFT JOIN units u ON u.id=m.unit_id $joinKebun $joinTT $joinBlok $where ORDER BY m.tanggal DESC, m.id DESC LIMIT :limit OFFSET :offset";
    $st=$conn->prepare($sql);
    foreach($p as $k=>$v)$st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $st->bindValue(':limit',$per_page,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT);
    $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

    $sql_tot="SELECT COALESCE(SUM(m.jumlah),0) AS tot_kg, COALESCE(SUM(m.luas),0) AS tot_luas, COALESCE(SUM(m.invt_pokok),0) AS tot_invt FROM menabur_pupuk m $joinKebun $joinTT $joinBlok $where";
    $stt=$conn->prepare($sql_tot);
    foreach($p as $k=>$v)$stt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
    $stt->execute(); $tot_all=$stt->fetch(PDO::FETCH_ASSOC);
    $tot_all_kg=(float)($tot_all['tot_kg']??0); $tot_all_luas=(float)($tot_all['tot_luas']??0); $tot_all_invt=(float)($tot_all['tot_invt']??0);
    $sum_page_kg=0.0;$sum_page_luas=0.0;$sum_page_invt=0.0;
    foreach($rows as $r){$sum_page_kg+=(float)($r['jumlah']??0);$sum_page_luas+=(float)($r['luas']??0);$sum_page_invt+=(float)($r['invt_pokok']??0);}
  }
} catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }

function qs_no_page(array $extra=[]){
  $base=['tab'=>$_GET['tab']??'','unit_id'=>$_GET['unit_id']??'','kebun_id'=>$_GET['kebun_id']??'','tanggal'=>$_GET['tanggal']??'','bulan'=>$_GET['bulan']??'','jenis_pupuk'=>$_GET['jenis_pupuk']??'','rayon'=>$_GET['rayon']??'','keterangan'=>$_GET['keterangan']??''];
  return http_build_query(array_merge($base,$extra));
}
$currentPage='pemupukan'; include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  .i-input,.i-select{border:1px solid #e5e7eb;border-radius:.6rem;padding:.5rem .75rem;width:100%;outline:none}
  .i-input:focus,.i-select:focus{border-color:#9ca3af;box-shadow:0 0 0 3px rgba(156,163,175,.15)}
  .btn{display:inline-flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;background:#fff;color:#1f2937;border-radius:.6rem;padding:.5rem 1rem}
  .btn:hover{background:#f9fafb}
  .btn-dark{background:#059669;color:#fff;border-color:#059669}
  .btn-dark:hover{background:#047857}
  .tbl-wrap{max-height:60vh;overflow-y:auto}
  thead.sticky{position:sticky;top:0;z-index:10}
  table.table-fixed{table-layout:fixed}
  /* --- MODIFIKASI: Style untuk tombol disabled --- */
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
</style>

<div class="space-y-6">
  <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">🔬 Pemupukan Kimia</h1>
  <div class="border-b border-gray-200 flex flex-wrap gap-2 md:gap-6">
    <?php
      $persist=['unit_id'=>$f_unit_id,'kebun_id'=>$f_kebun_id,'tanggal'=>$f_tanggal,'bulan'=>$f_bulan,'jenis_pupuk'=>$f_jenis,'per_page'=>$per_page,'rayon'=>$f_rayon,'keterangan'=>$f_keterangan];
      $qsMen=http_build_query(array_merge(['tab'=>'menabur'],$persist));
      $qsAng=http_build_query(array_merge(['tab'=>'angkutan'],$persist));
    ?>
    <a href="?<?=$qsMen?>" class="px-3 py-2 border-b-2 text-sm font-medium <?=$tab==='menabur'?'border-green-600 text-gray-900':'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'?>">Menabur Pupuk</a>
    <a href="?<?=$qsAng?>" class="px-3 py-2 border-b-2 text-sm font-medium <?=$tab==='angkutan'?'border-green-600 text-gray-900':'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'?>">Angkutan Pupuk</a>
  </div>
  <div class="bg-white p-6 rounded-xl shadow-md">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
      <h2 class="text-xl font-bold text-gray-900"><?=htmlspecialchars($title)?></h2>
      <div class="flex gap-2">
        <?php $qsExport=qs_no_page();?>
        <a href="cetak/pemupukan_excel.php?<?=$qsExport?>" class="btn"><i class="ti ti-file-spreadsheet text-emerald-600 text-xl"></i><span>Export Excel</span></a>
        <a href="cetak/pemupukan_pdf.php?<?=$qsExport?>" target="_blank" rel="noopener" class="btn"><i class="ti ti-file-type-pdf text-red-600 text-xl"></i><span>Cetak PDF</span></a>
        <button id="btn-add" class="btn btn-dark"><i class="ti ti-plus"></i><span>Tambah Data</span></button>
      </div>
    </div>
    <form class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-4" method="GET" id="filter-form">
      <input type="hidden" name="tab" value="<?=htmlspecialchars($tab)?>">
      <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-700 mb-1">Unit</label><select name="unit_id" class="i-select"><option value="">— Semua Unit —</option><?php foreach($units as $u):?><option value="<?=(int)$u['id']?>" <?=($f_unit_id!==''&&(int)$f_unit_id===(int)$u['id'])?'selected':''?>><?=htmlspecialchars($u['nama_unit'])?></option><?php endforeach;?></select></div>
      <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-700 mb-1">Kebun</label><select name="kebun_id" class="i-select"><option value="">— Semua Kebun —</option><?php foreach($kebuns as $k):?><option value="<?=(int)$k['id']?>" <?=($f_kebun_id!==''&&(int)$f_kebun_id===(int)$k['id'])?'selected':''?>><?=htmlspecialchars($k['nama_kebun'])?> (<?=htmlspecialchars($k['kode'])?>)</option><?php endforeach;?></select></div>
      <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal</label><input type="date" name="tanggal" value="<?=htmlspecialchars($f_tanggal)?>" class="i-input"></div>
      <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-700 mb-1">Bulan (dari Tanggal)</label><select name="bulan" class="i-select"><option value="">— Semua Bulan —</option><?php foreach($bulanList as $num=>$name):?><option value="<?=$num?>" <?=($f_bulan!==''&&(int)$f_bulan===$num)?'selected':''?>><?=$name?></option><?php endforeach;?></select></div>
      <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-700 mb-1">Jenis Pupuk</label><select name="jenis_pupuk" class="i-select"><option value="">— Semua Jenis —</option><?php foreach($pupuks as $jp):?><option value="<?=htmlspecialchars($jp)?>" <?=($f_jenis===$jp)?'selected':''?>><?=htmlspecialchars($jp)?></option><?php endforeach;?></select></div>
      <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-700 mb-1">Rayon</label><input type="text" name="rayon" value="<?=htmlspecialchars($f_rayon)?>" class="i-input" placeholder="Cari Rayon"></div>
      <div class="md:col-span-3"><label class="block text-xs font-semibold text-gray-700 mb-1">Keterangan</label><input type="text" name="keterangan" value="<?=htmlspecialchars($f_keterangan)?>" class="i-input" placeholder="Cari Keterangan"></div>
      <div class="md:col-span-12 flex items-end justify-between gap-3">
        <div class="flex items-center gap-3">
          <?php $from=$total_rows?($offset+1):0;$to=min($offset+$per_page,$total_rows);?><span class="text-sm text-gray-700">Menampilkan <strong><?=$from?></strong>–<strong><?=$to?></strong> dari <strong><?=number_format($total_rows)?></strong> data</span>
          <div class="flex items-center gap-2"><input type="hidden" name="page" value="1"><label class="text-sm text-gray-700">Baris/hal</label><select name="per_page" class="i-select" style="width:auto" onchange="this.form.submit()"><?php foreach($perOpts as $opt):?><option value="<?=$opt?>" <?=$per_page==$opt?'selected':''?>><?=$opt?></option><?php endforeach;?></select></div>
        </div>
        <div class="flex gap-2"><button class="btn btn-dark" type="submit"><i class="ti ti-filter"></i> Terapkan</button><a href="?tab=<?=urlencode($tab)?>" class="btn"><i class="ti ti-restore"></i> Reset</a></div>
      </div>
    </form>
    <div class="overflow-x-auto border rounded-xl">
      <div class="tbl-wrap">
        <table class="min-w-full text-sm table-fixed">
          <thead class="sticky">
            <?php if($tab==='angkutan'):?>
              <tr class="bg-green-600 text-white uppercase tracking-wider"><th class="py-3 px-4 text-left w-[14rem]">Kebun</th><th class="py-3 px-4 text-left w-[10rem]">Rayon</th><th class="py-3 px-4 text-left w-[12rem]">Gudang Asal</th><th class="py-3 px-4 text-left w-[12rem]">Unit Tujuan</th><th class="py-3 px-4 text-left w-[10rem]">Tanggal</th><th class="py-3 px-4 text-left w-[12rem]">Jenis Pupuk</th><th class="py-3 px-4 text-right w-[10rem]">Jumlah (Kg)</th><th class="py-3 px-4 text-left w-[12rem]">No SPB</th><th class="py-3 px-4 text-left w-[12rem]">Keterangan</th><th class="py-3 px-4 text-left w-[10rem]">Aksi</th></tr>
            <?php else:?>
              <tr class="bg-green-600 text-white uppercase tracking-wider"><th class="py-3 px-4 text-left w-[7rem]">Tahun</th><th class="py-3 px-4 text-left w-[12rem]">Kebun</th><th class="py-3 px-4 text-left w-[10rem]">Tanggal</th><th class="py-3 px-4 text-left w-[10rem]">Periode</th><th class="py-3 px-4 text-left w-[12rem]">Unit/Defisi</th><th class="py-3 px-4 text-left w-[9rem]">T.Tanam</th><th class="py-3 px-4 text-left w-[9rem]">Blok</th><th class="py-3 px-4 text-left w-[9rem]">Rayon</th><th class="py-3 px-4 text-right w-[9rem]">Luas (Ha)</th><th class="py-3 px-4 text-right w-[9rem]">Inv.Pkk</th><th class="py-3 px-4 text-left w-[12rem]">Jenis Pupuk</th><th class="py-3 px-4 text-left w-[7rem]">APL</th><th class="py-3 px-4 text-right w-[8rem]">Dosis</th><th class="py-3 px-4 text-left w-[12rem]">No AU-58</th><th class="py-3 px-4 text-left w-[12rem]">Keterangan</th><th class="py-3 px-4 text-right w-[8rem]">Kg</th><th class="py-3 px-4 text-left w-[8rem]">Aksi</th></tr>
              <tr class="bg-green-50 text-green-900 border-b border-green-200"><th class="py-2 px-4 text-xs font-bold" colspan="8">JUMLAH</th><th class="py-2 px-4 text-right text-xs font-bold"><?=number_format($tot_all_luas??0,2)?></th><th class="py-2 px-4 text-right text-xs font-bold"><?=number_format($tot_all_invt??0,0)?></th><th class="py-2 px-4" colspan="5"></th></tr>
            <?php endif;?>
          </thead>
          <tbody class="text-gray-900">
            <?php if(empty($rows)):?><tr><td colspan="<?=$tab==='angkutan'?10:17?>" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>
            <?php else: foreach($rows as $r):?>
              <tr class="border-b hover:bg-gray-50">
                <?php if($tab==='angkutan'):?>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['kebun_nama']??($r['kebun_kode']??'-'))?></td><td class="py-3 px-4"><?=htmlspecialchars($r['rayon']??'-')?></td>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['gudang_asal'])?></td><td class="py-3 px-4"><?=htmlspecialchars($r['unit_tujuan_nama']??'-')?></td>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['tanggal'])?></td><td class="py-3 px-4"><?=htmlspecialchars($r['jenis_pupuk'])?></td>
                  <td class="py-3 px-4 text-right"><?=number_format((float)$r['jumlah'],2)?></td><td class="py-3 px-4"><?=htmlspecialchars($r['no_spb']??'')?></td>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['keterangan']??'')?></td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <button class="btn-edit text-blue-700" title="Edit" data-tab="angkutan" data-json='<?=htmlspecialchars(json_encode($r),ENT_QUOTES,"UTF-8")?>' <?=$isStaf?'disabled':''?>>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.1 2.1 0 0 1 2.97 2.97l-9.9 9.9-4.2 1.23 1.23-4.2 9.9-9.9z" /></svg>
                      </button>
                      <button class="btn-delete text-red-700" title="Hapus" data-tab="angkutan" data-id="<?=(int)$r['id']?>" <?=$isStaf?'disabled':''?>>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3m-9 0h12" /></svg>
                      </button>
                    </div>
                  </td>
                <?php else: $ts=strtotime($r['tanggal']); $tahunFallback=$ts?date('Y',$ts):''; $tahunRow=isset($r['tahun_input'])&&$r['tahun_input']!==''?$r['tahun_input']:$tahunFallback; $bulanIndex=$ts?(int)date('n',$ts):0; $periodeRow=$bulanIndex?($bulanList[$bulanIndex].' '.$tahunFallback):''; $ttanam=isset($r['t_tanam'])&&$r['t_tanam']!==''?$r['t_tanam']:'-'; $apl=$r['apl']??'-';?>
                  <td class="py-3 px-4"><?=htmlspecialchars($tahunRow)?></td><td class="py-3 px-4"><?=htmlspecialchars($r['kebun_nama']??($r['kebun_kode']??'-'))?></td>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['tanggal'])?></td><td class="py-3 px-4"><?=htmlspecialchars($periodeRow)?></td>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['unit_nama']??'-')?></td><td class="py-3 px-4"><?=htmlspecialchars($ttanam)?></td>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['blok'])?></td><td class="py-3 px-4"><?=htmlspecialchars($r['rayon']??'-')?></td>
                  <td class="py-3 px-4 text-right"><?=number_format((float)$r['luas'],2)?></td><td class="py-3 px-4 text-right"><?=(int)($r['invt_pokok']??0)?></td>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['jenis_pupuk'])?></td><td class="py-3 px-4"><?=htmlspecialchars($apl)?></td>
                  <td class="py-3 px-4 text-right"><?=isset($r['dosis'])?number_format((float)$r['dosis'],2):'-'?></td>
                  <td class="py-3 px-4"><?=htmlspecialchars($r['no_au_58']??'')?></td><td class="py-3 px-4"><?=htmlspecialchars($r['keterangan']??'')?></td>
                  <td class="py-3 px-4 text-right"><?=number_format((float)$r['jumlah'],2)?></td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <button class="btn-edit text-blue-700" title="Edit" data-tab="menabur" data-json='<?=htmlspecialchars(json_encode($r),ENT_QUOTES,"UTF-8")?>' <?=$isStaf?'disabled':''?>>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.1 2.1 0 0 1 2.97 2.97l-9.9 9.9-4.2 1.23 1.23-4.2 9.9-9.9z" /></svg>
                      </button>
                      <button class="btn-delete text-red-700" title="Hapus" data-tab="menabur" data-id="<?=(int)$r['id']?>" <?=$isStaf?'disabled':''?>>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3m-9 0h12" /></svg>
                      </button>
                    </div>
                  </td>
                <?php endif;?>
              </tr>
            <?php endforeach; endif;?>
          </tbody>
        </table>
      </div>
      <div class="grid <?=$tab==='angkutan'?'grid-cols-1':'md:grid-cols-2'?> gap-3 p-3">
        <?php if($tab==='angkutan'):?>
          <div class="rounded-xl border bg-gray-50 p-4"><div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Halaman Ini</div><div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm"><div class="p-3 rounded-lg bg-white border text-center"><div class="text-xs text-gray-500">Jumlah (Kg)</div><div class="text-lg font-bold"><?=number_format($sum_page_kg??0,2)?></div></div></div></div>
          <div class="rounded-xl border bg-gray-50 p-4"><div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Semua (sesuai filter)</div><div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm"><div class="p-3 rounded-lg bg-white border text-center"><div class="text-xs text-gray-500">Jumlah (Kg)</div><div class="text-lg font-bold"><?=number_format($tot_all_kg??0,2)?></div></div></div></div>
        <?php else:?>
          <div class="rounded-xl border bg-gray-50 p-4"><div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Halaman Ini</div><div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm"><div class="p-3 rounded-lg bg-white border text-center"><div class="text-xs text-gray-500">Jumlah (Kg)</div><div class="text-lg font-bold"><?=number_format($sum_page_kg??0,2)?></div></div><div class="p-3 rounded-lg bg-white border text-center"><div class="text-xs text-gray-500">Luas (Ha)</div><div class="text-lg font-bold"><?=number_format($sum_page_luas??0,2)?></div></div><div class="p-3 rounded-lg bg-white border text-center"><div class="text-xs text-gray-500">Invt. Pokok</div><div class="text-lg font-bold"><?=number_format($sum_page_invt??0,0)?></div></div></div></div>
          <div class="rounded-xl border bg-gray-50 p-4"><div class="text-sm font-semibold text-gray-700 mb-2">Ringkasan Semua (sesuai filter)</div><div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm"><div class="p-3 rounded-lg bg-white border text-center"><div class="text-xs text-gray-500">Jumlah (Kg)</div><div class="text-lg font-bold"><?=number_format($tot_all_kg??0,2)?></div></div><div class="p-3 rounded-lg bg-white border text-center"><div class="text-xs text-gray-500">Luas (Ha)</div><div class="text-lg font-bold"><?=number_format($tot_all_luas??0,2)?></div></div><div class="p-3 rounded-lg bg-white border text-center"><div class="text-xs text-gray-500">Invt. Pokok</div><div class="text-lg font-bold"><?=number_format($tot_all_invt??0,0)?></div></div></div></div>
        <?php endif;?>
      </div>
      <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between p-3">
        <div class="text-sm text-gray-700">Halaman <span class="font-semibold"><?=$page?></span> dari <span class="font-semibold"><?=$total_pages?></span></div>
        <?php function page_link($p){$q=['tab'=>$_GET['tab']??'','unit_id'=>$_GET['unit_id']??'','kebun_id'=>$_GET['kebun_id']??'','tanggal'=>$_GET['tanggal']??'','bulan'=>$_GET['bulan']??'','jenis_pupuk'=>$_GET['jenis_pupuk']??'','rayon'=>$_GET['rayon']??'','keterangan'=>$_GET['keterangan']??'','per_page'=>$_GET['per_page']??'','page'=>$p,];return '?'.http_build_query($q);}?>
        <div class="inline-flex gap-2"><a href="<?=$page>1?page_link($page-1):'javascript:void(0)'?>" class="px-3 py-2 rounded-lg border <?=$page>1?'hover:bg-gray-50 text-gray-800':'opacity-50 cursor-not-allowed text-gray-400'?>">Prev</a><a href="<?=$page<$total_pages?page_link($page+1):'javascript:void(0)'?>" class="px-3 py-2 rounded-lg border <?=$page<$total_pages?'hover:bg-gray-50 text-gray-800':'opacity-50 cursor-not-allowed text-gray-400'?>">Next</a></div>
      </div>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-4xl">
    <div class="flex justify-between items-center mb-6"><h2 id="modal-title" class="text-2xl font-bold text-gray-900">Tambah Data</h2><button id="btn-close" class="text-gray-500 hover:text-gray-800 text-3xl" aria-label="Close">&times;</button></div>
    <form id="crud-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($CSRF)?>"><input type="hidden" name="action" id="form-action"><input type="hidden" name="id" id="form-id"><input type="hidden" name="tab" id="form-tab" value="<?=htmlspecialchars($tab)?>">
      <div id="group-angkutan" class="<?=$tab==='angkutan'?'':'hidden'?>"><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><label class="block text-sm font-semibold mb-1">Kebun</label><select id="kebun_id_angkutan" name="kebun_id" class="i-select"><option value="">— Pilih Kebun —</option><?php foreach($kebuns as $k):?><option value="<?=(int)$k['id']?>" data-kode="<?=htmlspecialchars($k['kode'])?>"><?=htmlspecialchars($k['nama_kebun'])?> (<?=htmlspecialchars($k['kode'])?>)</option><?php endforeach;?></select><input type="hidden" id="kebun_kode_angkutan" name="kebun_kode"></div><div><label class="block text-sm font-semibold mb-1">Rayon</label><input type="text" id="rayon_angkutan" name="rayon" class="i-input" placeholder="Rayon manual"></div><div><label class="block text-sm font-semibold mb-1">Gudang Asal *</label><input type="text" id="gudang_asal" name="gudang_asal" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">Unit Tujuan *</label><select id="unit_tujuan_id" name="unit_tujuan_id" class="i-select"><option value="">— Pilih Unit —</option><?php foreach($units as $u):?><option value="<?=(int)$u['id']?>"><?=htmlspecialchars($u['nama_unit'])?></option><?php endforeach;?></select></div><div><label class="block text-sm font-semibold mb-1">Tanggal *</label><input type="date" id="tanggal_angkutan" class="i-input"></div><div><label class="block textsm font-semibold mb-1">Jenis Pupuk *</label><select id="jenis_pupuk_angkutan" class="i-select"><option value="">-- Pilih Jenis --</option><?php foreach($pupuks as $jp):?><option value="<?=htmlspecialchars($jp)?>"><?=htmlspecialchars($jp)?></option><?php endforeach;?></select></div><div><label class="block text-sm font-semibold mb-1">Jumlah (Kg)</label><input type="number" step="0.01" id="jumlah_angkutan" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">No SPB</label><input type="text" id="no_spb_angkutan" name="no_spb" class="i-input" placeholder="Contoh: SPB/XX/2025"></div><div><label class="block text-sm font-semibold mb-1">Keterangan</label><input type="text" id="keterangan_angkutan" name="keterangan" class="i-input" placeholder="Keterangan singkat"></div><div><label class="block text-sm font-semibold mb-1"></label><input type="text" id="nomor_do" name="nomor_do" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">Supir</label><input type="text" id="supir" name="supir" class="i-input"></div></div></div>
      <div id="group-menabur" class="<?=$tab==='menabur'?'':'hidden'?>"><div class="grid grid-cols-1 md:grid-cols-3 gap-4"><div class="md:col-span-1"><label class="block text-sm font-semibold mb-1">Unit *</label><select id="unit_id" name="unit_id" class="i-select"><option value="">— Pilih Unit —</option><?php foreach($units as $u):?><option value="<?=(int)$u['id']?>"><?=htmlspecialchars($u['nama_unit'])?></option><?php endforeach;?></select></div><div class="md:col-span-1"><label class="block text-sm font-semibold mb-1">Kebun</label><select id="kebun_id_menabur" name="kebun_id" class="i-select"><option value="">— Pilih Kebun —</option><?php foreach($kebuns as $k):?><option value="<?=(int)$k['id']?>" data-kode="<?=htmlspecialchars($k['kode'])?>"><?=htmlspecialchars($k['nama_kebun'])?> (<?=htmlspecialchars($k['kode'])?>)</option><?php endforeach;?></select></div><div class="md:col-span-1"><label class="block text-sm font-semibold mb-1">Blok *</label><select id="blok" name="blok" class="i-select"><option value="">— pilih Unit dulu —</option></select></div><div><label class="block text-sm font-semibold mb-1">Rayon</label><input type="text" id="rayon_menabur" name="rayon" class="i-input" placeholder="Rayon manual"></div><div><label class="block text-sm font-semibold mb-1">Tanggal *</label><input type="date" id="tanggal_menabur" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">Tahun</label><input type="number" id="tahun" min="1900" max="2100" placeholder="YYYY" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">Jenis Pupuk *</label><select id="jenis_pupuk_menabur" class="i-select"><option value="">-- Pilih Jenis --</option><?php foreach($pupuks as $jp):?><option value="<?=htmlspecialchars($jp)?>"><?=htmlspecialchars($jp)?></option><?php endforeach;?></select></div><div><label class="block text-sm font-semibold mb-1">Tahun Tanam</label><select id="tahun_tanam_select" class="i-select"><option value="">— Pilih Tahun Tanam —</option><?php foreach(($tahunTanamList??[])as $tt):?><option value="<?=htmlspecialchars($tt['tahun'])?>" data-id="<?=(int)$tt['id']?>"><?=htmlspecialchars($tt['tahun'])?><?=$tt['ket']?' - '.htmlspecialchars($tt['ket']):''?></option><?php endforeach;?></select></div><div><label class="block text-sm font-semibold mb-1">APL (Aplikasi)</label><input type="text" id="apl" class="i-input" placeholder="Nama aplikasi"></div><div><label class="block text-sm font-semibold mb-1">Dosis</label><input type="number" step="0.01" id="dosis" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">Jumlah (Kg)</label><input type="number" step="0.01" id="jumlah_menabur" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">Luas (Ha)</label><input type="number" step="0.01" id="luas" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">Invt. Pokok</label><input type="number" step="1" id="invt_pokok" class="i-input"></div><div><label class="block text-sm font-semibold mb-1">No AU-58</label><input type="text" id="no_au_58_menabur" name="no_au_58" class="i-input" placeholder="Contoh: AU58/XX/2025"></div><div><label class="block text-sm font-semibold mb-1">Keterangan</label><input type="text" id="keterangan_menabur" name="keterangan" class="i-input" placeholder="Keterangan singkat"></div><div class="md:col-span-3"></div></div></div>
      <div class="flex justify-end gap-3 mt-6"><button type="button" id="btn-cancel" class="btn">Batal</button><button type="submit" class="btn btn-dark">Simpan</button></div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // --- MODIFIKASI: Kirim role ke JavaScript ---
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;

  const $=s=>document.querySelector(s);
  async function loadJSON(url){try{const r=await fetch(url);return await r.json()}catch{return null}}
  function fillSelect(el,list,placeholder='— Pilih —'){if(!el)return;el.innerHTML='';const d=document.createElement('option');d.value='';d.textContent=placeholder;el.appendChild(d);(list||[]).forEach(v=>{const op=document.createElement('option');op.value=v;op.textContent=v;el.appendChild(op)})}
  (async()=>{const j=await loadJSON('?ajax=options&type=jenis');const arr=(j&&j.success&&Array.isArray(j.data))?j.data:[];if(arr.length){fillSelect($('#jenis_pupuk_angkutan'),arr,'-- Pilih Jenis --');fillSelect($('#jenis_pupuk_menabur'),arr,'-- Pilih Jenis --')}})();
  async function refreshFormBlokByUnit(){const uid=$('#unit_id')?.value||'';const sel=$('#blok');if(!sel)return;sel.disabled=!uid;if(!uid){sel.innerHTML='<option value="">— pilih Unit dulu —</option>';return}const j=await loadJSON(`?ajax=options&type=blok&unit_id=${encodeURIComponent(uid)}`);const list=(j&&j.success&&Array.isArray(j.data))?j.data:[];fillSelect(sel,list,'-- Pilih Blok --')}
  $('#unit_id')?.addEventListener('change',refreshFormBlokByUnit);
  function syncKebunKode(selectId,hiddenId){const sel=document.getElementById(selectId);const hid=document.getElementById(hiddenId);if(!sel||!hid)return;const kode=sel.options[sel.selectedIndex]?.dataset?.kode||'';hid.value=kode}
  document.getElementById('kebun_id_angkutan')?.addEventListener('change',()=>syncKebunKode('kebun_id_angkutan','kebun_kode_angkutan'));
  const modal=$('#crud-modal'),form=$('#crud-form'),title=$('#modal-title');
  const open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')},close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};
  function toggleGroup(tab){const ga=$('#group-angkutan'),gm=$('#group-menabur');ga.classList.toggle('hidden',tab!=='angkutan');gm.classList.toggle('hidden',tab!=='menabur');['gudang_asal','unit_tujuan_id','tanggal_angkutan','jenis_pupuk_angkutan'].forEach(id=>$('#'+id)?.removeAttribute('required'));['unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'].forEach(id=>$('#'+id)?.removeAttribute('required'));if(tab==='angkutan')['gudang_asal','unit_tujuan_id','tanggal_angkutan','jenis_pupuk_angkutan'].forEach(id=>$('#'+id)?.setAttribute('required','required'));else['unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'].forEach(id=>$('#'+id)?.setAttribute('required','required'))}
  $('#btn-add')?.addEventListener('click',()=>{form.reset();$('#form-action').value='store';$('#form-id').value='';$('#form-tab').value='<?=htmlspecialchars($tab)?>';title.textContent='Tambah Data';toggleGroup($('#form-tab').value);if($('#form-tab').value==='menabur')refreshFormBlokByUnit();if($('#form-tab').value==='angkutan'){$('#kebun_kode_angkutan').value=''}open()});
  $('#btn-close')?.addEventListener('click',close);$('#btn-cancel')?.addEventListener('click',close);
  function setKebunSelect(selectId,row){const sel=document.getElementById(selectId);if(!sel)return;if(row.kebun_id){sel.value=row.kebun_id;return}if(row.kebun_kode){const opt=Array.from(sel.options).find(o=>(o.dataset.kode||'')===String(row.kebun_kode));if(opt)sel.value=opt.value}}
  document.body.addEventListener('click',async e=>{const t=e.target.closest('button');if(!t)return;
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(t.classList.contains('btn-edit')&&!IS_STAF){form.reset();const row=JSON.parse(t.dataset.json);const tab=t.dataset.tab;$('#form-tab').value=tab;$('#form-action').value='update';$('#form-id').value=row.id||'';title.textContent='Edit Data';toggleGroup(tab);if(tab==='angkutan'){setKebunSelect('kebun_id_angkutan',row);syncKebunKode('kebun_id_angkutan','kebun_kode_angkutan');$('#rayon_angkutan').value=row.rayon??'';$('#gudang_asal').value=row.gudang_asal??'';$('#unit_tujuan_id').value=row.unit_tujuan_id??'';$('#tanggal_angkutan').value=row.tanggal??'';$('#jenis_pupuk_angkutan').value=row.jenis_pupuk??'';$('#jumlah_angkutan').value=row.jumlah??'';$('#no_spb_angkutan').value=row.no_spb??'';$('#keterangan_angkutan').value=row.keterangan??'';$('#nomor_do').value=row.nomor_do??'';$('#supir').value=row.supir??''}else{setKebunSelect('kebun_id_menabur',row);$('#unit_id').value=row.unit_id??'';await refreshFormBlokByUnit();$('#blok').value=row.blok??'';$('#rayon_menabur').value=row.rayon??'';$('#tanggal_menabur').value=row.tanggal??'';$('#jenis_pupuk_menabur').value=row.jenis_pupuk??'';$('#dosis').value=(row.dosis??'')===null?'':row.dosis;$('#jumlah_menabur').value=row.jumlah??'';$('#luas').value=row.luas??'';$('#invt_pokok').value=row.invt_pokok??'';$('#no_au_58_menabur').value=row.no_au_58??'';$('#keterangan_menabur').value=row.keterangan??'';$('#apl').value=row.apl??'';const tahunFromRow=(row.tahun_input??'');const tahunFromTanggal=row.tanggal?String(row.tanggal).slice(0,4):'';$('#tahun').value=tahunFromRow||tahunFromTanggal||'';const ttSel=document.getElementById('tahun_tanam_select');if(ttSel){const idFromRow=row.tahun_tanam_id??'';const tahunFromRowTT=row.tahun_tanam??row.t_tanam??'';if(idFromRow){const op=Array.from(ttSel.options).find(o=>String(o.dataset.id||'')===String(idFromRow));if(op)ttSel.value=op.value}else if(tahunFromRowTT){ttSel.value=String(tahunFromRowTT)}else{ttSel.value=''}}}open()}
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(t.classList.contains('btn-delete')&&!IS_STAF){const id=t.dataset.id;const tab=t.dataset.tab;Swal.fire({title:'Hapus data ini?',text:'Tindakan ini tidak dapat dibatalkan.',icon:'warning',showCancelButton:true}).then(res=>{if(!res.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?=htmlspecialchars($CSRF)?>');fd.append('action','delete');fd.append('tab',tab);fd.append('id',id);fetch('pemupukan_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(j.success)Swal.fire({icon:'success',title:'Terhapus',timer:1200,showConfirmButton:false}).then(()=>location.reload());else Swal.fire('Gagal',j.message||'Error','error')}).catch(err=>Swal.fire('Error',String(err),'error'))})}});
  $('#tanggal_menabur')?.addEventListener('change',e=>{const y=(e.target.value||'').slice(0,4);const t=document.getElementById('tahun');if(y&&t&&!t.value)t.value=y});
  $('#crud-form').addEventListener('submit',e=>{e.preventDefault();const tab=$('#form-tab').value;const fd=new FormData(e.target);fd.set('tab',tab);fd.set('action',$('#form-action').value);if(tab==='angkutan'){const need=['gudang_asal','unit_tujuan_id','tanggal_angkutan','jenis_pupuk_angkutan'];for(const id of need){const el=$('#'+id);if(!el||!el.value){Swal.fire('Validasi',`Field ${id.replaceAll('_',' ')} wajib diisi.`,'warning');return}}syncKebunKode('kebun_id_angkutan','kebun_kode_angkutan');fd.set('tanggal',$('#tanggal_angkutan').value);fd.set('jenis_pupuk',$('#jenis_pupuk_angkutan').value);fd.set('jumlah',$('#jumlah_angkutan').value||'');fd.set('kebun_id',$('#kebun_id_angkutan').value||'');fd.set('kebun_kode',$('#kebun_kode_angkutan').value||'');fd.set('rayon',$('#rayon_angkutan').value||'');fd.set('keterangan',$('#keterangan_angkutan').value||'');fd.set('no_spb',$('#no_spb_angkutan').value||'')}else{const need=['unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'];for(const id of need){const el=$('#'+id);if(!el||!el.value){Swal.fire('Validasi',`Field ${id.replaceAll('_',' ')} wajib diisi.`,'warning');return}}fd.set('tanggal',$('#tanggal_menabur').value);fd.set('tahun',$('#tahun').value||'');fd.set('jenis_pupuk',$('#jenis_pupuk_menabur').value);fd.set('apl',$('#apl').value||'');fd.set('dosis',$('#dosis').value||'');fd.set('jumlah',$('#jumlah_menabur').value||'');fd.set('luas',$('#luas').value||'');fd.set('invt_pokok',$('#invt_pokok').value||'');fd.set('rayon',$('#rayon_menabur').value||'');fd.set('keterangan',$('#keterangan_menabur').value||'');fd.set('no_au_58',$('#no_au_58_menabur').value||'');const ttSel=document.getElementById('tahun_tanam_select');const ttVal=ttSel?(ttSel.value||''):'';const ttId=ttSel?(ttSel.selectedOptions[0]?.dataset?.id||''):'';fd.set('tahun_tanam',ttVal);fd.set('tahun_tanam_id',ttId)}fetch('pemupukan_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(j.success){close();Swal.fire({icon:'success',title:'Berhasil',text:j.message||'Tersimpan',timer:1400,showConfirmButton:false}).then(()=>location.reload())}else{const html=j.errors?.length?`<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>`:(j.message||'Terjadi kesalahan');Swal.fire('Gagal',html,'error')}}).catch(err=>Swal.fire('Error',String(err),'error'))});
  if(document.querySelector('#group-menabur')&&!document.querySelector('#group-menabur').classList.contains('hidden')){refreshFormBlokByUnit()}
});
</script> modifkasi fulll ini agar sama juga agar role staf gabisa eidt dan delete namun masih bisa create modfikasi full ya untuk icon edit dan hapus dirubah juga ya pakai icon jngn text doang modifkasi fulll