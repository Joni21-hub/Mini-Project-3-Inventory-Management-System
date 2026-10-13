<?php
$pageTitle = 'Dashboard Analitik';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/flash.php';

require_login();

// 1. Ambil Ringkasan Statistik Produk & Nilai Inventory
$productStatsStmt = $pdo->query("
    SELECT 
        COUNT(id) AS total_products,
        COALESCE(SUM(stock * price), 0) AS total_inventory_value,
        COALESCE(SUM(stock), 0) AS total_stock_count,
        COUNT(CASE WHEN stock <= minimum_stock THEN 1 END) AS low_stock_count
    FROM products
");
$productStats = $productStatsStmt->fetch();

// 2. Ambil Statistik Transaksi Barang Masuk & Keluar
$movementStatsStmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END), 0) AS total_out,
        COUNT(id) AS total_transactions
    FROM stock_movements
");
$movementStats = $movementStatsStmt->fetch();

// 3. Distribusi Stok per Kategori untuk Visualisasi Grafik
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

// 5. Riwayat Mutasi Stok Terbaru
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

// Tentukan sapaan ramah berdasarkan waktu lokal
$hour = (int)date('G');
if ($hour >= 5 && $hour < 11) {
    $greeting = 'Selamat Pagi';
} elseif ($hour >= 11 && $hour < 15) {
    $greeting = 'Selamat Siang';
} elseif ($hour >= 15 && $hour < 18) {
    $greeting = 'Selamat Sore';
} else {
    $greeting = 'Selamat Malam';
}

require_once __DIR__ . '/../views/header.php';
?>

<!-- Hero Banner Dashboard -->
<div class="card border-0 mb-4 overflow-hidden position-relative" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #31104b 100%); border-radius: 20px; box-shadow: 0 12px 30px -8px rgba(15, 23, 42, 0.4);">
    <div class="position-absolute end-0 top-0 bottom-0 d-none d-lg-block opacity-25" style="width: 45%; background: url('<?= base_url('public/assets/images/hero-banner.jpg') ?>') center/cover no-repeat; mask-image: linear-gradient(to left, rgba(0,0,0,1) 0%, rgba(0,0,0,0) 100%); -webkit-mask-image: linear-gradient(to left, rgba(0,0,0,1) 0%, rgba(0,0,0,0) 100%); pointer-events: none;"></div>
    
    <div class="card-body p-4 p-md-5 position-relative z-1">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-white bg-opacity-10 text-white-50 small mb-3 border border-white border-opacity-15">
                    <span class="stock-dot" style="background-color: #38bdf8;"></span>
                    <span class="text-white fw-semibold">Pusat Kendali Inventaris & Mutasi Fisik</span>
                </div>
                <h2 class="display-6 fw-bold text-white mb-2" style="letter-spacing: -0.03em;">
                    <?= $greeting ?>, <?= e(current_user()['full_name'] ?: current_user()['username']) ?>
                </h2>
                <p class="text-white-50 mb-4" style="max-width: 620px; font-size: 0.95rem;">
                    Sistem inventaris saat ini mengelola <strong><?= number_format($productStats['total_products']) ?> katalog SKU</strong> dengan estimasi total valuasi aset gudang senilai <strong><?= format_currency($productStats['total_inventory_value']) ?></strong>.
                </p>
                
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= base_url('public/stock/in.php') ?>" class="btn btn-success px-3 py-2">
                        Catat Barang Masuk
                    </a>
                    <a href="<?= base_url('public/stock/out.php') ?>" class="btn btn-danger px-3 py-2">
                        Catat Barang Keluar
                    </a>
                    <a href="<?= base_url('public/products/index.php') ?>" class="btn btn-outline-secondary px-3 py-2 text-white border-white border-opacity-25 bg-white bg-opacity-10">
                        Jelajahi Produk
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5 Widget Ringkasan Metrik Utama -->
<div class="row g-3 mb-4">
    <!-- Total Produk -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card stat-card-primary h-100">
            <div class="stat-card-label">Total Katalog Produk</div>
            <div class="stat-card-value"><?= number_format($productStats['total_products']) ?></div>
            <div class="stat-card-sub text-muted"><?= number_format($productStats['total_stock_count']) ?> total unit fisik</div>
        </div>
    </div>

    <!-- Nilai Inventory -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card stat-card-info h-100">
            <div class="stat-card-label">Valuasi Persediaan</div>
            <div class="stat-card-value" style="font-size: 1.45rem; padding-top: 0.2rem;"><?= format_currency($productStats['total_inventory_value']) ?></div>
            <div class="stat-card-sub text-muted">Total nilai aset gudang</div>
        </div>
    </div>

    <!-- Produk Stok Rendah -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card stat-card-warning h-100">
            <div class="stat-card-label">Stok Kritis & Menipis</div>
            <div class="stat-card-value <?= $productStats['low_stock_count'] > 0 ? 'text-danger' : 'text-dark' ?>">
                <?= number_format($productStats['low_stock_count']) ?>
            </div>
            <div class="stat-card-sub text-muted">Perlu restock segera</div>
        </div>
    </div>

    <!-- Barang Masuk -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card stat-card-success h-100">
            <div class="stat-card-label">Unit Masuk (IN)</div>
            <div class="stat-card-value text-success">+<?= number_format($movementStats['total_in']) ?></div>
            <div class="stat-card-sub text-muted">Akumulasi pasokan</div>
        </div>
    </div>

    <!-- Barang Keluar -->
    <div class="col-sm-6 col-xl">
        <div class="stat-card stat-card-danger h-100">
            <div class="stat-card-label">Unit Keluar (OUT)</div>
            <div class="stat-card-value text-danger">-<?= number_format($movementStats['total_out']) ?></div>
            <div class="stat-card-sub text-muted">Distribusi operasional</div>
        </div>
    </div>
</div>

<!-- Visualisasi Grafik Analitik Interaktif -->
<div class="row g-4 mb-4">
    <!-- Grafik Distribusi Kategori -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="fw-bold text-dark">Komposisi Stok per Kategori</div>
                    <small class="text-muted">Proporsi kuantitas unit per kelompok inventaris</small>
                </div>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 290px;">
                <canvas id="categoryDistributionChart" style="max-height: 260px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Grafik Rasio Mutasi Masuk vs Keluar -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="fw-bold text-dark">Volume Perputaran Mutasi</div>
                    <small class="text-muted">Perbandingan rasio kuantitas barang masuk vs keluar</small>
                </div>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 290px;">
                <canvas id="movementComparisonChart" style="max-height: 260px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Peringatan Stok Menipis -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="fw-bold text-dark">Peringatan Stok Menipis</div>
                    <small class="text-muted">Item mendekati atau di bawah batas minimum</small>
                </div>
                <a href="<?= base_url('public/products/index.php?stock_status=low') ?>" class="btn btn-sm btn-outline-secondary">
                    Semua
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="p-4 text-center text-muted">
                        Semua stok produk saat ini dalam level aman.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $lowP): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?= product_image_url($lowP['sku'], $lowP['name']) ?>" alt="<?= e($lowP['name']) ?>" class="product-avatar-sm">
                                                <div class="overflow-hidden">
                                                    <a href="<?= base_url('public/products/view.php?id=' . $lowP['id']) ?>" class="fw-semibold text-dark text-decoration-none text-truncate d-block" style="max-width: 170px;">
                                                        <?= e($lowP['name']) ?>
                                                    </a>
                                                    <small class="text-muted font-monospace"><?= e($lowP['sku']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?= stock_badge((int)$lowP['stock'], (int)$lowP['minimum_stock']) ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= base_url('public/stock/in.php?product_id=' . $lowP['id']) ?>" class="btn btn-sm btn-outline-success">
                                                Restock
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
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="fw-bold text-dark">Mutasi Stok Terakhir</div>
                    <small class="text-muted">Audit trail pergerakan inventaris terverifikasi</small>
                </div>
                <a href="<?= base_url('public/stock/history.php') ?>" class="btn btn-sm btn-outline-secondary">
                    Riwayat Lengkap
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
                                    <th>Produk & SKU</th>
                                    <th class="text-center">Tipe</th>
                                    <th class="text-center">Jumlah</th>
                                    <th>Petugas</th>
                                    <th class="text-end">Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMovements as $mov): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?= product_image_url($mov['sku'], $mov['product_name']) ?>" alt="<?= e($mov['product_name']) ?>" class="product-avatar-sm" style="width:36px; height:36px;">
                                                <div class="overflow-hidden">
                                                    <span class="fw-semibold text-dark text-truncate d-block" style="max-width: 190px;">
                                                        <?= e($mov['product_name']) ?>
                                                    </span>
                                                    <small class="text-muted font-monospace"><?= e($mov['sku']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($mov['type'] === 'IN'): ?>
                                                <span class="movement-pill-in">MASUK</span>
                                            <?php else: ?>
                                                <span class="movement-pill-out">KELUAR</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold <?= $mov['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                            <?= $mov['type'] === 'IN' ? '+' : '-' ?><?= number_format($mov['quantity']) ?>
                                        </td>
                                        <td>
                                            <small class="text-dark fw-medium"><?= e($mov['full_name'] ?: $mov['username']) ?></small>
                                        </td>
                                        <td class="text-end text-muted small">
                                            <?= format_date($mov['created_at']) ?>
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
                        '#4f46e5', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#64748b'
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            padding: 15,
                            font: { family: 'Plus Jakarta Sans', size: 11, weight: '500' }
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
                    backgroundColor: ['#10b981', '#f43f5e'],
                    borderRadius: 10,
                    maxBarThickness: 56
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
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 12, weight: '600' } }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
