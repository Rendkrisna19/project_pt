<?php
// Konfigurasi untuk koneksi ke database
define('DB_HOST', 'localhost'); // Sesuaikan dengan host database Anda
define('DB_USER', 'root');      // Sesuaikan dengan username database Anda
define('DB_PASS', '');          // Sesuaikan dengan password database Anda
define('DB_NAME', 'db_ptpn');   // Sesuaikan dengan nama database Anda

class Database {
    private $dbh; // Database Handler
    private $stmt; // Statement

    public function __construct() {
        // Data Source Name
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME;

        $options = [
            PDO::ATTR_PERSISTENT => true, // Menjaga koneksi tetap terbuka
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION // Mode error untuk menampilkan exception
        ];

        try {
            $this->dbh = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }
    
    // Fungsi ini bisa dikembangkan nanti untuk query
    public function query($query) {
        $this->stmt = $this->dbh->prepare($query);
    }

    public function getConnection() {
    return $this->dbh;
}
    
    // Dan fungsi-fungsi lainnya untuk binding, execute, dll.
}

// Inisialisasi koneksi bisa dilakukan di file yang membutuhkan
// $db = new Database();
?>