<?php
/**
 * Helper: Cross-Site Request Forgery (CSRF) Protection
 * Menghasilkan dan memvalidasi token CSRF per session
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? '';
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        if (!$token || !hash_equals($sessionToken, $token)) {
            return false;
        }
    }
    return true;
}
