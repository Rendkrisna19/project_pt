<?php
// pemupukan.php (REVISI: pakai units, hilangkan field teks afdeling/afreading)
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php");
  exit;
}

// CSRF sederhana
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';

$tab = $_GET['tab'] ?? 'menabur'; // 'menabur' | 'angkutan'
if (!in_array($tab, ['menabur','angkutan'], true)) $tab = 'menabur';

try {
  $db = new Database();
  $conn = $db->getConnection();

  // Ambil master units untuk dropdown
  $units = $conn->query("SELECT id, nama_unit FROM units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

  if ($tab === 'angkutan') {
    // Tampilkan unit tujuan (JOIN)
    $stmt = $conn->query("
      SELECT a.*, u.nama_unit AS unit_tujuan_nama
      FROM angkutan_pupuk a
      LEFT JOIN units u ON u.id = a.unit_tujuan_id
      ORDER BY a.tanggal DESC, a.id DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $title = "Data Angkutan Pupuk Kimia";
  } else {
    // menabur_pupuk sudah punya unit_id -> tampilkan nama unit
    $stmt = $conn->query("
      SELECT m.*, u.nama_unit AS unit_nama
      FROM menabur_pupuk m
      LEFT JOIN units u ON u.id = m.unit_id
      ORDER BY m.tanggal DESC, m.id DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $title = "Data Penaburan Pupuk Kimia";
  }
} catch (PDOException $e) {
  die("DB Error: " . $e->getMessage());
}

$currentPage = 'pemupukan';
include_once '../layouts/header.php';
?>
<div class="space-y-6">
  <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">🔬 Pemupukan Kimia</h1>

  <div class="border-b border-gray-200 flex flex-wrap gap-2 md:gap-6">
    <a href="?tab=menabur" class="px-3 py-2 border-b-2 text-sm font-medium <?= $tab==='menabur' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Menabur Pupuk</a>
    <a href="?tab=angkutan" class="px-3 py-2 border-b-2 text-sm font-medium <?= $tab==='angkutan' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Angkutan Pupuk</a>
  </div>

  <div class="bg-white p-6 rounded-xl shadow-md">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
  <h2 class="text-xl font-bold"><?= htmlspecialchars($title) ?></h2>
  <div class="flex gap-2">
    <a href="cetak/pemupukan_excel.php?tab=<?= urlencode($tab) ?>"
       class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
      Export Excel
    </a>
    <a href="cetak/pemupukan_pdf.php?tab=<?= urlencode($tab) ?>"
       class="inline-flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition" target="_blank" rel="noopener">
      Export PDF
    </a>
    <button id="btn-add" class="inline-flex items-center gap-2 bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
      <span>+</span> <span>Tambah Data</span>
    </button>
  </div>
</div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-gray-600">
            <?php if ($tab==='angkutan'): ?>
              <th class="py-3 px-4 text-left">Gudang Asal</th>
              <th class="py-3 px-4 text-left">Unit Tujuan</th>
              <th class="py-3 px-4 text-left">Tanggal</th>
              <th class="py-3 px-4 text-left">Jenis Pupuk</th>
              <th class="py-3 px-4 text-left">Jumlah (Kg)</th>
              <th class="py-3 px-4 text-left">Nomor DO</th>
              <th class="py-3 px-4 text-left">Supir</th>
              <th class="py-3 px-4 text-left">Aksi</th>
            <?php else: ?>
              <th class="py-3 px-4 text-left">Unit</th>
              <th class="py-3 px-4 text-left">Blok</th>
              <th class="py-3 px-4 text-left">Tanggal</th>
              <th class="py-3 px-4 text-left">Jenis Pupuk</th>
              <th class="py-3 px-4 text-left">Jumlah (Kg)</th>
              <th class="py-3 px-4 text-left">Luas (Ha)</th>
              <th class="py-3 px-4 text-left">Invt. Pokok</th>
              <th class="py-3 px-4 text-left">Catatan</th>
              <th class="py-3 px-4 text-left">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="table-body" class="text-gray-800">
          <?php if (!$rows): ?>
            <tr><td colspan="<?= $tab==='angkutan' ? 8 : 9 ?>" class="text-center py-8 text-gray-500">Belum ada data.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr class="border-b hover:bg-gray-50">
              <?php if ($tab==='angkutan'): ?>
                <td class="py-3 px-4"><?= htmlspecialchars($r['gudang_asal']) ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['unit_tujuan_nama'] ?? '-') ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['tanggal']) ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                <td class="py-3 px-4"><?= number_format((float)$r['jumlah'],2) ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['nomor_do']) ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['supir']) ?></td>
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <button class="btn-edit text-blue-600 hover:text-blue-800 underline" data-tab="angkutan" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'>Edit</button>
                    <button class="btn-delete text-red-600 hover:text-red-800 underline" data-tab="angkutan" data-id="<?= (int)$r['id'] ?>">Hapus</button>
                  </div>
                </td>
              <?php else: ?>
                <td class="py-3 px-4"><?= htmlspecialchars($r['unit_nama'] ?? '-') ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['blok']) ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['tanggal']) ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['jenis_pupuk']) ?></td>
                <td class="py-3 px-4"><?= number_format((float)$r['jumlah'],2) ?></td>
                <td class="py-3 px-4"><?= number_format((float)$r['luas'],2) ?></td>
                <td class="py-3 px-4"><?= (int)$r['invt_pokok'] ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($r['catatan']) ?></td>
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <button class="btn-edit text-blue-600 hover:text-blue-800 underline" data-tab="menabur" data-json='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>'>Edit</button>
                    <button class="btn-delete text-red-600 hover:text-red-800 underline" data-tab="menabur" data-id="<?= (int)$r['id'] ?>">Hapus</button>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
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
      <input type="hidden" name="tab" id="form-tab" value="<?= htmlspecialchars($tab) ?>">
      <input type="hidden" name="id" id="form-id">

      <!-- GROUP: ANGKUTAN -->
      <div id="group-angkutan" class="<?= $tab==='angkutan' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm mb-1">Gudang Asal</label>
            <input type="text" name="gudang_asal" id="gudang_asal" class="w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">Unit Tujuan</label>
            <select name="unit_tujuan_id" id="unit_tujuan_id" class="w-full border rounded px-3 py-2">
              <option value="">-- Pilih Unit --</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Tanggal</label>
            <input type="date" name="tanggal_dummy" id="tanggal_angkutan" class="w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">Jenis Pupuk</label>
            <input type="text" name="jenis_pupuk_dummy" id="jenis_pupuk_angkutan" class="w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">Jumlah (Kg)</label>
            <input type="number" step="0.01" name="jumlah_dummy" id="jumlah_angkutan" class="w-full border rounded px-3 py-2" min="0">
          </div>
          <div>
            <label class="block text-sm mb-1">Nomor DO</label>
            <input type="text" name="nomor_do" id="nomor_do" class="w-full border rounded px-3 py-2">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">Supir</label>
            <input type="text" name="supir" id="supir" class="w-full border rounded px-3 py-2">
          </div>
        </div>
      </div>

      <!-- GROUP: MENABUR -->
      <div id="group-menabur" class="<?= $tab==='menabur' ? '' : 'hidden' ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm mb-1">Unit</label>
            <select name="unit_id" id="unit_id" class="w-full border rounded px-3 py-2">
              <option value="">-- Pilih Unit --</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Blok</label>
            <input type="text" name="blok" id="blok" class="w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">Tanggal</label>
            <input type="date" name="tanggal_dummy2" id="tanggal_menabur" class="w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">Jenis Pupuk</label>
            <input type="text" name="jenis_pupuk_dummy2" id="jenis_pupuk_menabur" class="w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">Jumlah (Kg)</label>
            <input type="number" step="0.01" name="jumlah_dummy2" id="jumlah_menabur" class="w-full border rounded px-3 py-2" min="0">
          </div>
          <div>
            <label class="block text-sm mb-1">Luas (Ha)</label>
            <input type="number" step="0.01" name="luas" id="luas" class="w-full border rounded px-3 py-2" min="0">
          </div>
          <div>
            <label class="block text-sm mb-1">Invt. Pokok</label>
            <input type="number" name="invt_pokok" id="invt_pokok" class="w-full border rounded px-3 py-2" min="0" step="1">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">Catatan</label>
            <textarea name="catatan" id="catatan" class="w-full border rounded px-3 py-2" rows="2"></textarea>
          </div>
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
  const $ = (sel) => document.querySelector(sel);
  const modal = $('#crud-modal');
  const form  = $('#crud-form');
  const title = $('#modal-title');
  const btnAdd = $('#btn-add');
  const btnClose = $('#btn-close');
  const btnCancel = $('#btn-cancel');
  const formAction = $('#form-action');
  const formTab = $('#form-tab');
  const formId = $('#form-id');

  const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }

  function switchGroup(tab) {
    const gAng = document.getElementById('group-angkutan');
    const gMen = document.getElementById('group-menabur');

    const setGroup = (el, enabled) => {
      el.classList.toggle('hidden', !enabled);
      el.querySelectorAll('input, select, textarea').forEach(i => {
        if (enabled) { i.removeAttribute('disabled'); }
        else { i.setAttribute('disabled','disabled'); i.removeAttribute('required'); }
      });
    };

    setGroup(gAng, tab==='angkutan');
    setGroup(gMen, tab==='menabur');

    const reqAng = ['gudang_asal','unit_tujuan_id','tanggal_angkutan','jenis_pupuk_angkutan'];
    const reqMen = ['unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'];
    (tab==='angkutan' ? reqAng : reqMen).forEach(id => document.getElementById(id)?.setAttribute('required','required'));
  }

  // ADD
  btnAdd.addEventListener('click', () => {
    form.reset();
    formId.value = '';
    formAction.value = 'store';
    title.textContent = 'Tambah Data';

    const tab = formTab.value;
    switchGroup(tab);
    open();
  });

  btnClose.addEventListener('click', close);
  btnCancel.addEventListener('click', close);

  // EDIT & DELETE
  document.body.addEventListener('click', (e) => {
    const t = e.target;

    if (t.classList.contains('btn-edit')) {
      form.reset();
      const row = JSON.parse(t.dataset.json);
      const tab = t.dataset.tab;
      formTab.value = tab;
      formAction.value = 'update';
      formId.value = row.id;
      title.textContent = 'Edit Data';

      switchGroup(tab);

      if (tab === 'angkutan') {
        document.getElementById('gudang_asal').value = row.gudang_asal ?? '';
        document.getElementById('unit_tujuan_id').value = row.unit_tujuan_id ?? '';
        document.getElementById('tanggal_angkutan').value = row.tanggal ?? '';
        document.getElementById('jenis_pupuk_angkutan').value = row.jenis_pupuk ?? '';
        document.getElementById('jumlah_angkutan').value = row.jumlah ?? '';
        document.getElementById('nomor_do').value = row.nomor_do ?? '';
        document.getElementById('supir').value = row.supir ?? '';
      } else {
        document.getElementById('unit_id').value = row.unit_id ?? '';
        document.getElementById('blok').value = row.blok ?? '';
        document.getElementById('tanggal_menabur').value = row.tanggal ?? '';
        document.getElementById('jenis_pupuk_menabur').value = row.jenis_pupuk ?? '';
        document.getElementById('jumlah_menabur').value = row.jumlah ?? '';
        document.getElementById('luas').value = row.luas ?? '';
        document.getElementById('invt_pokok').value = row.invt_pokok ?? '';
        document.getElementById('catatan').value = row.catatan ?? '';
      }
      open();
    }

    if (t.classList.contains('btn-delete')) {
      const id = t.dataset.id;
      const tab = t.dataset.tab;

      Swal.fire({
        title: 'Hapus data ini?',
        text: 'Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal'
      }).then((res) => {
        if (res.isConfirmed) {
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action', 'delete');
          fd.append('tab', tab);
          fd.append('id', id);

          fetch('pemupukan_crud.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(j => {
              if (j.success) Swal.fire('Terhapus!', j.message, 'success').then(()=>location.reload());
              else {
                const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
                Swal.fire('Gagal', html, 'error');
              }
            })
            .catch(err => Swal.fire('Error', err?.message||'Request gagal', 'error'));
        }
      });
    }
  });

  // SUBMIT
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const tab = formTab.value;

    const fd = new FormData(form);
    fd.set('tab', tab);
    fd.set('action', formAction.value);

    if (tab==='angkutan') {
      const req = ['gudang_asal','unit_tujuan_id','tanggal_angkutan','jenis_pupuk_angkutan'];
      for (const id of req) {
        const el = document.getElementById(id);
        if (!el || !el.value) {
          Swal.fire('Validasi', `Field ${id.replaceAll('_',' ')} wajib diisi.`, 'warning');
          return;
        }
      }
      fd.set('tanggal', document.getElementById('tanggal_angkutan').value);
      fd.set('jenis_pupuk', document.getElementById('jenis_pupuk_angkutan').value);
      fd.set('jumlah', document.getElementById('jumlah_angkutan').value || '');
    } else {
      const req = ['unit_id','blok','tanggal_menabur','jenis_pupuk_menabur'];
      for (const id of req) {
        const el = document.getElementById(id);
        if (!el || !el.value) {
          Swal.fire('Validasi', `Field ${id.replaceAll('_',' ')} wajib diisi.`, 'warning');
          return;
        }
      }
      fd.set('tanggal', document.getElementById('tanggal_menabur').value);
      fd.set('jenis_pupuk', document.getElementById('jenis_pupuk_menabur').value);
      fd.set('jumlah', document.getElementById('jumlah_menabur').value || '');
    }

    fetch('pemupukan_crud.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        if (j.success) {
          close();
          Swal.fire({icon:'success', title:'Berhasil', text:j.message, timer:1500, showConfirmButton:false})
            .then(()=>location.reload());
        } else {
          const html = j.errors?.length ? `<ul style="text-align:left">${j.errors.map(e=>`<li>• ${e}</li>`).join('')}</ul>` : j.message;
          Swal.fire('Gagal', html, 'error');
        }
      })
      .catch(err => Swal.fire('Error', err?.message||'Request gagal', 'error'));
  });
});
</script>
