<?php
/**
 * Views: Header Layout
 */
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/flash.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Inventory Management System';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - InventarisHub</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-body-font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --brand-primary: #3b82f6;
            --brand-dark: #0f172a;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --sidebar-active: #2563eb;
        }
        body {
            background-color: #f8fafc;
            color: #334155;
        }
        .sidebar {
            min-height: 100vh;
            background-color: var(--sidebar-bg);
            color: #e2e8f0;
            width: 260px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar .brand {
            padding: 1.25rem 1.5rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .sidebar .nav-link {
            color: #94a3b8;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            border-radius: 8px;
            margin: 0.15rem 0.75rem;
            font-weight: 500;
            font-size: 0.92rem;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover {
            color: #f8fafc;
            background-color: var(--sidebar-hover);
        }
        .sidebar .nav-link.active {
            color: #ffffff;
            background-color: var(--sidebar-active);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .sidebar .nav-link i {
            font-size: 1.15rem;
            margin-right: 0.75rem;
        }
        .sidebar-heading {
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            color: #64748b;
            padding: 1rem 1.5rem 0.4rem;
            font-weight: 700;
        }
        .main-content {
            margin-left: 260px;
            padding: 1.75rem 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: #ffffff;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        .table thead th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 1rem;
        }
        .table tbody td {
            padding: 0.85rem 1rem;
            vertical-align: middle;
            font-size: 0.92rem;
        }
        .badge-admin {
            background-color: #e0e7ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }
        .badge-staff {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: -260px;
            }
            .sidebar.show {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<?php if (is_logged_in()): ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebarMenu">
        <div class="brand">
            <i class="bi bi-boxes text-primary me-2 fs-4"></i>
            <span>Inventaris<span class="text-primary">Hub</span></span>
        </div>
        
        <div class="py-3">
            <div class="sidebar-heading">Menu Utama</div>
            <a href="<?= base_url('public/dashboard.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'dashboard.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>

            <div class="sidebar-heading">Master Data</div>
            <a href="<?= base_url('public/products/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/products/') !== false ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Produk & Stok
            </a>
            <?php if (is_admin()): ?>
            <a href="<?= base_url('public/categories/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/categories/') !== false ? 'active' : '' ?>">
                <i class="bi bi-tags"></i> Kategori
            </a>
            <a href="<?= base_url('public/suppliers/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/suppliers/') !== false ? 'active' : '' ?>">
                <i class="bi bi-truck"></i> Supplier
            </a>
            <?php endif; ?>

            <div class="sidebar-heading">Transaksi & Mutasi</div>
            <a href="<?= base_url('public/stock/in.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/stock/in.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-arrow-down-circle text-success"></i> Barang Masuk
            </a>
            <a href="<?= base_url('public/stock/out.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/stock/out.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-arrow-up-circle text-danger"></i> Barang Keluar
            </a>
            <a href="<?= base_url('public/stock/history.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/stock/history.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Riwayat Mutasi
            </a>

            <div class="sidebar-heading">Laporan & Analitik</div>
            <a href="<?= base_url('public/reports/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/reports/') !== false ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Laporan Inventory
            </a>

            <?php if (is_admin()): ?>
            <div class="sidebar-heading">Pengaturan Sistem</div>
            <a href="<?= base_url('public/users/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/users/') !== false ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Manajemen User
            </a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-sm btn-outline-secondary d-lg-none me-2" id="sidebarToggle" type="button">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="d-none d-sm-block">
                    <span class="text-muted small">Mini Project 3: Relational Database & Advanced PHP</span>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold text-dark"><?= e($currentUser['full_name'] ?? $currentUser['username']) ?></div>
                    <span class="badge <?= is_admin() ? 'badge-admin' : 'badge-staff' ?> text-uppercase" style="font-size:0.7rem;">
                        <?= e($currentUser['role']) ?>
                    </span>
                </div>
                
                <div class="dropdown">
                    <button class="btn btn-light rounded-circle p-2 dropdown-toggle-split" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 text-secondary"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li>
                            <div class="px-3 py-2 border-bottom">
                                <div class="fw-bold"><?= e($currentUser['full_name'] ?? $currentUser['username']) ?></div>
                                <small class="text-muted">@<?= e($currentUser['username']) ?> (<?= e($currentUser['role']) ?>)</small>
                            </div>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= base_url('public/profile.php') ?>">
                                <i class="bi bi-person me-2 text-primary"></i> Profil Saya
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger" href="<?= base_url('public/logout.php') ?>">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Flash Messages -->
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm border-0" role="alert">
                <div class="d-flex align-items-center">
                    <?php if ($flash['type'] === 'success'): ?>
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <?php elseif ($flash['type'] === 'danger'): ?>
                        <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                    <?php elseif ($flash['type'] === 'warning'): ?>
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <?php else: ?>
                        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                    <?php endif; ?>
                    <div><?= e($flash['message']) ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>

<?php endif; ?>
