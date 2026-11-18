<?php
declare(strict_types=1);
namespace app\core\utils;
final class Config {
    private static ?array $settings = null;
    public static function get(string $key, mixed $default = null): mixed {
        if (self::$settings === null) {
            // Override reservado ao processo do servidor/testes, nunca a parâmetros HTTP.
            $path = getenv('ZTF_CONFIG') ?: __DIR__ . '/../../etc/config.json';
            self::$settings = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }
        $value = self::$settings;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return $default;
            $value = $value[$part];
        }
        return $value;
    }
    public static function has(string $key): bool { return self::get($key) !== null; }
    public static function isEnabled(string $key): bool { return self::get($key.'.enabled', false) === true; }
    public static function reload(): void { self::$settings = null; }
}
