<?php
/**
 * Database Configuration & Connection
 * Menggunakan PDO dengan Prepared Statements & Exception Handling
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Menggunakan native prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Sembunyikan kredensial di production, tampilkan error yang aman
            die("Koneksi Database Gagal: " . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

// Inisialisasi variabel global $pdo untuk kemudahan pemanggilan
$pdo = getDB();
