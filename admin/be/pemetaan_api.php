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

    // --- GET JENIS PEKERJAAN ---
    if ($action === 'get_jenis_pekerjaan') {
        $sql = "SELECT id, nama FROM md_jenis_pekerjaan ORDER BY nama ASC";
        $pekerjaan = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        ob_clean();
        echo json_encode(['success' => true, 'data' => $pekerjaan]);
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
        
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;

        // Validasi ketat
        if (empty($_POST['kebun_id'])) throw new Exception("ID Kebun tidak ditemukan.");
        if (empty($_POST['unit_id'])) throw new Exception("ID Unit tidak ditemukan.");
        if (empty($_POST['blok_nama'])) throw new Exception("Nama Blok harus diisi!");
        if (empty($_POST['tanggal_realisasi'])) throw new Exception("Tanggal Realisasi harus diisi!");
        if (empty($_POST['geojson'])) throw new Exception("Data Peta kosong! Silakan gambar ulang di peta.");

        $kebun_id   = (int)$_POST['kebun_id'];
        $unit_id    = (int)$_POST['unit_id'];
        $blok_nama  = trim($_POST['blok_nama']);
        $jp_id      = !empty($_POST['jenis_pekerjaan_id']) ? (int)$_POST['jenis_pekerjaan_id'] : null;
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

        // Query Insert (SEKARANG SUDAH ADA KOLOM KETERANGAN & REALISASI & JENIS PEKERJAAN)
        if ($id) {
            $sql = "UPDATE tr_pemetaan SET 
                        blok_nama = ?, jenis_pekerjaan_id = ?, geojson = ?, latitude = ?, longitude = ?, warna = ?,
                        keterangan = ?, tanggal_realisasi = ?, fisik_hari_ini = ?, fisik_sd = ?, hk_hari_ini = ?, hk_sd = ?, 
                        bahan_kimia_hari_ini = ?, bahan_kimia_sd = ?, campuran_hari_ini = ?, campuran_sd = ?";
            $params = [
                $blok_nama, $jp_id, $geojson, $lat, $lng, $warna, 
                $keterangan, $tgl, $f_hi, $f_sd, $hk_hi, $hk_sd, $k_hi, $k_sd, $c_hi, $c_sd
            ];
            if ($foto_name) {
                $sql .= ", foto = ?";
                $params[] = $foto_name;
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $msg = 'Area GIS berhasil diupdate!';
        } else {
            $sql = "INSERT INTO tr_pemetaan (
                        kebun_id, unit_id, blok_nama, jenis_pekerjaan_id, geojson, latitude, longitude, warna, foto, keterangan,
                        tanggal_realisasi, fisik_hari_ini, fisik_sd, hk_hari_ini, hk_sd, bahan_kimia_hari_ini, bahan_kimia_sd, campuran_hari_ini, campuran_sd
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $kebun_id, $unit_id, $blok_nama, $jp_id, $geojson, $lat, $lng, $warna, $foto_name, $keterangan,
                $tgl, $f_hi, $f_sd, $hk_hi, $hk_sd, $k_hi, $k_sd, $c_hi, $c_sd
            ]);
            $msg = 'Area GIS berhasil disimpan ke database!';
        }
    
        // === CASCADE RECALCULATE S/D untuk semua baris SETELAH baris yang baru disimpan ===
        // Ambil SEMUA baris untuk kelompok ini, urutkan by tanggal ASC, id ASC
        $sqlAll = "SELECT id, fisik_hari_ini, hk_hari_ini, bahan_kimia_hari_ini, campuran_hari_ini
                   FROM tr_pemetaan
                   WHERE kebun_id = ? AND unit_id = ? AND jenis_pekerjaan_id = ?
                   ORDER BY tanggal_realisasi ASC, id ASC";
        $stAll = $conn->prepare($sqlAll);
        $stAll->execute([$kebun_id, $unit_id, $jp_id]);
        $allRows = $stAll->fetchAll(PDO::FETCH_ASSOC);
    
        $prevF = 0; $prevH = 0; $prevB = 0; $prevC = 0;
        $stUpd = $conn->prepare("UPDATE tr_pemetaan SET fisik_sd=?, hk_sd=?, bahan_kimia_sd=?, campuran_sd=? WHERE id=?");
        
        foreach ($allRows as $r) {
            $newF = $prevF + (float)$r['fisik_hari_ini'];
            $newH = $prevH + (float)$r['hk_hari_ini'];
            $newB = $prevB + (float)$r['bahan_kimia_hari_ini'];
            $newC = $prevC + (float)$r['campuran_hari_ini'];
        
            $stUpd->execute([$newF, $newH, $newB, $newC, $r['id']]);
        
            $prevF = $newF; $prevH = $newH; $prevB = $newB; $prevC = $newC;
        }
    
        ob_clean();
        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    }

    // --- 4. AMBIL DATA MAP & STATS ---
    if ($action === 'get_map_data') {
        if (empty($_GET['kebun_id']) || empty($_GET['unit_id'])) {
            throw new Exception("Parameter Kebun atau Unit tidak lengkap.");
        }
        $kebun_id = (int)$_GET['kebun_id'];
        $unit_id  = (int)$_GET['unit_id'];
        $jp_id    = isset($_GET['jenis_pekerjaan_id']) ? (int)$_GET['jenis_pekerjaan_id'] : 0;
        $bulan    = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m'); // format YYYY-MM

        $sql = "SELECT * FROM tr_pemetaan WHERE kebun_id = ? AND unit_id = ?";
        $params = [$kebun_id, $unit_id];
        
        if ($jp_id > 0) {
            $sql .= " AND jenis_pekerjaan_id = ?";
            $params[] = $jp_id;
        }

        if (!empty($bulan)) {
            $sql .= " AND DATE_FORMAT(tanggal_realisasi, '%Y-%m') = ?";
            $params[] = $bulan;
        }

        $sql .= " ORDER BY tanggal_realisasi ASC, id ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ambil info Peta Dasar per Unit (berlaku untuk SEMUA jenis pekerjaan)
        $peta_kerja_foto = null;
        $sqlUnit = "SELECT peta_kerja_foto FROM tr_pemetaan_peta_dasar WHERE unit_id = ? LIMIT 1";
        $stmtUnit = $conn->prepare($sqlUnit);
        $stmtUnit->execute([$unit_id]);
        $unitData = $stmtUnit->fetch(PDO::FETCH_ASSOC);
        $peta_kerja_foto = $unitData ? $unitData['peta_kerja_foto'] : null;

        // Ambil info unit & kebun
        $info = null;
        $sqlInfo = "SELECT u.nama_unit, k.nama_kebun FROM units u LEFT JOIN md_kebun k ON u.kebun_id = k.id WHERE u.id = ?";
        $stmtInfo = $conn->prepare($sqlInfo);
        $stmtInfo->execute([$unit_id]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        ob_clean();
        echo json_encode(['success' => true, 'data' => $data, 'peta_kerja_foto' => $peta_kerja_foto, 'info' => $info]);
        exit;
    }

    // --- 5. GET REKAP DATA (semua JP per bulan untuk Peta Rekap) ---
    if ($action === 'get_rekap_data') {
        if (empty($_GET['kebun_id']) || empty($_GET['unit_id'])) {
            throw new Exception("Parameter Kebun atau Unit tidak lengkap.");
        }
        $kebun_id = (int)$_GET['kebun_id'];
        $unit_id  = (int)$_GET['unit_id'];
        $bulan    = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

        // Ambil semua data pemetaan bulan ini + nama JP
        $sql = "SELECT p.*, jp.nama AS jp_nama 
                FROM tr_pemetaan p
                LEFT JOIN md_jenis_pekerjaan jp ON p.jenis_pekerjaan_id = jp.id
                WHERE p.kebun_id = ? AND p.unit_id = ?
                AND DATE_FORMAT(p.tanggal_realisasi, '%Y-%m') = ?
                ORDER BY jp.nama ASC, p.tanggal_realisasi ASC, p.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$kebun_id, $unit_id, $bulan]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ambil info Peta Dasar per Unit
        $peta_kerja_foto = null;
        $sqlUnit = "SELECT peta_kerja_foto FROM tr_pemetaan_peta_dasar WHERE unit_id = ? LIMIT 1";
        $stmtUnit = $conn->prepare($sqlUnit);
        $stmtUnit->execute([$unit_id]);
        $unitData = $stmtUnit->fetch(PDO::FETCH_ASSOC);
        $peta_kerja_foto = $unitData ? $unitData['peta_kerja_foto'] : null;

        // Ambil info unit & kebun
        $stmtInfo = $conn->prepare("SELECT u.nama_unit, k.nama_kebun FROM units u LEFT JOIN md_kebun k ON u.kebun_id = k.id WHERE u.id = ?");
        $stmtInfo->execute([$unit_id]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => $data,
            'peta_kerja_foto' => $peta_kerja_foto,
            'info' => $info ?: ['nama_unit' => 'UNIT', 'nama_kebun' => 'KEBUN']
        ]);
        exit;
    }

    // --- 6. UPLOAD PETA DASAR (IMAGE / PDF) ---
    if ($action === 'upload_peta_dasar') {
        if (empty($_POST['unit_id'])) throw new Exception("ID Unit tidak ditemukan.");
        $unit_id = (int)$_POST['unit_id'];

        // jenis_pekerjaan_id tidak wajib — peta dasar berlaku untuk semua JP di unit ini
        $jp_id = (int)($_POST['jenis_pekerjaan_id'] ?? 0);

        if (empty($_FILES['peta_dasar']['name'])) {
            throw new Exception("File gambar/PDF Peta Dasar belum dipilih.");
        }

        $dir = "../../uploads/pemetaan/base_map/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['peta_dasar']['name'], PATHINFO_EXTENSION));
        $valid_ext = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (!in_array($ext, $valid_ext)) {
            throw new Exception("Format file harus JPG, PNG, WEBP, atau PDF.");
        }

        $base_name = "BASEMAP_UNIT_" . $unit_id . "_" . time();

        if ($ext === 'pdf') {
            // Simpan file PDF asli dulu
            $pdf_path = $dir . $base_name . ".pdf";
            if (!move_uploaded_file($_FILES['peta_dasar']['tmp_name'], $pdf_path)) {
                throw new Exception("Gagal mengupload file PDF ke server.");
            }

            // Konversi halaman 1 PDF ke PNG menggunakan Imagick
            if (!class_exists('Imagick')) {
                // Fallback: jika Imagick tidak tersedia, coba GD + exec ghostscript
                $png_path = $dir . $base_name . ".png";
                $gs_cmd = "gswin64c";
                // Cek apakah ghostscript tersedia
                $check = @shell_exec("where gswin64c 2>&1");
                if (empty($check) || strpos($check, 'Could not find') !== false) {
                    $check2 = @shell_exec("where gs 2>&1");
                    if (empty($check2) || strpos($check2, 'Could not find') !== false) {
                        // Tidak ada Ghostscript, biarkan PDF sebagai file (tidak bisa preview di peta)
                        $foto_name = $base_name . ".pdf";
                        
                        $sql = "INSERT INTO tr_pemetaan_peta_dasar (unit_id, jenis_pekerjaan_id, peta_kerja_foto) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE peta_kerja_foto = VALUES(peta_kerja_foto)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$unit_id, $jp_id, $foto_name]);

                        ob_clean();
                        echo json_encode(['success' => true, 'message' => 'PDF berhasil diupload! (Preview peta tidak tersedia karena Ghostscript/Imagick belum terinstal)', 'foto' => $foto_name]);
                        exit;
                    }
                    $gs_cmd = "gs";
                }

                // Konversi PDF ke PNG via Ghostscript
                $escaped_pdf = escapeshellarg(realpath($pdf_path));
                $escaped_png = escapeshellarg(realpath($dir) . DIRECTORY_SEPARATOR . $base_name . ".png");
                $cmd = "$gs_cmd -dNOPAUSE -dBATCH -sDEVICE=png16m -r200 -dFirstPage=1 -dLastPage=1 -sOutputFile=$escaped_png $escaped_pdf 2>&1";
                $output = shell_exec($cmd);
                
                if (file_exists($dir . $base_name . ".png")) {
                    $foto_name = $base_name . ".png";
                } else {
                    // Ghostscript gagal, simpan sebagai PDF saja
                    $foto_name = $base_name . ".pdf";
                }
            } else {
                // Imagick tersedia — konversi PDF halaman 1 ke PNG
                try {
                    $imagick = new \Imagick();
                    $imagick->setResolution(200, 200);
                    $imagick->readImage(realpath($pdf_path) . '[0]'); // Halaman pertama saja
                    $imagick->setImageFormat('png');
                    
                    $png_path = $dir . $base_name . ".png";
                    $imagick->writeImage($png_path);
                    $imagick->clear();
                    $imagick->destroy();
                    
                    $foto_name = $base_name . ".png";
                } catch (\Exception $imgErr) {
                    // Imagick gagal, simpan sebagai PDF saja
                    $foto_name = $base_name . ".pdf";
                }
            }
        } else {
            // Upload gambar biasa (JPG/PNG/WEBP)
            $foto_name = $base_name . ".$ext";
            if (!move_uploaded_file($_FILES['peta_dasar']['tmp_name'], $dir . $foto_name)) {
                throw new Exception("Gagal mengupload file ke server.");
            }
        }

        $sql = "INSERT INTO tr_pemetaan_peta_dasar (unit_id, jenis_pekerjaan_id, peta_kerja_foto) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE peta_kerja_foto = VALUES(peta_kerja_foto)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$unit_id, $jp_id, $foto_name]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Peta Dasar berhasil diunggah!', 'foto' => $foto_name]);
        exit;
    }

    // --- 6. HAPUS DATA PEMETAAN ---
    if ($action === 'delete_map_data') {
        if (empty($_POST['id'])) throw new Exception("ID tidak ditemukan.");
        $id = (int)$_POST['id'];
        
        // Ambil info record sebelum dihapus (untuk cascade recalc)
        $stInfo = $conn->prepare("SELECT kebun_id, unit_id, jenis_pekerjaan_id FROM tr_pemetaan WHERE id = ?");
        $stInfo->execute([$id]);
        $delInfo = $stInfo->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("DELETE FROM tr_pemetaan WHERE id = ?");
        $stmt->execute([$id]);

        // Cascade recalculate S/D after delete
        if ($delInfo) {
            $sqlAll = "SELECT id, fisik_hari_ini, hk_hari_ini, bahan_kimia_hari_ini, campuran_hari_ini
                       FROM tr_pemetaan
                       WHERE kebun_id = ? AND unit_id = ? AND jenis_pekerjaan_id = ?
                       ORDER BY tanggal_realisasi ASC, id ASC";
            $stAll = $conn->prepare($sqlAll);
            $stAll->execute([$delInfo['kebun_id'], $delInfo['unit_id'], $delInfo['jenis_pekerjaan_id']]);
            $allRows = $stAll->fetchAll(PDO::FETCH_ASSOC);

            $prevF = 0; $prevH = 0; $prevB = 0; $prevC = 0;
            $stUpd = $conn->prepare("UPDATE tr_pemetaan SET fisik_sd=?, hk_sd=?, bahan_kimia_sd=?, campuran_sd=? WHERE id=?");

            foreach ($allRows as $r) {
                $newF = $prevF + (float)$r['fisik_hari_ini'];
                $newH = $prevH + (float)$r['hk_hari_ini'];
                $newB = $prevB + (float)$r['bahan_kimia_hari_ini'];
                $newC = $prevC + (float)$r['campuran_hari_ini'];

                $stUpd->execute([$newF, $newH, $newB, $newC, $r['id']]);

                $prevF = $newF; $prevH = $newH; $prevB = $newB; $prevC = $newC;
            }
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Data Realisasi berhasil dihapus!']);
        exit;
    }

    // --- 7. AMBIL DATA S/D SEBELUMNYA ---
    if ($action === 'get_previous_sd') {
        $kebun_id = (int)($_GET['kebun_id'] ?? 0);
        $unit_id  = (int)($_GET['unit_id'] ?? 0);
        $jp_id    = (int)($_GET['jenis_pekerjaan_id'] ?? 0);
        $blok     = $_GET['blok_nama'] ?? '';
        $tgl      = $_GET['tanggal_realisasi'] ?? date('Y-m-d');
        // Pengecualian ID edit saat ini jika ada
        $current_id = (int)($_GET['current_id'] ?? 0);

        if (!$kebun_id || !$unit_id || !$jp_id) {
            echo json_encode(['success' => true, 'data' => null]);
            exit;
        }

        // Cari record terakhir untuk jenis pekerjaan yang sama (SEMUA blok) SEBELUM atau SAMA DENGAN tanggal yang dipilih 
        // yang BUKAN merupakan record yang sedang diedit. S/D bersifat kumulatif lintas blok.
        $sql = "SELECT fisik_sd, hk_sd, bahan_kimia_sd, campuran_sd 
                FROM tr_pemetaan 
                WHERE kebun_id = ? AND unit_id = ? AND jenis_pekerjaan_id = ?
                  AND tanggal_realisasi <= ? AND id != ?
                ORDER BY tanggal_realisasi DESC, id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$kebun_id, $unit_id, $jp_id, $tgl, $current_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        ob_clean();
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // === MCS BULANAN APIs ===

    // --- GET JENIS PEKERJAAN BULANAN (per kebun) ---
    if ($action === 'get_jenis_pekerjaan_bulanan') {
        $kebun_id = (int)($_GET['kebun_id'] ?? 0);
        if (!$kebun_id) throw new Exception("Parameter kebun_id wajib.");
        $sql = "SELECT id, nama, keterangan FROM md_jenis_pekerjaan_bulanan WHERE kebun_id = ? ORDER BY nama ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$kebun_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ob_clean();
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // --- GET MCS BULANAN DATA (load semua data + base map, filter tahun) ---
    if ($action === 'get_mcs_bulanan_data') {
        if (empty($_GET['kebun_id']) || empty($_GET['unit_id'])) {
            throw new Exception("Parameter Kebun atau Unit tidak lengkap.");
        }
        $kebun_id = (int)$_GET['kebun_id'];
        $unit_id  = (int)$_GET['unit_id'];
        $tahun    = (int)($_GET['tahun'] ?? date('Y'));

        $sql = "SELECT m.*, jp.nama AS jp_nama, jp.satuan AS satuan
                FROM tr_mcs_bulanan m
                LEFT JOIN md_jenis_pekerjaan_bulanan jp ON m.jenis_pekerjaan_bulanan_id = jp.id
                WHERE m.kebun_id = ? AND m.unit_id = ? AND m.tahun = ?
                ORDER BY jp.nama ASC, m.objek_pekerjaan ASC, m.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$kebun_id, $unit_id, $tahun]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Base map — khusus MCS Bulanan (jenis_pekerjaan_id = 99999)
        $peta_kerja_foto = null;
        $sqlUnit = "SELECT peta_kerja_foto FROM tr_pemetaan_peta_dasar WHERE unit_id = ? AND jenis_pekerjaan_id = 99999 LIMIT 1";
        $stmtUnit = $conn->prepare($sqlUnit);
        $stmtUnit->execute([$unit_id]);
        $unitData = $stmtUnit->fetch(PDO::FETCH_ASSOC);
        $peta_kerja_foto = $unitData ? $unitData['peta_kerja_foto'] : null;

        // Fetch all Luas values for this unit
        $sqlLuas = "SELECT jenis_pekerjaan_id, luas_ha FROM tr_pemetaan_peta_dasar WHERE unit_id = ? AND jenis_pekerjaan_id >= 99999";
        $stmtLuas = $conn->prepare($sqlLuas);
        $stmtLuas->execute([$unit_id]);
        $luas_data = [];
        while($r = $stmtLuas->fetch(PDO::FETCH_ASSOC)) {
            $luas_data[$r['jenis_pekerjaan_id']] = $r['luas_ha'];
        }

        // Info
        $stmtInfo = $conn->prepare("SELECT u.nama_unit, k.nama_kebun FROM units u LEFT JOIN md_kebun k ON u.kebun_id = k.id WHERE u.id = ?");
        $stmtInfo->execute([$unit_id]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => $data,
            'peta_kerja_foto' => $peta_kerja_foto,
            'luas_data' => $luas_data,
            'info' => $info ?: ['nama_unit' => 'UNIT', 'nama_kebun' => 'KEBUN'],
            'tahun' => $tahun
        ]);
        exit;
    }

    // --- SAVE LUAS STATIK (PETA DASAR) ---
    if ($action === 'save_luas_peta_dasar') {
        $unit_id = (int)$_POST['unit_id'];
        $jp_id = isset($_POST['jp_id']) && $_POST['jp_id'] !== '' ? (int)$_POST['jp_id'] : 0;
        $mapped_id = $jp_id ? (100000 + $jp_id) : 99999;
        $luas_ha = isset($_POST['luas_ha']) && $_POST['luas_ha'] !== '' ? (float)$_POST['luas_ha'] : null;
        if (!$unit_id) throw new Exception("Unit ID tidak valid.");
        
        $sqlCheck = "SELECT id FROM tr_pemetaan_peta_dasar WHERE unit_id = ? AND jenis_pekerjaan_id = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->execute([$unit_id, $mapped_id]);
        $exists = $stmtCheck->fetch();

        if ($exists) {
            $sql = "UPDATE tr_pemetaan_peta_dasar SET luas_ha = ? WHERE unit_id = ? AND jenis_pekerjaan_id = ?";
            $conn->prepare($sql)->execute([$luas_ha, $unit_id, $mapped_id]);
        } else {
            $sql = "INSERT INTO tr_pemetaan_peta_dasar (unit_id, jenis_pekerjaan_id, luas_ha) VALUES (?, ?, ?)";
            $conn->prepare($sql)->execute([$unit_id, $mapped_id, $luas_ha]);
        }
        ob_clean();
        echo json_encode(['success' => true]);
        exit;
    }

    // --- SAVE MCS BULANAN (insert) ---
    if ($action === 'save_mcs_bulanan') {
        if (empty($_POST['kebun_id'])) throw new Exception("Kebun tidak ditemukan.");
        if (empty($_POST['unit_id'])) throw new Exception("Unit tidak ditemukan.");
        if (empty($_POST['jenis_pekerjaan_bulanan_id'])) throw new Exception("Jenis Pekerjaan wajib dipilih.");
        if (empty($_POST['geojson'])) throw new Exception("Data peta kosong! Gambar area di peta terlebih dahulu.");

        $kebun_id = (int)$_POST['kebun_id'];
        $unit_id  = (int)$_POST['unit_id'];
        $tahun    = (int)($_POST['tahun'] ?? date('Y'));
        $jp_id    = (int)$_POST['jenis_pekerjaan_bulanan_id'];
        $objek    = trim($_POST['objek_pekerjaan'] ?? '');
        $geojson  = trim($_POST['geojson']);
        $lat      = $_POST['latitude'] ?? null;
        $lng      = $_POST['longitude'] ?? null;
        $warna    = $_POST['warna'] ?? '#0891b2';
        $ket      = trim($_POST['keterangan'] ?? '');

        // 12 bulan
        $b = [];
        for ($i = 1; $i <= 12; $i++) {
            $b[$i] = (float)($_POST['bulan_' . $i] ?? 0);
        }

        // Validate GeoJSON
        json_decode($geojson);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Format gambar peta (GeoJSON) rusak.");
        }

        $sql = "INSERT INTO tr_mcs_bulanan (
                    kebun_id, unit_id, tahun, jenis_pekerjaan_bulanan_id, objek_pekerjaan,
                    geojson, latitude, longitude, warna,
                    bulan_1, bulan_2, bulan_3, bulan_4, bulan_5, bulan_6,
                    bulan_7, bulan_8, bulan_9, bulan_10, bulan_11, bulan_12,
                    keterangan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $kebun_id, $unit_id, $tahun, $jp_id, $objek,
            $geojson, $lat, $lng, $warna,
            $b[1], $b[2], $b[3], $b[4], $b[5], $b[6],
            $b[7], $b[8], $b[9], $b[10], $b[11], $b[12],
            $ket
        ]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Data MCS Bulanan tahun ' . $tahun . ' berhasil disimpan!', 'id' => $conn->lastInsertId()]);
        exit;
    }

    // --- UPDATE MCS BULANAN ---
    if ($action === 'update_mcs_bulanan') {
        if (empty($_POST['id'])) throw new Exception("ID tidak ditemukan.");
        $id = (int)$_POST['id'];

        $jp_id = (int)($_POST['jenis_pekerjaan_bulanan_id'] ?? 0);
        $tahun = (int)($_POST['tahun'] ?? date('Y'));
        $objek = trim($_POST['objek_pekerjaan'] ?? '');
        $geojson = trim($_POST['geojson'] ?? '');
        $lat   = $_POST['latitude'] ?? null;
        $lng   = $_POST['longitude'] ?? null;
        $warna = $_POST['warna'] ?? '#0891b2';
        $ket   = trim($_POST['keterangan'] ?? '');

        $b = [];
        for ($i = 1; $i <= 12; $i++) {
            $b[$i] = (float)($_POST['bulan_' . $i] ?? 0);
        }

        if (!empty($geojson)) {
            json_decode($geojson);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Format gambar peta (GeoJSON) rusak.");
            }
        }

        $sql = "UPDATE tr_mcs_bulanan SET
                    tahun = ?, jenis_pekerjaan_bulanan_id = ?, objek_pekerjaan = ?,
                    geojson = ?, latitude = ?, longitude = ?, warna = ?,
                    bulan_1=?, bulan_2=?, bulan_3=?, bulan_4=?, bulan_5=?, bulan_6=?,
                    bulan_7=?, bulan_8=?, bulan_9=?, bulan_10=?, bulan_11=?, bulan_12=?,
                    keterangan = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $tahun, $jp_id, $objek,
            $geojson, $lat, $lng, $warna,
            $b[1], $b[2], $b[3], $b[4], $b[5], $b[6],
            $b[7], $b[8], $b[9], $b[10], $b[11], $b[12],
            $ket,
            $id
        ]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Data MCS Bulanan berhasil diupdate!']);
        exit;
    }

    // --- DELETE MCS BULANAN ---
    if ($action === 'delete_mcs_bulanan') {
        if (empty($_POST['id'])) throw new Exception("ID tidak ditemukan.");
        $id = (int)$_POST['id'];

        $stmt = $conn->prepare("DELETE FROM tr_mcs_bulanan WHERE id = ?");
        $stmt->execute([$id]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Data MCS Bulanan berhasil dihapus!']);
        exit;
    }

    // Jika Action tidak dikenali
    throw new Exception("Aksi API tidak ditemukan: " . $action);

// 4. KUNCI UTAMA: Tangkap \Throwable (Semua level error, termasuk Fatal Error)
} catch (\Throwable $e) {
    ob_clean(); // Buang semua output sampah (spasi/html error) yang bikin JSON rusak
    
    echo json_encode([
        'success' => false, 
        'message' => 'Kesalahan Sistem: ' . $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine()
    ]);
    exit;
}
?>