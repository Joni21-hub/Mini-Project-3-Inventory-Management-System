<?php
$pageTitle = 'Tambah Pengguna Baru';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$errors = [];
$username = '';
$fullName = '';
$role     = 'staff';
$phone    = '';
$address  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $username = strtolower(trim($_POST['username'] ?? ''));
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? 'staff');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    validate_string_required($username, 'Username', 50, $errors);
    validate_string_required($fullName, 'Nama Lengkap', 100, $errors);

    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }

    if (!in_array($role, ['admin', 'staff'], true)) {
        $errors[] = 'Role pengguna tidak valid.';
    }

    // Cek username unik
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :u");
        $check->execute(['u' => $username]);
        if ($check->fetch()) {
            $errors[] = "Username '{$username}' sudah terdaftar. Gunakan username lain.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            // 1. Simpan ke tabel users
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password_hash, role)
                VALUES (:u, :h, :r)
            ");
            $stmt->execute([
                'u' => $username,
                'h' => $hash,
                'r' => $role,
            ]);
            $newUserId = (int)$pdo->lastInsertId();

            // 2. Simpan ke tabel user_profiles (One-to-One Relation - Slide 6)
            $pStmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, full_name, phone, address)
                VALUES (:uid, :name, :phone, :address)
            ");
            $pStmt->execute([
                'uid'     => $newUserId,
                'name'    => $fullName,
                'phone'   => $phone ?: null,
                'address' => $address ?: null,
            ]);

            $pdo->commit();
            set_flash('success', "Pengguna baru '{$username}' ({$fullName}) berhasil dibuat dengan role " . strtoupper($role) . ".");
            header("Location: index.php");
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal mendaftarkan pengguna: ' . $e->getMessage();
        }
    }
}
}

require_once __DIR__ . '/../../views/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <div>
                <h3 class="fw-bold text-dark mb-0">Tambah Pengguna Baru</h3>
                <small class="text-muted">Buat kredensial login dan profil lengkap (One-to-One)</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i> Terjadi kesalahan:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="create.php">
                    <?= csrf_field() ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" value="<?= e($username) ?>" placeholder="Contoh: hendra" required autofocus>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($fullName) ?>" placeholder="Contoh: Hendra Saputra" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Role / Hak Akses <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff (Hanya kelola transaksi & produk)</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin (Akses penuh seluruh modul)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($phone) ?>" placeholder="Contoh: 081298765432">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small">Alamat Tempat Tinggal</label>
                            <textarea name="address" rows="3" class="form-control" placeholder="Alamat lengkap"><?= e($address) ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-person-check me-1"></i> Daftarkan Pengguna
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
