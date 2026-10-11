<?php
$pageTitle = 'Dashboard Inventory';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/flash.php';

require_login();

// 1. Ambil Ringkasan Statistik Produk & Nilai Inventory (Slide 29)
$productStatsStmt = $pdo->query("
    SELECT 
        COUNT(id) AS total_products,
        COALESCE(SUM(stock * price), 0) AS total_inventory_value,
        COALESCE(SUM(stock), 0) AS total_stock_count,
        COUNT(CASE WHEN stock <= minimum_stock THEN 1 END) AS low_stock_count
    FROM products
");
$productStats = $productStatsStmt->fetch();

// 2. Ambil Statistik Transaksi Barang Masuk & Keluar (Slide 29)
$movementStatsStmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END), 0) AS total_out,
        COUNT(id) AS total_transactions
    FROM stock_movements
");
$movementStats = $movementStatsStmt->fetch();

// 3. Distribusi Stok per Kategori untuk Visualisasi Grafik (Slide 14)
$catDistributionStmt = $pdo->query("
    SELECT c.name, COALESCE(SUM(p.stock), 0) AS total_stock
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY total_stock DESC
");
$categoryChartData = $catDistributionStmt->fetchAll();

// 4. Daftar Produk Stok Rendah (stock <= minimum_stock)
$lowStockStmt = $pdo->query("
    SELECT p.id, p.sku, p.name, p.stock, p.minimum_stock, p.price, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.stock <= p.minimum_stock
    ORDER BY p.stock ASC
    LIMIT 6
");
$lowStockProducts = $lowStockStmt->fetchAll();

// 5. Riwayat Mutasi Stok Terbaru (Slide 25-27)
$recentMovementsStmt = $pdo->query("
    SELECT sm.id, sm.type, sm.quantity, sm.notes, sm.created_at,
           p.sku, p.name AS product_name,
           u.username, up.full_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    ORDER BY sm.created_at DESC
    LIMIT 6
");
$recentMovements = $recentMovementsStmt->fetchAll();

require_once __DIR__ . '/../views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Dashboard Inventory</h3>
        <p class="text-muted small mb-0">Informasi ringkas real-time untuk memantau stok dan pergerakan barang.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="<?= base_url('public/stock/in.php') ?>" class="btn btn-success">
            <i class="bi bi-arrow-down-circle me-1"></i> Input Barang Masuk
        </a>
        <a href="<?= base_url('public/stock/out.php') ?>" class="btn btn-danger">
            <i class="bi bi-arrow-up-circle me-1"></i> Input Barang Keluar
        </a>
    </div>
</div>

<!-- 5 Widget Ringkasan Utama (Sesuai Slide 29) -->
<div class="row g-3 mb-4">
    <!-- Total Produk -->
    <div class="col-sm-6 col-xl">
        <div class="card h-100 shadow-sm border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Produk</div>
                        <div class="h3 fw-bold text-dark my-1"><?= number_format($productStats['total_products']) ?></div>
                        <small class="text-muted"><?= number_format($productStats['total_stock_count']) ?> unit total stok</small>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3">
                        <i class="bi bi-box-seam fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nilai Inventory -->
    <div class="col-sm-6 col-xl">
        <div class="card h-100 shadow-sm border-start border-info border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Nilai Inventory</div>
                        <div class="h4 fw-bold text-dark my-1"><?= format_currency($productStats['total_inventory_value']) ?></div>
                        <small class="text-muted">Total valuasi aset fisik</small>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-3">
                        <i class="bi bi-cash-stack fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Produk Stok Rendah -->
    <div class="col-sm-6 col-xl">
        <div class="card h-100 shadow-sm border-start border-warning border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Stok Menipis</div>
                        <div class="h3 fw-bold <?= $productStats['low_stock_count'] > 0 ? 'text-danger' : 'text-dark' ?> my-1">
                            <?= number_format($productStats['low_stock_count']) ?>
                        </div>
                        <small class="text-muted">Stok &le; Batas Minimum</small>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3">
                        <i class="bi bi-exclamation-triangle fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barang Masuk -->
    <div class="col-sm-6 col-xl">
        <div class="card h-100 shadow-sm border-start border-success border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Barang Masuk</div>
                        <div class="h3 fw-bold text-success my-1">+<?= number_format($movementStats['total_in']) ?></div>
                        <small class="text-muted">Total unit masuk (IN)</small>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-3">
                        <i class="bi bi-arrow-down-left-square fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barang Keluar -->
    <div class="col-sm-6 col-xl">
        <div class="card h-100 shadow-sm border-start border-danger border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Barang Keluar</div>
                        <div class="h3 fw-bold text-danger my-1">-<?= number_format($movementStats['total_out']) ?></div>
                        <small class="text-muted">Total unit keluar (OUT)</small>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-3">
                        <i class="bi bi-arrow-up-right-square fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Visualisasi Grafik Analitik Interaktif -->
<div class="row g-4 mb-4">
    <!-- Grafik Distribusi Kategori -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-pie-chart text-primary me-2"></i>Komposisi Stok per Kategori Barang
                </h6>
            </div>
            <div class="card-body p-4 d-flex align-items-center justify-content-center" style="min-height: 280px;">
                <canvas id="categoryDistributionChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Grafik Rasio Mutasi Masuk vs Keluar -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-bar-chart-line text-primary me-2"></i>Volume Mutasi: Total Barang Masuk vs Keluar
                </h6>
            </div>
            <div class="card-body p-4 d-flex align-items-center justify-content-center" style="min-height: 280px;">
                <canvas id="movementComparisonChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Peringatan Stok Menipis -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="fw-bold text-dark">
                    <i class="bi bi-bell-fill text-warning me-2"></i>Peringatan Stok Kritis & Menipis
                </div>
                <a href="<?= base_url('public/products/index.php?stock_status=low') ?>" class="btn btn-sm btn-outline-primary">
                    Lihat Semua
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check2-circle text-success fs-1 mb-2 d-block"></i>
                        Semua stok produk saat ini dalam level aman.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-center">Sisa Stok</th>
                                    <th class="text-end">Aksi Restock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $lowP): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($lowP['name']) ?></div>
                                            <small class="text-muted">SKU: <code><?= e($lowP['sku']) ?></code> | <?= e($lowP['category_name']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?= stock_badge((int)$lowP['stock'], (int)$lowP['minimum_stock']) ?>
                                            <div class="small text-muted mt-1">Min: <?= e($lowP['minimum_stock']) ?></div>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= base_url('public/stock/in.php?product_id=' . $lowP['id']) ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-plus-lg me-1"></i>Restock
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Riwayat Transaksi Mutasi Terbaru -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="fw-bold text-dark">
                    <i class="bi bi-clock-history text-primary me-2"></i>Mutasi Stok Terakhir (Audit Trail)
                </div>
                <a href="<?= base_url('public/stock/history.php') ?>" class="btn btn-sm btn-outline-primary">
                    Selengkapnya
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentMovements)): ?>
                    <div class="p-4 text-center text-muted">Belum ada transaksi mutasi yang tercatat.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Tipe</th>
                                    <th>Produk</th>
                                    <th class="text-center">Jumlah</th>
                                    <th>Petugas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMovements as $mov): ?>
                                    <tr>
                                        <td>
                                            <div class="small fw-semibold"><?= format_date($mov['created_at']) ?></div>
                                            <small class="text-muted"><?= date('H:i', strtotime($mov['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($mov['type'] === 'IN'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bi bi-arrow-down me-1"></i>MASUK
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                    <i class="bi bi-arrow-up me-1"></i>KELUAR
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small text-dark"><?= e($mov['product_name']) ?></div>
                                            <small class="text-muted"><code><?= e($mov['sku']) ?></code></small>
                                        </td>
                                        <td class="text-center fw-bold <?= $mov['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                            <?= $mov['type'] === 'IN' ? '+' : '-' ?><?= number_format($mov['quantity']) ?>
                                        </td>
                                        <td>
                                            <small class="text-dark fw-medium"><?= e($mov['full_name'] ?: $mov['username']) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Chart Komposisi Stok per Kategori
    const catLabels = <?= json_encode(array_column($categoryChartData, 'name')) ?>;
    const catData = <?= json_encode(array_map('intval', array_column($categoryChartData, 'total_stock'))) ?>;

    const ctxCat = document.getElementById('categoryDistributionChart');
    if (ctxCat) {
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catData,
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { family: 'Plus Jakarta Sans', size: 11 }
                        }
                    }
                }
            }
        });
    }

    // 2. Chart Volume Mutasi Masuk vs Keluar
    const ctxMov = document.getElementById('movementComparisonChart');
    if (ctxMov) {
        new Chart(ctxMov, {
            type: 'bar',
            data: {
                labels: ['Barang Masuk (IN)', 'Barang Keluar (OUT)'],
                datasets: [{
                    label: 'Total Unit Mutasi',
                    data: [<?= (int)$movementStats['total_in'] ?>, <?= (int)$movementStats['total_out'] ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderRadius: 8,
                    maxBarThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 12, weight: 'bold' } }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
