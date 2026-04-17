<?php
/**
 * Rate Limiter — Protección contra brute force
 * Implementación simple sobre MySQL, sin dependencias externas.
 * Almacena intentos fallidos por IP en una tabla de la misma DB.
 */
class RateLimiter {
    private PDO $db;
    private int $maxAttempts;
    private int $lockoutMinutes;

    public function __construct(PDO $db) {
        $this->db = $db;
        require_once __DIR__ . '/Env.php';
        Env::load();
        $this->maxAttempts    = Env::int('LOGIN_MAX_ATTEMPTS', 5);
        $this->lockoutMinutes = Env::int('LOGIN_LOCKOUT_MINUTES', 15);
        $this->ensureTable();
    }

    /**
     * Verifica si la IP está bloqueada.
     * Si no está bloqueada, registra el intento fallido.
     * Retorna true si se debe bloquear la request.
     */
    public function isBlocked(string $ip, string $action = 'login'): bool {
        $this->cleanup();

        $stmt = $this->db->prepare(
            'SELECT attempts, blocked_until FROM rate_limit_attempts
             WHERE ip = ? AND action = ?'
        );
        $stmt->execute([$ip, $action]);
        $record = $stmt->fetch();

        if (!$record) return false;

        // Verificar si está en período de bloqueo activo
        if ($record['blocked_until'] && new DateTime() < new DateTime($record['blocked_until'])) {
            return true;
        }

        return false;
    }

    /**
     * Registra un intento fallido. Si supera el límite, bloquea la IP.
     */
    public function recordFailure(string $ip, string $action = 'login'): void {
        $stmt = $this->db->prepare(
            'INSERT INTO rate_limit_attempts (ip, action, attempts, last_attempt)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE
                attempts = IF(blocked_until IS NOT NULL AND blocked_until < NOW(), 1, attempts + 1),
                last_attempt = NOW(),
                blocked_until = IF(
                    IF(blocked_until IS NOT NULL AND blocked_until < NOW(), 1, attempts + 1) >= ?,
                    DATE_ADD(NOW(), INTERVAL ? MINUTE),
                    NULL
                )'
        );
        $stmt->execute([$ip, $action, $this->maxAttempts, $this->lockoutMinutes]);
    }

    /**
     * Resetea los intentos fallidos tras un login exitoso.
     */
    public function recordSuccess(string $ip, string $action = 'login'): void {
        $stmt = $this->db->prepare(
            'DELETE FROM rate_limit_attempts WHERE ip = ? AND action = ?'
        );
        $stmt->execute([$ip, $action]);
    }

    /**
     * Retorna los segundos restantes de bloqueo (0 si no está bloqueado).
     */
    public function getSecondsRemaining(string $ip, string $action = 'login'): int {
        $stmt = $this->db->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) as secs
             FROM rate_limit_attempts
             WHERE ip = ? AND action = ? AND blocked_until > NOW()'
        );
        $stmt->execute([$ip, $action]);
        $row = $stmt->fetch();
        return $row ? max(0, (int)$row['secs']) : 0;
    }

    private function cleanup(): void {
        // Limpiar registros expirados cada ~100 requests (aproximado)
        if (rand(1, 100) === 1) {
            $this->db->exec(
                "DELETE FROM rate_limit_attempts
                 WHERE (blocked_until IS NULL AND last_attempt < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                    OR (blocked_until IS NOT NULL AND blocked_until < DATE_SUB(NOW(), INTERVAL 1 DAY))"
            );
        }
    }

    private function ensureTable(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_attempts (
                ip           VARCHAR(45)  NOT NULL,
                action       VARCHAR(50)  NOT NULL DEFAULT 'login',
                attempts     INT UNSIGNED NOT NULL DEFAULT 0,
                last_attempt DATETIME     NOT NULL,
                blocked_until DATETIME    DEFAULT NULL,
                PRIMARY KEY (ip, action),
                INDEX idx_blocked (blocked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
