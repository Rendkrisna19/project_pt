<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* helper cek kolom ada */
function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

$hasKebun = col_exists($conn, 'lm76', 'kebun_id');

$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$kebun  = $hasKebun ? $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC")->fetchAll(PDO::FETCH_ASSOC) : [];

/* === Tahun Tanam diambil langsung dari md_tahun_tanam === */
$ttList = $conn->query("SELECT DISTINCT tahun FROM md_tahun_tanam WHERE tahun IS NOT NULL AND tahun<>'' ORDER BY tahun ASC")
               ->fetchAll(PDO::FETCH_COLUMN);

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

$currentPage = 'lm76';
include_once '../layouts/header.php';
?>
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold">LM-76 — Statistik Panen Kelapa Sawit</h1>
      <p class="text-gray-500">Input per afdeling (dipakai untuk template Excel LM-76)</p>
    </div>

    <div class="flex items-center gap-2">
      <!-- Export Excel -->
      <button id="btn-export-excel" class="flex items-center gap-2 border border-gray-300 px-3 py-2 rounded-lg bg-white hover:bg-gray-50" title="Export Excel">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/><path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.3 1.7L7 12l-1.7 2.3h1.5l1-1.5 1 1.5h1.5L8.6 12l1.7-2.3H8.8L7.8 11 6.8 9.7H5.3z"/></svg>
        <span>Excel</span>
      </button>

      <!-- Cetak PDF -->
      <button id="btn-export-pdf" class="flex items-center gap-2 border border-gray-300 px-3 py-2 rounded-lg bg-white hover:bg-gray-50" title="Cetak PDF">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/><path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/></svg>
        <span>PDF</span>
      </button>

      <button id="btn-add" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ Input LM-76</button>
    </div>
  </div>

  <!-- Filter singkat -->
  <div class="bg-white p-4 rounded-xl shadow-sm grid grid-cols-1 md:grid-cols-3 gap-3">
    <select id="filter-unit" class="border border-gray-300 rounded px-3 py-2">
      <option value="">Semua Unit</option>
      <?php foreach ($units as $u): ?>
        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="filter-bulan" class="border border-gray-300 rounded px-3 py-2">
      <option value="">Semua Bulan</option>
      <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
    </select>
    <select id="filter-tahun" class="border border-gray-300 rounded px-3 py-2">
      <?php for ($y=$tahunNow-2;$y<=$tahunNow+2;$y++): ?>
        <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <!-- Tabel -->
  <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-gray-600">
          <?php if ($hasKebun): ?><th class="py-3 px-4 text-left">Kebun</th><?php endif; ?>
          <th class="py-3 px-4 text-left">Unit</th>
          <th class="py-3 px-4 text-left">Periode</th>
          <th class="py-3 px-4 text-left">Blok</th>
          <th class="py-3 px-4 text-left">Luas (Ha)</th>
          <th class="py-3 px-4 text-left">Jml Pohon</th>
          <th class="py-3 px-4 text-left">Prod BI (Real/Angg)</th>
          <th class="py-3 px-4 text-left">Prod SD (Real/Angg)</th>
          <th class="py-3 px-4 text-left">Jml Tandan (BI)</th>
          <th class="py-3 px-4 text-left">PSTB (BI/TL)</th>
          <th class="py-3 px-4 text-left">Panen HK</th>
          <th class="py-3 px-4 text-left">Panen Ha (BI/SD)</th>
          <th class="py-3 px-4 text-left">Freq (BI/SD)</th>
          <th class="py-3 px-4 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-data"><tr><td colspan="<?= $hasKebun?14:13 ?>" class="text-center py-10 text-gray-500">Memuat…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-5xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input LM-76</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>

    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <?php if ($hasKebun): ?>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">Nama Kebun</label>
            <select id="kebun_id" name="kebun_id" class="w-full border border-gray-300 rounded px-3 py-2">
              <option value="">-- Pilih Kebun --</option>
              <?php foreach ($kebun as $k): ?>
                <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Unit/Afdeling</label>
          <select id="unit_id" name="unit_id" class="w-full border border-gray-300 rounded px-3 py-2" required>
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select id="bulan" name="bulan" class="w-full border border-gray-300 rounded px-3 py-2" required>
            <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Tahun</label>
          <select id="tahun" name="tahun" class="w-full border border-gray-300 rounded px-3 py-2" required>
            <?php for ($y=$tahunNow-1;$y<=$tahunNow+3;$y++): ?>
              <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <!-- T.T dari md_tahun_tanam -->
        <div>
          <label class="block text-sm mb-1">T.T (Tahun Tanam)</label>
          <select id="tt" name="tt" class="w-full border border-gray-300 rounded px-3 py-2" required>
            <option value="">-- Pilih Tahun Tanam --</option>
            <?php foreach ($ttList as $tt): ?>
              <option value="<?= htmlspecialchars($tt) ?>"><?= htmlspecialchars($tt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Blok sebagai input bebas (tidak dipaksa dari md_blok) -->
        <div>
          <label class="block text-sm mb-1">Blok</label>
          <input type="text" id="blok" name="blok" class="w-full border border-gray-300 rounded px-3 py-2">
        </div>

        <div><label class="block text-sm mb-1">Luas (Ha)</label><input type="number" step="0.01" id="luas_ha" name="luas_ha" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Jumlah Pohon</label><input type="number" id="jumlah_pohon" name="jumlah_pohon" class="w-full border border-gray-300 rounded px-3 py-2"></div>

        <div><label class="block text-sm mb-1">Varietas</label><input type="text" id="varietas" name="varietas" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prod BI Real</label><input type="number" step="0.01" id="prod_bi_realisasi" name="prod_bi_realisasi" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prod BI Angg</label><input type="number" step="0.01" id="prod_bi_anggaran" name="prod_bi_anggaran" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prod SD Real</label><input type="number" step="0.01" id="prod_sd_realisasi" name="prod_sd_realisasi" class="w-full border border-gray-300 rounded px-3 py-2"></div>

        <div><label class="block text-sm mb-1">Prod SD Angg</label><input type="number" step="0.01" id="prod_sd_anggaran" name="prod_sd_anggaran" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Jml Tandan BI</label><input type="number" id="jumlah_tandan_bi" name="jumlah_tandan_bi" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">PSTB BI (Ton/Ha)</label><input type="number" step="0.01" id="pstb_ton_ha_bi" name="pstb_ton_ha_bi" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">PSTB TL (Ton/Ha)</label><input type="number" step="0.01" id="pstb_ton_ha_tl" name="pstb_ton_ha_tl" class="w-full border border-gray-300 rounded px-3 py-2"></div>

        <div><label class="block text-sm mb-1">Panen HK Realisasi</label><input type="number" step="0.01" id="panen_hk_realisasi" name="panen_hk_realisasi" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Panen Ha BI</label><input type="number" step="0.01" id="panen_ha_bi" name="panen_ha_bi" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Panen Ha SD</label><input type="number" step="0.01" id="panen_ha_sd" name="panen_ha_sd" class="w-full border border-gray-300 rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Freq Panen BI</label><input type="number" id="frek_panen_bi" name="frek_panen_bi" class="w-full border border-gray-300 rounded px-3 py-2"></div>

        <div><label class="block text-sm mb-1">Freq Panen SD</label><input type="number" id="frek_panen_sd" name="frek_panen_sd" class="w-full border border-gray-300 rounded px-3 py-2"></div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded border border-gray-300 text-gray-800 hover:bg-gray-50">Batal</button>
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data');
  const selU = $('#filter-unit'), selB = $('#filter-bulan'), selT = $('#filter-tahun');
  const modal = $('#crud-modal');
  const open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')};
  const close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

  $('#btn-add').addEventListener('click',()=>{
    const f = document.getElementById('crud-form');
    f.reset();
    document.getElementById('form-action').value='store';
    document.getElementById('form-id').value='';
    open();
  });
  $('#btn-close').addEventListener('click',close);
  $('#btn-cancel').addEventListener('click',close);

  function refresh(){
    const fd=new FormData();
    fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
    fd.append('action','list');
    fd.append('unit_id', selU.value);
    fd.append('bulan', selB.value);
    fd.append('tahun', selT.value);

    fetch('lm76_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        if(!j.success){ tbody.innerHTML=`<tr><td colspan="<?= $hasKebun?14:13 ?>" class="text-center py-8 text-red-500">${j.message||'Error'}</td></tr>`; return; }
        if(!j.data.length){ tbody.innerHTML=`<tr><td colspan="<?= $hasKebun?14:13 ?>" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`; return; }
        tbody.innerHTML = j.data.map(x=>`
          <tr class="border-b border-gray-200 hover:bg-gray-50">
            <?php if ($hasKebun): ?>
            <td class="py-2 px-3">${x.nama_kebun ?? '-'}</td>
            <?php endif; ?>
            <td class="py-2 px-3">${x.nama_unit}</td>
            <td class="py-2 px-3">${x.bulan} ${x.tahun}</td>
            <td class="py-2 px-3">${x.blok||'-'}</td>
            <td class="py-2 px-3">${x.luas_ha ?? '-'}</td>
            <td class="py-2 px-3">${x.jumlah_pohon ?? '-'}</td>
            <td class="py-2 px-3">${x.prod_bi_realisasi}/${x.prod_bi_anggaran}</td>
            <td class="py-2 px-3">${x.prod_sd_realisasi}/${x.prod_sd_anggaran}</td>
            <td class="py-2 px-3">${x.jumlah_tandan_bi}</td>
            <td class="py-2 px-3">${x.pstb_ton_ha_bi}/${x.pstb_ton_ha_tl}</td>
            <td class="py-2 px-3">${x.panen_hk_realisasi}</td>
            <td class="py-2 px-3">${x.panen_ha_bi}/${x.panen_ha_sd}</td>
            <td class="py-2 px-3">${x.frek_panen_bi}/${x.frek_panen_sd}</td>
            <td class="py-2 px-3">
              <button class="text-blue-600 underline btn-edit" data-json='${encodeURIComponent(JSON.stringify(x))}'>Edit</button>
              <button class="text-red-600 underline btn-del" data-id="${x.id}">Hapus</button>
            </td>
          </tr>
        `).join('');
      });
  }
  refresh(); [selU, selB, selT].forEach(el=>el.addEventListener('change',refresh));

  document.body.addEventListener('click',e=>{
    if(e.target.classList.contains('btn-edit')){
      const d = JSON.parse(decodeURIComponent(e.target.dataset.json));
      const f = document.getElementById('crud-form');
      f.reset();
      document.getElementById('form-action').value='update';
      document.getElementById('form-id').value=d.id;

      // map field yang ada (termasuk tt)
      const ids = ['kebun_id','unit_id','bulan','tahun','tt','blok','luas_ha','jumlah_pohon','varietas','prod_bi_realisasi','prod_bi_anggaran','prod_sd_realisasi','prod_sd_anggaran','jumlah_tandan_bi','pstb_ton_ha_bi','pstb_ton_ha_tl','panen_hk_realisasi','panen_ha_bi','panen_ha_sd','frek_panen_bi','frek_panen_sd'];
      ids.forEach(k=>{ const el=document.getElementById(k); if(el && d[k]!==undefined) el.value=d[k] ?? ''; });
      open();
    }
    if(e.target.classList.contains('btn-del')){
      const id=e.target.dataset.id;
      Swal.fire({title:'Hapus data?',icon:'warning',showCancelButton:true}).then(res=>{
        if(res.isConfirmed){
          const fd=new FormData();
          fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete');
          fd.append('id',id);
          fetch('lm76_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
            if(j.success){ Swal.fire('Terhapus','', 'success'); refresh(); } else Swal.fire('Gagal', j.message||'Error', 'error');
          });
        }
      });
    }
  });

  document.getElementById('crud-form').addEventListener('submit',e=>{
    e.preventDefault();
    const fd=new FormData(e.target);
    fetch('lm76_crud.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        if(j.success){ close(); Swal.fire('Tersimpan','', 'success'); refresh(); }
        else Swal.fire('Gagal', j.message||'Error', 'error');
      });
  });
});

// Export (ikut filter)
document.getElementById('btn-export-excel').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    unit_id: document.getElementById('filter-unit').value || '',
    bulan: document.getElementById('filter-bulan').value || '',
    tahun: document.getElementById('filter-tahun').value || ''
  }).toString();
  window.open('cetak/lm76_export_excel.php?' + qs, '_blank');
});
document.getElementById('btn-export-pdf').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    unit_id: document.getElementById('filter-unit').value || '',
    bulan: document.getElementById('filter-bulan').value || '',
    tahun: document.getElementById('filter-tahun').value || ''
  }).toString();
  window.open('cetak/lm76_export_pdf.php?' + qs, '_blank');
});
</script>
