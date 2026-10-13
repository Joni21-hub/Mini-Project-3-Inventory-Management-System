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
        $error = 'Sesi form telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $stmt = $pdo->prepare("
                SELECT u.*, up.full_name, up.phone, up.address
                FROM users u
                LEFT JOIN user_profiles up ON up.user_id = u.id
                WHERE u.username = :username
                LIMIT 1
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

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
                $error = 'Kombinasi username atau password tidak sesuai.';
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
    <title>Masuk Sistem - InventarisHub Enterprise</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Premium Theme CSS -->
    <link href="<?= base_url('public/assets/css/premium-theme.css') ?>" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.25) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(14, 165, 233, 0.2) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 1) 0px, rgba(11, 15, 25, 1) 100%);
            padding: 1.5rem;
            margin: 0;
        }

        .auth-container {
            width: 100%;
            max-width: 980px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            display: flex;
            min-height: 600px;
            animation: fadeInUp 0.4s ease-out;
        }

        .auth-banner {
            flex: 1.1;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(30, 41, 59, 0.8) 100%),
                        url('<?= base_url('public/assets/images/hero-login.jpg') ?>') center/cover no-repeat;
            position: relative;
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #ffffff;
        }

        .auth-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.3) 0%, rgba(15, 23, 42, 0.95) 100%);
            pointer-events: none;
        }

        .auth-banner-content {
            position: relative;
            z-index: 2;
        }

        .auth-form-side {
            flex: 1;
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .quick-chip {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.65rem 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
        }

        .quick-chip:hover {
            border-color: #6366f1;
            background: #eef2ff;
            transform: translateY(-2px);
        }

        @media (max-width: 991.98px) {
            .auth-banner {
                display: none;
            }
            .auth-container {
                max-width: 480px;
            }
            .auth-form-side {
                padding: 2.5rem 2rem;
            }
        }
    </style>
</head>
<body>

<div class="auth-container">
    <!-- Kolom Visual Kiri (High Resolution Photography & Brand Story) -->
    <div class="auth-banner">
        <div class="auth-banner-content">
            <div class="d-inline-flex align-items-center gap-2 mb-4 px-3 py-1 rounded-pill bg-white bg-opacity-10 border border-white border-opacity-20 backdrop-blur">
                <span class="stock-dot" style="background-color: #38bdf8;"></span>
                <span class="small fw-semibold text-white">InventarisHub Platform v2.5</span>
            </div>
            <h2 class="display-6 fw-bold mb-3 text-white" style="letter-spacing:-0.03em;">
                Manajemen Logistik & Stok Berstandar Global
            </h2>
            <p class="text-white-50 leading-relaxed mb-0" style="font-size: 0.95rem;">
                Pantau ribuan SKU secara terintegrasi, audit mutasi atomik, dan kalkulasi valuasi persediaan dengan performa tingkat tinggi.
            </p>
        </div>

        <div class="auth-banner-content mt-5">
            <div class="row g-3">
                <div class="col-6">
                    <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-15">
                        <div class="h4 fw-bold text-white mb-0">100%</div>
                        <small class="text-white-50">Integritas ACID</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-15">
                        <div class="h4 fw-bold text-white mb-0">Real-Time</div>
                        <small class="text-white-50">Audit Mutasi</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Formulir Kanan -->
    <div class="auth-form-side">
        <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="brand-badge" style="width:34px; height:34px; font-size:1rem;">IH</div>
                <span class="fw-bold fs-5 text-dark">InventarisHub</span>
            </div>
            <h4 class="fw-bold text-dark mb-1" style="letter-spacing:-0.02em;">Masuk ke Portal</h4>
            <p class="text-muted small mb-0">Gunakan kredensial akun Anda untuk melanjutkan akses.</p>
        </div>

        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 small border-0 mb-3 rounded-3">
                <?= e($flash['message']) ?>
            </div>
        <?php endforeach; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small border-0 mb-3 rounded-3 d-flex align-items-center gap-2">
                <span class="stock-dot" style="background-color: #ef4444;"></span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" required autofocus placeholder="Masukkan username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan password">
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fs-6 mb-3">
                Masuk ke Sistem
            </button>
        </form>

        <!-- Akun Demo Cepat (1-Click Fill) -->
        <div class="mt-2 pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-semibold text-muted">Akun Uji Coba Cepat:</span>
                <span class="badge bg-light text-muted border">Klik untuk isi</span>
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <div class="quick-chip" onclick="fillAccount('admin', 'admin123')">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="role-pill role-pill-admin">Admin</span>
                        </div>
                        <div class="text-muted" style="font-size:0.75rem;">User: <strong>admin</strong></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="quick-chip" onclick="fillAccount('staff', 'staff123')">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="role-pill role-pill-staff">Staff</span>
                        </div>
                        <div class="text-muted" style="font-size:0.75rem;">User: <strong>staff</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillAccount(u, p) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = p;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
