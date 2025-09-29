<?php
// admin/lm_biaya.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

function col_exists(PDO $pdo, $table, $col){
  $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

try {
  $db = new Database();
  $conn = $db->getConnection();

  // cek apakah lm_biaya punya kebun_id
  $hasKebun = col_exists($conn, 'lm_biaya', 'kebun_id');

  // masters
  $units  = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
  $aktivs = $conn->query("SELECT id, kode, nama FROM md_kode_aktivitas ORDER BY kode ASC")->fetchAll(PDO::FETCH_ASSOC);
  $jenis  = $conn->query("SELECT id, nama FROM md_jenis_pekerjaan ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);
  $kebuns = $hasKebun
    ? $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

  // ====== FILTER & SEARCH (GET) ======
  $unit_id  = isset($_GET['unit_id'])  && $_GET['unit_id']  !== '' ? (int)$_GET['unit_id']  : '';
  $tahun    = isset($_GET['tahun'])    && $_GET['tahun']    !== '' ? (int)$_GET['tahun']    : '';
  $bulan    = isset($_GET['bulan'])    && $_GET['bulan']    !== '' ? trim($_GET['bulan'])   : '';
  $kebun_id = ($hasKebun && isset($_GET['kebun_id']) && $_GET['kebun_id'] !== '') ? (int)$_GET['kebun_id'] : '';
  $q        = isset($_GET['q']) ? trim($_GET['q']) : '';

  // data (ikut filter & pencarian)
  $sql = "
    SELECT b.*,
           u.nama_unit,
           a.kode AS kode_aktivitas, a.nama AS nama_aktivitas,
           j.nama AS nama_jenis
           ".($hasKebun ? ", kb.nama_kebun " : "")."
    FROM lm_biaya b
    LEFT JOIN units u ON u.id = b.unit_id
    LEFT JOIN md_kode_aktivitas a ON a.id = b.kode_aktivitas_id
    LEFT JOIN md_jenis_pekerjaan j ON j.id = b.jenis_pekerjaan_id
    ".($hasKebun ? "LEFT JOIN md_kebun kb ON kb.id = b.kebun_id" : "")."
    WHERE 1=1
  ";
  $bind = [];
  if ($unit_id  !== '') { $sql .= " AND b.unit_id   = :uid"; $bind[':uid'] = $unit_id; }
  if ($tahun    !== '') { $sql .= " AND b.tahun     = :thn"; $bind[':thn'] = $tahun; }
  if ($bulan    !== '') { $sql .= " AND b.bulan     = :bln"; $bind[':bln'] = $bulan; }
  if ($hasKebun && $kebun_id !== '') { $sql .= " AND b.kebun_id = :kid"; $bind[':kid'] = $kebun_id; }
  if ($q !== '') {
    // cari di beberapa kolom yang wajar untuk pencarian teks
    $sql .= " AND (
      a.kode LIKE :kw OR a.nama LIKE :kw OR
      j.nama LIKE :kw OR
      u.nama_unit LIKE :kw OR
      ".($hasKebun ? "kb.nama_kebun LIKE :kw OR" : "")."
      b.catatan LIKE :kw
    )";
    $bind[':kw'] = "%$q%";
  }

  $sql .= "
    ORDER BY b.tahun DESC,
             FIELD(b.bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'),
             b.id DESC
  ";

  $st = $conn->prepare($sql);
  $st->execute($bind);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // hitung diff (hindari NaN/Div 0)
  foreach ($rows as &$r) {
    $r['diff_bi']  = (float)($r['realisasi_bi'] - $r['rencana_bi']);
    $r['diff_pct'] = ($r['rencana_bi'] ?? 0) > 0 ? (($r['realisasi_bi'] / $r['rencana_bi']) - 1) * 100 : null;
  }
  unset($r);
} catch (PDOException $e) {
  die("DB Error: ".$e->getMessage());
}

$currentPage = 'lm_biaya';
include_once '../layouts/header.php';
?>
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-3xl font-bold text-gray-800">📊 LM Biaya</h1>
    <div class="flex gap-2">
      <!-- Export Excel -->
      <button id="btn-export-excel"
        class="p-2 rounded-lg border border-green-200 bg-green-50 text-green-600 hover:bg-green-100 hover:border-green-300 focus:outline-none focus:ring-2 focus:ring-green-300"
        title="Export Excel" aria-label="Export Excel">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M19 2H8a2 2 0 0 0-2 2v3h2V4h11v16H8v-3H6v3a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
          <path d="M15.707 15.293 13.414 13l2.293-2.293-1.414-1.414L12 11.586 9.707 9.293 8.293 10.707 10.586 13l-2.293 2.293 1.414 1.414L12 14.414l2.293 2.293z"/>
        </svg>
      </button>

      <!-- Export PDF -->
      <button id="btn-export-pdf"
        class="p-2 rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 hover:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-300"
        title="Export PDF" aria-label="Export PDF">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M6 2a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h9l5-5V4a2 2 0 0 0-2-2H6zm0 2h11v11h-4v4H6V4z"/>
          <text x="7" y="16" font-size="6" font-weight="bold" fill="currentColor">PDF</text>
        </svg>
      </button>

      <!-- Tambah -->
      <button id="btn-add"
        class="p-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-800 hover:bg-gray-100 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-300"
        title="Tambah Data" aria-label="Tambah Data">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- ====== FILTER & SEARCH BAR (GET) ====== -->
  <form method="get" class="bg-white p-4 rounded-xl shadow-md flex flex-wrap items-end gap-3">
    <div>
      <label for="f-unit" class="block text-xs text-gray-500 mb-1">Unit/Divisi</label>
      <select id="f-unit" name="unit_id" class="border rounded px-3 py-2">
        <option value="">Semua Unit</option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= ($unit_id !== '' && (int)$unit_id===(int)$u['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['nama_unit']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="f-bulan" class="block text-xs text-gray-500 mb-1">Bulan</label>
      <select id="f-bulan" name="bulan" class="border rounded px-3 py-2">
        <option value="">Semua Bulan</option>
        <?php $blns=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        foreach($blns as $b): ?>
          <option value="<?= $b ?>" <?= ($bulan!=='' && $bulan===$b) ? 'selected' : '' ?>><?= $b ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="f-tahun" class="block text-xs text-gray-500 mb-1">Tahun</label>
      <select id="f-tahun" name="tahun" class="border rounded px-3 py-2">
        <option value="">Semua Tahun</option>
        <?php for($y=date('Y')-2;$y<=date('Y')+2;$y++): ?>
          <option value="<?= $y ?>" <?= ($tahun!=='' && (int)$tahun===$y) ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <?php if ($hasKebun): ?>
    <div>
      <label for="f-kebun" class="block text-xs text-gray-500 mb-1">Nama Kebun</label>
      <select id="f-kebun" name="kebun_id" class="border rounded px-3 py-2">
        <option value="">Semua Kebun</option>
        <?php foreach ($kebuns as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= ($kebun_id!=='' && (int)$kebun_id===(int)$k['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($k['nama_kebun']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="flex-1 min-w-[220px]">
      <label for="f-q" class="block text-xs text-gray-500 mb-1">Pencarian</label>
      <input id="f-q" name="q" type="text" value="<?= htmlspecialchars($q) ?>" class="w-full border rounded px-3 py-2" placeholder="Cari kode/nama aktivitas, jenis, unit, kebun, catatan...">
    </div>

    <div>
      <button type="submit" class="px-4 py-2 bg-black text-white rounded hover:bg-gray-800">Filter</button>
      <a href="lm_biaya.php" class="px-4 py-2 border rounded hover:bg-gray-50">Reset</a>
    </div>
  </form>

  <div class="bg-white p-6 rounded-xl shadow-md overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-gray-600">
          <th class="py-2 px-3 text-left">Kode Aktivitas</th>
          <th class="py-2 px-3 text-left">Jenis Pekerjaan</th>
          <th class="py-2 px-3 text-left">Bulan</th>
          <th class="py-2 px-3 text-left">Tahun</th>
          <?php if ($hasKebun): ?><th class="py-2 px-3 text-left">Kebun</th><?php endif; ?>
          <th class="py-2 px-3 text-left">Unit/Divisi</th>
          <th class="py-2 px-3 text-right">Rencana Bulan Ini</th>
          <th class="py-2 px-3 text-right">Realisasi Bulan Ini</th>
          <th class="py-2 px-3 text-right">+/− Biaya</th>
          <th class="py-2 px-3 text-right">+/− %</th>
          <th class="py-2 px-3 text-left">Aksi</th>
        </tr>
      </thead>
      <tbody class="text-gray-800">
        <?php if (!$rows): ?>
          <tr><td colspan="<?= $hasKebun ? 11 : 10 ?>" class="text-center py-6 text-gray-500">Belum ada data.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr class="border-b hover:bg-gray-50">
            <td class="py-2 px-3"><?= htmlspecialchars(($r['kode_aktivitas'] ?? '').' - '.($r['nama_aktivitas'] ?? '')) ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($r['nama_jenis'] ?? '-') ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($r['bulan']) ?></td>
            <td class="py-2 px-3"><?= (int)$r['tahun'] ?></td>
            <?php if ($hasKebun): ?><td class="py-2 px-3"><?= htmlspecialchars($r['nama_kebun'] ?? '-') ?></td><?php endif; ?>
            <td class="py-2 px-3"><?= htmlspecialchars($r['nama_unit'] ?? '-') ?></td>
            <td class="py-2 px-3 text-right"><?= number_format((float)$r['rencana_bi'],2) ?></td>
            <td class="py-2 px-3 text-right"><?= number_format((float)$r['realisasi_bi'],2) ?></td>
            <td class="py-2 px-3 text-right"><?= number_format((float)$r['diff_bi'],2) ?></td>
            <td class="py-2 px-3 text-right">
              <?= is_null($r['diff_pct']) ? '-' : number_format((float)$r['diff_pct'],2).'%' ?>
            </td>
            <td class="py-2 px-3">
              <div class="flex items-center gap-3">
                <button class="btn-edit text-blue-600 underline" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'>Edit</button>
                <button class="btn-delete text-red-600 underline" data-id="<?= (int)$r['id'] ?>">Hapus</button>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="crud-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-3xl">
    <div class="flex items-center justify-between mb-4">
      <h3 id="modal-title" class="text-xl font-bold">Tambah Data</h3>
      <button id="btn-close" class="text-3xl text-gray-500 hover:text-gray-800" aria-label="Close">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="id" id="form-id">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Kode Aktivitas</label>
          <select name="kode_aktivitas_id" id="kode_aktivitas_id" class="w-full border rounded px-3 py-2" required>
            <option value="">-- Pilih --</option>
            <?php foreach($aktivs as $a): ?>
              <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['kode'].' - '.$a['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Jenis Pekerjaan</label>
          <select name="jenis_pekerjaan_id" id="jenis_pekerjaan_id" class="w-full border rounded px-3 py-2">
            <option value="">-- Pilih --</option>
            <?php foreach($jenis as $j): ?>
              <option value="<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm mb-1">Bulan</label>
          <select name="bulan" id="bulan" class="w-full border rounded px-3 py-2" required>
            <?php
              $blns=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
              foreach($blns as $b) echo '<option value="'.$b.'">'.$b.'</option>';
            ?>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Tahun</label>
          <input type="number" name="tahun" id="tahun" class="w-full border rounded px-3 py-2" min="2000" max="2100" value="<?= date('Y') ?>" required>
        </div>

        <?php if ($hasKebun): ?>
        <div>
          <label class="block text-sm mb-1">Nama Kebun</label>
          <select name="kebun_id" id="kebun_id" class="w-full border rounded px-3 py-2">
            <option value="">-- Pilih Kebun --</option>
            <?php foreach($kebuns as $k): ?>
              <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div>
          <label class="block text-sm mb-1">Unit/Divisi</label>
          <select name="unit_id" id="unit_id" class="w-full border rounded px-3 py-2" required>
            <option value="">-- Pilih Unit --</option>
            <?php foreach($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm mb-1">Rencana Bulan Ini</label>
          <input type="number" step="0.01" min="0" name="rencana_bi" id="rencana_bi" class="w-full border rounded px-3 py-2" required>
        </div>
        <div>
          <label class="block text-sm mb-1">Realisasi Bulan Ini</label>
          <input type="number" step="0.01" min="0" name="realisasi_bi" id="realisasi_bi" class="w-full border rounded px-3 py-2" required>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Catatan</label>
          <input type="text" name="catatan" id="catatan" class="w-full border rounded px-3 py-2">
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="bg-gray-200 text-gray-800 px-5 py-2 rounded-lg hover:bg-gray-300">Batal</button>
        <button type="submit" class="bg-black text-white px-5 py-2 rounded-lg hover:bg-gray-800">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);
  const modal = $('#crud-modal');
  const form  = $('#crud-form');
  const title = $('#modal-title');
  const btnAdd = $('#btn-add');
  const btnClose = $('#btn-close');
  const btnCancel = $('#btn-cancel');
  const formAction = $('#form-action');
  const formId = $('#form-id');

  const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }

  btnAdd.addEventListener('click', () => {
    form.reset();
    formAction.value = 'store';
    formId.value = '';
    title.textContent = 'Tambah Data';
    open();
  });
  btnClose?.addEventListener('click', close);
  btnCancel?.addEventListener('click', close);

  document.body.addEventListener('click', (e) => {
    const t = e.target;

    if (t.classList.contains('btn-edit')) {
      const row = JSON.parse(t.dataset.json);
      form.reset();
      formAction.value = 'update';
      formId.value = row.id;
      $('#kode_aktivitas_id').value = row.kode_aktivitas_id ?? '';
      $('#jenis_pekerjaan_id').value = row.jenis_pekerjaan_id ?? '';
      $('#bulan').value = row.bulan ?? '';
      $('#tahun').value = row.tahun ?? '';
      <?php if ($hasKebun): ?> if (document.getElementById('kebun_id')) document.getElementById('kebun_id').value = row.kebun_id ?? ''; <?php endif; ?>
      $('#unit_id').value = row.unit_id ?? '';
      $('#rencana_bi').value = row.rencana_bi ?? '';
      $('#realisasi_bi').value = row.realisasi_bi ?? '';
      $('#catatan').value = row.catatan ?? '';
      title.textContent = 'Edit Data';
      open();
    }

    if (t.classList.contains('btn-delete')) {
      const id = t.dataset.id;
      Swal.fire({
        title:'Hapus data ini?', icon:'warning', showCancelButton:true,
        confirmButtonColor:'#d33', cancelButtonColor:'#3085d6',
        confirmButtonText:'Ya, hapus', cancelButtonText:'Batal'
      }).then(res => {
        if (res.isConfirmed) {
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action', 'delete');
          fd.append('id', id);
          fetch('lm_biaya_crud.php',{method:'POST', body:fd})
           .then(r=>r.json()).then(j=>{
             if(j.success) Swal.fire('Terhapus!', j.message, 'success').then(()=>location.reload());
             else Swal.fire('Gagal', j.message, 'error');
           })
           .catch(err=> Swal.fire('Error', err?.message||'Request gagal', 'error'));
        }
      });
    }
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const req = ['kode_aktivitas_id','bulan','tahun','unit_id','rencana_bi','realisasi_bi'];
    for (const id of req) {
      const el = document.getElementById(id);
      if (!el || !el.value) { Swal.fire('Validasi', `Field ${id.replaceAll('_',' ')} wajib diisi.`, 'warning'); return; }
    }
    const fd = new FormData(form);
    fetch('lm_biaya_crud.php',{ method:'POST', body:fd })
      .then(r=>r.json()).then(j=>{
        if (j.success) { close(); Swal.fire({icon:'success',title:'Berhasil',text:j.message,timer:1200,showConfirmButton:false}).then(()=>location.reload()); }
        else {
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
          Swal.fire('Gagal', html, 'error');
        }
      })
      .catch(err=> Swal.fire('Error', err?.message||'Request gagal', 'error'));
  });
});

// Export Excel & PDF (sertakan CSRF + ikut filter & search)
document.getElementById('btn-export-excel').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    unit_id:  document.getElementById('f-unit').value || '',
    bulan:    document.getElementById('f-bulan').value || '',
    tahun:    document.getElementById('f-tahun').value || '',
    kebun_id: (document.getElementById('f-kebun')?.value || ''),
    q:        document.getElementById('f-q').value || ''
  }).toString();
  window.open('cetak/lm_biaya_excel.php?' + qs, '_blank');
});
document.getElementById('btn-export-pdf').addEventListener('click', () => {
  const qs = new URLSearchParams({
    csrf_token: '<?= htmlspecialchars($CSRF) ?>',
    unit_id:  document.getElementById('f-unit').value || '',
    bulan:    document.getElementById('f-bulan').value || '',
    tahun:    document.getElementById('f-tahun').value || '',
    kebun_id: (document.getElementById('f-kebun')?.value || ''),
    q:        document.getElementById('f-q').value || ''
  }).toString();
  window.open('cetak/lm_biaya_pdf.php?' + qs, '_blank');
});
</script>
