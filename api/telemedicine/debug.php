<?php
require_once __DIR__ . '/../../core/Database.php';
$db = Database::connect();
$id = $_GET['id'] ?? 1;
$stmt = $db->prepare('SELECT a.id, a.doctor_id, a.estado_videollamada, d.name as doctor_name, d.meet_link FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id ORDER BY a.id DESC LIMIT 5');
$stmt->execute();
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
