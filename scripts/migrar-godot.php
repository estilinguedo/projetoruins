<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/autoload.php';
try {
    $db = app\core\database\DBConnection::getConnection();
    $db->exec(file_get_contents(dirname(__DIR__).'/app/core/database/migrations/001-godot.sql'));
    echo "Integração Godot preparada. Dados existentes preservados.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Falha na migração: '.$e->getMessage()."\n"); exit(1);
}
