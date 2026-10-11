<?php
/**
 * Helper: Output Escaping & Formatting
 * Memastikan proteksi terhadap ancaman Cross-Site Scripting (XSS)
 * Sesuai Slide 20: htmlspecialchars($val, ENT_QUOTES, 'UTF-8')
 */

if (!function_exists('e')) {
    function e($value): string {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount): string {
        $num = (float)$amount;
        return 'Rp ' . number_format($num, 0, ',', '.');
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime): string {
        if (!$datetime) return '-';
        $time = strtotime($datetime);
        return date('d M Y H:i', $time);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $datetime): string {
        if (!$datetime) return '-';
        $time = strtotime($datetime);
        return date('d M Y', $time);
    }
}

if (!function_exists('stock_badge')) {
    function stock_badge(int $stock, int $minimum_stock): string {
        if ($stock <= 0) {
            return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Habis (0)</span>';
        } elseif ($stock <= $minimum_stock) {
            return '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Menipis (' . $stock . ')</span>';
        } else {
            return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Tersedia (' . $stock . ')</span>';
        }
    }
}
