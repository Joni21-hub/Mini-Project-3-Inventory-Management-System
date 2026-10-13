<?php
$pageTitle = 'Master Kategori Produk';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

// Query Laporan Kategori dengan GROUP BY & Fungsi Agregat
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
        <h3 class="fw-bold text-dark mb-1" style="letter-spacing: -0.02em;">Kategori Produk</h3>
        <p class="text-muted small mb-0">Kelola kelompok inventaris, total produk terdaftar, dan statistik rata-rata harga.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="create.php" class="btn btn-primary">
            Tambah Kategori Baru
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span class="text-muted small">Daftar Kategori Terdaftar (<?= count($categories) ?> kelompok)</span>
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
                    <th class="text-end" style="width: 150px;">Aksi</th>
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
                                <a href="<?= base_url('public/products/index.php?category_id=' . $cat['id']) ?>" class="badge bg-light text-dark border text-decoration-none">
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
                                    <a href="edit.php?id=<?= $cat['id'] ?>" class="btn btn-outline-primary">
                                        Edit
                                    </a>
                                    <a href="delete.php?id=<?= $cat['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?');">
                                        Hapus
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
