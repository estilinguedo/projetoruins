<?php
declare(strict_types=1);
namespace app\core\utils;
final class PasswordSecurity {
    private static function prepare(string $password): string {
        $pepper=(string)Config::get('security.password_pepper','');
        return $pepper!=='' ? hash_hmac('sha256',$password,$pepper) : hash('sha256',$password);
    }
    public static function hash(string $password): string { return password_hash(self::prepare($password),PASSWORD_BCRYPT,['cost'=>(int)Config::get('security.bcrypt_cost',12)]); }
    public static function verify(string $password,string $hash): bool { return password_verify(self::prepare($password),$hash); }
    public static function needsRehash(string $hash): bool { return password_needs_rehash($hash,PASSWORD_BCRYPT,['cost'=>(int)Config::get('security.bcrypt_cost',12)]); }
}
