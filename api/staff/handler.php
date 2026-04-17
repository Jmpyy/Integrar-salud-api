<?php
/**
 * GET    /api/staff
 * POST   /api/staff
 * PUT    /api/staff/{id}
 * DELETE /api/staff/{id}
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$id = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;

// ─── GET ALL ───
if ($method === 'GET' && !$id) {
    $stmt = $db->query('
        SELECT s.*, u.id as user_id 
        FROM admin_staff s
        LEFT JOIN users u ON u.staff_id = s.id
        ORDER BY s.name');
    $staff = $stmt->fetchAll();
    json_success(200, ['staff' => $staff]);
}

// ─── CREATE ───
if ($method === 'POST') {
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

        // Crear usuario de autenticación para el personal
        $baseEmail = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $body['name'] ?? 'staff'));
        $baseEmail = $baseEmail ?: 'staff' . $staffId;
        $email = $baseEmail . '@integrarsalud.com';
        $password = 'password';
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $userRole = 'recepcion';

        // Si el email ya existe, agregar número
        $checkStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $counter = 1;
        while ($checkStmt->execute([$email]) && $checkStmt->fetch()) {
            $email = $baseEmail . $counter . '@integrarsalud.com';
            $counter++;
        }

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
        json_error(500, 'Error al crear empleado: ' . $e->getMessage());
    }
}

// ─── UPDATE ───
if ($method === 'PUT') {
    if (!$id) {
        json_error(400, 'ID requerido');
    }

    $stmt = $db->prepare('SELECT id FROM admin_staff WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
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

    $stmt = $db->prepare('SELECT * FROM admin_staff WHERE id = ?');
    $stmt->execute([$id]);
    $staff = $stmt->fetch();

    json_success(200, ['staff' => $staff]);
}

// ─── DELETE ───
if ($method === 'DELETE') {
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
