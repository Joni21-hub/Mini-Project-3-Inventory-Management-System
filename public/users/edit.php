<?php
$pageTitle = 'Edit Pengguna';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Pengguna tidak valid.');
    header("Location: index.php");
    exit;
}

// Ambil data user & user_profiles
$stmt = $pdo->prepare("
    SELECT u.*, up.full_name, up.phone, up.address
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE u.id = :id
");
$stmt->execute(['id' => $id]);
$userData = $stmt->fetch();

if (!$userData) {
    set_flash('danger', 'Pengguna tidak ditemukan.');
    header("Location: index.php");
    exit;
}

$errors = [];
$username = $userData['username'];
$fullName = $userData['full_name'];
$role     = $userData['role'];
$phone    = $userData['phone'];
$address  = $userData['address'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
    $role     = trim($_POST['role'] ?? 'staff');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    validate_string_required($fullName, 'Nama Lengkap', 100, $errors);

    if (!in_array($role, ['admin', 'staff'], true)) {
        $errors[] = 'Role pengguna tidak valid.';
    }

    // Mencegah admin mengubah role akun sendiri menjadi non-admin jika tidak ada admin lain
    if ($id === current_user()['id'] && $role !== 'admin') {
        $errors[] = 'Anda tidak dapat menurunkan role akun Anda sendiri saat sedang login.';
    }

    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'Jika ingin mengganti password, minimal 6 karakter.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Update tabel users
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $uStmt = $pdo->prepare("UPDATE users SET role = :role, password_hash = :hash WHERE id = :id");
                $uStmt->execute(['role' => $role, 'hash' => $hash, 'id' => $id]);
            } else {
                $uStmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
                $uStmt->execute(['role' => $role, 'id' => $id]);
            }

            // 2. Update atau Insert user_profiles (1:1)
            $checkProfile = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = :uid");
            $checkProfile->execute(['uid' => $id]);
            if ($checkProfile->fetch()) {
                $pStmt = $pdo->prepare("
                    UPDATE user_profiles 
                    SET full_name = :name, phone = :phone, address = :address
                    WHERE user_id = :uid
                ");
            } else {
                $pStmt = $pdo->prepare("
                    INSERT INTO user_profiles (user_id, full_name, phone, address)
                    VALUES (:uid, :name, :phone, :address)
                ");
            }
            $pStmt->execute([
                'name'    => $fullName,
                'phone'   => $phone ?: null,
                'address' => $address ?: null,
                'uid'     => $id,
            ]);

            // Jika mengedit akun sendiri, update session
            if ($id === current_user()['id']) {
                $_SESSION['user']['role'] = $role;
                $_SESSION['user']['full_name'] = $fullName;
            }

            $pdo->commit();
            set_flash('success', "Akun '{$username}' ({$fullName}) berhasil diperbarui.");
            header("Location: index.php");
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal memperbarui pengguna: ' . $e->getMessage();
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
                <h3 class="fw-bold text-dark mb-0">Edit Pengguna: @<?= e($userData['username']) ?></h3>
                <small class="text-muted">Perbarui data profil, otorisasi role, atau atur ulang password</small>
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
                <form method="POST" action="edit.php?id=<?= $id ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Username</label>
                            <input type="text" class="form-control bg-light" value="<?= e($userData['username']) ?>" disabled>
                            <div class="form-text">Username bersifat permanen.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Role / Hak Akses <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($fullName) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($phone) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small">Reset Password Baru (Kosongkan jika tidak diubah)</label>
                            <input type="password" name="password" class="form-control" placeholder="Isi hanya jika ingin mengganti password akun">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small">Alamat Tempat Tinggal</label>
                            <textarea name="address" rows="3" class="form-control"><?= e($address) ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
