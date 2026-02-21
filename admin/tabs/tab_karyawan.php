<style>
    /* Container Tabel agar bisa di-scroll horizontal & vertikal */
    .table-container {
        max-height: 70vh;
        overflow: auto;
        position: relative;
        border: 1px solid #cbd5e1;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Style Dasar Tabel */
    table.table-grid {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        /* DIPERLEBAR AGAR 30 KOLOM MUAT RAPI */
        min-width: 4500px; 
    }

    table.table-grid th,
    table.table-grid td {
        padding: 0.75rem;
        font-size: 0.85rem;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        vertical-align: middle;
        background-color: #fff;
        white-space: nowrap; /* Mencegah text turun ke bawah */
    }

    /* HEADER STICKY (Freeze Top) - WARNA CYAN */
    table.table-grid thead th {
        position: sticky;
        top: 0;
        background: #0e7490; /* Cyan-700 */
        color: #fff;
        z-index: 40;
        font-weight: 700;
        text-transform: uppercase;
        height: 50px;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
        text-align: center;
    }

    /* KOLOM STICKY (Freeze Left) */
    th.sticky-col,
    td.sticky-col {
        position: sticky;
        left: 0;
        z-index: 20;
        border-right: 2px solid #cbd5e1;
    }

    /* Header yang juga Sticky Column (Pojok Kiri Atas) */
    thead th.sticky-col {
        z-index: 50;
        background: #0e7490;
    }

    /* Posisi Kolom yang di-Freeze (Lebar harus fix) */
    .col-foto { left: 0px; width: 70px; text-align: center; }
    .col-sap { left: 70px; width: 100px; }
    .col-old { left: 170px; width: 100px; }
    .col-nama { left: 270px; width: 250px; }

    /* Fix visual saat hover row */
    tr:hover td { background-color: #ecfeff !important; }

    /* Aksi Sticky di Kanan */
    th.sticky-action,
    td.sticky-action {
        position: sticky;
        right: 0;
        z-index: 20;
        border-left: 2px solid #cbd5e1;
        text-align: center;
        width: 100px;
    }
    thead th.sticky-action { z-index: 50; }
    
    /* Input Style Custom */
    .lbl {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        color: #4b5563;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
        letter-spacing: 0.025em;
    }
    .inp {
        width: 100%;
        border: 1px solid #d1d5db;
        padding: 0.5rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        color: #1f2937;
        transition: border-color 0.2s;
    }
    .inp:focus {
        outline: none;
        border-color: #0891b2;
        box-shadow: 0 0 0 2px #a5f3fc;
    }
</style>

<div class="space-y-4">
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
        <div class="flex p-1 bg-gray-100 rounded-lg border border-gray-200">
            <button onclick="switchView('active')" id="tab-active" class="px-4 py-2 text-sm font-bold rounded-md shadow-sm bg-white text-cyan-800 transition flex items-center gap-2">
                <i class="ti ti-users"></i> Karyawan Aktif
            </button>
            <button onclick="switchView('pension')" id="tab-pension" class="px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition flex items-center gap-2">
                <i class="ti ti-user-off"></i> Monitoring Pensiun
            </button>
        </div>

        <div class="flex flex-wrap gap-2 justify-end">
            <div class="flex bg-gray-100 p-1 rounded-lg border border-gray-200 gap-1">
                <button onclick="doExport('pdf')" class="px-3 py-2 bg-white text-red-600 border border-gray-200 rounded-md hover:bg-red-50 text-sm font-bold flex items-center gap-2 shadow-sm transition" title="Export PDF">
                    <i class="ti ti-file-type-pdf text-lg"></i> <span class="hidden md:inline">PDF</span>
                </button>

                <button onclick="doExport('excel_data')" class="px-3 py-2 bg-white text-green-700 border border-gray-200 rounded-md hover:bg-green-50 text-sm font-bold flex items-center gap-2 shadow-sm transition" title="Export Excel">
                    <i class="ti ti-table-export text-lg"></i> <span class="hidden md:inline">Excel Data</span>
                </button>

                <a href="cetak/template_karyawan.php" target="_blank" class="px-3 py-2 bg-white text-slate-600 border border-gray-200 rounded-md hover:bg-slate-50 text-sm font-bold flex items-center gap-2 shadow-sm transition" title="Download Template">
                    <i class="ti ti-template text-lg"></i> <span class="hidden md:inline">Template</span>
                </a>
            </div>

            <?php if ($canInput): ?>
                <?php if($_SESSION['user_role'] === 'admin'): ?>
                <button onclick="deleteAllData()" class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-bold flex items-center gap-2 shadow-sm transition border border-red-800" title="Reset Database">
                    <i class="ti ti-trash-x"></i>
                </button>
                <?php endif; ?>

                <button onclick="openImportModal()" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-bold flex items-center gap-2 shadow-sm transition">
                    <i class="ti ti-file-spreadsheet"></i> Import
                </button>
                
                <button id="btn-add" class="px-4 py-2 bg-cyan-800 text-white rounded-lg hover:bg-cyan-900 text-sm font-bold flex items-center gap-2 shadow-sm transition">
                    <i class="ti ti-plus"></i> Baru
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl border border-gray-200 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
        <div class="flex flex-wrap gap-3 items-center w-full md:w-auto text-sm">
            <div class="flex items-center gap-2">
                <span class="text-gray-600 font-semibold">Show:</span>
                <select id="limit" class="bg-gray-50 border border-gray-300 text-gray-800 rounded-lg p-2 focus:ring-2 focus:ring-cyan-500 outline-none cursor-pointer">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <select id="f_kebun" class="bg-gray-50 border border-gray-300 text-gray-800 rounded-lg p-2 w-40 cursor-pointer outline-none focus:ring-2 focus:ring-cyan-500">
                <option value="">Semua Kebun</option>
            </select>

            <select id="f_afdeling" class="bg-gray-50 border border-gray-300 text-gray-800 rounded-lg p-2 w-40 cursor-pointer outline-none focus:ring-2 focus:ring-cyan-500">
                <option value="">Semua Afdeling</option>
            </select>
        </div>

        <div class="relative w-full md:w-80 group">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="ti ti-search text-gray-400 group-focus-within:text-cyan-600"></i>
            </div>
            <input type="text" id="q" class="block w-full p-2.5 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition" placeholder="Cari Nama, NIK, atau SAP ID...">
        </div>
    </div>

    <div class="table-container bg-white">
        <table class="table-grid" id="table-karyawan">
            <thead>
                <tr>
                    <th class="sticky-col col-foto">Foto</th>
                    <th class="sticky-col col-sap">SAP ID</th>
                    <th class="sticky-col col-old">Old Pers</th>
                    <th class="sticky-col col-nama text-left">Nama Lengkap</th>
                    
                    <th class="text-left">NIK KTP</th>
                    <th class="text-center">Gender</th>
                    <th class="text-left">Tempat Lahir</th>
                    <th class="text-center">Tgl Lahir</th>
                    <th class="text-left">Agama</th>
                    <th class="text-left">No HP</th>
                    <th class="text-center">Status Kel.</th>

                    <th class="text-left">Nama Kebun</th>
                    <th class="text-left">Afdeling</th>
                    <th class="text-left">Jabatan Real</th>
                    <th class="text-left">Jabatan SAP</th>
                    <th class="text-center">Status Kry</th>
                    <th class="text-center">Person Grade</th>
                    <th class="text-center">Gol. PHDP</th>

                    <th class="text-center">TMT Kerja</th>
                    <th class="text-center">TMT MBT</th>
                    <th class="text-center">TMT Pensiun</th>

                    <th class="text-center">Status Pajak</th>
                    <th class="text-left">Tax ID (NPWP)</th>
                    <th class="text-left">BPJS ID</th>
                    <th class="text-left">Jamsostek ID</th>

                    <th class="text-left">Nama Bank</th>
                    <th class="text-left">No Rekening</th>
                    <th class="text-left">A.N Rekening</th>

                    <th class="text-left">Pendidikan</th>
                    <th class="text-left">Jurusan</th>
                    <th class="text-left">Institusi</th>
                    <th class="text-center">Dokumen</th>

                    <th class="sticky-action">Aksi</th>
                </tr>
            </thead>
            <tbody id="tbody-data" class="text-gray-700">
            </tbody>
        </table>
    </div>

    <div class="bg-gray-50 px-4 py-3 border border-gray-200 rounded-b-xl flex flex-col sm:flex-row justify-between items-center gap-4 mt-[-1rem] z-10 relative">
        <div class="text-sm text-gray-600">
            Menampilkan <span class="font-bold text-gray-900" id="info-start">0</span>
            sampai <span class="font-bold text-gray-900" id="info-end">0</span>
            dari <span class="font-bold text-gray-900" id="info-total">0</span> data
        </div>
        <div class="inline-flex rounded-md shadow-sm gap-1" id="pagination-controls">
        </div>
    </div>
</div>

<div id="import-modal" class="fixed inset-0 bg-gray-900/60 z-[60] hidden items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform scale-100 transition-transform">
        <div class="bg-cyan-700 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-lg flex items-center gap-2"><i class="ti ti-file-spreadsheet"></i> Import Data Excel</h3>
            <button onclick="closeImportModal()" class="hover:text-red-200 transition"><i class="ti ti-x text-xl"></i></button>
        </div>
        <div class="p-6">
            <div class="bg-cyan-50 border border-cyan-100 p-4 rounded-lg text-sm text-cyan-800 mb-5">
                <i class="ti ti-info-circle mr-1"></i>
                Gunakan <a href="cetak/template_karyawan.php" class="font-bold underline hover:text-cyan-900">Template Terbaru</a>.
                Pastikan kolom <strong>Status Tax</strong>, <strong>Kebun</strong>, dan <strong>Pendidikan</strong> terisi.
            </div>
            <form id="form-import">
                <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                <input type="hidden" name="action" value="import_excel_lib">

                <label class="block text-sm font-bold text-gray-700 mb-2">Pilih File (.xlsx / .csv)</label>
                <input type="file" name="file_excel" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-cyan-100 file:text-cyan-700 hover:file:bg-cyan-200 mb-6 cursor-pointer" required>

                <button type="submit" class="w-full bg-cyan-700 text-white py-2.5 rounded-lg font-bold hover:bg-cyan-800 shadow-lg transition flex justify-center items-center gap-2">
                    <i class="ti ti-upload"></i> Proses Import
                </button>
            </form>
        </div>
    </div>
</div>

<?php if ($canInput): ?>
    <div id="crud-modal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white w-full max-w-7xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
                <div class="px-8 py-5 border-b flex justify-between items-center bg-gray-50">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="ti ti-user-edit text-cyan-600"></i> Form Data Karyawan
                    </h3>
                    <button id="btn-close" class="text-gray-400 hover:text-red-500 transition"><i class="ti ti-x text-2xl"></i></button>
                </div>

                <form id="crud-form" class="flex-1 overflow-y-auto p-8 grid grid-cols-1 md:grid-cols-4 gap-8 bg-white">
                    <input type="hidden" name="csrf_token" value="<?= $CSRF ?>">
                    <input type="hidden" name="action" id="form-action">
                    <input type="hidden" name="id" id="form-id">

                    <div class="space-y-5 border-r border-dashed border-gray-200 pr-4">
                        <h4 class="text-sm font-bold text-cyan-700 uppercase tracking-wider border-b pb-1">Identitas</h4>

                        <div class="text-center group">
                            <div class="w-32 h-32 mx-auto bg-gray-100 rounded-full overflow-hidden border-4 border-white shadow-md relative">
                                <img id="preview-foto" src="../assets/img/default-avatar.png" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/50 hidden group-hover:flex items-center justify-center text-white cursor-pointer transition" onclick="document.getElementById('foto_karyawan').click()">
                                    <i class="ti ti-camera text-3xl"></i>
                                </div>
                            </div>
                            <input type="file" name="foto_karyawan" id="foto_karyawan" class="hidden" accept="image/*" onchange="previewImage(this)">
                            <p class="text-xs text-gray-500 mt-2 italic">Klik foto untuk mengganti</p>
                        </div>

                        <div>
                            <label class="lbl">SAP ID <span class="text-red-500">*</span></label>
                            <input type="text" name="sap_id" id="sap_id" class="inp font-mono font-bold" required placeholder="Ex: 2024001">
                        </div>
                        <div>
                            <label class="lbl">Old Pers No</label>
                            <input type="text" name="old_pers_no" id="old_pers_no" class="inp" placeholder="Ex: P-123">
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                            <label class="lbl text-blue-800">Upload Dokumen</label>
                            <input type="file" name="dokumen_file" id="dokumen_file" class="inp text-xs bg-white" accept=".pdf,.doc,.docx">
                            <div id="link-dokumen" class="text-xs mt-1 text-gray-500">Max 2MB (PDF/Doc)</div>
                        </div>
                    </div>

                    <div class="space-y-5 border-r border-dashed border-gray-200 pr-4">
                        <h4 class="text-sm font-bold text-cyan-700 uppercase tracking-wider border-b pb-1">Data Pribadi</h4>

                        <div>
                            <label class="lbl">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" name="nama_karyawan" id="nama_karyawan" class="inp" required>
                        </div>
                        <div>
                            <label class="lbl">NIK KTP (16 Digit)</label>
                            <input type="text" name="nik_ktp" id="nik_ktp" class="inp font-mono" maxlength="16">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="lbl">Gender</label>
                                <select name="gender" id="gender" class="inp cursor-pointer">
                                    <option value="">-Pilih-</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Agama</label>
                                <select name="agama" id="agama" class="inp cursor-pointer">
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="lbl">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" id="tempat_lahir" class="inp">
                            </div>
                            <div>
                                <label class="lbl">Tgl Lahir</label>
                                <input type="date" name="tgl_lahir" id="tgl_lahir" class="inp">
                            </div>
                        </div>
                        <div>
                            <label class="lbl">Status Tax (PTKP)</label>
                            <input type="text" name="status_pajak" id="status_pajak" class="inp" placeholder="Contoh: K/0, TK/0, K/1">
                        </div>
                         <div>
                            <label class="lbl">No HP</label>
                            <input type="text" name="no_hp" id="no_hp" class="inp">
                        </div>
                    </div>

                    <div class="space-y-5 border-r border-dashed border-gray-200 pr-4">
                        <h4 class="text-sm font-bold text-cyan-700 uppercase tracking-wider border-b pb-1">Kepegawaian</h4>

                        <div>
                            <label class="lbl">Nama Kebun <span class="text-red-500">*</span></label>
                            <input type="text" name="kebun_id" id="kebun_id" class="inp" list="list-kebun" placeholder="Ketik Nama Kebun..." autocomplete="off" required>
                            <datalist id="list-kebun"></datalist>
                        </div>

                        <div>
                            <label class="lbl">Afdeling / Unit</label>
                            <input type="text" name="afdeling" id="afdeling" class="inp" placeholder="Ex: Afdeling 1">
                        </div>
                        <div>
                            <label class="lbl">Jabatan Real</label>
                            <input type="text" name="jabatan_real" id="jabatan_real" class="inp">
                        </div>
                        <div>
                            <label class="lbl">Jabatan SAP</label>
                            <input type="text" name="jabatan_sap" id="jabatan_sap" class="inp">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="lbl">Status </label>
                                <select name="status_karyawan" id="status_karyawan" class="inp cursor-pointer">
                                    <option value="KARPIM">KARPIM</option>
                                    <option value="TS">TS</option>
                                    <option value="KNG">KNG</option>
                                    <option value="PKWT">PKWT</option>
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Grade</label>
                                <input type="text" name="person_grade" id="person_grade" class="inp text-center">
                            </div>
                        </div>
                        <div>
                            <label class="lbl">Status Keluarga</label>
                            <input type="text" name="status_keluarga" id="status_keluarga" class="inp" placeholder="Contoh: K/1, TK/0">
                        </div>
                        <div>
                            <label class="lbl">Gol PHDP</label>
                            <input type="text" name="phdp_golongan" id="phdp_golongan" class="inp">
                        </div>
                    </div>

                    <div class="space-y-5">
                        <h4 class="text-sm font-bold text-cyan-700 uppercase tracking-wider border-b pb-1">Lainnya</h4>

                        <div>
                            <label class="lbl">Pendidikan Terakhir</label>
                            <select name="pendidikan_terakhir" id="pendidikan_terakhir" class="inp cursor-pointer">
                                <option value="">-Pilih-</option>
                                <option value="SD">SD</option>
                                <option value="SMP">SMP</option>
                                <option value="SMA">SMA/SMK</option>
                                <option value="D3">D3</option>
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="lbl">Jurusan</label>
                                <input type="text" name="jurusan" id="jurusan" class="inp">
                            </div>
                            <div>
                                <label class="lbl">Institusi</label>
                                <input type="text" name="institusi" id="institusi" class="inp">
                            </div>
                        </div>

                        <div class="pt-2 border-t border-gray-100"></div>

                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="lbl text-blue-700">TMT Masuk Kerja</label>
                                <input type="date" name="tmt_kerja" id="tmt_kerja" class="inp bg-blue-50 border-blue-200">
                            </div>
                            <div>
                                <label class="lbl text-orange-700">TMT MBT</label>
                                <input type="date" name="tmt_mbt" id="tmt_mbt" class="inp bg-orange-50 border-orange-200">
                            </div>
                            <div>
                                <label class="lbl text-red-700">TMT Pensiun</label>
                                <input type="date" name="tmt_pensiun" id="tmt_pensiun" class="inp bg-red-50 border-red-200">
                            </div>
                        </div>
                        
                        <div class="pt-2">
                             <label class="lbl">No Rekening</label>
                             <input type="text" name="no_rekening" id="no_rekening" class="inp">
                        </div>
                        <div>
                             <label class="lbl">Nama Bank</label>
                             <input type="text" name="nama_bank" id="nama_bank" class="inp">
                        </div>
                        <div>
                             <label class="lbl">Nama Pemilik Rek</label>
                             <input type="text" name="nama_pemilik_rekening" id="nama_pemilik_rekening" class="inp">
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-2">
                             <input type="text" name="tax_id" id="tax_id" class="inp text-xs" placeholder="Tax ID">
                             <input type="text" name="bpjs_id" id="bpjs_id" class="inp text-xs" placeholder="BPJS ID">
                             <input type="text" name="jamsostek_id" id="jamsostek_id" class="inp text-xs" placeholder="Jamsos">
                         </div>
                    </div>
                </form>

                <div class="px-8 py-5 border-t bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                    <button type="button" id="btn-cancel" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-100 font-medium transition">Batal</button>
                    <button type="button" id="btn-save" class="px-6 py-2.5 bg-cyan-700 text-white rounded-lg hover:bg-cyan-800 shadow-md font-bold flex items-center gap-2 transition">
                        <i class="ti ti-device-floppy"></i> Simpan Data
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    // Global State
    let currentPage = 1;
    let perPage = 10;
    let totalPages = 1;
    let viewType = 'active'; // 'active' atau 'pension'
    let searchTimeout = null;

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Initial Load Options
        loadOptions(); 

        // 2. CEK URL PARAMETER & AUTO SWITCH TAB
        const urlParams = new URLSearchParams(window.location.search);
        const requestedView = urlParams.get('view'); 

        if (requestedView === 'pension') {
            switchView('pension');
        } else {
            loadData(); 
        }

        // 3. Event Listeners
        document.getElementById('limit').addEventListener('change', (e) => {
            perPage = e.target.value;
            currentPage = 1;
            loadData();
        });

        // Filters
        document.getElementById('f_kebun').addEventListener('change', () => {
            currentPage = 1;
            loadData();
        });
        document.getElementById('f_afdeling').addEventListener('change', () => {
            currentPage = 1;
            loadData();
        });

        // Search with Debounce
        document.getElementById('q').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadData();
            }, 500); 
        });

        // Modal Handlers (Only if authorized)
        if (document.getElementById('btn-add')) {
            document.getElementById('btn-add').onclick = () => openCrudModal('store');
            document.getElementById('btn-close').onclick = () => document.getElementById('crud-modal').classList.add('hidden');
            document.getElementById('btn-cancel').onclick = () => document.getElementById('crud-modal').classList.add('hidden');
            document.getElementById('btn-save').onclick = saveData;
        }

        // Import Form
        document.getElementById('form-import').addEventListener('submit', handleImport);
    });

    // --- FUNGSI EXPORT (PDF & EXCEL) ---
    function doExport(type) {
        const q = document.getElementById('q').value;
        const kebun = document.getElementById('f_kebun').value;
        const afdeling = document.getElementById('f_afdeling').value;
        
        let url = '';
        if (type === 'pdf') {
            url = `./cetak/laporan_karyawan_pdf.php?view=${viewType}&q=${q}&kebun=${kebun}&afdeling=${afdeling}`;
        } else if (type === 'excel_data') {
            url = `./cetak/laporan_karyawan_excel.php?view=${viewType}&q=${q}&kebun=${kebun}&afdeling=${afdeling}`;
        }

        if (url) window.open(url, '_blank');
    }

    // --- FUNGSI UTAMA ---

    function switchView(type) {
        viewType = type;

        const btnActive = document.getElementById('tab-active');
        const btnPension = document.getElementById('tab-pension');

        const styleActive = 'px-4 py-2 text-sm font-bold rounded-md shadow-sm bg-white text-cyan-800 transition flex items-center gap-2 ring-1 ring-gray-200';
        const styleInactive = 'px-4 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700 transition flex items-center gap-2';
        
        const stylePensionActive = 'px-4 py-2 text-sm font-bold rounded-md shadow-sm bg-white text-red-700 transition flex items-center gap-2 ring-1 ring-gray-200';

        if (type === 'active') {
            btnActive.className = styleActive;
            btnPension.className = styleInactive;
        } else {
            btnActive.className = styleInactive;
            btnPension.className = stylePensionActive;
        }

        currentPage = 1;
        loadData();
    }

    async function loadOptions() {
        const fd = new FormData();
        fd.append('action', 'list_options');

        try {
            const res = await fetch('data_karyawan_crud.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                let htmlK = '<option value="">Semua Kebun</option>';
                json.kebun.forEach(k => htmlK += `<option value="${k}">${k}</option>`);
                document.getElementById('f_kebun').innerHTML = htmlK;

                let htmlDataList = '';
                json.kebun.forEach(k => htmlDataList += `<option value="${k}">`);
                if(document.getElementById('list-kebun')) document.getElementById('list-kebun').innerHTML = htmlDataList;

                let htmlA = '<option value="">Semua Afdeling</option>';
                json.afdeling.forEach(a => htmlA += `<option value="${a}">${a}</option>`);
                document.getElementById('f_afdeling').innerHTML = htmlA;
            }
        } catch (e) { console.error('Error loading options', e); }
    }

    async function loadData() {
        const tbody = document.getElementById('tbody-data');
        tbody.innerHTML = '<tr><td colspan="33" class="text-center py-12 text-gray-500"><i class="ti ti-loader animate-spin text-2xl mb-2"></i><br>Memuat data...</td></tr>';

        const fd = new FormData();
        fd.append('action', 'list');
        fd.append('view_type', viewType);
        fd.append('page', currentPage);
        fd.append('limit', perPage);
        fd.append('q', document.getElementById('q').value);
        fd.append('f_kebun', document.getElementById('f_kebun').value);
        fd.append('f_afdeling', document.getElementById('f_afdeling').value);

        try {
            const res = await fetch('data_karyawan_crud.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                const total = parseInt(json.total);
                const start = total === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
                const end = Math.min(currentPage * perPage, total);

                document.getElementById('info-start').innerText = start;
                document.getElementById('info-end').innerText = end;
                document.getElementById('info-total').innerText = total;

                if (json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="33" class="text-center py-10 text-gray-400 italic">Tidak ada data ditemukan.</td></tr>';
                } else {
                    // MAPPING SEMUA 30 KOLOM
                    tbody.innerHTML = json.data.map(r => {
                        const foto = r.foto_karyawan ? `../uploads/profil/${r.foto_karyawan}` : '../assets/img/default-avatar.png';
                        
                        let docIcon = '<span class="text-gray-300 text-xs">-</span>';
                        if (r.dokumen_path) {
                            docIcon = `<a href="../uploads/dokumen/${r.dokumen_path}" target="_blank" class="text-cyan-600 hover:text-cyan-800 bg-cyan-50 px-2 py-1 rounded text-xs border border-cyan-200 flex items-center justify-center gap-1 w-fit mx-auto"><i class="ti ti-file-text"></i></a>`;
                        }

                        const rowJson = encodeURIComponent(JSON.stringify(r));
                        let statusClass = 'bg-gray-100 text-gray-600';
                        if (r.status_karyawan === 'KARPIM') statusClass = 'bg-green-100 text-green-700 border border-green-200';
                        if (r.status_karyawan === 'TS') statusClass = 'bg-yellow-100 text-yellow-700 border border-yellow-200';

                        return `
                        <tr class="hover:bg-cyan-50 border-b transition duration-150 group">
                            <td class="text-center p-2 sticky-col col-foto bg-white group-hover:bg-cyan-50">
                                <img src="${foto}" class="w-9 h-9 rounded-full object-cover mx-auto border shadow-sm">
                            </td>
                            <td class="p-3 sticky-col col-sap bg-white group-hover:bg-cyan-50 font-mono text-xs font-bold text-cyan-700">${r.sap_id}</td>
                            <td class="p-3 sticky-col col-old bg-white group-hover:bg-cyan-50 text-xs text-gray-500">${r.old_pers_no || '-'}</td>
                            <td class="p-3 sticky-col col-nama bg-white group-hover:bg-cyan-50 font-bold text-gray-800 text-sm truncate">${r.nama_karyawan}</td>
                            
                            <td class="p-3 font-mono text-xs">${r.nik_ktp || '-'}</td>
                            <td class="p-3 text-center">${r.gender || '-'}</td>
                            <td class="p-3">${r.tempat_lahir || '-'}</td>
                            <td class="p-3 text-center text-xs">${r.tanggal_lahir || '-'}</td>
                            <td class="p-3 text-sm">${r.agama || '-'}</td>
                            <td class="p-3 text-sm font-mono">${r.no_hp || '-'}</td>
                            <td class="p-3 text-center">${r.s_kel || '-'}</td>

                            <td class="p-3 text-sm font-semibold text-gray-700">${r.nama_kebun || '-'}</td>
                            <td class="p-3 text-sm">${r.afdeling || '-'}</td>
                            <td class="p-3 text-sm font-medium">${r.jabatan_real || '-'}</td>
                            <td class="p-3 text-sm text-gray-500">${r.jabatan_sap || '-'}</td>
                            <td class="p-3 text-center"><span class="${statusClass} text-xs px-2 py-1 rounded font-bold">${r.status_karyawan}</span></td>
                            <td class="p-3 text-center text-xs font-mono">${r.person_grade || '-'}</td>
                            <td class="p-3 text-center text-xs">${r.phdp_golongan || '-'}</td>

                            <td class="p-3 text-center text-xs">${r.tmt_kerja || '-'}</td>
                            <td class="p-3 text-center text-xs text-orange-600 font-bold">${r.tmt_mbt || '-'}</td>
                            <td class="p-3 text-center text-xs text-red-600 font-bold">${r.tmt_pensiun || '-'}</td>

                            <td class="p-3 text-center font-mono text-xs">${r.status_pajak || '-'}</td>
                            <td class="p-3 text-xs font-mono text-gray-500">${r.tax_id || '-'}</td>
                            <td class="p-3 text-xs font-mono text-gray-500">${r.bpjs_id || '-'}</td>
                            <td class="p-3 text-xs font-mono text-gray-500">${r.jamsostek_id || '-'}</td>

                            <td class="p-3 text-sm">${r.nama_bank || '-'}</td>
                            <td class="p-3 text-sm font-mono text-xs">${r.no_rekening || '-'}</td>
                            <td class="p-3 text-sm text-xs text-gray-500">${r.nama_pemilik_rekening || '-'}</td>

                            <td class="p-3 text-sm font-bold text-center">${r.pendidikan_terakhir || '-'}</td>
                            <td class="p-3 text-xs">${r.jurusan || '-'}</td>
                            <td class="p-3 text-xs">${r.institusi || '-'}</td>
                            <td class="p-3 text-center">${docIcon}</td>
                            
                            <td class="p-3 text-center sticky-action bg-white group-hover:bg-cyan-50">
                                <div class="flex justify-center gap-1">
                                    <a href="export_cv.php?id=${r.id}" target="_blank" class="w-7 h-7 flex items-center justify-center text-green-600 hover:bg-green-100 rounded transition" title="Print CV"><i class="ti ti-printer"></i></a>
                                    <?php if ($canAction): ?>
                                    <button onclick="editData('${rowJson}')" class="w-7 h-7 flex items-center justify-center text-blue-600 hover:bg-blue-100 rounded transition" title="Edit"><i class="ti ti-pencil"></i></button>
                                    <button onclick="deleteData(${r.id})" class="w-7 h-7 flex items-center justify-center text-red-600 hover:bg-red-100 rounded transition" title="Hapus"><i class="ti ti-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>`;
                    }).join('');
                }
                renderPagination(Math.ceil(total / perPage));
            } else {
                alert('Gagal memuat data: ' + json.message);
            }
        } catch (e) { console.error(e); }
    }

    function renderPagination(total) {
        let html = '';
        html += `<button onclick="changePage(${currentPage-1})" ${currentPage===1?'disabled':''} class="w-8 h-8 flex items-center justify-center border rounded hover:bg-gray-100 disabled:opacity-50"><i class="ti ti-chevron-left"></i></button>`;
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= currentPage - 1 && i <= currentPage + 1)) {
                let active = i === currentPage ? 'bg-cyan-700 text-white border-cyan-700' : 'bg-white text-gray-600 hover:bg-gray-100';
                html += `<button onclick="changePage(${i})" class="w-8 h-8 flex items-center justify-center border rounded ${active} text-sm font-bold transition">${i}</button>`;
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                html += `<span class="w-8 h-8 flex items-center justify-center text-gray-400">...</span>`;
            }
        }
        html += `<button onclick="changePage(${currentPage+1})" ${currentPage===total || total===0?'disabled':''} class="w-8 h-8 flex items-center justify-center border rounded hover:bg-gray-100 disabled:opacity-50"><i class="ti ti-chevron-right"></i></button>`;
        document.getElementById('pagination-controls').innerHTML = html;
    }

    function changePage(p) {
        if (p < 1 || (document.getElementById('info-total').innerText > 0 && p > Math.ceil(document.getElementById('info-total').innerText / perPage))) return;
        currentPage = p;
        loadData();
    }

    function openImportModal() {
        document.getElementById('import-modal').classList.remove('hidden');
        document.getElementById('import-modal').classList.add('flex');
    }
    function closeImportModal() {
        document.getElementById('import-modal').classList.add('hidden');
        document.getElementById('import-modal').classList.remove('flex');
    }
    function openCrudModal(mode) {
        document.getElementById('crud-form').reset();
        document.getElementById('form-action').value = mode;
        document.getElementById('preview-foto').src = '../assets/img/default-avatar.png';
        document.getElementById('link-dokumen').innerHTML = 'Max 2MB (PDF/Doc)';
        document.getElementById('crud-modal').classList.remove('hidden');
    }

    window.editData = (jsonStr) => {
        const r = JSON.parse(decodeURIComponent(jsonStr));
        openCrudModal('update');
        document.getElementById('form-id').value = r.id;

        // POPULASI FORMULIR LENGKAP
        if(document.getElementById('sap_id')) document.getElementById('sap_id').value = r.sap_id || '';
        if(document.getElementById('old_pers_no')) document.getElementById('old_pers_no').value = r.old_pers_no || '';
        if(document.getElementById('nama_karyawan')) document.getElementById('nama_karyawan').value = r.nama_karyawan || '';
        if(document.getElementById('nik_ktp')) document.getElementById('nik_ktp').value = r.nik_ktp || '';
        if(document.getElementById('gender')) document.getElementById('gender').value = r.gender || '';
        if(document.getElementById('agama')) document.getElementById('agama').value = r.agama || '';
        if(document.getElementById('tempat_lahir')) document.getElementById('tempat_lahir').value = r.tempat_lahir || '';
        if(document.getElementById('tgl_lahir')) document.getElementById('tgl_lahir').value = r.tanggal_lahir || '';
        if(document.getElementById('status_pajak')) document.getElementById('status_pajak').value = r.status_pajak || ''; // Fix: use status_pajak from DB
        
        if(document.getElementById('kebun_id')) document.getElementById('kebun_id').value = r.nama_kebun || ''; 
        if(document.getElementById('afdeling')) document.getElementById('afdeling').value = r.afdeling || '';
        if(document.getElementById('jabatan_real')) document.getElementById('jabatan_real').value = r.jabatan_real || '';
        if(document.getElementById('jabatan_sap')) document.getElementById('jabatan_sap').value = r.jabatan_sap || '';
        if(document.getElementById('status_karyawan')) document.getElementById('status_karyawan').value = r.status_karyawan || '';
        if(document.getElementById('person_grade')) document.getElementById('person_grade').value = r.person_grade || '';
        if(document.getElementById('status_keluarga')) document.getElementById('status_keluarga').value = r.s_kel || '';
        if(document.getElementById('phdp_golongan')) document.getElementById('phdp_golongan').value = r.phdp_golongan || '';

        if(document.getElementById('pendidikan_terakhir')) document.getElementById('pendidikan_terakhir').value = r.pendidikan_terakhir || '';
        if(document.getElementById('jurusan')) document.getElementById('jurusan').value = r.jurusan || '';
        if(document.getElementById('institusi')) document.getElementById('institusi').value = r.institusi || '';

        if(document.getElementById('tmt_kerja')) document.getElementById('tmt_kerja').value = r.tmt_kerja || '';
        if(document.getElementById('tmt_mbt')) document.getElementById('tmt_mbt').value = r.tmt_mbt || '';
        if(document.getElementById('tmt_pensiun')) document.getElementById('tmt_pensiun').value = r.tmt_pensiun || '';
        if(document.getElementById('no_rekening')) document.getElementById('no_rekening').value = r.no_rekening || '';
        if(document.getElementById('nama_bank')) document.getElementById('nama_bank').value = r.nama_bank || '';
        if(document.getElementById('nama_pemilik_rekening')) document.getElementById('nama_pemilik_rekening').value = r.nama_pemilik_rekening || '';
        if(document.getElementById('no_hp')) document.getElementById('no_hp').value = r.no_hp || '';
        if(document.getElementById('tax_id')) document.getElementById('tax_id').value = r.tax_id || '';
        if(document.getElementById('bpjs_id')) document.getElementById('bpjs_id').value = r.bpjs_id || '';
        if(document.getElementById('jamsostek_id')) document.getElementById('jamsostek_id').value = r.jamsostek_id || '';

        if (r.foto_karyawan) document.getElementById('preview-foto').src = '../uploads/profil/' + r.foto_karyawan;
        if (r.dokumen_path) document.getElementById('link-dokumen').innerHTML = `<span class="text-green-600 font-bold"><i class="ti ti-check"></i> File saat ini: ${r.dokumen_path}</span>`;
    };

    async function saveData() {
        const btn = document.getElementById('btn-save');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Menyimpan...';
        btn.disabled = true;

        const fd = new FormData(document.getElementById('crud-form'));
        try {
            const res = await fetch('data_karyawan_crud.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                document.getElementById('crud-modal').classList.add('hidden');
                Swal.fire('Berhasil!', 'Data telah disimpan.', 'success');
                loadData();
                loadOptions(); 
            } else {
                Swal.fire('Gagal!', json.message, 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Terjadi kesalahan sistem', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    function handleImport(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Mengunggah...';
        btn.disabled = true;

        const fd = new FormData(e.target);
        fetch('data_karyawan_crud.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(j => {
                if (j.success) {
                    closeImportModal();
                    Swal.fire('Sukses', j.message, 'success');
                    loadData();
                    loadOptions(); 
                } else {
                    Swal.fire('Gagal Import', j.message, 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Gagal koneksi server', 'error'))
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }

    window.deleteData = (id) => {
        Swal.fire({
            title: 'Hapus Data?', text: "Data yang dihapus tidak bisa dikembalikan!", icon: 'warning',
            showCancelButton: true, confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', confirmButtonColor: '#ef4444'
        }).then(res => {
            if (res.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', id);
                fetch('data_karyawan_crud.php', { method: 'POST', body: fd }).then(r => r.json()).then(j => {
                    if (j.success) {
                        Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success');
                        loadData();
                        loadOptions();
                    } else {
                        Swal.fire('Gagal', 'Tidak bisa menghapus data', 'error');
                    }
                });
            }
        });
    };

    window.deleteAllData = () => {
        Swal.fire({
            title: 'RESET DATA DATABASE?', 
            text: 'Masukkan Kode Konfirmasi untuk menghapus SEMUA data karyawan:',
            input: 'text',
            inputAttributes: { autocapitalize: 'off', placeholder: 'Masukkan Kode...' },
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#d33', 
            confirmButtonText: 'Hapus Permanen',
            showLoaderOnConfirm: true,
            preConfirm: (code) => {
                if (!code) {
                    Swal.showValidationMessage('Kode harus diisi!')
                }
                return code;
            }
        }).then(res => {
            if(res.isConfirmed) {
                const fd = new FormData(); 
                fd.append('action', 'delete_all');
                fd.append('code', res.value); 

                fetch('data_karyawan_crud.php', {method:'POST', body:fd})
                .then(r=>r.json())
                .then(j=>{
                    if(j.success) { 
                        Swal.fire('Reset Berhasil', j.message, 'success'); 
                        loadData(); 
                    } else {
                        Swal.fire('Gagal', j.message, 'error'); 
                    }
                });
            }
        });
    };

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => document.getElementById('preview-foto').src = e.target.result;
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>