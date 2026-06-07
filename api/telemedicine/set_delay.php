<?php
/**
 * POST /api/telemedicine/set_delay
 * Endpoint para que el doctor configure un mensaje de demora.
 * Requiere autenticación de doctor/admin.
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth(); // Solo personal autenticado puede poner demoras

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed');
}

$body = json_body();
$id = $body['id'] ?? null;
$delayMessage = $body['delayMessage'] ?? null; // Si es null o vacío, se borra la demora

if (!$id || !is_numeric($id)) {
    json_error(400, 'ID de turno requerido');
}

try {
    $db = Database::connect();

    // Verificamos que el turno exista
    $stmt = $db->prepare('SELECT id FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        json_error(404, 'Turno no encontrado');
    }

    $updateStmt = $db->prepare('UPDATE appointments SET delay_message = ? WHERE id = ?');
    $updateStmt->execute([$delayMessage, $id]);

    json_success(200, [
        'status' => 'success',
        'message' => 'Demora actualizada correctamente',
        'delayMessage' => $delayMessage
    ]);
} catch (Exception $e) {
    json_error(500, 'Error interno del servidor: ' . $e->getMessage());
}
