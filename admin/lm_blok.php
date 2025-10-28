<?php
// lm_blok.php — LM per Blok (Realisasi dari laporan mingguan, anggaran manual)
// ------------------------------------------------------------------------------------------------
session_start();

ini_set('display_errors','0'); ini_set('log_errors','1'); error_reporting(E_ALL);
set_exception_handler(function($e){ http_response_code(500); error_log("[LM_BLOK] ".$e->getMessage()); echo "<h2 style='font-family:system-ui;padding:16px'>Terjadi kesalahan.</h2>"; exit; });

if (empty($_SESSION['loggedin'])) { header("Location: ../auth/login.php"); exit; }
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');
$userId   = (int)($_SESSION['user_id'] ?? 0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function norm_bulan($b){
  static $map = ['Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12];
  $k = ucfirst(strtolower(trim((string)$b))); return $map[$k] ?? 1;
}
$bulanListID = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];

$tahunNow = (int)date('Y');

// === Masters ===
$kebun = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$jpm   = $conn->query("SELECT id, nama FROM md_jenis_pekerjaan_mingguan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

// === Filters (default aman) ===
$f_kebun  = $_GET['kebun_id'] ?? ($kebun[0]['id'] ?? '');
$f_unit   = $_GET['unit_id']  ?? ($units[0]['id'] ?? '');
$f_jpm    = $_GET['jenis_pekerjaan_id'] ?? ($jpm[0]['id'] ?? '');
$f_tahun  = (int)($_GET['tahun'] ?? $tahunNow);

// === Display names ===
$namaKebun = ''; foreach($kebun as $x){ if((string)$x['id']===(string)$f_kebun){ $namaKebun=strtoupper($x['nama_kebun']); break; } }
$namaUnit  = ''; foreach($units as $x){ if((string)$x['id']===(string)$f_unit){ $namaUnit=$x['nama_unit']; break; } }
$namaJpm   = ''; foreach($jpm as $x){ if((string)$x['id']===(string)$f_jpm){ $namaJpm=strtoupper($x['nama']); break; } }

// === Ambil daftar blok dari md_blok (hanya dari unit terpilih) ===
$blokStmt = $conn->prepare("SELECT kode AS blok_kode, tahun_tanam, luas_ha FROM md_blok WHERE unit_id=:u ORDER BY kode");
$blokStmt->execute([':u'=>$f_unit]);
$blokList = $blokStmt->fetchAll(PDO::FETCH_ASSOC);

// === Ambil anggaran tersimpan untuk kombinasi (kebun,unit,jenis,tahun) ===
$anggaran = []; // key: blok_kode => anggaran
if ($blokList) {
  $stA = $conn->prepare("SELECT blok_kode, anggaran FROM lm_anggaran_blok
                         WHERE kebun_id=:k AND unit_id=:u AND tahun=:t AND jenis_pekerjaan_id=:jp");
  $stA->execute([':k'=>$f_kebun, ':u'=>$f_unit, ':t'=>$f_tahun, ':jp'=>$f_jpm]);
  foreach($stA->fetchAll(PDO::FETCH_ASSOC) as $row){ $anggaran[$row['blok_kode']] = (float)$row['anggaran']; }
}

// === Ambil realisasi per bulan per blok dari laporan_mingguan ===
//     realisasi = SUM(ts + pkwt + kng + tp)
$realisasi = []; // $realisasi[blok_kode][1..12] = float
if ($blokList) {
  $stR = $conn->prepare("
    SELECT blok, bulan, SUM(ts+pkwt+kng+tp) AS qty
    FROM laporan_mingguan
    WHERE kebun_id=:k AND afdeling=:u AND tahun=:t AND jenis_pekerjaan_id=:jp
    GROUP BY blok, bulan
  ");
  $stR->execute([':k'=>$f_kebun, ':u'=>$f_unit, ':t'=>$f_tahun, ':jp'=>$f_jpm]);
  while($r = $stR->fetch(PDO::FETCH_ASSOC)){
    $m = norm_bulan($r['bulan'] ?? 'Januari');
    $b = (string)($r['blok'] ?? '');
    if ($b==='') continue;
    if (!isset($realisasi[$b])) $realisasi[$b] = array_fill(1,12,0.0);
    $realisasi[$b][$m] += (float)$r['qty'];
  }
}

// view
$currentPage = 'lm_blok';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.table { width:100%; border-collapse:collapse; }
.table th, .table td { border:1px solid #cbd5e1; padding:6px 8px; font-size:12px; }
.table thead th { background:#0ea5e9; color:#fff; text-align:center; }
.table tfoot td { font-weight:700; background:#fef3c7; }
.table td.num { text-align:right; }
.sticky-head { position:sticky; top:0; z-index:5; }
.badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; background:#e2e8f0; }
input.anggaran-input { width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:4px 6px; text-align:right; }
input.anggaran-input[readonly] { background:transparent; border-color:transparent; }
@media print { .no-print { display:none !important; } }
</style>

<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold">LM per Blok</h1>
      <p class="text-gray-500">Realisasi dari Laporan Mingguan (TS+PKWT+KNG+TP) vs Anggaran per blok.</p>
    </div>
    <div class="no-print flex gap-2">
      <?php if(!$isStaf): ?>
      <button id="btn-save" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
        <i class="ti ti-device-floppy"></i> Simpan Anggaran
      </button>
      <?php endif; ?>
      <button id="btn-print" class="px-4 py-2 rounded-lg bg-rose-600 text-white hover:bg-rose-700">
        <i class="ti ti-printer"></i> Print
      </button>
    </div>
  </div>

  <!-- FILTER -->
  <form method="GET" class="no-print grid grid-cols-1 md:grid-cols-5 gap-3 bg-white p-4 rounded-xl shadow">
    <div>
      <label class="text-sm text-gray-700">Kebun</label>
      <select name="kebun_id" class="w-full border rounded-lg px-2 py-1">
        <?php foreach($kebun as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$f_kebun===(string)$k['id']?'selected':'' ?>>
            <?= htmlspecialchars($k['nama_kebun']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-sm text-gray-700">Afdeling (Unit)</label>
      <select name="unit_id" class="w-full border rounded-lg px-2 py-1">
        <?php foreach($units as $u): ?>
          <option value="<?= $u['id'] ?>" <?= (string)$f_unit===(string)$u['id']?'selected':'' ?>>
            <?= htmlspecialchars($u['nama_unit']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-sm text-gray-700">Jenis Pekerjaan (Mingguan)</label>
      <select name="jenis_pekerjaan_id" class="w-full border rounded-lg px-2 py-1">
        <?php foreach($jpm as $j): ?>
          <option value="<?= $j['id'] ?>" <?= (string)$f_jpm===(string)$j['id']?'selected':'' ?>>
            <?= htmlspecialchars($j['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-sm text-gray-700">Tahun</label>
      <input type="number" name="tahun" value="<?= (int)$f_tahun ?>" class="w-full border rounded-lg px-2 py-1">
    </div>
    <div class="flex items-end">
      <button class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700 w-full">Terapkan</button>
    </div>
  </form>

  <div class="bg-white p-3 rounded-xl shadow">
    <div class="mb-2 text-sm text-gray-600">
      <span class="badge"><?= htmlspecialchars($namaKebun) ?></span>
      <span class="badge"><?= htmlspecialchars($namaUnit) ?></span>
      <span class="badge"><?= htmlspecialchars($namaJpm) ?></span>
      <span class="badge">Tahun <?= (int)$f_tahun ?></span>
    </div>

    <div class="overflow-x-auto">
      <table class="table">
        <thead class="sticky-head">
          <tr>
            <th rowspan="2">T.T</th>
            <th rowspan="2">Kode Blok</th>
            <th rowspan="2">Luas (Ha)</th>
            <th rowspan="2" style="background:#ef4444;">Anggaran</th>
            <th colspan="12">Realisasi</th>
            <th rowspan="2" style="background:#16a34a;">Jumlah</th>
            <th rowspan="2" style="background:#22c55e;">+/−</th>
          </tr>
          <tr>
            <?php foreach($bulanListID as $b) echo "<th>{$b}</th>"; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $sum_ang=0; $sum_bul=array_fill(1,12,0.0); $sum_total=0;
          foreach($blokList as $row):
            $kode = (string)$row['blok_kode'];
            $tt   = (int)($row['tahun_tanam'] ?? 0);
            $luas = (float)($row['luas_ha'] ?? 0);
            $ang  = (float)($anggaran[$kode] ?? 0);
            $sum_ang += $ang;

            $bul = $realisasi[$kode] ?? array_fill(1,12,0.0);
            $rowTotal = 0.0;
            for($m=1;$m<=12;$m++){ $rowTotal += (float)$bul[$m]; $sum_bul[$m] += (float)$bul[$m]; }
            $sum_total += $rowTotal;
            $diff = $rowTotal - $ang;
          ?>
          <tr data-blok="<?= htmlspecialchars($kode) ?>">
            <td class="num"><?= $tt ?></td>
            <td><?= htmlspecialchars($kode) ?></td>
            <td class="num"><?= number_format($luas,2) ?></td>
            <td class="num">
              <input class="anggaran-input" type="number" step="0.01" value="<?= number_format($ang,2,'.','') ?>"
                     <?= $isStaf ? 'readonly' : '' ?> >
            </td>
            <?php for($m=1;$m<=12;$m++): ?>
              <td class="num"><?= number_format((float)$bul[$m],2) ?></td>
            <?php endfor; ?>
            <td class="num" style="font-weight:700;"><?= number_format($rowTotal,2) ?></td>
            <td class="num" style="font-weight:700;"><?= number_format($diff,2) ?></td>
          </tr>
          <?php endforeach; ?>

          <?php if (!$blokList): ?>
          <tr><td colspan="1+1+1+1+12+1+1" class="text-center">Tidak ada data blok untuk unit ini.</td></tr>
          <?php endif; ?>
        </tbody>

        <tfoot>
          <tr>
            <td colspan="3" class="num">Jumlah</td>
            <td class="num"><?= number_format($sum_ang,2) ?></td>
            <?php for($m=1;$m<=12;$m++): ?>
              <td class="num"><?= number_format($sum_bul[$m],2) ?></td>
            <?php endfor; ?>
            <td class="num"><?= number_format($sum_total,2) ?></td>
            <td class="num"><?= number_format($sum_total - $sum_ang,2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const isStaf = <?= $isStaf ? 'true':'false' ?>;
  const btnSave = document.getElementById('btn-save');
  const btnPrint= document.getElementById('btn-print');

  if (btnSave) btnSave.addEventListener('click', async ()=>{
    const rows = Array.from(document.querySelectorAll('tbody tr[data-blok]'));
    const payload = rows.map(tr => ({
      blok_kode: tr.dataset.blok,
      anggaran:  parseFloat(tr.querySelector('.anggaran-input').value || '0') || 0
    }));

    const fd = new FormData();
    fd.append('csrf_token','<?= $CSRF ?>');
    fd.append('action','save_anggaran_blok');
    fd.append('kebun_id','<?= $f_kebun ?>');
    fd.append('unit_id','<?= $f_unit ?>');
    fd.append('jenis_pekerjaan_id','<?= $f_jpm ?>');
    fd.append('tahun','<?= $f_tahun ?>');
    fd.append('payload', JSON.stringify(payload));

    try{
      const res = await fetch('lm_blok_crud.php', { method:'POST', body: fd });
      const out = await res.json();
      if(out.success){
        Swal.fire({icon:'success', title:'Tersimpan', timer:1200, showConfirmButton:false});
      }else{
        Swal.fire('Gagal', out.message || 'Error', 'error');
      }
    }catch(e){
      Swal.fire('Error','Tidak dapat terhubung ke server','error');
    }
  });

  if (btnPrint) btnPrint.addEventListener('click', ()=>{
    window.print();
  });
});
</script>
