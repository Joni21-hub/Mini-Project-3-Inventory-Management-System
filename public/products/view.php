<?php
$pageTitle = 'Detail Produk & Persediaan';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Produk tidak valid.');
    header("Location: index.php");
    exit;
}

// 1. Ambil detail produk dengan JOIN kategori
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.description AS category_desc
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.id = :id
");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan di database.');
    header("Location: index.php");
    exit;
}

// 2. Ambil Supplier yang terhubung dari junction table
$supStmt = $pdo->prepare("
    SELECT s.*, ps.supplier_price
    FROM product_suppliers ps
    JOIN suppliers s ON s.id = ps.supplier_id
    WHERE ps.product_id = :pid
    ORDER BY s.name ASC
");
$supStmt->execute(['pid' => $id]);
$productSuppliers = $supStmt->fetchAll();

// 3. Ambil Histori Mutasi Barang untuk produk ini
$movStmt = $pdo->prepare("
    SELECT sm.*, u.username, up.full_name
    FROM stock_movements sm
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE sm.product_id = :pid
    ORDER BY sm.created_at DESC
");
$movStmt->execute(['pid' => $id]);
$movements = $movStmt->fetchAll();

// Hitung total masuk & total keluar
$inOutStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END), 0) AS total_out
    FROM stock_movements
    WHERE product_id = :pid
");
$inOutStmt->execute(['pid' => $id]);
$totals = $inOutStmt->fetch();

require_once __DIR__ . '/../../views/header.php';
?>

<!-- Header Navigasi Atas -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            &larr; Kembali ke Katalog
        </a>
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-secondary border font-monospace"><?= e($product['sku']) ?></span>
                <span class="badge bg-light text-dark border"><?= e($product['category_name']) ?></span>
            </div>
            <h3 class="fw-bold text-dark mb-0 mt-1" style="letter-spacing: -0.02em;"><?= e($product['name']) ?></h3>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="<?= base_url('public/stock/in.php?product_id=' . $product['id']) ?>" class="btn btn-success btn-sm">
            Barang Masuk
        </a>
        <a href="<?= base_url('public/stock/out.php?product_id=' . $product['id']) ?>" class="btn btn-danger btn-sm">
            Barang Keluar
        </a>
        <?php if (is_admin()): ?>
        <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-outline-primary btn-sm">
            Edit Data
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Product Showcase & Key Metrics -->
<div class="row g-4 mb-4">
    <!-- Visual Produk & Status -->
    <div class="col-lg-5">
        <div class="card h-100 overflow-hidden">
            <div style="background-color: #f1f5f9; position: relative; padding-top: 65%;">
                <img src="<?= product_image_url($product['sku'], $product['name']) ?>" alt="<?= e($product['name']) ?>" style="position: absolute; top:0; left:0; width:100%; height:100%; object-fit: cover;">
                <div style="position: absolute; top: 1rem; left: 1rem;">
                    <?= stock_badge((int)$product['stock'], (int)$product['minimum_stock']) ?>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="p-3 bg-light rounded-3 mb-3 text-center">
                    <small class="text-muted text-uppercase fw-semibold d-block mb-1">Stok Fisik Tersedia</small>
                    <div class="display-6 fw-bold text-dark my-1"><?= number_format($product['stock']) ?> <span class="fs-6 fw-normal text-muted">unit</span></div>
                    <small class="text-muted">Batas Minimum: <strong><?= e($product['minimum_stock']) ?> unit</strong></small>
                </div>

                <div class="d-flex flex-column gap-2 small">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Harga Jual Resmi</span>
                        <strong class="text-dark fs-6"><?= format_currency($product['price']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Total Valuasi Nilai Stok</span>
                        <strong class="text-primary fs-6"><?= format_currency($product['stock'] * $product['price']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Akumulasi Pasokan Masuk</span>
                        <span class="fw-bold text-success">+<?= number_format($totals['total_in']) ?> unit</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Akumulasi Distribusi Keluar</span>
                        <span class="fw-bold text-danger">-<?= number_format($totals['total_out']) ?> unit</span>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Terdaftar Pada</span>
                        <span class="text-secondary"><?= format_datetime($product['created_at']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mitra Supplier Terdaftar -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="fw-bold text-dark">Mitra Supplier Terdaftar</div>
                    <small class="text-muted">Jaringan pemasok resmi produk ini</small>
                </div>
                <span class="badge bg-light text-secondary border"><?= count($productSuppliers) ?> Mitra Terkait</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($productSuppliers)): ?>
                    <div class="p-4 text-center text-muted">
                        Produk ini belum dikaitkan dengan supplier manapun.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nama Supplier</th>
                                    <th>Kontak & Telp</th>
                                    <th>Email</th>
                                    <th class="text-end">Harga Beli Kulakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productSuppliers as $sup): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($sup['name']) ?></div>
                                            <small class="text-muted">PIC: <?= e($sup['contact_name'] ?: '-') ?></small>
                                        </td>
                                        <td><?= e($sup['phone'] ?: '-') ?></td>
                                        <td>
                                            <?php if ($sup['email']): ?>
                                                <a href="mailto:<?= e($sup['email']) ?>" class="text-decoration-none small text-secondary"><?= e($sup['email']) ?></a>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold text-dark">
                                            <?= $sup['supplier_price'] ? format_currency($sup['supplier_price']) : '<span class="text-muted small">-</span>' ?>
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

<!-- Riwayat Mutasi Transaksi Produk Ini -->
<div class="card mb-4">
    <div class="card-header">
        <div>
            <div class="fw-bold text-dark">Riwayat Pergerakan Stok Produk</div>
            <small class="text-muted">Audit trail seluruh transaksi keluar dan masuk untuk item ini</small>
        </div>
        <span class="badge bg-light text-secondary border"><?= count($movements) ?> Catatan</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($movements)): ?>
            <div class="p-4 text-center text-muted">
                Belum ada transaksi keluar-masuk yang dicatat untuk produk ini.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Waktu Transaksi</th>
                            <th class="text-center">Jenis Mutasi</th>
                            <th class="text-center">Jumlah Unit</th>
                            <th>Keterangan / Catatan</th>
                            <th>Petugas Input</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small text-dark"><?= format_datetime($m['created_at']) ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['type'] === 'IN'): ?>
                                        <span class="movement-pill-in">MASUK</span>
                                    <?php else: ?>
                                        <span class="movement-pill-out">KELUAR</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold <?= $m['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                    <?= $m['type'] === 'IN' ? '+' : '-' ?><?= number_format($m['quantity']) ?>
                                </td>
                                <td><?= e($m['notes'] ?: '-') ?></td>
                                <td>
                                    <small class="text-dark fw-medium"><?= e($m['full_name'] ?: $m['username']) ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
