<?php
/**
 * API Router
 * Routes requests to the correct endpoint file
 */

// Load environment variables first
require_once __DIR__ . '/core/Env.php';
Env::load();

// CORS Headers — whitelist desde .env
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOriginsRaw = Env::get('ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost,http://localhost:3000');
$allowed_origins = array_map('trim', explode(',', $allowedOriginsRaw));

// En producción nunca usar '*' — solo los orígenes explícitamente permitidos
$cors_origin = in_array($origin, $allowed_origins) ? $origin : '';

if ($cors_origin) {
    header("Access-Control-Allow-Origin: $cors_origin");
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
} elseif (!Env::isProduction()) {
    // Solo en dev local: permitir cualquier origen para facilitar el desarrollo
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load core files
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/JWT.php';
require_once __DIR__ . '/core/Response.php';

// Global debug log to verify request arrival
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? 'NONE';
debug_log("REQUEST received", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri'    => $_SERVER['REQUEST_URI'],
    'auth'   => (strpos($authHeader, 'Bearer ') === 0) ? 'Bearer [HIDDEN]' : $authHeader
]);

// Parse URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Para que funcione en cualquier subcarpeta (como /api-integrar/api/),
// simplemente buscamos la posición de "/api/" y tomamos lo que sigue.
if (strrpos($uri, '/api/') !== false) {
    $path = substr($uri, strrpos($uri, '/api/') + 5);
} else {
    // Si no tiene /api/, fallback al método dinámico previo pero mejorado
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    $scriptName = str_replace(['\\', 'api'], ['', ''], $scriptName); // Limpiar barras y subcarpeta api
    $path = (strpos($uri, $scriptName) === 0) ? substr($uri, strlen($scriptName)) : $uri;
}

$path = trim($path, '/');


// Split into segments: [resource, id?, subresource?, subid?]
$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;

// Route mapping
$routes = [
    'auth' => [
        'login'           => 'api/auth/login.php',
        'logout'          => 'api/auth/logout.php',
        'refresh'         => 'api/auth/refresh.php',
        'me'              => 'api/auth/me.php',
        'change-password' => 'api/auth/change-password.php',
        'update-profile'  => 'api/auth/update-profile.php',
    ],
    'doctors' => [
        'default' => 'api/doctors/handler.php',
        'manage'  => 'api/doctors/manage.php',
    ],
    'patients' => [
        'default' => 'api/patients/handler.php',
        'history' => 'api/patients/history.php',
        'medications' => 'api/patients/medications.php',
        'files' => 'api/patients/files.php'
    ],
    'appointments' => [
        'default' => 'api/appointments/handler.php',
    ],
    'staff' => [
        'default' => 'api/staff/handler.php',
    ],
    'users' => [
        'default' => 'api/users/handler.php',
    ],
    'transactions' => [
        'default' => 'api/transactions/handler.php',
    ],
    'notes' => [
        'default' => 'api/notes/handler.php',
    ],
    'afip' => [
        'default' => 'api/afip/handler.php',
    ],
];

// Set path info for endpoint files
$_GET['path'] = isset($segments[1]) ? implode('/', array_slice($segments, 1)) : '';

// Route dispatch
switch ($resource) {
    case 'auth':
        $action = $segments[1] ?? 'login';
        $file = $routes['auth'][$action] ?? null;
        break;

    case 'doctors':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // GET /doctors/ or GET /doctors/{id} → handler (read-only)
            $file = $routes['doctors']['default'];
        } else {
            // POST (sin ID) / PUT / DELETE (con ID) → manage
            $file = $routes['doctors']['manage'];
        }
        break;

    case 'patients':
        $subResource = $segments[2] ?? null;
        if ($subResource === 'history') {
            $file = $routes['patients']['history'];
        } elseif ($subResource === 'medications') {
            $file = $routes['patients']['medications'];
        } elseif ($subResource === 'files') {
            $file = $routes['patients']['files'];
        } else {
            $file = $routes['patients']['default'];
        }
        break;

    case 'appointments':
        $subResource = $segments[2] ?? null;
        $file = $routes['appointments']['default'];
        break;

    case 'staff':
        $file = $routes['staff']['default'];
        break;

    case 'users':
        $file = $routes['users']['default'];
        break;

    case 'transactions':
        $subResource = $segments[1] ?? null;
        $file = $routes['transactions']['default'];
        break;

    case 'notes':
        $file = $routes['notes']['default'];
        break;

    case 'afip':
        $file = $routes['afip']['default'];
        break;

    default:
        json_error(404, "Endpoint '$resource' no encontrado");
        exit;
}

if (!$file || !file_exists(__DIR__ . '/' . $file)) {
    json_error(404, 'Endpoint no encontrado');
    exit;
}

require __DIR__ . '/' . $file;
