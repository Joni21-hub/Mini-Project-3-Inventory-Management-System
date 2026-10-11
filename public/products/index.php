<?php
$pageTitle = 'Daftar Produk';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

// 1. Ambil Parameter Filter, Search, Sort & Pagination
$search       = trim($_GET['search'] ?? '');
$categoryId   = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null;
$stockStatus  = trim($_GET['stock_status'] ?? '');

// Allowlist Sorting (Slide 16)
$allowedSortColumns = ['name', 'sku', 'price', 'stock', 'created_at'];
$sortColumn = validate_sort_column($_GET['sort'] ?? 'created_at', $allowedSortColumns, 'created_at');
$sortOrder  = validate_sort_direction($_GET['order'] ?? 'DESC', 'DESC');

// Pagination (Slide 17)
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
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

// 4. Ambil Data Produk dengan JOIN categories & agregat supplier (Slide 12 & 14)
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
// Bind parameter pencarian
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
// Bind integer limit & offset
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
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-box-seam text-primary me-2"></i>Daftar Master Produk</h3>
        <p class="text-muted small mb-0">Kelola informasi produk, SKU unik, stok minimum, dan keterhubungan supplier.</p>
    </div>
    <?php if (is_admin()): ?>
    <div class="mt-3 mt-md-0">
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Tambah Produk Baru
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Form Filter & Pencarian (Slide 16) -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Cari Nama / SKU</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ketik nama atau SKU..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary mb-1">Kategori Produk</label>
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
                <label class="form-label small fw-semibold text-secondary mb-1">Status Stok</label>
                <select name="stock_status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="available" <?= $stockStatus === 'available' ? 'selected' : '' ?>>Tersedia Aman</option>
                    <option value="low" <?= $stockStatus === 'low' ? 'selected' : '' ?>>Stok Menipis</option>
                    <option value="out" <?= $stockStatus === 'out' ? 'selected' : '' ?>>Stok Habis (0)</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="index.php" class="btn btn-outline-secondary" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar Produk -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold text-muted small">
            Menampilkan <?= count($products) ?> dari <?= $totalRows ?> total produk
        </span>
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">Urutkan:</span>
            <?php
            // Helper fungsi untuk link sorting
            function sort_link($col, $label, $currentCol, $currentOrder) {
                $newOrder = ($currentCol === $col && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
                $icon = '';
                if ($currentCol === $col) {
                    $icon = $currentOrder === 'ASC' ? '<i class="bi bi-sort-up text-primary"></i>' : '<i class="bi bi-sort-down text-primary"></i>';
                }
                $params = $_GET;
                $params['sort'] = $col;
                $params['order'] = $newOrder;
                $url = '?' . http_build_query($params);
                return "<a href='{$url}' class='text-decoration-none text-dark fw-semibold'>{$label} {$icon}</a>";
            }
            ?>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;"><?= sort_link('sku', 'SKU', $sortColumn, $sortOrder) ?></th>
                    <th><?= sort_link('name', 'Nama Produk', $sortColumn, $sortOrder) ?></th>
                    <th>Kategori</th>
                    <th class="text-end"><?= sort_link('price', 'Harga Jual', $sortColumn, $sortOrder) ?></th>
                    <th class="text-center"><?= sort_link('stock', 'Stok Saat Ini', $sortColumn, $sortOrder) ?></th>
                    <th class="text-center">Supplier</th>
                    <th class="text-end" style="width: 190px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada data produk yang cocok dengan pencarian / filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <code><?= e($p['sku']) ?></code>
                            </td>
                            <td>
                                <a href="view.php?id=<?= $p['id'] ?>" class="fw-semibold text-decoration-none text-dark">
                                    <?= e($p['name']) ?>
                                </a>
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
                                <div class="text-muted" style="font-size: 0.72rem;">Min: <?= e($p['minimum_stock']) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                    <i class="bi bi-truck me-1"></i><?= $p['total_suppliers'] ?> Mitra
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Detail Produk & Histori">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('public/stock/in.php?product_id=' . $p['id']) ?>" class="btn btn-outline-success" title="Tambah Stok (Barang Masuk)">
                                        <i class="bi bi-plus-lg"></i>
                                    </a>
                                    <?php if (is_admin()): ?>
                                    <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Edit Produk">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-outline-danger" title="Hapus Produk" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination (Slide 17) -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">
                Halaman <?= $page ?> dari <?= $totalPages ?>
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <!-- Tombol Prev -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <?php 
                        $prevParams = $_GET; 
                        $prevParams['page'] = $page - 1; 
                        ?>
                        <a class="page-link" href="?<?= http_build_query($prevParams) ?>">&laquo; Prev</a>
                    </li>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php 
                        $pageParams = $_GET; 
                        $pageParams['page'] = $i; 
                        ?>
                        <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query($pageParams) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Tombol Next -->
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <?php 
                        $nextParams = $_GET; 
                        $nextParams['page'] = $page + 1; 
                        ?>
                        <a class="page-link" href="?<?= http_build_query($nextParams) ?>">Next &raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
