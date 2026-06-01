<?php
// be/pemetaan_api.php
session_start();

// 1. TAHAN SEMUA OUTPUT! Jangan ada spasi atau HTML nyasar yang merusak JSON
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    // 2. CEK LOGIN
    if (!isset($_SESSION['loggedin'])) {
        throw new Exception('Sesi habis, silakan login kembali.');
    }

    // 3. KONEKSI DATABASE (Dimasukkan ke dalam TRY agar jika gagal, terbaca di catch)
    require_once '../../config/database.php'; 
    $db = new Database();
    $conn = $db->getConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_REQUEST['action'] ?? '';

    // --- 1. AMBIL DATA UNIT & KEBUN ---
    if ($action === 'get_units') {
        $f_kebun_id = isset($_GET['kebun_id']) ? (int)$_GET['kebun_id'] : 0;
        $kebuns = $conn->query("SELECT id, nama_kebun FROM md_kebun ORDER BY nama_kebun ASC")->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT u.id, u.nama_unit, u.kebun_id, k.nama_kebun 
                FROM units u 
                LEFT JOIN md_kebun k ON u.kebun_id = k.id ";
        if ($f_kebun_id > 0) $sql .= " WHERE u.kebun_id = $f_kebun_id ";
        $sql .= " ORDER BY k.nama_kebun ASC, u.nama_unit ASC";

        $units = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        ob_clean(); // Bersihkan buffer sebelum echo JSON
        echo json_encode(['success' => true, 'kebuns' => $kebuns, 'units' => $units]);
        exit;
    }

    // --- 2. GET BLOK BERDASARKAN UNIT ---
    if ($action === 'get_bloks') {
        $unit_id = (int)$_GET['unit_id'];
        $sql = "SELECT id, kode FROM md_blok WHERE unit_id = ? ORDER BY kode ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$unit_id]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // --- 3. SIMPAN DATA GEOJSON (DRAWING MULTI POLYGON) ---
    if ($action === 'save_map_data') {
        
        // Validasi ketat
        if (empty($_POST['kebun_id'])) throw new Exception("ID Kebun tidak ditemukan.");
        if (empty($_POST['unit_id'])) throw new Exception("ID Unit tidak ditemukan.");
        if (empty($_POST['blok_id'])) throw new Exception("Anda belum memilih Blok!");
        if (empty($_POST['jenis_aset'])) throw new Exception("Anda belum memilih Jenis Aset!");
        if (empty($_POST['geojson'])) throw new Exception("Data Peta kosong! Silakan gambar ulang di peta.");

        $kebun_id   = (int)$_POST['kebun_id'];
        $unit_id    = (int)$_POST['unit_id'];
        $blok_id    = (int)$_POST['blok_id'];
        $jenis_aset = trim($_POST['jenis_aset']);
        $geojson    = trim($_POST['geojson']); 
        
        $lat        = $_POST['latitude'];
        $lng        = $_POST['longitude'];
        $warna      = $_POST['warna'] ?? '#0891b2'; 
        
        // Ambil data keterangan & realisasi dari form
        $keterangan = trim($_POST['keterangan'] ?? '');
        $tgl = $_POST['tanggal_realisasi'] ?: null;
        $f_hi = (float)($_POST['fisik_hari_ini'] ?? 0);
        $f_sd = (float)($_POST['fisik_sd'] ?? 0);
        $hk_hi = (float)($_POST['hk_hari_ini'] ?? 0);
        $hk_sd = (float)($_POST['hk_sd'] ?? 0);
        $k_hi = (float)($_POST['bahan_kimia_hari_ini'] ?? 0);
        $k_sd = (float)($_POST['bahan_kimia_sd'] ?? 0);
        $c_hi = (float)($_POST['campuran_hari_ini'] ?? 0);
        $c_sd = (float)($_POST['campuran_sd'] ?? 0);

        // Cek validitas GeoJSON
        json_decode($geojson);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Format gambar peta (GeoJSON) rusak. Silakan gambar ulang.");
        }

        // Upload Foto
        $foto_name = null;
        if (!empty($_FILES['foto']['name'])) {
            $dir = "../../uploads/pemetaan/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto_name = "MAP_" . time() . ".$ext";
            
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $foto_name)) {
                throw new Exception("Gagal mengupload foto ke folder uploads/pemetaan.");
            }
        }

        // Query Insert (SEKARANG SUDAH ADA KOLOM KETERANGAN & REALISASI)
        $sql = "INSERT INTO tr_pemetaan (
                    kebun_id, unit_id, blok_id, jenis_aset, geojson, latitude, longitude, warna, foto, keterangan,
                    tanggal_realisasi, fisik_hari_ini, fisik_sd, hk_hari_ini, hk_sd, bahan_kimia_hari_ini, bahan_kimia_sd, campuran_hari_ini, campuran_sd
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $kebun_id, $unit_id, $blok_id, $jenis_aset, $geojson, $lat, $lng, $warna, $foto_name, $keterangan,
            $tgl, $f_hi, $f_sd, $hk_hi, $hk_sd, $k_hi, $k_sd, $c_hi, $c_sd
        ]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Area GIS berhasil disimpan ke database!']);
        exit;
    }

    // --- 4. AMBIL DATA MAP & STATS ---
    if ($action === 'get_map_data') {
        if (empty($_GET['kebun_id']) || empty($_GET['unit_id'])) {
            throw new Exception("Parameter Kebun atau Unit tidak lengkap.");
        }
        $kebun_id = (int)$_GET['kebun_id'];
        $unit_id  = (int)$_GET['unit_id'];

        $sql = "SELECT p.*, b.kode as nama_blok 
                FROM tr_pemetaan p
                LEFT JOIN md_blok b ON p.blok_id = b.id
                WHERE p.kebun_id = ? AND p.unit_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$kebun_id, $unit_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlStats = "SELECT jenis_aset as label, COUNT(*) as value FROM tr_pemetaan WHERE unit_id = ? GROUP BY jenis_aset";
        $st = $conn->prepare($sqlStats);
        $st->execute([$unit_id]);
        $stats = $st->fetchAll(PDO::FETCH_ASSOC);

        // Ambil info Peta Dasar
        $sqlUnit = "SELECT peta_kerja_foto FROM units WHERE id = ?";
        $stmtUnit = $conn->prepare($sqlUnit);
        $stmtUnit->execute([$unit_id]);
        $unitData = $stmtUnit->fetch(PDO::FETCH_ASSOC);
        $peta_kerja_foto = $unitData ? $unitData['peta_kerja_foto'] : null;

        ob_clean();
        echo json_encode(['success' => true, 'data' => $data, 'stats' => $stats, 'peta_kerja_foto' => $peta_kerja_foto]);
        exit;
    }

    // --- 5. UPLOAD PETA DASAR (IMAGE) ---
    if ($action === 'upload_peta_dasar') {
        if (empty($_POST['unit_id'])) throw new Exception("ID Unit tidak ditemukan.");
        $unit_id = (int)$_POST['unit_id'];

        if (empty($_FILES['peta_dasar']['name'])) {
            throw new Exception("File gambar Peta Dasar belum dipilih.");
        }

        $dir = "../../uploads/pemetaan/base_map/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['peta_dasar']['name'], PATHINFO_EXTENSION));
        $valid_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $valid_ext)) {
            throw new Exception("Format gambar harus JPG, PNG, atau WEBP.");
        }

        $foto_name = "BASEMAP_UNIT_" . $unit_id . "_" . time() . ".$ext";

        if (!move_uploaded_file($_FILES['peta_dasar']['tmp_name'], $dir . $foto_name)) {
            throw new Exception("Gagal mengupload file ke server.");
        }

        $sql = "UPDATE units SET peta_kerja_foto = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$foto_name, $unit_id]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Peta Dasar berhasil diunggah!', 'foto' => $foto_name]);
        exit;
    }

    // Jika Action tidak dikenali
    throw new Exception("Aksi API tidak ditemukan: " . $action);

// 4. KUNCI UTAMA: Tangkap \Throwable (Semua level error, termasuk Fatal Error)
} catch (\Throwable $e) {
    ob_clean(); // Buang semua output sampah (spasi/html error) yang bikin JSON rusak
    
    echo json_encode([
        'success' => false, 
        'message' => 'Kesalahan Sistem PHP!',
        'detail'  => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine()
    ]);
    exit;
}
?>