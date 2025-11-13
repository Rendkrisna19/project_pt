<?php
// pages/pemeliharaan_tbm3.php — Rekap TM (grup per Jenis, subtotal RY A/RY B, Grand Total)
// Modif: pakai field 'ket' (bukan 'keterangan') untuk tabel & form, filter pakai 'ket',
// header bercabang, +/- warna, kolom Anggaran non-sticky, HAPUS baris "Total per Bulan (Grand)",
// HAPUS kolom "Keterangan" di ujung kanan.

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php");
  exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

$AFDS = ['AFD01','AFD02','AFD03','AFD04','AFD05','AFD06','AFD07','AFD08','AFD09','AFD10'];

/* ===== Filter ===== */
$f_tahun = ($_GET['tahun'] ?? '') === '' ? (int)date('Y') : (int)$_GET['tahun'];
$f_afd   = trim((string)($_GET['afd'] ?? ''));
$f_hk    = trim((string)($_GET['hk'] ?? ''));       // kode HK
$f_ket   = trim((string)($_GET['ket'] ?? ''));      // PAKAI 'ket' untuk pencarian teks
$f_jenis = trim((string)($_GET['jenis'] ?? ''));    // nama jenis (untuk filter tampil)

/* Master jenis (untuk filter) */
$jenisMaster = $conn->query("SELECT nama FROM md_pemeliharaan_tbm3 ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);

/* Master tenaga (untuk filter HK) */
$TENAGA = $conn->query("SELECT id, kode FROM md_tenaga ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC);

/* Jenis untuk modal (id+nama) supaya bisa datalist */
$JENIS_ROWS = $conn->query("SELECT id, nama FROM md_pemeliharaan_tbm3 ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

/* Kolom & colspan dinamis */
$monthLabels = ['Jan','Feb','Mar','Apr','Mei','Juni','Juli','Agust','Sept','Okt','Nov','Des'];
$monthKeys   = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des']; // match DB/CRUD
// 7 kolom awal + 1 Anggaran + 12 bulan + 1 Jumlah + 1 +/- + 1 Progress + (1 Aksi jika admin)
$COLS_TOTAL  = 7 + 1 + count($monthLabels) + 1 + 1 + 1 + ($isStaf ? 0 : 1);

$currentPage = 'pemeliharaan_tbm3';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  .freeze-parent{max-height:70vh; overflow:auto}
  .table-wrap{overflow:auto}
  table.rekap{width:100%; border-collapse:separate; border-spacing:0}
  table.rekap th, table.rekap td{
    padding:.60rem .70rem; border-bottom:1px solid #e5e7eb; font-size:.88rem; white-space:nowrap;
  }
  table.rekap thead th{
    position:sticky; top:0; z-index:5; background:#1546b0; color:#fff;
  }
  table.rekap thead tr:nth-child(1) th { z-index:6; }
  table.rekap thead tr:nth-child(2) th { z-index:5; }

  tr.group-head td{
    background: linear-gradient(90deg, #e9f1ff 0%, #edf3ff 60%, #f6f9ff 100%);
    border-top:2px solid #c4d6ff; font-weight:700; color:#123;
  }
  tr.sum-jenis td{background:#e8f5e9; font-weight:700}
  tr.sum-rayon td{background:#fff7ed; font-weight:700}
  tr.sum-grand td{background:#dcfce7; font-weight:800}

  .text-right{text-align:right}
  .text-center{text-align:center}

  .toolbar{display:grid;grid-template-columns:repeat(12,1fr);gap:.75rem}
  .toolbar > * {grid-column: span 12;}
  @media (min-width: 768px){
    .toolbar > .md-span-2{grid-column: span 2;}
    .toolbar > .md-span-3{grid-column: span 3;}
    .toolbar > .md-span-4{grid-column: span 4;}
  }
  .btn{display:inline-flex;align-items:center;gap:.45rem;border:1px solid #059669;background:#059669;color:#fff;border-radius:.6rem;padding:.45rem .9rem}
  .btn-gray{border:1px solid #cbd5e1;background:#fff;color:#111827}
  .act{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:.5rem;border:1px solid #e5e7eb;background:#fff}

  /* +/- warna */
  .delta-pos{ color:#dc2626; font-weight:600; }   /* merah (over) */
  .delta-neg{ color:#16a34a; font-weight:600; }   /* hijau (hemat) */

  /* highlight kolom anggaran (non-sticky) */
  .cell-anggaran{ background:rgba(21,70,176,.06); }
</style>

<div class="space-y-6">
  <div>
    <h1 class="text-3xl font-bold">Pemeliharaan TBM III</h1>
  </div>

  <form method="GET" class="bg-white p-4 rounded-xl shadow toolbar">
    <div class="md-span-2">
      <label class="text-xs font-semibold mb-1 block">Tahun</label>
      <input type="number" name="tahun" min="2000" max="2100"
            value="<?= htmlspecialchars($f_tahun) ?>" class="w-full border rounded-lg px-3 py-2">
    </div>
    <div class="md-span-3">
      <label class="text-xs font-semibold mb-1 block">AFD</label>
      <select name="afd" class="w-full border rounded-lg px-3 py-2">
        <option value="">— Semua AFD —</option>
        <?php foreach($AFDS as $a): ?>
          <option value="<?= $a ?>" <?= $f_afd===$a?'selected':'' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="md-span-4">
      <label class="text-xs font-semibold mb-1 block">Jenis Pekerjaan</label>
      <select name="jenis" class="w-full border rounded-lg px-3 py-2">
        <option value="">— Semua Jenis —</option>
        <?php foreach($jenisMaster as $jn): ?>
          <option value="<?= htmlspecialchars($jn) ?>" <?= $f_jenis===$jn?'selected':'' ?>><?= htmlspecialchars($jn) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="md-span-2">
      <label class="text-xs font-semibold mb-1 block">HK</label>
      <select name="hk" class="w-full border rounded-lg px-3 py-2">
        <option value="">— Semua HK —</option>
        <?php foreach($TENAGA as $t): ?>
          <option value="<?= htmlspecialchars($t['kode']) ?>" <?= $f_hk === $t['kode'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['kode']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="md-span-3">
      <label class="text-xs font-semibold mb-1 block">Ket</label>
      <input name="ket" value="<?= htmlspecialchars($f_ket) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Cari ket…">
    </div>
    <div class="md-span-2 flex items-end gap-2">
      <button class="btn"><i class="ti ti-filter"></i> Terapkan</button>
      <a href="pemeliharaan_tbm3.php" class="btn btn-gray"><i class="ti ti-refresh"></i> Reset</a>
    </div>
  </form>

  <div class="bg-white rounded-xl shadow freeze-parent">
    <div class="px-3 py-2 border-b flex items-center gap-3">
      <div class="font-semibold text-gray-700 flex-1">
        Rekap TBM III • Tahun <?= htmlspecialchars($f_tahun) ?> <?= $f_afd ? "• $f_afd" : '' ?>
      </div>
      <?php if(!$isStaf): ?>
        <button id="btn-add" class="btn"><i class="ti ti-plus"></i> Tambah Data</button>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="rekap" id="tm-table">
        <thead>
          <tr>
            <th rowspan="2">Tahun</th>
            <th rowspan="2">Kebun</th>
            <th rowspan="2">Rayon</th>
            <th rowspan="2">Unit/Devisi</th>
            <th rowspan="2">Ket</th>
            <th rowspan="2">HK</th>
            <th rowspan="2">Sat</th>
            <th rowspan="2" class="text-right">Anggaran 1 Tahun</th>
            <th colspan="<?= count($monthLabels) ?>" class="text-center">Realisasi</th>
            <th rowspan="2" class="text-right">Jumlah</th>
            <th rowspan="2" class="text-right">+/- Anggaran</th>
            <th rowspan="2" class="text-right">Progress</th>
            <?php if(!$isStaf): ?><th rowspan="2" class="text-center">Aksi</th><?php endif; ?>
          </tr>
          <tr>
            <?php foreach($monthLabels as $m): ?>
              <th class="text-right"><?= $m ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="tm-body">
          <tr><td colspan="<?= $COLS_TOTAL ?>" class="text-center py-6 text-gray-500">Memuat…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const IS_STAF = <?= $isStaf ? 'true' : 'false' ?>;
  const CSRF    = '<?= htmlspecialchars($CSRF) ?>';
  const months  = <?= json_encode($monthKeys) ?>;

  const nf     = (n)=> Number(n||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
  const dash   = (n)=> (Number(n||0)===0 ? '—' : nf(n));
  const dashPct= (n)=> (Number(n||0)===0 ? '—' : `${nf(n)}%`);

  const DEFAULT_AFD = '<?= $f_afd ?: 'AFD01' ?>';
  const btnAdd = document.getElementById('btn-add');
  if (btnAdd) btnAdd.addEventListener('click', ()=> openForm({ unit_kode: DEFAULT_AFD, tahun: '<?= $f_tahun ?>' }));

  // Logika Rayon berdasarkan AFD
  const rayonA_AFDS = ['AFD02','AFD03','AFD04','AFD05','AFD06'];
  const rayonB_AFDS = ['AFD01','AFD07','AFD08','AFD09','AFD10'];

  (async function loadAll(){
    const qs = new URLSearchParams({
      action:'list',
      tahun:'<?= $f_tahun ?>',
      afd:'<?= addslashes($f_afd) ?>',
      hk:'<?= addslashes($f_hk) ?>',
      ket:'<?= addslashes($f_ket) ?>',
      jenis:'<?= addslashes($f_jenis) ?>'
    });
    const res = await fetch('pemeliharaan_tbm3_crud.php?'+qs.toString(), {credentials:'same-origin'});
    const j = await res.json();
    const tbody = document.getElementById('tm-body');

    if (!j.success){
      tbody.innerHTML = `<tr><td colspan="<?= $COLS_TOTAL ?>" class="text-center py-6 text-red-600">${j.message||'Error'}</td></tr>`;
      return;
    }

    const rows  = j.rows||[];
    const order = j.jenis_order||[]; // urutan jenis dari master
    const kebunNama = j.kebun_nama || 'Sei Rokan';

    // regroup per jenis
    const byJenis = {};
    order.forEach(jn => byJenis[jn] = []);
    rows.forEach(r=>{
      const jn = r.jenis_nama || 'Tidak Berjenis';
      if (!byJenis[jn]) byJenis[jn]=[];
      byJenis[jn].push(r);
    });

    // GRAND accumulator
    let grand = {anggaran:0, jumlah:0};

    let html = '';
    const emptyRow = (jn)=>`<tr class="text-gray-500"><td colspan="<?= $COLS_TOTAL ?>">Tidak ada data untuk jenis <b>${jn}</b> pada filter ini.</td></tr>`;

    for (const jn of order){
      html += `<tr class="group-head"><td colspan="<?= $COLS_TOTAL ?>"><b>${jn}</b></td></tr>`;
      const list = (byJenis[jn]||[]).sort((a,b)=> (a.unit_kode||'').localeCompare(b.unit_kode||'') || (a.id - b.id));
      if (!list.length) { html += emptyRow(jn); continue; }

      // accumulator jenis & rayon
      const sumJenis = {anggaran:0, jumlah:0};
      const sumRY = {'RY A':{anggaran:0,jumlah:0}, 'RY B':{anggaran:0,jumlah:0}};
      const perBulanJenis = Object.fromEntries(months.map(m=>[m,0]));
      const perBulanRYA   = Object.fromEntries(months.map(m=>[m,0]));
      const perBulanRYB   = Object.fromEntries(months.map(m=>[m,0]));

      for (const r of list){
        const jml = months.reduce((a,m)=> a + Number(r[m]||0), 0);
        const delt= jml - Number(r.anggaran_tahun||0);
        const prog= Number(r.anggaran_tahun||0) > 0 ? (jml/Number(r.anggaran_tahun)*100) : 0;
        const deltStr = Number(delt||0)===0 ? '—' : nf(delt);
        const deltCls = delt < 0 ? 'delta-neg' : (delt > 0 ? 'delta-pos' : '');

        sumJenis.anggaran += Number(r.anggaran_tahun||0);
        sumJenis.jumlah   += jml;

        // Rayon label
        const unit = r.unit_kode || '';
        let rayonLabel = r.rayon_nama || '';
        const isRayonA = rayonA_AFDS.includes(unit);
        const isRayonB = rayonB_AFDS.includes(unit);
        if (!rayonLabel) { if (isRayonA) rayonLabel = 'RY A'; else if (isRayonB) rayonLabel = 'RY B'; }

        // Akumulasi bulanan
        months.forEach(m=>{
          const val = Number(r[m]||0);
          perBulanJenis[m] += val;
          if (isRayonA) perBulanRYA[m] += val;
          else if (isRayonB) perBulanRYB[m] += val;
        });

        if (isRayonA) { sumRY['RY A'].anggaran += Number(r.anggaran_tahun||0); sumRY['RY A'].jumlah += jml; }
        else if (isRayonB){ sumRY['RY B'].anggaran += Number(r.anggaran_tahun||0); sumRY['RY B'].jumlah += jml; }

        html += `
          <tr>
            <td>${r.tahun||''}</td>
            <td>${kebunNama}</td>
            <td>${rayonLabel||''}</td>
            <td>${r.unit_kode||''}</td>
            <td>${r.ket||''}</td>
            <td>${r.hk||''}</td>
            <td>${r.satuan||''}</td>
            <td class="text-right cell-anggaran">${dash(r.anggaran_tahun)}</td>
            ${months.map(m=>`<td class="text-right">${dash(r[m])}</td>`).join('')}
            <td class="text-right">${dash(jml)}</td>
            <td class="text-right ${deltCls}">${deltStr}</td>
            <td class="text-right">${dashPct(prog)}</td>
            <?php if(!$isStaf): ?>
            <td class="text-center">
              <button class="act" title="Edit" data-edit='${JSON.stringify(r).replaceAll("'","&apos;")}'><i class="ti ti-pencil"></i></button>
              <button class="act" title="Hapus" data-del="${r.id}"><i class="ti ti-trash text-red-600"></i></button>
            </td>
            <?php endif; ?>
          </tr>`;
      }

      // Subtotal per jenis
      const deltaJenis = (sumJenis.jumlah - sumJenis.anggaran);
      html += `
        <tr class="sum-jenis">
          <td colspan="7"><b>Jumlah (${jn})</b></td>
          <td class="text-right cell-anggaran"><b>${dash(sumJenis.anggaran)}</b></td>
          ${months.map(m=>`<td class="text-right"><b>${dash(perBulanJenis[m])}</b></td>`).join('')}
          <td class="text-right"><b>${dash(sumJenis.jumlah)}</b></td>
          <td class="text-right ${(deltaJenis<0)?'delta-neg':(deltaJenis>0?'delta-pos':'')}"><b>${Number(deltaJenis||0)===0?'—':nf(deltaJenis)}</b></td>
          <td class="text-right"><b>${sumJenis.anggaran>0?dashPct(sumJenis.jumlah/sumJenis.anggaran*100):'—'}</b></td>
          <?= $isStaf ? '' : '<td></td>' ?>
        </tr>
      `;

      // Subtotal RY A
      const dRYA = sumRY['RY A'].jumlah - sumRY['RY A'].anggaran;
      html += `
        <tr class="sum-rayon">
          <td colspan="7"><b>Jumlah RY A</b></td>
          <td class="text-right cell-anggaran"><b>${dash(sumRY['RY A'].anggaran)}</b></td>
          ${months.map(m=>`<td class="text-right"><b>${dash(perBulanRYA[m])}</b></td>`).join('')}
          <td class="text-right"><b>${dash(sumRY['RY A'].jumlah)}</b></td>
          <td class="text-right ${(dRYA<0)?'delta-neg':(dRYA>0?'delta-pos':'')}"><b>${Number(dRYA||0)===0?'—':nf(dRYA)}</b></td>
          <td class="text-right"><b>${sumRY['RY A'].anggaran>0?dashPct(sumRY['RY A'].jumlah/sumRY['RY A'].anggaran*100):'—'}</b></td>
          <?= $isStaf ? '' : '<td></td>' ?>
        </tr>
      `;

      // Subtotal RY B
      const dRYB = sumRY['RY B'].jumlah - sumRY['RY B'].anggaran;
      html += `
        <tr class="sum-rayon">
          <td colspan="7"><b>Jumlah RY B</b></td>
          <td class="text-right cell-anggaran"><b>${dash(sumRY['RY B'].anggaran)}</b></td>
          ${months.map(m=>`<td class="text-right"><b>${dash(perBulanRYB[m])}</b></td>`).join('')}
          <td class="text-right"><b>${dash(sumRY['RY B'].jumlah)}</b></td>
          <td class="text-right ${(dRYB<0)?'delta-neg':(dRYB>0?'delta-pos':'')}"><b>${Number(dRYB||0)===0?'—':nf(dRYB)}</b></td>
          <td class="text-right"><b>${sumRY['RY B'].anggaran>0?dashPct(sumRY['RY B'].jumlah/sumRY['RY B'].anggaran*100):'—'}</b></td>
          <?= $isStaf ? '' : '<td></td>' ?>
        </tr>
      `;

      grand.anggaran += sumJenis.anggaran;
      grand.jumlah   += sumJenis.jumlah;
    }

   

    tbody.innerHTML = html;

    // Bind edit/delete
    if (!IS_STAF){
      tbody.querySelectorAll('[data-edit]').forEach(b=>{
        b.addEventListener('click', ()=> openForm(JSON.parse(b.dataset.edit||'{}')));
      });
      tbody.querySelectorAll('[data-del]').forEach(b=>{
        b.addEventListener('click', async ()=>{
          const id = b.dataset.del;
          const y = await Swal.fire({
            title:'Hapus data ini?', icon:'warning',
            showCancelButton:true, confirmButtonText:'Hapus', cancelButtonText:'Batal'
          });
          if(!y.isConfirmed) return;
          const fd=new FormData();
          fd.append('csrf_token',CSRF);
          fd.append('action','delete');
          fd.append('id',id);
          const r = await fetch('pemeliharaan_tbm3_crud.php',{method:'POST',body:fd});
          const jj=await r.json();
          if (jj.success){ Swal.fire('Berhasil','Data dihapus','success'); location.reload(); }
          else { Swal.fire('Gagal',jj.message||'Error','error'); }
        });
      });
    }
  })();

  /* ===== Modal (Create/Update) ===== */
  let MODAL=null;
  function modalTpl(){return `
<div id="tm-modal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl w-full max-w-5xl shadow-xl">
    <div class="flex items-center justify-between p-4 border-b">
      <h3 id="tm-title" class="font-bold">Form TM</h3>
      <button id="tm-x" class="text-xl">&times;</button>
    </div>
    <form id="tm-form" class="p-4 grid grid-cols-12 gap-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" value="store">
      <input type="hidden" name="id" value="">
      <div class="col-span-2"><label class="text-xs font-semibold">Tahun</label><input name="tahun" type="number" min="2000" max="2100" class="w-full border rounded px-3 py-2" required value="<?= htmlspecialchars($f_tahun) ?>"></div>
      <div class="col-span-2"><label class="text-xs font-semibold">AFD</label>
        <select name="unit_kode" class="w-full border rounded px-3 py-2" required>
          <?php foreach($AFDS as $a){ echo '<option value="'.$a.'">'.$a.'</option>'; } ?>
        </select>
      </div>
      <div class="col-span-3"><label class="text-xs font-semibold">Rayon</label>
        <select name="rayon_id" class="w-full border rounded px-3 py-2">
          <option value="">— Pilih —</option>
          <?php foreach($conn->query("SELECT id,nama FROM md_rayon ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC) as $x){ echo '<option value="'.$x['id'].'">'.htmlspecialchars($x['nama']).'</option>'; } ?>
        </select>
      </div>

      <!-- Jenis Pekerjaan: search (datalist) + hidden id -->
      <div class="col-span-5">
        <label class="text-xs font-semibold">Jenis Pekerjaan</label>
        <input id="jenis_text" class="w-full border rounded px-3 py-2" list="jenis_datalist" placeholder="Ketik untuk mencari..." autocomplete="off" required>
        <datalist id="jenis_datalist">
          <?php foreach($JENIS_ROWS as $jr){ echo '<option data-id="'.$jr['id'].'" value="'.htmlspecialchars($jr['nama']).'"></option>'; } ?>
        </datalist>
        <input type="hidden" name="jenis_id" id="jenis_id">
      </div>

      <div class="col-span-2"><label class="text-xs font-semibold">HK (Tenaga)</label>
        <select name="hk_id" id="hk_id" class="w-full border rounded px-3 py-2">
          <option value="">— Pilih —</option>
          <?php foreach($TENAGA as $t){ echo '<option value="'.$t['id'].'">'.htmlspecialchars($t['kode']).'</option>'; } ?>
        </select>
        <input type="hidden" name="hk" id="hk_hidden" value="">
      </div>

      <div class="col-span-2"><label class="text-xs font-semibold">Sat</label><input name="satuan" class="w-full border rounded px-3 py-2" placeholder="Ha"></div>
      <div class="col-span-5"><label class="text-xs font-semibold">Anggaran 1 Tahun</label><input name="anggaran_tahun" inputmode="decimal" class="w-full border rounded px-3 py-2"></div>
      <?php foreach($monthKeys as $m): ?>
        <div class="col-span-2"><label class="text-xs font-semibold"><?= strtoupper($m) ?></label><input name="<?= $m ?>" inputmode="decimal" class="w-full border rounded px-3 py-2"></div>
      <?php endforeach; ?>

      <!-- PAKAI 'ket' saja -->
      <div class="col-span-12"><label class="text-xs font-semibold">Ket</label>
        <textarea name="ket" rows="2" class="w-full border rounded px-3 py-2" placeholder="Catatan / keterangan"></textarea>
      </div>

      <div class="col-span-12 flex justify-end gap-2 mt-2">
        <button type="button" id="tm-cancel" class="btn btn-gray">Batal</button>
        <button class="btn">Simpan</button>
      </div>
    </form>
  </div>
</div>`}

  function openForm(d={}) {
    if (MODAL) MODAL.remove();
    document.body.insertAdjacentHTML('beforeend', modalTpl());
    MODAL=document.getElementById('tm-modal');
    const F=document.getElementById('tm-form'); const T=document.getElementById('tm-title');

    F.action.value = d.id ? 'update' : 'store';
    if (!d.id && (!d.unit_kode)) d.unit_kode = '<?= $f_afd ?: 'AFD01' ?>';

    ['id','tahun','satuan','anggaran_tahun','ket',
     'jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'
    ].forEach(k=>{ if (F[k]!==undefined) F[k].value = d[k] ?? ''; });

    if (d.unit_kode && F['unit_kode']) F['unit_kode'].value = d.unit_kode;

    // rayon id prefill
    if (d.rayon_id) {
        F['rayon_id'].value = d.rayon_id;
    } else if (d.rayon_nama && F['rayon_id']){
      [...F['rayon_id'].options].forEach(o=>{ if (o.text.trim()===String(d.rayon_nama||'').trim()) F['rayon_id'].value=o.value; });
    }

    // Jenis (datalist): isi teks + hidden id
    const jenisText = document.getElementById('jenis_text');
    const jenisId   = document.getElementById('jenis_id');
    if (d.jenis_id) {
      jenisId.value = d.jenis_id;
      const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o=>o.dataset.id==String(d.jenis_id));
      if (opt) jenisText.value = opt.value;
    } else if (d.jenis_nama) {
      jenisText.value = d.jenis_nama;
      const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o=>o.value.trim()===String(d.jenis_nama).trim());
      if (opt) jenisId.value = opt.dataset.id || '';
    }
    const syncJenis = ()=>{
      const val = jenisText.value.trim();
      const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o=>o.value.trim()===val);
      jenisId.value = opt ? (opt.dataset.id||'') : '';
    };
    jenisText.addEventListener('change', syncJenis);
    jenisText.addEventListener('input',  syncJenis);

    // HK (select id) + hidden hk (kode)
    const selHK = document.getElementById('hk_id');
    const hidHK = document.getElementById('hk_hidden');
    if (d.hk_id && selHK){ selHK.value = String(d.hk_id); hidHK.value = selHK.options[selHK.selectedIndex]?.text || ''; }
    else if (d.hk && selHK){
      [...selHK.options].forEach(o=>{ if (o.text.trim()===String(d.hk||'').trim()) selHK.value=o.value; });
      hidHK.value = d.hk || '';
    }
    selHK?.addEventListener('change', e=>{
      hidHK.value = e.target.options[e.target.selectedIndex]?.text || '';
    });

    T.textContent = (d.id?'Edit':'Tambah')+' Data TM';

    const close=()=>MODAL.remove();
    document.getElementById('tm-x').onclick=close;
    document.getElementById('tm-cancel').onclick=close;

    F.onsubmit = async (e)=>{
      e.preventDefault();

      // Validasi: jenis_id harus terisi dari datalist
      const jenisId = F.querySelector('#jenis_id');
      const jenisText = F.querySelector('#jenis_text');
      if (!jenisId.value) {
        await Swal.fire('Validasi', 'Silakan pilih Jenis Pekerjaan dari daftar yang muncul.', 'warning');
        jenisText.focus(); return;
      }

      const fd=new FormData(F);
      const r = await fetch('pemeliharaan_tbm3_crud.php',{method:'POST',body:fd});
      const j = await r.json();
      if (j.success){
        await Swal.fire('Berhasil', j.message||'Tersimpan','success');
        close(); location.reload();
      } else {
        Swal.fire('Gagal', (j.errors||[]).map(x=>'• '+x).join('<br>') || j.message || 'Error', 'error');
      }
    }
  }

  // Shortcut tambah data: Ctrl/Cmd + N
  <?php if(!$isStaf): ?>
  document.addEventListener('keydown', (e)=>{ 
    if (e.key==='n' && (e.ctrlKey||e.metaKey)){ 
      e.preventDefault(); 
      openForm({ unit_kode: '<?= $f_afd ?: 'AFD01' ?>', tahun: '<?= $f_tahun ?>' }); 
    }
  });
  <?php endif; ?>
});
</script>
