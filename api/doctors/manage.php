<?php
/**
 * POST /api/doctors   - Create
 * PUT  /api/doctors/{id} - Update
 * DELETE /api/doctors/{id} - Delete
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

// Parse path for ID
$path = explode('/', trim($_GET['path'] ?? '', '/'));
$id = isset($path[0]) && is_numeric($path[0]) ? (int)$path[0] : null;

// ─── CREATE ───
if ($method === 'POST') {
    require_roles(['admin', 'administracion']);
    $errors = validate_required($body, ['name', 'specialty']);
    if (!empty($errors)) {
        json_error(400, 'Datos incompletos', $errors);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO doctors (name, specialty, license, color, phone, meet_link, remuneration, remuneration_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $body['name'],
            $body['specialty'],
            $body['license'] ?? null,
            $body['color'] ?? 'indigo',
            $body['phone'] ?? null,
            $body['meetLink'] ?? null,
            $body['remuneration'] ?? null,
            $body['remunerationType'] ?? 'fijo',
        ]);
        $doctorId = (int)$db->lastInsertId();

        // Usar email proporcionado o generar uno automáticamente
        if (!empty($body['email'])) {
            $email = trim(strtolower($body['email']));
            // Verificar que no esté en uso
            $checkStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $db->rollBack();
                json_error(409, 'El correo electrónico ya está registrado en el sistema.');
            }
        } else {
            // Autogenerar desde el nombre
            $baseEmail = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $body['name'] ?? 'doctor'));
            $baseEmail = $baseEmail ?: 'doctor' . $doctorId;
            $email = $baseEmail . '@integrarsalud.com';
            $checkStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $counter = 1;
            while ($checkStmt->execute([$email]) && $checkStmt->fetch()) {
                $email = $baseEmail . $counter . '@integrarsalud.com';
                $counter++;
            }
        }

        $password = bin2hex(random_bytes(8)); // 16 caracteres aleatorios seguros
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $role = 'medico';

        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, doctor_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$body['name'], $email, $passwordHash, $role, $doctorId, 1]);

        // Save schedule if provided
        if (!empty($body['schedule']) && is_array($body['schedule'])) {
            $stmt = $db->prepare('INSERT INTO doctor_schedules (doctor_id, day_of_week, start_hour, end_hour) VALUES (?, ?, ?, ?)');
            foreach ($body['schedule'] as $day => $hours) {
                $stmt->execute([$doctorId, (int)$day, (int)$hours['start'], (int)$hours['end']]);
            }
        }

        // Fetch created doctor
        $stmt = $db->prepare('SELECT * FROM doctors WHERE id = ?');
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();

        $db->commit();
        json_success(201, ['doctor' => $doctor, 'email' => $email, 'password' => $password]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Error creating doctor: ' . $e->getMessage());
        json_error(500, 'Error interno del servidor al crear doctor.');
    }
}

// ─── UPDATE ───
if ($method === 'PUT') {
    require_roles(['admin', 'administracion']);
    if (!$id) {
        json_error(400, 'ID de doctor requerido');
    }

    // Check exists
    $stmt = $db->prepare('SELECT id FROM doctors WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        json_error(404, 'Doctor no encontrado');
    }

    $stmt = $db->prepare('UPDATE doctors SET name=?, specialty=?, license=?, color=?, phone=?, meet_link=?, remuneration=?, remuneration_type=? WHERE id=?');
    $stmt->execute([
        $body['name'],
        $body['specialty'],
        $body['license'] ?? null,
        $body['color'] ?? 'indigo',
        $body['phone'] ?? null,
        $body['meetLink'] ?? null,
        $body['remuneration'] ?? null,
        $body['remunerationType'] ?? 'fijo',
        $id,
    ]);

    // Update schedule if provided
    if (!empty($body['schedule']) && is_array($body['schedule'])) {
        $db->prepare('DELETE FROM doctor_schedules WHERE doctor_id = ?')->execute([$id]);
        $stmt = $db->prepare('INSERT INTO doctor_schedules (doctor_id, day_of_week, start_hour, end_hour) VALUES (?, ?, ?, ?)');
        foreach ($body['schedule'] as $day => $hours) {
            $stmt->execute([$id, (int)$day, (int)$hours['start'], (int)$hours['end']]);
        }
    }

    $stmt = $db->prepare('SELECT * FROM doctors WHERE id = ?');
    $stmt->execute([$id]);
    $doctor = $stmt->fetch();

    json_success(200, ['doctor' => $doctor]);
}

// ─── DELETE ───
if ($method === 'DELETE') {
    require_roles(['admin', 'administracion']);
    if (!$id) {
        json_error(400, 'ID de doctor requerido');
    }

    $stmt = $db->prepare('SELECT name FROM doctors WHERE id = ?');
    $stmt->execute([$id]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        json_error(404, 'Doctor no encontrado');
    }

    $db->prepare('DELETE FROM doctors WHERE id = ?')->execute([$id]);

    json_success(200, ['message' => "Doctor {$doctor['name']} eliminado"]);
}

json_error(405, 'Method not allowed');
