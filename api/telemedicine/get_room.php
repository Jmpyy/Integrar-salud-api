<?php
/**
 * GET /api/telemedicine/get_room.php?id={appointment_id}&codigo={codigo}
 * Creates or fetches a Daily.co room URL for a specific appointment.
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Env.php';

Env::load();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error(405, 'Method not allowed');
}

$appointmentId = $_GET['id'] ?? null;
$codigo = $_GET['codigo'] ?? null;

if (!$appointmentId || !$codigo) {
    json_error(400, 'Faltan parámetros');
}

$db = Database::connect();

// Verificar que el turno existe y las credenciales coinciden
$stmt = $db->prepare('SELECT id, codigo_acceso, estado_videollamada FROM appointments WHERE id = ? AND codigo_acceso = ? LIMIT 1');
$stmt->execute([$appointmentId, $codigo]);
$appointment = $stmt->fetch();

if (!$appointment) {
    json_error(403, 'Acceso denegado a la sala');
}

$dailyApiKey = Env::get('DAILY_API_KEY');
if (!$dailyApiKey) {
    json_error(500, 'Daily.co API Key no configurada en el servidor');
}

$roomName = strtolower('integrarsalud-' . $appointmentId . '-' . substr($codigo, 0, 5));

// URL de la API de Daily
$url = 'https://api.daily.co/v1/rooms';

// Intento 1: Obtener la sala si ya existe
$ch = curl_init($url . '/' . $roomName);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $dailyApiKey
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['url'])) {
        json_success(200, ['url' => $data['url']]);
    }
}

// Intento 2: Crear la sala si no existe
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => $roomName,
    'privacy' => 'public', // Usamos public porque el control de acceso ya lo hacemos nosotros con PHP
    'properties' => [
        'enable_chat' => true,
        'enable_screenshare' => true,
        'lang' => 'es',
        'exp' => time() + (50 * 60) // Expira automáticamente en 50 minutos
    ]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $dailyApiKey,
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['url'])) {
        json_success(200, ['url' => $data['url']]);
    } else {
        json_error(500, 'Daily.co no devolvió la URL de la sala', ['response' => $data]);
    }
} else {
    $errorData = $response ? json_decode($response, true) : ['curl_error' => curl_error($ch)];
    json_error(500, 'Error al crear la sala en Daily.co', ['http_code' => $httpCode, 'details' => $errorData]);
}
