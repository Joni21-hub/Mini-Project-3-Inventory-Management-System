<?php
$pageTitle = 'Detail Produk';
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

// 1. Ambil detail produk dengan JOIN kategori (Slide 12)
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.description AS category_desc
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.id = :id
");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// 2. Ambil Supplier yang terhubung dari junction table (Slide 8 Many-to-Many)
$supStmt = $pdo->prepare("
    SELECT s.*, ps.supplier_price
    FROM product_suppliers ps
    JOIN suppliers s ON s.id = ps.supplier_id
    WHERE ps.product_id = :pid
    ORDER BY s.name ASC
");
$supStmt->execute(['pid' => $id]);
$productSuppliers = $supStmt->fetchAll();

// 3. Ambil Histori Mutasi Barang untuk produk ini (Slide 28)
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

// Hitung total masuk & total keluar untuk produk ini
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

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <div>
            <h3 class="fw-bold text-dark mb-0"><?= e($product['name']) ?></h3>
            <span class="badge bg-secondary-subtle text-secondary border me-2">SKU: <?= e($product['sku']) ?></span>
            <span class="badge bg-primary-subtle text-primary border"><?= e($product['category_name']) ?></span>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="<?= base_url('public/stock/in.php?product_id=' . $product['id']) ?>" class="btn btn-success btn-sm">
            <i class="bi bi-arrow-down-circle me-1"></i> Barang Masuk
        </a>
        <a href="<?= base_url('public/stock/out.php?product_id=' . $product['id']) ?>" class="btn btn-danger btn-sm">
            <i class="bi bi-arrow-up-circle me-1"></i> Barang Keluar
        </a>
        <?php if (is_admin()): ?>
        <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit Produk
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Informasi Utama Produk -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-info-circle text-primary me-2"></i>Status Persediaan</h5>
            </div>
            <div class="card-body p-4">
                <div class="text-center p-3 bg-light rounded-3 mb-4">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Stok Tersedia Saat Ini</div>
                    <div class="display-5 fw-bold text-dark my-1"><?= number_format($product['stock']) ?></div>
                    <div><?= stock_badge((int)$product['stock'], (int)$product['minimum_stock']) ?></div>
                    <div class="small text-muted mt-2">Batas Minimum: <?= e($product['minimum_stock']) ?> unit</div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Harga Jual Resmi</span>
                        <strong class="text-primary fs-6"><?= format_currency($product['price']) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Total Nilai Stok Produk</span>
                        <strong><?= format_currency($product['stock'] * $product['price']) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Akumulasi Masuk (IN)</span>
                        <span class="badge bg-success-subtle text-success">+<?= number_format($totals['total_in']) ?> unit</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Akumulasi Keluar (OUT)</span>
                        <span class="badge bg-danger-subtle text-danger">-<?= number_format($totals['total_out']) ?> unit</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Didaftarkan Pada</span>
                        <span><?= format_datetime($product['created_at']) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Mitra Supplier (Many-to-Many Junction Table) -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-truck text-primary me-2"></i>Pemasok Terdaftar (Relasi Many-to-Many)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($productSuppliers)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-truck fs-1 d-block mb-2 text-secondary"></i>
                        Produk ini belum dikaitkan dengan supplier manapun.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nama Supplier</th>
                                    <th>Kontak / Telp</th>
                                    <th>Email</th>
                                    <th class="text-end">Harga Beli Supplier</th>
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
                                                <a href="mailto:<?= e($sup['email']) ?>" class="text-decoration-none small"><?= e($sup['email']) ?></a>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold text-dark">
                                            <?= $sup['supplier_price'] ? format_currency($sup['supplier_price']) : '<span class="text-muted small">Tidak diset</span>' ?>
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
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Pergerakan Stok Produk (Stock Movements)</h5>
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
                                    <div class="fw-semibold small"><?= format_datetime($m['created_at']) ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['type'] === 'IN'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-arrow-down-circle me-1"></i>MASUK (IN)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-arrow-up-circle me-1"></i>KELUAR (OUT)
                                        </span>
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
