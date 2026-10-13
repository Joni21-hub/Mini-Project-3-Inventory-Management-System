<?php
$pageTitle = 'Manajemen Pengguna & Akses';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/flash.php';

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
        <h3 class="fw-bold text-dark mb-1" style="letter-spacing: -0.02em;">Manajemen Pengguna (RBAC)</h3>
        <p class="text-muted small mb-0">Kelola akun, profil One-to-One, dan hak otorisasi role Admin/Staff.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="create.php" class="btn btn-primary">
            Tambah Pengguna Baru
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <span class="text-muted small">Daftar Akun Pengguna Terdaftar (<?= count($users) ?> user)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Pengguna & Profil</th>
                    <th class="text-center">Role Akses</th>
                    <th>Kontak Telepon</th>
                    <th class="text-center">Transaksi Tercatat</th>
                    <th>Terdaftar Sejak</th>
                    <th class="text-end" style="width: 150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                        $uName = $u['full_name'] ?: $u['username'];
                        $parts = explode(' ', trim($uName));
                        $uInit = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                    ?>
                    <tr>
                        <td class="text-muted small"><?= $u['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="user-avatar-circle" style="width: 34px; height: 34px; font-size: 0.78rem;">
                                    <?= e($uInit) ?>
                                </span>
                                <div>
                                    <div class="fw-bold text-dark"><?= e($uName) ?></div>
                                    <small class="text-muted font-monospace">@<?= e($u['username']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="role-pill <?= $u['role'] === 'admin' ? 'role-pill-admin' : 'role-pill-staff' ?>">
                                <?= e(strtoupper($u['role'])) ?>
                            </span>
                        </td>
                        <td><?= e($u['phone'] ?: '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">
                                <?= number_format($u['total_logged_transactions']) ?> Log
                            </span>
                        </td>
                        <td>
                            <small class="text-muted"><?= format_date($u['created_at']) ?></small>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-outline-primary">
                                    Edit
                                </a>
                                <?php if ($u['id'] !== current_user()['id']): ?>
                                <a href="delete.php?id=<?= $u['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus akun ini?');">
                                    Hapus
                                </a>
                                <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled title="Akun aktif Anda">
                                    Aktif
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
