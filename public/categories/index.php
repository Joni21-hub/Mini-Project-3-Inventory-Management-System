<?php
$pageTitle = 'Master Kategori Produk';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

// Query Laporan Kategori dengan GROUP BY & Fungsi Agregat (Slide 14)
$sql = "
    SELECT c.id, c.name, c.description, c.created_at,
           COUNT(p.id) AS total_products,
           COALESCE(SUM(p.stock), 0) AS total_stock,
           COALESCE(AVG(p.price), 0) AS average_price
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.description, c.created_at
    ORDER BY c.name ASC
";
$categories = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-tags text-primary me-2"></i>Kategori Produk (One-to-Many)</h3>
        <p class="text-muted small mb-0">Kelola kelompok produk, total barang per kategori, dan agregasi statistik stok.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Tambah Kategori Baru
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-semibold text-muted small">Daftar Kategori dan Agregasi Produk</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Nama Kategori</th>
                    <th>Deskripsi</th>
                    <th class="text-center">Jumlah Produk</th>
                    <th class="text-center">Total Unit Stok</th>
                    <th class="text-end">Rata-rata Harga</th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada kategori yang terdaftar.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($categories as $cat): ?>
                        <tr>
                            <td class="text-muted small"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($cat['name']) ?></div>
                            </td>
                            <td>
                                <small class="text-muted"><?= e($cat['description'] ?: '-') ?></small>
                            </td>
                            <td class="text-center">
                                <a href="<?= base_url('public/products/index.php?category_id=' . $cat['id']) ?>" class="badge bg-primary-subtle text-primary border text-decoration-none">
                                    <?= number_format($cat['total_products']) ?> Produk
                                </a>
                            </td>
                            <td class="text-center fw-semibold">
                                <?= number_format($cat['total_stock']) ?> unit
                            </td>
                            <td class="text-end fw-semibold text-dark">
                                <?= format_currency($cat['average_price']) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?= $cat['id'] ?>" class="btn btn-outline-primary" title="Edit Kategori">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $cat['id'] ?>" class="btn btn-outline-danger" title="Hapus Kategori" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
