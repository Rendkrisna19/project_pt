<?php
// lm_biaya.php — MOD: Role 'staf' tidak bisa edit/hapus + tombol ikon

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

// --- MODIFIKASI: Dapatkan role user ---
$userRole = $_SESSION['user_role'] ?? 'staf';
$isStaf = ($userRole === 'staf');
// ------------------------------------

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}
function build_url(array $params = []): string {
  $merged = array_merge($_GET, $params);
  foreach ($merged as $k=>$v) if ($v==='') unset($merged[$k]);
  return htmlspecialchars($_SERVER['PHP_SELF'].'?'.http_build_query($merged), ENT_QUOTES, 'UTF-8');
}

try {
  $db   = new Database();
  $conn = $db->getConnection();
  $hasKebun = col_exists($conn,'lm_biaya','kebun_id');
  $units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
  $kebuns = $hasKebun ? $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC) : [];

  $unit_id  = $_GET['unit_id']  ?? ''; $tahun = $_GET['tahun'] ?? '';
  $bulan    = $_GET['bulan']    ?? ''; $kebun_id = $_GET['kebun_id'] ?? '';
  $q        = trim($_GET['q'] ?? '');
  $per_page = (int)($_GET['per_page'] ?? 15);
  if (!in_array($per_page,[10,15,20,25,50,100],true)) $per_page = 15;
  $page   = max(1, (int)($_GET['page'] ?? 1));
  $offset = ($page-1)*$per_page;

  $fromJoin = " FROM lm_biaya b LEFT JOIN units u ON u.id=b.unit_id ".($hasKebun ? "LEFT JOIN md_kebun kb ON kb.id=b.kebun_id" : "")." WHERE 1=1 ";
  $where=""; $bind=[];
  if ($unit_id!==''){ $where.=" AND b.unit_id=:uid"; $bind[':uid']=$unit_id; }
  if ($tahun!==''){ $where.=" AND b.tahun=:thn"; $bind[':thn']=$tahun; }
  if ($bulan!==''){ $where.=" AND b.bulan=:bln"; $bind[':bln']=$bulan; }
  if ($hasKebun && $kebun_id!==''){ $where.=" AND b.kebun_id=:kid"; $bind[':kid']=$kebun_id; }
  if ($q!==''){
    $where.=" AND (b.alokasi LIKE :kw OR b.uraian_pekerjaan LIKE :kw OR u.nama_unit LIKE :kw ".($hasKebun?"OR kb.nama_kebun LIKE :kw ":"").")";
    $bind[':kw'] = "%$q%";
  }

  $stc = $conn->prepare("SELECT COUNT(*) ".$fromJoin.$where);
  foreach($bind as $k=>$v) $stc->bindValue($k,$v);
  $stc->execute();
  $total_rows  = (int)$stc->fetchColumn();
  $total_pages = max(1, (int)ceil($total_rows/$per_page));
  if ($page>$total_pages){ $page=$total_pages; $offset=($page-1)*$per_page; }

  $sql = "SELECT b.*, u.nama_unit ".($hasKebun ? ", kb.nama_kebun " : "")." ".$fromJoin.$where."
          ORDER BY b.tahun DESC, FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), b.id DESC
          LIMIT :lim OFFSET :off";
  $st = $conn->prepare($sql);
  foreach($bind as $k=>$v) $st->bindValue($k,$v);
  $st->bindValue(':lim',$per_page,PDO::PARAM_INT);
  $st->bindValue(':off',$offset,PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $fromRow = $total_rows ? $offset+1 : 0;
  $toRow   = min($offset+$per_page,$total_rows);

  $sumAnggaran=0; $sumRealisasi=0;
  foreach($rows as &$r){
    $ang = (float)$r['rencana_bi']; $rea = (float)$r['realisasi_bi'];
    $r['diff_bi']  = $rea - $ang;
    $r['diff_pct'] = ($ang!=0) ? (($rea - $ang) / $ang * 100) : null;
    $sumAnggaran  += $ang; $sumRealisasi += $rea;
  }
  unset($r);
  $total_pct = ($sumAnggaran!=0) ? (($sumRealisasi - $sumAnggaran)/$sumAnggaran*100) : null;
} catch(PDOException $e){
  die("DB Error: ".$e->getMessage());
}

$currentPage = 'lm_biaya';
include_once '../layouts/header.php';
?>
<style>
  .thead-green th{background:#059669;color:#fff;position:sticky;top:0;z-index:10}
  .tfoot-green td{background:#ECFDF5;color:#065F46;font-weight:600}
  .neg{color:#dc2626} .pos{color:#059669}
  /* --- MODIFIKASI: Style untuk tombol disabled --- */
  button:disabled { opacity: 0.5; cursor: not-allowed !important; }
</style>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-900">LM Biaya</h1>
    <button id="btn-add" class="px-3 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">+ Tambah</button>
  </div>

  <div class="flex gap-2">
    <a href="cetak/lm_biaya_pdf.php?<?= http_build_query($_GET) ?>" target="_blank" class="px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700">Export PDF</a>
    <a href="cetak/lm_biaya_excel.php?<?= http_build_query($_GET) ?>" class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700">Export Excel</a>
  </div>

  <form method="get" class="bg-white p-4 rounded-xl shadow-md flex flex-wrap items-end gap-3">
    <?php if ($hasKebun): ?>
    <div><label class="block text-xs text-gray-500 mb-1">Kebun</label><select name="kebun_id" class="border rounded px-3 py-2"><option value="">Semua Kebun</option><?php foreach($kebuns as $k): ?><option value="<?= (int)$k['id'] ?>" <?= ($kebun_id!=='' && (int)$kebun_id===(int)$k['id'])?'selected':'' ?>><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div><label class="block text-xs text-gray-500 mb-1">Unit/Defisi</label><select name="unit_id" class="border rounded px-3 py-2"><option value="">Semua Unit</option><?php foreach($units as $u): ?><option value="<?= (int)$u['id'] ?>" <?= ($unit_id!=='' && (int)$unit_id===(int)$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-xs text-gray-500 mb-1">Bulan</label><select name="bulan" class="border rounded px-3 py-2"><option value="">Semua Bulan</option><?php foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $b): ?><option value="<?= $b ?>" <?= ($bulan!=='' && $bulan===$b)?'selected':'' ?>><?= $b ?></option><?php endforeach; ?></select></div>
    <div><label class="block text-xs text-gray-500 mb-1">Tahun</label><select name="tahun" class="border rounded px-3 py-2"><option value="">Semua Tahun</option><?php for($y=date('Y')-2;$y<=date('Y')+2;$y++): ?><option value="<?= $y ?>" <?= ($tahun!=='' && (int)$tahun===$y)?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></div>
    <div class="flex-1 min-w-[220px]"><label class="block text-xs text-gray-500 mb-1">Pencarian</label><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="w-full border rounded px-3 py-2" placeholder="Cari alokasi / uraian / unit / kebun"></div>
    <div><label class="block text-xs text-gray-500 mb-1">Per Halaman</label><select name="per_page" class="border rounded px-3 py-2" onchange="this.form.submit()"><?php foreach([10,15,20,25,50,100] as $pp): ?><option value="<?= $pp ?>" <?= $per_page===$pp?'selected':'' ?>><?= $pp ?></option><?php endforeach; ?></select></div>
    <div><button type="submit" class="px-4 py-2 bg-black text-white rounded hover:bg-gray-800">Filter</button></div>
  </form>

  <div class="bg-white rounded-xl shadow-md overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="thead-green">
        <tr>
          <?php if ($hasKebun): ?><th class="py-2 px-3 text-left">Kebun</th><?php endif; ?>
          <th class="py-2 px-3 text-left">Unit/Defisi</th><th class="py-2 px-3 text-left">Alokasi</th>
          <th class="py-2 px-3 text-left">Uraian Pekerjaan</th><th class="py-2 px-3 text-left">Bulan</th>
          <th class="py-2 px-3 text-left">Tahun</th><th class="py-2 px-3 text-right">Anggaran</th>
          <th class="py-2 px-3 text-right">Realisasi</th><th class="py-2 px-3 text-right">+/- Biaya</th>
          <th class="py-2 px-3 text-right">%</th><th class="py-2 px-3 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody class="text-gray-800">
        <?php if (!$rows): ?>
          <tr><td colspan="<?= $hasKebun?11:10 ?>" class="text-center py-6 text-gray-500">Belum ada data.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <?php
            $pct = $r['diff_pct'];
            $pctStr = is_null($pct) ? '-' : sprintf('%s%s%%', $pct>=0?'+':'', number_format($pct,2));
            $pctClass = is_null($pct) ? '' : ($pct>=0?'pos':'neg');
          ?>
          <tr class="border-b hover:bg-gray-50">
            <?php if ($hasKebun): ?><td class="py-2 px-3"><?= htmlspecialchars($r['nama_kebun']??'-') ?></td><?php endif; ?>
            <td class="py-2 px-3"><?= htmlspecialchars($r['nama_unit']??'-') ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($r['alokasi']??'-') ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($r['uraian_pekerjaan']??'-') ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($r['bulan']) ?></td>
            <td class="py-2 px-3"><?= (int)$r['tahun'] ?></td>
            <td class="py-2 px-3 text-right"><?= number_format((float)$r['rencana_bi'],2) ?></td>
            <td class="py-2 px-3 text-right"><?= number_format((float)$r['realisasi_bi'],2) ?></td>
            <td class="py-2 px-3 text-right"><?= number_format((float)$r['diff_bi'],2) ?></td>
            <td class="py-2 px-3 text-right <?= $pctClass ?>"><?= $pctStr ?></td>
            <td class="py-2 px-3">
              <div class="flex items-center gap-3">
                <button class="btn-edit text-blue-600" title="Edit" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>' <?= $isStaf ? 'disabled' : '' ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.1 2.1 0 0 1 2.97 2.97l-9.9 9.9-4.2 1.23 1.23-4.2 9.9-9.9z" /></svg>
                </button>
                <button class="btn-delete text-red-600" title="Hapus" data-id="<?= (int)$r['id'] ?>" <?= $isStaf ? 'disabled' : '' ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3m-9 0h12" /></svg>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr class="tfoot-green">
          <td colspan="<?= $hasKebun?6:5 ?>" class="py-2 px-3 font-semibold">TOTAL</td>
          <td class="py-2 px-3 text-right"><?= number_format($sumAnggaran,2) ?></td>
          <td class="py-2 px-3 text-right"><?= number_format($sumRealisasi,2) ?></td>
          <td class="py-2 px-3 text-right"><?= number_format($sumRealisasi-$sumAnggaran,2) ?></td>
          <td class="py-2 px-3 text-right"><?php if(!is_null($total_pct)){$tp=$total_pct;echo sprintf('%s%s%%',$tp>=0?'+':'',number_format($tp,2));}else{echo'-';}?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="flex items-center justify-between gap-3 text-sm">
    <div class="text-gray-600">Menampilkan <b><?= number_format($fromRow) ?></b>–<b><?= number_format($toRow) ?></b> dari <b><?= number_format($total_rows) ?></b> data</div>
    <?php $win=5; $start=max(1,$page-intval($win/2)); $end=min($total_pages,$start+$win-1); $start=max(1,$end-$win+1); ?>
    <div class="flex items-center gap-1">
      <a class="px-3 py-1 border rounded <?= $page<=1?'pointer-events-none opacity-40':'' ?>" href="<?= build_url(['page'=>1]) ?>">« First</a>
      <a class="px-3 py-1 border rounded <?= $page<=1?'pointer-events-none opacity-40':'' ?>" href="<?= build_url(['page'=>max(1,$page-1)]) ?>">‹ Prev</a>
      <?php for($p=$start;$p<=$end;$p++): ?><a class="px-3 py-1 border rounded <?= $p===$page?'bg-black text-white border-black':'hover:bg-gray-50' ?>" href="<?= build_url(['page'=>$p]) ?>"><?= $p ?></a><?php endfor; ?>
      <a class="px-3 py-1 border rounded <?= $page>=$total_pages?'pointer-events-none opacity-40':'' ?>" href="<?= build_url(['page'=>min($total_pages,$page+1)]) ?>">Next ›</a>
      <a class="px-3 py-1 border rounded <?= $page>=$total_pages?'pointer-events-none opacity-40':'' ?>" href="<?= build_url(['page'=>$total_pages]) ?>">Last »</a>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-3xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Tambah Data</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800" aria-label="Close">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>"><input type="hidden" name="action" id="form-action"><input type="hidden" name="id" id="form-id">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php if ($hasKebun): ?><div><label class="block text-sm mb-1">Kebun</label><select name="kebun_id" id="kebun_id" class="w-full border rounded px-3 py-2"><option value="">-- Pilih Kebun --</option><?php foreach($kebuns as $k): ?><option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div><label class="block text-sm mb-1">Unit/Defisi</label><select name="unit_id" id="unit_id" class="w-full border rounded px-3 py-2" required><option value="">-- Pilih Unit --</option><?php foreach($units as $u): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-sm mb-1">Alokasi</label><input type="text" name="alokasi" id="alokasi" class="w-full border rounded px-3 py-2" placeholder="Masukkan alokasi" required></div>
        <div class="md:col-span-2"><label class="block text-sm mb-1">Uraian Pekerjaan</label><input type="text" name="uraian_pekerjaan" id="uraian_pekerjaan" class="w-full border rounded px-3 py-2" placeholder="Masukkan uraian pekerjaan" required></div>
        <div><label class="block text-sm mb-1">Bulan</label><select name="bulan" id="bulan" class="w-full border rounded px-3 py-2" required><?php $bln=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; foreach($bln as $b) echo '<option value="'.$b.'">'.$b.'</option>'; ?></select></div>
        <div><label class="block text-sm mb-1">Tahun</label><input type="number" name="tahun" id="tahun" class="w-full border rounded px-3 py-2" min="2000" max="2100" value="<?= date('Y') ?>" required></div>
        <div><label class="block text-sm mb-1">Anggaran</label><input type="number" step="0.01" min="0" name="rencana_bi" id="rencana_bi" class="w-full border rounded px-3 py-2" required></div>
        <div><label class="block text-sm mb-1">Realisasi</label><input type="number" step="0.01" min="0" name="realisasi_bi" id="realisasi_bi" class="w-full border rounded px-3 py-2" required></div>
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded bg-gray-100 hover:bg-gray-200">Batal</button>
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  // --- MODIFIKASI: Kirim role ke JavaScript ---
  const IS_STAF = <?= $isStaf ? 'true' : 'false'; ?>;

  const $=s=>document.querySelector(s);
  const modal=$('#crud-modal'), open=()=>{modal.classList.remove('hidden');modal.classList.add('flex')}, close=()=>{modal.classList.add('hidden');modal.classList.remove('flex')};

  $('#btn-add').addEventListener('click',()=>{
    const f=$('#crud-form'); f.reset(); $('#form-action').value='store'; $('#form-id').value=''; open();
  });
  $('#btn-close').addEventListener('click',close);
  $('#btn-cancel').addEventListener('click',close);

  document.body.addEventListener('click',(e)=>{
    const editBtn = e.target.closest('.btn-edit');
    const delBtn = e.target.closest('.btn-delete');

    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(editBtn && !IS_STAF){
      const r=JSON.parse(editBtn.dataset.json); const f=$('#crud-form'); f.reset();
      $('#form-action').value='update'; $('#form-id').value=r.id;
      const kebunEl=$('#kebun_id'); if(kebunEl)kebunEl.value=r.kebun_id??'';
      $('#unit_id').value=r.unit_id??''; $('#alokasi').value=r.alokasi??'';
      $('#uraian_pekerjaan').value=r.uraian_pekerjaan??''; $('#bulan').value=r.bulan??'';
      $('#tahun').value=r.tahun??''; $('#rencana_bi').value=r.rencana_bi??'';
      $('#realisasi_bi').value=r.realisasi_bi??'';
      open();
    }
    // --- MODIFIKASI: Tambahkan pengecekan !IS_STAF ---
    if(delBtn && !IS_STAF){
      const id=delBtn.dataset.id;
      Swal.fire({title:'Hapus data?',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33'})
      .then(res=>{
        if(!res.isConfirmed)return;
        const fd=new FormData(); fd.append('csrf_token','<?= htmlspecialchars($CSRF) ?>');
        fd.append('action','delete'); fd.append('id',id);
        fetch('lm_biaya_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
          if(j.success)Swal.fire('Terhapus','','success').then(()=>location.reload());
          else Swal.fire('Gagal',j.message||'Error','error');
        }).catch(err=>Swal.fire('Error',err?.message||'Request gagal','error'));
      });
    }
  });

  $('#crud-form').addEventListener('submit',e=>{
    e.preventDefault(); const fd=new FormData(e.target);
    fetch('lm_biaya_crud.php',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
      if(j.success){
        close(); Swal.fire({icon:'success',title:'Berhasil',text:j.message,timer:1200,showConfirmButton:false})
        .then(()=>location.reload());
      }else{
        const html=j.errors?.length?`<ul style="text-align:left">${j.errors.map(x=>`<li>• ${x}</li>`).join('')}</ul>`:(j.message||'Error');
        Swal.fire('Gagal',html,'error');
      }
    }).catch(err=>Swal.fire('Error',err?.message||'Request gagal','error'));
  });
});
</script>