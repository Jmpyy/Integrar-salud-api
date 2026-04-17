<?php
/**
 * GET    /api/patients/{patientId}/files
 * POST   /api/patients/{patientId}/files
 * DELETE /api/patients/{patientId}/files/{fileId}
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];

// Parse path: {patientId}/files/{fileId}?
$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$patientId = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;
$fileId = isset($pathParts[2]) && is_numeric($pathParts[2]) ? (int)$pathParts[2] : null;

if (!$patientId) {
    json_error(400, 'ID de paciente requerido');
}

// ─── GET: LIST FILES ───
if ($method === 'GET') {
    $stmt = $db->prepare('SELECT id, file_name as name, file_type as type, file_size as size, uploaded_at as date 
                          FROM patient_files WHERE patient_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$patientId]);
    $files = $stmt->fetchAll();

    json_success(200, ['files' => $files]);
}

// ─── POST: UPLOAD FILE ───
if ($method === 'POST') {
    if (!isset($_FILES['file'])) {
        json_error(400, 'No se recibió ningún archivo');
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error(500, 'Error en la subida del archivo: ' . $file['error']);
    }

    // Configuración de almacenamiento y seguridad
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true); // Permisos más seguros
    }

    $fileName = basename($file['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // 1. Whitelist de extensiones
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
    if (!in_array($fileExt, $allowedExtensions)) {
        json_error(400, 'Tipo de archivo no permitido (.'.$fileExt.')');
    }

    // 2. Verificación de tipo MIME (seguridad adicional)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'
    ];

    if (!in_array($mimeType, $allowedMimes)) {
        json_error(400, 'El contenido del archivo no coincide con su extensión o no está permitido');
    }

    $uniqueName = 'p' . $patientId . '_' . time() . '_' . uniqid() . '.' . $fileExt;
    $targetPath = $uploadDir . $uniqueName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $stmt = $db->prepare('INSERT INTO patient_files (patient_id, file_name, file_path, file_type, file_size) 
                              VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $patientId,
            $fileName,
            $uniqueName,
            $mimeType, // Guardar el MIME real detectado
            $file['size']
        ]);
        $newId = $db->lastInsertId();

        json_success(201, [
            'file' => [
                'id' => (int)$newId,
                'name' => $fileName,
                'type' => $file['type'],
                'size' => $file['size'],
                'date' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        json_error(500, 'No se pudo guardar el archivo en el servidor');
    }
}

// ─── DELETE: REMOVE FILE ───
if ($method === 'DELETE') {
    if (!$fileId) {
        json_error(400, 'ID de archivo requerido');
    }

    // Buscar archivo para borrarlo del disco
    $stmt = $db->prepare('SELECT file_path FROM patient_files WHERE id = ? AND patient_id = ?');
    $stmt->execute([$fileId, $patientId]);
    $fileRecord = $stmt->fetch();

    if (!$fileRecord) {
        json_error(404, 'Archivo no encontrado');
    }

    $filePath = __DIR__ . '/../../uploads/' . $fileRecord['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    $stmt = $db->prepare('DELETE FROM patient_files WHERE id = ?');
    $stmt->execute([$fileId]);

    json_success(200, ['message' => 'Archivo eliminado correctamente']);
}

json_error(405, 'Método no permitido');
