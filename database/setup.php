<?php
/**
 * Database Auto Setup & Migration Script
 * Can be run via CLI: php database/setup.php
 * Or via browser: http://localhost/Mini-Project-3-Inventory-Management-System/database/setup.php
 */

$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("schema.sql file not found at " . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);

    $msg = "Database `inventory_management` and initial seed data initialized successfully!";
    if (php_sapi_name() === 'cli') {
        echo "[SUCCESS] " . $msg . PHP_EOL;
    } else {
        echo "<div style='font-family:sans-serif;padding:20px;background:#e8f5e9;color:#2e7d32;border-radius:8px;margin:30px auto;max-width:600px;'>
                <h2>Setup Berhasil!</h2>
                <p>{$msg}</p>
                <p>Default Login:</p>
                <ul>
                    <li><strong>Admin:</strong> admin / admin123</li>
                    <li><strong>Staff:</strong> staff / staff123</li>
                </ul>
                <a href='../public/login.php' style='display:inline-block;padding:10px 20px;background:#2e7d32;color:#fff;text-decoration:none;border-radius:4px;'>Masuk ke Aplikasi</a>
              </div>";
    }
} catch (Exception $e) {
    $errMsg = "Error during setup: " . $e->getMessage();
    if (php_sapi_name() === 'cli') {
        echo "[ERROR] " . $errMsg . PHP_EOL;
    } else {
        echo "<div style='font-family:sans-serif;padding:20px;background:#ffebee;color:#c62828;border-radius:8px;margin:30px auto;max-width:600px;'>
                <h2>Setup Gagal!</h2>
                <p>{$errMsg}</p>
              </div>";
    }
}
