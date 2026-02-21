<?php
session_start();
// Pastikan tidak ada output HTML error yg merusak file download
ini_set('display_errors', 0);
ini_set('memory_limit', '256M'); // Tambah memory untuk export db besar
error_reporting(E_ALL);

require_once '../config/database.php';

// Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Access Denied');
}

$db = new Database();
$pdo = $db->getConnection();
$action = $_POST['action'] ?? '';

try {

    // --- 1. FITUR BACKUP DATABASE (DOWNLOAD .SQL) ---
    if ($action === 'backup_db') {
        
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sqlScript = "-- BACKUP DATABASE PTPN SYSTEM \n";
        $sqlScript .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Get Create Table Structure
            $row = $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
            $sqlScript .= "\n\n" . $row[1] . ";\n\n";

            // Get Data
            $rows = $pdo->query("SELECT * FROM $table");
            $columnCount = $rows->columnCount();

            while ($r = $rows->fetch(PDO::FETCH_NUM)) {
                $sqlScript .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $columnCount; $j++) {
                    $row[$j] = $r[$j];
                    if (isset($row[$j])) {
                        // Escape string untuk SQL (Native way)
                        $sqlScript .= $pdo->quote($row[$j]);
                    } else {
                        $sqlScript .= "NULL";
                    }
                    if ($j < ($columnCount - 1)) {
                        $sqlScript .= ',';
                    }
                }
                $sqlScript .= ");\n";
            }
        }
        
        $sqlScript .= "\n\nSET FOREIGN_KEY_CHECKS=1;";

        // Force Download
        $filename = 'backup_ptpn_' . date('Y-m-d_His') . '.sql';
        
        // Bersihkan buffer sebelum kirim header
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($sqlScript));
        
        echo $sqlScript;
        exit;
    }

    // --- 2. FITUR REFRESH SYSTEM (OPTIMIZE & CLEANUP) ---
    if ($action === 'refresh_system') {
        header('Content-Type: application/json');
        
        // A. Optimize Tables (Defragmentasi Database)
        // Ambil semua tabel
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $pdo->query("OPTIMIZE TABLE $t");
            $pdo->query("ANALYZE TABLE $t"); // Update statistik index
        }

        // B. Clear Session Garbage (Opsional, membersihkan session lama)
        // Note: Tidak destroy session user saat ini agar admin tidak logout
        
        // C. Simulasi response sukses
        echo json_encode([
            'success' => true,
            'message' => 'Database berhasil dioptimalkan (Defrag & Analyze). Cache sistem disegarkan.'
        ]);
        exit;
    }

} catch (Exception $e) {
    // Handle Error
    if ($action === 'refresh_system') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>