<?php
/**
 * Helper: Input Validation & Sanitization
 * Sesuai Slide 21: Syntactic & Semantic Validation
 * Sesuai Slide 16: Allowlist Sorting Validation
 */

/**
 * Validasi allowlist untuk sorting column
 */
function validate_sort_column(string $column, array $allowed_columns, string $default = 'id'): string {
    return in_array($column, $allowed_columns, true) ? $column : $default;
}

/**
 * Validasi arah sort (ASC atau DESC)
 */
function validate_sort_direction(string $direction, string $default = 'ASC'): string {
    $dir = strtoupper(trim($direction));
    return in_array($dir, ['ASC', 'DESC'], true) ? $dir : $default;
}

/**
 * Validasi integer positif (> 0)
 */
function validate_positive_int($value, string $field_name, array &$errors): ?int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false || $filtered <= 0) {
        $errors[] = "Field {$field_name} harus berupa angka bulat lebih dari 0.";
        return null;
    }
    return (int)$filtered;
}

/**
 * Validasi non-negatif integer (>= 0)
 */
function validate_non_negative_int($value, string $field_name, array &$errors): ?int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false || $filtered < 0) {
        $errors[] = "Field {$field_name} tidak boleh bernilai negatif.";
        return null;
    }
    return (int)$filtered;
}

/**
 * Validasi nilai uang / desimal non-negatif
 */
function validate_currency_amount($value, string $field_name, array &$errors): ?float {
    if (!is_numeric($value)) {
        $errors[] = "Field {$field_name} harus berupa nominal angka valid.";
        return null;
    }
    $val = (float)$value;
    if ($val < 0) {
        $errors[] = "Field {$field_name} tidak boleh negatif.";
        return null;
    }
    return $val;
}

/**
 * Validasi string wajib diisi & batas panjang
 */
function validate_string_required(string $value, string $field_name, int $max_length = 255, array &$errors): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        $errors[] = "Field {$field_name} wajib diisi.";
    } elseif (mb_strlen($trimmed) > $max_length) {
        $errors[] = "Field {$field_name} maksimal {$max_length} karakter.";
    }
    return $trimmed;
}

/**
 * Validasi SKU Unik di database
 */
function validate_sku_unique(PDO $pdo, string $sku, ?int $ignore_id = null, array &$errors): bool {
    $sku = trim($sku);
    if ($sku === '') {
        $errors[] = "SKU produk wajib diisi.";
        return false;
    }
    
    $sql = "SELECT id FROM products WHERE sku = :sku";
    if ($ignore_id !== null) {
        $sql .= " AND id != :ignore_id";
    }
    
    $stmt = $pdo->prepare($sql);
    $params = ['sku' => $sku];
    if ($ignore_id !== null) {
        $params['ignore_id'] = $ignore_id;
    }
    
    $stmt->execute($params);
    if ($stmt->fetch()) {
        $errors[] = "SKU '{$sku}' sudah digunakan oleh produk lain. SKU harus unik.";
        return false;
    }
    return true;
}
