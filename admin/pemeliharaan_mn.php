<?php
// pages/pemeliharaan_mn.php — Rekap PN per STOOD (grup + subtotal), tanpa kolom "Stood"
// Versi: 2025-11-10 — per Stood (master md_jenis_bibitmn), filter & form pakai dropdown Stood
// MODIFIKASI: 2025-11-11 — Pindah kolom "Jumlah" (total realisasi) setelah bulan, sebelum +/-
// CATATAN: Filter 'ket' diproses di pemeliharaan_mn_crud.php, BUKAN DI FILE INI.

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

/* ===== Filter ===== */
$f_tahun   = ($_GET['tahun'] ?? '') === '' ? (int)date('Y') : (int)$_GET['tahun'];
$f_hk      = trim((string)($_GET['hk'] ?? ''));
$f_ket     = trim((string)($_GET['ket'] ?? ''));        // pakai 'ket'
$f_jenis   = trim((string)($_GET['jenis'] ?? ''));      // nama jenis (opsional)
$f_kebun   = (int)($_GET['kebun_id'] ?? 0);
$f_stoodId = (int)($_GET['stood_id'] ?? 0);       // <- ganti stood text -> stood_id

/* Master dropdown */
$jenisMaster  = $conn->query("SELECT nama FROM md_pemeliharaan_mn ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
$kebunList    = $conn->query("SELECT id, nama_kebun AS nama FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$TENAGA       = $conn->query("SELECT id, kode FROM md_tenaga ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC);
/* Master STOOD */
$STOOD_ROWS   = $conn->query("SELECT id, COALESCE(NULLIF(kode,''), CONCAT('STD-',id)) AS kode, nama FROM md_jenis_bibitmn WHERE is_active=1 ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

/* Table columns */
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$monthKeys   = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des']; // match DB

// Kolom: Tahun, Kebun, Jenis, Ket, HK, Sat, Anggaran + 12 bulan + Jumlah + +/- + Progress + (Aksi)
// (Kolom "Stood" DIHILANGKAN karena jadi header grup)
$COLS_TOTAL  = 6 /*tahun,kebun,jenis,ket,hk,sat*/ + 1 /*anggaran*/ + count($monthLabels)
  + 1 /*jumlah*/ + 1 /*+/-*/ + 1 /*progress*/ + ($isStaf ? 0 : 1);

$currentPage = 'pemeliharaan_mn';
include_once '../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  .freeze-parent {
    max-height: 70vh;
    overflow: auto
  }

  .table-wrap {
    overflow: auto
  }

  table.rekap {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0
  }

  table.rekap th,
  table.rekap td {
    padding: .60rem .70rem;
    border-bottom: 1px solid #e5e7eb;
    font-size: .88rem;
    white-space: nowrap;
  }

  table.rekap thead th {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #1546b0;
    color: #fff;
  }

  table.rekap thead tr:nth-child(1) th {
    z-index: 6;
  }

  table.rekap thead tr:nth-child(2) th {
    z-index: 5;
  }

  table.rekap tbody tr:hover {
    background: #f8fafc;
  }

  tr.group-head td {
    background: linear-gradient(90deg, #e9f1ff 0%, #edf3ff 60%, #f6f9ff 100%);
    border-top: 2px solid #c4d6ff;
    font-weight: 700;
    color: #123;
  }

  tr.sum-stood td {
    background: #e8f5e9;
    font-weight: 700
  }

  .text-right {
    text-align: right
  }

  .text-center {
    text-align: center
  }

  .toolbar {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: .75rem
  }

  .toolbar>* {
    grid-column: span 12;
  }

  @media (min-width: 768px) {
    .toolbar>.md-span-2 {
      grid-column: span 2;
    }

    .toolbar>.md-span-3 {
      grid-column: span 3;
    }

    .toolbar>.md-span-4 {
      grid-column: span 4;
    }
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border: 1px solid #059669;
    background: #059669;
    color: #fff;
    border-radius: .6rem;
    padding: .45rem .9rem
  }

  .btn:hover {
    filter: brightness(0.95)
  }

  .btn:active {
    transform: translateY(1px)
  }

  .btn-gray {
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #111827
  }

  .act {
    display: inline-grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: .5rem;
    border: 1px solid #e5e7eb;
    background: #fff
  }

  /* +/- warna (sama dgn TM) */
  .delta-pos {
    color: #dc2626;
    font-weight: 600;
  }

  /* over (merah) */
  .delta-neg {
    color: #16a34a;
    font-weight: 600;
  }

  /* hemat (hijau) */

  /* highlight kolom anggaran */
  .cell-anggaran {
    background: rgba(21, 70, 176, .06);
  }
</style>

<div class="space-y-6">
  <div>
    <h1 class="text-3xl font-bold">Pemeliharaan MN</h1>
  </div>

  <form method="GET" class="bg-white p-4 rounded-xl shadow toolbar">
    <div class="md-span-2">
      <label class="text-xs font-semibold mb-1 block">Tahun</label>
      <input type="number" name="tahun" min="2000" max="2100"
        value="<?= htmlspecialchars((string)$f_tahun, ENT_QUOTES) ?>" class="w-full border rounded-lg px-3 py-2">
    </div>

    <div class="md-span-4">
      <label class="text-xs font-semibold mb-1 block">Kebun</label>
      <select name="kebun_id" class="w-full border rounded-lg px-3 py-2">
        <option value="0">— Semua Kebun —</option>
        <?php foreach ($kebunList as $kb): ?>
          <option value="<?= (int)$kb['id'] ?>" <?= $f_kebun === (int)$kb['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kb['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="md-span-3">
      <label class="text-xs font-semibold mb-1 block">Jenis Pekerjaan</label>
      <select name="jenis" class="w-full border rounded-lg px-3 py-2">
        <option value="">— Semua Jenis —</option>
        <?php foreach ($jenisMaster as $jn): ?>
          <option value="<?= htmlspecialchars($jn, ENT_QUOTES) ?>" <?= $f_jenis === $jn ? 'selected' : '' ?>><?= htmlspecialchars($jn) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="md-span-2">
      <label class="text-xs font-semibold mb-1 block">HK</label>
      <input name="hk" value="<?= htmlspecialchars($f_hk, ENT_QUOTES) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="TP / TS">
    </div>

    <div class="md-span-3">
      <label class="text-xs font-semibold mb-1 block">Stood</label>
      <select name="stood_id" class="w-full border rounded-lg px-3 py-2">
        <option value="0">— Semua Stood —</option>
        <?php foreach ($STOOD_ROWS as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $f_stoodId === (int)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="md-span-3">
      <label class="text-xs font-semibold mb-1 block">Ket</label>
      <input name="ket" value="<?= htmlspecialchars($f_ket, ENT_QUOTES) ?>" class="w-full border rounded-lg px-3 py-2" placeholder="Cari ket…">
    </div>

    <div class="md-span-2 flex items-end gap-2">
      <button class="btn" type="submit"><i class="ti ti-filter"></i> Terapkan</button>
      <a href="pemeliharaan_mn.php" class="btn btn-gray"><i class="ti ti-refresh"></i> Reset</a>
    </div>
  </form>

  <div class="bg-white rounded-xl shadow freeze-parent">
    <div class="px-3 py-2 border-b flex items-center gap-3">
      <div class="font-semibold text-gray-700 flex-1">
        Rekap MN • Tahun <?= htmlspecialchars((string)$f_tahun) ?>
      </div>
      <?php if (!$isStaf): ?>
        <button id="btn-add" class="btn" type="button"><i class="ti ti-plus"></i> Tambah Data</button>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="rekap" id="pn-table">
        <thead>
          <tr>
            <th rowspan="2">Tahun</th>
            <th rowspan="2">Kebun</th>
            <th rowspan="2">Jenis Pekerjaan</th>
            <th rowspan="2">Ket</th>
            <th rowspan="2">HK</th>
            <th rowspan="2">Sat</th>
            
            <th rowspan="2" class="text-right">Anggaran 1 Tahun</th>
            <th colspan="<?= count($monthLabels) ?>" class="text-center">Realisasi</th>
            <th rowspan="2" class="text-right">Jumlah</th>
            <th rowspan="2" class="text-right">+/- Anggaran</th>
            <th rowspan="2" class="text-right">Progress</th>
            <?php if (!$isStaf): ?><th rowspan="2" class="text-center">Aksi</th><?php endif; ?>
          </tr>
          <tr>
            <?php foreach ($monthLabels as $m): ?>
              <th class="text-right"><?= htmlspecialchars($m) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="pn-body">
          <tr>
            <td colspan="<?= (int)$COLS_TOTAL ?>" class="text-center py-6 text-gray-500">Memuat…</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const IS_STAF = <?= $isStaf ? 'true' : 'false' ?>;
    const CSRF = '<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>';
    const months = <?= json_encode($monthKeys) ?>;

    const nf = (n) => Number(n || 0).toLocaleString('id-ID', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
    const dash = (n) => (Number(n || 0) === 0 ? '—' : nf(n));
    const dashPct = (n) => (Number(n || 0) === 0 ? '—' : nf(n) + '%');

    // map stood (fallback dr master halaman jika CRUD tdk kirim stood_map)
    <?php
    $stoodMap = [];
    foreach ($STOOD_ROWS as $s) {
      $stoodMap[(int)$s['id']] = $s['nama'];
    }
    ?>
    const STOOD_MAP = <?= json_encode($stoodMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const btnAdd = document.getElementById('btn-add');
    if (btnAdd) btnAdd.addEventListener('click', () => openForm({
      tahun: '<?= (int)$f_tahun ?>'
    }));

    // Load data
    (async function loadAll() {
      const qs = new URLSearchParams({
        action: 'list',
        tahun: '<?= (int)$f_tahun ?>',
        hk: '<?= htmlspecialchars($f_hk, ENT_QUOTES) ?>',
        ket: '<?= htmlspecialchars($f_ket, ENT_QUOTES) ?>', // 'ket' SUDAH DIKIRIM DENGAN BENAR
        jenis: '<?= htmlspecialchars($f_jenis, ENT_QUOTES) ?>',
        kebun_id: '<?= (int)$f_kebun ?>',
        stood_id: '<?= (int)$f_stoodId ?>'
      });
      try {
        // File ini yang harus diperbaiki query SQL-nya
        const res = await fetch('pemeliharaan_mn_crud.php?' + qs.toString(), {
          credentials: 'same-origin'
        });
        const j = await res.json();
        const tbody = document.getElementById('pn-body');

        if (!j || j.success !== true) {
          tbody.innerHTML = `<tr><td colspan="<?= (int)$COLS_TOTAL ?>" class="text-center py-6 text-red-600">${(j && (j.message||'')) || 'Error memuat data'}</td></tr>`;
          return;
        }

        const rows = j.rows || [];
        if (!rows.length) {
          tbody.innerHTML = `<tr><td colspan="<?= (int)$COLS_TOTAL ?>" class="text-center py-6 text-gray-500">Tidak ada data pada filter ini.</td></tr>`;
          return;
        }

        const kebunMap = j.kebun_map || {};
        const stoodMap = j.stood_map || STOOD_MAP;

        // Urutan group Stood dari master
        const stoodOrder = j.stood_order || Object.keys(stoodMap).map(k => Number(k));
        const byStood = {};
        stoodOrder.forEach(id => byStood[id] = []);
        rows.forEach(r => {
          const sid = Number(r.stood_id || 0);
          if (!byStood[sid]) byStood[sid] = [];
          byStood[sid].push(r);
        });

        let html = '';
        const emptyRow = (nm) => `<tr class="text-gray-500"><td colspan="<?= (int)$COLS_TOTAL ?>">Tidak ada data untuk Stood <b>${nm}</b> pada filter ini.</td></tr>`;

        for (const sid of stoodOrder) {
          const stoodName = stoodMap[sid] || '(Tanpa Stood)';
          html += `<tr class="group-head"><td colspan="<?= (int)$COLS_TOTAL ?>"><b>Stood: ${stoodName}</b></td></tr>`;

          const list = (byStood[sid] || []).sort((a, b) => String(a.kebun_nama || a.kebun_id || '').localeCompare(String(b.kebun_nama || b.kebun_id || '')) || (a.id - b.id));
          if (!list.length) {
            html += emptyRow(stoodName);
            continue;
          }

          // subtotal accumulator per stood
          const sumStood = {
            anggaran: 0,
            jumlah: 0
          };
          const perBulan = Object.fromEntries(months.map(m => [m, 0]));

          for (const r of list) {
            const totalRealisasi = months.reduce((a, m) => a + (parseFloat(r[m] || 0) || 0), 0);
            const anggaran = (parseFloat(r.anggaran_tahun || 0) || 0);
            const delt = totalRealisasi - anggaran;
            const prog = anggaran > 0 ? (totalRealisasi / anggaran * 100) : 0;
            const deltCls = (delt < 0) ? 'delta-neg' : (delt > 0 ? 'delta-pos' : '');

            sumStood.anggaran += anggaran;
            sumStood.jumlah += totalRealisasi; // Akumulasi total realisasi
            months.forEach(m => {
              perBulan[m] += (parseFloat(r[m] || 0) || 0);
            });

            const safe = (x) => (x == null ? '' : String(x));

            html += `
          <tr>
            <td>${safe(r.tahun)}</td>
            <td>${safe(r.kebun_nama || kebunMap[r.kebun_id] || '')}</td>
            <td>${safe(r.jenis_nama)}</td>
            <td>${safe(r.ket)}</td>
            <td>${safe(r.hk)}</td>
            <td>${safe(r.satuan)}</td>
            <td class="text-right cell-anggaran">${dash(r.anggaran_tahun)}</td>
            ${months.map(m=>`<td class="text-right">${dash(r[m])}</td>`).join('')}
            <td class="text-right">${dash(totalRealisasi)}</td>
            <td class="text-right ${deltCls}">${Number(delt||0)===0?'—':nf(delt)}</td>
            <td class="text-right">${anggaran>0 ? dashPct(prog) : '—'}</td>
            ${!IS_STAF ? `
            <td class="text-center">
              <button class="act" title="Edit" data-edit='${JSON.stringify(r).replaceAll("'","&apos;")}'><i class="ti ti-pencil"></i></button>
              <button class="act" title="Hapus" data-del="${safe(r.id)}"><i class="ti ti-trash" style="color:#dc2626"></i></button>
            </td>` : ``}
          </tr>`;
          }

          // Subtotal per STOOD (seperti TM per Jenis)
          const deltaStood = sumStood.jumlah - sumStood.anggaran;
          
          html += `
        <tr class="sum-stood">
          <td colspan="6"><b>Jumlah (${stoodName})</b></td>
          <td class="text-right cell-anggaran"><b>${dash(sumStood.anggaran)}</b></td>
          ${months.map(m=>`<td class="text-right"><b>${dash(perBulan[m])}</b></td>`).join('')}
          <td class="text-right"><b>${dash(sumStood.jumlah)}</b></td>
          <td class="text-right ${(deltaStood<0)?'delta-neg':(deltaStood>0?'delta-pos':'')}"><b>${Number(deltaStood||0)===0?'—':nf(deltaStood)}</b></td>
          <td class="text-right"><b>${sumStood.anggaran>0 ? dashPct(sumStood.jumlah/sumStood.anggaran*100) : '—'}</b></td>
          <?= $isStaf ? '' : '<td></td>' ?>
        </tr>`;
        }

        document.getElementById('pn-body').innerHTML = html;

        if (!IS_STAF) {
          const tbody = document.getElementById('pn-body');
          tbody.querySelectorAll('[data-edit]').forEach(b => {
            b.addEventListener('click', () => {
              try {
                openForm(JSON.parse(b.dataset.edit || '{}'));
              } catch (e) {}
            });
          });
          tbody.querySelectorAll('[data-del]').forEach(b => {
            b.addEventListener('click', async () => {
              const id = b.dataset.del;
              if (!id) return;
              const y = await Swal.fire({
                title: 'Hapus data ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal'
              });
              if (!y.isConfirmed) return;
              const fd = new FormData();
              fd.append('csrf_token', CSRF);
              fd.append('action', 'delete');
              fd.append('id', id);
              const r = await fetch('pemeliharaan_mn_crud.php', {
                method: 'POST',
                body: fd
              });
              const jj = await r.json();
              if (jj && jj.success) {
                Swal.fire('Berhasil', 'Data dihapus', 'success');
                location.reload();
              } else {
                Swal.fire('Gagal', (jj && (jj.message || 'Error')) || 'Error', 'error');
              }
            });
          });
        }
      } catch (err) {
        const tbody = document.getElementById('pn-body');
        tbody.innerHTML = `<tr><td colspan="<?= (int)$COLS_TOTAL ?>" class="text-center py-6 text-red-600">Gagal memuat data: ${err?.message||err}</td></tr>`;
      }
    })();

    /* ===== Modal (Create/Update) ===== */
    let MODAL = null;

    function modalTpl() {
      return `
<div id="pn-modal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl w-full max-w-5xl shadow-xl">
    <div class="flex items-center justify-between p-4 border-b">
      <h3 id="pn-title" class="font-bold">Form PN</h3>
      <button id="pn-x" class="text-xl" type="button" aria-label="Tutup">&times;</button>
    </div>
    <form id="pn-form" class="p-4 grid grid-cols-12 gap-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
      <input type="hidden" name="action" value="store">
      <input type="hidden" name="id" value="">

      <div class="col-span-2">
        <label class="text-xs font-semibold">Tahun</label>
        <input name="tahun" type="number" min="2000" max="2100" class="w-full border rounded px-3 py-2" required value="<?= htmlspecialchars((string)$f_tahun, ENT_QUOTES) ?>">
      </div>

      <div class="col-span-4">
        <label class="text-xs font-semibold">Kebun</label>
        <select name="kebun_id" class="w-full border rounded px-3 py-2">
          <option value="">— Pilih —</option>
          <?php foreach ($kebunList as $kb) {
            echo '<option value="' . (int)$kb['id'] . '">' . htmlspecialchars($kb['nama']) . '</option>';
          } ?>
        </select>
      </div>

      <div class="col-span-6">
        <label class="text-xs font-semibold">Stood</label>
        <select name="stood_id" id="stood_id" class="w-full border rounded px-3 py-2" required>
          <option value="">— Pilih —</option>
          <?php foreach ($STOOD_ROWS as $s) {
            echo '<option value="' . (int)$s['id'] . '">' . htmlspecialchars($s['nama']) . '</option>';
          } ?>
        </select>
        <input type="hidden" name="stood" id="stood_hidden" value="">
      </div>

      <div class="col-span-6">
        <label class="text-xs font-semibold">Jenis Pekerjaan</label>
        <input id="jenis_text" class="w-full border rounded px-3 py-2" list="jenis_datalist" placeholder="Ketik untuk mencari..." autocomplete="off" required>
        <datalist id="jenis_datalist">
          <?php foreach ($conn->query("SELECT id, nama FROM md_pemeliharaan_mn ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC) as $jr) {
            echo '<option data-id="' . $jr['id'] . '" value="' . htmlspecialchars($jr['nama']) . '"></option>';
          } ?>
        </datalist>
        <input type="hidden" name="jenis_id" id="jenis_id">
      </div>

      <div class="col-span-3">
        <label class="text-xs font-semibold">HK (Tenaga)</label>
        <select name="hk_id" id="hk_id" class="w-full border rounded px-3 py-2">
          <option value="">— Pilih —</option>
          <?php foreach ($TENAGA as $t) {
            echo '<option value="' . (int)$t['id'] . '">' . htmlspecialchars($t['kode']) . '</option>';
          } ?>
        </select>
        <input type="hidden" name="hk" id="hk_hidden" value="">
      </div>

      <div class="col-span-3">
        <label class="text-xs font-semibold">Sat</label>
        <input name="satuan" class="w-full border rounded px-3 py-2" placeholder="Ha">
      </div>

      <div class="col-span-12">
        <label class="text-xs font-semibold">Anggaran 1 Tahun</label>
        <input name="anggaran_tahun" inputmode="decimal" class="w-full border rounded px-3 py-2">
      </div>

      <?php foreach ($monthKeys as $m): ?>
        <div class="col-span-2">
          <label class="text-xs font-semibold"><?= strtoupper($m) ?></label>
          <input name="<?= $m ?>" inputmode="decimal" class="w-full border rounded px-3 py-2">
        </div>
      <?php endforeach; ?>

      <div class="col-span-12">
        <label class="text-xs font-semibold">Ket</label>
        <textarea name="ket" rows="2" class="w-full border rounded px-3 py-2" placeholder="Catatan / keterangan"></textarea>
      </div>

      <div class="col-span-12 flex justify-end gap-2 mt-2">
        <button type="button" id="pn-cancel" class="btn btn-gray">Batal</button>
        <button class="btn">Simpan</button>
      </div>
    </form>
  </div>
</div>`
    }

    function openForm(d = {}) {
      if (MODAL) MODAL.remove();
      document.body.insertAdjacentHTML('beforeend', modalTpl());
      MODAL = document.getElementById('pn-modal');
      const F = document.getElementById('pn-form');
      const T = document.getElementById('pn-title');

      F.action.value = d.id ? 'update' : 'store';

      // Prefill fields
      const fields = ['id', 'tahun', 'ket', 'satuan', 'anggaran_tahun',
        'jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des'
      ];
      fields.forEach(k => {
        if (F[k] !== undefined) F[k].value = (d[k] ?? '');
      });

      // Kebun
      if (F['kebun_id']) {
        if (d.kebun_id) {
          F['kebun_id'].value = d.kebun_id;
        } else if (d.kebun_nama) {
          [...F['kebun_id'].options].forEach(o => {
            if (o.text.trim() === String(d.kebun_nama || '').trim()) F['kebun_id'].value = o.value;
          });
        }
      }

      // STOOD select + hidden stood (teks)
      const selStood = document.getElementById('stood_id');
      const hidStood = document.getElementById('stood_hidden');
      if (selStood && hidStood) {
        if (d.stood_id) {
          selStood.value = String(d.stood_id);
          hidStood.value = selStood.options[selStood.selectedIndex]?.text || '';
        } else if (d.stood) { // fallback by name
          [...selStood.options].forEach(o => {
            if (o.text.trim() === String(d.stood || '').trim()) selStood.value = o.value;
          });
          hidStood.value = d.stood || '';
        }
        selStood.addEventListener('change', e => {
          hidStood.value = e.target.options[e.target.selectedIndex]?.text || '';
        });
      }

      // Jenis (datalist): isi teks + hidden id
      const jenisText = document.getElementById('jenis_text');
      const jenisId = document.getElementById('jenis_id');
      if (d.jenis_id) {
        jenisId.value = d.jenis_id;
        const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o => o.dataset.id == String(d.jenis_id));
        if (opt) jenisText.value = opt.value;
      } else if (d.jenis_nama) {
        jenisText.value = d.jenis_nama;
        const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o => o.value.trim() === String(d.jenis_nama).trim());
        if (opt) jenisId.value = opt.dataset.id || '';
      }
      const syncJenis = () => {
        const val = jenisText.value.trim();
        const opt = [...document.querySelectorAll('#jenis_datalist option')].find(o => o.value.trim() === val);
        jenisId.value = opt ? (opt.dataset.id || '') : '';
      };
      jenisText.addEventListener('change', syncJenis);
      jenisText.addEventListener('input', syncJenis);

      // HK (select id) + hidden hk (kode)
      const selHK = document.getElementById('hk_id');
      const hidHK = document.getElementById('hk_hidden');
      if (selHK && hidHK) {
        if (d.hk_id) {
          selHK.value = String(d.hk_id);
          hidHK.value = selHK.options[selHK.selectedIndex]?.text || '';
        } else if (d.hk) {
          [...selHK.options].forEach(o => {
            if (o.text.trim() === String(d.hk || '').trim()) selHK.value = o.value;
          });
          hidHK.value = d.hk || '';
        }
        selHK.addEventListener('change', e => {
          hidHK.value = e.target.options[e.target.selectedIndex]?.text || '';
        });
      }

      T.textContent = (d.id ? 'Edit' : 'Tambah') + ' Data PN';

      const close = () => {
        MODAL?.remove();
        MODAL = null;
      };
      document.getElementById('pn-x').onclick = close;
      document.getElementById('pn-cancel').onclick = close;

      F.onsubmit = async (e) => {
        e.preventDefault();

        // Validasi: stood_id & jenis_id harus terisi
        if (!document.getElementById('stood_id').value) {
          await Swal.fire('Validasi', 'Silakan pilih Stood.', 'warning');
          return;
        }
        if (!document.getElementById('jenis_id').value) {
          await Swal.fire('Validasi', 'Silakan pilih Jenis Pekerjaan dari daftar yang muncul.', 'warning');
          jenisText.focus();
          return;
        }

        const fd = new FormData(F);
        try {
          const r = await fetch('pemeliharaan_mn_crud.php', {
            method: 'POST',
            body: fd
          });
          const j = await r.json();
          if (j && j.success) {
            await Swal.fire('Berhasil', j.message || 'Tersimpan', 'success');
            close();
            location.reload();
          } else {
            const msg = (j && ((j.errors || []).map(x => '• ' + x).join('<br>') || j.message)) || 'Error';
            Swal.fire('Gagal', msg, 'error');
          }
        } catch (err) {
          Swal.fire('Gagal', err?.message || String(err), 'error');
        }
      }
    }

    <?php if (!$isStaf): ?>
      // Shortcut: Ctrl/Cmd + N
      document.addEventListener('keydown', (e) => {
        if (e.key.toLowerCase() === 'n' && (e.ctrlKey || e.metaKey)) {
          e.preventDefault();
          openForm({
            tahun: '<?= (int)$f_tahun ?>'
          });
        }
      });
    <?php endif; ?>
  });
</script>