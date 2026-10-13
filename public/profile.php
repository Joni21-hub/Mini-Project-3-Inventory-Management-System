<?php
$pageTitle = 'Profil Pengguna';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/flash.php';

require_login();
$userId = current_user()['id'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $address  = trim($_POST['address'] ?? '');

            if ($fullName === '') {
                $errors[] = 'Nama lengkap wajib diisi.';
            }

            if (empty($errors)) {
                $checkStmt = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = :uid");
                $checkStmt->execute(['uid' => $userId]);
                $exists = $checkStmt->fetch();

                if ($exists) {
                    $stmt = $pdo->prepare("
                        UPDATE user_profiles 
                        SET full_name = :full_name, phone = :phone, address = :address
                        WHERE user_id = :uid
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_profiles (user_id, full_name, phone, address)
                        VALUES (:uid, :full_name, :phone, :address)
                    ");
                }

                $stmt->execute([
                    'uid'       => $userId,
                    'full_name' => $fullName,
                    'phone'     => $phone ?: null,
                    'address'   => $address ?: null,
                ]);

                $_SESSION['user']['full_name'] = $fullName;
                set_flash('success', 'Profil Anda berhasil diperbarui.');
                header("Location: profile.php");
                exit;
            }
        } elseif ($action === 'change_password') {
            $oldPass = $_POST['old_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            if (strlen($newPass) < 6) {
                $errors[] = 'Password baru minimal 6 karakter.';
            }
            if ($newPass !== $confirmPass) {
                $errors[] = 'Konfirmasi password baru tidak cocok.';
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                $user = $stmt->fetch();

                if ($user && password_verify($oldPass, $user['password_hash'])) {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                    $updateStmt->execute(['hash' => $newHash, 'id' => $userId]);

                    set_flash('success', 'Password berhasil diubah. Gunakan password baru saat login berikutnya.');
                    header("Location: profile.php");
                    exit;
                } else {
                    $errors[] = 'Password saat ini salah.';
                }
            }
        }
    }
}

// Ambil data profil terbaru
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role, u.created_at, up.full_name, up.phone, up.address
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE u.id = :id
");
$stmt->execute(['id' => $userId]);
$profileData = $stmt->fetch();

require_once __DIR__ . '/../views/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.02em;">Profil Pengguna</h3>
        <p class="text-muted small mb-0">Kelola informasi identitas pribadi dan keamanan autentikasi akun Anda.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
        <div class="fw-bold mb-1">Terdapat kesalahan:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Profil Info -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold text-dark">Informasi Akun (One-to-One)</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control bg-light" value="<?= e($profileData['username']) ?>" disabled>
                            <div class="form-text">Username unik permanen.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role Akses</label>
                            <input type="text" class="form-control bg-light" value="<?= e(strtoupper($profileData['role'])) ?>" disabled>
                            <div class="form-text">Dikelola oleh Administrator.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($profileData['full_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($profileData['phone'] ?? '') ?>" placeholder="Contoh: 08123456789">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Terdaftar Sejak</label>
                            <input type="text" class="form-control bg-light" value="<?= format_datetime($profileData['created_at']) ?>" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat Lengkap</label>
                            <textarea name="address" rows="3" class="form-control" placeholder="Alamat tinggal atau kantor"><?= e($profileData['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 text-end pt-2">
                            <button type="submit" class="btn btn-primary px-4">
                                Simpan Perubahan Profil
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Ubah Password -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <span class="fw-bold text-dark">Ubah Password Akun</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label">Password Saat Ini</label>
                        <input type="password" name="old_password" class="form-control" required placeholder="Masukkan password lama">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="Minimal 6 karakter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="Ulangi password baru">
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100 py-2 mt-2">
                        Perbarui Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
