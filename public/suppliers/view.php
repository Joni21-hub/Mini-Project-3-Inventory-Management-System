<?php
$pageTitle = 'Detail Mitra Supplier';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Supplier tidak valid.');
    header("Location: index.php");
    exit;
}

// 1. Ambil data profil supplier
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
$stmt->execute(['id' => $id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    set_flash('danger', 'Supplier tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// 2. Ambil daftar produk yang dipasok oleh supplier ini
$prodStmt = $pdo->prepare("
    SELECT p.id, p.sku, p.name, p.price, p.stock, p.minimum_stock,
           c.name AS category_name,
           ps.supplier_price
    FROM product_suppliers ps
    JOIN products p ON p.id = ps.product_id
    JOIN categories c ON c.id = p.category_id
    WHERE ps.supplier_id = :sid
    ORDER BY p.name ASC
");
$prodStmt->execute(['sid' => $id]);
$suppliedProducts = $prodStmt->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            &larr; Kembali
        </a>
        <div>
            <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.02em;"><?= e($supplier['name']) ?></h3>
            <span class="badge bg-light text-secondary border">Mitra Pemasok Resmi</span>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="edit.php?id=<?= $supplier['id'] ?>" class="btn btn-primary btn-sm">
            Edit Data Supplier
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Profil Perusahaan -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold text-dark">Informasi Kontak Vendor</span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-3 small">
                    <div class="border-bottom pb-2">
                        <div class="text-muted mb-1">Nama Kontak Person (PIC)</div>
                        <div class="fw-semibold text-dark fs-6"><?= e($supplier['contact_name'] ?: '-') ?></div>
                    </div>
                    <div class="border-bottom pb-2">
                        <div class="text-muted mb-1">Nomor Telepon</div>
                        <div class="fw-semibold text-dark"><?= e($supplier['phone'] ?: '-') ?></div>
                    </div>
                    <div class="border-bottom pb-2">
                        <div class="text-muted mb-1">Alamat Email</div>
                        <div>
                            <?php if ($supplier['email']): ?>
                                <a href="mailto:<?= e($supplier['email']) ?>" class="text-decoration-none fw-semibold text-primary"><?= e($supplier['email']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="border-bottom pb-2">
                        <div class="text-muted mb-1">Alamat Gudang / Kantor</div>
                        <div class="text-secondary"><?= nl2br(e($supplier['address'] ?: '-')) ?></div>
                    </div>
                    <div>
                        <div class="text-muted mb-1">Terdaftar Sejak</div>
                        <div class="text-secondary"><?= format_datetime($supplier['created_at']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Katalog Produk Yang Dipasok -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="fw-bold text-dark">Katalog Produk Yang Dipasok</div>
                    <small class="text-muted">Item yang disuplai oleh mitra ini</small>
                </div>
                <span class="badge bg-light text-secondary border"><?= count($suppliedProducts) ?> Item</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($suppliedProducts)): ?>
                    <div class="p-4 text-center text-muted">
                        Belum ada produk yang diasosiasikan dengan supplier ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Produk & SKU</th>
                                    <th>Kategori</th>
                                    <th class="text-end">Harga Beli Kulakan</th>
                                    <th class="text-center">Sisa Stok</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliedProducts as $sp): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?= product_image_url($sp['sku'], $sp['name']) ?>" alt="<?= e($sp['name']) ?>" class="product-avatar-sm" style="width: 38px; height: 38px;">
                                                <div>
                                                    <a href="<?= base_url('public/products/view.php?id=' . $sp['id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                                        <?= e($sp['name']) ?>
                                                    </a><br>
                                                    <small class="text-muted font-monospace"><?= e($sp['sku']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border"><?= e($sp['category_name']) ?></span>
                                        </td>
                                        <td class="text-end fw-semibold text-dark">
                                            <?= $sp['supplier_price'] ? format_currency($sp['supplier_price']) : '<span class="text-muted small">-</span>' ?>
                                        </td>
                                        <td class="text-center">
                                            <?= stock_badge((int)$sp['stock'], (int)$sp['minimum_stock']) ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= base_url('public/stock/in.php?product_id=' . $sp['id']) ?>" class="btn btn-sm btn-outline-success">
                                                + Restock
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
