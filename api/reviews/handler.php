<?php
/**
 * GET  /api/reviews          → Listar reseñas aprobadas (público, para landing)
 * GET  /api/reviews?all=1    → Listar todas las reseñas (requiere auth admin/medico)
 * POST /api/reviews          → Crear reseña (paciente tras consulta, sin auth)
 * PUT  /api/reviews/{id}     → Aprobar/rechazar/publicar reseña (solo admin)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

$db = Database::connect();

// Auto-crear tabla si no existe
$db->exec("
    CREATE TABLE IF NOT EXISTS reviews (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT UNSIGNED NOT NULL,
        patient_name VARCHAR(100) NOT NULL,
        doctor_id INT UNSIGNED DEFAULT NULL,
        doctor_name VARCHAR(100) DEFAULT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        comment TEXT DEFAULT NULL,
        approved TINYINT(1) DEFAULT 0,
        show_on_landing TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_doctor (doctor_id),
        INDEX idx_approved (approved),
        INDEX idx_appointment (appointment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$method = $_SERVER['REQUEST_METHOD'];

// Extraer el ID del PATH_INFO si viene (e.g. /reviews/5)
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$parts    = array_filter(explode('/', trim($pathInfo, '/')));
$reviewId = count($parts) >= 1 ? (int)end($parts) : null;

// ─── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $all = isset($_GET['all']) && $_GET['all'] == '1';

    if ($all) {
        // Requiere autenticación de admin o médico
        $user = require_roles(['admin', 'medico']);

        $stmt = $db->prepare('
            SELECT id, appointment_id, patient_name, doctor_id, doctor_name,
                   rating, comment, approved, show_on_landing, created_at
            FROM reviews
            ORDER BY created_at DESC
        ');
        $stmt->execute();
    } else {
        // Público: solo aprobadas y marcadas para landing
        $stmt = $db->prepare('
            SELECT id, doctor_name, rating, comment, created_at
            FROM reviews
            WHERE approved = 1 AND show_on_landing = 1
            ORDER BY created_at DESC
            LIMIT 50
        ');
        $stmt->execute();
    }

    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_success(200, ['data' => $reviews]);
    exit;
}

// ─── POST → crear reseña (sin auth, el paciente usa su código) ───────────────
if ($method === 'POST') {
    $body = json_body();

    $appointmentId = isset($body['appointment_id']) ? (int)$body['appointment_id'] : 0;
    $codigo        = isset($body['codigo'])         ? trim($body['codigo'])          : '';
    $rating        = isset($body['rating'])         ? (int)$body['rating']           : 0;
    $comment       = isset($body['comment'])        ? trim($body['comment'])         : null;

    if (!$appointmentId || !$codigo || $rating < 1 || $rating > 5) {
        json_error(400, 'Datos inválidos. Se requiere appointment_id, codigo y rating (1-5).');
    }
    if ($comment && strlen($comment) > 1000) {
        json_error(400, 'El comentario no puede superar los 1000 caracteres.');
    }

    // Verificar que el appointment es real y el código coincide
    $stmt = $db->prepare('
        SELECT a.id, a.doctor_id, d.name AS doctor_name, p.name AS patient_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors  d ON a.doctor_id  = d.id
        WHERE a.id = ? AND a.codigo_acceso = ? AND a.modalidad = "virtual"
        LIMIT 1
    ');
    $stmt->execute([$appointmentId, $codigo]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appt) {
        json_error(403, 'No se pudo verificar la consulta.');
    }

    // Evitar duplicados
    $dup = $db->prepare('SELECT id FROM reviews WHERE appointment_id = ? LIMIT 1');
    $dup->execute([$appointmentId]);
    if ($dup->fetch()) {
        json_error(409, 'Ya enviaste una reseña para esta consulta.');
    }

    $ins = $db->prepare('
        INSERT INTO reviews (appointment_id, patient_name, doctor_id, doctor_name, rating, comment, approved, show_on_landing)
        VALUES (?, ?, ?, ?, ?, ?, 0, 0)
    ');
    $ins->execute([
        $appt['id'],
        $appt['patient_name'],
        $appt['doctor_id'],
        $appt['doctor_name'],
        $rating,
        $comment ?: null,
    ]);

    json_success(201, ['message' => '¡Gracias por tu opinión! La revisaremos pronto.']);
    exit;
}

// ─── PUT → aprobar/rechazar/publicar (solo admin) ────────────────────────────
if ($method === 'PUT') {
    $user = require_roles(['admin']);

    if (!$reviewId) {
        json_error(400, 'Se requiere el ID de la reseña en la URL. Ej: /api/reviews/5');
    }

    $body          = json_body();
    $approved      = isset($body['approved'])      ? (int)$body['approved']      : null;
    $showOnLanding = isset($body['show_on_landing'])? (int)$body['show_on_landing'] : 0;

    if ($approved === null) {
        json_error(400, 'Se requiere el campo "approved".');
    }

    $stmt = $db->prepare('UPDATE reviews SET approved = ?, show_on_landing = ? WHERE id = ?');
    $stmt->execute([$approved, $showOnLanding, $reviewId]);

    if ($stmt->rowCount() === 0) {
        json_error(404, 'Reseña no encontrada.');
    }

    json_success(200, ['message' => 'Reseña actualizada.']);
    exit;
}

json_error(405, 'Método no permitido.');
