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

        $validQty = validate_positive_int($qtyInput, 'Jumlah Keluar (Quantity)', $errors);

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. SELECT stock ... FOR UPDATE (Pessimistic Locking mencegah race condition)
                $pStmt = $pdo->prepare("SELECT id, name, sku, stock FROM products WHERE id = :id FOR UPDATE");
                $pStmt->execute(['id' => $productId]);
                $prod = $pStmt->fetch();

                if (!$prod) {
                    throw new Exception('Produk tidak ditemukan di database.');
                }

                $currentStock = (int)$prod['stock'];

                // 2. Validasi Semantik: Barang keluar tidak boleh melebihi stok tersedia!
                if ($validQty > $currentStock) {
                    throw new Exception("Stok tidak mencukupi! Permintaan pengeluaran: {$validQty} unit, namun stok tersedia hanya {$currentStock} unit.");
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

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= base_url('public/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        &larr; Dashboard
    </a>
    <div>
        <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.02em;">Pengeluaran Barang (Stock OUT)</h3>
        <p class="text-muted small mb-0">Pencatatan distribusi barang keluar dengan validasi ketersediaan stok ketat</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
        <div class="fw-bold mb-1">Transaksi Gagal / Ditolak:</div>
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
                    <span class="fw-bold text-dark">Formulir Barang Keluar</span>
                </div>
                <span class="stock-pill stock-pill-danger"><span class="stock-dot"></span>Modul Distribusi</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="out.php" id="stockOutForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Pilih Produk Yang Dikeluarkan <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select" required onchange="updateStockOutInfo()">
                            <option value="">-- Pilih Produk Inventaris --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-min="<?= $p['minimum_stock'] ?>" data-sku="<?= e($p['sku']) ?>" data-img="<?= product_image_url($p['sku'], $p['name']) ?>" <?= $productId == $p['id'] ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Sisa stok: <?= $p['stock'] ?> unit)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Perhatikan batas sisa stok sebelum mengajukan pengeluaran.</div>
                    </div>

                    <!-- Panel Live Status Stok Fisik -->
                    <div id="stockOutPreviewCard" class="card border p-3 d-none mb-3 bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <img id="previewOutImg" src="" alt="Produk" class="product-avatar-sm" style="width: 50px; height: 50px;">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">Sisa Stok Fisik Gudang:</span>
                                    <strong id="currentOutStockDisplay" class="text-dark">-</strong>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Proyeksi Setelah Transaksi:</span>
                                    <strong id="projectedOutStockDisplay">-</strong>
                                </div>
                                <div id="stockWarningAlert" class="mt-2 text-danger small fw-semibold d-none">
                                    Peringatan: Jumlah keluar melebihi stok yang tersedia di gudang!
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah Unit Keluar (Quantity) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" name="quantity" id="outQuantityInput" class="form-control" value="<?= e($quantity) ?>" placeholder="Contoh: 5" required oninput="calculateProjectedOutStock()">
                        <div class="form-text">Maksimal pengeluaran dibatasi sejumlah stok yang ada.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Keterangan / Divisi Penerima / No. DO</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Contoh: Distribusi tim operasional lantai 2 / Penjualan DO-401"><?= e($notes) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <button type="submit" id="btnSubmitOut" class="btn btn-danger px-4 py-2">
                            Simpan Transaksi Keluar
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
                <span class="fw-bold text-dark">Logika Bisnis & Pengamanan Stok</span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-3 small text-muted mb-4">
                    <div class="d-flex gap-2">
                        <span class="stock-dot mt-1" style="background-color: #ef4444;"></span>
                        <div><strong>Kunci Baris (Row Lock):</strong> Kueri <code>SELECT ... FOR UPDATE</code> mengunci baris produk guna mencegah inkonsistensi.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="stock-dot mt-1" style="background-color: #ef4444;"></span>
                        <div><strong>Validasi Semantik:</strong> <code>quantity &le; stock</code>. Menjamin stok gudang tidak pernah menjadi negatif.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="stock-dot mt-1" style="background-color: #ef4444;"></span>
                        <div><strong>Audit Trail Terintegrasi:</strong> Riwayat mutasi tipe <code>OUT</code> dicatat lengkap dengan timestamp transaksi.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="stock-dot mt-1" style="background-color: #ef4444;"></span>
                        <div><strong>Transaksi Database:</strong> Jaminan atomik (Semua tersimpan atau tidak sama sekali).</div>
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
function updateStockOutInfo() {
    const select = document.getElementById('product_id');
    const selectedOption = select.options[select.selectedIndex];
    const previewCard = document.getElementById('stockOutPreviewCard');
    const currentStockDisplay = document.getElementById('currentOutStockDisplay');
    const previewImg = document.getElementById('previewOutImg');
    const qtyInput = document.getElementById('outQuantityInput');

    if (select.value) {
        const stock = parseInt(selectedOption.getAttribute('data-stock') || '0', 10);
        const imgSrc = selectedOption.getAttribute('data-img') || '';
        currentStockDisplay.textContent = stock + ' unit';
        previewImg.src = imgSrc;
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
            projectedDisplay.className = 'text-danger fw-bold';
            projectedDisplay.textContent = diff + ' unit (Defisit / Stok Tidak Cukup)';
            warningAlert.classList.remove('d-none');
            submitBtn.disabled = true;
        } else {
            projectedDisplay.className = 'text-success fw-bold';
            projectedDisplay.textContent = diff + ' unit tersisa';
            warningAlert.classList.add('d-none');
            submitBtn.disabled = false;
        }
    }
}

document.addEventListener('DOMContentLoaded', updateStockOutInfo);
</script>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
