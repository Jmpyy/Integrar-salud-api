<?php
/**
 * Environment Loader
 * Carga variables desde .env si existen y las expone via getenv()
 * Compatible con hosting compartido sin dependencias externas
 */
class Env {
    private static bool $loaded = false;

    public static function load(string $path = null): void {
        if (self::$loaded) return;

        $envFile = $path ?? __DIR__ . '/../.env';

        if (!file_exists($envFile)) {
            self::$loaded = true;
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Saltar comentarios y líneas vacías
            if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Quitar comillas opcionales
            if (preg_match('/^["\'](.*)[\'"]\s*$/', $value, $m)) {
                $value = $m[1];
            }

            // Solo setear si no está ya definida por el servidor (variables del servidor tienen prioridad)
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed {
        $value = getenv($key);
        return ($value !== false) ? $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool {
        $value = self::get($key);
        if ($value === null) return $default;
        return in_array(strtolower((string)$value), ['true', '1', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int {
        $value = self::get($key);
        return ($value !== null) ? (int)$value : $default;
    }

    public static function isProduction(): bool {
        return strtolower(self::get('APP_ENV', 'development')) === 'production';
    }
}
