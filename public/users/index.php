<?php
$pageTitle = 'Manajemen Pengguna & Otorisasi';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

// Hak akses khusus Admin (Slide 24 & 33)
require_role('admin');

// Query Users dengan JOIN 1:1 user_profiles & agregasi riwayat mutasi
$sql = "
    SELECT u.id, u.username, u.role, u.created_at,
           up.full_name, up.phone, up.address,
           COUNT(sm.id) AS total_logged_transactions
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    LEFT JOIN stock_movements sm ON sm.user_id = u.id
    GROUP BY u.id, u.username, u.role, u.created_at, up.full_name, up.phone, up.address
    ORDER BY u.id ASC
";
$users = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-people text-primary me-2"></i>Manajemen User & Hak Akses (RBAC)</h3>
        <p class="text-muted small mb-0">Kelola akun pengguna, profil (Relasi One-to-One), dan otorisasi role Admin/Staff.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Tambah Pengguna Baru
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="fw-semibold text-muted small">Daftar Akun Pengguna Terdaftar</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Username & Nama Lengkap</th>
                    <th class="text-center">Role / Otorisasi</th>
                    <th>Kontak (Telepon)</th>
                    <th class="text-center">Total Transaksi Dicatat</th>
                    <th>Terdaftar Sejak</th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-muted small"><?= $u['id'] ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($u['full_name'] ?: $u['username']) ?></div>
                            <small class="text-muted">Username: <code>@<?= e($u['username']) ?></code></small>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-staff' ?> text-uppercase">
                                <i class="bi <?= $u['role'] === 'admin' ? 'bi-shield-check' : 'bi-person' ?> me-1"></i>
                                <?= e($u['role']) ?>
                            </span>
                        </td>
                        <td><?= e($u['phone'] ?: '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-secondary border">
                                <?= number_format($u['total_logged_transactions']) ?> Transaksi
                            </span>
                        </td>
                        <td>
                            <small class="text-muted"><?= format_date($u['created_at']) ?></small>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-outline-primary" title="Edit User">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($u['id'] !== current_user()['id']): ?>
                                <a href="delete.php?id=<?= $u['id'] ?>" class="btn btn-outline-danger" title="Hapus User" onclick="return confirm('Apakah Anda yakin ingin menghapus akun ini?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled title="Tidak dapat menghapus akun sendiri">
                                    <i class="bi bi-lock"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
