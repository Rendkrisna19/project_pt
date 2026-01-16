<?php
session_start();
require_once '../config/database.php'; 

header('Content-Type: application/json');

// Cek Sesi
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$action = $_POST['action'] ?? '';

// --- FUNGSI UPDATE STATUS OTOMATIS ---
function updateStatusOtomatis($pdo) {
    // 1. Pensiun (56 Thn) - Kecuali yg sudah diset PHK
    $pdo->query("UPDATE hr_karyawan SET status_karyawan = 'Pensiun' 
                 WHERE TIMESTAMPDIFF(YEAR, tgl_lahir, CURDATE()) >= 56 
                 AND status_karyawan != 'PHK'");

    // 2. MBT (55 Thn) - Kecuali PHK/Pensiun
    $pdo->query("UPDATE hr_karyawan SET status_karyawan = 'MBT' 
                 WHERE TIMESTAMPDIFF(YEAR, tgl_lahir, CURDATE()) = 55 
                 AND status_karyawan NOT IN ('PHK', 'Pensiun')");
}

try {
    // --- READ LIST ---
    if ($action === 'list') {
        updateStatusOtomatis($pdo); 
        $filterStatus = $_POST['status_filter'] ?? 'all';
        $sql = "SELECT *, TIMESTAMPDIFF(YEAR, tgl_lahir, CURDATE()) as usia FROM hr_karyawan WHERE 1=1";
        $params = [];

        if ($filterStatus !== 'all') {
            $sql .= " AND status_karyawan = :status";
            $params[':status'] = $filterStatus;
        }

        $sql .= " ORDER BY nama_karyawan ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- CREATE (TAMBAH DATA) ---
    if ($action === 'store') {
        $sql = "INSERT INTO hr_karyawan (sap_karyawan, nama_karyawan, nik, tgl_lahir, tmt_kerja, bagian, status_karyawan) 
                VALUES (:sap, :nama, :nik, :tgl, :tmt, :bagian, :status)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sap' => $_POST['sap'],
            ':nama' => $_POST['nama'],
            ':nik' => $_POST['nik'],
            ':tgl' => $_POST['tgl_lahir'],
            ':tmt' => $_POST['tmt_kerja'],
            ':bagian' => $_POST['bagian'],
            ':status' => $_POST['status'] // User bisa pilih manual, nnti divalidasi ulang otomatis
        ]);
        
        updateStatusOtomatis($pdo); // Cek ulang umur
        echo json_encode(['success' => true, 'message' => 'Data berhasil ditambahkan']);
        exit;
    }

    // --- GET SINGLE DATA (UNTUK EDIT) ---
    if ($action === 'get_single') {
        $stmt = $pdo->prepare("SELECT * FROM hr_karyawan WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- UPDATE DATA ---
    if ($action === 'update') {
        $sql = "UPDATE hr_karyawan SET 
                sap_karyawan=:sap, nama_karyawan=:nama, nik=:nik, 
                tgl_lahir=:tgl, tmt_kerja=:tmt, bagian=:bagian, status_karyawan=:status 
                WHERE id=:id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sap' => $_POST['sap'],
            ':nama' => $_POST['nama'],
            ':nik' => $_POST['nik'],
            ':tgl' => $_POST['tgl_lahir'],
            ':tmt' => $_POST['tmt_kerja'],
            ':bagian' => $_POST['bagian'],
            ':status' => $_POST['status'],
            ':id' => $_POST['id']
        ]);

        updateStatusOtomatis($pdo);
        echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui']);
        exit;
    }

    // --- DELETE DATA ---
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM hr_karyawan WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
        exit;
    }

    // --- STATISTIK ---
    if ($action === 'stats') {
        updateStatusOtomatis($pdo);
        $stats = ['total' => 0, 'aktif' => 0, 'mbt' => 0, 'pensiun' => 0, 'phk' => 0, 'notif_list' => []];

        $stmt = $pdo->query("SELECT status_karyawan, COUNT(*) as jumlah FROM hr_karyawan GROUP BY status_karyawan");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = strtolower($row['status_karyawan']);
            if(isset($stats[$key])) $stats[$key] = $row['jumlah'];
            $stats['total'] += $row['jumlah'];
        }

        $sqlNotif = "SELECT nama_karyawan, status_karyawan FROM hr_karyawan 
                     WHERE (status_karyawan = 'MBT' OR status_karyawan = 'Pensiun') 
                     AND MONTH(tgl_lahir) = MONTH(CURDATE()) LIMIT 5";
        $stmtNotif = $pdo->query($sqlNotif);
        $stats['notif_list'] = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $stats]);
        exit;
    }

    // --- DOWNLOAD TEMPLATE EXCEL (BERWARNA) ---
    if ($action === 'download_template') {
        // Kita gunakan HTML Table dengan Header Content-Type Excel
        // Ini trik PHP Native agar Excel membaca CSS (Warna & Border)
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=Template_Karyawan_Cantik.xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo '
        <html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <style>
                table { border-collapse: collapse; width: 100%; }
                th { background-color: #06b6d4; color: #ffffff; border: 1px solid #000000; font-weight: bold; padding: 10px; text-align: center; }
                td { border: 1px solid #000000; padding: 5px; }
                .note { color: red; font-style: italic; }
            </style>
        </head>
        <body>
            <h3>Template Data Karyawan</h3>
            <table>
                <thead>
                    <tr>
                        <th style="background-color: #06b6d4; color: white;">SAP ID</th>
                        <th style="background-color: #06b6d4; color: white;">Nama Lengkap</th>
                        <th style="background-color: #06b6d4; color: white;">NIK (KTP)</th>
                        <th style="background-color: #06b6d4; color: white;">Tanggal Lahir (YYYY-MM-DD)</th>
                        <th style="background-color: #06b6d4; color: white;">TMT Kerja (YYYY-MM-DD)</th>
                        <th style="background-color: #06b6d4; color: white;">Bagian</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>10001</td>
                        <td>Budi Santoso</td>
                        <td>\'1234567890123456</td>
                        <td>1990-01-31</td>
                        <td>2015-05-20</td>
                        <td>Panen</td>
                    </tr>
                    <tr>
                        <td>10002</td>
                        <td>Siti Aminah</td>
                        <td>\'6543210987654321</td>
                        <td>1965-05-15</td>
                        <td>1990-01-10</td>
                        <td>Kantor</td>
                    </tr>
                </tbody>
            </table>
            <p class="note">* Harap isi format tanggal dengan benar (Tahun-Bulan-Tanggal).</p>
        </body>
        </html>';
        exit;
    }

    // --- IMPORT DATA ---
    if ($action === 'import') {
        if (!isset($_FILES['file_excel']['tmp_name'])) throw new Exception("File tidak ditemukan.");
        
        // Logic Import sederhana (Membaca file sebagai HTML/CSV text stream)
        // Karena file .xls HTML diatas formatnya text, kita perlu parser khusus atau 
        // Sarankan user "Save As CSV" jika import error. 
        // TAPI, agar konsisten dengan request sebelumnya (CSV), kita pakai parser CSV standar.
        
        $file = $_FILES['file_excel']['tmp_name'];
        $handle = fopen($file, "r");
        
        // Skip header
        fgetcsv($handle); // Coba skip baris 1
       
       //mulai untuk impoert data   
        $pdo->beginTransaction();
        $sukses = 0;
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if(count($row) < 4) continue; 
            // Cek apakah ini baris HTML (jika user nekat upload file XLS langsung)
            if(strpos($row[0], '<') !== false) continue; 

            $sql = "INSERT INTO hr_karyawan (sap_karyawan, nama_karyawan, nik, tgl_lahir, tmt_kerja, bagian, status_karyawan) VALUES (?, ?, ?, ?, ?, ?, 'Aktif')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$row[0], $row[1], $row[2], $row[3], $row[4], $row[5]]);
            $sukses++;
        }
        $pdo->commit();
        fclose($handle);
        updateStatusOtomatis($pdo);
        echo json_encode(['success' => true, 'message' => "Import $sukses data selesai."]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>