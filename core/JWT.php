<?php
/**
 * JWT Helper
 * Implements encode, decode, verify using openssl (no external libs needed)
 * Compatible with HS256
 */
class JWT {
    private static string $secret;
    private static int $accessTtl  = 3600;   // 1 hora
    private static int $refreshTtl = 604800; // 7 días

    public static function init(): void {
        if (isset(self::$secret)) return;
        
        require_once __DIR__ . '/Env.php';
        Env::load();
        
        $envSecret = Env::get('JWT_SECRET');
        
        // Si hay una secret en el env, usarla (Prioridad Máxima)
        if ($envSecret) {
            self::$secret = $envSecret;
            return;
        }

        // En producción SI O SI debe haber una variable de entorno
        // Loguear detalles internamente sin exponerlos al cliente
        $host = $_SERVER['HTTP_HOST'] ?? 'unknown';
        $dir = __DIR__;
        $envFileExists = file_exists(__DIR__ . '/../.env') ? 'yes' : 'no';
        $getenv = getenv('JWT_SECRET');
        $_env = isset($_ENV['JWT_SECRET']) ? $_ENV['JWT_SECRET'] : 'not set';
        $_server = isset($_SERVER['JWT_SECRET']) ? $_SERVER['JWT_SECRET'] : 'not set';
        
        error_log("CRITICAL: JWT_SECRET no configurada. HOST: $host, DIR: $dir, envFileExists: $envFileExists, getenv: $getenv, _ENV: $_env, _SERVER: $_server");
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Error interno de configuración del servidor.']);
        exit;
    }

    /**
     * Encode payload to JWT string
     */
    public static function encode(array $payload, int $ttl = null): string {
        self::init();
        $now = time();
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload['iat'] = $now;
        $payload['exp'] = $now + ($ttl ?? self::$accessTtl);

        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", self::$secret, true)
        );

        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Decode and verify JWT. Returns payload or throws.
     */
    public static function decode(string $token): array {
        self::init();
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format', 401);
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        // Verify signature
        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::$secret, true)
        );
        if (!hash_equals($expectedSig, $signature)) {
            throw new Exception('Invalid token signature', 401);
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!$payload) {
            throw new Exception('Invalid token payload', 401);
        }

        // Check expiration
        if (isset($payload['exp']) && time() >= $payload['exp']) {
            throw new Exception('Token expired', 401);
        }

        return $payload;
    }

    public static function getRefreshTtl(): int {
        return self::$refreshTtl;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
