<?php
/**
 * GET    /api/appointments?dateFrom=&dateTo=&doctorId=
 * POST   /api/appointments
 * PUT    /api/appointments/{id}
 * DELETE /api/appointments/{id}
 * PATCH  /api/appointments/{id}/status
 * PATCH  /api/appointments/{id}/payment
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validation.php';

$currentUser = require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

// Parse path
$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$id = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;

/**
 * Normaliza una fila de DB al formato camelCase que espera el frontend.
 * Debe usarse en todas las respuestas de appointments (GET, PUT, PATCH).
 */
function normalize_appointment(array $row): array {
    return [
        'id'             => (int)$row['id'],
        'patientId'      => $row['patient_id'] ? (int)$row['patient_id'] : null,
        'title'          => $row['title'],
        'date'           => $row['appointment_date'],
        'time'           => $row['appointment_time'],
        'duration'       => (float)$row['duration'],
        'type'           => $row['type'],
        'doctorId'       => (int)$row['doctor_id'],
        'patient'        => $row['patient_name'] ?? null,
        'phone'          => $row['patient_phone'] ?? null,
        'coverage'       => $row['patient_coverage'] ?? null,
        'coverageNumber' => $row['patient_coverage_number'] ?? null,
        'dni'            => $row['patient_dni'] ?? null,
        'birthDate'      => $row['patient_birth_date'] ?? null,
        'gender'         => $row['patient_gender'] ?? 'femenino',
        'email'          => $row['patient_email'] ?? null,
        'address'        => $row['patient_address'] ?? null,
        'emergencyContact' => $row['patient_emergency_contact'] ?? null,
        'plan'           => $row['patient_plan'] ?? null,
        'isBlock'        => (bool)$row['is_block'],
        'color'          => $row['color_class'],
        'attendance'     => $row['attendance'],
        'paymentStatus'  => $row['payment_status'],
        'isPaid'         => (bool)$row['is_paid'],
        'paymentAmount'  => (float)$row['payment_amount'],
        'paidAmount'     => (float)($row['paid_amount'] ?? 0),
        'paidMethod'     => $row['paid_method'] ?? 'Efectivo',
        'paymentMethod'  => $row['payment_method'],
        'notes'          => $row['notes'],
        'waitTicket'     => $row['wait_ticket'],
        'referrer'       => $row['referrer'],
        'modalidad'      => $row['modalidad'] ?? 'presencial',
        'codigoAcceso'   => $row['codigo_acceso'] ?? null,
        'estadoVideollamada' => $row['estado_videollamada'] ?? 'pendiente',
        'meetLink'       => $row['doctor_meet_link'] ?? null,
    ];
}

$baseFetchSql = 'SELECT a.*, p.name as patient_name, p.phone as patient_phone, p.coverage as patient_coverage, p.coverage_number as patient_coverage_number, p.dni as patient_dni, p.birth_date as patient_birth_date, p.gender as patient_gender, p.email as patient_email, p.address as patient_address, p.emergency_contact as patient_emergency_contact, p.plan as patient_plan, d.meet_link as doctor_meet_link
                 FROM appointments a
                 LEFT JOIN patients p ON a.patient_id = p.id
                 LEFT JOIN doctors d ON a.doctor_id = d.id';


// ─── GET LIST ───
if ($method === 'GET' && !$id) {
    $dateFrom = sanitize_date($_GET['dateFrom'] ?? null);
    $dateTo   = sanitize_date($_GET['dateTo'] ?? null);
    $doctorId = sanitize_int($_GET['doctorId'] ?? null);

    // SECURITY: IDOR Prevention for Doctors
    if (($currentUser['role'] ?? '') === 'medico') {
        $doctorId = (int)($currentUser['doctor_id'] ?? 0);
    }

    $sql = $baseFetchSql . ' WHERE 1=1';
    $params = [];

    if ($dateFrom) {
        $sql .= ' AND a.appointment_date >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $sql .= ' AND a.appointment_date <= ?';
        $params[] = $dateTo;
    }
    if ($doctorId) {
        $sql .= ' AND a.doctor_id = ?';
        $params[] = $doctorId;
    }

    $sql .= ' ORDER BY a.appointment_date ASC, a.appointment_time ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Transform to frontend format
    $appointments = array_map(function($row) {
        return [
            'id'             => (int)$row['id'],
            'patientId'      => $row['patient_id'] ? (int)$row['patient_id'] : null,
            'title'          => $row['title'],
            'date'           => $row['appointment_date'],
            'time'           => $row['appointment_time'],
            'duration'       => (float)$row['duration'],
            'type'           => $row['type'],
            'doctorId'       => (int)$row['doctor_id'],
            'patient'        => $row['patient_name'],
            'dni'            => $row['patient_dni'] ?? null,
            'phone'          => $row['patient_phone'],
            'coverage'       => $row['patient_coverage'],
            'coverageNumber' => $row['patient_coverage_number'],
            'isBlock'        => (bool)$row['is_block'],
            'color'          => $row['color_class'],
            'attendance'     => $row['attendance'],
            'paymentStatus'  => $row['payment_status'],
            'isPaid'         => (bool)$row['is_paid'],
            'paymentAmount'  => $row['payment_amount'],
            'paidAmount'     => $row['paid_amount'] ?? 0,
            'paidMethod'     => $row['paid_method'] ?? 'Efectivo',
            'paymentMethod'  => $row['payment_method'],
            'notes'          => $row['notes'],
            'waitTicket'     => $row['wait_ticket'],
            'referrer'       => $row['referrer'],
            'modalidad'      => $row['modalidad'] ?? 'presencial',
            'codigoAcceso'   => $row['codigo_acceso'] ?? null,
            'estadoVideollamada' => $row['estado_videollamada'] ?? 'pendiente',
            'meetLink'       => $row['doctor_meet_link'] ?? null,
        ];
    }, $rows);

    json_success(200, ['appointments' => $appointments]);
}

// ─── CREATE ───
if ($method === 'POST') {
    $patientId = isset($body['patientId']) && $body['patientId'] !== '' ? (int)$body['patientId'] : null;
    $doctorId  = isset($body['doctorId'])  && $body['doctorId']  !== '' ? (int)$body['doctorId']  : null;
    $isBlock = !empty($body['isBlock']);

    // SECURITY: RBAC for Doctors
    if (($currentUser['role'] ?? '') === 'medico') {
        if (!$isBlock) {
            json_error(403, 'Acceso denegado: Los médicos solo pueden crear bloqueos de agenda, no turnos de pacientes.');
        }
        $doctorId = (int)($currentUser['doctor_id'] ?? 0);
    }
    
    $modalidad = $body['modalidad'] ?? 'presencial';
    $codigoAcceso = null;
    if ($modalidad === 'virtual') {
        $codigoAcceso = strtoupper(bin2hex(random_bytes(5))); // 10 caracteres criptográficamente seguros
    }

    $stmt = $db->prepare('INSERT INTO appointments (doctor_id, patient_id, title, appointment_date, appointment_time, duration, type, attendance, payment_status, is_paid, payment_amount, paid_amount, paid_method, payment_method, is_block, notes, wait_ticket, referrer, color_class, modalidad, codigo_acceso, estado_videollamada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $doctorId,
        $patientId,
        $body['title'],
        $body['date'],
        $body['time'],
        $body['duration'],
        $body['type'] ?? null,
        $body['attendance'] ?? 'agendado',
        $body['paymentStatus'] ?? 'pendiente',
        ($body['paymentStatus'] ?? '') === 'pagado' ? 1 : 0,
        $body['paymentAmount'] ?? 0,
        $body['paidAmount'] ?? ($body['paymentStatus'] === 'pagado' ? ($body['paymentAmount'] ?? 0) : 0),
        $body['paidMethod'] ?? ($body['paymentMethod'] ?? 'Efectivo'),
        $body['paymentMethod'] ?? null,
        $body['isBlock'] ? 1 : 0,
        $body['notes'] ?? null,
        $body['waitTicket'] ?? null,
        $body['referrer'] ?? null,
        $body['color'] ?? null,
        $modalidad,
        $codigoAcceso,
        'pendiente'
    ]);
    $appId = (int)$db->lastInsertId();
    debug_log('Appointment created with ID', $appId);

    // If recurring, create additional appointments
    $weeks = $body['recurringWeeks'] ?? 0;
    $created = [$appId];

    if ($weeks > 0) {
        $stmt = $db->prepare('INSERT INTO appointments (doctor_id, patient_id, title, appointment_date, appointment_time, duration, type, attendance, payment_status, is_block, notes, wait_ticket, referrer, color_class, payment_amount, paid_amount, paid_method, payment_method, modalidad, codigo_acceso, estado_videollamada) VALUES (?, ?, ?, DATE_ADD(?, INTERVAL ? WEEK), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($i = 1; $i <= $weeks; $i++) {
            $codigoAccesoRecurrente = null;
            if ($modalidad === 'virtual') {
                $codigoAccesoRecurrente = strtoupper(bin2hex(random_bytes(5)));
            }

            $stmt->execute([
                $body['doctorId'],
                $patientId,
                $body['title'],
                $body['date'],
                $i,
                $body['time'],
                $body['duration'],
                $body['type'] ?? null,
                $body['attendance'] ?? 'agendado',
                $body['paymentStatus'] ?? 'pendiente',
                !empty($body['isBlock']) ? 1 : 0,
                $body['notes'] ?? null,
                $body['waitTicket'] ?? null,
                $body['referrer'] ?? null,
                $body['color'] ?? null,
                $body['paymentAmount'] ?? 0,
                0, // paid_amount
                'Efectivo', // paid_method
                $body['paymentMethod'] ?? null,
                $modalidad,
                $codigoAccesoRecurrente,
                'pendiente'
            ]);
            $created[] = (int)$db->lastInsertId();
        }
    }

    // Fetch created with normalize_appointment and Patient JOIN
    $sqlFetch = $baseFetchSql . ' WHERE a.id = ?';

    if (count($created) > 1) {
        $placeholders = implode(',', array_fill(0, count($created), '?'));
        $sqlFetchMult = $baseFetchSql . " WHERE a.id IN ($placeholders) ORDER BY a.appointment_date";
        $stmt = $db->prepare($sqlFetchMult);
        $stmt->execute($created);
        $rows = $stmt->fetchAll();
        $appointments = array_map('normalize_appointment', $rows);
        json_success(201, ['appointments' => $appointments]);
    }

    $stmt = $db->prepare($sqlFetch);
    $stmt->execute([$appId]);
    $row = $stmt->fetch();

    json_success(201, ['appointment' => normalize_appointment($row)]);
}

// ─── PUT (update single) ───
if ($method === 'PUT') {
    if (!$id) {
        json_error(400, 'ID de turno requerido');
    }

    $patientId = !empty($body['patientId']) ? (int)$body['patientId'] : null;
    $doctorId  = !empty($body['doctorId'])  ? (int)$body['doctorId']  : null;

    $stmt = $db->prepare('SELECT id, doctor_id, is_block, modalidad, codigo_acceso, estado_videollamada FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        json_error(404, 'Turno no encontrado');
    }

    $isBlock = !empty($body['isBlock']);

    // SECURITY: RBAC for Doctors
    if (($currentUser['role'] ?? '') === 'medico') {
        if ((int)$existing['doctor_id'] !== (int)($currentUser['doctor_id'] ?? 0)) {
            json_error(403, 'Acceso denegado: No puedes modificar la agenda de otro profesional.');
        }
        if (!$existing['is_block'] || !$isBlock) {
            json_error(403, 'Acceso denegado: Los médicos solo pueden modificar bloques de agenda, no turnos de pacientes.');
        }
        $doctorId = (int)$existing['doctor_id'];
    }

    $modalidad = $body['modalidad'] ?? $existing['modalidad'];
    $codigoAcceso = $existing['codigo_acceso'];
    if ($modalidad === 'virtual' && !$codigoAcceso) {
        $codigoAcceso = strtoupper(bin2hex(random_bytes(5))); // 10 caracteres criptográficamente seguros
    }
    $estadoVideollamada = $body['estadoVideollamada'] ?? $existing['estado_videollamada'];

    $stmt = $db->prepare('UPDATE appointments SET doctor_id=?, patient_id=?, title=?, appointment_date=?, appointment_time=?, duration=?, type=?, attendance=?, payment_status=?, is_paid=?, payment_amount=?, paid_amount=?, paid_method=?, payment_method=?, is_block=?, notes=?, wait_ticket=?, referrer=?, color_class=?, modalidad=?, codigo_acceso=?, estado_videollamada=? WHERE id=?');
    $stmt->execute([
        $doctorId,
        $patientId,
        $body['title'],
        $body['date'],
        $body['time'],
        $body['duration'],
        $body['type'] ?? null,
        $body['attendance'] ?? 'agendado',
        $body['paymentStatus'] ?? 'pendiente',
        ($body['paymentStatus'] ?? '') === 'pagado' ? 1 : 0,
        $body['paymentAmount'] ?? 0,
        $body['paidAmount'] ?? ($body['paymentStatus'] === 'pagado' ? ($body['paymentAmount'] ?? 0) : 0),
        $body['paidMethod'] ?? ($body['paymentMethod'] ?? 'Efectivo'),
        $body['paymentMethod'] ?? null,
        !empty($body['isBlock']) ? 1 : 0,
        $body['notes'] ?? null,
        $body['waitTicket'] ?? null,
        $body['referrer'] ?? null,
        $body['color'] ?? null,
        $modalidad,
        $codigoAcceso,
        $estadoVideollamada,
        $id,
    ]);

    $stmt = $db->prepare($baseFetchSql . ' WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    json_success(200, ['appointment' => normalize_appointment($row)]);
}

// ─── DELETE ───
if ($method === 'DELETE') {
    if (!$id) {
        json_error(400, 'ID de turno requerido');
    }

    $stmt = $db->prepare('SELECT title, doctor_id, patient_id, is_block FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();

    if (!$app) {
        json_error(404, 'Turno no encontrado');
    }

    // SECURITY: RBAC for Doctors
    if (($currentUser['role'] ?? '') === 'medico') {
        if ((int)$app['doctor_id'] !== (int)($currentUser['doctor_id'] ?? 0)) {
            json_error(403, 'Acceso denegado: No puedes eliminar un turno de otro profesional.');
        }
        if (!$app['is_block']) {
            json_error(403, 'Acceso denegado: Los médicos solo pueden eliminar bloques de agenda, no turnos de pacientes.');
        }
    }
    
    // Fetch patient name if exists
    $patientName = '';
    if ($app['patient_id']) {
        $pStmt = $db->prepare('SELECT name FROM patients WHERE id = ?');
        $pStmt->execute([$app['patient_id']]);
        $p = $pStmt->fetch();
        if ($p) $patientName = $p['name'];
    }

    $db->prepare('DELETE FROM appointments WHERE id = ?')->execute([$id]);

    // -- Push Notification --
    require_once __DIR__ . '/../../libs/PushNotificationService.php';
    $push = new PushNotificationService($db);
    $push->notifyUser($app['doctor_id'], 'Turno Cancelado', "El turno " . ($patientName ? "de $patientName" : "'{$app['title']}'") . " fue eliminado/cancelado.");

    json_success(200, ['message' => "Turno '{$app['title']}' eliminado"]);
}

// ─── PATCH status ───
if ($method === 'PATCH' && isset($pathParts[1]) && $pathParts[1] === 'status') {
    if (!$id) {
        json_error(400, 'ID de turno requerido');
    }
    if (empty($body['attendance'])) {
        json_error(400, 'Estado requerido');
    }
    $allowedAttendance = ['agendado', 'confirmado', 'en_espera', 'en_curso', 'finalizado', 'ausente'];
    if (!in_array($body['attendance'], $allowedAttendance, true)) {
        json_error(400, 'Estado de asistencia inválido');
    }

    $update = ['attendance' => $body['attendance']];

    // Generate wait ticket if en_espera
    if ($body['attendance'] === 'en_espera') {
        $char = chr(65 + rand(0, 2));
        $num = rand(10, 99);
        $update['waitTicket'] = "$char-$num";
    }

    $fields = [];
    $params = [];
    foreach ($update as $key => $val) {
        $col = $key === 'attendance' ? 'attendance' : 'wait_ticket';
        $fields[] = "$col = ?";
        $params[] = $val;
    }
    $params[] = $id;

    $sql = 'UPDATE appointments SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $stmt = $db->prepare($baseFetchSql . ' WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    // -- Push Notifications --
    require_once __DIR__ . '/../../libs/PushNotificationService.php';
    $push = new PushNotificationService($db);
    $statusMsg = [
        'en_espera' => ['title' => 'Paciente en espera', 'body' => ($row['patient_name'] ?? 'Un paciente') . ' ya está en la sala de espera.'],
        'en_curso'  => ['title' => 'Paciente en consultorio', 'body' => 'Atención iniciada para ' . ($row['patient_name'] ?? 'un paciente') . '.'],
        'finalizado' => ['title' => 'Atención finalizada', 'body' => 'Consulta terminada.'],
        'ausente'   => ['title' => 'Paciente ausente/cancelado', 'body' => 'El turno de ' . ($row['patient_name'] ?? 'un paciente') . ' fue marcado como ausente.']
    ];
    if (isset($statusMsg[$body['attendance']])) {
        $push->notifyUser($row['doctor_id'], $statusMsg[$body['attendance']]['title'], $statusMsg[$body['attendance']]['body']);
    }

    json_success(200, ['appointment' => normalize_appointment($row)]);
}

// ─── PATCH video_status ───
if ($method === 'PATCH' && isset($pathParts[1]) && $pathParts[1] === 'video_status') {
    if (!$id) {
        json_error(400, 'ID de turno requerido');
    }
    if (empty($body['estado_videollamada'])) {
        json_error(400, 'Estado de videollamada requerido');
    }
    $allowedVideoStates = ['pendiente', 'en_espera', 'activa', 'finalizada'];
    if (!in_array($body['estado_videollamada'], $allowedVideoStates, true)) {
        json_error(400, 'Estado de videollamada inválido');
    }

    $stmt = $db->prepare('UPDATE appointments SET estado_videollamada = ? WHERE id = ?');
    $stmt->execute([$body['estado_videollamada'], $id]);

    // -- Audit Logs para Videollamadas --
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS call_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            doctor_id INT,
            patient_id INT,
            started_at DATETIME NOT NULL,
            ended_at DATETIME NULL,
            duration_seconds INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_appointment (appointment_id),
            INDEX idx_doctor (doctor_id)
        )");

        if ($body['estado_videollamada'] === 'activa') {
            $stmtLog = $db->prepare("INSERT INTO call_logs (appointment_id, doctor_id, patient_id, started_at) 
                SELECT ?, doctor_id, patient_id, NOW() FROM appointments WHERE id = ?
                AND NOT EXISTS (SELECT 1 FROM call_logs WHERE appointment_id = ? AND ended_at IS NULL)");
            $stmtLog->execute([$id, $id, $id]);
        } elseif ($body['estado_videollamada'] === 'finalizada') {
            $stmtLog = $db->prepare("UPDATE call_logs SET ended_at = NOW(), duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW()) WHERE appointment_id = ? AND ended_at IS NULL");
            $stmtLog->execute([$id]);
        }
    } catch (Exception $e) {
        // Ignoramos errores de log para no romper el flujo principal
        error_log("Error guardando call_log: " . $e->getMessage());
    }

    $stmt = $db->prepare($baseFetchSql . ' WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    json_success(200, ['appointment' => normalize_appointment($row)]);
}

// ─── PATCH payment ───
if ($method === 'PATCH' && isset($pathParts[1]) && $pathParts[1] === 'payment') {
    // SECURITY: RBAC for Doctors
    if (($currentUser['role'] ?? '') === 'medico') {
        json_error(403, 'Acceso denegado: Los médicos no pueden realizar cobros ni modificar el estado de pago.');
    }

    if (!$id) {
        json_error(400, 'ID de turno requerido');
    }

    // Obtener valores actuales para fallback o lógica
    $stmt = $db->prepare('SELECT payment_amount, paid_amount, payment_status FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    if (!$current) json_error(404, 'Turno no encontrado');

    $fields = [];
    $params = [];

    if (isset($body['paymentStatus'])) {
        $allowedPaymentStatus = ['pendiente', 'señado', 'pagado'];
        if (!in_array($body['paymentStatus'], $allowedPaymentStatus, true)) {
            json_error(400, 'Estado de pago inválido');
        }
        $fields[] = 'payment_status = ?';
        $params[] = $body['paymentStatus'];
        $fields[] = 'is_paid = ?';
        $params[] = ($body['paymentStatus'] === 'pagado' ? 1 : 0);
    }
    if (isset($body['paymentAmount'])) {
        $fields[] = 'payment_amount = ?';
        $params[] = $body['paymentAmount'];
    }
    if (isset($body['paidAmount'])) {
        $fields[] = 'paid_amount = ?';
        $params[] = $body['paidAmount'];
    }
    if (isset($body['paymentMethod'])) {
        $fields[] = 'payment_method = ?';
        $params[] = $body['paymentMethod'];
    }

    if (empty($fields)) {
        json_error(400, 'No hay campos para actualizar');
    }

    $params[] = $id;
    $sql = 'UPDATE appointments SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // NOTA: La creación de transacciones en Finanzas es responsabilidad exclusiva del frontend,
    // que calcula el monto exacto (saldo menos seña previa) y lo envía via POST /api/transactions.
    // No se auto-registra aquí para evitar duplicados.

    $stmt = $db->prepare($baseFetchSql . ' WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    json_success(200, ['appointment' => normalize_appointment($row)]);
}

json_error(405, 'Method not allowed');
