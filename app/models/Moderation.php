<?php
declare(strict_types=1);
namespace app\models;
use app\core\database\DBConnection;
use app\core\utils\{HttpError,Web};
final class Moderation {
    // Registro explícito para acrescentar novos tipos de conteúdo no futuro.
    public static function history(int $offset): array {
        return DBConnection::getConnection()->query('SELECT a.tipo,a.motivo,a.criadoEm,u.nomeUsuario,m.nomeUsuario AS moderador FROM acoesmoderacao a JOIN usuarios u ON u.idUsuario=a.idUsuario JOIN usuarios m ON m.idUsuario=a.idModerador ORDER BY a.idAcao DESC LIMIT 51 OFFSET '.max(0,$offset))->fetchAll();
    }
    public const TYPES=['foto'=>'foto de perfil','descricao'=>'descrição'];
    public static function remove(int $moderator,int $target,string $type,string $reason,string $expected): void {
        if(!isset(self::TYPES[$type])) throw new HttpError(422,'Tipo de conteúdo inválido.');
        if(mb_strlen($reason)<3 || mb_strlen($reason)>200) throw new HttpError(422,'Informe um motivo entre 3 e 200 caracteres.');
        $db=DBConnection::getConnection(); $db->beginTransaction();
        try {
            $s=$db->prepare("SELECT papel,ativacao FROM usuarios WHERE idUsuario=? FOR UPDATE"); $s->execute([$moderator]); $m=$s->fetch();
            if(!$m || $m['papel']!=='moderador' || $m['ativacao']!=='S') throw new HttpError(403,'Permissão de moderação necessária.');
            $s=$db->prepare('SELECT descricao FROM usuarios WHERE idUsuario=? FOR UPDATE'); $s->execute([$target]); $u=$s->fetch();
            if(!$u) throw new HttpError(404,'Perfil não encontrado.');
            if($type==='foto') {
                $s=$db->prepare('SELECT idFoto FROM fotosperfil WHERE idUsuario=? FOR UPDATE'); $s->execute([$target]); $photo=$s->fetch();
                if(!$photo || !hash_equals($expected,hash('sha256',(string)$photo['idFoto']))) throw new HttpError(409,'A foto mudou ou já foi removida. Revise o perfil.');
                // Remoção lógica: sem referência no banco, o arquivo deixa de ser acessível.
                // A manutenção CLI elimina arquivos órfãos após 24 horas.
                $s=$db->prepare('DELETE FROM fotosperfil WHERE idUsuario=?'); $s->execute([$target]);
            } else {
                if($u['descricao']==='' || !hash_equals($expected,hash('sha256',$u['descricao']))) throw new HttpError(409,'A descrição mudou ou já foi removida. Revise o perfil.');
                $s=$db->prepare("UPDATE usuarios SET descricao='' WHERE idUsuario=?"); $s->execute([$target]);
            }
            $s=$db->prepare('INSERT INTO acoesmoderacao (idModerador,idUsuario,tipo,motivo) VALUES (?,?,?,?)'); $s->execute([$moderator,$target,$type,$reason]);
            $db->commit();
        } catch(\Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
