<?php
declare(strict_types=1);
namespace app\core\database;
use app\core\utils\Config;
use PDO;
final class DBConnection {
    private static ?PDO $instance = null;
    public static function getConnection(): PDO {
        if (!self::$instance) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', Config::get('database.host'), Config::get('database.port',3306), Config::get('database.dbname'));
            self::$instance = new PDO($dsn, Config::get('database.user'), Config::get('database.password'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
            self::$instance->exec("SET time_zone = '+00:00'");
        }
        return self::$instance;
    }
    public static function disconnect(): void { self::$instance = null; }
}
