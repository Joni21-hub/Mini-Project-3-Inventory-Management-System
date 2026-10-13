<?php
$pageTitle = 'Transaksi Barang Masuk';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/csrf.php';

require_login();

$preselectedProductId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);

$errors = [];
$productId = $preselectedProductId ?: '';
$quantity  = '';
$notes     = '';

// Ambil semua produk untuk pilihan dropdown
$products = $pdo->query("
    SELECT p.id, p.sku, p.name, p.stock, p.minimum_stock, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    ORDER BY p.name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $qtyInput  = $_POST['quantity'] ?? '';
        $notes     = trim($_POST['notes'] ?? '');

        if (!$productId) {
            $errors[] = 'Silakan pilih produk yang akan ditambahkan.';
        }

        $validQty = validate_positive_int($qtyInput, 'Jumlah Masuk (Quantity)', $errors);

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Verifikasi eksistensi produk
                $pStmt = $pdo->prepare("SELECT id, name, sku, stock FROM products WHERE id = :id FOR UPDATE");
                $pStmt->execute(['id' => $productId]);
                $prod = $pStmt->fetch();

                if (!$prod) {
                    throw new Exception('Produk tidak ditemukan di database.');
                }

                // 2. Tambah stok produk
                $updateStmt = $pdo->prepare("
                    UPDATE products 
                    SET stock = stock + :qty 
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    'qty' => $validQty,
                    'id'  => $productId,
                ]);

                // 3. Catat histori mutasi pada stock_movements
                $movStmt = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                    VALUES (:pid, :uid, 'IN', :qty, :notes)
                ");
                $movStmt->execute([
                    'pid'   => $productId,
                    'uid'   => current_user()['id'],
                    'qty'   => $validQty,
                    'notes' => $notes ?: 'Penerimaan barang masuk (Restock)',
                ]);

                $pdo->commit();

                $newStock = $prod['stock'] + $validQty;
                set_flash('success', "Berhasil mencatat Barang Masuk! {$validQty} unit '{$prod['name']}' ditambahkan. Stok saat ini: {$newStock} unit.");
                header("Location: history.php");
                exit;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal memproses transaksi: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= base_url('public/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        &larr; Dashboard
    </a>
    <div>
        <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.02em;">Penerimaan Barang Masuk (Stock IN)</h3>
        <p class="text-muted small mb-0">Catat pasokan barang dari supplier ke gudang dengan verifikasi transaksi atomik</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
        <div class="fw-bold mb-1">Transaksi Tidak Dapat Diproses:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <span class="fw-bold text-dark">Formulir Barang Masuk</span>
                </div>
                <span class="stock-pill stock-pill-success"><span class="stock-dot"></span>Modul Restock</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="in.php" id="stockInForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Pilih Produk Inventaris <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select" required onchange="updateStockInfo()">
                            <option value="">-- Pilih Produk Inventaris --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-min="<?= $p['minimum_stock'] ?>" data-sku="<?= e($p['sku']) ?>" data-img="<?= product_image_url($p['sku'], $p['name']) ?>" <?= $productId == $p['id'] ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Stok saat ini: <?= $p['stock'] ?> unit)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih barang yang diterima dari vendor atau pembelian baru.</div>
                    </div>

                    <!-- Panel Live Preview Produk & Estimasi Stok -->
                    <div id="stockPreviewCard" class="card border p-3 d-none mb-3 bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <img id="previewImg" src="" alt="Produk" class="product-avatar-sm" style="width: 50px; height: 50px;">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">Stok Fisik Saat Ini:</span>
                                    <strong id="currentStockDisplay" class="text-dark">-</strong>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Proyeksi Stok Akhir:</span>
                                    <strong id="projectedStockDisplay" class="text-success">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah Unit Masuk (Quantity) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" name="quantity" id="quantityInput" class="form-control" value="<?= e($quantity) ?>" placeholder="Contoh: 10" required oninput="calculateProjectedStock()">
                        <div class="form-text">Kuantitas bulat positif minimal 1 unit.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Catatan / Surat Jalan / Asal Supplier</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Contoh: Pengadaan PO #8892 dari PT Nusantara Komputindo"><?= e($notes) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <button type="submit" class="btn btn-success px-4 py-2">
                            Simpan Transaksi Masuk
                        </button>
                        <a href="history.php" class="text-decoration-none text-muted small">Lihat Riwayat &rarr;</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel Informasi Aturan Bisnis -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold text-dark">Logika Bisnis & Transaksi</span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-3 small text-muted mb-4">
                    <div class="d-flex gap-2">
                        <span class="stock-dot mt-1" style="background-color: #10b981;"></span>
                        <div><strong>Validasi Syntactic:</strong> <code>quantity &gt; 0</code> dan verifikasi ID produk terdaftar.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="stock-dot mt-1" style="background-color: #10b981;"></span>
                        <div><strong>Inkrementasi Stok:</strong> Nilai stok produk bertambah secara atomik di tabel <code>products</code>.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="stock-dot mt-1" style="background-color: #10b981;"></span>
                        <div><strong>Audit Trail Mutasi:</strong> Rekam riwayat mutasi tipe <code>IN</code> bersama ID petugas aktif.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="stock-dot mt-1" style="background-color: #10b981;"></span>
                        <div><strong>Kepatuhan ACID:</strong> Jika terjadi galat, seluruh kueri dibatalkan secara rollback.</div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 small">
                    <div class="text-muted">Petugas Bertanggung Jawab:</div>
                    <strong class="text-dark"><?= e(current_user()['full_name'] ?: current_user()['username']) ?></strong>
                    <span class="role-pill <?= is_admin() ? 'role-pill-admin' : 'role-pill-staff' ?> ms-2"><?= strtoupper(current_user()['role']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStockInfo() {
    const select = document.getElementById('product_id');
    const selectedOption = select.options[select.selectedIndex];
    const previewCard = document.getElementById('stockPreviewCard');
    const currentStockDisplay = document.getElementById('currentStockDisplay');
    const previewImg = document.getElementById('previewImg');

    if (select.value) {
        const stock = parseInt(selectedOption.getAttribute('data-stock') || '0', 10);
        const imgSrc = selectedOption.getAttribute('data-img') || '';
        currentStockDisplay.textContent = stock + ' unit';
        previewImg.src = imgSrc;
        previewCard.classList.remove('d-none');
        calculateProjectedStock();
    } else {
        previewCard.classList.add('d-none');
    }
}

function calculateProjectedStock() {
    const select = document.getElementById('product_id');
    const selectedOption = select.options[select.selectedIndex];
    const qtyInput = document.getElementById('quantityInput');
    const projectedStockDisplay = document.getElementById('projectedStockDisplay');

    if (select.value) {
        const stock = parseInt(selectedOption.getAttribute('data-stock') || '0', 10);
        const qty = parseInt(qtyInput.value || '0', 10);
        const total = stock + (qty > 0 ? qty : 0);
        projectedStockDisplay.textContent = total + ' unit (+' + (qty > 0 ? qty : 0) + ')';
    }
}

document.addEventListener('DOMContentLoaded', updateStockInfo);
</script>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
