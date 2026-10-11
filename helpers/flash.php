<?php
/**
 * Helper: Flash Messages (Session Based)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['_flashes'])) {
        $_SESSION['_flashes'] = [];
    }
    $_SESSION['_flashes'][] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message,
    ];
}

function get_flashes(): array {
    $flashes = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return $flashes;
}
