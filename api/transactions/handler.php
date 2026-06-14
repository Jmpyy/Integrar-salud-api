<?php
/**
 * GET  /api/transactions?dateFrom=&dateTo=&type=
 * GET  /api/transactions/stats?dateFrom=&dateTo=
 * POST /api/transactions
 * GET  /api/transactions/export?format=csv
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validation.php';

$currentUser = require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$subRoute = $pathParts[0] ?? null;

// ─── GET LIST (Admin only) ───
if ($method === 'GET' && !$subRoute) {
    if (!in_array($currentUser['role'], ['admin', 'administracion'])) {
        json_error(403, 'Acceso restringido');
    }
    $dateFrom = sanitize_date($_GET['dateFrom'] ?? null);
    $dateTo   = sanitize_date($_GET['dateTo'] ?? null);
    $type     = sanitize_string($_GET['type'] ?? null);
    $doctorId = sanitize_int($_GET['doctor_id'] ?? null);
    $staffId  = sanitize_int($_GET['staff_id'] ?? null);

    $sql = 'SELECT * FROM transactions WHERE 1=1';
    $params = [];

    if ($dateFrom) {
        $sql .= ' AND transaction_date >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $sql .= ' AND transaction_date <= ?';
        // Si la fecha viene sin hora, le agregamos 23:59:59 para incluir todo el día
        $params[] = strlen($dateTo) === 10 ? $dateTo . ' 23:59:59' : $dateTo;
    }
    if ($type) {
        $sql .= ' AND type = ?';
        $params[] = $type;
    }
    if ($doctorId) {
        $sql .= ' AND doctor_id = ?';
        $params[] = $doctorId;
    }
    if ($staffId) {
        $sql .= ' AND staff_id = ?';
        $params[] = $staffId;
    }

    $sql .= ' ORDER BY transaction_date DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $transactions = array_map(function($row) {
        return [
            'id'      => (int)$row['id'],
            'date'    => $row['transaction_date'],
            'type'    => $row['type'],
            'concept' => $row['concept'],
            'method'  => $row['method'],
            'amount'  => (float)$row['amount'],
            'notes'   => $row['notes'],
            'doctor_id' => $row['doctor_id'] ? (int)$row['doctor_id'] : null,
            'staff_id'  => $row['staff_id'] ? (int)$row['staff_id'] : null,
            'patient_id' => $row['patient_id'] ? (int)$row['patient_id'] : null,
            'afip_cae'   => $row['afip_cae'],
            'afip_nro'   => $row['afip_nro'],
            'afip_punto_venta' => $row['afip_punto_venta'],
        ];
    }, $rows);

    json_success(200, ['transactions' => $transactions]);
}

// ─── STATS ───
if ($method === 'GET' && $subRoute === 'stats') {
    require_roles(['admin', 'administracion']);
    $dateFrom = sanitize_date($_GET['dateFrom'] ?? date('Y-m-01')) ?? date('Y-m-01');
    $dateTo   = sanitize_date($_GET['dateTo'] ?? date('Y-m-d')) ?? date('Y-m-d');

    $stmt = $db->prepare('
        SELECT
            COALESCE(SUM(CASE WHEN type = "Ingreso" THEN amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN type = "Egreso" THEN amount ELSE 0 END), 0) as total_expense,
            COUNT(*) as total_txs
        FROM transactions
        WHERE transaction_date BETWEEN ? AND ?
    ');
    $stmt->execute([$dateFrom, $dateTo . ' 23:59:59']);
    $stats = $stmt->fetch();

    $stats['net'] = (float)$stats['total_income'] - (float)$stats['total_expense'];

    // Method distribution
    $stmt = $db->prepare('
        SELECT method, COUNT(*) as count
        FROM transactions
        WHERE transaction_date BETWEEN ? AND ?
        GROUP BY method
    ');
    $stmt->execute([$dateFrom, $dateTo . ' 23:59:59']);
    $distribution = $stmt->fetchAll();

    $stats['distribution'] = $distribution;

    // Daily flow (last 6 days)
    $stmt = $db->prepare('
        SELECT DATE(transaction_date) as day,
               SUM(CASE WHEN type = "Ingreso" THEN amount ELSE 0 END) as income,
               SUM(CASE WHEN type = "Egreso" THEN amount ELSE 0 END) as expense
        FROM transactions
        WHERE transaction_date >= DATE_SUB(?, INTERVAL 6 DAY)
        GROUP BY DATE(transaction_date)
        ORDER BY day ASC
    ');
    $stmt->execute([date('Y-m-d')]);
    $stats['dailyFlow'] = $stmt->fetchAll();

    json_success(200, ['stats' => $stats]);
}

// ─── CREATE (admin, administracion o recepcionista — NO médicos) ───
if ($method === 'POST') {
    require_roles(['admin', 'administracion', 'recepcion', 'recepcionista']);
    // Validación flexible (amount puede llegar como int/float desde JS)
    $type    = !empty($body['type'])    ? $body['type']    : 'Ingreso';
    $concept = !empty($body['concept']) ? $body['concept'] : 'Pago desde Agenda';
    $method2 = !empty($body['method'])  ? $body['method']  : 'Efectivo';
    $amount  = isset($body['amount'])   ? (float)$body['amount'] : 0.0;

    if ($amount <= 0) {
        debug_log('Transaccion rechazada - monto inválido', ['amount' => $amount, 'body' => $body]);
        json_error(400, 'El monto debe ser mayor a 0');
    }

    $cleanDoctorId  = (isset($body['doctor_id'])  && is_numeric($body['doctor_id']))  ? (int)$body['doctor_id']  : null;
    $cleanStaffId   = (isset($body['staff_id'])   && is_numeric($body['staff_id']))   ? (int)$body['staff_id']   : null;
    $cleanPatientId = (isset($body['patient_id']) && is_numeric($body['patient_id'])) ? (int)$body['patient_id'] : null;
    $cleanDate = !empty($body['date']) ? $body['date'] : date('Y-m-d H:i:s');

    $stmt = $db->prepare('INSERT INTO transactions (type, concept, method, amount, receipt_number, notes, transaction_date, doctor_id, staff_id, patient_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $type,
        $concept,
        $method2,
        $amount,
        $body['receipt'] ?? null,
        $body['notes'] ?? null,
        $cleanDate,
        $cleanDoctorId,
        $cleanStaffId,
        $cleanPatientId
    ]);
    $txId = (int)$db->lastInsertId();

    if (!$txId) {
        debug_log('Error al insertar transacción', $body);
        json_error(500, 'Error al guardar la transacción en la base de datos');
    }

    $stmt = $db->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$txId]);
    $transaction = $stmt->fetch();

    json_success(201, ['transaction' => $transaction]);
}

// ─── EXPORT CSV (Admin only) ───
if ($method === 'GET' && $subRoute === 'export') {
    require_admin();
    $format = $_GET['format'] ?? 'csv';

    if ($format !== 'csv') {
        json_error(400, 'Formato no soportado. Use csv.');
    }

    $stmt = $db->query('SELECT id, transaction_date as date, type, concept, method, amount, notes FROM transactions ORDER BY transaction_date DESC');
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_transacciones_' . date('Y-m-d_H-i') . '.csv"');
    header('Cache-Control: max-age=0');
    
    $headers = ['ID', 'Fecha', 'Hora', 'Tipo', 'Concepto', 'Metodo', 'Monto'];
    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";

    fputcsv($output, $headers, ';');
    foreach ($rows as $row) {
        $dt = new DateTime($row['date']);
        fputcsv($output, [
            "TX-{$row['id']}",
            $dt->format('d/m/Y'),
            $dt->format('H:i'),
            $row['type'],
            $row['concept'],
            $row['method'],
            $row['type'] === 'Egreso' ? -($row['amount']) : $row['amount'],
        ], ';');
    }
    fclose($output);
    exit;
}

// ─── DELETE ───
if ($method === 'DELETE' && is_numeric($subRoute)) {
    require_admin();
    $id = (int)$subRoute;
    
    $stmt = $db->prepare('SELECT id FROM transactions WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        json_error(404, 'Transacción no encontrada');
    }

    $db->prepare('DELETE FROM transactions WHERE id = ?')->execute([$id]);
    json_success(200, ['message' => 'Transacción eliminada con éxito']);
}

// ─── UPDATE ───
if ($method === 'PUT' && is_numeric($subRoute)) {
    require_admin();
    $id = (int)$subRoute;
    
    $stmt = $db->prepare('UPDATE transactions SET type = ?, concept = ?, method = ?, amount = ?, transaction_date = ?, notes = ?, receipt_number = ? WHERE id = ?');
    $stmt->execute([
        $body['type'],
        $body['concept'],
        $body['method'],
        $body['amount'],
        $body['date'] ?? date('Y-m-d H:i:s'),
        $body['notes'] ?? null,
        $body['receipt'] ?? null,
        $id
    ]);
    
    json_success(200, ['message' => 'Transacción actualizada']);
}

json_error(405, 'Method not allowed');
