<?php
/**
 * Integrar Salud - AFIP API Handler
 * GET  /api/afip/status
 * GET  /api/afip/config
 * POST /api/afip/config
 * POST /api/afip/upload-cert
 * POST /api/afip/emitir
 */
require_once __DIR__ . '/../../core/AfipManager.php';
require_once __DIR__ . '/../../core/Response.php';

require_admin(); // Solo admins gestionan AFIP
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$subRoute = $pathParts[0] ?? null;

// ——— STATUS ———
if ($method === 'GET' && $subRoute === 'status') {
    try {
        $status = AfipManager::getStatus();
        json_success(200, ['status' => $status]);
    } catch (Exception $e) {
        json_error(500, 'AFIP Offline o Error de Configuración', ['detail' => $e->getMessage()]);
    }
}

// ——— CONFIG ———
if ($method === 'GET' && $subRoute === 'config') {
    $stmt = $db->query('SELECT cuit, punto_venta, environment, tax_condition, cert_file, key_file FROM afip_config WHERE id = 1');
    $config = $stmt->fetch();
    
    // Ocultar nombres reales de archivos por seguridad, solo indicar si existen
    $config['has_cert'] = !empty($config['cert_file']);
    $config['has_key']  = !empty($config['key_file']);
    unset($config['cert_file'], $config['key_file']);
    
    json_success(200, ['config' => $config]);
}

if ($method === 'POST' && $subRoute === 'config') {
    $stmt = $db->prepare('UPDATE afip_config SET cuit = ?, punto_venta = ?, environment = ?, tax_condition = ? WHERE id = 1');
    $stmt->execute([
        $body['cuit'] ?? null,
        $body['punto_venta'] ?? 1,
        $body['environment'] ?? 'test',
        $body['tax_condition'] ?? 'monotributo'
    ]);
    json_success(200, 'Configuración actualizada');
}

// ——— UPLOAD CERTS ———
if ($method === 'POST' && $subRoute === 'upload-cert') {
    if (!isset($_FILES['file']) || !isset($body['type'])) {
        json_error(400, 'Faltan datos (file y type)');
    }

    $type = $body['type']; // 'cert' o 'key'
    $file = $_FILES['file'];
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Validaciones básicas
    if ($type === 'cert' && !in_array($extension, ['crt', 'pem'])) json_error(400, 'Extensión de certificado inválida (.crt)');
    if ($type === 'key' && !in_array($extension, ['key', 'pem'])) json_error(400, 'Extensión de llave inválida (.key)');

    $uploadDir = __DIR__ . '/../../certificates/';
    $newName = "afip_" . $type . "_" . time() . "." . $extension;
    $target = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        $column = ($type === 'cert') ? 'cert_file' : 'key_file';
        $stmt = $db->prepare("UPDATE afip_config SET $column = ? WHERE id = 1");
        $stmt->execute([$newName]);
        json_success(200, 'Archivo subido correctamente');
    } else {
        json_error(500, 'Error al mover el archivo');
    }
}

// ——— EMITIR FACTURA (Puede ser invocado por recepción) ———
if ($method === 'POST' && $subRoute === 'emitir') {
    require_auth(); // No necesita admin, pero sí estar logueado
    
    if (!isset($body['transaction_id'])) json_error(400, 'transaction_id requerido');
    
    $txId = $body['transaction_id'];
    
    // Buscar transacción
    $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$txId]);
    $tx = $stmt->fetch();
    
    if (!$tx) json_error(404, 'Transacción no encontrada');
    if ($tx['afip_cae']) json_error(400, 'Esta transacción ya fue facturada');

    // Buscar DNI del paciente si existe
    $docNro = 0;
    if ($tx['patient_id']) {
        $stmt = $db->prepare('SELECT dni FROM patients WHERE id = ?');
        $stmt->execute([$tx['patient_id']]);
        $p = $stmt->fetch();
        $docNro = $p['dni'] ?? 0;
    }

    try {
        // 1. Obtener Próximo Número
        $nextNumber = AfipManager::getNextNumber();
        
        // 2. Emitir
        $result = AfipManager::emitInvoice([
            'cbte_nro' => $nextNumber,
            'monto'    => (float)$tx['amount'],
            'doc_nro'  => $docNro,
            'doc_tipo' => ($docNro > 0 ? 96 : 99) // 96: DNI, 99: Consumidor Final
        ]);
        
        // 3. Persistir en DB
        $stmt = $db->prepare('UPDATE transactions SET afip_cae = ?, afip_cae_vence = ?, afip_nro = ?, afip_punto_venta = ? WHERE id = ?');
        $stmt->execute([
            $result['CAE'],
            $result['CAEFchVto'],
            $nextNumber,
            AfipManager::init()->ElectronicBilling->options['punto_venta'] ?? 1,
            $txId
        ]);
        
        json_success(200, [
            'cae'    => $result['CAE'],
            'vto'    => $result['CAEFchVto'],
            'numero' => $nextNumber
        ]);
        
    } catch (Exception $e) {
        json_error(500, 'Error de AFIP', ['detail' => $e->getMessage()]);
    }
}

json_error(405, 'Método no permitido');
