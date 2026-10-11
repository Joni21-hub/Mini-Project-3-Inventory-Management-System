<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/flash.php';
require_once __DIR__ . '/../helpers/csrf.php';

// Jika sudah login, langsung alihkan ke dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi!';
        } else {
            // Query user dengan Prepared Statement (Slide 18 & 23)
            $stmt = $pdo->prepare("
                SELECT u.*, up.full_name, up.phone, up.address
                FROM users u
                LEFT JOIN user_profiles up ON up.user_id = u.id
                WHERE u.username = :username
                LIMIT 1
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            // Verifikasi password hash (Slide 23)
            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user, [
                    'full_name' => $user['full_name'],
                    'phone'     => $user['phone'],
                    'address'   => $user['address']
                ]);

                set_flash('success', "Selamat datang kembali, " . ($user['full_name'] ?: $user['username']) . "! Anda masuk sebagai " . strtoupper($user['role']) . ".");
                
                $targetUrl = $_SESSION['intended_url'] ?? 'dashboard.php';
                unset($_SESSION['intended_url']);
                header("Location: " . $targetUrl);
                exit;
            } else {
                $error = 'Kombinasi username atau password salah!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - InventarisHub</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .login-header {
            background: #f8fafc;
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        .demo-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 60px; height: 60px;">
            <i class="bi bi-boxes fs-2"></i>
        </div>
        <h4 class="fw-bold mb-1 text-dark">InventarisHub</h4>
        <p class="text-muted small mb-0">Sistem Manajemen Persediaan Barang & Transaksi Mutasi</p>
    </div>

    <div class="p-4 p-sm-4">
        <!-- Notifikasi Flash -->
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 small border-0 mb-3">
                <?= e($flash['message']) ?>
            </div>
        <?php endforeach; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small border-0 mb-3 d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold small text-secondary">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" value="<?= e($username) ?>" required autofocus placeholder="Masukkan username">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold small text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" required placeholder="Masukkan password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
            </button>
        </form>

        <!-- Kredensial Demo untuk Kemudahan Evaluasi -->
        <div class="demo-box p-3 mt-4 text-muted">
            <div class="fw-bold text-dark mb-1 small"><i class="bi bi-info-circle me-1 text-primary"></i> Akun Uji Coba (Demo):</div>
            <div class="row g-2 mt-1">
                <div class="col-6">
                    <div class="p-2 bg-white rounded border">
                        <span class="badge bg-primary mb-1">Admin</span><br>
                        User: <code>admin</code><br>
                        Pass: <code>admin123</code>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 bg-white rounded border">
                        <span class="badge bg-success mb-1">Staff</span><br>
                        User: <code>staff</code><br>
                        Pass: <code>staff123</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
