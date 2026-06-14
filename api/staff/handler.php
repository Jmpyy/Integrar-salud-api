<?php
/**
 * GET    /api/staff
 * POST   /api/staff
 * PUT    /api/staff/{id}
 * DELETE /api/staff/{id}
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_roles(['admin', 'administracion', 'recepcion', 'recepcionista', 'medico', 'profesional']);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$id = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;

// ─── GET ALL ───
if ($method === 'GET' && !$id) {
    $stmt = $db->query('
        SELECT s.*, u.id as user_id, u.profile_picture
        FROM admin_staff s
        LEFT JOIN users u ON u.staff_id = s.id
        ORDER BY s.name');
    $staff = $stmt->fetchAll();
    json_success(200, ['staff' => $staff]);
}

// ─── CREATE ───
if ($method === 'POST') {
    require_admin();
    $errors = validate_required($body, ['name', 'role']);
    if (!empty($errors)) {
        json_error(400, 'Datos incompletos', $errors);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO admin_staff (name, role, shift, phone, remuneration, remuneration_type) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $body['name'],
            $body['role'],
            $body['shift'] ?? 'Mañana',
            $body['phone'] ?? null,
            $body['remuneration'] ?? null,
            $body['remunerationType'] ?? 'fijo',
        ]);
        $staffId = (int)$db->lastInsertId();

        // Usar email proporcionado o generar uno automáticamente
        if (!empty($body['email'])) {
            $email = trim(strtolower($body['email']));
            $checkStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $db->rollBack();
                json_error(409, 'El correo electrónico ya está registrado en el sistema.');
            }
        } else {
            $baseEmail = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $body['name'] ?? 'staff'));
            $baseEmail = $baseEmail ?: 'staff' . $staffId;
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
        $userRole = (strtolower($body['role']) === 'administración' || strtolower($body['role']) === 'administracion') ? 'administracion' : 'recepcionista';

        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, staff_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$body['name'], $email, $passwordHash, $userRole, $staffId, 1]);

        $db->commit();

        $stmt = $db->prepare('SELECT * FROM admin_staff WHERE id = ?');
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch();

        json_success(201, ['staff' => $staff, 'email' => $email, 'password' => $password]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Error creating staff: ' . $e->getMessage());
        json_error(500, 'Error interno del servidor al crear empleado.');
    }
}

// ─── UPDATE ───
if ($method === 'PUT') {
    require_admin();
    if (!$id) {
        json_error(400, 'ID requerido');
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT id FROM admin_staff WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $db->rollBack();
            json_error(404, 'Empleado no encontrado');
        }

        $stmt = $db->prepare('UPDATE admin_staff SET name=?, role=?, shift=?, phone=?, remuneration=?, remuneration_type=? WHERE id=?');
        $stmt->execute([
            $body['name'],
            $body['role'],
            $body['shift'] ?? 'Mañana',
            $body['phone'] ?? null,
            $body['remuneration'] ?? null,
            $body['remunerationType'] ?? 'fijo',
            $id,
        ]);

        // Actualizar el rol y el nombre también en la tabla `users` asociada para aplicar permisos reales.
        $userRole = (strtolower($body['role']) === 'administración' || strtolower($body['role']) === 'administracion') ? 'admin' : 'recepcionista';
        
        $stmt = $db->prepare('UPDATE users SET name=?, role=? WHERE staff_id=?');
        $stmt->execute([$body['name'], $userRole, $id]);

        $db->commit();

        $stmt = $db->prepare('SELECT * FROM admin_staff WHERE id = ?');
        $stmt->execute([$id]);
        $staff = $stmt->fetch();

        json_success(200, ['staff' => $staff]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Error updating staff: ' . $e->getMessage());
        json_error(500, 'Error interno del servidor al actualizar empleado.');
    }
}

// ─── DELETE ───
if ($method === 'DELETE') {
    require_admin();
    if (!$id) {
        json_error(400, 'ID requerido');
    }

    $stmt = $db->prepare('SELECT name FROM admin_staff WHERE id = ?');
    $stmt->execute([$id]);
    $person = $stmt->fetch();

    if (!$person) {
        json_error(404, 'Empleado no encontrado');
    }

    $db->prepare('DELETE FROM admin_staff WHERE id = ?')->execute([$id]);

    json_success(200, ['message' => "{$person['name']} eliminado"]);
}

json_error(405, 'Method not allowed');
