<?php
/**
 * Database Configuration
 * Lee credenciales desde variables de entorno (.env).
 * Fallback a valores locales de XAMPP si no están definidas.
 */
require_once __DIR__ . '/../core/Env.php';
Env::load();

return [
    'host'     => Env::get('DB_HOST', '127.0.0.1'),
    'port'     => Env::int('DB_PORT', 3306),
    'dbname'   => Env::get('DB_NAME', 'integrar_salud'),
    'username' => Env::get('DB_USER', 'root'),
    'password' => Env::get('DB_PASS', ''),
    'charset'  => 'utf8mb4',
];
