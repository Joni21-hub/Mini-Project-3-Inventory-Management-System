<?php
$pageTitle = 'Master Supplier';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

// Query suppliers dengan aggregate jumlah produk yang dipasok (Slide 8 & 14)
$sql = "
    SELECT s.*,
           COUNT(ps.product_id) AS total_supplied_products
    FROM suppliers s
    LEFT JOIN product_suppliers ps ON ps.supplier_id = s.id
    GROUP BY s.id
    ORDER BY s.name ASC
";
$suppliers = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-truck text-primary me-2"></i>Master Supplier (Many-to-Many)</h3>
        <p class="text-muted small mb-0">Kelola direktori mitra pemasok barang inventaris dan harga kulakan.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Tambah Supplier Baru
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-semibold text-muted small">Daftar Pemasok Terdaftar</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nama Perusahaan</th>
                    <th>Kontak Person</th>
                    <th>Nomor Telepon</th>
                    <th>Email</th>
                    <th class="text-center">Produk Dipasok</th>
                    <th class="text-end" style="width: 160px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Belum ada pemasok terdaftar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?= $sup['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                    <?= e($sup['name']) ?>
                                </a>
                                <div class="small text-muted text-truncate" style="max-width: 250px;"><?= e($sup['address'] ?: '-') ?></div>
                            </td>
                            <td><?= e($sup['contact_name'] ?: '-') ?></td>
                            <td><?= e($sup['phone'] ?: '-') ?></td>
                            <td>
                                <?php if ($sup['email']): ?>
                                    <a href="mailto:<?= e($sup['email']) ?>" class="small text-decoration-none"><?= e($sup['email']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="view.php?id=<?= $sup['id'] ?>" class="badge bg-info-subtle text-info border text-decoration-none">
                                    <i class="bi bi-box me-1"></i><?= $sup['total_supplied_products'] ?> Produk
                                </a>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?= $sup['id'] ?>" class="btn btn-outline-secondary" title="Detail Supplier">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?= $sup['id'] ?>" class="btn btn-outline-primary" title="Edit Supplier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $sup['id'] ?>" class="btn btn-outline-danger" title="Hapus Supplier" onclick="return confirm('Apakah Anda yakin ingin menghapus supplier ini?');">
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
