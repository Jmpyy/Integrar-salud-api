<?php
require_once __DIR__ . '/core/Database.php';

try {
    $db = Database::connect();
    
    // Check if columns exist first
    $stmt = $db->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('staff_id', $columns)) {
        $db->exec("ALTER TABLE users ADD COLUMN staff_id INT UNSIGNED NULL AFTER doctor_id");
        echo "Column staff_id added.\n";
    } else {
        echo "Column staff_id already exists.\n";
    }
    
    if (!in_array('must_change_password', $columns)) {
        $db->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) DEFAULT 1 AFTER role");
        echo "Column must_change_password added.\n";
    } else {
        echo "Column must_change_password already exists.\n";
    }
    
    echo "Migration completed successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
