<?php
/**
 * GET /api/patients           - List all (with optional ?search=)
 * GET /api/patients/{id}      - Get single with history + medications
 * POST /api/patients          - Create
 * PUT  /api/patients/{id}     - Update
 * DELETE /api/patients/{id}   - Delete
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validation.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

// Parse path
$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$id = isset($pathParts[0]) && is_numeric($pathParts[0]) ? (int)$pathParts[0] : null;

// ─── LIST (con paginación y búsqueda) ───
if ($method === 'GET' && !$id) {
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 25))); // máx 100 por página
    $offset = ($page - 1) * $limit;

    if ($search) {
        $term = "%$search%";
        // Total para paginación
        $countStmt = $db->prepare('SELECT COUNT(*) FROM patients WHERE name LIKE ? OR dni LIKE ? OR nhc LIKE ?');
        $countStmt->execute([$term, $term, $term]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare('SELECT * FROM patients WHERE name LIKE ? OR dni LIKE ? OR nhc LIKE ? ORDER BY name LIMIT ? OFFSET ?');
        $stmt->execute([$term, $term, $term, $limit, $offset]);
    } else {
        $countStmt = $db->query('SELECT COUNT(*) FROM patients');
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare('SELECT * FROM patients ORDER BY name LIMIT ? OFFSET ?');
        $stmt->execute([$limit, $offset]);
    }

    $patients = $stmt->fetchAll();
    json_success(200, [
        'patients' => $patients,
        'total'    => $total,
        'page'     => $page,
        'limit'    => $limit,
        'pages'    => (int)ceil($total / $limit),
    ]);
}

// ─── GET SINGLE ───
if ($method === 'GET' && $id) {
    $stmt = $db->prepare('SELECT * FROM patients WHERE id = ?');
    $stmt->execute([$id]);
    $patient = $stmt->fetch();

    if (!$patient) {
        json_error(404, 'Paciente no encontrado');
    }

    // History
    $stmt = $db->prepare('
        SELECT sh.*, d.name as doctor_name
        FROM soap_history sh
        JOIN doctors d ON sh.doctor_id = d.id
        WHERE sh.patient_id = ?
        ORDER BY sh.created_at DESC
    ');
    $stmt->execute([$id]);
    $history = $stmt->fetchAll();

    // Medications
    $stmt = $db->prepare('SELECT * FROM medications WHERE patient_id = ? AND active = 1 ORDER BY start_date DESC');
    $stmt->execute([$id]);
    $medications = $stmt->fetchAll();

    $patient['history'] = $history;
    $patient['medications'] = $medications;

    json_success(200, ['patient' => $patient]);
}

// ─── CREATE ───
if ($method === 'POST') {
    debug_log('POST /api/patients', $body);
    $errors = validate_required($body, ['name']);
    if (!empty($errors)) {
        json_error(400, 'Datos incompletos', $errors);
    }

    // Verificar si ya existe un paciente con el mismo DNI (evita duplicados)
    if (!empty($body['dni'])) {
        $stmtCheck = $db->prepare('SELECT * FROM patients WHERE dni = ?');
        $stmtCheck->execute([$body['dni']]);
        $existing = $stmtCheck->fetch();
        if ($existing) {
            // Ya existe: devolver el existente sin crear uno nuevo
            json_success(201, ['patient' => $existing]);
            exit;
        }
    }

    // Generar NHC secuencial si no viene (NHC-00001, NHC-00002, ...)
    if (!empty($body['nhc'])) {
        $nhc = $body['nhc'];
    } else {
        // Buscar el último número secuencial usado
        $stmtNhc = $db->query("SELECT nhc FROM patients WHERE nhc REGEXP '^NHC-[0-9]+$' ORDER BY CAST(SUBSTRING(nhc, 5) AS UNSIGNED) DESC LIMIT 1");
        $lastNhc = $stmtNhc->fetchColumn();
        if ($lastNhc) {
            $lastNum = (int) substr($lastNhc, 4); // quitar "NHC-"
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }
        $nhc = 'NHC-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    $stmt = $db->prepare('INSERT INTO patients (nhc, name, dni, birth_date, gender, phone, email, address, emergency_contact, coverage, coverage_number, plan, allergies, diagnosis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $nhc,
        sanitize_string($body['name'], 150),
        sanitize_dni($body['dni'] ?? null),
        sanitize_date($body['birthDate'] ?? null),
        sanitize_enum($body['gender'] ?? null, ['Masculino', 'Femenino', 'No binario', 'Prefiero no decir', 'Otro']),
        sanitize_phone($body['phone'] ?? null),
        sanitize_email($body['email'] ?? null),
        sanitize_string($body['address'] ?? null, 300),
        sanitize_string($body['emergencyContact'] ?? null, 200),
        sanitize_string($body['coverage'] ?? 'Particular', 100),
        sanitize_string($body['coverageNumber'] ?? null, 50),
        sanitize_string($body['plan'] ?? null, 100),
        sanitize_string($body['allergies'] ?? null, 500),
        sanitize_string($body['diagnosis'] ?? null, 500),
    ]);
    $patientId = (int)$db->lastInsertId();
    debug_log('Patient created with ID', $patientId);

    $stmt = $db->prepare('SELECT * FROM patients WHERE id = ?');
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();

    json_success(201, ['patient' => $patient]);
}

// ─── UPDATE ───
if ($method === 'PUT') {
    if (!$id) {
        json_error(400, 'ID de paciente requerido');
    }

    $stmt = $db->prepare('SELECT id FROM patients WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        json_error(404, 'Paciente no encontrado');
    }

    $stmt = $db->prepare('UPDATE patients SET name=?, dni=?, birth_date=?, gender=?, phone=?, email=?, address=?, emergency_contact=?, coverage=?, coverage_number=?, plan=?, allergies=?, diagnosis=? WHERE id=?');
    $stmt->execute([
        sanitize_string($body['name'], 150),
        sanitize_dni($body['dni'] ?? null),
        sanitize_date($body['birthDate'] ?? null),
        sanitize_enum($body['gender'] ?? null, ['Masculino', 'Femenino', 'No binario', 'Prefiero no decir', 'Otro']),
        sanitize_phone($body['phone'] ?? null),
        sanitize_email($body['email'] ?? null),
        sanitize_string($body['address'] ?? null, 300),
        sanitize_string($body['emergencyContact'] ?? null, 200),
        sanitize_string($body['coverage'] ?? 'Particular', 100),
        sanitize_string($body['coverageNumber'] ?? null, 50),
        sanitize_string($body['plan'] ?? null, 100),
        sanitize_string($body['allergies'] ?? null, 500),
        sanitize_string($body['diagnosis'] ?? null, 500),
        $id,
    ]);

    $stmt = $db->prepare('SELECT * FROM patients WHERE id = ?');
    $stmt->execute([$id]);
    $patient = $stmt->fetch();

    json_success(200, ['patient' => $patient]);
}

// ─── DELETE ───
if ($method === 'DELETE') {
    require_admin();
    if (!$id) {
        json_error(400, 'ID de paciente requerido');
    }

    $stmt = $db->prepare('SELECT name FROM patients WHERE id = ?');
    $stmt->execute([$id]);
    $patient = $stmt->fetch();

    if (!$patient) {
        json_error(404, 'Paciente no encontrado');
    }

    $db->prepare('DELETE FROM patients WHERE id = ?')->execute([$id]);

    json_success(200, ['message' => "Paciente {$patient['name']} eliminado"]);
}

json_error(405, 'Method not allowed');
