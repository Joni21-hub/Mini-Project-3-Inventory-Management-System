<?php
/**
 * Views: Header Layout (Premium Redesign)
 */
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/flash.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Inventory Management System';

// Inisial Nama untuk Avatar Modern
$initials = 'IH';
if ($currentUser) {
    $nameSource = $currentUser['full_name'] ?? $currentUser['username'] ?? 'User';
    $words = explode(' ', trim($nameSource));
    if (count($words) >= 2) {
        $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } else {
        $initials = strtoupper(substr($nameSource, 0, 2));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - InventarisHub Enterprise</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Premium Design System CSS -->
    <link href="<?= base_url('public/assets/css/premium-theme.css') ?>" rel="stylesheet">
</head>
<body>

<?php if (is_logged_in()): ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebarMenu">
        <div class="brand">
            <div class="brand-badge">IH</div>
            <div>
                <div class="brand-title">InventarisHub</div>
                <span class="brand-subtitle">Enterprise Suite</span>
            </div>
        </div>
        
        <div class="sidebar-menu-wrapper">
            <div class="sidebar-heading">Ikhtisar</div>
            <a href="<?= base_url('public/dashboard.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], 'dashboard.php') !== false ? 'active' : '' ?>">
                <span>Dashboard</span>
                <span class="nav-pill-badge">Live</span>
            </a>

            <div class="sidebar-heading">Katalog & Persediaan</div>
            <a href="<?= base_url('public/products/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/products/') !== false ? 'active' : '' ?>">
                <span>Katalog Produk</span>
            </a>
            <?php if (is_admin()): ?>
            <a href="<?= base_url('public/categories/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/categories/') !== false ? 'active' : '' ?>">
                <span>Kategori Produk</span>
            </a>
            <a href="<?= base_url('public/suppliers/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/suppliers/') !== false ? 'active' : '' ?>">
                <span>Mitra Supplier</span>
            </a>
            <?php endif; ?>

            <div class="sidebar-heading">Pergerakan Stok</div>
            <a href="<?= base_url('public/stock/in.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/stock/in.php') !== false ? 'active' : '' ?>">
                <span>Barang Masuk</span>
            </a>
            <a href="<?= base_url('public/stock/out.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/stock/out.php') !== false ? 'active' : '' ?>">
                <span>Barang Keluar</span>
            </a>
            <a href="<?= base_url('public/stock/history.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/stock/history.php') !== false ? 'active' : '' ?>">
                <span>Audit & Riwayat Mutasi</span>
            </a>

            <div class="sidebar-heading">Analitik & Bisnis</div>
            <a href="<?= base_url('public/reports/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/reports/') !== false ? 'active' : '' ?>">
                <span>Laporan & Valuasi</span>
            </a>

            <?php if (is_admin()): ?>
            <div class="sidebar-heading">Administrasi</div>
            <a href="<?= base_url('public/users/index.php') ?>" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/users/') !== false ? 'active' : '' ?>">
                <span>Manajemen User</span>
            </a>
            <?php endif; ?>
        </div>

        <div class="sidebar-user-footer">
            <div class="user-avatar-circle"><?= e($initials) ?></div>
            <div class="overflow-hidden">
                <div class="text-white text-truncate fw-semibold" style="font-size:0.85rem;">
                    <?= e($currentUser['full_name'] ?? $currentUser['username']) ?>
                </div>
                <div class="text-secondary small text-uppercase" style="font-size:0.68rem; letter-spacing:0.05em;">
                    <?= e($currentUser['role']) ?>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" type="button" aria-label="Buka Menu">
                    Menu
                </button>
                <div class="topbar-breadcrumb d-none d-sm-block">
                    <span>Sistem Inventaris</span> &nbsp;/&nbsp; <strong><?= e($pageTitle) ?></strong>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-flex align-items-center gap-2 px-3 py-1 bg-light rounded-pill border">
                    <span class="stock-dot" style="background-color: #22c55e;"></span>
                    <span class="small fw-semibold text-secondary">Server Aktif</span>
                </div>

                <div class="dropdown">
                    <button class="btn btn-outline-secondary rounded-pill py-1 px-3 d-flex align-items-center gap-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-avatar-circle" style="width:26px; height:26px; font-size:0.7rem;"><?= e($initials) ?></span>
                        <span class="fw-semibold small d-none d-sm-inline"><?= e($currentUser['username']) ?></span>
                        <span class="role-pill <?= is_admin() ? 'role-pill-admin' : 'role-pill-staff' ?>"><?= e(strtoupper($currentUser['role'])) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-2" style="border-radius: 14px; min-width: 220px;">
                        <li class="px-3 py-2 border-bottom mb-1">
                            <div class="fw-bold text-dark"><?= e($currentUser['full_name'] ?? $currentUser['username']) ?></div>
                            <small class="text-muted">@<?= e($currentUser['username']) ?></small>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 rounded-2" href="<?= base_url('public/profile.php') ?>">
                                Profil & Pengaturan Akun
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item py-2 rounded-2 text-danger fw-semibold" href="<?= base_url('public/logout.php') ?>">
                                Keluar Sistem (Logout)
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Flash Messages -->
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show border-0 shadow-sm mb-4 d-flex align-items-center justify-content-between p-3" style="border-radius: 12px;" role="alert">
                <div class="d-flex align-items-center gap-2">
                    <span class="stock-dot" style="background-color: <?= $flash['type'] === 'success' ? '#10b981' : ($flash['type'] === 'danger' ? '#ef4444' : '#f59e0b') ?>;"></span>
                    <span class="fw-semibold"><?= e($flash['message']) ?></span>
                </div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endforeach; ?>

<?php endif; ?>
