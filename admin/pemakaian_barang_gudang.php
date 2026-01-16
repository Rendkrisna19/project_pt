<?php
// pages/pemakaian_barang_gudang.php
// MODIFIKASI FULL: Role Akses (Viewer/Staf/Admin) tanpa merubah ID/Class layout.

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: ../auth/login.php"); exit; }

// --- 1. SETUP ROLE ---
$userRole = $_SESSION['user_role'] ?? 'viewer'; // Default ke viewer demi keamanan

$isAdmin   = ($userRole === 'admin');
$isStaf    = ($userRole === 'staf');
$isViewer  = ($userRole === 'viewer');

// Admin & Staf boleh Input. Hanya Admin boleh Edit/Hapus.
$canInput  = ($isAdmin || $isStaf); 
$canAction = ($isAdmin); 

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

// --- AMBIL MASTER DATA ---
$kebuns = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun")->fetchAll(PDO::FETCH_ASSOC);
$bahans = $conn->query("SELECT id, nama, satuan FROM md_jenis_bahan_bakar_pelumas ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

// Data Waktu Default
$bulanList = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
$f_tahun = date('Y');
$f_bulan = date('n'); 

function qs_export($y, $m) { return "tahun=$y&bulan=$m"; }

$currentPage = 'pemakaian_barang_gudang';
include_once '../layouts/header.php';
?>

<style>
.sticky-container { max-height: 70vh; overflow: auto; border: 1px solid #cbd5e1; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background: #fff; }

table.table-grid { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1200px; }
table.table-grid th, table.table-grid td { padding: 0.65rem; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle; }
table.table-grid th:last-child, table.table-grid td:last-child { border-right: none; }

/* Sticky Header Biru */
table.table-grid thead th { position: sticky; top: 0; background: #059fd3; color: white; z-index: 10; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

/* Sticky Footer Total */
table.table-grid tfoot td { position: sticky; bottom: 0; background: #f0f9ff; color: #0c4a6e; font-weight: bold; z-index: 10; border-top: 2px solid #bae6fd; }

.i-select, .i-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.35rem 0.5rem; font-size: 0.875rem; outline: none; }
.i-select:focus, .i-input:focus { border-color: #059fd3; box-shadow: 0 0 0 2px rgba(5,159,211,0.15); }

/* Button Styles */
.btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; border: 1px solid transparent; text-decoration: none; }
.btn-primary { background: #059fd3; color: white; } .btn-primary:hover { background: #0386b3; }
.btn-excel { background: #0097e8ff; color: white; } .btn-excel:hover { background: #058596ff; }
.btn-pdf { background: #ef4444; color: white; } .btn-pdf:hover { background: #dc2626; }
</style>

<div class="space-y-6">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Pemakaian BBM</h1>
            <p class="text-gray-500 text-sm mt-1">Monitoring pemakaian bahan bakar, pelumas, dan sparepart.</p>
        </div>
        <div class="flex gap-2">
            <a id="btn-excel" href="cetak/pemakaian_barang_gudang_excel.php?<?= qs_export($f_tahun, $f_bulan) ?>" class="btn btn-excel">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M8 11h8"/><path d="M8 15h8"/><path d="M8 19h8"/></svg>
                Excel
            </a>
            <a id="btn-pdf" href="cetak/pemakaian_barang_gudang_pdf.php?<?= qs_export($f_tahun, $f_bulan) ?>" target="_blank" class="btn btn-pdf">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M12 17v-6"/><path d="M9 14l3 3 3-3"/></svg>
                PDF
            </a>
            
            <?php if ($canInput): ?>
            <button id="btn-add" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Tambah Data
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tahun</label>
            <select id="f_tahun" class="i-select filter-input">
                <option value="">Semua Tahun</option>
                <?php for($y=2020; $y<=date('Y')+1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y==$f_tahun?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bulan</label>
            <select id="f_bulan" class="i-select filter-input">
                <option value="">Semua Bulan</option>
                <?php foreach($bulanList as $i => $nm): ?>
                    <option value="<?= $i+1 ?>" <?= ($i+1)==$f_bulan?'selected':'' ?>><?= $nm ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kebun</label>
            <select id="f_kebun" class="i-select filter-input">
                <option value="">— Semua Kebun —</option>
                <?php foreach($kebuns as $k): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Jenis Bahan</label>
            <select id="f_bahan" class="i-select filter-input">
                <option value="">— Semua Bahan —</option>
                <?php foreach($bahans as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tanggal Spesifik</label>
            <input type="date" id="f_tanggal" class="i-input filter-input">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Baris / Hal</label>
            <select id="f_limit" class="i-select filter-input">
                <option value="10">10 Baris</option>
                <option value="25" selected>25 Baris</option>
                <option value="50">50 Baris</option>
                <option value="100">100 Baris</option>
                <option value="all">Semua</option>
            </select>
        </div>
    </div>

    <div class="sticky-container">
        <table class="table-grid">
            <thead>
                <tr>
                    <th style="width: 60px;">Tahun</th>
                    <th>Kebun</th>
                    <th style="width: 100px;">Tanggal</th>
                    <th style="width: 90px;">Bulan</th>
                    <th>No. Dokumen</th>
                    <th>Jenis Bahan</th>
                    <th style="width: 80px;">Satuan</th>
                    <th class="text-right" style="width: 100px;">Jlh Bahan</th>
                    <th>Keterangan</th>
                    <?php if ($canAction): ?>
                    <th class="text-center" style="width: 80px;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody-data" class="text-gray-700 bg-white">
                <tr><td colspan="<?= $canAction ? 10 : 9 ?>" class="text-center py-8 text-gray-400">Memuat data...</td></tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="text-right pr-4 uppercase">Total Jumlah:</td>
                    <td class="text-right font-mono text-blue-700" id="footer-total">0.00</td>
                    <td colspan="<?= $canAction ? 2 : 1 ?>"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if ($canInput): ?>
<div id="modal-form" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden transform scale-100 transition-all">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="text-xl font-bold text-gray-800" id="modal-title">Input Pemakaian</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <form id="form-data" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
            <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
            <input type="hidden" name="action" id="form-action" value="store">
            <input type="hidden" name="id" id="form-id">

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tanggal *</label>
                <input type="date" name="tanggal" id="inp_tanggal" class="i-input" required>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Kebun *</label>
                <select name="kebun_id" id="inp_kebun" class="i-select" required>
                    <option value="">Pilih Kebun</option>
                    <?php foreach($kebuns as $k): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kebun']) ?></option><?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Jenis Bahan *</label>
                <select name="jenis_bahan_id" id="inp_bahan" class="i-select" required onchange="updateSatuan()">
                    <option value="" data-satuan="">Pilih Bahan</option>
                    <?php foreach($bahans as $b): ?><option value="<?= $b['id'] ?>" data-satuan="<?= htmlspecialchars($b['satuan']) ?>"><?= htmlspecialchars($b['nama']) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Jumlah Bahan</label>
                <div class="flex gap-2">
                    <input type="number" step="0.01" name="jumlah" id="inp_jumlah" class="i-input" required placeholder="0.00">
                    <input type="text" id="txt_satuan" class="i-input w-24 bg-gray-100 text-center text-gray-600" readonly placeholder="Satuan">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">No. Dokumen</label>
                <input type="text" name="no_dokumen" id="inp_dokumen" class="i-input" placeholder="Contoh: SPB-001">
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Keterangan</label>
                <textarea name="keterangan" id="inp_ket" rows="2" class="i-input"></textarea>
            </div>

            <div class="md:col-span-2 flex justify-end gap-3 mt-4 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeModal()" class="px-5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50 transition">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 shadow-lg shadow-cyan-500/30 transition">Simpan Data</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const $ = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);
const bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// --- PASS PHP ROLE TO JS ---
const CAN_ACTION = <?= $canAction ? 'true' : 'false' ?>; // Admin Only
const CAN_INPUT  = <?= $canInput ? 'true' : 'false' ?>;  // Admin & Staf

// Update Satuan di Modal
function updateSatuan() {
    const sel = $('#inp_bahan');
    const sat = sel.options[sel.selectedIndex].getAttribute('data-satuan') || '';
    $('#txt_satuan').value = sat;
}

// Function: Load Data Realtime
async function loadData() {
    const tahun   = $('#f_tahun').value;
    const bulan   = $('#f_bulan').value;
    const kebun   = $('#f_kebun').value;
    const tanggal = $('#f_tanggal').value;
    const bahan   = $('#f_bahan').value;
    const limit   = $('#f_limit').value;

    const qs = new URLSearchParams({
        tahun: tahun,
        bulan: bulan,
        kebun_id: kebun,
        tanggal: tanggal,
        jenis_bahan_id: bahan
    }).toString();

    $('#btn-excel').href = `cetak/pemakaian_barang_gudang_excel.php?${qs}`;
    $('#btn-pdf').href = `cetak/pemakaian_barang_gudang_pdf.php?${qs}`;

    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('tahun', tahun);
    fd.append('bulan', bulan);
    fd.append('kebun_id', kebun);
    fd.append('tanggal', tanggal);
    fd.append('jenis_bahan_id', bahan);
    fd.append('limit', limit);

    try {
        const res = await fetch('pemakaian_barang_gudang_crud.php', { method: 'POST', body: fd });
        const json = await res.json();

        // Tentukan jumlah kolom saat kosong/loading
        const colSpan = CAN_ACTION ? 10 : 9;

        if(json.success) {
            const rows = json.data;
            if(rows.length === 0) {
                $('#tbody-data').innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-8 text-gray-400 italic">Tidak ada data ditemukan.</td></tr>`;
                $('#footer-total').innerText = '0.00';
                return;
            }

            let html = '';
            rows.forEach(r => {
                const d = new Date(r.tanggal);
                const thn = d.getFullYear();
                const bln = bulanIndo[d.getMonth() + 1];
                const rowJson = encodeURIComponent(JSON.stringify(r));

                // Logic Tombol Aksi (Hanya Render jika Admin)
                let actionCell = '';
                if(CAN_ACTION) {
                    actionCell = `
                    <td class="text-center">
                        <div class="flex justify-center gap-1">
                            <button onclick="editData('${rowJson}')" class="p-1 text-cyan-600 hover:bg-cyan-100 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>
                            <button onclick="deleteData(${r.id})" class="p-1 text-red-600 hover:bg-red-100 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                        </div>
                    </td>`;
                }

                html += `
                <tr class="hover:bg-blue-50 transition">
                    <td>${thn}</td>
                    <td>${r.nama_kebun || '-'}</td>
                    <td class="text-center">${r.tanggal}</td>
                    <td class="text-center">${bln}</td>
                    <td>${r.no_dokumen || '-'}</td>
                    <td class="font-medium text-slate-700">${r.nama_bahan || '-'}</td>
                    <td class="text-center text-xs bg-gray-100 rounded px-1">${r.satuan || ''}</td>
                    <td class="text-right font-mono font-bold text-blue-600">${parseFloat(r.jumlah).toLocaleString('en-US', {minimumFractionDigits:2})}</td>
                    <td class="text-sm text-gray-500 truncate max-w-xs">${r.keterangan || ''}</td>
                    ${actionCell}
                </tr>`;
            });
            $('#tbody-data').innerHTML = html;
            $('#footer-total').innerText = parseFloat(json.total_jumlah).toLocaleString('en-US', {minimumFractionDigits:2});
        }
    } catch (e) {
        console.error(e);
        const colSpan = CAN_ACTION ? 10 : 9;
        $('#tbody-data').innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-8 text-red-500">Gagal memuat data.</td></tr>`;
    }
}

// Modal Logic
function openModal() { 
    if(!CAN_INPUT) return; // Guard extra
    $('#modal-form').classList.remove('hidden'); 
    $('#modal-form').classList.add('flex'); 
}
function closeModal() { 
    if(!CAN_INPUT) return;
    $('#modal-form').classList.add('hidden'); 
    $('#modal-form').classList.remove('flex'); 
}

// Bind Add Button (Check if exists first)
if(CAN_INPUT && $('#btn-add')) {
    $('#btn-add').addEventListener('click', () => {
        $('#form-data').reset();
        $('#form-action').value = 'store';
        $('#form-id').value = '';
        $('#modal-title').innerText = 'Input Pemakaian';
        $('#txt_satuan').value = '';
        openModal();
    });
}

// Edit Data (Hanya akan dipanggil jika tombol render via CAN_ACTION)
function editData(jsonStr) {
    if(!CAN_ACTION) return; // Guard
    const r = JSON.parse(decodeURIComponent(jsonStr));
    $('#form-action').value = 'update';
    $('#form-id').value = r.id;
    $('#modal-title').innerText = 'Edit Pemakaian';
    
    $('#inp_tanggal').value = r.tanggal;
    $('#inp_kebun').value = r.kebun_id;
    $('#inp_bahan').value = r.jenis_bahan_id;
    $('#inp_jumlah').value = r.jumlah;
    $('#inp_dokumen').value = r.no_dokumen;
    $('#inp_ket').value = r.keterangan;
    
    updateSatuan(); 
    openModal();
}

// Delete Data (Hanya akan dipanggil jika tombol render via CAN_ACTION)
function deleteData(id) {
    if(!CAN_ACTION) return; // Guard
    Swal.fire({
        title: 'Hapus Data?', text: "Data tidak bisa dikembalikan!", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('pemakaian_barang_gudang_crud.php', {method: 'POST', body: fd})
            .then(r => r.json()).then(j => {
                if(j.success) { Swal.fire('Terhapus!', '', 'success'); loadData(); }
                else Swal.fire('Gagal', j.message, 'error');
            });
        }
    })
}

// Submit Handler (Hanya jika form ada)
if($('#form-data')) {
    $('#form-data').addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fetch('pemakaian_barang_gudang_crud.php', {method: 'POST', body: fd})
        .then(r => r.json()).then(j => {
            if(j.success) {
                closeModal();
                Swal.fire('Berhasil', 'Data telah disimpan', 'success');
                loadData();
            } else {
                Swal.fire('Error', j.message || 'Gagal menyimpan', 'error');
            }
        });
    });
}

// Auto Trigger Filter
$$('.filter-input').forEach(el => {
    el.addEventListener('change', () => loadData());
});

// Init
loadData();
</script>

<?php include_once '../layouts/footer.php'; ?>