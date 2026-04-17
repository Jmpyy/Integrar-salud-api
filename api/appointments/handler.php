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

require_auth();
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
        'isBlock'        => (bool)$row['is_block'],
        'color'          => $row['color_class'],
        'attendance'     => $row['attendance'],
        'paymentStatus'  => $row['payment_status'],
        'isPaid'         => (bool)$row['is_paid'],
        'paymentAmount'  => (float)$row['payment_amount'],
        'paidAmount'     => (float)($row['paid_amount'] ?? 0),
        'paymentMethod'  => $row['payment_method'],
        'notes'          => $row['notes'],
        'waitTicket'     => $row['wait_ticket'],
        'referrer'       => $row['referrer'],
    ];
}

// ─── GET LIST ───
if ($method === 'GET' && !$id) {
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo   = $_GET['dateTo'] ?? null;
    $doctorId = $_GET['doctorId'] ?? null;

    $sql = 'SELECT a.*, p.name as patient_name, p.phone as patient_phone, p.coverage as patient_coverage, p.coverage_number as patient_coverage_number
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE 1=1';
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
            'title'          => $row['title'],
            'date'           => $row['appointment_date'],
            'time'           => $row['appointment_time'],
            'duration'       => (float)$row['duration'],
            'type'           => $row['type'],
            'doctorId'       => (int)$row['doctor_id'],
            'patient'        => $row['patient_name'],
            'phone'          => $row['patient_phone'],
            'coverage'       => $row['patient_coverage'],
            'coverageNumber' => $row['patient_coverage_number'],
            'isBlock'        => (bool)$row['is_block'],
            'color'          => $row['color_class'],
            'attendance'     => $row['attendance'],
            'paymentStatus'  => $row['payment_status'],
            'isPaid'         => (bool)$row['is_paid'],
            'paymentAmount'  => $row['payment_amount'],
            'paymentMethod'  => $row['payment_method'],
            'notes'          => $row['notes'],
            'waitTicket'     => $row['wait_ticket'],
            'referrer'       => $row['referrer'],
        ];
    }, $rows);

    json_success(200, ['appointments' => $appointments]);
}

// ─── CREATE ───
if ($method === 'POST') {
    $patientId = !empty($body['patientId']) ? $body['patientId'] : null;

    $stmt = $db->prepare('INSERT INTO appointments (doctor_id, patient_id, title, appointment_date, appointment_time, duration, type, attendance, payment_status, is_paid, payment_amount, paid_amount, payment_method, is_block, notes, wait_ticket, referrer, color_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $body['doctorId'],
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
        $body['paymentMethod'] ?? null,
        $body['isBlock'] ? 1 : 0,
        $body['notes'] ?? null,
        $body['waitTicket'] ?? null,
        $body['referrer'] ?? null,
        $body['color'] ?? null,
    ]);
    $appId = (int)$db->lastInsertId();
    debug_log('Appointment created with ID', $appId);

    // If recurring, create additional appointments
    $weeks = $body['recurringWeeks'] ?? 0;
    $created = [$appId];

    if ($weeks > 0) {
        $stmt = $db->prepare('INSERT INTO appointments (doctor_id, patient_id, title, appointment_date, appointment_time, duration, type, attendance, payment_status, is_block, notes, wait_ticket, referrer, color_class, payment_amount, payment_method) VALUES (?, ?, ?, DATE_ADD(?, INTERVAL ? WEEK), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($i = 1; $i <= $weeks; $i++) {
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
                $body['paymentMethod'] ?? null,
            ]);
            $created[] = (int)$db->lastInsertId();
        }
    }

    // Fetch created with normalize_appointment and Patient JOIN
    $sqlFetch = 'SELECT a.*, p.name as patient_name, p.phone as patient_phone,
                        p.coverage as patient_coverage, p.coverage_number as patient_coverage_number
                 FROM appointments a
                 LEFT JOIN patients p ON a.patient_id = p.id
                 WHERE a.id = ?';

    if (count($created) > 1) {
        $placeholders = implode(',', array_fill(0, count($created), '?'));
        $sqlFetchMult = "SELECT a.*, p.name as patient_name, p.phone as patient_phone,
                                 p.coverage as patient_coverage, p.coverage_number as patient_coverage_number
                          FROM appointments a
                          LEFT JOIN patients p ON a.patient_id = p.id
                          WHERE a.id IN ($placeholders)
                          ORDER BY a.appointment_date";
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

    $stmt = $db->prepare('SELECT id FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        json_error(404, 'Turno no encontrado');
    }

    $stmt = $db->prepare('UPDATE appointments SET doctor_id=?, patient_id=?, title=?, appointment_date=?, appointment_time=?, duration=?, type=?, attendance=?, payment_status=?, is_paid=?, payment_amount=?, paid_amount=?, payment_method=?, is_block=?, notes=?, wait_ticket=?, referrer=?, color_class=? WHERE id=?');
    $stmt->execute([
        $body['doctorId'],
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
        $body['paymentMethod'] ?? null,
        !empty($body['isBlock']) ? 1 : 0,
        $body['notes'] ?? null,
        $body['waitTicket'] ?? null,
        $body['referrer'] ?? null,
        $body['color'] ?? null,
        $id,
    ]);

    $stmt = $db->prepare('
        SELECT a.*, p.name as patient_name, p.phone as patient_phone,
               p.coverage as patient_coverage, p.coverage_number as patient_coverage_number
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    json_success(200, ['appointment' => normalize_appointment($row)]);
}

// ─── DELETE ───
if ($method === 'DELETE') {
    if (!$id) {
        json_error(400, 'ID de turno requerido');
    }

    $stmt = $db->prepare('SELECT title FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();

    if (!$app) {
        json_error(404, 'Turno no encontrado');
    }

    $db->prepare('DELETE FROM appointments WHERE id = ?')->execute([$id]);

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

    $stmt = $db->prepare('
        SELECT a.*, p.name as patient_name, p.phone as patient_phone,
               p.coverage as patient_coverage, p.coverage_number as patient_coverage_number
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    json_success(200, ['appointment' => normalize_appointment($row)]);
}

// ─── PATCH payment ───
if ($method === 'PATCH' && isset($pathParts[1]) && $pathParts[1] === 'payment') {
    if (!$id) {
        json_error(400, 'ID de turno requerido');
    }

    $stmt = $db->prepare('UPDATE appointments SET payment_status=?, is_paid=?, payment_amount=?, paid_amount=?, payment_method=? WHERE id=?');
    $stmt->execute([
        $body['paymentStatus'] ?? 'pendiente',
        ($body['paymentStatus'] ?? '') === 'pagado' ? 1 : 0,
        $body['paymentAmount'] ?? 0,
        $body['paidAmount'] ?? ($body['paymentStatus'] === 'pagado' ? ($body['paymentAmount'] ?? 0) : 0),
        $body['paymentMethod'] ?? null,
        $id,
    ]);

    $stmt = $db->prepare('
        SELECT a.*, p.name as patient_name, p.phone as patient_phone,
               p.coverage as patient_coverage, p.coverage_number as patient_coverage_number
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    json_success(200, ['appointment' => normalize_appointment($row)]);
}

json_error(405, 'Method not allowed');
