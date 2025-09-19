<?php
// pages/pemeliharaan.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database(); $conn = $db->getConnection();

/* Tabs */
$tab_aktif = $_GET['tab'] ?? 'TU';
$daftar_tab = [
  'TU'=>'Pemeliharaan TU',
  'TBM'=>'Pemeliharaan TBM',
  'TM'=>'Pemeliharaan TM',
  'BIBIT_PN'=>'Pemeliharaan Bibit PN',
  'BIBIT_MN'=>'Pemeliharaan Bibit MN'
];
$judul_tab_aktif = $daftar_tab[$tab_aktif] ?? 'Pemeliharaan TU';

/* Ambil data list + nama unit */
$sql = "SELECT p.*, u.nama_unit AS unit_nama
        FROM pemeliharaan p
        LEFT JOIN units u ON u.id = p.unit_id
        WHERE p.kategori = :k
        ORDER BY p.tanggal DESC, p.id DESC";
$st = $conn->prepare($sql);
$st->execute([':k'=>$tab_aktif]);
$data_pemeliharaan = $st->fetchAll(PDO::FETCH_ASSOC);

/* Ambil master units utk select */
$units = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")
              ->fetchAll(PDO::FETCH_ASSOC);

$currentPage='pemeliharaan';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="space-y-6">
  <div>
    <h1 class="text-3xl font-bold text-gray-800">Pemeliharaan</h1>
    <p class="text-gray-500 mt-1">Kelola data pemeliharaan perkebunan PTPN IV Regional 3</p>
  </div>

  <div class="border-b border-gray-200">
    <nav class="-mb-px flex flex-wrap gap-4 md:space-x-6">
      <?php foreach ($daftar_tab as $kode => $nama): ?>
        <a href="?tab=<?= urlencode($kode) ?>"
           class="py-3 px-2 border-b-2 font-medium text-sm <?= ($tab_aktif==$kode)?'border-emerald-600 text-emerald-700':'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
          <?= htmlspecialchars($nama) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="bg-white p-6 rounded-xl shadow-md">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3 mb-6">
      <div>
        <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($judul_tab_aktif) ?></h2>
        <p class="text-gray-500 mt-1">
          Kategori: <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold"><?= htmlspecialchars($tab_aktif) ?></span>
        </p>
      </div>
      <div class="flex gap-2">
        <a href="cetak/pemeliharaan_pdf.php?tab=<?= urlencode($tab_aktif) ?>"
           class="inline-flex items-center gap-2 bg-white border px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50">
          <i class="ti ti-file-type-pdf text-red-600 text-xl"></i> PDF
        </a>
        <a href="cetak/pemeliharaan_excel.php?tab=<?= urlencode($tab_aktif) ?>"
           class="inline-flex items-center gap-2 bg-white border px-4 py-2 rounded-lg shadow-sm hover:bg-gray-50">
          <i class="ti ti-file-spreadsheet text-emerald-600 text-xl"></i> Excel
        </a>
        <button id="btn-input-baru"
                class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-emerald-700">
          <i class="ti ti-plus"></i> Input Baru
        </button>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white">
        <thead class="bg-gray-50">
          <tr>
            <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jenis Pekerjaan</th>
            <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit/Devisi</th>
            <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rayon</th>
            <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Periode</th>
            <th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Rencana</th>
            <th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Realisasi</th>
            <th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Progress (%)</th>
            <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
          </tr>
        </thead>
        <tbody class="text-gray-700">
          <?php if (empty($data_pemeliharaan)): ?>
            <tr><td colspan="9" class="text-center py-10 text-gray-500">Belum ada data.</td></tr>
          <?php else: foreach ($data_pemeliharaan as $data):
            $rencana = (float)($data['rencana'] ?? 0);
            $realisasi = (float)($data['realisasi'] ?? 0);
            $progress = $rencana > 0 ? ($realisasi / $rencana) * 100 : 0;
            $badge = 'bg-gray-100 text-gray-800';
            if (($data['status'] ?? '') === 'Selesai')   $badge = 'bg-emerald-100 text-emerald-800';
            elseif (($data['status'] ?? '') === 'Berjalan') $badge = 'bg-blue-100 text-blue-800';
            elseif (($data['status'] ?? '') === 'Tertunda') $badge = 'bg-yellow-100 text-yellow-800';
          ?>
            <tr class="border-b border-gray-200 hover:bg-gray-50">
              <td class="py-3 px-4"><?= htmlspecialchars($data['jenis_pekerjaan']) ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($data['unit_nama'] ?: $data['afdeling'] ?: '-') ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars($data['rayon']) ?></td>
              <td class="py-3 px-4"><?= htmlspecialchars(($data['bulan'] ?? '').' '.($data['tahun'] ?? '')) ?></td>
              <td class="py-3 px-4 text-right"><?= number_format($rencana, 2) ?></td>
              <td class="py-3 px-4 text-right"><?= number_format($realisasi, 2) ?></td>
              <td class="py-3 px-4 text-right"><?= number_format($progress, 2) ?>%</td>
              <td class="py-3 px-4"><span class="px-2 py-1 text-xs font-semibold rounded-full <?= $badge ?>"><?= htmlspecialchars($data['status']) ?></span></td>
              <td class="py-3 px-4">
                <div class="flex items-center gap-3">
                  <button class="btn-edit text-blue-600 hover:text-blue-800"
                          title="Edit"
                          data-json='<?= htmlspecialchars(json_encode($data), ENT_QUOTES, "UTF-8") ?>'>
                    <i class="ti ti-edit"></i>
                  </button>
                  <button class="btn-delete text-red-600 hover:text-red-800"
                          title="Hapus"
                          data-id="<?= (int)$data['id'] ?>">
                    <i class="ti ti-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal CRUD -->
<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-3xl">
    <div class="flex justify-between items-center mb-6">
      <h2 id="modal-title" class="text-2xl font-bold text-gray-800">Input Pekerjaan Baru</h2>
      <button id="btn-close-modal" class="text-gray-500 hover:text-gray-800 text-3xl" aria-label="Close">&times;</button>
    </div>
    <form id="crud-form">
      <input type="hidden" name="id" id="modal-id">
      <input type="hidden" name="action" id="modal-action">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="kategori" value="<?= htmlspecialchars($tab_aktif) ?>">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <label class="form-label">Jenis Pekerjaan <span class="text-red-500">*</span></label>
          <input type="text" id="jenis_pekerjaan" name="jenis_pekerjaan" class="form-input w-full" required>
        </div>

        <div>
          <label class="form-label">Unit/Devisi <span class="text-red-500">*</span></label>
          <select id="unit_id" name="unit_id" class="form-select w-full" required>
            <option value="">— Pilih Unit —</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="form-label">Rayon</label>
          <input type="text" id="rayon" name="rayon" class="form-input w-full">
        </div>

        <div>
          <label class="form-label">Tanggal <span class="text-red-500">*</span></label>
          <input type="date" id="tanggal" name="tanggal" class="form-input w-full" required>
        </div>

        <div>
          <label class="form-label">Bulan <span class="text-red-500">*</span></label>
          <select id="bulan" name="bulan" class="form-select w-full" required>
            <?php foreach (["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"] as $b): ?>
              <option value="<?= $b ?>"><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="form-label">Tahun <span class="text-red-500">*</span></label>
          <input type="number" id="tahun" name="tahun" class="form-input w-full" min="2000" max="2100" required>
        </div>

        <div>
          <label class="form-label">Rencana Bulan Ini</label>
          <input type="number" step="0.01" id="rencana" name="rencana" class="form-input w-full" min="0">
        </div>

        <div>
          <label class="form-label">Realisasi Bulan Ini</label>
          <input type="number" step="0.01" id="realisasi" name="realisasi" class="form-input w-full" min="0">
        </div>

        <div class="md:col-span-2">
          <label class="form-label">Status</label>
          <select id="status" name="status" class="form-select w-full">
            <option value="Berjalan">Berjalan</option>
            <option value="Selesai">Selesai</option>
            <option value="Tertunda">Tertunda</option>
          </select>
        </div>
      </div>

      <div class="flex justify-end gap-4 mt-8">
        <button type="button" id="btn-batal" class="bg-gray-200 text-gray-800 font-semibold py-2 px-6 rounded-lg hover:bg-gray-300">Batal</button>
        <button type="submit" class="bg-black text-white font-semibold py-2 px-6 rounded-lg hover:bg-gray-800">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('crud-modal');
  const form  = document.getElementById('crud-form');
  const open  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

  document.getElementById('btn-input-baru').addEventListener('click', () => {
    form.reset();
    document.getElementById('modal-title').textContent = 'Input Pekerjaan Baru';
    document.getElementById('modal-action').value = 'store';
    document.getElementById('modal-id').value = '';
    open();
  });
  document.getElementById('btn-close-modal').addEventListener('click', close);
  document.getElementById('btn-batal').addEventListener('click', close);

  // Edit & Delete (delegation)
  document.querySelector('tbody').addEventListener('click', (e) => {
    const btn = e.target.closest('button'); if (!btn) return;

    if (btn.classList.contains('btn-edit')) {
      const data = JSON.parse(btn.dataset.json);
      form.reset();
      document.getElementById('modal-title').textContent = 'Edit Pekerjaan';
      document.getElementById('modal-action').value = 'update';
      for (const k in data) { if (form.elements[k]) form.elements[k].value = data[k]; }
      // fallback jika data lama masih pakai teks 'afdeling'
      if (!form.unit_id.value && data.afdeling) {
        [...form.unit_id.options].forEach(op => { if (op.text === data.afdeling) form.unit_id.value = op.value; });
      }
      document.getElementById('modal-id').value = data.id || '';
      open();
    }

    if (btn.classList.contains('btn-delete')) {
      const id = btn.dataset.id;
      Swal.fire({title:'Anda yakin?', text:'Data yang dihapus tidak dapat dikembalikan!', icon:'warning', showCancelButton:true})
        .then(res => {
          if (!res.isConfirmed) return;
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action','delete');
          fd.append('id', id);
          fetch('pemeliharaan_crud.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(j=>{
              if (j.success) Swal.fire('Terhapus!', j.message, 'success').then(()=>location.reload());
              else Swal.fire('Gagal', j.message||'Error', 'error');
            })
            .catch(err=> Swal.fire('Error', String(err), 'error'));
        });
    }
  });

  // Submit (store/update)
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const need = ['jenis_pekerjaan','unit_id','tanggal','bulan','tahun'];
    for (const n of need) { if (!form[n].value) { Swal.fire('Validasi', `Field ${n.replace('_',' ')} wajib diisi.`, 'warning'); return; } }

    const fd = new FormData(form);
    fetch('pemeliharaan_crud.php', { method:'POST', body:fd })
      .then(r=>r.json()).then(j=>{
        if (j.success) {
          close();
          Swal.fire({icon:'success', title:'Berhasil', text:j.message, timer:1600, showConfirmButton:false})
            .then(()=>location.reload());
        } else {
          Swal.fire('Gagal', j.message||'Terjadi kesalahan', 'error');
        }
      })
      .catch(err=> Swal.fire('Error', String(err), 'error'));
  });
});
</script>
