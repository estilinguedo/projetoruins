<?php
declare(strict_types=1);
namespace app\models;

use app\core\database\DBConnection;
use app\core\utils\HttpError;

final class GameAccess
{
    public static function create(array $user): array
    {
        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + 86400);
        $db = DBConnection::getConnection();
        $s = $db->prepare('INSERT INTO jogoacessos (tokenHash,idUsuario,authVersion,expiraEm) VALUES (?,?,?,?)');
        $s->execute([hash('sha256', $token), $user['idUsuario'], $user['authVersion'], $expires]);
        return ['token' => $token, 'expiresAt' => str_replace(' ', 'T', $expires).'Z'];
    }

    public static function bearer(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/iD', $header, $match)) {
            throw new HttpError(401, 'Envie o token do jogo no cabeçalho Authorization: Bearer.');
        }
        return $match[1];
    }

    public static function authenticate(): array
    {
        $s = DBConnection::getConnection()->prepare(
            "SELECT u.idUsuario,u.nomeUsuario,u.papel,u.authVersion FROM jogoacessos a
             JOIN usuarios u ON u.idUsuario=a.idUsuario
             WHERE a.tokenHash=? AND a.expiraEm>UTC_TIMESTAMP()
             AND a.authVersion=u.authVersion AND u.ativacao='S'"
        );
        $s->execute([hash('sha256', self::bearer())]);
        $user = $s->fetch();
        if (!$user) throw new HttpError(401, 'Acesso inválido ou expirado. Entre novamente no jogo.');
        return $user;
    }

    public static function revoke(): void
    {
        $s = DBConnection::getConnection()->prepare('DELETE FROM jogoacessos WHERE tokenHash=?');
        $s->execute([hash('sha256', self::bearer())]);
    }
}
