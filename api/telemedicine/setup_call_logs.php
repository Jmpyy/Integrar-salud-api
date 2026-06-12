<?php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS call_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        doctor_id INT,
        patient_id INT,
        started_at DATETIME NOT NULL,
        ended_at DATETIME NULL,
        duration_seconds INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_appointment (appointment_id),
        INDEX idx_doctor (doctor_id)
    )";
    
    $db->exec($sql);
    echo "Tabla call_logs creada correctamente.";
} catch (PDOException $e) {
    error_log("Error al crear la tabla call_logs: " . $e->getMessage());
    echo "Error interno al crear la tabla.";
}
