<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Supplier tidak valid.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = :id");
$stmt->execute(['id' => $id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    set_flash('danger', 'Supplier tidak ditemukan.');
    header("Location: index.php");
    exit;
}

try {
    $del = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
    $del->execute(['id' => $id]);
    set_flash('success', "Supplier '{$supplier['name']}' berhasil dihapus.");
} catch (PDOException $e) {
    set_flash('danger', "Gagal menghapus supplier: " . $e->getMessage());
}

header("Location: index.php");
exit;
