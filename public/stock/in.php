<?php
$pageTitle = 'Transaksi Barang Masuk';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';
require_once __DIR__ . '/../../helpers/csrf.php';

// Baik admin maupun staff dapat mencatat transaksi (Slide 24)
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

        // Validasi syntactic: jumlah harus berupa integer positif (Slide 21)
        $validQty = validate_positive_int($qtyInput, 'Jumlah Masuk (Quantity)', $errors);

        if (empty($errors)) {
            try {
                // DATABASE TRANSACTION (Slide 25 & 27)
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

                // Komit transaksi
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

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <a href="<?= base_url('public/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
            <div>
                <h3 class="fw-bold text-dark mb-0">Transaksi Barang Masuk (Stock IN)</h3>
                <small class="text-muted">Proses penambahan kuantitas stok produk ke gudang secara aman dengan transaksi atomik</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i> Transaksi Tidak Dapat Diproses:</h6>
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
                <h5 class="card-title mb-0 fw-bold text-success">
                    <i class="bi bi-arrow-down-circle text-success me-2"></i>Formulir Barang Masuk
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="in.php" id="stockInForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Pilih Produk <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select" required onchange="updateStockInfo()">
                            <option value="">-- Pilih Produk Inventaris --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-min="<?= $p['minimum_stock'] ?>" data-sku="<?= e($p['sku']) ?>" <?= $productId == $p['id'] ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Stok saat ini: <?= $p['stock'] ?> unit)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih barang yang diterima dari supplier atau pengadaan baru.</div>
                    </div>

                    <!-- Panel Interaktif Live Stock Preview -->
                    <div id="stockPreviewCard" class="alert alert-light border d-none mb-3 py-2 px-3 small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Sisa Stok Fisik Saat Ini:</span>
                            <span id="currentStockDisplay" class="fw-bold text-dark">-</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Estimasi Stok Setelah Transaksi:</span>
                            <span id="projectedStockDisplay" class="fw-bold text-success">-</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Jumlah Unit Masuk (Quantity) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" name="quantity" id="quantityInput" class="form-control" value="<?= e($quantity) ?>" placeholder="Masukkan jumlah unit (min. 1)" required oninput="calculateProjectedStock()">
                        <div class="form-text">Angka bulat positif lebih dari 0.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-secondary small">Catatan / Referensi Surat Jalan / Supplier</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Contoh: Penerimaan PO #8892 dari PT Nusantara Komputindo"><?= e($notes) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="submit" class="btn btn-success px-4 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Transaksi Barang Masuk
                        </button>
                        <a href="history.php" class="text-decoration-none text-muted small">Lihat Riwayat Mutasi &raquo;</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel Informasi Aturan Bisnis -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-start border-success border-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shield-check text-success me-2"></i>Business Logic: Barang Masuk (Slide 25)</h5>
                <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span>Validasi input: <code>product_id</code> valid dan <code>quantity &gt; 0</code>.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span>Menambah kolom stok pada tabel <code>products</code>.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span>Merekam audit trail transaksi mutasi (type: <code>IN</code>) pada tabel <code>stock_movements</code>.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span>Menyimpan ID user petugas yang sedang aktif login.</span>
                    </li>
                    <li class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                        <span>Menerapkan <strong>Database Transaction</strong>: Jika satu query gagal, seluruh proses dibatalkan (*Rollback*).</span>
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
function updateStockInfo() {
    const select = document.getElementById('product_id');
    const selectedOption = select.options[select.selectedIndex];
    const previewCard = document.getElementById('stockPreviewCard');
    const currentStockDisplay = document.getElementById('currentStockDisplay');

    if (select.value) {
        const stock = parseInt(selectedOption.getAttribute('data-stock') || '0', 10);
        currentStockDisplay.textContent = stock + ' unit';
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
        projectedStockDisplay.textContent = total + ' unit (+' + qty + ')';
    }
}

document.addEventListener('DOMContentLoaded', updateStockInfo);
</script>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
