<?php
declare(strict_types=1);
// Mantém o namespace PSR-4 do professor. Composer acrescenta o PHPMailer para SMTP.
if (is_file(__DIR__ . '/vendor/autoload.php')) require_once __DIR__ . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'app\\')) {
        $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
        if (is_file($path)) require_once $path;
    }
});
