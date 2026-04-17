<?php
/**
 * GET  /api/notes
 * POST /api/notes
 */
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';

require_auth();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$body = json_body();

// ─── GET NOTE ───
if ($method === 'GET') {
    $stmt = $db->query('SELECT content FROM dashboard_notes LIMIT 1');
    $note = $stmt->fetch();
    
    json_success(200, ['content' => $note['content'] ?? '']);
}

// ─── UPDATE/CREATE NOTE ───
if ($method === 'POST') {
    $content = $body['content'] ?? '';
    
    // Check if exists
    $stmt = $db->query('SELECT id FROM dashboard_notes LIMIT 1');
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $db->prepare('UPDATE dashboard_notes SET content = ? WHERE id = ?');
        $stmt->execute([$content, $existing['id']]);
    } else {
        $stmt = $db->prepare('INSERT INTO dashboard_notes (content) VALUES (?)');
        $stmt->execute([$content]);
    }
    
    json_success(200, ['message' => 'Nota actualizada', 'content' => $content]);
}

json_error(405, 'Method not allowed');
