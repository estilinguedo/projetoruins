<?php
declare(strict_types=1);
namespace app\core\utils;
use app\controllers\{ApiController,GameController};
use app\models\GameAccess;
final class Router {
    public static function dispatch(): array {
        $routes=json_decode(file_get_contents(__DIR__.'/../../etc/routes.json'),true,512,JSON_THROW_ON_ERROR);
        $path=trim((string)($_SERVER['PATH_INFO']??''),'/');
        if($path==='') {
            $uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
            $base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/');
            $path=trim(substr($uri,strlen($base)),'/');
            if(str_starts_with($path,'index.php/')) $path=substr($path,10);
        }
        $method=$_SERVER['REQUEST_METHOD'];$route=$routes[$method.' '.$path]??null;
        if(!$route) {
            $allowed=[];foreach($routes as $key=>$value) { [$m,$p]=explode(' ',$key,2);if($p===$path)$allowed[]=$m; }
            if($allowed) {header('Allow: '.implode(', ',$allowed));throw new HttpError(405,'Método não permitido.');}
            throw new HttpError(404,'Endpoint não encontrado.');
        }
        $game = ($route['controller'] ?? 'site') === 'game';
        if ($game) {
            $tls = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
            $local = Config::get('game.allow_local_http', false) === true
                && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
            if (!$tls && !$local) throw new HttpError(426, 'Use HTTPS para acessar o jogo.');
            // Game endpoints never authenticate through browser cookies.
            $user = $route['access'] === 'game' ? GameAccess::authenticate() : null;
        } else {
            $user = Web::user();
        }
        if($route['access']!=='public' && !$user) throw new HttpError(401,'Entre na sua conta para continuar.');
        if($route['access']==='moderator' && $user['papel']!=='moderador') throw new HttpError(403,'Permissão de moderação necessária.');
        if($method==='POST') {
            if (!$game) Web::checkCsrf();
            if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') throw new HttpError(415,'Envie application/json.');
            $raw=file_get_contents('php://input',false,null,0,7100001);
            if(strlen($raw)>7100000) throw new HttpError(413,'Requisição muito grande.');
            try { $object=json_decode($raw,false,16,JSON_THROW_ON_ERROR); } catch(\JsonException $e) {throw new HttpError(400,'JSON inválido.');}
            if(!is_object($object)) throw new HttpError(400,'Envie um objeto JSON.');
            Web::$input=(array)$object;
        }
        $controller = $game ? new GameController($user) : new ApiController($user);
        return $controller->{$route['action']}();
    }
}
