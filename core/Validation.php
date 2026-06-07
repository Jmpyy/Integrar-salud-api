<?php
/**
 * Validation helpers — sanitización y validación de input del usuario
 * Centralizado para reutilizar en todos los handlers
 */

/**
 * Sanitiza un string: elimina espacios extremos y limita longitud máxima.
 * Retorna null si el valor es vacío.
 */
function sanitize_string(?string $value, int $maxLength = 255): ?string {
    if ($value === null) return null;
    $value = trim($value);
    if ($value === '') return null;
    return mb_substr($value, 0, $maxLength);
}

/**
 * Valida y normaliza un email. Retorna null si inválido.
 */
function sanitize_email(?string $value): ?string {
    if ($value === null || trim($value) === '') return null;
    $email = filter_var(trim($value), FILTER_VALIDATE_EMAIL);
    return $email !== false ? mb_strtolower(mb_substr($email, 0, 254)) : null;
}

/**
 * Valida y retorna una fecha en formato Y-m-d. Retorna null si inválida.
 */
function sanitize_date(?string $value): ?string {
    if ($value === null || trim($value) === '') return null;
    $value = trim($value);
    // Aceptar Y-m-d o d/m/Y
    $date = DateTime::createFromFormat('Y-m-d', $value)
         ?? DateTime::createFromFormat('d/m/Y', $value);
    if (!$date) return null;
    // Sanity check: año entre 1900 y 2100
    $year = (int)$date->format('Y');
    if ($year < 1900 || $year > 2100) return null;
    return $date->format('Y-m-d');
}

/**
 * Valida y retorna un número de teléfono limpio (solo dígitos, +, -, espacios).
 * Retorna null si inválido.
 */
function sanitize_phone(?string $value): ?string {
    if ($value === null || trim($value) === '') return null;
    $clean = preg_replace('/[^0-9+\-\s()]/', '', trim($value));
    $clean = trim($clean);
    if (strlen($clean) < 6 || strlen($clean) > 30) return null;
    return $clean;
}

/**
 * Valida y retorna un DNI/número de documento (solo alfanumérico).
 * Retorna null si inválido.
 */
function sanitize_dni(?string $value): ?string {
    if ($value === null || trim($value) === '') return null;
    $clean = preg_replace('/[^0-9A-Za-z\-]/', '', trim($value));
    if (strlen($clean) < 5 || strlen($clean) > 20) return null;
    return $clean;
}

/**
 * Sanitiza un ID numérico. Retorna null si inválido o no positivo.
 */
function sanitize_int($value): ?int {
    if ($value === null || $value === '') return null;
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false || $int <= 0) return null;
    return $int;
}

/**
 * Retorna un valor de un enum allowlist. Retorna $default si no es válido.
 */
function sanitize_enum(?string $value, array $allowed, string $default = ''): string {
    if ($value === null) return $default;
    return in_array($value, $allowed, true) ? $value : $default;
}
