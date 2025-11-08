<?php
// pages/pemeliharaan_mn.php — Rekap mn (tanpa Jumlah/Total), ada Kebun & Stood
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf   = ($userRole === 'staf');

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

$AFDS = ['AFD01','AFD02','AFD03','AFD04','AFD05','AFD06','AFD07','AFD08','AFD09','AFD10'];

/* ===== Filter ===== */
$f_tahun = ($_GET['tahun']??'')==='' ? (int)date('Y') : (int)$_GET['tahun'];
$f_afd   = trim((string)($_GET['afd'] ?? ''));
$f_hk    = trim((string)($_GET['hk']??''));
$f_ket   = trim((string)($_GET['keterangan']??''));
$f_jenis = trim((string)($_GET['jenis']??''));
$f_kebun = (int)($_GET['kebun_id'] ?? 0);
$f_stood = trim((string)($_GET['stood'] ?? ''));

/* Master dropdown */
$jenisMaster = $conn->query("SELECT nama FROM md_pemeliharaan_mn ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
$kebunList   = $conn->query("SELECT id, nama_kebun AS nama FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$TENAGA      = $conn->query("SELECT id, kode FROM md_tenaga ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC);

/* Table columns */
$monthLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Agust','Sept','Okt','Nov','Des'];
$monthKeys   = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des']; // match DB
$COLS_TOTAL  = 8 + count($monthLabels) + 1 + ($isStaf ? 0 : 1); 
// Kolom: Tahun,Kebun,Stood,Jenis, Ket, HK, Sat, Anggaran + 12 bulan + +/- Anggaran + Aksi?

$currentPage = 'pemeliharaan_mn';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  .freeze-parent{max-height:70vh; overflow:auto}
  .table-wrap{overflow:auto}
  table.rekap{width:100%; border-collapse:separate; border-spacing:0}
  table.rekap th, table.rekap td{ padding:.60rem .70rem; border-bottom:1px solid #e5e7eb; font-size:.88rem; white-space:nowrap; }
  table.rekap thead th{ position:sticky; top:0; z-index:5; background:#1546b0; color:#fff; }
  .text-right{text-align:right} .text-center{text-align:center}
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
</style>

<div class="space-y-6">
  <div><h1 class="text-3xl font-bold">Pemeliharaan mn</h1></div>

  <!-- Filter -->
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
      <label class="text-xs font-semibold mb-1 block">Kebun</label>
      <select name="kebun_id" class="w-full border rounded-lg px-3 py-2">
        <option value="0">— Semua Kebun —</option>
        <?php foreach($kebunList as $kb): ?>
          <option value="<?= (int)$kb['id'] ?>" <?= $f_kebun===(int)$kb['id']?'selected':'' ?>><?= htmlspecialchars($kb['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="md-span-3">
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
      <input name="hk" value="<?= htmlspecialchars($f_hk) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="TP / TS">
    </div>
    <div class="md-span-2">
      <label class="text-xs font-semibold mb-1 block">Stood</label>
      <input name="stood" value="<?= htmlspecialchars($f_stood) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="...">
    </div>
    <div class="md-span-3">
      <label class="text-xs font-semibold mb-1 block">Keterangan</label>
      <input name="keterangan" value="<?= htmlspecialchars($f_ket) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Datar / ...">
    </div>
    <div class="md-span-2 flex items-end gap-2">
      <button class="btn"><i class="ti ti-filter"></i> Terapkan</button>
      <a href="pemeliharaan_mn.php" class="btn btn-gray"><i class="ti ti-refresh"></i> Reset</a>
    </div>
  </form>

  <!-- Tabel -->
  <div class="bg-white rounded-xl shadow freeze-parent">
    <div class="px-3 py-2 border-b flex items-center gap-3">
      <div class="font-semibold text-gray-700 flex-1">
        Rekap mn • Tahun <?= htmlspecialchars($f_tahun) ?> <?= $f_afd ? "• $f_afd" : '' ?>
      </div>
      <?php if(!$isStaf): ?>
        <button id="btn-add" class="btn"><i class="ti ti-plus"></i> Tambah Data</button>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="rekap" id="mn-table">
        <thead>
        <tr>
          <th>Tahun</th>
          <th>Kebun</th>
          <th>Stood</th>
          <th>Jenis Pekerjaan</th>
          <th>Ket</th>
          <th>HK</th>
          <th>Sat</th>
          <th class="text-right">Anggaran 1 Tahun</th>
          <?php foreach($monthLabels as $m): ?>
            <th class="text-right"><?= $m ?></th>
          <?php endforeach; ?>
          <th class="text-right">+/- Anggaran</th>
          <?php if(!$isStaf): ?><th class="text-center">Aksi</th><?php endif; ?>
        </tr>
        </thead>
        <tbody id="mn-body">
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

  const DEFAULT_AFD = '<?= $f_afd ?: 'AFD01' ?>';
  const btnAdd = document.getElementById('btn-add');
  if (btnAdd) btnAdd.addEventListener('click', ()=> openForm({ unit_kode: DEFAULT_AFD, tahun: '<?= $f_tahun ?>' }));

  (async function loadAll(){
    const qs = new URLSearchParams({
      action:'list',
      tahun:'<?= $f_tahun ?>',
      afd:'<?= addslashes($f_afd) ?>',
      hk:'<?= addslashes($f_hk) ?>',
      keterangan:'<?= addslashes($f_ket) ?>',
      jenis:'<?= addslashes($f_jenis) ?>',
      kebun_id:'<?= (int)$f_kebun ?>',
      stood:'<?= addslashes($f_stood) ?>'
    });
    const res = await fetch('pemeliharaan_mn_crud.php?'+qs.toString(), {credentials:'same-origin'});
    const j = await res.json();
    const tbody = document.getElementById('mn-body');

    if (!j.success){
      tbody.innerHTML = `<tr><td colspan="<?= $COLS_TOTAL ?>" class="text-center py-6 text-red-600">${j.message||'Error'}</td></tr>`;
      return;
    }

    const rows = j.rows||[];
    if (!rows.length){
      tbody.innerHTML = `<tr><td colspan="<?= $COLS_TOTAL ?>" class="text-center py-6 text-gray-500">Tidak ada data pada filter ini.</td></tr>`;
      return;
    }

    const kebunMap = j.kebun_map||{};
    let html = '';

    for (const r of rows){
      const jml = months.reduce((a,m)=> a + Number(r[m]||0), 0);
      const delt= jml - Number(r.anggaran_tahun||0);

      html += `
      <tr>
        <td>${r.tahun||''}</td>
        <td>${r.kebun_nama || kebunMap[r.kebun_id] || ''}</td>
        <td>${r.stood||''}</td>
        <td>${r.jenis_nama||''}</td>
        <td>${r.ket||''}</td>
        <td>${r.hk||''}</td>
        <td>${r.satuan||''}</td>
        <td class="text-right">${dash(r.anggaran_tahun)}</td>
        ${months.map(m=>`<td class="text-right">${dash(r[m])}</td>`).join('')}
        <td class="text-right">${Number(delt||0)===0?'—':nf(delt)}</td>
        <?php if(!$isStaf): ?>
        <td class="text-center">
          <button class="act" title="Edit" data-edit='${JSON.stringify(r).replaceAll("'","&apos;")}'><i class="ti ti-pencil"></i></button>
          <button class="act" title="Hapus" data-del="${r.id}"><i class="ti ti-trash text-red-600"></i></button>
        </td>
        <?php endif; ?>
      </tr>`;
    }

    tbody.innerHTML = html;

    if (!IS_STAF){
      tbody.querySelectorAll('[data-edit]').forEach(b=>{
        b.addEventListener('click', ()=> openForm(JSON.parse(b.dataset.edit||'{}')));
      });
      tbody.querySelectorAll('[data-del]').forEach(b=>{
        b.addEventListener('click', async ()=>{
          const id = b.dataset.del;
          const y = await Swal.fire({title:'Hapus data ini?',icon:'warning',showCancelButton:true,confirmButtonText:'Hapus',cancelButtonText:'Batal'});
          if(!y.isConfirmed) return;
          const fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('action','delete'); fd.append('id',id);
          const r = await fetch('pemeliharaan_mn_crud.php',{method:'POST',body:fd});
          const jj=await r.json();
          if (jj.success){ Swal.fire('Berhasil','Data dihapus','success'); location.reload(); } else { Swal.fire('Gagal',jj.message||'Error','error'); }
        });
      });
    }
  })();

  /* ===== Modal (Create/Update) ===== */
  let MODAL=null;

  function modalTpl(){return `
<div id="mn-modal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl w-full max-w-5xl shadow-xl">
    <div class="flex items-center justify-between p-4 border-b">
      <h3 id="mn-title" class="font-bold">Form mn</h3>
      <button id="mn-x" class="text-xl">&times;</button>
    </div>
    <form id="mn-form" class="p-4 grid grid-cols-12 gap-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" value="store">
      <input type="hidden" name="id" value="">
      <div class="col-span-2"><label class="text-xs font-semibold">Tahun</label><input name="tahun" type="number" min="2000" max="2100" class="w-full border rounded px-3 py-2" required value="<?= htmlspecialchars($f_tahun) ?>"></div>
      <div class="col-span-2"><label class="text-xs font-semibold">AFD</label>
        <select name="unit_kode" class="w-full border rounded px-3 py-2" required>
          <?php foreach($AFDS as $a){ echo '<option value="'.$a.'">'.$a.'</option>'; } ?>
        </select>
      </div>
      <div class="col-span-4"><label class="text-xs font-semibold">Kebun</label>
        <select name="kebun_id" class="w-full border rounded px-3 py-2">
          <option value="">— Pilih —</option>
          <?php foreach($kebunList as $kb){ echo '<option value="'.$kb['id'].'">'.htmlspecialchars($kb['nama']).'</option>'; } ?>
        </select>
      </div>
      <div class="col-span-4"><label class="text-xs font-semibold">Stood</label>
        <input name="stood" class="w-full border rounded px-3 py-2" placeholder="...">
      </div>
      <div class="col-span-6"><label class="text-xs font-semibold">Jenis Pekerjaan</label>
        <select name="jenis_id" class="w-full border rounded px-3 py-2" required>
          <option value="">— Pilih —</option>
          <?php foreach($conn->query("SELECT id,nama FROM md_pemeliharaan_mn ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC) as $x){ echo '<option value="'.$x['id'].'">'.htmlspecialchars($x['nama']).'</option>'; } ?>
        </select>
      </div>
      <div class="col-span-3"><label class="text-xs font-semibold">HK (Tenaga)</label>
        <select name="hk_id" id="hk_id" class="w-full border rounded px-3 py-2">
          <option value="">— Pilih —</option>
          <?php foreach($TENAGA as $t){ echo '<option value="'.$t['id'].'">'.htmlspecialchars($t['kode']).'</option>'; } ?>
        </select>
        <input type="hidden" name="hk" id="hk_hidden" value="">
      </div>
      <div class="col-span-3"><label class="text-xs font-semibold">Sat</label><input name="satuan" class="w-full border rounded px-3 py-2" placeholder="Ha"></div>
      <div class="col-span-12"><label class="text-xs font-semibold">Anggaran 1 Tahun</label><input name="anggaran_tahun" inputmode="decimal" class="w-full border rounded px-3 py-2"></div>
      <?php foreach($monthKeys as $m): ?>
        <div class="col-span-2"><label class="text-xs font-semibold"><?= strtoupper($m) ?></label><input name="<?= $m ?>" inputmode="decimal" class="w-full border rounded px-3 py-2"></div>
      <?php endforeach; ?>
      <div class="col-span-12"><label class="text-xs font-semibold">Keterangan</label><textarea name="keterangan" rows="2" class="w-full border rounded px-3 py-2"></textarea></div>
      <div class="col-span-12 flex justify-end gap-2 mt-2">
        <button type="button" id="mn-cancel" class="btn btn-gray">Batal</button>
        <button class="btn">Simpan</button>
      </div>
    </form>
  </div>
</div>`}

  function openForm(d={}) {
    if (MODAL) MODAL.remove();
    document.body.insertAdjacentHTML('beforeend', modalTpl());
    MODAL=document.getElementById('mn-modal');
    const F=document.getElementById('mn-form'); const T=document.getElementById('mn-title');

    F.action.value = d.id ? 'update' : 'store';
    if (!d.id && (!d.unit_kode)) d.unit_kode = '<?= $f_afd ?: 'AFD01' ?>';

    ['id','tahun','stood','ket','satuan','anggaran_tahun','keterangan',
     'jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'
    ].forEach(k=>{ if (F[k]!==undefined) F[k].value = d[k] ?? ''; });

    if (d.unit_kode && F['unit_kode']) F['unit_kode'].value = d.unit_kode;

    if (d.kebun_nama && F['kebun_id']){
      [...F['kebun_id'].options].forEach(o=>{ if (o.text.trim()===String(d.kebun_nama||'').trim()) F['kebun_id'].value=o.value; });
    } else if (d.kebun_id && F['kebun_id']) {
      F['kebun_id'].value = d.kebun_id;
    }

    if (d.jenis_nama && F['jenis_id']){
      [...F['jenis_id'].options].forEach(o=>{ if (o.text.trim()===String(d.jenis_nama||'').trim()) F['jenis_id'].value=o.value; });
    }

    // Prefill HK
    const selHK = document.getElementById('hk_id');
    const hidHK = document.getElementById('hk_hidden');
    if (d.hk_id && selHK){ selHK.value = String(d.hk_id); hidHK.value = selHK.options[selHK.selectedIndex]?.text || ''; }
    else if (d.hk && selHK){
      [...selHK.options].forEach(o=>{ if (o.text.trim()===String(d.hk||'').trim()) selHK.value=o.value; });
      hidHK.value = d.hk || '';
    }
    selHK?.addEventListener('change', e=>{ hidHK.value = e.target.options[e.target.selectedIndex]?.text || ''; });

    T.textContent = (d.id?'Edit':'Tambah')+' Data mn';

    const close=()=>MODAL.remove();
    document.getElementById('mn-x').onclick=close;
    document.getElementById('mn-cancel').onclick=close;

    F.onsubmit = async (e)=>{
      e.preventDefault();
      const fd=new FormData(F);
      const r = await fetch('pemeliharaan_mn_crud.php',{method:'POST',body:fd});
      const j = await r.json();
      if (j.success){
        await Swal.fire('Berhasil', j.message||'Tersimpan','success');
        close(); location.reload();
      } else {
        Swal.fire('Gagal', (j.errors||[]).map(x=>'• '+x).join('<br>') || j.message || 'Error', 'error');
      }
    }
  }

  <?php if(!$isStaf): ?>
  document.addEventListener('keydown', (e)=>{ if (e.key==='n' && (e.ctrlKey||e.metaKey)){ e.preventDefault(); openForm({ unit_kode: DEFAULT_AFD, tahun: '<?= $f_tahun ?>' }); }});
  <?php endif; ?>
});
</script>
