<?php
/**
 * Helper: Authentication & Authorization
 * Sesuai Slide 22, 23, 24:
 * - Session & Role check
 * - session_regenerate_id(true)
 * - Basic Authorization (Admin vs Staff)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';

function base_url(string $path = ''): string {
    // Menghitung root folder aplikasi secara dinamis
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    // Mencari posisi root project 'Mini-Project-3-Inventory-Management-System'
    $pos = strpos($scriptName, '/Mini-Project-3-Inventory-Management-System');
    if ($pos !== false) {
        $base = '/Mini-Project-3-Inventory-Management-System';
    } else {
        $base = '';
    }
    
    $path = ltrim($path, '/');
    return $base . ($path ? '/' . $path : '');
}

function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function current_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function is_admin(): bool {
    return current_role() === 'admin';
}

function is_staff(): bool {
    return current_role() === 'staff';
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        header('Location: ' . base_url('public/login.php'));
        exit;
    }
}

function require_role($roles): void {
    require_login();
    $allowed = is_array($roles) ? $roles : [$roles];
    $current = current_role();

    if (!in_array($current, $allowed, true)) {
        http_response_code(403);
        $pageTitle = "403 Akses Ditolak";
        // Tampilkan halaman forbidden
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Akses Dilarang</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        </head>
        <body class="bg-light d-flex align-items-center min-vh-100">
            <div class="container text-center">
                <div class="card shadow-sm border-0 mx-auto" style="max-width: 500px;">
                    <div class="card-body p-5">
                        <div class="text-danger mb-3">
                            <i class="bi bi-shield-slash" style="font-size: 4rem;"></i>
                        </div>
                        <h2 class="h4 fw-bold text-dark">403 - Akses Dilarang (Forbidden)</h2>
                        <p class="text-muted mt-2">
                            Maaf, akun Anda dengan role <strong><?= htmlspecialchars(strtoupper($current)) ?></strong> tidak memiliki izin untuk mengakses halaman ini. Halaman ini khusus untuk <strong><?= htmlspecialchars(strtoupper(implode(', ', $allowed))) ?></strong>.
                        </p>
                        <div class="mt-4">
                            <a href="<?= base_url('public/dashboard.php') ?>" class="btn btn-primary px-4">
                                <i class="bi bi-house-door me-1"></i> Kembali ke Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

function login_user(array $user, ?array $profile = null): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'role'      => $user['role'],
        'full_name' => $profile['full_name'] ?? $user['username'],
    ];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
