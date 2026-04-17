<?php
require_once __DIR__ . '/core/Database.php';

try {
    $db = Database::connect();
    
    // 1. Agregar columna paid_amount si no existe
    $db->exec("ALTER TABLE appointments ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_amount");
    
    echo "✅ Migración técnica completada: Columna 'paid_amount' agregada correctamente.\n";
    
} catch (Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . "\n";
}
