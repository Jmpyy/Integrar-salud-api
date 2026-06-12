<?php
class Logger {
    /**
     * Escribe un log en la tabla system_logs
     *
     * @param PDO $db Instancia de la base de datos
     * @param string $level Nivel (INFO, WARN, ERROR, CRITICAL)
     * @param string $action Acción realizada (ej: LOGIN_SUCCESS, DELETE_PATIENT)
     * @param int|null $userId ID del usuario que realizó la acción
     * @param array $details Arreglo con datos adicionales, se guardará como JSON
     */
    public static function log($db, $level, $action, $userId = null, $details = []) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $jsonDetails = json_encode($details);

        try {
            $stmt = $db->prepare('INSERT INTO system_logs (level, action, user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$level, $action, $userId, $jsonDetails, $ip]);
        } catch (Exception $e) {
            // Fallback silencioso si la tabla no existe o hay error de DB
            error_log('Error escribiendo en system_logs: ' . $e->getMessage());
        }
    }

    public static function info($db, $action, $userId = null, $details = []) {
        self::log($db, 'INFO', $action, $userId, $details);
    }

    public static function warn($db, $action, $userId = null, $details = []) {
        self::log($db, 'WARN', $action, $userId, $details);
    }

    public static function error($db, $action, $userId = null, $details = []) {
        self::log($db, 'ERROR', $action, $userId, $details);
    }

    public static function critical($db, $action, $userId = null, $details = []) {
        self::log($db, 'CRITICAL', $action, $userId, $details);
    }
}
