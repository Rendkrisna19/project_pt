<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* =========================
   AJAX OPTIONS (blok / tt)
   ========================= */
if (($_GET['ajax'] ?? '') === 'options') {
  header('Content-Type: application/json');
  $type = trim($_GET['type'] ?? '');
  try {
    if ($type === 'blok') {
      $unit_id = isset($_GET['unit_id']) && ctype_digit((string)$_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;
      if ($unit_id <= 0) { echo json_encode(['success'=>true,'data'=>[]]); exit; }
      // md_blok: ambil by unit + kolom kode sebagai value & label
      $st = $conn->prepare("SELECT kode FROM md_blok WHERE unit_id = :u AND kode<>'' ORDER BY kode");
      $st->execute([':u'=>$unit_id]);
      $rows = $st->fetchAll(PDO::FETCH_COLUMN);
      echo json_encode(['success'=>true,'data'=>$rows]); exit;
    }
    if ($type === 'tt') {
      // md_tahun_tanam: ambil list tahun (nilai = tahun)
      $st = $conn->query("SELECT tahun FROM md_tahun_tanam WHERE tahun IS NOT NULL AND tahun<>'' ORDER BY tahun DESC");
      $rows = $st->fetchAll(PDO::FETCH_COLUMN);
      echo json_encode(['success'=>true,'data'=>$rows]); exit;
    }
    echo json_encode(['success'=>false,'message'=>'Tipe tidak dikenali']);
  } catch(Throwable $e){
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
  }
  exit;
}

/* =========================
   PAGE (non-AJAX)
   ========================= */
$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$kebuns = $conn->query("SELECT id, kode, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);

$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$tahunNow = (int)date('Y');

$currentPage = 'lm77';
include_once '../layouts/header.php';
?>
<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold">LM-77 — Statistik Panen (Rekap)</h1>
      <p class="text-gray-500">Rekap kebun/afdeling (dipakai template Excel LM-77)</p>
    </div>

    <div class="flex items-center gap-2">
      <!-- Export Excel -->
      <button id="btn-export-excel" class="flex items-center gap-2 border px-3 py-2 rounded-lg bg-white hover:bg-gray-50" title="Export Excel">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
          <path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2z"/>
          <path d="M3 8h10a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1zm2.3 1.7L7 12l-1.7 2.3h1.5l1-1.5 1 1.5h1.5L8.6 12l1.7-2.3H8.8L7.8 11 6.8 9.7H5.3z"/>
        </svg>
        <span>Excel</span>
      </button>

      <!-- Cetak PDF -->
      <button id="btn-export-pdf" class="flex items-center gap-2 border px-3 py-2 rounded-lg bg-white hover:bg-gray-50" title="Cetak PDF">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
          <path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/>
          <path d="M8 12h2.5a2.5 2.5 0 1 1 0 5H8v-5zm1.5 1.5V15H10a1 1 0 0 0 0-2h-.5zM14 12h2a2 2 0 1 1 0 4h-1v1.5h-1V12zm2 1.5a.5.5 0 0 1 0 1H15v-1h1z"/>
        </svg>
        <span>PDF</span>
      </button>

      <button id="btn-add" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">+ Input LM-77</button>
    </div>
  </div>

  <!-- FILTER -->
  <div class="bg-white p-4 rounded-xl shadow-sm grid grid-cols-1 md:grid-cols-4 gap-3">
    <select id="filter-unit" class="border rounded px-3 py-2">
      <option value="">Semua Unit</option>
      <?php foreach ($units as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
    </select>
    <select id="filter-bulan" class="border rounded px-3 py-2">
      <option value="">Semua Bulan</option>
      <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
    </select>
    <select id="filter-tahun" class="border rounded px-3 py-2">
      <?php for ($y=$tahunNow-2;$y<=$tahunNow+2;$y++): ?>
        <option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select id="filter-kebun" class="border rounded px-3 py-2">
      <option value="">Semua Kebun</option>
      <?php foreach ($kebuns as $k): ?>
        <option value="<?= htmlspecialchars($k['kode']) ?>"><?= htmlspecialchars($k['nama_kebun']) ?> (<?= htmlspecialchars($k['kode']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- TABEL -->
  <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-gray-600">
          <th class="py-3 px-4 text-left">Kebun</th>
          <th class="py-3 px-4 text-left">Unit</th>
          <th class="py-3 px-4 text-left">Periode</th>
          <th class="py-3 px-4 text-left">Blok</th>
          <th class="py-3 px-4 text-left">Luas</th>
          <th class="py-3 px-4 text-left">Pohon</th>
          <th class="py-3 px-4 text-left">Var % (BI/SD)</th>
          <th class="py-3 px-4 text-left">Tandan/Pohon (BI/SD)</th>
          <th class="py-3 px-4 text-left">Prod Ton/Ha (BI/SD THI/TL)</th>
          <th class="py-3 px-4 text-left">BTR (BI/SD THI/TL)</th>
          <th class="py-3 px-4 text-left">Basis (Kg/HK)</th>
          <th class="py-3 px-4 text-left">Prestasi Kg/HK (BI/SD)</th>
          <th class="py-3 px-4 text-left">Prestasi Tandan/HK (BI/SD)</th>
          <th class="py-3 px-4 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-data"><tr><td colspan="14" class="text-center py-10 text-gray-500">Memuat…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-5xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Input LM-77</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Kebun</label>
          <select id="kebun_id" name="kebun_id" class="w-full border rounded px-3 py-2">
            <option value="">-- Pilih Kebun --</option>
            <?php foreach ($kebuns as $k): ?>
              <option value="<?= (int)$k['id'] ?>" data-kode="<?= htmlspecialchars($k['kode']) ?>">
                <?= htmlspecialchars($k['nama_kebun']) ?> (<?= htmlspecialchars($k['kode']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Unit/Afdeling</label>
          <select id="unit_id" name="unit_id" class="w-full border rounded px-3 py-2" required>
            <option value="">-- Pilih Unit --</option>
            <?php foreach ($units as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select id="bulan" name="bulan" class="w-full border rounded px-3 py-2" required>
            <?php foreach ($bulanList as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Tahun</label>
          <select id="tahun" name="tahun" class="w-full border rounded px-3 py-2" required>
            <?php for ($y=$tahunNow-1;$y<=$tahunNow+3;$y++): ?><option value="<?= $y ?>" <?= $y===$tahunNow?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
          </select>
        </div>

        <!-- T.T dari md_tahun_tanam -->
        <div>
          <label class="block text-sm mb-1">T.T (Tahun Tanam)</label>
          <select id="tt" name="tt" class="w-full border rounded px-3 py-2">
            <option value="">-- Pilih Tahun Tanam --</option>
            <!-- options di-load via AJAX md_tahun_tanam -->
          </select>
        </div>

        <!-- BLOK dari md_blok (by unit) -->
        <div>
          <label class="block text-sm mb-1">Blok</label>
          <select id="blok" name="blok" class="w-full border rounded px-3 py-2" disabled>
            <option value="">— pilih Unit dulu —</option>
          </select>
        </div>

        <div><label class="block text-sm mb-1">Luas (Ha)</label><input type="number" step="0.01" id="luas_ha" name="luas_ha" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Jumlah Pohon</label><input type="number" id="jumlah_pohon" name="jumlah_pohon" class="w-full border rounded px-3 py-2"></div>

        <div><label class="block text-sm mb-1">Pohon/Ha</label><input type="number" step="0.01" id="pohon_ha" name="pohon_ha" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Var % BI</label><input type="number" step="0.01" id="var_prod_bi" name="var_prod_bi" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Var % SD</label><input type="number" step="0.01" id="var_prod_sd" name="var_prod_sd" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Tandan/ Pohon BI</label><input type="number" step="0.0001" id="jtandan_per_pohon_bi" name="jtandan_per_pohon_bi" class="w-full border rounded px-3 py-2"></div>

        <div><label class="block text-sm mb-1">Tandan/ Pohon SD</label><input type="number" step="0.0001" id="jtandan_per_pohon_sd" name="jtandan_per_pohon_sd" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prod Ton/Ha BI</label><input type="number" step="0.01" id="prod_tonha_bi" name="prod_tonha_bi" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prod Ton/Ha SD THI</label><input type="number" step="0.01" id="prod_tonha_sd_thi" name="prod_tonha_sd_thi" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prod Ton/Ha SD TL</label><input type="number" step="0.01" id="prod_tonha_sd_tl" name="prod_tonha_sd_tl" class="w-full border rounded px-3 py-2"></div>

        <div><label class="block text-sm mb-1">BTR BI (Kg/Tdn)</label><input type="number" step="0.01" id="btr_bi" name="btr_bi" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">BTR SD THI</label><input type="number" step="0.01" id="btr_sd_thi" name="btr_sd_thi" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">BTR SD TL</label><input type="number" step="0.01" id="btr_sd_tl" name="btr_sd_tl" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Basis Borong (Kg/HK)</label><input type="number" step="0.01" id="basis_borong_kg_hk" name="basis_borong_kg_hk" class="w-full border rounded px-3 py-2"></div>

        <div><label class="block text-sm mb-1">Prestasi Kg/HK BI</label><input type="number" step="0.01" id="prestasi_kg_hk_bi" name="prestasi_kg_hk_bi" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prestasi Kg/HK SD</label><input type="number" step="0.01" id="prestasi_kg_hk_sd" name="prestasi_kg_hk_sd" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prestasi Tandan/HK BI</label><input type="number" step="0.01" id="prestasi_tandan_hk_bi" name="prestasi_tandan_hk_bi" class="w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Prestasi Tandan/HK SD</label><input type="number" step="0.01" id="prestasi_tandan_hk_sd" name="prestasi_tandan_hk_sd" class="w-full border rounded px-3 py-2"></div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded bg-gray-100 hover:bg-gray-200">Batal</button>
        <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const $=s=>document.querySelector(s);
  const tbody=$('#tbody-data');
  const selU=$('#filter-unit'), selB=$('#filter-bulan'), selT=$('#filter-tahun'), selK=$('#filter-kebun');

  const modal=$('#crud-modal');
  const open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')};
  const close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

  const loadOptions = async (url) => {
    try { const r=await fetch(url); const j=await r.json(); return (j && j.success && Array.isArray(j.data))? j.data : []; }
    catch(e){ return []; }
  };
  const fillSelect = (sel, arr, placeholder='— Pilih —') => {
    sel.innerHTML=''; const def=document.createElement('option'); def.value=''; def.textContent=placeholder; sel.appendChild(def);
    arr.forEach(v=>{ const o=document.createElement('option'); o.value=v; o.textContent=v; sel.appendChild(o); });
  };

  // ===== Init T.T (tahun tanam) dari md_tahun_tanam =====
  (async()=>{
    const yrs = await loadOptions('?ajax=options&type=tt');
    const ttSel = document.getElementById('tt');
    if (ttSel) fillSelect(ttSel, yrs, '-- Pilih Tahun Tanam --');
  })();

  // ===== Blok by Unit (md_blok.kode) =====
  async function refreshBlokByUnit(){
    const uid = document.getElementById('unit_id').value;
    const sel = document.getElementById('blok');
    if (!uid){ sel.disabled=true; sel.innerHTML='<option value="">— pilih Unit dulu —</option>'; return; }
    const data = await loadOptions(`?ajax=options&type=blok&unit_id=${encodeURIComponent(uid)}`);
    sel.disabled=false; fillSelect(sel, data, '-- Pilih Blok --');
  }
  document.getElementById('unit_id')?.addEventListener('change', refreshBlokByUnit);

  // ===== CRUD Modal =====
  $('#btn-add').addEventListener('click',()=>{
    const f=document.getElementById('crud-form');
    f.reset();
    document.getElementById('form-action').value='store';
    document.getElementById('form-id').value='';
    // reset blok
    const selBlok=document.getElementById('blok'); selBlok.disabled=true; selBlok.innerHTML='<option value="">— pilih Unit dulu —</option>';
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
    fd.append('kebun_kode', selK.value);

    fetch('lm77_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
      if(!j.success){tbody.innerHTML=`<tr><td colspan="14" class="text-center py-8 text-red-500">${j.message||'Error'}</td></tr>`;return;}
      if(!j.data.length){tbody.innerHTML=`<tr><td colspan="14" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>`;return;}
      tbody.innerHTML=j.data.map(x=>`
        <tr class="border-b hover:bg-gray-50">
          <td class="py-2 px-3">${x.kebun_nama ? x.kebun_nama + ' (' + x.kebun_kode + ')' : (x.kebun_kode || '-')}</td>
          <td class="py-2 px-3">${x.nama_unit}</td>
          <td class="py-2 px-3">${x.bulan} ${x.tahun}</td>
          <td class="py-2 px-3">${x.blok||'-'}</td>
          <td class="py-2 px-3">${x.luas_ha??'-'}</td>
          <td class="py-2 px-3">${x.jumlah_pohon??'-'}</td>
          <td class="py-2 px-3">${x.var_prod_bi??'-'}% / ${x.var_prod_sd??'-'}%</td>
          <td class="py-2 px-3">${x.jtandan_per_pohon_bi??'-'} / ${x.jtandan_per_pohon_sd??'-'}</td>
          <td class="py-2 px-3">${x.prod_tonha_bi??'-'} / ${x.prod_tonha_sd_thi??'-'} / ${x.prod_tonha_sd_tl??'-'}</td>
          <td class="py-2 px-3">${x.btr_bi??'-'} / ${x.btr_sd_thi??'-'} / ${x.btr_sd_tl??'-'}</td>
          <td class="py-2 px-3">${x.basis_borong_kg_hk??'-'}</td>
          <td class="py-2 px-3">${x.prestasi_kg_hk_bi??'-'} / ${x.prestasi_kg_hk_sd??'-'}</td>
          <td class="py-2 px-3">${x.prestasi_tandan_hk_bi??'-'} / ${x.prestasi_tandan_hk_sd??'-'}</td>
          <td class="py-2 px-3">
            <button class="text-blue-600 underline btn-edit" data-json='${JSON.stringify(x)}'>Edit</button>
            <button class="text-red-600 underline btn-del" data-id="${x.id}">Hapus</button>
          </td>
        </tr>
      `).join('');
    });
  }
  refresh(); [selU, selB, selT, selK].forEach(el=>el.addEventListener('change',refresh));

  document.body.addEventListener('click',async e=>{
    if(e.target.classList.contains('btn-edit')){
      const d=JSON.parse(e.target.dataset.json);
      const form=document.getElementById('crud-form');
      form.reset();
      document.getElementById('form-action').value='update';
      document.getElementById('form-id').value=d.id;

      // isi field umum
      ['kebun_id','unit_id','bulan','tahun','tt','blok','luas_ha','jumlah_pohon','pohon_ha','var_prod_bi','var_prod_sd','jtandan_per_pohon_bi','jtandan_per_pohon_sd','prod_tonha_bi','prod_tonha_sd_thi','prod_tonha_sd_tl','btr_bi','btr_sd_thi','btr_sd_tl','basis_borong_kg_hk','prestasi_kg_hk_bi','prestasi_kg_hk_sd','prestasi_tandan_hk_bi','prestasi_tandan_hk_sd']
        .forEach(id => { const el=document.getElementById(id); if(el && d[id]!==undefined) el.value = d[id]??''; });

      // map kebun by kode jika kebun_id kosong
      const selKebun=document.getElementById('kebun_id');
      if (selKebun){
        if (d.kebun_id){ selKebun.value=d.kebun_id; }
        else if (d.kebun_kode){
          const opt=[...selKebun.options].find(o=>(o.dataset.kode||'')===String(d.kebun_kode));
          if (opt) selKebun.value=opt.value;
        }
      }

      // refresh blok by unit, lalu set nilai blok
      await refreshBlokByUnit();
      if (d.blok){ const sel=document.getElementById('blok'); sel.value = d.blok; }

      // pastikan T.T (tt) juga ada di opsi (kalau list md_tahun_tanam lengkap, ini otomatis cocok)
      open();
    }
    if(e.target.classList.contains('btn-del')){
      const id=e.target.dataset.id;
      Swal.fire({title:'Hapus data?',icon:'warning',showCancelButton:true}).then(res=>{
        if(res.isConfirmed){
          const fd=new FormData(); fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>'); fd.append('action','delete'); fd.append('id',id);
          fetch('lm77_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
            if(j.success){Swal.fire('Terhapus','', 'success'); refresh();} else Swal.fire('Gagal', j.message||'Error', 'error');
          });
        }
      });
    }
  });

  document.getElementById('crud-form').addEventListener('submit',e=>{
    e.preventDefault();
    const fd=new FormData(e.target);
    fetch('lm77_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
      if(j.success){Swal.fire('Tersimpan','', 'success'); document.getElementById('crud-modal').classList.add('hidden'); refresh();}
      else Swal.fire('Gagal', j.message||'Error', 'error');
    });
  });
});

// === Export Excel & PDF (ikut filter aktif + CSRF) ===
document.getElementById('btn-export-excel').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    unit_id: document.getElementById('filter-unit').value || '',
    bulan: document.getElementById('filter-bulan').value || '',
    tahun: document.getElementById('filter-tahun').value || '',
    kebun_kode: document.getElementById('filter-kebun').value || ''
  }).toString();
  window.open('cetak/lm77_export_excel.php?' + qs, '_blank');
});

document.getElementById('btn-export-pdf').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    unit_id: document.getElementById('filter-unit').value || '',
    bulan: document.getElementById('filter-bulan').value || '',
    tahun: document.getElementById('filter-tahun').value || '',
    kebun_kode: document.getElementById('filter-kebun').value || ''
  }).toString();
  window.open('cetak/lm77_export_pdf.php?' + qs, '_blank');
});
</script>
