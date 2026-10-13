<?php
$pageTitle = 'Katalog Master Produk';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

// 1. Ambil Parameter Filter, Search, Sort, View Mode & Pagination
$search       = trim($_GET['search'] ?? '');
$categoryId   = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null;
$stockStatus  = trim($_GET['stock_status'] ?? '');
$viewMode     = in_array($_GET['view'] ?? '', ['grid', 'table'], true) ? $_GET['view'] : 'grid';

// Allowlist Sorting
$allowedSortColumns = ['name', 'sku', 'price', 'stock', 'created_at'];
$sortColumn = validate_sort_column($_GET['sort'] ?? 'created_at', $allowedSortColumns, 'created_at');
$sortOrder  = validate_sort_direction($_GET['order'] ?? 'DESC', 'DESC');

// Pagination
$perPage = ($viewMode === 'grid') ? 8 : 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// 2. Bangun Query WHERE secara dinamis dengan Prepared Statements
$whereClauses = ['1=1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(p.name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params['search_name'] = "%{$search}%";
    $params['search_sku']  = "%{$search}%";
}

if ($categoryId) {
    $whereClauses[] = "p.category_id = :category_id";
    $params['category_id'] = $categoryId;
}

if ($stockStatus === 'low') {
    $whereClauses[] = "p.stock <= p.minimum_stock AND p.stock > 0";
} elseif ($stockStatus === 'out') {
    $whereClauses[] = "p.stock = 0";
} elseif ($stockStatus === 'available') {
    $whereClauses[] = "p.stock > p.minimum_stock";
}

$whereSql = implode(' AND ', $whereClauses);

// 3. Hitung Total Data untuk Pagination
$countSql = "SELECT COUNT(p.id) FROM products p WHERE {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

// 4. Ambil Data Produk dengan JOIN categories & agregat supplier
$dataSql = "
    SELECT p.*, c.name AS category_name,
           COUNT(ps.supplier_id) AS total_suppliers
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_suppliers ps ON ps.product_id = p.id
    WHERE {$whereSql}
    GROUP BY p.id, c.name
    ORDER BY p.{$sortColumn} {$sortOrder}
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Ambil daftar kategori untuk dropdown filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1" style="letter-spacing: -0.02em;">Katalog Master Produk</h3>
        <p class="text-muted small mb-0">Kelola informasi produk, SKU unik, stok minimum, dan keterhubungan multi-supplier.</p>
    </div>
    <?php if (is_admin()): ?>
    <div class="mt-3 mt-md-0">
        <a href="create.php" class="btn btn-primary">
            Tambah Produk Baru
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Form Filter & Pencarian -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="view" value="<?= e($viewMode) ?>">

            <div class="col-md-4">
                <label class="form-label small text-secondary mb-1">Cari Nama / SKU</label>
                <input type="text" name="search" class="form-control" placeholder="Ketik nama atau SKU..." value="<?= e($search) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label small text-secondary mb-1">Kategori Produk</label>
                <select name="category_id" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-secondary mb-1">Status Ketersediaan</label>
                <select name="stock_status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="available" <?= $stockStatus === 'available' ? 'selected' : '' ?>>Tersedia Aman</option>
                    <option value="low" <?= $stockStatus === 'low' ? 'selected' : '' ?>>Stok Menipis</option>
                    <option value="out" <?= $stockStatus === 'out' ? 'selected' : '' ?>>Stok Habis (0)</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    Terapkan Filter
                </button>
                <a href="index.php?view=<?= e($viewMode) ?>" class="btn btn-outline-secondary" title="Reset Filter">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Toolbar Tampilan & Jumlah Data -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div class="text-muted small">
        Menampilkan <strong><?= count($products) ?></strong> dari total <strong><?= $totalRows ?></strong> produk
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
        <span class="small text-muted me-1">Format Tampilan:</span>
        <div class="btn-group btn-group-sm" role="group">
            <?php 
                $gridParams = $_GET; $gridParams['view'] = 'grid'; 
                $tableParams = $_GET; $tableParams['view'] = 'table'; 
            ?>
            <a href="?<?= http_build_query($gridParams) ?>" class="btn <?= $viewMode === 'grid' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Grid Card
            </a>
            <a href="?<?= http_build_query($tableParams) ?>" class="btn <?= $viewMode === 'table' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Tabel List
            </a>
        </div>
    </div>
</div>

<?php if (empty($products)): ?>
    <div class="card p-5 text-center text-muted">
        <h5 class="fw-bold text-dark mb-1">Tidak Ada Produk Ditemukan</h5>
        <p class="small mb-3">Coba ubah kata kunci pencarian atau bersihkan filter yang aktif.</p>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset Pencarian</a>
        </div>
    </div>
<?php elseif ($viewMode === 'grid'): ?>
    <!-- Mode Grid Card (Marketplace Style dengan Foto Asli Berkualitas) -->
    <div class="row g-4 mb-4">
        <?php foreach ($products as $p): ?>
            <?php
                $stockPct = ($p['minimum_stock'] > 0) ? min(100, round(($p['stock'] / ($p['minimum_stock'] * 3)) * 100)) : 100;
                $barColor = ($p['stock'] <= 0) ? '#ef4444' : (($p['stock'] <= $p['minimum_stock']) ? '#f59e0b' : '#10b981');
            ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="product-card">
                    <div class="product-card-img-wrap">
                        <img src="<?= product_image_url($p['sku'], $p['name']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                        <div class="product-card-badge-top">
                            <?= stock_badge((int)$p['stock'], (int)$p['minimum_stock']) ?>
                        </div>
                    </div>
                    <div class="product-card-body">
                        <div class="product-card-category"><?= e($p['category_name']) ?></div>
                        <a href="view.php?id=<?= $p['id'] ?>" class="product-card-title text-decoration-none">
                            <?= e($p['name']) ?>
                        </a>
                        <div class="product-card-sku">SKU: <?= e($p['sku']) ?></div>

                        <div class="mb-2">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Kapasitas Stok</span>
                                <strong class="text-dark"><?= number_format($p['stock']) ?> unit</strong>
                            </div>
                            <div class="stock-progress-track">
                                <div class="stock-progress-fill" style="width: <?= $stockPct ?>%; background-color: <?= $barColor ?>;"></div>
                            </div>
                        </div>

                        <div class="product-card-price mb-3">
                            <?= format_currency($p['price']) ?>
                        </div>

                        <div class="d-flex gap-2 pt-2 border-top">
                            <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-sm flex-grow-1">
                                Detail
                            </a>
                            <a href="<?= base_url('public/stock/in.php?product_id=' . $p['id']) ?>" class="btn btn-outline-success btn-sm" title="Restock">
                                + Stok
                            </a>
                            <?php if (is_admin()): ?>
                            <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                                Edit
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- Mode Tabel Dense -->
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">Visual</th>
                        <th>SKU & Nama Produk</th>
                        <th>Kategori</th>
                        <th class="text-end">Harga Satuan</th>
                        <th class="text-center">Status Stok</th>
                        <th class="text-center">Supplier</th>
                        <th class="text-end" style="width: 170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <img src="<?= product_image_url($p['sku'], $p['name']) ?>" alt="<?= e($p['name']) ?>" class="product-avatar-sm">
                            </td>
                            <td>
                                <a href="view.php?id=<?= $p['id'] ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                    <?= e($p['name']) ?>
                                </a>
                                <small class="text-muted font-monospace"><?= e($p['sku']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    <?= e($p['category_name']) ?>
                                </span>
                            </td>
                            <td class="text-end fw-semibold text-dark">
                                <?= format_currency($p['price']) ?>
                            </td>
                            <td class="text-center">
                                <?= stock_badge((int)$p['stock'], (int)$p['minimum_stock']) ?>
                                <div class="text-muted mt-1" style="font-size: 0.72rem;">Min: <?= e($p['minimum_stock']) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">
                                    <?= $p['total_suppliers'] ?> Mitra
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary">Detail</a>
                                    <a href="<?= base_url('public/stock/in.php?product_id=' . $p['id']) ?>" class="btn btn-outline-success">+ IN</a>
                                    <?php if (is_admin()): ?>
                                    <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary">Edit</a>
                                    <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">Hapus</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2">
        <small class="text-muted">
            Halaman <?= $page ?> dari <?= $totalPages ?> (Total <?= $totalRows ?> item)
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <?php $prevParams = $_GET; $prevParams['page'] = $page - 1; ?>
                    <a class="page-link" href="?<?= http_build_query($prevParams) ?>">&laquo; Sebelumnya</a>
                </li>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $pageParams = $_GET; $pageParams['page'] = $i; ?>
                    <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query($pageParams) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <?php $nextParams = $_GET; $nextParams['page'] = $page + 1; ?>
                    <a class="page-link" href="?<?= http_build_query($nextParams) ?>">Selanjutnya &raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
