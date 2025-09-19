<?php
// admin/dashboard.php (FULL with Sidebar + Header + Realtime Filters, NO DUMMY UNITS)
session_start();

// ===== CSRF =====
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// ===== DB =====
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// ===== AJAX (API) =====
if (($_POST['ajax'] ?? '') === 'dashboard') {
  header('Content-Type: application/json');

  if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
    echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit;
  }

  $section  = $_POST['section']  ?? '';            // 'gudang' | 'pemakaian'
  $afdeling = trim($_POST['afdeling'] ?? '');
  $bulan    = trim($_POST['bulan']    ?? '');
  $tahun    = trim($_POST['tahun']    ?? '');

  try {
    // Helper bulan dari DATE -> enum Indonesia
    $bulanFromDate = "ELT(MONTH(tanggal),
      'Januari','Februari','Maret','April','Mei','Juni',
      'Juli','Agustus','September','Oktober','November','Desember')";

    if ($section === 'gudang') {
      // stok_gudang (bulan + tahun)
      $sql = "SELECT nama_bahan, satuan, bulan, tahun,
                     stok_awal, mutasi_masuk, mutasi_keluar, pasokan, dipakai
              FROM stok_gudang
              WHERE (:bulan='' OR bulan = :bulan)
                AND (:tahun='' OR tahun = :tahun)
              ORDER BY tahun DESC,
                FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";
      $st = $pdo->prepare($sql);
      $st->execute([':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    if ($section === 'pemakaian') {
      // Gabungkan 3 tabel jadi stream pemakaian
      $sql = "
        SELECT * FROM (
          -- 1) Pemakaian kimia
          SELECT
            no_dokumen,
            afdeling,
            bulan,
            tahun,
            nama_bahan,
            jenis_pekerjaan,
            COALESCE(jlh_diminta,0) AS jlh_diminta,
            COALESCE(jlh_fisik,0)   AS jlh_fisik
          FROM pemakaian_bahan_kimia
          WHERE (:afdeling='' OR afdeling = :afdeling)
            AND (:bulan=''    OR bulan = :bulan)
            AND (:tahun=''    OR tahun = :tahun)

          UNION ALL

          -- 2) Menabur pupuk (tanggal -> bulan/tahun)
          SELECT
            CONCAT('MENABUR-', LPAD(CAST(id AS CHAR), 6, '0')) AS no_dokumen,
            afdeling,
            {$bulanFromDate} AS bulan,
            YEAR(tanggal)    AS tahun,
            jenis_pupuk      AS nama_bahan,
            'Pemupukan'      AS jenis_pekerjaan,
            COALESCE(jumlah,0) AS jlh_diminta,
            COALESCE(jumlah,0) AS jlh_fisik
          FROM menabur_pupuk
          WHERE (:afdeling='' OR afdeling = :afdeling)
            AND (:bulan=''    OR {$bulanFromDate} = :bulan)
            AND (:tahun=''    OR YEAR(tanggal) = :tahun)

          UNION ALL

          -- 3) Menabur pupuk organik
          SELECT
            CONCAT('MENABURORG-', LPAD(CAST(id AS CHAR), 6, '0')) AS no_dokumen,
            afdeling,
            {$bulanFromDate} AS bulan,
            YEAR(tanggal)    AS tahun,
            jenis_pupuk      AS nama_bahan,
            'Pemupukan Organik' AS jenis_pekerjaan,
            COALESCE(jumlah,0) AS jlh_diminta,
            COALESCE(jumlah,0) AS jlh_fisik
          FROM menabur_pupuk_organik
          WHERE (:afdeling='' OR afdeling = :afdeling)
            AND (:bulan=''    OR {$bulanFromDate} = :bulan)
            AND (:tahun=''    OR YEAR(tanggal) = :tahun)
        ) pem
        ORDER BY
          tahun DESC,
          FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
          no_dokumen ASC
      ";
      $st = $pdo->prepare($sql);
      $st->execute([':afdeling'=>$afdeling, ':bulan'=>$bulan, ':tahun'=>$tahun]);
      echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }

    echo json_encode(['success'=>false,'message'=>'Section tidak dikenal']);
  } catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'DB error','error'=>$e->getMessage()]);
  }
  exit;
}

// ====== RENDER HALAMAN (bukan AJAX) ======
$HARI_ID  = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
$BULAN_ID = ["","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$now = new DateTime();
$hariIni = $HARI_ID[(int)$now->format('w')] . ', ' . $now->format('d') . ' ' . $BULAN_ID[(int)$now->format('n')] . ' ' . $now->format('Y');
$tahunNow = (int)date('Y');
$bulanList = $BULAN_ID; array_shift($bulanList);

// ===== Ambil daftar Afdeling REAL dari DB (tanpa dummy/fallback) =====
$opsiAfdeling = [];
try {
  $sqlAfd = "
    SELECT afd FROM (
      SELECT nama_unit AS afd FROM units
      UNION
      SELECT DISTINCT afdeling FROM pemakaian_bahan_kimia
      UNION
      SELECT DISTINCT afdeling FROM menabur_pupuk
      UNION
      SELECT DISTINCT afdeling FROM menabur_pupuk_organik
    ) X
    WHERE afd IS NOT NULL AND afd <> ''
    ORDER BY afd
  ";
  $stmA = $pdo->query($sqlAfd);
  $opsiAfdeling = $stmA->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  // Biarkan kosong jika memang tidak ada; tidak ada fallback dummy
  $opsiAfdeling = [];
}
include_once '../layouts/header.php';

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard Utama</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800">
  <!-- Layout with Sidebar -->
  <div class="min-h-screen flex">
   

    <!-- MAIN -->
    <main class="flex-1">
      <!-- HEADER -->
      <header class="bg-white border-b sticky top-0 z-10">
        <div class="mx-auto max-w-7xl px-4 py-3 flex items-center justify-between">
          <div>
            <div class="text-sm text-slate-500">Dashboard Utama</div>
            <h1 class="text-2xl font-bold"><?= htmlspecialchars($hariIni) ?></h1>
          </div>
          <div class="flex items-center gap-3">
            <select id="f-afdeling" class="border rounded-lg px-3 py-2 min-w-[170px]">
              <option value="">Semua Afdeling</option>
              <?php foreach ($opsiAfdeling as $afd): ?>
                <option value="<?= htmlspecialchars($afd) ?>"><?= htmlspecialchars($afd) ?></option>
              <?php endforeach; ?>
            </select>
            <select id="f-bulan" class="border rounded-lg px-3 py-2 min-w-[140px]">
              <option value="">Semua Bulan</option>
              <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
            </select>
            <select id="f-tahun" class="border rounded-lg px-3 py-2 min-w-[110px]">
              <?php for ($y=$tahunNow-2; $y<=$tahunNow+2; $y++): ?>
                <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
            <button id="btn-refresh" class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg">Refresh</button>
          </div>
        </div>
      </header>

      <div class="mx-auto max-w-7xl px-4 py-6">
        <!-- KPIs -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white p-5 rounded-xl shadow-sm">
            <p class="text-slate-500 text-sm">Total Item Gudang</p>
            <h3 id="kpi-total-item" class="text-3xl font-extrabold mt-1">—</h3>
            <p class="text-xs text-slate-400 mt-1">Distinct nama bahan (periode)</p>
          </div>
          <div class="bg-white p-5 rounded-xl shadow-sm">
            <p class="text-slate-500 text-sm">Stok Bersih</p>
            <h3 id="kpi-stok-bersih" class="text-3xl font-extrabold mt-1 text-emerald-600">—</h3>
            <p class="text-xs text-slate-400 mt-1">StokAwal + Masuk + Pasokan − Keluar − Dipakai</p>
          </div>
          <div class="bg-white p-5 rounded-xl shadow-sm">
            <p class="text-slate-500 text-sm">Total Pemakaian (fisik)</p>
            <h3 id="kpi-pemakaian" class="text-3xl font-extrabold mt-1 text-sky-600">—</h3>
            <p class="text-xs text-slate-400 mt-1">Σ jlh_fisik</p>
          </div>
          <div class="bg-white p-5 rounded-xl shadow-sm">
            <p class="text-slate-500 text-sm">Dokumen Pemakaian</p>
            <h3 id="kpi-doc" class="text-3xl font-extrabold mt-1 text-amber-600">—</h3>
            <p class="text-xs text-slate-400 mt-1">Count no_dokumen</p>
          </div>
        </section>

        <!-- Charts -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
          <div class="bg-white p-6 rounded-xl shadow">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold">Top Sisa Stok Gudang</h3>
              <select id="limit-stok" class="border rounded px-2 py-1 text-sm">
                <option value="5">Top 5</option><option value="10" selected>Top 10</option><option value="20">Top 20</option>
              </select>
            </div>
            <canvas id="ch-stok-top" height="180"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Tren Pemakaian (per Bulan)</h3>
            <canvas id="ch-pemakaian-tren" height="180"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Komposisi Pemakaian</h3>
            <canvas id="ch-pemakaian-komposisi" height="220"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Ringkasan Mutasi Gudang</h3>
            <canvas id="ch-mutasi-stacked" height="220"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Pemakaian per Afdeling</h3>
            <canvas id="ch-per-afdeling" height="200"></canvas>
          </div>

          <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="font-semibold mb-3">Top Bahan: Diminta vs Fisik</h3>
            <canvas id="ch-diminta-fisik" height="200"></canvas>
          </div>
        </section>
      </div>
    </main>
  </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);

  // Filter elements
  const fAfd = $('#f-afdeling');
  const fBulan = $('#f-bulan');
  const fTahun = $('#f-tahun');
  const limitStok = $('#limit-stok');
  $('#btn-refresh').addEventListener('click', () => refreshDashboard(false));

  // KPI
  const KPI = {
    total:  $('#kpi-total-item'),
    stok:   $('#kpi-stok-bersih'),
    pakai:  $('#kpi-pemakaian'),
    doc:    $('#kpi-doc')
  };

  // Chart holders
  let chStokTop, chPemTren, chKomposisi, chMutasi, chPerAfd, chDimintaFisik;

  const bulanOrder = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  const palette = n => Array.from({length:n}, (_,i)=>['#16a34a','#f59e0b','#0ea5e9'][i%3]); // G/Y/B
  const fmt = x => Number(x||0).toLocaleString(undefined,{maximumFractionDigits:2});
  const sisaStok = r => (+r.stok_awal||0)+(+r.mutasi_masuk||0)+(+r.pasokan||0)-(+r.mutasi_keluar||0)-(+r.dipakai||0);

  async function postJSON(section, extra){
    const fd = new FormData();
    fd.append('ajax','dashboard');
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('section', section);
    fd.append('afdeling', fAfd.value || '');
    fd.append('bulan',    fBulan.value || '');
    fd.append('tahun',    fTahun.value || '');
    if (extra) Object.entries(extra).forEach(([k,v])=> fd.append(k,v));
    const ctrl = new AbortController(); const t = setTimeout(()=>ctrl.abort(), 12000);
    try {
      const res = await fetch(location.href, {method:'POST', body:fd, signal:ctrl.signal});
      clearTimeout(t);
      return await res.json();
    } catch (e) {
      clearTimeout(t);
      return {success:false,error:String(e)};
    }
  }

  async function fetchStokGudang() {
    const j = await postJSON('gudang');
    return (j && j.success && Array.isArray(j.data)) ? j.data : [];
  }
  async function fetchPemakaian() {
    const j = await postJSON('pemakaian');
    return (j && j.success && Array.isArray(j.data)) ? j.data : [];
  }

  function renderOrUpdate(ctxId, cfg, holderRef){
    const ctx = document.getElementById(ctxId).getContext('2d');
    if (holderRef.value) { holderRef.value.data = cfg.data; holderRef.value.options = cfg.options||holderRef.value.options; holderRef.value.update(); }
    else { holderRef.value = new Chart(ctx, cfg); }
  }
  const barCfg  = (labels, datasets, stacked=false, horizontal=false)=>({type:'bar', data:{labels,datasets}, options:{responsive:true, indexAxis:horizontal?'y':'x', plugins:{legend:{display:true}}, scales: stacked?{x:{stacked:true},y:{stacked:true}}:{}}});
  const lineCfg = (labels, dataset)=>({type:'line', data:{labels, datasets:[dataset]}, options:{responsive:true, plugins:{legend:{display:true}}, tension:.3}});
  const pieCfg  = (labels, data)=>({type:'pie', data:{labels, datasets:[{data, backgroundColor: palette(labels.length)}]}, options:{responsive:true, plugins:{legend:{position:'right'}}}});

  async function refreshDashboard(showLoading=true){
    if (showLoading) KPI.total.textContent = KPI.stok.textContent = KPI.pakai.textContent = KPI.doc.textContent = '…';

    const [rowsG, rowsP] = await Promise.all([fetchStokGudang(), fetchPemakaian()]);

    // ===== KPI =====
    const distinct = new Set(rowsG.map(r=>r.nama_bahan));
    KPI.total.textContent = fmt(distinct.size);
    KPI.stok.textContent  = fmt(rowsG.reduce((a,r)=>a+sisaStok(r),0));
    KPI.pakai.textContent = fmt(rowsP.reduce((a,r)=>a+(+r.jlh_fisik||0),0));
    KPI.doc.textContent   = fmt(rowsP.length);

    // ===== Top Sisa Stok =====
    const aggStok = {};
    rowsG.forEach(r => { const k=r.nama_bahan||'-'; aggStok[k]=(aggStok[k]||0)+sisaStok(r); });
    const top = Object.entries(aggStok).sort((a,b)=>b[1]-a[1]).slice(0, parseInt(limitStok.value,10)||10);
    renderOrUpdate('ch-stok-top',
      barCfg(top.map(x=>x[0]), [{label:'Sisa Stok', data: top.map(x=>x[1]), backgroundColor: palette(top.length)}], false, true),
      {get value(){return chStokTop}, set value(v){chStokTop=v}}
    );

    // ===== Tren Pemakaian =====
    const mapTren = new Map(bulanOrder.map(b=>[b,0]));
    rowsP.forEach(r=> mapTren.set(r.bulan, (mapTren.get(r.bulan)||0) + (+r.jlh_fisik||0)));
    renderOrUpdate('ch-pemakaian-tren',
      lineCfg(bulanOrder, {label:`Pemakaian ${fTahun.value||'Tahun ini'}`, data: bulanOrder.map(b=>mapTren.get(b)||0), borderColor:'#0ea5e9', backgroundColor:'rgba(14,165,233,.14)', fill:true}),
      {get value(){return chPemTren}, set value(v){chPemTren=v}}
    );

    // ===== Komposisi Pemakaian =====
    const kom = {};
    rowsP.forEach(r=> { const k=r.nama_bahan||'-'; kom[k]=(kom[k]||0)+(+r.jlh_fisik||0); });
    const komArr = Object.entries(kom).sort((a,b)=>b[1]-a[1]);
    const main = komArr.slice(0,9); const other = komArr.slice(9).reduce((a,b)=>a+(b[1]||0),0);
    if (other>0) main.push(['Lainnya',other]);
    renderOrUpdate('ch-pemakaian-komposisi',
      pieCfg(main.map(x=>x[0]), main.map(x=>x[1])),
      {get value(){return chKomposisi}, set value(v){chKomposisi=v}}
    );

    // ===== Mutasi Gudang (stacked) =====
    const sum = rowsG.reduce((o,r)=>{o.masuk+=(+r.mutasi_masuk||0);o.keluar+=(+r.mutasi_keluar||0);o.pasokan+=(+r.pasokan||0);o.dipakai+=(+r.dipakai||0);return o;},{masuk:0,keluar:0,pasokan:0,dipakai:0});
    renderOrUpdate('ch-mutasi-stacked',
      barCfg(['Mutasi'], [
        {label:'Masuk', data:[sum.masuk],   backgroundColor:'#16a34a'},
        {label:'Keluar', data:[sum.keluar], backgroundColor:'#f59e0b'},
        {label:'Pasokan', data:[sum.pasokan], backgroundColor:'#22c55e'},
        {label:'Dipakai', data:[sum.dipakai], backgroundColor:'#0ea5e9'}
      ], true, false),
      {get value(){return chMutasi}, set value(v){chMutasi=v}}
    );

    // ===== Pemakaian per Afdeling =====
    const perAfd = {};
    rowsP.forEach(r=>{ const k=r.afdeling||'-'; perAfd[k]=(perAfd[k]||0)+(+r.jlh_fisik||0); });
    const afdArr = Object.entries(perAfd).sort((a,b)=>b[1]-a[1]);
    renderOrUpdate('ch-per-afdeling',
      barCfg(afdArr.map(x=>x[0]), [{label:'Fisik', data: afdArr.map(x=>x[1]), backgroundColor:'#0ea5e9'}]),
      {get value(){return chPerAfd}, set value(v){chPerAfd=v}}
    );

    // ===== Diminta vs Fisik =====
    const df = {};
    rowsP.forEach(r=>{ const k=r.nama_bahan||'-'; if(!df[k]) df[k]={diminta:0,fisik:0}; df[k].diminta+=(+r.jlh_diminta||0); df[k].fisik+=(+r.jlh_fisik||0); });
    const dfArr = Object.entries(df).sort((a,b)=>b[1].fisik-a[1].fisik).slice(0,10);
    renderOrUpdate('ch-diminta-fisik',
      barCfg(
        dfArr.map(x=>x[0]),
        [
          {label:'Diminta', data: dfArr.map(x=>x[1].diminta), backgroundColor:'#f59e0b'},
          {label:'Fisik',   data: dfArr.map(x=>x[1].fisik),   backgroundColor:'#0ea5e9'}
        ]
      ),
      {get value(){return chDimintaFisik}, set value(v){chDimintaFisik=v}}
    );
  }

  // Listeners + realtime interval
  [fAfd, fBulan, fTahun, limitStok].forEach(el => el.addEventListener('change', () => refreshDashboard(false)));
  refreshDashboard(true);
  setInterval(()=>refreshDashboard(false), 30000);
});
</script>
</body>
</html>
