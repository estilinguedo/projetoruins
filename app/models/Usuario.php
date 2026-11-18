<?php
declare(strict_types=1);
namespace app\models;
use app\core\database\DBQuery;
class Usuario extends DBQuery {
    public function __construct() { parent::__construct('usuarios','*','idUsuario'); }
    public function find(int $id): ?array { return $this->addCondition('AND','idUsuario','=',$id)->select()[0]??null; }
    public function byEmail(string $email): ?array { return $this->addCondition('AND','email','=',$email)->select()[0]??null; }
    public function publicProfile(int $id): ?array {
        $s=$this->conn->prepare("SELECT u.idUsuario,u.nomeUsuario,u.descricao,u.papel,f.idFoto FROM usuarios u LEFT JOIN fotosperfil f ON f.idUsuario=u.idUsuario WHERE u.idUsuario=? AND u.ativacao='S'");
        $s->execute([$id]); return $s->fetch() ?: null;
    }
    public function listActive(string $search,int $offset): array {
        $s=$this->conn->prepare("SELECT u.idUsuario,u.nomeUsuario,u.descricao,f.idFoto FROM usuarios u LEFT JOIN fotosperfil f ON f.idUsuario=u.idUsuario WHERE u.ativacao='S' AND u.nomeUsuario LIKE ? ORDER BY u.nomeUsuario LIMIT 51 OFFSET ".max(0,$offset));
        $s->execute(['%'.$search.'%']); return $s->fetchAll();
    }
}
