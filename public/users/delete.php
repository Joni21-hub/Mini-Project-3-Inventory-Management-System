<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Pengguna tidak valid.');
    header("Location: index.php");
    exit;
}

if ($id === current_user()['id']) {
    set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = :id");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'Pengguna tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// Cek apakah user telah mencatat histori mutasi (Foreign Key RESTRICT)
$checkMoves = $pdo->prepare("SELECT COUNT(id) FROM stock_movements WHERE user_id = :id");
$checkMoves->execute(['id' => $id]);
$moveCount = (int)$checkMoves->fetchColumn();

if ($moveCount > 0) {
    set_flash('danger', "Integritas Referensial (RESTRICT): Akun '{$user['username']}' tidak dapat dihapus karena tercatat sebagai petugas dalam {$moveCount} transaksi mutasi barang. Akun ini harus dipertahankan untuk audit trail.");
    header("Location: index.php");
    exit;
}

try {
    $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $del->execute(['id' => $id]);
    set_flash('success', "Akun pengguna '{$user['username']}' berhasil dihapus.");
} catch (PDOException $e) {
    set_flash('danger', "Gagal menghapus pengguna: " . $e->getMessage());
}

header("Location: index.php");
exit;
