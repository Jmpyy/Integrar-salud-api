<?php
/**
 * GET /api/users - List all user accounts
 * POST /api/users - Create new Admin user
 * PUT /api/users/{id} - Update user (password/email)
 * DELETE /api/users/{id} - Revoke access
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_admin(); // Only admins can reach this handler
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$path = explode('/', trim($_GET['path'] ?? '', '/'));
$id = isset($path[0]) && is_numeric($path[0]) ? (int)$path[0] : null;

// --- LIST USERS ---
if ($method === 'GET' && !$id) {
    try {
        $query = "
            SELECT u.id, u.name, u.email, u.role, u.must_change_password, u.doctor_id, u.staff_id, u.created_at,
                   d.name as doctor_name, s.name as staff_name
            FROM users u
            LEFT JOIN doctors d ON u.doctor_id = d.id
            LEFT JOIN admin_staff s ON u.staff_id = s.id
            ORDER BY u.role, u.name
        ";
        $stmt = $db->query($query);
        $users = $stmt->fetchAll();
        json_success(200, ['users' => $users]);
    } catch (Exception $e) {
        error_log('Error al listar usuarios: ' . $e->getMessage());
        json_error(500, 'Error interno del servidor al listar usuarios.');
    }
}

// --- CREATE ADMIN ---
if ($method === 'POST') {
    $errors = validate_required($body, ['name', 'email', 'password']);
    if (!empty($errors)) {
        json_error(400, 'Datos incompletos', $errors);
    }

    if (strlen($body['password']) < 8 || !preg_match('/[A-Za-z]/', $body['password']) || !preg_match('/[0-9]/', $body['password'])) {
        json_error(400, 'La contraseña debe tener al menos 8 caracteres, incluir letras y números.');
    }

    try {
        $passwordHash = password_hash($body['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, must_change_password) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $body['name'],
            $body['email'],
            $passwordHash,
            'admin',
            1 // Admins created this way must change password on first login
        ]);
        
        json_success(201, ['message' => 'Usuario administrador creado correctamente']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            json_error(400, 'El email ya está registrado.');
        }
        error_log('Error al crear usuario: ' . $e->getMessage());
        json_error(500, 'Error interno del servidor al crear usuario.');
    }
}

// --- UPDATE USER (Password Reset or Email change) ---
if ($method === 'PUT' && $id) {
    try {
        if (isset($body['password'])) {
            if (strlen($body['password']) < 8 || !preg_match('/[A-Za-z]/', $body['password']) || !preg_match('/[0-9]/', $body['password'])) {
                json_error(400, 'La contraseña debe tener al menos 8 caracteres, incluir letras y números.');
            }
            $passwordHash = password_hash($body['password'], PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?');
            $stmt->execute([$passwordHash, $id]);
            json_success(200, ['message' => 'Contraseña restablecida correctamente. El usuario deberá cambiarla al ingresar.']);
        }
        
        if (isset($body['email'])) {
            $stmt = $db->prepare('UPDATE users SET email = ? WHERE id = ?');
            $stmt->execute([$body['email'], $id]);
            json_success(200, ['message' => 'Email actualizado correctamente.']);
        }
        
        json_error(400, 'No se proporcionaron campos para actualizar.');
    } catch (Exception $e) {
        error_log('Error al actualizar usuario: ' . $e->getMessage());
        json_error(500, 'Error interno del servidor al actualizar usuario.');
    }
}

// --- DELETE USER ---
if ($method === 'DELETE' && $id) {
    try {
        // Prevent deleting yourself (admin should not delete their own account from here)
        // This is a safety measure
        // El require_admin() ya se ejecutó arriba, usamos el payload del JWT global
        $currentUser = require_auth();
        if ($id === (int)$currentUser['sub']) {
            json_error(400, 'No puedes eliminar tu propio usuario administrador desde el panel.');
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        json_success(200, ['message' => 'Acceso revocado correctamente (usuario eliminado).']);
    } catch (Exception $e) {
        error_log('Error al eliminar usuario: ' . $e->getMessage());
        json_error(500, 'Error interno del servidor al eliminar usuario.');
    }
}

json_error(405, 'Método no permitido');
