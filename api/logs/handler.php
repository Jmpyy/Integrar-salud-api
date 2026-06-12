<?php
/**
 * GET /api/logs
 * Endpoint protegido para obtener el historial de logs.
 * Solo accesible por usuarios con rol 'admin'.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

$user = require_auth();

// Verificación estricta de permisos
if ($user['role'] !== 'admin') {
    json_error(403, 'Acceso denegado. Se requiere rol de administrador.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error(405, 'Método no permitido');
}

try {
    $db = Database::connect();
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    if ($limit > 1000) $limit = 1000;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Obtener total
    $total = $db->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();
    
    // Obtener registros con info del usuario si existe
    $stmt = $db->prepare('
        SELECT l.*, u.name as user_name, u.email as user_email
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?
    ');
    
    // Bind parameters for LIMIT/OFFSET
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decodificar JSON de details
    foreach ($logs as &$log) {
        if ($log['details']) {
            $log['details'] = json_decode($log['details'], true);
        }
    }
    
    json_success(200, [
        'logs' => $logs,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (Exception $e) {
    error_log('Error en logs/handler.php: ' . $e->getMessage());
    json_error(500, 'Error al obtener los registros de auditoría.');
}
