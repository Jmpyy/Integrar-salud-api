<?php
require_once __DIR__ . '/core/Database.php';
$db = Database::connect();
$stmt = $db->query("SELECT id, name, email, role, must_change_password FROM users");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
