<?php
$pageTitle = 'Edit Produk';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Produk tidak valid.');
    header("Location: index.php");
    exit;
}

// Ambil data produk
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header("Location: index.php");
    exit;
}

// Ambil data kategori & semua supplier
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$allSuppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

// Ambil supplier yang sudah terhubung dengan produk ini
$psStmt = $pdo->prepare("SELECT supplier_id, supplier_price FROM product_suppliers WHERE product_id = :pid");
$psStmt->execute(['pid' => $id]);
$connectedSuppliers = [];
while ($row = $psStmt->fetch()) {
    $connectedSuppliers[$row['supplier_id']] = $row['supplier_price'];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $sku          = strtoupper(trim($_POST['sku'] ?? ''));
        $name         = trim($_POST['name'] ?? '');
        $categoryId   = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $priceInput   = $_POST['price'] ?? '';
        $minStockIn   = $_POST['minimum_stock'] ?? '5';
        $supplierData = $_POST['suppliers'] ?? [];
        $pricesData   = $_POST['supplier_prices'] ?? [];

        validate_sku_unique($pdo, $sku, $id, $errors);
        validate_string_required($name, 'Nama Produk', 150, $errors);

        if (!$categoryId) {
            $errors[] = 'Kategori produk wajib dipilih.';
        }

        $validPrice = validate_currency_amount($priceInput, 'Harga Jual', $errors);
        $validMinStock = validate_non_negative_int($minStockIn, 'Stok Minimum', $errors);

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Update data pokok produk
                $updateStmt = $pdo->prepare("
                    UPDATE products
                    SET category_id = :category_id,
                        sku = :sku,
                        name = :name,
                        price = :price,
                        minimum_stock = :minimum_stock
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    'category_id'   => $categoryId,
                    'sku'           => $sku,
                    'name'          => $name,
                    'price'         => $validPrice,
                    'minimum_stock' => $validMinStock,
                    'id'            => $id,
                ]);

                // 2. Sinkronisasi many-to-many product_suppliers
                $delPs = $pdo->prepare("DELETE FROM product_suppliers WHERE product_id = :pid");
                $delPs->execute(['pid' => $id]);

                if (!empty($supplierData)) {
                    $insPs = $pdo->prepare("
                        INSERT INTO product_suppliers (product_id, supplier_id, supplier_price)
                        VALUES (:pid, :sid, :sprice)
                    ");
                    foreach ($supplierData as $supId) {
                        $supId = (int)$supId;
                        $supPrice = !empty($pricesData[$supId]) ? (float)$pricesData[$supId] : null;
                        $insPs->execute([
                            'pid'    => $id,
                            'sid'    => $supId,
                            'sprice' => $supPrice,
                        ]);
                    }
                }

                $pdo->commit();
                set_flash('success', "Data produk '{$name}' berhasil diperbarui.");
                header("Location: view.php?id=" . $id);
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal memperbarui data: ' . $e->getMessage();
            }
        }
    }
} else {
    $sku        = $product['sku'];
    $name       = $product['name'];
    $categoryId = $product['category_id'];
    $price      = $product['price'];
    $minStock   = $product['minimum_stock'];
}

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        &larr; Kembali
    </a>
    <div>
        <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.02em;">Edit Produk: <?= e($product['name']) ?></h3>
        <p class="text-muted small mb-0">Perbarui master data produk, kategori, dan keterhubungan supplier</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
        <div class="fw-bold mb-1">Terdapat kesalahan pada formulir:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="edit.php?id=<?= $id ?>">
    <?= csrf_field() ?>
    <div class="row g-4">
        <!-- Kolom Informasi Produk -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <span class="fw-bold text-dark">Data Pokok Produk</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">SKU Produk <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control" value="<?= e($sku) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Kategori Produk <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e($name) ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Harga Jual (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="1000" min="0" name="price" class="form-control" value="<?= e($price) ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Stok Fisik (Terkunci)</label>
                            <input type="text" class="form-control bg-light" value="<?= e($product['stock']) ?> unit" disabled>
                            <div class="form-text">Ubah melalui transaksi mutasi.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Batas Stok Minimum</label>
                            <input type="number" min="0" name="minimum_stock" class="form-control" value="<?= e($minStock) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Relasi Many-to-Many Supplier -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <span class="fw-bold text-dark">Pemasok Terkait (Many-to-Many)</span>
                </div>
                <div class="card-body p-4">
                    <p class="small text-muted mb-3">
                        Sesuaikan mitra pemasok dan harga kulakan dari supplier tersebut.
                    </p>

                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($allSuppliers as $sup): ?>
                            <?php 
                            $isChecked = array_key_exists($sup['id'], $connectedSuppliers);
                            $supPriceVal = $isChecked ? $connectedSuppliers[$sup['id']] : '';
                            ?>
                            <div class="p-3 border rounded-3 bg-light">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="suppliers[]" value="<?= $sup['id'] ?>" id="sup_<?= $sup['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold text-dark" for="sup_<?= $sup['id'] ?>">
                                        <?= e($sup['name']) ?>
                                    </label>
                                </div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white">Harga Beli Rp</span>
                                    <input type="number" step="500" min="0" name="supplier_prices[<?= $sup['id'] ?>]" value="<?= e($supPriceVal) ?>" class="form-control" placeholder="Opsional (Harga dari Supplier)">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
