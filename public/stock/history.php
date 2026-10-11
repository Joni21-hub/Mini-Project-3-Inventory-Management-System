<?php
$pageTitle = 'Riwayat Mutasi Stok';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

// 1. Ambil Parameter Filter
$typeFilter = trim($_GET['type'] ?? '');
$productId  = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
$search     = trim($_GET['search'] ?? '');
$startDate  = trim($_GET['start_date'] ?? '');
$endDate    = trim($_GET['end_date'] ?? '');

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// 2. Bangun Klausa WHERE dengan Prepared Statement
$whereClauses = ['1=1'];
$params = [];

if (in_array($typeFilter, ['IN', 'OUT'], true)) {
    $whereClauses[] = "sm.type = :type";
    $params['type'] = $typeFilter;
}

if ($productId) {
    $whereClauses[] = "sm.product_id = :product_id";
    $params['product_id'] = $productId;
}

if ($search !== '') {
    $whereClauses[] = "(p.name LIKE :search_name OR p.sku LIKE :search_sku OR sm.notes LIKE :search_notes)";
    $params['search_name']  = "%{$search}%";
    $params['search_sku']   = "%{$search}%";
    $params['search_notes'] = "%{$search}%";
}

if ($startDate !== '') {
    $whereClauses[] = "DATE(sm.created_at) >= :start_date";
    $params['start_date'] = $startDate;
}

if ($endDate !== '') {
    $whereClauses[] = "DATE(sm.created_at) <= :end_date";
    $params['end_date'] = $endDate;
}

$whereSql = implode(' AND ', $whereClauses);

// 3. Hitung Total Data
$countSql = "
    SELECT COUNT(sm.id)
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    WHERE {$whereSql}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

// 4. Ambil Data Mutasi
$dataSql = "
    SELECT sm.*,
           p.sku, p.name AS product_name, c.name AS category_name,
           u.username, u.role, up.full_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN categories c ON c.id = p.category_id
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE {$whereSql}
    ORDER BY sm.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$movements = $stmt->fetchAll();

// Ambil daftar produk untuk filter
$productsList = $pdo->query("SELECT id, name, sku FROM products ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Audit Mutasi Stok</h3>
        <p class="text-muted small mb-0">Log seluruh transaksi barang masuk (IN) dan barang keluar (OUT) secara kronologis.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="in.php" class="btn btn-success btn-sm">
            <i class="bi bi-arrow-down-circle me-1"></i> Input Barang Masuk
        </a>
        <a href="out.php" class="btn btn-danger btn-sm">
            <i class="bi bi-arrow-up-circle me-1"></i> Input Barang Keluar
        </a>
    </div>
</div>

<!-- Form Filter & Pencarian -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="history.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary mb-1">Cari Produk / Catatan</label>
                <input type="text" name="search" class="form-control" placeholder="Kata kunci..." value="<?= e($search) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-secondary mb-1">Tipe Mutasi</label>
                <select name="type" class="form-select">
                    <option value="">Semua (IN & OUT)</option>
                    <option value="IN" <?= $typeFilter === 'IN' ? 'selected' : '' ?>>Masuk (IN)</option>
                    <option value="OUT" <?= $typeFilter === 'OUT' ? 'selected' : '' ?>>Keluar (OUT)</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary mb-1">Filter Produk</label>
                <select name="product_id" class="form-select">
                    <option value="">Semua Produk</option>
                    <?php foreach ($productsList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $productId == $p['id'] ? 'selected' : '' ?>>
                            [<?= e($p['sku']) ?>] <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-secondary mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="history.php" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Log Mutasi -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-muted small">Menampilkan <?= count($movements) ?> dari <?= $totalRows ?> total catatan mutasi</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 170px;">Waktu & Tanggal</th>
                    <th class="text-center" style="width: 110px;">Jenis</th>
                    <th>Nama Produk & SKU</th>
                    <th>Kategori</th>
                    <th class="text-center" style="width: 100px;">Jumlah</th>
                    <th>Keterangan / Alasan</th>
                    <th>Petugas Input</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada riwayat mutasi yang cocok dengan kriteria pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small text-dark"><?= format_datetime($m['created_at']) ?></div>
                            </td>
                            <td class="text-center">
                                <?php if ($m['type'] === 'IN'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-arrow-down-circle me-1"></i>MASUK
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="bi bi-arrow-up-circle me-1"></i>KELUAR
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= base_url('public/products/view.php?id=' . $m['product_id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($m['product_name']) ?>
                                </a><br>
                                <small class="text-muted"><code><?= e($m['sku']) ?></code></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border"><?= e($m['category_name']) ?></span>
                            </td>
                            <td class="text-center fw-bold fs-6 <?= $m['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                <?= $m['type'] === 'IN' ? '+' : '-' ?><?= number_format($m['quantity']) ?>
                            </td>
                            <td>
                                <small class="text-secondary"><?= e($m['notes'] ?: '-') ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold small text-dark"><?= e($m['full_name'] ?: $m['username']) ?></div>
                                <span class="badge <?= $m['role'] === 'admin' ? 'badge-admin' : 'badge-staff' ?>" style="font-size: 0.68rem;">
                                    <?= strtoupper($m['role']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">Halaman <?= $page ?> dari <?= $totalPages ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <?php $pParams = $_GET; $pParams['page'] = $page - 1; ?>
                        <a class="page-link" href="?<?= http_build_query($pParams) ?>">&laquo; Prev</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $pParams = $_GET; $pParams['page'] = $i; ?>
                        <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query($pParams) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <?php $pParams = $_GET; $pParams['page'] = $page + 1; ?>
                        <a class="page-link" href="?<?= http_build_query($pParams) ?>">Next &raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
