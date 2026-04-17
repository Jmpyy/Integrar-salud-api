<?php
/**
 * GET /api/health
 * Health check endpoint para load balancers y uptime monitors.
 * Verifica conexión a la base de datos y devuelve estado del sistema.
 * NO requiere autenticación (es público).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

require_once __DIR__ . '/../core/Env.php';
Env::load();

$status = 'ok';
$checks = [];
$startTime = microtime(true);

// ─── Check: Database ──────────────────────────────────────
try {
    require_once __DIR__ . '/../core/Database.php';
    $db = Database::connect();
    $db->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (Exception $e) {
    $checks['database'] = 'error';
    $status = 'degraded';
}

// ─── Check: Uploads directory writable ───────────────────
$uploadsDir = __DIR__ . '/../uploads/';
$checks['uploads'] = (is_dir($uploadsDir) && is_writable($uploadsDir)) ? 'ok' : 'warning';

// ─── Check: JWT_SECRET configurado ───────────────────────
$checks['jwt_secret'] = Env::get('JWT_SECRET') ? 'ok' : (Env::isProduction() ? 'error' : 'warning_dev_key');
if ($checks['jwt_secret'] === 'error') $status = 'degraded';

$responseTime = round((microtime(true) - $startTime) * 1000, 2);

$httpCode = $status === 'ok' ? 200 : 503;
http_response_code($httpCode);

echo json_encode([
    'status'        => $status,
    'app'           => 'Integrar Salud API',
    'environment'   => Env::get('APP_ENV', 'development'),
    'timestamp'     => date('c'),
    'response_ms'   => $responseTime,
    'checks'        => $checks,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
