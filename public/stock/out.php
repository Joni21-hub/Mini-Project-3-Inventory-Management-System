<?php
$pageTitle = 'Transaksi Barang Keluar';
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
            $errors[] = 'Silakan pilih produk yang akan dikeluarkan.';
        }

        // Validasi syntactic: jumlah harus berupa integer positif (Slide 21)
        $validQty = validate_positive_int($qtyInput, 'Jumlah Keluar (Quantity)', $errors);

        if (empty($errors)) {
            try {
                // DATABASE TRANSACTION (Slide 26 & 27)
                $pdo->beginTransaction();

                // 1. SELECT stock ... FOR UPDATE (Pessimistic Locking mencegah race condition)
                $pStmt = $pdo->prepare("SELECT id, name, sku, stock FROM products WHERE id = :id FOR UPDATE");
                $pStmt->execute(['id' => $productId]);
                $prod = $pStmt->fetch();

                if (!$prod) {
                    throw new Exception('Produk tidak ditemukan di database.');
                }

                $currentStock = (int)$prod['stock'];

                // 2. Validasi Semantic & Business Rule: Barang keluar tidak boleh melebihi stok tersedia!
                if ($validQty > $currentStock) {
                    throw new Exception("Stok tidak mencukupi! Permintaan pengeluaran: {$validQty} unit, namun stok yang tersedia saat ini hanya {$currentStock} unit.");
                }

                // 3. UPDATE products SET stock = stock - quantity
                $updateStmt = $pdo->prepare("
                    UPDATE products 
                    SET stock = stock - :qty 
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    'qty' => $validQty,
                    'id'  => $productId,
                ]);

                // 4. INSERT stock_movements (type = 'OUT')
                $movStmt = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                    VALUES (:pid, :uid, 'OUT', :qty, :notes)
                ");
                $movStmt->execute([
                    'pid'   => $productId,
                    'uid'   => current_user()['id'],
                    'qty'   => $validQty,
                    'notes' => $notes ?: 'Pengeluaran barang gudang (Stock OUT)',
                ]);

                // COMMIT TRANSACTION
                $pdo->commit();

                $remainingStock = $currentStock - $validQty;
                set_flash('success', "Berhasil mencatat Barang Keluar! {$validQty} unit '{$prod['name']}' telah dikeluarkan. Sisa stok gudang: {$remainingStock} unit.");
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

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <a href="<?= base_url('public/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
            <div>
                <h3 class="fw-bold text-dark mb-0">Transaksi Barang Keluar (Stock OUT)</h3>
                <small class="text-muted">Pencatatan pengeluaran barang dengan validasi ketersediaan stok ketat (Pessimistic Lock FOR UPDATE)</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i> Transaksi Gagal / Ditolak:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold text-danger">
                    <i class="bi bi-arrow-up-circle text-danger me-2"></i>Formulir Barang Keluar
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="out.php" id="stockOutForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Pilih Produk Yang Dikeluarkan <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select" required onchange="updateStockOutInfo()">
                            <option value="">-- Pilih Produk Inventaris --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-min="<?= $p['minimum_stock'] ?>" data-sku="<?= e($p['sku']) ?>" <?= $productId == $p['id'] ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Sisa stok: <?= $p['stock'] ?> unit)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Perhatikan batas sisa stok sebelum mengajukan pengeluaran.</div>
                    </div>

                    <!-- Panel Interaktif Live Stock Status -->
                    <div id="stockOutPreviewCard" class="alert alert-light border d-none mb-3 py-2 px-3 small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Sisa Stok Fisik Gudang:</span>
                            <span id="currentOutStockDisplay" class="fw-bold text-dark">-</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Sisa Stok Setelah Transaksi:</span>
                            <span id="projectedOutStockDisplay" class="fw-bold">-</span>
                        </div>
                        <div id="stockWarningAlert" class="mt-2 text-danger fw-semibold d-none">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Peringatan: Jumlah keluar melebihi stok yang tersedia!
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Jumlah Unit Keluar (Quantity) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" name="quantity" id="outQuantityInput" class="form-control" value="<?= e($quantity) ?>" placeholder="Masukkan jumlah unit (min. 1)" required oninput="calculateProjectedOutStock()">
                        <div class="form-text">Maksimal pengeluaran dibatasi sejumlah stok yang ada.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-secondary small">Keterangan / Keperluan / Divisi Penerima</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Contoh: Distribusi untuk unit operasional lantai 2 / Penjualan DO-401"><?= e($notes) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="submit" id="btnSubmitOut" class="btn btn-danger px-4 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Transaksi Barang Keluar
                        </button>
                        <a href="history.php" class="text-decoration-none text-muted small">Lihat Riwayat Mutasi &raquo;</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel Informasi Aturan Bisnis -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-start border-danger border-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shield-lock text-danger me-2"></i>Business Logic: Barang Keluar (Slide 26)</h5>
                <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                        <span><strong>BEGIN TRANSACTION</strong>: Mengawali transaksi database atomik.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                        <span><code>SELECT stock ... FOR UPDATE</code>: Mengunci baris produk guna mencegah race-condition.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                        <span><strong>Validasi Semantik:</strong> <code>quantity &le; stock</code>. Menjamin stok <em>tidak pernah negatif</em>.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                        <span><code>UPDATE products SET stock = stock - quantity</code>.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                        <span><code>INSERT stock_movements (type = 'OUT')</code> mencatat waktu & petugas.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-danger me-2 mt-1"></i>
                        <span><strong>COMMIT / ROLLBACK</strong>: Konsistensi data 100% terjaga jika terjadi galat.</span>
                    </li>
                </ul>

                <div class="p-3 bg-light rounded-3 small">
                    <strong>Petugas Aktif:</strong><br>
                    <?= e(current_user()['full_name'] ?: current_user()['username']) ?> (Role: <?= strtoupper(current_user()['role']) ?>)
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStockOutInfo() {
    const select = document.getElementById('product_id');
    const selectedOption = select.options[select.selectedIndex];
    const previewCard = document.getElementById('stockOutPreviewCard');
    const currentStockDisplay = document.getElementById('currentOutStockDisplay');
    const qtyInput = document.getElementById('outQuantityInput');

    if (select.value) {
        const stock = parseInt(selectedOption.getAttribute('data-stock') || '0', 10);
        currentStockDisplay.textContent = stock + ' unit';
        qtyInput.max = stock > 0 ? stock : 0;
        previewCard.classList.remove('d-none');
        calculateProjectedOutStock();
    } else {
        previewCard.classList.add('d-none');
    }
}

function calculateProjectedOutStock() {
    const select = document.getElementById('product_id');
    const selectedOption = select.options[select.selectedIndex];
    const qtyInput = document.getElementById('outQuantityInput');
    const projectedDisplay = document.getElementById('projectedOutStockDisplay');
    const warningAlert = document.getElementById('stockWarningAlert');
    const submitBtn = document.getElementById('btnSubmitOut');

    if (select.value) {
        const stock = parseInt(selectedOption.getAttribute('data-stock') || '0', 10);
        const qty = parseInt(qtyInput.value || '0', 10);
        const diff = stock - qty;

        if (qty > stock) {
            projectedDisplay.className = 'fw-bold text-danger';
            projectedDisplay.textContent = diff + ' unit (Defisit/Tidak Cukup)';
            warningAlert.classList.remove('d-none');
            submitBtn.disabled = true;
        } else {
            projectedDisplay.className = 'fw-bold text-success';
            projectedDisplay.textContent = diff + ' unit sisa';
            warningAlert.classList.add('d-none');
            submitBtn.disabled = false;
        }
    }
}

document.addEventListener('DOMContentLoaded', updateStockOutInfo);
</script>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
