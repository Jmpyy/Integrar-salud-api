<?php
require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::connect();
    $db->exec("ALTER TABLE appointments ADD COLUMN delay_message VARCHAR(255) DEFAULT NULL;");
    echo "¡Columna 'delay_message' creada exitosamente en la tabla appointments!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "La columna 'delay_message' ya existe. Todo está bien.";
    } else {
        echo "Error al intentar crear la columna: " . $e->getMessage();
    }
}
