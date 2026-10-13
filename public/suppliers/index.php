<?php
$pageTitle = 'Master Mitra Supplier';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

// Query suppliers dengan aggregate jumlah produk yang dipasok
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
        <h3 class="fw-bold text-dark mb-1" style="letter-spacing: -0.02em;">Mitra Supplier</h3>
        <p class="text-muted small mb-0">Direktori vendor dan rekanan pengadaan barang inventaris.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="create.php" class="btn btn-primary">
            Tambah Supplier Baru
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span class="text-muted small">Daftar Pemasok Resmi (<?= count($suppliers) ?> rekanan)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nama Perusahaan & Alamat</th>
                    <th>Kontak Person (PIC)</th>
                    <th>Nomor Telepon</th>
                    <th>Email</th>
                    <th class="text-center">Produk Dipasok</th>
                    <th class="text-end" style="width: 170px;">Aksi</th>
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
                                    <a href="mailto:<?= e($sup['email']) ?>" class="small text-decoration-none text-secondary"><?= e($sup['email']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="view.php?id=<?= $sup['id'] ?>" class="badge bg-light text-dark border text-decoration-none">
                                    <?= $sup['total_supplied_products'] ?> Produk
                                </a>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?= $sup['id'] ?>" class="btn btn-outline-secondary">
                                        Detail
                                    </a>
                                    <a href="edit.php?id=<?= $sup['id'] ?>" class="btn btn-outline-primary">
                                        Edit
                                    </a>
                                    <a href="delete.php?id=<?= $sup['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus supplier ini?');">
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
