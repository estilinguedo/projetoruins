<?php
declare(strict_types=1);
namespace app\controllers;

use app\core\utils\{Web, HttpError, RateLimit};
use app\models\{Account, GameAccess, GameResult, Usuario, Leaderboard};

final class GameController
{
    public function __construct(private ?array $user) {}

    public function login(): array
    {
        RateLimit::check('game-login-ip:'.($_SERVER['REMOTE_ADDR'] ?? ''), 10);
        $email = strtolower(Web::input('email'));
        RateLimit::check('game-login-email:'.$email, 10);
        $user = Account::authenticate($email, Web::input('senha', false));
        return GameAccess::create($user) + ['usuario' => [
            'idUsuario' => (int)$user['idUsuario'], 'nomeUsuario' => $user['nomeUsuario']
        ]];
    }

    public function me(): array
    {
        $id = (int)$this->user['idUsuario'];
        return ['usuario' => (new Usuario())->publicProfile($id), 'ranking' => Leaderboard::forUser($id)];
    }

    public function result(): array
    {
        if (array_keys(Web::$input) !== ['pontuacao']) {
            throw new HttpError(422, 'Envie somente o campo pontuacao no JSON.');
        }
        RateLimit::check('game-score:'.$this->user['idUsuario'], 120);
        return GameResult::saveBest((int)$this->user['idUsuario'], Web::$input['pontuacao']);
    }

    public function logout(): array
    {
        GameAccess::revoke();
        return ['message' => 'Acesso do jogo encerrado.'];
    }
}
