<?php
$pageTitle = 'Tambah Produk Baru';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$errors = [];
$sku          = '';
$name         = '';
$categoryId   = '';
$price        = '';
$initialStock = 0;
$minStock     = 5;
$selectedSuppliers = []; // [supplier_id => supplier_price]

// Ambil daftar kategori & supplier
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers  = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $sku          = strtoupper(trim($_POST['sku'] ?? ''));
    $name         = trim($_POST['name'] ?? '');
    $categoryId   = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $priceInput   = $_POST['price'] ?? '';
    $stockInput   = $_POST['initial_stock'] ?? '0';
    $minStockIn   = $_POST['minimum_stock'] ?? '5';
    $supplierData = $_POST['suppliers'] ?? []; // array of supplier IDs
    $pricesData   = $_POST['supplier_prices'] ?? []; // array of prices keyed by supplier ID

    // Validasi
    validate_sku_unique($pdo, $sku, null, $errors);
    validate_string_required($name, 'Nama Produk', 150, $errors);
    
    if (!$categoryId) {
        $errors[] = 'Kategori produk wajib dipilih.';
    }

    $validPrice = validate_currency_amount($priceInput, 'Harga Jual', $errors);
    $validStock = validate_non_negative_int($stockInput, 'Stok Awal', $errors);
    $validMinStock = validate_non_negative_int($minStockIn, 'Stok Minimum', $errors);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Simpan Data Produk
            $stmt = $pdo->prepare("
                INSERT INTO products (category_id, sku, name, price, stock, minimum_stock)
                VALUES (:category_id, :sku, :name, :price, :stock, :minimum_stock)
            ");
            $stmt->execute([
                'category_id'   => $categoryId,
                'sku'           => $sku,
                'name'          => $name,
                'price'         => $validPrice,
                'stock'         => $validStock,
                'minimum_stock' => $validMinStock,
            ]);
            $productId = (int)$pdo->lastInsertId();

            // 2. Simpan Relasi Many-to-Many ke Tabel Junction `product_suppliers` (Slide 8)
            if (!empty($supplierData)) {
                $psStmt = $pdo->prepare("
                    INSERT INTO product_suppliers (product_id, supplier_id, supplier_price)
                    VALUES (:product_id, :supplier_id, :supplier_price)
                ");
                foreach ($supplierData as $supId) {
                    $supId = (int)$supId;
                    $supPrice = !empty($pricesData[$supId]) ? (float)$pricesData[$supId] : null;
                    $psStmt->execute([
                        'product_id'     => $productId,
                        'supplier_id'    => $supId,
                        'supplier_price' => $supPrice,
                    ]);
                }
            }

            // 3. Jika ada stok awal, catat ke tabel `stock_movements` (Slide 25)
            if ($validStock > 0) {
                $smStmt = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                    VALUES (:pid, :uid, 'IN', :qty, :notes)
                ");
                $smStmt->execute([
                    'pid'   => $productId,
                    'uid'   => current_user()['id'],
                    'qty'   => $validStock,
                    'notes' => 'Saldo stok awal saat produk baru didaftarkan',
                ]);
            }

            $pdo->commit();
            set_flash('success', "Produk baru '{$name}' (SKU: {$sku}) berhasil ditambahkan!");
            header("Location: index.php");
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan produk: ' . $e->getMessage();
        }
    }
}
}

require_once __DIR__ . '/../../views/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <div>
                <h3 class="fw-bold text-dark mb-0">Tambah Produk Baru</h3>
                <small class="text-muted">Isi formulir lengkap master produk dan relasi multi-supplier</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i> Terdapat kesalahan pada formulir:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="create.php">
    <?= csrf_field() ?>
    <div class="row g-4">
        <!-- Informasi Produk -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Informasi Pokok Produk</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">SKU Produk <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control" value="<?= e($sku) ?>" placeholder="Contoh: PRD-ACC-011" required>
                            <div class="form-text">Kode unik inventaris (Unique Constraint).</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Kategori Produk <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Relasi One-to-Many dengan tabel kategori.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small">Nama Lengkap Produk <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e($name) ?>" placeholder="Contoh: Wireless Mouse Logitech M330 Silent" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary small">Harga Jual (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="1000" min="0" name="price" class="form-control" value="<?= e($price) ?>" placeholder="0" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary small">Stok Awal</label>
                            <input type="number" min="0" name="initial_stock" class="form-control" value="<?= e($initialStock) ?>">
                            <div class="form-text">Otomatis dicatat ke histori mutasi.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary small">Batas Stok Minimum</label>
                            <input type="number" min="0" name="minimum_stock" class="form-control" value="<?= e($minStock) ?>">
                            <div class="form-text">Untuk peringatan low stock di dashboard.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Relasi Many-to-Many Supplier -->
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-truck text-primary me-2"></i>Pemasok Terkait (Many-to-Many)</h5>
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted mb-3">
                        Pilih supplier yang memasok produk ini beserta estimasi harga beli/kulakan (disimpan di junction table <code>product_suppliers</code>).
                    </p>

                    <?php if (empty($suppliers)): ?>
                        <div class="alert alert-warning small">Belum ada data supplier. Tambahkan supplier di menu Supplier terlebih dahulu.</div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($suppliers as $sup): ?>
                                <div class="p-3 border rounded-3 bg-light-subtle">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="suppliers[]" value="<?= $sup['id'] ?>" id="sup_<?= $sup['id'] ?>">
                                        <label class="form-check-label fw-semibold text-dark" for="sup_<?= $sup['id'] ?>">
                                            <?= e($sup['name']) ?>
                                        </label>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white">Harga Beli Rp</span>
                                        <input type="number" step="500" min="0" name="supplier_prices[<?= $sup['id'] ?>]" class="form-control" placeholder="Opsional (Harga dari Supplier)">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white p-3 text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i> Simpan Produk
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
