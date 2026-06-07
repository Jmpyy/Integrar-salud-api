<?php
/**
 * GET /api/settings - Leer config (Auth required)
 * GET /api/settings/public - Leer config pública (No auth)
 * POST /api/settings - Guardar config (Auth required)
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Si la ruta es "public", devolvemos solo la información básica para el Landing Page sin auth
if ($method === 'GET' && $path === 'public') {
    $stmt = $db->query('SELECT config_json FROM system_settings WHERE id = 1');
    $row = $stmt->fetch();
    
    $config = $row ? json_decode($row['config_json'], true) : [];
    
    // Devolvemos solo datos seguros
    $publicConfig = [
        'businessName' => $config['businessName'] ?? 'Integrar Salud',
        'address' => $config['address'] ?? 'San mateo 189, Turdera, Buenos Aires',
        'phone' => $config['phone'] ?? '+54 11 4427-6312',
        'email' => $config['email'] ?? 'ayuda.integrarsalud@gmail.com',
        'instagram' => $config['instagram'] ?? '',
        'facebook' => $config['facebook'] ?? '',
        'linkedin' => $config['linkedin'] ?? '',
        'hours' => $config['hours'] ?? [],
        'primaryColor' => $config['primaryColor'] ?? '#f43f5e', // rose-500 por defecto
        'logoUrl' => $config['logoUrl'] ?? '/pwa-192x192.png'
    ];
    
    json_success(200, $publicConfig);
    exit;
}

// Para todas las demás rutas (GET completo, POST), se requiere autenticación y rol de admin
require_auth();

if ($method === 'GET') {
    $stmt = $db->query('SELECT config_json FROM system_settings WHERE id = 1');
    $row = $stmt->fetch();
    
    $config = $row ? json_decode($row['config_json'], true) : [];
    json_success(200, ['config' => $config]);
}

if ($method === 'POST') {
    $body = json_body();
    $configJson = json_encode($body);
    
    $stmt = $db->prepare('UPDATE system_settings SET config_json = ? WHERE id = 1');
    $stmt->execute([$configJson]);
    
    json_success(200, ['message' => 'Configuración guardada en el servidor', 'config' => $body]);
}

json_error(405, 'Método no permitido');
