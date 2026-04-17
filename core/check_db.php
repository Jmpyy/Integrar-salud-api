<?php
require_once __DIR__ . '/Database.php';
try {
    $db = Database::connect();
    $stmt = $db->query("SELECT * FROM appointments ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
