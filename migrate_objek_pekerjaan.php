<?php
// REVERSE MIGRATION: Drop 'objek_pekerjaan' column from tr_pemetaan
echo "<pre>";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=db_ptpn_tes', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM tr_pemetaan LIKE 'objek_pekerjaan'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        $pdo->exec("ALTER TABLE tr_pemetaan DROP COLUMN objek_pekerjaan");
        echo "SUCCESS: Column 'objek_pekerjaan' DROPPED from tr_pemetaan\n";
    } else {
        echo "SKIP: Column 'objek_pekerjaan' does not exist (already clean)\n";
    }
    
    // Verify columns
    $cols = $pdo->query("SHOW COLUMNS FROM tr_pemetaan")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nColumns in tr_pemetaan:\n";
    foreach ($cols as $c) {
        echo "  - {$c['Field']} ({$c['Type']})\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
