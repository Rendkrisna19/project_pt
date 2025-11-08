<?php
// pages/master_data.php (MODIFIED: Added RAYON, APL, KETERANGAN, ASAL GUDANG)

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php");
  exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Ambil data master untuk dropdown (jika diperlukan oleh entitas lain)
$units   = $conn->query("SELECT id, nama_unit FROM units ORDER BY nama_unit")->fetchAll(PDO::FETCH_ASSOC);
$satuan  = $conn->query("SELECT id, nama FROM md_satuan ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$sap     = $conn->query("SELECT id, no_sap FROM md_sap ORDER BY no_sap")->fetchAll(PDO::FETCH_ASSOC);
$pupuk   = $conn->query("SELECT id, nama FROM md_pupuk ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$kodeActs = $conn->query("SELECT id, kode FROM md_kode_aktivitas ORDER BY kode")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'master_data';
include_once '../layouts/header.php';
?>
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-gray-900">Manajemen Master Data</h1>
      <p class="text-gray-500 mt-1">CRUD semua master (Nama Kebun, Bahan Kimia, Jenis Pekerjaan, Unit, Blok, Satuan, dst.)</p>
    </div>
    <button id="btn-add" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 bg-emerald-600 text-white hover:bg-emerald-700 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-emerald-400">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
        <path d="M11 11V5a1 1 0 1 1 2 0v6h6a1 1 0 1 1 0 2h-6v6a1 1 0 1 1-2 0v-6H5a1 1 0 1 1 0-2h6z" />
      </svg>
      <span class="hidden sm:inline">Tambah</span>
    </button>
  </div>

  <div class="bg-white p-2 md:p-3 rounded-xl shadow-sm overflow-x-auto">
    <div id="tabs" class="flex gap-2 md:gap-3 whitespace-nowrap">
      <button data-entity="kebun" class="tab active">Nama Kebun</button>
      <button data-entity="bahan_kimia" class="tab">Bahan Kimia</button>
      <button data-entity="jenis_pekerjaan" class="tab">Jenis Pekerjaan</button>
      <button data-entity="jenis_pekerjaan_mingguan" class="tab">Jenis Pekerjaan (Mingguan)</button>
      <button data-entity="unit" class="tab">Unit/Devisi</button>
      <button data-entity="tahun_tanam" class="tab">Tahun Tanam</button>
      <button data-entity="blok" class="tab">Blok</button>
      <button data-entity="fisik" class="tab">Fisik</button>
      <button data-entity="satuan" class="tab">Satuan</button>
      <button data-entity="tenaga" class="tab">Tenaga</button>
      <button data-entity="mobil" class="tab">Mobil</button>
      <button data-entity="alat_panen" class="tab">Jenis Alat Panen</button>
      <button data-entity="sap" class="tab">No SAP</button>
      <button data-entity="jabatan" class="tab">Jabatan</button>
      <button data-entity="pupuk" class="tab">Pupuk</button>
      <button data-entity="kode_aktivitas" class="tab">Kode Aktivitas</button>
      <button data-entity="anggaran" class="tab">Anggaran</button>
      <button data-entity="rayon" class="tab">Rayon</button>
      <button data-entity="apl" class="tab">APL</button>
      <button data-entity="keterangan" class="tab">Keterangan Master</button>
      <button data-entity="asal_gudang" class="tab">Asal Gudang</button>
      <button data-entity="pem_tm" class="tab">Pemeliharaan TM</button>
      <button data-entity="pem_tu" class="tab">Pemeliharaan TU</button>
      <button data-entity="pem_tk" class="tab">Pemeliharaan TK</button>
      <button data-entity="pem_tbm1" class="tab">Pemeliharaan TBM I</button>
      <button data-entity="pem_tbm2" class="tab">Pemeliharaan TBM II</button>
      <button data-entity="pem_tbm3" class="tab">Pemeliharaan TBM III</button>
      <button data-entity="pem_pn" class="tab">Pemeliharaan PN</button>
      <button data-entity="pem_mn" class="tab">Pemeliharaan MN</button>

    </div>
  </div>

  <div id="blok-filter-bar" class="bg-white p-4 rounded-xl shadow-sm border hidden">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
      <div class="md:col-span-2">
        <label class="block text-sm text-gray-700 mb-1">Unit</label>
        <select id="blok-filter-unit" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300">
          <option value="">— Semua Unit —</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nama_unit']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm text-gray-700 mb-1">Kode Blok (contains)</label>
        <input id="blok-filter-kode" type="text" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300" placeholder="Contoh: A12">
      </div>
      <div>
        <label class="block text-sm text-gray-700 mb-1">Tahun Tanam</label>
        <input id="blok-filter-tahun" type="number" min="1900" max="2100" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300" placeholder="YYYY">
      </div>

      <div class="md:col-span-5 flex flex-wrap gap-2">
        <button id="blok-filter-apply" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400">
          Terapkan
        </button>
        <button id="blok-filter-reset" class="px-4 py-2 rounded border bg-white hover:bg-gray-50 text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">
          Reset
        </button>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm">
    <div class="overflow-x-auto">
      <div class="max-h-[70vh] overflow-y-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 sticky top-0 z-10">
            <tr id="thead-row" class="text-gray-600"></tr>
          </thead>
          <tbody id="tbody-data" class="[&>tr:nth-child(even)]:bg-gray-50/40">
            <tr>
              <td class="py-10 text-center text-gray-500">Memuat…</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="flex flex-col md:flex-row items-center justify-between gap-3 p-4 border-t">
      <div class="text-sm text-gray-600" id="page-info">—</div>
      <div class="flex items-center gap-3">
        <label class="text-sm text-gray-600">Per Halaman
          <select id="per-page" class="ml-2 border rounded px-2 py-1">
            <option>10</option>
            <option selected>15</option>
            <option>20</option>
            <option>25</option>
            <option>50</option>
            <option>100</option>
          </select>
        </label>
        <div id="pager" class="flex items-center flex-wrap gap-1"></div>
      </div>
    </div>
  </div>
</div>

<div id="crud-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl w-full max-w-4xl">
    <div class="flex justify-between items-center mb-4">
      <h3 id="modal-title" class="text-xl md:text-2xl font-bold">Tambah Data</h3>
      <button id="btn-close" class="text-2xl text-gray-500 hover:text-gray-800 focus:outline-none" aria-label="Tutup">&times;</button>
    </div>
    <form id="crud-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" id="form-action">
      <input type="hidden" name="entity" id="form-entity">
      <input type="hidden" name="id" id="form-id">
      <div id="form-fields" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="btn-cancel" class="px-4 py-2 rounded border bg-white hover:bg-gray-50 text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">Batal</button>
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include_once '../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
  const $ = s => document.querySelector(s);
  const tbody = $('#tbody-data'),
    thead = $('#thead-row');

  // default buka "Nama Kebun"
  let currentEntity = 'kebun';

  // pagination state
  let page = 1;
  let perPage = 15;
  let total = 0;
  let totalPages = 1;

  // cache untuk client-side pagination fallback
  let clientCache = {
    entity: null,
    rows: []
  };

  // === State filter khusus BLOK ===
  const blokFilter = {
    unit_id: '',
    kode: '',
    tahun: ''
  };

  // --- Master Data untuk Dropdown (dari PHP) ---
  const OPTIONS_UNITS = [<?php foreach ($units as $u) {
                            echo "{value:{$u['id']},label:'" . htmlspecialchars($u['nama_unit'], ENT_QUOTES) . "'},";
                          } ?>];
  const OPTIONS_SATUAN = [<?php foreach ($satuan as $s) {
                            echo "{value:{$s['id']},label:'" . htmlspecialchars($s['nama'], ENT_QUOTES) . "'},";
                          } ?>];
  const OPTIONS_SAP = [<?php foreach ($sap as $s) {
                          echo "{value:{$s['id']},label:'" . htmlspecialchars($s['no_sap'], ENT_QUOTES) . "'},";
                        } ?>];
  const OPTIONS_PUPUK = [<?php foreach ($pupuk as $p) {
                            echo "{value:{$p['id']},label:'" . htmlspecialchars($p['nama'], ENT_QUOTES) . "'},";
                          } ?>];
  const OPTIONS_KODE = [<?php foreach ($kodeActs as $k) {
                          echo "{value:{$k['id']},label:'" . htmlspecialchars($k['kode'], ENT_QUOTES) . "'},";
                        } ?>];
  const OPTIONS_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'].map(b => ({
    value: b,
    label: b
  }));
  const yearNow = (new Date()).getFullYear();
  const OPTIONS_TAHUN = Array.from({
    length: 6
  }, (_, i) => yearNow - 1 + i).map(y => ({
    value: y,
    label: y
  }));


  // ==== MASTER PEMELIHARAAN (nama + deskripsi) ====
  // Ini adalah FUNGSI BANTU, ini sudah benar.
  const ENTITY_PEM = (title) => ({
    title,
    table: ['Nama', 'Deskripsi', 'Aksi'],
    fields: [{
        name: 'nama',
        label: 'Nama',
        type: 'text',
        required: true
      },
      {
        name: 'deskripsi',
        label: 'Deskripsi',
        type: 'textarea'
      }
    ]
  });

  //
  // ===================================================================
  // BLOK YANG MENYEBABKAN BUG TELAH DIHAPUS DARI SINI
  // ===================================================================
  //


  // ================== ENTITIES DEFINITION ==================
  const ENTITIES = {
    kebun: {
      title: 'Nama Kebun',
      table: ['Kode', 'Nama Kebun', 'Keterangan', 'Aksi'],
      fields: [{
          name: 'kode',
          label: 'Kode Kebun',
          type: 'text',
          required: true
        },
        {
          name: 'nama_kebun',
          label: 'Nama Kebun',
          type: 'text',
          required: true
        },
        {
          name: 'keterangan',
          label: 'Keterangan',
          type: 'text'
        }
      ]
    },
    bahan_kimia: {
      title: 'Bahan Kimia',
      table: ['Kode', 'Nama Bahan', 'Satuan', 'Keterangan', 'Aksi'],
      fields: [{
          name: 'kode',
          label: 'Kode Bahan',
          type: 'text',
          required: true
        },
        {
          name: 'nama_bahan',
          label: 'Nama Bahan',
          type: 'text',
          required: true
        },
        {
          name: 'satuan_id',
          label: 'Satuan',
          type: 'select',
          options: OPTIONS_SATUAN
        },
        {
          name: 'keterangan',
          label: 'Keterangan',
          type: 'text'
        }
      ]
    },
    jenis_pekerjaan_mingguan: {
      title: 'Jenis Pekerjaan (Mingguan)',
      table: ['Nama', 'Keterangan', 'Aksi'],
      fields: [{
          name: 'nama',
          label: 'Nama',
          type: 'text',
          required: true
        },
        {
          name: 'keterangan',
          label: 'Keterangan',
          type: 'text'
        }
      ]
    },
    jenis_pekerjaan: {
      title: 'Jenis Pekerjaan',
      table: ['Nama', 'Keterangan', 'Aksi'],
      fields: [{
          name: 'nama',
          label: 'Nama',
          type: 'text',
          required: true
        },
        {
          name: 'keterangan',
          label: 'Keterangan',
          type: 'text'
        }
      ]
    },
    unit: {
      title: 'Unit/Devisi',
      table: ['Nama Unit', 'Keterangan', 'Aksi'],
      fields: [{
          name: 'nama_unit',
          label: 'Nama Unit',
          type: 'text',
          required: true
        },
        {
          name: 'keterangan',
          label: 'Keterangan',
          type: 'text'
        }
      ]
    },
    tahun_tanam: {
      title: 'Tahun Tanam',
      table: ['Tahun', 'Keterangan', 'Aksi'],
      fields: [{
          name: 'tahun',
          label: 'Tahun',
          type: 'number',
          required: true,
          min: 1900,
          max: 2100
        },
        {
          name: 'keterangan',
          label: 'Keterangan',
          type: 'text'
        }
      ]
    },
    blok: {
      title: 'Blok',
      table: ['Unit', 'Kode Blok', 'Tahun Tanam', 'Luas (Ha)', 'Aksi'],
      fields: [{
          name: 'unit_id',
          label: 'Unit',
          type: 'select',
          options: OPTIONS_UNITS,
          required: true
        },
        {
          name: 'kode',
          label: 'Kode Blok',
          type: 'text',
          required: true
        },
        {
          name: 'tahun_tanam',
          label: 'Tahun Tanam',
          type: 'number',
          min: 1900,
          max: 2100
        },
        {
          name: 'luas_ha',
          label: 'Luas (Ha)',
          type: 'number',
          step: '0.01',
          min: 0
        }
      ]
    },
    fisik: {
      title: 'Fisik',
      table: ['Nama', 'Aksi'],
      fields: [{
        name: 'nama',
        label: 'Nama Fisik (Ha/Pkk/Unit/..)',
        type: 'text',
        required: true
      }]
    },
    satuan: {
      title: 'Satuan',
      table: ['Nama', 'Aksi'],
      fields: [{
        name: 'nama',
        label: 'Nama Satuan (Kg/Liter/..)',
        type: 'text',
        required: true
      }]
    },
    tenaga: {
      title: 'Tenaga',
      table: ['Kode', 'Nama', 'Aksi'],
      fields: [{
          name: 'kode',
          label: 'Kode (TS/KNG/PKWT/TP)',
          type: 'text',
          required: true
        },
        {
          name: 'nama',
          label: 'Nama',
          type: 'text',
          required: true
        }
      ]
    },
    mobil: {
      title: 'Mobil',
      table: ['Kode', 'Nama', 'Aksi'],
      fields: [{
          name: 'kode',
          label: 'Kode (TS/TP)',
          type: 'text',
          required: true
        },
        {
          name: 'nama',
          label: 'Nama',
          type: 'text',
          required: true
        }
      ]
    },
    alat_panen: {
      title: 'Jenis Alat Panen',
      table: ['Nama', 'Keterangan', 'Aksi'],
      fields: [{
          name: 'nama',
          label: 'Nama',
          type: 'text',
          required: true
        },
        {
          name: 'keterangan',
          label: 'Keterangan',
          type: 'text'
        }
      ]
    },
    sap: {
      title: 'No SAP',
      table: ['No SAP', 'Deskripsi', 'Aksi'],
      fields: [{
          name: 'no_sap',
          label: 'No SAP',
          type: 'text',
          required: true
        },
        {
          name: 'deskripsi',
          label: 'Deskripsi',
          type: 'text'
        }
      ]
    },
    jabatan: {
      title: 'Jabatan',
      table: ['Nama', 'Aksi'],
      fields: [{
        name: 'nama',
        label: 'Nama Jabatan',
        type: 'text',
        required: true
      }]
    },
    pupuk: {
      title: 'Pupuk',
      table: ['Nama', 'Satuan', 'Aksi'],
      fields: [{
          name: 'nama',
          label: 'Nama Pupuk',
          type: 'text',
          required: true
        },
        {
          name: 'satuan_id',
          label: 'Satuan',
          type: 'select',
          options: OPTIONS_SATUAN
        }
      ]
    },
    kode_aktivitas: {
      title: 'Kode Aktivitas',
      table: ['Kode', 'Nama', 'No SAP', 'Aksi'],
      fields: [{
          name: 'kode',
          label: 'Kode',
          type: 'text',
          required: true
        },
        {
          name: 'nama',
          label: 'Nama',
          type: 'text',
          required: true
        },
        {
          name: 'no_sap_id',
          label: 'No SAP',
          type: 'select',
          options: OPTIONS_SAP
        }
      ]
    },
    anggaran: {
      title: 'Anggaran',
      table: ['Periode', 'Unit', 'Kode Aktivitas', 'Pupuk', 'Angg Bulan', 'Angg Tahun', 'Aksi'],
      fields: [{
          name: 'tahun',
          label: 'Tahun',
          type: 'select',
          options: OPTIONS_TAHUN,
          required: true
        },
        {
          name: 'bulan',
          label: 'Bulan',
          type: 'select',
          options: OPTIONS_BULAN,
          required: true
        },
        {
          name: 'unit_id',
          label: 'Unit',
          type: 'select',
          options: OPTIONS_UNITS,
          required: true
        },
        {
          name: 'kode_aktivitas_id',
          label: 'Kode Aktivitas',
          type: 'select',
          options: OPTIONS_KODE,
          required: true
        },
        {
          name: 'pupuk_id',
          label: 'Pupuk',
          type: 'select',
          options: OPTIONS_PUPUK
        },
        {
          name: 'anggaran_bulan_ini',
          label: 'Anggaran Bulan Ini',
          type: 'number',
          step: '0.01',
          required: true,
          min: 0
        },
        {
          name: 'anggaran_tahun',
          label: 'Anggaran Tahun',
          type: 'number',
          step: '0.01',
          required: true,
          min: 0
        }
      ]
    },
    // --- [MODIFIED] Definisi Entitas Baru ---
    rayon: {
      title: 'Rayon',
      table: ['Nama Rayon', 'Aksi'],
      fields: [{
        name: 'nama',
        label: 'Nama Rayon',
        type: 'text',
        required: true
      }]
    },
    apl: {
      title: 'APL',
      table: ['Nama APL', 'Aksi'],
      fields: [{
        name: 'nama',
        label: 'Nama APL',
        type: 'text',
        required: true
      }]
    },
    keterangan: {
      title: 'Keterangan Master',
      table: ['Keterangan', 'Aksi'],
      fields: [{
        name: 'keterangan',
        label: 'Keterangan',
        type: 'textarea',
        required: true
      }]
    },
    asal_gudang: {
      title: 'Asal Gudang',
      table: ['Nama Gudang', 'Aksi'],
      fields: [{
        name: 'nama',
        label: 'Nama Gudang',
        type: 'text',
        required: true
      }]
    },

    // INI ADALAH TEMPAT YANG BENAR untuk definisi Pemeliharaan
    pem_tm: ENTITY_PEM('Pemeliharaan TM'),
    pem_tu: ENTITY_PEM('Pemeliharaan TU'),
    pem_tk: ENTITY_PEM('Pemeliharaan TK'),
    pem_tbm1: ENTITY_PEM('Pemeliharaan TBM I'),
    pem_tbm2: ENTITY_PEM('Pemeliharaan TBM II'),
    pem_tbm3: ENTITY_PEM('Pemeliharaan TBM III'),
    pem_pn: ENTITY_PEM('Pemeliharaan PN'),
    pem_mn: ENTITY_PEM('Pemeliharaan MN'),
  };


  // ================== HELPER FUNCTIONS ==================
  const modal = $('#crud-modal');
  const open = () => {
    modal.classList.remove('hidden');
    modal.classList.add('flex')
  };
  const close = () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex')
  };

  function renderHead(entity) {
    thead.innerHTML = '';
    ENTITIES[entity].table.forEach((h, i) => {
      const th = document.createElement('th');
      th.className = 'py-3 px-4 text-left font-semibold text-gray-700';
      th.textContent = h;
      if (i === ENTITIES[entity].table.length - 1) th.className += ' w-32'; // Lebar kolom Aksi
      thead.appendChild(th);
    });
    // tampilkan/hidden filter bar blok
    const bar = document.getElementById('blok-filter-bar');
    bar.classList.toggle('hidden', entity !== 'blok');
  }

  function inputEl(f) {
    const wrap = document.createElement('div');
    // If field type is textarea, make it span 2 columns
    if (f.type === 'textarea') {
      wrap.className = 'md:col-span-2';
    }
    const label = `<label class="block text-sm font-medium text-gray-700 mb-1">${f.label}${f.required?'<span class="text-red-500">*</span>':''}</label>`;
    let control = '';
    if (f.type === 'select') {
      const opts = (f.options || []).map(o => `<option value="${o.value}">${o.label}</option>`).join('');
      control = `<select id="${f.name}" name="${f.name}" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300" ${f.required?'required':''}>${opts}</select>`;
    } else if (f.type === 'textarea') {
      control = `<textarea id="${f.name}" name="${f.name}" rows="3" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300" ${f.required?'required':''}></textarea>`;
    } else {
      control = `<input type="${f.type||'text'}" ${f.step?`step="${f.step}"`:''} ${f.min?`min="${f.min}"`:''} ${f.max?`max="${f.max}"`:''} id="${f.name}" name="${f.name}" class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300" ${f.required?'required':''}>`;
    }
    wrap.innerHTML = label + control;
    return wrap;
  }


  function renderForm(entity, data = {}) {
    document.getElementById('modal-title').textContent = (data.id ? 'Edit ' : 'Tambah ') + ENTITIES[entity].title;
    document.getElementById('form-entity').value = entity;
    document.getElementById('form-action').value = data.id ? 'update' : 'store';
    document.getElementById('form-id').value = data.id || '';
    const holder = document.getElementById('form-fields');
    holder.innerHTML = '';
    ENTITIES[entity].fields.forEach(f => {
      const el = inputEl(f);
      holder.appendChild(el);
      // Pre-fill value if editing
      if (data && data[f.name] != null) {
        const inputElement = holder.querySelector(`#${f.name}`);
        if (inputElement) inputElement.value = data[f.name];
      }
    });
    open();
  }

  function cell(v) {
    return (v == null || v === '') ? '-' : v;
  }

  function actionButtons(entity, rowJson, id) {
    const payload = encodeURIComponent(JSON.stringify(rowJson));
    return `
    <div class="flex items-center gap-2">
      <button class="btn-edit icon-btn text-blue-600 hover:bg-blue-50" title="Edit" aria-label="Edit" data-entity="${entity}" data-json="${payload}">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M21.7 7.04a1 1 0 0 0 0-1.41l-3.33-3.33a1 1 0 0 0-1.41 0L4 14.26V18a1 1 0 0 0 1 1h3.74L21.7 7.04zM7.41 17H6v-1.41L15.06 6.5l1.41 1.41L7.41 17z"/></svg>
      </button>
      <button class="btn-del icon-btn text-rose-600 hover:bg-rose-50" title="Hapus" aria-label="Hapus" data-entity="${entity}" data-id="${id}">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M9 3a1 1 0 0 0-1 1v1H5a1 1 0 1 0 0 2h.59l.86 12.04A2 2 0 0 0 8.45 22h7.1a2 2 0 0 0 2-1.96L18.41 7H19a1 1 0 1 0 0-2h-3V4a1 1 0 0 0-1-1H9zm2 2h2v1h-2V5zm-1.58 3h7.16l-.82 11.5a.5.5 0 0 1-.5.47h-4.99a.5.5 0 0 1-.5-.47L9.42 8z"/></svg>
      </button>
    </div>`;
  }

  // --- RENDER TABLE ROWS based on entity ---
  function rowCols(entity, r) {
    switch (entity) {
      case 'kebun':
        return `<td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.nama_kebun)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'bahan_kimia':
        return `<td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.nama_bahan)}</td><td class="py-2.5 px-3">${cell(r.nama_satuan||'')}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'jenis_pekerjaan_mingguan':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'jenis_pekerjaan':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'unit':
        return `<td class="py-2.5 px-3">${cell(r.nama_unit)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'tahun_tanam':
        return `<td class="py-2.5 px-3">${cell(r.tahun)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'blok':
        return `<td class="py-2.5 px-3">${cell(r.nama_unit)}</td><td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.tahun_tanam)}</td><td class="py-2.5 px-3">${cell(r.luas_ha)}</td>`;
      case 'fisik':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'satuan':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'tenaga':
        return `<td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'mobil':
        return `<td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'alat_panen':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'sap':
        return `<td class="py-2.5 px-3">${cell(r.no_sap)}</td><td class="py-2.5 px-3">${cell(r.deskripsi)}</td>`;
      case 'jabatan':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'pupuk':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.nama_satuan||'')}</td>`;
      case 'kode_aktivitas':
        return `<td class="py-2.5 px-3">${cell(r.kode)}</td><td class="py-2.5 px-3">${cell(r.nama)}</td><td class="py-2.5 px-3">${cell(r.no_sap||'')}</td>`;
      case 'anggaran':
        return `<td class="py-2.5 px-3">${cell(r.bulan)} ${cell(r.tahun)}</td><td class="py-2.5 px-3">${cell(r.nama_unit)}</td><td class="py-2.5 px-3">${cell(r.kode_aktivitas)}</td><td class="py-2.5 px-3">${cell(r.nama_pupuk||'')}</td><td class="py-2.5 px-3">${(+r.anggaran_bulan_ini).toLocaleString()}</td><td class="py-2.5 px-3">${(+r.anggaran_tahun).toLocaleString()}</td>`;
        // --- [MODIFIED] Render Row untuk Entitas Baru ---
      case 'rayon':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'apl':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'keterangan':
        return `<td class="py-2.5 px-3">${cell(r.keterangan)}</td>`;
      case 'asal_gudang':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td>`;
      case 'pem_tm':
      case 'pem_tu':
      case 'pem_tk':
      case 'pem_tbm1':
      case 'pem_tbm2':
      case 'pem_tbm3':
      case 'pem_pn':
      case 'pem_mn':
        return `<td class="py-2.5 px-3">${cell(r.nama)}</td>
          <td class="py-2.5 px-3">${cell(r.deskripsi)}</td>`;

    }
    return ''; // Default case
  }


  function renderRows(entity, rows) {
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-gray-500">Belum ada data.</td></tr>`;
      return;
    }
    tbody.innerHTML = rows.map(r => `
    <tr class="border-b last:border-0 hover:bg-emerald-50/40 transition-colors">
      ${rowCols(entity, r)}
      <td class="py-2.5 px-3">${actionButtons(entity, r, r.id)}</td>
    </tr>
    `).join('');
  }

  // ===== Pagination UI =====
  function updatePageInfo() {
    const info = $('#page-info');
    const start = total ? ((page - 1) * perPage) + 1 : 0;
    const end = Math.min(page * perPage, total);
    info.textContent = `Menampilkan ${start.toLocaleString()}–${end.toLocaleString()} dari ${total.toLocaleString()} data`;
  }

  function renderPager() {
    const pager = $('#pager');
    pager.innerHTML = '';
    if (totalPages <= 1) return; // Jangan render jika hanya 1 halaman

    const makeBtn = (label, targetPage, disabled = false, active = false) => {
      const a = document.createElement('button');
      a.innerHTML = label; // Use innerHTML for symbols like «
      a.className = 'px-3 py-1 border rounded text-sm';
      if (active) {
        a.className += ' bg-emerald-600 text-white border-emerald-600 cursor-default';
      } else if (!disabled) {
        a.className += ' hover:bg-gray-100';
      }
      if (disabled) {
        a.disabled = true;
        a.classList.add('opacity-40', 'cursor-not-allowed');
      }
      a.addEventListener('click', () => {
        if (!disabled && !active) {
          page = targetPage;
          renderHeadAndLoad(currentEntity);
        }
      });
      pager.appendChild(a);
    };

    makeBtn('&laquo; First', 1, page <= 1);
    makeBtn('&lsaquo; Prev', Math.max(1, page - 1), page <= 1);

    const win = 5; // Window size for page numbers
    let start = Math.max(1, page - Math.floor(win / 2));
    let end = Math.min(totalPages, start + win - 1);
    start = Math.max(1, end - win + 1); // Adjust start if end hit the limit

    if (start > 1) {
      makeBtn('1', 1);
      if (start > 2) pager.insertAdjacentHTML('beforeend', '<span class="px-2">...</span>');
    }

    for (let p = start; p <= end; p++) {
      makeBtn(String(p), p, false, p === page);
    }

    if (end < totalPages) {
      if (end < totalPages - 1) pager.insertAdjacentHTML('beforeend', '<span class="px-2">...</span>');
      makeBtn(String(totalPages), totalPages);
    }

    makeBtn('Next &rsaquo;', Math.min(totalPages, page + 1), page >= totalPages);
    makeBtn('Last &raquo;', totalPages, page >= totalPages);
  }

  // ===== Helper: apply blok filter locally (client-side) =====
  function applyBlokFilterClient(rows) {
    let out = rows;
    if (blokFilter.unit_id) {
      out = out.filter(r => String(r.unit_id || '') === String(blokFilter.unit_id));
    }
    if (blokFilter.kode) {
      const kw = blokFilter.kode.toLowerCase();
      out = out.filter(r => String(r.kode || '').toLowerCase().includes(kw));
    }
    if (blokFilter.tahun) {
      out = out.filter(r => String(r.tahun_tanam || '') === String(blokFilter.tahun));
    }
    return out;
  }

  // ===== Data Loader (server or client pagination) =====
  function loadServer(entity) {
    const fd = new FormData();
    fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
    fd.append('action', 'list');
    fd.append('entity', entity);
    fd.append('page', String(page));
    fd.append('per_page', String(perPage));

    if (entity === 'blok') {
      fd.append('unit_id', blokFilter.unit_id || '');
      fd.append('kode', blokFilter.kode || '');
      fd.append('tahun', blokFilter.tahun || '');
      fd.append('filters', JSON.stringify(blokFilter)); // Send filters
    }

    return fetch('master_data_crud.php', {
        method: 'POST',
        body: fd
      })
      .then(r => {
        if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
        return r.json();
      })
      .then(j => {
        // Server-side pagination
        if (j && j.success && typeof j.total === 'number') {
          total = j.total;
          perPage = j.per_page ? parseInt(j.per_page) : perPage;
          page = j.page ? parseInt(j.page) : page;
          totalPages = Math.max(1, Math.ceil(total / perPage));
          renderRows(entity, j.data || []);
          updatePageInfo();
          renderPager();
          clientCache = {
            entity: null,
            rows: []
          }; // Clear client cache if server paginates
          return true;
        }
        // Fallback to client-side
        if (j && j.success && Array.isArray(j.data)) {
          clientCache = {
            entity,
            rows: j.data
          };
          let list = clientCache.rows;
          if (entity === 'blok') {
            list = applyBlokFilterClient(list);
          }
          total = list.length;
          totalPages = Math.max(1, Math.ceil(total / perPage));
          page = Math.min(page, totalPages); // Adjust page if it's out of bounds after filtering
          const start = (page - 1) * perPage;
          const slice = list.slice(start, start + perPage);
          renderRows(entity, slice);
          updatePageInfo();
          renderPager();
          return true;
        }
        throw new Error(j?.message || 'Gagal memuat data');
      });
  }

  function renderHeadAndLoad(entity) {
    renderHead(entity);
    tbody.innerHTML = `<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-gray-500">Memuat…</td></tr>`;
    $('#page-info').textContent = 'Memuat...';
    $('#pager').innerHTML = '';


    // If cache is valid for current entity (client-side mode)
    if (clientCache.entity === entity && clientCache.rows.length) {
      let list = clientCache.rows;
      if (entity === 'blok') {
        list = applyBlokFilterClient(list);
      }
      total = list.length;
      totalPages = Math.max(1, Math.ceil(total / perPage));
      page = Math.min(page, totalPages); // Adjust page number if needed
      const start = (page - 1) * perPage;
      const slice = list.slice(start, start + perPage);
      renderRows(entity, slice);
      updatePageInfo();
      renderPager();
      return;
    }

    // Load from server
    loadServer(entity).catch((err) => {
      console.error("Load error:", err);
      tbody.innerHTML = `<tr><td colspan="${ENTITIES[entity].table.length}" class="py-10 text-center text-red-500">Gagal memuat data. ${err.message || ''}</td></tr>`;
      $('#page-info').textContent = 'Gagal memuat';
      $('#pager').innerHTML = '';
    });
  }

  // ================== EVENT LISTENERS ==================

  // Tabs
  document.querySelectorAll('#tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#tabs button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentEntity = btn.dataset.entity;
      page = 1; // Reset page on tab change

      // Sync filter UI with state if switching to 'blok'
      if (currentEntity === 'blok') {
        document.getElementById('blok-filter-unit').value = blokFilter.unit_id || '';
        document.getElementById('blok-filter-kode').value = blokFilter.kode || '';
        document.getElementById('blok-filter-tahun').value = blokFilter.tahun || '';
      }

      renderHeadAndLoad(currentEntity);
    });
  });

  // Per-page selector
  document.getElementById('per-page').addEventListener('change', (e) => {
    perPage = parseInt(e.target.value) || 15;
    page = 1;
    renderHeadAndLoad(currentEntity);
  });

  // Add Button
  document.getElementById('btn-add').addEventListener('click', () => renderForm(currentEntity));

  // Edit/Delete Buttons (using event delegation on body)
  document.body.addEventListener('click', e => {
    const editBtn = e.target.closest('.btn-edit');
    const delBtn = e.target.closest('.btn-del');

    if (editBtn) {
      const entity = editBtn.dataset.entity;
      const data = JSON.parse(decodeURIComponent(editBtn.dataset.json));
      renderForm(entity, data);
    } else if (delBtn) {
      const entity = delBtn.dataset.entity;
      const id = delBtn.dataset.id;
      Swal.fire({
        title: 'Hapus data?',
        text: "Tindakan ini tidak dapat dibatalkan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
      }).then(res => {
        if (res.isConfirmed) {
          const fd = new FormData();
          fd.append('csrf_token', '<?= htmlspecialchars($CSRF) ?>');
          fd.append('action', 'delete');
          fd.append('entity', entity);
          fd.append('id', id);
          fetch('master_data_crud.php', {
              method: 'POST',
              body: fd
            })
            .then(r => r.json())
            .then(j => {
              if (j.success) {
                Swal.fire('Terhapus', '', 'success');
                clientCache = {
                  entity: null,
                  rows: []
                }; // Invalidate cache
                renderHeadAndLoad(entity); // Refresh
              } else Swal.fire('Gagal', j.message || 'Error', 'error');
            })
            .catch(() => Swal.fire('Gagal', 'Jaringan bermasalah', 'error'));
        }
      })
    }
  });

  // Modal Close/Cancel Buttons
  document.getElementById('btn-close').onclick = close;
  document.getElementById('btn-cancel').onclick = close;

  // Form Submit
  document.getElementById('crud-form').addEventListener('submit', e => {
    e.preventDefault();
    const formElement = e.target;
    const fd = new FormData(formElement);
    const entity = fd.get('entity');
    const def = ENTITIES[entity];
    let ok = true;

    // Basic client-side validation
    def.fields.forEach(f => {
      const input = formElement.querySelector(`[name="${f.name}"]`);
      if (f.required && !(input.value || '').trim()) {
        ok = false;
        // Add some visual indication (optional)
        input.classList.add('border-red-500');
      } else {
        input.classList.remove('border-red-500');
      }
    });

    if (!ok) {
      Swal.fire('Oops', 'Lengkapi semua field yang wajib diisi (*)', 'warning');
      return;
    }

    fetch('master_data_crud.php', {
        method: 'POST',
        body: fd
      })
      .then(r => r.json())
      .then(j => {
        if (j.success) {
          Swal.fire('Berhasil', j.message || 'Data tersimpan.', 'success');
          close();
          clientCache = {
            entity: null,
            rows: []
          }; // Invalidate cache
          page = 1; // Go to first page after save
          renderHeadAndLoad(entity); // Refresh
        } else {
          // Display specific errors if available, otherwise generic message
          const errorMsg = j.errors ? j.errors.join('<br>') : (j.message || 'Terjadi kesalahan.');
          Swal.fire('Gagal', errorMsg, 'error');
        }
      })
      .catch((err) => Swal.fire('Gagal', `Jaringan bermasalah: ${err.message}`, 'error'));
  });

  // ====== EVENT: Apply / Reset Filter BLOK ======
  document.getElementById('blok-filter-apply').addEventListener('click', (e) => {
    e.preventDefault();
    blokFilter.unit_id = document.getElementById('blok-filter-unit').value.trim();
    blokFilter.kode = document.getElementById('blok-filter-kode').value.trim();
    blokFilter.tahun = document.getElementById('blok-filter-tahun').value.trim();
    page = 1;
    // Invalidate client cache to force server refetch/re-filter
    clientCache = {
      entity: null,
      rows: []
    };
    renderHeadAndLoad('blok');
  });

  document.getElementById('blok-filter-reset').addEventListener('click', (e) => {
    e.preventDefault();
    blokFilter.unit_id = '';
    blokFilter.kode = '';
    blokFilter.tahun = '';
    document.getElementById('blok-filter-unit').value = '';
    document.getElementById('blok-filter-kode').value = '';
    document.getElementById('blok-filter-tahun').value = '';
    page = 1;
    clientCache = {
      entity: null,
      rows: []
    }; // Invalidate cache
    renderHeadAndLoad('blok');
  });

  // Initial Load
  renderHeadAndLoad(currentEntity);
});
</script>

<style>
  #tabs .tab {
    padding: .5rem .75rem;
    border-radius: .5rem;
    border: 1px solid transparent;
    color: #334155;
    /* gray-700 */
    background: #f0fdf4;
    /* green-50 */
    transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    white-space: nowrap;
  }

  #tabs .tab:hover {
    background: #dcfce7;
    /* green-100 */
    color: #166534;
    /* green-800 */
  }

  #tabs .tab.active {
    background: #22c55e;
    /* green-500 */
    border-color: #16a34a;
    /* green-600 */
    color: white;
    font-weight: 600;
  }

  .icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: .375rem;
    /* rounded-md */
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background-color .15s ease;
  }

  .icon-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
  }

  .icon-btn:active {
    transform: scale(.98);
  }

  table thead th {
    position: sticky;
    top: 0;
    background: #f9fafb;
    z-index: 10;
  }

  /* bg-gray-50 */
  /* Styling for required field indicator */
  input:required,
  select:required,
  textarea:required {
    border-left: 3px solid #f87171;
    /* red-400 */
  }

  input:invalid,
  select:invalid,
  textarea:invalid {
    border-color: #f87171;
  }

  /* For native validation */
</style>