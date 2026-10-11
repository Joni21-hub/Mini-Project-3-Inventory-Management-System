<?php
$pageTitle = 'Laporan Inventory & Agregasi Data';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_login();

// Penanganan Export CSV jika diminta
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan-inventory-' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    
    // BOM untuk UTF-8 di Excel
    fputs($output, "\xEF\xBB\xBF");
    
    fputcsv($output, ['SKU', 'Nama Produk', 'Kategori', 'Total Masuk (IN)', 'Total Keluar (OUT)', 'Stok Akhir', 'Harga Satuan', 'Nilai Persediaan']);
    
    $exportStmt = $pdo->query("
        SELECT p.sku, p.name, c.name AS category_name, p.price, p.stock,
               COALESCE(SUM(CASE WHEN sm.type='IN' THEN sm.quantity ELSE 0 END), 0) AS total_in,
               COALESCE(SUM(CASE WHEN sm.type='OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN stock_movements sm ON sm.product_id = p.id
        GROUP BY p.id, p.sku, p.name, c.name, p.price, p.stock
        ORDER BY p.name ASC
    ");
    
    while ($row = $exportStmt->fetch()) {
        fputcsv($output, [
            $row['sku'],
            $row['name'],
            $row['category_name'],
            $row['total_in'],
            $row['total_out'],
            $row['stock'],
            $row['price'],
            $row['stock'] * $row['price']
        ]);
    }
    fclose($output);
    exit;
}

// 1. Query Laporan Mutasi & Keseimbangan Stok Per Produk (Slide 28)
$stockReportSql = "
    SELECT p.id, p.sku, p.name, p.price, p.stock, p.minimum_stock,
           c.name AS category_name,
           COALESCE(SUM(CASE WHEN sm.type='IN' THEN sm.quantity ELSE 0 END), 0) AS total_in,
           COALESCE(SUM(CASE WHEN sm.type='OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN stock_movements sm ON sm.product_id = p.id
    GROUP BY p.id, p.sku, p.name, c.name, p.price, p.stock, p.minimum_stock
    ORDER BY p.name ASC
";
$stockReports = $pdo->query($stockReportSql)->fetchAll();

// 2. Query Laporan Ringkasan Per Kategori Menggunakan Aggregate & GROUP BY (Slide 14)
$categoryReportSql = "
    SELECT c.name AS category_name,
           COUNT(p.id) AS total_products,
           COALESCE(SUM(p.stock), 0) AS total_stock,
           COALESCE(AVG(p.price), 0) AS average_price,
           COALESCE(SUM(p.stock * p.price), 0) AS category_valuation
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY total_products DESC, category_name ASC
";
$categoryReports = $pdo->query($categoryReportSql)->fetchAll();

// 3. Ringkasan Total Keseluruhan
$totalValuation = 0;
$grandTotalIn = 0;
$grandTotalOut = 0;
$grandTotalStock = 0;

foreach ($stockReports as $sr) {
    $totalValuation += ($sr['stock'] * $sr['price']);
    $grandTotalIn += $sr['total_in'];
    $grandTotalOut += $sr['total_out'];
    $grandTotalStock += $sr['stock'];
}

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Laporan Inventory & Analisis Agregat</h3>
        <p class="text-muted small mb-0">Implementasi query agregasi (COUNT, SUM, AVG) dan audit saldo pergerakan stok (Slide 14 & 28).</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i> Cetak Laporan
        </button>
        <a href="index.php?export=csv" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Ekspor ke CSV / Excel
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Akumulasi -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border-0 bg-white">
            <small class="text-muted text-uppercase fw-semibold">Total Unit Masuk (IN)</small>
            <div class="h3 fw-bold text-success my-1">+<?= number_format($grandTotalIn) ?></div>
            <small class="text-muted">Dari semua supplier & restock</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border-0 bg-white">
            <small class="text-muted text-uppercase fw-semibold">Total Unit Keluar (OUT)</small>
            <div class="h3 fw-bold text-danger my-1">-<?= number_format($grandTotalOut) ?></div>
            <small class="text-muted">Distribusi divisi & penjualan</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border-0 bg-white">
            <small class="text-muted text-uppercase fw-semibold">Saldo Unit Tersedia</small>
            <div class="h3 fw-bold text-primary my-1"><?= number_format($grandTotalStock) ?></div>
            <small class="text-muted">Kapasitas stok fisik gudang</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border-0 bg-white">
            <small class="text-muted text-uppercase fw-semibold">Total Valuasi Nilai Stok</small>
            <div class="h4 fw-bold text-dark my-1"><?= format_currency($totalValuation) ?></div>
            <small class="text-muted">SUM(stock &times; price)</small>
        </div>
    </div>
</div>

<!-- Laporan 1: Pergerakan & Saldo Inventaris Produk (Slide 28) -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">
            <i class="bi bi-table text-primary me-2"></i>Laporan Pergerakan & Valuasi Stok Produk (Slide 28 Query)
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th class="text-center text-success">Total IN</th>
                    <th class="text-center text-danger">Total OUT</th>
                    <th class="text-center">Sisa Stok</th>
                    <th class="text-end">Harga Jual</th>
                    <th class="text-end">Total Nilai Aset</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stockReports as $row): ?>
                    <tr>
                        <td><code><?= e($row['sku']) ?></code></td>
                        <td>
                            <a href="<?= base_url('public/products/view.php?id=' . $row['id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                <?= e($row['name']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border"><?= e($row['category_name']) ?></span>
                        </td>
                        <td class="text-center fw-bold text-success">
                            +<?= number_format($row['total_in']) ?>
                        </td>
                        <td class="text-center fw-bold text-danger">
                            -<?= number_format($row['total_out']) ?>
                        </td>
                        <td class="text-center">
                            <?= stock_badge((int)$row['stock'], (int)$row['minimum_stock']) ?>
                        </td>
                        <td class="text-end">
                            <?= format_currency($row['price']) ?>
                        </td>
                        <td class="text-end fw-bold text-dark">
                            <?= format_currency($row['stock'] * $row['price']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3" class="text-end">TOTAL KESELURUHAN:</td>
                    <td class="text-center text-success">+<?= number_format($grandTotalIn) ?></td>
                    <td class="text-center text-danger">-<?= number_format($grandTotalOut) ?></td>
                    <td class="text-center"><?= number_format($grandTotalStock) ?> unit</td>
                    <td></td>
                    <td class="text-end text-primary"><?= format_currency($totalValuation) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Laporan 2: Ringkasan Kategori Menggunakan GROUP BY & Aggregate (Slide 14) -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold">
            <i class="bi bi-pie-chart text-primary me-2"></i>Laporan Agregasi Per Kategori (Slide 14 Query)
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nama Kategori</th>
                    <th class="text-center">Total Produk (COUNT)</th>
                    <th class="text-center">Total Kuantitas Stok (SUM)</th>
                    <th class="text-end">Rata-rata Harga Jual (AVG)</th>
                    <th class="text-end">Valuasi Kategori</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryReports as $cRow): ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?= e($cRow['category_name']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary border">
                                <?= number_format($cRow['total_products']) ?> SKU
                            </span>
                        </td>
                        <td class="text-center fw-semibold">
                            <?= number_format($cRow['total_stock']) ?> unit
                        </td>
                        <td class="text-end">
                            <?= format_currency($cRow['average_price']) ?>
                        </td>
                        <td class="text-end fw-bold text-dark">
                            <?= format_currency($cRow['category_valuation']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
