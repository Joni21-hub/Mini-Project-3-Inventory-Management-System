<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Produk tidak valid.');
    header("Location: index.php");
    exit;
}

// 1. Periksa apakah produk ada
$stmt = $pdo->prepare("SELECT id, name, sku FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// 2. Terapkan Aturan Bisnis & Referential Integrity (Slide 9 & 33):
// "Produk yang memiliki histori tidak boleh dihapus sembarangan."
$checkHistory = $pdo->prepare("SELECT COUNT(id) FROM stock_movements WHERE product_id = :id");
$checkHistory->execute(['id' => $id]);
$movementCount = (int)$checkHistory->fetchColumn();

if ($movementCount > 0) {
    set_flash('danger', "Aturan Bisnis: Produk '{$product['name']}' ({$product['sku']}) TIDAK DAPAT DIHAPUS karena memiliki {$movementCount} riwayat transaksi mutasi (Foreign Key RESTRICT). Data historis harus tetap terjaga demi integritas audit stok.");
    header("Location: index.php");
    exit;
}

try {
    $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $deleteStmt->execute(['id' => $id]);
    set_flash('success', "Produk '{$product['name']}' berhasil dihapus.");
} catch (PDOException $e) {
    set_flash('danger', "Gagal menghapus produk karena adanya integritas referensial database: " . $e->getMessage());
}

header("Location: index.php");
exit;
