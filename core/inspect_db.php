<?php
require_once __DIR__ . '/Database.php';

function log_debug($msg) {
    echo $msg . PHP_EOL;
}

try {
    $config = require __DIR__ . '/../config/database.php';
    log_debug("Checking DB Connection to: " . $config['dbname']);
    
    $db = Database::connect();
    
    // Check tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    log_debug("Tables found: " . implode(', ', $tables));
    
    // Check patient count
    $stmt = $db->query("SELECT COUNT(*) FROM patients");
    $count = $stmt->fetchColumn();
    log_debug("Total patients: " . $count);
    
    // Check last 3 patients
    $stmt = $db->query("SELECT id, name, nhc, created_at FROM patients ORDER BY id DESC LIMIT 3");
    $pats = $stmt->fetchAll();
    log_debug("Last 3 patients: " . json_encode($pats));

    // Check last 3 appointments
    $stmt = $db->query("SELECT id, title, appointment_date, created_at FROM appointments ORDER BY id DESC LIMIT 3");
    $apps = $stmt->fetchAll();
    log_debug("Last 3 appointments: " . json_encode($apps));

} catch (Exception $e) {
    log_debug("CRITICAL ERROR: " . $e->getMessage());
}
