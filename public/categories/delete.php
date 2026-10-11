<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Kategori tidak valid.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = :id");
$stmt->execute(['id' => $id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('danger', 'Kategori tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// Cek apakah ada produk dalam kategori ini (Foreign Key RESTRICT)
$checkProducts = $pdo->prepare("SELECT COUNT(id) FROM products WHERE category_id = :id");
$checkProducts->execute(['id' => $id]);
$productCount = (int)$checkProducts->fetchColumn();

if ($productCount > 0) {
    set_flash('danger', "Integritas Referensial (RESTRICT): Kategori '{$category['name']}' tidak dapat dihapus karena masih memuat {$productCount} produk. Pindahkan atau hapus produk tersebut terlebih dahulu.");
    header("Location: index.php");
    exit;
}

try {
    $del = $pdo->prepare("DELETE FROM categories WHERE id = :id");
    $del->execute(['id' => $id]);
    set_flash('success', "Kategori '{$category['name']}' berhasil dihapus.");
} catch (PDOException $e) {
    set_flash('danger', "Gagal menghapus kategori: " . $e->getMessage());
}

header("Location: index.php");
exit;
