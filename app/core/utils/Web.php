<?php
declare(strict_types=1);
namespace app\core\utils;
use app\models\Usuario;
final class Web {
    public static array $input=[];
    public static function url(string $path=''): string { return rtrim((string)Config::get('app.url'),'/').'/'.ltrim($path,'/'); }
    public static function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
    public static function checkCsrf(): void {
        $token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if(!is_string($token) || !hash_equals(self::csrf(),$token)) throw new HttpError(403,'Formulário expirado. Recarregue a página e tente novamente.');
    }
    public static function input(string $name,bool $trim=true): string {
        $v=self::$input[$name]??'';
        if(!is_string($v)) throw new HttpError(422,'Formato de campo inválido.');
        if(!mb_check_encoding($v,'UTF-8')) throw new HttpError(422,'Texto inválido.');
        return $trim ? trim($v) : $v;
    }
    public static function id(mixed $value): int {
        if(!is_scalar($value) || !ctype_digit((string)$value) || (int)$value<=0) throw new HttpError(404,'Perfil não encontrado.');
        return (int)$value;
    }
    public static function user(): ?array {
        if(empty($_SESSION['idUsuario'])) return null;
        if(time()-(int)($_SESSION['last_activity']??0)>(int)Config::get('security.session_lifetime',7200)) { self::logout(); return null; }
        $u=(new Usuario())->find((int)$_SESSION['idUsuario']);
        if(!$u || $u['ativacao']!=='S' || (int)$u['authVersion']!==(int)($_SESSION['authVersion']??-1)) { self::logout(); return null; }
        $_SESSION['last_activity']=time(); return $u;
    }
    public static function login(array $u): void {
        session_regenerate_id(true); $_SESSION=[];
        $_SESSION['idUsuario']=(int)$u['idUsuario']; $_SESSION['authVersion']=(int)$u['authVersion']; $_SESSION['last_activity']=time();
    }
    public static function logout(): void { $_SESSION=[]; session_regenerate_id(true); }
    public static function storage(string $sub=''): string {
        $base=(string)Config::get('storage.path','storage');
        if(!preg_match('~^([A-Za-z]:[\\\\/]|/)~',$base)) $base=dirname(__DIR__,3).'/'.$base;
        $path=rtrim($base,'/\\').($sub!=='' ? '/'.$sub:'');
        if(!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) throw new \RuntimeException('Armazenamento indisponível.');
        return $path;
    }
}
