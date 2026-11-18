<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/autoload.php';
use app\core\utils\{Router,Web,HttpError};
ini_set('display_errors','0');
ini_set('session.use_strict_mode','1');
session_name('jogadores_session');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try { $response=['ok'=>true,'data'=>Router::dispatch()]; }
catch(HttpError $e) {http_response_code($e->status);$response=['ok'=>false,'message'=>$e->getMessage()];}
catch(Throwable $e) {error_log((string)$e);http_response_code(500);$response=['ok'=>false,'message'=>'Erro interno. Confira o banco, as permissões de storage e o log do Apache.'];}
$response['csrf']=Web::csrf();
echo json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
