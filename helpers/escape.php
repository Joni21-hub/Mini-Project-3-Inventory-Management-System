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
            return '<span class="stock-pill stock-pill-danger"><span class="stock-dot"></span>Habis (0)</span>';
        } elseif ($stock <= $minimum_stock) {
            return '<span class="stock-pill stock-pill-warning"><span class="stock-dot"></span>Menipis (' . $stock . ')</span>';
        } else {
            return '<span class="stock-pill stock-pill-success"><span class="stock-dot"></span>Tersedia (' . $stock . ')</span>';
        }
    }
}

if (!function_exists('product_image_url')) {
    function product_image_url(?string $sku, string $name = ''): string {
        $sku = trim((string)$sku);
        $localFile = __DIR__ . '/../public/assets/images/products/' . $sku . '.jpg';
        if ($sku !== '' && file_exists($localFile)) {
            return base_url('public/assets/images/products/' . $sku . '.jpg');
        }

        // Mapping cadangan berdasarkan kategori kata kunci atau fallback aman
        $lower = strtolower($name);
        if (strpos($lower, 'laptop') !== false || strpos($lower, 'thinkpad') !== false) {
            return base_url('public/assets/images/products/PRD-LPT-001.jpg');
        } elseif (strpos($lower, 'monitor') !== false) {
            return base_url('public/assets/images/products/PRD-MON-002.jpg');
        } elseif (strpos($lower, 'keyboard') !== false) {
            return base_url('public/assets/images/products/PRD-KBD-003.jpg');
        } elseif (strpos($lower, 'mouse') !== false) {
            return base_url('public/assets/images/products/PRD-MOU-004.jpg');
        } elseif (strpos($lower, 'router') !== false) {
            return base_url('public/assets/images/products/PRD-RTR-005.jpg');
        } elseif (strpos($lower, 'switch') !== false) {
            return base_url('public/assets/images/products/PRD-SWT-006.jpg');
        } elseif (strpos($lower, 'ssd') !== false || strpos($lower, 'storage') !== false) {
            return base_url('public/assets/images/products/PRD-SSD-007.jpg');
        } elseif (strpos($lower, 'webcam') !== false || strpos($lower, 'kamera') !== false) {
            return base_url('public/assets/images/products/PRD-WBC-009.jpg');
        } elseif (strpos($lower, 'tinta') !== false || strpos($lower, 'printer') !== false) {
            return base_url('public/assets/images/products/PRD-INK-010.jpg');
        }

        // Fallback foto studio hardware default
        return base_url('public/assets/images/products/PRD-LPT-001.jpg');
    }
}
