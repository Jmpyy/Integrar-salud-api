<?php
/**
 * POST /api/telemedicine/verify_access
 * Body: { dni: string, codigo: string }
 * Public endpoint para pacientes. Verifica DNI y Código de Acceso.
 * Si el turno es de otro día, devuelve 202 con la fecha/hora programada.
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed');
}

$body = json_body();
$dni = isset($body['dni']) ? trim($body['dni']) : '';
$codigo = isset($body['codigo']) ? trim($body['codigo']) : '';

if (!$dni || !$codigo) {
    json_error(400, 'DNI y Código de Acceso son requeridos');
}

$db = Database::connect();

require_once __DIR__ . '/../../core/RateLimiter.php';
$limiter = new RateLimiter($db);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if ($limiter->isBlocked($clientIp, 'telemedicine_verify')) {
    $secsLeft = $limiter->getSecondsRemaining($clientIp, 'telemedicine_verify');
    json_error(429, "Demasiados intentos. Intenta de nuevo en " . ceil($secsLeft / 60) . " minuto(s).");
}

// 1. Primero buscar el turno por DNI + Código sin restricción de fecha
$stmt = $db->prepare('
    SELECT a.id, a.estado_videollamada, a.appointment_date, a.appointment_time, a.payment_status, d.name as doctor_name, p.name as patient_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    WHERE p.dni = ? 
      AND a.codigo_acceso = ?
      AND a.modalidad = "virtual"
    ORDER BY a.appointment_date ASC
    LIMIT 1
');
$stmt->execute([$dni, $codigo]);
$appointment = $stmt->fetch();

if (!$appointment) {
    $limiter->recordFailure($clientIp, 'telemedicine_verify');
    json_error(404, 'Credenciales incorrectas. Verificá tu DNI y el Código de Acceso.');
}

if ($appointment['payment_status'] !== 'pagado') {
    json_error(402, 'La consulta requiere pago previo. Por favor, contactá con recepción para abonar la consulta; de otra manera, no podrás ingresar a la sala.');
}

// 2. Verificar si ya finalizó
// Si está finalizada, lo dejamos pasar igual para que el frontend lo mande directo a la pantalla de reseña (goodbye)

// 3. Verificar límite de tiempo de ingreso (15 minutos antes)
$today = date('Y-m-d');
$appointmentDate = $appointment['appointment_date'];
$appointmentTime = $appointment['appointment_time'];

$appointmentDateTime = strtotime("$appointmentDate $appointmentTime");
$now = time();
$minutesDifference = ($appointmentDateTime - $now) / 60;

$isCallActive = in_array($appointment['estado_videollamada'], ['activa', 'en_curso']);

// Bloquear si es de un día anterior, o si faltan más de 5 minutos (a menos que el médico ya haya iniciado la llamada)
if (!$isCallActive && ($appointmentDate < $today || $minutesDifference > 5)) {
    $fechaFormateada = date('d/m/Y', strtotime($appointmentDate));
    $horaFormateada  = date('H:i', strtotime($appointmentTime));
    
    $statusKey = 'early';

    if ($appointmentDate < $today) {
        $statusKey = 'past';
        $mensaje = 'Tu consulta virtual del ' . $fechaFormateada . ' a las ' . $horaFormateada . 'h ya pasó.';
    } else if ($appointmentDate === $today) {
        $mensaje = 'Aún falta para tu turno. Podés ingresar a la sala de espera 5 minutos antes de las ' . $horaFormateada . 'h.';
    } else {
        $mensaje = 'Tu turno está programado para el ' . $fechaFormateada . ' a las ' . $horaFormateada . 'h. Podés ingresar 5 minutos antes.';
    }

    // Limpiar intentos fallidos porque las credenciales son correctas (aunque sea temprano)
    $limiter->recordSuccess($clientIp, 'telemedicine_verify');

    // HTTP 202 = turno encontrado pero acceso no habilitado aún
    http_response_code(202);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $statusKey,
        'message' => $mensaje,
        'data' => [
            'appointmentDate' => $fechaFormateada,
            'appointmentTime' => $horaFormateada,
            'doctorName'      => $appointment['doctor_name'],
            'message'         => $mensaje
        ]
    ]);
    exit;
}

// 4. El turno es hoy — flujo normal
if ($appointment['estado_videollamada'] === 'pendiente') {
    $char = chr(65 + rand(0, 2));
    $num = rand(10, 99);
    $waitTicket = "$char-$num";

    $updateStmt = $db->prepare('UPDATE appointments SET estado_videollamada = "en_espera", attendance = "en_espera", wait_ticket = ? WHERE id = ?');
    $updateStmt->execute([$waitTicket, $appointment['id']]);
}

// Generar un token temporal para que el paciente pueda conectarse a los WebSockets de forma segura
require_once __DIR__ . '/../../core/JWT.php';
JWT::init();
$patientToken = JWT::encode([
    'sub'           => 'patient_' . $appointment['id'],
    'role'          => 'patient',
    'appointmentId' => $appointment['id'],
    'patientName'   => $appointment['patient_name'],
    'doctorName'    => $appointment['doctor_name']
]);

// Limpiar intentos fallidos tras ingreso exitoso
$limiter->recordSuccess($clientIp, 'telemedicine_verify');

json_success(200, [
    'appointmentId' => $appointment['id'],
    'doctorName'    => $appointment['doctor_name'],
    'patientName'   => $appointment['patient_name'],
    'status'        => $appointment['estado_videollamada'] === 'pendiente' ? 'en_espera' : $appointment['estado_videollamada'],
    'token'         => $patientToken
]);
