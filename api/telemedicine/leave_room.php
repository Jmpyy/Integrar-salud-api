<?php
// CORS ya se maneja en index.php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

try {
    $db = Database::connect();

    // Soportar POST JSON o GET id
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_body();
        $id = $body['id'] ?? null;
        $codigo = $body['codigo'] ?? null;
    } else {
        $id = $_GET['id'] ?? null;
        $codigo = $_GET['codigo'] ?? null;
    }

    if (!$id) {
        json_error(400, 'Falta el ID del turno.');
    }

    // Verificar el estado actual
    $stmt = $db->prepare('SELECT id, estado_videollamada, codigo_acceso FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();

    if (!$appointment || $appointment['codigo_acceso'] !== $codigo) {
        json_error(403, 'Acceso no autorizado.');
    }

    // Solo podemos salir si estábamos en espera. Si ya está activa o finalizada, no tocamos nada.
    if ($appointment['estado_videollamada'] === 'en_espera') {
        $updateStmt = $db->prepare('UPDATE appointments SET estado_videollamada = "pendiente", attendance = "agendado", wait_ticket = NULL WHERE id = ?');
        $updateStmt->execute([$id]);
    }

    json_success(200, ['status' => 'left_room']);
} catch (Exception $e) {
    error_log('Error en leave_room: ' . $e->getMessage());
    json_error(500, 'Error interno del servidor al salir de la sala de espera.');
}
