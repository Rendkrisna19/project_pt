<?php
// pages/mcs.php — Master Control Sheet (Versi Stabil - Clean Core)
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Ambil daftar file
$files = $conn->query("SELECT id, nama_file, updated_at FROM mcs_sheets ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include_once '../layouts/header.php';
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/css/luckysheet.css' />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/assets/iconfont/iconfont.css' />

<script src="https://cdn.jsdelivr.net/npm/luckysheet@2.1.13/dist/luckysheet.umd.js"></script>

<script src="https://cdn.jsdelivr.net/npm/luckyexcel/dist/luckyexcel.umd.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<style>
  /* Style Layout */
  .freeze-parent{max-height:70vh; overflow:auto; border-radius: 0.75rem; border:1px solid #e5e7eb;}
  .table-wrap{overflow:auto}
  table.rekap{width:100%; border-collapse:separate; border-spacing:0}
  table.rekap th, table.rekap td{ padding:.60rem .70rem; border-bottom:1px solid #e5e7eb; font-size:.88rem; white-space:nowrap; }
  table.rekap thead th{ position:sticky; top:0; z-index:5; background:#059fd3ff; color:#fff; font-weight: 600; }
  
  .btn{display:inline-flex;align-items:center;gap:.45rem;border:1px solid #059fd3ff;background:#059fd3ff;color:#fff;border-radius:.6rem;padding:.45rem .9rem; cursor: pointer; font-size: 0.875rem;}
  .btn-gray{border:1px solid #cbd5e1;background:#fff;color:#111827}
  .act{display:inline-grid;place-items:center;width:32px;height:32px;border-radius:.5rem;border:1px solid #e5e7eb;background:#fff; cursor: pointer;}

  /* --- CSS EDITOR --- */
  #view-editor {
    position: relative;
    height: 85vh; 
    background: #fff;
    display: none; /* Default Hidden */
    flex-direction: column;
  }

  #editor-header {
    flex: 0 0 60px; 
    z-index: 1000;
  }

  #luckysheet-container {
    margin: 0; padding: 0;
    position: absolute;
    top: 60px; 
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    border-top: 1px solid #ccc;
  }
  
  /* Paksa Background Putih */
  .luckysheet-work-area { background-color: #fff !important; }
</style>

<div class="space-y-6">
  <div class="flex justify-between items-center">
    <h1 class="text-3xl font-bold">Master Control Sheet</h1>
  </div>

  <div id="view-list">
    <div class="bg-white p-4 rounded-xl shadow mb-6">
       <div class="flex items-center gap-4">
          <input type="file" id="file-input" accept=".xlsx, .xls" class="hidden">
          <button onclick="document.getElementById('file-input').click()" class="btn">
            <i class="ti ti-file-upload"></i> Upload Excel
          </button>
          <span class="text-sm text-gray-500">Format .xlsx (Desain 100% Sesuai)</span>
       </div>
    </div>

    <div class="bg-white shadow freeze-parent">
      <div class="table-wrap">
        <table class="rekap">
          <thead>
            <tr>
              <th style="width:50px; text-align:center;">No</th>
              <th>Nama File</th>
              <th>Terakhir Update</th>
              <th style="text-align:center; width:120px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if(count($files) > 0): ?>
              <?php $no=1; foreach($files as $f): ?>
              <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                   <a href="javascript:void(0)" onclick="openEditor(<?= $f['id'] ?>)" class="text-blue-600 font-bold hover:underline flex items-center gap-2">
                      <i class="ti ti-layout-grid"></i> <?= htmlspecialchars($f['nama_file']) ?>
                   </a>
                </td>
                <td><?= date('d M Y H:i', strtotime($f['updated_at'])) ?></td>
                <td class="text-center">
                   <div class="flex justify-center gap-2">
                     <button class="act" onclick="openEditor(<?= $f['id'] ?>)" title="Edit"><i class="ti ti-pencil text-blue-600"></i></button>
                     <button class="act" onclick="deleteFile(<?= $f['id'] ?>)" title="Hapus"><i class="ti ti-trash text-red-600"></i></button>
                   </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="4" class="text-center py-6 text-gray-500">Belum ada file.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="view-editor">
    <div id="editor-header" class="flex items-center justify-between bg-white p-3 px-4 border-b border-gray-200">
      <div class="flex items-center gap-4">
        <button onclick="closeEditor()" class="btn btn-gray text-sm"><i class="ti ti-arrow-left"></i> Kembali</button>
        <div class="flex flex-col">
            <span id="editor-filename" class="font-bold text-gray-800 text-lg">...</span>
            <span id="save-status" class="text-xs font-semibold text-gray-400">Menyiapkan...</span>
        </div>
      </div>
      <button onclick="saveToServerManual()" class="btn text-sm"><i class="ti ti-device-floppy"></i> Simpan</button>
    </div>

    <div id="luckysheet-container"></div>
  </div>

</div>

<?php include_once '../layouts/footer.php'; ?>

<script>
let currentId = null;

/* 1. UPLOAD */
document.getElementById('file-input').addEventListener('change', function(e){
    const file = e.target.files[0];
    if(!file) return;

    Swal.fire({title: 'Memproses...', text: 'Mohon tunggu sebentar', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});

    LuckyExcel.transformExcelToLucky(file, function(exportJson){
        if(!exportJson.sheets || exportJson.sheets.length === 0){
            Swal.fire('Error', 'File Excel tidak terbaca/kosong.', 'error'); return;
        }
        uploadToServer(file.name, JSON.stringify(exportJson.sheets));
    }, function(err){
        Swal.fire('Error', 'Gagal memproses file. Pastikan format .xlsx valid.', 'error');
    });
});

async function uploadToServer(name, jsonData){
    const fd = new FormData(); 
    fd.append('action','upload'); 
    fd.append('nama_file',name); 
    fd.append('data_json',jsonData);

    try {
        const r = await fetch('mcs_action.php', {method:'POST', body:fd});
        const j = await r.json();
        if(j.success) {
            Swal.fire('Berhasil', 'File berhasil diupload!', 'success').then(() => location.reload());
        } else {
            throw new Error(j.message);
        }
    } catch(e){
        Swal.fire('Gagal Upload', e.message, 'error');
    }
}

/* 2. BUKA EDITOR */
async function openEditor(id){
    currentId = id;
    
    // UI Switch
    document.getElementById('view-list').style.display = 'none';
    document.getElementById('view-editor').style.display = 'flex';
    
    // Loading
    document.getElementById('luckysheet-container').innerHTML = '';
    document.getElementById('save-status').innerText = 'Menyiapkan...';
    
    try {
        const fd = new FormData(); 
        fd.append('action','load'); 
        fd.append('id',id);
        
        const r = await fetch('mcs_action.php', {method:'POST', body:fd});
        const j = await r.json();
        
        if(!j.success) throw new Error(j.message);
        
        document.getElementById('editor-filename').innerText = j.data.nama_file;

        // Parsing Data
        let dataSheet = [];
        try { 
            if(!j.data.sheet_data || j.data.sheet_data.trim() === "") throw new Error("Data kosong");
            dataSheet = JSON.parse(j.data.sheet_data); 
        } catch(parseError){
            // Jika format salah, suruh hapus
            Swal.fire({
                title: 'Data Korup',
                text: 'File ini menggunakan format lama. Silakan hapus dan upload ulang.',
                icon: 'error'
            }).then(()=>closeEditor());
            return;
        }

        // Render (Jeda 100ms agar DOM siap)
        setTimeout(() => {
            luckysheet.create({
                container: 'luckysheet-container',
                data: dataSheet,
                title: j.data.nama_file,
                lang: 'id',
                showinfobar: false,
                showstatisticBar: false,
                enableAddRow: true,
                showsheetbar: true,
                
                // PENTING: Jangan tambahkan opsi 'plugins' apapun disini
                // Biarkan Luckysheet menggunakan default core-nya saja
                
                hook: {
                    updated: function() {
                       const st = document.getElementById('save-status');
                       if(st) {
                           st.innerText = 'Belum disimpan';
                           st.className = 'text-xs font-bold text-orange-500';
                       }
                    }
                }
            });
        }, 100);
        
    } catch(e){
        Swal.fire('Error', e.message, 'error');
        closeEditor();
    }
}

/* 3. SIMPAN */
async function saveToServerManual(){
    const st = document.getElementById('save-status');
    st.innerText = 'Menyimpan...';
    st.className = 'text-xs font-bold text-blue-500';
    
    const json = JSON.stringify(luckysheet.getAllSheets());
    const fd = new FormData(); 
    fd.append('action','autosave'); 
    fd.append('id',currentId); 
    fd.append('data_json',json);
    
    try {
        const r = await fetch('mcs_action.php', {method:'POST', body:fd});
        const j = await r.json();
        if(j.success) {
            st.innerText = 'Tersimpan';
            st.className = 'text-xs font-bold text-green-600';
            Swal.fire({icon:'success', title:'Berhasil Disimpan', toast:true, position:'top-end', showConfirmButton:false, timer:1500});
        } else {
            throw new Error(j.message);
        }
    } catch(e){
        st.innerText = 'Gagal Simpan';
        st.className = 'text-xs font-bold text-red-600';
        Swal.fire('Error', 'Gagal menyimpan: '+e.message, 'error');
    }
}

function closeEditor(){ location.reload(); }

async function deleteFile(id){
    if(!confirm('Hapus file ini?')) return;
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    await fetch('mcs_action.php', {method:'POST', body:fd});
    location.reload();
}
</script>