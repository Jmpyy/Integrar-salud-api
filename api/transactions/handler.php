<?php
/**
 * GET  /api/transactions?dateFrom=&dateTo=&type=
 * GET  /api/transactions/stats?dateFrom=&dateTo=
 * POST /api/transactions
 * GET  /api/transactions/export?format=csv
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$subRoute = $pathParts[0] ?? null;

// ─── GET LIST (Admin only) ───
if ($method === 'GET' && !$subRoute) {
    require_admin();
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo   = $_GET['dateTo'] ?? null;
    $type     = $_GET['type'] ?? null;

    $sql = 'SELECT * FROM transactions WHERE 1=1';
    $params = [];

    if ($dateFrom) {
        $sql .= ' AND transaction_date >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $sql .= ' AND transaction_date <= ?';
        $params[] = $dateTo;
    }
    if ($type) {
        $sql .= ' AND type = ?';
        $params[] = $type;
    }
    if (isset($_GET['doctor_id'])) {
        $sql .= ' AND doctor_id = ?';
        $params[] = $_GET['doctor_id'];
    }
    if (isset($_GET['staff_id'])) {
        $sql .= ' AND staff_id = ?';
        $params[] = $_GET['staff_id'];
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
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-01');
    $dateTo   = $_GET['dateTo'] ?? date('Y-m-d');

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

// ─── CREATE ───
if ($method === 'POST') {
    $errors = validate_required($body, ['type', 'concept', 'method', 'amount']);
    if (!empty($errors)) {
        json_error(400, 'Datos incompletos', $errors);
    }

    $stmt = $db->prepare('INSERT INTO transactions (type, concept, method, amount, receipt_number, notes, transaction_date, doctor_id, staff_id, patient_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $body['type'],
        $body['concept'],
        $body['method'],
        $body['amount'],
        $body['receipt'] ?? null,
        $body['notes'] ?? null,
        $body['date'] ?? date('Y-m-d H:i:s'),
        $body['doctor_id'] ?? null,
        $body['staff_id'] ?? null,
        $body['patient_id'] ?? null,
    ]);
    $txId = (int)$db->lastInsertId();

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

json_error(405, 'Method not allowed');
